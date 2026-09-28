'use server';

import { eq } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { z } from 'zod';
import type { ActionState } from '@/app/pro/[est]/actions';
import { DEAL_STAGE_LABELS, type DealStage } from '@/lib/constants';
import { fmtDayMonth, fromParisLocal } from '@/lib/format';
import { audit } from '@/server/audit';
import { requirePlatformStaff, type Actor } from '@/server/authz';
import { db } from '@/server/db';
import { dealContacts, dealDocuments, deals } from '@/server/db/schema';
import { MediaError, saveDocumentUpload } from '@/server/media';
import {
  addDealTask,
  advanceDeal,
  GROUP_ACTIONS,
  groupOf,
  logDealActivity,
  markDealLost,
  refreshNextAction,
  reopenDeal,
  STAGE_PROBABILITY,
  toggleDealTask,
} from '@/server/services/crm';

/** Le suivi commercial est ouvert aux super administrateurs et à l'équipe commerciale. */
async function salesActor(): Promise<Actor | null> {
  const actor = await requirePlatformStaff();
  if (!actor.isPlatformAdmin && !actor.roles.some((r) => r.role === 'PLATFORM_SALES')) return null;
  return actor;
}

function refresh() {
  revalidatePath('/console');
  revalidatePath('/console/crm');
}

const uuid = z.string().uuid();

/** Adresse de retour du panneau (modale de la vue d'ensemble ou page du suivi). */
function panelBase(form: FormData): '/console' | '/console/crm' {
  return form.get('base') === '/console/crm' ? '/console/crm' : '/console';
}

export async function advanceDealAction(form: FormData): Promise<void> {
  const actor = await salesActor();
  if (!actor) return;
  const dealId = uuid.parse(form.get('dealId'));
  const res = await advanceDeal(dealId, actor.user.id);
  if (res) {
    await refreshNextAction(dealId);
    await audit({
      actor: { user: actor.user },
      category: 'MODIFICATION',
      action: 'crm.stage',
      summary: `Suivi commercial : « ${res.name} » passe de ${DEAL_STAGE_LABELS[res.from]} à ${DEAL_STAGE_LABELS[res.to]}`,
      targetType: 'deal',
      targetId: dealId,
    });
    refresh();
    // L'affaire change de colonne : on la suit dans son nouvel onglet.
    if (groupOf(res.to) !== groupOf(res.from)) redirect(`${panelBase(form)}?crm=${groupOf(res.to)}&deal=${dealId}`);
  }
  refresh();
}

export async function toggleDealTaskAction(form: FormData): Promise<void> {
  const actor = await salesActor();
  if (!actor) return;
  await toggleDealTask(uuid.parse(form.get('taskId')));
  refresh();
}

export async function addDealTaskAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await salesActor();
  if (!actor) return { status: 'error', message: 'Réservé à l’équipe commerciale.' };
  const d = z
    .object({ dealId: uuid, text: z.string().trim().min(2, 'Décrivez l’action').max(255), dueText: z.string().trim().max(64).optional() })
    .safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: d.error.issues[0]?.message };
  await addDealTask(d.data.dealId, d.data.text, d.data.dueText || null);
  refresh();
  return { status: 'ok', message: 'Action ajoutée.' };
}

export async function logDealActivityAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await salesActor();
  if (!actor) return { status: 'error', message: 'Réservé à l’équipe commerciale.' };
  const d = z
    .object({ dealId: uuid, kind: z.enum(['Appel', 'Email', 'RDV', 'Démo', 'Note']), text: z.string().trim().min(2, 'Résumez l’échange').max(2000) })
    .safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: d.error.issues[0]?.message };
  await logDealActivity(d.data.dealId, d.data.kind, d.data.text, actor.user.id);
  refresh();
  return { status: 'ok', message: 'Échange consigné.' };
}

export async function saveDealNotesAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await salesActor();
  if (!actor) return { status: 'error', message: 'Réservé à l’équipe commerciale.' };
  const d = z.object({ dealId: uuid, notes: z.string().max(10_000) }).safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: 'Note trop longue.' };
  await db
    .update(deals)
    .set({ notes: d.data.notes.trim() || null, updatedAt: new Date() })
    .where(eq(deals.id, d.data.dealId));
  refresh();
  return { status: 'ok', message: 'Notes enregistrées.' };
}

export async function planMeetingAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await salesActor();
  if (!actor) return { status: 'error', message: 'Réservé à l’équipe commerciale.' };
  const d = z
    .object({
      dealId: uuid,
      date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Date invalide'),
      time: z.string().regex(/^\d{2}:\d{2}$/, 'Heure invalide'),
      subject: z.string().trim().min(2, 'Objet du rendez-vous requis').max(160),
      place: z.string().trim().max(160).optional(),
    })
    .safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: d.error.issues[0]?.message };
  const at = fromParisLocal(d.data.date, d.data.time);
  const when = `${fmtDayMonth(at)} ${d.data.time.replace(':', 'h')}`;
  await addDealTask(d.data.dealId, `RDV : ${d.data.subject}${d.data.place ? ` (${d.data.place})` : ''}`, when);
  await logDealActivity(d.data.dealId, 'RDV', `Rendez-vous planifié le ${when} : ${d.data.subject}`, actor.user.id);
  refresh();
  return { status: 'ok', message: `Rendez-vous planifié le ${when}.` };
}

export async function addDealContactAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await salesActor();
  if (!actor) return { status: 'error', message: 'Réservé à l’équipe commerciale.' };
  const d = z
    .object({
      dealId: uuid,
      name: z.string().trim().min(2, 'Nom requis').max(255),
      role: z.string().trim().max(255).optional(),
      tag: z.enum(['Décideur', 'Influence', 'Utilisateur', 'Technique']),
      email: z.string().trim().email('Email invalide').optional().or(z.literal('')),
      phone: z.string().trim().max(32).optional(),
    })
    .safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: d.error.issues[0]?.message };
  await db.insert(dealContacts).values({
    dealId: d.data.dealId,
    name: d.data.name,
    role: d.data.role || null,
    tag: d.data.tag,
    email: d.data.email || null,
    phone: d.data.phone || null,
    sortOrder: 10,
  });
  refresh();
  return { status: 'ok', message: 'Interlocuteur ajouté.' };
}

export async function uploadDealDocumentAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await salesActor();
  if (!actor) return { status: 'error', message: 'Réservé à l’équipe commerciale.' };
  const dealId = uuid.safeParse(form.get('dealId'));
  const file = form.get('file');
  if (!dealId.success || !(file instanceof File) || file.size === 0) return { status: 'error', message: 'Choisissez un fichier PDF.' };
  try {
    const m = await saveDocumentUpload(file, { ownerType: 'DEAL_DOCUMENT', ownerId: dealId.data, uploadedById: actor.user.id });
    const name = (file.name || 'document.pdf').replace(/[^\p{L}\p{N} ._()-]/gu, '').slice(0, 200) || 'document.pdf';
    await db.insert(dealDocuments).values({ dealId: dealId.data, name, url: `/api/documents/${m.id}` });
  } catch (e) {
    return { status: 'error', message: e instanceof MediaError ? e.message : 'Envoi impossible.' };
  }
  refresh();
  return { status: 'ok', message: 'Document ajouté.' };
}

export async function markDealLostAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await salesActor();
  if (!actor) return { status: 'error', message: 'Réservé à l’équipe commerciale.' };
  const d = z.object({ dealId: uuid, reason: z.string().trim().min(3, 'Indiquez la raison').max(300) }).safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: d.error.issues[0]?.message };
  const [deal] = await db.select().from(deals).where(eq(deals.id, d.data.dealId)).limit(1);
  if (!deal) return { status: 'error', message: 'Affaire introuvable.' };
  await markDealLost(deal.id, `Affaire perdue : ${d.data.reason}`, actor.user.id);
  await audit({
    actor: { user: actor.user },
    category: 'MODIFICATION',
    action: 'crm.lost',
    summary: `Suivi commercial : « ${deal.name} » classée perdue (${d.data.reason})`,
    targetType: 'deal',
    targetId: deal.id,
  });
  refresh();
  redirect(`${panelBase(form)}?crm=lost&deal=${deal.id}`);
}

export async function reopenDealAction(form: FormData): Promise<void> {
  const actor = await salesActor();
  if (!actor) return;
  const id = uuid.parse(form.get('dealId'));
  await reopenDeal(id, actor.user.id);
  refresh();
  redirect(`${panelBase(form)}?crm=pro&deal=${id}`);
}

export async function createDealAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await salesActor();
  if (!actor) return { status: 'error', message: 'Réservé à l’équipe commerciale.' };
  const d = z
    .object({
      name: z.string().trim().min(2, 'Nom de la collectivité requis').max(255),
      kind: z.enum(['CC', 'CA', 'CU', 'METROPOLE', 'COMMUNE', 'PETR', 'OFFICE', 'AUTRE']),
      communesCount: z.coerce.number().int().min(1).max(500),
      population: z.coerce
        .number()
        .int()
        .min(0)
        .max(10_000_000)
        .optional()
        .or(z.literal('').transform(() => undefined)),
      stage: z.enum(['PROSPECT', 'FIRST_CONTACT', 'DEMO', 'PROPOSAL', 'NEGOTIATION']),
      licence: z.coerce.number().min(0).max(1_000_000),
      source: z.string().trim().max(64).optional(),
      contactName: z.string().trim().max(255).optional(),
      contactRole: z.string().trim().max(255).optional(),
      contactEmail: z.string().trim().email('Email invalide').optional().or(z.literal('')),
      contactPhone: z.string().trim().max(32).optional(),
      notes: z.string().trim().max(5000).optional(),
    })
    .safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: d.error.issues[0]?.message };
  const v = d.data;
  const stage = v.stage as DealStage;
  const now = new Date();
  const [deal] = await db
    .insert(deals)
    .values({
      name: v.name,
      kind: v.kind,
      communesCount: v.communesCount,
      population: v.population ?? null,
      stage,
      probability: STAGE_PROBABILITY[stage],
      licenceCents: Math.round(v.licence * 100),
      setupCents: v.communesCount > 30 ? 800_000 : v.communesCount > 10 ? 500_000 : 200_000,
      ownerId: actor.user.id,
      source: v.source || null,
      notes: v.notes || null,
      contactEmail: v.contactEmail || null,
      contactPhone: v.contactPhone || null,
      lastInteractionAt: now,
    })
    .returning();
  if (v.contactName)
    await db.insert(dealContacts).values({
      dealId: deal.id,
      name: v.contactName,
      role: v.contactRole || null,
      tag: 'Décideur',
      email: v.contactEmail || null,
      phone: v.contactPhone || null,
    });
  await logDealActivity(deal.id, 'Premier contact', v.source ? `Origine : ${v.source}` : 'Nouvelle affaire', actor.user.id, now);
  const group = groupOf(stage);
  for (const [text, due] of GROUP_ACTIONS[group]) await addDealTask(deal.id, text, due);
  await audit({
    actor: { user: actor.user },
    category: 'MODIFICATION',
    action: 'crm.created',
    summary: `Suivi commercial : nouvelle affaire « ${v.name} »`,
    targetType: 'deal',
    targetId: deal.id,
  });
  refresh();
  redirect(`/console?crm=${group}&deal=${deal.id}`);
}
