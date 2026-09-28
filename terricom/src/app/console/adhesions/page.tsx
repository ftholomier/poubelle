import { and, desc, eq } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { DEAL_STAGE_LABELS, type DealStage, type PlanKey } from '@/lib/constants';
import { fmtEuros, fmtInt, fmtShortDate } from '@/lib/format';
import { monthlyEquivalentCents, subscriptionName, type BillingInterval } from '@/lib/pricing';
import { requirePlatformStaff } from '@/server/authz';
import { db } from '@/server/db';
import { communes, companies, companySubscriptions, establishments, plans } from '@/server/db/schema';
import { directMembersByEpci } from '@/server/services/direct';
import { ensureNationalTerritory } from '@/server/services/territories';

export const metadata: Metadata = { title: 'Adhésions directes' };

/**
 * Entreprises qui adhèrent directement (aucune collectivité partenaire) : revenu, et levier commercial par
 * intercommunalité — « 14 entreprises de votre territoire adhèrent déjà ».
 */
export default async function DirectMembersPage() {
  await requirePlatformStaff();
  const national = await ensureNationalTerritory();
  const [{ total, byEpci }, members] = await Promise.all([
    directMembersByEpci(),
    db
      .select({
        estId: establishments.id,
        name: establishments.name,
        status: establishments.status,
        commune: communes.name,
        epci: communes.epciName,
        company: companies.legalName,
        plan: companySubscriptions.plan,
        direct: companySubscriptions.direct,
        interval: companySubscriptions.interval,
        since: companySubscriptions.startedAt,
        planName: plans.name,
        planCents: plans.priceMonthlyCents,
      })
      .from(establishments)
      .innerJoin(communes, eq(communes.id, establishments.communeId))
      .innerJoin(companies, eq(companies.id, establishments.companyId))
      .leftJoin(companySubscriptions, and(eq(companySubscriptions.companyId, companies.id), eq(companySubscriptions.status, 'ACTIVE')))
      .leftJoin(plans, eq(plans.key, companySubscriptions.plan))
      .where(eq(establishments.territoryId, national.id))
      .orderBy(desc(companySubscriptions.startedAt)),
  ]);
  const mrr = members.reduce(
    (s, m) =>
      s +
      (m.plan
        ? monthlyEquivalentCents({
            plan: m.plan as PlanKey,
            direct: Boolean(m.direct),
            interval: (m.interval ?? 'MONTH') as BillingInterval,
            planMonthlyCents: m.planCents ?? 0,
          })
        : 0),
    0,
  );
  const withDeal = byEpci.filter((e) => e.dealId).length;
  return (
    <div className="app-content">
      <div className="kpi-row" style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 12 }}>
        <div className="panel">
          <div className="panel-title">Adhérents directs</div>
          <div className="display" style={{ fontSize: 40 }}>
            {fmtInt(total)}
          </div>
          <small style={{ color: 'var(--muted)' }}>fiches publiées dans la vitrine nationale</small>
        </div>
        <div className="panel">
          <div className="panel-title">Revenu mensuel</div>
          <div className="display" style={{ fontSize: 40 }}>
            {fmtEuros(mrr)}
          </div>
          <small style={{ color: 'var(--muted)' }}>HT, abonnements annuels ramenés au mois</small>
        </div>
        <div className="panel">
          <div className="panel-title">Intercommunalités à convaincre</div>
          <div className="display" style={{ fontSize: 40 }}>
            {fmtInt(byEpci.length)}
          </div>
          <small style={{ color: 'var(--muted)' }}>dont {fmtInt(withDeal)} déjà dans le suivi commercial</small>
        </div>
      </div>

      <div className="panel">
        <h2 className="panel-title">Le levier : des entreprises du territoire adhèrent déjà</h2>
        <p style={{ margin: '0 0 12px', color: 'var(--muted)' }}>
          Chaque adhésion ouvre ou alimente l’affaire de l’intercommunalité. Quand elle signe, les fiches rejoignent son portail, offertes, et l’Adhésion passe
          de 29 à 24 € : « vous offrez leur fiche à vos commerçants ».{' '}
          <Link href={`/${national.slug}`} target="_blank">
            Voir la vitrine nationale ↗
          </Link>
        </p>
        {byEpci.length ? (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th>Intercommunalité</th>
                  <th>Adhérents directs</th>
                  <th>Suivi commercial</th>
                </tr>
              </thead>
              <tbody>
                {byEpci.map((e) => (
                  <tr key={e.epciSiren}>
                    <td>
                      <strong>{e.epciName}</strong>{' '}
                      <span className="mono" style={{ color: 'var(--muted)', fontSize: 12 }}>
                        {e.epciSiren}
                      </span>
                    </td>
                    <td>{fmtInt(e.count)}</td>
                    <td>
                      {e.dealId ? (
                        <Link href={`/console?crm=pro&deal=${e.dealId}`}>{DEAL_STAGE_LABELS[e.stage as DealStage] ?? e.stage} →</Link>
                      ) : (
                        <span style={{ color: 'var(--muted)' }}>Aucune affaire</span>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p style={{ margin: 0 }}>Aucune adhésion directe pour l’instant.</p>
        )}
      </div>

      <div className="panel">
        <h2 className="panel-title">Toutes les adhésions</h2>
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th>Entreprise</th>
                <th>Commune</th>
                <th>Formule</th>
                <th>Depuis</th>
                <th>Statut</th>
              </tr>
            </thead>
            <tbody>
              {members.map((m) => (
                <tr key={m.estId}>
                  <td>
                    <strong>{m.name}</strong>
                    <br />
                    <small style={{ color: 'var(--muted)' }}>{m.company}</small>
                  </td>
                  <td>
                    {m.commune}
                    {m.epci ? <small style={{ display: 'block', color: 'var(--muted)' }}>{m.epci}</small> : null}
                  </td>
                  <td>
                    {m.plan
                      ? `${subscriptionName(m.plan as PlanKey, Boolean(m.direct), m.planName ?? m.plan)} · ${m.interval === 'YEAR' ? 'annuelle' : 'mensuelle'}`
                      : '—'}
                  </td>
                  <td>{m.since ? fmtShortDate(m.since) : '—'}</td>
                  <td>{m.status === 'SUSPENDED' ? 'En attente ou résiliée' : 'Publiée'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
