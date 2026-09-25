import { sql } from 'drizzle-orm';
import { boolean, date, doublePrecision, index, integer, jsonb, pgTable, smallint, text, time, timestamp, unique, uuid, varchar } from 'drizzle-orm/pg-core';
import { citext, createdAt, pk, tstz, updatedAt } from './_common';
import { establishments, media } from './business';
import { appointmentStatus, authorType, contractType, eventKind, inboxStatus, jobStatus, poiKind, postKind, postStatus, publishStatus } from './enums';
import { communes, territories } from './tenancy';
import { users } from './users';

export type PostChannel = 'FICHE' | 'COMMUNE' | 'TERRITOIRE' | 'NEWSLETTER' | 'SOCIAL';

export type PostVariants = {
  fiche?: string;
  facebook?: string;
  instagram?: string;
  linkedin?: string;
  email?: string;
  seoTitle?: string;
};

/** Publications : actualités, promotions, nouveautés, horaires, recrutement. */
export const posts = pgTable(
  'posts',
  {
    id: pk(),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    communeId: uuid().references(() => communes.id, { onDelete: 'set null' }),
    establishmentId: uuid().references(() => establishments.id, { onDelete: 'cascade' }),
    authorType: authorType().notNull().default('ESTABLISHMENT'),
    kind: postKind().notNull().default('NEWS'),
    status: postStatus().notNull().default('DRAFT'),
    title: varchar({ length: 255 }).notNull(),
    body: text().notNull().default(''),
    imageUrl: text(),

    // Promotion / offre
    promoLabel: varchar({ length: 32 }),
    validFrom: date({ mode: 'string' }),
    validTo: date({ mode: 'string' }),
    ctaLabel: varchar({ length: 64 }),
    ctaUrl: text(),

    channels: text()
      .array()
      .$type<PostChannel[]>()
      .notNull()
      .default(sql`'{FICHE}'::text[]`),
    campaignId: uuid(),
    variants: jsonb()
      .$type<PostVariants>()
      .notNull()
      .default(sql`'{}'::jsonb`),
    aiGenerated: boolean().notNull().default(false),

    publishAt: tstz(),
    publishedAt: tstz(),
    expiresAt: tstz(),
    viewCount: integer().notNull().default(0),

    moderationNote: text(),
    moderatedById: uuid().references(() => users.id, { onDelete: 'set null' }),
    moderatedAt: tstz(),
    socialSentAt: tstz(),

    createdById: uuid().references(() => users.id, { onDelete: 'set null' }),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [
    index('posts_territory_published_idx').on(t.territoryId, t.status, t.publishedAt),
    index('posts_establishment_idx').on(t.establishmentId, t.publishedAt),
    index('posts_scheduled_idx').on(t.status, t.publishAt),
  ],
);

export type ProgramItem = { label: string; text: string };

/** Événements : marchés, portes ouvertes, dégustations, ateliers… */
export const events = pgTable(
  'events',
  {
    id: pk(),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    communeId: uuid().references(() => communes.id, { onDelete: 'set null' }),
    establishmentId: uuid().references(() => establishments.id, { onDelete: 'cascade' }),
    authorType: authorType().notNull().default('ESTABLISHMENT'),
    organizerName: varchar({ length: 255 }),
    slug: varchar({ length: 160 }).notNull(),
    title: varchar({ length: 255 }).notNull(),
    kind: eventKind().notNull().default('AUTRE'),
    summary: varchar({ length: 300 }),
    description: text().notNull().default(''),
    startsAt: timestamp({ withTimezone: true }).notNull(),
    endsAt: timestamp({ withTimezone: true }),
    allDay: boolean().notNull().default(false),
    locationName: varchar({ length: 255 }),
    address: varchar({ length: 255 }),
    lat: doublePrecision(),
    lng: doublePrecision(),
    priceText: varchar({ length: 120 }),
    accessibilityText: varchar({ length: 255 }),
    registrationUrl: text(),
    capacity: integer(),
    program: jsonb()
      .$type<ProgramItem[]>()
      .notNull()
      .default(sql`'[]'::jsonb`),
    imageUrl: text(),
    status: publishStatus().notNull().default('PUBLISHED'),
    isFeatured: boolean().notNull().default(false),
    campaignId: uuid(),
    createdById: uuid().references(() => users.id, { onDelete: 'set null' }),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [
    unique('events_territory_slug_uq').on(t.territoryId, t.slug),
    index('events_territory_start_idx').on(t.territoryId, t.startsAt),
    index('events_establishment_idx').on(t.establishmentId),
  ],
);

/** Marchés hebdomadaires récurrents. */
export const markets = pgTable(
  'markets',
  {
    id: pk(),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    communeId: uuid()
      .notNull()
      .references(() => communes.id, { onDelete: 'cascade' }),
    name: varchar({ length: 255 }).notNull(),
    place: varchar({ length: 255 }),
    weekday: smallint().notNull(),
    startTime: time().notNull(),
    endTime: time().notNull(),
    description: text(),
    lat: doublePrecision(),
    lng: doublePrecision(),
    isActive: boolean().notNull().default(true),
    createdAt: createdAt(),
  },
  (t) => [index('markets_commune_idx').on(t.communeId)],
);

/** Offres d'emploi locales. */
export const jobs = pgTable(
  'jobs',
  {
    id: pk(),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    communeId: uuid().references(() => communes.id, { onDelete: 'set null' }),
    establishmentId: uuid()
      .notNull()
      .references(() => establishments.id, { onDelete: 'cascade' }),
    slug: varchar({ length: 160 }).notNull(),
    title: varchar({ length: 255 }).notNull(),
    contractType: contractType().notNull(),
    status: jobStatus().notNull().default('PUBLISHED'),
    startText: varchar({ length: 160 }),
    salaryText: varchar({ length: 160 }),
    workTimeText: varchar({ length: 160 }),
    description: text().notNull().default(''),
    missions: text()
      .array()
      .notNull()
      .default(sql`'{}'::text[]`),
    profile: text()
      .array()
      .notNull()
      .default(sql`'{}'::text[]`),
    applyEmail: varchar({ length: 255 }),
    publishedAt: tstz(),
    expiresAt: tstz(),
    createdById: uuid().references(() => users.id, { onDelete: 'set null' }),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [unique('jobs_territory_slug_uq').on(t.territoryId, t.slug), index('jobs_territory_status_idx').on(t.territoryId, t.status)],
);

export const jobApplications = pgTable(
  'job_applications',
  {
    id: pk(),
    jobId: uuid()
      .notNull()
      .references(() => jobs.id, { onDelete: 'cascade' }),
    establishmentId: uuid()
      .notNull()
      .references(() => establishments.id, { onDelete: 'cascade' }),
    fullName: varchar({ length: 255 }).notNull(),
    email: citext().notNull(),
    phone: varchar({ length: 32 }),
    message: text(),
    cvMediaId: uuid().references(() => media.id, { onDelete: 'set null' }),
    status: inboxStatus().notNull().default('NEW'),
    consentAt: timestamp({ withTimezone: true }).notNull().defaultNow(),
    purgeAfter: date({ mode: 'string' })
      .notNull()
      .default(sql`(current_date + interval '24 months')::date`),
    createdAt: createdAt(),
  },
  (t) => [index('job_applications_est_idx').on(t.establishmentId, t.createdAt)],
);

/** Messages reçus par les établissements (formulaire de contact, collectivité). */
export const messages = pgTable(
  'messages',
  {
    id: pk(),
    establishmentId: uuid()
      .notNull()
      .references(() => establishments.id, { onDelete: 'cascade' }),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    source: varchar({ length: 16 }).notNull().default('PORTAL'),
    fromUserId: uuid().references(() => users.id, { onDelete: 'set null' }),
    senderName: varchar({ length: 255 }).notNull(),
    senderEmail: citext(),
    senderPhone: varchar({ length: 32 }),
    subject: varchar({ length: 255 }),
    body: text().notNull(),
    status: inboxStatus().notNull().default('NEW'),
    readAt: tstz(),
    repliedAt: tstz(),
    ipHash: varchar({ length: 64 }),
    purgeAfter: date({ mode: 'string' })
      .notNull()
      .default(sql`(current_date + interval '36 months')::date`),
    createdAt: createdAt(),
  },
  (t) => [index('messages_est_idx').on(t.establishmentId, t.createdAt)],
);

/** Demandes de rendez-vous (offre Premium). */
export const appointments = pgTable(
  'appointments',
  {
    id: pk(),
    establishmentId: uuid()
      .notNull()
      .references(() => establishments.id, { onDelete: 'cascade' }),
    fullName: varchar({ length: 255 }).notNull(),
    email: citext().notNull(),
    phone: varchar({ length: 32 }),
    service: varchar({ length: 255 }),
    preferredAt: timestamp({ withTimezone: true }).notNull(),
    message: text(),
    status: appointmentStatus().notNull().default('REQUESTED'),
    responseNote: text(),
    respondedAt: tstz(),
    createdAt: createdAt(),
  },
  (t) => [index('appointments_est_idx').on(t.establishmentId, t.preferredAt)],
);

/** Points d'intérêt économiques affichés sur la carte : zones d'activités, halles, office de tourisme… */
export const pointsOfInterest = pgTable(
  'points_of_interest',
  {
    id: pk(),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    communeId: uuid().references(() => communes.id, { onDelete: 'set null' }),
    kind: poiKind().notNull().default('AUTRE'),
    name: varchar({ length: 255 }).notNull(),
    description: text(),
    address: varchar({ length: 255 }),
    url: text(),
    lat: doublePrecision().notNull(),
    lng: doublePrecision().notNull(),
    isActive: boolean().notNull().default(true),
    createdById: uuid().references(() => users.id, { onDelete: 'set null' }),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [index('points_of_interest_territory_idx').on(t.territoryId)],
);
