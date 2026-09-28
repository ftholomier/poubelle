'use client';

import Link from 'next/link';
import { useActionState } from 'react';
import { requestDemoAction, type DemoState } from '@/app/demo/actions';

const KINDS: [string, string][] = [
  ['CC', 'Communauté de communes'],
  ['CA', 'Communauté d’agglomération'],
  ['COMMUNE', 'Commune'],
  ['CU', 'Communauté urbaine'],
  ['METROPOLE', 'Métropole'],
  ['PETR', 'Pays / PETR'],
  ['OFFICE', 'Office économique, CCI'],
  ['AUTRE', 'Autre structure'],
];

export function DemoForm() {
  const [state, action, pending] = useActionState<DemoState, FormData>(requestDemoAction, { status: 'idle' });
  if (state.status === 'ok')
    return (
      <div className="mk-card" style={{ padding: 30, display: 'flex', flexDirection: 'column', gap: 12 }} role="status">
        <span
          className="display"
          style={{ alignSelf: 'flex-start', background: 'var(--amber)', padding: '7px 12px', borderRadius: 10, transform: 'rotate(-4deg)' }}
        >
          C’est noté !
        </span>
        <h2 className="display" style={{ fontSize: 32, margin: 0, letterSpacing: '-0.03em' }}>
          Merci{state.name ? ` ${state.name}` : ''}, on vous rappelle sous 48 h.
        </h2>
        <p style={{ margin: 0, color: '#4A514C', fontSize: 16, lineHeight: 1.55 }}>
          Un email de confirmation vient de partir. Nous préparons la démonstration avec les entreprises de vos communes : vos élus découvriront leur propre
          territoire.
        </p>
        <Link href="/territoires" style={{ fontWeight: 700 }}>
          En attendant, visitez un portail en ligne →
        </Link>
      </div>
    );
  return (
    <form action={action} className="mk-card" style={{ padding: 26, display: 'flex', flexDirection: 'column', gap: 12 }}>
      <div className="console-form-grid">
        <label className="field">
          <span>Prénom *</span>
          <input name="firstName" className="input" required autoComplete="given-name" />
        </label>
        <label className="field">
          <span>Nom *</span>
          <input name="lastName" className="input" required autoComplete="family-name" />
        </label>
      </div>
      <label className="field">
        <span>Fonction</span>
        <input name="role" className="input" placeholder="Vice-président·e au développement économique, DGS…" autoComplete="organization-title" />
      </label>
      <div className="console-form-grid">
        <label className="field">
          <span>Collectivité *</span>
          <input name="organization" className="input" required placeholder="Communauté de communes…" autoComplete="organization" />
        </label>
        <label className="field">
          <span>Type</span>
          <select name="kind" className="input" defaultValue="CC">
            {KINDS.map(([k, l]) => (
              <option key={k} value={k}>
                {l}
              </option>
            ))}
          </select>
        </label>
        <label className="field">
          <span>Nombre de communes</span>
          <input name="communes" type="number" min={1} max={500} className="input" />
        </label>
      </div>
      <div className="console-form-grid">
        <label className="field">
          <span>Email professionnel *</span>
          <input name="email" type="email" className="input" required autoComplete="email" />
        </label>
        <label className="field">
          <span>Téléphone</span>
          <input name="phone" className="input" inputMode="tel" autoComplete="tel" />
        </label>
      </div>
      <label className="field">
        <span>Votre projet</span>
        <textarea
          name="message"
          className="textarea"
          rows={4}
          placeholder="Contexte, calendrier (débat d’orientation budgétaire, conseil communautaire), attentes…"
        />
      </label>
      <div aria-hidden="true" style={{ position: 'absolute', left: -9999, width: 1, height: 1, overflow: 'hidden' }}>
        <input name="website" tabIndex={-1} autoComplete="off" />
      </div>
      <label className="checkbox">
        <input type="checkbox" name="consent" required style={{ accentColor: 'var(--green)', marginTop: 2 }} />
        <span>
          J’accepte d’être recontacté·e par l’équipe terricom au sujet de cette demande. Voir la{' '}
          <Link href="/confidentialite">politique de confidentialité</Link>.
        </span>
      </label>
      {state.status === 'error' ? (
        <div className="alert alert-error" role="alert">
          {state.message}
        </div>
      ) : null}
      <button type="submit" className="btn btn-dark" style={{ alignSelf: 'flex-start' }} disabled={pending} aria-busy={pending}>
        {pending ? 'Envoi…' : 'Demander ma démo'}
      </button>
    </form>
  );
}
