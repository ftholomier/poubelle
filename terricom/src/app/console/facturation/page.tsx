import { asc } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { cancelInvoiceAction, createManualInvoiceAction, markInvoicePaidAction, remindInvoiceAction, renewLicenceAction, updatePlanAction } from './actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { fmtEuros, fmtShortDate, parisDate } from '@/lib/format';
import { requirePlatformStaff } from '@/server/authz';
import { db } from '@/server/db';
import { plans, territories } from '@/server/db/schema';
import { recurringRevenue } from '@/server/services/billing';
import { flagOverdueInvoices, invoiceKpis, licencesToRenew, listInvoices, type InvoiceFilter } from '@/server/services/console-billing';

export const metadata: Metadata = { title: 'Facturation' };

type Props = { searchParams: Promise<{ statut?: string; client?: string; territoire?: string }> };

const STATUS: Record<string, { label: string; bg: string }> = {
  DRAFT: { label: 'Brouillon', bg: '#E4E7E1' },
  ISSUED: { label: 'Émise', bg: '#CDE3F2' },
  PAID: { label: 'Payée', bg: '#D6E8B4' },
  OVERDUE: { label: 'En retard', bg: '#F5DCD8' },
  CANCELLED: { label: 'Annulée', bg: '#E4E7E1' },
};

const FLAGS: [string, string][] = [
  ['scheduling', 'Programmation'],
  ['newsletterChannel', 'Canal newsletter du territoire'],
  ['socialChannel', 'Réseaux sociaux'],
  ['advancedStats', 'Statistiques avancées'],
  ['jobs', 'Offres d’emploi'],
  ['appointments', 'Prise de rendez-vous'],
  ['miniSite', 'Mini-site'],
  ['customerNewsletter', 'Newsletter clients'],
  ['contactsExport', 'Export des contacts'],
  ['customQr', 'QR codes personnalisés'],
];

export default async function BillingPage({ searchParams }: Props) {
  const sp = await searchParams;
  const actor = await requirePlatformStaff();
  const canEdit = actor.isPlatformAdmin;
  await flagOverdueInvoices();
  const filter: InvoiceFilter = {
    status: sp.statut && sp.statut in STATUS ? (sp.statut as InvoiceFilter['status']) : undefined,
    customerType: sp.client === 'TERRITORY' || sp.client === 'COMPANY' ? sp.client : undefined,
    territoryId: sp.territoire && /^[0-9a-f-]{36}$/.test(sp.territoire) ? sp.territoire : undefined,
  };
  const [k, rev, list, renew, terrs, allPlans] = await Promise.all([
    invoiceKpis(),
    recurringRevenue(),
    listInvoices(filter),
    licencesToRenew(90),
    db.select({ id: territories.id, name: territories.name }).from(territories).orderBy(asc(territories.name)),
    db.select().from(plans).orderBy(asc(plans.sortOrder)),
  ]);
  const selectedTerritory = filter.territoryId ? terrs.find((t) => t.id === filter.territoryId) : null;
  const tiles = [
    { l: 'ARR', v: fmtEuros(rev.arrCents), d: `licences ${fmtEuros(rev.licencesCents)} · Premium ${fmtEuros(rev.premiumCents)}`, dark: true },
    { l: 'Facturé (12 mois)', v: fmtEuros(k.billed), d: 'TTC, hors annulations' },
    { l: 'Encaissé (12 mois)', v: fmtEuros(k.paid), d: 'TTC' },
    { l: 'En attente', v: fmtEuros(k.pending), d: 'dans les délais' },
    { l: 'En retard', v: fmtEuros(k.overdue), d: `${k.overdueCount} facture${k.overdueCount > 1 ? 's' : ''}`, warn: k.overdue > 0 },
  ];
  const today = parisDate();

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 12 }}>
        {tiles.map((x) => (
          <div
            key={x.l}
            style={{
              background: x.dark ? 'var(--ink)' : '#fff',
              color: x.dark ? 'var(--amber)' : 'var(--text)',
              borderRadius: 18,
              padding: 18,
              border: x.dark ? 0 : '1px solid var(--console-line)',
            }}
          >
            <div style={{ fontSize: 13, fontWeight: 600, opacity: 0.75 }}>{x.l}</div>
            <div className="display" style={{ fontSize: 30, letterSpacing: '-0.03em', lineHeight: 1.15, color: x.warn ? 'var(--brick)' : undefined }}>
              {x.v}
            </div>
            <div style={{ fontSize: 12, fontWeight: 700, opacity: 0.85 }}>{x.d}</div>
          </div>
        ))}
      </div>

      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.6fr) minmax(300px,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <section className="console-card" style={{ borderRadius: 20, overflow: 'hidden' }}>
          <div style={{ display: 'flex', gap: 10, padding: '14px 18px', alignItems: 'center', flexWrap: 'wrap' }}>
            <b>Factures{selectedTerritory ? ` · ${selectedTerritory.name}` : ''}</b>
            {selectedTerritory ? (
              <Link href="/console/facturation" style={{ fontSize: 12 }}>
                Tous les clients
              </Link>
            ) : null}
            <form method="get" style={{ display: 'flex', gap: 6, marginLeft: 'auto', flexWrap: 'wrap' }}>
              {filter.territoryId ? <input type="hidden" name="territoire" value={filter.territoryId} /> : null}
              <select
                name="statut"
                className="input"
                defaultValue={filter.status ?? ''}
                aria-label="Statut"
                style={{ padding: '7px 10px', fontSize: 13, width: 'auto' }}
              >
                <option value="">Tous statuts</option>
                {Object.entries(STATUS).map(([key, s]) => (
                  <option key={key} value={key}>
                    {s.label}
                  </option>
                ))}
              </select>
              <select
                name="client"
                className="input"
                defaultValue={filter.customerType ?? ''}
                aria-label="Type de client"
                style={{ padding: '7px 10px', fontSize: 13, width: 'auto' }}
              >
                <option value="">Tous clients</option>
                <option value="TERRITORY">Collectivités</option>
                <option value="COMPANY">Entreprises</option>
              </select>
              <button type="submit" className="btn btn-dark btn-sm">
                Filtrer
              </button>
            </form>
          </div>
          <div style={{ overflowX: 'auto' }}>
            <table className="console-table">
              <thead>
                <tr>
                  <th>N°</th>
                  <th>Client</th>
                  <th>Émise</th>
                  <th>Échéance</th>
                  <th style={{ textAlign: 'right' }}>TTC</th>
                  <th>Statut</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {list.map((inv) => (
                  <tr key={inv.id}>
                    <td style={{ fontFamily: 'ui-monospace,monospace', fontSize: 12, whiteSpace: 'nowrap' }}>
                      <a href={`/api/factures/${inv.id}.pdf`} target="_blank" rel="noreferrer">
                        {inv.number}
                      </a>
                    </td>
                    <td>
                      <b>{inv.customerName}</b>
                      <div style={{ fontSize: 12, color: 'var(--muted)' }}>{inv.lines[0]?.label}</div>
                    </td>
                    <td>{fmtShortDate(inv.issuedAt)}</td>
                    <td style={{ color: inv.status === 'OVERDUE' ? 'var(--brick)' : undefined }}>{fmtShortDate(inv.dueAt)}</td>
                    <td style={{ textAlign: 'right', fontWeight: 700, whiteSpace: 'nowrap' }}>{fmtEuros(inv.totalTtcCents, { decimals: true })}</td>
                    <td>
                      <span
                        style={{
                          fontSize: 11,
                          fontWeight: 800,
                          padding: '3px 8px',
                          borderRadius: 999,
                          background: STATUS[inv.status].bg,
                          whiteSpace: 'nowrap',
                        }}
                      >
                        {STATUS[inv.status].label}
                      </span>
                      {inv.paidAt ? <div style={{ fontSize: 11, color: 'var(--muted)', marginTop: 2 }}>le {fmtShortDate(inv.paidAt)}</div> : null}
                    </td>
                    <td>
                      {canEdit && (inv.status === 'ISSUED' || inv.status === 'OVERDUE') ? (
                        <details className="console-details">
                          <summary style={{ fontSize: 12, fontWeight: 700, color: 'var(--green)' }}>Gérer</summary>
                          <div style={{ display: 'flex', flexDirection: 'column', gap: 6, marginTop: 6, minWidth: 220 }}>
                            <ActionForm action={markInvoicePaidAction} style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
                              <input type="hidden" name="id" value={inv.id} />
                              <select
                                name="method"
                                className="input"
                                defaultValue={inv.customerType === 'TERRITORY' ? 'MANDAT_ADMINISTRATIF' : 'VIREMENT'}
                                aria-label="Moyen de paiement"
                                style={{ padding: '6px 8px', fontSize: 12 }}
                              >
                                <option value="MANDAT_ADMINISTRATIF">Mandat administratif</option>
                                <option value="VIREMENT">Virement</option>
                                <option value="PRELEVEMENT">Prélèvement</option>
                                <option value="CARTE">Carte</option>
                                <option value="CHEQUE">Chèque</option>
                              </select>
                              <input
                                type="date"
                                name="paidAt"
                                className="input"
                                defaultValue={today}
                                max={today}
                                aria-label="Date d’encaissement"
                                style={{ padding: '6px 8px', fontSize: 12 }}
                              />
                              <SubmitButton className="btn btn-brand btn-xs">Encaisser</SubmitButton>
                            </ActionForm>
                            <div style={{ display: 'flex', gap: 4 }}>
                              <ActionForm action={remindInvoiceAction}>
                                <input type="hidden" name="id" value={inv.id} />
                                <SubmitButton className="btn btn-outline btn-xs">Relancer</SubmitButton>
                              </ActionForm>
                              <ActionForm action={cancelInvoiceAction}>
                                <input type="hidden" name="id" value={inv.id} />
                                <SubmitButton className="btn btn-ghost btn-xs">Annuler</SubmitButton>
                              </ActionForm>
                            </div>
                          </div>
                        </details>
                      ) : null}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            {list.length === 0 ? <p style={{ padding: '6px 18px 16px', color: 'var(--muted)', margin: 0 }}>Aucune facture pour ces critères.</p> : null}
          </div>
        </section>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <section className="console-card" style={{ borderRadius: 20, padding: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <b>Licences à renouveler (90 jours)</b>
            {renew.length === 0 ? <span style={{ fontSize: 13, color: 'var(--muted)' }}>Aucune échéance proche.</span> : null}
            {renew.map((c) => (
              <div key={c.id} style={{ borderTop: '1px solid var(--console-line-2)', paddingTop: 10, display: 'flex', flexDirection: 'column', gap: 6 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8, fontSize: 14 }}>
                  <b>{c.territoryName}</b>
                  <span style={{ color: c.endsAt && c.endsAt < today ? 'var(--brick)' : 'var(--muted)', fontSize: 12, fontWeight: 700 }}>
                    échéance {c.endsAt ? fmtShortDate(c.endsAt) : '—'}
                  </span>
                </div>
                {canEdit ? (
                  <ActionForm action={renewLicenceAction} style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                    <input type="hidden" name="id" value={c.id} />
                    <input
                      name="amount"
                      type="number"
                      min={0}
                      step={100}
                      className="input"
                      defaultValue={c.amountCents / 100}
                      aria-label="Montant HT"
                      style={{ padding: '6px 8px', fontSize: 13, width: 110 }}
                    />
                    <span style={{ fontSize: 12, color: 'var(--muted)' }}>€ HT / an</span>
                    <SubmitButton className="btn btn-dark btn-xs" style={{ marginLeft: 'auto' }}>
                      Renouveler et facturer
                    </SubmitButton>
                  </ActionForm>
                ) : null}
              </div>
            ))}
          </section>
          {canEdit ? (
            <section className="console-card" style={{ borderRadius: 20, padding: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
              <b>Nouvelle facture collectivité</b>
              <ActionForm action={createManualInvoiceAction} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                <select name="territoryId" className="input" defaultValue={filter.territoryId ?? ''} required aria-label="Territoire client">
                  <option value="" disabled>
                    Territoire client…
                  </option>
                  {terrs.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.name}
                    </option>
                  ))}
                </select>
                {[0, 1, 2].map((i) => (
                  <div key={i} style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) 54px 90px', gap: 6 }}>
                    <input
                      name={`label${i}`}
                      className="input"
                      placeholder={i === 0 ? 'Prestation (ex. formation des agents)' : 'Ligne facultative'}
                      aria-label={`Libellé ligne ${i + 1}`}
                      style={{ fontSize: 13 }}
                      required={i === 0}
                    />
                    <input
                      name={`qty${i}`}
                      type="number"
                      min={1}
                      step={1}
                      defaultValue={1}
                      className="input"
                      aria-label={`Quantité ligne ${i + 1}`}
                      style={{ fontSize: 13, padding: '8px' }}
                    />
                    <input
                      name={`unit${i}`}
                      inputMode="decimal"
                      className="input"
                      placeholder="€ HT"
                      aria-label={`Prix unitaire HT ligne ${i + 1}`}
                      style={{ fontSize: 13 }}
                    />
                  </div>
                ))}
                <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                  <input name="issuedAt" type="date" className="input" defaultValue={today} aria-label="Date d’émission" style={{ fontSize: 13 }} />
                  <SubmitButton className="btn btn-dark btn-sm">Émettre</SubmitButton>
                </div>
                <p style={{ margin: 0, fontSize: 12, color: 'var(--muted)' }}>
                  TVA 20 %, échéance 30 jours (délai global de paiement du secteur public). Numérotation continue.
                </p>
              </ActionForm>
            </section>
          ) : null}
        </div>
      </div>

      <section className="console-card" style={{ borderRadius: 20, padding: 20 }}>
        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap', marginBottom: 12 }}>
          <b>Offres entreprises</b>
          <span style={{ fontSize: 12, color: 'var(--muted)' }}>Les modifications s’appliquent aux nouveaux abonnements et aux pages tarifs.</span>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))', gap: 14 }}>
          {allPlans.map((p) => (
            <ActionForm key={p.key} action={updatePlanAction} resetOnSuccess={false} className="plan-editor">
              <input type="hidden" name="key" value={p.key} />
              <fieldset disabled={!canEdit} style={{ border: 0, padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 8 }}>
                <div style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) 110px', gap: 8 }}>
                  <label className="field">
                    <span>Nom</span>
                    <input name="name" className="input" defaultValue={p.name} />
                  </label>
                  <label className="field">
                    <span>€ HT / mois</span>
                    <input name="price" type="number" min={0} step={0.5} className="input" defaultValue={p.priceMonthlyCents / 100} />
                  </label>
                </div>
                <label className="field">
                  <span>Accroche</span>
                  <input name="tagline" className="input" defaultValue={p.tagline} />
                </label>
                <label className="field">
                  <span>Avantages (un par ligne)</span>
                  <textarea name="features" className="textarea" rows={4} defaultValue={p.features.join('\n')} style={{ fontSize: 13 }} />
                </label>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                  <label className="field">
                    <span>Publications / mois</span>
                    <input name="postsPerMonth" type="number" min={0} className="input" defaultValue={p.limits.postsPerMonth ?? ''} placeholder="illimité" />
                  </label>
                  <label className="field">
                    <span>Rédactions IA / mois</span>
                    <input name="aiPerMonth" type="number" min={0} className="input" defaultValue={p.limits.aiPerMonth ?? ''} placeholder="illimité" />
                  </label>
                </div>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '4px 10px' }}>
                  {FLAGS.map(([f, label]) => (
                    <label key={f} className="checkbox" style={{ fontSize: 12, alignItems: 'center' }}>
                      <input type="checkbox" name={`f_${f}`} defaultChecked={Boolean(p.limits[f as keyof typeof p.limits])} /> {label}
                    </label>
                  ))}
                </div>
                {canEdit ? (
                  <SubmitButton className="btn btn-dark btn-sm" style={{ alignSelf: 'flex-start' }} pendingLabel="Enregistrement…">
                    Enregistrer l’offre
                  </SubmitButton>
                ) : null}
              </fieldset>
            </ActionForm>
          ))}
        </div>
      </section>
    </div>
  );
}
