'use client';

import { useActionState } from 'react';
import { acceptInvitation, type InviteState } from '@/app/invitation/[token]/actions';

const idle: InviteState = { status: 'idle' };

/** Acceptation d'une invitation : création du compte si nécessaire. */
export function InvitationForm({ token, email, needsAccount }: { token: string; email: string; needsAccount: boolean }) {
  const [state, action, pending] = useActionState(acceptInvitation, idle);
  return (
    <form action={action}>
      <input type="hidden" name="token" value={token} />
      {needsAccount ? (
        <>
          <label className="field">
            <span>Email</span>
            <input className="input" value={email} readOnly aria-readonly="true" style={{ background: 'var(--cream)' }} />
          </label>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
            <label className="field">
              <span>Prénom</span>
              <input name="firstName" className="input" autoComplete="given-name" required />
            </label>
            <label className="field">
              <span>Nom</span>
              <input name="lastName" className="input" autoComplete="family-name" required />
            </label>
          </div>
          <label className="field">
            <span>Mot de passe</span>
            <input name="password" type="password" className="input" autoComplete="new-password" minLength={10} required />
            <small style={{ color: 'var(--muted)' }}>10 caractères minimum, avec lettres et chiffres.</small>
          </label>
        </>
      ) : null}
      {state.status === 'error' ? (
        <div className="alert alert-error" role="alert">
          {state.message}
        </div>
      ) : null}
      <button type="submit" className="btn btn-brand" disabled={pending} style={{ justifyContent: 'center', padding: 14, fontSize: 15 }}>
        {pending ? 'Un instant…' : needsAccount ? 'Créer mon compte et rejoindre' : 'Accepter l’invitation'}
      </button>
    </form>
  );
}
