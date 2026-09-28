'use server';

import { and, eq } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { z } from 'zod';
import { audit } from '@/server/audit';
import { invalidate } from '@/server/cache';
import { db } from '@/server/db';
import { claims, establishments, messages, posts } from '@/server/db/schema';
import { enqueue } from '@/server/queue';
import { estScope, loadBoContext, type BoContext } from '@/server/services/backoffice';
import { approveClaim, ClaimError, rejectClaim, requestClaimInfo } from '@/server/services/claims';

export type ModState = { status: 'idle' | 'ok' | 'error'; message?: string };

const uuid = z.string().uuid();

async function scopedClaim(ctx: BoContext, raw: unknown) {
  const id = uuid.parse(raw);
  const [row] = await db
    .select({ id: claims.id, estName: establishments.name })
    .from(claims)
    .innerJoin(establishments, eq(establishments.id, claims.establishmentId))
    .where(and(eq(claims.id, id), estScope(ctx)))
    .limit(1);
  return row ?? null;
}

function done(ctx: BoContext) {
  invalidate(`cards:${ctx.territory.id}`);
  invalidate(`pros:${ctx.territory.id}`);
  revalidatePath('/collectivite', 'layout');
}

export async function approveClaimAction(_prev: ModState, form: FormData): Promise<ModState> {
  const ctx = await loadBoContext();
  const c = await scopedClaim(ctx, form.get('claimId'));
  if (!c) return { status: 'error', message: 'Demande introuvable.' };
  try {
    await approveClaim(c.id, { user: ctx.actor.user });
  } catch (err) {
    if (err instanceof ClaimError) return { status: 'error', message: err.message };
    throw err;
  }
  done(ctx);
  redirect('/collectivite/moderation?ok=valide');
}

export async function requestInfoAction(_prev: ModState, form: FormData): Promise<ModState> {
  const ctx = await loadBoContext();
  const c = await scopedClaim(ctx, form.get('claimId'));
  if (!c) return { status: 'error', message: 'Demande introuvable.' };
  const note = z.string().trim().min(10, 'Précisez le justificatif attendu (10 caractères minimum).').max(600).safeParse(form.get('note'));
  if (!note.success) return { status: 'error', message: note.error.issues[0]?.message };
  try {
    await requestClaimInfo(c.id, { user: ctx.actor.user }, note.data);
  } catch (err) {
    if (err instanceof ClaimError) return { status: 'error', message: err.message };
    throw err;
  }
  done(ctx);
  return { status: 'ok', message: 'Demande de justificatif envoyée par email.' };
}

export async function rejectClaimAction(_prev: ModState, form: FormData): Promise<ModState> {
  const ctx = await loadBoContext();
  const c = await scopedClaim(ctx, form.get('claimId'));
  if (!c) return { status: 'error', message: 'Demande introuvable.' };
  const note = z.string().trim().min(10, 'Expliquez brièvement le motif du refus.').max(600).safeParse(form.get('note'));
  if (!note.success) return { status: 'error', message: note.error.issues[0]?.message };
  try {
    await rejectClaim(c.id, { user: ctx.actor.user }, note.data);
  } catch (err) {
    if (err instanceof ClaimError) return { status: 'error', message: err.message };
    throw err;
  }
  done(ctx);
  redirect('/collectivite/moderation?ok=refuse');
}

// ─── Publications ──────────────────────────────────────────────────────────

async function scopedPost(ctx: BoContext, raw: unknown) {
  const id = uuid.parse(raw);
  const [p] = await db
    .select()
    .from(posts)
    .where(and(eq(posts.id, id), eq(posts.territoryId, ctx.territory.id)))
    .limit(1);
  if (!p) return null;
  if (ctx.communeIds && (!p.communeId || !ctx.communeIds.includes(p.communeId))) return null;
  return p;
}

export async function approvePostAction(_prev: ModState, form: FormData): Promise<ModState> {
  const ctx = await loadBoContext();
  const p = await scopedPost(ctx, form.get('postId'));
  if (!p || p.status !== 'PENDING') return { status: 'error', message: 'Publication introuvable ou déjà traitée.' };
  const now = new Date();
  const future = p.publishAt && p.publishAt > now;
  await db
    .update(posts)
    .set({ status: future ? 'SCHEDULED' : 'PUBLISHED', publishedAt: future ? null : now, moderatedById: ctx.actor.user.id, moderatedAt: now, updatedAt: now })
    .where(eq(posts.id, p.id));
  if (!future && p.channels.includes('SOCIAL')) await enqueue('posts.social-sync', { postId: p.id }, { dedupeKey: `social:${p.id}` });
  if (!future && p.establishmentId) {
    const { emitPostPublished } = await import('@/server/services/connectors');
    await emitPostPublished(p.id);
  }
  if (p.establishmentId) await db.update(establishments).set({ lastActivityAt: now }).where(eq(establishments.id, p.establishmentId));
  await audit({
    actor: { user: ctx.actor.user },
    category: 'MODERATION',
    action: 'post.approve',
    summary: `Publication validée : « ${p.title} »`,
    territoryId: ctx.territory.id,
    targetType: 'post',
    targetId: p.id,
  });
  invalidate(`feed:${ctx.territory.id}`);
  done(ctx);
  redirect('/collectivite/moderation?onglet=publications&ok=publiee');
}

export async function rejectPostAction(_prev: ModState, form: FormData): Promise<ModState> {
  const ctx = await loadBoContext();
  const p = await scopedPost(ctx, form.get('postId'));
  if (!p || p.status !== 'PENDING') return { status: 'error', message: 'Publication introuvable ou déjà traitée.' };
  const note = z.string().trim().min(10, 'Expliquez le motif au professionnel.').max(600).safeParse(form.get('note'));
  if (!note.success) return { status: 'error', message: note.error.issues[0]?.message };
  const now = new Date();
  await db
    .update(posts)
    .set({ status: 'REJECTED', moderationNote: note.data, moderatedById: ctx.actor.user.id, moderatedAt: now, updatedAt: now })
    .where(eq(posts.id, p.id));
  if (p.establishmentId)
    await db.insert(messages).values({
      establishmentId: p.establishmentId,
      territoryId: ctx.territory.id,
      source: 'COLLECTIVITE',
      fromUserId: ctx.actor.user.id,
      senderName: ctx.scopeName,
      senderEmail: ctx.territory.contactEmail ?? ctx.actor.user.email,
      body: `Votre publication « ${p.title} » n'a pas été publiée : ${note.data}`,
    });
  await audit({
    actor: { user: ctx.actor.user },
    category: 'MODERATION',
    action: 'post.reject',
    summary: `Publication refusée : « ${p.title} »`,
    territoryId: ctx.territory.id,
    targetType: 'post',
    targetId: p.id,
  });
  done(ctx);
  redirect('/collectivite/moderation?onglet=publications&ok=refusee');
}
