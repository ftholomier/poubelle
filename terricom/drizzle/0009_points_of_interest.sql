CREATE TYPE "public"."poi_kind" AS ENUM('ZONE_ACTIVITE', 'HALLE', 'OFFICE_TOURISME', 'TIERS_LIEU', 'PEPINIERE', 'GARE', 'AUTRE');--> statement-breakpoint
CREATE TABLE "points_of_interest" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"territory_id" uuid NOT NULL,
	"commune_id" uuid,
	"kind" "poi_kind" DEFAULT 'AUTRE' NOT NULL,
	"name" varchar(255) NOT NULL,
	"description" text,
	"address" varchar(255),
	"url" text,
	"lat" double precision NOT NULL,
	"lng" double precision NOT NULL,
	"is_active" boolean DEFAULT true NOT NULL,
	"created_by_id" uuid,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
ALTER TABLE "points_of_interest" ADD CONSTRAINT "points_of_interest_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "points_of_interest" ADD CONSTRAINT "points_of_interest_commune_id_communes_id_fk" FOREIGN KEY ("commune_id") REFERENCES "public"."communes"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "points_of_interest" ADD CONSTRAINT "points_of_interest_created_by_id_users_id_fk" FOREIGN KEY ("created_by_id") REFERENCES "public"."users"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
CREATE INDEX "points_of_interest_territory_idx" ON "points_of_interest" USING btree ("territory_id");