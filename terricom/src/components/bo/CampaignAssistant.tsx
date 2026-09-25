'use client';

import { useActionState, useState, useTransition } from 'react';
import { createFromPlanAction, planCampaignAction, type CampState, type PlanResult } from '@/app/collectivite/campagnes/actions';
import { Photo } from '@/components/ui/Photo';
import { FAMILIES } from '@/lib/constants';

const idle: CampState = { status: 'idle' };

/** Assistant territorial (C4) : l'agent décrit son idée, l'assistant prépare la campagne complète. */
export function CampaignAssistant({ defaultPrompt, analysing }: { defaultPrompt: string; analysing: string }) {
  const [prompt, setPrompt] = useState(defaultPrompt);
  const [result, setResult] = useState<PlanResult | null>(null);
  const [loading, start] = useTransition();
  const [created, createAction, creating] = useActionState(createFromPlanAction, idle);

  const run = () =>
    start(async () => {
      setResult(null);
      setResult(await planCampaignAction(prompt));
    });

  const plan = result?.ok ? result.plan : null;
  const who = plan && plan.families.length === 1 ? FAMILIES[plan.families[0]].plural : 'établissements';
  const payload = plan && result?.ok ? JSON.stringify({ ...plan, selectedIds: result.picks.map((p) => p.id), prompt }) : '';

  return (
    <section
      style={{ background: 'var(--ink)', color: 'var(--cream)', borderRadius: 24, padding: 26, display: 'flex', flexDirection: 'column', gap: 16 }}
      aria-label="Assistant territorial"
    >
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
        <h2 className="display" style={{ fontSize: 26, letterSpacing: '-0.02em', margin: 0 }}>
          ✦ Assistant territorial
        </h2>
        <span style={{ fontSize: 12, color: 'var(--sage-2)' }}>Décrivez votre idée, l&apos;assistant prépare la campagne complète</span>
      </div>
      <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
        <label htmlFor="ai-prompt" className="sr-only">
          Votre idée de campagne
        </label>
        <textarea
          id="ai-prompt"
          value={prompt}
          onChange={(e) => setPrompt(e.target.value)}
          rows={2}
          maxLength={600}
          style={{
            flex: 1,
            minWidth: 280,
            border: '1.5px solid var(--dark-4)',
            background: 'var(--dark-5)',
            color: 'var(--cream)',
            borderRadius: 14,
            padding: 14,
            fontSize: 16,
            resize: 'vertical',
          }}
        />
        <button
          type="button"
          onClick={run}
          disabled={loading}
          style={{
            border: 0,
            background: 'var(--amber)',
            color: 'var(--ink)',
            padding: '0 22px',
            minHeight: 54,
            borderRadius: 14,
            fontWeight: 800,
            cursor: 'pointer',
            fontSize: 15,
          }}
        >
          {loading ? 'Préparation…' : plan ? 'Régénérer' : '✦ Préparer'}
        </button>
      </div>
      {loading ? (
        <div role="status" style={{ display: 'flex', gap: 10, alignItems: 'center', color: 'var(--sage)', fontSize: 14 }}>
          <span
            aria-hidden="true"
            style={{
              width: 18,
              height: 18,
              borderRadius: '50%',
              border: '3px solid var(--dark-4)',
              borderTopColor: 'var(--amber)',
              animation: 'spin 1s linear infinite',
            }}
          />
          {analysing}
        </div>
      ) : null}
      {result && !result.ok ? (
        <div role="alert" style={{ color: 'var(--rose)', fontSize: 14 }}>
          {result.message}
        </div>
      ) : null}
      {plan && result?.ok ? (
        <>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))', gap: 14 }}>
            <div style={{ background: 'var(--dark-2)', borderRadius: 18, padding: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
              <div style={{ fontSize: 11, fontWeight: 800, letterSpacing: '0.08em', color: 'var(--amber)' }}>
                SÉLECTION · {result.picks.length} {who.toUpperCase()}
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(6,1fr)', gap: 6 }}>
                {result.picks.slice(0, 12).map((p) => (
                  <span key={p.id} title={`${p.name} · ${p.commune}`} style={{ aspectRatio: '1', borderRadius: 10, overflow: 'hidden', display: 'block' }}>
                    <Photo src={p.image} alt={p.name} label={p.name} />
                  </span>
                ))}
              </div>
              <div style={{ fontSize: 13, color: 'var(--sage)' }}>{plan.criteriaText}</div>
            </div>
            <div style={{ background: 'var(--dark-2)', borderRadius: 18, padding: 18, display: 'flex', flexDirection: 'column', gap: 8 }}>
              <div style={{ fontSize: 11, fontWeight: 800, letterSpacing: '0.08em', color: 'var(--amber)' }}>PAGE THÉMATIQUE</div>
              <div className="display" style={{ fontSize: 24, lineHeight: 1.05 }}>
                {plan.pageTitle}
              </div>
              <div style={{ fontSize: 13, color: 'var(--sage-5)', lineHeight: 1.5 }}>{plan.pageText}</div>
            </div>
            <div style={{ background: 'var(--dark-2)', borderRadius: 18, padding: 18, display: 'flex', flexDirection: 'column', gap: 8 }}>
              <div style={{ fontSize: 11, fontWeight: 800, letterSpacing: '0.08em', color: 'var(--amber)' }}>PLAN DE DIFFUSION</div>
              {plan.plan.map((p, i) => (
                <div
                  key={i}
                  style={{ display: 'grid', gridTemplateColumns: '62px 1fr', gap: 10, fontSize: 13, padding: '5px 0', borderTop: '1px solid var(--dark-3)' }}
                >
                  <b style={{ color: 'var(--amber)' }}>{p.date}</b>
                  <span>{p.text}</span>
                </div>
              ))}
            </div>
          </div>
          <form action={createAction} style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center' }}>
            <input type="hidden" name="plan" value={payload} />
            <button
              type="submit"
              name="invite"
              value="0"
              disabled={creating}
              style={{ border: 0, background: 'var(--amber)', color: 'var(--ink)', padding: '12px 18px', borderRadius: 12, fontWeight: 800, cursor: 'pointer' }}
            >
              Créer la campagne
            </button>
            <button
              type="submit"
              name="invite"
              value="adjust"
              disabled={creating}
              style={{
                border: '1.5px solid var(--dark-4)',
                background: 'transparent',
                color: 'var(--cream)',
                padding: '12px 18px',
                borderRadius: 12,
                fontWeight: 700,
                cursor: 'pointer',
              }}
            >
              Ajuster la sélection
            </button>
            <button
              type="submit"
              name="invite"
              value="1"
              disabled={creating || !result.picks.length}
              style={{
                border: '1.5px solid var(--dark-4)',
                background: 'transparent',
                color: 'var(--cream)',
                padding: '12px 18px',
                borderRadius: 12,
                fontWeight: 700,
                cursor: 'pointer',
              }}
            >
              Inviter les {result.picks.length} {who} à participer
            </button>
            <span style={{ fontSize: 12, color: 'var(--sage-3)', marginLeft: 'auto' }}>
              {plan.source === 'ai' ? 'Proposition de l’IA · à relire' : 'Proposition automatique · à relire'}
            </span>
            {created.status === 'error' ? (
              <span role="alert" style={{ color: 'var(--rose)', fontSize: 13, width: '100%' }}>
                {created.message}
              </span>
            ) : null}
          </form>
        </>
      ) : null}
    </section>
  );
}
