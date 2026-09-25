import Anthropic from '@anthropic-ai/sdk';
import { betaZodOutputFormat } from '@anthropic-ai/sdk/helpers/beta/zod';
import { and, eq, gte, sql } from 'drizzle-orm';
import type * as z from 'zod/v4';
import { db } from '../db';
import { aiUsage, territories } from '../db/schema';
import { env } from '../env';
import { logger } from '../logger';

export type AiFeature = 'WRITER' | 'IMPROVE' | 'AUDIT' | 'TERRITORIAL' | 'SEARCH' | 'TRANSLATE';

export type AiContext = { territoryId?: string | null; companyId?: string | null; userId?: string | null };

let client: Anthropic | null = null;

/** L'assistant IA est actif si une clé API est configurée ; sinon les fonctions basculent sur des règles. */
export function aiEnabled(): boolean {
  return Boolean(env.ANTHROPIC_API_KEY);
}

function getClient(): Anthropic {
  if (!client) client = new Anthropic({ apiKey: env.ANTHROPIC_API_KEY, timeout: 60_000, maxRetries: 2 });
  return client;
}

function monthStart(): Date {
  const d = new Date();
  return new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), 1));
}

/** Crédits IA consommés ce mois par un territoire. */
export async function territoryCreditsUsed(territoryId: string): Promise<number> {
  const [row] = await db
    .select({ n: sql<number>`coalesce(sum(${aiUsage.credits}), 0)::int` })
    .from(aiUsage)
    .where(and(eq(aiUsage.territoryId, territoryId), gte(aiUsage.createdAt, monthStart()), eq(aiUsage.fallback, false)));
  return Number(row?.n ?? 0);
}

/** Nombre d'usages de l'assistant ce mois par une entreprise (limites de l'offre Essentiel). */
export async function companyAiUses(companyId: string): Promise<number> {
  const [row] = await db
    .select({ n: sql<number>`count(*)::int` })
    .from(aiUsage)
    .where(and(eq(aiUsage.companyId, companyId), gte(aiUsage.createdAt, monthStart())));
  return Number(row?.n ?? 0);
}

async function withinTerritoryQuota(territoryId: string | null | undefined): Promise<boolean> {
  if (!territoryId) return true;
  const [t] = await db
    .select({ quota: territories.quotaAiCreditsMonthly })
    .from(territories)
    .where(eq(territories.id, territoryId))
    .limit(1);
  if (!t) return true;
  return (await territoryCreditsUsed(territoryId)) < t.quota;
}

export async function recordUsage(
  feature: AiFeature,
  ctx: AiContext,
  usage: { model: string; inputTokens: number; outputTokens: number; fallback: boolean },
): Promise<void> {
  await db
    .insert(aiUsage)
    .values({
      feature,
      territoryId: ctx.territoryId ?? null,
      companyId: ctx.companyId ?? null,
      userId: ctx.userId ?? null,
      model: usage.model,
      inputTokens: usage.inputTokens,
      outputTokens: usage.outputTokens,
      credits: usage.fallback ? 0 : Math.max(1, Math.ceil((usage.inputTokens + usage.outputTokens) / 1000)),
      fallback: usage.fallback,
    })
    .catch((err) => logger.warn('ai.usage_record_failed', { err }));
}

/**
 * Appel structuré à Claude : la réponse est contrainte par un schéma Zod et validée.
 * Renvoie null (et l'appelant utilise son repli déterministe) si l'IA n'est pas
 * configurée, si le quota est atteint, en cas de refus ou d'erreur de l'API.
 */
export async function structuredCall<S extends z.ZodType>(opts: {
  feature: AiFeature;
  system: string;
  user: string;
  schema: S;
  effort?: 'low' | 'medium' | 'high';
  maxTokens?: number;
  ctx: AiContext;
}): Promise<z.infer<S> | null> {
  if (!aiEnabled()) return null;
  if (!(await withinTerritoryQuota(opts.ctx.territoryId))) {
    logger.info('ai.quota_reached', { territoryId: opts.ctx.territoryId, feature: opts.feature });
    return null;
  }
  const model = env.AI_MODEL;
  try {
    const response = await getClient().beta.messages.parse({
      model,
      max_tokens: opts.maxTokens ?? 16000,
      betas: ['server-side-fallback-2026-07-01'],
      fallbacks: 'default',
      system: [{ type: 'text', text: opts.system, cache_control: { type: 'ephemeral' } }],
      messages: [{ role: 'user', content: opts.user }],
      output_config: { format: betaZodOutputFormat(opts.schema), effort: opts.effort ?? 'medium' },
    });
    await recordUsage(opts.feature, opts.ctx, {
      model: response.model,
      inputTokens: response.usage.input_tokens,
      outputTokens: response.usage.output_tokens,
      fallback: false,
    });
    if (response.stop_reason === 'refusal') {
      logger.warn('ai.refusal', { feature: opts.feature, category: response.stop_details?.category ?? null });
      return null;
    }
    if (response.stop_reason === 'max_tokens') {
      logger.warn('ai.truncated', { feature: opts.feature });
      return null;
    }
    return (response.parsed_output as z.infer<S> | null) ?? null;
  } catch (err) {
    if (err instanceof Anthropic.RateLimitError) logger.warn('ai.rate_limited', { feature: opts.feature });
    else if (err instanceof Anthropic.AuthenticationError) logger.error('ai.auth_failed', { feature: opts.feature });
    else if (err instanceof Anthropic.BadRequestError) logger.error('ai.bad_request', { feature: opts.feature, err });
    else if (err instanceof Anthropic.APIError) logger.error('ai.api_error', { feature: opts.feature, status: err.status, err });
    else logger.error('ai.unexpected_error', { feature: opts.feature, err });
    return null;
  }
}
