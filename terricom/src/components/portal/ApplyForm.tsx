'use client';

import { useActionState, useState } from 'react';
import { applyToJob, type FormState } from '@/app/[territory]/actions';

/** Candidature en deux minutes (CV PDF facultatif, données transmises au seul employeur). */
export function ApplyForm({ jobId, companyName }: { jobId: string; companyName: string }) {
  const [state, action, pending] = useActionState<FormState, FormData>(applyToJob, { status: 'idle' });
  const [file, setFile] = useState<string | null>(null);
  if (state.status === 'ok') {
    return (
      <div style={{ display: 'flex', flexDirection: 'column', gap: 10, alignItems: 'flex-start' }} role="status">
        <span className="display" style={{ background: 'var(--amber)', fontSize: 15, padding: '7px 12px', borderRadius: 10, transform: 'rotate(-3deg)' }}>
          Candidature envoyée !
        </span>
        <div style={{ fontSize: 15, lineHeight: 1.5 }}>{state.message ?? `${companyName} a bien reçu votre candidature.`}</div>
      </div>
    );
  }
  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      <b style={{ fontSize: 17 }}>Postuler en 2 minutes</b>
      <input type="hidden" name="jobId" value={jobId} />
      <input type="text" name="website" tabIndex={-1} autoComplete="off" className="sr-only" aria-hidden="true" />
      <label className="sr-only" htmlFor="apply-name">
        Prénom et nom
      </label>
      <input id="apply-name" name="fullName" className="input" placeholder="Prénom et nom" autoComplete="name" required maxLength={160} />
      <label className="sr-only" htmlFor="apply-email">
        Email
      </label>
      <input id="apply-email" name="email" type="email" className="input" placeholder="Email" autoComplete="email" required maxLength={254} />
      <input
        name="phone"
        type="tel"
        className="input"
        placeholder="Téléphone (facultatif)"
        aria-label="Téléphone (facultatif)"
        autoComplete="tel"
        maxLength={32}
      />
      <label
        style={{
          border: '2px dashed var(--sand-3)',
          borderRadius: 10,
          padding: 14,
          textAlign: 'center',
          fontSize: 13,
          color: file ? 'var(--green)' : 'var(--muted)',
          fontWeight: 600,
          cursor: 'pointer',
        }}
      >
        {file ? `✓ ${file}` : '+ Déposer mon CV (PDF)'}
        <input type="file" name="cv" accept="application/pdf" className="sr-only" onChange={(e) => setFile(e.target.files?.[0]?.name ?? null)} />
      </label>
      <textarea
        name="message"
        rows={3}
        className="textarea"
        placeholder="Quelques mots sur vous (facultatif)"
        aria-label="Quelques mots sur vous (facultatif)"
        maxLength={3000}
      />
      <label className="checkbox" style={{ fontSize: 12, color: 'var(--muted)' }}>
        <input type="checkbox" name="consent" required />
        <span>J&apos;accepte que ma candidature soit transmise à {companyName}.</span>
      </label>
      {state.status === 'error' ? (
        <div className="alert alert-error" role="alert">
          {state.message}
        </div>
      ) : null}
      <button
        type="submit"
        className="btn btn-brand"
        disabled={pending}
        style={{ justifyContent: 'center', padding: 14, borderRadius: 12, fontWeight: 800, fontSize: 15 }}
      >
        {pending ? 'Envoi…' : 'Envoyer ma candidature'}
      </button>
      <div style={{ fontSize: 11, color: 'var(--muted)' }}>
        Vos données sont transmises uniquement à l&apos;employeur (RGPD) et supprimées au plus tard 2 ans après votre candidature.
      </div>
    </form>
  );
}
