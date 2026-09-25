'use client';

import Link from 'next/link';
import { useActionState, useState } from 'react';
import { bulkCampaignAction, bulkInviteAction, remindHoursAction, type BoState } from '@/app/collectivite/entreprises/actions';
import { Photo } from '@/components/ui/Photo';

export type EstRow = {
  id: string;
  name: string;
  image: string | null;
  color: string;
  commune: string;
  category: string;
  status: { label: string; bg: string; fg: string };
  completeness: number;
  plan: string;
  planPaid: boolean;
  updated: string;
};

const idle: BoState = { status: 'idle' };
const COLS = '36px 2.2fr 1.2fr 1.1fr 1.1fr 1.3fr 0.9fr 0.9fr';

function Feedback({ state }: { state: BoState }) {
  if (state.status === 'idle') return null;
  return (
    <span
      role={state.status === 'error' ? 'alert' : 'status'}
      style={{ color: state.status === 'error' ? 'var(--danger-fg)' : 'var(--green)', fontWeight: 700 }}
    >
      {state.message}
    </span>
  );
}

/** Tableau des établissements (C2) avec sélection et actions groupées. */
export function EstablishmentTable({ rows, campaigns, exportHref }: { rows: EstRow[]; campaigns: { id: string; name: string }[]; exportHref: string }) {
  const [sel, setSel] = useState<string[]>([]);
  const [invite, inviteAction, invitePending] = useActionState(bulkInviteAction, idle);
  const [camp, campAction, campPending] = useActionState(bulkCampaignAction, idle);
  const [hours, hoursAction, hoursPending] = useActionState(remindHoursAction, idle);
  const ids = JSON.stringify(sel);
  const all = rows.length > 0 && sel.length === rows.length;
  const toggle = (id: string) => setSel((s) => (s.includes(id) ? s.filter((x) => x !== id) : [...s, id]));
  const box = (on: boolean) => ({
    width: 18,
    height: 18,
    borderRadius: 5,
    border: '1.5px solid var(--green)',
    background: on ? 'var(--green)' : 'transparent',
    color: '#fff',
    fontSize: 11,
    display: 'grid',
    placeItems: 'center',
    cursor: 'pointer',
    padding: 0,
  });

  return (
    <>
      {sel.length ? (
        <div
          style={{
            display: 'flex',
            gap: 14,
            alignItems: 'center',
            background: 'var(--mint)',
            borderRadius: 12,
            padding: '10px 14px',
            fontSize: 13,
            fontWeight: 600,
            flexWrap: 'wrap',
          }}
        >
          <b>
            {sel.length} sélectionnée{sel.length > 1 ? 's' : ''}
          </b>
          <form action={inviteAction}>
            <input type="hidden" name="ids" value={ids} />
            <button type="submit" className="btn-link" disabled={invitePending} style={{ fontWeight: 600 }}>
              Envoyer une invitation
            </button>
          </form>
          <form action={campAction} style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
            <input type="hidden" name="ids" value={ids} />
            <label htmlFor="bulk-camp" className="sr-only">
              Campagne
            </label>
            <select id="bulk-camp" name="campaignId" className="input" style={{ padding: '5px 8px', fontSize: 13, width: 'auto' }} defaultValue="">
              <option value="" disabled>
                Ajouter à une campagne…
              </option>
              {campaigns.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </select>
            <button type="submit" className="btn-link" disabled={campPending} style={{ fontWeight: 600 }}>
              Ajouter
            </button>
          </form>
          <form action={hoursAction}>
            <input type="hidden" name="ids" value={ids} />
            <button type="submit" className="btn-link" disabled={hoursPending} style={{ fontWeight: 600 }}>
              Relancer pour les horaires
            </button>
          </form>
          <a href={`${exportHref}${exportHref.includes('?') ? '&' : '?'}ids=${sel.join(',')}`} style={{ fontWeight: 600 }}>
            Exporter
          </a>
          <a href={`/api/collectivite/courriers.pdf?ids=${sel.join(',')}`} style={{ fontWeight: 600 }}>
            Courriers (PDF)
          </a>
          <button type="button" className="btn-link" onClick={() => setSel([])} style={{ marginLeft: 'auto', color: 'var(--muted)' }}>
            Désélectionner
          </button>
          <div style={{ width: '100%', display: 'flex', flexDirection: 'column', gap: 2 }}>
            <Feedback state={invite} />
            <Feedback state={camp} />
            <Feedback state={hours} />
          </div>
        </div>
      ) : null}
      <div style={{ background: 'var(--paper)', border: '1px solid var(--line)', borderRadius: 18, overflow: 'hidden' }}>
        <div style={{ overflowX: 'auto' }}>
          <div style={{ minWidth: 920 }} role="table" aria-label="Établissements">
            <div
              role="row"
              className="bo-table-head"
              style={{ display: 'grid', gridTemplateColumns: COLS, gap: 12, padding: '12px 16px', borderBottom: '1px solid var(--line)' }}
            >
              <span role="columnheader">
                <button
                  type="button"
                  aria-label={all ? 'Tout désélectionner' : 'Tout sélectionner'}
                  onClick={() => setSel(all ? [] : rows.map((r) => r.id))}
                  style={box(all)}
                >
                  {all ? '✓' : ''}
                </button>
              </span>
              <span role="columnheader">Établissement</span>
              <span role="columnheader">Commune</span>
              <span role="columnheader">Catégorie</span>
              <span role="columnheader">Statut</span>
              <span role="columnheader">Complétude</span>
              <span role="columnheader">Offre</span>
              <span role="columnheader">Mise à jour</span>
            </div>
            {rows.map((r) => {
              const on = sel.includes(r.id);
              return (
                <div
                  key={r.id}
                  role="row"
                  style={{
                    display: 'grid',
                    gridTemplateColumns: COLS,
                    gap: 12,
                    padding: '10px 16px',
                    fontSize: 14,
                    alignItems: 'center',
                    borderBottom: '1px solid var(--line-3)',
                    background: on ? 'var(--mint-2)' : 'transparent',
                  }}
                >
                  <span role="cell">
                    <button type="button" aria-pressed={on} aria-label={`Sélectionner ${r.name}`} onClick={() => toggle(r.id)} style={box(on)}>
                      {on ? '✓' : ''}
                    </button>
                  </span>
                  <Link
                    role="cell"
                    href={`/collectivite/entreprises/${r.id}`}
                    style={{ display: 'flex', gap: 10, alignItems: 'center', minWidth: 0, color: 'var(--text)' }}
                  >
                    <span style={{ width: 36, height: 36, borderRadius: 8, overflow: 'hidden', flexShrink: 0 }}>
                      <Photo src={r.image} alt="" label={r.name} color={r.color} />
                    </span>
                    <b style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{r.name}</b>
                  </Link>
                  <span role="cell">{r.commune}</span>
                  <span role="cell" style={{ color: 'var(--muted)' }}>
                    {r.category}
                  </span>
                  <span role="cell">
                    <span
                      style={{
                        fontSize: 12,
                        fontWeight: 700,
                        padding: '4px 10px',
                        borderRadius: 999,
                        background: r.status.bg,
                        color: r.status.fg,
                        whiteSpace: 'nowrap',
                      }}
                    >
                      {r.status.label}
                    </span>
                  </span>
                  <span role="cell" style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <span style={{ flex: 1, height: 7, background: 'var(--sand)', borderRadius: 4, overflow: 'hidden' }}>
                      <span
                        style={{
                          display: 'block',
                          height: '100%',
                          width: `${r.completeness}%`,
                          background: r.completeness >= 70 ? 'var(--green)' : r.completeness >= 40 ? 'var(--amber)' : 'var(--danger)',
                        }}
                      />
                    </span>
                    <span style={{ fontSize: 12, width: 32 }}>{r.completeness}%</span>
                  </span>
                  <span role="cell" style={{ fontSize: 12, fontWeight: 700, color: r.planPaid ? '#7A5BB5' : 'var(--muted)' }}>
                    {r.plan}
                  </span>
                  <span role="cell" style={{ fontSize: 12, color: 'var(--muted)' }}>
                    {r.updated}
                  </span>
                </div>
              );
            })}
            {!rows.length ? <div style={{ padding: 30, textAlign: 'center', color: 'var(--muted)' }}>Aucune fiche ne correspond à ces critères.</div> : null}
          </div>
        </div>
      </div>
    </>
  );
}
