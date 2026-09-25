import { createHmac, timingSafeEqual } from 'node:crypto';
import type { PlanKey } from '@/lib/constants';
import { env } from '../env';
import { logger } from '../logger';
import { appUrl } from '../urls';
import { getPlan } from './billing';

/**
 * Paiement par carte (facultatif) via Stripe Checkout, appelé directement en REST.
 * Sans clé configurée, les abonnements sont facturés par virement (factures mensuelles).
 */
export async function createCheckoutSession(p: { companyId: string; plan: PlanKey; estId: string; email: string }): Promise<string | null> {
  if (!env.STRIPE_SECRET_KEY) return null;
  const plan = await getPlan(p.plan);
  const body = new URLSearchParams({
    mode: 'subscription',
    customer_email: p.email,
    success_url: appUrl(`/pro/${p.estId}/offre?paiement=ok`),
    cancel_url: appUrl(`/pro/${p.estId}/offre?paiement=annule`),
    'line_items[0][quantity]': '1',
    'line_items[0][price_data][currency]': 'eur',
    'line_items[0][price_data][unit_amount]': String(Math.round(plan.priceMonthlyCents * 1.2)),
    'line_items[0][price_data][recurring][interval]': 'month',
    'line_items[0][price_data][product_data][name]': `terricom ${plan.name}`,
    'metadata[companyId]': p.companyId,
    'metadata[plan]': p.plan,
    'subscription_data[metadata][companyId]': p.companyId,
    'subscription_data[metadata][plan]': p.plan,
    locale: 'fr',
  });
  const res = await fetch('https://api.stripe.com/v1/checkout/sessions', {
    method: 'POST',
    headers: { authorization: `Bearer ${env.STRIPE_SECRET_KEY}`, 'content-type': 'application/x-www-form-urlencoded' },
    body,
  });
  if (!res.ok) {
    logger.error('stripe.checkout_failed', { status: res.status, body: (await res.text()).slice(0, 500) });
    return null;
  }
  const json = (await res.json()) as { url?: string };
  return json.url ?? null;
}

/** Vérifie la signature d'un webhook Stripe (en-tête Stripe-Signature, tolérance 5 min). */
export function verifyStripeSignature(payload: string, header: string | null): boolean {
  if (!env.STRIPE_WEBHOOK_SECRET || !header) return false;
  const parts = Object.fromEntries(header.split(',').map((kv) => kv.split('=') as [string, string]));
  const ts = Number(parts.t);
  if (!ts || Math.abs(Date.now() / 1000 - ts) > 300) return false;
  const expected = createHmac('sha256', env.STRIPE_WEBHOOK_SECRET).update(`${ts}.${payload}`).digest('hex');
  const given = header
    .split(',')
    .filter((kv) => kv.startsWith('v1='))
    .map((kv) => kv.slice(3));
  return given.some((g) => g.length === expected.length && timingSafeEqual(Buffer.from(g), Buffer.from(expected)));
}
