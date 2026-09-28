import { and, asc, eq, inArray, isNull, or, sql } from 'drizzle-orm';
import { cache } from 'react';
import { MODULE_ORDER, type ModuleKey } from '@/lib/constants';
import { db } from '../db';
import { communeMemberships, communes, establishments, territories, territoryDomains, territoryModules } from '../db/schema';

export type Territory = typeof territories.$inferSelect;
export type Commune = typeof communes.$inferSelect;

export const getTerritoryBySlug = cache(async (slug: string): Promise<Territory | null> => {
  const [t] = await db.select().from(territories).where(eq(territories.slug, slug.toLowerCase())).limit(1);
  return t ?? null;
});

export const getTerritoryById = cache(async (id: string): Promise<Territory | null> => {
  const [t] = await db.select().from(territories).where(eq(territories.id, id)).limit(1);
  return t ?? null;
});

export const getTerritoryByHost = cache(async (host: string): Promise<Territory | null> => {
  const rows = await db
    .select({ t: territories })
    .from(territoryDomains)
    .innerJoin(territories, eq(territories.id, territoryDomains.territoryId))
    .where(eq(territoryDomains.host, host.toLowerCase()))
    .limit(1);
  return rows[0]?.t ?? null;
});

/**
 * Le segment [territory] des routes du portail vaut soit un identifiant (accès par
 * chemin terricom.fr/valdeloue), soit « ~hôte » quand le proxy a réécrit une requête
 * arrivée sur un domaine personnalisé (commerces.valdeloue.fr).
 */
export async function resolveTerritoryParam(param: string): Promise<Territory | null> {
  const decoded = decodeURIComponent(param);
  if (decoded.startsWith('~')) return getTerritoryByHost(decoded.slice(1));
  return getTerritoryBySlug(decoded);
}

/**
 * Communes du territoire : celles qui lui sont rattachées, et celles où il a des fiches sans rattachement
 * (vitrine nationale : les communes de ses adhérents directs). Pour un territoire partenaire, les deux coïncident.
 */
export const getTerritoryCommunes = cache(async (territoryId: string): Promise<Commune[]> => {
  const rows = await db
    .select({ c: communes })
    .from(communes)
    .where(
      or(
        inArray(
          communes.id,
          db
            .select({ id: communeMemberships.communeId })
            .from(communeMemberships)
            .where(and(eq(communeMemberships.territoryId, territoryId), isNull(communeMemberships.validTo))),
        ),
        inArray(communes.id, db.selectDistinct({ id: establishments.communeId }).from(establishments).where(eq(establishments.territoryId, territoryId))),
      ),
    )
    .orderBy(asc(communes.name));
  return rows.map((r) => r.c);
});

export async function getCommuneInTerritory(territoryId: string, communeSlug: string): Promise<Commune | null> {
  const list = await getTerritoryCommunes(territoryId);
  return list.find((c) => c.slug === communeSlug) ?? null;
}

/** Adresse de la vitrine nationale (terricom.fr/france). */
export const NATIONAL_SLUG = 'france';

export function isNational(t: Pick<Territory, 'settings'> | null | undefined): boolean {
  return Boolean(t?.settings?.national);
}

/**
 * Vitrine nationale terricom : territoire technique des entreprises en adhésion directe. Créé à la demande
 * (idempotent) ; aucune commune ne lui est rattachée, aucune licence ni équipe territoriale.
 */
export async function ensureNationalTerritory(): Promise<Territory> {
  const [found] = await db.select().from(territories).where(eq(territories.slug, NATIONAL_SLUG)).limit(1);
  if (found) return found;
  const [created] = await db
    .insert(territories)
    .values({
      slug: NATIONAL_SLUG,
      name: 'terricom France',
      legalName: 'terricom',
      kind: 'AUTRE',
      status: 'ACTIVE',
      initials: 'FR',
      tagline: 'Les entreprises adhérentes, partout en France',
      heroTitle: 'Les commerces et savoir-faire de France',
      heroSubtitle:
        'Commerçants, artisans, producteurs et prestataires qui ont rejoint terricom directement, en attendant que leur commune ou leur intercommunalité les rejoigne.',
      homeBlocks: ['search', 'openNow', 'campaign', 'map', 'feed', 'newsletter'],
      centerLat: 46.6,
      centerLng: 2.4,
      defaultZoom: 6,
      contactEmail: 'bonjour@terricom.fr',
      quotaEstablishments: 100000,
      settings: { national: true, claimValidation: 'AUTO', postModeration: 'POST', sirene: { autoSync: false } },
    })
    .onConflictDoNothing()
    .returning();
  if (created) return created;
  const [again] = await db.select().from(territories).where(eq(territories.slug, NATIONAL_SLUG)).limit(1);
  return again;
}

/** Modules activés pour un territoire (absents = activés par défaut pour le socle MVP). */
export const getEnabledModules = cache(async (territoryId: string): Promise<Set<ModuleKey>> => {
  const rows = await db.select().from(territoryModules).where(eq(territoryModules.territoryId, territoryId));
  const map = new Map(rows.map((r) => [r.module as ModuleKey, r.enabled]));
  const enabled = new Set<ModuleKey>();
  for (const m of MODULE_ORDER) {
    const v = map.get(m);
    if (v === undefined ? ['PORTAL', 'MAP', 'NEWSLETTER', 'IMPORT', 'CAMPAIGNS'].includes(m) : v) enabled.add(m);
  }
  return enabled;
});

/**
 * Change le rattachement d'une commune (fusion ou changement d'intercommunalité) : clôt l'ancien
 * rattachement (historique), en ouvre un nouveau et déplace fiches, contenus, marchés, lieux,
 * agents communaux et opérations communales.
 */
export async function moveCommune(communeId: string, toTerritoryId: string): Promise<void> {
  await db.transaction(async (tx) => {
    await tx
      .update(communeMemberships)
      .set({ validTo: sql`current_date` })
      .where(and(eq(communeMemberships.communeId, communeId), isNull(communeMemberships.validTo)));
    await tx.insert(communeMemberships).values({ communeId, territoryId: toTerritoryId });
    await tx.execute(sql`UPDATE establishments SET territory_id = ${toTerritoryId} WHERE commune_id = ${communeId}`);
    await tx.execute(sql`UPDATE posts SET territory_id = ${toTerritoryId} WHERE commune_id = ${communeId}`);
    await tx.execute(sql`UPDATE events SET territory_id = ${toTerritoryId} WHERE commune_id = ${communeId}`);
    await tx.execute(sql`UPDATE jobs SET territory_id = ${toTerritoryId} WHERE commune_id = ${communeId}`);
    await tx.execute(sql`UPDATE markets SET territory_id = ${toTerritoryId} WHERE commune_id = ${communeId}`);
    await tx.execute(sql`UPDATE points_of_interest SET territory_id = ${toTerritoryId} WHERE commune_id = ${communeId}`);
    // Les agents de la mairie suivent leur commune ; ses opérations commerciales aussi (sauf homonymie).
    await tx.execute(sql`UPDATE role_assignments SET territory_id = ${toTerritoryId} WHERE commune_id = ${communeId}`);
    await tx.execute(sql`UPDATE campaigns c SET territory_id = ${toTerritoryId} WHERE c.commune_id = ${communeId}
      AND NOT EXISTS (SELECT 1 FROM campaigns o WHERE o.territory_id = ${toTerritoryId} AND o.slug = c.slug)`);
  });
}

export async function getTerritoriesByIds(ids: string[]): Promise<Territory[]> {
  if (!ids.length) return [];
  return db.select().from(territories).where(inArray(territories.id, ids)).orderBy(asc(territories.name));
}
