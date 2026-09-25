import { sql } from 'drizzle-orm';
import { boolean, index, integer, jsonb, pgTable, text, timestamp, unique, uuid, varchar } from 'drizzle-orm/pg-core';
import { citext, createdAt, pk, tstz, updatedAt } from './_common';
import { staffRole, tokenKind, userStatus } from './enums';
import { communes, territories } from './tenancy';

export const users = pgTable('users', {
  id: pk(),
  email: citext().notNull().unique(),
  passwordHash: text(),
  firstName: varchar({ length: 120 }).notNull().default(''),
  lastName: varchar({ length: 120 }).notNull().default(''),
  phone: varchar({ length: 32 }),
  avatarUrl: text(),
  jobTitle: varchar({ length: 160 }),
  status: userStatus().notNull().default('ACTIVE'),
  emailVerifiedAt: tstz(),

  // Authentification forte
  mfaEnabled: boolean().notNull().default(false),
  mfaSecretEnc: text(),
  mfaRecoveryCodes: text()
    .array()
    .notNull()
    .default(sql`'{}'::text[]`),

  // Protection contre la force brute
  failedLoginCount: integer().notNull().default(0),
  lockedUntil: tstz(),
  lastLoginAt: tstz(),
  passwordChangedAt: tstz(),

  createdAt: createdAt(),
  updatedAt: updatedAt(),
  deletedAt: tstz(),
});

/** Sessions stockées en base : révocables individuellement, listables par l'utilisateur. */
export const sessions = pgTable(
  'sessions',
  {
    id: varchar({ length: 64 }).primaryKey(), // SHA-256 du jeton
    userId: uuid()
      .notNull()
      .references(() => users.id, { onDelete: 'cascade' }),
    createdAt: createdAt(),
    expiresAt: timestamp({ withTimezone: true }).notNull(),
    lastSeenAt: timestamp({ withTimezone: true }).notNull().defaultNow(),
    ip: varchar({ length: 64 }),
    userAgent: text(),
    mfaVerified: boolean().notNull().default(false),
    // Accès support temporaire (console plateforme)
    impersonationTerritoryId: uuid().references(() => territories.id, { onDelete: 'set null' }),
    impersonationExpiresAt: tstz(),
    impersonationTicket: varchar({ length: 64 }),
  },
  (t) => [index('sessions_user_idx').on(t.userId)],
);

/** Rôles des agents : plateforme, territoire ou commune. */
export const roleAssignments = pgTable(
  'role_assignments',
  {
    id: pk(),
    userId: uuid()
      .notNull()
      .references(() => users.id, { onDelete: 'cascade' }),
    role: staffRole().notNull(),
    territoryId: uuid().references(() => territories.id, { onDelete: 'cascade' }),
    communeId: uuid().references(() => communes.id, { onDelete: 'cascade' }),
    createdById: uuid().references(() => users.id, { onDelete: 'set null' }),
    createdAt: createdAt(),
  },
  (t) => [
    unique('role_assignments_uq').on(t.userId, t.role, t.territoryId, t.communeId).nullsNotDistinct(),
    index('role_assignments_territory_idx').on(t.territoryId),
  ],
);

/** Jetons à usage unique : vérification d'email, réinitialisation, invitations. */
export const tokens = pgTable(
  'tokens',
  {
    id: pk(),
    kind: tokenKind().notNull(),
    tokenHash: varchar({ length: 64 }).notNull().unique(),
    userId: uuid().references(() => users.id, { onDelete: 'cascade' }),
    email: citext(),
    payload: jsonb()
      .$type<Record<string, unknown>>()
      .notNull()
      .default(sql`'{}'::jsonb`),
    expiresAt: timestamp({ withTimezone: true }).notNull(),
    usedAt: tstz(),
    createdById: uuid().references(() => users.id, { onDelete: 'set null' }),
    createdAt: createdAt(),
  },
  (t) => [index('tokens_email_idx').on(t.email)],
);

/** Compteurs de limitation de débit (fenêtre fixe). */
export const rateLimits = pgTable('rate_limits', {
  key: varchar({ length: 200 }).primaryKey(),
  count: integer().notNull().default(0),
  resetAt: timestamp({ withTimezone: true }).notNull(),
});

/** Abonnements aux notifications push (un par navigateur ou appareil). */
export const pushSubscriptions = pgTable(
  'push_subscriptions',
  {
    id: pk(),
    userId: uuid()
      .notNull()
      .references(() => users.id, { onDelete: 'cascade' }),
    endpoint: text().notNull().unique(),
    p256dh: varchar({ length: 200 }).notNull(),
    auth: varchar({ length: 100 }).notNull(),
    userAgent: varchar({ length: 255 }),
    failures: integer().notNull().default(0),
    lastSuccessAt: tstz(),
    createdAt: createdAt(),
  },
  (t) => [index('push_subscriptions_user_idx').on(t.userId)],
);
