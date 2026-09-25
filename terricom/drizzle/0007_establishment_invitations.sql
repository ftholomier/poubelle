ALTER TABLE "establishments" ADD COLUMN "invited_at" timestamp with time zone;--> statement-breakpoint
ALTER TABLE "establishments" ADD COLUMN "invitation_count" integer DEFAULT 0 NOT NULL;