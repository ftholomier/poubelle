CREATE TYPE "public"."activity_family" AS ENUM('COMMERCE', 'ARTISAN', 'PRODUCTEUR', 'RESTAURATION', 'SERVICES');--> statement-breakpoint
CREATE TYPE "public"."ai_feature" AS ENUM('WRITER', 'IMPROVE', 'AUDIT', 'TERRITORIAL', 'SEARCH', 'TRANSLATE');--> statement-breakpoint
CREATE TYPE "public"."analytics_type" AS ENUM('PAGE_VIEW', 'EST_VIEW', 'PHONE_CLICK', 'DIRECTIONS_CLICK', 'WEBSITE_CLICK', 'SHARE_CLICK', 'CONTACT_SENT', 'QR_SCAN', 'SEARCH', 'POST_VIEW', 'EVENT_VIEW', 'JOB_VIEW', 'JOB_APPLY', 'CAMPAIGN_VIEW', 'CIRCUIT_VIEW', 'STAMP', 'NEWSLETTER_OPEN', 'NEWSLETTER_CLICK', 'APPOINTMENT_REQUEST');--> statement-breakpoint
CREATE TYPE "public"."appointment_status" AS ENUM('REQUESTED', 'CONFIRMED', 'DECLINED', 'CANCELLED');--> statement-breakpoint
CREATE TYPE "public"."attribute_group" AS ENUM('SERVICE', 'PAYMENT', 'ACCESSIBILITY', 'LABEL', 'HIGHLIGHT');--> statement-breakpoint
CREATE TYPE "public"."audience_kind" AS ENUM('MANUAL', 'COMMUNE', 'BUSINESSES', 'CIRCUIT');--> statement-breakpoint
CREATE TYPE "public"."audit_category" AS ENUM('VALIDATION', 'SUPPORT', 'MODIFICATION', 'IMPORT', 'ENVOI', 'SECURITE', 'MODERATION', 'CONFIGURATION', 'RGPD', 'AUTH', 'FACTURATION');--> statement-breakpoint
CREATE TYPE "public"."author_type" AS ENUM('ESTABLISHMENT', 'COMMUNE', 'TERRITORY');--> statement-breakpoint
CREATE TYPE "public"."campaign_mode" AS ENUM('STANDARD', 'ADVENT');--> statement-breakpoint
CREATE TYPE "public"."campaign_status" AS ENUM('DRAFT', 'SCHEDULED', 'ACTIVE', 'ENDED');--> statement-breakpoint
CREATE TYPE "public"."claim_status" AS ENUM('PENDING', 'NEEDS_INFO', 'APPROVED', 'REJECTED', 'CANCELLED');--> statement-breakpoint
CREATE TYPE "public"."company_member_role" AS ENUM('OWNER', 'EDITOR');--> statement-breakpoint
CREATE TYPE "public"."contract_kind" AS ENUM('LICENCE', 'SETUP', 'SERVICE');--> statement-breakpoint
CREATE TYPE "public"."contract_status" AS ENUM('DRAFT', 'ACTIVE', 'EXPIRED', 'TERMINATED');--> statement-breakpoint
CREATE TYPE "public"."contract_type" AS ENUM('CDI', 'CDD', 'ALTERNANCE', 'SAISONNIER', 'STAGE', 'INTERIM', 'INDEPENDANT');--> statement-breakpoint
CREATE TYPE "public"."customer_type" AS ENUM('TERRITORY', 'COMPANY');--> statement-breakpoint
CREATE TYPE "public"."deal_stage" AS ENUM('PROSPECT', 'FIRST_CONTACT', 'DEMO', 'PROPOSAL', 'NEGOTIATION', 'SIGNED', 'ONBOARDING', 'ACTIVE', 'LOST');--> statement-breakpoint
CREATE TYPE "public"."delivery_status" AS ENUM('QUEUED', 'SENT', 'FAILED', 'BOUNCED');--> statement-breakpoint
CREATE TYPE "public"."email_status" AS ENUM('QUEUED', 'SENT', 'FAILED', 'OUTBOX');--> statement-breakpoint
CREATE TYPE "public"."establishment_status" AS ENUM('PRECREATED', 'TO_COMPLETE', 'CLAIMED', 'VALIDATED', 'SUSPENDED', 'ARCHIVED');--> statement-breakpoint
CREATE TYPE "public"."event_kind" AS ENUM('MARCHE', 'DEGUSTATION', 'PORTES_OUVERTES', 'ATELIER', 'CONCERT', 'ANIMATION', 'SALON', 'AUTRE');--> statement-breakpoint
CREATE TYPE "public"."inbox_status" AS ENUM('NEW', 'READ', 'REPLIED', 'ARCHIVED', 'SPAM');--> statement-breakpoint
CREATE TYPE "public"."invoice_status" AS ENUM('DRAFT', 'ISSUED', 'PAID', 'OVERDUE', 'CANCELLED');--> statement-breakpoint
CREATE TYPE "public"."job_status" AS ENUM('DRAFT', 'PUBLISHED', 'FILLED', 'EXPIRED');--> statement-breakpoint
CREATE TYPE "public"."module_key" AS ENUM('PORTAL', 'MAP', 'NEWSLETTER', 'IMPORT', 'CAMPAIGNS', 'AI', 'CIRCUITS', 'JOBS', 'MULTILINGUAL', 'APPOINTMENTS');--> statement-breakpoint
CREATE TYPE "public"."newsletter_status" AS ENUM('DRAFT', 'SCHEDULED', 'SENDING', 'SENT', 'CANCELLED');--> statement-breakpoint
CREATE TYPE "public"."participant_status" AS ENUM('INVITED', 'JOINED', 'DECLINED');--> statement-breakpoint
CREATE TYPE "public"."plan_key" AS ENUM('ESSENTIEL', 'PREMIUM', 'COMMUNICATION');--> statement-breakpoint
CREATE TYPE "public"."post_kind" AS ENUM('NEWS', 'PROMO', 'NOUVEAUTE', 'EVENT', 'HOURS', 'JOB');--> statement-breakpoint
CREATE TYPE "public"."post_status" AS ENUM('DRAFT', 'SCHEDULED', 'PENDING', 'PUBLISHED', 'REJECTED', 'ARCHIVED');--> statement-breakpoint
CREATE TYPE "public"."privacy_request_kind" AS ENUM('EXPORT', 'DELETE', 'RECTIFY');--> statement-breakpoint
CREATE TYPE "public"."privacy_request_status" AS ENUM('OPEN', 'DONE', 'REJECTED');--> statement-breakpoint
CREATE TYPE "public"."publish_status" AS ENUM('DRAFT', 'PUBLISHED', 'ARCHIVED');--> statement-breakpoint
CREATE TYPE "public"."queue_status" AS ENUM('QUEUED', 'RUNNING', 'DONE', 'FAILED');--> statement-breakpoint
CREATE TYPE "public"."record_origin" AS ENUM('IMPORT', 'COLLECTIVITE', 'PRO', 'SYSTEM');--> statement-breakpoint
CREATE TYPE "public"."risk_level" AS ENUM('LOW', 'MEDIUM', 'HIGH');--> statement-breakpoint
CREATE TYPE "public"."staff_role" AS ENUM('PLATFORM_ADMIN', 'PLATFORM_SUPPORT', 'PLATFORM_SALES', 'TERRITORY_ADMIN', 'TERRITORY_EDITOR', 'COMMUNE_ADMIN', 'COMMUNE_EDITOR');--> statement-breakpoint
CREATE TYPE "public"."subscriber_status" AS ENUM('PENDING', 'CONFIRMED', 'UNSUBSCRIBED', 'BOUNCED');--> statement-breakpoint
CREATE TYPE "public"."subscription_status" AS ENUM('ACTIVE', 'PAST_DUE', 'CANCELED');--> statement-breakpoint
CREATE TYPE "public"."territory_kind" AS ENUM('CC', 'CA', 'CU', 'METROPOLE', 'COMMUNE', 'PETR', 'OFFICE', 'AUTRE');--> statement-breakpoint
CREATE TYPE "public"."territory_status" AS ENUM('ONBOARDING', 'ACTIVE', 'SUSPENDED', 'CHURNED');--> statement-breakpoint
CREATE TYPE "public"."ticket_status" AS ENUM('OPEN', 'PENDING', 'RESOLVED');--> statement-breakpoint
CREATE TYPE "public"."token_kind" AS ENUM('EMAIL_VERIFY', 'PASSWORD_RESET', 'INVITE_STAFF', 'INVITE_MEMBER', 'INVITE_CLAIM', 'LOGIN_MFA');--> statement-breakpoint
CREATE TYPE "public"."traffic_source" AS ENUM('PLATFORM_SEARCH', 'GOOGLE', 'MAP', 'NEWSLETTER', 'QR', 'SOCIAL', 'CAMPAIGN', 'DIRECT', 'OTHER');--> statement-breakpoint
CREATE TYPE "public"."user_status" AS ENUM('ACTIVE', 'DISABLED', 'DELETED');--> statement-breakpoint
CREATE TABLE "attributes" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid,
	"group" "attribute_group" NOT NULL,
	"slug" varchar(120) NOT NULL,
	"label" varchar(160) NOT NULL,
	"is_filter" boolean DEFAULT false NOT NULL,
	"sort_order" integer DEFAULT 0 NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "attributes_territory_slug_uq" UNIQUE NULLS NOT DISTINCT("territory_id","slug")
);
--> statement-breakpoint
CREATE TABLE "categories" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid,
	"family" "activity_family" NOT NULL,
	"slug" varchar(120) NOT NULL,
	"name" varchar(160) NOT NULL,
	"naf_codes" text[] DEFAULT '{}'::text[] NOT NULL,
	"synonyms" text[] DEFAULT '{}'::text[] NOT NULL,
	"sort_order" integer DEFAULT 0 NOT NULL,
	"is_active" boolean DEFAULT true NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "categories_territory_slug_uq" UNIQUE NULLS NOT DISTINCT("territory_id","slug")
);
--> statement-breakpoint
CREATE TABLE "commune_memberships" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"commune_id" uuid NOT NULL,
	"territory_id" uuid NOT NULL,
	"valid_from" date DEFAULT current_date NOT NULL,
	"valid_to" date,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "communes" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"insee_code" varchar(5) NOT NULL,
	"name" varchar(160) NOT NULL,
	"slug" varchar(160) NOT NULL,
	"postal_codes" text[] DEFAULT '{}'::text[] NOT NULL,
	"department_code" varchar(3),
	"population" integer,
	"lat" double precision,
	"lng" double precision,
	"tagline" text,
	"description" text,
	"hero_image_url" text,
	"mayor_quote" text,
	"mayor_name" varchar(160),
	"mayor_role" varchar(160),
	"mayor_photo_url" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "communes_inseeCode_unique" UNIQUE("insee_code"),
	CONSTRAINT "communes_slug_unique" UNIQUE("slug")
);
--> statement-breakpoint
CREATE TABLE "territories" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"slug" varchar(64) NOT NULL,
	"name" varchar(160) NOT NULL,
	"legal_name" varchar(255) NOT NULL,
	"kind" "territory_kind" DEFAULT 'CC' NOT NULL,
	"status" "territory_status" DEFAULT 'ONBOARDING' NOT NULL,
	"is_pilot" boolean DEFAULT false NOT NULL,
	"siren" varchar(9),
	"population" integer,
	"department_code" varchar(3),
	"initials" varchar(4) DEFAULT 'T' NOT NULL,
	"tagline" varchar(160) DEFAULT 'Commerces & savoir-faire' NOT NULL,
	"color_primary" varchar(9) DEFAULT '#1F6B52' NOT NULL,
	"color_accent" varchar(9) DEFAULT '#F4B266' NOT NULL,
	"logo_url" text,
	"hero_title" text,
	"hero_subtitle" text,
	"hero_image_url" text,
	"home_blocks" jsonb DEFAULT '["search","openNow","campaign","map","feed","circuits","jobs","newsletter"]'::jsonb NOT NULL,
	"primary_host" varchar(255),
	"center_lat" double precision,
	"center_lng" double precision,
	"default_zoom" integer DEFAULT 11 NOT NULL,
	"contact_email" varchar(255),
	"website_url" text,
	"settings" jsonb DEFAULT '{}'::jsonb NOT NULL,
	"quota_establishments" integer DEFAULT 1500 NOT NULL,
	"quota_emails_monthly" integer DEFAULT 40000 NOT NULL,
	"quota_ai_credits_monthly" integer DEFAULT 5000 NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "territories_slug_unique" UNIQUE("slug")
);
--> statement-breakpoint
CREATE TABLE "territory_domains" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"host" varchar(255) NOT NULL,
	"is_primary" boolean DEFAULT false NOT NULL,
	"verified_at" date,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "territory_domains_host_unique" UNIQUE("host")
);
--> statement-breakpoint
CREATE TABLE "territory_modules" (
	"territory_id" uuid NOT NULL,
	"module" "module_key" NOT NULL,
	"enabled" boolean DEFAULT true NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "territory_modules_territory_id_module_pk" PRIMARY KEY("territory_id","module")
);
--> statement-breakpoint
CREATE TABLE "rate_limits" (
	"key" varchar(200) PRIMARY KEY NOT NULL,
	"count" integer DEFAULT 0 NOT NULL,
	"reset_at" timestamp with time zone NOT NULL
);
--> statement-breakpoint
CREATE TABLE "role_assignments" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"user_id" uuid NOT NULL,
	"role" "staff_role" NOT NULL,
	"territory_id" uuid,
	"commune_id" uuid,
	"created_by_id" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "role_assignments_uq" UNIQUE NULLS NOT DISTINCT("user_id","role","territory_id","commune_id")
);
--> statement-breakpoint
CREATE TABLE "sessions" (
	"id" varchar(64) PRIMARY KEY NOT NULL,
	"user_id" uuid NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"expires_at" timestamp with time zone NOT NULL,
	"last_seen_at" timestamp with time zone DEFAULT now() NOT NULL,
	"ip" varchar(64),
	"user_agent" text,
	"mfa_verified" boolean DEFAULT false NOT NULL,
	"impersonation_territory_id" uuid,
	"impersonation_expires_at" timestamp with time zone,
	"impersonation_ticket" varchar(64)
);
--> statement-breakpoint
CREATE TABLE "tokens" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"kind" "token_kind" NOT NULL,
	"token_hash" varchar(64) NOT NULL,
	"user_id" uuid,
	"email" "citext",
	"payload" jsonb DEFAULT '{}'::jsonb NOT NULL,
	"expires_at" timestamp with time zone NOT NULL,
	"used_at" timestamp with time zone,
	"created_by_id" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "tokens_tokenHash_unique" UNIQUE("token_hash")
);
--> statement-breakpoint
CREATE TABLE "users" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"email" "citext" NOT NULL,
	"password_hash" text,
	"first_name" varchar(120) DEFAULT '' NOT NULL,
	"last_name" varchar(120) DEFAULT '' NOT NULL,
	"phone" varchar(32),
	"avatar_url" text,
	"job_title" varchar(160),
	"status" "user_status" DEFAULT 'ACTIVE' NOT NULL,
	"email_verified_at" timestamp with time zone,
	"mfa_enabled" boolean DEFAULT false NOT NULL,
	"mfa_secret_enc" text,
	"mfa_recovery_codes" text[] DEFAULT '{}'::text[] NOT NULL,
	"failed_login_count" integer DEFAULT 0 NOT NULL,
	"locked_until" timestamp with time zone,
	"last_login_at" timestamp with time zone,
	"password_changed_at" timestamp with time zone,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	"deleted_at" timestamp with time zone,
	CONSTRAINT "users_email_unique" UNIQUE("email")
);
--> statement-breakpoint
CREATE TABLE "companies" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"siren" varchar(9),
	"legal_name" varchar(255) NOT NULL,
	"trade_name" varchar(255),
	"legal_form" varchar(120),
	"naf_code" varchar(8),
	"plan" "plan_key" DEFAULT 'ESSENTIEL' NOT NULL,
	"billing_email" varchar(255),
	"billing_name" varchar(255),
	"billing_address" text,
	"vat_number" varchar(32),
	"social_webhook_url" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "companies_siren_unique" UNIQUE("siren")
);
--> statement-breakpoint
CREATE TABLE "company_members" (
	"company_id" uuid NOT NULL,
	"user_id" uuid NOT NULL,
	"role" "company_member_role" DEFAULT 'EDITOR' NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "company_members_company_id_user_id_pk" PRIMARY KEY("company_id","user_id")
);
--> statement-breakpoint
CREATE TABLE "establishment_attributes" (
	"establishment_id" uuid NOT NULL,
	"attribute_id" uuid NOT NULL,
	CONSTRAINT "establishment_attributes_establishment_id_attribute_id_pk" PRIMARY KEY("establishment_id","attribute_id")
);
--> statement-breakpoint
CREATE TABLE "establishment_categories" (
	"establishment_id" uuid NOT NULL,
	"category_id" uuid NOT NULL,
	CONSTRAINT "establishment_categories_establishment_id_category_id_pk" PRIMARY KEY("establishment_id","category_id")
);
--> statement-breakpoint
CREATE TABLE "establishment_pages" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"establishment_id" uuid NOT NULL,
	"title" varchar(160) NOT NULL,
	"slug" varchar(160) NOT NULL,
	"body" text DEFAULT '' NOT NULL,
	"sort_order" integer DEFAULT 0 NOT NULL,
	"published" boolean DEFAULT true NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "establishment_pages_slug_uq" UNIQUE("establishment_id","slug")
);
--> statement-breakpoint
CREATE TABLE "establishments" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"company_id" uuid NOT NULL,
	"commune_id" uuid NOT NULL,
	"territory_id" uuid NOT NULL,
	"category_id" uuid NOT NULL,
	"slug" varchar(160) NOT NULL,
	"name" varchar(255) NOT NULL,
	"siret" varchar(14),
	"status" "establishment_status" DEFAULT 'PRECREATED' NOT NULL,
	"origin" "record_origin" DEFAULT 'IMPORT' NOT NULL,
	"activity_label" varchar(160),
	"tagline" varchar(255),
	"description" text,
	"street" varchar(255),
	"postal_code" varchar(10),
	"lat" double precision,
	"lng" double precision,
	"phone" varchar(32),
	"email" varchar(255),
	"website" text,
	"socials" jsonb DEFAULT '{}'::jsonb NOT NULL,
	"logo_url" text,
	"cover_url" text,
	"price_info" text,
	"service_area" text,
	"service_area_km" integer,
	"accessibility_info" text,
	"appointments_enabled" boolean DEFAULT false NOT NULL,
	"appointment_info" text,
	"theme_color" varchar(9),
	"translations" jsonb DEFAULT '{}'::jsonb NOT NULL,
	"is_featured" boolean DEFAULT false NOT NULL,
	"completeness" integer DEFAULT 0 NOT NULL,
	"hours_confirmed_at" timestamp with time zone,
	"last_activity_at" timestamp with time zone,
	"published_at" timestamp with time zone,
	"suspended_reason" text,
	"archived_at" timestamp with time zone,
	"qr_code" varchar(16) NOT NULL,
	"search_keywords" text DEFAULT '' NOT NULL,
	"search_vector" "tsvector" GENERATED ALWAYS AS (setweight(to_tsvector('french', f_unaccent(coalesce(name, ''))), 'A') || setweight(to_tsvector('french', f_unaccent(coalesce(activity_label, '') || ' ' || coalesce(search_keywords, ''))), 'B') || setweight(to_tsvector('french', f_unaccent(coalesce(tagline, '') || ' ' || coalesce(description, ''))), 'C')) STORED,
	"created_by_id" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "establishments_siret_unique" UNIQUE("siret"),
	CONSTRAINT "establishments_qrCode_unique" UNIQUE("qr_code"),
	CONSTRAINT "establishments_commune_slug_uq" UNIQUE("commune_id","slug")
);
--> statement-breakpoint
CREATE TABLE "exceptional_hours" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"establishment_id" uuid NOT NULL,
	"date" date NOT NULL,
	"closed" boolean DEFAULT true NOT NULL,
	"opens_at" time,
	"closes_at" time,
	"label" varchar(160)
);
--> statement-breakpoint
CREATE TABLE "media" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid,
	"establishment_id" uuid,
	"owner_type" varchar(32) NOT NULL,
	"owner_id" uuid,
	"kind" varchar(16) DEFAULT 'IMAGE' NOT NULL,
	"url" text NOT NULL,
	"storage_key" text,
	"mime_type" varchar(100),
	"size_bytes" integer,
	"width" integer,
	"height" integer,
	"variants" jsonb DEFAULT '{}'::jsonb NOT NULL,
	"alt" varchar(255),
	"tag" varchar(64),
	"sort_order" integer DEFAULT 0 NOT NULL,
	"is_private" boolean DEFAULT false NOT NULL,
	"uploaded_by_id" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "opening_hours" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"establishment_id" uuid NOT NULL,
	"weekday" smallint NOT NULL,
	"opens_at" time NOT NULL,
	"closes_at" time NOT NULL
);
--> statement-breakpoint
CREATE TABLE "products" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"establishment_id" uuid NOT NULL,
	"kind" varchar(16) DEFAULT 'PRODUCT' NOT NULL,
	"name" varchar(255) NOT NULL,
	"description" text,
	"price_text" varchar(120),
	"image_url" text,
	"sort_order" integer DEFAULT 0 NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "appointments" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"establishment_id" uuid NOT NULL,
	"full_name" varchar(255) NOT NULL,
	"email" "citext" NOT NULL,
	"phone" varchar(32),
	"service" varchar(255),
	"preferred_at" timestamp with time zone NOT NULL,
	"message" text,
	"status" "appointment_status" DEFAULT 'REQUESTED' NOT NULL,
	"response_note" text,
	"responded_at" timestamp with time zone,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "events" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"commune_id" uuid,
	"establishment_id" uuid,
	"author_type" "author_type" DEFAULT 'ESTABLISHMENT' NOT NULL,
	"organizer_name" varchar(255),
	"slug" varchar(160) NOT NULL,
	"title" varchar(255) NOT NULL,
	"kind" "event_kind" DEFAULT 'AUTRE' NOT NULL,
	"summary" varchar(300),
	"description" text DEFAULT '' NOT NULL,
	"starts_at" timestamp with time zone NOT NULL,
	"ends_at" timestamp with time zone,
	"all_day" boolean DEFAULT false NOT NULL,
	"location_name" varchar(255),
	"address" varchar(255),
	"lat" double precision,
	"lng" double precision,
	"price_text" varchar(120),
	"accessibility_text" varchar(255),
	"registration_url" text,
	"capacity" integer,
	"program" jsonb DEFAULT '[]'::jsonb NOT NULL,
	"image_url" text,
	"status" "publish_status" DEFAULT 'PUBLISHED' NOT NULL,
	"is_featured" boolean DEFAULT false NOT NULL,
	"campaign_id" uuid,
	"created_by_id" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "events_territory_slug_uq" UNIQUE("territory_id","slug")
);
--> statement-breakpoint
CREATE TABLE "job_applications" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"job_id" uuid NOT NULL,
	"establishment_id" uuid NOT NULL,
	"full_name" varchar(255) NOT NULL,
	"email" "citext" NOT NULL,
	"phone" varchar(32),
	"message" text,
	"cv_media_id" uuid,
	"status" "inbox_status" DEFAULT 'NEW' NOT NULL,
	"consent_at" timestamp with time zone DEFAULT now() NOT NULL,
	"purge_after" date DEFAULT (current_date + interval '24 months')::date NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "jobs" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"commune_id" uuid,
	"establishment_id" uuid NOT NULL,
	"slug" varchar(160) NOT NULL,
	"title" varchar(255) NOT NULL,
	"contract_type" "contract_type" NOT NULL,
	"status" "job_status" DEFAULT 'PUBLISHED' NOT NULL,
	"start_text" varchar(160),
	"salary_text" varchar(160),
	"work_time_text" varchar(160),
	"description" text DEFAULT '' NOT NULL,
	"missions" text[] DEFAULT '{}'::text[] NOT NULL,
	"profile" text[] DEFAULT '{}'::text[] NOT NULL,
	"apply_email" varchar(255),
	"published_at" timestamp with time zone,
	"expires_at" timestamp with time zone,
	"created_by_id" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "jobs_territory_slug_uq" UNIQUE("territory_id","slug")
);
--> statement-breakpoint
CREATE TABLE "markets" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"commune_id" uuid NOT NULL,
	"name" varchar(255) NOT NULL,
	"place" varchar(255),
	"weekday" smallint NOT NULL,
	"start_time" time NOT NULL,
	"end_time" time NOT NULL,
	"description" text,
	"lat" double precision,
	"lng" double precision,
	"is_active" boolean DEFAULT true NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "messages" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"establishment_id" uuid NOT NULL,
	"territory_id" uuid NOT NULL,
	"source" varchar(16) DEFAULT 'PORTAL' NOT NULL,
	"from_user_id" uuid,
	"sender_name" varchar(255) NOT NULL,
	"sender_email" "citext",
	"sender_phone" varchar(32),
	"subject" varchar(255),
	"body" text NOT NULL,
	"status" "inbox_status" DEFAULT 'NEW' NOT NULL,
	"read_at" timestamp with time zone,
	"replied_at" timestamp with time zone,
	"ip_hash" varchar(64),
	"purge_after" date DEFAULT (current_date + interval '36 months')::date NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "posts" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"commune_id" uuid,
	"establishment_id" uuid,
	"author_type" "author_type" DEFAULT 'ESTABLISHMENT' NOT NULL,
	"kind" "post_kind" DEFAULT 'NEWS' NOT NULL,
	"status" "post_status" DEFAULT 'DRAFT' NOT NULL,
	"title" varchar(255) NOT NULL,
	"body" text DEFAULT '' NOT NULL,
	"image_url" text,
	"promo_label" varchar(32),
	"valid_from" date,
	"valid_to" date,
	"cta_label" varchar(64),
	"cta_url" text,
	"channels" text[] DEFAULT '{FICHE}'::text[] NOT NULL,
	"campaign_id" uuid,
	"variants" jsonb DEFAULT '{}'::jsonb NOT NULL,
	"ai_generated" boolean DEFAULT false NOT NULL,
	"publish_at" timestamp with time zone,
	"published_at" timestamp with time zone,
	"expires_at" timestamp with time zone,
	"view_count" integer DEFAULT 0 NOT NULL,
	"moderation_note" text,
	"moderated_by_id" uuid,
	"moderated_at" timestamp with time zone,
	"social_sent_at" timestamp with time zone,
	"created_by_id" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "claims" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"establishment_id" uuid NOT NULL,
	"territory_id" uuid NOT NULL,
	"user_id" uuid NOT NULL,
	"status" "claim_status" DEFAULT 'PENDING' NOT NULL,
	"claimant_role" varchar(120),
	"method" varchar(16) DEFAULT 'SIRET' NOT NULL,
	"siret_provided" varchar(14),
	"checks" jsonb DEFAULT '[]'::jsonb NOT NULL,
	"sirene_holder" varchar(255),
	"code_hash" varchar(64),
	"code_sent_to" varchar(255),
	"code_sent_at" timestamp with time zone,
	"code_verified_at" timestamp with time zone,
	"kbis_media_id" uuid,
	"risk_level" "risk_level" DEFAULT 'MEDIUM' NOT NULL,
	"reviewer_id" uuid,
	"reviewed_at" timestamp with time zone,
	"decision_note" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "establishment_revisions" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"establishment_id" uuid NOT NULL,
	"user_id" uuid,
	"source" "record_origin" DEFAULT 'PRO' NOT NULL,
	"summary" text NOT NULL,
	"changes" jsonb DEFAULT '[]'::jsonb NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "advent_doors" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"campaign_id" uuid NOT NULL,
	"day" smallint NOT NULL,
	"title" varchar(255) NOT NULL,
	"establishment_id" uuid,
	CONSTRAINT "advent_doors_day_uq" UNIQUE("campaign_id","day")
);
--> statement-breakpoint
CREATE TABLE "campaign_participants" (
	"campaign_id" uuid NOT NULL,
	"establishment_id" uuid NOT NULL,
	"status" "participant_status" DEFAULT 'INVITED' NOT NULL,
	"offer_label" varchar(80),
	"offer_description" text,
	"invited_at" timestamp with time zone,
	"joined_at" timestamp with time zone,
	CONSTRAINT "campaign_participants_campaign_id_establishment_id_pk" PRIMARY KEY("campaign_id","establishment_id")
);
--> statement-breakpoint
CREATE TABLE "campaigns" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"slug" varchar(160) NOT NULL,
	"name" varchar(255) NOT NULL,
	"tagline" varchar(255),
	"description" text DEFAULT '' NOT NULL,
	"starts_at" date NOT NULL,
	"ends_at" date NOT NULL,
	"status" "campaign_status" DEFAULT 'DRAFT' NOT NULL,
	"mode" "campaign_mode" DEFAULT 'STANDARD' NOT NULL,
	"color_bg" varchar(9) DEFAULT '#7A2E26' NOT NULL,
	"color_bg_dark" varchar(9) DEFAULT '#5E1F1A' NOT NULL,
	"color_text" varchar(9) DEFAULT '#FFF3E6' NOT NULL,
	"color_text_soft" varchar(9) DEFAULT '#F3D5C9' NOT NULL,
	"hero_image_url" text,
	"card_image_url" text,
	"cta_label" varchar(64),
	"criteria" jsonb DEFAULT '{}'::jsonb NOT NULL,
	"ai_plan" jsonb DEFAULT '{}'::jsonb NOT NULL,
	"invitation_message" text,
	"highlight_event_id" uuid,
	"created_by_id" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "campaigns_territory_slug_uq" UNIQUE("territory_id","slug")
);
--> statement-breakpoint
CREATE TABLE "circuit_stops" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"circuit_id" uuid NOT NULL,
	"position" integer NOT NULL,
	"establishment_id" uuid,
	"name" varchar(255),
	"lat" double precision,
	"lng" double precision,
	"note" text,
	"stamp_secret" varchar(32) NOT NULL,
	CONSTRAINT "circuit_stops_stampSecret_unique" UNIQUE("stamp_secret")
);
--> statement-breakpoint
CREATE TABLE "circuits" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"slug" varchar(160) NOT NULL,
	"name" varchar(255) NOT NULL,
	"meta" varchar(255),
	"description" text DEFAULT '' NOT NULL,
	"distance_km" numeric(6, 1),
	"duration_text" varchar(64),
	"travel_mode" varchar(120),
	"reward_text" varchar(255),
	"reward_threshold" integer,
	"image_url" text,
	"tag_color" varchar(9) DEFAULT '#F4B266' NOT NULL,
	"status" "publish_status" DEFAULT 'PUBLISHED' NOT NULL,
	"sort_order" integer DEFAULT 0 NOT NULL,
	"created_by_id" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "circuits_territory_slug_uq" UNIQUE("territory_id","slug")
);
--> statement-breakpoint
CREATE TABLE "passport_stamps" (
	"passport_id" uuid NOT NULL,
	"stop_id" uuid NOT NULL,
	"stamped_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "passport_stamps_passport_id_stop_id_pk" PRIMARY KEY("passport_id","stop_id")
);
--> statement-breakpoint
CREATE TABLE "passports" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"circuit_id" uuid NOT NULL,
	"token_hash" varchar(64) NOT NULL,
	"reward_code" varchar(16),
	"completed_at" timestamp with time zone,
	"reward_claimed_at" timestamp with time zone,
	"last_seen_at" timestamp with time zone,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "passports_tokenHash_unique" UNIQUE("token_hash")
);
--> statement-breakpoint
CREATE TABLE "audiences" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"name" varchar(160) NOT NULL,
	"description" varchar(255),
	"kind" "audience_kind" DEFAULT 'MANUAL' NOT NULL,
	"commune_id" uuid,
	"is_default" boolean DEFAULT false NOT NULL,
	"sort_order" integer DEFAULT 0 NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "company_contacts" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"company_id" uuid NOT NULL,
	"email" "citext" NOT NULL,
	"full_name" varchar(255),
	"source" varchar(32) DEFAULT 'MANUAL' NOT NULL,
	"consent_text" text,
	"consent_at" timestamp with time zone,
	"subscribed" boolean DEFAULT true NOT NULL,
	"unsubscribe_token" varchar(48) NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "company_contacts_unsubscribeToken_unique" UNIQUE("unsubscribe_token"),
	CONSTRAINT "company_contacts_uq" UNIQUE("company_id","email")
);
--> statement-breakpoint
CREATE TABLE "newsletter_deliveries" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"newsletter_id" uuid NOT NULL,
	"subscriber_id" uuid,
	"contact_id" uuid,
	"email" "citext" NOT NULL,
	"status" "delivery_status" DEFAULT 'QUEUED' NOT NULL,
	"token" varchar(48) NOT NULL,
	"sent_at" timestamp with time zone,
	"opened_at" timestamp with time zone,
	"clicked_at" timestamp with time zone,
	"error" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "newsletter_deliveries_token_unique" UNIQUE("token"),
	CONSTRAINT "newsletter_deliveries_uq" UNIQUE("newsletter_id","email")
);
--> statement-breakpoint
CREATE TABLE "newsletters" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"commune_id" uuid,
	"company_id" uuid,
	"number" integer,
	"subject" varchar(255) NOT NULL,
	"preheader" varchar(255),
	"title" varchar(255) NOT NULL,
	"intro" text DEFAULT '' NOT NULL,
	"hero_image_url" text,
	"blocks" jsonb DEFAULT '[]'::jsonb NOT NULL,
	"audience_ids" uuid[] DEFAULT '{}'::uuid[] NOT NULL,
	"status" "newsletter_status" DEFAULT 'DRAFT' NOT NULL,
	"scheduled_at" timestamp with time zone,
	"sent_at" timestamp with time zone,
	"stats_recipients" integer DEFAULT 0 NOT NULL,
	"stats_sent" integer DEFAULT 0 NOT NULL,
	"stats_opens" integer DEFAULT 0 NOT NULL,
	"stats_clicks" integer DEFAULT 0 NOT NULL,
	"stats_unsubscribes" integer DEFAULT 0 NOT NULL,
	"stats_bounces" integer DEFAULT 0 NOT NULL,
	"created_by_id" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "subscriber_audiences" (
	"subscriber_id" uuid NOT NULL,
	"audience_id" uuid NOT NULL,
	CONSTRAINT "subscriber_audiences_subscriber_id_audience_id_pk" PRIMARY KEY("subscriber_id","audience_id")
);
--> statement-breakpoint
CREATE TABLE "subscribers" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"email" "citext" NOT NULL,
	"first_name" varchar(120),
	"commune_id" uuid,
	"status" "subscriber_status" DEFAULT 'PENDING' NOT NULL,
	"source" varchar(32) DEFAULT 'PORTAL' NOT NULL,
	"consent_text" text NOT NULL,
	"consent_at" timestamp with time zone DEFAULT now() NOT NULL,
	"consent_ip_hash" varchar(64),
	"confirm_token_hash" varchar(64),
	"confirmed_at" timestamp with time zone,
	"unsubscribed_at" timestamp with time zone,
	"unsubscribe_token" varchar(48) NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "subscribers_unsubscribeToken_unique" UNIQUE("unsubscribe_token"),
	CONSTRAINT "subscribers_territory_email_uq" UNIQUE("territory_id","email")
);
--> statement-breakpoint
CREATE TABLE "analytics_daily" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"day" date NOT NULL,
	"territory_id" uuid,
	"establishment_id" uuid,
	"type" "analytics_type" NOT NULL,
	"source" "traffic_source",
	"count" integer DEFAULT 0 NOT NULL,
	"uniques" integer DEFAULT 0 NOT NULL,
	CONSTRAINT "analytics_daily_uq" UNIQUE NULLS NOT DISTINCT("day","territory_id","establishment_id","type","source")
);
--> statement-breakpoint
CREATE TABLE "analytics_events" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"occurred_at" timestamp with time zone DEFAULT now() NOT NULL,
	"territory_id" uuid,
	"commune_id" uuid,
	"establishment_id" uuid,
	"ref_id" uuid,
	"type" "analytics_type" NOT NULL,
	"source" "traffic_source",
	"query" text,
	"result_count" integer,
	"visitor_hash" varchar(32),
	"path" text,
	"referrer_host" varchar(255),
	"device" varchar(10)
);
--> statement-breakpoint
CREATE TABLE "company_subscriptions" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"company_id" uuid NOT NULL,
	"plan" "plan_key" NOT NULL,
	"status" "subscription_status" DEFAULT 'ACTIVE' NOT NULL,
	"provider" varchar(16) DEFAULT 'MANUAL' NOT NULL,
	"provider_ref" varchar(255),
	"started_at" timestamp with time zone DEFAULT now() NOT NULL,
	"current_period_end" timestamp with time zone,
	"cancel_at_period_end" boolean DEFAULT false NOT NULL,
	"canceled_at" timestamp with time zone,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "invoice_counters" (
	"year" integer PRIMARY KEY NOT NULL,
	"last_number" integer DEFAULT 0 NOT NULL
);
--> statement-breakpoint
CREATE TABLE "invoices" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"number" varchar(32) NOT NULL,
	"customer_type" "customer_type" NOT NULL,
	"territory_id" uuid,
	"company_id" uuid,
	"customer_name" varchar(255) NOT NULL,
	"customer_address" text,
	"issued_at" date NOT NULL,
	"due_at" date NOT NULL,
	"status" "invoice_status" DEFAULT 'ISSUED' NOT NULL,
	"lines" jsonb NOT NULL,
	"total_ht_cents" integer NOT NULL,
	"vat_cents" integer NOT NULL,
	"total_ttc_cents" integer NOT NULL,
	"paid_at" date,
	"payment_method" varchar(32),
	"chorus_ref" varchar(64),
	"notes" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "invoices_number_unique" UNIQUE("number")
);
--> statement-breakpoint
CREATE TABLE "plans" (
	"key" "plan_key" PRIMARY KEY NOT NULL,
	"name" varchar(80) NOT NULL,
	"price_monthly_cents" integer DEFAULT 0 NOT NULL,
	"tagline" varchar(255) DEFAULT '' NOT NULL,
	"features" text[] DEFAULT '{}'::text[] NOT NULL,
	"limits" jsonb NOT NULL,
	"is_active" boolean DEFAULT true NOT NULL,
	"sort_order" integer DEFAULT 0 NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "territory_contracts" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"kind" "contract_kind" DEFAULT 'LICENCE' NOT NULL,
	"label" varchar(255) NOT NULL,
	"amount_cents" integer NOT NULL,
	"starts_at" date NOT NULL,
	"ends_at" date,
	"status" "contract_status" DEFAULT 'ACTIVE' NOT NULL,
	"signed_at" date,
	"notes" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "deal_activities" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"deal_id" uuid NOT NULL,
	"occurred_at" timestamp with time zone DEFAULT now() NOT NULL,
	"kind" varchar(64) NOT NULL,
	"text" text NOT NULL,
	"user_id" uuid
);
--> statement-breakpoint
CREATE TABLE "deal_contacts" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"deal_id" uuid NOT NULL,
	"name" varchar(255) NOT NULL,
	"role" varchar(255),
	"tag" varchar(32),
	"email" varchar(255),
	"phone" varchar(32),
	"sort_order" integer DEFAULT 0 NOT NULL
);
--> statement-breakpoint
CREATE TABLE "deal_documents" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"deal_id" uuid NOT NULL,
	"name" varchar(255) NOT NULL,
	"url" text,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "deal_tasks" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"deal_id" uuid NOT NULL,
	"text" varchar(255) NOT NULL,
	"due_text" varchar(64),
	"done_at" timestamp with time zone,
	"sort_order" integer DEFAULT 0 NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "deals" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"name" varchar(255) NOT NULL,
	"kind" "territory_kind" DEFAULT 'CC' NOT NULL,
	"communes_count" integer DEFAULT 1 NOT NULL,
	"population" integer,
	"stage" "deal_stage" DEFAULT 'PROSPECT' NOT NULL,
	"probability" integer DEFAULT 10 NOT NULL,
	"licence_cents" integer DEFAULT 0 NOT NULL,
	"setup_cents" integer DEFAULT 0 NOT NULL,
	"owner_id" uuid,
	"territory_id" uuid,
	"next_action" varchar(255),
	"notes" text,
	"source" varchar(64),
	"lat" double precision,
	"lng" double precision,
	"contact_email" varchar(255),
	"contact_phone" varchar(32),
	"last_interaction_at" timestamp with time zone,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "ai_usage" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid,
	"company_id" uuid,
	"user_id" uuid,
	"feature" "ai_feature" NOT NULL,
	"model" varchar(64) NOT NULL,
	"input_tokens" integer DEFAULT 0 NOT NULL,
	"output_tokens" integer DEFAULT 0 NOT NULL,
	"credits" integer DEFAULT 1 NOT NULL,
	"fallback" boolean DEFAULT false NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "api_keys" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"name" varchar(160) NOT NULL,
	"prefix" varchar(12) NOT NULL,
	"key_hash" varchar(64) NOT NULL,
	"scopes" text[] DEFAULT '{read}'::text[] NOT NULL,
	"last_used_at" timestamp with time zone,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"revoked_at" timestamp with time zone,
	CONSTRAINT "api_keys_keyHash_unique" UNIQUE("key_hash")
);
--> statement-breakpoint
CREATE TABLE "audit_log" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"occurred_at" timestamp with time zone DEFAULT now() NOT NULL,
	"actor_user_id" uuid,
	"actor_label" varchar(255) NOT NULL,
	"territory_id" uuid,
	"category" "audit_category" NOT NULL,
	"action" varchar(120) NOT NULL,
	"target_type" varchar(64),
	"target_id" varchar(64),
	"summary" text NOT NULL,
	"metadata" jsonb DEFAULT '{}'::jsonb NOT NULL,
	"ip_hash" varchar(64),
	"prev_hash" varchar(64) NOT NULL,
	"hash" varchar(64) NOT NULL
);
--> statement-breakpoint
CREATE TABLE "emails" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"to" varchar(320) NOT NULL,
	"subject" varchar(255) NOT NULL,
	"html" text NOT NULL,
	"text" text NOT NULL,
	"template" varchar(64),
	"status" "email_status" DEFAULT 'QUEUED' NOT NULL,
	"error" text,
	"provider_id" varchar(255),
	"headers" jsonb DEFAULT '{}'::jsonb NOT NULL,
	"territory_id" uuid,
	"attempts" integer DEFAULT 0 NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"sent_at" timestamp with time zone
);
--> statement-breakpoint
CREATE TABLE "job_schedules" (
	"name" varchar(64) PRIMARY KEY NOT NULL,
	"every_minutes" integer NOT NULL,
	"last_run_at" timestamp with time zone,
	"enabled" boolean DEFAULT true NOT NULL
);
--> statement-breakpoint
CREATE TABLE "privacy_requests" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"number" serial NOT NULL,
	"email" "citext" NOT NULL,
	"kind" "privacy_request_kind" NOT NULL,
	"status" "privacy_request_status" DEFAULT 'OPEN' NOT NULL,
	"territory_id" uuid,
	"note" text,
	"handled_by_id" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"completed_at" timestamp with time zone,
	CONSTRAINT "privacy_requests_number_unique" UNIQUE("number")
);
--> statement-breakpoint
CREATE TABLE "queue_jobs" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"queue" varchar(64) NOT NULL,
	"payload" jsonb DEFAULT '{}'::jsonb NOT NULL,
	"run_at" timestamp with time zone DEFAULT now() NOT NULL,
	"status" "queue_status" DEFAULT 'QUEUED' NOT NULL,
	"attempts" integer DEFAULT 0 NOT NULL,
	"max_attempts" integer DEFAULT 5 NOT NULL,
	"locked_at" timestamp with time zone,
	"locked_by" varchar(64),
	"last_error" text,
	"dedupe_key" varchar(200),
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"finished_at" timestamp with time zone,
	CONSTRAINT "queue_jobs_dedupeKey_unique" UNIQUE("dedupe_key")
);
--> statement-breakpoint
CREATE TABLE "support_tickets" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"number" serial NOT NULL,
	"territory_id" uuid,
	"subject" varchar(255) NOT NULL,
	"body" text DEFAULT '' NOT NULL,
	"status" "ticket_status" DEFAULT 'OPEN' NOT NULL,
	"priority" varchar(16) DEFAULT 'NORMAL' NOT NULL,
	"created_by_id" uuid,
	"assignee_id" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "support_tickets_number_unique" UNIQUE("number")
);
--> statement-breakpoint
ALTER TABLE "attributes" ADD CONSTRAINT "attributes_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "categories" ADD CONSTRAINT "categories_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "commune_memberships" ADD CONSTRAINT "commune_memberships_commune_id_communes_id_fk" FOREIGN KEY ("commune_id") REFERENCES "public"."communes"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "commune_memberships" ADD CONSTRAINT "commune_memberships_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "territory_domains" ADD CONSTRAINT "territory_domains_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "territory_modules" ADD CONSTRAINT "territory_modules_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "role_assignments" ADD CONSTRAINT "role_assignments_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "role_assignments" ADD CONSTRAINT "role_assignments_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "role_assignments" ADD CONSTRAINT "role_assignments_commune_id_communes_id_fk" FOREIGN KEY ("commune_id") REFERENCES "public"."communes"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "role_assignments" ADD CONSTRAINT "role_assignments_created_by_id_users_id_fk" FOREIGN KEY ("created_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "sessions" ADD CONSTRAINT "sessions_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "sessions" ADD CONSTRAINT "sessions_impersonation_territory_id_territories_id_fk" FOREIGN KEY ("impersonation_territory_id") REFERENCES "public"."territories"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "tokens" ADD CONSTRAINT "tokens_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "tokens" ADD CONSTRAINT "tokens_created_by_id_users_id_fk" FOREIGN KEY ("created_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "company_members" ADD CONSTRAINT "company_members_company_id_companies_id_fk" FOREIGN KEY ("company_id") REFERENCES "public"."companies"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "company_members" ADD CONSTRAINT "company_members_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "establishment_attributes" ADD CONSTRAINT "establishment_attributes_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "establishment_attributes" ADD CONSTRAINT "establishment_attributes_attribute_id_attributes_id_fk" FOREIGN KEY ("attribute_id") REFERENCES "public"."attributes"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "establishment_categories" ADD CONSTRAINT "establishment_categories_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "establishment_categories" ADD CONSTRAINT "establishment_categories_category_id_categories_id_fk" FOREIGN KEY ("category_id") REFERENCES "public"."categories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "establishment_pages" ADD CONSTRAINT "establishment_pages_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "establishments" ADD CONSTRAINT "establishments_company_id_companies_id_fk" FOREIGN KEY ("company_id") REFERENCES "public"."companies"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "establishments" ADD CONSTRAINT "establishments_commune_id_communes_id_fk" FOREIGN KEY ("commune_id") REFERENCES "public"."communes"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "establishments" ADD CONSTRAINT "establishments_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "establishments" ADD CONSTRAINT "establishments_category_id_categories_id_fk" FOREIGN KEY ("category_id") REFERENCES "public"."categories"("id") ON DELETE restrict ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "establishments" ADD CONSTRAINT "establishments_created_by_id_users_id_fk" FOREIGN KEY ("created_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "exceptional_hours" ADD CONSTRAINT "exceptional_hours_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "media" ADD CONSTRAINT "media_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "media" ADD CONSTRAINT "media_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "media" ADD CONSTRAINT "media_uploaded_by_id_users_id_fk" FOREIGN KEY ("uploaded_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "opening_hours" ADD CONSTRAINT "opening_hours_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "products" ADD CONSTRAINT "products_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "appointments" ADD CONSTRAINT "appointments_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "events" ADD CONSTRAINT "events_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "events" ADD CONSTRAINT "events_commune_id_communes_id_fk" FOREIGN KEY ("commune_id") REFERENCES "public"."communes"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "events" ADD CONSTRAINT "events_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "events" ADD CONSTRAINT "events_created_by_id_users_id_fk" FOREIGN KEY ("created_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "job_applications" ADD CONSTRAINT "job_applications_job_id_jobs_id_fk" FOREIGN KEY ("job_id") REFERENCES "public"."jobs"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "job_applications" ADD CONSTRAINT "job_applications_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "job_applications" ADD CONSTRAINT "job_applications_cv_media_id_media_id_fk" FOREIGN KEY ("cv_media_id") REFERENCES "public"."media"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "jobs" ADD CONSTRAINT "jobs_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "jobs" ADD CONSTRAINT "jobs_commune_id_communes_id_fk" FOREIGN KEY ("commune_id") REFERENCES "public"."communes"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "jobs" ADD CONSTRAINT "jobs_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "jobs" ADD CONSTRAINT "jobs_created_by_id_users_id_fk" FOREIGN KEY ("created_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "markets" ADD CONSTRAINT "markets_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "markets" ADD CONSTRAINT "markets_commune_id_communes_id_fk" FOREIGN KEY ("commune_id") REFERENCES "public"."communes"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "messages" ADD CONSTRAINT "messages_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "messages" ADD CONSTRAINT "messages_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "messages" ADD CONSTRAINT "messages_from_user_id_users_id_fk" FOREIGN KEY ("from_user_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "posts" ADD CONSTRAINT "posts_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "posts" ADD CONSTRAINT "posts_commune_id_communes_id_fk" FOREIGN KEY ("commune_id") REFERENCES "public"."communes"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "posts" ADD CONSTRAINT "posts_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "posts" ADD CONSTRAINT "posts_moderated_by_id_users_id_fk" FOREIGN KEY ("moderated_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "posts" ADD CONSTRAINT "posts_created_by_id_users_id_fk" FOREIGN KEY ("created_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "claims" ADD CONSTRAINT "claims_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "claims" ADD CONSTRAINT "claims_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "claims" ADD CONSTRAINT "claims_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "claims" ADD CONSTRAINT "claims_kbis_media_id_media_id_fk" FOREIGN KEY ("kbis_media_id") REFERENCES "public"."media"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "claims" ADD CONSTRAINT "claims_reviewer_id_users_id_fk" FOREIGN KEY ("reviewer_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "establishment_revisions" ADD CONSTRAINT "establishment_revisions_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "establishment_revisions" ADD CONSTRAINT "establishment_revisions_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "advent_doors" ADD CONSTRAINT "advent_doors_campaign_id_campaigns_id_fk" FOREIGN KEY ("campaign_id") REFERENCES "public"."campaigns"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "advent_doors" ADD CONSTRAINT "advent_doors_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "campaign_participants" ADD CONSTRAINT "campaign_participants_campaign_id_campaigns_id_fk" FOREIGN KEY ("campaign_id") REFERENCES "public"."campaigns"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "campaign_participants" ADD CONSTRAINT "campaign_participants_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "campaigns" ADD CONSTRAINT "campaigns_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "campaigns" ADD CONSTRAINT "campaigns_created_by_id_users_id_fk" FOREIGN KEY ("created_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "circuit_stops" ADD CONSTRAINT "circuit_stops_circuit_id_circuits_id_fk" FOREIGN KEY ("circuit_id") REFERENCES "public"."circuits"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "circuit_stops" ADD CONSTRAINT "circuit_stops_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "circuits" ADD CONSTRAINT "circuits_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "circuits" ADD CONSTRAINT "circuits_created_by_id_users_id_fk" FOREIGN KEY ("created_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "passport_stamps" ADD CONSTRAINT "passport_stamps_passport_id_passports_id_fk" FOREIGN KEY ("passport_id") REFERENCES "public"."passports"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "passport_stamps" ADD CONSTRAINT "passport_stamps_stop_id_circuit_stops_id_fk" FOREIGN KEY ("stop_id") REFERENCES "public"."circuit_stops"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "passports" ADD CONSTRAINT "passports_circuit_id_circuits_id_fk" FOREIGN KEY ("circuit_id") REFERENCES "public"."circuits"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "audiences" ADD CONSTRAINT "audiences_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "audiences" ADD CONSTRAINT "audiences_commune_id_communes_id_fk" FOREIGN KEY ("commune_id") REFERENCES "public"."communes"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "company_contacts" ADD CONSTRAINT "company_contacts_company_id_companies_id_fk" FOREIGN KEY ("company_id") REFERENCES "public"."companies"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "newsletter_deliveries" ADD CONSTRAINT "newsletter_deliveries_newsletter_id_newsletters_id_fk" FOREIGN KEY ("newsletter_id") REFERENCES "public"."newsletters"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "newsletter_deliveries" ADD CONSTRAINT "newsletter_deliveries_subscriber_id_subscribers_id_fk" FOREIGN KEY ("subscriber_id") REFERENCES "public"."subscribers"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "newsletters" ADD CONSTRAINT "newsletters_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "newsletters" ADD CONSTRAINT "newsletters_commune_id_communes_id_fk" FOREIGN KEY ("commune_id") REFERENCES "public"."communes"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "newsletters" ADD CONSTRAINT "newsletters_company_id_companies_id_fk" FOREIGN KEY ("company_id") REFERENCES "public"."companies"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "newsletters" ADD CONSTRAINT "newsletters_created_by_id_users_id_fk" FOREIGN KEY ("created_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "subscriber_audiences" ADD CONSTRAINT "subscriber_audiences_subscriber_id_subscribers_id_fk" FOREIGN KEY ("subscriber_id") REFERENCES "public"."subscribers"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "subscriber_audiences" ADD CONSTRAINT "subscriber_audiences_audience_id_audiences_id_fk" FOREIGN KEY ("audience_id") REFERENCES "public"."audiences"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "subscribers" ADD CONSTRAINT "subscribers_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "subscribers" ADD CONSTRAINT "subscribers_commune_id_communes_id_fk" FOREIGN KEY ("commune_id") REFERENCES "public"."communes"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "company_subscriptions" ADD CONSTRAINT "company_subscriptions_company_id_companies_id_fk" FOREIGN KEY ("company_id") REFERENCES "public"."companies"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "invoices" ADD CONSTRAINT "invoices_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "invoices" ADD CONSTRAINT "invoices_company_id_companies_id_fk" FOREIGN KEY ("company_id") REFERENCES "public"."companies"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "territory_contracts" ADD CONSTRAINT "territory_contracts_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "deal_activities" ADD CONSTRAINT "deal_activities_deal_id_deals_id_fk" FOREIGN KEY ("deal_id") REFERENCES "public"."deals"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "deal_activities" ADD CONSTRAINT "deal_activities_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "deal_contacts" ADD CONSTRAINT "deal_contacts_deal_id_deals_id_fk" FOREIGN KEY ("deal_id") REFERENCES "public"."deals"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "deal_documents" ADD CONSTRAINT "deal_documents_deal_id_deals_id_fk" FOREIGN KEY ("deal_id") REFERENCES "public"."deals"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "deal_tasks" ADD CONSTRAINT "deal_tasks_deal_id_deals_id_fk" FOREIGN KEY ("deal_id") REFERENCES "public"."deals"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "deals" ADD CONSTRAINT "deals_owner_id_users_id_fk" FOREIGN KEY ("owner_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "deals" ADD CONSTRAINT "deals_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "ai_usage" ADD CONSTRAINT "ai_usage_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "ai_usage" ADD CONSTRAINT "ai_usage_company_id_companies_id_fk" FOREIGN KEY ("company_id") REFERENCES "public"."companies"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "ai_usage" ADD CONSTRAINT "ai_usage_user_id_users_id_fk" FOREIGN KEY ("user_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "api_keys" ADD CONSTRAINT "api_keys_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "privacy_requests" ADD CONSTRAINT "privacy_requests_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "privacy_requests" ADD CONSTRAINT "privacy_requests_handled_by_id_users_id_fk" FOREIGN KEY ("handled_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "support_tickets" ADD CONSTRAINT "support_tickets_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "support_tickets" ADD CONSTRAINT "support_tickets_created_by_id_users_id_fk" FOREIGN KEY ("created_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "support_tickets" ADD CONSTRAINT "support_tickets_assignee_id_users_id_fk" FOREIGN KEY ("assignee_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
CREATE INDEX "categories_family_idx" ON "categories" USING btree ("family");--> statement-breakpoint
CREATE UNIQUE INDEX "commune_memberships_current_uq" ON "commune_memberships" USING btree ("commune_id") WHERE valid_to is null;--> statement-breakpoint
CREATE INDEX "commune_memberships_territory_idx" ON "commune_memberships" USING btree ("territory_id");--> statement-breakpoint
CREATE INDEX "communes_name_idx" ON "communes" USING btree ("name");--> statement-breakpoint
CREATE INDEX "territories_status_idx" ON "territories" USING btree ("status");--> statement-breakpoint
CREATE INDEX "territory_domains_territory_idx" ON "territory_domains" USING btree ("territory_id");--> statement-breakpoint
CREATE INDEX "role_assignments_territory_idx" ON "role_assignments" USING btree ("territory_id");--> statement-breakpoint
CREATE INDEX "sessions_user_idx" ON "sessions" USING btree ("user_id");--> statement-breakpoint
CREATE INDEX "tokens_email_idx" ON "tokens" USING btree ("email");--> statement-breakpoint
CREATE INDEX "company_members_user_idx" ON "company_members" USING btree ("user_id");--> statement-breakpoint
CREATE INDEX "establishment_attributes_attr_idx" ON "establishment_attributes" USING btree ("attribute_id");--> statement-breakpoint
CREATE INDEX "establishments_territory_status_idx" ON "establishments" USING btree ("territory_id","status");--> statement-breakpoint
CREATE INDEX "establishments_company_idx" ON "establishments" USING btree ("company_id");--> statement-breakpoint
CREATE INDEX "establishments_category_idx" ON "establishments" USING btree ("category_id");--> statement-breakpoint
CREATE INDEX "establishments_search_idx" ON "establishments" USING gin ("search_vector");--> statement-breakpoint
CREATE INDEX "establishments_name_trgm_idx" ON "establishments" USING gin (f_unaccent("name") gin_trgm_ops);--> statement-breakpoint
CREATE INDEX "exceptional_hours_est_date_idx" ON "exceptional_hours" USING btree ("establishment_id","date");--> statement-breakpoint
CREATE INDEX "media_establishment_idx" ON "media" USING btree ("establishment_id","sort_order");--> statement-breakpoint
CREATE INDEX "media_owner_idx" ON "media" USING btree ("owner_type","owner_id");--> statement-breakpoint
CREATE INDEX "opening_hours_est_idx" ON "opening_hours" USING btree ("establishment_id");--> statement-breakpoint
CREATE INDEX "products_est_idx" ON "products" USING btree ("establishment_id","sort_order");--> statement-breakpoint
CREATE INDEX "appointments_est_idx" ON "appointments" USING btree ("establishment_id","preferred_at");--> statement-breakpoint
CREATE INDEX "events_territory_start_idx" ON "events" USING btree ("territory_id","starts_at");--> statement-breakpoint
CREATE INDEX "events_establishment_idx" ON "events" USING btree ("establishment_id");--> statement-breakpoint
CREATE INDEX "job_applications_est_idx" ON "job_applications" USING btree ("establishment_id","created_at");--> statement-breakpoint
CREATE INDEX "jobs_territory_status_idx" ON "jobs" USING btree ("territory_id","status");--> statement-breakpoint
CREATE INDEX "markets_commune_idx" ON "markets" USING btree ("commune_id");--> statement-breakpoint
CREATE INDEX "messages_est_idx" ON "messages" USING btree ("establishment_id","created_at");--> statement-breakpoint
CREATE INDEX "posts_territory_published_idx" ON "posts" USING btree ("territory_id","status","published_at");--> statement-breakpoint
CREATE INDEX "posts_establishment_idx" ON "posts" USING btree ("establishment_id","published_at");--> statement-breakpoint
CREATE INDEX "posts_scheduled_idx" ON "posts" USING btree ("status","publish_at");--> statement-breakpoint
CREATE INDEX "claims_territory_status_idx" ON "claims" USING btree ("territory_id","status");--> statement-breakpoint
CREATE INDEX "claims_establishment_idx" ON "claims" USING btree ("establishment_id");--> statement-breakpoint
CREATE INDEX "establishment_revisions_est_idx" ON "establishment_revisions" USING btree ("establishment_id","created_at");--> statement-breakpoint
CREATE INDEX "campaign_participants_est_idx" ON "campaign_participants" USING btree ("establishment_id");--> statement-breakpoint
CREATE INDEX "campaigns_territory_status_idx" ON "campaigns" USING btree ("territory_id","status");--> statement-breakpoint
CREATE INDEX "circuit_stops_circuit_idx" ON "circuit_stops" USING btree ("circuit_id","position");--> statement-breakpoint
CREATE INDEX "passports_circuit_idx" ON "passports" USING btree ("circuit_id");--> statement-breakpoint
CREATE INDEX "audiences_territory_idx" ON "audiences" USING btree ("territory_id");--> statement-breakpoint
CREATE INDEX "newsletter_deliveries_status_idx" ON "newsletter_deliveries" USING btree ("newsletter_id","status");--> statement-breakpoint
CREATE INDEX "newsletters_territory_idx" ON "newsletters" USING btree ("territory_id","status");--> statement-breakpoint
CREATE INDEX "subscribers_territory_status_idx" ON "subscribers" USING btree ("territory_id","status");--> statement-breakpoint
CREATE INDEX "analytics_daily_territory_idx" ON "analytics_daily" USING btree ("territory_id","day");--> statement-breakpoint
CREATE INDEX "analytics_territory_time_idx" ON "analytics_events" USING btree ("territory_id","occurred_at");--> statement-breakpoint
CREATE INDEX "analytics_establishment_time_idx" ON "analytics_events" USING btree ("establishment_id","occurred_at");--> statement-breakpoint
CREATE INDEX "analytics_type_time_idx" ON "analytics_events" USING btree ("type","occurred_at");--> statement-breakpoint
CREATE INDEX "analytics_ref_idx" ON "analytics_events" USING btree ("ref_id");--> statement-breakpoint
CREATE INDEX "company_subscriptions_company_idx" ON "company_subscriptions" USING btree ("company_id");--> statement-breakpoint
CREATE INDEX "invoices_territory_idx" ON "invoices" USING btree ("territory_id");--> statement-breakpoint
CREATE INDEX "invoices_company_idx" ON "invoices" USING btree ("company_id");--> statement-breakpoint
CREATE INDEX "territory_contracts_territory_idx" ON "territory_contracts" USING btree ("territory_id");--> statement-breakpoint
CREATE INDEX "deal_activities_deal_idx" ON "deal_activities" USING btree ("deal_id","occurred_at");--> statement-breakpoint
CREATE INDEX "deal_contacts_deal_idx" ON "deal_contacts" USING btree ("deal_id");--> statement-breakpoint
CREATE INDEX "deal_documents_deal_idx" ON "deal_documents" USING btree ("deal_id");--> statement-breakpoint
CREATE INDEX "deal_tasks_deal_idx" ON "deal_tasks" USING btree ("deal_id");--> statement-breakpoint
CREATE INDEX "deals_stage_idx" ON "deals" USING btree ("stage");--> statement-breakpoint
CREATE INDEX "ai_usage_territory_idx" ON "ai_usage" USING btree ("territory_id","created_at");--> statement-breakpoint
CREATE INDEX "ai_usage_company_idx" ON "ai_usage" USING btree ("company_id","created_at");--> statement-breakpoint
CREATE INDEX "api_keys_territory_idx" ON "api_keys" USING btree ("territory_id");--> statement-breakpoint
CREATE INDEX "audit_log_territory_time_idx" ON "audit_log" USING btree ("territory_id","occurred_at");--> statement-breakpoint
CREATE INDEX "audit_log_category_idx" ON "audit_log" USING btree ("category","occurred_at");--> statement-breakpoint
CREATE INDEX "audit_log_target_idx" ON "audit_log" USING btree ("target_type","target_id");--> statement-breakpoint
CREATE INDEX "emails_status_idx" ON "emails" USING btree ("status","created_at");--> statement-breakpoint
CREATE INDEX "queue_jobs_pick_idx" ON "queue_jobs" USING btree ("status","run_at");--> statement-breakpoint
CREATE INDEX "support_tickets_status_idx" ON "support_tickets" USING btree ("status");