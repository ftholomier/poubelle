'use client';

import { useActionState, useState } from 'react';
import { applyToJob, type FormState } from '@/app/[territory]/actions';
import { useT } from './I18n';

/** Candidature en deux minutes (CV PDF facultatif, données transmises au seul employeur). */
export function ApplyForm({ jobId, companyName }: { jobId: string; companyName: string }) {
  const [state, action, pending] = useActionState<FormState, FormData>(applyToJob, { status: 'idle' });
  const [file, setFile] = useState<string | null>(null);
  const t = useT();
  if (state.status === 'ok') {
    return (
      <div style={{ display: 'flex', flexDirection: 'column', gap: 10, alignItems: 'flex-start' }} role="status">
        <span className="display" style={{ background: 'var(--amber)', fontSize: 15, padding: '7px 12px', borderRadius: 10, transform: 'rotate(-3deg)' }}>
          {t('apply.sent')}
        </span>
        <div style={{ fontSize: 15, lineHeight: 1.5 }}>{state.message ?? t('apply.received', { name: companyName })}</div>
      </div>
    );
  }
  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      <b style={{ fontSize: 17 }}>{t('apply.title')}</b>
      <input type="hidden" name="jobId" value={jobId} />
      <input type="text" name="website" tabIndex={-1} autoComplete="off" className="sr-only" aria-hidden="true" />
      <label className="sr-only" htmlFor="apply-name">
        {t('fc.fullName')}
      </label>
      <input id="apply-name" name="fullName" className="input" placeholder={t('fc.fullName')} autoComplete="name" required maxLength={160} />
      <label className="sr-only" htmlFor="apply-email">
        {t('fc.email')}
      </label>
      <input id="apply-email" name="email" type="email" className="input" placeholder={t('fc.email')} autoComplete="email" required maxLength={254} />
      <input
        name="phone"
        type="tel"
        className="input"
        placeholder={t('fc.phoneOptional')}
        aria-label={t('fc.phoneOptional')}
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
        {file ? `✓ ${file}` : t('apply.cv')}
        <input type="file" name="cv" accept="application/pdf" className="sr-only" onChange={(e) => setFile(e.target.files?.[0]?.name ?? null)} />
      </label>
      <textarea name="message" rows={3} className="textarea" placeholder={t('apply.about')} aria-label={t('apply.about')} maxLength={3000} />
      <label className="checkbox" style={{ fontSize: 12, color: 'var(--muted)' }}>
        <input type="checkbox" name="consent" required />
        <span>{t('apply.consent', { name: companyName })}</span>
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
        {pending ? t('common.sending') : t('apply.submit')}
      </button>
      <div style={{ fontSize: 11, color: 'var(--muted)' }}>{t('apply.gdpr')}</div>
    </form>
  );
}
