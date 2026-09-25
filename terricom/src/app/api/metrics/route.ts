import { sql } from 'drizzle-orm';
import { safeEqual } from '@/server/crypto';
import { db } from '@/server/db';
import { env } from '@/server/env';

/** Métriques Prometheus (files de tâches, emails, contenus). Protégées par METRICS_TOKEN. */
export const dynamic = 'force-dynamic';

export async function GET(req: Request) {
  if (!env.METRICS_TOKEN) return new Response('Introuvable', { status: 404 });
  const auth = req.headers.get('authorization') ?? '';
  if (!safeEqual(auth, `Bearer ${env.METRICS_TOKEN}`)) return new Response('Non autorisé', { status: 401 });
  const [queues, mails, counts] = await Promise.all([
    db.execute<{ queue: string; status: string; n: number }>(sql`select queue, status, count(*)::int as n from queue_jobs group by 1, 2`),
    db.execute<{ status: string; n: number }>(sql`select status, count(*)::int as n from emails where created_at >= now() - interval '1 day' group by 1`),
    db.execute<{ establishments: number; territories: number; sessions: number; lag: number }>(sql`
      select (select count(*)::int from establishments where status <> 'ARCHIVED') as establishments,
             (select count(*)::int from territories where status = 'ACTIVE') as territories,
             (select count(*)::int from sessions where expires_at > now()) as sessions,
             coalesce((select extract(epoch from now() - min(run_at))::int from queue_jobs where status = 'PENDING' and run_at <= now()), 0) as lag`),
  ]);
  const c = counts.rows[0];
  const lines = [
    '# HELP terricom_queue_jobs Tâches de la file par état',
    '# TYPE terricom_queue_jobs gauge',
    ...queues.rows.map((r) => `terricom_queue_jobs{queue="${r.queue}",status="${r.status}"} ${r.n}`),
    '# HELP terricom_queue_lag_seconds Ancienneté de la plus vieille tâche en attente',
    '# TYPE terricom_queue_lag_seconds gauge',
    `terricom_queue_lag_seconds ${c?.lag ?? 0}`,
    '# HELP terricom_emails_24h Emails des dernières 24 h par état',
    '# TYPE terricom_emails_24h gauge',
    ...mails.rows.map((r) => `terricom_emails_24h{status="${r.status}"} ${r.n}`),
    '# TYPE terricom_establishments gauge',
    `terricom_establishments ${c?.establishments ?? 0}`,
    '# TYPE terricom_territories_active gauge',
    `terricom_territories_active ${c?.territories ?? 0}`,
    '# TYPE terricom_sessions_active gauge',
    `terricom_sessions_active ${c?.sessions ?? 0}`,
    '# TYPE terricom_process_uptime_seconds gauge',
    `terricom_process_uptime_seconds ${Math.round(process.uptime())}`,
  ];
  return new Response(`${lines.join('\n')}\n`, { headers: { 'content-type': 'text/plain; version=0.0.4', 'cache-control': 'no-store' } });
}
