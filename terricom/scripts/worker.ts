import { createServer } from 'node:http';
import { hostname } from 'node:os';
import { loadEnvFile } from './_env';

loadEnvFile();

/**
 * Worker terricom : exécute la file de tâches PostgreSQL (SELECT … FOR UPDATE SKIP LOCKED) et
 * déclenche les tâches planifiées. Plusieurs réplicas peuvent tourner en parallèle : chaque tâche
 * n'est prise qu'une fois et chaque créneau planifié n'est déclenché que par un seul worker.
 *
 *   WORKER_CONCURRENCY   tâches exécutées en parallèle (défaut 4)
 *   WORKER_HEALTH_PORT   sonde de vie HTTP pour Kubernetes (défaut 9090, 0 pour désactiver)
 */
async function main() {
  const { sql } = await import('drizzle-orm');
  const { db, pool } = await import('@/server/db');
  const { env } = await import('@/server/env');
  const { logger } = await import('@/server/logger');
  const { claimJobs, completeJob, enqueue, failJob, releaseStaleJobs } = await import('@/server/queue');
  const { SCHEDULES } = await import('@/server/jobs/schedules');
  const { runJob } = await import('@/server/jobs/handlers');
  const { fromParisLocal, parisDate } = await import('@/lib/format');

  const workerId = `${hostname()}:${process.pid}`;
  const concurrency = Math.max(1, Number(process.env.WORKER_CONCURRENCY ?? 4));
  const schedules = SCHEDULES.filter((s) => !s.demoOnly || env.DEMO_MODE);
  let running = 0;
  let stopping = false;
  let lastLoop = Date.now();

  /**
   * Inscription des tâches planifiées, sans écraser l'état « activée » choisi dans la console.
   * Une tâche quotidienne nouvellement inscrite attend son prochain créneau (pas d'exécution
   * immédiate au premier démarrage) ; l'inscription est refaite à chaque cycle, ce qui la rétablit
   * après une réinitialisation de la base.
   */
  async function registerSchedules(updateInterval: boolean) {
    for (const s of schedules) {
      await db.execute(sql`
        insert into job_schedules (name, every_minutes, enabled, last_run_at)
        values (${s.name}, ${s.everyMinutes}, true, ${s.at ? sql`now()` : sql`null`})
        on conflict (name) do ${updateInterval ? sql`update set every_minutes = excluded.every_minutes` : sql`nothing`}`);
    }
  }
  await registerSchedules(true);
  logger.info('worker.started', { workerId, concurrency, schedules: schedules.length });

  /** Déclenche les créneaux échus ; l'UPDATE conditionnel garantit un seul déclenchement par créneau. */
  async function tickSchedules() {
    await registerSchedules(false);
    const now = Date.now();
    for (const s of schedules) {
      let threshold: Date;
      if (s.at) {
        const todayAt = fromParisLocal(parisDate(), s.at);
        if (now < todayAt.getTime()) continue;
        threshold = todayAt;
      } else {
        threshold = new Date(now - s.everyMinutes * 60_000 + 5_000);
      }
      const claimed = await db.execute(sql`
        update job_schedules set last_run_at = now()
        where name = ${s.name} and enabled and (last_run_at is null or last_run_at < ${threshold.toISOString()}::timestamptz)
        returning name`);
      if (claimed.rowCount) await enqueue(s.queue, { scheduled: s.name }, { dedupeKey: `sched:${s.name}:${Math.floor(now / 60_000)}`, maxAttempts: 3 });
    }
  }

  async function execute(job: Awaited<ReturnType<typeof claimJobs>>[number]) {
    running++;
    try {
      await runJob(job.queue, job.payload);
      await completeJob(job.id);
    } catch (err) {
      logger.error('job.failed', { id: job.id, queue: job.queue, attempt: job.attempts, err });
      await failJob(job, err).catch((e) => logger.error('job.fail_record_failed', { err: e }));
    } finally {
      running--;
    }
  }

  let lastScheduleTick = 0;
  let lastStaleSweep = 0;
  async function loop() {
    while (!stopping) {
      lastLoop = Date.now();
      try {
        if (Date.now() - lastScheduleTick > 15_000) {
          lastScheduleTick = Date.now();
          await tickSchedules();
        }
        if (Date.now() - lastStaleSweep > 60_000) {
          lastStaleSweep = Date.now();
          const released = await releaseStaleJobs(15);
          if (released) logger.warn('worker.stale_released', { released });
        }
        const free = concurrency - running;
        const jobs = free > 0 ? await claimJobs(workerId, free) : [];
        for (const job of jobs) void execute(job);
        if (!jobs.length) await new Promise((r) => setTimeout(r, 1000));
      } catch (err) {
        logger.error('worker.loop_error', { err });
        await new Promise((r) => setTimeout(r, 5000));
      }
    }
  }

  // Sonde de vie : la boucle doit avoir tourné dans les 60 dernières secondes.
  const healthPort = Number(process.env.WORKER_HEALTH_PORT ?? 9090);
  const server =
    healthPort > 0
      ? createServer((req, res) => {
          const alive = Date.now() - lastLoop < 60_000;
          res.writeHead(alive ? 200 : 503, { 'content-type': 'application/json' });
          res.end(JSON.stringify({ status: alive ? 'ok' : 'stalled', running, workerId }));
        })
          .on('error', (err) => logger.warn('worker.health_server_unavailable', { port: healthPort, err: err.message }))
          .listen(healthPort)
      : null;

  async function shutdown(signal: string) {
    if (stopping) return;
    stopping = true;
    logger.info('worker.stopping', { signal, running });
    const deadline = Date.now() + 25_000;
    while (running > 0 && Date.now() < deadline) await new Promise((r) => setTimeout(r, 250));
    server?.close();
    await pool.end().catch(() => undefined);
    logger.info('worker.stopped', { unfinished: running });
    process.exit(0);
  }
  process.on('SIGTERM', () => void shutdown('SIGTERM'));
  process.on('SIGINT', () => void shutdown('SIGINT'));

  await loop();
}

main().catch((err) => {
  console.error('✗ Worker arrêté', err);
  process.exit(1);
});
