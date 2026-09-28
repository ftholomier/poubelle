import { createHmac, timingSafeEqual } from 'node:crypto';
import type { PlanKey } from '@/lib/constants';
import { subscriptionName, subscriptionPriceCents, type BillingInterval } from '@/lib/pricing';
import { env } from '../env';
import { logger } from '../logger';
import { appUrl } from '../urls';
import { getPlan } from './billing';

/**
 * Paiement par carte (facultatif) via Stripe Checkout, appelé directement en REST.
 * Sans clé configurée, les abonnements sont facturés par virement (factures mensuelles).
 */
export async function createCheckoutSession(p: {
  companyId: string;
  plan: PlanKey;
  estId: string;
  email: string;
  direct?: boolean;
  interval?: BillingInterval;
}): Promise<string | null> {
  if (!env.STRIPE_SECRET_KEY) return null;
  const plan = await getPlan(p.plan);
  const direct = Boolean(p.direct);
  const interval = p.interval ?? 'MONTH';
  const priceHt = subscriptionPriceCents({ plan: p.plan, direct, interval, planMonthlyCents: plan.priceMonthlyCents });
  const body = new URLSearchParams({
    mode: 'subscription',
    customer_email: p.email,
    success_url: appUrl(`/pro/${p.estId}/offre?paiement=ok`),
    cancel_url: appUrl(`/pro/${p.estId}/offre?paiement=annule`),
    'line_items[0][quantity]': '1',
    'line_items[0][price_data][currency]': 'eur',
    'line_items[0][price_data][unit_amount]': String(Math.round(priceHt * 1.2)),
    'line_items[0][price_data][recurring][interval]': interval === 'YEAR' ? 'year' : 'month',
    'line_items[0][price_data][product_data][name]': `terricom ${subscriptionName(p.plan, direct, plan.name)}`,
    'metadata[companyId]': p.companyId,
    'metadata[plan]': p.plan,
    'metadata[direct]': direct ? '1' : '0',
    'metadata[interval]': interval,
    'subscription_data[metadata][companyId]': p.companyId,
    'subscription_data[metadata][plan]': p.plan,
    'subscription_data[metadata][direct]': direct ? '1' : '0',
    'subscription_data[metadata][interval]': interval,
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

/**
 * Change le prix d'un abonnement Stripe en cours (bascule de l'adhésion directe vers l'option équivalente quand
 * la collectivité adhère), sans prorata : le nouveau prix s'applique à la prochaine échéance.
 */
export async function updateStripeSubscriptionPrice(p: {
  subscriptionId: string;
  plan: PlanKey;
  direct: boolean;
  interval: BillingInterval;
}): Promise<boolean> {
  if (!env.STRIPE_SECRET_KEY) return false;
  const auth = { authorization: `Bearer ${env.STRIPE_SECRET_KEY}` };
  const cur = await fetch(`https://api.stripe.com/v1/subscriptions/${encodeURIComponent(p.subscriptionId)}`, { headers: auth });
  if (!cur.ok) {
    logger.error('stripe.subscription_read_failed', { status: cur.status });
    return false;
  }
  const sub = (await cur.json()) as { items?: { data?: { id: string; price?: { product?: string } }[] } };
  const item = sub.items?.data?.[0];
  if (!item?.id || !item.price?.product) return false;
  const plan = await getPlan(p.plan);
  const priceHt = subscriptionPriceCents({ plan: p.plan, direct: p.direct, interval: p.interval, planMonthlyCents: plan.priceMonthlyCents });
  const body = new URLSearchParams({
    'items[0][id]': item.id,
    'items[0][price_data][currency]': 'eur',
    'items[0][price_data][unit_amount]': String(Math.round(priceHt * 1.2)),
    'items[0][price_data][recurring][interval]': p.interval === 'YEAR' ? 'year' : 'month',
    'items[0][price_data][product]': item.price.product,
    proration_behavior: 'none',
    'metadata[direct]': p.direct ? '1' : '0',
  });
  const res = await fetch(`https://api.stripe.com/v1/subscriptions/${encodeURIComponent(p.subscriptionId)}`, {
    method: 'POST',
    headers: { ...auth, 'content-type': 'application/x-www-form-urlencoded' },
    body,
  });
  if (!res.ok) logger.error('stripe.subscription_update_failed', { status: res.status, body: (await res.text()).slice(0, 500) });
  return res.ok;
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
