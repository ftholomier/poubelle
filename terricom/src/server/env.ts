import { z } from 'zod';

/**
 * Configuration de l'application, validée au démarrage.
 * Toute variable manquante ou invalide empêche le serveur de démarrer
 * avec un message explicite plutôt que d'échouer plus tard.
 */
const bool = z
  .string()
  .optional()
  .transform((v) => v === 'true' || v === '1');

const schema = z.object({
  NODE_ENV: z.enum(['development', 'test', 'production']).default('development'),
  APP_URL: z.string().url().default('http://localhost:3000'),
  PLATFORM_DOMAIN: z.string().default('terricom.fr'),
  PLATFORM_HOSTS: z.string().default('localhost:3000,127.0.0.1:3000'),
  DEMO_MODE: bool,

  DATABASE_URL: z.string().min(1).default('postgres://terricom:terricom@localhost:5432/terricom'),
  DATABASE_POOL_MAX: z.coerce.number().int().positive().default(10),

  SESSION_SECRET: z.string().min(16),
  DATA_ENCRYPTION_KEY: z.string().min(32),
  ANALYTICS_SALT: z.string().min(8),

  STORAGE_DRIVER: z.enum(['local', 's3']).default('local'),
  STORAGE_LOCAL_DIR: z.string().default('./storage'),
  S3_ENDPOINT: z.string().optional(),
  S3_REGION: z.string().default('fr-par'),
  S3_BUCKET: z.string().default('terricom-medias'),
  S3_ACCESS_KEY_ID: z.string().optional(),
  S3_SECRET_ACCESS_KEY: z.string().optional(),
  S3_PUBLIC_URL: z.string().optional(),

  MAIL_DRIVER: z.enum(['smtp', 'outbox']).default('outbox'),
  SMTP_URL: z.string().optional(),
  MAIL_FROM: z.string().default('terricom <bonjour@terricom.fr>'),
  /** Boîte de l'équipe support (notification des nouveaux tickets). */
  SUPPORT_EMAIL: z.string().email().default('support@terricom.fr'),
  /** Boîte de l'équipe commerciale (demandes de démonstration). */
  SALES_EMAIL: z.string().email().default('bonjour@terricom.fr'),

  ANTHROPIC_API_KEY: z.string().optional(),
  AI_MODEL: z.string().default('claude-opus-5'),
  /** Tarifs d'estimation des coûts IA (€ par million de jetons), affichés dans la console. */
  AI_COST_INPUT_PER_MTOK: z.coerce.number().min(0).default(5),
  AI_COST_OUTPUT_PER_MTOK: z.coerce.number().min(0).default(25),

  MAP_TILE_URL: z.string().default('https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
  MAP_TILE_ATTRIBUTION: z.string().default('© OpenStreetMap contributors'),

  SIRENE_API_URL: z.string().default('https://recherche-entreprises.api.gouv.fr'),
  GEO_API_URL: z.string().default('https://geo.api.gouv.fr'),
  BAN_API_URL: z.string().default('https://api-adresse.data.gouv.fr'),

  /** Identité légale de l'exploitant (mentions des factures). */
  COMPANY_LEGAL_NAME: z.string().default('terricom'),
  COMPANY_ADDRESS: z.string().default('Adresse du siège à configurer (COMPANY_ADDRESS)'),
  COMPANY_SIREN: z.string().optional(),
  COMPANY_VAT_NUMBER: z.string().optional(),
  COMPANY_IBAN: z.string().optional(),
  /** Mentions légales du site : directeur de la publication, hébergeur, contact DPO. */
  PUBLICATION_DIRECTOR: z.string().optional(),
  HOSTING_PROVIDER: z.string().default('Hébergeur à préciser (HOSTING_PROVIDER), infrastructure située en France'),
  DPO_EMAIL: z.string().email().default('dpo@terricom.fr'),

  /** Passerelle SMS générique (POST JSON { to, text }) : codes de vérification des revendications. */
  SMS_WEBHOOK_URL: z.string().url().optional(),
  SMS_WEBHOOK_TOKEN: z.string().optional(),

  /** Jeton d'accès aux métriques Prometheus (/api/metrics). */
  METRICS_TOKEN: z.string().min(16).optional(),

  STRIPE_SECRET_KEY: z.string().optional(),
  STRIPE_WEBHOOK_SECRET: z.string().optional(),
});

export type Env = z.infer<typeof schema>;

function load(): Env {
  const parsed = schema.safeParse(Object.fromEntries(Object.entries(process.env).map(([k, v]) => [k, v === '' ? undefined : v])));
  if (!parsed.success) {
    const details = parsed.error.issues.map((i) => `  • ${i.path.join('.')}: ${i.message}`).join('\n');
    throw new Error(`Configuration invalide :\n${details}`);
  }
  return parsed.data;
}

export const env: Env = load();

export const isProd = env.NODE_ENV === 'production';

/** Hôtes (host:port) qui servent la plateforme elle-même et non un portail de territoire. */
export const platformHosts = new Set(
  [env.PLATFORM_DOMAIN, `www.${env.PLATFORM_DOMAIN}`, ...env.PLATFORM_HOSTS.split(',')].map((h) => h.trim().toLowerCase()).filter(Boolean),
);
