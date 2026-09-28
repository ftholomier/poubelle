import { and, eq, gte, inArray, notInArray, sql } from 'drizzle-orm';
import { parseIcs, type ParsedIcsEvent } from '@/lib/ics';
import { slugify } from '@/lib/slug';
import { invalidate } from '../cache';
import { db } from '../db';
import { calendarFeeds, events, territories } from '../db/schema';
import { logger } from '../logger';
import { OutboundError, safeFetch } from '../net';

/**
 * Synchronisation des agendas externes (iCal) : l'agenda d'un office de tourisme, d'une mairie ou d'une
 * association est repris dans l'agenda du territoire, sans ressaisie. Les événements importés sont liés à
 * leur source (UID) : mis à jour à chaque passage, retirés s'ils disparaissent ou sont annulés.
 */

const HORIZON_DAYS = 365;
const MAX_EVENTS = 500;

type Feed = typeof calendarFeeds.$inferSelect;

function eventSlug(title: string, startsAt: Date, uid: string): string {
  const day = startsAt.toISOString().slice(0, 10);
  // Suffixe stable dérivé de l'UID : un même événement garde son adresse d'une synchronisation à l'autre.
  let h = 0;
  for (const ch of uid) h = (h * 31 + ch.charCodeAt(0)) >>> 0;
  return `${slugify(`${title}-${day}`).slice(0, 130)}-${h.toString(36).slice(0, 6)}`;
}

/** Garde les événements à venir (ou en cours), dans l'horizon d'un an. */
export function selectUpcoming(list: ParsedIcsEvent[], now = new Date()): ParsedIcsEvent[] {
  const from = now.getTime() - 86_400_000;
  const to = now.getTime() + HORIZON_DAYS * 86_400_000;
  return list
    .filter((e) => (e.endsAt ?? e.startsAt).getTime() >= from && e.startsAt.getTime() <= to)
    .sort((a, b) => a.startsAt.getTime() - b.startsAt.getTime())
    .slice(0, MAX_EVENTS);
}

async function syncOne(feed: Feed): Promise<{ imported: number; removed: number }> {
  const res = await safeFetch(feed.url, { allowHttp: true, timeoutMs: 15_000, maxBytes: 5_000_000, headers: { accept: 'text/calendar, */*' } });
  if (!res.ok) throw new OutboundError(`Agenda indisponible (HTTP ${res.status}).`);
  return importIcsText(feed, res.text);
}

/** Reprend le contenu d'un agenda (texte iCalendar) : création, mise à jour et retrait des événements importés. */
export async function importIcsText(feed: Feed, text: string): Promise<{ imported: number; removed: number }> {
  if (!/BEGIN:VCALENDAR/i.test(text)) throw new OutboundError('Ce n’est pas un agenda iCalendar (.ics).', true);
  const parsed = selectUpcoming(parseIcs(text));
  const keep = parsed.filter((e) => !e.cancelled);
  const [t] = await db.select({ slug: territories.slug }).from(territories).where(eq(territories.id, feed.territoryId)).limit(1);
  let imported = 0;
  for (const e of keep) {
    const values = {
      title: e.title,
      summary: e.description ? e.description.replace(/\s+/g, ' ').slice(0, 280) : null,
      description: e.description.slice(0, 5000),
      startsAt: e.startsAt,
      endsAt: e.endsAt,
      allDay: e.allDay,
      locationName: e.location?.slice(0, 255) ?? null,
      lat: e.lat,
      lng: e.lng,
      registrationUrl: e.url,
      updatedAt: new Date(),
    };
    await db
      .insert(events)
      .values({
        ...values,
        // Le statut n'est posé qu'à la création : un événement retiré par un agent reste retiré.
        status: 'PUBLISHED',
        territoryId: feed.territoryId,
        communeId: feed.communeId,
        authorType: feed.communeId ? 'COMMUNE' : 'TERRITORY',
        organizerName: feed.name,
        kind: feed.kind,
        slug: eventSlug(e.title, e.startsAt, `${feed.id}:${e.uid}`),
        sourceFeedId: feed.id,
        externalUid: e.uid,
        createdById: feed.createdById,
      })
      .onConflictDoUpdate({ target: [events.sourceFeedId, events.externalUid], set: values });
    imported++;
  }
  // Événements à venir disparus de la source (ou annulés) : retirés de l'agenda.
  const uids = keep.map((e) => e.uid);
  const removed = await db
    .update(events)
    .set({ status: 'ARCHIVED', updatedAt: new Date() })
    .where(
      and(
        eq(events.sourceFeedId, feed.id),
        eq(events.status, 'PUBLISHED'),
        gte(sql`coalesce(${events.endsAt}, ${events.startsAt})`, new Date()),
        uids.length ? notInArray(events.externalUid, uids) : undefined,
      ),
    );
  if (t) invalidate(`portal:${t.slug}`);
  return { imported, removed: removed.rowCount ?? 0 };
}

/** Synchronise un agenda (bouton du back-office) ou tous les agendas actifs (tâche horaire). */
export async function syncCalendarFeeds(feedId?: string): Promise<{ feeds: number; imported: number; errors: number }> {
  const feeds = await db
    .select()
    .from(calendarFeeds)
    .where(feedId ? eq(calendarFeeds.id, feedId) : eq(calendarFeeds.active, true));
  let imported = 0;
  let errors = 0;
  for (const feed of feeds) {
    try {
      const r = await syncOne(feed);
      imported += r.imported;
      await db
        .update(calendarFeeds)
        .set({
          lastSyncAt: new Date(),
          lastCount: r.imported,
          lastStatus: `${r.imported} événement${r.imported > 1 ? 's' : ''} à venir${r.removed ? ` · ${r.removed} retiré${r.removed > 1 ? 's' : ''}` : ''}`,
        })
        .where(eq(calendarFeeds.id, feed.id));
    } catch (err) {
      errors++;
      const message = err instanceof OutboundError ? err.message : 'Erreur inattendue pendant la synchronisation.';
      if (!(err instanceof OutboundError)) logger.error('agenda.sync_failed', { feedId: feed.id, err });
      await db
        .update(calendarFeeds)
        .set({ lastSyncAt: new Date(), lastStatus: `Échec : ${message}`.slice(0, 255) })
        .where(eq(calendarFeeds.id, feed.id));
    }
  }
  return { feeds: feeds.length, imported, errors };
}

/** Suppression d'un agenda : ses événements importés disparaissent avec lui (clé étrangère en cascade). */
export async function removeCalendarFeed(territoryId: string, feedId: string, communeIds: string[] | null): Promise<boolean> {
  const conds = [eq(calendarFeeds.id, feedId), eq(calendarFeeds.territoryId, territoryId)];
  if (communeIds) conds.push(inArray(calendarFeeds.communeId, communeIds));
  const res = await db.delete(calendarFeeds).where(and(...conds));
  return (res.rowCount ?? 0) > 0;
}
