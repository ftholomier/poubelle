'use server';

import { eq, sql } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { z } from 'zod';
import type { ActionState } from '@/app/pro/[est]/actions';
import { audit } from '@/server/audit';
import { requirePlatformStaff } from '@/server/authz';
import { db } from '@/server/db';
import { jobSchedules, queueJobs } from '@/server/db/schema';
import { SCHEDULES } from '@/server/jobs/schedules';
import { enqueue } from '@/server/queue';

async function adminActor() {
  const actor = await requirePlatformStaff();
  return actor.isPlatformAdmin ? actor : null;
}

export async function retryJobAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await adminActor();
  if (!actor) return { status: 'error', message: 'Réservé aux super administrateurs.' };
  const id = z.coerce.number().int().positive().safeParse(form.get('id'));
  if (!id.success) return { status: 'error', message: 'Tâche invalide.' };
  const res = await db.execute(
    sql`update queue_jobs set status = 'QUEUED', attempts = 0, run_at = now(), last_error = null, finished_at = null where id = ${id.data} and status = 'FAILED'`,
  );
  revalidatePath('/console/taches');
  return res.rowCount ? { status: 'ok', message: `Tâche n°${id.data} relancée.` } : { status: 'error', message: 'Tâche introuvable ou déjà relancée.' };
}

export async function retryAllFailedAction(): Promise<ActionState> {
  const actor = await adminActor();
  if (!actor) return { status: 'error', message: 'Réservé aux super administrateurs.' };
  const res = await db.execute(
    sql`update queue_jobs set status = 'QUEUED', attempts = 0, run_at = now(), last_error = null, finished_at = null where status = 'FAILED'`,
  );
  await audit({ actor: { user: actor.user }, category: 'CONFIGURATION', action: 'jobs.retry_all', summary: `${res.rowCount ?? 0} tâches en échec relancées` });
  revalidatePath('/console/taches');
  return { status: 'ok', message: `${res.rowCount ?? 0} tâche(s) relancée(s).` };
}

export async function deleteJobAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await adminActor();
  if (!actor) return { status: 'error', message: 'Réservé aux super administrateurs.' };
  const id = z.coerce.number().int().positive().safeParse(form.get('id'));
  if (!id.success) return { status: 'error', message: 'Tâche invalide.' };
  await db.delete(queueJobs).where(eq(queueJobs.id, id.data));
  revalidatePath('/console/taches');
  return { status: 'ok', message: 'Tâche supprimée.' };
}

export async function toggleScheduleAction(form: FormData): Promise<void> {
  const actor = await adminActor();
  if (!actor) return;
  const name = z.string().parse(form.get('name'));
  const def = SCHEDULES.find((s) => s.name === name);
  if (!def) return;
  const enabled = form.get('enabled') === '1';
  await db.insert(jobSchedules).values({ name, everyMinutes: def.everyMinutes, enabled }).onConflictDoUpdate({ target: jobSchedules.name, set: { enabled } });
  await audit({
    actor: { user: actor.user },
    category: 'CONFIGURATION',
    action: enabled ? 'schedule.enabled' : 'schedule.disabled',
    summary: `Tâche planifiée « ${def.label} » ${enabled ? 'activée' : 'suspendue'}`,
  });
  revalidatePath('/console/taches');
}

export async function runScheduleNowAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await adminActor();
  if (!actor) return { status: 'error', message: 'Réservé aux super administrateurs.' };
  const def = SCHEDULES.find((s) => s.name === form.get('name'));
  if (!def) return { status: 'error', message: 'Tâche inconnue.' };
  await enqueue(def.queue, { manual: true, by: actor.user.id });
  await audit({ actor: { user: actor.user }, category: 'CONFIGURATION', action: 'schedule.run', summary: `Exécution manuelle : ${def.label}` });
  revalidatePath('/console/taches');
  return { status: 'ok', message: `« ${def.label} » ajoutée à la file.` };
}
