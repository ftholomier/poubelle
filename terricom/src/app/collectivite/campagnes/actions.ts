'use server';

import { and, count, eq, inArray, ne, sql } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { z } from 'zod';
import { audit } from '@/server/audit';
import { rateLimit } from '@/server/auth/rate-limit';
import { invalidate } from '@/server/cache';
import { db } from '@/server/db';
import { adventDoors, campaignParticipants, campaigns, categories, communes, establishments, messages, posts, subscribers } from '@/server/db/schema';
import { planCampaign, type CampaignPlan } from '@/server/ai/features';
import { MediaError, saveImageUpload } from '@/server/media';
import { canEditCampaign, estScope, loadBoContext, type BoContext } from '@/server/services/backoffice';
import { slugify } from '@/lib/slug';
import { sized } from '@/lib/images';

export type CampState = { status: 'idle' | 'ok' | 'error'; message?: string };

export type PlanResult =
  | {
      ok: true;
      plan: CampaignPlan;
      picks: { id: string; name: string; image: string | null; commune: string }[];
      analysed: { establishments: number; posts: number; events: number };
    }
  | { ok: false; message: string };

const uuid = z.string().uuid();

function actorOf(ctx: BoContext) {
  return { user: ctx.actor.user };
}

/** Assistant territorial : prépare sélection, page thématique et plan de diffusion. */
export async function planCampaignAction(prompt: string): Promise<PlanResult> {
  const ctx = await loadBoContext();
  const clean = prompt.trim().slice(0, 600);
  if (clean.length < 10) return { ok: false, message: 'Décrivez votre idée en une phrase (10 caractères minimum).' };
  if (!(await rateLimit(`ai-campaign:${ctx.actor.user.id}`, 20, 3600)).ok)
    return { ok: false, message: 'Beaucoup de demandes en peu de temps : réessayez dans quelques minutes.' };
  const rows = await db
    .select({
      id: establishments.id,
      name: establishments.name,
      activity: sql<string>`coalesce(${establishments.activityLabel}, ${categories.name})`,
      family: categories.family,
      commune: communes.name,
      completeness: establishments.completeness,
      coverUrl: establishments.coverUrl,
      attrs: sql<
        string[]
      >`coalesce((select array_agg(a.slug) from establishment_attributes ea join attributes a on a.id = ea.attribute_id where ea.establishment_id = "establishments"."id"), '{}')`,
    })
    .from(establishments)
    .innerJoin(categories, eq(categories.id, establishments.categoryId))
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .where(and(estScope(ctx), inArray(establishments.status, ['CLAIMED', 'VALIDATED', 'TO_COMPLETE'])))
    .orderBy(sql`${establishments.completeness} desc`)
    .limit(400);
  const [[subs], [postCount]] = await Promise.all([
    db
      .select({ n: count() })
      .from(subscribers)
      .where(and(eq(subscribers.territoryId, ctx.territory.id), eq(subscribers.status, 'CONFIRMED'))),
    db
      .select({ n: count() })
      .from(posts)
      .where(and(eq(posts.territoryId, ctx.territory.id), eq(posts.status, 'PUBLISHED'))),
  ]);
  const plan = await planCampaign(
    clean,
    ctx.territory.name,
    rows.map((r) => ({
      id: r.id,
      name: r.name,
      activity: r.activity,
      family: r.family,
      commune: r.commune,
      attributes: r.attrs ?? [],
      completeness: r.completeness,
    })),
    Number(subs?.n ?? 0),
    { territoryId: ctx.territory.id, userId: ctx.actor.user.id },
  );
  const byId = new Map(rows.map((r) => [r.id, r]));
  const picks = plan.selectedIds
    .map((id) => byId.get(id))
    .filter(Boolean)
    .map((r) => ({ id: r!.id, name: r!.name, image: sized(r!.coverUrl, 120, 120), commune: r!.commune }));
  return { ok: true, plan, picks, analysed: { establishments: rows.length, posts: Number(postCount?.n ?? 0), events: 0 } };
}

const planSchema = z.object({
  name: z.string().trim().min(3).max(200),
  tagline: z.string().trim().max(250).optional().default(''),
  pageText: z.string().trim().max(2000).optional().default(''),
  pageTitle: z.string().trim().max(250).optional().default(''),
  newsletterSubject: z.string().trim().max(250).optional().default(''),
  newsletterIntro: z.string().trim().max(1000).optional().default(''),
  criteriaText: z.string().trim().max(500).optional().default(''),
  families: z
    .array(z.enum(['COMMERCE', 'ARTISAN', 'PRODUCTEUR', 'RESTAURATION', 'SERVICES']))
    .max(5)
    .default([]),
  attributeSlugs: z.array(z.string().max(80)).max(20).default([]),
  startsAt: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  endsAt: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
  plan: z
    .array(z.object({ date: z.string().max(40), text: z.string().max(300) }))
    .max(12)
    .default([]),
  selectedIds: z.array(uuid).max(400).default([]),
  prompt: z.string().max(600).optional(),
});

async function uniqueSlug(territoryId: string, name: string) {
  const base = slugify(name) || 'campagne';
  const taken = new Set((await db.select({ s: campaigns.slug }).from(campaigns).where(eq(campaigns.territoryId, territoryId))).map((r) => r.s));
  let slug = base;
  for (let i = 2; taken.has(slug); i++) slug = `${base}-${i}`;
  return slug;
}

/** Crée la campagne proposée par l'assistant (et invite éventuellement la sélection). */
export async function createFromPlanAction(_prev: CampState, form: FormData): Promise<CampState> {
  const ctx = await loadBoContext();
  let raw: unknown;
  try {
    raw = JSON.parse(String(form.get('plan') ?? '{}'));
  } catch {
    return { status: 'error', message: 'Proposition invalide : relancez l’assistant.' };
  }
  const parsed = planSchema.safeParse(raw);
  if (!parsed.success) return { status: 'error', message: 'Proposition incomplète : relancez l’assistant.' };
  const p = parsed.data;
  const invite = form.get('invite') === '1';
  const scoped = p.selectedIds.length
    ? (
        await db
          .select({ id: establishments.id })
          .from(establishments)
          .where(and(estScope(ctx), inArray(establishments.id, p.selectedIds)))
      ).map((r) => r.id)
    : [];
  const [camp] = await db
    .insert(campaigns)
    .values({
      territoryId: ctx.territory.id,
      communeId: communeOf(ctx),
      slug: await uniqueSlug(ctx.territory.id, p.name),
      name: p.name,
      tagline: p.tagline || null,
      description: p.pageText,
      startsAt: p.startsAt,
      endsAt: p.endsAt < p.startsAt ? p.startsAt : p.endsAt,
      status: 'DRAFT',
      criteria: { families: p.families, attributeSlugs: p.attributeSlugs, ...(ctx.communeIds ? { communeIds: ctx.communeIds } : {}) },
      aiPlan: {
        pageTitle: p.pageTitle,
        pageText: p.pageText,
        newsletterSubject: p.newsletterSubject,
        newsletterIntro: p.newsletterIntro,
        plan: p.plan,
        prompt: p.prompt,
      },
      invitationMessage: `${ctx.scopeName} lance « ${p.name} » du ${p.startsAt.split('-').reverse().join('/')} au ${p.endsAt.split('-').reverse().join('/')}. Participez gratuitement en proposant une offre ou une animation : elle sera mise en avant sur le portail et dans la newsletter.`,
      createdById: ctx.actor.user.id,
    })
    .returning();
  if (scoped.length)
    await db
      .insert(campaignParticipants)
      .values(scoped.map((id) => ({ campaignId: camp.id, establishmentId: id, status: 'INVITED' as const, invitedAt: invite ? new Date() : null })))
      .onConflictDoNothing();
  if (invite && scoped.length) await notifyParticipants(ctx, camp.id, scoped, camp.name, camp.invitationMessage ?? '');
  await audit({
    actor: actorOf(ctx),
    category: 'MODIFICATION',
    action: 'campaign.create',
    summary: `Campagne « ${camp.name} » préparée avec l'assistant (${scoped.length} établissements${invite ? ', invités' : ''})`,
    territoryId: ctx.territory.id,
    targetType: 'campaign',
    targetId: camp.id,
  });
  revalidatePath('/collectivite/campagnes');
  redirect(`/collectivite/campagnes/${camp.id}${invite ? '?ok=invites' : form.get('invite') === 'adjust' ? '#participants' : ''}`);
}

/** Invitation dans la messagerie des professionnels (et sur leur tableau de bord). */
async function notifyParticipants(ctx: BoContext, campaignId: string, ids: string[], name: string, text: string) {
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
        body: `Vous êtes invité·e à la campagne « ${name} ». ${text} Rejoignez-la depuis votre tableau de bord.`,
      })),
    );
  await db
    .update(campaignParticipants)
    .set({ invitedAt: new Date() })
    .where(and(eq(campaignParticipants.campaignId, campaignId), inArray(campaignParticipants.establishmentId, ids)));
}

export async function createCampaignAction(_prev: CampState, form: FormData): Promise<CampState> {
  const ctx = await loadBoContext();
  const parsed = z
    .object({
      name: z.string().trim().min(3, 'Nom trop court').max(200),
      startsAt: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Date de début'),
      endsAt: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Date de fin'),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  if (d.endsAt < d.startsAt) return { status: 'error', message: 'La fin doit suivre le début.' };
  const [camp] = await db
    .insert(campaigns)
    .values({
      territoryId: ctx.territory.id,
      communeId: communeOf(ctx),
      criteria: ctx.communeIds ? { communeIds: ctx.communeIds } : {},
      slug: await uniqueSlug(ctx.territory.id, d.name),
      name: d.name,
      startsAt: d.startsAt,
      endsAt: d.endsAt,
      status: 'DRAFT',
      createdById: ctx.actor.user.id,
    })
    .returning({ id: campaigns.id });
  await audit({
    actor: actorOf(ctx),
    category: 'MODIFICATION',
    action: 'campaign.create',
    summary: `Campagne « ${d.name} » créée`,
    territoryId: ctx.territory.id,
    targetType: 'campaign',
    targetId: camp.id,
  });
  redirect(`/collectivite/campagnes/${camp.id}`);
}

/** Au niveau communal, une nouvelle campagne est une opération de la commune. */
function communeOf(ctx: BoContext): string | null {
  return ctx.level === 'COMMUNE' ? (ctx.commune?.id ?? null) : null;
}

/** Campagne du territoire modifiable par l'agent (une commune ne modifie que ses propres opérations). */
async function scopedCampaign(ctx: BoContext, raw: unknown) {
  const id = uuid.safeParse(raw);
  if (!id.success) return null;
  const [c] = await db
    .select()
    .from(campaigns)
    .where(and(eq(campaigns.id, id.data), eq(campaigns.territoryId, ctx.territory.id)))
    .limit(1);
  return c && canEditCampaign(ctx, c) ? c : null;
}

/** Établissement du périmètre de l'agent. */
async function inScope(ctx: BoContext, estId: string): Promise<boolean> {
  const [row] = await db
    .select({ id: establishments.id })
    .from(establishments)
    .where(and(estScope(ctx), eq(establishments.id, estId)))
    .limit(1);
  return Boolean(row);
}

const color = z.string().regex(/^#[0-9a-fA-F]{6}$/);

export async function updateCampaignAction(_prev: CampState, form: FormData): Promise<CampState> {
  const ctx = await loadBoContext();
  const c = await scopedCampaign(ctx, form.get('campaignId'));
  if (!c) return { status: 'error', message: 'Campagne introuvable.' };
  const parsed = z
    .object({
      name: z.string().trim().min(3).max(200),
      tagline: z.string().trim().max(250),
      description: z.string().trim().max(4000),
      startsAt: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
      endsAt: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
      status: z.enum(['DRAFT', 'SCHEDULED', 'ACTIVE', 'ENDED']),
      mode: z.enum(['STANDARD', 'ADVENT']),
      colorBg: color,
      colorText: color,
      ctaLabel: z.string().trim().max(64),
      invitationMessage: z.string().trim().max(2000),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: `Champ invalide : ${parsed.error.issues[0]?.path.join('.')}` };
  const d = parsed.data;
  if (d.endsAt < d.startsAt) return { status: 'error', message: 'La fin doit suivre le début.' };
  let heroImageUrl = c.heroImageUrl;
  const file = form.get('hero');
  if (file instanceof File && file.size > 0) {
    try {
      const m = await saveImageUpload(file, { ownerType: 'CAMPAIGN', ownerId: c.id, territoryId: ctx.territory.id, uploadedById: ctx.actor.user.id });
      heroImageUrl = m.url;
    } catch (err) {
      return { status: 'error', message: err instanceof MediaError ? err.message : 'Image refusée.' };
    }
  }
  // Texte secondaire : couleur du texte adoucie vers le fond.
  const blend = (a: string, b: string, w: number) => {
    const [x, y] = [parseInt(a.slice(1), 16), parseInt(b.slice(1), 16)];
    const ch = (sh: number) => Math.round(((x >> sh) & 255) * w + ((y >> sh) & 255) * (1 - w));
    return `#${[16, 8, 0].map((sh) => ch(sh).toString(16).padStart(2, '0')).join('')}`;
  };
  const darker = (hex: string) => {
    const n = parseInt(hex.slice(1), 16);
    const f = (v: number) => Math.max(0, Math.round(v * 0.78));
    return `#${[(n >> 16) & 255, (n >> 8) & 255, n & 255].map((v) => f(v).toString(16).padStart(2, '0')).join('')}`;
  };
  await db
    .update(campaigns)
    .set({
      name: d.name,
      tagline: d.tagline || null,
      description: d.description,
      startsAt: d.startsAt,
      endsAt: d.endsAt,
      status: d.status,
      mode: d.mode,
      colorBg: d.colorBg,
      colorBgDark: darker(d.colorBg),
      colorText: d.colorText,
      colorTextSoft: blend(d.colorText, d.colorBg, 0.75),
      ctaLabel: d.ctaLabel || null,
      invitationMessage: d.invitationMessage || null,
      heroImageUrl,
      cardImageUrl: heroImageUrl ?? c.cardImageUrl,
      updatedAt: new Date(),
    })
    .where(eq(campaigns.id, c.id));
  await audit({
    actor: actorOf(ctx),
    category: 'MODIFICATION',
    action: 'campaign.update',
    summary: `Campagne « ${d.name} » mise à jour (${d.status.toLowerCase()})`,
    territoryId: ctx.territory.id,
    targetType: 'campaign',
    targetId: c.id,
  });
  invalidate(`campaign:${ctx.territory.id}`);
  revalidatePath(`/collectivite/campagnes/${c.id}`);
  return { status: 'ok', message: 'Campagne enregistrée.' };
}

export async function inviteParticipantsAction(_prev: CampState, form: FormData): Promise<CampState> {
  const ctx = await loadBoContext();
  const c = await scopedCampaign(ctx, form.get('campaignId'));
  if (!c) return { status: 'error', message: 'Campagne introuvable.' };
  const pending = await db
    .select({ id: campaignParticipants.establishmentId })
    .from(campaignParticipants)
    .where(and(eq(campaignParticipants.campaignId, c.id), eq(campaignParticipants.status, 'INVITED')));
  const ids = pending.map((p) => p.id);
  if (!ids.length) return { status: 'error', message: 'Aucun établissement à inviter.' };
  await notifyParticipants(ctx, c.id, ids, c.name, c.invitationMessage ?? '');
  await audit({
    actor: actorOf(ctx),
    category: 'ENVOI',
    action: 'campaign.invite',
    summary: `${ids.length} invitations envoyées pour « ${c.name} »`,
    territoryId: ctx.territory.id,
    targetType: 'campaign',
    targetId: c.id,
  });
  revalidatePath(`/collectivite/campagnes/${c.id}`);
  return { status: 'ok', message: `${ids.length} établissement${ids.length > 1 ? 's' : ''} invité${ids.length > 1 ? 's' : ''}.` };
}

export async function addParticipantAction(_prev: CampState, form: FormData): Promise<CampState> {
  const ctx = await loadBoContext();
  const c = await scopedCampaign(ctx, form.get('campaignId'));
  if (!c) return { status: 'error', message: 'Campagne introuvable.' };
  const q = String(form.get('q') ?? '').trim();
  if (q.length < 2) return { status: 'error', message: 'Tapez le nom d’un établissement.' };
  const found = await db
    .select({ id: establishments.id, name: establishments.name })
    .from(establishments)
    .where(and(estScope(ctx), ne(establishments.status, 'ARCHIVED'), sql`f_unaccent(${establishments.name}) ilike f_unaccent(${`%${q}%`})`))
    .limit(5);
  if (!found.length) return { status: 'error', message: `Aucun établissement ne correspond à « ${q} ».` };
  await db
    .insert(campaignParticipants)
    .values(found.slice(0, 1).map((f) => ({ campaignId: c.id, establishmentId: f.id, status: 'INVITED' as const })))
    .onConflictDoNothing();
  revalidatePath(`/collectivite/campagnes/${c.id}`);
  return { status: 'ok', message: `${found[0].name} ajouté à la campagne.` };
}

export async function removeParticipantAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const c = await scopedCampaign(ctx, form.get('campaignId'));
  const estId = uuid.parse(form.get('estId'));
  if (!c || !(await inScope(ctx, estId))) return;
  await db.delete(campaignParticipants).where(and(eq(campaignParticipants.campaignId, c.id), eq(campaignParticipants.establishmentId, estId)));
  revalidatePath(`/collectivite/campagnes/${c.id}`);
}

export async function saveDoorAction(_prev: CampState, form: FormData): Promise<CampState> {
  const ctx = await loadBoContext();
  const c = await scopedCampaign(ctx, form.get('campaignId'));
  if (!c) return { status: 'error', message: 'Campagne introuvable.' };
  const day = z.coerce.number().int().min(1).max(31).parse(form.get('day'));
  const title = z.string().trim().min(2).max(255).safeParse(form.get('title'));
  if (!title.success) return { status: 'error', message: 'Titre de la case requis.' };
  const est = String(form.get('establishmentId') ?? '');
  let estId: string | null = uuid.safeParse(est).success ? est : null;
  // La case ne peut mettre en avant qu'un participant de la campagne, dans le périmètre de l'agent.
  if (estId) {
    const [p] = await db
      .select({ id: campaignParticipants.establishmentId })
      .from(campaignParticipants)
      .where(and(eq(campaignParticipants.campaignId, c.id), eq(campaignParticipants.establishmentId, estId)))
      .limit(1);
    if (!p || !(await inScope(ctx, estId))) estId = null;
  }
  await db
    .insert(adventDoors)
    .values({ campaignId: c.id, day, title: title.data, establishmentId: estId })
    .onConflictDoUpdate({ target: [adventDoors.campaignId, adventDoors.day], set: { title: title.data, establishmentId: estId } });
  revalidatePath(`/collectivite/campagnes/${c.id}`);
  return { status: 'ok', message: `Case du ${day} enregistrée.` };
}
