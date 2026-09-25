import { sql } from 'drizzle-orm';
import { boolean, date, doublePrecision, index, integer, jsonb, pgTable, primaryKey, smallint, text, time, unique, uuid, varchar } from 'drizzle-orm/pg-core';
import { createdAt, pk, tsvector, tstz, updatedAt } from './_common';
import { companyMemberRole, establishmentStatus, planKey, recordOrigin } from './enums';
import { attributes, categories, communes, territories } from './tenancy';
import { users } from './users';

/** Traductions d'une fiche (module MULTILINGUAL) : texte traduit et empreinte du texte français d'origine. */
export type EstablishmentTranslations = Partial<
  Record<'en' | 'de', { tagline?: string; description?: string; hash?: string; source?: 'ai' | 'manual'; translatedAt?: string }>
>;
export type Socials = {
  facebook?: string;
  instagram?: string;
  linkedin?: string;
  tiktok?: string;
  youtube?: string;
};

/** Entité juridique (SIREN). Une entreprise peut avoir plusieurs établissements. */
export const companies = pgTable('companies', {
  id: pk(),
  siren: varchar({ length: 9 }).unique(),
  legalName: varchar({ length: 255 }).notNull(),
  tradeName: varchar({ length: 255 }),
  legalForm: varchar({ length: 120 }),
  nafCode: varchar({ length: 8 }),
  plan: planKey().notNull().default('ESSENTIEL'),
  billingEmail: varchar({ length: 255 }),
  billingName: varchar({ length: 255 }),
  billingAddress: text(),
  vatNumber: varchar({ length: 32 }),
  /**
   * Connecteur de synchronisation (offres Premium et Communication) : adresse appelée à chaque contenu publié
   * (Make, Zapier, n8n, site de l'entreprise…), requêtes signées HMAC avec webhookSecret (chiffré).
   */
  socialWebhookUrl: text(),
  webhookSecret: text(),
  webhookLastAt: tstz(),
  webhookLastStatus: varchar({ length: 120 }),
  createdAt: createdAt(),
  updatedAt: updatedAt(),
});

export const companyMembers = pgTable(
  'company_members',
  {
    companyId: uuid()
      .notNull()
      .references(() => companies.id, { onDelete: 'cascade' }),
    userId: uuid()
      .notNull()
      .references(() => users.id, { onDelete: 'cascade' }),
    role: companyMemberRole().notNull().default('EDITOR'),
    createdAt: createdAt(),
  },
  (t) => [primaryKey({ columns: [t.companyId, t.userId] }), index('company_members_user_idx').on(t.userId)],
);

/** Réglages du mini-site (offre Communication). */
export type MiniSite = {
  enabled?: boolean;
  /** En-tête : grande photo ou aplat de la couleur de marque. */
  hero?: 'photo' | 'color';
  /** Accroche de l'en-tête. */
  headline?: string | null;
  /** Bouton principal de l'en-tête. */
  cta?: { label: string; href: string } | null;
  /** Ordre et visibilité des sections de la page d'accueil. */
  sections?: MiniSiteSection[];
};
export type MiniSiteSection = 'presentation' | 'offre' | 'produits' | 'actualites' | 'pages' | 'formulaires' | 'contact';

/**
 * Établissement : l'unité présentée au public (une boutique, un atelier, un restaurant).
 * territory_id est dénormalisé depuis le rattachement courant de la commune
 * pour cloisonner efficacement les requêtes par territoire.
 */
export const establishments = pgTable(
  'establishments',
  {
    id: pk(),
    companyId: uuid()
      .notNull()
      .references(() => companies.id, { onDelete: 'restrict' }),
    communeId: uuid()
      .notNull()
      .references(() => communes.id, { onDelete: 'restrict' }),
    territoryId: uuid()
      .notNull()
      .references(() => territories.id, { onDelete: 'restrict' }),
    categoryId: uuid()
      .notNull()
      .references(() => categories.id, { onDelete: 'restrict' }),

    slug: varchar({ length: 160 }).notNull(),
    name: varchar({ length: 255 }).notNull(),
    siret: varchar({ length: 14 }).unique(),
    status: establishmentStatus().notNull().default('PRECREATED'),
    origin: recordOrigin().notNull().default('IMPORT'),

    activityLabel: varchar({ length: 160 }),
    tagline: varchar({ length: 255 }),
    description: text(),

    street: varchar({ length: 255 }),
    postalCode: varchar({ length: 10 }),
    lat: doublePrecision(),
    lng: doublePrecision(),

    phone: varchar({ length: 32 }),
    email: varchar({ length: 255 }),
    website: text(),
    socials: jsonb()
      .$type<Socials>()
      .notNull()
      .default(sql`'{}'::jsonb`),

    logoUrl: text(),
    coverUrl: text(),

    priceInfo: text(),
    serviceArea: text(),
    serviceAreaKm: integer(),
    accessibilityInfo: text(),

    appointmentsEnabled: boolean().notNull().default(false),
    appointmentInfo: text(),
    themeColor: varchar({ length: 9 }),
    miniSite: jsonb()
      .$type<MiniSite>()
      .notNull()
      .default(sql`'{}'::jsonb`),
    translations: jsonb()
      .$type<EstablishmentTranslations>()
      .notNull()
      .default(sql`'{}'::jsonb`),

    isFeatured: boolean().notNull().default(false),
    completeness: integer().notNull().default(0),
    hoursConfirmedAt: tstz(),
    lastActivityAt: tstz(),
    /** Dernière invitation à revendiquer la fiche (email ou courrier) et nombre d'envois (relances automatiques). */
    invitedAt: tstz(),
    invitationCount: integer().notNull().default(0),
    publishedAt: tstz(),
    suspendedReason: text(),
    archivedAt: tstz(),

    qrCode: varchar({ length: 16 }).notNull().unique(),

    /** Mots-clés dérivés (catégories, services, produits) maintenus par l'application. */
    searchKeywords: text().notNull().default(''),
    searchVector: tsvector().generatedAlwaysAs(
      sql`setweight(to_tsvector('french', f_unaccent(coalesce(name, ''))), 'A') || setweight(to_tsvector('french', f_unaccent(coalesce(activity_label, '') || ' ' || coalesce(search_keywords, ''))), 'B') || setweight(to_tsvector('french', f_unaccent(coalesce(tagline, '') || ' ' || coalesce(description, ''))), 'C')`,
    ),

    createdById: uuid().references(() => users.id, { onDelete: 'set null' }),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [
    unique('establishments_commune_slug_uq').on(t.communeId, t.slug),
    index('establishments_territory_status_idx').on(t.territoryId, t.status),
    index('establishments_company_idx').on(t.companyId),
    index('establishments_category_idx').on(t.categoryId),
    index('establishments_search_idx').using('gin', t.searchVector),
    index('establishments_name_trgm_idx').using('gin', sql`f_unaccent(${t.name}) gin_trgm_ops`),
  ],
);

export const establishmentCategories = pgTable(
  'establishment_categories',
  {
    establishmentId: uuid()
      .notNull()
      .references(() => establishments.id, { onDelete: 'cascade' }),
    categoryId: uuid()
      .notNull()
      .references(() => categories.id, { onDelete: 'cascade' }),
  },
  (t) => [primaryKey({ columns: [t.establishmentId, t.categoryId] })],
);

export const establishmentAttributes = pgTable(
  'establishment_attributes',
  {
    establishmentId: uuid()
      .notNull()
      .references(() => establishments.id, { onDelete: 'cascade' }),
    attributeId: uuid()
      .notNull()
      .references(() => attributes.id, { onDelete: 'cascade' }),
  },
  (t) => [primaryKey({ columns: [t.establishmentId, t.attributeId] }), index('establishment_attributes_attr_idx').on(t.attributeId)],
);

/** Horaires hebdomadaires ; plusieurs créneaux possibles par jour (0 = lundi … 6 = dimanche). */
export const openingHours = pgTable(
  'opening_hours',
  {
    id: pk(),
    establishmentId: uuid()
      .notNull()
      .references(() => establishments.id, { onDelete: 'cascade' }),
    weekday: smallint().notNull(),
    opensAt: time().notNull(),
    closesAt: time().notNull(),
  },
  (t) => [index('opening_hours_est_idx').on(t.establishmentId)],
);

/** Horaires exceptionnels : fermeture ou ouverture spécifique à une date. */
export const exceptionalHours = pgTable(
  'exceptional_hours',
  {
    id: pk(),
    establishmentId: uuid()
      .notNull()
      .references(() => establishments.id, { onDelete: 'cascade' }),
    date: date({ mode: 'string' }).notNull(),
    closed: boolean().notNull().default(true),
    opensAt: time(),
    closesAt: time(),
    label: varchar({ length: 160 }),
  },
  (t) => [index('exceptional_hours_est_date_idx').on(t.establishmentId, t.date)],
);

export type MediaVariants = Partial<Record<'w320' | 'w640' | 'w1280' | 'w1920', string>>;

/** Médias : photos d'établissement, visuels, documents privés (Kbis, CV). */
export const media = pgTable(
  'media',
  {
    id: pk(),
    territoryId: uuid().references(() => territories.id, { onDelete: 'cascade' }),
    establishmentId: uuid().references(() => establishments.id, { onDelete: 'cascade' }),
    ownerType: varchar({ length: 32 }).notNull(),
    ownerId: uuid(),
    kind: varchar({ length: 16 }).notNull().default('IMAGE'),
    url: text().notNull(),
    storageKey: text(),
    mimeType: varchar({ length: 100 }),
    sizeBytes: integer(),
    width: integer(),
    height: integer(),
    variants: jsonb()
      .$type<MediaVariants>()
      .notNull()
      .default(sql`'{}'::jsonb`),
    alt: varchar({ length: 255 }),
    tag: varchar({ length: 64 }),
    sortOrder: integer().notNull().default(0),
    isPrivate: boolean().notNull().default(false),
    uploadedById: uuid().references(() => users.id, { onDelete: 'set null' }),
    createdAt: createdAt(),
  },
  (t) => [index('media_establishment_idx').on(t.establishmentId, t.sortOrder), index('media_owner_idx').on(t.ownerType, t.ownerId)],
);

/** Produits & services présentés sur la fiche, avec tarif indicatif. */
export const products = pgTable(
  'products',
  {
    id: pk(),
    establishmentId: uuid()
      .notNull()
      .references(() => establishments.id, { onDelete: 'cascade' }),
    kind: varchar({ length: 16 }).notNull().default('PRODUCT'),
    name: varchar({ length: 255 }).notNull(),
    description: text(),
    priceText: varchar({ length: 120 }),
    imageUrl: text(),
    sortOrder: integer().notNull().default(0),
    createdAt: createdAt(),
  },
  (t) => [index('products_est_idx').on(t.establishmentId, t.sortOrder)],
);

/** Pages supplémentaires du mini-site (offre Communication). */
export const establishmentPages = pgTable(
  'establishment_pages',
  {
    id: pk(),
    establishmentId: uuid()
      .notNull()
      .references(() => establishments.id, { onDelete: 'cascade' }),
    title: varchar({ length: 160 }).notNull(),
    slug: varchar({ length: 160 }).notNull(),
    body: text().notNull().default(''),
    coverUrl: text(),
    sortOrder: integer().notNull().default(0),
    published: boolean().notNull().default(true),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [unique('establishment_pages_slug_uq').on(t.establishmentId, t.slug)],
);

export type FormFieldType = 'text' | 'textarea' | 'email' | 'tel' | 'date' | 'number' | 'select' | 'checkbox';
export type FormField = { id: string; label: string; type: FormFieldType; required: boolean; options?: string[]; help?: string | null };

/** Formulaires personnalisés de la fiche (offre Premium) : les réponses arrivent dans la messagerie. */
export const establishmentForms = pgTable(
  'establishment_forms',
  {
    id: pk(),
    establishmentId: uuid()
      .notNull()
      .references(() => establishments.id, { onDelete: 'cascade' }),
    title: varchar({ length: 160 }).notNull(),
    intro: text(),
    fields: jsonb()
      .$type<FormField[]>()
      .notNull()
      .default(sql`'[]'::jsonb`),
    submitLabel: varchar({ length: 60 }).notNull().default('Envoyer'),
    successText: text(),
    isActive: boolean().notNull().default(true),
    sortOrder: integer().notNull().default(0),
    createdAt: createdAt(),
    updatedAt: updatedAt(),
  },
  (t) => [index('establishment_forms_est_idx').on(t.establishmentId, t.sortOrder)],
);
