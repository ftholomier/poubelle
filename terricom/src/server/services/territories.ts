import { and, asc, eq, inArray, isNull, sql } from 'drizzle-orm';
import { cache } from 'react';
import { MODULE_ORDER, type ModuleKey } from '@/lib/constants';
import { db } from '../db';
import { communeMemberships, communes, territories, territoryDomains, territoryModules } from '../db/schema';

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

/** Communes actuellement rattachées au territoire. */
export const getTerritoryCommunes = cache(async (territoryId: string): Promise<Commune[]> => {
  const rows = await db
    .select({ c: communes })
    .from(communeMemberships)
    .innerJoin(communes, eq(communes.id, communeMemberships.communeId))
    .where(and(eq(communeMemberships.territoryId, territoryId), isNull(communeMemberships.validTo)))
    .orderBy(asc(communes.name));
  return rows.map((r) => r.c);
});

export async function getCommuneInTerritory(territoryId: string, communeSlug: string): Promise<Commune | null> {
  const rows = await db
    .select({ c: communes })
    .from(communeMemberships)
    .innerJoin(communes, eq(communes.id, communeMemberships.communeId))
    .where(and(eq(communeMemberships.territoryId, territoryId), isNull(communeMemberships.validTo), eq(communes.slug, communeSlug)))
    .limit(1);
  return rows[0]?.c ?? null;
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
 * Change le rattachement d'une commune (fusion ou changement d'intercommunalité) :
 * clôt l'ancien rattachement, en ouvre un nouveau et déplace les établissements.
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
  });
}

export async function getTerritoriesByIds(ids: string[]): Promise<Territory[]> {
  if (!ids.length) return [];
  return db.select().from(territories).where(inArray(territories.id, ids)).orderBy(asc(territories.name));
}
