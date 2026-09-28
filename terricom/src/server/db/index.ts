import { drizzle } from 'drizzle-orm/node-postgres';
import { Pool } from 'pg';
import { env } from '../env';
import * as schema from './schema';

/**
 * Pool de connexions partagé. En développement, le rechargement à chaud
 * réévalue les modules : on réutilise le pool pour ne pas épuiser les connexions.
 */
const globalForDb = globalThis as unknown as { __terricomPool?: Pool };

export const pool =
  globalForDb.__terricomPool ??
  new Pool({
    connectionString: env.DATABASE_URL,
    max: env.DATABASE_POOL_MAX,
    idleTimeoutMillis: 30_000,
    connectionTimeoutMillis: 10_000,
    application_name: 'terricom',
  });

if (env.NODE_ENV !== 'production') globalForDb.__terricomPool = pool;

export const db = drizzle(pool, { schema, casing: 'snake_case' });

export type DB = typeof db;
export type Tx = Parameters<Parameters<DB['transaction']>[0]>[0];
export type DbOrTx = DB | Tx;
export { schema };
