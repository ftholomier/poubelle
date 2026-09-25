'use server';

import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { eq } from 'drizzle-orm';
import { z } from 'zod';
import { EXCLUSION_GROUPS, parseNafList } from '@/lib/sirene';
import { audit } from '@/server/audit';
import { rateLimit } from '@/server/auth/rate-limit';
import { invalidate } from '@/server/cache';
import { db } from '@/server/db';
import { territories, type TerritorySettings } from '@/server/db/schema';
import { loadBoContext, requireBoAdmin } from '@/server/services/backoffice';
import { acceptCreations, archiveClosures, rejectChanges } from '@/server/services/sirene-sync';
import { enqueue } from '@/server/queue';

const PAGE = '/collectivite/entreprises/sirene';

/** Propositions cochées dans le formulaire (200 au plus par décision). */
function checkedIds(form: FormData): string[] {
  const ids = z.array(z.string().uuid()).max(200).safeParse(form.getAll('id').map(String));
  return ids.success ? ids.data : [];
}

async function adminContext() {
  const ctx = await loadBoContext();
  requireBoAdmin(ctx);
  return ctx;
}

function done(params: Record<string, string>): never {
  revalidatePath('/collectivite', 'layout');
  redirect(`${PAGE}?${new URLSearchParams(params)}`);
}

export async function acceptSireneAction(form: FormData): Promise<void> {
  const ctx = await adminContext();
  const ids = checkedIds(form);
  if (!ids.length) done({ ok: 'vide' });
  const { created } = await acceptCreations(ctx, ids);
  done(created.length ? { ok: 'creees', fiches: created.join(',') } : { ok: 'rien' });
}

export async function archiveSireneAction(form: FormData): Promise<void> {
  const ctx = await adminContext();
  const ids = checkedIds(form);
  if (!ids.length) done({ ok: 'vide' });
  const n = await archiveClosures(ctx, ids);
  done({ ok: 'archivees', n: String(n) });
}

export async function rejectSireneAction(form: FormData): Promise<void> {
  const ctx = await adminContext();
  const ids = checkedIds(form);
  if (!ids.length) done({ ok: 'vide' });
  const n = await rejectChanges(ctx, ids);
  done({ ok: 'ecartees', n: String(n) });
}

export async function syncNowAction(): Promise<void> {
  const ctx = await adminContext();
  if (ctx.level !== 'TERRITORY') done({ ok: 'perimetre' });
  if (!(await rateLimit(`sirene-sync:${ctx.territory.id}`, 2, 3600)).ok) done({ ok: 'limite' });
  await enqueue(
    'sirene.sync',
    { territoryId: ctx.territory.id, trigger: 'MANUAL' },
    { dedupeKey: `sirene-sync:${ctx.territory.id}:${Date.now()}`, maxAttempts: 1 },
  );
  await audit({
    actor: { user: ctx.actor.user },
    category: 'IMPORT',
    action: 'sirene.sync_started',
    summary: 'Synchronisation SIRENE lancée manuellement',
    territoryId: ctx.territory.id,
    targetType: 'sirene',
  });
  done({ ok: 'lancee' });
}

/** Réglages du territoire : synchronisation mensuelle et activités à ne jamais importer. */
export async function saveSireneSettingsAction(form: FormData): Promise<void> {
  const ctx = await adminContext();
  if (ctx.level !== 'TERRITORY') done({ ok: 'perimetre' });
  const groups = form.getAll('group').map(String);
  const settings: TerritorySettings = {
    ...((ctx.territory.settings ?? {}) as TerritorySettings),
    sirene: {
      autoSync: form.get('autoSync') === 'on',
      excludedGroups: EXCLUSION_GROUPS.filter((g) => groups.includes(g.key)).map((g) => g.key),
      excludedNaf: parseNafList(String(form.get('naf') ?? '')),
    },
  };
  await db.update(territories).set({ settings, updatedAt: new Date() }).where(eq(territories.id, ctx.territory.id));
  invalidate(`territory:${ctx.territory.id}`);
  invalidate(`portal:${ctx.territory.slug}`);
  await audit({
    actor: { user: ctx.actor.user },
    category: 'CONFIGURATION',
    action: 'territory.sirene_settings',
    summary: 'Réglages SIRENE mis à jour (synchronisation, activités exclues)',
    territoryId: ctx.territory.id,
    targetType: 'territory',
    targetId: ctx.territory.id,
  });
  done({ ok: 'reglages' });
}
