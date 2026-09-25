import {
  doublePrecision,
  index,
  integer,
  pgTable,
  text,
  uuid,
  varchar,
} from 'drizzle-orm/pg-core';
import { createdAt, pk, tstz, updatedAt } from './_common';
import { dealStage, territoryKind } from './enums';
import { territories } from './tenancy';
import { users } from './users';

/** Suivi commercial des collectivités prospects et clientes. */
export const deals = pgTable(
  'deals',
  {
    id: pk(),
    name: varchar({ length: 255 }).notNull(),
    kind: territoryKind().notNull().default('CC'),
    communesCount: integer().notNull().default(1),
    population: integer(),
    stage: dealStage().notNull().default('PROSPECT'),
    probability: integer().notNull().default(10),
    licenceCents: integer().notNull().default(0),
    setupCents: integer().notNull().default(0),
    ownerId: uuid().references(() => users.id, { onDelete: 'set null' }),
    territoryId: uuid().references(() => territories.id, { onDelete: 'set null' }),
    nextAction: varchar({ length: 255 }),
    notes: text(),
    source: varchar({ length: 64 }),
    lat: doublePrecision(),
    lng: doublePrecision(),
    contactEmail: varchar({ length: 255 }),
    contactPhone: varchar({ length: 32 }),
    lastInteractionAt: tstz(),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [index('deals_stage_idx').on(t.stage)],
);

export const dealContacts = pgTable(
  'deal_contacts',
  {
    id: pk(),
    dealId: uuid()
      .notNull()
      .references(() => deals.id, { onDelete: 'cascade' }),
    name: varchar({ length: 255 }).notNull(),
    role: varchar({ length: 255 }),
    tag: varchar({ length: 32 }),
    email: varchar({ length: 255 }),
    phone: varchar({ length: 32 }),
    sortOrder: integer().notNull().default(0),
  },
  (t) => [index('deal_contacts_deal_idx').on(t.dealId)],
);

export const dealActivities = pgTable(
  'deal_activities',
  {
    id: pk(),
    dealId: uuid()
      .notNull()
      .references(() => deals.id, { onDelete: 'cascade' }),
    occurredAt: tstz().notNull().defaultNow(),
    kind: varchar({ length: 64 }).notNull(),
    text: text().notNull(),
    userId: uuid().references(() => users.id, { onDelete: 'set null' }),
  },
  (t) => [index('deal_activities_deal_idx').on(t.dealId, t.occurredAt)],
);

export const dealTasks = pgTable(
  'deal_tasks',
  {
    id: pk(),
    dealId: uuid()
      .notNull()
      .references(() => deals.id, { onDelete: 'cascade' }),
    text: varchar({ length: 255 }).notNull(),
    dueText: varchar({ length: 64 }),
    doneAt: tstz(),
    sortOrder: integer().notNull().default(0),
    createdAt: createdAt(),
  },
  (t) => [index('deal_tasks_deal_idx').on(t.dealId)],
);

export const dealDocuments = pgTable(
  'deal_documents',
  {
    id: pk(),
    dealId: uuid()
      .notNull()
      .references(() => deals.id, { onDelete: 'cascade' }),
    name: varchar({ length: 255 }).notNull(),
    url: text(),
    createdAt: createdAt(),
  },
  (t) => [index('deal_documents_deal_idx').on(t.dealId)],
);
