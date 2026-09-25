import { defineConfig } from 'drizzle-kit';

export default defineConfig({
  dialect: 'postgresql',
  schema: './src/server/db/schema/index.ts',
  out: './drizzle',
  dbCredentials: {
    url: process.env.DATABASE_URL ?? 'postgres://terricom:terricom@localhost:5432/terricom',
  },
  casing: 'snake_case',
  strict: true,
  verbose: true,
});
