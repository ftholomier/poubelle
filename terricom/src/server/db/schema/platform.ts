import { sql } from 'drizzle-orm';
import { bigserial, boolean, index, integer, jsonb, pgTable, serial, text, timestamp, uuid, varchar } from 'drizzle-orm/pg-core';
import { citext, createdAt, pk, tstz, updatedAt } from './_common';
import { companies } from './business';
import { aiFeature, auditCategory, emailStatus, privacyRequestKind, privacyRequestStatus, queueStatus, ticketStatus } from './enums';
import { territories } from './tenancy';
import { users } from './users';

/**
 * Journal d'audit en ajout seul, chaîné par hachage (chaque entrée scelle la précédente).
 * Un déclencheur PostgreSQL interdit toute modification ou suppression hors purge de rétention.
 */
export const auditLog = pgTable(
  'audit_log',
  {
    id: bigserial({ mode: 'number' }).primaryKey(),
    occurredAt: tstz().notNull().defaultNow(),
    actorUserId: uuid(),
    actorLabel: varchar({ length: 255 }).notNull(),
    territoryId: uuid(),
    category: auditCategory().notNull(),
    action: varchar({ length: 120 }).notNull(),
    targetType: varchar({ length: 64 }),
    targetId: varchar({ length: 64 }),
    summary: text().notNull(),
    metadata: jsonb()
      .$type<Record<string, unknown>>()
      .notNull()
      .default(sql`'{}'::jsonb`),
    ipHash: varchar({ length: 64 }),
    prevHash: varchar({ length: 64 }).notNull(),
    hash: varchar({ length: 64 }).notNull(),
  },
  (t) => [
    index('audit_log_territory_time_idx').on(t.territoryId, t.occurredAt),
    index('audit_log_category_idx').on(t.category, t.occurredAt),
    index('audit_log_target_idx').on(t.targetType, t.targetId),
  ],
);

/** File de tâches de fond sur PostgreSQL (SELECT … FOR UPDATE SKIP LOCKED). */
export const queueJobs = pgTable(
  'queue_jobs',
  {
    id: bigserial({ mode: 'number' }).primaryKey(),
    queue: varchar({ length: 64 }).notNull(),
    payload: jsonb()
      .$type<Record<string, unknown>>()
      .notNull()
      .default(sql`'{}'::jsonb`),
    runAt: tstz().notNull().defaultNow(),
    status: queueStatus().notNull().default('QUEUED'),
    attempts: integer().notNull().default(0),
    maxAttempts: integer().notNull().default(5),
    lockedAt: tstz(),
    lockedBy: varchar({ length: 64 }),
    lastError: text(),
    dedupeKey: varchar({ length: 200 }).unique(),
    createdAt: createdAt(),
    finishedAt: tstz(),
  },
  (t) => [index('queue_jobs_pick_idx').on(t.status, t.runAt)],
);

export const jobSchedules = pgTable('job_schedules', {
  name: varchar({ length: 64 }).primaryKey(),
  everyMinutes: integer().notNull(),
  lastRunAt: tstz(),
  enabled: boolean().notNull().default(true),
});

/** Emails émis : envoyés par SMTP ou conservés en boîte d'envoi (mode démo). */
export const emails = pgTable(
  'emails',
  {
    id: pk(),
    to: varchar({ length: 320 }).notNull(),
    subject: varchar({ length: 255 }).notNull(),
    html: text().notNull(),
    text: text().notNull(),
    template: varchar({ length: 64 }),
    status: emailStatus().notNull().default('QUEUED'),
    error: text(),
    providerId: varchar({ length: 255 }),
    headers: jsonb()
      .$type<Record<string, string>>()
      .notNull()
      .default(sql`'{}'::jsonb`),
    territoryId: uuid(),
    attempts: integer().notNull().default(0),
    createdAt: createdAt(),
    sentAt: tstz(),
  },
  (t) => [index('emails_status_idx').on(t.status, t.createdAt)],
);

/** Consommation de l'assistant IA (quotas mensuels, suivi des coûts). */
export const aiUsage = pgTable(
  'ai_usage',
  {
    id: pk(),
    territoryId: uuid().references(() => territories.id, { onDelete: 'set null' }),
    companyId: uuid().references(() => companies.id, { onDelete: 'set null' }),
    userId: uuid().references(() => users.id, { onDelete: 'set null' }),
    feature: aiFeature().notNull(),
    model: varchar({ length: 64 }).notNull(),
    inputTokens: integer().notNull().default(0),
    outputTokens: integer().notNull().default(0),
    credits: integer().notNull().default(1),
    fallback: boolean().notNull().default(false),
    createdAt: createdAt(),
  },
  (t) => [index('ai_usage_territory_idx').on(t.territoryId, t.createdAt), index('ai_usage_company_idx').on(t.companyId, t.createdAt)],
);

export const supportTickets = pgTable(
  'support_tickets',
  {
    id: pk(),
    number: serial().notNull().unique(),
    territoryId: uuid().references(() => territories.id, { onDelete: 'set null' }),
    subject: varchar({ length: 255 }).notNull(),
    body: text().notNull().default(''),
    status: ticketStatus().notNull().default('OPEN'),
    priority: varchar({ length: 16 }).notNull().default('NORMAL'),
    createdById: uuid().references(() => users.id, { onDelete: 'set null' }),
    assigneeId: uuid().references(() => users.id, { onDelete: 'set null' }),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [index('support_tickets_status_idx').on(t.status)],
);

/** Fil d'échanges d'un ticket : messages de la collectivité, réponses et notes internes du support. */
export const ticketMessages = pgTable(
  'ticket_messages',
  {
    id: pk(),
    ticketId: uuid()
      .notNull()
      .references(() => supportTickets.id, { onDelete: 'cascade' }),
    authorId: uuid().references(() => users.id, { onDelete: 'set null' }),
    authorLabel: varchar({ length: 255 }).notNull(),
    fromSupport: boolean().notNull().default(false),
    internal: boolean().notNull().default(false),
    body: text().notNull(),
    createdAt: createdAt(),
  },
  (t) => [index('ticket_messages_ticket_idx').on(t.ticketId, t.createdAt)],
);

/** Demandes d'exercice des droits RGPD (export, suppression, rectification). */
export const privacyRequests = pgTable('privacy_requests', {
  id: pk(),
  number: serial().notNull().unique(),
  email: citext().notNull(),
  kind: privacyRequestKind().notNull(),
  status: privacyRequestStatus().notNull().default('OPEN'),
  territoryId: uuid().references(() => territories.id, { onDelete: 'set null' }),
  note: text(),
  handledById: uuid().references(() => users.id, { onDelete: 'set null' }),
  createdAt: createdAt(),
  completedAt: tstz(),
});

/** Clés d'accès à l'API publique (marque blanche, open data, connecteurs). */
export const apiKeys = pgTable(
  'api_keys',
  {
    id: pk(),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    name: varchar({ length: 160 }).notNull(),
    prefix: varchar({ length: 12 }).notNull(),
    keyHash: varchar({ length: 64 }).notNull().unique(),
    scopes: text()
      .array()
      .notNull()
      .default(sql`'{read}'::text[]`),
    lastUsedAt: timestamp({ withTimezone: true }),
    createdAt: createdAt(),
    revokedAt: tstz(),
  },
  (t) => [index('api_keys_territory_idx').on(t.territoryId)],
);

export type ImportMapping = Partial<
  Record<'name' | 'siret' | 'naf' | 'category' | 'street' | 'postalCode' | 'inseeCode' | 'city' | 'phone' | 'email' | 'website' | 'lat' | 'lng', string>
>;

export type ImportRow = {
  line: number;
  name: string;
  siret: string | null;
  categoryId: string | null;
  categoryName: string | null;
  communeId: string | null;
  communeName: string | null;
  street: string | null;
  postalCode: string | null;
  phone: string | null;
  email: string | null;
  website: string | null;
  lat: number | null;
  lng: number | null;
  /** CREATE : nouvelle fiche ; MERGE : complète une fiche existante ; SKIP : ligne en erreur ou doublon du fichier */
  action: 'CREATE' | 'MERGE' | 'SKIP';
  existingId?: string | null;
  errors: string[];
};

export type ImportReport = {
  valid: number;
  merged: number;
  duplicatesInFile: number;
  errors: number;
  total: number;
  created?: number;
  updated?: number;
  invited?: number;
  /** Fiches créées sans email : invitation par courrier. */
  letterIds?: string[];
};

/** Lots d'import (CSV ou base SIRENE) : analyse, correspondance des colonnes, puis création des fiches. */
export const importBatches = pgTable(
  'import_batches',
  {
    id: pk(),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    createdById: uuid().references(() => users.id, { onDelete: 'set null' }),
    source: varchar({ length: 16 }).notNull().default('CSV'),
    filename: varchar({ length: 255 }).notNull(),
    status: varchar({ length: 16 }).notNull().default('ANALYZED'),
    headers: text()
      .array()
      .notNull()
      .default(sql`'{}'::text[]`),
    mapping: jsonb()
      .$type<ImportMapping>()
      .notNull()
      .default(sql`'{}'::jsonb`),
    rawRows: jsonb()
      .$type<Record<string, string>[]>()
      .notNull()
      .default(sql`'[]'::jsonb`),
    rows: jsonb()
      .$type<ImportRow[]>()
      .notNull()
      .default(sql`'[]'::jsonb`),
    report: jsonb()
      .$type<ImportReport>()
      .notNull()
      .default(sql`'{}'::jsonb`),
    defaultCategoryId: uuid(),
    error: text(),
    committedAt: tstz(),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [index('import_batches_territory_idx').on(t.territoryId, t.createdAt)],
);

/** Sondes de disponibilité (le worker interroge /api/health chaque minute). */
export const healthProbes = pgTable(
  'health_probes',
  {
    id: bigserial({ mode: 'number' }).primaryKey(),
    at: tstz().notNull().defaultNow(),
    ok: boolean().notNull(),
    latencyMs: integer().notNull(),
    detail: varchar({ length: 255 }),
  },
  (t) => [index('health_probes_at_idx').on(t.at)],
);
