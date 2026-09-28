CREATE TABLE "sirene_changes" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"commune_id" uuid NOT NULL,
	"run_id" uuid,
	"kind" varchar(16) NOT NULL,
	"siret" varchar(14) NOT NULL,
	"record" jsonb NOT NULL,
	"category_id" uuid,
	"establishment_id" uuid,
	"status" varchar(16) DEFAULT 'PENDING' NOT NULL,
	"decided_by_id" uuid,
	"decided_at" timestamp with time zone,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
CREATE TABLE "sirene_sync_runs" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"source" varchar(16) NOT NULL,
	"trigger" varchar(16) DEFAULT 'SCHEDULE' NOT NULL,
	"status" varchar(16) DEFAULT 'RUNNING' NOT NULL,
	"since" timestamp with time zone,
	"creations" integer DEFAULT 0 NOT NULL,
	"closures" integer DEFAULT 0 NOT NULL,
	"ignored" integer DEFAULT 0 NOT NULL,
	"error" text,
	"started_at" timestamp with time zone DEFAULT now() NOT NULL,
	"finished_at" timestamp with time zone
);
--> statement-breakpoint
ALTER TABLE "sirene_changes" ADD CONSTRAINT "sirene_changes_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "sirene_changes" ADD CONSTRAINT "sirene_changes_commune_id_communes_id_fk" FOREIGN KEY ("commune_id") REFERENCES "public"."communes"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "sirene_changes" ADD CONSTRAINT "sirene_changes_run_id_sirene_sync_runs_id_fk" FOREIGN KEY ("run_id") REFERENCES "public"."sirene_sync_runs"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "sirene_changes" ADD CONSTRAINT "sirene_changes_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "sirene_changes" ADD CONSTRAINT "sirene_changes_decided_by_id_users_id_fk" FOREIGN KEY ("decided_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "sirene_sync_runs" ADD CONSTRAINT "sirene_sync_runs_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
CREATE INDEX "sirene_changes_pending_idx" ON "sirene_changes" USING btree ("territory_id","status","kind");--> statement-breakpoint
CREATE UNIQUE INDEX "sirene_changes_siret_kind_uq" ON "sirene_changes" USING btree ("territory_id","siret","kind");--> statement-breakpoint
CREATE INDEX "sirene_sync_runs_territory_idx" ON "sirene_sync_runs" USING btree ("territory_id","started_at");