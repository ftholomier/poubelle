import { and, asc, eq, isNull, or, sql } from 'drizzle-orm';
import { cache } from 'react';
import { FAMILY_ORDER, type Family } from '@/lib/constants';
import { db } from '../db';
import { categories, territoryCategories } from '../db/schema';

export type TerritoryCategory = {
  id: string;
  slug: string;
  family: Family;
  /** Nom affiché sur le portail du territoire (personnalisé ou nom commun). */
  name: string;
  baseName: string;
  hidden: boolean;
  /** Catégorie propre au territoire (créée par la collectivité). */
  own: boolean;
  count: number;
};

/** Nom affiché d'une catégorie : personnalisation du territoire, sinon nom commun. */
export const categoryDisplayName = sql<string>`coalesce(${territoryCategories.label}, ${categories.name})`;

/** Catégories d'un territoire (communes et propres), avec personnalisation et nombre de fiches. */
export const territoryCategoryList = cache(async (territoryId: string): Promise<TerritoryCategory[]> => {
  const rows = await db
    .select({
      id: categories.id,
      slug: categories.slug,
      family: categories.family,
      baseName: categories.name,
      label: territoryCategories.label,
      hidden: territoryCategories.hidden,
      own: sql<boolean>`${categories.territoryId} is not null`,
      count: sql<number>`(select count(*)::int from establishments e where e.category_id = ${categories.id} and e.territory_id = ${territoryId} and e.status <> 'ARCHIVED')`,
    })
    .from(categories)
    .leftJoin(territoryCategories, and(eq(territoryCategories.categoryId, categories.id), eq(territoryCategories.territoryId, territoryId)))
    .where(and(eq(categories.isActive, true), or(isNull(categories.territoryId), eq(categories.territoryId, territoryId))))
    .orderBy(asc(categories.sortOrder), asc(categories.name));
  return rows
    .map((r) => ({
      id: r.id,
      slug: r.slug,
      family: r.family as Family,
      name: r.label ?? r.baseName,
      baseName: r.baseName,
      hidden: r.hidden ?? false,
      own: Boolean(r.own),
      count: Number(r.count),
    }))
    .sort((a, b) => FAMILY_ORDER.indexOf(a.family) - FAMILY_ORDER.indexOf(b.family) || a.name.localeCompare(b.name, 'fr'));
});

/** Catégories proposées dans les formulaires (fiche, création, inscription) : non masquées. */
export async function pickableCategories(territoryId: string, keepId?: string | null) {
  return (await territoryCategoryList(territoryId))
    .filter((c) => !c.hidden || c.id === keepId)
    .map((c) => ({ id: c.id, name: c.name, family: c.family }))
    .sort((a, b) => a.name.localeCompare(b.name, 'fr'));
}
