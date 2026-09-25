import { sql } from 'drizzle-orm';
import { db, type DbOrTx } from './db';
import { queueJobs } from './db/schema';

export type QueueName =
  | 'email.send'
  | 'push.send'
  | 'newsletter.dispatch'
  | 'newsletter.send-batch'
  | 'posts.publish-due'
  | 'posts.social-sync'
  | 'analytics.rollup'
  | 'maintenance.purge'
  | 'claims.reminders'
  | 'import.geocode'
  | 'import.sirene'
  | 'search.refresh'
  | 'campaigns.status'
  | 'health.probe'
  | 'billing.overdue'
  | 'domains.sync'
  | 'demo.reset';

export type EnqueueOptions = { runAt?: Date; dedupeKey?: string; maxAttempts?: number };

/**
 * Ajoute une tâche à la file PostgreSQL. La clé de déduplication évite les doublons
 * (ex. une seule tâche de publication par créneau).
 */
export async function enqueue(queue: QueueName, payload: Record<string, unknown> = {}, opts: EnqueueOptions = {}, tx: DbOrTx = db): Promise<void> {
  await tx
    .insert(queueJobs)
    .values({
      queue,
      payload,
      runAt: opts.runAt ?? new Date(),
      maxAttempts: opts.maxAttempts ?? 5,
      dedupeKey: opts.dedupeKey ?? null,
    })
    .onConflictDoNothing({ target: queueJobs.dedupeKey });
}

export type ClaimedJob = {
  id: number;
  queue: QueueName;
  payload: Record<string, unknown>;
  attempts: number;
  max_attempts: number;
};

/** Réserve des tâches prêtes (FOR UPDATE SKIP LOCKED : plusieurs workers sans conflit). */
export async function claimJobs(workerId: string, limit = 10): Promise<ClaimedJob[]> {
  const res = await db.execute<ClaimedJob>(sql`
    UPDATE queue_jobs SET status = 'RUNNING', locked_at = now(), locked_by = ${workerId}, attempts = attempts + 1
    WHERE id IN (
      SELECT id FROM queue_jobs
      WHERE status = 'QUEUED' AND run_at <= now()
      ORDER BY run_at
      LIMIT ${limit}
      FOR UPDATE SKIP LOCKED
    )
    RETURNING id, queue, payload, attempts, max_attempts
  `);
  return res.rows;
}

export async function completeJob(id: number): Promise<void> {
  await db.execute(sql`UPDATE queue_jobs SET status = 'DONE', finished_at = now(), last_error = NULL WHERE id = ${id}`);
}

/** Échec : nouvelle tentative avec attente exponentielle, puis abandon après maxAttempts. */
export async function failJob(job: ClaimedJob, error: unknown): Promise<void> {
  const message = error instanceof Error ? `${error.message}\n${error.stack ?? ''}`.slice(0, 4000) : String(error);
  const final = job.attempts >= job.max_attempts;
  const delaySeconds = Math.min(3600, 15 * 2 ** job.attempts);
  await db.execute(sql`
    UPDATE queue_jobs SET
      status = ${final ? 'FAILED' : 'QUEUED'}::queue_status,
      last_error = ${message},
      locked_at = NULL, locked_by = NULL,
      run_at = CASE WHEN ${final} THEN run_at ELSE now() + make_interval(secs => ${delaySeconds}) END,
      finished_at = CASE WHEN ${final} THEN now() ELSE NULL END
    WHERE id = ${job.id}
  `);
}

/** Libère les tâches bloquées par un worker arrêté brutalement (pod supprimé…). */
export async function releaseStaleJobs(olderThanMinutes = 15): Promise<number> {
  const res = await db.execute(sql`
    UPDATE queue_jobs SET status = 'QUEUED', locked_at = NULL, locked_by = NULL
    WHERE status = 'RUNNING' AND locked_at < now() - make_interval(mins => ${olderThanMinutes})
  `);
  return res.rowCount ?? 0;
}

export async function purgeFinishedJobs(): Promise<number> {
  const res = await db.execute(sql`
    DELETE FROM queue_jobs WHERE status IN ('DONE') AND finished_at < now() - interval '7 days'
       OR status = 'FAILED' AND finished_at < now() - interval '30 days'
  `);
  return res.rowCount ?? 0;
}

export async function queueStats(): Promise<Record<string, number>> {
  const res = await db.execute<{ status: string; n: number }>(sql`SELECT status, count(*)::int AS n FROM queue_jobs GROUP BY status`);
  return Object.fromEntries(res.rows.map((r) => [r.status, Number(r.n)]));
}
