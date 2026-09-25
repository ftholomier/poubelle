import { and, desc, eq } from 'drizzle-orm';
import { PlanPicker } from '@/components/pro/PlanPicker';
import { fmtEuros, fmtShortDate } from '@/lib/format';
import { db } from '@/server/db';
import { companies, companySubscriptions } from '@/server/db/schema';
import { companyInvoices, getPlans } from '@/server/services/billing';
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
  const [plans, invoices, [company], [sub]] = await Promise.all([
    getPlans(),
    ctx.role === 'MEMBER' ? Promise.resolve([]) : companyInvoices(est.companyId),
    db.select().from(companies).where(eq(companies.id, est.companyId)).limit(1),
    db
      .select()
      .from(companySubscriptions)
      .where(and(eq(companySubscriptions.companyId, est.companyId), eq(companySubscriptions.status, 'ACTIVE')))
      .orderBy(desc(companySubscriptions.startedAt))
      .limit(1),
  ]);
  return (
    <div className="app-content">
      {sp.paiement === 'ok' ? <div className="alert alert-ok">Paiement confirmé : votre abonnement est actif. Merci !</div> : null}
      {sp.paiement === 'annule' ? <div className="alert alert-warn">Paiement annulé : aucun montant n&apos;a été débité.</div> : null}
      <div style={{ textAlign: 'center', maxWidth: 640, margin: '0 auto' }}>
        <h2 className="display" style={{ fontSize: 40, letterSpacing: '-0.03em', margin: '0 0 8px', lineHeight: 1.05 }}>
          Le gratuit pour être vu.
          <br />
          Le Premium pour faire venir.
        </h2>
        <p style={{ margin: 0, color: 'var(--muted)' }}>Votre fiche reste gratuite pour toujours, financée par votre collectivité.</p>
      </div>
      <PlanPicker
        estId={est.id}
        current={ctx.plan}
        canChange={ctx.role !== 'MEMBER'}
        billing={{
          name: company?.billingName ?? company?.legalName ?? est.name,
          email: company?.billingEmail ?? ctx.actor.user.email,
          address: company?.billingAddress ?? [est.street, [est.postalCode, est.commune.name].filter(Boolean).join(' ')].filter(Boolean).join(', '),
        }}
        plans={plans.map((p) => ({
          key: p.key as 'ESSENTIEL' | 'PREMIUM' | 'COMMUNICATION',
          name: p.name,
          price: p.priceMonthlyCents ? fmtEuros(p.priceMonthlyCents) : '0 €',
          unit: p.priceMonthlyCents ? ' HT / mois' : '',
          description: p.key === 'ESSENTIEL' ? `Offert par ${territory.legalName.replace(/^Communauté de communes/, 'la CC')}` : p.tagline,
          features: p.features,
          popular: p.key === 'PREMIUM',
        }))}
      />
      {sub ? (
        <div className="card" style={{ borderRadius: 18, padding: 18, fontSize: 14, maxWidth: 720, margin: '0 auto', width: '100%' }}>
          Abonnement actif depuis le {fmtShortDate(sub.startedAt)}
          {sub.currentPeriodEnd ? ` · prochaine échéance le ${fmtShortDate(sub.currentPeriodEnd)}` : ''} · paiement {sub.provider === 'STRIPE' ? 'par carte' : 'par virement'}.
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
