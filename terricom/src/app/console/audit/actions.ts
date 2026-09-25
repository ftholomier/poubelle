'use server';

import { eq } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { z } from 'zod';
import type { ActionState } from '@/app/pro/[est]/actions';
import { fmtInt } from '@/lib/format';
import { audit, verifyAuditChain } from '@/server/audit';
import { requirePlatformStaff, type Actor } from '@/server/authz';
import { db } from '@/server/db';
import { privacyRequests } from '@/server/db/schema';
import { erasePersonalData } from '@/server/services/privacy';

async function rgpdActor(): Promise<Actor | null> {
  const actor = await requirePlatformStaff();
  if (!actor.isPlatformAdmin && !actor.roles.some((r) => r.role === 'PLATFORM_SUPPORT')) return null;
  return actor;
}

export async function verifyAuditChainAction(): Promise<ActionState> {
  const actor = await requirePlatformStaff();
  const res = await verifyAuditChain();
  await audit({
    actor: { user: actor.user },
    category: 'SECURITE',
    action: res.ok ? 'audit.verified' : 'audit.broken',
    summary: res.ok ? `Intégrité du journal vérifiée (${res.checked} entrées)` : `Altération du journal détectée à l’entrée n°${res.brokenAt}`,
  });
  revalidatePath('/console/audit');
  return res.ok
    ? { status: 'ok', message: `Chaîne intègre : ${fmtInt(res.checked)} entrées vérifiées.` }
    : { status: 'error', message: `Altération détectée à l’entrée n°${res.brokenAt} (après ${fmtInt(res.checked)} entrées valides).` };
}

export async function registerPrivacyRequestAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await rgpdActor();
  if (!actor) return { status: 'error', message: 'Réservé au support et aux super administrateurs.' };
  const d = z
    .object({
      email: z.string().trim().toLowerCase().email('Email invalide'),
      kind: z.enum(['EXPORT', 'DELETE', 'RECTIFY']),
      territoryId: z.string().uuid().optional().or(z.literal('')),
      note: z.string().trim().max(500).optional(),
    })
    .safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: d.error.issues[0]?.message };
  const [row] = await db
    .insert(privacyRequests)
    .values({ email: d.data.email, kind: d.data.kind, territoryId: d.data.territoryId || null, note: d.data.note || null })
    .returning();
  await audit({
    actor: { user: actor.user },
    category: 'RGPD',
    action: 'privacy.registered',
    summary: `Demande RGPD n°${row.number} enregistrée (${d.data.kind === 'EXPORT' ? 'accès' : d.data.kind === 'DELETE' ? 'effacement' : 'rectification'})`,
    territoryId: row.territoryId,
    targetType: 'privacy_request',
    targetId: row.id,
  });
  revalidatePath('/console/audit');
  return { status: 'ok', message: `Demande n°${row.number} enregistrée : réponse due sous un mois.` };
}

export async function processPrivacyRequestAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await rgpdActor();
  if (!actor) return { status: 'error', message: 'Réservé au support et aux super administrateurs.' };
  const d = z
    .object({ id: z.string().uuid(), decision: z.enum(['DONE', 'REJECTED', 'ERASE']), note: z.string().trim().max(500).optional() })
    .safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: 'Demande invalide.' };
  const [req] = await db.select().from(privacyRequests).where(eq(privacyRequests.id, d.data.id)).limit(1);
  if (!req || req.status !== 'OPEN') return { status: 'error', message: 'Demande introuvable ou déjà traitée.' };
  let note = d.data.note || null;
  if (d.data.decision === 'ERASE') {
    if (req.kind !== 'DELETE') return { status: 'error', message: 'L’effacement ne concerne que les demandes de suppression.' };
    const r = await erasePersonalData(req.email);
    note = `Effacement : ${r.subscribers} abonnement(s), ${r.contacts} contact(s), ${r.messages} message(s) anonymisé(s), ${r.applications} candidature(s), ${r.appointments} rendez-vous anonymisé(s)${r.hasAccount ? ' — un compte utilisateur existe : suppression par son titulaire ou le support' : ''}.`;
  }
  await db
    .update(privacyRequests)
    .set({ status: d.data.decision === 'REJECTED' ? 'REJECTED' : 'DONE', note, handledById: actor.user.id, completedAt: new Date() })
    .where(eq(privacyRequests.id, req.id));
  await audit({
    actor: { user: actor.user },
    category: 'RGPD',
    action: d.data.decision === 'REJECTED' ? 'privacy.rejected' : 'privacy.done',
    summary: `Demande RGPD n°${req.number} ${d.data.decision === 'REJECTED' ? 'refusée' : 'traitée'}${d.data.decision === 'ERASE' ? ' (données effacées)' : ''}`,
    territoryId: req.territoryId,
    targetType: 'privacy_request',
    targetId: req.id,
  });
  revalidatePath('/console/audit');
  return { status: 'ok', message: note ?? 'Demande close.' };
}
