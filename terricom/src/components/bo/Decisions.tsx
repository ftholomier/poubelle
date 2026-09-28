'use client';

import { useActionState, useState } from 'react';
import {
  approveClaimAction,
  approvePostAction,
  rejectClaimAction,
  rejectPostAction,
  requestInfoAction,
  type ModState,
} from '@/app/collectivite/moderation/actions';

const idle: ModState = { status: 'idle' };

function Msg({ state }: { state: ModState }) {
  if (state.status === 'idle') return null;
  return (
    <div
      className={`alert ${state.status === 'error' ? 'alert-error' : 'alert-ok'}`}
      role={state.status === 'error' ? 'alert' : 'status'}
      style={{ width: '100%' }}
    >
      {state.message}
    </div>
  );
}

/** Décision sur une revendication : valider, demander un justificatif, refuser. */
export function ClaimDecision({ claimId, canDecide }: { claimId: string; canDecide: boolean }) {
  const [mode, setMode] = useState<'none' | 'info' | 'reject'>('none');
  const [approve, approveAction, approving] = useActionState(approveClaimAction, idle);
  const [info, infoAction, asking] = useActionState(requestInfoAction, idle);
  const [reject, rejectAction, rejecting] = useActionState(rejectClaimAction, idle);
  if (!canDecide) return <p style={{ margin: 0, fontSize: 13, color: 'var(--muted)' }}>Seuls les administrateurs peuvent statuer sur une revendication.</p>;
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 10, borderTop: '1px solid var(--line-2)', paddingTop: 16 }}>
      <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
        <form action={approveAction}>
          <input type="hidden" name="claimId" value={claimId} />
          <button
            type="submit"
            disabled={approving}
            style={{ border: 0, background: 'var(--green)', color: '#fff', padding: '12px 20px', borderRadius: 12, fontWeight: 800, cursor: 'pointer' }}
          >
            {approving ? 'Validation…' : 'Valider · l’entreprise prend la main'}
          </button>
        </form>
        <button
          type="button"
          onClick={() => setMode(mode === 'info' ? 'none' : 'info')}
          aria-expanded={mode === 'info'}
          style={{
            border: '1.5px solid var(--ink)',
            background: 'transparent',
            padding: '12px 18px',
            borderRadius: 12,
            fontWeight: 700,
            cursor: 'pointer',
            color: 'var(--text)',
          }}
        >
          Demander un justificatif
        </button>
        <button
          type="button"
          onClick={() => setMode(mode === 'reject' ? 'none' : 'reject')}
          aria-expanded={mode === 'reject'}
          style={{
            border: 0,
            background: 'var(--danger-bg)',
            color: 'var(--danger-fg)',
            padding: '12px 18px',
            borderRadius: 12,
            fontWeight: 700,
            cursor: 'pointer',
            marginLeft: 'auto',
          }}
        >
          Refuser
        </button>
      </div>
      {mode === 'info' ? (
        <form action={infoAction} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <input type="hidden" name="claimId" value={claimId} />
          <label htmlFor="info-note" style={{ fontSize: 13, fontWeight: 600 }}>
            Justificatif demandé (envoyé par email au demandeur)
          </label>
          <textarea
            id="info-note"
            name="note"
            className="input"
            rows={3}
            required
            defaultValue="Pouvez-vous nous transmettre un extrait Kbis de moins de 3 mois, ou tout document attestant de votre lien avec l'établissement ?"
          />
          <button type="submit" className="btn btn-dark btn-sm" disabled={asking} style={{ alignSelf: 'flex-start' }}>
            Envoyer la demande
          </button>
        </form>
      ) : null}
      {mode === 'reject' ? (
        <form action={rejectAction} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <input type="hidden" name="claimId" value={claimId} />
          <label htmlFor="reject-note" style={{ fontSize: 13, fontWeight: 600 }}>
            Motif du refus (communiqué au demandeur)
          </label>
          <textarea
            id="reject-note"
            name="note"
            className="input"
            rows={3}
            required
            placeholder="Ex. : le SIRET fourni ne correspond pas à cet établissement."
          />
          <button type="submit" className="btn btn-danger btn-sm" disabled={rejecting} style={{ alignSelf: 'flex-start' }}>
            Confirmer le refus
          </button>
        </form>
      ) : null}
      <Msg state={approve} />
      <Msg state={info} />
      <Msg state={reject} />
    </div>
  );
}

/** Décision sur une publication en attente de modération. */
export function PostDecision({ postId }: { postId: string }) {
  const [rejectOpen, setRejectOpen] = useState(false);
  const [ok, okAction, publishing] = useActionState(approvePostAction, idle);
  const [ko, koAction, rejecting] = useActionState(rejectPostAction, idle);
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 10, borderTop: '1px solid var(--line-2)', paddingTop: 16 }}>
      <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
        <form action={okAction}>
          <input type="hidden" name="postId" value={postId} />
          <button
            type="submit"
            disabled={publishing}
            style={{ border: 0, background: 'var(--green)', color: '#fff', padding: '12px 20px', borderRadius: 12, fontWeight: 800, cursor: 'pointer' }}
          >
            {publishing ? 'Publication…' : 'Publier'}
          </button>
        </form>
        <button
          type="button"
          onClick={() => setRejectOpen((o) => !o)}
          aria-expanded={rejectOpen}
          style={{
            border: 0,
            background: 'var(--danger-bg)',
            color: 'var(--danger-fg)',
            padding: '12px 18px',
            borderRadius: 12,
            fontWeight: 700,
            cursor: 'pointer',
            marginLeft: 'auto',
          }}
        >
          Refuser
        </button>
      </div>
      {rejectOpen ? (
        <form action={koAction} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <input type="hidden" name="postId" value={postId} />
          <label htmlFor="post-note" style={{ fontSize: 13, fontWeight: 600 }}>
            Motif (envoyé dans la messagerie du professionnel)
          </label>
          <textarea
            id="post-note"
            name="note"
            className="input"
            rows={3}
            required
            placeholder="Ex. : les promotions de plus de 50 % doivent préciser leur durée."
          />
          <button type="submit" className="btn btn-danger btn-sm" disabled={rejecting} style={{ alignSelf: 'flex-start' }}>
            Confirmer le refus
          </button>
        </form>
      ) : null}
      <Msg state={ok} />
      <Msg state={ko} />
    </div>
  );
}
