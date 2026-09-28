'use client';

import { useActionState } from 'react';
import { searchStopAction, type CircState } from '@/app/collectivite/circuits/actions';

export function StopSearch({ circuitId }: { circuitId: string }) {
  const [state, action, pending] = useActionState(searchStopAction, { status: 'idle' } as CircState);
  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
      <input type="hidden" name="circuitId" value={circuitId} />
      <div style={{ display: 'flex', gap: 6 }}>
        <input
          name="q"
          className="input"
          placeholder="Rechercher un établissement…"
          aria-label="Rechercher un établissement à ajouter"
          style={{ fontSize: 13 }}
        />
        <button type="submit" className="btn btn-outline btn-sm" disabled={pending}>
          Ajouter
        </button>
      </div>
      {state.status !== 'idle' ? (
        <span role="status" style={{ fontSize: 12, fontWeight: 600, color: state.status === 'error' ? 'var(--danger-fg)' : 'var(--green)' }}>
          {state.message}
        </span>
      ) : null}
    </form>
  );
}
