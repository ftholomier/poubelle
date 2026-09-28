import { sql } from 'drizzle-orm';
import { db } from '@/server/db';

/** Sonde de disponibilité (Kubernetes readiness) : la base de données doit répondre. */
export const dynamic = 'force-dynamic';

export async function GET() {
  const started = Date.now();
  try {
    await Promise.race([db.execute(sql`select 1`), new Promise((_, reject) => setTimeout(() => reject(new Error('timeout')), 2000))]);
    return Response.json({ status: 'ready', dbMs: Date.now() - started }, { headers: { 'cache-control': 'no-store' } });
  } catch (err) {
    return Response.json(
      { status: 'unavailable', error: err instanceof Error ? err.message : 'db' },
      { status: 503, headers: { 'cache-control': 'no-store' } },
    );
  }
}
