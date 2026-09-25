'use server';

import { and, eq, inArray, sql } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { z } from 'zod';
import { audit } from '@/server/audit';
import { rateLimit } from '@/server/auth/rate-limit';
import { invalidate } from '@/server/cache';
import { shortCode } from '@/server/crypto';
import { db } from '@/server/db';
import { campaignParticipants, campaigns, categories, companies, companyMembers, establishments, messages, type ImportMapping } from '@/server/db/schema';
import { isValidSiret } from '@/server/integrations/public-data';
import { sendEmail } from '@/server/mail/send';
import { claimInvitationTemplate } from '@/server/mail/templates';
import { estScope, loadBoContext, requireBoAdmin, type BoContext } from '@/server/services/backoffice';
import { refreshCompleteness, refreshSearchKeywords, recordRevision } from '@/server/services/establishments';
import { commitBatch, createCsvBatch, createSireneBatch, IMPORT_FIELDS, MAX_IMPORT_BYTES, remapBatch } from '@/server/services/imports';
import { appUrl } from '@/server/urls';
import { slugify } from '@/lib/slug';

export type BoState = { status: 'idle' | 'ok' | 'error'; message?: string };

const uuid = z.string().uuid();

function actorOf(ctx: BoContext) {
  return { user: ctx.actor.user };
}

function refreshPortal(ctx: BoContext) {
  invalidate(`cards:${ctx.territory.id}`);
  invalidate(`pros:${ctx.territory.id}`);
  invalidate(`communeCounts:${ctx.territory.id}`);
  revalidatePath('/collectivite', 'layout');
}

/** Identifiants reçus du navigateur, restreints au périmètre de l'agent. */
async function scopedIds(ctx: BoContext, raw: unknown): Promise<string[]> {
  const ids = z.array(uuid).max(500).safeParse(JSON.parse(String(raw ?? '[]')));
  if (!ids.success || !ids.data.length) return [];
  const rows = await db.select({ id: establishments.id }).from(establishments).where(and(estScope(ctx), inArray(establishments.id, ids.data)));
  return rows.map((r) => r.id);
}

// ─── Import ────────────────────────────────────────────────────────────────

export async function uploadImportAction(_prev: BoState, form: FormData): Promise<BoState> {
  const ctx = await loadBoContext();
  requireBoAdmin(ctx);
  const file = form.get('file');
  if (!(file instanceof File) || file.size === 0) return { status: 'error', message: 'Choisissez un fichier CSV.' };
  if (file.size > MAX_IMPORT_BYTES) return { status: 'error', message: 'Fichier trop lourd (8 Mo maximum).' };
  if (!/\.(csv|txt)$/i.test(file.name)) return { status: 'error', message: 'Format attendu : CSV (séparateur virgule ou point-virgule).' };
  let batchId: string;
  try {
    batchId = await createCsvBatch(ctx.territory.id, ctx.actor.user.id, file.name, Buffer.from(await file.arrayBuffer()));
  } catch (err) {
    return { status: 'error', message: err instanceof Error ? err.message : 'Fichier illisible.' };
  }
  redirect(`/collectivite/entreprises?import=1&lot=${batchId}`);
}

export async function startSireneImportAction(): Promise<void> {
  const ctx = await loadBoContext();
  requireBoAdmin(ctx);
  if (!(await rateLimit(`sirene-import:${ctx.territory.id}`, 3, 3600)).ok) redirect('/collectivite/entreprises?import=1');
  const id = await createSireneBatch(ctx.territory.id, ctx.actor.user.id);
  await audit({ actor: actorOf(ctx), category: 'IMPORT', action: 'import.sirene_started', summary: 'Import depuis la base SIRENE lancé', territoryId: ctx.territory.id, targetType: 'import', targetId: id });
  redirect(`/collectivite/entreprises?import=1&lot=${id}`);
}

export async function remapImportAction(_prev: BoState, form: FormData): Promise<BoState> {
  const ctx = await loadBoContext();
  requireBoAdmin(ctx);
  const batchId = uuid.parse(form.get('batchId'));
  const mapping: ImportMapping = {};
  for (const f of IMPORT_FIELDS) {
    const v = String(form.get(`map_${f.key}`) ?? '');
    if (v) mapping[f.key] = v;
  }
  if (!mapping.name) return { status: 'error', message: 'Indiquez au moins la colonne du nom.' };
  const cat = String(form.get('defaultCategoryId') ?? '');
  try {
    await remapBatch(batchId, ctx.territory.id, mapping, cat && uuid.safeParse(cat).success ? cat : null);
  } catch (err) {
    return { status: 'error', message: err instanceof Error ? err.message : 'Analyse impossible.' };
  }
  revalidatePath('/collectivite/entreprises');
  return { status: 'ok', message: 'Correspondance mise à jour.' };
}

export async function commitImportAction(_prev: BoState, form: FormData): Promise<BoState> {
  const ctx = await loadBoContext();
  requireBoAdmin(ctx);
  const batchId = uuid.parse(form.get('batchId'));
  try {
    await commitBatch(batchId, ctx.territory.id, actorOf(ctx), { invite: form.get('invite') === 'on' });
  } catch (err) {
    return { status: 'error', message: err instanceof Error ? err.message : 'Import impossible.' };
  }
  refreshPortal(ctx);
  redirect(`/collectivite/entreprises?import=1&lot=${batchId}`);
}

// ─── Actions groupées ──────────────────────────────────────────────────────

export async function bulkInviteAction(_prev: BoState, form: FormData): Promise<BoState> {
  const ctx = await loadBoContext();
  const ids = await scopedIds(ctx, form.get('ids'));
  if (!ids.length) return { status: 'error', message: 'Aucune fiche sélectionnée.' };
  if (!(await rateLimit(`bulk-invite:${ctx.actor.user.id}`, 20, 3600)).ok) return { status: 'error', message: 'Trop d’envois : réessayez dans une heure.' };
  const rows = await db
    .select({ id: establishments.id, name: establishments.name, email: establishments.email, managed: sql<boolean>`exists (select 1 from company_members m where m.company_id = "establishments"."company_id")` })
    .from(establishments)
    .where(inArray(establishments.id, ids));
  let sent = 0;
  for (const r of rows) {
    if (r.managed || !r.email) continue;
    await sendEmail({ ...claimInvitationTemplate({ to: r.email, establishmentName: r.name, territory: ctx.territory, url: appUrl(`/pro/revendiquer/${r.id}`) }), territoryId: ctx.territory.id });
    sent++;
  }
  const letters = rows.filter((r) => !r.managed && !r.email).length;
  await audit({ actor: actorOf(ctx), category: 'ENVOI', action: 'claim.invitations', summary: `${sent} invitation${sent > 1 ? 's' : ''} à revendiquer envoyée${sent > 1 ? 's' : ''}`, territoryId: ctx.territory.id });
  return {
    status: 'ok',
    message: `${sent} invitation${sent > 1 ? 's' : ''} envoyée${sent > 1 ? 's' : ''} par email${letters ? ` · ${letters} fiche${letters > 1 ? 's' : ''} sans email : imprimez les courriers` : ''}.`,
  };
}

export async function bulkCampaignAction(_prev: BoState, form: FormData): Promise<BoState> {
  const ctx = await loadBoContext();
  const ids = await scopedIds(ctx, form.get('ids'));
  const campaignId = uuid.safeParse(form.get('campaignId'));
  if (!ids.length || !campaignId.success) return { status: 'error', message: 'Choisissez une campagne.' };
  const [c] = await db.select().from(campaigns).where(and(eq(campaigns.id, campaignId.data), eq(campaigns.territoryId, ctx.territory.id))).limit(1);
  if (!c) return { status: 'error', message: 'Campagne introuvable.' };
  const res = await db
    .insert(campaignParticipants)
    .values(ids.map((id) => ({ campaignId: c.id, establishmentId: id, status: 'INVITED' as const, invitedAt: new Date() })))
    .onConflictDoNothing()
    .returning({ id: campaignParticipants.establishmentId });
  await audit({ actor: actorOf(ctx), category: 'MODIFICATION', action: 'campaign.invite', summary: `${res.length} établissement(s) invité(s) à « ${c.name} »`, territoryId: ctx.territory.id, targetType: 'campaign', targetId: c.id });
  revalidatePath('/collectivite/campagnes');
  return { status: 'ok', message: `${res.length} établissement${res.length > 1 ? 's' : ''} invité${res.length > 1 ? 's' : ''} à « ${c.name} ».` };
}

// ─── Fiche : création et statut ────────────────────────────────────────────

export async function createEstablishmentAction(_prev: BoState, form: FormData): Promise<BoState> {
  const ctx = await loadBoContext();
  const parsed = z
    .object({
      name: z.string().trim().min(2, 'Nom requis').max(160),
      siret: z.string().trim().optional(),
      categoryId: uuid,
      communeId: uuid,
      street: z.string().trim().max(255).optional(),
      phone: z.string().trim().max(32).optional(),
      email: z.union([z.literal(''), z.string().trim().email('Email invalide')]).optional(),
      website: z.union([z.literal(''), z.string().trim().url('Adresse du site invalide')]).optional(),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  const commune = ctx.communes.find((c) => c.id === d.communeId);
  if (!commune) return { status: 'error', message: 'Commune hors de votre périmètre.' };
  const siret = d.siret?.replace(/\s/g, '') || null;
  if (siret && !isValidSiret(siret)) return { status: 'error', message: 'SIRET invalide.' };
  if (siret) {
    const [dup] = await db.select({ id: establishments.id }).from(establishments).where(eq(establishments.siret, siret)).limit(1);
    if (dup) return { status: 'error', message: 'Une fiche existe déjà avec ce SIRET.' };
  }
  const [cat] = await db.select().from(categories).where(eq(categories.id, d.categoryId)).limit(1);
  if (!cat) return { status: 'error', message: 'Catégorie inconnue.' };
  const taken = new Set((await db.select({ s: establishments.slug }).from(establishments).where(eq(establishments.communeId, commune.id))).map((r) => r.s));
  const base = slugify(d.name) || 'etablissement';
  let slug = base;
  for (let i = 2; taken.has(slug); i++) slug = `${base}-${i}`;
  const siren = siret?.slice(0, 9) ?? null;
  const [existingCo] = siren ? await db.select({ id: companies.id }).from(companies).where(eq(companies.siren, siren)).limit(1) : [];
  const companyId =
    existingCo?.id ??
    (await db.insert(companies).values({ siren, legalName: d.name.toUpperCase(), tradeName: d.name, nafCode: cat.nafCodes[0] ?? null }).returning({ id: companies.id }))[0].id;
  const [est] = await db
    .insert(establishments)
    .values({
      companyId,
      communeId: commune.id,
      territoryId: ctx.territory.id,
      categoryId: cat.id,
      slug,
      name: d.name,
      siret,
      status: 'PRECREATED',
      origin: 'COLLECTIVITE',
      activityLabel: cat.name,
      street: d.street || null,
      postalCode: commune.postalCodes[0] ?? null,
      lat: commune.lat,
      lng: commune.lng,
      phone: d.phone || null,
      email: d.email || null,
      website: d.website || null,
      qrCode: shortCode(8),
      createdById: ctx.actor.user.id,
    })
    .returning({ id: establishments.id });
  await refreshSearchKeywords(est.id);
  await refreshCompleteness(est.id);
  await audit({ actor: actorOf(ctx), category: 'MODIFICATION', action: 'establishment.create', summary: `Fiche créée : ${d.name} (${commune.name})`, territoryId: ctx.territory.id, targetType: 'establishment', targetId: est.id });
  refreshPortal(ctx);
  redirect(`/collectivite/entreprises/${est.id}`);
}

const STATUS_ACTIONS = {
  validate: { to: 'VALIDATED', summary: 'Fiche validée par la collectivité', verb: 'validée' },
  suspend: { to: 'SUSPENDED', summary: 'Fiche suspendue', verb: 'suspendue' },
  restore: { to: 'CLAIMED', summary: 'Fiche réactivée', verb: 'réactivée' },
  archive: { to: 'ARCHIVED', summary: 'Fiche archivée (activité cessée)', verb: 'archivée' },
} as const;

export async function setStatusAction(_prev: BoState, form: FormData): Promise<BoState> {
  const ctx = await loadBoContext();
  const id = uuid.parse(form.get('estId'));
  const op = z.enum(['validate', 'suspend', 'restore', 'archive']).parse(form.get('op'));
  if ((op === 'archive' || op === 'suspend') && ctx.access !== 'ADMIN') return { status: 'error', message: 'Réservé aux administrateurs.' };
  const [e] = await db.select().from(establishments).where(and(estScope(ctx), eq(establishments.id, id))).limit(1);
  if (!e) return { status: 'error', message: 'Fiche introuvable.' };
  const reason = String(form.get('reason') ?? '').trim().slice(0, 300) || null;
  if (op === 'suspend' && !reason) return { status: 'error', message: 'Indiquez le motif de la suspension (il sera communiqué au professionnel).' };
  const def = STATUS_ACTIONS[op];
  let to: (typeof establishments.$inferSelect)['status'] = def.to;
  if (op === 'restore') {
    const [m] = await db.select({ id: companyMembers.userId }).from(companyMembers).where(eq(companyMembers.companyId, e.companyId)).limit(1);
    to = m ? 'CLAIMED' : e.description ? 'TO_COMPLETE' : 'PRECREATED';
  }
  await db
    .update(establishments)
    .set({ status: to, suspendedReason: op === 'suspend' ? reason : null, archivedAt: op === 'archive' ? new Date() : null, updatedAt: new Date() })
    .where(eq(establishments.id, e.id));
  await recordRevision(e.id, { status: e.status }, { status: to }, { userId: ctx.actor.user.id, source: 'COLLECTIVITE', summary: `${def.summary}${reason ? ` : ${reason}` : ''}` });
  await audit({
    actor: actorOf(ctx),
    category: op === 'suspend' || op === 'archive' ? 'MODERATION' : 'VALIDATION',
    action: `establishment.${op}`,
    summary: `${e.name} : fiche ${def.verb}${reason ? ` (${reason})` : ''}`,
    territoryId: ctx.territory.id,
    targetType: 'establishment',
    targetId: e.id,
  });
  refreshPortal(ctx);
  revalidatePath(`/collectivite/entreprises/${e.id}`);
  return { status: 'ok', message: `Fiche ${def.verb}.` };
}

/** Message de la collectivité au professionnel (ex. confirmer les horaires). */
export async function messageProAction(_prev: BoState, form: FormData): Promise<BoState> {
  const ctx = await loadBoContext();
  const id = uuid.parse(form.get('estId'));
  const body = z.string().trim().min(5, 'Message trop court').max(2000).safeParse(form.get('body'));
  if (!body.success) return { status: 'error', message: body.error.issues[0]?.message };
  const [e] = await db.select().from(establishments).where(and(estScope(ctx), eq(establishments.id, id))).limit(1);
  if (!e) return { status: 'error', message: 'Fiche introuvable.' };
  await db.insert(messages).values({
    establishmentId: e.id,
    territoryId: ctx.territory.id,
    source: 'COLLECTIVITE',
    fromUserId: ctx.actor.user.id,
    senderName: ctx.scopeName,
    senderEmail: ctx.territory.contactEmail ?? ctx.actor.user.email,
    body: body.data,
  });
  await audit({ actor: actorOf(ctx), category: 'ENVOI', action: 'establishment.message', summary: `Message envoyé à ${e.name}`, territoryId: ctx.territory.id, targetType: 'establishment', targetId: e.id });
  revalidatePath(`/collectivite/entreprises/${e.id}`);
  return { status: 'ok', message: 'Message envoyé : il apparaît dans la messagerie du professionnel.' };
}

/** Relance « horaires à confirmer » pour une sélection. */
export async function remindHoursAction(_prev: BoState, form: FormData): Promise<BoState> {
  const ctx = await loadBoContext();
  const ids = await scopedIds(ctx, form.get('ids'));
  if (!ids.length) return { status: 'error', message: 'Aucune fiche sélectionnée.' };
  const managed = await db
    .select({ id: establishments.id })
    .from(establishments)
    .where(and(inArray(establishments.id, ids), sql`exists (select 1 from company_members m where m.company_id = "establishments"."company_id")`));
  if (managed.length)
    await db.insert(messages).values(
      managed.map((m) => ({
        establishmentId: m.id,
        territoryId: ctx.territory.id,
        source: 'COLLECTIVITE' as const,
        fromUserId: ctx.actor.user.id,
        senderName: ctx.scopeName,
        senderEmail: ctx.territory.contactEmail ?? ctx.actor.user.email,
        body: 'Bonjour, pouvez-vous vérifier et confirmer vos horaires sur votre fiche ? Des horaires à jour, ce sont des clients qui ne trouvent pas porte close. Merci !',
      })),
    );
  return { status: 'ok', message: `${managed.length} relance${managed.length > 1 ? 's' : ''} envoyée${managed.length > 1 ? 's' : ''}${ids.length - managed.length ? ` · ${ids.length - managed.length} fiche(s) non revendiquée(s) ignorée(s)` : ''}.` };
}
