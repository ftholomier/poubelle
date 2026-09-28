import { and, asc, eq, inArray, isNull, or } from 'drizzle-orm';
import { slugify } from '@/lib/slug';
import { shortCode } from '../crypto';
import { db } from '../db';
import { categories, communeMemberships, communes, companies, establishments, territories } from '../db/schema';
import { geocode, isValidSiret, type SireneEstablishment } from '../integrations/public-data';
import { enqueue } from '../queue';
import { ClaimError, PENDING_CREATION } from './claims';
import { refreshCompleteness, refreshSearchKeywords } from './establishments';

/**
 * Inscription d'une activité absente de la base : la fiche est créée masquée,
 * puis publiée quand la collectivité valide la demande (même circuit qu'une revendication).
 */

export type SignupTerritory = {
  id: string;
  name: string;
  slug: string;
  communes: { id: string; name: string; postalCode: string | null; inseeCode: string }[];
};

/** Territoires ouverts aux inscriptions et leurs communes. */
export async function signupTerritories(territoryId?: string | null): Promise<SignupTerritory[]> {
  const rows = await db
    .select({
      tId: territories.id,
      tName: territories.name,
      tSlug: territories.slug,
      cId: communes.id,
      cName: communes.name,
      inseeCode: communes.inseeCode,
      postalCodes: communes.postalCodes,
    })
    .from(communeMemberships)
    .innerJoin(territories, eq(territories.id, communeMemberships.territoryId))
    .innerJoin(communes, eq(communes.id, communeMemberships.communeId))
    .where(and(isNull(communeMemberships.validTo), eq(territories.status, 'ACTIVE'), territoryId ? eq(territories.id, territoryId) : undefined))
    .orderBy(asc(territories.name), asc(communes.name));
  const map = new Map<string, SignupTerritory>();
  for (const r of rows) {
    const t = map.get(r.tId) ?? { id: r.tId, name: r.tName, slug: r.tSlug, communes: [] };
    t.communes.push({ id: r.cId, name: r.cName, postalCode: r.postalCodes[0] ?? null, inseeCode: r.inseeCode });
    map.set(r.tId, t);
  }
  return [...map.values()];
}

export async function signupCategories(territoryIds: string[]) {
  return db
    .select({ id: categories.id, name: categories.name, family: categories.family })
    .from(categories)
    .where(
      and(eq(categories.isActive, true), or(isNull(categories.territoryId), territoryIds.length ? inArray(categories.territoryId, territoryIds) : undefined)),
    )
    .orderBy(asc(categories.name));
}

export type SignupInput = {
  userId: string;
  siret: string;
  name: string;
  categoryId: string;
  activityLabel: string | null;
  street: string;
  communeId: string;
  phone: string | null;
  email: string | null;
  website: string | null;
  sirene: SireneEstablishment | null;
};

/** Crée l'entreprise (si besoin) et la fiche masquée ; renvoie l'identifiant de la fiche. */
export async function createPendingEstablishment(input: SignupInput): Promise<string> {
  const siret = input.siret.replace(/\s/g, '');
  if (!isValidSiret(siret)) throw new ClaimError('Numéro SIRET invalide (14 chiffres, clé de contrôle).');
  const [existing] = await db.select({ id: establishments.id }).from(establishments).where(eq(establishments.siret, siret)).limit(1);
  if (existing) throw new ClaimError(`EXISTS:${existing.id}`);
  const [membership] = await db
    .select({ territoryId: communeMemberships.territoryId, commune: communes })
    .from(communeMemberships)
    .innerJoin(communes, eq(communes.id, communeMemberships.communeId))
    .innerJoin(territories, eq(territories.id, communeMemberships.territoryId))
    .where(and(eq(communeMemberships.communeId, input.communeId), isNull(communeMemberships.validTo), eq(territories.status, 'ACTIVE')))
    .limit(1);
  if (!membership) throw new ClaimError('Cette commune ne fait pas encore partie d’un territoire partenaire.');
  const [category] = await db.select().from(categories).where(eq(categories.id, input.categoryId)).limit(1);
  if (!category) throw new ClaimError('Choisissez une catégorie.');

  const commune = membership.commune;
  const point = await geocode(`${input.street} ${commune.postalCodes[0] ?? ''} ${commune.name}`, commune.inseeCode);
  const base = slugify(input.name) || 'etablissement';
  const taken = new Set(
    (await db.select({ slug: establishments.slug }).from(establishments).where(eq(establishments.communeId, commune.id))).map((r) => r.slug),
  );
  let slug = base;
  for (let i = 2; taken.has(slug); i++) slug = `${base}-${i}`;

  const estId = await db.transaction(async (tx) => {
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
        territoryId: membership.territoryId,
        categoryId: category.id,
        slug,
        name: input.name,
        siret,
        status: 'SUSPENDED',
        suspendedReason: PENDING_CREATION,
        origin: 'PRO',
        activityLabel: input.activityLabel || category.name,
        street: input.street,
        postalCode: commune.postalCodes[0] ?? null,
        lat: point?.lat ?? commune.lat,
        lng: point?.lng ?? commune.lng,
        phone: input.phone,
        email: input.email,
        website: input.website,
        qrCode: shortCode(8),
        createdById: input.userId,
      })
      .returning({ id: establishments.id });
    return est.id;
  });
  await refreshSearchKeywords(estId);
  await refreshCompleteness(estId);
  if (!point) await enqueue('import.geocode', { establishmentId: estId }, { dedupeKey: `geocode:${estId}` });
  return estId;
}
