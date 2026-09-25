import { Pool } from 'pg';
import { loadEnvFile } from './_env';

loadEnvFile();

/** Réinitialise complètement la base (développement uniquement). */
async function main() {
  if (process.env.NODE_ENV === 'production') throw new Error('Interdit en production');
  const pool = new Pool({ connectionString: process.env.DATABASE_URL });
  await pool.query('DROP SCHEMA IF EXISTS public CASCADE; DROP SCHEMA IF EXISTS drizzle CASCADE; CREATE SCHEMA public;');
  console.log('✓ Base réinitialisée');
  await pool.end();
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
