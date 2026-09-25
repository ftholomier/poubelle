'use client';

import { useActionState } from 'react';
import { subscribeNewsletter, type SubscribeState } from '@/app/[territory]/actions';

/** Inscription à la lettre du territoire (double opt-in, consentement explicite). */
export function NewsletterForm({ territoryId, consentText, dark = true }: { territoryId: string; consentText: string; dark?: boolean }) {
  const [state, action, pending] = useActionState<SubscribeState, FormData>(subscribeNewsletter, { status: 'idle' });
  if (state.status === 'ok') {
    return (
      <div style={{ background: 'var(--brand)', borderRadius: 12, padding: 14, fontWeight: 600, color: '#fff' }} role="status">
        {state.message}
      </div>
    );
  }
  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
      <input type="hidden" name="territoryId" value={territoryId} />
      <input type="text" name="website" tabIndex={-1} autoComplete="off" className="sr-only" aria-hidden="true" />
      <div style={{ display: 'flex', gap: 8 }}>
        <label htmlFor="nl-email" className="sr-only">
          Votre adresse email
        </label>
        <input
          id="nl-email"
          name="email"
          type="email"
          required
          placeholder="votre@email.fr"
          autoComplete="email"
          style={{ flex: 1, minWidth: 0, border: 0, borderRadius: 10, padding: 13, fontSize: 15 }}
        />
        <button type="submit" className="btn btn-amber" disabled={pending} style={{ borderRadius: 10, padding: '0 18px' }}>
          {pending ? '…' : "S'inscrire"}
        </button>
      </div>
      <label style={{ display: 'flex', gap: 8, fontSize: 12, color: dark ? 'var(--sage)' : 'var(--muted)', lineHeight: 1.4 }}>
        <input type="checkbox" name="consent" required defaultChecked={false} style={{ accentColor: 'var(--amber)', marginTop: 2 }} />
        {consentText}
      </label>
      {state.status === 'error' ? (
        <div className="alert alert-error" role="alert">
          {state.message}
        </div>
      ) : null}
    </form>
  );
}
