'use client';

import { useActionState, useState } from 'react';
import { createApiKeyAction, type ApiKeyState } from '@/app/collectivite/api/actions';

/** Création d'une clé d'API : la valeur complète n'est montrée qu'une fois, avec un bouton de copie. */
export function ApiKeyCreator() {
  const [state, action, pending] = useActionState<ApiKeyState, FormData>(createApiKeyAction, { status: 'idle' });
  const [copied, setCopied] = useState(false);
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      <form action={action} style={{ display: 'flex', gap: 8, alignItems: 'flex-end', flexWrap: 'wrap' }}>
        <label className="field" style={{ flex: '1 1 260px' }}>
          <span>Nom de la clé</span>
          <input name="name" className="input" required maxLength={160} placeholder="Site de l’office de tourisme" />
        </label>
        <button type="submit" className="btn btn-brand" disabled={pending}>
          {pending ? 'Création…' : 'Créer une clé'}
        </button>
      </form>
      {state.status === 'error' ? (
        <div className="alert alert-error" role="alert">
          {state.message}
        </div>
      ) : null}
      {state.status === 'ok' && state.key ? (
        <div className="alert alert-info" role="status" style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <b>{state.message}</b>
          <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
            <code className="mono" style={{ background: 'var(--paper)', padding: '8px 10px', borderRadius: 8, overflowWrap: 'anywhere', flex: 1 }}>
              {state.key}
            </code>
            <button
              type="button"
              className="btn btn-outline btn-sm"
              onClick={async () => {
                await navigator.clipboard?.writeText(state.key!);
                setCopied(true);
              }}
            >
              {copied ? 'Copiée ✓' : 'Copier'}
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
