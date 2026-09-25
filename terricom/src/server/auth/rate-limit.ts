import { sql } from 'drizzle-orm';
import { db } from '../db';

export type RateLimitResult = { ok: boolean; count: number; remaining: number; resetAt: Date };

/**
 * Limitation de débit à fenêtre fixe, stockée en base : partagée entre toutes
 * les instances de l'application (pas d'état local à un pod).
 */
export async function rateLimit(key: string, limit: number, windowSeconds: number): Promise<RateLimitResult> {
  const res = await db.execute<{ count: number; reset_at: Date }>(sql`
    INSERT INTO rate_limits (key, count, reset_at)
    VALUES (${key}, 1, now() + make_interval(secs => ${windowSeconds}))
    ON CONFLICT (key) DO UPDATE SET
      count = CASE WHEN rate_limits.reset_at < now() THEN 1 ELSE rate_limits.count + 1 END,
      reset_at = CASE WHEN rate_limits.reset_at < now() THEN now() + make_interval(secs => ${windowSeconds}) ELSE rate_limits.reset_at END
    RETURNING count, reset_at
  `);
  const row = res.rows[0];
  const count = Number(row.count);
  return { ok: count <= limit, count, remaining: Math.max(0, limit - count), resetAt: new Date(row.reset_at) };
}

/** Marque une clé comme consommée (ex. code TOTP déjà utilisé) ; renvoie false si elle l'était déjà. */
export async function consumeOnce(key: string, ttlSeconds: number): Promise<boolean> {
  const res = await rateLimit(key, 1, ttlSeconds);
  return res.ok;
}

export async function resetRateLimit(key: string): Promise<void> {
  await db.execute(sql`DELETE FROM rate_limits WHERE key = ${key}`);
}

export async function purgeExpiredRateLimits(): Promise<number> {
  const res = await db.execute(sql`DELETE FROM rate_limits WHERE reset_at < now() - interval '1 hour'`);
  return res.rowCount ?? 0;
}
