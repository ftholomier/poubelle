'use server';

import { and, eq, inArray, sql } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { z } from 'zod';
import { fromParisLocal } from '@/lib/format';
import { audit } from '@/server/audit';
import { rateLimit } from '@/server/auth/rate-limit';
import { db } from '@/server/db';
import { audiences, campaigns, establishments, newsletters, type NewsletterBlock } from '@/server/db/schema';
import { renderNewsletter } from '@/server/mail/newsletter';
import { sendEmail } from '@/server/mail/send';
import { MediaError, saveImageUpload } from '@/server/media';
import { enqueue } from '@/server/queue';
import { estScope, loadBoContext, type BoContext } from '@/server/services/backoffice';
import { autoCompose, newsletterName, nextNumber, previewHtml, recipientCount, resolveBlocks } from '@/server/services/newsletters';
import { portalUrl } from '@/server/urls';
import { sized } from '@/lib/images';

export type NlState = { status: 'idle' | 'ok' | 'error'; message?: string };

const uuid = z.string().uuid();
const base = '/collectivite/newsletter';

async function scopedLetter(ctx: BoContext, raw: unknown) {
  const id = uuid.parse(raw);
  const [n] = await db
    .select()
    .from(newsletters)
    .where(and(eq(newsletters.id, id), eq(newsletters.territoryId, ctx.territory.id)))
    .limit(1);
  if (!n || n.companyId) return null;
  if (ctx.commune && n.communeId !== ctx.commune.id) return null;
  return n;
}

function editable(n: { status: string }) {
  return n.status === 'DRAFT' || n.status === 'SCHEDULED';
}

export async function createNewsletterAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const campaignId = uuid.safeParse(form.get('campaignId'));
  const draft = await autoCompose(ctx.territory, ctx.communeIds);
  let { title, intro, subject } = draft;
  let blocks = draft.blocks;
  if (campaignId.success) {
    const [c] = await db
      .select()
      .from(campaigns)
      .where(and(eq(campaigns.id, campaignId.data), eq(campaigns.territoryId, ctx.territory.id)))
      .limit(1);
    if (c) {
      subject = c.aiPlan?.newsletterSubject || c.name;
      title = c.aiPlan?.pageTitle || c.name;
      intro = c.aiPlan?.newsletterIntro || c.tagline || c.description.slice(0, 280);
      blocks = [{ type: 'cta', label: 'Découvrir la sélection', url: `/campagnes/${c.slug}` }];
    }
  }
  const defaults = await db
    .select({ id: audiences.id })
    .from(audiences)
    .where(and(eq(audiences.territoryId, ctx.territory.id), ctx.commune ? eq(audiences.communeId, ctx.commune.id) : eq(audiences.isDefault, true)));
  const [n] = await db
    .insert(newsletters)
    .values({
      territoryId: ctx.territory.id,
      communeId: ctx.commune?.id ?? null,
      number: await nextNumber(ctx.territory.id),
      subject,
      title,
      intro,
      heroImageUrl: draft.heroImageUrl,
      blocks,
      audienceIds: defaults.map((a) => a.id),
      createdById: ctx.actor.user.id,
    })
    .returning({ id: newsletters.id });
  await audit({
    actor: { user: ctx.actor.user },
    category: 'MODIFICATION',
    action: 'newsletter.create',
    summary: `Lettre « ${subject} » créée`,
    territoryId: ctx.territory.id,
    targetType: 'newsletter',
    targetId: n.id,
  });
  redirect(`${base}?lettre=${n.id}`);
}

export async function saveContentAction(_prev: NlState, form: FormData): Promise<NlState> {
  const ctx = await loadBoContext();
  const n = await scopedLetter(ctx, form.get('newsletterId'));
  if (!n || !editable(n)) return { status: 'error', message: 'Cette lettre ne peut plus être modifiée.' };
  const parsed = z
    .object({
      subject: z.string().trim().min(3, 'Objet trop court').max(255),
      preheader: z.string().trim().max(255),
      title: z.string().trim().min(3, 'Titre trop court').max(255),
      intro: z.string().trim().max(3000),
      ctaLabel: z.string().trim().max(64),
      ctaUrl: z.string().trim().max(500),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  let heroImageUrl = n.heroImageUrl;
  const file = form.get('hero');
  if (file instanceof File && file.size > 0) {
    try {
      heroImageUrl = (await saveImageUpload(file, { ownerType: 'NEWSLETTER', ownerId: n.id, territoryId: ctx.territory.id, uploadedById: ctx.actor.user.id }))
        .url;
    } catch (err) {
      return { status: 'error', message: err instanceof MediaError ? err.message : 'Image refusée.' };
    }
  }
  const blocks: NewsletterBlock[] = n.blocks.filter((b) => b.type !== 'cta');
  if (d.ctaLabel && d.ctaUrl) blocks.push({ type: 'cta', label: d.ctaLabel, url: d.ctaUrl });
  await db
    .update(newsletters)
    .set({ subject: d.subject, preheader: d.preheader || null, title: d.title, intro: d.intro, heroImageUrl, blocks, updatedAt: new Date() })
    .where(eq(newsletters.id, n.id));
  revalidatePath(base);
  return { status: 'ok', message: 'Contenu enregistré.' };
}

export async function autoFillAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const n = await scopedLetter(ctx, form.get('newsletterId'));
  if (!n || !editable(n)) return;
  const d = await autoCompose(ctx.territory, ctx.communeIds);
  await db
    .update(newsletters)
    .set({ subject: d.subject, title: d.title, intro: d.intro, heroImageUrl: d.heroImageUrl ?? n.heroImageUrl, blocks: d.blocks, updatedAt: new Date() })
    .where(eq(newsletters.id, n.id));
  revalidatePath(base);
  redirect(`${base}?lettre=${n.id}`);
}

export async function addEstablishmentAction(_prev: NlState, form: FormData): Promise<NlState> {
  const ctx = await loadBoContext();
  const n = await scopedLetter(ctx, form.get('newsletterId'));
  if (!n || !editable(n)) return { status: 'error', message: 'Cette lettre ne peut plus être modifiée.' };
  const q = String(form.get('q') ?? '').trim();
  if (q.length < 2) return { status: 'error', message: 'Tapez le nom d’un établissement.' };
  const [e] = await db
    .select({ id: establishments.id, name: establishments.name })
    .from(establishments)
    .where(
      and(
        estScope(ctx),
        inArray(establishments.status, ['PRECREATED', 'TO_COMPLETE', 'CLAIMED', 'VALIDATED']),
        sql`f_unaccent(${establishments.name}) ilike f_unaccent(${`%${q}%`})`,
      ),
    )
    .limit(1);
  if (!e) return { status: 'error', message: `Aucun établissement ne correspond à « ${q} ».` };
  const blocks = [...n.blocks];
  const idx = blocks.findIndex((b) => b.type === 'establishments');
  if (idx >= 0) {
    const b = blocks[idx] as Extract<NewsletterBlock, { type: 'establishments' }>;
    if (!b.ids.includes(e.id)) blocks[idx] = { ...b, ids: [...b.ids, e.id].slice(0, 8) };
  } else {
    const ctaIdx = blocks.findIndex((b) => b.type === 'cta');
    blocks.splice(ctaIdx >= 0 ? ctaIdx : blocks.length, 0, { type: 'establishments', ids: [e.id], title: 'Des adresses à découvrir' });
  }
  await db.update(newsletters).set({ blocks, updatedAt: new Date() }).where(eq(newsletters.id, n.id));
  revalidatePath(base);
  return { status: 'ok', message: `${e.name} ajouté à la lettre.` };
}

export async function removeItemAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const n = await scopedLetter(ctx, form.get('newsletterId'));
  if (!n || !editable(n)) return;
  const itemId = uuid.parse(form.get('itemId'));
  const blocks = n.blocks
    .map((b) => (b.type === 'establishments' || b.type === 'posts' || b.type === 'events' ? { ...b, ids: b.ids.filter((x) => x !== itemId) } : b))
    .filter((b) => !('ids' in b) || b.ids.length);
  await db.update(newsletters).set({ blocks, updatedAt: new Date() }).where(eq(newsletters.id, n.id));
  revalidatePath(base);
}

export async function saveAudiencesAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const n = await scopedLetter(ctx, form.get('newsletterId'));
  if (!n || !editable(n)) return;
  const wanted = form
    .getAll('audience')
    .map(String)
    .filter((x) => uuid.safeParse(x).success);
  const valid = wanted.length
    ? (
        await db
          .select({ id: audiences.id, communeId: audiences.communeId, criteria: audiences.criteria })
          .from(audiences)
          .where(and(eq(audiences.territoryId, ctx.territory.id), inArray(audiences.id, wanted)))
      )
        .filter((a) => audienceVisible(ctx, a))
        .map((a) => a.id)
    : [];
  await db.update(newsletters).set({ audienceIds: valid, updatedAt: new Date() }).where(eq(newsletters.id, n.id));
  revalidatePath(base);
}

export async function scheduleAction(_prev: NlState, form: FormData): Promise<NlState> {
  const ctx = await loadBoContext();
  const n = await scopedLetter(ctx, form.get('newsletterId'));
  if (!n || !editable(n)) return { status: 'error', message: 'Cette lettre ne peut plus être modifiée.' };
  if (!n.audienceIds.length) return { status: 'error', message: 'Choisissez au moins une audience.' };
  const total = await recipientCount(ctx.territory.id, n.audienceIds);
  if (!total) return { status: 'error', message: 'Aucun abonné confirmé dans ces audiences.' };
  const now = form.get('mode') === 'now';
  let at = new Date();
  if (!now) {
    const date = String(form.get('date') ?? '');
    const time = String(form.get('time') ?? '08:00');
    if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !/^\d{2}:\d{2}$/.test(time)) return { status: 'error', message: 'Date ou heure invalide.' };
    at = fromParisLocal(date, time);
    if (at.getTime() < Date.now() - 60_000) return { status: 'error', message: 'Cette date est déjà passée.' };
  }
  if (!(await rateLimit(`nl-send:${ctx.territory.id}`, 10, 86_400)).ok) return { status: 'error', message: 'Limite d’envois quotidienne atteinte.' };
  await db.update(newsletters).set({ status: 'SCHEDULED', scheduledAt: at, updatedAt: new Date() }).where(eq(newsletters.id, n.id));
  await enqueue('newsletter.dispatch', { newsletterId: n.id }, { runAt: at, dedupeKey: `nl-dispatch:${n.id}:${at.getTime()}` });
  await audit({
    actor: { user: ctx.actor.user },
    category: 'ENVOI',
    action: 'newsletter.schedule',
    summary: `Lettre « ${n.subject} » ${now ? 'envoyée' : 'programmée'} · ${total} destinataires`,
    territoryId: ctx.territory.id,
    targetType: 'newsletter',
    targetId: n.id,
  });
  revalidatePath(base);
  return {
    status: 'ok',
    message: now ? `Envoi lancé à ${total.toLocaleString('fr-FR')} abonnés.` : `Envoi programmé pour ${total.toLocaleString('fr-FR')} abonnés.`,
  };
}

export async function unscheduleAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const n = await scopedLetter(ctx, form.get('newsletterId'));
  if (!n || n.status !== 'SCHEDULED') return;
  await db.update(newsletters).set({ status: 'DRAFT', scheduledAt: null, updatedAt: new Date() }).where(eq(newsletters.id, n.id));
  revalidatePath(base);
}

export async function sendTestAction(_prev: NlState, form: FormData): Promise<NlState> {
  const ctx = await loadBoContext();
  const n = await scopedLetter(ctx, form.get('newsletterId'));
  if (!n) return { status: 'error', message: 'Lettre introuvable.' };
  if (!(await rateLimit(`nl-test:${ctx.actor.user.id}`, 10, 3600)).ok) return { status: 'error', message: 'Trop de tests : réessayez plus tard.' };
  const { html, text } = renderNewsletter({
    brandName: ctx.territory.name,
    number: n.number,
    color: ctx.territory.colorPrimary,
    accent: ctx.territory.colorAccent,
    title: n.title,
    intro: n.intro,
    preheader: n.preheader,
    heroImageUrl: sized(n.heroImageUrl, 1100, 500),
    blocks: await resolveBlocks(ctx.territory, n.blocks),
    newsletterName: newsletterName(ctx.territory),
    unsubscribeUrl: portalUrl(ctx.territory, '/newsletter/desinscription'),
    preferencesUrl: portalUrl(ctx.territory, '/newsletter/desinscription'),
  });
  await sendEmail({ to: ctx.actor.user.email, subject: `[TEST] ${n.subject}`, html, text, template: 'newsletter-test', territoryId: ctx.territory.id });
  return { status: 'ok', message: `Test envoyé à ${ctx.actor.user.email}.` };
}

export async function deleteDraftAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const n = await scopedLetter(ctx, form.get('newsletterId'));
  if (!n || n.status !== 'DRAFT') return;
  await db.update(newsletters).set({ status: 'CANCELLED', updatedAt: new Date() }).where(eq(newsletters.id, n.id));
  redirect(base);
}

export async function previewAction(newsletterId: string): Promise<string> {
  const ctx = await loadBoContext();
  const n = await scopedLetter(ctx, newsletterId);
  return n ? previewHtml(n, ctx.territory) : '';
}

/** Une mairie n'utilise que les audiences sans zone ou dont la zone comprend sa commune. */
function audienceVisible(ctx: BoContext, a: { communeId: string | null; criteria: { communeIds?: string[] } }): boolean {
  if (!ctx.commune) return true;
  const zone = [...(a.criteria.communeIds ?? []), ...(a.communeId ? [a.communeId] : [])];
  return !zone.length || zone.includes(ctx.commune.id);
}

/** Nouvelle audience : zone géographique (habitants), professionnels (familles, catégories) ou liste. */
export async function createAudienceAction(_prev: NlState, form: FormData): Promise<NlState> {
  const ctx = await loadBoContext();
  if (ctx.level !== 'TERRITORY') return { status: 'error', message: 'Les audiences se créent au niveau du territoire.' };
  const parsed = z
    .object({
      name: z.string().trim().min(3, 'Nom trop court').max(160),
      description: z.string().trim().max(255),
      kind: z.enum(['COMMUNE', 'BUSINESSES', 'MANUAL']),
    })
    .safeParse(Object.fromEntries([...form.entries()].filter(([, v]) => typeof v === 'string')));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  const communeIds = form
    .getAll('communeIds')
    .map(String)
    .filter((id) => ctx.communes.some((c) => c.id === id));
  const families = form
    .getAll('families')
    .map(String)
    .filter((f) => ['COMMERCE', 'ARTISAN', 'PRODUCTEUR', 'RESTAURATION', 'SERVICES'].includes(f));
  const categoryIds = form
    .getAll('categoryIds')
    .map(String)
    .filter((id) => /^[0-9a-f-]{36}$/.test(id))
    .slice(0, 40);
  if (d.kind === 'COMMUNE' && !communeIds.length) return { status: 'error', message: 'Choisissez au moins une commune pour la zone.' };
  const criteria = d.kind === 'COMMUNE' ? { communeIds } : d.kind === 'BUSINESSES' ? { families, categoryIds } : {};
  const [a] = await db
    .insert(audiences)
    .values({ territoryId: ctx.territory.id, name: d.name, description: d.description || null, kind: d.kind, criteria, sortOrder: 50 })
    .returning({ id: audiences.id });
  await audit({
    actor: { user: ctx.actor.user },
    category: 'CONFIGURATION',
    action: 'audience.create',
    summary: `Audience « ${d.name} » créée`,
    territoryId: ctx.territory.id,
    targetType: 'audience',
    targetId: a.id,
  });
  revalidatePath(base, 'layout');
  return { status: 'ok', message: `Audience « ${d.name} » créée.` };
}

/** Suppression d'une audience (hors audience par défaut des inscriptions). */
export async function deleteAudienceAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  if (ctx.level !== 'TERRITORY' || ctx.access !== 'ADMIN') return;
  const id = z.string().uuid().parse(form.get('audienceId'));
  const [a] = await db
    .select()
    .from(audiences)
    .where(and(eq(audiences.id, id), eq(audiences.territoryId, ctx.territory.id)))
    .limit(1);
  if (!a || a.isDefault) return;
  await db.delete(audiences).where(eq(audiences.id, a.id));
  await db.execute(
    sql`update newsletters set audience_ids = array_remove(audience_ids, ${a.id}::uuid) where territory_id = ${ctx.territory.id} and status in ('DRAFT', 'SCHEDULED')`,
  );
  await audit({
    actor: { user: ctx.actor.user },
    category: 'CONFIGURATION',
    action: 'audience.delete',
    summary: `Audience « ${a.name} » supprimée`,
    territoryId: ctx.territory.id,
  });
  revalidatePath(base, 'layout');
}
