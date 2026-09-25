import { sql } from 'drizzle-orm';
import { index, jsonb, pgTable, text, uuid, varchar } from 'drizzle-orm/pg-core';
import { createdAt, pk, tstz, updatedAt } from './_common';
import { establishments, media } from './business';
import { claimStatus, recordOrigin, riskLevel } from './enums';
import { territories } from './tenancy';
import { users } from './users';

export type CheckResult = {
  ok: boolean | null; // null = non vérifiable
  label: string;
  detail: string;
};

/**
 * Demande de revendication d'un établissement par un professionnel.
 * Les contrôles automatiques (SIRET/SIRENE, domaine email, téléphone, code)
 * alimentent un niveau de risque qui oriente la décision de la collectivité.
 */
export const claims = pgTable(
  'claims',
  {
    id: pk(),
    establishmentId: uuid()
      .notNull()
      .references(() => establishments.id, { onDelete: 'cascade' }),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    userId: uuid()
      .notNull()
      .references(() => users.id, { onDelete: 'cascade' }),
    status: claimStatus().notNull().default('PENDING'),
    claimantRole: varchar({ length: 120 }),
    method: varchar({ length: 16 }).notNull().default('SIRET'),

    siretProvided: varchar({ length: 14 }),
    checks: jsonb().$type<CheckResult[]>().notNull().default(sql`'[]'::jsonb`),
    sireneHolder: varchar({ length: 255 }),

    codeHash: varchar({ length: 64 }),
    /** Code chiffré tant qu'il doit pouvoir être imprimé (courrier) ; effacé après vérification. */
    codeEnc: text(),
    codeSentTo: varchar({ length: 255 }),
    codeSentAt: tstz(),
    codeVerifiedAt: tstz(),
    kbisMediaId: uuid().references(() => media.id, { onDelete: 'set null' }),

    riskLevel: riskLevel().notNull().default('MEDIUM'),

    reviewerId: uuid().references(() => users.id, { onDelete: 'set null' }),
    reviewedAt: tstz(),
    decisionNote: text(),

    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [
    index('claims_territory_status_idx').on(t.territoryId, t.status),
    index('claims_establishment_idx').on(t.establishmentId),
  ],
);

export type RevisionChange = { field: string; label: string; from: unknown; to: unknown };

/** Historique des modifications importantes d'une fiche. */
export const establishmentRevisions = pgTable(
  'establishment_revisions',
  {
    id: pk(),
    establishmentId: uuid()
      .notNull()
      .references(() => establishments.id, { onDelete: 'cascade' }),
    userId: uuid().references(() => users.id, { onDelete: 'set null' }),
    source: recordOrigin().notNull().default('PRO'),
    summary: text().notNull(),
    changes: jsonb().$type<RevisionChange[]>().notNull().default(sql`'[]'::jsonb`),
    createdAt: createdAt(),
  },
  (t) => [index('establishment_revisions_est_idx').on(t.establishmentId, t.createdAt)],
);
