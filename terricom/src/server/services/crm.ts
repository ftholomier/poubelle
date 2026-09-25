import { and, asc, desc, eq, inArray, isNull, ne, sql } from 'drizzle-orm';
import { db } from '../db';
import { dealActivities, dealContacts, dealDocuments, deals, dealTasks, users } from '../db/schema';
import { DEAL_STAGE_LABELS, DEAL_STAGES, PIPELINE_GROUPS, type DealStage } from '@/lib/constants';

/** Suivi commercial des collectivités (S5) : affaires, interlocuteurs, historique, prochaines actions. */

export type DealGroupKey = (typeof PIPELINE_GROUPS)[number]['key'];

export const STAGE_PROBABILITY: Record<DealStage, number> = {
  PROSPECT: 10,
  FIRST_CONTACT: 20,
  DEMO: 30,
  PROPOSAL: 50,
  NEGOTIATION: 70,
  SIGNED: 100,
  ONBOARDING: 100,
  ACTIVE: 100,
  LOST: 0,
};

/** Entrée d'historique consignée au passage de chaque étape. */
export const STAGE_ACTIVITY: Record<DealStage, [string, string]> = {
  PROSPECT: ['Premier contact', 'Nouveau prospect'],
  FIRST_CONTACT: ['Appel', 'Qualification du besoin'],
  DEMO: ['Démo', 'Démonstration de la plateforme aux élus'],
  PROPOSAL: ['Proposition', 'Envoi de la proposition commerciale'],
  NEGOTIATION: ['Négociation', 'Discussion sur le tarif et le périmètre'],
  SIGNED: ['Signature', 'Délibération votée, contrat signé'],
  ONBOARDING: ['Onboarding', 'Kick-off et import des données'],
  ACTIVE: ['Bilan', 'Portail lancé, client actif'],
  LOST: ['Perdu', 'Affaire perdue'],
};

/** Actions types proposées à l'entrée dans chaque groupe du pipeline. */
export const GROUP_ACTIONS: Record<DealGroupKey, [string, string][]> = {
  pro: [
    ['Qualifier le besoin (appel 20 min)', 'cette sem.'],
    ['Envoyer la plaquette terricom.fr', 'J+2'],
    ['Proposer une démo en ligne', 'J+7'],
  ],
  neg: [
    ['Envoyer la proposition chiffrée', 'J+1'],
    ['Préparer la note pour le conseil communautaire', 'J+3'],
    ['Répondre aux questions RGPD / hébergement', 'J+5'],
  ],
  onb: [
    ['Import SIRENE et contrôle des doublons', 'en cours'],
    ['Former les administrateurs communaux', 'J+4'],
    ['Planifier le lancement presse', 'J+20'],
  ],
  act: [
    ["Bilan d'usage trimestriel", 'trimestre'],
    ['Proposer le module Assistant IA', 'J+30'],
    ["Recueillir un témoignage d'élu", 'J+60'],
  ],
};

export const ACTIVITY_COLORS: Record<string, string> = {
  'Premier contact': '#9A9F95',
  Appel: '#3E6FB0',
  Email: '#3E6FB0',
  Démo: '#7A5BB5',
  RDV: '#7A5BB5',
  Proposition: '#C8892A',
  Négociation: '#D95C4E',
  Signature: '#1F6B52',
  Onboarding: '#3E6FB0',
  Bilan: '#1F6B52',
  Note: '#9A9F95',
  Perdu: '#D95C4E',
};

export function groupOf(stage: DealStage): DealGroupKey {
  return PIPELINE_GROUPS.find((g) => g.stages.includes(stage))?.key ?? 'pro';
}

export function nextStage(stage: DealStage): DealStage | null {
  const i = DEAL_STAGES.indexOf(stage);
  return i >= 0 && i < DEAL_STAGES.length - 1 ? DEAL_STAGES[i + 1] : null;
}

export async function groupCounts(): Promise<Record<DealGroupKey, number>> {
  const r = await db
    .select({ stage: deals.stage, n: sql<number>`count(*)::int` })
    .from(deals)
    .where(ne(deals.stage, 'LOST'))
    .groupBy(deals.stage);
  const out: Record<DealGroupKey, number> = { act: 0, onb: 0, neg: 0, pro: 0 };
  for (const x of r) out[groupOf(x.stage as DealStage)] += Number(x.n);
  return out;
}

export async function dealsInGroup(group: DealGroupKey) {
  const g = PIPELINE_GROUPS.find((x) => x.key === group)!;
  return db.select().from(deals).where(inArray(deals.stage, g.stages)).orderBy(desc(deals.probability), desc(deals.licenceCents), asc(deals.name));
}

export async function dealDetail(id: string) {
  const [deal] = await db.select().from(deals).where(eq(deals.id, id)).limit(1);
  if (!deal) return null;
  const [owner, contacts, activities, tasks, documents] = await Promise.all([
    deal.ownerId
      ? db
          .select({ id: users.id, firstName: users.firstName, lastName: users.lastName, email: users.email, avatarUrl: users.avatarUrl })
          .from(users)
          .where(eq(users.id, deal.ownerId))
          .limit(1)
          .then((r) => r[0] ?? null)
      : Promise.resolve(null),
    db.select().from(dealContacts).where(eq(dealContacts.dealId, id)).orderBy(asc(dealContacts.sortOrder), asc(dealContacts.name)),
    db.select().from(dealActivities).where(eq(dealActivities.dealId, id)).orderBy(desc(dealActivities.occurredAt)).limit(40),
    db.select().from(dealTasks).where(eq(dealTasks.dealId, id)).orderBy(asc(dealTasks.sortOrder), asc(dealTasks.createdAt)),
    db.select().from(dealDocuments).where(eq(dealDocuments.dealId, id)).orderBy(asc(dealDocuments.createdAt)),
  ]);
  return { deal, owner, contacts, activities, tasks, documents };
}

async function addGroupActions(dealId: string, group: DealGroupKey) {
  const existing = await db.select({ text: dealTasks.text }).from(dealTasks).where(eq(dealTasks.dealId, dealId));
  const have = new Set(existing.map((t) => t.text));
  const [{ max }] = await db
    .select({ max: sql<number>`coalesce(max(${dealTasks.sortOrder}), -1)::int` })
    .from(dealTasks)
    .where(eq(dealTasks.dealId, dealId));
  const fresh = GROUP_ACTIONS[group].filter(([t]) => !have.has(t));
  if (fresh.length) await db.insert(dealTasks).values(fresh.map(([text, dueText], k) => ({ dealId, text, dueText, sortOrder: Number(max) + 1 + k })));
}

/** Fait avancer l'affaire d'une étape : probabilité, historique et actions types de la nouvelle étape. */
export async function advanceDeal(id: string, userId: string): Promise<{ from: DealStage; to: DealStage; name: string } | null> {
  const [d] = await db.select().from(deals).where(eq(deals.id, id)).limit(1);
  if (!d || d.stage === 'LOST') return null;
  const to = nextStage(d.stage as DealStage);
  if (!to) return null;
  const now = new Date();
  await db
    .update(deals)
    .set({ stage: to, probability: Math.max(d.probability, STAGE_PROBABILITY[to]), lastInteractionAt: now, updatedAt: now })
    .where(eq(deals.id, id));
  const [kind, text] = STAGE_ACTIVITY[to];
  await db.insert(dealActivities).values({ dealId: id, kind, text, userId, occurredAt: now });
  if (groupOf(to) !== groupOf(d.stage as DealStage)) await addGroupActions(id, groupOf(to));
  return { from: d.stage as DealStage, to, name: d.name };
}

export async function markDealLost(id: string, reason: string, userId: string) {
  const now = new Date();
  await db.update(deals).set({ stage: 'LOST', probability: 0, lastInteractionAt: now, updatedAt: now, nextAction: null }).where(eq(deals.id, id));
  await db.insert(dealActivities).values({ dealId: id, kind: 'Perdu', text: reason || 'Affaire perdue', userId, occurredAt: now });
}

export async function reopenDeal(id: string, userId: string) {
  const now = new Date();
  await db
    .update(deals)
    .set({ stage: 'PROSPECT', probability: STAGE_PROBABILITY.PROSPECT, lastInteractionAt: now, updatedAt: now })
    .where(and(eq(deals.id, id), eq(deals.stage, 'LOST')));
  await db.insert(dealActivities).values({ dealId: id, kind: 'Note', text: 'Affaire rouverte', userId, occurredAt: now });
}

export async function toggleDealTask(taskId: string): Promise<{ dealId: string; done: boolean } | null> {
  const [t] = await db.select().from(dealTasks).where(eq(dealTasks.id, taskId)).limit(1);
  if (!t) return null;
  const done = !t.doneAt;
  await db
    .update(dealTasks)
    .set({ doneAt: done ? new Date() : null })
    .where(eq(dealTasks.id, taskId));
  await refreshNextAction(t.dealId);
  return { dealId: t.dealId, done };
}

/** « Prochaine action » affichée dans la liste : première tâche non faite. */
export async function refreshNextAction(dealId: string) {
  const [next] = await db
    .select()
    .from(dealTasks)
    .where(and(eq(dealTasks.dealId, dealId), isNull(dealTasks.doneAt)))
    .orderBy(asc(dealTasks.sortOrder), asc(dealTasks.createdAt))
    .limit(1);
  await db
    .update(deals)
    .set({ nextAction: next ? `${next.text}${next.dueText ? ` · ${next.dueText}` : ''}`.slice(0, 255) : null })
    .where(eq(deals.id, dealId));
}

export async function addDealTask(dealId: string, text: string, dueText: string | null) {
  const [{ max }] = await db
    .select({ max: sql<number>`coalesce(max(${dealTasks.sortOrder}), -1)::int` })
    .from(dealTasks)
    .where(eq(dealTasks.dealId, dealId));
  await db.insert(dealTasks).values({ dealId, text, dueText, sortOrder: Number(max) + 1 });
  await refreshNextAction(dealId);
}

export async function logDealActivity(dealId: string, kind: string, text: string, userId: string, occurredAt = new Date()) {
  await db.insert(dealActivities).values({ dealId, kind, text, userId, occurredAt });
  await db.update(deals).set({ lastInteractionAt: new Date(), updatedAt: new Date() }).where(eq(deals.id, dealId));
}

export function stageLabel(stage: DealStage): string {
  return DEAL_STAGE_LABELS[stage];
}
