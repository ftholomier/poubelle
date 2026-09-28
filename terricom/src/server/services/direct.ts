import { and, desc, eq, inArray, isNull, ne, sql } from 'drizzle-orm';
import type { TerritoryKind } from '@/lib/constants';
import { slugify } from '@/lib/slug';
import { audit } from '../audit';
import { shortCode } from '../crypto';
import { db } from '../db';
import {
  categories,
  communeMemberships,
  communes,
  companies,
  companyMembers,
  companySubscriptions,
  deals,
  establishments,
  territories,
  users,
} from '../db/schema';
import { communeByInsee, geocode, isValidSiret, type SireneEstablishment } from '../integrations/public-data';
import { logger } from '../logger';
import { renderEmail } from '../mail/layout';
import { sendEmail } from '../mail/send';
import { enqueue } from '../queue';
import { appUrl } from '../urls';
import { ClaimError } from './claims';
import { addDealTask, logDealActivity, STAGE_PROBABILITY } from './crm';
import { refreshCompleteness, refreshSearchKeywords } from './establishments';
import { updateStripeSubscriptionPrice } from './stripe';
import { ensureNationalTerritory, NATIONAL_SLUG } from './territories';

/**
 * Adhésion directe : une entreprise dont ni la commune ni l'intercommunalité ne sont partenaires adhère seule
 * à terricom, et paie (lib/pricing.ts). Sa fiche vit dans la vitrine nationale (terricom.fr/france) ; chaque
 * adhésion alimente le suivi commercial de son intercommunalité ; quand la collectivité adhère, la fiche rejoint
 * son portail et l'abonnement passe au prix de l'option équivalente.
 */

export const PENDING_PAYMENT = 'Adhésion en attente de paiement';
export const DIRECT_CANCELED = 'Adhésion résiliée';

export type Coverage = { status: 'free' } | { status: 'covered' | 'coming'; territory: { id: string; name: string; slug: string } };

/** La commune est-elle déjà couverte par une collectivité partenaire (portail ouvert, ou en préparation) ? */
export async function coverageOfCommune(inseeCode: string): Promise<Coverage> {
  const [row] = await db
    .select({ id: territories.id, name: territories.name, slug: territories.slug, status: territories.status })
    .from(communeMemberships)
    .innerJoin(communes, eq(communes.id, communeMemberships.communeId))
    .innerJoin(territories, eq(territories.id, communeMemberships.territoryId))
    .where(and(eq(communes.inseeCode, inseeCode), isNull(communeMemberships.validTo), inArray(territories.status, ['ACTIVE', 'ONBOARDING'])))
    .limit(1);
  if (!row) return { status: 'free' };
  return { status: row.status === 'ACTIVE' ? 'covered' : 'coming', territory: { id: row.id, name: row.name, slug: row.slug } };
}

/** Commune du référentiel (créée depuis l'API Géo au besoin), avec son intercommunalité. */
export async function ensureCommuneByInsee(inseeCode: string) {
  const [row] = await db.select().from(communes).where(eq(communes.inseeCode, inseeCode)).limit(1);
  if (row?.epciSiren) return row;
  const geo = await communeByInsee(inseeCode);
  if (row) {
    if (geo?.codeEpci) {
      const [updated] = await db
        .update(communes)
        .set({ epciSiren: geo.codeEpci, epciName: geo.epci?.nom ?? null })
        .where(eq(communes.id, row.id))
        .returning();
      return updated;
    }
    return row;
  }
  if (!geo) return null;
  const base = slugify(geo.nom) || 'commune';
  const taken = new Set(
    (
      await db
        .select({ slug: communes.slug })
        .from(communes)
        .where(sql`${communes.slug} LIKE ${`${base}%`}`)
    ).map((r) => r.slug),
  );
  let slug = base;
  for (let i = 2; taken.has(slug); i++) slug = `${base}-${i}`;
  const [created] = await db
    .insert(communes)
    .values({
      inseeCode: geo.code,
      name: geo.nom,
      slug,
      postalCodes: geo.codesPostaux ?? [],
      departmentCode: geo.codeDepartement || null,
      population: geo.population ?? null,
      lat: geo.centre ? geo.centre.coordinates[1] : null,
      lng: geo.centre ? geo.centre.coordinates[0] : null,
      epciSiren: geo.codeEpci ?? null,
      epciName: geo.epci?.nom ?? null,
    })
    .onConflictDoNothing()
    .returning();
  if (created) return created;
  const [again] = await db.select().from(communes).where(eq(communes.inseeCode, inseeCode)).limit(1);
  return again ?? null;
}

async function nationalId(): Promise<string> {
  return (await ensureNationalTerritory()).id;
}

/** Une entreprise est en adhésion directe si toutes ses fiches actives sont dans la vitrine nationale. */
export async function companyIsDirect(companyId: string): Promise<boolean> {
  const rows = await db
    .select({ slug: territories.slug })
    .from(establishments)
    .innerJoin(territories, eq(territories.id, establishments.territoryId))
    .where(and(eq(establishments.companyId, companyId), ne(establishments.status, 'ARCHIVED')));
  return rows.length > 0 && rows.every((r) => r.slug === NATIONAL_SLUG);
}

export type DirectMemberInput = {
  userId: string;
  siret: string;
  name: string;
  categoryId: string;
  activityLabel: string | null;
  street: string;
  inseeCode: string;
  phone: string | null;
  email: string | null;
  website: string | null;
  sirene: SireneEstablishment | null;
};

/**
 * Crée l'entreprise (si besoin) et sa fiche dans la vitrine nationale, masquée jusqu'au paiement ; le déclarant
 * en devient titulaire. Refuse une commune déjà couverte (le parcours gratuit s'applique) et un SIRET déjà présent.
 */
export async function createDirectMember(input: DirectMemberInput): Promise<{ estId: string; companyId: string }> {
  const siret = input.siret.replace(/\s/g, '');
  if (!isValidSiret(siret)) throw new ClaimError('Numéro SIRET invalide (14 chiffres, clé de contrôle).');
  const [existing] = await db.select({ id: establishments.id }).from(establishments).where(eq(establishments.siret, siret)).limit(1);
  if (existing) throw new ClaimError(`EXISTS:${existing.id}`);
  const coverage = await coverageOfCommune(input.inseeCode);
  if (coverage.status !== 'free') throw new ClaimError(`COVERED:${coverage.territory.slug}`);
  const commune = await ensureCommuneByInsee(input.inseeCode);
  if (!commune) throw new ClaimError('Commune introuvable : vérifiez le code postal ou réessayez dans un instant.');
  const [category] = await db.select().from(categories).where(eq(categories.id, input.categoryId)).limit(1);
  if (!category) throw new ClaimError('Choisissez une catégorie.');
  const territoryId = await nationalId();

  const point =
    input.sirene?.lat && input.sirene?.lng
      ? { lat: input.sirene.lat, lng: input.sirene.lng }
      : await geocode(`${input.street} ${commune.postalCodes[0] ?? ''} ${commune.name}`, commune.inseeCode);
  const base = slugify(input.name) || 'etablissement';
  const taken = new Set(
    (await db.select({ slug: establishments.slug }).from(establishments).where(eq(establishments.communeId, commune.id))).map((r) => r.slug),
  );
  let slug = base;
  for (let i = 2; taken.has(slug); i++) slug = `${base}-${i}`;

  const res = await db.transaction(async (tx) => {
    const siren = siret.slice(0, 9);
    let [company] = await tx.select().from(companies).where(eq(companies.siren, siren)).limit(1);
    if (!company) {
      [company] = await tx
        .insert(companies)
        .values({
          siren,
          legalName: input.sirene?.legalName ?? input.name.toUpperCase(),
          tradeName: input.name,
          nafCode: input.sirene?.nafCode ?? category.nafCodes[0] ?? null,
        })
        .returning();
    }
    const [est] = await tx
      .insert(establishments)
      .values({
        companyId: company.id,
        communeId: commune.id,
        territoryId,
        categoryId: category.id,
        slug,
        name: input.name,
        siret,
        status: 'SUSPENDED',
        suspendedReason: PENDING_PAYMENT,
        origin: 'PRO',
        activityLabel: input.activityLabel || category.name,
        street: input.street,
        postalCode: input.sirene?.postalCode ?? commune.postalCodes[0] ?? null,
        lat: point?.lat ?? commune.lat,
        lng: point?.lng ?? commune.lng,
        phone: input.phone,
        email: input.email,
        website: input.website,
        qrCode: shortCode(8),
        createdById: input.userId,
      })
      .returning({ id: establishments.id });
    await tx.insert(companyMembers).values({ companyId: company.id, userId: input.userId, role: 'OWNER' }).onConflictDoNothing();
    return { estId: est.id, companyId: company.id };
  });
  await refreshSearchKeywords(res.estId);
  await refreshCompleteness(res.estId);
  if (!point) await enqueue('import.geocode', { establishmentId: res.estId }, { dedupeKey: `geocode:${res.estId}` });
  await audit({
    actor: 'Système',
    category: 'MODIFICATION',
    action: 'direct.created',
    summary: `Adhésion directe créée : ${input.name} (${commune.name})`,
    territoryId,
    targetType: 'establishment',
    targetId: res.estId,
  });
  return res;
}

/** Paiement reçu (ou adhésion facturée par virement) : les fiches de la vitrine nationale sont publiées. */
export async function activateDirectMember(companyId: string): Promise<void> {
  const territoryId = await nationalId();
  const published = await db
    .update(establishments)
    .set({ status: 'CLAIMED', suspendedReason: null, publishedAt: sql`coalesce(${establishments.publishedAt}, now())`, updatedAt: new Date() })
    .where(
      and(
        eq(establishments.companyId, companyId),
        eq(establishments.territoryId, territoryId),
        eq(establishments.status, 'SUSPENDED'),
        inArray(establishments.suspendedReason, [PENDING_PAYMENT, DIRECT_CANCELED]),
      ),
    )
    .returning({ id: establishments.id });
  if (published.length) await recordDirectLever(companyId);
}

/** Adhésion résiliée : sans collectivité qui l'offre, la fiche est retirée de la vitrine (réactivable). */
export async function suspendDirectMember(companyId: string): Promise<void> {
  const territoryId = await nationalId();
  await db
    .update(establishments)
    .set({ status: 'SUSPENDED', suspendedReason: DIRECT_CANCELED, updatedAt: new Date() })
    .where(and(eq(establishments.companyId, companyId), eq(establishments.territoryId, territoryId), ne(establishments.status, 'ARCHIVED')));
}

function kindFromEpciName(name: string): TerritoryKind {
  if (/^CA\b|agglom/i.test(name)) return 'CA';
  if (/^CU\b|urbaine/i.test(name)) return 'CU';
  if (/^M[ée]tropole/i.test(name)) return 'METROPOLE';
  return 'CC';
}

/**
 * Levier commercial : chaque adhésion directe est consignée dans l'affaire de l'intercommunalité de l'entreprise
 * (créée au besoin, au stade prospect) — « 14 entreprises de votre territoire adhèrent déjà ».
 */
export async function recordDirectLever(companyId: string): Promise<void> {
  const territoryId = await nationalId();
  const rows = await db
    .select({ name: establishments.name, commune: communes.name, epciSiren: communes.epciSiren, epciName: communes.epciName })
    .from(establishments)
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .where(and(eq(establishments.companyId, companyId), eq(establishments.territoryId, territoryId)));
  for (const r of rows) {
    if (!r.epciSiren || !r.epciName) continue;
    const [open] = await db
      .select({ id: deals.id })
      .from(deals)
      .where(and(eq(deals.siren, r.epciSiren), ne(deals.stage, 'LOST')))
      .orderBy(desc(deals.createdAt))
      .limit(1);
    let dealId = open?.id;
    if (!dealId) {
      const [created] = await db
        .insert(deals)
        .values({
          name: r.epciName,
          siren: r.epciSiren,
          kind: kindFromEpciName(r.epciName),
          stage: 'PROSPECT',
          probability: STAGE_PROBABILITY.PROSPECT,
          source: 'Adhésions directes',
          notes: 'Affaire ouverte automatiquement : des entreprises de ce territoire adhèrent directement à terricom.',
          lastInteractionAt: new Date(),
        })
        .returning({ id: deals.id });
      dealId = created.id;
      await addDealTask(dealId, 'Présenter terricom : des entreprises du territoire adhèrent déjà', 'cette semaine');
    }
    await logDealActivity(dealId, 'Note', `Adhésion directe : ${r.name} (${r.commune})`, null);
  }
}

export type DirectCount = { epciSiren: string; epciName: string; count: number; dealId: string | null; stage: string | null };

/** Adhérents directs par intercommunalité (console : levier commercial), du plus au moins fourni. */
export async function directMembersByEpci(): Promise<{ total: number; byEpci: DirectCount[] }> {
  const territoryId = await nationalId();
  const res = await db.execute<{ epci_siren: string; epci_name: string; n: number; deal_id: string | null; stage: string | null }>(sql`
    SELECT c.epci_siren, c.epci_name, count(*)::int AS n,
      (SELECT d.id FROM deals d WHERE d.siren = c.epci_siren AND d.stage <> 'LOST' ORDER BY d.created_at DESC LIMIT 1) AS deal_id,
      (SELECT d.stage FROM deals d WHERE d.siren = c.epci_siren AND d.stage <> 'LOST' ORDER BY d.created_at DESC LIMIT 1) AS stage
    FROM establishments e JOIN communes c ON c.id = e.commune_id
    WHERE e.territory_id = ${territoryId} AND e.status IN ('CLAIMED', 'VALIDATED') AND c.epci_siren IS NOT NULL
    GROUP BY c.epci_siren, c.epci_name
    ORDER BY n DESC, c.epci_name
  `);
  const byEpci = res.rows.map((r) => ({ epciSiren: r.epci_siren, epciName: r.epci_name, count: Number(r.n), dealId: r.deal_id, stage: r.stage }));
  return { total: byEpci.reduce((s, r) => s + r.count, 0), byEpci };
}

/** Nombre d'adhérents directs actifs d'une intercommunalité (affaire du suivi commercial). */
export async function directCountForSiren(siren: string): Promise<number> {
  const territoryId = await nationalId();
  const res = await db.execute<{ n: number }>(sql`
    SELECT count(*)::int AS n FROM establishments e JOIN communes c ON c.id = e.commune_id
    WHERE e.territory_id = ${territoryId} AND e.status IN ('CLAIMED', 'VALIDATED') AND c.epci_siren = ${siren}
  `);
  return Number(res.rows[0]?.n ?? 0);
}

/**
 * La collectivité adhère : les fiches des adhérents directs de ses communes quittent la vitrine nationale pour
 * son portail (avec leurs publications, événements et offres d'emploi), et l'abonnement passe au prix de
 * l'option équivalente. À appeler après tout nouveau rattachement de communes.
 */
export async function absorbDirectMembers(territoryId: string): Promise<{ moved: number; companies: number }> {
  const national = await nationalId();
  if (national === territoryId) return { moved: 0, companies: 0 };
  const moved = await db.transaction(async (tx) => {
    const rows = await tx.execute<{ id: string; company_id: string }>(sql`
      UPDATE establishments e SET territory_id = ${territoryId}, updated_at = now()
      FROM commune_memberships m
      WHERE e.territory_id = ${national} AND m.commune_id = e.commune_id AND m.territory_id = ${territoryId} AND m.valid_to IS NULL
      RETURNING e.id, e.company_id
    `);
    const ids = rows.rows.map((r) => r.id);
    if (ids.length) {
      const list = sql.join(
        ids.map((id) => sql`${id}`),
        sql`, `,
      );
      await tx.execute(sql`UPDATE posts SET territory_id = ${territoryId} WHERE establishment_id IN (${list})`);
      await tx.execute(sql`UPDATE events SET territory_id = ${territoryId} WHERE establishment_id IN (${list})`);
      await tx.execute(sql`UPDATE jobs SET territory_id = ${territoryId} WHERE establishment_id IN (${list})`);
      // Fiche retirée faute d'adhésion (non payée ou résiliée) : la collectivité l'offre désormais.
      await tx.execute(sql`
        UPDATE establishments SET status = 'CLAIMED', suspended_reason = NULL, published_at = coalesce(published_at, now())
        WHERE id IN (${list}) AND status = 'SUSPENDED' AND suspended_reason IN (${PENDING_PAYMENT}, ${DIRECT_CANCELED})
      `);
    }
    return rows.rows;
  });
  if (!moved.length) return { moved: 0, companies: 0 };
  const [territory] = await db.select({ name: territories.name, slug: territories.slug }).from(territories).where(eq(territories.id, territoryId)).limit(1);
  const companyIds = [...new Set(moved.map((r) => r.company_id))];
  for (const companyId of companyIds) {
    if (await companyIsDirect(companyId)) continue;
    const [sub] = await db
      .select()
      .from(companySubscriptions)
      .where(and(eq(companySubscriptions.companyId, companyId), eq(companySubscriptions.status, 'ACTIVE'), eq(companySubscriptions.direct, true)))
      .limit(1);
    if (sub) {
      await db.update(companySubscriptions).set({ direct: false }).where(eq(companySubscriptions.id, sub.id));
      if (sub.provider === 'STRIPE' && sub.providerRef) {
        const ok = await updateStripeSubscriptionPrice({
          subscriptionId: sub.providerRef,
          plan: sub.plan,
          direct: false,
          interval: sub.interval as 'MONTH' | 'YEAR',
        });
        if (!ok) logger.warn('direct.stripe_price_not_updated', { companyId });
      }
    }
    await notifyAbsorbed(companyId, territory?.name ?? 'votre collectivité', Boolean(sub));
    await audit({
      actor: 'Système',
      category: 'FACTURATION',
      action: 'direct.absorbed',
      summary: `Adhésion directe reprise par ${territory?.name ?? 'une collectivité'} : fiche offerte, abonnement au prix de l'option`,
      territoryId,
      targetType: 'company',
      targetId: companyId,
    });
  }
  return { moved: moved.length, companies: companyIds.length };
}

async function notifyAbsorbed(companyId: string, territoryName: string, hadSubscription: boolean): Promise<void> {
  const [co] = await db.select().from(companies).where(eq(companies.id, companyId)).limit(1);
  const owners = await db
    .select({ email: users.email, firstName: users.firstName })
    .from(companyMembers)
    .innerJoin(users, eq(users.id, companyMembers.userId))
    .where(and(eq(companyMembers.companyId, companyId), eq(companyMembers.role, 'OWNER')));
  const to = co?.billingEmail ?? owners[0]?.email;
  if (!to) return;
  const { html, text } = renderEmail({
    eyebrow: 'Bonne nouvelle',
    title: `${territoryName} rejoint terricom`,
    paragraphs: [
      `Bonjour${owners[0]?.firstName ? ` ${owners[0].firstName}` : ''}, votre collectivité vient d’adhérer à terricom : votre fiche rejoint son portail, aux côtés de toutes les entreprises du territoire, et elle vous est désormais offerte.`,
      hadSubscription
        ? 'Votre abonnement passe automatiquement au prix de l’option équivalente dès la prochaine échéance : vous gardez toutes vos fonctionnalités.'
        : 'Vous pouvez à tout moment ajouter une option pour aller plus loin.',
    ],
    cta: { label: 'Voir mon espace', url: appUrl('/pro') },
  });
  await sendEmail({ to, subject: `${territoryName} rejoint terricom : votre fiche est désormais offerte`, html, text, template: 'direct-absorbed' });
}
