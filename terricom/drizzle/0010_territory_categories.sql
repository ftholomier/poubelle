CREATE TABLE "territory_categories" (
	"territory_id" uuid NOT NULL,
	"category_id" uuid NOT NULL,
	"label" varchar(160),
	"hidden" boolean DEFAULT false NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL,
	CONSTRAINT "territory_categories_territory_id_category_id_pk" PRIMARY KEY("territory_id","category_id")
);
--> statement-breakpoint
ALTER TABLE "territory_categories" ADD CONSTRAINT "territory_categories_territory_id_territories_id_fk" FOREIGN KEY ("territory_id") REFERENCES "public"."territories"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "territory_categories" ADD CONSTRAINT "territory_categories_category_id_categories_id_fk" FOREIGN KEY ("category_id") REFERENCES "public"."categories"("id") ON DELETE cascade ON UPDATE no action;