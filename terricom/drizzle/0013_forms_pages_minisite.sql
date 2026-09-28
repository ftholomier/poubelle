CREATE TABLE "establishment_forms" (
	"id" uuid PRIMARY KEY DEFAULT gen_random_uuid() NOT NULL,
	"establishment_id" uuid NOT NULL,
	"title" varchar(160) NOT NULL,
	"intro" text,
	"fields" jsonb DEFAULT '[]'::jsonb NOT NULL,
	"submit_label" varchar(60) DEFAULT 'Envoyer' NOT NULL,
	"success_text" text,
	"is_active" boolean DEFAULT true NOT NULL,
	"sort_order" integer DEFAULT 0 NOT NULL,
	"created_at" timestamp with time zone DEFAULT now() NOT NULL,
	"updated_at" timestamp with time zone DEFAULT now() NOT NULL
);
--> statement-breakpoint
ALTER TABLE "establishment_pages" ADD COLUMN "cover_url" text;--> statement-breakpoint
ALTER TABLE "establishments" ADD COLUMN "mini_site" jsonb DEFAULT '{}'::jsonb NOT NULL;--> statement-breakpoint
ALTER TABLE "messages" ADD COLUMN "form_id" uuid;--> statement-breakpoint
ALTER TABLE "messages" ADD COLUMN "answers" jsonb;--> statement-breakpoint
ALTER TABLE "newsletters" ADD COLUMN "establishment_id" uuid;--> statement-breakpoint
ALTER TABLE "establishment_forms" ADD CONSTRAINT "establishment_forms_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE cascade ON UPDATE no action;--> statement-breakpoint
CREATE INDEX "establishment_forms_est_idx" ON "establishment_forms" USING btree ("establishment_id","sort_order");--> statement-breakpoint
ALTER TABLE "messages" ADD CONSTRAINT "messages_form_id_establishment_forms_id_fk" FOREIGN KEY ("form_id") REFERENCES "public"."establishment_forms"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
ALTER TABLE "newsletters" ADD CONSTRAINT "newsletters_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
UPDATE "plans" SET "limits" = "limits" || jsonb_build_object('customForms', "key" <> 'ESSENTIEL', 'extraPages', "key" <> 'ESSENTIEL');
