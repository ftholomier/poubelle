import { sql } from 'drizzle-orm';
import { boolean, date, index, integer, jsonb, pgTable, text, uuid, varchar } from 'drizzle-orm/pg-core';
import { createdAt, pk, tstz, updatedAt } from './_common';
import { companies } from './business';
import { contractKind, contractStatus, customerType, invoiceStatus, planKey, subscriptionStatus } from './enums';
import { territories } from './tenancy';

export type PlanLimits = {
  postsPerMonth: number | null; // null = illimité
  aiPerMonth: number | null;
  scheduling: boolean;
  newsletterChannel: boolean;
  socialChannel: boolean;
  advancedStats: boolean;
  jobs: boolean;
  appointments: boolean;
  miniSite: boolean;
  customerNewsletter: boolean;
  contactsExport: boolean;
  customQr: boolean;
  /** Formulaires personnalisés sur la fiche (devis, réservation, inscription…). */
  customForms?: boolean;
  /** Pages supplémentaires de la fiche. */
  extraPages?: boolean;
};

/** Offres entreprises, modifiables par l'exploitant (personnalisation des offres). */
export const plans = pgTable('plans', {
  key: planKey().primaryKey(),
  name: varchar({ length: 80 }).notNull(),
  priceMonthlyCents: integer().notNull().default(0),
  tagline: varchar({ length: 255 }).notNull().default(''),
  features: text()
    .array()
    .notNull()
    .default(sql`'{}'::text[]`),
  limits: jsonb().$type<PlanLimits>().notNull(),
  isActive: boolean().notNull().default(true),
  sortOrder: integer().notNull().default(0),
  updatedAt: updatedAt(),
});

export const companySubscriptions = pgTable(
  'company_subscriptions',
  {
    id: pk(),
    companyId: uuid()
      .notNull()
      .references(() => companies.id, { onDelete: 'cascade' }),
    plan: planKey().notNull(),
    status: subscriptionStatus().notNull().default('ACTIVE'),
    provider: varchar({ length: 16 }).notNull().default('MANUAL'),
    providerRef: varchar({ length: 255 }),
    startedAt: tstz().notNull().defaultNow(),
    currentPeriodEnd: tstz(),
    cancelAtPeriodEnd: boolean().notNull().default(false),
    canceledAt: tstz(),
    createdAt: createdAt(),
  },
  (t) => [index('company_subscriptions_company_idx').on(t.companyId)],
);

/** Contrats des collectivités : licence annuelle, mise en service, prestations. */
export const territoryContracts = pgTable(
  'territory_contracts',
  {
    id: pk(),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    kind: contractKind().notNull().default('LICENCE'),
    label: varchar({ length: 255 }).notNull(),
    amountCents: integer().notNull(),
    startsAt: date({ mode: 'string' }).notNull(),
    endsAt: date({ mode: 'string' }),
    status: contractStatus().notNull().default('ACTIVE'),
    signedAt: date({ mode: 'string' }),
    notes: text(),
    createdAt: createdAt(),
  },
  (t) => [index('territory_contracts_territory_idx').on(t.territoryId)],
);

export type InvoiceLine = { label: string; quantity: number; unitCents: number; vatRate: number };

/** Factures (numérotation continue par année, dépôt Chorus Pro pour le secteur public). */
export const invoices = pgTable(
  'invoices',
  {
    id: pk(),
    number: varchar({ length: 32 }).notNull().unique(),
    customerType: customerType().notNull(),
    territoryId: uuid().references(() => territories.id, { onDelete: 'set null' }),
    companyId: uuid().references(() => companies.id, { onDelete: 'set null' }),
    customerName: varchar({ length: 255 }).notNull(),
    customerAddress: text(),
    issuedAt: date({ mode: 'string' }).notNull(),
    dueAt: date({ mode: 'string' }).notNull(),
    status: invoiceStatus().notNull().default('ISSUED'),
    lines: jsonb().$type<InvoiceLine[]>().notNull(),
    totalHtCents: integer().notNull(),
    vatCents: integer().notNull(),
    totalTtcCents: integer().notNull(),
    paidAt: date({ mode: 'string' }),
    paymentMethod: varchar({ length: 32 }),
    chorusRef: varchar({ length: 64 }),
    notes: text(),
    createdAt: createdAt(),
  },
  (t) => [index('invoices_territory_idx').on(t.territoryId), index('invoices_company_idx').on(t.companyId)],
);

export const invoiceCounters = pgTable('invoice_counters', {
  year: integer().primaryKey(),
  lastNumber: integer().notNull().default(0),
});
