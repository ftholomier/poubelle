CREATE TABLE "calendar_feeds" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"commune_id" uuid,
	"name" varchar(160) NOT NULL,
	"url" text NOT NULL,
	"kind" "event_kind" DEFAULT 'AUTRE' NOT NULL,
	"active" boolean DEFAULT true NOT NULL,
	"last_sync_at" timestamp with time zone,
	"last_status" varchar(255),
	"last_count" integer DEFAULT 0 NOT NULL,
	"created_by_id" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
ALTER TABLE "companies" ADD COLUMN "webhook_secret" text;--> statement-breakpoint
ALTER TABLE "companies" ADD COLUMN "webhook_last_at" timestamp with time zone;--> statement-breakpoint
ALTER TABLE "companies" ADD COLUMN "webhook_last_status" varchar(120);--> statement-breakpoint
ALTER TABLE "events" ADD COLUMN "source_feed_id" uuid;--> statement-breakpoint
ALTER TABLE "events" ADD COLUMN "external_uid" varchar(255);--> statement-breakpoint
ALTER TABLE "calendar_feeds" ADD CONSTRAINT "calendar_feeds_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "calendar_feeds" ADD CONSTRAINT "calendar_feeds_commune_id_communes_id_fk" FOREIGN KEY ("commune_id") REFERENCES "public"."communes"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "calendar_feeds" ADD CONSTRAINT "calendar_feeds_created_by_id_users_id_fk" FOREIGN KEY ("created_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
CREATE INDEX "calendar_feeds_territory_idx" ON "calendar_feeds" USING btree ("territory_id");--> statement-breakpoint
ALTER TABLE "events" ADD CONSTRAINT "events_source_feed_id_calendar_feeds_id_fk" FOREIGN KEY ("source_feed_id") REFERENCES "public"."calendar_feeds"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "events" ADD CONSTRAINT "events_feed_uid_uq" UNIQUE("source_feed_id","external_uid");--> statement-breakpoint
UPDATE "plans" SET "limits" = "limits" || jsonb_build_object('contentSync', "key" <> 'ESSENTIEL');--> statement-breakpoint
UPDATE "plans" SET "features" = array_append("features", 'Synchronisation de vos contenus (connecteur)') WHERE "key" = 'PREMIUM' AND NOT ('Synchronisation de vos contenus (connecteur)' = ANY("features"));
