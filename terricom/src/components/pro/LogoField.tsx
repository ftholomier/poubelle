'use client';

import { useActionState, useRef } from 'react';
import { removeLogo, uploadLogo } from '@/app/pro/[est]/actions';
import type { ActionState } from '@/app/pro/[est]/actions';

/** Logo de l'établissement : affiché sur la fiche publique, à côté du nom. */
export function LogoField({ estId, logoUrl, name }: { estId: string; logoUrl: string | null; name: string }) {
  const [state, action, pending] = useActionState<ActionState, FormData>(uploadLogo, { status: 'idle' });
  const formRef = useRef<HTMLFormElement>(null);
  return (
    <div style={{ display: 'flex', gap: 14, alignItems: 'center', flexWrap: 'wrap' }}>
      <div
        style={{
          width: 64,
          height: 64,
          borderRadius: 16,
          border: '1px solid var(--line)',
          background: '#fff',
          display: 'grid',
          placeItems: 'center',
          overflow: 'hidden',
          flexShrink: 0,
          fontSize: 11,
          color: 'var(--muted)',
        }}
      >
        {logoUrl ? <img src={logoUrl} alt={`Logo ${name}`} style={{ width: '100%', height: '100%', objectFit: 'contain' }} /> : 'Logo'}
      </div>
      <form ref={formRef} action={action}>
        <input type="hidden" name="estId" value={estId} />
        <label className="btn btn-outline btn-sm" style={{ cursor: pending ? 'progress' : 'pointer' }}>
          {pending ? 'Envoi…' : logoUrl ? 'Remplacer le logo' : 'Ajouter un logo'}
          <input
            type="file"
            name="logo"
            accept="image/png,image/jpeg,image/webp,image/avif"
            className="sr-only"
            disabled={pending}
            onChange={() => formRef.current?.requestSubmit()}
          />
        </label>
      </form>
      {logoUrl ? (
        <form action={removeLogo}>
          <input type="hidden" name="estId" value={estId} />
          <button type="submit" className="btn-link" style={{ color: 'var(--danger-fg)', fontSize: 13 }}>
            Retirer
          </button>
        </form>
      ) : null}
      <span style={{ fontSize: 12, color: 'var(--muted)', flexBasis: '100%' }}>
        {state.status === 'error' ? (
          <span role="alert" style={{ color: 'var(--danger-fg)' }}>
            {state.message}
          </span>
        ) : state.status === 'ok' ? (
          <span role="status">{state.message}</span>
        ) : (
          'PNG transparent de préférence, carré, 10 Mo maximum.'
        )}
      </span>
    </div>
  );
}
