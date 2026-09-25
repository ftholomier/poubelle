import { sql } from 'drizzle-orm';
import { boolean, date, doublePrecision, index, integer, jsonb, pgTable, primaryKey, text, unique, uniqueIndex, uuid, varchar } from 'drizzle-orm/pg-core';
import { createdAt, pk, updatedAt } from './_common';
import { activityFamily, attributeGroup, moduleKey, territoryKind, territoryStatus } from './enums';

export type TerritorySettings = {
  /** MANUAL : chaque revendication est validée par un agent. AUTO : validation automatique si le SIRET correspond. */
  claimValidation?: 'MANUAL' | 'AUTO';
  /** PRE : les publications des pros sont modérées avant publication. POST : publiées puis modérables. */
  postModeration?: 'PRE' | 'POST';
  newsletterName?: string;
  newsletterSenderName?: string;
  newsletterReplyTo?: string;
  jobsTitle?: string;
  jobsIntro?: string;
  footerText?: string;
  socials?: { facebook?: string; instagram?: string; linkedin?: string };
  dpoEmail?: string;
  legalPublisher?: string;
  hostingNotice?: string;
  /** Service d'itinéraire proposé sur les fiches. */
  directionsProvider?: 'google' | 'osm' | 'apple';
  /** Rubrique mise en avant dans la navigation (slug de campagne). */
  featuredCampaignSlug?: string;
  /** Titre de la rubrique Circuits (« Suivez le fil de la Loue »). */
  circuitsTitle?: string;
  /** Encadré « Vivre ici » des offres d'emploi (attractivité du territoire). */
  livingTitle?: string;
  livingText?: string;
  livingImageUrl?: string;
  /** Objectif d'adoption affiché au tableau de bord (part de fiches revendiquées). */
  adoptionGoalPct?: number;
  adoptionGoalLabel?: string;
  /** Double authentification exigée pour toute l'équipe (sinon : administrateurs seulement). */
  requireMfaForAll?: boolean;
  /** Marque blanche (option) : aucune mention de terricom sur le portail ni dans les emails du territoire. */
  whiteLabel?: boolean;
};

export type HomeBlock = 'search' | 'openNow' | 'campaign' | 'map' | 'feed' | 'circuits' | 'jobs' | 'newsletter';

export const HOME_BLOCKS: { key: HomeBlock; label: string }[] = [
  { key: 'search', label: 'Recherche intelligente' },
  { key: 'openNow', label: 'Ouvert maintenant' },
  { key: 'campaign', label: 'Campagne à la une' },
  { key: 'map', label: 'Carte interactive' },
  { key: 'feed', label: 'Fil des actualités' },
  { key: 'circuits', label: 'Circuits' },
  { key: 'jobs', label: "Offres d'emploi" },
  { key: 'newsletter', label: 'Inscription newsletter' },
];

/** Un territoire est un client de la plateforme : EPCI, commune indépendante, office… */
export const territories = pgTable(
  'territories',
  {
    id: pk(),
    slug: varchar({ length: 64 }).notNull().unique(),
    name: varchar({ length: 160 }).notNull(),
    legalName: varchar({ length: 255 }).notNull(),
    kind: territoryKind().notNull().default('CC'),
    status: territoryStatus().notNull().default('ONBOARDING'),
    isPilot: boolean().notNull().default(false),
    siren: varchar({ length: 9 }),
    population: integer(),
    departmentCode: varchar({ length: 3 }),

    // Personnalisation
    initials: varchar({ length: 4 }).notNull().default('T'),
    tagline: varchar({ length: 160 }).notNull().default('Commerces & savoir-faire'),
    colorPrimary: varchar({ length: 9 }).notNull().default('#1F6B52'),
    colorAccent: varchar({ length: 9 }).notNull().default('#F4B266'),
    logoUrl: text(),
    heroTitle: text(),
    heroSubtitle: text(),
    heroImageUrl: text(),
    homeBlocks: jsonb()
      .$type<HomeBlock[]>()
      .notNull()
      .default(sql`'["search","openNow","campaign","map","feed","circuits","jobs","newsletter"]'::jsonb`),
    primaryHost: varchar({ length: 255 }),

    // Carte
    centerLat: doublePrecision(),
    centerLng: doublePrecision(),
    defaultZoom: integer().notNull().default(11),

    contactEmail: varchar({ length: 255 }),
    websiteUrl: text(),
    settings: jsonb()
      .$type<TerritorySettings>()
      .notNull()
      .default(sql`'{}'::jsonb`),

    // Quotas contractuels
    quotaEstablishments: integer().notNull().default(1500),
    quotaEmailsMonthly: integer().notNull().default(40000),
    quotaAiCreditsMonthly: integer().notNull().default(5000),

    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [index('territories_status_idx').on(t.status)],
);

/** Domaines et sous-domaines servant le portail d'un territoire. */
export const territoryDomains = pgTable(
  'territory_domains',
  {
    id: pk(),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    host: varchar({ length: 255 }).notNull().unique(),
    isPrimary: boolean().notNull().default(false),
    verifiedAt: date({ mode: 'string' }),
    createdAt: createdAt(),
  },
  (t) => [index('territory_domains_territory_idx').on(t.territoryId)],
);

export const territoryModules = pgTable(
  'territory_modules',
  {
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    module: moduleKey().notNull(),
    enabled: boolean().notNull().default(true),
    updatedAt: updatedAt(),
  },
  (t) => [primaryKey({ columns: [t.territoryId, t.module] })],
);

/** Référentiel des communes (code officiel géographique). */
export const communes = pgTable(
  'communes',
  {
    id: pk(),
    inseeCode: varchar({ length: 5 }).notNull().unique(),
    name: varchar({ length: 160 }).notNull(),
    slug: varchar({ length: 160 }).notNull().unique(),
    postalCodes: text()
      .array()
      .notNull()
      .default(sql`'{}'::text[]`),
    departmentCode: varchar({ length: 3 }),
    population: integer(),
    lat: doublePrecision(),
    lng: doublePrecision(),

    tagline: text(),
    description: text(),
    heroImageUrl: text(),
    mayorQuote: text(),
    mayorName: varchar({ length: 160 }),
    mayorRole: varchar({ length: 160 }),
    mayorPhotoUrl: text(),

    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [index('communes_name_idx').on(t.name)],
);

/**
 * Rattachement historisé d'une commune à un territoire.
 * Une commune n'a qu'un rattachement courant (valid_to IS NULL) : un changement
 * d'intercommunalité clôt l'ancien rattachement et en ouvre un nouveau.
 */
export const communeMemberships = pgTable(
  'commune_memberships',
  {
    id: pk(),
    communeId: uuid()
      .notNull()
      .references(() => communes.id, { onDelete: 'cascade' }),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    validFrom: date({ mode: 'string' })
      .notNull()
      .default(sql`current_date`),
    validTo: date({ mode: 'string' }),
    createdAt: createdAt(),
  },
  (t) => [
    uniqueIndex('commune_memberships_current_uq')
      .on(t.communeId)
      .where(sql`valid_to is null`),
    index('commune_memberships_territory_idx').on(t.territoryId),
  ],
);

/** Catégories d'activité. territory_id NULL = taxonomie commune à la plateforme. */
export const categories = pgTable(
  'categories',
  {
    id: pk(),
    territoryId: uuid().references(() => territories.id, { onDelete: 'cascade' }),
    family: activityFamily().notNull(),
    slug: varchar({ length: 120 }).notNull(),
    name: varchar({ length: 160 }).notNull(),
    nafCodes: text()
      .array()
      .notNull()
      .default(sql`'{}'::text[]`),
    synonyms: text()
      .array()
      .notNull()
      .default(sql`'{}'::text[]`),
    sortOrder: integer().notNull().default(0),
    isActive: boolean().notNull().default(true),
    createdAt: createdAt(),
  },
  (t) => [unique('categories_territory_slug_uq').on(t.territoryId, t.slug).nullsNotDistinct(), index('categories_family_idx').on(t.family)],
);

/** Catégories vues par un territoire : nom affiché sur son portail, catégorie masquée des choix. */
export const territoryCategories = pgTable(
  'territory_categories',
  {
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'cascade' }),
    categoryId: uuid()
      .notNull()
      .references(() => categories.id, { onDelete: 'cascade' }),
    label: varchar({ length: 160 }),
    hidden: boolean().notNull().default(false),
    updatedAt: updatedAt(),
  },
  (t) => [primaryKey({ columns: [t.territoryId, t.categoryId] })],
);

/** Services, moyens de paiement, accessibilité, labels. */
export const attributes = pgTable(
  'attributes',
  {
    id: pk(),
    territoryId: uuid().references(() => territories.id, { onDelete: 'cascade' }),
    group: attributeGroup().notNull(),
    slug: varchar({ length: 120 }).notNull(),
    label: varchar({ length: 160 }).notNull(),
    isFilter: boolean().notNull().default(false),
    sortOrder: integer().notNull().default(0),
    createdAt: createdAt(),
  },
  (t) => [unique('attributes_territory_slug_uq').on(t.territoryId, t.slug).nullsNotDistinct()],
);
