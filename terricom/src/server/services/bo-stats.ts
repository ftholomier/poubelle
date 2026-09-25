import { sql } from 'drizzle-orm';
import { cache } from 'react';
import { db } from '../db';
import type { BoContext } from './backoffice';

/**
 * Indicateurs du back-office : adoption, météo du commerce, podium des communes,
 * activité en direct, statistiques consolidées. Toutes les requêtes respectent le périmètre.
 */

function communeFilter(ctx: BoContext, column: string) {
  return ctx.communeIds ? sql`and ${sql.raw(column)} = any(${sql.param(ctx.communeIds)}::uuid[])` : sql``;
}

async function rows<T>(query: ReturnType<typeof sql>): Promise<T[]> {
  const res = await db.execute(query);
  return res.rows as T[];
}

export type CommuneRow = {
  id: string;
  name: string;
  slug: string;
  lat: number | null;
  lng: number | null;
  total: number;
  claimed: number;
  complete: number;
  views: number;
  calls: number;
  directions: number;
};

/** Par commune : fiches, fiches revendiquées et complètes, audience des 12 derniers mois. */
export const communeStats = cache(async (ctx: BoContext): Promise<CommuneRow[]> => {
  const base = await rows<Omit<CommuneRow, 'views' | 'calls' | 'directions'>>(sql`
    select c.id, c.name, c.slug, c.lat, c.lng,
      (count(e.id) filter (where e.status <> 'ARCHIVED'))::int as total,
      (count(e.id) filter (where e.status <> 'ARCHIVED' and exists (select 1 from company_members m where m.company_id = e.company_id)))::int as claimed,
      (count(e.id) filter (where e.status <> 'ARCHIVED' and e.completeness >= 70))::int as complete
    from communes c
    join commune_memberships cm on cm.commune_id = c.id and cm.valid_to is null and cm.territory_id = ${ctx.territory.id}
    left join establishments e on e.commune_id = c.id and e.territory_id = ${ctx.territory.id}
    where true ${communeFilter(ctx, 'c.id')}
    group by c.id
    order by total desc, c.name asc
  `);
  const audience = await rows<{ commune_id: string; type: string; n: number }>(sql`
    select commune_id, type, count(*)::int as n
    from analytics_events
    where territory_id = ${ctx.territory.id} and occurred_at >= now() - interval '12 months'
      and type in ('EST_VIEW', 'PHONE_CLICK', 'DIRECTIONS_CLICK') ${communeFilter(ctx, 'commune_id')}
    group by commune_id, type
  `);
  const byCommune = new Map<string, Record<string, number>>();
  for (const a of audience) {
    if (!a.commune_id) continue;
    const m = byCommune.get(a.commune_id) ?? {};
    m[a.type] = a.n;
    byCommune.set(a.commune_id, m);
  }
  return base.map((c) => {
    const m = byCommune.get(c.id) ?? {};
    return { ...c, views: m.EST_VIEW ?? 0, calls: m.PHONE_CLICK ?? 0, directions: m.DIRECTIONS_CLICK ?? 0 };
  });
});

/** Entonnoir d'adoption : référencées, revendiquées, complètes, actives ce mois. */
export async function adoptionFunnel(ctx: BoContext) {
  const [r] = await rows<{ total: number; claimed: number; complete: number; active: number }>(sql`
    select
      (count(*))::int as total,
      (count(*) filter (where exists (select 1 from company_members m where m.company_id = e.company_id)))::int as claimed,
      (count(*) filter (where e.completeness >= 70))::int as complete,
      (count(*) filter (where e.last_activity_at >= now() - interval '30 days'
        or exists (select 1 from posts p where p.establishment_id = e.id and p.published_at >= now() - interval '30 days')))::int as active
    from establishments e
    where e.territory_id = ${ctx.territory.id} and e.status <> 'ARCHIVED' ${communeFilter(ctx, 'e.commune_id')}
  `);
  return r ?? { total: 0, claimed: 0, complete: 0, active: 0 };
}

export type Weather = { commune: string; index: number; label: string; posts: number; newClaims: number; searchTrend: number | null };

const WEATHER: [number, string][] = [
  [70, 'Grand soleil'],
  [55, 'Éclaircies'],
  [40, 'Nuageux'],
  [25, 'Averses'],
  [0, 'Temps gris'],
];

export function weatherLabel(index: number): string {
  return WEATHER.find(([min]) => index >= min)![1];
}

/**
 * « Météo du commerce local » : indice d'activité 0–100 par commune, combinant taux de
 * revendication, fiches actives, rythme de publication et tendance d'audience sur 7 jours.
 */
export async function commerceWeather(ctx: BoContext, limit = 3): Promise<{ top: Weather[]; territory: Weather }> {
  const data = await rows<{
    id: string;
    name: string;
    total: number;
    claimed: number;
    active: number;
    posts: number;
    new_claims: number;
    views_now: number;
    views_prev: number;
  }>(sql`
    with scope as (
      select e.* from establishments e
      where e.territory_id = ${ctx.territory.id} and e.status <> 'ARCHIVED' ${communeFilter(ctx, 'e.commune_id')}
    )
    select c.id, c.name,
      count(s.id)::int as total,
      (count(s.id) filter (where exists (select 1 from company_members m where m.company_id = s.company_id)))::int as claimed,
      (count(s.id) filter (where s.last_activity_at >= now() - interval '30 days'))::int as active,
      (select count(*)::int from posts p where p.commune_id = c.id and p.status = 'PUBLISHED' and p.published_at >= now() - interval '7 days') as posts,
      (select count(*)::int from claims cl join establishments e2 on e2.id = cl.establishment_id where e2.commune_id = c.id and cl.status = 'APPROVED' and cl.reviewed_at >= now() - interval '7 days') as new_claims,
      (select count(*)::int from analytics_events a where a.commune_id = c.id and a.type = 'EST_VIEW' and a.occurred_at >= now() - interval '7 days') as views_now,
      (select count(*)::int from analytics_events a where a.commune_id = c.id and a.type = 'EST_VIEW' and a.occurred_at >= now() - interval '14 days' and a.occurred_at < now() - interval '7 days') as views_prev
    from communes c
    join scope s on s.commune_id = c.id
    group by c.id
    having count(s.id) >= 5
    order by count(s.id) desc
    limit 12
  `);
  const score = (d: (typeof data)[number]) => {
    const claimRate = d.total ? d.claimed / d.total : 0;
    const activeRate = d.total ? d.active / d.total : 0;
    const postScore = Math.min(1, d.posts / Math.max(1, 0.04 * d.total));
    const ratio = d.views_prev ? d.views_now / d.views_prev : 1;
    const trendScore = Math.max(0, Math.min(1, 0.5 + (ratio - 1)));
    return Math.round(100 * (0.3 * claimRate + 0.25 * Math.min(1, activeRate * 2) + 0.25 * postScore + 0.2 * trendScore));
  };
  const all = data.map((d) => ({
    commune: d.name,
    index: score(d),
    label: '',
    posts: d.posts,
    newClaims: d.new_claims,
    searchTrend: d.views_prev ? Math.round(((d.views_now - d.views_prev) / d.views_prev) * 100) : null,
  }));
  for (const w of all) w.label = weatherLabel(w.index);
  const top = [...all].sort((a, b) => b.index - a.index).slice(0, limit);
  // Vue d'ensemble (toutes communes)
  const [t] = await rows<{ posts: number; new_claims: number; s_now: number; s_prev: number }>(sql`
    select
      (select count(*)::int from posts p where p.territory_id = ${ctx.territory.id} and p.status = 'PUBLISHED' and p.published_at >= now() - interval '7 days' ${communeFilter(ctx, 'p.commune_id')}) as posts,
      (select count(*)::int from claims cl join establishments e on e.id = cl.establishment_id where cl.territory_id = ${ctx.territory.id} and cl.status = 'APPROVED' and cl.reviewed_at >= now() - interval '7 days' ${communeFilter(ctx, 'e.commune_id')}) as new_claims,
      (select count(*)::int from analytics_events a where a.territory_id = ${ctx.territory.id} and a.type = 'SEARCH' and a.occurred_at >= now() - interval '7 days' ${communeFilter(ctx, 'a.commune_id')}) as s_now,
      (select count(*)::int from analytics_events a where a.territory_id = ${ctx.territory.id} and a.type = 'SEARCH' and a.occurred_at >= now() - interval '14 days' and a.occurred_at < now() - interval '7 days' ${communeFilter(ctx, 'a.commune_id')}) as s_prev
  `);
  const avg = all.length ? Math.round(all.reduce((s, w) => s + w.index, 0) / all.length) : 0;
  const lead = top[0];
  return {
    top,
    territory: {
      commune: lead?.commune ?? ctx.scopeName,
      index: lead ? lead.index : avg,
      label: lead ? lead.label : weatherLabel(avg),
      posts: t?.posts ?? 0,
      newClaims: t?.new_claims ?? 0,
      searchTrend: t && t.s_prev ? Math.round(((t.s_now - t.s_prev) / t.s_prev) * 100) : null,
    },
  };
}

/** À faire : revendications, horaires obsolètes, publications à modérer, lettres prêtes. */
export async function todoCounts(ctx: BoContext) {
  const [r] = await rows<{ claims: number; stale: number; posts: number; letters: number }>(sql`
    select
      (select count(*)::int from claims cl join establishments e on e.id = cl.establishment_id
        where cl.territory_id = ${ctx.territory.id} and cl.status in ('PENDING', 'NEEDS_INFO') ${communeFilter(ctx, 'e.commune_id')}) as claims,
      (select count(*)::int from establishments e
        where e.territory_id = ${ctx.territory.id} and e.status in ('CLAIMED', 'VALIDATED', 'TO_COMPLETE') ${communeFilter(ctx, 'e.commune_id')}
          and exists (select 1 from opening_hours h where h.establishment_id = e.id)
          and (e.hours_confirmed_at is null or e.hours_confirmed_at < now() - interval '6 months')) as stale,
      (select count(*)::int from posts p where p.territory_id = ${ctx.territory.id} and p.status = 'PENDING' ${communeFilter(ctx, 'p.commune_id')}) as posts,
      (select count(*)::int from newsletters n where n.territory_id = ${ctx.territory.id} and n.company_id is null and n.status in ('DRAFT', 'SCHEDULED')
        and ${ctx.commune ? sql`n.commune_id = ${ctx.commune.id}` : sql`n.commune_id is null`}) as letters
  `);
  return r ?? { claims: 0, stale: 0, posts: 0, letters: 0 };
}

export type FeedItem = { text: string; at: Date; color: string };

const POST_VERB: Record<string, string> = {
  NEWS: 'une actualité',
  PROMO: 'une promo',
  NOUVEAUTE: 'une nouveauté',
  EVENT: 'un événement',
  HOURS: 'ses horaires',
  JOB: 'une offre d’emploi',
};

/** « En direct du territoire » : publications, revendications, abonnements, suspensions, circuits. */
export async function liveFeed(ctx: BoContext, limit = 6): Promise<FeedItem[]> {
  const t = ctx.territory.id;
  const [postsRows, claimRows, planRows, suspended, subs, passportsRows] = await Promise.all([
    rows<{ name: string; kind: string; at: Date }>(sql`
      select e.name, p.kind, p.published_at as at from posts p join establishments e on e.id = p.establishment_id
      where p.territory_id = ${t} and p.status = 'PUBLISHED' and p.published_at <= now() ${communeFilter(ctx, 'e.commune_id')}
      order by p.published_at desc limit 4`),
    rows<{ who: string; name: string; at: Date; status: string }>(sql`
      select trim(u.first_name || ' ' || u.last_name) as who, e.name, cl.created_at as at, cl.status from claims cl
      join users u on u.id = cl.user_id join establishments e on e.id = cl.establishment_id
      where cl.territory_id = ${t} ${communeFilter(ctx, 'e.commune_id')}
      order by cl.created_at desc limit 3`),
    rows<{ name: string; plan: string; at: Date }>(sql`
      select e.name, s.plan, s.started_at as at from company_subscriptions s
      join establishments e on e.company_id = s.company_id
      where e.territory_id = ${t} and s.plan <> 'ESSENTIEL' and s.started_at >= now() - interval '30 days' ${communeFilter(ctx, 'e.commune_id')}
      order by s.started_at desc limit 2`),
    rows<{ name: string; reason: string | null; at: Date }>(sql`
      select e.name, e.suspended_reason as reason, e.updated_at as at from establishments e
      where e.territory_id = ${t} and e.status = 'SUSPENDED' and (e.suspended_reason is null or e.suspended_reason not like 'Création en attente%') ${communeFilter(ctx, 'e.commune_id')}
      order by e.updated_at desc limit 1`),
    rows<{ n: number }>(sql`
      select count(*)::int as n from subscribers where territory_id = ${t} and status = 'CONFIRMED' and confirmed_at >= now() - interval '7 days'`),
    rows<{ name: string; n: number; at: Date }>(sql`
      select c.name, count(p.id)::int as n, least(max(p.created_at), now()) as at from passports p join circuits c on c.id = p.circuit_id
      where c.territory_id = ${t} and p.created_at >= now() - interval '4 days'
      group by c.id order by n desc limit 1`),
  ]);
  const feed: FeedItem[] = [];
  for (const p of postsRows)
    feed.push({ text: `${p.name} a publié ${POST_VERB[p.kind] ?? 'une actualité'}`, at: new Date(p.at), color: p.kind === 'PROMO' ? '#F4B266' : '#C8892A' });
  for (const c of claimRows)
    feed.push({
      text: c.status === 'APPROVED' ? `${c.who} gère désormais ${c.name}` : `${c.who || 'Un professionnel'} a revendiqué ${c.name}`,
      at: new Date(c.at),
      color: '#3E6FB0',
    });
  for (const s of planRows)
    feed.push({ text: `${s.name} est passé ${s.plan === 'PREMIUM' ? 'Premium' : 'Communication'}`, at: new Date(s.at), color: '#7A5BB5' });
  for (const s of suspended)
    feed.push({ text: `${s.name} : fiche suspendue${s.reason ? ` (${s.reason.toLowerCase()})` : ''}`, at: new Date(s.at), color: '#D95C4E' });
  if (subs[0]?.n) feed.push({ text: `${subs[0].n} inscrit${subs[0].n > 1 ? 's' : ''} à la newsletter cette semaine`, at: new Date(), color: '#1F6B52' });
  for (const p of passportsRows)
    if (p.n) feed.push({ text: `Circuit « ${p.name} » : ${p.n} passeport${p.n > 1 ? 's' : ''} ces derniers jours`, at: new Date(p.at), color: '#1F6B52' });
  return feed.sort((a, b) => b.at.getTime() - a.at.getTime()).slice(0, limit);
}

// ─── Statistiques consolidées (C7) ─────────────────────────────────────────

export type StatsFilter = { since: Date; communeId: string | null };

function statsScope(ctx: BoContext, f: StatsFilter, alias = 'a') {
  const communes = f.communeId ? [f.communeId] : ctx.communeIds;
  return sql`${sql.raw(alias)}.territory_id = ${ctx.territory.id} and ${sql.raw(alias)}.occurred_at >= ${f.since} ${
    communes ? sql`and ${sql.raw(alias)}.commune_id = any(${sql.param(communes)}::uuid[])` : sql``
  }`;
}

/** Indicateurs globaux de la période. */
export async function consolidatedKpis(ctx: BoContext, f: StatsFilter) {
  const [r] = await rows<{
    visitors: number;
    searches: number;
    nl_searches: number;
    est_views: number;
    calls: number;
    directions: number;
    messages: number;
  }>(sql`
    select
      (select coalesce(sum(u), 0)::int from (
        select count(distinct a.visitor_hash) as u from analytics_events a
        where ${statsScope(ctx, f)} and a.type in ('PAGE_VIEW', 'EST_VIEW')
        group by (a.occurred_at at time zone 'Europe/Paris')::date
      ) d) as visitors,
      (select count(*)::int from analytics_events a where ${statsScope(ctx, f)} and a.type = 'SEARCH') as searches,
      (select count(*)::int from analytics_events a where ${statsScope(ctx, f)} and a.type = 'SEARCH' and array_length(regexp_split_to_array(trim(coalesce(a.query, '')), '\\s+'), 1) >= 3) as nl_searches,
      (select count(*)::int from analytics_events a where ${statsScope(ctx, f)} and a.type = 'EST_VIEW') as est_views,
      (select count(*)::int from analytics_events a where ${statsScope(ctx, f)} and a.type = 'PHONE_CLICK') as calls,
      (select count(*)::int from analytics_events a where ${statsScope(ctx, f)} and a.type = 'DIRECTIONS_CLICK') as directions,
      (select count(*)::int from analytics_events a where ${statsScope(ctx, f)} and a.type = 'CONTACT_SENT') as messages
  `);
  return r ?? { visitors: 0, searches: 0, nl_searches: 0, est_views: 0, calls: 0, directions: 0, messages: 0 };
}

/** Visiteurs par mois (12 derniers mois glissants, fuseau de Paris). */
export async function monthlyVisitors(ctx: BoContext, communeId: string | null) {
  const since = new Date();
  since.setUTCMonth(since.getUTCMonth() - 11, 1);
  since.setUTCHours(0, 0, 0, 0);
  const data = await rows<{ month: string; visitors: number }>(sql`
    select to_char(day, 'YYYY-MM') as month, sum(u)::int as visitors from (
      select (a.occurred_at at time zone 'Europe/Paris')::date as day, count(distinct a.visitor_hash) as u
      from analytics_events a
      where ${statsScope(ctx, { since, communeId })} and a.type in ('PAGE_VIEW', 'EST_VIEW')
      group by 1
    ) d group by 1 order by 1
  `);
  const byMonth = new Map(data.map((d) => [d.month, d.visitors]));
  const out: { key: string; label: string; value: number }[] = [];
  const labels = ['jan', 'fév', 'mar', 'avr', 'mai', 'juin', 'juil', 'août', 'sep', 'oct', 'nov', 'déc'];
  const cursor = new Date(since);
  for (let i = 0; i < 12; i++) {
    const key = `${cursor.getUTCFullYear()}-${String(cursor.getUTCMonth() + 1).padStart(2, '0')}`;
    out.push({ key, label: labels[cursor.getUTCMonth()], value: byMonth.get(key) ?? 0 });
    cursor.setUTCMonth(cursor.getUTCMonth() + 1);
  }
  return out;
}

/** Recherches les plus fréquentes des habitants. */
export async function topSearches(ctx: BoContext, f: StatsFilter, limit = 7) {
  return rows<{ q: string; n: number }>(sql`
    select lower(trim(a.query)) as q, count(*)::int as n from analytics_events a
    where ${statsScope(ctx, f)} and a.type = 'SEARCH' and coalesce(trim(a.query), '') <> ''
    group by 1 order by n desc limit ${limit}
  `);
}

/** « Signal faible » : recherche fréquente sans aucun résultat sur le territoire. */
export async function weakSignal(ctx: BoContext, f: StatsFilter) {
  const [r] = await rows<{ q: string; n: number }>(sql`
    select lower(trim(a.query)) as q, count(*)::int as n from analytics_events a
    where ${statsScope(ctx, f)} and a.type = 'SEARCH' and a.result_count = 0 and coalesce(trim(a.query), '') <> ''
    group by 1 having count(*) >= 5 order by n desc limit 1
  `);
  return r ?? null;
}

/** Taux de revendication par catégorie (vue communale du tableau de bord). */
export async function categoryPodium(ctx: BoContext, limit = 7) {
  return rows<{ id: string; name: string; total: number; claimed: number }>(sql`
    select k.id, k.name, count(e.id)::int as total,
      (count(e.id) filter (where exists (select 1 from company_members m where m.company_id = e.company_id)))::int as claimed
    from establishments e join categories k on k.id = e.category_id
    where e.territory_id = ${ctx.territory.id} and e.status <> 'ARCHIVED' ${communeFilter(ctx, 'e.commune_id')}
    group by k.id having count(e.id) >= 3
    order by (count(e.id) filter (where exists (select 1 from company_members m where m.company_id = e.company_id)))::float / count(e.id) desc, count(e.id) desc
    limit ${limit}
  `);
}

/** Établissements géolocalisés du périmètre (carte de la vue communale). */
export async function scopePoints(ctx: BoContext, limit = 400) {
  return rows<{ id: string; name: string; lat: number; lng: number; family: string; activity: string | null }>(sql`
    select e.id, e.name, e.lat, e.lng, k.family, e.activity_label as activity
    from establishments e join categories k on k.id = e.category_id
    where e.territory_id = ${ctx.territory.id} and e.status in ('PRECREATED','TO_COMPLETE','CLAIMED','VALIDATED') and e.lat is not null ${communeFilter(ctx, 'e.commune_id')}
    limit ${limit}
  `);
}
