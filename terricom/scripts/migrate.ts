import { drizzle } from 'drizzle-orm/node-postgres';
import { migrate } from 'drizzle-orm/node-postgres/migrator';
import { Pool } from 'pg';
import { loadEnvFile } from './_env';

loadEnvFile();

async function main() {
  const url = process.env.DATABASE_URL ?? 'postgres://terricom:terricom@localhost:5432/terricom';
  const pool = new Pool({ connectionString: url, max: 1 });
  const db = drizzle(pool);
  const started = Date.now();
  // Verrou consultatif : plusieurs réplicas peuvent lancer la migration au démarrage sans conflit.
  await pool.query('SELECT pg_advisory_lock(727274)');
  try {
    await migrate(db, { migrationsFolder: './drizzle' });
  } finally {
    await pool.query('SELECT pg_advisory_unlock(727274)');
  }
  console.log(`✓ Migrations appliquées en ${Date.now() - started} ms`);
  await pool.end();
}

main().catch((err) => {
  console.error('✗ Échec des migrations', err);
  process.exit(1);
});
