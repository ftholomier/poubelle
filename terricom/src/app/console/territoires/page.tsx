import type { Metadata } from 'next';
import Link from 'next/link';
import {
  attachCommunesAction,
  detachCommuneAction,
  setTerritoryStatusAction,
  startSupportAccessAction,
  toggleModuleAction,
  updateQuotasAction,
} from './actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { TERRITORY_STATUS, type TerritoryStatus } from '@/lib/constants';
import { fmtEuros, fmtInt, fullName } from '@/lib/format';
import { requirePlatformStaff } from '@/server/authz';
import { groupOf } from '@/server/services/crm';
import { listClients, territoryCommunes, territoryPanel } from '@/server/services/console-territories';
import { db } from '@/server/db';
import { deals, territories, type TerritorySettings } from '@/server/db/schema';
import { asc, eq } from 'drizzle-orm';

export const metadata: Metadata = { title: 'Territoires & abonnements' };

type Props = { searchParams: Promise<{ t?: string; d?: string; cree?: string; ignorees?: string }> };

const COLS = '1.8fr 1.2fr 0.8fr 0.9fr 0.9fr 1fr';

export default async function ConsoleTerritories({ searchParams }: Props) {
  const sp = await searchParams;
  const actor = await requirePlatformStaff();
  const clients = await listClients();
  const selectedDeal = sp.d ? clients.find((c) => c.kind === 'deal' && c.id === sp.d) : undefined;
  const selected = selectedDeal ?? clients.find((c) => c.kind === 'territory' && c.id === sp.t) ?? clients.find((c) => c.kind === 'territory');
  const panel = selected?.kind === 'territory' ? await territoryPanel(selected.id) : null;
  const [deal] = selectedDeal ? await db.select().from(deals).where(eq(deals.id, selectedDeal.id)).limit(1) : [];
  const [membership, allTerritories] = panel
    ? await Promise.all([
        territoryCommunes(panel.territory.id),
        db.select({ id: territories.id, name: territories.name }).from(territories).orderBy(asc(territories.name)),
      ])
    : [null, []];
  const canAdmin = actor.isPlatformAdmin;
  const canSupport = actor.isPlatformAdmin || actor.roles.some((r) => r.role === 'PLATFORM_SUPPORT');
  const impersonatingHere = panel && actor.impersonation?.territoryId === panel.territory.id;

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      {sp.cree !== undefined ? (
        <div className="alert alert-ok" role="status">
          Territoire créé : {sp.cree} {Number(sp.cree) > 1 ? 'communes rattachées' : 'commune rattachée'}.
          {sp.ignorees ? ` Non rattachées : ${sp.ignorees}.` : ''} Configurez maintenant les modules et envoyez les accès aux administrateurs.
        </div>
      ) : null}
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.5fr) minmax(320px,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <section className="console-card" style={{ borderRadius: 20, overflow: 'hidden' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', padding: '14px 18px', alignItems: 'center', gap: 10 }}>
            <b>{clients.length} clients</b>
            {canAdmin ? (
              <Link href="/console/territoires/nouveau" className="btn btn-dark btn-sm" style={{ borderRadius: 10 }}>
                + Nouveau territoire
              </Link>
            ) : null}
          </div>
          <div style={{ overflowX: 'auto' }}>
            <div style={{ minWidth: 760 }} role="table" aria-label="Clients de la plateforme">
              <div
                role="row"
                style={{
                  display: 'grid',
                  gridTemplateColumns: COLS,
                  gap: 10,
                  padding: '10px 18px',
                  fontSize: 11,
                  fontWeight: 800,
                  color: 'var(--muted)',
                  textTransform: 'uppercase',
                  letterSpacing: '.06em',
                  borderTop: '1px solid var(--console-bg)',
                  borderBottom: '1px solid var(--console-bg)',
                }}
              >
                <span role="columnheader">Client</span>
                <span role="columnheader">Type</span>
                <span role="columnheader">Communes</span>
                <span role="columnheader">Fiches</span>
                <span role="columnheader">Premium</span>
                <span role="columnheader">Licence / an</span>
              </div>
              {clients.map((c) => {
                const on = selected?.id === c.id;
                return (
                  <Link
                    key={`${c.kind}-${c.id}`}
                    href={c.kind === 'deal' ? `/console/territoires?d=${c.id}` : `/console/territoires?t=${c.id}`}
                    scroll={false}
                    role="row"
                    aria-current={on ? 'true' : undefined}
                    className="console-row"
                    style={{
                      display: 'grid',
                      gridTemplateColumns: COLS,
                      gap: 10,
                      padding: '11px 18px',
                      fontSize: 14,
                      alignItems: 'center',
                      borderBottom: '1px solid var(--console-line-2)',
                      background: on ? '#F2F8F4' : 'transparent',
                      color: 'var(--text)',
                    }}
                  >
                    <div role="cell" style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
                      <span style={{ width: 10, height: 10, borderRadius: '50%', background: c.color, flexShrink: 0 }} />
                      <div>
                        <b>{c.name}</b>
                        <div style={{ fontSize: 12, color: 'var(--muted)' }}>{c.statusLabel}</div>
                      </div>
                    </div>
                    <span role="cell" style={{ fontSize: 13 }}>
                      {c.typeLabel}
                    </span>
                    <span role="cell">{fmtInt(c.communes)}</span>
                    <span role="cell">{c.establishments !== null ? fmtInt(c.establishments) : '—'}</span>
                    <span role="cell">{c.premium !== null ? fmtInt(c.premium) : '—'}</span>
                    <b role="cell">{c.licenceCents ? fmtEuros(c.licenceCents) : '—'}</b>
                  </Link>
                );
              })}
            </div>
          </div>
        </section>

        <div className="sticky-aside" style={{ display: 'flex', flexDirection: 'column', gap: 14, position: 'sticky', top: 20 }}>
          {panel ? (
            <>
              <div style={{ background: 'var(--ink)', color: 'var(--cream)', borderRadius: 20, padding: 20, display: 'flex', flexDirection: 'column', gap: 6 }}>
                <div style={{ fontSize: 12, color: '#8FA197' }}>
                  {panel.typeLabel} · {panel.statusLabel}
                </div>
                <div className="display" style={{ fontSize: 26, letterSpacing: '-0.02em' }}>
                  {panel.territory.name}
                </div>
                <div style={{ fontSize: 13, color: '#AEBDB5' }}>{panel.host}</div>
                <div style={{ display: 'flex', gap: 8, marginTop: 8, flexWrap: 'wrap', alignItems: 'flex-start' }}>
                  {impersonatingHere ? (
                    <Link href="/collectivite" className="btn btn-amber btn-sm" style={{ borderRadius: 9, fontSize: 12 }}>
                      Ouvrir le back-office
                    </Link>
                  ) : canSupport ? (
                    <details className="console-details">
                      <summary className="btn btn-amber btn-sm" style={{ borderRadius: 9, fontSize: 12 }}>
                        Accès support temporaire
                      </summary>
                      <ActionForm
                        action={startSupportAccessAction}
                        resetOnSuccess={false}
                        style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 10, minWidth: 260 }}
                      >
                        <input type="hidden" name="territoryId" value={panel.territory.id} />
                        <label className="field field-dark">
                          <span>Ticket concerné</span>
                          <select name="ticketId" className="input" defaultValue={panel.tickets[0]?.id ?? ''}>
                            <option value="">Sans ticket (préciser le motif)</option>
                            {panel.tickets.map((tk) => (
                              <option key={tk.id} value={tk.id}>
                                #{tk.number} · {tk.subject}
                              </option>
                            ))}
                          </select>
                        </label>
                        <label className="field field-dark">
                          <span>Motif</span>
                          <input name="reason" className="input" maxLength={300} placeholder="Ex. vérifier un import à la demande de l’admin" />
                        </label>
                        <p style={{ margin: 0, fontSize: 12, color: '#AEBDB5' }}>
                          Accès de 30 minutes, visible par la collectivité et intégralement journalisé.
                        </p>
                        <SubmitButton className="btn btn-amber btn-sm" pendingLabel="Ouverture…">
                          Ouvrir l’accès (30 min)
                        </SubmitButton>
                      </ActionForm>
                    </details>
                  ) : null}
                  <Link
                    href={`/console/facturation?territoire=${panel.territory.id}`}
                    className="btn btn-sm console-btn-ghost-dark"
                    style={{ borderRadius: 9, fontSize: 12 }}
                  >
                    Factures
                  </Link>
                  <a
                    href={`/${panel.territory.slug}`}
                    target="_blank"
                    rel="noreferrer"
                    className="btn btn-sm console-btn-ghost-dark"
                    style={{ borderRadius: 9, fontSize: 12 }}
                  >
                    Portail ↗
                  </a>
                </div>
              </div>

              <section className="console-card" style={{ borderRadius: 20, padding: 18, display: 'flex', flexDirection: 'column', gap: 4 }}>
                <b style={{ marginBottom: 6 }}>Modules activés</b>
                {panel.modules.map((m) => (
                  <div
                    key={m.key}
                    style={{
                      display: 'flex',
                      justifyContent: 'space-between',
                      alignItems: 'center',
                      padding: '8px 0',
                      borderTop: '1px solid var(--console-line-2)',
                      fontSize: 14,
                    }}
                  >
                    <div>
                      <span style={{ fontWeight: 600 }}>{m.label}</span>{' '}
                      <span style={{ fontSize: 10, fontWeight: 800, padding: '2px 6px', borderRadius: 4, background: m.tag === 'MVP' ? '#D6E8B4' : '#DCD3F3' }}>
                        {m.tag}
                      </span>
                    </div>
                    {canAdmin ? (
                      <form action={toggleModuleAction}>
                        <input type="hidden" name="territoryId" value={panel.territory.id} />
                        <input type="hidden" name="module" value={m.key} />
                        <input type="hidden" name="enabled" value={m.enabled ? '0' : '1'} />
                        <button
                          type="submit"
                          role="switch"
                          aria-checked={m.enabled}
                          aria-label={`${m.enabled ? 'Désactiver' : 'Activer'} le module ${m.label}`}
                          className="switch"
                        />
                      </form>
                    ) : (
                      <span role="switch" aria-checked={m.enabled} aria-disabled="true" aria-label={m.label} className="switch" />
                    )}
                  </div>
                ))}
              </section>

              <section className="console-card" style={{ borderRadius: 20, padding: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
                <b>Quotas</b>
                {panel.quotas.map((q) => {
                  const pct = q.max ? Math.min(100, (q.used / q.max) * 100) : 0;
                  return (
                    <div key={q.key}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 4 }}>
                        <span>{q.label}</span>
                        <b>
                          {fmtInt(q.used)} / {fmtInt(q.max)}
                        </b>
                      </div>
                      <div
                        style={{ height: 7, background: 'var(--console-bg)', borderRadius: 4, overflow: 'hidden' }}
                        role="progressbar"
                        aria-label={q.label}
                        aria-valuenow={Math.round(pct)}
                        aria-valuemin={0}
                        aria-valuemax={100}
                      >
                        <div style={{ height: '100%', width: `${pct}%`, background: pct >= 90 ? 'var(--danger)' : q.color }} />
                      </div>
                    </div>
                  );
                })}
                {canAdmin ? (
                  <details className="console-details">
                    <summary style={{ fontSize: 13, fontWeight: 700, color: 'var(--green)', cursor: 'pointer' }}>Ajuster les quotas</summary>
                    <ActionForm
                      action={updateQuotasAction}
                      resetOnSuccess={false}
                      style={{ display: 'grid', gridTemplateColumns: 'repeat(3,minmax(0,1fr))', gap: 8, marginTop: 10 }}
                    >
                      <input type="hidden" name="territoryId" value={panel.territory.id} />
                      <label className="field">
                        <span>Fiches</span>
                        <input className="input" name="quotaEstablishments" type="number" min={10} defaultValue={panel.territory.quotaEstablishments} />
                      </label>
                      <label className="field">
                        <span>Emails / mois</span>
                        <input
                          className="input"
                          name="quotaEmailsMonthly"
                          type="number"
                          min={0}
                          step={1000}
                          defaultValue={panel.territory.quotaEmailsMonthly}
                        />
                      </label>
                      <label className="field">
                        <span>Crédits IA</span>
                        <input
                          className="input"
                          name="quotaAiCreditsMonthly"
                          type="number"
                          min={0}
                          step={100}
                          defaultValue={panel.territory.quotaAiCreditsMonthly}
                        />
                      </label>
                      <SubmitButton className="btn btn-dark btn-sm" style={{ gridColumn: '1 / -1', justifySelf: 'start' }} pendingLabel="Enregistrement…">
                        Enregistrer les quotas
                      </SubmitButton>
                    </ActionForm>
                  </details>
                ) : null}
              </section>

              <section className="console-card" style={{ borderRadius: 20, padding: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
                <b>Contrat & administrateurs</b>
                <div style={{ fontSize: 13, color: 'var(--muted)' }}>
                  {panel.admins.length
                    ? panel.admins
                        .map(
                          (a) =>
                            `${fullName({ firstName: a.first_name, lastName: a.last_name, email: a.email })}${a.mfa_enabled ? ' (MFA ✓)' : ' (MFA à activer)'}`,
                        )
                        .join(' · ')
                    : 'Aucun administrateur territorial : invitez-le depuis la création ou le back-office.'}
                </div>
                {canAdmin ? (
                  <ActionForm
                    action={setTerritoryStatusAction}
                    resetOnSuccess={false}
                    style={{ display: 'flex', gap: 8, alignItems: 'flex-end', flexWrap: 'wrap' }}
                  >
                    <input type="hidden" name="territoryId" value={panel.territory.id} />
                    <label className="field" style={{ flex: 1, minWidth: 150 }}>
                      <span>Statut du client</span>
                      <select name="status" className="input" defaultValue={panel.territory.status}>
                        {(Object.keys(TERRITORY_STATUS) as TerritoryStatus[]).map((s) => (
                          <option key={s} value={s}>
                            {TERRITORY_STATUS[s].label}
                          </option>
                        ))}
                      </select>
                    </label>
                    <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 13, fontWeight: 600, paddingBottom: 10 }}>
                      <input type="checkbox" name="isPilot" defaultChecked={panel.territory.isPilot} /> Pilote
                    </label>
                    <label
                      style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 13, fontWeight: 600, paddingBottom: 10 }}
                      title="Aucune mention de terricom sur le portail ni dans les emails du territoire"
                    >
                      <input type="checkbox" name="whiteLabel" defaultChecked={Boolean((panel.territory.settings as TerritorySettings | null)?.whiteLabel)} />{' '}
                      Marque blanche
                    </label>
                    <SubmitButton className="btn btn-outline btn-sm" pendingLabel="…">
                      Appliquer
                    </SubmitButton>
                  </ActionForm>
                ) : null}
              </section>

              {membership ? (
                <section className="console-card" style={{ borderRadius: 20, padding: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
                  <b>Communes rattachées ({membership.current.length})</b>
                  <div style={{ display: 'flex', flexWrap: 'wrap', gap: 6, maxHeight: 150, overflowY: 'auto' }}>
                    {membership.current.map((c) => (
                      <span
                        key={c.id}
                        title={`INSEE ${c.insee_code} · depuis le ${c.valid_from.split('-').reverse().join('/')}`}
                        style={{ fontSize: 12, padding: '4px 9px', borderRadius: 999, background: 'var(--sand)' }}
                      >
                        {c.name} · {fmtInt(c.establishments)}
                      </span>
                    ))}
                  </div>
                  {canAdmin ? (
                    <details className="console-details">
                      <summary>Rattacher, détacher ou transférer une commune</summary>
                      <ActionForm action={attachCommunesAction} style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 10 }}>
                        <input type="hidden" name="territoryId" value={panel.territory.id} />
                        <label className="field">
                          <span>Codes INSEE à rattacher</span>
                          <input name="codes" className="input" placeholder="ex. 25424, 25178" required />
                        </label>
                        <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 13 }}>
                          <input type="checkbox" name="transfer" /> Transférer une commune qui appartient à un autre territoire (fiches et contenus suivent)
                        </label>
                        <SubmitButton className="btn btn-outline btn-sm" pendingLabel="Rattachement…" style={{ alignSelf: 'flex-start' }}>
                          Rattacher
                        </SubmitButton>
                      </ActionForm>
                      <ActionForm action={detachCommuneAction} style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 14 }}>
                        <input type="hidden" name="territoryId" value={panel.territory.id} />
                        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                          <label className="field">
                            <span>Commune</span>
                            <select name="communeId" className="input" required>
                              {membership.current.map((c) => (
                                <option key={c.id} value={c.id}>
                                  {c.name} ({fmtInt(c.establishments)} fiches)
                                </option>
                              ))}
                            </select>
                          </label>
                          <label className="field">
                            <span>Reprise par</span>
                            <select name="to" className="input" defaultValue="">
                              <option value="">Aucun territoire (détacher)</option>
                              {allTerritories
                                .filter((x) => x.id !== panel.territory.id)
                                .map((x) => (
                                  <option key={x.id} value={x.id}>
                                    {x.name}
                                  </option>
                                ))}
                            </select>
                          </label>
                        </div>
                        <span style={{ fontSize: 12, color: 'var(--muted)' }}>
                          Une commune qui compte des fiches doit être reprise par un territoire. Le rattachement actuel est clos et conservé dans l’historique.
                        </span>
                        <SubmitButton className="btn btn-outline btn-sm" pendingLabel="…" style={{ alignSelf: 'flex-start' }}>
                          Détacher ou transférer
                        </SubmitButton>
                      </ActionForm>
                    </details>
                  ) : null}
                  {membership.past.length ? (
                    <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                      <b style={{ color: 'var(--text)' }}>Historique</b>
                      {membership.past.map((m, i) => (
                        <div key={i}>
                          {m.name} : {m.valid_from.split('-').reverse().join('/')} → {m.valid_to.split('-').reverse().join('/')}
                          {m.now_in ? ` · aujourd’hui ${m.now_in}` : ' · non rattachée'}
                        </div>
                      ))}
                    </div>
                  ) : null}
                </section>
              ) : null}
            </>
          ) : selectedDeal && deal ? (
            <>
              <div style={{ background: 'var(--ink)', color: 'var(--cream)', borderRadius: 20, padding: 20, display: 'flex', flexDirection: 'column', gap: 6 }}>
                <div style={{ fontSize: 12, color: '#8FA197' }}>{selectedDeal.typeLabel} · Négociation</div>
                <div className="display" style={{ fontSize: 26, letterSpacing: '-0.02em' }}>
                  {selectedDeal.name}
                </div>
                <div style={{ fontSize: 13, color: '#AEBDB5' }}>
                  Licence envisagée {fmtEuros(deal.licenceCents)} / an · probabilité {deal.probability} %
                </div>
                <div style={{ display: 'flex', gap: 8, marginTop: 8, flexWrap: 'wrap' }}>
                  <Link href={`/console?crm=${groupOf(deal.stage)}&deal=${deal.id}`} className="btn btn-amber btn-sm" style={{ borderRadius: 9, fontSize: 12 }}>
                    Ouvrir le suivi commercial
                  </Link>
                  {canAdmin ? (
                    <Link
                      href={`/console/territoires/nouveau?deal=${deal.id}`}
                      className="btn btn-sm console-btn-ghost-dark"
                      style={{ borderRadius: 9, fontSize: 12 }}
                    >
                      Créer le territoire
                    </Link>
                  ) : null}
                </div>
              </div>
              <section className="console-card" style={{ borderRadius: 20, padding: 18, fontSize: 14, color: 'var(--muted)', lineHeight: 1.5 }}>
                Les modules et les quotas se configurent à la création du territoire, après la signature. La création rattache les communes (API Géo), ouvre les
                contrats et invite l’administrateur territorial.
              </section>
            </>
          ) : (
            <section className="console-card" style={{ borderRadius: 20, padding: 18 }}>
              Aucun client pour l’instant.
            </section>
          )}
        </div>
      </div>
    </div>
  );
}
