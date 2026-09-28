import { sql } from 'drizzle-orm';
import { date, doublePrecision, index, integer, jsonb, numeric, pgTable, primaryKey, smallint, text, unique, uuid, varchar } from 'drizzle-orm/pg-core';
import { createdAt, pk, tstz, updatedAt } from './_common';
import { establishments } from './business';
import { campaignMode, campaignStatus, participantStatus, publishStatus } from './enums';
import { communes, territories } from './tenancy';
import { users } from './users';

export type CampaignCriteria = {
  families?: string[];
  categoryIds?: string[];
  attributeSlugs?: string[];
  communeIds?: string[];
  keywords?: string[];
};

export type CampaignAiPlan = {
  pageTitle?: string;
  pageText?: string;
  newsletterSubject?: string;
  newsletterIntro?: string;
  plan?: { date: string; text: string }[];
  prompt?: string;
};

/** Campagnes d'animation territoriale (Noël chez vos commerçants, semaine de l'artisanat…). */
export const campaigns = pgTable(
  'campaigns',
  {
    id: pk(),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    /** Opération portée par une commune (null : campagne du territoire entier). */
    communeId: uuid().references(() => communes.id, { onDelete: 'set null' }),
    slug: varchar({ length: 160 }).notNull(),
    name: varchar({ length: 255 }).notNull(),
    tagline: varchar({ length: 255 }),
    description: text().notNull().default(''),
    startsAt: date({ mode: 'string' }).notNull(),
    endsAt: date({ mode: 'string' }).notNull(),
    status: campaignStatus().notNull().default('DRAFT'),
    mode: campaignMode().notNull().default('STANDARD'),
    colorBg: varchar({ length: 9 }).notNull().default('#7A2E26'),
    colorBgDark: varchar({ length: 9 }).notNull().default('#5E1F1A'),
    colorText: varchar({ length: 9 }).notNull().default('#FFF3E6'),
    colorTextSoft: varchar({ length: 9 }).notNull().default('#F3D5C9'),
    heroImageUrl: text(),
    cardImageUrl: text(),
    ctaLabel: varchar({ length: 64 }),
    criteria: jsonb()
      .$type<CampaignCriteria>()
      .notNull()
      .default(sql`'{}'::jsonb`),
    aiPlan: jsonb()
      .$type<CampaignAiPlan>()
      .notNull()
      .default(sql`'{}'::jsonb`),
    invitationMessage: text(),
    highlightEventId: uuid(),
    createdById: uuid().references(() => users.id, { onDelete: 'set null' }),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [
    unique('campaigns_territory_slug_uq').on(t.territoryId, t.slug),
    index('campaigns_territory_status_idx').on(t.territoryId, t.status),
    index('campaigns_commune_idx').on(t.communeId),
  ],
);

export const campaignParticipants = pgTable(
  'campaign_participants',
  {
    campaignId: uuid()
      .notNull()
      .references(() => campaigns.id, { onDelete: 'cascade' }),
    establishmentId: uuid()
      .notNull()
      .references(() => establishments.id, { onDelete: 'cascade' }),
    status: participantStatus().notNull().default('INVITED'),
    offerLabel: varchar({ length: 80 }),
    offerDescription: text(),
    invitedAt: tstz(),
    joinedAt: tstz(),
  },
  (t) => [primaryKey({ columns: [t.campaignId, t.establishmentId] }), index('campaign_participants_est_idx').on(t.establishmentId)],
);

/** Cases du calendrier de l'Avent d'une campagne. */
export const adventDoors = pgTable(
  'advent_doors',
  {
    id: pk(),
    campaignId: uuid()
      .notNull()
      .references(() => campaigns.id, { onDelete: 'cascade' }),
    day: smallint().notNull(),
    title: varchar({ length: 255 }).notNull(),
    establishmentId: uuid().references(() => establishments.id, { onDelete: 'set null' }),
  },
  (t) => [unique('advent_doors_day_uq').on(t.campaignId, t.day)],
);

/** Circuits et parcours (circuit des artisans, gastronomique…) avec passeport à tamponner. */
export const circuits = pgTable(
  'circuits',
  {
    id: pk(),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    slug: varchar({ length: 160 }).notNull(),
    name: varchar({ length: 255 }).notNull(),
    meta: varchar({ length: 255 }),
    description: text().notNull().default(''),
    distanceKm: numeric({ precision: 6, scale: 1 }),
    durationText: varchar({ length: 64 }),
    travelMode: varchar({ length: 120 }),
    rewardText: varchar({ length: 255 }),
    rewardThreshold: integer(),
    imageUrl: text(),
    tagColor: varchar({ length: 9 }).notNull().default('#F4B266'),
    status: publishStatus().notNull().default('PUBLISHED'),
    sortOrder: integer().notNull().default(0),
    createdById: uuid().references(() => users.id, { onDelete: 'set null' }),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [unique('circuits_territory_slug_uq').on(t.territoryId, t.slug)],
);

export const circuitStops = pgTable(
  'circuit_stops',
  {
    id: pk(),
    circuitId: uuid()
      .notNull()
      .references(() => circuits.id, { onDelete: 'cascade' }),
    position: integer().notNull(),
    establishmentId: uuid().references(() => establishments.id, { onDelete: 'cascade' }),
    name: varchar({ length: 255 }),
    lat: doublePrecision(),
    lng: doublePrecision(),
    note: text(),
    stampSecret: varchar({ length: 32 }).notNull().unique(),
  },
  (t) => [index('circuit_stops_circuit_idx').on(t.circuitId, t.position)],
);

/** Passeport anonyme d'un visiteur (jeton stocké dans un cookie, aucune donnée personnelle). */
export const passports = pgTable(
  'passports',
  {
    id: pk(),
    circuitId: uuid()
      .notNull()
      .references(() => circuits.id, { onDelete: 'cascade' }),
    tokenHash: varchar({ length: 64 }).notNull().unique(),
    rewardCode: varchar({ length: 16 }),
    completedAt: tstz(),
    rewardClaimedAt: tstz(),
    lastSeenAt: tstz(),
    createdAt: createdAt(),
  },
  (t) => [index('passports_circuit_idx').on(t.circuitId)],
);

export const passportStamps = pgTable(
  'passport_stamps',
  {
    passportId: uuid()
      .notNull()
      .references(() => passports.id, { onDelete: 'cascade' }),
    stopId: uuid()
      .notNull()
      .references(() => circuitStops.id, { onDelete: 'cascade' }),
    stampedAt: tstz().notNull().defaultNow(),
  },
  (t) => [primaryKey({ columns: [t.passportId, t.stopId] })],
);
