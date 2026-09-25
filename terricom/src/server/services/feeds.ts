import { and, desc, eq, gte, isNull, or, sql } from 'drizzle-orm';
import type { IcsEvent } from '@/lib/ics';
import type { RssItem } from '@/lib/rss';
import { POST_KINDS, type PostKind } from '@/lib/constants';
import { truncate } from '@/lib/format';
import { db } from '../db';
import { events, posts } from '../db/schema';
import { portalUrl } from '../urls';
import { getFeed, upcomingEvents } from './portal';

/**
 * Flux publics (synchronisation de contenus) : agendas iCal auxquels s'abonner et actualités RSS, pour un
 * territoire ou un établissement. Ils permettent de reprendre les contenus sur le site de la mairie, de
 * l'office de tourisme ou de l'entreprise, sans ressaisie.
 */

type TerritoryRef = Parameters<typeof portalUrl>[0];

const plain = (s: string | null | undefined) =>
  (s ?? '')
    .replace(/[#*_`>]/g, '')
    .replace(/\s+/g, ' ')
    .trim();

export async function territoryCalendar(t: TerritoryRef & { id: string }): Promise<IcsEvent[]> {
  const rows = await upcomingEvents(t.id, { limit: 400 });
  return rows.map(({ ev, e, c }) => ({
    uid: `${ev.id}@terricom.fr`,
    title: ev.title,
    description: [ev.summary, ev.description, e?.name ? `Organisé par ${e.name}` : null].filter(Boolean).join('\n\n'),
    location: [ev.locationName, ev.address, c?.name].filter(Boolean).join(', '),
    url: portalUrl(t, `/agenda/${ev.slug}`),
    startsAt: ev.startsAt,
    endsAt: ev.endsAt,
    allDay: ev.allDay,
    lat: ev.lat,
    lng: ev.lng,
    updatedAt: ev.updatedAt,
  }));
}

export async function establishmentCalendar(t: TerritoryRef, estId: string): Promise<IcsEvent[]> {
  const rows = await db
    .select()
    .from(events)
    .where(
      and(
        eq(events.establishmentId, estId),
        eq(events.status, 'PUBLISHED'),
        gte(sql`coalesce(${events.endsAt}, ${events.startsAt})`, new Date(Date.now() - 86_400_000)),
      ),
    )
    .orderBy(events.startsAt)
    .limit(200);
  return rows.map((ev) => ({
    uid: `${ev.id}@terricom.fr`,
    title: ev.title,
    description: [ev.summary, ev.description].filter(Boolean).join('\n\n'),
    location: [ev.locationName, ev.address].filter(Boolean).join(', '),
    url: portalUrl(t, `/agenda/${ev.slug}`),
    startsAt: ev.startsAt,
    endsAt: ev.endsAt,
    allDay: ev.allDay,
    lat: ev.lat,
    lng: ev.lng,
    updatedAt: ev.updatedAt,
  }));
}

export async function territoryNews(t: TerritoryRef & { id: string }): Promise<RssItem[]> {
  const feed = await getFeed(t.id, { limit: 50, channel: 'TERRITOIRE' });
  return feed.map((f) => ({
    guid: `${f.id}@terricom.fr`,
    title: f.title,
    link: portalUrl(t, f.whoPath ? `${f.whoPath}#actualites` : '/actualites'),
    description: truncate(plain(f.body), 600),
    publishedAt: f.publishedAt,
    category: `${f.kindLabel} · ${f.who}`,
    imageUrl: f.image,
  }));
}

export async function establishmentNews(t: TerritoryRef, est: { id: string; path: string }): Promise<RssItem[]> {
  const now = new Date();
  const rows = await db
    .select()
    .from(posts)
    .where(and(eq(posts.establishmentId, est.id), eq(posts.status, 'PUBLISHED'), or(isNull(posts.expiresAt), gte(posts.expiresAt, now))))
    .orderBy(desc(posts.publishedAt))
    .limit(30);
  return rows.map((p) => ({
    guid: `${p.id}@terricom.fr`,
    title: p.title,
    link: portalUrl(t, `${est.path}#actualites`),
    description: truncate(plain(p.body), 600),
    publishedAt: p.publishedAt ?? p.createdAt,
    category: POST_KINDS[p.kind as PostKind]?.label ?? null,
    imageUrl: p.imageUrl,
  }));
}

/** Réponse HTTP d'un flux (mise en cache courte, adaptée aux abonnements). */
export function feedResponse(body: string, type: 'ics' | 'rss', filename: string): Response {
  return new Response(body, {
    headers: {
      'content-type': type === 'ics' ? 'text/calendar; charset=utf-8' : 'application/rss+xml; charset=utf-8',
      'content-disposition': `inline; filename="${filename}"`,
      'cache-control': 'public, max-age=900',
      'access-control-allow-origin': '*',
    },
  });
}
