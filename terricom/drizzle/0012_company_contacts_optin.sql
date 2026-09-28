ALTER TABLE "company_contacts" ADD COLUMN "establishment_id" uuid;--> statement-breakpoint
ALTER TABLE "company_contacts" ADD COLUMN "confirm_token_hash" varchar(64);--> statement-breakpoint
ALTER TABLE "company_contacts" ADD COLUMN "confirmed_at" timestamp with time zone;--> statement-breakpoint
ALTER TABLE "company_contacts" ADD COLUMN "unsubscribed_at" timestamp with time zone;--> statement-breakpoint
ALTER TABLE "company_contacts" ADD CONSTRAINT "company_contacts_establishment_id_establishments_id_fk" FOREIGN KEY ("establishment_id") REFERENCES "public"."establishments"("id") ON DELETE set null ON UPDATE no action;--> statement-breakpoint
CREATE INDEX "company_contacts_company_idx" ON "company_contacts" USING btree ("company_id","subscribed");