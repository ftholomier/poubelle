ALTER TABLE "communes" ADD COLUMN "epci_siren" varchar(9);--> statement-breakpoint
ALTER TABLE "communes" ADD COLUMN "epci_name" varchar(255);--> statement-breakpoint
ALTER TABLE "company_subscriptions" ADD COLUMN "direct" boolean DEFAULT false NOT NULL;--> statement-breakpoint
ALTER TABLE "company_subscriptions" ADD COLUMN "interval" varchar(8) DEFAULT 'MONTH' NOT NULL;--> statement-breakpoint
ALTER TABLE "deals" ADD COLUMN "siren" varchar(9);