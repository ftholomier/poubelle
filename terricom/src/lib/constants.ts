/**
 * Référentiels partagés client/serveur : libellés français et couleurs de la charte terricom.
 */

export const BRAND = {
  ink: '#14201B',
  text: '#1C2320',
  green: '#1F6B52',
  amber: '#F4B266',
  cream: '#F7F4EC',
  paper: '#FFFDF8',
  line: '#E4DFD3',
  muted: '#5E655F',
  brick: '#C8702A',
  danger: '#D95C4E',
} as const;

export type Family = 'COMMERCE' | 'ARTISAN' | 'PRODUCTEUR' | 'RESTAURATION' | 'SERVICES';

export const FAMILIES: Record<Family, { label: string; plural: string; color: string; slug: string }> = {
  COMMERCE: { label: 'Commerce', plural: 'commerces', color: '#C8892A', slug: 'commerce' },
  ARTISAN: { label: 'Artisan', plural: 'artisans', color: '#3E6FB0', slug: 'artisan' },
  PRODUCTEUR: { label: 'Producteur', plural: 'producteurs', color: '#3F8F4E', slug: 'producteur' },
  RESTAURATION: { label: 'Restauration', plural: 'restaurants', color: '#D95C4E', slug: 'restauration' },
  SERVICES: { label: 'Services', plural: 'services', color: '#7A5BB5', slug: 'services' },
};

export const FAMILY_ORDER: Family[] = ['COMMERCE', 'ARTISAN', 'PRODUCTEUR', 'RESTAURATION', 'SERVICES'];

export function familyFromSlug(slug: string | null | undefined): Family | null {
  if (!slug) return null;
  const found = FAMILY_ORDER.find((f) => FAMILIES[f].slug === slug.toLowerCase());
  return found ?? null;
}

export type PostKind = 'NEWS' | 'PROMO' | 'NOUVEAUTE' | 'EVENT' | 'HOURS' | 'JOB';

export const POST_KINDS: Record<PostKind, { label: string; short: string; bg: string }> = {
  NEWS: { label: 'Actualité', short: 'Actualité', bg: '#E1ECE5' },
  PROMO: { label: 'Promotion', short: 'Promo', bg: '#F4B266' },
  NOUVEAUTE: { label: 'Nouveauté', short: 'Nouveauté', bg: '#D6E8B4' },
  EVENT: { label: 'Événement', short: 'Événement', bg: '#DCD3F3' },
  HOURS: { label: 'Horaires', short: 'Horaires', bg: '#CDE3F2' },
  JOB: { label: 'Recrutement', short: 'Recrutement', bg: '#F6C9C1' },
};

export const POST_KIND_ORDER: PostKind[] = ['NEWS', 'PROMO', 'EVENT', 'NOUVEAUTE', 'HOURS', 'JOB'];

export type EventKind = 'MARCHE' | 'DEGUSTATION' | 'PORTES_OUVERTES' | 'ATELIER' | 'CONCERT' | 'ANIMATION' | 'SALON' | 'AUTRE';

export const EVENT_KINDS: Record<EventKind, { label: string; plural: string; bg: string }> = {
  MARCHE: { label: 'Marché', plural: 'Marchés', bg: '#F4B266' },
  DEGUSTATION: { label: 'Dégustation', plural: 'Dégustations', bg: '#D6E8B4' },
  PORTES_OUVERTES: { label: 'Portes ouvertes', plural: 'Portes ouvertes', bg: '#DCD3F3' },
  ATELIER: { label: 'Atelier', plural: 'Ateliers', bg: '#CDE3F2' },
  CONCERT: { label: 'Concert', plural: 'Concerts', bg: '#F6C9C1' },
  ANIMATION: { label: 'Animation', plural: 'Animations', bg: '#E1ECE5' },
  SALON: { label: 'Salon', plural: 'Salons', bg: '#EDE8DC' },
  AUTRE: { label: 'Événement', plural: 'Événements', bg: '#EDE8DC' },
};

/** Programme type proposé à la création d'un événement, selon sa nature. */
export const EVENT_PROGRAM_TEMPLATES: Partial<Record<EventKind, { label: string; text: string }[]>> = {
  MARCHE: [
    { label: 'Ouverture', text: 'Installation des exposants et vin chaud' },
    { label: 'Après-midi', text: 'Animations enfants et musique' },
    { label: 'Fin de journée', text: 'Tombola des commerçants' },
  ],
  DEGUSTATION: [
    { label: 'Accueil', text: 'Présentation du producteur et de son métier' },
    { label: 'Dégustation', text: '3 à 5 produits commentés' },
    { label: 'Boutique', text: 'Vente sur place, emballage cadeau offert' },
  ],
  PORTES_OUVERTES: [
    { label: 'Visite', text: "Découverte de l'atelier et des outils" },
    { label: 'Démonstration', text: "L'artisan au travail, en direct" },
    { label: 'Échanges', text: 'Questions, commandes et devis' },
  ],
  ATELIER: [
    { label: 'Accueil', text: 'Tablier fourni, présentation du matériel' },
    { label: 'Atelier', text: 'Réalisation guidée pas à pas' },
    { label: 'À emporter', text: 'Chacun repart avec sa création' },
  ],
};

export type ContractType = 'CDI' | 'CDD' | 'ALTERNANCE' | 'SAISONNIER' | 'STAGE' | 'INTERIM' | 'INDEPENDANT';

export const CONTRACT_TYPES: Record<ContractType, { label: string; bg: string }> = {
  CDI: { label: 'CDI', bg: '#D6E8B4' },
  CDD: { label: 'CDD', bg: '#CDE3F2' },
  ALTERNANCE: { label: 'Alternance', bg: '#DCD3F3' },
  SAISONNIER: { label: 'Saisonnier', bg: '#F4B266' },
  STAGE: { label: 'Stage', bg: '#F6C9C1' },
  INTERIM: { label: 'Intérim', bg: '#EDE8DC' },
  INDEPENDANT: { label: 'Indépendant', bg: '#E1ECE5' },
};

export type EstablishmentStatus = 'PRECREATED' | 'TO_COMPLETE' | 'CLAIMED' | 'VALIDATED' | 'SUSPENDED' | 'ARCHIVED';

export const ESTABLISHMENT_STATUS: Record<EstablishmentStatus, { label: string; bg: string; fg: string }> = {
  PRECREATED: { label: 'Précréée', bg: '#ECEAE4', fg: '#5E655F' },
  TO_COMPLETE: { label: 'À compléter', bg: '#F7E6D2', fg: '#9A5419' },
  CLAIMED: { label: 'Revendiquée', bg: '#DDE7F2', fg: '#2C5A86' },
  VALIDATED: { label: 'Validée', bg: '#DDEEE3', fg: '#1F6B52' },
  SUSPENDED: { label: 'Suspendue', bg: '#F5DCD8', fg: '#9C3328' },
  ARCHIVED: { label: 'Archivée', bg: '#E4E7E1', fg: '#5E655F' },
};

export const ESTABLISHMENT_STATUS_ORDER: EstablishmentStatus[] = ['PRECREATED', 'TO_COMPLETE', 'CLAIMED', 'VALIDATED', 'SUSPENDED', 'ARCHIVED'];

/** Statuts visibles sur le portail public. */
export const PUBLIC_STATUSES: EstablishmentStatus[] = ['PRECREATED', 'TO_COMPLETE', 'CLAIMED', 'VALIDATED'];

export type PlanKey = 'ESSENTIEL' | 'PREMIUM' | 'COMMUNICATION';

export const PLAN_LABELS: Record<PlanKey, string> = {
  ESSENTIEL: 'Essentiel',
  PREMIUM: 'Premium',
  COMMUNICATION: 'Communication',
};

export type TerritoryKind = 'CC' | 'CA' | 'CU' | 'METROPOLE' | 'COMMUNE' | 'PETR' | 'OFFICE' | 'AUTRE';

export const TERRITORY_KINDS: Record<TerritoryKind, { label: string; short: string }> = {
  CC: { label: 'Communauté de communes', short: 'CC' },
  CA: { label: "Communauté d'agglomération", short: 'CA' },
  CU: { label: 'Communauté urbaine', short: 'CU' },
  METROPOLE: { label: 'Métropole', short: 'Métropole' },
  COMMUNE: { label: 'Commune indépendante', short: 'Commune' },
  PETR: { label: 'Pays / PETR', short: 'PETR' },
  OFFICE: { label: 'Office économique', short: 'Office' },
  AUTRE: { label: 'Autre structure', short: 'Autre' },
};

export type TerritoryStatus = 'ONBOARDING' | 'ACTIVE' | 'SUSPENDED' | 'CHURNED';

export const TERRITORY_STATUS: Record<TerritoryStatus, { label: string; color: string }> = {
  ACTIVE: { label: 'Actif', color: '#1F6B52' },
  ONBOARDING: { label: 'Onboarding', color: '#3E6FB0' },
  SUSPENDED: { label: 'Suspendu', color: '#D95C4E' },
  CHURNED: { label: 'Résilié', color: '#9A9F95' },
};

export type StaffRole = 'PLATFORM_ADMIN' | 'PLATFORM_SUPPORT' | 'PLATFORM_SALES' | 'TERRITORY_ADMIN' | 'TERRITORY_EDITOR' | 'COMMUNE_ADMIN' | 'COMMUNE_EDITOR';

export const STAFF_ROLES: Record<StaffRole, string> = {
  PLATFORM_ADMIN: 'Super administrateur',
  PLATFORM_SUPPORT: 'Support plateforme',
  PLATFORM_SALES: 'Commercial',
  TERRITORY_ADMIN: 'Admin territoriale',
  TERRITORY_EDITOR: 'Chargé·e de communication',
  COMMUNE_ADMIN: 'Admin communale',
  COMMUNE_EDITOR: 'Agent communal',
};

export type ModuleKey = 'PORTAL' | 'MAP' | 'NEWSLETTER' | 'IMPORT' | 'CAMPAIGNS' | 'AI' | 'CIRCUITS' | 'JOBS' | 'MULTILINGUAL' | 'APPOINTMENTS';

export const MODULES: Record<ModuleKey, { label: string; tag: 'MVP' | 'V2' }> = {
  PORTAL: { label: 'Portail & fiches', tag: 'MVP' },
  MAP: { label: 'Cartographie', tag: 'MVP' },
  NEWSLETTER: { label: 'Newsletters territoriales', tag: 'MVP' },
  IMPORT: { label: 'Import SIRENE', tag: 'MVP' },
  CAMPAIGNS: { label: 'Campagnes & animation', tag: 'MVP' },
  AI: { label: 'Assistant IA', tag: 'V2' },
  CIRCUITS: { label: 'Circuits & QR codes', tag: 'V2' },
  JOBS: { label: 'Recrutement local', tag: 'V2' },
  MULTILINGUAL: { label: 'Multilingue', tag: 'V2' },
  APPOINTMENTS: { label: 'Prise de rendez-vous', tag: 'V2' },
};

export const MODULE_ORDER: ModuleKey[] = ['PORTAL', 'MAP', 'NEWSLETTER', 'IMPORT', 'CAMPAIGNS', 'AI', 'CIRCUITS', 'JOBS', 'MULTILINGUAL', 'APPOINTMENTS'];

export type DealStage = 'PROSPECT' | 'FIRST_CONTACT' | 'DEMO' | 'PROPOSAL' | 'NEGOTIATION' | 'SIGNED' | 'ONBOARDING' | 'ACTIVE' | 'LOST';

export const DEAL_STAGES: DealStage[] = ['PROSPECT', 'FIRST_CONTACT', 'DEMO', 'PROPOSAL', 'NEGOTIATION', 'SIGNED', 'ONBOARDING', 'ACTIVE'];

export const DEAL_STAGE_LABELS: Record<DealStage, string> = {
  PROSPECT: 'Prospect',
  FIRST_CONTACT: '1er contact',
  DEMO: 'Démo',
  PROPOSAL: 'Proposition',
  NEGOTIATION: 'Négociation',
  SIGNED: 'Signé',
  ONBOARDING: 'Onboarding',
  ACTIVE: 'Actif',
  LOST: 'Perdu',
};

/** Regroupement des étapes en colonnes de pipeline (console). */
export const PIPELINE_GROUPS: { key: 'act' | 'onb' | 'neg' | 'pro'; label: string; bg: string; stages: DealStage[] }[] = [
  { key: 'act', label: 'Actifs', bg: '#D6E8B4', stages: ['ACTIVE'] },
  { key: 'onb', label: 'Onboarding', bg: '#CDE3F2', stages: ['SIGNED', 'ONBOARDING'] },
  { key: 'neg', label: 'Négociation', bg: '#F4B266', stages: ['PROPOSAL', 'NEGOTIATION'] },
  { key: 'pro', label: 'Prospects', bg: '#E4E7E1', stages: ['PROSPECT', 'FIRST_CONTACT', 'DEMO'] },
];

export type AuditCategory =
  | 'VALIDATION'
  | 'SUPPORT'
  | 'MODIFICATION'
  | 'IMPORT'
  | 'ENVOI'
  | 'SECURITE'
  | 'MODERATION'
  | 'CONFIGURATION'
  | 'RGPD'
  | 'AUTH'
  | 'FACTURATION';

export const AUDIT_CATEGORIES: Record<AuditCategory, { label: string; bg: string }> = {
  VALIDATION: { label: 'Validation', bg: '#D6E8B4' },
  SUPPORT: { label: 'Support', bg: '#F6C9C1' },
  MODIFICATION: { label: 'Modification', bg: '#CDE3F2' },
  IMPORT: { label: 'Import', bg: '#E4E7E1' },
  ENVOI: { label: 'Envoi', bg: '#F4B266' },
  SECURITE: { label: 'Sécurité', bg: '#F5DCD8' },
  MODERATION: { label: 'Modération', bg: '#DCD3F3' },
  CONFIGURATION: { label: 'Configuration', bg: '#E4E7E1' },
  RGPD: { label: 'RGPD', bg: '#D6E8B4' },
  AUTH: { label: 'Connexion', bg: '#E4E7E1' },
  FACTURATION: { label: 'Facturation', bg: '#F4B266' },
};

/** Durées de conservation (registre des traitements, purge automatique par le worker). */
export const RETENTION = {
  /** Abonnés sans aucune ouverture : suppression après 3 ans. */
  subscribersInactiveMonths: 36,
  /** Messages envoyés aux professionnels : 3 ans après le dernier échange. */
  messagesMonths: 36,
  /** Candidatures (et CV) : 2 ans maximum. */
  applicationsMonths: 24,
  /** Demandes de rendez-vous : 12 mois. */
  appointmentsMonths: 12,
  /** Journal d'audit : 12 mois. */
  auditMonths: 12,
  /** Événements d'audience bruts : 13 mois, puis agrégats anonymes. */
  analyticsRawMonths: 13,
  /** Passeports des circuits : 12 mois. */
  passportsMonths: 12,
} as const;

export const WEEKDAYS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'] as const;
export const WEEKDAYS_SHORT = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'] as const;
export const MONTHS_SHORT = ['jan', 'fév', 'mar', 'avr', 'mai', 'juin', 'juil', 'août', 'sep', 'oct', 'nov', 'déc'] as const;

/** Mots réservés : ne peuvent pas servir d'identifiant de territoire dans les URL. */
export const RESERVED_SLUGS = new Set([
  'api',
  'pro',
  'collectivite',
  'console',
  'connexion',
  'inscription',
  'deconnexion',
  'marque',
  'media',
  'q',
  'fonts',
  'demo',
  'tarifs',
  'territoires',
  'mentions-legales',
  'confidentialite',
  'accessibilite',
  'cgu',
  'cgv',
  'compte',
  'invitation',
  'mot-de-passe',
  'mot-de-passe-oublie',
  'contact',
  'aide',
  'sitemap.xml',
  'robots.txt',
  'favicon.ico',
  'manifest.webmanifest',
  '_next',
]);

/** Sous-rubriques du portail : ne peuvent pas servir de slug de commune. */
export const PORTAL_SECTIONS = new Set([
  'explorer',
  'agenda',
  'circuits',
  'emploi',
  'campagnes',
  'communes',
  'actualites',
  'newsletter',
  'mentions-legales',
  'donnees-personnelles',
  'accessibilite',
  'passeport',
  'recherche',
]);
