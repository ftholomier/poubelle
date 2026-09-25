import { sql } from 'drizzle-orm';
import { boolean, index, integer, jsonb, pgTable, primaryKey, text, unique, uuid, varchar } from 'drizzle-orm/pg-core';
import { citext, createdAt, pk, tstz, updatedAt } from './_common';
import { companies } from './business';
import { audienceKind, deliveryStatus, newsletterStatus, subscriberStatus } from './enums';
import { communes, territories } from './tenancy';
import { users } from './users';

/**
 * Abonnés aux lettres d'information d'un territoire.
 * Le consentement est horodaté, le texte présenté est conservé (preuve RGPD),
 * la confirmation se fait en double opt-in et la désinscription en un clic.
 */
export const subscribers = pgTable(
  'subscribers',
  {
    id: pk(),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    email: citext().notNull(),
    firstName: varchar({ length: 120 }),
    communeId: uuid().references(() => communes.id, { onDelete: 'set null' }),
    status: subscriberStatus().notNull().default('PENDING'),
    source: varchar({ length: 32 }).notNull().default('PORTAL'),
    consentText: text().notNull(),
    consentAt: tstz().notNull().defaultNow(),
    consentIpHash: varchar({ length: 64 }),
    confirmTokenHash: varchar({ length: 64 }),
    confirmedAt: tstz(),
    unsubscribedAt: tstz(),
    unsubscribeToken: varchar({ length: 48 }).notNull().unique(),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [unique('subscribers_territory_email_uq').on(t.territoryId, t.email), index('subscribers_territory_status_idx').on(t.territoryId, t.status)],
);

export const audiences = pgTable(
  'audiences',
  {
    id: pk(),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    name: varchar({ length: 160 }).notNull(),
    description: varchar({ length: 255 }),
    kind: audienceKind().notNull().default('MANUAL'),
    communeId: uuid().references(() => communes.id, { onDelete: 'cascade' }),
    isDefault: boolean().notNull().default(false),
    sortOrder: integer().notNull().default(0),
    createdAt: createdAt(),
  },
  (t) => [index('audiences_territory_idx').on(t.territoryId)],
);

export const subscriberAudiences = pgTable(
  'subscriber_audiences',
  {
    subscriberId: uuid()
      .notNull()
      .references(() => subscribers.id, { onDelete: 'cascade' }),
    audienceId: uuid()
      .notNull()
      .references(() => audiences.id, { onDelete: 'cascade' }),
  },
  (t) => [primaryKey({ columns: [t.subscriberId, t.audienceId] })],
);

export type NewsletterBlock =
  | { type: 'text'; text: string }
  | { type: 'establishments'; ids: string[]; title?: string }
  | { type: 'posts'; ids: string[]; title?: string }
  | { type: 'events'; ids: string[]; title?: string }
  | { type: 'cta'; label: string; url: string };

/** Lettres d'information (territoire, commune ou entreprise). */
export const newsletters = pgTable(
  'newsletters',
  {
    id: pk(),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    communeId: uuid().references(() => communes.id, { onDelete: 'set null' }),
    companyId: uuid().references(() => companies.id, { onDelete: 'cascade' }),
    number: integer(),
    subject: varchar({ length: 255 }).notNull(),
    preheader: varchar({ length: 255 }),
    title: varchar({ length: 255 }).notNull(),
    intro: text().notNull().default(''),
    heroImageUrl: text(),
    blocks: jsonb()
      .$type<NewsletterBlock[]>()
      .notNull()
      .default(sql`'[]'::jsonb`),
    audienceIds: uuid()
      .array()
      .notNull()
      .default(sql`'{}'::uuid[]`),
    status: newsletterStatus().notNull().default('DRAFT'),
    scheduledAt: tstz(),
    sentAt: tstz(),
    statsRecipients: integer().notNull().default(0),
    statsSent: integer().notNull().default(0),
    statsOpens: integer().notNull().default(0),
    statsClicks: integer().notNull().default(0),
    statsUnsubscribes: integer().notNull().default(0),
    statsBounces: integer().notNull().default(0),
    createdById: uuid().references(() => users.id, { onDelete: 'set null' }),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [index('newsletters_territory_idx').on(t.territoryId, t.status)],
);

export const newsletterDeliveries = pgTable(
  'newsletter_deliveries',
  {
    id: pk(),
    newsletterId: uuid()
      .notNull()
      .references(() => newsletters.id, { onDelete: 'cascade' }),
    subscriberId: uuid().references(() => subscribers.id, { onDelete: 'set null' }),
    contactId: uuid(),
    email: citext().notNull(),
    status: deliveryStatus().notNull().default('QUEUED'),
    token: varchar({ length: 48 }).notNull().unique(),
    sentAt: tstz(),
    openedAt: tstz(),
    clickedAt: tstz(),
    error: text(),
    createdAt: createdAt(),
  },
  (t) => [unique('newsletter_deliveries_uq').on(t.newsletterId, t.email), index('newsletter_deliveries_status_idx').on(t.newsletterId, t.status)],
);

/** Contacts clients consentis d'une entreprise (offre Communication). */
export const companyContacts = pgTable(
  'company_contacts',
  {
    id: pk(),
    companyId: uuid()
      .notNull()
      .references(() => companies.id, { onDelete: 'cascade' }),
    email: citext().notNull(),
    fullName: varchar({ length: 255 }),
    source: varchar({ length: 32 }).notNull().default('MANUAL'),
    consentText: text(),
    consentAt: tstz(),
    subscribed: boolean().notNull().default(true),
    unsubscribeToken: varchar({ length: 48 }).notNull().unique(),
    createdAt: createdAt(),
  },
  (t) => [unique('company_contacts_uq').on(t.companyId, t.email)],
);
