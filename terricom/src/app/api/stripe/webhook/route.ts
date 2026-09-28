import { NextResponse, type NextRequest } from 'next/server';
import { audit } from '@/server/audit';
import { logger } from '@/server/logger';
import { changeCompanyPlan } from '@/server/services/billing';
import { activateDirectMember, companyIsDirect, suspendDirectMember } from '@/server/services/direct';
import { verifyStripeSignature } from '@/server/services/stripe';

/** Webhook Stripe : activation et résiliation des abonnements payés par carte. */
export async function POST(req: NextRequest) {
  const payload = await req.text();
  if (!verifyStripeSignature(payload, req.headers.get('stripe-signature'))) return new NextResponse('Signature invalide', { status: 400 });
  const event = JSON.parse(payload) as { type: string; data: { object: { metadata?: Record<string, string>; subscription?: string | null } } };
  const meta = event.data.object.metadata ?? {};
  try {
    if (event.type === 'checkout.session.completed' && meta.companyId && meta.plan) {
      const direct = meta.direct === '1';
      await changeCompanyPlan(meta.companyId, meta.plan as 'PREMIUM' | 'COMMUNICATION', 'STRIPE', {
        direct,
        interval: meta.interval === 'YEAR' ? 'YEAR' : 'MONTH',
        providerRef: event.data.object.subscription ?? null,
      });
      // Adhésion directe : la fiche de la vitrine nationale est publiée dès le paiement.
      if (direct) await activateDirectMember(meta.companyId);
      await audit({
        actor: 'Stripe',
        category: 'FACTURATION',
        action: 'company.plan_paid',
        summary: `Abonnement ${meta.plan} payé par carte`,
        targetType: 'company',
        targetId: meta.companyId,
      });
    } else if (event.type === 'customer.subscription.deleted' && meta.companyId) {
      await changeCompanyPlan(meta.companyId, 'ESSENTIEL', 'STRIPE');
      // Sans collectivité qui offre la fiche, la résiliation d'une adhésion directe retire la fiche de la vitrine.
      if (await companyIsDirect(meta.companyId)) await suspendDirectMember(meta.companyId);
      await audit({
        actor: 'Stripe',
        category: 'FACTURATION',
        action: 'company.plan_canceled',
        summary: 'Abonnement résilié (retour à Essentiel)',
        targetType: 'company',
        targetId: meta.companyId,
      });
    }
  } catch (err) {
    logger.error('stripe.webhook_failed', { err, type: event.type });
    return new NextResponse('Erreur', { status: 500 });
  }
  return NextResponse.json({ received: true });
}
