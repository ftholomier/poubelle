'use client';

import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { useActionState, useEffect, useState, useTransition } from 'react';
import {
  addDocumentAction,
  checkSiretAction,
  confirmClaimMfa,
  createClaimAccount,
  submitClaimAction,
  verifyCodeAction,
  type ClaimFormState,
} from '@/app/pro/revendiquer/actions';
import { FileDrop } from '@/components/ui/FileDrop';

const idle: ClaimFormState = { status: 'idle' };

const inputStyle = { border: '1px solid var(--line)', borderRadius: 10, padding: 13, fontSize: 15, width: '100%', background: '#fff' } as const;
const backStyle = {
  border: '1px solid var(--line)',
  background: '#fff',
  padding: '13px 18px',
  borderRadius: 12,
  fontWeight: 700,
  cursor: 'pointer',
  color: 'var(--text)',
} as const;
const nextStyle = {
  flex: 1,
  border: 0,
  background: 'var(--green)',
  color: '#fff',
  padding: 13,
  borderRadius: 12,
  fontWeight: 700,
  cursor: 'pointer',
  fontSize: 15,
} as const;

function ErrorLine({ state }: { state: ClaimFormState }) {
  if (state.status !== 'error') return null;
  return (
    <div className="alert alert-error" role="alert">
      {state.message}
    </div>
  );
}

export function StepButtons({ back, label, pending }: { back: string; label: string; pending?: boolean }) {
  return (
    <div style={{ display: 'flex', gap: 10 }}>
      <Link href={back} style={{ ...backStyle, textDecoration: 'none' }}>
        Retour
      </Link>
      <button type="submit" style={{ ...nextStyle, opacity: pending ? 0.7 : 1 }} disabled={pending}>
        {pending ? 'Un instant…' : label}
      </button>
    </div>
  );
}

// ─── Compte ────────────────────────────────────────────────────────────────

export function AccountForm({
  estId,
  back,
  loginHref,
  prefill,
}: {
  estId: string;
  back: string;
  loginHref: string;
  prefill?: { firstName: string; lastName: string; email: string; password: string } | null;
}) {
  const [state, action, pending] = useActionState(createClaimAccount, idle);
  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      <input type="hidden" name="estId" value={estId} />
      <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
        <label className="sr-only" htmlFor="c-first">
          Prénom
        </label>
        <input id="c-first" name="firstName" placeholder="Prénom" autoComplete="given-name" required defaultValue={prefill?.firstName} style={inputStyle} />
        <label className="sr-only" htmlFor="c-last">
          Nom
        </label>
        <input id="c-last" name="lastName" placeholder="Nom" autoComplete="family-name" required defaultValue={prefill?.lastName} style={inputStyle} />
      </div>
      <label className="sr-only" htmlFor="c-email">
        Email professionnel
      </label>
      <input
        id="c-email"
        name="email"
        type="email"
        placeholder="Email professionnel"
        autoComplete="email"
        required
        defaultValue={prefill?.email}
        style={inputStyle}
      />
      <label className="sr-only" htmlFor="c-pass">
        Mot de passe
      </label>
      <input
        id="c-pass"
        name="password"
        type="password"
        placeholder="Mot de passe (10 caractères minimum)"
        autoComplete="new-password"
        minLength={10}
        required
        defaultValue={prefill?.password}
        style={inputStyle}
      />
      <div aria-hidden="true" style={{ position: 'absolute', left: -9999, width: 1, height: 1, overflow: 'hidden' }}>
        <input name="website" tabIndex={-1} autoComplete="off" />
      </div>
      <label style={{ display: 'flex', gap: 8, fontSize: 13, color: 'var(--muted)', alignItems: 'center' }}>
        <input type="checkbox" name="mfa" defaultChecked style={{ accentColor: 'var(--green)' }} />
        Activer la double authentification (recommandé)
      </label>
      <label style={{ display: 'flex', gap: 8, fontSize: 13, color: 'var(--muted)', alignItems: 'flex-start' }}>
        <input type="checkbox" name="cgu" required defaultChecked={Boolean(prefill)} style={{ accentColor: 'var(--green)', marginTop: 2 }} />
        <span>
          J&apos;accepte les <Link href="/cgu">conditions d&apos;utilisation</Link> et la <Link href="/confidentialite">politique de confidentialité</Link>.
        </span>
      </label>
      <ErrorLine state={state} />
      {state.status === 'error' && state.message?.includes('connectez-vous') ? (
        <Link href={loginHref} className="btn btn-outline" style={{ justifyContent: 'center' }}>
          Me connecter et continuer
        </Link>
      ) : null}
      <StepButtons back={back} label="Continuer" pending={pending} />
    </form>
  );
}

// ─── Double authentification ───────────────────────────────────────────────

export function MfaEnrollForm({ secret, qrSvg, next, skip }: { secret: string; qrSvg: string; next: string; skip: string }) {
  const [state, action, pending] = useActionState(confirmClaimMfa, idle);
  if (state.status === 'ok' && state.recoveryCodes) {
    return (
      <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
        <div className="alert alert-ok" role="status">
          ✓ Double authentification activée.
        </div>
        <p style={{ margin: 0, color: 'var(--muted)', fontSize: 14 }}>
          Conservez ces codes de secours en lieu sûr : chacun permet une connexion si vous perdez votre téléphone. Ils ne seront plus affichés.
        </p>
        <div
          style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(2,1fr)',
            gap: 8,
            fontFamily: 'var(--font-mono)',
            fontSize: 15,
            background: 'var(--cream)',
            borderRadius: 12,
            padding: 14,
          }}
        >
          {state.recoveryCodes.map((c) => (
            <span key={c}>{c}</span>
          ))}
        </div>
        <Link href={next} style={{ ...nextStyle, textAlign: 'center', textDecoration: 'none' }}>
          J&apos;ai noté mes codes, continuer
        </Link>
      </div>
    );
  }
  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      <div style={{ display: 'grid', gridTemplateColumns: '150px 1fr', gap: 16, alignItems: 'center' }}>
        <div
          aria-label="QR code à scanner avec votre application d'authentification"
          role="img"
          style={{ width: 150, height: 150, border: '1px solid var(--line)', borderRadius: 12, padding: 6, background: '#fff' }}
          dangerouslySetInnerHTML={{ __html: qrSvg }}
        />
        <div style={{ fontSize: 14, color: 'var(--muted)', display: 'flex', flexDirection: 'column', gap: 6 }}>
          <span>1. Scannez ce QR code avec une application d&apos;authentification (Aegis, FreeOTP, Google Authenticator…).</span>
          <span>
            Ou saisissez la clé : <code style={{ fontFamily: 'var(--font-mono)', color: 'var(--text)', fontWeight: 700 }}>{secret}</code>
          </span>
          <span>2. Entrez le code à 6 chiffres affiché.</span>
        </div>
      </div>
      <label className="sr-only" htmlFor="mfa-code">
        Code à 6 chiffres
      </label>
      <input
        id="mfa-code"
        name="code"
        className="input code-input"
        inputMode="numeric"
        autoComplete="one-time-code"
        pattern="[0-9 ]{6,7}"
        maxLength={7}
        required
        placeholder="000000"
      />
      <ErrorLine state={state} />
      <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
        <Link href={skip} style={{ ...backStyle, textDecoration: 'none' }}>
          Plus tard
        </Link>
        <button type="submit" style={{ ...nextStyle, opacity: pending ? 0.7 : 1 }} disabled={pending}>
          {pending ? 'Vérification…' : 'Activer'}
        </button>
      </div>
    </form>
  );
}

// ─── Identité ──────────────────────────────────────────────────────────────

type Method = 'SIRET' | 'CODE' | 'KBIS';

function MethodCard({ active, onSelect, title, children }: { active: boolean; onSelect: () => void; title: string; children?: React.ReactNode }) {
  return (
    <div
      onClick={onSelect}
      style={{
        border: active ? '2px solid var(--green)' : '1px solid var(--line)',
        borderRadius: 14,
        padding: active ? 16 : 17,
        background: active ? 'var(--mint-2)' : 'transparent',
        cursor: 'pointer',
      }}
    >
      <label style={{ display: 'flex', gap: 10, alignItems: 'center', fontWeight: 700, cursor: 'pointer' }}>
        <input type="radio" name="method-choice" value={title} checked={active} onChange={onSelect} className="sr-only" />
        {title === 'SIRET' ? 'Numéro SIRET' : title === 'CODE' ? 'Code par courrier ou SMS' : 'Extrait Kbis'}
      </label>
      {children}
    </div>
  );
}

export function IdentityForm({ estId, back, siretPrefill, codeLabel }: { estId: string; back: string; siretPrefill: string; codeLabel: string }) {
  const [state, action, pending] = useActionState(submitClaimAction, idle);
  const [method, setMethod] = useState<Method>('SIRET');
  const [siret, setSiret] = useState(siretPrefill);
  const [verdict, setVerdict] = useState<{ ok: boolean | null; message: string } | null>(null);
  const [checking, startCheck] = useTransition();

  const check = (value: string) => {
    const digits = value.replace(/\s/g, '');
    if (digits.length !== 14) {
      setVerdict(digits.length ? { ok: null, message: 'Le SIRET comporte 14 chiffres.' } : null);
      return;
    }
    startCheck(async () => {
      const v = await checkSiretAction(estId, digits);
      setVerdict({ ok: v.ok, message: v.message });
    });
  };

  // Vérification initiale du SIRET prérempli (démonstration, fiche importée)
  useEffect(() => {
    if (siretPrefill.replace(/\s/g, '').length !== 14) return;
    let alive = true;
    checkSiretAction(estId, siretPrefill).then((v) => {
      if (alive) setVerdict({ ok: v.ok, message: v.message });
    });
    return () => {
      alive = false;
    };
  }, [estId, siretPrefill]);

  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      <input type="hidden" name="estId" value={estId} />
      <input type="hidden" name="method" value={method} />
      <div style={{ display: 'grid', gap: 10 }} role="radiogroup" aria-label="Méthode de vérification">
        <MethodCard active={method === 'SIRET'} onSelect={() => setMethod('SIRET')} title="SIRET">
          <input
            name="siret"
            value={siret}
            onChange={(e) => setSiret(e.target.value)}
            onBlur={(e) => check(e.target.value)}
            onClick={(e) => e.stopPropagation()}
            inputMode="numeric"
            placeholder="123 456 789 00012"
            aria-label="Numéro SIRET"
            style={{
              marginTop: 10,
              width: '100%',
              border: '1px solid var(--mint-4)',
              borderRadius: 10,
              padding: 12,
              fontSize: 15,
              fontFamily: 'var(--font-mono)',
              background: '#fff',
            }}
          />
          {checking ? (
            <div style={{ marginTop: 8, fontSize: 13, color: 'var(--muted)' }}>Vérification dans la base SIRENE…</div>
          ) : verdict ? (
            <div
              role="status"
              style={{
                marginTop: 8,
                fontSize: 13,
                fontWeight: 700,
                color: verdict.ok === true ? 'var(--green)' : verdict.ok === false ? 'var(--danger-fg)' : 'var(--brick)',
              }}
            >
              {verdict.ok === true ? '✓ ' : verdict.ok === false ? '✗ ' : '● '}
              {verdict.message}
            </div>
          ) : null}
        </MethodCard>
        <MethodCard active={method === 'CODE'} onSelect={() => setMethod('CODE')} title="CODE">
          <div style={{ fontSize: 13, color: 'var(--muted)', fontWeight: 400, marginTop: 2 }}>{codeLabel}</div>
        </MethodCard>
        <MethodCard active={method === 'KBIS'} onSelect={() => setMethod('KBIS')} title="KBIS">
          <div style={{ fontSize: 13, color: 'var(--muted)', fontWeight: 400, marginTop: 2 }}>Déposez un PDF de moins de 3 mois</div>
          {method === 'KBIS' ? (
            <div style={{ marginTop: 10 }} onClick={(e) => e.stopPropagation()}>
              <FileDrop name="kbis" accept="application/pdf" label="Choisir le PDF de mon Kbis (5 Mo max.)" />
            </div>
          ) : null}
        </MethodCard>
      </div>
      <ErrorLine state={state} />
      <StepButtons back={back} label="Envoyer ma demande" pending={pending} />
      <p style={{ margin: 0, fontSize: 12, color: 'var(--muted)' }}>
        Vos justificatifs ne sont visibles que par les agents habilités de votre collectivité et sont supprimés après la décision.
      </p>
    </form>
  );
}

// ─── Suivi ─────────────────────────────────────────────────────────────────

export function CodeEntryForm({ claimId }: { claimId: string }) {
  const [state, action, pending] = useActionState(verifyCodeAction, idle);
  return (
    <form action={action} style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
      <input type="hidden" name="claimId" value={claimId} />
      <label className="sr-only" htmlFor="claim-code">
        Code reçu
      </label>
      <input
        id="claim-code"
        name="code"
        className="input"
        inputMode="numeric"
        pattern="[0-9 ]{6,7}"
        maxLength={7}
        required
        placeholder="Code à 6 chiffres"
        style={{ maxWidth: 190, fontFamily: 'var(--font-mono)', fontSize: 17 }}
      />
      <button type="submit" className="btn btn-brand" disabled={pending}>
        {pending ? 'Vérification…' : 'Valider le code'}
      </button>
      {state.status === 'error' ? (
        <span role="alert" style={{ color: 'var(--danger-fg)', fontSize: 13, fontWeight: 600, width: '100%' }}>
          {state.message}
        </span>
      ) : null}
    </form>
  );
}

export function DocumentForm({ claimId }: { claimId: string }) {
  const [state, action, pending] = useActionState(addDocumentAction, idle);
  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 10, width: '100%' }}>
      <input type="hidden" name="claimId" value={claimId} />
      <FileDrop name="kbis" accept="application/pdf" label="Déposer le justificatif demandé (PDF, 5 Mo max.)" required />
      <ErrorLine state={state} />
      <button type="submit" className="btn btn-brand" disabled={pending} style={{ alignSelf: 'flex-start' }}>
        {pending ? 'Envoi…' : 'Envoyer le justificatif'}
      </button>
    </form>
  );
}

/** Rafraîchit la page de suivi pendant l'attente de validation. */
export function PendingRefresher({ seconds = 20 }: { seconds?: number }) {
  const router = useRouter();
  useEffect(() => {
    const id = window.setInterval(() => router.refresh(), seconds * 1000);
    return () => window.clearInterval(id);
  }, [router, seconds]);
  return null;
}
