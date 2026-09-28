import { revalidatePath } from 'next/cache';
import { z } from 'zod';
import { invalidate } from '@/server/cache';
import { loadProContext, type ProContext } from '@/server/services/pro';

/** Contexte vérifié (session, droits sur l'établissement) pour une action de l'espace pro. */
export async function proCtx(estId: unknown): Promise<ProContext> {
  const id = z.string().uuid().parse(estId);
  return loadProContext(id);
}

export function actorOf(ctx: ProContext) {
  return { user: ctx.actor.user };
}

/** Invalide les caches du portail et de l'espace pro après une modification. */
export function refresh(ctx: ProContext, ...paths: string[]) {
  invalidate(`cards:${ctx.est.territoryId}`);
  invalidate(`pros:${ctx.est.territoryId}`);
  invalidate(`communeCounts:${ctx.est.territoryId}`);
  revalidatePath(ctx.base, 'layout');
  for (const p of paths) revalidatePath(p);
}
