import { and, asc, count, desc, eq, gte, inArray, isNull, lte, or, sql } from 'drizzle-orm';
import { notFound } from 'next/navigation';
import { cache } from 'react';
import { FAMILIES, FAMILY_ORDER, type Family, type ModuleKey, POST_KINDS, type PostKind, PUBLIC_STATUSES } from '@/lib/constants';
import { parisDate } from '@/lib/format';
import { memo } from '../cache';
import { db } from '../db';
import {
  adventDoors,
  campaignParticipants,
  campaigns,
  categories,
  circuitStops,
  circuits,
  communes,
  establishments,
  events,
  jobs,
  markets,
  posts,
  territories,
} from '../db/schema';
import { env } from '../env';
import { requestInfo } from '../request';
import { loadCards, publicStatusFilter, type EstablishmentCard } from './establishments';
import { getEnabledModules, getTerritoryCommunes, resolveTerritoryParam, type Territory } from './territories';

export type PortalContext = {
  territory: Territory;
  base: string;
  modules: Set<ModuleKey>;
  featuredCampaign: typeof campaigns.$inferSelect | null;
  prosCount: number;
  communesCount: number;
  mapConfig: { tileUrl: string; attribution: string };
};

/** Contexte commun à toutes les pages d'un portail (mis en cache pour la requête). */
export const getPortal = cache(async (param: string): Promise<PortalContext> => {
  const territory = await resolveTerritoryParam(param);
  if (!territory || territory.status === 'CHURNED' || territory.status === 'SUSPENDED') notFound();
  const info = await requestInfo();
  const base = info.portalMode === 'host' ? '' : `/${territory.slug}`;
  const [modules, featuredCampaign, prosCount, communeList] = await Promise.all([
    getEnabledModules(territory.id),
    getFeaturedCampaign(territory.id, (territory.settings as { featuredCampaignSlug?: string }).featuredCampaignSlug),
    countPublicEstablishments(territory.id),
    getTerritoryCommunes(territory.id),
  ]);
  return {
    territory,
    base,
    modules,
    featuredCampaign,
    prosCount,
    communesCount: communeList.length,
    mapConfig: { tileUrl: env.MAP_TILE_URL, attribution: env.MAP_TILE_ATTRIBUTION },
  };
});

export function countPublicEstablishments(territoryId: string): Promise<number> {
  return memo(`pros:${territoryId}`, 60_000, async () => {
    const [row] = await db
      .select({ n: count() })
      .from(establishments)
      .where(and(eq(establishments.territoryId, territoryId), publicStatusFilter()));
    return Number(row?.n ?? 0);
  });
}

/** Campagne mise à la une : celle choisie par la collectivité, sinon calendrier de l'Avent en cours ou à venir (90 j), sinon campagne active. */
export async function getFeaturedCampaign(territoryId: string, preferredSlug?: string) {
  const today = parisDate();
  const rows = await db
    .select()
    .from(campaigns)
    .where(and(eq(campaigns.territoryId, territoryId), inArray(campaigns.status, ['ACTIVE', 'SCHEDULED']), gte(campaigns.endsAt, today)))
    .orderBy(asc(campaigns.startsAt));
  if (!rows.length) return null;
  if (preferredSlug) {
    const pref = rows.find((c) => c.slug === preferredSlug);
    if (pref) return pref;
  }
  const soon = new Date(Date.now() + 90 * 86_400_000).toISOString().slice(0, 10);
  return rows.find((c) => c.mode === 'ADVENT' && c.startsAt <= soon) ?? rows.find((c) => c.status === 'ACTIVE' && c.startsAt <= today) ?? rows[0];
}

export function campaignNavLabel(name: string): string {
  const words = name.split(/\s+/);
  return /^(la|le|les|l’|l')$/i.test(words[0]) ? words.slice(0, 2).join(' ') : words[0];
}

/** Toutes les cartes publiques d'un territoire (liste, carte, compteurs), mises en cache 60 s. */
export function allCards(territoryId: string): Promise<EstablishmentCard[]> {
  return memo(`cards:${territoryId}`, 60_000, () => loadCards(and(eq(establishments.territoryId, territoryId), publicStatusFilter()), { limit: 5000 }));
}

export function toMapPoints(cards: EstablishmentCard[], base: string) {
  return cards
    .filter((c) => c.lat !== null && c.lng !== null)
    .map((c) => ({
      id: c.id,
      lat: c.lat!,
      lng: c.lng!,
      name: c.name,
      color: c.color,
      subtitle: `${c.activity} · ${c.communeName}`,
      image: c.coverUrl ? c.coverUrl.replace(/w=\d+/, 'w=460').replace(/&h=\d+/, '') : null,
      href: `${base}${c.path}?src=carte`,
    }));
}

export type FeedItem = {
  id: string;
  kind: PostKind;
  kindLabel: string;
  bg: string;
  title: string;
  body: string;
  who: string;
  whoPath: string | null;
  image: string | null;
  publishedAt: Date;
  promoLabel: string | null;
};

/** Fil des actualités : publications des pros (et de la collectivité) visibles au niveau choisi. */
export async function getFeed(
  territoryId: string,
  opts: { communeId?: string; limit?: number; channel?: 'TERRITOIRE' | 'COMMUNE'; establishmentsOnly?: boolean } = {},
): Promise<FeedItem[]> {
  const now = new Date();
  const conds = [
    eq(posts.territoryId, territoryId),
    eq(posts.status, 'PUBLISHED'),
    or(isNull(posts.expiresAt), gte(posts.expiresAt, now)),
    opts.channel ? sql`${opts.channel} = ANY(${posts.channels})` : undefined,
    opts.communeId ? eq(posts.communeId, opts.communeId) : undefined,
    opts.establishmentsOnly ? eq(posts.authorType, 'ESTABLISHMENT') : undefined,
  ].filter(Boolean);
  const rows = await db
    .select({
      p: posts,
      e: { id: establishments.id, name: establishments.name, slug: establishments.slug, coverUrl: establishments.coverUrl, status: establishments.status },
      c: { slug: communes.slug },
      k: { slug: categories.slug },
    })
    .from(posts)
    .leftJoin(establishments, eq(establishments.id, posts.establishmentId))
    .leftJoin(communes, eq(communes.id, establishments.communeId))
    .leftJoin(categories, eq(categories.id, establishments.categoryId))
    .where(and(...(conds as ReturnType<typeof eq>[])))
    .orderBy(desc(posts.publishedAt))
    .limit((opts.limit ?? 6) + 6);
  const [t] = await db.select({ name: territories.name }).from(territories).where(eq(territories.id, territoryId)).limit(1);
  return rows
    .filter((r) => !r.e || ['PRECREATED', 'TO_COMPLETE', 'CLAIMED', 'VALIDATED'].includes(r.e.status))
    .map(({ p, e, c, k }) => ({
      id: p.id,
      kind: p.kind as PostKind,
      kindLabel: POST_KINDS[p.kind as PostKind].short,
      bg: POST_KINDS[p.kind as PostKind].bg,
      title: p.title,
      body: p.body,
      who: e?.name ?? (p.authorType === 'COMMUNE' ? 'Votre mairie' : (t?.name ?? 'Le territoire')),
      whoPath: e && c && k ? `/${c.slug}/${k.slug}/${e.slug}` : null,
      image: p.imageUrl ?? e?.coverUrl ?? null,
      publishedAt: p.publishedAt ?? p.createdAt,
      promoLabel: p.promoLabel,
    }))
    .slice(0, opts.limit ?? 6);
}

export async function getCircuits(territoryId: string) {
  const rows = await db
    .select({
      c: circuits,
      stops: sql<number>`(SELECT count(*)::int FROM ${circuitStops} s WHERE s.circuit_id = "circuits"."id")`,
    })
    .from(circuits)
    .where(and(eq(circuits.territoryId, territoryId), eq(circuits.status, 'PUBLISHED')))
    .orderBy(asc(circuits.sortOrder));
  return rows.map((r) => ({ ...r.c, stopCount: Number(r.stops) }));
}

export async function countOpenJobs(territoryId: string): Promise<number> {
  const [row] = await db
    .select({ n: count() })
    .from(jobs)
    .where(and(eq(jobs.territoryId, territoryId), eq(jobs.status, 'PUBLISHED')));
  return Number(row?.n ?? 0);
}

export async function upcomingEvents(territoryId: string, opts: { limit?: number; communeId?: string } = {}) {
  const from = new Date(Date.now() - 6 * 3_600_000);
  return db
    .select({ ev: events, e: { name: establishments.name, coverUrl: establishments.coverUrl }, c: { name: communes.name, slug: communes.slug } })
    .from(events)
    .leftJoin(establishments, eq(establishments.id, events.establishmentId))
    .leftJoin(communes, eq(communes.id, events.communeId))
    .where(
      and(
        eq(events.territoryId, territoryId),
        eq(events.status, 'PUBLISHED'),
        gte(sql`coalesce(${events.endsAt}, ${events.startsAt})`, from),
        opts.communeId ? eq(events.communeId, opts.communeId) : undefined,
        // Les événements d'une fiche suspendue ou archivée ne sont plus affichés.
        or(isNull(events.establishmentId), inArray(establishments.status, PUBLIC_STATUSES)),
      ),
    )
    .orderBy(asc(events.startsAt))
    .limit(opts.limit ?? 50);
}

export async function communeMarkets(communeId: string) {
  return db
    .select()
    .from(markets)
    .where(and(eq(markets.communeId, communeId), eq(markets.isActive, true)))
    .orderBy(asc(markets.weekday));
}

/** Compteurs par famille pour une commune (page commune). */
export async function familyCounts(territoryId: string, communeId?: string) {
  const rows = await db
    .select({ family: categories.family, n: count() })
    .from(establishments)
    .innerJoin(categories, eq(categories.id, establishments.categoryId))
    .where(and(eq(establishments.territoryId, territoryId), publicStatusFilter(), communeId ? eq(establishments.communeId, communeId) : undefined))
    .groupBy(categories.family);
  const map = new Map(rows.map((r) => [r.family as Family, Number(r.n)]));
  const total = [...map.values()].reduce((a, b) => a + b, 0);
  return { total, byFamily: FAMILY_ORDER.map((f) => ({ family: f, label: FAMILIES[f].plural, color: FAMILIES[f].color, count: map.get(f) ?? 0 })) };
}

export async function campaignsForTerritory(territoryId: string) {
  return db
    .select()
    .from(campaigns)
    .where(and(eq(campaigns.territoryId, territoryId), inArray(campaigns.status, ['ACTIVE', 'SCHEDULED', 'ENDED'])))
    .orderBy(desc(campaigns.startsAt));
}

export { lte };

/** Nombre de professionnels visibles par commune (index des communes, carte de densité). */
export function communeCounts(territoryId: string): Promise<Map<string, number>> {
  return memo(`communeCounts:${territoryId}`, 60_000, async () => {
    const rows = await db
      .select({ communeId: establishments.communeId, n: count() })
      .from(establishments)
      .where(and(eq(establishments.territoryId, territoryId), publicStatusFilter()))
      .groupBy(establishments.communeId);
    return new Map(rows.map((r) => [r.communeId, Number(r.n)]));
  });
}

/** Campagne publique avec ses participants visibles et son calendrier (les cases futures restent secrètes). */
export async function getPublicCampaign(territoryId: string, slug: string) {
  const [camp] = await db
    .select()
    .from(campaigns)
    .where(and(eq(campaigns.territoryId, territoryId), eq(campaigns.slug, slug), inArray(campaigns.status, ['ACTIVE', 'SCHEDULED', 'ENDED'])))
    .limit(1);
  if (!camp) return null;
  const rows = await db
    .select({ id: campaignParticipants.establishmentId, offer: campaignParticipants.offerLabel, offerDescription: campaignParticipants.offerDescription })
    .from(campaignParticipants)
    .where(and(eq(campaignParticipants.campaignId, camp.id), eq(campaignParticipants.status, 'JOINED')));
  const offers = new Map(rows.map((r) => [r.id, r]));
  const cards = rows.length
    ? (
        await loadCards(
          and(
            inArray(
              establishments.id,
              rows.map((r) => r.id),
            ),
            publicStatusFilter(),
          ),
          { limit: 500 },
        )
      ).sort((a, b) => Number(Boolean(b.coverUrl)) - Number(Boolean(a.coverUrl)) || b.completeness - a.completeness)
    : [];
  const today = parisDate();
  const doors =
    camp.mode === 'ADVENT'
      ? await db
          .select({ day: adventDoors.day, title: adventDoors.title, establishmentId: adventDoors.establishmentId })
          .from(adventDoors)
          .where(eq(adventDoors.campaignId, camp.id))
          .orderBy(asc(adventDoors.day))
      : [];
  const start = camp.startsAt;
  const byId = new Map(cards.map((c) => [c.id, c]));
  const calendar = doors.map((d) => {
    const date = addDaysIsoLocal(start, d.day - 1);
    const open = date <= today;
    const e = d.establishmentId ? byId.get(d.establishmentId) : undefined;
    return { day: d.day, date, open, title: open ? d.title : null, path: open && e ? e.path : null };
  });
  return {
    campaign: camp,
    participants: cards.map((c) => ({ ...c, offer: offers.get(c.id)?.offer ?? null, offerDescription: offers.get(c.id)?.offerDescription ?? null })),
    calendar,
    today,
  };
}

function addDaysIsoLocal(date: string, n: number): string {
  const d = new Date(`${date}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + n);
  return d.toISOString().slice(0, 10);
}

/** Événement public (avec l'établissement organisateur s'il est visible). */
export async function getPublicEvent(territoryId: string, slug: string) {
  const [row] = await db
    .select({ ev: events, c: { name: communes.name, slug: communes.slug } })
    .from(events)
    .leftJoin(communes, eq(communes.id, events.communeId))
    .where(and(eq(events.territoryId, territoryId), eq(events.slug, slug), eq(events.status, 'PUBLISHED')))
    .limit(1);
  if (!row) return null;
  const organizer = row.ev.establishmentId
    ? ((await loadCards(and(eq(establishments.id, row.ev.establishmentId), publicStatusFilter()), { limit: 1 }))[0] ?? null)
    : null;
  if (row.ev.establishmentId && !organizer) return null;
  return { event: row.ev, commune: row.c, organizer };
}

/** Offres d'emploi publiées dont l'entreprise est visible. */
export async function listPublicJobs(territoryId: string) {
  const rows = await db
    .select({ j: jobs })
    .from(jobs)
    .innerJoin(establishments, eq(establishments.id, jobs.establishmentId))
    .where(
      and(
        eq(jobs.territoryId, territoryId),
        eq(jobs.status, 'PUBLISHED'),
        inArray(establishments.status, PUBLIC_STATUSES),
        or(isNull(jobs.expiresAt), gte(jobs.expiresAt, new Date())),
      ),
    )
    .orderBy(desc(jobs.publishedAt));
  const cards = rows.length ? await loadCards(inArray(establishments.id, [...new Set(rows.map((r) => r.j.establishmentId))]), { limit: 1000 }) : [];
  const byId = new Map(cards.map((c) => [c.id, c]));
  return rows.map((r) => ({ job: r.j, company: byId.get(r.j.establishmentId)! })).filter((r) => r.company);
}

export async function getPublicJob(territoryId: string, slug: string) {
  const [row] = await db
    .select({ j: jobs })
    .from(jobs)
    .where(and(eq(jobs.territoryId, territoryId), eq(jobs.slug, slug), eq(jobs.status, 'PUBLISHED')))
    .limit(1);
  if (!row) return null;
  const [company] = await loadCards(and(eq(establishments.id, row.j.establishmentId), publicStatusFilter()), { limit: 1 });
  if (!company) return null;
  const [est] = await db
    .select({ street: establishments.street, postalCode: establishments.postalCode })
    .from(establishments)
    .where(eq(establishments.id, company.id))
    .limit(1);
  return { job: row.j, company, address: est };
}
