import { pgEnum } from 'drizzle-orm/pg-core';

// ─── Plateforme & territoires ───────────────────────────────────────────────
export const territoryKind = pgEnum('territory_kind', [
  'CC', // communauté de communes
  'CA', // communauté d'agglomération
  'CU', // communauté urbaine
  'METROPOLE',
  'COMMUNE', // commune indépendante
  'PETR', // pôle d'équilibre territorial / pays
  'OFFICE', // office économique, office de tourisme…
  'AUTRE',
]);

export const territoryStatus = pgEnum('territory_status', ['ONBOARDING', 'ACTIVE', 'SUSPENDED', 'CHURNED']);

export const moduleKey = pgEnum('module_key', ['PORTAL', 'MAP', 'NEWSLETTER', 'IMPORT', 'CAMPAIGNS', 'AI', 'CIRCUITS', 'JOBS', 'MULTILINGUAL', 'APPOINTMENTS']);

export const activityFamily = pgEnum('activity_family', ['COMMERCE', 'ARTISAN', 'PRODUCTEUR', 'RESTAURATION', 'SERVICES']);

export const attributeGroup = pgEnum('attribute_group', ['SERVICE', 'PAYMENT', 'ACCESSIBILITY', 'LABEL', 'HIGHLIGHT']);

// ─── Entreprises & établissements ───────────────────────────────────────────
export const establishmentStatus = pgEnum('establishment_status', [
  'PRECREATED', // précréée (import, données publiques)
  'TO_COMPLETE', // à compléter
  'CLAIMED', // revendiquée
  'VALIDATED', // validée par la collectivité
  'SUSPENDED', // suspendue (masquée du public)
  'ARCHIVED', // archivée (activité cessée)
]);

export const recordOrigin = pgEnum('record_origin', ['IMPORT', 'COLLECTIVITE', 'PRO', 'SYSTEM']);

export const planKey = pgEnum('plan_key', ['ESSENTIEL', 'PREMIUM', 'COMMUNICATION']);

export const companyMemberRole = pgEnum('company_member_role', ['OWNER', 'EDITOR']);

// ─── Utilisateurs & rôles ───────────────────────────────────────────────────
export const staffRole = pgEnum('staff_role', [
  'PLATFORM_ADMIN', // super administrateur (exploitant)
  'PLATFORM_SUPPORT', // assistance
  'PLATFORM_SALES', // commercial
  'TERRITORY_ADMIN', // administrateur territorial (EPCI…)
  'TERRITORY_EDITOR', // chargé·e de communication territorial
  'COMMUNE_ADMIN', // administrateur communal
  'COMMUNE_EDITOR', // agent communal
]);

export const userStatus = pgEnum('user_status', ['ACTIVE', 'DISABLED', 'DELETED']);

export const tokenKind = pgEnum('token_kind', ['EMAIL_VERIFY', 'PASSWORD_RESET', 'INVITE_STAFF', 'INVITE_MEMBER', 'INVITE_CLAIM', 'LOGIN_MFA']);

// ─── Contenus ───────────────────────────────────────────────────────────────
export const postKind = pgEnum('post_kind', ['NEWS', 'PROMO', 'NOUVEAUTE', 'EVENT', 'HOURS', 'JOB']);

export const postStatus = pgEnum('post_status', [
  'DRAFT',
  'SCHEDULED',
  'PENDING', // en attente de modération
  'PUBLISHED',
  'REJECTED',
  'ARCHIVED',
]);

export const authorType = pgEnum('author_type', ['ESTABLISHMENT', 'COMMUNE', 'TERRITORY']);

export const eventKind = pgEnum('event_kind', ['MARCHE', 'DEGUSTATION', 'PORTES_OUVERTES', 'ATELIER', 'CONCERT', 'ANIMATION', 'SALON', 'AUTRE']);

export const publishStatus = pgEnum('publish_status', ['DRAFT', 'PUBLISHED', 'ARCHIVED']);

export const contractType = pgEnum('contract_type', ['CDI', 'CDD', 'ALTERNANCE', 'SAISONNIER', 'STAGE', 'INTERIM', 'INDEPENDANT']);

export const jobStatus = pgEnum('job_status', ['DRAFT', 'PUBLISHED', 'FILLED', 'EXPIRED']);

export const inboxStatus = pgEnum('inbox_status', ['NEW', 'READ', 'REPLIED', 'ARCHIVED', 'SPAM']);

export const appointmentStatus = pgEnum('appointment_status', ['REQUESTED', 'CONFIRMED', 'DECLINED', 'CANCELLED']);

// ─── Revendication ──────────────────────────────────────────────────────────
export const claimStatus = pgEnum('claim_status', ['PENDING', 'NEEDS_INFO', 'APPROVED', 'REJECTED', 'CANCELLED']);

export const riskLevel = pgEnum('risk_level', ['LOW', 'MEDIUM', 'HIGH']);

// ─── Animation territoriale ─────────────────────────────────────────────────
export const campaignStatus = pgEnum('campaign_status', ['DRAFT', 'SCHEDULED', 'ACTIVE', 'ENDED']);
export const campaignMode = pgEnum('campaign_mode', ['STANDARD', 'ADVENT']);
export const participantStatus = pgEnum('participant_status', ['INVITED', 'JOINED', 'DECLINED']);

// ─── Newsletter ─────────────────────────────────────────────────────────────
export const subscriberStatus = pgEnum('subscriber_status', ['PENDING', 'CONFIRMED', 'UNSUBSCRIBED', 'BOUNCED']);
export const audienceKind = pgEnum('audience_kind', ['MANUAL', 'COMMUNE', 'BUSINESSES', 'CIRCUIT']);
export const newsletterStatus = pgEnum('newsletter_status', ['DRAFT', 'SCHEDULED', 'SENDING', 'SENT', 'CANCELLED']);
export const deliveryStatus = pgEnum('delivery_status', ['QUEUED', 'SENT', 'FAILED', 'BOUNCED']);

// ─── Statistiques ───────────────────────────────────────────────────────────
export const analyticsType = pgEnum('analytics_type', [
  'PAGE_VIEW',
  'EST_VIEW',
  'PHONE_CLICK',
  'DIRECTIONS_CLICK',
  'WEBSITE_CLICK',
  'SHARE_CLICK',
  'CONTACT_SENT',
  'QR_SCAN',
  'SEARCH',
  'POST_VIEW',
  'EVENT_VIEW',
  'JOB_VIEW',
  'JOB_APPLY',
  'CAMPAIGN_VIEW',
  'CIRCUIT_VIEW',
  'STAMP',
  'NEWSLETTER_OPEN',
  'NEWSLETTER_CLICK',
  'APPOINTMENT_REQUEST',
]);

export const trafficSource = pgEnum('traffic_source', ['PLATFORM_SEARCH', 'GOOGLE', 'MAP', 'NEWSLETTER', 'QR', 'SOCIAL', 'CAMPAIGN', 'DIRECT', 'OTHER']);

// ─── Facturation ────────────────────────────────────────────────────────────
export const subscriptionStatus = pgEnum('subscription_status', ['ACTIVE', 'PAST_DUE', 'CANCELED']);
export const contractKind = pgEnum('contract_kind', ['LICENCE', 'SETUP', 'SERVICE']);
export const contractStatus = pgEnum('contract_status', ['DRAFT', 'ACTIVE', 'EXPIRED', 'TERMINATED']);
export const invoiceStatus = pgEnum('invoice_status', ['DRAFT', 'ISSUED', 'PAID', 'OVERDUE', 'CANCELLED']);
export const customerType = pgEnum('customer_type', ['TERRITORY', 'COMPANY']);

// ─── CRM ────────────────────────────────────────────────────────────────────
export const dealStage = pgEnum('deal_stage', ['PROSPECT', 'FIRST_CONTACT', 'DEMO', 'PROPOSAL', 'NEGOTIATION', 'SIGNED', 'ONBOARDING', 'ACTIVE', 'LOST']);

// ─── Plateforme ─────────────────────────────────────────────────────────────
export const auditCategory = pgEnum('audit_category', [
  'VALIDATION',
  'SUPPORT',
  'MODIFICATION',
  'IMPORT',
  'ENVOI',
  'SECURITE',
  'MODERATION',
  'CONFIGURATION',
  'RGPD',
  'AUTH',
  'FACTURATION',
]);

export const queueStatus = pgEnum('queue_status', ['QUEUED', 'RUNNING', 'DONE', 'FAILED']);
export const emailStatus = pgEnum('email_status', ['QUEUED', 'SENT', 'FAILED', 'OUTBOX']);
export const privacyRequestKind = pgEnum('privacy_request_kind', ['EXPORT', 'DELETE', 'RECTIFY']);
export const privacyRequestStatus = pgEnum('privacy_request_status', ['OPEN', 'DONE', 'REJECTED']);
export const ticketStatus = pgEnum('ticket_status', ['OPEN', 'PENDING', 'RESOLVED']);
export const aiFeature = pgEnum('ai_feature', ['WRITER', 'IMPROVE', 'AUDIT', 'TERRITORIAL', 'SEARCH', 'TRANSLATE']);
