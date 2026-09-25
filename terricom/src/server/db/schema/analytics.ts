import { bigserial, date, index, integer, pgTable, text, unique, uuid, varchar } from 'drizzle-orm/pg-core';
import { tstz } from './_common';
import { analyticsType, trafficSource } from './enums';

/**
 * Événements de mesure d'audience, sans cookie ni donnée personnelle :
 * le visiteur est représenté par un hachage salé quotidien (IP + user-agent + jour),
 * impossible à relier d'un jour à l'autre.
 */
export const analyticsEvents = pgTable(
  'analytics_events',
  {
    id: bigserial({ mode: 'number' }).primaryKey(),
    occurredAt: tstz().notNull().defaultNow(),
    territoryId: uuid(),
    communeId: uuid(),
    establishmentId: uuid(),
    refId: uuid(),
    type: analyticsType().notNull(),
    source: trafficSource(),
    query: text(),
    resultCount: integer(),
    visitorHash: varchar({ length: 32 }),
    path: text(),
    referrerHost: varchar({ length: 255 }),
    device: varchar({ length: 10 }),
  },
  (t) => [
    index('analytics_territory_time_idx').on(t.territoryId, t.occurredAt),
    index('analytics_establishment_time_idx').on(t.establishmentId, t.occurredAt),
    index('analytics_type_time_idx').on(t.type, t.occurredAt),
    index('analytics_ref_idx').on(t.refId),
  ],
);

/** Agrégats journaliers conservés au-delà de la durée de rétention des événements bruts. */
export const analyticsDaily = pgTable(
  'analytics_daily',
  {
    id: bigserial({ mode: 'number' }).primaryKey(),
    day: date({ mode: 'string' }).notNull(),
    territoryId: uuid(),
    establishmentId: uuid(),
    type: analyticsType().notNull(),
    source: trafficSource(),
    count: integer().notNull().default(0),
    uniques: integer().notNull().default(0),
  },
  (t) => [
    unique('analytics_daily_uq')
      .on(t.day, t.territoryId, t.establishmentId, t.type, t.source)
      .nullsNotDistinct(),
    index('analytics_daily_territory_idx').on(t.territoryId, t.day),
  ],
);
