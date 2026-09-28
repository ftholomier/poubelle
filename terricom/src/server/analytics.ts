import { sql } from 'drizzle-orm';
import { parisDate } from '@/lib/format';
import { sha256 } from './crypto';
import { db } from './db';
import { analyticsEvents } from './db/schema';
import { env } from './env';
import { logger } from './logger';
import { deviceType, isBot } from './request';

export type AnalyticsType = (typeof analyticsEvents.$inferInsert)['type'];
export type TrafficSource = NonNullable<(typeof analyticsEvents.$inferInsert)['source']>;

export type TrackInput = {
  type: AnalyticsType;
  territoryId?: string | null;
  communeId?: string | null;
  establishmentId?: string | null;
  refId?: string | null;
  source?: TrafficSource | null;
  query?: string | null;
  resultCount?: number | null;
  path?: string | null;
  referrer?: string | null;
  userAgent?: string | null;
  ip?: string | null;
};

/**
 * Identifiant visiteur éphémère : empreinte salée de l'IP et du navigateur, qui change
 * chaque jour. Aucune donnée n'est déposée sur le terminal (pas de consentement requis,
 * conformément aux lignes directrices de la CNIL sur la mesure d'audience).
 */
export function visitorHash(ip: string | null | undefined, userAgent: string | null | undefined, day = parisDate()): string {
  return sha256(`${env.ANALYTICS_SALT}|${day}|${ip ?? ''}|${userAgent ?? ''}`).slice(0, 32);
}

function referrerHost(ref: string | null | undefined): string | null {
  if (!ref) return null;
  try {
    return new URL(ref).host.toLowerCase();
  } catch {
    return null;
  }
}

/** Déduit la provenance d'une visite à partir du référent et des paramètres de campagne. */
export function detectSource(opts: { referrer?: string | null; src?: string | null; utmSource?: string | null; ownHosts?: string[] }): TrafficSource {
  const src = (opts.src ?? opts.utmSource ?? '').toLowerCase();
  if (src === 'qr') return 'QR';
  if (src.includes('newsletter') || src === 'nl') return 'NEWSLETTER';
  if (src === 'map' || src === 'carte') return 'MAP';
  if (src === 'search' || src === 'recherche') return 'PLATFORM_SEARCH';
  if (src.startsWith('campagne') || src === 'campaign') return 'CAMPAIGN';
  const host = referrerHost(opts.referrer);
  if (!host) return 'DIRECT';
  if (/(^|\.)google\.|bing\.com|qwant\.com|duckduckgo\.com|ecosia\.org|yahoo\./.test(host)) return 'GOOGLE';
  if (/facebook\.com|instagram\.com|linkedin\.com|t\.co$|twitter\.com|x\.com|tiktok\.com|pinterest\./.test(host)) return 'SOCIAL';
  if (/mail\.|outlook\.|gmail/.test(host)) return 'NEWSLETTER';
  if (opts.ownHosts?.some((h) => host === h)) return 'PLATFORM_SEARCH';
  return 'OTHER';
}

export async function track(input: TrackInput): Promise<void> {
  if (input.userAgent && isBot(input.userAgent)) return;
  try {
    await db.insert(analyticsEvents).values({
      type: input.type,
      territoryId: input.territoryId ?? null,
      communeId: input.communeId ?? null,
      establishmentId: input.establishmentId ?? null,
      refId: input.refId ?? null,
      source: input.source ?? null,
      query: input.query ? input.query.slice(0, 200) : null,
      resultCount: input.resultCount ?? null,
      visitorHash: visitorHash(input.ip, input.userAgent),
      path: input.path ? input.path.slice(0, 500) : null,
      referrerHost: referrerHost(input.referrer),
      device: input.userAgent ? deviceType(input.userAgent) : null,
    });
  } catch (err) {
    logger.warn('analytics.track_failed', { err, type: input.type });
  }
}

/** Agrège les événements d'une journée dans analytics_daily (idempotent). */
export async function rollupDay(day: string): Promise<void> {
  await db.execute(sql`
    INSERT INTO analytics_daily (day, territory_id, establishment_id, type, source, count, uniques)
    SELECT ${day}::date, territory_id, establishment_id, type, source, count(*)::int, count(DISTINCT visitor_hash)::int
    FROM analytics_events
    WHERE occurred_at >= ${day}::date AND occurred_at < ${day}::date + interval '1 day'
    GROUP BY territory_id, establishment_id, type, source
    ON CONFLICT ON CONSTRAINT analytics_daily_uq DO UPDATE SET count = EXCLUDED.count, uniques = EXCLUDED.uniques
  `);
}

/** Rétention : événements bruts conservés 13 mois (agrégats conservés au-delà). */
export async function purgeOldEvents(): Promise<number> {
  const res = await db.execute(sql`DELETE FROM analytics_events WHERE occurred_at < now() - interval '13 months'`);
  return res.rowCount ?? 0;
}
