import { and, asc, count, desc, eq, gte, inArray, isNull, lt, sql } from 'drizzle-orm';
import { notFound, redirect } from 'next/navigation';
import { cache } from 'react';
import type { ModuleKey, PlanKey } from '@/lib/constants';
import { parisDate } from '@/lib/format';
import { canManageEstablishment, requireActor, type Actor } from '../authz';
import { computeCompleteness, vitrineLevel } from '../completeness';
import { db } from '../db';
import {
  analyticsEvents,
  appointments,
  campaignParticipants,
  campaigns,
  communes,
  companyMembers,
  establishments,
  jobApplications,
  messages,
  posts,
  type PlanLimits,
} from '../db/schema';
import { planLimits } from './billing';
import { completenessInput, getEstablishmentDetailById, type EstablishmentDetail } from './establishments';
import { getEnabledModules, getTerritoryById } from './territories';

export type ProContext = {
  actor: Actor;
  est: EstablishmentDetail;
  role: 'OWNER' | 'MEMBER' | 'STAFF';
  plan: PlanKey;
  limits: PlanLimits;
  modules: Set<ModuleKey>;
  territory: NonNullable<Awaited<ReturnType<typeof getTerritoryById>>>;
  completeness: ReturnType<typeof computeCompleteness>;
  level: ReturnType<typeof vitrineLevel>;
  unreadMessages: number;
  pendingAppointments: number;
  newApplications: number;
  campaign: { id: string; name: string; slug: string; status: 'INVITED' | 'JOINED' | 'DECLINED'; offerLabel: string | null; startsAt: string; endsAt: string } | null;
  base: string;
};

/** Établissements que l'utilisateur gère (membre d'entreprise). */
export async function managedEstablishments(userId: string) {
  return db
    .select({
      id: establishments.id,
      name: establishments.name,
      status: establishments.status,
      coverUrl: establishments.coverUrl,
      communeName: communes.name,
      role: companyMembers.role,
    })
    .from(companyMembers)
    .innerJoin(establishments, eq(establishments.companyId, companyMembers.companyId))
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .where(and(eq(companyMembers.userId, userId), isNull(establishments.archivedAt)))
    .orderBy(asc(establishments.name));
}

/** Contexte d'une page de l'espace pro : contrôle d'accès, offre, complétude, campagne en cours. */
export const loadProContext = cache(async (estId: string): Promise<ProContext> => {
  if (!/^[0-9a-f-]{36}$/.test(estId)) notFound();
  const actor = await requireActor(`/pro/${estId}`);
  const est = await getEstablishmentDetailById(estId);
  if (!est) notFound();
  const role = canManageEstablishment(actor, est);
  if (!role) notFound();
  if (est.status === 'ARCHIVED') redirect('/pro');
  const today = parisDate();
  const [limits, modules, territory, input, unread, pendingAppt, newApps, camp] = await Promise.all([
    planLimits(est.plan),
    getEnabledModules(est.territoryId),
    getTerritoryById(est.territoryId),
    completenessInput(est.id),
    db.select({ n: count() }).from(messages).where(and(eq(messages.establishmentId, est.id), isNull(messages.readAt))),
    db.select({ n: count() }).from(appointments).where(and(eq(appointments.establishmentId, est.id), eq(appointments.status, 'REQUESTED'))),
    db.select({ n: count() }).from(jobApplications).where(and(eq(jobApplications.establishmentId, est.id), eq(jobApplications.status, 'NEW'))),
    db
      .select({ c: campaigns, p: campaignParticipants })
      .from(campaignParticipants)
      .innerJoin(campaigns, eq(campaigns.id, campaignParticipants.campaignId))
      .where(
        and(
          eq(campaignParticipants.establishmentId, est.id),
          inArray(campaigns.status, ['ACTIVE', 'SCHEDULED']),
          gte(campaigns.endsAt, today),
        ),
      )
      .orderBy(asc(campaigns.startsAt))
      .limit(1),
  ]);
  if (!input) notFound();
  const completeness = computeCompleteness(input);
  return {
    actor,
    est,
    role,
    plan: est.plan,
    limits,
    modules,
    territory: territory!,
    completeness,
    level: vitrineLevel(completeness.score),
    unreadMessages: Number(unread[0]?.n ?? 0),
    pendingAppointments: Number(pendingAppt[0]?.n ?? 0),
    newApplications: Number(newApps[0]?.n ?? 0),
    campaign: camp[0]
      ? {
          id: camp[0].c.id,
          name: camp[0].c.name,
          slug: camp[0].c.slug,
          status: camp[0].p.status,
          offerLabel: camp[0].p.offerLabel,
          startsAt: camp[0].c.startsAt,
          endsAt: camp[0].c.endsAt,
        }
      : null,
    base: `/pro/${est.id}`,
  };
});

/** Exige un rôle de gestion complet (propriétaire ou agent) pour les actions sensibles. */
export function assertOwner(ctx: ProContext) {
  if (ctx.role === 'MEMBER') throw new Error('Seul le ou la titulaire du compte peut effectuer cette action.');
}

// ─── Statistiques ───────────────────────────────────────────────────────────

const DAY = 86_400_000;

export async function establishmentKpis(estId: string, days: number) {
  const now = Date.now();
  const from = new Date(now - days * DAY);
  const prevFrom = new Date(now - 2 * days * DAY);
  const types = ['EST_VIEW', 'PHONE_CLICK', 'DIRECTIONS_CLICK', 'QR_SCAN', 'WEBSITE_CLICK', 'CONTACT_SENT'] as const;
  const rows = await db
    .select({
      type: analyticsEvents.type,
      cur: sql<number>`count(*) FILTER (WHERE ${analyticsEvents.occurredAt} >= ${from})::int`,
      prev: sql<number>`count(*) FILTER (WHERE ${analyticsEvents.occurredAt} < ${from})::int`,
    })
    .from(analyticsEvents)
    .where(and(eq(analyticsEvents.establishmentId, estId), gte(analyticsEvents.occurredAt, prevFrom), inArray(analyticsEvents.type, [...types])))
    .groupBy(analyticsEvents.type);
  const get = (t: (typeof types)[number]) => rows.find((r) => r.type === t) ?? { cur: 0, prev: 0 };
  // 7 tranches égales sur la période (mini-histogrammes)
  const buckets = await db.execute<{ type: string; b: number; n: number }>(sql`
    SELECT type::text, least(6, floor(extract(epoch FROM (occurred_at - ${from})) / (${days} * 86400 / 7.0)))::int AS b, count(*)::int AS n
    FROM analytics_events
    WHERE establishment_id = ${estId} AND occurred_at >= ${from}
      AND type IN ('EST_VIEW','PHONE_CLICK','DIRECTIONS_CLICK','QR_SCAN')
    GROUP BY 1, 2
  `);
  const bars = (t: string) => {
    const arr = Array.from({ length: 7 }, () => 0);
    for (const r of buckets.rows) if (r.type === t) arr[Number(r.b)] = Number(r.n);
    return arr;
  };
  const [msg] = await db
    .select({
      cur: sql<number>`count(*) FILTER (WHERE ${messages.createdAt} >= ${from})::int`,
      prev: sql<number>`count(*) FILTER (WHERE ${messages.createdAt} < ${from})::int`,
    })
    .from(messages)
    .where(and(eq(messages.establishmentId, estId), gte(messages.createdAt, prevFrom)));
  return {
    views: { ...get('EST_VIEW'), bars: bars('EST_VIEW') },
    calls: { ...get('PHONE_CLICK'), bars: bars('PHONE_CLICK') },
    directions: { ...get('DIRECTIONS_CLICK'), bars: bars('DIRECTIONS_CLICK') },
    qr: { ...get('QR_SCAN'), bars: bars('QR_SCAN') },
    website: get('WEBSITE_CLICK'),
    messages: { cur: Number(msg?.cur ?? 0), prev: Number(msg?.prev ?? 0) },
  };
}

export function deltaLabel(cur: number, prev: number, suffix = ''): string {
  if (!prev) return cur ? `+${cur}${suffix}` : '—';
  const pct = Math.round(((cur - prev) / prev) * 100);
  return `${pct >= 0 ? '+' : ''}${pct} %${suffix}`;
}

/** Vues quotidiennes de la période et de la période précédente (courbe comparée), en jours de Paris. */
export async function dailyViews(estId: string, days: number) {
  const res = await db.execute<{ d: string; n: number }>(sql`
    SELECT to_char((occurred_at AT TIME ZONE 'Europe/Paris')::date, 'YYYY-MM-DD') AS d, count(*)::int AS n
    FROM analytics_events
    WHERE establishment_id = ${estId} AND type = 'EST_VIEW' AND occurred_at >= now() - make_interval(days => ${2 * days + 1})
    GROUP BY 1
  `);
  const byDay = new Map(res.rows.map((r) => [r.d, Number(r.n)]));
  const today = parisDate();
  const iso = (offset: number) => {
    const d = new Date(`${today}T12:00:00Z`);
    d.setUTCDate(d.getUTCDate() - offset);
    return d.toISOString().slice(0, 10);
  };
  return Array.from({ length: days }, (_, i) => {
    const offset = days - 1 - i;
    return { date: iso(offset), cur: byDay.get(iso(offset)) ?? 0, prev: byDay.get(iso(offset + days)) ?? 0 };
  });
}

export const SOURCE_LABELS: Record<string, { label: string; color: string }> = {
  PLATFORM_SEARCH: { label: 'Recherche sur la plateforme', color: '#1F6B52' },
  GOOGLE: { label: 'Google', color: '#3E6FB0' },
  MAP: { label: 'Carte', color: '#C8892A' },
  NEWSLETTER: { label: 'Newsletter & campagnes', color: '#D95C4E' },
  CAMPAIGN: { label: 'Newsletter & campagnes', color: '#D95C4E' },
  QR: { label: 'QR code vitrine', color: '#7A5BB5' },
  SOCIAL: { label: 'Réseaux sociaux', color: '#3B5A1F' },
  DIRECT: { label: 'Accès direct', color: '#8A8F86' },
  OTHER: { label: 'Autres sites', color: '#C9C2B2' },
};

export async function viewSources(estId: string, days: number) {
  const rows = await db
    .select({ source: analyticsEvents.source, n: count() })
    .from(analyticsEvents)
    .where(and(eq(analyticsEvents.establishmentId, estId), eq(analyticsEvents.type, 'EST_VIEW'), gte(analyticsEvents.occurredAt, new Date(Date.now() - days * DAY))))
    .groupBy(analyticsEvents.source);
  const merged = new Map<string, { label: string; color: string; n: number }>();
  for (const r of rows) {
    const meta = SOURCE_LABELS[r.source ?? 'DIRECT'] ?? SOURCE_LABELS.OTHER;
    const cur = merged.get(meta.label) ?? { ...meta, n: 0 };
    cur.n += Number(r.n);
    merged.set(meta.label, cur);
  }
  const total = [...merged.values()].reduce((s, x) => s + x.n, 0) || 1;
  return [...merged.values()].sort((a, b) => b.n - a.n).map((x) => ({ ...x, pct: Math.round((x.n / total) * 100) }));
}

/** Carte de chaleur jour × heure (8h-19h, heure de Paris). */
export async function viewHeatmap(estId: string, days: number) {
  const res = await db.execute<{ wd: number; h: number; n: number }>(sql`
    SELECT (extract(isodow FROM occurred_at AT TIME ZONE 'Europe/Paris') - 1)::int AS wd,
           extract(hour FROM occurred_at AT TIME ZONE 'Europe/Paris')::int AS h, count(*)::int AS n
    FROM analytics_events
    WHERE establishment_id = ${estId} AND type = 'EST_VIEW' AND occurred_at >= now() - make_interval(days => ${days})
    GROUP BY 1, 2
  `);
  const grid = Array.from({ length: 7 }, () => Array.from({ length: 12 }, () => 0));
  for (const r of res.rows) if (r.h >= 8 && r.h <= 19) grid[Number(r.wd)][Number(r.h) - 8] = Number(r.n);
  const max = Math.max(1, ...grid.flat());
  let best = { wd: 0, h: 8, n: 0 };
  grid.forEach((row, wd) => row.forEach((n, i) => n > best.n && (best = { wd, h: i + 8, n })));
  return { grid, max, best };
}

export async function searchQueries(estId: string, days: number, limit = 6) {
  return db
    .select({ q: analyticsEvents.query, n: count() })
    .from(analyticsEvents)
    .where(
      and(
        eq(analyticsEvents.establishmentId, estId),
        eq(analyticsEvents.type, 'EST_VIEW'),
        sql`${analyticsEvents.query} IS NOT NULL AND length(${analyticsEvents.query}) > 1`,
        gte(analyticsEvents.occurredAt, new Date(Date.now() - days * DAY)),
      ),
    )
    .groupBy(analyticsEvents.query)
    .orderBy(desc(count()))
    .limit(limit);
}

// ─── Publications ───────────────────────────────────────────────────────────

/** Publications comptant dans le quota du mois : publiées ce mois-ci ou programmées pour ce mois-ci. */
export async function postsThisMonth(estId: string): Promise<number> {
  const start = new Date();
  start.setDate(1);
  start.setHours(0, 0, 0, 0);
  const end = new Date(start);
  end.setMonth(end.getMonth() + 1);
  const [row] = await db
    .select({ n: count() })
    .from(posts)
    .where(
      and(
        eq(posts.establishmentId, estId),
        inArray(posts.status, ['PUBLISHED', 'SCHEDULED', 'PENDING']),
        gte(sql`coalesce(${posts.publishAt}, ${posts.publishedAt}, ${posts.createdAt})`, start),
        lt(sql`coalesce(${posts.publishAt}, ${posts.publishedAt}, ${posts.createdAt})`, end),
      ),
    );
  return Number(row?.n ?? 0);
}

export async function scheduledPosts(estId: string, limit = 3) {
  return db
    .select()
    .from(posts)
    .where(and(eq(posts.establishmentId, estId), eq(posts.status, 'SCHEDULED')))
    .orderBy(asc(posts.publishAt))
    .limit(limit);
}

export async function recentMessages(estId: string, limit = 3) {
  return db.select().from(messages).where(eq(messages.establishmentId, estId)).orderBy(desc(messages.createdAt)).limit(limit);
}

export async function pastPosts(estId: string, limit = 6) {
  return db
    .select()
    .from(posts)
    .where(and(eq(posts.establishmentId, estId), eq(posts.status, 'PUBLISHED')))
    .orderBy(desc(posts.publishedAt))
    .limit(limit);
}

export async function monthSchedule(estId: string, year: number, month: number) {
  const from = new Date(Date.UTC(year, month - 1, 1));
  const to = new Date(Date.UTC(year, month, 1));
  const rows = await db
    .select({ at: sql<Date>`coalesce(${posts.publishAt}, ${posts.publishedAt})` })
    .from(posts)
    .where(
      and(
        eq(posts.establishmentId, estId),
        inArray(posts.status, ['SCHEDULED', 'PUBLISHED']),
        gte(sql`coalesce(${posts.publishAt}, ${posts.publishedAt})`, from),
        lt(sql`coalesce(${posts.publishAt}, ${posts.publishedAt})`, to),
      ),
    );
  return rows.map((r) => new Date(r.at));
}
