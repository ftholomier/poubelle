'use client';

import Link from 'next/link';
import { useActionState, useEffect, useState, useTransition } from 'react';
import { communesByPostalCodeAction, directLookupAction, joinDirectAction, type CommuneChoice, type DirectState } from '@/app/pro/adhesion/actions';
import { FAMILIES, type Family } from '@/lib/constants';

type Category = { id: string; name: string; family: string };
type Prices = Record<'PREMIUM' | 'COMMUNICATION', { month: string; year: string }>;
export type DirectPrefill = {
  siret: string;
  name: string;
  categoryId: string;
  activityLabel: string;
  street: string;
  postalCode: string;
  phone: string;
  firstName: string;
  lastName: string;
  email: string;
  password: string;
};

const idle: DirectState = { status: 'idle' };

const FORMULAS: { key: 'PREMIUM' | 'COMMUNICATION'; name: string; pitch: string; features: string[] }[] = [
  {
    key: 'PREMIUM',
    name: 'Adhésion',
    pitch: 'Votre fiche complète et tous les outils pour faire venir vos clients.',
    features: ['Fiche publiée dans la vitrine terricom', 'Assistant de rédaction, publications illimitées', 'Statistiques, emploi, rendez-vous, formulaires'],
  },
  {
    key: 'COMMUNICATION',
    name: 'Adhésion Communication',
    pitch: 'Tout l’Adhésion, plus votre communication clients.',
    features: ['Tout ce que comprend l’Adhésion', 'Lettres à vos propres clients', 'Réseaux sociaux et mini-site'],
  },
];

/** Adhésion directe : entreprise hors territoire partenaire, fiche dans la vitrine nationale, formule payante. */
export function DirectMemberForm({
  categories,
  loggedIn,
  prefill,
  prices,
  billingEmail,
}: {
  categories: Category[];
  loggedIn: boolean;
  prefill: DirectPrefill | null;
  prices: Prices;
  billingEmail: string;
}) {
  const [state, action, pending] = useActionState(async (prev: DirectState, form: FormData) => {
    const res = await joinDirectAction(prev, form);
    if (res.redirectUrl) window.location.href = res.redirectUrl;
    return res;
  }, idle);
  const [siret, setSiret] = useState(prefill?.siret ?? '');
  const [name, setName] = useState(prefill?.name ?? '');
  const [street, setStreet] = useState(prefill?.street ?? '');
  const [postalCode, setPostalCode] = useState(prefill?.postalCode ?? '');
  const [choices, setChoices] = useState<CommuneChoice[]>([]);
  const [inseeCode, setInseeCode] = useState('');
  const [plan, setPlan] = useState<'PREMIUM' | 'COMMUNICATION'>('PREMIUM');
  const [interval, setBillingInterval] = useState<'MONTH' | 'YEAR'>('MONTH');
  const [note, setNote] = useState<{ ok: boolean; message: string; existingId?: string } | null>(null);
  const [busy, startBusy] = useTransition();
  const byFamily = new Map<string, Category[]>();
  for (const c of categories) byFamily.set(c.family, [...(byFamily.get(c.family) ?? []), c]);
  const chosen = choices.find((c) => c.inseeCode === inseeCode);

  const loadCommunes = (cp: string, select?: string | null) =>
    startBusy(async () => {
      const list = await communesByPostalCodeAction(cp);
      setChoices(list);
      setInseeCode(select && list.some((c) => c.inseeCode === select) ? select : list.length === 1 ? list[0].inseeCode : '');
    });

  // Code postal prérempli (démonstration) : charger les communes dès l'affichage.
  const initialPostalCode = prefill?.postalCode;
  useEffect(() => {
    if (initialPostalCode?.length === 5) loadCommunes(initialPostalCode);
  }, [initialPostalCode]);

  const lookup = () =>
    startBusy(async () => {
      const r = await directLookupAction(siret);
      setNote({ ok: r.ok, message: r.message, existingId: r.existingId });
      if (!r.ok) return;
      if (r.name) setName(r.name);
      if (r.street) setStreet(r.street);
      if (r.postalCode) {
        setPostalCode(r.postalCode);
        const list = await communesByPostalCodeAction(r.postalCode);
        setChoices(list);
        setInseeCode(r.inseeCode && list.some((c) => c.inseeCode === r.inseeCode) ? r.inseeCode : list.length === 1 ? list[0].inseeCode : '');
      }
    });

  const price = interval === 'YEAR' ? `${prices[plan].year} HT / an` : `${prices[plan].month} HT / mois`;

  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
      <fieldset style={{ border: 0, padding: 0, margin: 0, display: 'flex', flexDirection: 'column', gap: 12 }}>
        <legend style={{ fontWeight: 800, marginBottom: 10 }}>Votre entreprise</legend>
        <label className="field">
          <span>Numéro SIRET</span>
          <div style={{ display: 'flex', gap: 8 }}>
            <input
              name="siret"
              className="input"
              value={siret}
              onChange={(e) => setSiret(e.target.value)}
              inputMode="numeric"
              placeholder="123 456 789 00012"
              required
              style={{ fontFamily: 'var(--font-mono)' }}
            />
            <button type="button" className="btn btn-outline" onClick={lookup} disabled={busy || siret.replace(/\s/g, '').length !== 14}>
              {busy ? 'Recherche…' : 'Remplir'}
            </button>
          </div>
          {note ? (
            <small role="status" style={{ color: note.ok ? 'var(--green)' : 'var(--brick)', fontWeight: 600 }}>
              {note.ok ? '✓ ' : '● '}
              {note.message} {note.existingId ? <Link href={`/pro/revendiquer/${note.existingId}`}>Revendiquer cette fiche →</Link> : null}
            </small>
          ) : null}
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
        <label className="field">
          <span>Adresse</span>
          <input name="street" className="input" value={street} onChange={(e) => setStreet(e.target.value)} required autoComplete="street-address" />
        </label>
        <div style={{ display: 'grid', gridTemplateColumns: '160px 1fr', gap: 12 }}>
          <label className="field">
            <span>Code postal</span>
            <input
              className="input"
              value={postalCode}
              inputMode="numeric"
              maxLength={5}
              autoComplete="postal-code"
              onChange={(e) => {
                const v = e.target.value.replace(/\D/g, '').slice(0, 5);
                setPostalCode(v);
                if (v.length === 5) loadCommunes(v);
              }}
              onFocus={() => {
                if (postalCode.length === 5 && !choices.length) loadCommunes(postalCode);
              }}
            />
          </label>
          <label className="field">
            <span>Commune</span>
            <select name="inseeCode" className="input" required value={inseeCode} onChange={(e) => setInseeCode(e.target.value)}>
              <option value="" disabled>
                {choices.length ? 'Choisir…' : 'Indiquez d’abord le code postal'}
              </option>
              {choices.map((c) => (
                <option key={c.inseeCode} value={c.inseeCode}>
                  {c.name}
                </option>
              ))}
            </select>
          </label>
        </div>
        {chosen && chosen.coverage.status !== 'free' ? (
          <div className="alert alert-ok" role="status">
            {chosen.coverage.status === 'covered' ? (
              <>
                Bonne nouvelle : {chosen.name} fait partie de {chosen.coverage.territory.name}, partenaire de terricom. Votre fiche y est offerte :{' '}
                <Link href={`/pro/revendiquer?territoire=${chosen.coverage.territory.slug}`}>retrouvez-la</Link> ou{' '}
                <Link href={`/pro/inscription?territoire=${chosen.coverage.territory.slug}`}>référencez votre activité</Link>.
              </>
            ) : (
              <>
                {chosen.coverage.territory.name} rejoint terricom : son portail ouvre bientôt, et votre fiche y sera offerte. Écrivez-nous à{' '}
                <a href="mailto:bonjour@terricom.fr">bonjour@terricom.fr</a> pour être prévenu·e.
              </>
            )}
          </div>
        ) : null}
        <label className="field">
          <span>Téléphone public (facultatif)</span>
          <input name="phone" className="input" inputMode="tel" defaultValue={prefill?.phone} autoComplete="tel" />
        </label>
      </fieldset>

      <fieldset style={{ border: 0, padding: 0, margin: '6px 0 0', display: 'flex', flexDirection: 'column', gap: 12 }}>
        <legend style={{ fontWeight: 800, marginBottom: 10 }}>Votre formule</legend>
        <input type="hidden" name="plan" value={plan} />
        <input type="hidden" name="interval" value={interval} />
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(240px,1fr))', gap: 12 }} role="radiogroup" aria-label="Formule">
          {FORMULAS.map((f) => {
            const on = plan === f.key;
            return (
              <button
                key={f.key}
                type="button"
                role="radio"
                aria-checked={on}
                onClick={() => setPlan(f.key)}
                style={{
                  textAlign: 'left',
                  border: `2px solid ${on ? 'var(--green)' : 'var(--line)'}`,
                  background: on ? 'var(--mint, #E1ECE5)' : 'var(--paper)',
                  borderRadius: 16,
                  padding: 16,
                  cursor: 'pointer',
                  display: 'flex',
                  flexDirection: 'column',
                  gap: 6,
                  font: 'inherit',
                  color: 'inherit',
                }}
              >
                <strong style={{ fontSize: 16 }}>{f.name}</strong>
                <span className="display" style={{ fontSize: 28, letterSpacing: '-0.02em' }}>
                  {interval === 'YEAR' ? prices[f.key].year : prices[f.key].month}
                  <small style={{ fontSize: 13, fontFamily: 'inherit', letterSpacing: 0, opacity: 0.7 }}> HT / {interval === 'YEAR' ? 'an' : 'mois'}</small>
                </span>
                <span style={{ fontSize: 13.5, color: 'var(--muted)' }}>{f.pitch}</span>
                <span style={{ fontSize: 13.5 }}>
                  {f.features.map((x) => (
                    <span key={x} style={{ display: 'block' }}>
                      ✓ {x}
                    </span>
                  ))}
                </span>
              </button>
            );
          })}
        </div>
        <div style={{ display: 'flex', gap: 16, flexWrap: 'wrap' }} role="radiogroup" aria-label="Périodicité">
          {(['MONTH', 'YEAR'] as const).map((iv) => (
            <label key={iv} className="checkbox" style={{ fontSize: 14 }}>
              <input type="radio" name="_interval" checked={interval === iv} onChange={() => setBillingInterval(iv)} />
              <span>{iv === 'MONTH' ? 'Mensuel, sans engagement' : 'Annuel : deux mois offerts'}</span>
            </label>
          ))}
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))', gap: 10 }}>
          <input
            name="billingName"
            className="input"
            placeholder="Raison sociale à facturer"
            aria-label="Raison sociale à facturer"
            required
            defaultValue={name}
            key={name}
          />
          <input
            name="billingEmail"
            type="email"
            className="input"
            placeholder="Email de facturation"
            aria-label="Email de facturation"
            required
            defaultValue={billingEmail}
          />
          <input
            name="billingAddress"
            className="input"
            placeholder="Adresse de facturation"
            aria-label="Adresse de facturation"
            required
            defaultValue={[street, postalCode, chosen?.name].filter(Boolean).join(' ')}
            key={`${street}|${postalCode}|${chosen?.name ?? ''}`}
            style={{ gridColumn: '1 / -1' }}
          />
        </div>
      </fieldset>

      {loggedIn ? null : (
        <fieldset style={{ border: 0, padding: 0, margin: '6px 0 0', display: 'flex', flexDirection: 'column', gap: 12 }}>
          <legend style={{ fontWeight: 800, marginBottom: 10 }}>Votre accès</legend>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
            <input
              name="firstName"
              className="input"
              placeholder="Prénom"
              aria-label="Prénom"
              required
              autoComplete="given-name"
              defaultValue={prefill?.firstName}
            />
            <input name="lastName" className="input" placeholder="Nom" aria-label="Nom" required autoComplete="family-name" defaultValue={prefill?.lastName} />
          </div>
          <input
            name="email"
            type="email"
            className="input"
            placeholder="Email professionnel"
            aria-label="Email professionnel"
            required
            autoComplete="email"
            defaultValue={prefill?.email}
          />
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
      <label style={{ display: 'flex', gap: 8, fontSize: 13, color: 'var(--muted)', alignItems: 'flex-start' }}>
        <input type="checkbox" name="accept" required defaultChecked={Boolean(prefill)} style={{ accentColor: 'var(--green)', marginTop: 2 }} />
        <span>
          J&apos;accepte les <Link href="/cgv">conditions générales de vente</Link>. {interval === 'YEAR' ? 'Engagement d’un an.' : 'Résiliable à tout moment.'}
        </span>
      </label>
      {state.status === 'error' ? (
        <div className="alert alert-error" role="alert">
          {state.message} {state.existingId ? <Link href={`/pro/revendiquer/${state.existingId}`}>Revendiquer la fiche existante →</Link> : null}
          {state.coveredSlug ? <Link href={`/pro/inscription?territoire=${state.coveredSlug}`}>Référencer mon activité gratuitement →</Link> : null}
          {state.needsLogin ? <Link href="/connexion?next=/pro/adhesion">Me connecter →</Link> : null}
        </div>
      ) : null}
      <button
        type="submit"
        className="btn btn-brand"
        disabled={pending || (chosen ? chosen.coverage.status !== 'free' : false)}
        style={{ alignSelf: 'flex-start' }}
      >
        {pending ? 'Un instant…' : `Adhérer · ${price}`}
      </button>
      <small style={{ color: 'var(--muted)' }}>
        Dès que votre commune ou votre intercommunalité adhère à terricom, votre fiche lui est offerte et votre abonnement passe au prix de l&apos;option
        équivalente, sans rien perdre.
      </small>
    </form>
  );
}
