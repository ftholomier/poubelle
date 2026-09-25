import Link from 'next/link';
import {
  addDealContactAction,
  addDealTaskAction,
  advanceDealAction,
  logDealActivityAction,
  markDealLostAction,
  planMeetingAction,
  reopenDealAction,
  saveDealNotesAction,
  toggleDealTaskAction,
  uploadDealDocumentAction,
} from '@/app/console/crm/actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { Photo } from '@/components/ui/Photo';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { DEAL_STAGE_LABELS, DEAL_STAGES, MONTHS_SHORT, PIPELINE_GROUPS, TERRITORY_KINDS, type DealStage, type TerritoryKind } from '@/lib/constants';
import { fmtEuros, fmtInt, fullName, initials, parisDate, parisParts, relativeTime, tomorrowIso } from '@/lib/format';
import { db } from '@/server/db';
import { deals } from '@/server/db/schema';
import { ACTIVITY_COLORS, dealDetail, dealsInGroup, groupCounts, nextStage, type DealGroupKey } from '@/server/services/crm';
import { desc, eq } from 'drizzle-orm';

export type CrmTab = DealGroupKey | 'lost';

const TAG_BG: Record<string, string> = { Décideur: '#F4B266', Influence: '#CDE3F2', Utilisateur: '#D6E8B4', Technique: '#DCD3F3' };
const AVATAR_BG = ['#1F6B52', '#3E6FB0', '#7A5BB5', '#C8892A'];

function kEuros(cents: number): string {
  const v = cents / 100_000;
  return `${v.toLocaleString('fr-FR', { maximumFractionDigits: 1 })} k€`;
}

function ddmm(d: Date): string {
  const p = parisParts(d);
  return `${String(p.day).padStart(2, '0')}/${String(p.month).padStart(2, '0')}`;
}

function monthOf(d: Date): string {
  return MONTHS_SHORT[parisParts(d).month - 1];
}

/**
 * Fiche de suivi commercial (S5) : liste des affaires de l'étape, détail, interlocuteurs,
 * historique, prochaines actions, notes et documents. Affichée en modale sur la vue
 * d'ensemble ou en pleine page (/console/crm).
 */
export async function CrmPanel({
  tab,
  dealId,
  base,
  closeHref,
  canEdit,
}: {
  tab: CrmTab;
  dealId?: string;
  base: string;
  closeHref?: string;
  canEdit: boolean;
}) {
  const [counts, list, lostCount] = await Promise.all([
    groupCounts(),
    tab === 'lost' ? db.select().from(deals).where(eq(deals.stage, 'LOST')).orderBy(desc(deals.updatedAt)) : dealsInGroup(tab),
    db.$count(deals, eq(deals.stage, 'LOST')),
  ]);
  const current = list.find((d) => d.id === dealId) ?? list[0];
  const detail = current ? await dealDetail(current.id) : null;
  const href = (t: CrmTab, id?: string) => `${base}?crm=${t}${id ? `&deal=${id}` : ''}`;

  return (
    <div className="crm-shell">
      <div className="crm-head">
        <div className="display" style={{ fontSize: 20 }}>
          Suivi commercial
        </div>
        <nav style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }} aria-label="Étapes du pipeline">
          {PIPELINE_GROUPS.map((g) => (
            <Link key={g.key} href={href(g.key)} scroll={false} className="crm-tab" aria-current={tab === g.key ? 'page' : undefined}>
              {g.label} · {counts[g.key]}
            </Link>
          ))}
          {lostCount ? (
            <Link href={href('lost')} scroll={false} className="crm-tab" aria-current={tab === 'lost' ? 'page' : undefined}>
              Perdues · {lostCount}
            </Link>
          ) : null}
        </nav>
        <div style={{ marginLeft: 'auto', display: 'flex', gap: 8, alignItems: 'center' }}>
          {canEdit ? (
            <Link href="/console/crm/nouvelle" className="crm-new">
              + Nouvelle affaire
            </Link>
          ) : null}
          {closeHref ? (
            <Link href={closeHref} scroll={false} className="crm-close" aria-label="Fermer le suivi commercial">
              ×
            </Link>
          ) : null}
        </div>
      </div>
      <div className="crm-body">
        <div className="crm-list">
          {list.length === 0 ? <p style={{ fontSize: 13, color: 'var(--muted)', margin: 4 }}>Aucune affaire à cette étape.</p> : null}
          {list.map((d) => {
            const on = d.id === current?.id;
            return (
              <Link
                key={d.id}
                href={href(tab, d.id)}
                scroll={false}
                aria-current={on ? 'true' : undefined}
                className="crm-card"
                style={{ background: on ? '#fff' : 'transparent', borderColor: on ? 'var(--ink)' : 'var(--console-line)' }}
              >
                <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                  <b style={{ fontSize: 14 }}>{d.name}</b>
                  <b style={{ fontSize: 13, whiteSpace: 'nowrap' }}>{kEuros(d.licenceCents)}</b>
                </div>
                <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                  {TERRITORY_KINDS[d.kind as TerritoryKind]?.label} · {fmtInt(d.communesCount)} {d.communesCount > 1 ? 'communes' : 'commune'}
                </div>
                <div style={{ height: 6, background: 'var(--console-track)', borderRadius: 3, overflow: 'hidden' }}>
                  <div
                    style={{
                      height: '100%',
                      width: `${d.probability}%`,
                      background: d.probability >= 70 ? 'var(--green)' : d.probability >= 40 ? 'var(--amber)' : '#9A9F95',
                    }}
                  />
                </div>
                {d.nextAction ? <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--green)' }}>→ {d.nextAction}</div> : null}
              </Link>
            );
          })}
        </div>
        <div className="crm-detail">
          {detail ? <DealDetail detail={detail} canEdit={canEdit} base={base} /> : <p style={{ color: 'var(--muted)' }}>Sélectionnez une affaire.</p>}
        </div>
      </div>
    </div>
  );
}

function DealDetail({ detail, canEdit, base }: { detail: NonNullable<Awaited<ReturnType<typeof dealDetail>>>; canEdit: boolean; base: string }) {
  const { deal, owner, contacts, activities, tasks, documents } = detail;
  const stage = deal.stage as DealStage;
  const lost = stage === 'LOST';
  const step = lost ? -1 : DEAL_STAGES.indexOf(stage);
  const next = lost ? null : nextStage(stage);
  const signed = step >= 5;
  const probability = signed ? 100 : deal.probability;
  const stBg = lost ? '#F5DCD8' : step >= 6 ? '#D6E8B4' : step >= 4 ? '#F4B266' : '#E4E7E1';
  const kpis = [
    { l: 'Licence annuelle', v: fmtEuros(deal.licenceCents), bg: 'var(--ink)', fg: 'var(--amber)' },
    { l: 'Probabilité', v: `${probability} %`, bg: '#fff', fg: 'var(--ink)' },
    { l: 'Valeur pondérée', v: fmtEuros(Math.round((deal.licenceCents * probability) / 100)), bg: '#fff', fg: 'var(--ink)' },
    { l: 'Mise en service', v: fmtEuros(deal.setupCents), bg: '#fff', fg: 'var(--ink)' },
  ];
  return (
    <>
      <div style={{ display: 'flex', gap: 16, alignItems: 'flex-start', flexWrap: 'wrap' }}>
        <div style={{ flex: 1, minWidth: 260 }}>
          <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 6, flexWrap: 'wrap' }}>
            <span style={{ fontSize: 12, fontWeight: 800, padding: '4px 10px', borderRadius: 999, background: stBg }}>{DEAL_STAGE_LABELS[stage]}</span>
            {deal.lastInteractionAt ? (
              <span style={{ fontSize: 12, color: 'var(--muted)' }}>Dernière interaction {relativeTime(deal.lastInteractionAt)}</span>
            ) : null}
          </div>
          <div className="display" style={{ fontSize: 34, letterSpacing: '-0.025em', lineHeight: 1 }}>
            {deal.name}
          </div>
          <div style={{ fontSize: 14, color: 'var(--muted)', marginTop: 4 }}>
            {TERRITORY_KINDS[deal.kind as TerritoryKind]?.label} · {fmtInt(deal.communesCount)} {deal.communesCount > 1 ? 'communes' : 'commune'}
            {deal.population ? ` · ${fmtInt(deal.population)} habitants` : ''}
          </div>
        </div>
        {owner ? (
          <div
            style={{
              display: 'flex',
              gap: 10,
              alignItems: 'center',
              background: '#fff',
              border: '1px solid var(--console-line)',
              borderRadius: 14,
              padding: '8px 12px',
            }}
          >
            <Photo
              src={owner.avatarUrl}
              label={initials(owner.firstName, owner.lastName)}
              color="#7A5BB5"
              style={{ width: 34, height: 34, borderRadius: '50%' }}
            />
            <div style={{ fontSize: 12 }}>
              <span style={{ color: 'var(--muted)' }}>Suivi par</span>
              <br />
              <b>{fullName(owner)}</b>
            </div>
          </div>
        ) : null}
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 10 }}>
        {kpis.map((k) => (
          <div
            key={k.l}
            style={{ background: k.bg, color: k.fg, borderRadius: 14, padding: 14, border: k.bg === '#fff' ? '1px solid var(--console-line)' : 0 }}
          >
            <div style={{ fontSize: 12, fontWeight: 700, opacity: 0.75 }}>{k.l}</div>
            <div className="display" style={{ fontSize: 26, letterSpacing: '-0.02em' }}>
              {k.v}
            </div>
          </div>
        ))}
      </div>

      <div style={{ background: '#fff', border: '1px solid var(--console-line)', borderRadius: 16, padding: '14px 16px' }}>
        <ol
          style={{ display: 'grid', gridTemplateColumns: 'repeat(8,1fr)', gap: 4, listStyle: 'none', margin: 0, padding: 0 }}
          aria-label="Étapes de l’affaire"
        >
          {DEAL_STAGES.map((s, i) => (
            <li key={s} style={{ display: 'flex', flexDirection: 'column', gap: 6 }} aria-current={i === step ? 'step' : undefined}>
              <div style={{ height: 8, borderRadius: 4, background: i < step ? 'var(--green)' : i === step ? 'var(--amber)' : 'var(--console-track)' }} />
              <span style={{ fontSize: 11, fontWeight: i === step ? 800 : 500, color: i <= step ? 'var(--ink)' : '#9A9F95', lineHeight: 1.2 }}>
                {DEAL_STAGE_LABELS[s]}
              </span>
            </li>
          ))}
        </ol>
      </div>

      <div className="crm-cols">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16, minWidth: 0 }}>
          <div className="crm-box">
            <b>Interlocuteurs</b>
            {contacts.map((p, i) => (
              <div
                key={p.id}
                style={{
                  display: 'grid',
                  gridTemplateColumns: '40px 1fr auto',
                  gap: 12,
                  alignItems: 'center',
                  padding: '10px 0',
                  borderTop: '1px solid var(--console-line-2)',
                  marginTop: 6,
                }}
              >
                <span
                  aria-hidden="true"
                  style={{
                    width: 40,
                    height: 40,
                    borderRadius: '50%',
                    background: AVATAR_BG[i % AVATAR_BG.length],
                    color: '#fff',
                    display: 'grid',
                    placeItems: 'center',
                    fontWeight: 800,
                    fontSize: 14,
                  }}
                >
                  {initials(p.name.split(' ')[0], p.name.split(' ').slice(1).join(' '))}
                </span>
                <div style={{ minWidth: 0 }}>
                  <b style={{ fontSize: 14 }}>{p.name}</b>
                  <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                    {p.role}
                    {p.email ? (
                      <>
                        {' · '}
                        <a href={`mailto:${p.email}`}>{p.email}</a>
                      </>
                    ) : null}
                    {p.phone ? ` · ${p.phone}` : ''}
                  </div>
                </div>
                {p.tag ? (
                  <span style={{ fontSize: 11, fontWeight: 800, padding: '3px 8px', borderRadius: 999, background: TAG_BG[p.tag] ?? '#E4E7E1' }}>{p.tag}</span>
                ) : (
                  <span />
                )}
              </div>
            ))}
            {canEdit ? (
              <details className="console-details" style={{ marginTop: 8 }}>
                <summary style={{ fontSize: 13, fontWeight: 700, color: 'var(--green)' }}>+ Ajouter un interlocuteur</summary>
                <ActionForm action={addDealContactAction} style={{ display: 'grid', gridTemplateColumns: 'repeat(2,minmax(0,1fr))', gap: 8, marginTop: 8 }}>
                  <input type="hidden" name="dealId" value={deal.id} />
                  <input name="name" className="input" placeholder="Prénom Nom" required aria-label="Nom" />
                  <input name="role" className="input" placeholder="Fonction" aria-label="Fonction" />
                  <input name="email" type="email" className="input" placeholder="Email" aria-label="Email" />
                  <input name="phone" className="input" placeholder="Téléphone" aria-label="Téléphone" />
                  <select name="tag" className="input" defaultValue="Influence" aria-label="Rôle dans la décision">
                    <option>Décideur</option>
                    <option>Influence</option>
                    <option>Utilisateur</option>
                    <option>Technique</option>
                  </select>
                  <SubmitButton className="btn btn-dark btn-sm">Ajouter</SubmitButton>
                </ActionForm>
              </details>
            ) : null}
          </div>
          <div className="crm-box">
            <b>Historique</b>
            {canEdit && !lost ? (
              <ActionForm action={logDealActivityAction} style={{ display: 'grid', gridTemplateColumns: '110px minmax(0,1fr) auto', gap: 6, marginTop: 10 }}>
                <input type="hidden" name="dealId" value={deal.id} />
                <select name="kind" className="input" defaultValue="Appel" aria-label="Type d’échange" style={{ padding: '8px 10px', fontSize: 13 }}>
                  <option>Appel</option>
                  <option>Email</option>
                  <option>RDV</option>
                  <option>Démo</option>
                  <option>Note</option>
                </select>
                <input
                  name="text"
                  className="input"
                  placeholder="Consigner un échange…"
                  aria-label="Résumé de l’échange"
                  style={{ padding: '8px 10px', fontSize: 13 }}
                  required
                />
                <SubmitButton className="btn btn-outline btn-sm">Ajouter</SubmitButton>
              </ActionForm>
            ) : null}
            <div style={{ display: 'flex', flexDirection: 'column', marginTop: 8 }}>
              {activities.map((l) => (
                <div key={l.id} style={{ display: 'grid', gridTemplateColumns: '70px 16px 1fr', gap: 10, padding: '8px 0', fontSize: 13, alignItems: 'start' }}>
                  <span style={{ color: 'var(--muted)', fontFamily: 'ui-monospace,monospace', fontSize: 12 }}>{ddmm(l.occurredAt)}</span>
                  <span style={{ width: 10, height: 10, borderRadius: '50%', background: ACTIVITY_COLORS[l.kind] ?? '#9A9F95', marginTop: 4 }} />
                  <div>
                    <b>{l.kind}</b> · {l.text}
                  </div>
                </div>
              ))}
            </div>
          </div>
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 16, minWidth: 0 }}>
          <div style={{ background: 'var(--ink)', color: 'var(--cream)', borderRadius: 16, padding: 16, display: 'flex', flexDirection: 'column', gap: 6 }}>
            <b style={{ color: 'var(--amber)' }}>Prochaines actions</b>
            {tasks.length === 0 ? <span style={{ fontSize: 13, color: '#8FA197' }}>Aucune action planifiée.</span> : null}
            {tasks.map((a) => {
              const on = Boolean(a.doneAt);
              return (
                <form key={a.id} action={toggleDealTaskAction}>
                  <input type="hidden" name="taskId" value={a.id} />
                  <button
                    type="submit"
                    className="crm-task"
                    disabled={!canEdit}
                    aria-pressed={on}
                    aria-label={`${on ? 'Rouvrir' : 'Marquer comme faite'} : ${a.text}`}
                  >
                    <span className="crm-task-box" style={{ background: on ? 'var(--amber)' : 'transparent' }}>
                      {on ? '✓' : ''}
                    </span>
                    <span style={{ textDecoration: on ? 'line-through' : 'none', opacity: on ? 0.5 : 1, textAlign: 'left' }}>{a.text}</span>
                    <span style={{ fontSize: 11, color: '#8FA197', whiteSpace: 'nowrap' }}>{a.dueText}</span>
                  </button>
                </form>
              );
            })}
            {canEdit && !lost ? (
              <ActionForm action={addDealTaskAction} style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1fr) 90px auto', gap: 6, marginTop: 6 }}>
                <input type="hidden" name="dealId" value={deal.id} />
                <input name="text" className="input crm-input-dark" placeholder="Nouvelle action…" aria-label="Nouvelle action" required />
                <input name="dueText" className="input crm-input-dark" placeholder="Échéance" aria-label="Échéance" />
                <SubmitButton className="btn btn-amber btn-sm">+</SubmitButton>
              </ActionForm>
            ) : null}
          </div>
          <div className="crm-box" style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <b>Notes</b>
            {canEdit ? (
              <ActionForm action={saveDealNotesAction} resetOnSuccess={false} style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
                <input type="hidden" name="dealId" value={deal.id} />
                <textarea name="notes" className="textarea crm-notes" rows={4} defaultValue={deal.notes ?? ''} aria-label="Notes sur l’affaire" />
                <SubmitButton className="btn btn-ghost btn-xs" style={{ alignSelf: 'flex-end' }} pendingLabel="…">
                  Enregistrer
                </SubmitButton>
              </ActionForm>
            ) : (
              <div style={{ fontSize: 13, lineHeight: 1.55, color: 'var(--muted-3)', whiteSpace: 'pre-line' }}>{deal.notes}</div>
            )}
          </div>
          <div className="crm-box" style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            <b style={{ marginBottom: 4 }}>Documents</b>
            {documents.map((d) => (
              <div
                key={d.id}
                style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  padding: '7px 0',
                  borderTop: '1px solid var(--console-line-2)',
                  fontSize: 13,
                  gap: 8,
                }}
              >
                {d.url ? (
                  <a href={d.url} target="_blank" rel="noreferrer">
                    📄︎ {d.name}
                  </a>
                ) : (
                  <span>📄︎ {d.name}</span>
                )}
                <span style={{ color: 'var(--muted)' }}>{monthOf(d.createdAt)}</span>
              </div>
            ))}
            {canEdit ? (
              <ActionForm action={uploadDealDocumentAction} style={{ display: 'flex', gap: 6, alignItems: 'center', marginTop: 6, flexWrap: 'wrap' }}>
                <input type="hidden" name="dealId" value={deal.id} />
                <input type="file" name="file" accept="application/pdf" aria-label="Document PDF" style={{ fontSize: 12, flex: 1, minWidth: 0 }} required />
                <SubmitButton className="btn btn-outline btn-xs">Joindre</SubmitButton>
              </ActionForm>
            ) : null}
          </div>
          {canEdit ? (
            lost ? (
              <form action={reopenDealAction}>
                <input type="hidden" name="dealId" value={deal.id} />
                <input type="hidden" name="base" value={base} />
                <SubmitButton className="btn btn-brand btn-block">Rouvrir l’affaire</SubmitButton>
              </form>
            ) : (
              <>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                  <form action={advanceDealAction} style={{ flex: 1, display: 'flex' }}>
                    <input type="hidden" name="dealId" value={deal.id} />
                    <input type="hidden" name="base" value={base} />
                    <SubmitButton className="btn btn-brand crm-advance" pendingLabel="…">
                      {next ? `Passer à « ${DEAL_STAGE_LABELS[next]} »` : '✓ Client actif'}
                    </SubmitButton>
                  </form>
                  <details className="console-details crm-rdv">
                    <summary className="btn btn-outline">Planifier un RDV</summary>
                    <ActionForm action={planMeetingAction} style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 6, marginTop: 8 }}>
                      <input type="hidden" name="dealId" value={deal.id} />
                      <input name="date" type="date" className="input" defaultValue={tomorrowIso()} min={parisDate()} required aria-label="Date" />
                      <input name="time" type="time" className="input" defaultValue="10:00" required aria-label="Heure" />
                      <input
                        name="subject"
                        className="input"
                        placeholder="Objet (ex. démo aux élus)"
                        required
                        aria-label="Objet"
                        style={{ gridColumn: '1 / -1' }}
                      />
                      <input name="place" className="input" placeholder="Lieu ou visio" aria-label="Lieu" style={{ gridColumn: '1 / -1' }} />
                      <SubmitButton className="btn btn-dark btn-sm" style={{ gridColumn: '1 / -1' }}>
                        Planifier
                      </SubmitButton>
                    </ActionForm>
                  </details>
                </div>
                {next === 'SIGNED' || (signed && !deal.territoryId) ? (
                  <p style={{ margin: 0, fontSize: 12, color: 'var(--muted)' }}>
                    À la signature, <Link href={`/console/territoires/nouveau?deal=${deal.id}`}>créez le territoire</Link> : communes, contrats et accès
                    administrateur.
                  </p>
                ) : null}
                {!signed ? (
                  <details className="console-details">
                    <summary style={{ fontSize: 12, color: 'var(--muted)', fontWeight: 600 }}>Classer comme perdue…</summary>
                    <ActionForm action={markDealLostAction} style={{ display: 'flex', gap: 6, marginTop: 6 }}>
                      <input type="hidden" name="dealId" value={deal.id} />
                      <input type="hidden" name="base" value={base} />
                      <input name="reason" className="input" placeholder="Raison (budget, concurrent…)" required aria-label="Raison" />
                      <SubmitButton className="btn btn-danger btn-sm">Classer</SubmitButton>
                    </ActionForm>
                  </details>
                ) : null}
              </>
            )
          ) : null}
        </div>
      </div>
    </>
  );
}
