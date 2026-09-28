import { asc } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { processPrivacyRequestAction, registerPrivacyRequestAction, verifyAuditChainAction } from './actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { AUDIT_CATEGORIES, RETENTION, type AuditCategory } from '@/lib/constants';
import { fmtDecimal, fmtInt, fmtShortDate, parisParts } from '@/lib/format';
import { requirePlatformStaff } from '@/server/authz';
import { db } from '@/server/db';
import { territories } from '@/server/db/schema';
import { auditEntries, openPrivacyRequests, rgpdStats, securityStats } from '@/server/services/console-audit';
import { infraStatus, STATE_BG } from '@/server/services/infra';

export const metadata: Metadata = { title: 'Audit & sécurité' };

type Props = { searchParams: Promise<{ categorie?: string; territoire?: string; q?: string; du?: string; au?: string; page?: string }> };

function stamp(d: Date): string {
  const p = parisParts(d);
  const two = (n: number) => String(n).padStart(2, '0');
  return `${two(p.day)}/${two(p.month)} ${two(p.hour)}:${two(p.minute)}`;
}

const KIND_LABELS = { EXPORT: 'Accès / portabilité', DELETE: 'Effacement', RECTIFY: 'Rectification' } as const;

export default async function ConsoleAudit({ searchParams }: Props) {
  const sp = await searchParams;
  const actor = await requirePlatformStaff();
  const category = sp.categorie && sp.categorie in AUDIT_CATEGORIES ? (sp.categorie as AuditCategory) : undefined;
  const territoryId = sp.territoire && /^[0-9a-f-]{36}$/.test(sp.territoire) ? sp.territoire : undefined;
  const q = sp.q?.trim().slice(0, 100) || undefined;
  const page = Math.max(1, Number(sp.page) || 1);
  const filters = { category, territoryId, q, from: sp.du, to: sp.au };
  const [log, sec, rgpd, requests, infra, terrs] = await Promise.all([
    auditEntries({ ...filters, page }),
    securityStats(),
    rgpdStats(),
    openPrivacyRequests(),
    infraStatus(),
    db.select({ id: territories.id, name: territories.name }).from(territories).orderBy(asc(territories.name)),
  ]);
  const canRgpd = actor.isPlatformAdmin || actor.roles.some((r) => r.role === 'PLATFORM_SUPPORT');
  const qs = (extra: Record<string, string | undefined>) => {
    const p = new URLSearchParams();
    for (const [k, v] of Object.entries({ categorie: category, territoire: territoryId, q, du: sp.du, au: sp.au, ...extra })) if (v) p.set(k, v);
    const s = p.toString();
    return s ? `?${s}` : '';
  };
  const secRows = [
    { l: 'Comptes admin avec MFA', v: `${fmtDecimal(sec.mfaPct * 100, 0)} %`, c: sec.mfaPct >= 0.9 ? 'var(--green)' : 'var(--brick)' },
    { l: 'Sessions actives', v: fmtInt(sec.sessions), c: 'var(--text)' },
    { l: 'Tentatives bloquées (24 h)', v: fmtInt(sec.blocked), c: sec.blocked ? 'var(--brick)' : 'var(--green)' },
    { l: 'Médias analysés (antivirus)', v: sec.antivirus ? '100 %' : 'réencodage', c: sec.antivirus ? 'var(--green)' : 'var(--brick)' },
    { l: 'Secrets en coffre-fort', v: sec.secrets ? '✓' : 'valeurs d’exemple', c: sec.secrets ? 'var(--green)' : 'var(--danger)' },
  ];
  const rgpdRows = [
    { l: 'Contacts avec consentement', v: fmtInt(rgpd.consents) },
    { l: 'Demandes d’export traitées', v: fmtInt(rgpd.exports) },
    { l: 'Suppressions traitées', v: fmtInt(rgpd.deletions) },
    { l: 'Durée de conservation', v: `${rgpd.retentionMonths} mois` },
  ];

  return (
    <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.6fr) minmax(300px,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
      <section className="console-card" style={{ borderRadius: 20, overflow: 'hidden' }}>
        <div style={{ display: 'flex', gap: 8, padding: '14px 18px', alignItems: 'center', flexWrap: 'wrap' }}>
          <b>Journal d’audit</b>
          <span style={{ marginLeft: 'auto', fontSize: 12, color: 'var(--muted)' }}>conservé {RETENTION.auditMonths} mois · inaltérable</span>
        </div>
        <form method="get" className="audit-filters" aria-label="Filtrer le journal">
          <select name="categorie" className="input" defaultValue={category ?? ''} aria-label="Catégorie">
            <option value="">Toutes catégories</option>
            {(Object.keys(AUDIT_CATEGORIES) as AuditCategory[]).map((c) => (
              <option key={c} value={c}>
                {AUDIT_CATEGORIES[c].label}
              </option>
            ))}
          </select>
          <select name="territoire" className="input" defaultValue={territoryId ?? ''} aria-label="Territoire">
            <option value="">Tous territoires</option>
            {terrs.map((t) => (
              <option key={t.id} value={t.id}>
                {t.name}
              </option>
            ))}
          </select>
          <input name="q" className="input" defaultValue={q ?? ''} placeholder="Rechercher (auteur, action…)" aria-label="Recherche" />
          <input name="du" type="date" className="input" defaultValue={sp.du ?? ''} aria-label="Du" />
          <input name="au" type="date" className="input" defaultValue={sp.au ?? ''} aria-label="Au" />
          <button type="submit" className="btn btn-dark btn-sm">
            Filtrer
          </button>
        </form>
        <div style={{ display: 'flex', gap: 10, padding: '0 18px 12px', alignItems: 'center', flexWrap: 'wrap', fontSize: 13 }}>
          <span style={{ color: 'var(--muted)' }}>{fmtInt(log.total)} entrées</span>
          <a href={`/api/console/audit.csv${qs({})}`} style={{ fontWeight: 700 }}>
            Exporter en CSV
          </a>
          <ActionForm action={verifyAuditChainAction} resetOnSuccess={false} style={{ marginLeft: 'auto' }}>
            <SubmitButton className="btn btn-outline btn-xs" pendingLabel="Vérification…">
              Vérifier l’intégrité de la chaîne
            </SubmitButton>
          </ActionForm>
        </div>
        <div role="table" aria-label="Journal d’audit">
          {log.rows.map((a) => {
            const cat = AUDIT_CATEGORIES[a.category as AuditCategory];
            return (
              <div key={a.id} role="row" className="audit-row">
                <span role="cell" style={{ fontFamily: 'ui-monospace,monospace', fontSize: 12, color: 'var(--muted)' }} title={a.occurredAt.toISOString()}>
                  {stamp(a.occurredAt)}
                </span>
                <b role="cell" style={{ overflow: 'hidden', textOverflow: 'ellipsis' }}>
                  {a.actorLabel}
                </b>
                <span role="cell" style={{ minWidth: 0 }}>
                  {a.summary}
                  {a.territoryName && !territoryId ? <span style={{ color: 'var(--muted)', fontSize: 12 }}> · {a.territoryName}</span> : null}
                </span>
                <span
                  role="cell"
                  style={{ justifySelf: 'start', fontSize: 11, fontWeight: 800, padding: '3px 8px', borderRadius: 999, background: cat?.bg ?? '#E4E7E1' }}
                >
                  {cat?.label ?? a.category}
                </span>
              </div>
            );
          })}
          {log.rows.length === 0 ? <p style={{ padding: '10px 18px', color: 'var(--muted)', margin: 0 }}>Aucune entrée pour ces critères.</p> : null}
        </div>
        {log.pages > 1 ? (
          <nav
            style={{
              display: 'flex',
              gap: 10,
              justifyContent: 'center',
              padding: 14,
              fontSize: 13,
              fontWeight: 700,
              borderTop: '1px solid var(--console-line-2)',
            }}
            aria-label="Pagination"
          >
            {log.page > 1 ? <Link href={`/console/audit${qs({ page: String(log.page - 1) })}`}>← Plus récentes</Link> : null}
            <span style={{ color: 'var(--muted)', fontWeight: 600 }}>
              Page {log.page} / {log.pages}
            </span>
            {log.page < log.pages ? <Link href={`/console/audit${qs({ page: String(log.page + 1) })}`}>Plus anciennes →</Link> : null}
          </nav>
        ) : null}
      </section>

      <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
        <section className="console-card" style={{ borderRadius: 20, padding: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
          <b>Sécurité</b>
          {secRows.map((s) => (
            <div
              key={s.l}
              style={{ display: 'flex', justifyContent: 'space-between', fontSize: 14, padding: '6px 0', borderTop: '1px solid var(--console-line-2)', gap: 8 }}
            >
              <span>{s.l}</span>
              <b style={{ color: s.c }}>{s.v}</b>
            </div>
          ))}
          {sec.lockedNow ? (
            <div style={{ fontSize: 12, color: 'var(--muted)' }}>{sec.lockedNow} compte(s) verrouillé(s) en ce moment après des échecs de connexion.</div>
          ) : null}
        </section>
        <section className="console-card" style={{ borderRadius: 20, padding: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
          <b>RGPD</b>
          {rgpdRows.map((s) => (
            <div
              key={s.l}
              style={{ display: 'flex', justifyContent: 'space-between', fontSize: 14, padding: '6px 0', borderTop: '1px solid var(--console-line-2)' }}
            >
              <span>{s.l}</span>
              <b>{s.v}</b>
            </div>
          ))}
        </section>
        {canRgpd ? (
          <section className="console-card" style={{ borderRadius: 20, padding: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', gap: 8 }}>
              <b>Demandes d’exercice des droits</b>
              <span style={{ fontSize: 12, color: rgpd.open ? 'var(--brick)' : 'var(--muted)', fontWeight: 700 }}>
                {rgpd.open ? `${rgpd.open} en cours` : 'aucune en attente'}
              </span>
            </div>
            {requests.map((r) => (
              <div
                key={r.id}
                style={{ borderTop: '1px solid var(--console-line-2)', paddingTop: 10, display: 'flex', flexDirection: 'column', gap: 6, fontSize: 13 }}
              >
                <div>
                  <b>n°{r.number}</b> · {KIND_LABELS[r.kind]} · {r.email}
                  <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                    Reçue le {fmtShortDate(r.createdAt)}
                    {r.territoryName ? ` · ${r.territoryName}` : ''} · réponse due avant le {fmtShortDate(new Date(r.createdAt.getTime() + 30 * 86_400_000))}
                  </div>
                </div>
                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', alignItems: 'center' }}>
                  <a href={`/api/console/rgpd/${r.id}`} className="btn btn-outline btn-xs">
                    Exporter les données
                  </a>
                  {r.kind === 'DELETE' ? (
                    <ActionForm action={processPrivacyRequestAction}>
                      <input type="hidden" name="id" value={r.id} />
                      <input type="hidden" name="decision" value="ERASE" />
                      <SubmitButton className="btn btn-danger btn-xs" pendingLabel="Effacement…">
                        Effacer et clore
                      </SubmitButton>
                    </ActionForm>
                  ) : (
                    <ActionForm action={processPrivacyRequestAction}>
                      <input type="hidden" name="id" value={r.id} />
                      <input type="hidden" name="decision" value="DONE" />
                      <SubmitButton className="btn btn-brand btn-xs">Marquer traitée</SubmitButton>
                    </ActionForm>
                  )}
                  <ActionForm action={processPrivacyRequestAction}>
                    <input type="hidden" name="id" value={r.id} />
                    <input type="hidden" name="decision" value="REJECTED" />
                    <input type="hidden" name="note" value="Identité non vérifiée ou demande infondée" />
                    <SubmitButton className="btn btn-ghost btn-xs">Refuser</SubmitButton>
                  </ActionForm>
                </div>
              </div>
            ))}
            <details className="console-details">
              <summary style={{ fontSize: 13, fontWeight: 700, color: 'var(--green)' }}>+ Enregistrer une demande reçue</summary>
              <ActionForm action={registerPrivacyRequestAction} style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8, marginTop: 8 }}>
                <input
                  name="email"
                  type="email"
                  className="input"
                  placeholder="Email du demandeur"
                  required
                  aria-label="Email"
                  style={{ gridColumn: '1 / -1' }}
                />
                <select name="kind" className="input" defaultValue="EXPORT" aria-label="Type de demande">
                  <option value="EXPORT">Accès / portabilité</option>
                  <option value="DELETE">Effacement</option>
                  <option value="RECTIFY">Rectification</option>
                </select>
                <select name="territoryId" className="input" defaultValue="" aria-label="Territoire">
                  <option value="">Territoire…</option>
                  {terrs.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.name}
                    </option>
                  ))}
                </select>
                <input name="note" className="input" placeholder="Canal, précisions" aria-label="Note" style={{ gridColumn: '1 / -1' }} />
                <SubmitButton className="btn btn-dark btn-sm" style={{ gridColumn: '1 / -1', justifySelf: 'start' }}>
                  Enregistrer
                </SubmitButton>
              </ActionForm>
            </details>
          </section>
        ) : null}
        <section className="console-card" style={{ borderRadius: 20, padding: 18, display: 'flex', flexDirection: 'column', gap: 8 }}>
          <b>Infrastructure</b>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 6 }}>
            {infra.components.map((p) => (
              <div
                key={p.label}
                title={p.detail}
                style={{ background: STATE_BG[p.state], borderRadius: 8, padding: 8, fontSize: 11, fontWeight: 700, textAlign: 'center' }}
              >
                {p.label}
              </div>
            ))}
          </div>
          <div style={{ fontSize: 12, color: 'var(--muted)' }}>{infra.summary}</div>
          <details className="console-details">
            <summary style={{ fontSize: 12, fontWeight: 700, color: 'var(--green)' }}>Détail des sondes</summary>
            <ul style={{ margin: '6px 0 0', padding: 0, listStyle: 'none', fontSize: 12, display: 'flex', flexDirection: 'column', gap: 3 }}>
              {infra.components.map((p) => (
                <li key={p.label}>
                  <b>{p.label}</b> — {p.detail ?? (p.state === 'ok' ? 'opérationnel' : 'à vérifier')}
                </li>
              ))}
            </ul>
          </details>
        </section>
      </div>
    </div>
  );
}
