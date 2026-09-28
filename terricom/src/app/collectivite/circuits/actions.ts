'use server';

import { and, asc, eq, max, sql } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { z } from 'zod';
import { slugify } from '@/lib/slug';
import { audit } from '@/server/audit';
import { randomToken } from '@/server/crypto';
import { db } from '@/server/db';
import { circuitStops, circuits, establishments } from '@/server/db/schema';
import { MediaError, saveImageUpload } from '@/server/media';
import { estScope, loadBoContext, type BoContext } from '@/server/services/backoffice';

export type CircState = { status: 'idle' | 'ok' | 'error'; message?: string };

const uuid = z.string().uuid();
const page = (id: string) => `/collectivite/circuits?circuit=${id}`;

async function scopedCircuit(ctx: BoContext, raw: unknown) {
  const id = uuid.parse(raw);
  const [c] = await db
    .select()
    .from(circuits)
    .where(and(eq(circuits.id, id), eq(circuits.territoryId, ctx.territory.id)))
    .limit(1);
  return c ?? null;
}

async function renumber(circuitId: string) {
  const stops = await db.select({ id: circuitStops.id }).from(circuitStops).where(eq(circuitStops.circuitId, circuitId)).orderBy(asc(circuitStops.position));
  for (const [i, s] of stops.entries()) await db.update(circuitStops).set({ position: i }).where(eq(circuitStops.id, s.id));
}

export async function createCircuitAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const name = z
    .string()
    .trim()
    .min(3)
    .max(200)
    .catch('Nouveau circuit')
    .parse(form.get('name') || 'Nouveau circuit');
  const base = slugify(name) || 'circuit';
  const taken = new Set((await db.select({ s: circuits.slug }).from(circuits).where(eq(circuits.territoryId, ctx.territory.id))).map((r) => r.s));
  let slug = base;
  for (let i = 2; taken.has(slug); i++) slug = `${base}-${i}`;
  const [c] = await db
    .insert(circuits)
    .values({
      territoryId: ctx.territory.id,
      slug,
      name,
      status: 'DRAFT',
      rewardThreshold: 5,
      rewardText: 'Un panier de produits locaux à gagner',
      createdById: ctx.actor.user.id,
    })
    .returning({ id: circuits.id });
  await audit({
    actor: { user: ctx.actor.user },
    category: 'MODIFICATION',
    action: 'circuit.create',
    summary: `Circuit « ${name} » créé`,
    territoryId: ctx.territory.id,
    targetType: 'circuit',
    targetId: c.id,
  });
  redirect(page(c.id));
}

export async function renameCircuitAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const c = await scopedCircuit(ctx, form.get('circuitId'));
  const name = z.string().trim().min(3).max(200).safeParse(form.get('name'));
  if (!c || !name.success) return;
  await db.update(circuits).set({ name: name.data, updatedAt: new Date() }).where(eq(circuits.id, c.id));
  revalidatePath('/collectivite/circuits');
}

export async function addStopAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const c = await scopedCircuit(ctx, form.get('circuitId'));
  const estId = uuid.parse(form.get('estId'));
  if (!c) return;
  const [e] = await db
    .select({ id: establishments.id })
    .from(establishments)
    .where(and(estScope(ctx), eq(establishments.id, estId)))
    .limit(1);
  if (!e) return;
  const [dup] = await db
    .select({ id: circuitStops.id })
    .from(circuitStops)
    .where(and(eq(circuitStops.circuitId, c.id), eq(circuitStops.establishmentId, e.id)))
    .limit(1);
  if (dup) return;
  const [{ m }] = await db
    .select({ m: max(circuitStops.position) })
    .from(circuitStops)
    .where(eq(circuitStops.circuitId, c.id));
  await db.insert(circuitStops).values({ circuitId: c.id, position: (m ?? -1) + 1, establishmentId: e.id, stampSecret: randomToken(12) });
  revalidatePath('/collectivite/circuits');
}

export async function searchStopAction(_prev: CircState, form: FormData): Promise<CircState> {
  const ctx = await loadBoContext();
  const c = await scopedCircuit(ctx, form.get('circuitId'));
  const q = String(form.get('q') ?? '').trim();
  if (!c || q.length < 2) return { status: 'error', message: 'Tapez le nom d’un établissement.' };
  const [e] = await db
    .select({ id: establishments.id, name: establishments.name })
    .from(establishments)
    .where(
      and(
        estScope(ctx),
        sql`${establishments.status} in ('CLAIMED','VALIDATED','TO_COMPLETE','PRECREATED')`,
        sql`f_unaccent(${establishments.name}) ilike f_unaccent(${`%${q}%`})`,
      ),
    )
    .limit(1);
  if (!e) return { status: 'error', message: `Aucun établissement ne correspond à « ${q} ».` };
  const fd = new FormData();
  fd.set('circuitId', c.id);
  fd.set('estId', e.id);
  await addStopAction(fd);
  return { status: 'ok', message: `${e.name} ajouté.` };
}

export async function moveStopAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const c = await scopedCircuit(ctx, form.get('circuitId'));
  const stopId = uuid.parse(form.get('stopId'));
  if (!c) return;
  const stops = await db
    .select({ id: circuitStops.id, position: circuitStops.position })
    .from(circuitStops)
    .where(eq(circuitStops.circuitId, c.id))
    .orderBy(asc(circuitStops.position));
  const i = stops.findIndex((s) => s.id === stopId);
  if (i <= 0) return;
  await db
    .update(circuitStops)
    .set({ position: stops[i - 1].position })
    .where(eq(circuitStops.id, stops[i].id));
  await db
    .update(circuitStops)
    .set({ position: stops[i].position })
    .where(eq(circuitStops.id, stops[i - 1].id));
  revalidatePath('/collectivite/circuits');
}

export async function removeStopAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const c = await scopedCircuit(ctx, form.get('circuitId'));
  const stopId = uuid.parse(form.get('stopId'));
  if (!c) return;
  await db.delete(circuitStops).where(and(eq(circuitStops.id, stopId), eq(circuitStops.circuitId, c.id)));
  await renumber(c.id);
  revalidatePath('/collectivite/circuits');
}

export async function saveCircuitAction(_prev: CircState, form: FormData): Promise<CircState> {
  const ctx = await loadBoContext();
  const c = await scopedCircuit(ctx, form.get('circuitId'));
  if (!c) return { status: 'error', message: 'Circuit introuvable.' };
  const parsed = z
    .object({
      meta: z.string().trim().max(255),
      description: z.string().trim().max(3000),
      distanceKm: z.union([z.literal(''), z.coerce.number().min(0).max(9999)]),
      durationText: z.string().trim().max(64),
      travelMode: z.string().trim().max(120),
      rewardText: z.string().trim().max(255),
      rewardThreshold: z.union([z.literal(''), z.coerce.number().int().min(1).max(50)]),
      status: z.enum(['DRAFT', 'PUBLISHED', 'ARCHIVED']),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: `Champ invalide : ${parsed.error.issues[0]?.path.join('.')}` };
  const d = parsed.data;
  let imageUrl = c.imageUrl;
  const file = form.get('image');
  if (file instanceof File && file.size > 0) {
    try {
      imageUrl = (await saveImageUpload(file, { ownerType: 'CIRCUIT', ownerId: c.id, territoryId: ctx.territory.id, uploadedById: ctx.actor.user.id })).url;
    } catch (err) {
      return { status: 'error', message: err instanceof MediaError ? err.message : 'Image refusée.' };
    }
  }
  if (d.status === 'PUBLISHED') {
    const [{ n }] = await db
      .select({ n: sql<number>`count(*)::int` })
      .from(circuitStops)
      .where(eq(circuitStops.circuitId, c.id));
    if (n < 2) return { status: 'error', message: 'Un circuit publié compte au moins deux étapes.' };
  }
  await db
    .update(circuits)
    .set({
      meta: d.meta || null,
      description: d.description,
      distanceKm: d.distanceKm === '' ? null : String(d.distanceKm),
      durationText: d.durationText || null,
      travelMode: d.travelMode || null,
      rewardText: d.rewardText || null,
      rewardThreshold: d.rewardThreshold === '' ? null : d.rewardThreshold,
      status: d.status,
      imageUrl,
      updatedAt: new Date(),
    })
    .where(eq(circuits.id, c.id));
  await audit({
    actor: { user: ctx.actor.user },
    category: 'MODIFICATION',
    action: 'circuit.update',
    summary: `Circuit « ${c.name} » mis à jour (${d.status.toLowerCase()})`,
    territoryId: ctx.territory.id,
    targetType: 'circuit',
    targetId: c.id,
  });
  revalidatePath('/collectivite/circuits');
  return { status: 'ok', message: 'Circuit enregistré.' };
}
