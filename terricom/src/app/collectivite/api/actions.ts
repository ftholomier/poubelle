'use server';

import { revalidatePath } from 'next/cache';
import { z } from 'zod';
import { audit } from '@/server/audit';
import { loadBoContext, requireTerritoryLevel } from '@/server/services/backoffice';
import { createApiKey, listApiKeys, revokeApiKey } from '@/server/services/public-api';

export type ApiKeyState = { status: 'idle' | 'ok' | 'error'; message?: string; key?: string };

const MAX_KEYS = 10;

async function adminCtx() {
  const ctx = await loadBoContext();
  requireTerritoryLevel(ctx);
  if (ctx.access !== 'ADMIN') throw new Error('Réservé aux administrateurs du territoire');
  return ctx;
}

export async function createApiKeyAction(_prev: ApiKeyState, form: FormData): Promise<ApiKeyState> {
  const ctx = await adminCtx();
  const name = z.string().trim().min(2, 'Nommez la clé (usage, prestataire…)').max(160).safeParse(form.get('name'));
  if (!name.success) return { status: 'error', message: name.error.issues[0]?.message };
  const active = (await listApiKeys(ctx.territory.id)).filter((k) => !k.revokedAt);
  if (active.length >= MAX_KEYS) return { status: 'error', message: `${MAX_KEYS} clés actives au maximum : révoquez une clé inutilisée.` };
  const { id, key } = await createApiKey(ctx.territory.id, name.data);
  await audit({
    actor: { user: ctx.actor.user },
    category: 'SECURITE',
    action: 'api_key.create',
    summary: `Clé d’API « ${name.data} » créée`,
    territoryId: ctx.territory.id,
    targetType: 'api_key',
    targetId: id,
  });
  revalidatePath('/collectivite/api');
  return { status: 'ok', message: 'Clé créée : copiez-la maintenant, elle ne sera plus affichée.', key };
}

export async function revokeApiKeyAction(form: FormData): Promise<void> {
  const ctx = await adminCtx();
  const id = z.string().uuid().parse(form.get('keyId'));
  const row = await revokeApiKey(ctx.territory.id, id);
  if (row)
    await audit({
      actor: { user: ctx.actor.user },
      category: 'SECURITE',
      action: 'api_key.revoke',
      summary: `Clé d’API « ${row.name} » révoquée`,
      territoryId: ctx.territory.id,
      targetType: 'api_key',
      targetId: id,
    });
  revalidatePath('/collectivite/api');
}
