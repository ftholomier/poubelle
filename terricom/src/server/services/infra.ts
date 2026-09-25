import { existsSync, readFileSync } from 'node:fs';
import { request } from 'node:https';
import { sql } from 'drizzle-orm';
import { db } from '../db';
import { env } from '../env';
import { storage } from '../storage';

/**
 * État de l'infrastructure affiché dans la console (S3).
 * En production sur Kubernetes, les déploiements et la sauvegarde planifiée sont lus via l'API
 * du cluster (compte de service en lecture seule sur l'espace de noms). Ailleurs, chaque
 * composant est sondé directement (base, file de tâches, stockage, email, IA, antivirus).
 */

export type ComponentState = 'ok' | 'warn' | 'down';
export type InfraComponent = { label: string; state: ComponentState; detail?: string };
export type InfraStatus = { source: 'kubernetes' | 'direct'; components: InfraComponent[]; summary: string; checkedAt: Date };

const SA_DIR = '/var/run/secrets/kubernetes.io/serviceaccount';
let cache: { at: number; value: InfraStatus } | null = null;

/** Espace de noms courant quand l'application tourne dans Kubernetes (compte de service monté), sinon null. */
export function k8sNamespace(): string | null {
  if (!process.env.KUBERNETES_SERVICE_HOST || !existsSync(`${SA_DIR}/namespace`)) return null;
  return readFileSync(`${SA_DIR}/namespace`, 'utf8').trim();
}

/** Appel à l'API du cluster avec le jeton du compte de service (droits limités par RBAC). */
export function k8sRequest<T>(method: string, path: string, body?: unknown, contentType = 'application/json'): Promise<{ status: number; data: T | null }> {
  const host = process.env.KUBERNETES_SERVICE_HOST;
  const port = Number(process.env.KUBERNETES_SERVICE_PORT ?? 443);
  if (!host || !existsSync(`${SA_DIR}/token`)) return Promise.resolve({ status: 0, data: null });
  const token = readFileSync(`${SA_DIR}/token`, 'utf8').trim();
  const ca = readFileSync(`${SA_DIR}/ca.crt`);
  const payload = body === undefined ? undefined : JSON.stringify(body);
  const headers: Record<string, string | number> = { authorization: `Bearer ${token}`, accept: 'application/json' };
  if (payload) {
    headers['content-type'] = contentType;
    headers['content-length'] = Buffer.byteLength(payload);
  }
  return new Promise((resolve) => {
    const req = request({ host, port, path, method, ca, headers, timeout: 5000 }, (res) => {
      let raw = '';
      res.on('data', (c) => (raw += c));
      res.on('end', () => {
        try {
          resolve({ status: res.statusCode ?? 0, data: raw ? (JSON.parse(raw) as T) : null });
        } catch {
          resolve({ status: res.statusCode ?? 0, data: null });
        }
      });
    });
    req.on('timeout', () => req.destroy());
    req.on('error', () => resolve({ status: 0, data: null }));
    if (payload) req.write(payload);
    req.end();
  });
}

async function k8sGet<T>(path: string): Promise<T | null> {
  const res = await k8sRequest<T>('GET', path);
  return res.status === 200 ? res.data : null;
}

type Workload = {
  metadata: { name: string; labels?: Record<string, string> };
  spec: { replicas?: number };
  status: { readyReplicas?: number; replicas?: number };
};
type CronJob = { metadata: { name: string }; status?: { lastSuccessfulTime?: string; lastScheduleTime?: string } };

async function fromKubernetes(): Promise<InfraStatus | null> {
  const ns = k8sNamespace();
  if (!ns) return null;
  const [deps, sets, crons] = await Promise.all([
    k8sGet<{ items: Workload[] }>(`/apis/apps/v1/namespaces/${ns}/deployments`),
    k8sGet<{ items: Workload[] }>(`/apis/apps/v1/namespaces/${ns}/statefulsets`),
    k8sGet<{ items: CronJob[] }>(`/apis/batch/v1/namespaces/${ns}/cronjobs`),
  ]);
  if (!deps && !sets) return null;
  const components: InfraComponent[] = [...(deps?.items ?? []), ...(sets?.items ?? [])].map((w) => {
    const want = w.spec.replicas ?? 1;
    const ready = w.status.readyReplicas ?? 0;
    const name = w.metadata.labels?.['app.kubernetes.io/component'] ?? w.metadata.name.replace(/^terricom-/, '');
    return { label: `${name} ×${want}`, state: ready >= want ? 'ok' : ready > 0 ? 'warn' : 'down', detail: `${ready}/${want} prêts` };
  });
  const backup = crons?.items.find((c) => /backup|sauvegarde/.test(c.metadata.name));
  const last = backup?.status?.lastSuccessfulTime ? new Date(backup.status.lastSuccessfulTime) : null;
  if (backup) {
    const ageH = last ? (Date.now() - last.getTime()) / 3_600_000 : Infinity;
    components.push({
      label: 'sauvegardes',
      state: ageH < 26 ? 'ok' : 'warn',
      detail: last ? `dernière le ${last.toLocaleString('fr-FR', { timeZone: 'Europe/Paris' })}` : 'jamais exécutée',
    });
  }
  return {
    source: 'kubernetes',
    components,
    summary: `Kubernetes · espace « ${ns} » · ${components.length} composants${last ? ` · sauvegarde réussie le ${last.toLocaleDateString('fr-FR', { timeZone: 'Europe/Paris' })}` : ''}`,
    checkedAt: new Date(),
  };
}

async function timed<T>(fn: () => Promise<T>): Promise<{ ms: number; value: T | null; error?: string }> {
  const t0 = performance.now();
  try {
    const value = await fn();
    return { ms: Math.round(performance.now() - t0), value };
  } catch (e) {
    return { ms: Math.round(performance.now() - t0), value: null, error: e instanceof Error ? e.message : String(e) };
  }
}

async function direct(): Promise<InfraStatus> {
  const [pg, queue, sched, store] = await Promise.all([
    timed(
      async () =>
        (await db.execute<{ v: string; replica: boolean }>(sql`select current_setting('server_version') as v, pg_is_in_recovery() as replica`)).rows[0],
    ),
    timed(
      async () =>
        (
          await db.execute<{ late: number; failed: number }>(sql`
      select count(*) filter (where status = 'QUEUED' and run_at < now() - interval '10 minutes')::int as late,
             count(*) filter (where status = 'FAILED' and finished_at > now() - interval '24 hours')::int as failed
      from queue_jobs`)
        ).rows[0],
    ),
    timed(async () => (await db.execute<{ last: Date | null }>(sql`select max(last_run_at) as last from job_schedules`)).rows[0]),
    timed(async () => {
      const key = `health/probe-${process.pid}.txt`;
      await storage().put(key, Buffer.from('ok'), 'text/plain');
      await storage().delete(key);
      return true;
    }),
  ]);
  const lastRun = sched.value?.last ? new Date(sched.value.last) : null;
  const workerAgeMin = lastRun ? (Date.now() - lastRun.getTime()) / 60_000 : Infinity;
  const components: InfraComponent[] = [
    { label: 'web', state: 'ok', detail: `Node ${process.version}` },
    { label: 'postgres', state: pg.value ? 'ok' : 'down', detail: pg.value ? `PostgreSQL ${pg.value.v.split(' ')[0]} · ${pg.ms} ms` : pg.error },
    {
      label: 'worker',
      state: workerAgeMin < 10 ? 'ok' : lastRun ? 'warn' : 'down',
      detail: lastRun ? `dernière tâche planifiée il y a ${Math.round(workerAgeMin)} min` : 'aucune exécution enregistrée',
    },
    {
      label: 'file de tâches',
      state: !queue.value ? 'down' : queue.value.late > 50 ? 'warn' : 'ok',
      detail: queue.value ? `${queue.value.late} en retard · ${queue.value.failed} échecs 24 h` : queue.error,
    },
    {
      label: env.STORAGE_DRIVER === 's3' ? 's3 médias' : 'stockage',
      state: store.value ? 'ok' : 'down',
      detail: store.value ? `écriture ${store.ms} ms` : store.error,
    },
    { label: 'email', state: env.MAIL_DRIVER === 'smtp' && env.SMTP_URL ? 'ok' : 'warn', detail: env.MAIL_DRIVER === 'smtp' ? 'SMTP' : 'boîte d’envoi (démo)' },
    { label: 'ia', state: env.ANTHROPIC_API_KEY ? 'ok' : 'warn', detail: env.ANTHROPIC_API_KEY ? env.AI_MODEL : 'mode règles (sans clé API)' },
    { label: 'antivirus', state: process.env.CLAMAV_HOST ? 'ok' : 'warn', detail: process.env.CLAMAV_HOST ? 'ClamAV' : 'non configuré : images réencodées' },
    { label: 'recherche', state: pg.value ? 'ok' : 'down', detail: 'plein texte PostgreSQL' },
  ];
  return {
    source: 'direct',
    components,
    summary: `Environnement ${env.NODE_ENV === 'production' ? 'de production' : 'de développement'} · sondes directes${pg.value?.replica ? ' · base en lecture seule' : ''} · sauvegardes gérées par l’orchestrateur`,
    checkedAt: new Date(),
  };
}

export async function infraStatus(): Promise<InfraStatus> {
  if (cache && Date.now() - cache.at < 30_000) return cache.value;
  const value = (await fromKubernetes().catch(() => null)) ?? (await direct());
  cache = { at: Date.now(), value };
  return value;
}

export const STATE_BG: Record<ComponentState, string> = { ok: '#D6E8B4', warn: '#F8E3C0', down: '#F5DCD8' };
