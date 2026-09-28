import type { Metadata } from 'next';
import Link from 'next/link';
import type { ReactNode } from 'react';
import { acceptSireneAction, archiveSireneAction, rejectSireneAction, saveSireneSettingsAction, syncNowAction } from './actions';
import { ESTABLISHMENT_STATUS, type EstablishmentStatus } from '@/lib/constants';
import { fmtInt, fmtShortDate, fmtStamp } from '@/lib/format';
import { DEFAULT_EXCLUDED_GROUPS, EXCLUSION_GROUPS } from '@/lib/sirene';
import { inseeConfigured } from '@/server/integrations/insee-sirene';
import { loadBoContext } from '@/server/services/backoffice';
import { nextSireneSync, pendingSireneChanges, sireneRuns } from '@/server/services/sirene-sync';

export const metadata: Metadata = { title: 'Mises à jour SIRENE' };

type Props = { searchParams: Promise<Record<string, string | undefined>> };
type Change = Awaited<ReturnType<typeof pendingSireneChanges>>[number];

const NOTICE: Record<string, (sp: Record<string, string | undefined>) => { tone: 'ok' | 'warn'; text: string }> = {
  creees: (sp) => {
    const n = (sp.fiches ?? '').split(',').filter(Boolean).length;
    return {
      tone: 'ok',
      text: `${n} fiche${n > 1 ? 's' : ''} précréée${n > 1 ? 's' : ''}. SIRENE ne donne pas d’email : invitez ces entreprises par courrier.`,
    };
  },
  archivees: (sp) => ({ tone: 'ok', text: `${sp.n ?? 0} fiche(s) archivée(s) : elles n’apparaissent plus sur le portail.` }),
  ecartees: (sp) => ({ tone: 'ok', text: `${sp.n ?? 0} proposition(s) écartée(s) : elles ne seront plus proposées.` }),
  lancee: () => ({ tone: 'ok', text: 'Synchronisation lancée : les nouveautés apparaîtront ici d’ici quelques minutes.' }),
  reglages: () => ({ tone: 'ok', text: 'Réglages SIRENE enregistrés.' }),
  limite: () => ({ tone: 'warn', text: 'Une synchronisation vient déjà d’être lancée : réessayez dans une heure.' }),
  perimetre: () => ({ tone: 'warn', text: 'Action réservée au périmètre du territoire.' }),
  vide: () => ({ tone: 'warn', text: 'Cochez au moins une ligne.' }),
  rien: () => ({ tone: 'warn', text: 'Aucune fiche créée : ces établissements sont déjà présents ou ne sont plus valides.' }),
};

const RUN_STATUS: Record<string, string> = { RUNNING: 'en cours', DONE: 'terminée', FAILED: 'échec' };

function address(c: Change) {
  const r = c.record;
  return [r.street, [r.postalCode, r.city].filter(Boolean).join(' ')].filter(Boolean).join(', ');
}

function byCommune(list: Change[]): [string, Change[]][] {
  const m = new Map<string, Change[]>();
  for (const c of list) m.set(c.communeName, [...(m.get(c.communeName) ?? []), c]);
  return [...m.entries()];
}

function Section({ title, count, hint, children }: { title: string; count: number; hint: string; children: ReactNode }) {
  return (
    <section className="bo-card" aria-label={title} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      <div>
        <h2 style={{ margin: 0, fontSize: 18, display: 'flex', gap: 8, alignItems: 'center' }}>
          {title}
          <span className="bo-chip" style={{ padding: '2px 9px', fontSize: 12 }}>
            {fmtInt(count)}
          </span>
        </h2>
        <p style={{ margin: '4px 0 0', color: 'var(--muted)', fontSize: 13 }}>{hint}</p>
      </div>
      {children}
    </section>
  );
}

function Row({ c, admin, children }: { c: Change; admin: boolean; children: ReactNode }) {
  return (
    <label
      style={{
        display: 'grid',
        gridTemplateColumns: admin ? '20px minmax(0,1fr) auto' : 'minmax(0,1fr) auto',
        gap: 12,
        alignItems: 'center',
        padding: '10px 0',
        borderTop: '1px solid var(--line)',
        cursor: admin ? 'pointer' : undefined,
      }}
    >
      {admin ? <input type="checkbox" name="id" value={c.id} defaultChecked={!c.record.reviewReason} aria-label={c.record.name} /> : null}
      {children}
    </label>
  );
}

export default async function SireneUpdatesPage({ searchParams }: Props) {
  const ctx = await loadBoContext();
  const sp = await searchParams;
  const admin = ctx.access === 'ADMIN';
  const territoryAdmin = admin && ctx.level === 'TERRITORY';
  const sirene = ctx.settings.sirene ?? {};
  const autoSync = sirene.autoSync !== false;
  const [changes, runs, next] = await Promise.all([pendingSireneChanges(ctx), sireneRuns(ctx.territory.id), nextSireneSync(ctx.territory.id, autoSync)]);
  const creations = changes.filter((c) => c.kind === 'CREATION');
  const closures = changes.filter((c) => c.kind === 'CLOSURE');
  const notice = sp.ok && NOTICE[sp.ok] ? NOTICE[sp.ok](sp) : null;
  const letters = sp.ok === 'creees' ? (sp.fiches ?? '').split(',').filter((x) => /^[0-9a-f-]{36}$/.test(x)) : [];
  const last = runs.find((r) => r.status === 'DONE');
  const running = runs[0]?.status === 'RUNNING';
  const groups = sirene.excludedGroups ?? DEFAULT_EXCLUDED_GROUPS;

  return (
    <div className="app-content" style={{ gap: 16 }}>
      <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap', fontSize: 13 }}>
        <Link href="/collectivite/entreprises">← Entreprises</Link>
      </div>
      {notice ? (
        <div className={`alert alert-${notice.tone}`} role="status">
          {notice.text}
          {letters.length ? (
            <>
              {' '}
              <a href={`/api/collectivite/courriers.pdf?ids=${letters.join(',')}`} style={{ color: 'inherit', textDecoration: 'underline' }}>
                Imprimer les {fmtInt(letters.length)} courriers d’invitation (PDF)
              </a>
            </>
          ) : null}
        </div>
      ) : null}

      <section
        aria-label="Synchronisation"
        style={{
          background: 'var(--ink)',
          color: 'var(--cream)',
          borderRadius: 20,
          padding: 22,
          display: 'flex',
          gap: 18,
          flexWrap: 'wrap',
          alignItems: 'center',
        }}
      >
        <div style={{ flex: '1 1 320px', display: 'flex', flexDirection: 'column', gap: 6 }}>
          <span style={{ fontSize: 11, fontWeight: 800, letterSpacing: '0.08em', color: 'var(--amber)' }}>
            SYNCHRONISATION {autoSync ? 'MENSUELLE' : 'MANUELLE'}
          </span>
          <b style={{ fontSize: 17 }}>
            {running
              ? 'Synchronisation en cours…'
              : last
                ? `Dernier passage le ${fmtShortDate(last.startedAt)} : ${last.creations} nouveauté${last.creations > 1 ? 's' : ''}, ${last.closures} fermeture${last.closures > 1 ? 's' : ''}`
                : 'Aucune synchronisation pour l’instant'}
          </b>
          <span style={{ color: 'var(--sage)', fontSize: 13 }}>
            Source :{' '}
            {inseeConfigured()
              ? 'API Sirene de l’INSEE (changements depuis le dernier passage)'
              : 'API Recherche d’entreprises (relecture complète des communes)'}
            {next ? ` · prochain passage vers le ${fmtShortDate(next)}` : ' · synchronisation automatique désactivée'}. Rien n’est publié ni archivé sans votre
            validation.
          </span>
        </div>
        {territoryAdmin ? (
          <form action={syncNowAction}>
            <button
              type="submit"
              disabled={running}
              style={{
                border: '1.5px solid var(--dark-4)',
                background: 'transparent',
                color: 'var(--cream)',
                padding: '10px 14px',
                borderRadius: 10,
                fontWeight: 700,
                cursor: 'pointer',
              }}
            >
              Synchroniser maintenant
            </button>
          </form>
        ) : null}
      </section>

      {!admin && changes.length ? (
        <div className="alert alert-info">Les décisions sont prises par un administrateur du territoire ou de la commune.</div>
      ) : null}

      <Section
        title="Nouvelles entreprises"
        count={creations.length}
        hint="Établissements créés depuis le dernier passage, hors activités exclues, et cas « à vérifier » (société déclarée en holding ou en immobilier, artisan à enseigne…), non cochés par défaut. Une fois acceptés, ils deviennent des fiches précréées à inviter par courrier."
      >
        {creations.length ? (
          <form style={{ display: 'flex', flexDirection: 'column' }}>
            {byCommune(creations).map(([commune, list]) => (
              <div key={commune} style={{ marginBottom: 8 }}>
                <div className="bo-table-head" style={{ padding: '6px 0' }}>
                  {commune} · {list.length}
                </div>
                {list.map((c) => (
                  <Row key={c.id} c={c} admin={admin}>
                    <span style={{ minWidth: 0 }}>
                      <b style={{ display: 'block' }}>{c.record.name}</b>
                      {c.record.reviewReason ? (
                        <span
                          className="bo-chip"
                          style={{
                            padding: '2px 8px',
                            fontSize: 11,
                            background: 'var(--warn-bg)',
                            color: 'var(--warn-fg)',
                            borderColor: 'transparent',
                            margin: '3px 0',
                            display: 'flex',
                            width: 'fit-content',
                            whiteSpace: 'normal',
                          }}
                        >
                          À vérifier : {c.record.reviewReason}
                        </span>
                      ) : null}
                      <span style={{ fontSize: 12, color: 'var(--muted)' }}>
                        {address(c)} · SIRET {c.siret}
                      </span>
                    </span>
                    <span style={{ textAlign: 'right', fontSize: 12, color: 'var(--muted)' }}>
                      <span style={{ display: 'block', color: 'var(--text)', fontWeight: 700 }}>{c.categoryName ?? 'Catégorie à préciser'}</span>
                      {c.record.createdOn ? `créé le ${fmtShortDate(c.record.createdOn)}` : null} {c.record.naf ? `· NAF ${c.record.naf}` : null}
                    </span>
                  </Row>
                ))}
              </div>
            ))}
            {admin ? (
              <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', paddingTop: 8 }}>
                <button type="submit" formAction={acceptSireneAction} className="btn btn-brand btn-sm">
                  Créer les fiches cochées
                </button>
                <button type="submit" formAction={rejectSireneAction} className="btn btn-outline btn-sm">
                  Écarter les lignes cochées
                </button>
              </div>
            ) : null}
          </form>
        ) : (
          <p style={{ margin: 0, color: 'var(--muted)', fontSize: 14 }}>Aucune nouveauté à valider.</p>
        )}
      </Section>

      <Section
        title="Fermetures signalées"
        count={closures.length}
        hint="Établissements déclarés fermés dans SIRENE. La fiche reste en ligne tant que vous ne l’archivez pas : vérifiez d’abord, en particulier pour les fiches tenues par l’entreprise."
      >
        {closures.length ? (
          <form style={{ display: 'flex', flexDirection: 'column' }}>
            {byCommune(closures).map(([commune, list]) => (
              <div key={commune} style={{ marginBottom: 8 }}>
                <div className="bo-table-head" style={{ padding: '6px 0' }}>
                  {commune} · {list.length}
                </div>
                {list.map((c) => {
                  const st = c.listingStatus ? ESTABLISHMENT_STATUS[c.listingStatus as EstablishmentStatus] : null;
                  const managed = c.listingStatus === 'CLAIMED' || c.listingStatus === 'VALIDATED';
                  return (
                    <Row key={c.id} c={c} admin={admin}>
                      <span style={{ minWidth: 0 }}>
                        <b style={{ display: 'block' }}>
                          {c.establishmentId ? (
                            <Link href={`/collectivite/entreprises/${c.establishmentId}`}>{c.listingName ?? c.record.name}</Link>
                          ) : (
                            c.record.name
                          )}
                        </b>
                        <span style={{ fontSize: 12, color: 'var(--muted)' }}>
                          Fermée selon SIRENE{c.record.changedOn ? ` le ${fmtShortDate(c.record.changedOn)}` : ''} · SIRET {c.siret}
                          {managed ? ' · fiche tenue par l’entreprise, à vérifier' : ''}
                        </span>
                      </span>
                      {st ? (
                        <span className="bo-chip" style={{ background: st.bg, color: st.fg, borderColor: st.bg }}>
                          {st.label}
                        </span>
                      ) : (
                        <span />
                      )}
                    </Row>
                  );
                })}
              </div>
            ))}
            {admin ? (
              <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', paddingTop: 8 }}>
                <button type="submit" formAction={archiveSireneAction} className="btn btn-brand btn-sm">
                  Archiver les fiches cochées
                </button>
                <button type="submit" formAction={rejectSireneAction} className="btn btn-outline btn-sm">
                  Garder en ligne
                </button>
              </div>
            ) : null}
          </form>
        ) : (
          <p style={{ margin: 0, color: 'var(--muted)', fontSize: 14 }}>Aucune fermeture signalée.</p>
        )}
      </Section>

      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1fr)', ['--gap' as string]: '16px', ['--align' as string]: 'start' }}>
        <section className="bo-card" aria-label="Historique" style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 13 }}>
          <h2 style={{ margin: 0, fontSize: 18 }}>Historique</h2>
          {runs.length ? (
            runs.map((r) => (
              <div key={r.id} style={{ display: 'flex', justifyContent: 'space-between', gap: 10, borderTop: '1px solid var(--line)', paddingTop: 8 }}>
                <span>
                  <b>{fmtStamp(r.startedAt)}</b>
                  <span style={{ color: 'var(--muted)' }}> · {r.trigger === 'MANUAL' ? 'manuelle' : 'mensuelle'}</span>
                </span>
                <span style={{ color: r.status === 'FAILED' ? 'var(--danger)' : 'var(--muted)', textAlign: 'right' }}>
                  {r.status === 'DONE'
                    ? `${r.creations} nouveauté${r.creations > 1 ? 's' : ''} · ${r.closures} fermeture${r.closures > 1 ? 's' : ''}${r.ignored ? ` · ${r.ignored} ignorée${r.ignored > 1 ? 's' : ''}` : ''}`
                    : RUN_STATUS[r.status]}
                </span>
              </div>
            ))
          ) : (
            <span style={{ color: 'var(--muted)' }}>Aucun passage pour l’instant.</span>
          )}
        </section>

        {territoryAdmin ? (
          <form
            id="reglages"
            action={saveSireneSettingsAction}
            className="bo-card"
            aria-label="Réglages SIRENE"
            style={{ display: 'flex', flexDirection: 'column', gap: 10, fontSize: 13 }}
          >
            <h2 style={{ margin: 0, fontSize: 18 }}>Réglages</h2>
            <label style={{ display: 'flex', gap: 8, alignItems: 'center', fontWeight: 700 }}>
              <input type="checkbox" name="autoSync" defaultChecked={autoSync} />
              Synchronisation automatique chaque mois
            </label>
            <div className="bo-table-head" style={{ marginTop: 4 }}>
              Activités à ne jamais proposer ni importer
            </div>
            {EXCLUSION_GROUPS.map((g) => (
              <label key={g.key} style={{ display: 'flex', gap: 8, alignItems: 'flex-start' }}>
                <input type="checkbox" name="group" value={g.key} defaultChecked={groups.includes(g.key)} style={{ marginTop: 3 }} />
                <span>
                  <b>{g.label}</b>
                  <span style={{ display: 'block', color: 'var(--muted)', fontSize: 12 }}>{g.hint}</span>
                </span>
              </label>
            ))}
            <label style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
              <b>Autres codes NAF exclus</b>
              <input
                name="naf"
                className="input"
                defaultValue={(sirene.excludedNaf ?? []).join(', ')}
                placeholder="ex. 0111Z, 4791"
                style={{ border: '1px solid var(--line)', borderRadius: 10, padding: '8px 10px' }}
              />
              <span style={{ color: 'var(--muted)', fontSize: 12 }}>Codes complets ou débuts de code, séparés par des virgules.</span>
            </label>
            <div>
              <button type="submit" className="btn btn-brand btn-sm">
                Enregistrer
              </button>
            </div>
          </form>
        ) : null}
      </div>
    </div>
  );
}
