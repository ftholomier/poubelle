CREATE TABLE "health_probes" (
	"id" bigserial PRIMARY KEY NOT NULL,
	"at" timestamp with time zone DEFAULT now() NOT NULL,
	"ok" boolean NOT NULL,
	"latency_ms" integer NOT NULL,
	"detail" varchar(255)
);
--> statement-breakpoint
CREATE INDEX "health_probes_at_idx" ON "health_probes" USING btree ("at");