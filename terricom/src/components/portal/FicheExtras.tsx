'use client';

import { useActionState } from 'react';
import { followEstablishment, submitCustomForm, type FormState } from '@/app/[territory]/actions';
import type { FormField } from '@/server/db/schema';

/** Abonnement aux nouveautés d'un commerce (lettre client, double opt-in). */
export function FollowCard({ establishmentId, name }: { establishmentId: string; name: string }) {
  const [state, action, pending] = useActionState<FormState, FormData>(followEstablishment, { status: 'idle' });
  if (state.status === 'ok') {
    return (
      <div className="card follow-card" role="status">
        <span className="stamp" style={{ alignSelf: 'flex-start' }}>
          Merci !
        </span>
        <div style={{ fontSize: 14, lineHeight: 1.5 }}>{state.message}</div>
      </div>
    );
  }
  return (
    <form action={action} className="card follow-card">
      <div>
        <div style={{ fontWeight: 700 }}>Suivre {name}</div>
        <div style={{ fontSize: 13, color: 'var(--muted)', lineHeight: 1.45 }}>Nouveautés, offres et événements par email. Quatre envois par mois au plus.</div>
      </div>
      <input type="hidden" name="establishmentId" value={establishmentId} />
      <input type="text" name="website" tabIndex={-1} autoComplete="off" className="sr-only" aria-hidden="true" />
      <label className="sr-only" htmlFor={`follow-email-${establishmentId}`}>
        Votre email
      </label>
      <input
        id={`follow-email-${establishmentId}`}
        name="email"
        type="email"
        className="input"
        placeholder="Votre email"
        autoComplete="email"
        required
        maxLength={254}
      />
      <label className="checkbox" style={{ fontSize: 12, color: 'var(--muted)' }}>
        <input type="checkbox" name="consent" required />
        <span>J&apos;accepte de recevoir les emails de {name}. Désinscription en un clic.</span>
      </label>
      {state.status === 'error' ? (
        <div className="alert alert-error" role="alert">
          {state.message}
        </div>
      ) : null}
      <button type="submit" className="btn btn-dark" disabled={pending} style={{ padding: 11, borderRadius: 10, justifyContent: 'center' }}>
        {pending ? 'Envoi…' : 'Je m’abonne'}
      </button>
    </form>
  );
}

export type PublicForm = { id: string; title: string; intro: string | null; fields: FormField[]; submitLabel: string };

function Field({ formId, field }: { formId: string; field: FormField }) {
  const id = `cf-${formId}-${field.id}`;
  const name = `f_${field.id}`;
  const label = (
    <>
      {field.label}
      {field.required ? (
        <span aria-hidden="true" style={{ color: 'var(--danger-fg)' }}>
          {' '}
          *
        </span>
      ) : null}
    </>
  );
  const help = field.help ? (
    <small id={`${id}-help`} style={{ color: 'var(--muted)', fontSize: 12 }}>
      {field.help}
    </small>
  ) : null;
  const describedBy = field.help ? `${id}-help` : undefined;
  if (field.type === 'checkbox') {
    return (
      <div className="custom-form-wide" style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
        <label className="checkbox" htmlFor={id}>
          <input id={id} type="checkbox" name={name} required={field.required} aria-describedby={describedBy} />
          <span>{label}</span>
        </label>
        {help}
      </div>
    );
  }
  return (
    <div className={`field${field.type === 'textarea' ? ' custom-form-wide' : ''}`}>
      <label htmlFor={id}>{label}</label>
      {field.type === 'textarea' ? (
        <textarea id={id} name={name} rows={4} className="textarea" required={field.required} maxLength={3000} aria-describedby={describedBy} />
      ) : field.type === 'select' ? (
        <select id={id} name={name} className="select" required={field.required} defaultValue="" aria-describedby={describedBy}>
          <option value="" disabled={field.required}>
            Choisir…
          </option>
          {(field.options ?? []).map((o) => (
            <option key={o} value={o}>
              {o}
            </option>
          ))}
        </select>
      ) : (
        <input
          id={id}
          name={name}
          type={field.type}
          className="input"
          required={field.required}
          maxLength={field.type === 'number' || field.type === 'date' ? undefined : 300}
          inputMode={field.type === 'number' ? 'decimal' : undefined}
          aria-describedby={describedBy}
        />
      )}
      {help}
    </div>
  );
}

/** Formulaire personnalisé du professionnel (devis, réservation, inscription…). */
export function CustomFormCard({ form, name }: { form: PublicForm; name: string }) {
  const [state, action, pending] = useActionState<FormState, FormData>(submitCustomForm, { status: 'idle' });
  if (state.status === 'ok') {
    return (
      <div className="card custom-form" role="status">
        <span className="stamp" style={{ alignSelf: 'flex-start' }}>
          Demande envoyée
        </span>
        <div style={{ fontSize: 15, lineHeight: 1.5 }}>{state.message}</div>
      </div>
    );
  }
  return (
    <form action={action} className="card custom-form" aria-labelledby={`cf-title-${form.id}`}>
      <div>
        <h3 id={`cf-title-${form.id}`} className="h3" style={{ margin: 0 }}>
          {form.title}
        </h3>
        {form.intro ? <p style={{ margin: '6px 0 0', color: 'var(--muted)', fontSize: 14, lineHeight: 1.5, whiteSpace: 'pre-line' }}>{form.intro}</p> : null}
      </div>
      <input type="hidden" name="formId" value={form.id} />
      <input type="text" name="website" tabIndex={-1} autoComplete="off" className="sr-only" aria-hidden="true" />
      <div className="custom-form-grid">
        <div className="field">
          <label htmlFor={`cf-${form.id}-name`}>
            Votre nom
            <span aria-hidden="true" style={{ color: 'var(--danger-fg)' }}>
              {' '}
              *
            </span>
          </label>
          <input id={`cf-${form.id}-name`} name="name" className="input" autoComplete="name" required maxLength={120} />
        </div>
        <div className="field">
          <label htmlFor={`cf-${form.id}-email`}>
            Email
            <span aria-hidden="true" style={{ color: 'var(--danger-fg)' }}>
              {' '}
              *
            </span>
          </label>
          <input id={`cf-${form.id}-email`} name="email" type="email" className="input" autoComplete="email" required maxLength={254} />
        </div>
        <div className="field">
          <label htmlFor={`cf-${form.id}-phone`}>Téléphone</label>
          <input id={`cf-${form.id}-phone`} name="phone" type="tel" className="input" autoComplete="tel" maxLength={32} />
        </div>
        {form.fields.map((f) => (
          <Field key={f.id} formId={form.id} field={f} />
        ))}
      </div>
      <label className="checkbox" style={{ fontSize: 12, color: 'var(--muted)' }}>
        <input type="checkbox" name="consent" required />
        <span>J&apos;accepte que ces informations soient transmises à {name} pour traiter ma demande.</span>
      </label>
      {state.status === 'error' ? (
        <div className="alert alert-error" role="alert">
          {state.message}
        </div>
      ) : null}
      <button type="submit" className="btn btn-brand" disabled={pending} style={{ alignSelf: 'flex-start' }}>
        {pending ? 'Envoi…' : form.submitLabel}
      </button>
    </form>
  );
}
