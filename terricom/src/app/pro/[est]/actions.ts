'use server';

import { and, eq, sql } from 'drizzle-orm';
import { z } from 'zod';
import { audit } from '@/server/audit';
import { db } from '@/server/db';
import { campaignParticipants, messages } from '@/server/db/schema';
import { actorOf, proCtx, refresh } from './helpers';

export type ActionState = { status: 'idle' | 'ok' | 'error'; message?: string };

const uuid = z.string().uuid();

// ─── Campagnes ──────────────────────────────────────────────────────────────

export async function joinCampaign(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await proCtx(form.get('estId'));
  const parsed = z
    .object({
      campaignId: uuid,
      offerLabel: z.string().trim().min(2, 'Indiquez votre offre en quelques mots').max(80),
      offerDescription: z.string().trim().max(400).optional(),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  const updated = await db
    .update(campaignParticipants)
    .set({ status: 'JOINED', offerLabel: d.offerLabel, offerDescription: d.offerDescription || null, joinedAt: new Date() })
    .where(and(eq(campaignParticipants.campaignId, d.campaignId), eq(campaignParticipants.establishmentId, ctx.est.id)))
    .returning();
  if (!updated.length) return { status: 'error', message: "Vous n'êtes pas invité·e à cette campagne." };
  await audit({
    actor: actorOf(ctx),
    category: 'MODIFICATION',
    action: 'campaign.join',
    summary: `${ctx.est.name} participe à la campagne (${d.offerLabel})`,
    territoryId: ctx.est.territoryId,
    targetType: 'establishment',
    targetId: ctx.est.id,
  });
  refresh(ctx);
  return { status: 'ok', message: 'Bravo ! Votre offre apparaîtra dans la campagne.' };
}

export async function leaveCampaign(form: FormData): Promise<void> {
  const ctx = await proCtx(form.get('estId'));
  const campaignId = uuid.parse(form.get('campaignId'));
  await db
    .update(campaignParticipants)
    .set({ status: 'DECLINED' })
    .where(and(eq(campaignParticipants.campaignId, campaignId), eq(campaignParticipants.establishmentId, ctx.est.id)));
  refresh(ctx);
}

// ─── Messages ───────────────────────────────────────────────────────────────

export async function markMessage(form: FormData): Promise<void> {
  const ctx = await proCtx(form.get('estId'));
  const id = uuid.parse(form.get('messageId'));
  const status = z.enum(['READ', 'ARCHIVED', 'NEW']).parse(form.get('status') ?? 'READ');
  await db
    .update(messages)
    .set({ status, readAt: status === 'NEW' ? null : new Date() })
    .where(and(eq(messages.id, id), eq(messages.establishmentId, ctx.est.id)));
  refresh(ctx, `${ctx.base}/messages`);
}

// ─── Fiche : enregistrement de l'éditeur ───────────────────────────────────

const hoursSchema = z
  .array(z.object({ weekday: z.number().int().min(0).max(6), opensAt: z.string().regex(/^\d{2}:\d{2}$/), closesAt: z.string().regex(/^\d{2}:\d{2}$/) }))
  .max(30);
const exceptionsSchema = z
  .array(
    z.object({
      date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
      closed: z.boolean(),
      opensAt: z
        .string()
        .regex(/^\d{2}:\d{2}$/)
        .nullable()
        .optional(),
      closesAt: z
        .string()
        .regex(/^\d{2}:\d{2}$/)
        .nullable()
        .optional(),
      label: z.string().max(160).nullable().optional(),
    }),
  )
  .max(60);
const optionalUrl = z
  .string()
  .trim()
  .max(500)
  .transform((v) => (v && !/^https?:\/\//i.test(v) ? `https://${v}` : v))
  .pipe(z.union([z.literal(''), z.string().url('Adresse web invalide')]));

const ficheSchema = z.object({
  name: z.string().trim().min(2, 'Le nom est obligatoire').max(255),
  categoryId: z.string().uuid(),
  activityLabel: z.string().trim().max(160),
  tagline: z.string().trim().max(255),
  description: z.string().trim().max(5000),
  street: z.string().trim().max(255),
  postalCode: z.string().trim().max(10),
  phone: z.string().trim().max(32),
  email: z.union([z.literal(''), z.string().trim().email('Email invalide').max(255)]),
  website: optionalUrl,
  facebook: optionalUrl,
  instagram: optionalUrl,
  linkedin: optionalUrl,
  priceInfo: z.string().trim().max(500),
  serviceArea: z.string().trim().max(255),
  accessibilityInfo: z.string().trim().max(500),
  appointmentInfo: z.string().trim().max(500),
  appointmentsEnabled: z.enum(['on', '']).optional(),
  hours: z.string(),
  exceptions: z.string(),
  attributes: z.string(),
});

export async function saveFiche(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await proCtx(form.get('estId'));
  const raw = Object.fromEntries([...form.entries()].filter(([, v]) => typeof v === 'string'));
  const parsed = ficheSchema.safeParse({ appointmentsEnabled: '', ...raw });
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message ?? 'Formulaire invalide.' };
  const d = parsed.data;
  let hours, exceptions, attributeSlugs: string[];
  try {
    hours = hoursSchema.parse(JSON.parse(d.hours));
    exceptions = exceptionsSchema.parse(JSON.parse(d.exceptions));
    attributeSlugs = z.array(z.string().max(120)).max(80).parse(JSON.parse(d.attributes));
  } catch {
    return { status: 'error', message: 'Horaires ou services invalides.' };
  }
  for (const h of hours) if (h.opensAt >= h.closesAt) return { status: 'error', message: 'Une plage horaire se termine avant de commencer.' };
  const { categories, establishments, establishmentAttributes, attributes, exceptionalHours, openingHours } = await import('@/server/db/schema');
  const { inArray, isNull, or } = await import('drizzle-orm');
  const [cat] = await db
    .select()
    .from(categories)
    .where(and(eq(categories.id, d.categoryId), or(isNull(categories.territoryId), eq(categories.territoryId, ctx.est.territoryId))))
    .limit(1);
  if (!cat) return { status: 'error', message: 'Catégorie inconnue.' };

  const e = ctx.est;
  const socials = {
    ...(e.socials as Record<string, string>),
    facebook: d.facebook || undefined,
    instagram: d.instagram || undefined,
    linkedin: d.linkedin || undefined,
  };
  const before = {
    name: e.name,
    categoryId: e.categoryId,
    activityLabel: e.activityLabel,
    tagline: e.tagline,
    description: e.description,
    street: e.street,
    postalCode: e.postalCode,
    phone: e.phone,
    email: e.email,
    website: e.website,
    socials: e.socials,
    priceInfo: e.priceInfo,
    serviceArea: e.serviceArea,
    accessibilityInfo: e.accessibilityInfo,
    hours: e.hours.map((h) => `${h.weekday}:${h.opensAt.slice(0, 5)}-${h.closesAt.slice(0, 5)}`).sort(),
    attributes: e.attributes.map((a) => a.slug).sort(),
  };
  const after = {
    name: d.name,
    categoryId: d.categoryId,
    activityLabel: d.activityLabel || null,
    tagline: d.tagline || null,
    description: d.description || null,
    street: d.street || null,
    postalCode: d.postalCode || null,
    phone: d.phone.replace(/[^\d+]/g, '') || null,
    email: d.email || null,
    website: d.website || null,
    socials,
    priceInfo: d.priceInfo || null,
    serviceArea: d.serviceArea || null,
    accessibilityInfo: d.accessibilityInfo || null,
    hours: hours.map((h) => `${h.weekday}:${h.opensAt}-${h.closesAt}`).sort(),
    attributes: [...new Set(attributeSlugs)].sort(),
  };
  // Géocodage si l'adresse change (Base Adresse Nationale), sans bloquer l'enregistrement.
  let coords: { lat: number; lng: number } | null = null;
  if ((after.street ?? '') !== (e.street ?? '') || (after.postalCode ?? '') !== (e.postalCode ?? '')) {
    const { geocode } = await import('@/server/integrations/public-data');
    const g = after.street ? await geocode(`${after.street} ${after.postalCode ?? ''} ${e.commune.name}`, e.commune.inseeCode).catch(() => null) : null;
    if (g && g.score > 0.5) coords = { lat: g.lat, lng: g.lng };
  }
  const { recordRevision, refreshCompleteness, refreshSearchKeywords } = await import('@/server/services/establishments');
  const changes = await db.transaction(async (tx) => {
    await tx
      .update(establishments)
      .set({
        name: after.name,
        categoryId: after.categoryId,
        activityLabel: after.activityLabel,
        tagline: after.tagline,
        description: after.description,
        street: after.street,
        postalCode: after.postalCode,
        phone: after.phone,
        email: after.email,
        website: after.website,
        socials,
        priceInfo: after.priceInfo,
        serviceArea: after.serviceArea,
        accessibilityInfo: after.accessibilityInfo,
        appointmentsEnabled: ctx.limits.appointments ? d.appointmentsEnabled === 'on' : e.appointmentsEnabled,
        appointmentInfo: d.appointmentInfo || null,
        hoursConfirmedAt: new Date(),
        lastActivityAt: new Date(),
        status: e.status === 'PRECREATED' || e.status === 'TO_COMPLETE' ? e.status : e.status,
        ...(coords ?? {}),
      })
      .where(eq(establishments.id, e.id));
    await tx.delete(openingHours).where(eq(openingHours.establishmentId, e.id));
    if (hours.length)
      await tx.insert(openingHours).values(hours.map((h) => ({ establishmentId: e.id, weekday: h.weekday, opensAt: h.opensAt, closesAt: h.closesAt })));
    const today = new Date().toISOString().slice(0, 10);
    await tx.delete(exceptionalHours).where(and(eq(exceptionalHours.establishmentId, e.id), sql`${exceptionalHours.date} >= ${today}`));
    const futureExc = exceptions.filter((x) => x.date >= today);
    if (futureExc.length)
      await tx.insert(exceptionalHours).values(
        futureExc.map((x) => ({
          establishmentId: e.id,
          date: x.date,
          closed: x.closed,
          opensAt: x.closed ? null : (x.opensAt ?? null),
          closesAt: x.closed ? null : (x.closesAt ?? null),
          label: x.label || null,
        })),
      );
    await tx.delete(establishmentAttributes).where(eq(establishmentAttributes.establishmentId, e.id));
    if (after.attributes.length) {
      const attrRows = await tx
        .select({ id: attributes.id, slug: attributes.slug })
        .from(attributes)
        .where(and(inArray(attributes.slug, after.attributes), or(isNull(attributes.territoryId), eq(attributes.territoryId, e.territoryId))));
      if (attrRows.length)
        await tx
          .insert(establishmentAttributes)
          .values(attrRows.map((a) => ({ establishmentId: e.id, attributeId: a.id })))
          .onConflictDoNothing();
    }
    const ch = await recordRevision(e.id, before, after, { userId: ctx.actor.user.id, source: ctx.role === 'STAFF' ? 'COLLECTIVITE' : 'PRO' }, tx);
    await refreshSearchKeywords(e.id, tx);
    await refreshCompleteness(e.id, tx);
    return ch;
  });
  if (changes.length) {
    await audit({
      actor: actorOf(ctx),
      category: 'MODIFICATION',
      action: 'establishment.update',
      summary: `Fiche « ${after.name} » modifiée : ${[...new Set(changes.map((c) => c.label))].slice(0, 4).join(', ')}`,
      territoryId: e.territoryId,
      targetType: 'establishment',
      targetId: e.id,
    });
  }
  refresh(ctx, `${ctx.base}/fiche`);
  return { status: 'ok', message: changes.length ? 'Enregistré · publié instantanément' : 'Aucune modification à enregistrer.' };
}

/** Réécriture de la description par l'assistant (quota de l'offre Essentiel). */
export async function improveDescriptionAction(
  estId: string,
  current: string,
): Promise<{ ok: boolean; description?: string; seoTitle?: string; message?: string; source?: 'ai' | 'rules' }> {
  const ctx = await proCtx(estId);
  const { companyAiUses } = await import('@/server/ai/client');
  if (ctx.limits.aiPerMonth !== null && (await companyAiUses(ctx.est.companyId)) >= ctx.limits.aiPerMonth) {
    return { ok: false, message: `Vous avez utilisé vos ${ctx.limits.aiPerMonth} rédactions assistées du mois. Passez Premium pour un usage illimité.` };
  }
  const { improveDescription } = await import('@/server/ai/features');
  const e = ctx.est;
  const res = await improveDescription(
    {
      current: current.slice(0, 5000),
      name: e.name,
      activity: e.activity,
      commune: e.commune.name,
      territoryName: ctx.territory.name,
      products: e.products.map((p) => p.name),
      services: e.attributes.filter((a) => a.group !== 'PAYMENT').map((a) => a.label),
      payments: e.attributes.filter((a) => a.group === 'PAYMENT').map((a) => a.label),
    },
    { territoryId: e.territoryId, companyId: e.companyId, userId: ctx.actor.user.id },
  );
  return { ok: true, description: res.description, seoTitle: res.seoTitle, source: res.source };
}

// ─── Photos ────────────────────────────────────────────────────────────────

const MAX_PHOTOS = 20;

export async function uploadPhotos(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await proCtx(form.get('estId'));
  const files = form.getAll('photos').filter((f): f is File => f instanceof File && f.size > 0);
  if (!files.length) return { status: 'error', message: 'Choisissez au moins une photo.' };
  const { media } = await import('@/server/db/schema');
  const { count: countFn } = await import('drizzle-orm');
  const [{ n }] = await db
    .select({ n: countFn() })
    .from(media)
    .where(and(eq(media.establishmentId, ctx.est.id), eq(media.kind, 'IMAGE')));
  if (Number(n) + files.length > MAX_PHOTOS) return { status: 'error', message: `${MAX_PHOTOS} photos maximum : supprimez-en avant d'en ajouter.` };
  const { saveImageUpload, MediaError } = await import('@/server/media');
  const tag =
    z
      .string()
      .max(64)
      .optional()
      .parse(form.get('tag') || undefined) ?? null;
  let added = 0;
  try {
    for (const [i, f] of files.slice(0, 10).entries()) {
      await saveImageUpload(f, {
        ownerType: 'ESTABLISHMENT',
        ownerId: ctx.est.id,
        territoryId: ctx.est.territoryId,
        establishmentId: ctx.est.id,
        alt: ctx.est.name,
        tag,
        sortOrder: Number(n) + i,
        uploadedById: ctx.actor.user.id,
      });
      added++;
    }
  } catch (err) {
    if (added) await afterPhotos(ctx);
    return { status: 'error', message: err instanceof MediaError ? err.message : "Une photo n'a pas pu être enregistrée." };
  }
  await afterPhotos(ctx);
  return { status: 'ok', message: `${added} photo${added > 1 ? 's ajoutées' : ' ajoutée'}.` };
}

/** Logo de l'établissement (hors galerie) : réencodé en WebP, transparence conservée. */
export async function uploadLogo(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await proCtx(form.get('estId'));
  const file = form.get('logo');
  if (!(file instanceof File) || !file.size) return { status: 'error', message: 'Choisissez une image.' };
  const { saveImageUpload, MediaError } = await import('@/server/media');
  const { establishments } = await import('@/server/db/schema');
  try {
    const m = await saveImageUpload(file, {
      ownerType: 'ESTABLISHMENT_LOGO',
      ownerId: ctx.est.id,
      territoryId: ctx.est.territoryId,
      alt: `Logo ${ctx.est.name}`,
      uploadedById: ctx.actor.user.id,
    });
    const variants = (m.variants ?? {}) as Record<string, string>;
    await db
      .update(establishments)
      .set({ logoUrl: variants.w320 ?? variants.w640 ?? m.url, updatedAt: new Date(), lastActivityAt: new Date() })
      .where(eq(establishments.id, ctx.est.id));
  } catch (err) {
    return { status: 'error', message: err instanceof MediaError ? err.message : "Le logo n'a pas pu être enregistré." };
  }
  const { refreshCompleteness } = await import('@/server/services/establishments');
  await refreshCompleteness(ctx.est.id);
  await audit({
    actor: actorOf(ctx),
    category: 'MODIFICATION',
    action: 'establishment.logo',
    summary: `Logo de « ${ctx.est.name} » mis à jour`,
    territoryId: ctx.est.territoryId,
    targetType: 'establishment',
    targetId: ctx.est.id,
  });
  refresh(ctx, `${ctx.base}/fiche`);
  return { status: 'ok', message: 'Logo enregistré.' };
}

export async function removeLogo(form: FormData): Promise<void> {
  const ctx = await proCtx(form.get('estId'));
  const { establishments } = await import('@/server/db/schema');
  await db.update(establishments).set({ logoUrl: null, updatedAt: new Date() }).where(eq(establishments.id, ctx.est.id));
  const { refreshCompleteness } = await import('@/server/services/establishments');
  await refreshCompleteness(ctx.est.id);
  refresh(ctx, `${ctx.base}/fiche`);
}

async function afterPhotos(ctx: Awaited<ReturnType<typeof proCtx>>) {
  const { establishments, media } = await import('@/server/db/schema');
  const { asc } = await import('drizzle-orm');
  const [first] = await db
    .select()
    .from(media)
    .where(and(eq(media.establishmentId, ctx.est.id), eq(media.kind, 'IMAGE')))
    .orderBy(asc(media.sortOrder), asc(media.createdAt))
    .limit(1);
  const cover = first ? ((first.variants as Record<string, string>).w1280 ?? first.url) : null;
  await db.update(establishments).set({ coverUrl: cover, lastActivityAt: new Date() }).where(eq(establishments.id, ctx.est.id));
  const { refreshCompleteness } = await import('@/server/services/establishments');
  await refreshCompleteness(ctx.est.id);
  refresh(ctx, `${ctx.base}/fiche`);
}

export async function photoAction(form: FormData): Promise<void> {
  const ctx = await proCtx(form.get('estId'));
  const id = uuid.parse(form.get('photoId'));
  const op = z.enum(['delete', 'up', 'down', 'first', 'tag']).parse(form.get('op'));
  const { media } = await import('@/server/db/schema');
  const { asc } = await import('drizzle-orm');
  const list = await db
    .select()
    .from(media)
    .where(and(eq(media.establishmentId, ctx.est.id), eq(media.kind, 'IMAGE')))
    .orderBy(asc(media.sortOrder), asc(media.createdAt));
  const idx = list.findIndex((m) => m.id === id);
  if (idx < 0) return;
  if (op === 'delete') {
    const { deleteMediaFiles } = await import('@/server/media');
    await db.delete(media).where(eq(media.id, id));
    await deleteMediaFiles(list[idx]).catch(() => {});
    list.splice(idx, 1);
  } else if (op === 'tag') {
    const tag = z
      .string()
      .max(64)
      .parse(form.get('tag') ?? '');
    await db
      .update(media)
      .set({ tag: tag || null })
      .where(eq(media.id, id));
  } else {
    const [item] = list.splice(idx, 1);
    const to = op === 'first' ? 0 : op === 'up' ? Math.max(0, idx - 1) : Math.min(list.length, idx + 1);
    list.splice(to, 0, item);
  }
  if (op !== 'tag') await Promise.all(list.map((m, i) => db.update(media).set({ sortOrder: i }).where(eq(media.id, m.id))));
  await afterPhotos(ctx);
}

// ─── Produits & savoir-faire ────────────────────────────────────────────────

export async function saveProduct(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await proCtx(form.get('estId'));
  const parsed = z
    .object({
      productId: z.string().uuid().optional().or(z.literal('')),
      name: z.string().trim().min(2, 'Nom du produit requis').max(255),
      priceText: z.string().trim().max(120),
      description: z.string().trim().max(1000),
      kind: z.enum(['PRODUCT', 'SERVICE']),
    })
    .safeParse(Object.fromEntries([...form.entries()].filter(([, v]) => typeof v === 'string')));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  const { products } = await import('@/server/db/schema');
  let imageUrl: string | undefined;
  const file = form.get('image');
  if (file instanceof File && file.size > 0) {
    const { saveImageUpload, MediaError } = await import('@/server/media');
    try {
      const m = await saveImageUpload(file, {
        ownerType: 'PRODUCT',
        territoryId: ctx.est.territoryId,
        establishmentId: null,
        uploadedById: ctx.actor.user.id,
        alt: d.name,
      });
      imageUrl = (m.variants as Record<string, string>).w640 ?? m.url;
    } catch (err) {
      return { status: 'error', message: err instanceof MediaError ? err.message : "L'image n'a pas pu être enregistrée." };
    }
  }
  if (d.productId) {
    await db
      .update(products)
      .set({ name: d.name, priceText: d.priceText || null, description: d.description || null, kind: d.kind, ...(imageUrl ? { imageUrl } : {}) })
      .where(and(eq(products.id, d.productId), eq(products.establishmentId, ctx.est.id)));
  } else {
    const n = ctx.est.products.length;
    if (n >= 40) return { status: 'error', message: '40 produits maximum.' };
    await db.insert(products).values({
      establishmentId: ctx.est.id,
      name: d.name,
      priceText: d.priceText || null,
      description: d.description || null,
      kind: d.kind,
      imageUrl: imageUrl ?? null,
      sortOrder: n,
    });
  }
  const { refreshCompleteness, refreshSearchKeywords } = await import('@/server/services/establishments');
  await refreshSearchKeywords(ctx.est.id);
  await refreshCompleteness(ctx.est.id);
  refresh(ctx, `${ctx.base}/fiche`);
  return { status: 'ok', message: d.productId ? 'Produit mis à jour.' : 'Produit ajouté.' };
}

export async function deleteProduct(form: FormData): Promise<void> {
  const ctx = await proCtx(form.get('estId'));
  const id = uuid.parse(form.get('productId'));
  const { products } = await import('@/server/db/schema');
  await db.delete(products).where(and(eq(products.id, id), eq(products.establishmentId, ctx.est.id)));
  const { refreshCompleteness } = await import('@/server/services/establishments');
  await refreshCompleteness(ctx.est.id);
  refresh(ctx, `${ctx.base}/fiche`);
}

// ─── Publications ───────────────────────────────────────────────────────────

export type Variants = {
  fiche: string;
  facebook: string;
  instagram: string;
  linkedin: string;
  emailSubject: string;
  emailBody: string;
  seoTitle: string;
  source: 'ai' | 'rules';
};

const KIND = z.enum(['NEWS', 'PROMO', 'EVENT', 'NOUVEAUTE', 'HOURS', 'JOB']);

/** Rédaction multicanal par l'assistant (fiche, Facebook, Instagram, LinkedIn, email, titre SEO). */
export async function generateVariants(
  estId: string,
  input: { kind: string; draft: string; tone: string },
): Promise<{ ok: true; variants: Variants } | { ok: false; message: string }> {
  const ctx = await proCtx(estId);
  const kind = KIND.parse(input.kind);
  const tone = z.enum(['Chaleureux', 'Pro', 'Fun']).parse(input.tone);
  const draft = z.string().trim().min(3, 'Décrivez votre actualité en quelques mots.').max(500).safeParse(input.draft);
  if (!draft.success) return { ok: false, message: draft.error.issues[0]?.message ?? 'Texte invalide.' };
  const { companyAiUses } = await import('@/server/ai/client');
  if (ctx.limits.aiPerMonth !== null && (await companyAiUses(ctx.est.companyId)) >= ctx.limits.aiPerMonth) {
    return { ok: false, message: `Vos ${ctx.limits.aiPerMonth} rédactions assistées du mois sont utilisées. L'offre Premium les rend illimitées.` };
  }
  const { weeklyRows } = await import('@/lib/hours');
  const open = weeklyRows(ctx.est.hours).filter((r) => !r.closed);
  const summary = open.length ? `du ${open[0].day.toLowerCase()} au ${open[open.length - 1].day.toLowerCase()}` : null;
  const { writeMultichannel } = await import('@/server/ai/features');
  const out = await writeMultichannel(
    {
      draft: draft.data,
      kind,
      tone,
      establishment: {
        name: ctx.est.name,
        activity: ctx.est.activity,
        family: ctx.est.family,
        commune: ctx.est.commune.name,
        address: ctx.est.street,
        territoryName: ctx.territory.name,
        departmentCode: ctx.est.commune.departmentCode,
        openingSummary: summary,
        signature: `${ctx.actor.user.firstName} & l'équipe ${ctx.est.name}`.trim(),
      },
    },
    { territoryId: ctx.est.territoryId, companyId: ctx.est.companyId, userId: ctx.actor.user.id },
  );
  return { ok: true, variants: out };
}

const CHANNELS = z.enum(['FICHE', 'COMMUNE', 'TERRITOIRE', 'NEWSLETTER', 'SOCIAL']);

export async function createPost(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await proCtx(form.get('estId'));
  const parsed = z
    .object({
      kind: KIND,
      title: z.string().trim().min(3, 'Donnez un titre à votre publication.').max(255),
      body: z.string().trim().max(5000),
      promoLabel: z.string().trim().max(32).optional(),
      validTo: z.union([z.literal(''), z.string().regex(/^\d{4}-\d{2}-\d{2}$/)]).optional(),
      publishAt: z.union([z.literal(''), z.string().regex(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/)]).optional(),
      variants: z.string().optional(),
      aiGenerated: z.enum(['1', '0']).optional(),
    })
    .safeParse(Object.fromEntries([...form.entries()].filter(([, v]) => typeof v === 'string')));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  let channels = form.getAll('channels').map((c) => CHANNELS.parse(c));
  if (!channels.includes('FICHE')) channels = ['FICHE', ...channels];
  // Canaux réservés aux offres payantes
  if (!ctx.limits.newsletterChannel) channels = channels.filter((c) => c !== 'NEWSLETTER');
  if (!ctx.limits.socialChannel) channels = channels.filter((c) => c !== 'SOCIAL');
  const { postsThisMonth } = await import('@/server/services/pro');
  if (ctx.limits.postsPerMonth !== null && (await postsThisMonth(ctx.est.id)) >= ctx.limits.postsPerMonth) {
    return {
      status: 'error',
      message: `L'offre Essentiel comprend ${ctx.limits.postsPerMonth} publications par mois. Passez Premium pour publier sans limite.`,
    };
  }
  const { fromParisLocal } = await import('@/lib/format');
  let publishAt: Date | null = null;
  if (d.publishAt) {
    if (!ctx.limits.scheduling) return { status: 'error', message: 'La programmation des publications est incluse dans l’offre Premium.' };
    const [date, time] = d.publishAt.split('T');
    publishAt = fromParisLocal(date, time);
    if (publishAt.getTime() < Date.now() - 60_000) return { status: 'error', message: 'Choisissez une date de programmation à venir.' };
  }
  let imageUrl: string | null = null;
  const file = form.get('image');
  if (file instanceof File && file.size > 0) {
    const { saveImageUpload, MediaError } = await import('@/server/media');
    try {
      const m = await saveImageUpload(file, {
        ownerType: 'POST',
        territoryId: ctx.est.territoryId,
        establishmentId: null,
        uploadedById: ctx.actor.user.id,
        alt: d.title,
      });
      imageUrl = (m.variants as Record<string, string>).w1280 ?? m.url;
    } catch (err) {
      return { status: 'error', message: err instanceof MediaError ? err.message : "L'image n'a pas pu être enregistrée." };
    }
  }
  const settings = (ctx.territory.settings ?? {}) as { postModeration?: 'PRE' | 'POST' };
  const moderated = settings.postModeration === 'PRE' && ctx.role !== 'STAFF';
  const status = publishAt && publishAt.getTime() > Date.now() + 60_000 ? 'SCHEDULED' : moderated ? 'PENDING' : 'PUBLISHED';
  let variants = {};
  try {
    variants = d.variants ? JSON.parse(d.variants) : {};
  } catch {
    variants = {};
  }
  const { posts, establishments } = await import('@/server/db/schema');
  const [post] = await db
    .insert(posts)
    .values({
      territoryId: ctx.est.territoryId,
      communeId: ctx.est.communeId,
      establishmentId: ctx.est.id,
      authorType: 'ESTABLISHMENT',
      kind: d.kind,
      status,
      title: d.title,
      body: d.body,
      imageUrl: imageUrl ?? ctx.est.coverUrl,
      promoLabel: d.kind === 'PROMO' ? d.promoLabel || null : null,
      validTo: d.validTo || null,
      expiresAt: d.validTo ? fromParisLocal(d.validTo, '23:59') : null,
      channels,
      variants,
      aiGenerated: d.aiGenerated === '1',
      publishAt,
      publishedAt: status === 'PUBLISHED' ? new Date() : null,
      createdById: ctx.actor.user.id,
    })
    .returning();
  await db.update(establishments).set({ lastActivityAt: new Date() }).where(eq(establishments.id, ctx.est.id));
  if (status === 'PUBLISHED' && channels.includes('SOCIAL')) {
    const { enqueue } = await import('@/server/queue');
    await enqueue('posts.social-sync', { postId: post.id });
  }
  const { refreshCompleteness } = await import('@/server/services/establishments');
  await refreshCompleteness(ctx.est.id);
  refresh(ctx, `${ctx.base}/publications`);
  return {
    status: 'ok',
    message:
      status === 'SCHEDULED'
        ? 'Publication programmée.'
        : status === 'PENDING'
          ? 'Publication envoyée : elle sera visible après validation par la collectivité.'
          : 'Publié ! Votre actualité est en ligne.',
  };
}

export async function deletePost(form: FormData): Promise<void> {
  const ctx = await proCtx(form.get('estId'));
  const id = uuid.parse(form.get('postId'));
  const { posts } = await import('@/server/db/schema');
  await db
    .update(posts)
    .set({ status: 'ARCHIVED' })
    .where(and(eq(posts.id, id), eq(posts.establishmentId, ctx.est.id)));
  refresh(ctx, `${ctx.base}/publications`);
}

// ─── Offre & abonnement ─────────────────────────────────────────────────────

export async function changePlanAction(_prev: ActionState, form: FormData): Promise<ActionState & { upgraded?: boolean; redirectUrl?: string }> {
  const ctx = await proCtx(form.get('estId'));
  if (ctx.role === 'MEMBER') return { status: 'error', message: "Seul le ou la titulaire du compte peut changer d'offre." };
  const plan = z.enum(['ESSENTIEL', 'PREMIUM', 'COMMUNICATION']).parse(form.get('plan'));
  if (plan === ctx.plan) return { status: 'ok', message: 'C’est déjà votre offre.' };
  const billing = z
    .object({
      billingName: z.string().trim().min(2, 'Indiquez la raison sociale à facturer').max(255),
      billingEmail: z.string().trim().email('Email de facturation invalide'),
      billingAddress: z.string().trim().min(5, 'Indiquez l’adresse de facturation').max(500),
      accept: z.literal('on', { message: 'Merci d’accepter les conditions générales de vente.' }),
    })
    .safeParse(Object.fromEntries(form));
  if (plan !== 'ESSENTIEL' && !billing.success) return { status: 'error', message: billing.error.issues[0]?.message };
  const { companies } = await import('@/server/db/schema');
  if (billing.success) {
    await db
      .update(companies)
      .set({ billingName: billing.data.billingName, billingEmail: billing.data.billingEmail, billingAddress: billing.data.billingAddress })
      .where(eq(companies.id, ctx.est.companyId));
  }
  const { env } = await import('@/server/env');
  if (plan !== 'ESSENTIEL' && env.STRIPE_SECRET_KEY) {
    const { createCheckoutSession } = await import('@/server/services/stripe');
    const url = await createCheckoutSession({
      companyId: ctx.est.companyId,
      plan,
      estId: ctx.est.id,
      email: billing.success ? billing.data.billingEmail : ctx.actor.user.email,
    });
    if (url) return { status: 'ok', redirectUrl: url };
  }
  const { changeCompanyPlan } = await import('@/server/services/billing');
  await changeCompanyPlan(ctx.est.companyId, plan, 'MANUAL');
  await audit({
    actor: actorOf(ctx),
    category: 'FACTURATION',
    action: 'company.plan_changed',
    summary: `${ctx.est.name} : passage de l'offre ${ctx.plan} à ${plan}`,
    territoryId: ctx.est.territoryId,
    targetType: 'company',
    targetId: ctx.est.companyId,
  });
  refresh(ctx, `${ctx.base}/offre`);
  return plan === 'ESSENTIEL'
    ? { status: 'ok', message: 'Vous êtes revenu·e à l’offre Essentiel. Votre fiche reste en ligne, gratuitement.' }
    : { status: 'ok', upgraded: true, message: 'Bienvenue ! Vos nouvelles fonctionnalités sont actives. La facture est disponible ci-dessous.' };
}

// ─── Messagerie ─────────────────────────────────────────────────────────────

export async function replyMessage(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await proCtx(form.get('estId'));
  const id = uuid.parse(form.get('messageId'));
  const reply = z.string().trim().min(2, 'Votre réponse est vide.').max(5000).safeParse(form.get('reply'));
  if (!reply.success) return { status: 'error', message: reply.error.issues[0]?.message };
  const [msg] = await db
    .select()
    .from(messages)
    .where(and(eq(messages.id, id), eq(messages.establishmentId, ctx.est.id)))
    .limit(1);
  if (!msg) return { status: 'error', message: 'Message introuvable.' };
  if (!msg.senderEmail) return { status: 'error', message: "Cette personne n'a pas laissé d'email : rappelez-la plutôt." };
  const { sendEmail } = await import('@/server/mail/send');
  const { messageReplyTemplate } = await import('@/server/mail/templates');
  await sendEmail({
    ...messageReplyTemplate({
      to: msg.senderEmail,
      establishmentName: ctx.est.name,
      reply: reply.data,
      original: msg.body,
      replyTo: ctx.est.email ?? ctx.actor.user.email,
    }),
    territoryId: ctx.est.territoryId,
  });
  await db
    .update(messages)
    .set({ status: 'REPLIED', repliedAt: new Date(), readAt: msg.readAt ?? new Date() })
    .where(eq(messages.id, id));
  refresh(ctx, `${ctx.base}/messages`);
  return { status: 'ok', message: 'Réponse envoyée par email.' };
}

// ─── Rendez-vous ────────────────────────────────────────────────────────────

export async function respondAppointment(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await proCtx(form.get('estId'));
  const id = uuid.parse(form.get('appointmentId'));
  const decision = z.enum(['CONFIRMED', 'DECLINED', 'CANCELLED']).parse(form.get('decision'));
  const note = z
    .string()
    .trim()
    .max(1000)
    .parse(form.get('note') ?? '');
  const { appointments } = await import('@/server/db/schema');
  const [a] = await db
    .select()
    .from(appointments)
    .where(and(eq(appointments.id, id), eq(appointments.establishmentId, ctx.est.id)))
    .limit(1);
  if (!a) return { status: 'error', message: 'Demande introuvable.' };
  // Une demande se confirme ou se décline ; seul un rendez-vous confirmé peut être annulé.
  if (decision === 'CANCELLED' ? a.status !== 'CONFIRMED' : a.status !== 'REQUESTED') return { status: 'error', message: 'Ce rendez-vous a déjà été traité.' };
  await db
    .update(appointments)
    .set({ status: decision, responseNote: note || null, respondedAt: new Date() })
    .where(eq(appointments.id, id));
  const when = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'full', timeStyle: 'short', timeZone: 'Europe/Paris' }).format(a.preferredAt);
  const { sendEmail } = await import('@/server/mail/send');
  const { appointmentResponseTemplate } = await import('@/server/mail/templates');
  await sendEmail({
    ...appointmentResponseTemplate({
      to: a.email,
      establishmentName: ctx.est.name,
      confirmed: decision === 'CONFIRMED',
      cancelled: decision === 'CANCELLED',
      when: `le ${when}`,
      note: note || null,
    }),
    territoryId: ctx.est.territoryId,
  });
  refresh(ctx, `${ctx.base}/rendez-vous`);
  return {
    status: 'ok',
    message:
      decision === 'CONFIRMED' ? 'Rendez-vous confirmé au client.' : decision === 'CANCELLED' ? 'Annulation envoyée au client.' : 'Réponse envoyée au client.',
  };
}

// ─── Recrutement ────────────────────────────────────────────────────────────

export async function saveJob(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await proCtx(form.get('estId'));
  if (!ctx.modules.has('JOBS')) return { status: 'error', message: 'Le module Recrutement n’est pas activé sur votre territoire.' };
  const parsed = z
    .object({
      jobId: z.union([z.literal(''), z.string().uuid()]),
      title: z.string().trim().min(3, 'Intitulé du poste requis').max(255),
      contractType: z.enum(['CDI', 'CDD', 'ALTERNANCE', 'SAISONNIER', 'STAGE', 'INTERIM', 'INDEPENDANT']),
      startText: z.string().trim().max(160),
      salaryText: z.string().trim().max(160),
      workTimeText: z.string().trim().max(160),
      description: z.string().trim().min(20, 'Décrivez le poste en quelques phrases.').max(5000),
      missions: z.string().max(3000),
      profile: z.string().max(3000),
      applyEmail: z.union([z.literal(''), z.string().trim().email('Email invalide')]),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  const { jobs } = await import('@/server/db/schema');
  const { slugify } = await import('@/lib/slug');
  const lines = (s: string) =>
    s
      .split('\n')
      .map((l) => l.replace(/^[-•*]\s*/, '').trim())
      .filter(Boolean)
      .slice(0, 12);
  const values = {
    title: d.title,
    contractType: d.contractType,
    startText: d.startText || null,
    salaryText: d.salaryText || null,
    workTimeText: d.workTimeText || null,
    description: d.description,
    missions: lines(d.missions),
    profile: lines(d.profile),
    applyEmail: d.applyEmail || null,
  };
  if (d.jobId) {
    await db
      .update(jobs)
      .set(values)
      .where(and(eq(jobs.id, d.jobId), eq(jobs.establishmentId, ctx.est.id)));
  } else {
    if (!ctx.limits.jobs) {
      const { count: c } = await import('drizzle-orm');
      const [{ n }] = await db
        .select({ n: c() })
        .from(jobs)
        .where(and(eq(jobs.establishmentId, ctx.est.id), eq(jobs.status, 'PUBLISHED')));
      if (Number(n) >= 1)
        return { status: 'error', message: 'L’offre Essentiel permet une offre d’emploi publiée à la fois. Passez Premium pour en publier davantage.' };
    }
    await db.insert(jobs).values({
      ...values,
      territoryId: ctx.est.territoryId,
      communeId: ctx.est.communeId,
      establishmentId: ctx.est.id,
      slug: `${slugify(`${d.title}-${ctx.est.name}`).slice(0, 120)}-${Date.now().toString(36).slice(-4)}`,
      status: 'PUBLISHED',
      publishedAt: new Date(),
      expiresAt: new Date(Date.now() + 60 * 86_400_000),
      createdById: ctx.actor.user.id,
    });
  }
  refresh(ctx, `${ctx.base}/emploi`);
  return { status: 'ok', message: d.jobId ? 'Offre mise à jour.' : 'Offre publiée pour 60 jours.' };
}

export async function jobStatus(form: FormData): Promise<void> {
  const ctx = await proCtx(form.get('estId'));
  const id = uuid.parse(form.get('jobId'));
  const status = z.enum(['PUBLISHED', 'FILLED', 'EXPIRED']).parse(form.get('status'));
  const { jobs } = await import('@/server/db/schema');
  await db
    .update(jobs)
    .set({ status, ...(status === 'PUBLISHED' ? { publishedAt: new Date(), expiresAt: new Date(Date.now() + 60 * 86_400_000) } : {}) })
    .where(and(eq(jobs.id, id), eq(jobs.establishmentId, ctx.est.id)));
  refresh(ctx, `${ctx.base}/emploi`);
}

export async function applicationAction(form: FormData): Promise<void> {
  const ctx = await proCtx(form.get('estId'));
  const id = uuid.parse(form.get('applicationId'));
  const status = z.enum(['READ', 'REPLIED', 'ARCHIVED']).parse(form.get('status'));
  const notify = form.get('notify') as string | null;
  const { jobApplications, jobs } = await import('@/server/db/schema');
  const [app] = await db
    .select({ a: jobApplications, j: jobs })
    .from(jobApplications)
    .innerJoin(jobs, eq(jobs.id, jobApplications.jobId))
    .where(and(eq(jobApplications.id, id), eq(jobApplications.establishmentId, ctx.est.id)))
    .limit(1);
  if (!app) return;
  await db.update(jobApplications).set({ status }).where(eq(jobApplications.id, id));
  if (notify === 'accept' || notify === 'decline') {
    const { sendEmail } = await import('@/server/mail/send');
    const { applicationStatusTemplate } = await import('@/server/mail/templates');
    await sendEmail({
      ...applicationStatusTemplate({ to: app.a.email, jobTitle: app.j.title, companyName: ctx.est.name, accepted: notify === 'accept', note: null }),
      territoryId: ctx.est.territoryId,
    });
  }
  refresh(ctx, `${ctx.base}/emploi`);
}

// ─── Événements ─────────────────────────────────────────────────────────────

export async function saveEvent(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await proCtx(form.get('estId'));
  const parsed = z
    .object({
      eventId: z.union([z.literal(''), z.string().uuid()]),
      title: z.string().trim().min(3, 'Titre requis').max(255),
      kind: z.enum(['MARCHE', 'DEGUSTATION', 'PORTES_OUVERTES', 'ATELIER', 'CONCERT', 'ANIMATION', 'SALON', 'AUTRE']),
      date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Date requise'),
      start: z.string().regex(/^\d{2}:\d{2}$/, 'Heure de début requise'),
      end: z.union([z.literal(''), z.string().regex(/^\d{2}:\d{2}$/)]),
      locationName: z.string().trim().max(255),
      address: z.string().trim().max(255),
      priceText: z.string().trim().max(120),
      capacity: z.union([z.literal(''), z.coerce.number().int().min(1).max(100000)]),
      registrationUrl: z.union([z.literal(''), z.string().trim().url('Lien d’inscription invalide')]),
      description: z.string().trim().min(10, 'Décrivez votre événement en quelques mots.').max(5000),
    })
    .safeParse(Object.fromEntries([...form.entries()].filter(([, v]) => typeof v === 'string')));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  const { fromParisLocal } = await import('@/lib/format');
  const startsAt = fromParisLocal(d.date, d.start);
  const endsAt = d.end ? fromParisLocal(d.date, d.end) : null;
  if (endsAt && endsAt <= startsAt) return { status: 'error', message: 'L’heure de fin doit suivre l’heure de début.' };
  if (!d.eventId && startsAt.getTime() < Date.now()) return { status: 'error', message: 'Choisissez une date à venir.' };
  let imageUrl: string | undefined;
  const file = form.get('image');
  if (file instanceof File && file.size > 0) {
    const { saveImageUpload, MediaError } = await import('@/server/media');
    try {
      const m = await saveImageUpload(file, {
        ownerType: 'EVENT',
        territoryId: ctx.est.territoryId,
        establishmentId: null,
        uploadedById: ctx.actor.user.id,
        alt: d.title,
      });
      imageUrl = (m.variants as Record<string, string>).w1280 ?? m.url;
    } catch (err) {
      return { status: 'error', message: err instanceof MediaError ? err.message : "L'image n'a pas pu être enregistrée." };
    }
  }
  const { events } = await import('@/server/db/schema');
  const { EVENT_PROGRAM_TEMPLATES } = await import('@/lib/constants');
  const values = {
    title: d.title,
    kind: d.kind,
    startsAt,
    endsAt,
    locationName: d.locationName || ctx.est.name,
    address: d.address || [ctx.est.street, ctx.est.commune.name].filter(Boolean).join(', '),
    priceText: d.priceText || null,
    capacity: d.capacity === '' ? null : d.capacity,
    registrationUrl: d.registrationUrl || null,
    description: d.description,
    lat: ctx.est.lat,
    lng: ctx.est.lng,
    ...(imageUrl ? { imageUrl } : {}),
  };
  if (d.eventId) {
    await db
      .update(events)
      .set(values)
      .where(and(eq(events.id, d.eventId), eq(events.establishmentId, ctx.est.id)));
  } else {
    const { slugify } = await import('@/lib/slug');
    await db.insert(events).values({
      ...values,
      territoryId: ctx.est.territoryId,
      communeId: ctx.est.communeId,
      establishmentId: ctx.est.id,
      authorType: 'ESTABLISHMENT',
      slug: `${slugify(`${d.title}-${d.date}`).slice(0, 140)}-${Date.now().toString(36).slice(-3)}`,
      program: EVENT_PROGRAM_TEMPLATES[d.kind] ?? [],
      imageUrl: imageUrl ?? ctx.est.coverUrl,
      status: 'PUBLISHED',
      accessibilityText: ctx.est.attributes.some((a) => a.slug === 'acces-pmr') ? 'Accès PMR' : null,
      createdById: ctx.actor.user.id,
    });
  }
  refresh(ctx, `${ctx.base}/evenements`);
  return { status: 'ok', message: d.eventId ? 'Événement mis à jour.' : 'Événement publié dans l’agenda du territoire.' };
}

export async function deleteEvent(form: FormData): Promise<void> {
  const ctx = await proCtx(form.get('estId'));
  const id = uuid.parse(form.get('eventId'));
  const { events } = await import('@/server/db/schema');
  await db
    .update(events)
    .set({ status: 'ARCHIVED' })
    .where(and(eq(events.id, id), eq(events.establishmentId, ctx.est.id)));
  refresh(ctx, `${ctx.base}/evenements`);
}

// ─── Équipe ─────────────────────────────────────────────────────────────────

export async function inviteMember(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await proCtx(form.get('estId'));
  if (ctx.role === 'MEMBER') return { status: 'error', message: 'Seul le ou la titulaire peut inviter des collaborateurs.' };
  const email = z.string().trim().toLowerCase().email('Email invalide').safeParse(form.get('email'));
  if (!email.success) return { status: 'error', message: email.error.issues[0]?.message };
  const role = z.enum(['OWNER', 'EDITOR']).parse(form.get('role') ?? 'EDITOR');
  const { rateLimit } = await import('@/server/auth/rate-limit');
  if (!(await rateLimit(`invite:${ctx.actor.user.id}`, 20, 86_400)).ok) return { status: 'error', message: 'Trop d’invitations aujourd’hui.' };
  const { tokens, companies } = await import('@/server/db/schema');
  const { randomToken, sha256 } = await import('@/server/crypto');
  const token = randomToken(32);
  await db.insert(tokens).values({
    kind: 'INVITE_MEMBER',
    tokenHash: sha256(token),
    email: email.data,
    payload: { companyId: ctx.est.companyId, role, establishmentId: ctx.est.id },
    expiresAt: new Date(Date.now() + 7 * 86_400_000),
    createdById: ctx.actor.user.id,
  });
  const [co] = await db.select().from(companies).where(eq(companies.id, ctx.est.companyId)).limit(1);
  const { sendEmail } = await import('@/server/mail/send');
  const { memberInvitationTemplate } = await import('@/server/mail/templates');
  const { appUrl } = await import('@/server/urls');
  const { fullName } = await import('@/lib/format');
  await sendEmail({
    ...memberInvitationTemplate({
      to: email.data,
      inviter: fullName(ctx.actor.user),
      companyName: co?.tradeName ?? ctx.est.name,
      url: appUrl(`/invitation/${token}`),
    }),
    territoryId: ctx.est.territoryId,
  });
  await audit({
    actor: actorOf(ctx),
    category: 'SECURITE',
    action: 'company.member_invited',
    summary: `Invitation de ${email.data} (${role === 'OWNER' ? 'titulaire' : 'collaborateur'})`,
    territoryId: ctx.est.territoryId,
    targetType: 'company',
    targetId: ctx.est.companyId,
  });
  refresh(ctx, `${ctx.base}/equipe`);
  return { status: 'ok', message: `Invitation envoyée à ${email.data} (valable 7 jours).` };
}

export async function removeMember(form: FormData): Promise<void> {
  const ctx = await proCtx(form.get('estId'));
  if (ctx.role === 'MEMBER') return;
  const userId = uuid.parse(form.get('userId'));
  const { companyMembers } = await import('@/server/db/schema');
  const members = await db.select().from(companyMembers).where(eq(companyMembers.companyId, ctx.est.companyId));
  const target = members.find((m) => m.userId === userId);
  if (!target) return;
  if (target.role === 'OWNER' && members.filter((m) => m.role === 'OWNER').length <= 1) return; // au moins un titulaire
  await db.delete(companyMembers).where(and(eq(companyMembers.companyId, ctx.est.companyId), eq(companyMembers.userId, userId)));
  await audit({
    actor: actorOf(ctx),
    category: 'SECURITE',
    action: 'company.member_removed',
    summary: 'Accès d’un collaborateur retiré',
    territoryId: ctx.est.territoryId,
    targetType: 'user',
    targetId: userId,
  });
  refresh(ctx, `${ctx.base}/equipe`);
}
