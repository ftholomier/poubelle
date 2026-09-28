'use client';

import Link from 'next/link';
import { useActionState } from 'react';
import { loginAction, mfaAction, requestResetAction, resetPasswordAction, type AuthState } from '@/app/connexion/actions';

const idle: AuthState = { status: 'idle' };

function Message({ state }: { state: AuthState }) {
  if (state.status === 'error')
    return (
      <div className="alert alert-error" role="alert">
        {state.message}
      </div>
    );
  if (state.status === 'ok')
    return (
      <div className="alert alert-ok" role="status">
        {state.message}
      </div>
    );
  return null;
}

export function LoginForm({ next, notice }: { next?: string; notice?: string | null }) {
  const [state, action, pending] = useActionState(loginAction, idle);
  return (
    <form action={action}>
      <h2 className="h-page" style={{ fontSize: 40, margin: 0 }}>
        Connexion
      </h2>
      <p style={{ margin: 0, color: 'var(--muted)' }}>Espace professionnel, back-office de la collectivité ou console de la plateforme.</p>
      {notice ? (
        <div className="alert alert-info" role="status">
          {notice}
        </div>
      ) : null}
      <input type="hidden" name="next" value={next ?? ''} />
      <label className="field">
        <span>Email</span>
        <input name="email" type="email" className="input input-strong" autoComplete="username" required autoFocus />
      </label>
      <label className="field">
        <span style={{ display: 'flex', justifyContent: 'space-between' }}>
          Mot de passe
          <Link href="/mot-de-passe-oublie" style={{ fontWeight: 600, fontSize: 13 }}>
            Mot de passe oublié ?
          </Link>
        </span>
        <input name="password" type="password" className="input" autoComplete="current-password" required />
      </label>
      <Message state={state} />
      <button type="submit" className="btn btn-brand" disabled={pending} style={{ justifyContent: 'center', padding: 14, fontSize: 15 }}>
        {pending ? 'Connexion…' : 'Se connecter'}
      </button>
      <p style={{ fontSize: 14, color: 'var(--muted)', margin: '6px 0 0' }}>
        Vous êtes commerçant, artisan ou producteur ? <Link href="/pro/revendiquer">Revendiquez votre fiche gratuitement</Link>.
      </p>
    </form>
  );
}

export function MfaForm({ next }: { next?: string }) {
  const [state, action, pending] = useActionState(mfaAction, idle);
  return (
    <form action={action}>
      <h2 className="h-page" style={{ fontSize: 40, margin: 0 }}>
        Double authentification
      </h2>
      <p style={{ margin: 0, color: 'var(--muted)' }}>Saisissez le code à 6 chiffres affiché par votre application d&apos;authentification.</p>
      <input type="hidden" name="next" value={next ?? ''} />
      <label className="field">
        <span className="sr-only">Code de vérification</span>
        <input
          name="code"
          className="input code-input"
          inputMode="numeric"
          autoComplete="one-time-code"
          pattern="[0-9 ]{6,7}|[A-Za-z0-9-]{8,12}"
          maxLength={12}
          required
          autoFocus
          placeholder="000000"
        />
      </label>
      <Message state={state} />
      <button type="submit" className="btn btn-brand" disabled={pending} style={{ justifyContent: 'center', padding: 14, fontSize: 15 }}>
        {pending ? 'Vérification…' : 'Valider'}
      </button>
      <p style={{ fontSize: 13, color: 'var(--muted)', margin: 0 }}>
        Téléphone perdu ? Saisissez l&apos;un de vos codes de secours (format ABCD-EFGH) à la place du code.
      </p>
    </form>
  );
}

export function ResetRequestForm() {
  const [state, action, pending] = useActionState(requestResetAction, idle);
  return (
    <form action={action}>
      <h2 className="h-page" style={{ fontSize: 40, margin: 0 }}>
        Mot de passe oublié
      </h2>
      <p style={{ margin: 0, color: 'var(--muted)' }}>Indiquez votre email : nous vous envoyons un lien pour en choisir un nouveau.</p>
      <label className="field">
        <span>Email</span>
        <input name="email" type="email" className="input input-strong" autoComplete="username" required autoFocus />
      </label>
      <Message state={state} />
      <button type="submit" className="btn btn-brand" disabled={pending || state.status === 'ok'} style={{ justifyContent: 'center', padding: 14 }}>
        {pending ? 'Envoi…' : 'Recevoir le lien'}
      </button>
      <Link href="/connexion" style={{ fontSize: 14 }}>
        ← Retour à la connexion
      </Link>
    </form>
  );
}

export function ResetPasswordForm({ token }: { token: string }) {
  const [state, action, pending] = useActionState(resetPasswordAction, idle);
  return (
    <form action={action}>
      <h2 className="h-page" style={{ fontSize: 40, margin: 0 }}>
        Nouveau mot de passe
      </h2>
      <p style={{ margin: 0, color: 'var(--muted)' }}>Au moins 10 caractères, mêlant minuscules, majuscules, chiffres ou symboles.</p>
      <input type="hidden" name="token" value={token} />
      <label className="field">
        <span>Nouveau mot de passe</span>
        <input name="password" type="password" className="input" autoComplete="new-password" required minLength={10} autoFocus />
      </label>
      <label className="field">
        <span>Confirmation</span>
        <input name="confirm" type="password" className="input" autoComplete="new-password" required minLength={10} />
      </label>
      <Message state={state} />
      <button type="submit" className="btn btn-brand" disabled={pending} style={{ justifyContent: 'center', padding: 14 }}>
        {pending ? 'Enregistrement…' : 'Enregistrer'}
      </button>
    </form>
  );
}
