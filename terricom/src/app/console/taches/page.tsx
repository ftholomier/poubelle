import { sql } from 'drizzle-orm';
import type { Metadata } from 'next';
import { deleteJobAction, retryAllFailedAction, retryJobAction, runScheduleNowAction, toggleScheduleAction } from './actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { fmtInt, fmtStamp, relativeTime } from '@/lib/format';
import { requirePlatformStaff } from '@/server/authz';
import { db } from '@/server/db';
import { env } from '@/server/env';
import { QUEUE_LABELS, SCHEDULES } from '@/server/jobs/schedules';
import type { QueueName } from '@/server/queue';

export const metadata: Metadata = { title: 'Tâches de fond' };

type QueueRow = { queue: string; queued: number; running: number; done24: number; failed: number; late: number; avg_ms: number | null };

export default async function JobsPage() {
  const actor = await requirePlatformStaff();
  const canEdit = actor.isPlatformAdmin;
  const [queues, failed, schedules, workers] = await Promise.all([
    db.execute<QueueRow>(sql`
      select queue,
        count(*) filter (where status = 'QUEUED')::int as queued,
        count(*) filter (where status = 'RUNNING')::int as running,
        count(*) filter (where status = 'DONE' and finished_at > now() - interval '24 hours')::int as done24,
        count(*) filter (where status = 'FAILED')::int as failed,
        count(*) filter (where status = 'QUEUED' and run_at < now() - interval '10 minutes')::int as late,
        round(avg(extract(epoch from (finished_at - locked_at)) * 1000) filter (where status = 'DONE' and finished_at > now() - interval '24 hours'))::int as avg_ms
      from queue_jobs group by queue order by queue`),
    db.execute<{ id: number; queue: string; attempts: number; last_error: string | null; finished_at: Date | null; payload: Record<string, unknown> }>(sql`
      select id, queue, attempts, last_error, finished_at, payload from queue_jobs where status = 'FAILED' order by finished_at desc nulls last limit 30`),
    db.execute<{ name: string; last_run_at: Date | null; enabled: boolean }>(sql`select name, last_run_at, enabled from job_schedules`),
    db.execute<{ locked_by: string; n: number; last: Date }>(sql`
      select locked_by, count(*)::int as n, max(locked_at) as last from queue_jobs where locked_at > now() - interval '1 hour' and locked_by is not null group by locked_by order by last desc limit 10`),
  ]);
  const byName = new Map(schedules.rows.map((s) => [s.name, s]));
  const defs = SCHEDULES.filter((s) => !s.demoOnly || env.DEMO_MODE);
  const totals = queues.rows.reduce(
    (a, q) => ({ queued: a.queued + q.queued, running: a.running + q.running, done: a.done + q.done24, failed: a.failed + q.failed }),
    { queued: 0, running: 0, done: 0, failed: 0 },
  );
  const tiles = [
    { l: 'En file', v: fmtInt(totals.queued) },
    { l: 'En cours', v: fmtInt(totals.running) },
    { l: 'Terminées (24 h)', v: fmtInt(totals.done) },
    { l: 'En échec', v: fmtInt(totals.failed), warn: totals.failed > 0 },
    { l: 'Workers actifs (1 h)', v: fmtInt(workers.rows.length), warn: workers.rows.length === 0 },
  ];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(160px,1fr))', gap: 12 }}>
        {tiles.map((x) => (
          <div key={x.l} className="console-card" style={{ borderRadius: 18, padding: 18 }}>
            <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--muted)' }}>{x.l}</div>
            <div className="display" style={{ fontSize: 30, letterSpacing: '-0.03em', color: x.warn ? 'var(--brick)' : undefined }}>
              {x.v}
            </div>
          </div>
        ))}
      </div>

      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <section className="console-card" style={{ borderRadius: 20, overflow: 'hidden' }}>
          <div style={{ padding: '14px 18px' }}>
            <b>Files de tâches</b>
          </div>
          <table className="console-table" style={{ minWidth: 0 }}>
            <thead>
              <tr>
                <th>File</th>
                <th style={{ textAlign: 'right' }}>En file</th>
                <th style={{ textAlign: 'right' }}>24 h</th>
                <th style={{ textAlign: 'right' }}>Échecs</th>
                <th style={{ textAlign: 'right' }}>Durée moy.</th>
              </tr>
            </thead>
            <tbody>
              {queues.rows.map((q) => (
                <tr key={q.queue}>
                  <td>
                    <b>{QUEUE_LABELS[q.queue as QueueName] ?? q.queue}</b>
                    <div style={{ fontSize: 11, color: 'var(--muted)', fontFamily: 'ui-monospace,monospace' }}>{q.queue}</div>
                  </td>
                  <td style={{ textAlign: 'right', color: q.late ? 'var(--brick)' : undefined }}>
                    {fmtInt(q.queued)}
                    {q.late ? <div style={{ fontSize: 11 }}>{q.late} en retard</div> : null}
                  </td>
                  <td style={{ textAlign: 'right' }}>{fmtInt(q.done24)}</td>
                  <td style={{ textAlign: 'right', color: q.failed ? 'var(--brick)' : undefined }}>{fmtInt(q.failed)}</td>
                  <td style={{ textAlign: 'right' }}>{q.avg_ms !== null ? `${fmtInt(q.avg_ms)} ms` : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {queues.rows.length === 0 ? (
            <p style={{ padding: '4px 18px 16px', margin: 0, color: 'var(--muted)' }}>Aucune tâche enregistrée : le worker n’a pas encore tourné.</p>
          ) : null}
          <div style={{ padding: '10px 18px 16px', fontSize: 12, color: 'var(--muted)' }}>
            {workers.rows.length
              ? `Workers : ${workers.rows.map((w) => `${w.locked_by} (${w.n} tâches, ${relativeTime(w.last)})`).join(' · ')}`
              : 'Aucun worker n’a pris de tâche depuis une heure : vérifiez le déploiement « worker ».'}
          </div>
        </section>

        <section className="console-card" style={{ borderRadius: 20, overflow: 'hidden' }}>
          <div style={{ padding: '14px 18px' }}>
            <b>Tâches planifiées</b>
          </div>
          <table className="console-table" style={{ minWidth: 0 }}>
            <thead>
              <tr>
                <th>Tâche</th>
                <th>Fréquence</th>
                <th>Dernière exécution</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {defs.map((d) => {
                const row = byName.get(d.name);
                const enabled = row?.enabled ?? true;
                return (
                  <tr key={d.name}>
                    <td>
                      <b>{d.label}</b>
                      <div style={{ fontSize: 11, color: 'var(--muted)', fontFamily: 'ui-monospace,monospace' }}>{d.name}</div>
                    </td>
                    <td style={{ whiteSpace: 'nowrap' }}>
                      {d.everyMinutes >= 1440 ? 'quotidienne' : d.everyMinutes >= 60 ? `toutes les ${d.everyMinutes / 60} h` : `${d.everyMinutes} min`}
                    </td>
                    <td style={{ fontSize: 12, color: 'var(--muted)', whiteSpace: 'nowrap' }}>{row?.last_run_at ? relativeTime(row.last_run_at) : 'jamais'}</td>
                    <td>
                      {canEdit ? (
                        <div style={{ display: 'flex', gap: 6, alignItems: 'center', justifyContent: 'flex-end' }}>
                          <ActionForm action={runScheduleNowAction}>
                            <input type="hidden" name="name" value={d.name} />
                            <SubmitButton className="btn btn-outline btn-xs">Lancer</SubmitButton>
                          </ActionForm>
                          <form action={toggleScheduleAction}>
                            <input type="hidden" name="name" value={d.name} />
                            <input type="hidden" name="enabled" value={enabled ? '0' : '1'} />
                            <button
                              type="submit"
                              role="switch"
                              aria-checked={enabled}
                              aria-label={`${enabled ? 'Suspendre' : 'Activer'} : ${d.label}`}
                              className="switch"
                            />
                          </form>
                        </div>
                      ) : null}
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </section>
      </div>

      <section className="console-card" style={{ borderRadius: 20, overflow: 'hidden' }}>
        <div style={{ display: 'flex', padding: '14px 18px', alignItems: 'center', gap: 10 }}>
          <b>Tâches en échec</b>
          {canEdit && failed.rows.length ? (
            <ActionForm action={retryAllFailedAction} style={{ marginLeft: 'auto' }}>
              <SubmitButton className="btn btn-dark btn-xs">Tout relancer</SubmitButton>
            </ActionForm>
          ) : null}
        </div>
        {failed.rows.length === 0 ? <p style={{ padding: '0 18px 16px', margin: 0, color: 'var(--muted)' }}>Aucune tâche en échec.</p> : null}
        {failed.rows.map((j) => (
          <div
            key={j.id}
            style={{
              display: 'grid',
              gridTemplateColumns: '90px minmax(0,1fr) auto',
              gap: 12,
              padding: '12px 18px',
              borderTop: '1px solid var(--console-line-2)',
              fontSize: 13,
            }}
          >
            <span style={{ fontFamily: 'ui-monospace,monospace', color: 'var(--muted)' }}>n°{j.id}</span>
            <div style={{ minWidth: 0 }}>
              <b>{QUEUE_LABELS[j.queue as QueueName] ?? j.queue}</b> · {j.attempts} tentative{j.attempts > 1 ? 's' : ''} ·{' '}
              {j.finished_at ? fmtStamp(j.finished_at) : ''}
              <pre style={{ margin: '6px 0 0', whiteSpace: 'pre-wrap', fontSize: 12, color: 'var(--danger-fg)', maxHeight: 90, overflow: 'auto' }}>
                {(j.last_error ?? '').split('\n')[0]}
              </pre>
            </div>
            {canEdit ? (
              <div style={{ display: 'flex', gap: 6, alignItems: 'flex-start' }}>
                <ActionForm action={retryJobAction}>
                  <input type="hidden" name="id" value={j.id} />
                  <SubmitButton className="btn btn-outline btn-xs">Relancer</SubmitButton>
                </ActionForm>
                <ActionForm action={deleteJobAction}>
                  <input type="hidden" name="id" value={j.id} />
                  <SubmitButton className="btn btn-ghost btn-xs">Supprimer</SubmitButton>
                </ActionForm>
              </div>
            ) : null}
          </div>
        ))}
      </section>
    </div>
  );
}
