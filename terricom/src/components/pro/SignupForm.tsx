'use client';

import Link from 'next/link';
import { useActionState, useState, useTransition } from 'react';
import { lookupSiretAction, registerAction, type SignupState } from '@/app/pro/inscription/actions';
import { FAMILIES, type Family } from '@/lib/constants';

type Territory = { id: string; name: string; communes: { id: string; name: string; postalCode: string | null; inseeCode: string }[] };
type Category = { id: string; name: string; family: string };
export type SignupPrefill = { siret: string; name: string; categoryId: string; activityLabel: string; street: string; communeId: string; phone: string; firstName: string; lastName: string; email: string; password: string };

const idle: SignupState = { status: 'idle' };

/** Formulaire d'inscription d'une activité absente de la base. */
export function SignupForm({ territories, categories, loggedIn, prefill }: { territories: Territory[]; categories: Category[]; loggedIn: boolean; prefill: SignupPrefill | null }) {
  const [state, action, pending] = useActionState(registerAction, idle);
  const [siret, setSiret] = useState(prefill?.siret ?? '');
  const [name, setName] = useState(prefill?.name ?? '');
  const [street, setStreet] = useState(prefill?.street ?? '');
  const [communeId, setCommuneId] = useState(prefill?.communeId ?? '');
  const [lookup, setLookup] = useState<{ ok: boolean; message: string } | null>(null);
  const [searching, startSearch] = useTransition();
  const byFamily = new Map<string, Category[]>();
  for (const c of categories) byFamily.set(c.family, [...(byFamily.get(c.family) ?? []), c]);

  const search = () =>
    startSearch(async () => {
      const r = await lookupSiretAction(siret);
      setLookup({ ok: r.ok, message: r.message });
      if (r.ok) {
        if (r.name) setName(r.name);
        if (r.street) setStreet(r.street);
        const commune = territories.flatMap((t) => t.communes).find((c) => c.inseeCode === r.inseeCode);
        if (commune) setCommuneId(commune.id);
      }
    });

  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      <fieldset style={{ border: 0, padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 12 }}>
        <legend className="sr-only">Votre activité</legend>
        <label className="field">
          <span>Numéro SIRET</span>
          <div style={{ display: 'flex', gap: 8 }}>
            <input name="siret" className="input" value={siret} onChange={(e) => setSiret(e.target.value)} inputMode="numeric" placeholder="123 456 789 00012" required style={{ fontFamily: 'var(--font-mono)' }} />
            <button type="button" className="btn btn-outline" onClick={search} disabled={searching || siret.replace(/\s/g, '').length !== 14}>
              {searching ? 'Recherche…' : 'Remplir'}
            </button>
          </div>
          {lookup ? (
            <small role="status" style={{ color: lookup.ok ? 'var(--green)' : 'var(--brick)', fontWeight: 600 }}>
              {lookup.ok ? '✓ ' : '● '}
              {lookup.message}
            </small>
          ) : (
            <small style={{ color: 'var(--muted)' }}>Micro-entrepreneur, artisan, commerçant, exploitation agricole : tout le monde a un SIRET.</small>
          )}
        </label>
        <label className="field">
          <span>Nom de l&apos;établissement</span>
          <input name="name" className="input" value={name} onChange={(e) => setName(e.target.value)} required maxLength={160} />
        </label>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 12 }}>
          <label className="field">
            <span>Catégorie</span>
            <select name="categoryId" className="input" required defaultValue={prefill?.categoryId ?? ''}>
              <option value="" disabled>
                Choisir…
              </option>
              {[...byFamily.entries()].map(([family, list]) => (
                <optgroup key={family} label={FAMILIES[family as Family]?.label ?? family}>
                  {list.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                    </option>
                  ))}
                </optgroup>
              ))}
            </select>
          </label>
          <label className="field">
            <span>Activité (facultatif)</span>
            <input name="activityLabel" className="input" placeholder="Ex. Céramiste, Épicerie vrac" defaultValue={prefill?.activityLabel} maxLength={160} />
          </label>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 12 }}>
          <label className="field">
            <span>Adresse</span>
            <input name="street" className="input" value={street} onChange={(e) => setStreet(e.target.value)} required autoComplete="street-address" />
          </label>
          <label className="field">
            <span>Commune</span>
            <select name="communeId" className="input" required value={communeId} onChange={(e) => setCommuneId(e.target.value)}>
              <option value="" disabled>
                Choisir…
              </option>
              {territories.map((t) => (
                <optgroup key={t.id} label={t.name}>
                  {t.communes.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.name}
                      {c.postalCode ? ` (${c.postalCode})` : ''}
                    </option>
                  ))}
                </optgroup>
              ))}
            </select>
          </label>
        </div>
        <label className="field">
          <span>Téléphone public (facultatif)</span>
          <input name="phone" className="input" inputMode="tel" defaultValue={prefill?.phone} autoComplete="tel" />
        </label>
      </fieldset>
      {loggedIn ? null : (
        <fieldset style={{ border: 0, padding: 0, margin: '6px 0 0', display: 'flex', flexDirection: 'column', gap: 12 }}>
          <legend style={{ fontWeight: 800, marginBottom: 10 }}>Votre accès</legend>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
            <input name="firstName" className="input" placeholder="Prénom" aria-label="Prénom" required autoComplete="given-name" defaultValue={prefill?.firstName} />
            <input name="lastName" className="input" placeholder="Nom" aria-label="Nom" required autoComplete="family-name" defaultValue={prefill?.lastName} />
          </div>
          <input name="email" type="email" className="input" placeholder="Email professionnel" aria-label="Email professionnel" required autoComplete="email" defaultValue={prefill?.email} />
          <input
            name="password"
            type="password"
            className="input"
            placeholder="Mot de passe (10 caractères minimum)"
            aria-label="Mot de passe"
            minLength={10}
            required
            autoComplete="new-password"
            defaultValue={prefill?.password}
          />
          <div aria-hidden="true" style={{ position: 'absolute', left: -9999, width: 1, height: 1, overflow: 'hidden' }}>
            <input name="website" tabIndex={-1} autoComplete="off" />
          </div>
          <label style={{ display: 'flex', gap: 8, fontSize: 13, color: 'var(--muted)', alignItems: 'flex-start' }}>
            <input type="checkbox" name="cgu" required defaultChecked={Boolean(prefill)} style={{ accentColor: 'var(--green)', marginTop: 2 }} />
            <span>
              J&apos;accepte les <Link href="/cgu">conditions d&apos;utilisation</Link> et la <Link href="/confidentialite">politique de confidentialité</Link>.
            </span>
          </label>
        </fieldset>
      )}
      {state.status === 'error' ? (
        <div className="alert alert-error" role="alert">
          {state.message}{' '}
          {state.existingId ? <Link href={`/pro/revendiquer/${state.existingId}`}>Revendiquer la fiche existante →</Link> : null}
          {state.needsLogin ? <Link href="/connexion?next=/pro/inscription">Me connecter →</Link> : null}
        </div>
      ) : null}
      <button type="submit" disabled={pending} style={{ border: 0, background: 'var(--green)', color: '#fff', padding: 14, borderRadius: 12, fontWeight: 700, cursor: 'pointer', fontSize: 15, opacity: pending ? 0.7 : 1 }}>
        {pending ? 'Envoi…' : 'Créer ma fiche'}
      </button>
    </form>
  );
}
