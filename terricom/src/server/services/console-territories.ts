import { and, eq, isNull, sql } from 'drizzle-orm';
import { db } from '../db';
import { audiences, communeMemberships, communes, deals, territories, territoryContracts, territoryDomains, territoryModules, tokens } from '../db/schema';
import {
  MODULE_ORDER,
  MODULES,
  RESERVED_SLUGS,
  TERRITORY_KINDS,
  TERRITORY_STATUS,
  type ModuleKey,
  type TerritoryKind,
  type TerritoryStatus,
} from '@/lib/constants';
import { slugify, uniqueSlug } from '@/lib/slug';
import { communeByInsee, communesOfEpci, type GeoCommune } from '../integrations/public-data';
import { env } from '../env';
import { randomToken, sha256 } from '../crypto';

/**
 * Console plateforme — territoires clients (S2) : liste des clients et des négociations,
 * modules activés, quotas contractuels et création d'un nouveau territoire.
 */

async function rows<T>(query: ReturnType<typeof sql>): Promise<T[]> {
  return (await db.execute(query)).rows as T[];
}

export type ClientRow = {
  kind: 'territory' | 'deal';
  id: string;
  name: string;
  typeLabel: string;
  statusLabel: string;
  color: string;
  communes: number;
  establishments: number | null;
  premium: number | null;
  licenceCents: number | null;
  host: string | null;
};

/** Clients (territoires) puis négociations en cours sans territoire créé. */
export async function listClients(): Promise<ClientRow[]> {
  const [terrs, negs] = await Promise.all([
    rows<{
      id: string;
      name: string;
      kind: TerritoryKind;
      status: TerritoryStatus;
      is_pilot: boolean;
      primary_host: string | null;
      communes: number;
      establishments: number;
      premium: number;
      licence: number | null;
    }>(sql`
      select t.id, t.name, t.kind, t.status, t.is_pilot, t.primary_host,
        (select count(*)::int from commune_memberships m where m.territory_id = t.id and m.valid_to is null) as communes,
        (select count(*)::int from establishments e where e.territory_id = t.id and e.status <> 'ARCHIVED') as establishments,
        (select count(distinct s.company_id)::int from company_subscriptions s
          join establishments e on e.company_id = s.company_id
          where e.territory_id = t.id and s.status = 'ACTIVE' and s.plan <> 'ESSENTIEL') as premium,
        (select sum(c.amount_cents)::int from territory_contracts c where c.territory_id = t.id and c.kind = 'LICENCE' and c.status = 'ACTIVE') as licence
      from territories t
      order by case t.status when 'ACTIVE' then 0 when 'ONBOARDING' then 1 when 'SUSPENDED' then 2 else 3 end, t.is_pilot desc, t.created_at
    `),
    rows<{ id: string; name: string; kind: TerritoryKind; communes_count: number; licence_cents: number }>(sql`
      select id, name, kind, communes_count, licence_cents from deals
      where territory_id is null and stage in ('PROPOSAL', 'NEGOTIATION') order by probability desc, name
    `),
  ]);
  return [
    ...terrs.map((t) => ({
      kind: 'territory' as const,
      id: t.id,
      name: t.name,
      typeLabel: TERRITORY_KINDS[t.kind]?.label ?? t.kind,
      statusLabel: `${TERRITORY_STATUS[t.status].label}${t.is_pilot ? ' · pilote' : ''}`,
      color: TERRITORY_STATUS[t.status].color,
      communes: t.communes,
      establishments: t.establishments,
      premium: t.premium,
      licenceCents: t.licence,
      host: t.primary_host,
    })),
    ...negs.map((d) => ({
      kind: 'deal' as const,
      id: d.id,
      name: d.name,
      typeLabel: TERRITORY_KINDS[d.kind]?.label ?? d.kind,
      statusLabel: 'Négociation',
      color: '#C8892A',
      communes: d.communes_count,
      establishments: null,
      premium: null,
      licenceCents: null,
      host: null,
    })),
  ];
}

export type TerritoryPanel = NonNullable<Awaited<ReturnType<typeof territoryPanel>>>;

/** Détail d'un territoire client : domaine, modules, consommation des quotas, tickets ouverts. */
export async function territoryPanel(id: string) {
  const [t] = await db.select().from(territories).where(eq(territories.id, id)).limit(1);
  if (!t) return null;
  const [mods, [usage], tickets, [domain], admins] = await Promise.all([
    db.select().from(territoryModules).where(eq(territoryModules.territoryId, id)),
    rows<{ establishments: number; emails: number; ai: number }>(sql`
      select
        (select count(*)::int from establishments where territory_id = ${id} and status <> 'ARCHIVED') as establishments,
        (select coalesce(sum(stats_sent), 0)::int from newsletters where territory_id = ${id} and sent_at >= date_trunc('month', now())) as emails,
        (select coalesce(sum(credits), 0)::int from ai_usage where territory_id = ${id} and created_at >= date_trunc('month', now())) as ai
    `),
    rows<{ id: string; number: number; subject: string }>(sql`
      select id, number, subject from support_tickets where territory_id = ${id} and status in ('OPEN', 'PENDING') order by created_at desc limit 20
    `),
    db
      .select({ host: territoryDomains.host })
      .from(territoryDomains)
      .where(eq(territoryDomains.territoryId, id))
      .orderBy(sql`${territoryDomains.isPrimary} desc, ${territoryDomains.createdAt}`)
      .limit(1),
    rows<{ email: string; first_name: string | null; last_name: string | null; mfa_enabled: boolean }>(sql`
      select u.email, u.first_name, u.last_name, u.mfa_enabled from role_assignments r join users u on u.id = r.user_id
      where r.territory_id = ${id} and r.role = 'TERRITORY_ADMIN' order by u.created_at
    `),
  ]);
  const enabled = new Set(mods.filter((m) => m.enabled).map((m) => m.module as ModuleKey));
  return {
    territory: t,
    typeLabel: TERRITORY_KINDS[t.kind as TerritoryKind]?.label ?? t.kind,
    statusLabel: `${TERRITORY_STATUS[t.status as TerritoryStatus].label}${t.isPilot ? ' · pilote' : ''}`,
    host: t.primaryHost ?? domain?.host ?? `${env.PLATFORM_DOMAIN}/${t.slug}`,
    modules: MODULE_ORDER.map((key) => ({ key, ...MODULES[key], enabled: enabled.has(key) })),
    quotas: [
      { key: 'establishments', label: 'Établissements', used: usage?.establishments ?? 0, max: t.quotaEstablishments, color: 'var(--green)' },
      { key: 'emails', label: 'Emails newsletter / mois', used: usage?.emails ?? 0, max: t.quotaEmailsMonthly, color: 'var(--sky)' },
      { key: 'ai', label: 'Crédits IA / mois', used: usage?.ai ?? 0, max: t.quotaAiCreditsMonthly, color: '#7A5BB5' },
    ],
    tickets,
    admins,
  };
}

export async function setTerritoryModule(territoryId: string, module: ModuleKey, enabled: boolean): Promise<void> {
  await db
    .insert(territoryModules)
    .values({ territoryId, module, enabled })
    .onConflictDoUpdate({ target: [territoryModules.territoryId, territoryModules.module], set: { enabled, updatedAt: new Date() } });
}

export type NewTerritoryInput = {
  name: string;
  legalName: string;
  kind: TerritoryKind;
  slug?: string;
  siren?: string | null;
  departmentCode?: string | null;
  population?: number | null;
  contactEmail?: string | null;
  status: 'ONBOARDING' | 'ACTIVE';
  isPilot: boolean;
  inseeCodes: string[];
  licenceCents: number;
  setupCents: number;
  startsAt: string;
  modules: ModuleKey[];
  colorPrimary?: string;
  colorAccent?: string;
  quotaEstablishments: number;
  quotaEmailsMonthly: number;
  quotaAiCreditsMonthly: number;
  dealId?: string | null;
};

export type NewTerritoryResult = {
  id: string;
  slug: string;
  attached: number;
  skipped: { code: string; reason: string }[];
};

function addDaysIso(iso: string, days: number): string {
  const d = new Date(`${iso}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
}

/** Communes d'un EPCI (API Géo) ou d'une liste de codes INSEE ; repli sur le référentiel local. */
export async function resolveCommunes(input: { siren?: string | null; inseeCodes: string[] }): Promise<{ found: GeoCommune[]; missing: string[] }> {
  if (input.siren && /^\d{9}$/.test(input.siren) && input.inseeCodes.length === 0) {
    const list = await communesOfEpci(input.siren).catch(() => null);
    if (list?.length) return { found: list, missing: [] };
  }
  const found: GeoCommune[] = [];
  const missing: string[] = [];
  for (const code of input.inseeCodes) {
    const [local] = await db.select().from(communes).where(eq(communes.inseeCode, code)).limit(1);
    if (local) {
      found.push({
        nom: local.name,
        code: local.inseeCode,
        codesPostaux: local.postalCodes,
        codeDepartement: local.departmentCode ?? '',
        population: local.population ?? undefined,
        centre: local.lat !== null && local.lng !== null ? { type: 'Point', coordinates: [local.lng, local.lat] } : undefined,
      });
      continue;
    }
    const remote = await communeByInsee(code).catch(() => null);
    if (remote) found.push(remote);
    else missing.push(code);
  }
  return { found, missing };
}

/**
 * Crée un territoire client : identité, domaine, modules, contrats (licence + mise en service),
 * audience par défaut de la newsletter et rattachement des communes. Les communes déjà rattachées
 * à un autre territoire sont signalées et laissées en place (un changement d'intercommunalité se fait
 * explicitement, avec historique).
 */
export async function createTerritory(input: NewTerritoryInput): Promise<NewTerritoryResult> {
  const slug = await uniqueSlug(input.slug || input.name, async (c) => {
    if (RESERVED_SLUGS.has(c)) return true;
    const [x] = await db.select({ id: territories.id }).from(territories).where(eq(territories.slug, c)).limit(1);
    return Boolean(x);
  });
  const { found, missing } = await resolveCommunes({ siren: input.siren, inseeCodes: input.inseeCodes });
  const skipped: { code: string; reason: string }[] = missing.map((code) => ({ code, reason: 'code INSEE inconnu' }));
  const centre = found.find((c) => c.centre)?.centre?.coordinates;
  const population = input.population ?? (found.reduce((a, c) => a + (c.population ?? 0), 0) || null);

  return db.transaction(async (tx) => {
    const [t] = await tx
      .insert(territories)
      .values({
        slug,
        name: input.name,
        legalName: input.legalName,
        kind: input.kind,
        status: input.status,
        isPilot: input.isPilot,
        siren: input.siren || null,
        population,
        departmentCode: input.departmentCode || found[0]?.codeDepartement || null,
        initials:
          input.name
            .split(/[\s-]+/)
            .filter((w) => w.length > 2)
            .map((w) => w[0]!.toUpperCase())
            .join('')
            .slice(0, 3) || input.name.slice(0, 2).toUpperCase(),
        colorPrimary: input.colorPrimary ?? '#1F6B52',
        colorAccent: input.colorAccent ?? '#F4B266',
        heroTitle: `${input.name},|fait main & fait ici.`,
        heroSubtitle: `Commerces, artisans et producteurs de ${input.name}. Trouvez, poussez la porte, soutenez.`,
        centerLat: centre ? centre[1] : null,
        centerLng: centre ? centre[0] : null,
        contactEmail: input.contactEmail || null,
        settings: { claimValidation: 'MANUAL', postModeration: 'POST', newsletterName: 'La lettre locale', jobsTitle: `Travailler à ${input.name}` },
        quotaEstablishments: input.quotaEstablishments,
        quotaEmailsMonthly: input.quotaEmailsMonthly,
        quotaAiCreditsMonthly: input.quotaAiCreditsMonthly,
      })
      .returning();
    await tx.insert(territoryDomains).values({ territoryId: t.id, host: `${slug}.${env.PLATFORM_DOMAIN}`, isPrimary: false });
    await tx.insert(territoryModules).values(MODULE_ORDER.map((m) => ({ territoryId: t.id, module: m, enabled: input.modules.includes(m) })));
    await tx.insert(audiences).values({ territoryId: t.id, name: 'Habitants', description: 'Toutes communes', kind: 'MANUAL', isDefault: true, sortOrder: 0 });
    const contracts: (typeof territoryContracts.$inferInsert)[] = [];
    if (input.licenceCents > 0)
      contracts.push({
        territoryId: t.id,
        kind: 'LICENCE',
        label: `Licence annuelle ${input.name}`,
        amountCents: input.licenceCents,
        startsAt: input.startsAt,
        endsAt: addDaysIso(input.startsAt, 364),
        status: 'ACTIVE',
        signedAt: input.startsAt,
      });
    if (input.setupCents > 0)
      contracts.push({
        territoryId: t.id,
        kind: 'SETUP',
        label: 'Mise en service (paramétrage, import, formation)',
        amountCents: input.setupCents,
        startsAt: input.startsAt,
        status: 'ACTIVE',
        signedAt: input.startsAt,
      });
    if (contracts.length) await tx.insert(territoryContracts).values(contracts);

    let attached = 0;
    for (const c of found) {
      let [row] = await tx.select().from(communes).where(eq(communes.inseeCode, c.code)).limit(1);
      if (!row) {
        const slugC = await uniqueSlug(c.nom, async (cand) => {
          const [x] = await tx.select({ id: communes.id }).from(communes).where(eq(communes.slug, cand)).limit(1);
          return Boolean(x);
        });
        [row] = await tx
          .insert(communes)
          .values({
            inseeCode: c.code,
            name: c.nom,
            slug: slugC,
            postalCodes: c.codesPostaux ?? [],
            departmentCode: c.codeDepartement || null,
            population: c.population ?? null,
            lat: c.centre ? c.centre.coordinates[1] : null,
            lng: c.centre ? c.centre.coordinates[0] : null,
          })
          .returning();
      }
      const [current] = await tx
        .select({ territoryId: communeMemberships.territoryId, name: territories.name })
        .from(communeMemberships)
        .innerJoin(territories, eq(territories.id, communeMemberships.territoryId))
        .where(and(eq(communeMemberships.communeId, row.id), isNull(communeMemberships.validTo)))
        .limit(1);
      if (current) {
        skipped.push({ code: c.code, reason: `${c.nom} est déjà rattachée à ${current.name}` });
        continue;
      }
      await tx.insert(communeMemberships).values({ communeId: row.id, territoryId: t.id, validFrom: input.startsAt });
      attached++;
    }
    if (input.dealId)
      await tx.update(deals).set({ territoryId: t.id, stage: 'SIGNED', probability: 100, updatedAt: new Date() }).where(eq(deals.id, input.dealId));
    return { id: t.id, slug, attached, skipped };
  });
}

/** Invitation du premier administrateur territorial (lien valable 7 jours). */
export async function inviteTerritoryAdmin(territoryId: string, email: string, createdById: string): Promise<string> {
  const token = randomToken(32);
  await db.insert(tokens).values({
    kind: 'INVITE_STAFF',
    tokenHash: sha256(token),
    email,
    payload: { staffRole: 'TERRITORY_ADMIN', territoryId, communeId: null },
    expiresAt: new Date(Date.now() + 7 * 86_400_000),
    createdById,
  });
  return token;
}

/** Préremplissage du formulaire depuis une affaire du suivi commercial. */
export async function dealPrefill(dealId: string) {
  const [d] = await db.select().from(deals).where(eq(deals.id, dealId)).limit(1);
  if (!d) return null;
  return {
    id: d.id,
    name: d.name,
    kind: d.kind as TerritoryKind,
    licenceCents: d.licenceCents,
    setupCents: d.setupCents,
    population: d.population,
    email: d.contactEmail,
  };
}

export function suggestedSlug(name: string): string {
  return slugify(name.replace(/^(communaut[ée] de communes|communaut[ée] d['’]agglom[ée]ration|cc|ca)\s+(du|de la|des|de l['’]|de|d['’])?\s*/i, ''));
}
