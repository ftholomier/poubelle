import { and, eq, inArray, isNull, or, sql, type SQL } from 'drizzle-orm';
import { cache } from 'react';
import type { Family } from '@/lib/constants';
import { fmtDistance, haversine, parisDate } from '@/lib/format';
import { normalizeText } from '@/lib/slug';
import { answerSearch, interpretSearch } from '../ai/features';
import { memo } from '../cache';
import { interpretQuery, type SearchIntent, type SearchVocabulary } from '../ai/rules';
import type { AiContext } from '../ai/client';
import { db } from '../db';
import {
  attributes,
  campaignParticipants,
  campaigns,
  categories,
  communeMemberships,
  communes,
  establishmentAttributes,
  establishmentCategories,
  establishments,
} from '../db/schema';
import { loadCards, publicStatusFilter, type EstablishmentCard } from './establishments';

export type SearchParams = {
  q?: string;
  families?: Family[];
  categorySlugs?: string[];
  attributeSlugs?: string[];
  communeSlugs?: string[];
  openNow?: boolean;
  openTonight?: boolean;
  near?: { lat: number; lng: number } | null;
  limit?: number;
  withAnswer?: boolean;
  /** Module « Assistant IA » actif sur le territoire (sinon : interprétation par règles, sans réponse rédigée). */
  ai?: boolean;
};

export type SearchResultItem = EstablishmentCard & { distanceM: number | null; distance: string };

export type SearchResponse = {
  items: SearchResultItem[];
  total: number;
  intent: (SearchIntent & { source: 'ai' | 'rules' }) | null;
  answer: { text: string; meta: string; source: 'ai' | 'rules' } | null;
};

/** Vocabulaire du territoire : catégories, services/labels, communes (pour interpréter les requêtes). */
export const getVocabulary = cache(async (territoryId: string): Promise<SearchVocabulary> => {
  const [cats, attrs, coms] = await Promise.all([
    db
      .select({ slug: categories.slug, name: categories.name, family: categories.family, synonyms: categories.synonyms })
      .from(categories)
      .where(and(or(isNull(categories.territoryId), eq(categories.territoryId, territoryId)), eq(categories.isActive, true))),
    db
      .select({ slug: attributes.slug, label: attributes.label })
      .from(attributes)
      .where(or(isNull(attributes.territoryId), eq(attributes.territoryId, territoryId))),
    db
      .select({ slug: communes.slug, name: communes.name })
      .from(communeMemberships)
      .innerJoin(communes, eq(communes.id, communeMemberships.communeId))
      .where(and(eq(communeMemberships.territoryId, territoryId), isNull(communeMemberships.validTo))),
  ]);
  return {
    categories: cats.map((c) => ({ ...c, family: c.family as Family })),
    attributes: attrs,
    communes: coms,
  };
});

/** Requête plein texte avec préfixes (« boulang » trouve « boulangerie »), en français et sans accents. */
function tsQuery(words: string[], mode: 'and' | 'or'): SQL | null {
  const clean = words.map((w) => normalizeText(w).replace(/[^a-z0-9]/g, '')).filter((w) => w.length >= 2);
  if (!clean.length) return null;
  const expr = clean.map((w) => `${w}:*`).join(mode === 'and' ? ' & ' : ' | ');
  return sql`to_tsquery('french', ${expr})`;
}

function attributeFilter(slugs: string[]): SQL[] {
  return slugs.map(
    (slug) => sql`EXISTS (SELECT 1 FROM ${establishmentAttributes} ea JOIN ${attributes} a ON a.id = ea.attribute_id
      WHERE ea.establishment_id = ${establishments.id} AND a.slug = ${slug})`,
  );
}

function categoryFilter(slugs: string[]): SQL {
  return or(
    inArray(categories.slug, slugs),
    sql`EXISTS (SELECT 1 FROM ${establishmentCategories} ec JOIN ${categories} k2 ON k2.id = ec.category_id
      WHERE ec.establishment_id = ${establishments.id} AND k2.slug IN (${sql.join(
        slugs.map((s) => sql`${s}`),
        sql`, `,
      )}))`,
  )!;
}

export async function searchTerritory(
  territory: { id: string; centerLat: number | null; centerLng: number | null },
  params: SearchParams,
  ctx: AiContext = {},
): Promise<SearchResponse> {
  const q = (params.q ?? '').trim().slice(0, 200);
  const vocab = await getVocabulary(territory.id);
  // L'interprétation d'une même requête est mise en cache 10 min (coût et latence de l'IA).
  const aiAllowed = params.ai !== false;
  const intent = !q
    ? null
    : aiAllowed
      ? await memo(`intent:${territory.id}:${normalizeText(q)}`, 600_000, () => interpretSearch(q, vocab, { ...ctx, territoryId: territory.id }))
      : { ...interpretQuery(q, vocab), source: 'rules' as const };
  const natural = Boolean(intent?.natural);

  const conds: SQL[] = [eq(establishments.territoryId, territory.id), publicStatusFilter()];
  let rank: SQL | null = null;

  const families = params.families?.length ? params.families : natural && !intent?.categorySlugs.length ? (intent?.families ?? []) : [];
  if (families.length) conds.push(inArray(categories.family, families));
  const cats = [...(params.categorySlugs ?? []), ...(natural ? (intent?.categorySlugs ?? []) : [])];
  if (cats.length) conds.push(categoryFilter([...new Set(cats)]));
  const attrs = [...new Set([...(params.attributeSlugs ?? []), ...(natural ? (intent?.attributeSlugs ?? []) : [])])];
  conds.push(...attributeFilter(attrs));
  const communeSlugs = [...new Set([...(params.communeSlugs ?? []), ...(natural ? (intent?.communeSlugs ?? []) : [])])];
  if (communeSlugs.length) conds.push(inArray(communes.slug, communeSlugs));

  const openNow = Boolean(params.openNow || (natural && intent?.openNow));
  const openTonight = Boolean(params.openTonight || (natural && intent?.openTonight));

  let cards: EstablishmentCard[] = [];
  if (q && !natural) {
    const words = q.split(/\s+/);
    for (const mode of ['and', 'or'] as const) {
      const tq = tsQuery(words, mode);
      if (!tq) break;
      const match = sql`(${establishments.searchVector} @@ ${tq} OR similarity(f_unaccent(${establishments.name}), f_unaccent(${q})) > 0.35)`;
      rank = sql`(ts_rank(${establishments.searchVector}, ${tq}) + similarity(f_unaccent(${establishments.name}), f_unaccent(${q})))`;
      cards = await loadCards(and(...conds, match), { limit: 300, orderBy: [sql`${rank} DESC`] });
      if (cards.length) break;
    }
  } else if (natural && intent && !cats.length && !families.length && !attrs.length && intent.keywords.length) {
    // Requête naturelle sans filtre reconnu : on cherche les mots-clés restants.
    const tq = tsQuery(intent.keywords, 'or');
    if (tq) {
      rank = sql`ts_rank(${establishments.searchVector}, ${tq})`;
      cards = await loadCards(and(...conds, sql`${establishments.searchVector} @@ ${tq}`), { limit: 300, orderBy: [sql`${rank} DESC`] });
    }
  } else {
    cards = await loadCards(and(...conds), { limit: 5000 });
  }

  if (openNow) cards = cards.filter((c) => c.open.open);
  if (openTonight) cards = cards.filter((c) => c.open.openTonight);

  const ref = params.near ?? (territory.centerLat && territory.centerLng ? { lat: territory.centerLat, lng: territory.centerLng } : null);
  let items: SearchResultItem[] = cards.map((c) => {
    const d = ref && c.lat && c.lng ? haversine(ref, { lat: c.lat, lng: c.lng }) : null;
    return { ...c, distanceM: d, distance: fmtDistance(d) };
  });
  if (!q || natural) {
    const dist = (a: SearchResultItem, b: SearchResultItem) => (a.distanceM ?? 1e12) - (b.distanceM ?? 1e12);
    if (params.near) items.sort((a, b) => Number(b.open.open) - Number(a.open.open) || dist(a, b));
    else if (natural) {
      // Recherche en langage naturel : les pros engagés dans une campagne en cours remontent pour les idées cadeaux.
      const boost = intent?.kind === 'gift' || intent?.kind === 'local' ? await activeOffers(items.slice(0, 400).map((i) => i.id)) : new Map();
      items.sort(
        (a, b) =>
          Number(b.open.open) - Number(a.open.open) ||
          Number(boost.has(b.id)) - Number(boost.has(a.id)) ||
          Number(b.isFeatured) - Number(a.isFeatured) ||
          Number(Boolean(b.coverUrl)) - Number(Boolean(a.coverUrl)) ||
          dist(a, b),
      );
    } else
      // Tri « recommandés » : ouverts d'abord, puis mises en avant (Premium), fiches complètes, proximité.
      items.sort(
        (a, b) =>
          Number(b.open.open) - Number(a.open.open) ||
          Number(b.isFeatured) - Number(a.isFeatured) ||
          Number(Boolean(b.coverUrl)) - Number(Boolean(a.coverUrl)) ||
          Math.round(b.completeness / 20) - Math.round(a.completeness / 20) ||
          dist(a, b),
      );
  }
  const total = items.length;
  items = items.slice(0, params.limit ?? 200);

  let answer: SearchResponse['answer'] = null;
  if (aiAllowed && params.withAnswer !== false && intent?.natural) {
    const top = items.slice(0, 6);
    const offers = top.length ? await activeOffers(top.map((t) => t.id)) : new Map();
    const answerKey = `answer:${territory.id}:${normalizeText(q)}:${top.map((t) => t.id).join(',')}`;
    answer = await memo(answerKey, 600_000, async () =>
      answerSearch(
        q,
        intent,
        top.map((t) => ({
          name: t.name,
          activity: t.activity,
          communeName: t.communeName,
          family: t.family,
          openLabel: t.open.shortLabel,
          until: t.open.until,
          distance: t.distance,
          offer: offers.get(t.id)?.offer ?? null,
          campaign: offers.get(t.id)?.campaign ?? null,
        })),
        { ...ctx, territoryId: territory.id },
      ),
    );
  }
  return { items, total, intent, answer };
}

/** Offres de campagne en cours pour une liste d'établissements. */
export async function activeOffers(ids: string[]): Promise<Map<string, { offer: string | null; campaign: string }>> {
  if (!ids.length) return new Map();
  const rows = await db
    .select({ id: campaignParticipants.establishmentId, offer: campaignParticipants.offerLabel, campaign: campaigns.name })
    .from(campaignParticipants)
    .innerJoin(campaigns, eq(campaigns.id, campaignParticipants.campaignId))
    .where(
      and(
        inArray(campaignParticipants.establishmentId, ids),
        eq(campaignParticipants.status, 'JOINED'),
        inArray(campaigns.status, ['ACTIVE', 'SCHEDULED']),
        sql`${campaigns.endsAt} >= ${parisDate()}`,
      ),
    );
  return new Map(rows.map((r) => [r.id, { offer: r.offer, campaign: r.campaign }]));
}

/** Réduit un résultat aux champs affichés par l'explorateur. */
export function toExplorerItem(i: SearchResultItem): import('@/lib/explorer').ExplorerItem {
  return {
    id: i.id,
    name: i.name,
    path: i.path,
    activity: i.activity,
    color: i.color,
    communeName: i.communeName,
    coverUrl: i.coverUrl,
    isOpen: i.open.open,
    openLabel: i.open.unknown ? '' : i.open.shortLabel,
    tags: i.tags.slice(0, 3),
    distance: i.distance,
  };
}
