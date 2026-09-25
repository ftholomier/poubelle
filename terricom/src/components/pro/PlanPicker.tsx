'use client';

import { useActionState, useState } from 'react';
import { changePlanAction, type ActionState } from '@/app/pro/[est]/actions';
import { Confetti, useToast } from '@/components/ui/Feedback';

type Plan = { key: 'ESSENTIEL' | 'PREMIUM' | 'COMMUNICATION'; name: string; price: string; unit: string; description: string; features: string[]; popular: boolean };

/** Cartes d'offres (E7) et souscription : l'Essentiel reste gratuit, pour toujours. */
export function PlanPicker({
  estId,
  plans,
  current,
  canChange,
  billing,
}: {
  estId: string;
  plans: Plan[];
  current: string;
  canChange: boolean;
  billing: { name: string; email: string; address: string };
}) {
  const toast = useToast();
  const [choice, setChoice] = useState<Plan | null>(null);
  const [party, setParty] = useState(false);
  const [state, action, pending] = useActionState<ActionState & { upgraded?: boolean; redirectUrl?: string }, FormData>(async (prev, form) => {
    const res = await changePlanAction(prev, form);
    if (res.redirectUrl) window.location.href = res.redirectUrl;
    else if (res.status === 'ok') {
      toast(res.message ?? 'Offre mise à jour');
      setChoice(null);
      if (res.upgraded) {
        setParty(true);
        window.setTimeout(() => setParty(false), 3200);
      }
    }
    return res;
  }, { status: 'idle' });

  return (
    <>
      {party ? (
        <div style={{ position: 'relative', height: 0 }}>
          <Confetti />
        </div>
      ) : null}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(260px,1fr))', gap: 16, alignItems: 'stretch' }}>
        {plans.map((pl) => {
          const cur = pl.key === current;
          const dark = pl.key === 'PREMIUM';
          return (
            <div
              key={pl.key}
              style={{
                background: dark ? 'var(--ink)' : 'var(--paper)',
                color: dark ? 'var(--cream)' : 'var(--text)',
                border: `2px solid ${cur ? 'var(--amber)' : dark ? 'var(--ink)' : 'var(--line)'}`,
                borderRadius: 24,
                padding: 26,
                display: 'flex',
                flexDirection: 'column',
                gap: 14,
                position: 'relative',
              }}
            >
              {pl.popular ? (
                <span
                  style={{
                    position: 'absolute',
                    top: -14,
                    right: 20,
                    background: 'var(--amber)',
                    color: 'var(--ink)',
                    fontWeight: 800,
                    fontSize: 12,
                    padding: '6px 12px',
                    borderRadius: 999,
                    transform: 'rotate(3deg)',
                  }}
                >
                  Le plus choisi
                </span>
              ) : null}
              <div style={{ fontWeight: 800, fontSize: 15 }}>{pl.name}</div>
              <div>
                <span className="display" style={{ fontSize: 48, letterSpacing: '-0.03em' }}>
                  {pl.price}
                </span>
                <span style={{ fontSize: 14, opacity: 0.75 }}>{pl.unit}</span>
              </div>
              <div style={{ fontSize: 14, opacity: 0.8 }}>{pl.description}</div>
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 14, borderTop: `1px solid ${dark ? 'var(--dark-3)' : 'var(--line-2)'}`, paddingTop: 14 }}>
                {pl.features.map((f) => (
                  <div key={f}>✓ {f}</div>
                ))}
              </div>
              <button
                type="button"
                disabled={cur || !canChange}
                onClick={() => setChoice(pl)}
                style={{
                  marginTop: 'auto',
                  border: 0,
                  padding: 13,
                  borderRadius: 12,
                  fontWeight: 800,
                  background: cur ? 'transparent' : dark ? 'var(--amber)' : 'var(--ink)',
                  color: cur ? (dark ? 'var(--amber)' : 'var(--green)') : dark ? 'var(--ink)' : '#fff',
                  opacity: 1,
                  cursor: cur ? 'default' : 'pointer',
                }}
              >
                {cur ? 'Votre offre actuelle' : pl.key === 'ESSENTIEL' ? 'Revenir à l’Essentiel' : 'Choisir'}
              </button>
            </div>
          );
        })}
      </div>

      {choice ? (
        <form action={action} className="panel" style={{ borderRadius: 20, maxWidth: 720, margin: '0 auto', width: '100%' }}>
          <input type="hidden" name="estId" value={estId} />
          <input type="hidden" name="plan" value={choice.key} />
          <h2 className="panel-title" style={{ fontSize: 20 }}>
            {choice.key === 'ESSENTIEL' ? 'Revenir à l’offre Essentiel' : `Passer à l’offre ${choice.name} — ${choice.price}${choice.unit}`}
          </h2>
          {choice.key === 'ESSENTIEL' ? (
            <p style={{ margin: 0, color: 'var(--muted)' }}>
              Votre fiche reste en ligne gratuitement. Les fonctions de l&apos;offre payante (programmation, diffusion newsletter, statistiques avancées…) seront
              désactivées.
            </p>
          ) : (
            <>
              <p style={{ margin: 0, color: 'var(--muted)', fontSize: 14 }}>
                Sans engagement : résiliable à tout moment depuis cette page. Facture mensuelle, paiement par virement ou par carte.
              </p>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))', gap: 10 }}>
                <input name="billingName" className="input" placeholder="Raison sociale" defaultValue={billing.name} required />
                <input name="billingEmail" type="email" className="input" placeholder="Email de facturation" defaultValue={billing.email} required />
                <input name="billingAddress" className="input" placeholder="Adresse de facturation" defaultValue={billing.address} required style={{ gridColumn: '1 / -1' }} />
              </div>
              <label className="checkbox" style={{ fontSize: 13 }}>
                <input type="checkbox" name="accept" required />
                <span>
                  J&apos;accepte les <a href="/cgv" target="_blank">conditions générales de vente</a>.
                </span>
              </label>
            </>
          )}
          {state.status === 'error' ? <div className="alert alert-error">{state.message}</div> : null}
          <div style={{ display: 'flex', gap: 10 }}>
            <button type="submit" className="btn btn-brand" disabled={pending}>
              {pending ? '…' : choice.key === 'ESSENTIEL' ? 'Confirmer' : 'Confirmer mon abonnement'}
            </button>
            <button type="button" className="btn btn-ghost" onClick={() => setChoice(null)}>
              Annuler
            </button>
          </div>
        </form>
      ) : null}
    </>
  );
}
