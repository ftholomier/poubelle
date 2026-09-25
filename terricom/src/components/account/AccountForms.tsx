'use client';

import { useActionState } from 'react';
import { confirmMfaAction, deleteAccountAction, disableMfaAction, regenerateCodesAction, type AccountState } from '@/app/compte/actions';

const idle: AccountState = { status: 'idle' };

function Feedback({ state }: { state: AccountState }) {
  if (state.status === 'idle' || !state.message) return null;
  return (
    <div className={`alert ${state.status === 'error' ? 'alert-error' : 'alert-ok'}`} role={state.status === 'error' ? 'alert' : 'status'}>
      {state.message}
    </div>
  );
}

export function RecoveryCodes({ codes }: { codes: string[] }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
      <p style={{ margin: 0, fontSize: 14, color: 'var(--muted)' }}>
        Conservez ces codes de secours en lieu sûr (gestionnaire de mots de passe, papier) : chacun permet une connexion si vous perdez votre téléphone. Ils ne
        seront plus affichés.
      </p>
      <div
        style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill,minmax(120px,1fr))',
          gap: 8,
          fontFamily: 'var(--font-mono)',
          fontSize: 15,
          background: 'var(--cream)',
          borderRadius: 12,
          padding: 14,
        }}
      >
        {codes.map((c) => (
          <span key={c}>{c}</span>
        ))}
      </div>
    </div>
  );
}

export function MfaSetup({ secret, qrSvg }: { secret: string; qrSvg: string }) {
  const [state, action, pending] = useActionState(confirmMfaAction, idle);
  if (state.status === 'ok' && state.codes) {
    return (
      <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
        <Feedback state={state} />
        <RecoveryCodes codes={state.codes} />
      </div>
    );
  }
  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      <div style={{ display: 'grid', gridTemplateColumns: '150px 1fr', gap: 16, alignItems: 'center' }} className="mfa-grid">
        <div
          role="img"
          aria-label="QR code à scanner avec votre application d'authentification"
          style={{ width: 150, height: 150, border: '1px solid var(--line)', borderRadius: 12, padding: 6, background: '#fff' }}
          dangerouslySetInnerHTML={{ __html: qrSvg }}
        />
        <div style={{ fontSize: 14, color: 'var(--muted)', display: 'flex', flexDirection: 'column', gap: 6 }}>
          <span>1. Scannez ce QR code avec une application d&apos;authentification (Aegis, FreeOTP, Google Authenticator, 1Password…).</span>
          <span>
            Ou saisissez la clé : <code style={{ fontFamily: 'var(--font-mono)', color: 'var(--text)', fontWeight: 700 }}>{secret}</code>
          </span>
          <span>2. Entrez le code à 6 chiffres affiché pour confirmer.</span>
        </div>
      </div>
      <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
        <label className="sr-only" htmlFor="mfa-setup-code">
          Code à 6 chiffres
        </label>
        <input
          id="mfa-setup-code"
          name="code"
          className="input"
          inputMode="numeric"
          autoComplete="one-time-code"
          pattern="[0-9 ]{6,7}"
          maxLength={7}
          required
          placeholder="000 000"
          style={{ maxWidth: 180, fontFamily: 'var(--font-mono)', fontSize: 18 }}
        />
        <button type="submit" className="btn btn-brand" disabled={pending}>
          {pending ? 'Vérification…' : 'Activer la double authentification'}
        </button>
      </div>
      <Feedback state={state} />
    </form>
  );
}

export function RegenerateCodesForm() {
  const [state, action, pending] = useActionState(regenerateCodesAction, idle);
  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
      {state.codes ? (
        <RecoveryCodes codes={state.codes} />
      ) : (
        <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
          <label className="sr-only" htmlFor="regen-pass">
            Mot de passe
          </label>
          <input
            id="regen-pass"
            name="password"
            type="password"
            className="input"
            placeholder="Votre mot de passe"
            autoComplete="current-password"
            required
            style={{ maxWidth: 240 }}
          />
          <button type="submit" className="btn btn-outline" disabled={pending}>
            Générer de nouveaux codes de secours
          </button>
        </div>
      )}
      <Feedback state={state} />
    </form>
  );
}

export function DisableMfaForm() {
  const [state, action, pending] = useActionState(disableMfaAction, idle);
  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
      <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
        <label className="sr-only" htmlFor="mfa-off-code">
          Code de l&apos;application
        </label>
        <input
          id="mfa-off-code"
          name="code"
          className="input"
          inputMode="numeric"
          pattern="[0-9 ]{6,7}"
          maxLength={7}
          placeholder="Code à 6 chiffres"
          required
          style={{ maxWidth: 180 }}
        />
        <button type="submit" className="btn btn-ghost" disabled={pending} style={{ color: 'var(--danger-fg)' }}>
          Désactiver
        </button>
      </div>
      <Feedback state={state} />
    </form>
  );
}

export function DeleteAccountForm() {
  const [state, action, pending] = useActionState(deleteAccountAction, idle);
  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 10, maxWidth: 460 }}>
      <label className="field">
        <span>Mot de passe</span>
        <input name="password" type="password" className="input" autoComplete="current-password" required />
      </label>
      <label className="field">
        <span>Tapez SUPPRIMER pour confirmer</span>
        <input name="confirm" className="input" autoComplete="off" required pattern="[Ss][Uu][Pp][Pp][Rr][Ii][Mm][Ee][Rr]" />
      </label>
      <Feedback state={state} />
      <button type="submit" className="btn btn-danger" disabled={pending} style={{ alignSelf: 'flex-start' }}>
        {pending ? 'Suppression…' : 'Supprimer définitivement mon compte'}
      </button>
    </form>
  );
}
