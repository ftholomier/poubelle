import { and, desc, eq } from 'drizzle-orm';
import { PlanPicker } from '@/components/pro/PlanPicker';
import { fmtEuros, fmtShortDate } from '@/lib/format';
import { db } from '@/server/db';
import { companies, companySubscriptions } from '@/server/db/schema';
import { companyInvoices, getPlans } from '@/server/services/billing';
import type { PlanKey } from '@/lib/constants';
import { subscriptionName, subscriptionPriceCents } from '@/lib/pricing';
import { companyIsDirect } from '@/server/services/direct';
import { loadProContext } from '@/server/services/pro';

type Props = { params: Promise<{ est: string }>; searchParams: Promise<Record<string, string | undefined>> };

const INVOICE_STATUS: Record<string, { label: string; bg: string }> = {
  ISSUED: { label: 'À régler', bg: 'var(--warn-bg)' },
  PAID: { label: 'Payée', bg: 'var(--ok-bg)' },
  OVERDUE: { label: 'En retard', bg: 'var(--danger-bg)' },
  DRAFT: { label: 'Brouillon', bg: 'var(--sand)' },
  CANCELLED: { label: 'Annulée', bg: 'var(--sand)' },
};

export default async function OfferPage({ params, searchParams }: Props) {
  const { est: estId } = await params;
  const sp = await searchParams;
  const ctx = await loadProContext(estId);
  const { est, territory } = ctx;
  const [plans, invoices, [company], [sub], direct] = await Promise.all([
    getPlans(),
    ctx.role === 'MEMBER' ? Promise.resolve([]) : companyInvoices(est.companyId),
    db.select().from(companies).where(eq(companies.id, est.companyId)).limit(1),
    db
      .select()
      .from(companySubscriptions)
      .where(and(eq(companySubscriptions.companyId, est.companyId), eq(companySubscriptions.status, 'ACTIVE')))
      .orderBy(desc(companySubscriptions.startedAt))
      .limit(1),
    companyIsDirect(est.companyId),
  ]);
  const price = (key: PlanKey, cents: number, interval: 'MONTH' | 'YEAR') =>
    fmtEuros(subscriptionPriceCents({ plan: key, direct, interval, planMonthlyCents: cents }));
  return (
    <div className="app-content">
      {sp.paiement === 'ok' ? <div className="alert alert-ok">Paiement confirmé : votre abonnement est actif. Merci !</div> : null}
      {sp.adhesion === 'ok' ? (
        <div className="alert alert-ok">
          Bienvenue sur terricom ! Votre fiche est publiée dans la vitrine nationale. Votre facture est ci-dessous, à régler par virement sous 15 jours.
        </div>
      ) : null}
      {sp.paiement === 'annule' ? <div className="alert alert-warn">Paiement annulé : aucun montant n&apos;a été débité.</div> : null}
      <div style={{ textAlign: 'center', maxWidth: 640, margin: '0 auto' }}>
        {direct ? (
          <>
            <h2 className="display" style={{ fontSize: 40, letterSpacing: '-0.03em', margin: '0 0 8px', lineHeight: 1.05 }}>
              Votre adhésion directe à terricom.
            </h2>
            <p style={{ margin: 0, color: 'var(--muted)' }}>
              Votre commune n’a pas encore rejoint terricom : votre fiche est dans la vitrine nationale, et votre adhésion la finance. Le jour où votre
              collectivité adhère, votre fiche lui est offerte et votre abonnement passe automatiquement au tarif de l’option équivalente (l’Adhésion à 29 €
              devient le Premium à 24 €).
            </p>
          </>
        ) : (
          <>
            <h2 className="display" style={{ fontSize: 40, letterSpacing: '-0.03em', margin: '0 0 8px', lineHeight: 1.05 }}>
              Le gratuit pour être vu.
              <br />
              Le Premium pour faire venir.
            </h2>
            <p style={{ margin: 0, color: 'var(--muted)' }}>Votre fiche reste gratuite pour toujours, financée par votre collectivité.</p>
          </>
        )}
      </div>
      <PlanPicker
        estId={est.id}
        current={ctx.plan}
        canChange={ctx.role !== 'MEMBER'}
        direct={direct}
        billing={{
          name: company?.billingName ?? company?.legalName ?? est.name,
          email: company?.billingEmail ?? ctx.actor.user.email,
          address: company?.billingAddress ?? [est.street, [est.postalCode, est.commune.name].filter(Boolean).join(' ')].filter(Boolean).join(', '),
        }}
        plans={plans.map((p) => {
          const key = p.key as PlanKey;
          const paid = key !== 'ESSENTIEL';
          if (direct && !paid)
            return {
              key,
              name: 'Sans adhésion',
              price: '—',
              unit: '',
              description: 'Sans collectivité partenaire, la fiche n’est pas publiée.',
              features: ['Fiche retirée de la vitrine terricom', 'Réactivable à tout moment', 'Offerte dès que votre collectivité adhère'],
              popular: false,
            };
          return {
            key,
            name: subscriptionName(key, direct, p.name),
            price: paid ? price(key, p.priceMonthlyCents, 'MONTH') : '0 €',
            unit: paid ? ' HT / mois' : '',
            yearPrice: paid ? price(key, p.priceMonthlyCents, 'YEAR') : undefined,
            description: !paid
              ? `Offert par ${territory.legalName.replace(/^Communauté de communes/, 'la CC')}`
              : direct && key === 'PREMIUM'
                ? 'Votre fiche complète dans la vitrine terricom, et tout le Premium.'
                : p.tagline,
            features: direct && key === 'PREMIUM' ? ['Fiche publiée dans la vitrine nationale terricom', ...p.features] : p.features,
            popular: key === 'PREMIUM',
          };
        })}
      />
      {sub ? (
        <div className="card" style={{ borderRadius: 18, padding: 18, fontSize: 14, maxWidth: 720, margin: '0 auto', width: '100%' }}>
          {subscriptionName(sub.plan as PlanKey, sub.direct, plans.find((p) => p.key === sub.plan)?.name ?? sub.plan)}
          {sub.interval === 'YEAR' ? ' (annuel)' : ' (mensuel)'} actif depuis le {fmtShortDate(sub.startedAt)}
          {sub.currentPeriodEnd ? ` · prochaine échéance le ${fmtShortDate(sub.currentPeriodEnd)}` : ''} · paiement{' '}
          {sub.provider === 'STRIPE' ? 'par carte' : 'par virement'}.
        </div>
      ) : null}
      {invoices.length ? (
        <div className="panel" style={{ maxWidth: 720, margin: '0 auto', width: '100%' }}>
          <h2 className="panel-title">Vos factures</h2>
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Numéro</th>
                  <th>Date</th>
                  <th>Montant TTC</th>
                  <th>Statut</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {invoices.map((inv) => (
                  <tr key={inv.id}>
                    <td className="mono">{inv.number}</td>
                    <td>{fmtShortDate(inv.issuedAt)}</td>
                    <td>{fmtEuros(inv.totalTtcCents, { decimals: true })}</td>
                    <td>
                      <span className="tag" style={{ background: INVOICE_STATUS[inv.status]?.bg }}>
                        {INVOICE_STATUS[inv.status]?.label ?? inv.status}
                      </span>
                    </td>
                    <td style={{ textAlign: 'right' }}>
                      <a href={`/api/factures/${inv.id}.pdf`} target="_blank" rel="noopener">
                        PDF
                      </a>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}
    </div>
  );
}
