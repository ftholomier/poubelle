'use server';

import { and, asc, count, eq, ne } from 'drizzle-orm';
import { z } from 'zod';
import type { ActionState } from '../actions';
import { actorOf, proCtx, refresh } from '../helpers';
import { isHexColor } from '@/lib/color';
import { DEFAULT_SECTIONS, MAX_FIELDS, MAX_FORMS, MAX_PAGES } from '@/lib/minisite';
import { slugify } from '@/lib/slug';
import { audit } from '@/server/audit';
import { db } from '@/server/db';
import { establishmentForms, establishmentPages, establishments, media, type FormField, type MiniSite, type MiniSiteSection } from '@/server/db/schema';
import type { ProContext } from '@/server/services/pro';

const uuid = z.string().uuid();

function log(ctx: ProContext, action: string, summary: string) {
  return audit({
    actor: actorOf(ctx),
    category: 'MODIFICATION',
    action,
    summary,
    territoryId: ctx.est.territoryId,
    targetType: 'establishment',
    targetId: ctx.est.id,
  });
}

function done(ctx: ProContext) {
  refresh(ctx, `${ctx.base}/site`, `${ctx.base}/fiche`);
}

/** Visuel choisi parmi les photos de la fiche (aucune URL extérieure acceptée). */
async function ownedImage(ctx: ProContext, url: string | null): Promise<string | null> {
  if (!url) return null;
  if (url === ctx.est.coverUrl) return url;
  const rows = await db
    .select({ url: media.url, variants: media.variants })
    .from(media)
    .where(and(eq(media.establishmentId, ctx.est.id), eq(media.kind, 'IMAGE'), eq(media.isPrivate, false)));
  return rows.some((m) => m.url === url || Object.values((m.variants ?? {}) as Record<string, string>).includes(url)) ? url : null;
}

// ─── Pages supplémentaires (Premium) ────────────────────────────────────────

export async function savePageAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await proCtx(form.get('estId'));
  if (!ctx.limits.extraPages) return { status: 'error', message: 'Les pages supplémentaires sont incluses dans l’offre Premium.' };
  const parsed = z
    .object({
      pageId: uuid.optional().or(z.literal('')),
      title: z.string().trim().min(2, 'Donnez un titre à la page').max(160),
      body: z.string().trim().min(20, 'Rédigez quelques lignes (20 caractères au moins)').max(20000),
      coverUrl: z.string().trim().max(1000).optional(),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  const published = form.get('published') === 'on';
  const coverUrl = await ownedImage(ctx, d.coverUrl || null);
  const base = slugify(d.title, 60) || 'page';
  const taken = new Set(
    (
      await db
        .select({ slug: establishmentPages.slug })
        .from(establishmentPages)
        .where(and(eq(establishmentPages.establishmentId, ctx.est.id), d.pageId ? ne(establishmentPages.id, d.pageId) : undefined))
    ).map((r) => r.slug),
  );
  let slug = base;
  for (let i = 2; taken.has(slug); i++) slug = `${base}-${i}`;
  if (d.pageId) {
    const updated = await db
      .update(establishmentPages)
      .set({ title: d.title, slug, body: d.body, coverUrl, published, updatedAt: new Date() })
      .where(and(eq(establishmentPages.id, d.pageId), eq(establishmentPages.establishmentId, ctx.est.id)))
      .returning({ id: establishmentPages.id });
    if (!updated.length) return { status: 'error', message: 'Page introuvable.' };
  } else {
    const [{ n }] = await db.select({ n: count() }).from(establishmentPages).where(eq(establishmentPages.establishmentId, ctx.est.id));
    if (Number(n) >= MAX_PAGES) return { status: 'error', message: `${MAX_PAGES} pages au maximum : regroupez ou supprimez une page existante.` };
    await db.insert(establishmentPages).values({ establishmentId: ctx.est.id, title: d.title, slug, body: d.body, coverUrl, published, sortOrder: Number(n) });
  }
  await log(ctx, 'establishment.page', `Page « ${d.title} » de ${ctx.est.name} ${d.pageId ? 'modifiée' : 'créée'}`);
  done(ctx);
  return { status: 'ok', message: published ? 'Page enregistrée et publiée.' : 'Brouillon enregistré.' };
}

export async function deletePageAction(form: FormData): Promise<void> {
  const ctx = await proCtx(form.get('estId'));
  const id = uuid.parse(form.get('pageId'));
  const [p] = await db
    .delete(establishmentPages)
    .where(and(eq(establishmentPages.id, id), eq(establishmentPages.establishmentId, ctx.est.id)))
    .returning({ title: establishmentPages.title });
  if (p) await log(ctx, 'establishment.page_delete', `Page « ${p.title} » de ${ctx.est.name} supprimée`);
  done(ctx);
}

/** Déplace une page d'un cran (renumérote l'ensemble pour garder un ordre continu). */
export async function movePageAction(form: FormData): Promise<void> {
  const ctx = await proCtx(form.get('estId'));
  const id = uuid.parse(form.get('pageId'));
  const dir = form.get('dir') === 'up' ? -1 : 1;
  const list = await db
    .select({ id: establishmentPages.id })
    .from(establishmentPages)
    .where(eq(establishmentPages.establishmentId, ctx.est.id))
    .orderBy(asc(establishmentPages.sortOrder), asc(establishmentPages.createdAt));
  const i = list.findIndex((p) => p.id === id);
  const j = i + dir;
  if (i < 0 || j < 0 || j >= list.length) return;
  [list[i], list[j]] = [list[j], list[i]];
  await db.transaction(async (tx) => {
    for (const [k, p] of list.entries()) await tx.update(establishmentPages).set({ sortOrder: k }).where(eq(establishmentPages.id, p.id));
  });
  done(ctx);
}

// ─── Formulaires personnalisés (Premium) ────────────────────────────────────

const fieldSchema = z
  .object({
    id: z.string().regex(/^[a-z0-9]{4,12}$/),
    label: z.string().trim().min(1, 'Chaque champ doit avoir un libellé').max(120),
    type: z.enum(['text', 'textarea', 'email', 'tel', 'date', 'number', 'select', 'checkbox']),
    required: z.boolean(),
    options: z.array(z.string().trim().min(1).max(80)).max(30).optional(),
    help: z.string().trim().max(200).nullish(),
  })
  .refine((f) => f.type !== 'select' || (f.options?.length ?? 0) >= 2, { message: 'Une liste de choix doit proposer au moins deux options.' });

export async function saveFormAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await proCtx(form.get('estId'));
  if (!ctx.limits.customForms) return { status: 'error', message: 'Les formulaires personnalisés sont inclus dans l’offre Premium.' };
  const parsed = z
    .object({
      formId: uuid.optional().or(z.literal('')),
      title: z.string().trim().min(3, 'Donnez un titre au formulaire').max(160),
      intro: z.string().trim().max(1000).optional(),
      submitLabel: z.string().trim().min(2).max(60),
      successText: z.string().trim().max(500).optional(),
      fields: z.string().max(40000),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  let raw: unknown;
  try {
    raw = JSON.parse(d.fields);
  } catch {
    return { status: 'error', message: 'Champs illisibles, rechargez la page.' };
  }
  const fields = z.array(fieldSchema).max(MAX_FIELDS, `${MAX_FIELDS} champs au maximum`).safeParse(raw);
  if (!fields.success) return { status: 'error', message: fields.error.issues[0]?.message };
  if (new Set(fields.data.map((f) => f.id)).size !== fields.data.length) return { status: 'error', message: 'Champs en double, rechargez la page.' };
  const clean: FormField[] = fields.data.map((f) => ({
    id: f.id,
    label: f.label,
    type: f.type,
    required: f.required,
    ...(f.type === 'select' ? { options: [...new Set(f.options)] } : {}),
    help: f.help || null,
  }));
  const values = {
    title: d.title,
    intro: d.intro || null,
    fields: clean,
    submitLabel: d.submitLabel,
    successText: d.successText || null,
    isActive: form.get('isActive') === 'on',
    updatedAt: new Date(),
  };
  if (d.formId) {
    const updated = await db
      .update(establishmentForms)
      .set(values)
      .where(and(eq(establishmentForms.id, d.formId), eq(establishmentForms.establishmentId, ctx.est.id)))
      .returning({ id: establishmentForms.id });
    if (!updated.length) return { status: 'error', message: 'Formulaire introuvable.' };
  } else {
    const [{ n }] = await db.select({ n: count() }).from(establishmentForms).where(eq(establishmentForms.establishmentId, ctx.est.id));
    if (Number(n) >= MAX_FORMS) return { status: 'error', message: `${MAX_FORMS} formulaires au maximum.` };
    await db.insert(establishmentForms).values({ ...values, establishmentId: ctx.est.id, sortOrder: Number(n) });
  }
  await log(ctx, 'establishment.form', `Formulaire « ${d.title} » de ${ctx.est.name} ${d.formId ? 'modifié' : 'créé'}`);
  done(ctx);
  return { status: 'ok', message: values.isActive ? 'Formulaire enregistré, visible sur votre fiche.' : 'Formulaire enregistré (masqué).' };
}

export async function deleteFormAction(form: FormData): Promise<void> {
  const ctx = await proCtx(form.get('estId'));
  const id = uuid.parse(form.get('formId'));
  const [f] = await db
    .delete(establishmentForms)
    .where(and(eq(establishmentForms.id, id), eq(establishmentForms.establishmentId, ctx.est.id)))
    .returning({ title: establishmentForms.title });
  if (f) await log(ctx, 'establishment.form_delete', `Formulaire « ${f.title} » de ${ctx.est.name} supprimé`);
  done(ctx);
}

// ─── Mini-site (Communication) ──────────────────────────────────────────────

export async function saveMiniSiteAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await proCtx(form.get('estId'));
  if (!ctx.limits.miniSite) return { status: 'error', message: 'Le mini-site personnalisable est inclus dans l’offre Communication.' };
  const parsed = z
    .object({
      themeColor: z.string().trim().optional(),
      hero: z.enum(['photo', 'color']),
      headline: z.string().trim().max(160).optional(),
      ctaLabel: z.string().trim().max(40).optional(),
      ctaTarget: z.string().trim().max(200).optional(),
      ctaUrl: z.string().trim().max(500).optional(),
      sections: z.string().max(400),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  if (d.themeColor && !isHexColor(d.themeColor)) return { status: 'error', message: 'Couleur invalide.' };
  let href = d.ctaTarget ?? '';
  if (href === 'url') {
    if (!/^https:\/\/\S+$/.test(d.ctaUrl ?? '')) return { status: 'error', message: 'Le lien du bouton doit commencer par https://' };
    href = d.ctaUrl!;
  } else if (href && !/^(tel|itineraire|contact|rdv|form:[0-9a-f-]{36}|page:[a-z0-9-]{1,160})$/.test(href)) {
    return { status: 'error', message: 'Destination du bouton invalide.' };
  }
  if (d.ctaLabel && !href) return { status: 'error', message: 'Choisissez où mène le bouton.' };
  const sections = d.sections
    .split(',')
    .map((s) => s.trim())
    .filter((s): s is MiniSiteSection => (DEFAULT_SECTIONS as string[]).includes(s));
  const miniSite: MiniSite = {
    enabled: form.get('enabled') === 'on',
    hero: d.hero,
    headline: d.headline || null,
    cta: d.ctaLabel && href ? { label: d.ctaLabel, href } : null,
    sections: [...new Set(sections)],
  };
  await db
    .update(establishments)
    .set({ miniSite, themeColor: d.themeColor || null, updatedAt: new Date() })
    .where(eq(establishments.id, ctx.est.id));
  await log(ctx, 'establishment.minisite', `Mini-site de ${ctx.est.name} ${miniSite.enabled ? 'activé et mis à jour' : 'désactivé'}`);
  done(ctx);
  return { status: 'ok', message: miniSite.enabled ? 'Mini-site enregistré et en ligne.' : 'Réglages enregistrés (mini-site désactivé).' };
}
