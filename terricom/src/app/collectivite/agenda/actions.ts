'use server';

import { and, eq } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { z } from 'zod';
import { EVENT_PROGRAM_TEMPLATES } from '@/lib/constants';
import { fromParisLocal } from '@/lib/format';
import { slugify } from '@/lib/slug';
import { audit } from '@/server/audit';
import { invalidate } from '@/server/cache';
import { db } from '@/server/db';
import { events, markets, posts } from '@/server/db/schema';
import { MediaError, saveImageUpload } from '@/server/media';
import { loadBoContext, type BoContext } from '@/server/services/backoffice';

export type AgendaState = { status: 'idle' | 'ok' | 'error'; message?: string };

const optUuid = z.union([z.literal(''), z.string().uuid()]);

function strings(form: FormData) {
  return Object.fromEntries([...form.entries()].filter(([, v]) => typeof v === 'string'));
}

function communeAllowed(ctx: BoContext, communeId: string | null): boolean {
  if (!communeId) return ctx.level === 'TERRITORY';
  return ctx.communes.some((c) => c.id === communeId);
}

async function upload(ctx: BoContext, form: FormData, ownerType: string, alt: string): Promise<string | null | 'error'> {
  const file = form.get('image');
  if (!(file instanceof File) || file.size === 0) return null;
  try {
    const m = await saveImageUpload(file, { ownerType, territoryId: ctx.territory.id, uploadedById: ctx.actor.user.id, alt });
    return (m.variants as Record<string, string>).w1280 ?? m.url;
  } catch (err) {
    if (err instanceof MediaError) return 'error';
    throw err;
  }
}

function done(ctx: BoContext) {
  invalidate(`feed:${ctx.territory.id}`);
  invalidate(`agenda:${ctx.territory.id}`);
  revalidatePath('/collectivite/agenda');
}

export async function saveNewsAction(_prev: AgendaState, form: FormData): Promise<AgendaState> {
  const ctx = await loadBoContext();
  const parsed = z
    .object({
      postId: optUuid,
      title: z.string().trim().min(3, 'Titre requis').max(255),
      body: z.string().trim().min(10, 'Texte trop court').max(8000),
      communeId: optUuid,
      publishAt: z.union([z.literal(''), z.string().regex(/^\d{4}-\d{2}-\d{2}$/)]),
    })
    .safeParse(strings(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  const communeId = d.communeId || ctx.commune?.id || null;
  if (!communeAllowed(ctx, communeId)) return { status: 'error', message: 'Commune hors de votre périmètre.' };
  const imageUrl = await upload(ctx, form, 'POST', d.title);
  if (imageUrl === 'error') return { status: 'error', message: 'Image refusée (JPEG, PNG ou WebP, 10 Mo maximum).' };
  const publishAt = d.publishAt ? fromParisLocal(d.publishAt, '08:00') : null;
  const future = publishAt && publishAt > new Date();
  const values = {
    title: d.title,
    body: d.body,
    communeId,
    authorType: communeId && ctx.level === 'COMMUNE' ? ('COMMUNE' as const) : ('TERRITORY' as const),
    kind: 'NEWS' as const,
    status: future ? ('SCHEDULED' as const) : ('PUBLISHED' as const),
    publishAt,
    publishedAt: future ? null : new Date(),
    channels: ['TERRITOIRE', ...(communeId ? ['COMMUNE'] : [])] as ('TERRITOIRE' | 'COMMUNE')[],
    ...(imageUrl ? { imageUrl } : {}),
    updatedAt: new Date(),
  };
  if (d.postId) {
    const res = await db
      .update(posts)
      .set(values)
      .where(and(eq(posts.id, d.postId), eq(posts.territoryId, ctx.territory.id)))
      .returning({ id: posts.id });
    if (!res.length) return { status: 'error', message: 'Actualité introuvable.' };
  } else {
    await db.insert(posts).values({ ...values, territoryId: ctx.territory.id, createdById: ctx.actor.user.id });
  }
  await audit({
    actor: { user: ctx.actor.user },
    category: 'MODIFICATION',
    action: 'post.collectivite',
    summary: `Actualité « ${d.title} » ${future ? 'programmée' : 'publiée'}`,
    territoryId: ctx.territory.id,
  });
  done(ctx);
  return { status: 'ok', message: future ? 'Actualité programmée.' : 'Actualité publiée sur le portail.' };
}

export async function archiveNewsAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const id = z.string().uuid().parse(form.get('postId'));
  await db
    .update(posts)
    .set({ status: 'ARCHIVED', updatedAt: new Date() })
    .where(and(eq(posts.id, id), eq(posts.territoryId, ctx.territory.id)));
  done(ctx);
}

export async function saveEventAction(_prev: AgendaState, form: FormData): Promise<AgendaState> {
  const ctx = await loadBoContext();
  const parsed = z
    .object({
      eventId: optUuid,
      title: z.string().trim().min(3, 'Titre requis').max(255),
      kind: z.enum(['MARCHE', 'DEGUSTATION', 'PORTES_OUVERTES', 'ATELIER', 'CONCERT', 'ANIMATION', 'SALON', 'AUTRE']),
      date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Date requise'),
      start: z.string().regex(/^\d{2}:\d{2}$/, 'Heure de début requise'),
      end: z.union([z.literal(''), z.string().regex(/^\d{2}:\d{2}$/)]),
      communeId: optUuid,
      locationName: z.string().trim().max(255),
      address: z.string().trim().max(255),
      summary: z.string().trim().max(300),
      description: z.string().trim().min(10, 'Décrivez l’événement en quelques mots.').max(5000),
      priceText: z.string().trim().max(120),
      registrationUrl: z.union([z.literal(''), z.string().trim().url('Lien d’inscription invalide')]),
      organizerName: z.string().trim().max(255),
    })
    .safeParse(strings(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  const communeId = d.communeId || ctx.commune?.id || null;
  if (!communeAllowed(ctx, communeId)) return { status: 'error', message: 'Commune hors de votre périmètre.' };
  const startsAt = fromParisLocal(d.date, d.start);
  const endsAt = d.end ? fromParisLocal(d.date, d.end) : null;
  if (endsAt && endsAt <= startsAt) return { status: 'error', message: 'L’heure de fin doit suivre l’heure de début.' };
  const imageUrl = await upload(ctx, form, 'EVENT', d.title);
  if (imageUrl === 'error') return { status: 'error', message: 'Image refusée (JPEG, PNG ou WebP, 10 Mo maximum).' };
  const commune = ctx.communes.find((c) => c.id === communeId);
  const values = {
    title: d.title,
    kind: d.kind,
    startsAt,
    endsAt,
    communeId,
    locationName: d.locationName || null,
    address: d.address || null,
    lat: commune?.lat ?? null,
    lng: commune?.lng ?? null,
    summary: d.summary || null,
    description: d.description,
    priceText: d.priceText || null,
    registrationUrl: d.registrationUrl || null,
    organizerName: d.organizerName || ctx.scopeName,
    isFeatured: form.get('isFeatured') === 'on',
    authorType: ctx.level === 'COMMUNE' ? ('COMMUNE' as const) : ('TERRITORY' as const),
    ...(imageUrl ? { imageUrl } : {}),
    updatedAt: new Date(),
  };
  if (d.eventId) {
    const res = await db
      .update(events)
      .set(values)
      .where(and(eq(events.id, d.eventId), eq(events.territoryId, ctx.territory.id)))
      .returning({ id: events.id });
    if (!res.length) return { status: 'error', message: 'Événement introuvable.' };
  } else {
    await db.insert(events).values({
      ...values,
      territoryId: ctx.territory.id,
      slug: `${slugify(`${d.title}-${d.date}`).slice(0, 140)}-${Date.now().toString(36).slice(-3)}`,
      program: EVENT_PROGRAM_TEMPLATES[d.kind] ?? [],
      createdById: ctx.actor.user.id,
    });
  }
  await audit({
    actor: { user: ctx.actor.user },
    category: 'MODIFICATION',
    action: 'event.collectivite',
    summary: `Événement « ${d.title} » enregistré`,
    territoryId: ctx.territory.id,
  });
  done(ctx);
  return { status: 'ok', message: 'Événement enregistré dans l’agenda.' };
}

export async function archiveEventAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const id = z.string().uuid().parse(form.get('eventId'));
  await db
    .update(events)
    .set({ status: 'ARCHIVED', updatedAt: new Date() })
    .where(and(eq(events.id, id), eq(events.territoryId, ctx.territory.id)));
  done(ctx);
}

export async function saveMarketAction(_prev: AgendaState, form: FormData): Promise<AgendaState> {
  const ctx = await loadBoContext();
  const parsed = z
    .object({
      marketId: optUuid,
      name: z.string().trim().min(3, 'Nom requis').max(255),
      communeId: z.string().uuid('Commune requise'),
      place: z.string().trim().max(255),
      weekday: z.coerce.number().int().min(0).max(6),
      startTime: z.string().regex(/^\d{2}:\d{2}$/),
      endTime: z.string().regex(/^\d{2}:\d{2}$/),
      description: z.string().trim().max(1000),
    })
    .safeParse(strings(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  if (!communeAllowed(ctx, d.communeId)) return { status: 'error', message: 'Commune hors de votre périmètre.' };
  if (d.endTime <= d.startTime) return { status: 'error', message: 'L’heure de fin doit suivre l’heure de début.' };
  const commune = ctx.communes.find((c) => c.id === d.communeId);
  const values = {
    name: d.name,
    communeId: d.communeId,
    place: d.place || null,
    weekday: d.weekday,
    startTime: d.startTime,
    endTime: d.endTime,
    description: d.description || null,
    lat: commune?.lat ?? null,
    lng: commune?.lng ?? null,
  };
  if (d.marketId)
    await db
      .update(markets)
      .set(values)
      .where(and(eq(markets.id, d.marketId), eq(markets.territoryId, ctx.territory.id)));
  else await db.insert(markets).values({ ...values, territoryId: ctx.territory.id });
  await audit({
    actor: { user: ctx.actor.user },
    category: 'MODIFICATION',
    action: 'market.save',
    summary: `Marché « ${d.name} » enregistré`,
    territoryId: ctx.territory.id,
  });
  done(ctx);
  return { status: 'ok', message: 'Marché enregistré.' };
}

export async function toggleMarketAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const id = z.string().uuid().parse(form.get('marketId'));
  const [m] = await db
    .select()
    .from(markets)
    .where(and(eq(markets.id, id), eq(markets.territoryId, ctx.territory.id)))
    .limit(1);
  if (!m) return;
  await db.update(markets).set({ isActive: !m.isActive }).where(eq(markets.id, m.id));
  done(ctx);
}
