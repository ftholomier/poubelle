import { NextResponse, type NextRequest } from 'next/server';
import { audit } from '@/server/audit';
import { logger } from '@/server/logger';
import { changeCompanyPlan } from '@/server/services/billing';
import { verifyStripeSignature } from '@/server/services/stripe';

/** Webhook Stripe : activation et résiliation des abonnements payés par carte. */
export async function POST(req: NextRequest) {
  const payload = await req.text();
  if (!verifyStripeSignature(payload, req.headers.get('stripe-signature'))) return new NextResponse('Signature invalide', { status: 400 });
  const event = JSON.parse(payload) as { type: string; data: { object: { metadata?: Record<string, string> } } };
  const meta = event.data.object.metadata ?? {};
  try {
    if (event.type === 'checkout.session.completed' && meta.companyId && meta.plan) {
      await changeCompanyPlan(meta.companyId, meta.plan as 'PREMIUM' | 'COMMUNICATION', 'STRIPE');
      await audit({ actor: 'Stripe', category: 'FACTURATION', action: 'company.plan_paid', summary: `Abonnement ${meta.plan} payé par carte`, targetType: 'company', targetId: meta.companyId });
    } else if (event.type === 'customer.subscription.deleted' && meta.companyId) {
      await changeCompanyPlan(meta.companyId, 'ESSENTIEL', 'STRIPE');
      await audit({ actor: 'Stripe', category: 'FACTURATION', action: 'company.plan_canceled', summary: 'Abonnement résilié (retour à Essentiel)', targetType: 'company', targetId: meta.companyId });
    }
  } catch (err) {
    logger.error('stripe.webhook_failed', { err, type: event.type });
    return new NextResponse('Erreur', { status: 500 });
  }
  return NextResponse.json({ received: true });
}
