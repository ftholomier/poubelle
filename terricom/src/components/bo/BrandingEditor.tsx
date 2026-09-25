'use client';

import { useActionState, useState, type ReactNode } from 'react';
import { saveBrandingAction, type PersoState } from '@/app/collectivite/personnalisation/actions';
import { FileDrop } from '@/components/ui/FileDrop';
import { Photo } from '@/components/ui/Photo';

const PALETTES: [string, string][] = [
  ['#1F6B52', '#F4B266'],
  ['#2C4A7A', '#F2C94C'],
  ['#7A2E26', '#F6C9C1'],
  ['#3B3A36', '#C9E26B'],
];

type Block = { key: string; label: string };

const FORM = 'branding-form';

/** « | » marque un retour à la ligne dans le titre d'accueil (comme sur le portail). */
function withBreaks(text: string) {
  return text.split('|').map((part, i) => (
    <span key={i}>
      {i > 0 ? <br /> : null}
      {part}
    </span>
  ));
}

/** Identité visuelle du portail avec aperçu en direct (C8). */
export function BrandingEditor({
  initial,
  allBlocks,
  host,
  children,
}: {
  initial: { colorPrimary: string; colorAccent: string; initials: string; name: string; tagline: string; heroTitle: string; heroSubtitle: string; heroImageUrl: string | null; logoUrl: string | null; blocks: string[] };
  allBlocks: Block[];
  host: string;
  /** Contenu affiché sous l'aperçu (équipe & rôles), hors du formulaire. */
  children?: ReactNode;
}) {
  const [state, action, pending] = useActionState(saveBrandingAction, { status: 'idle' } as PersoState);
  const [main, setMain] = useState(initial.colorPrimary);
  const [acc, setAcc] = useState(initial.colorAccent);
  const [initials, setInitials] = useState(initial.initials);
  const [heroTitle, setHeroTitle] = useState(initial.heroTitle);
  const [order, setOrder] = useState<string[]>([...initial.blocks, ...allBlocks.map((b) => b.key).filter((k) => !initial.blocks.includes(k))]);
  const [enabled, setEnabled] = useState<string[]>(initial.blocks);
  const title = heroTitle || `${initial.name}, fait main & fait ici.`;
  const [before, after] = title.includes('&') ? [title.slice(0, title.indexOf('&')), title.slice(title.indexOf('&') + 1)] : [title, null];
  const move = (i: number) => setOrder((o) => (i <= 0 ? o : [...o.slice(0, i - 1), o[i], o[i - 1], ...o.slice(i + 1)]));

  return (
    <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1.2fr)', ['--gap' as string]: '20px', ['--align' as string]: 'start' }}>
      {/* Les champs sont reliés au formulaire par l'attribut form : l'équipe garde ses propres formulaires. */}
      <form id={FORM} action={action} hidden />
      <input type="hidden" form={FORM} name="colorPrimary" value={main} />
      <input type="hidden" form={FORM} name="colorAccent" value={acc} />
      <input type="hidden" form={FORM} name="blocks" value={JSON.stringify(order.filter((k) => enabled.includes(k)))} />
      <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
        <section className="bo-card" style={{ padding: 20, borderRadius: 20, display: 'flex', flexDirection: 'column', gap: 14 }}>
          <b>Identité visuelle du portail</b>
          <div style={{ display: 'flex', gap: 14, alignItems: 'center' }}>
            {initial.logoUrl ? (
              <span style={{ width: 64, height: 64, borderRadius: 16, overflow: 'hidden', transform: 'rotate(-4deg)' }}>
                <Photo src={initial.logoUrl} alt="" label={initials} color={main} />
              </span>
            ) : (
              <div className="display" style={{ width: 64, height: 64, borderRadius: 16, background: main, color: acc, display: 'grid', placeItems: 'center', fontSize: 24, transform: 'rotate(-4deg)' }}>
                {initials}
              </div>
            )}
            <div style={{ fontSize: 13, color: 'var(--muted)', display: 'flex', flexDirection: 'column', gap: 4 }}>
              Logo · SVG ou PNG
              <label style={{ color: 'var(--green)', fontWeight: 700, cursor: 'pointer' }}>
                Remplacer
                <input type="file" form={FORM} name="logo" accept="image/png,image/svg+xml,image/webp,image/jpeg" className="sr-only" />
              </label>
              <label style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                Initiales
                <input form={FORM} name="initials" value={initials} onChange={(e) => setInitials(e.target.value.toUpperCase().slice(0, 4))} className="input" style={{ width: 70, padding: '4px 8px' }} />
              </label>
            </div>
          </div>
          <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--muted)' }}>Palette</div>
          <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center' }}>
            {PALETTES.map(([a, b]) => {
              const on = a.toLowerCase() === main.toLowerCase() && b.toLowerCase() === acc.toLowerCase();
              return (
                <button
                  key={a}
                  type="button"
                  onClick={() => {
                    setMain(a);
                    setAcc(b);
                  }}
                  aria-pressed={on}
                  aria-label={`Palette ${a} et ${b}`}
                  style={{ display: 'flex', borderRadius: 12, overflow: 'hidden', border: `3px solid ${on ? 'var(--ink)' : 'transparent'}`, padding: 0, cursor: 'pointer' }}
                >
                  <span style={{ width: 34, height: 40, background: a }} />
                  <span style={{ width: 34, height: 40, background: b }} />
                </button>
              );
            })}
            <label style={{ display: 'flex', gap: 4, alignItems: 'center', fontSize: 12, color: 'var(--muted)' }}>
              <input type="color" value={main} onChange={(e) => setMain(e.target.value)} aria-label="Couleur principale" style={{ width: 34, height: 34, border: 0, padding: 0 }} />
              <input type="color" value={acc} onChange={(e) => setAcc(e.target.value)} aria-label="Couleur d’accent" style={{ width: 34, height: 34, border: 0, padding: 0 }} />
              sur mesure
            </label>
          </div>
          <label className="field">
            <span>Accroche du portail</span>
            <input form={FORM} name="tagline" className="input" defaultValue={initial.tagline} maxLength={160} />
          </label>
          <label className="field">
            <span>Titre de la page d’accueil (« | » : retour à la ligne, « & » : couleur d’accent)</span>
            <input form={FORM} name="heroTitle" className="input" value={heroTitle} onChange={(e) => setHeroTitle(e.target.value)} maxLength={200} placeholder={`${initial.name}, fait main & fait ici.`} />
          </label>
          <label className="field">
            <span>Sous-titre</span>
            <input form={FORM} name="heroSubtitle" className="input" defaultValue={initial.heroSubtitle} maxLength={400} />
          </label>
          <div className="field">
            <span>Photo d’accueil</span>
            <FileDrop form={FORM} name="hero" accept="image/jpeg,image/png,image/webp" label="Remplacer la photo (JPEG, PNG ou WebP)" />
          </div>
        </section>
        <section className="bo-card" style={{ padding: 20, borderRadius: 20, display: 'flex', flexDirection: 'column', gap: 8 }}>
          <b>Blocs de la page d’accueil</b>
          {order.map((key, i) => {
            const b = allBlocks.find((x) => x.key === key);
            if (!b) return null;
            const on = enabled.includes(key);
            return (
              <div key={key} style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', padding: '8px 0', borderTop: '1px solid var(--line-2)', fontSize: 14, gap: 10 }}>
                <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <button type="button" onClick={() => move(i)} aria-label={`Monter « ${b.label} »`} disabled={i === 0} style={{ border: 0, background: 'none', cursor: i === 0 ? 'default' : 'pointer', color: 'var(--muted)', padding: 0 }}>
                    ⋮⋮
                  </button>
                  {b.label}
                </span>
                <button
                  type="button"
                  role="switch"
                  aria-checked={on}
                  aria-label={b.label}
                  onClick={() => setEnabled((e) => (on ? e.filter((x) => x !== key) : [...e, key]))}
                  style={{ cursor: 'pointer', width: 40, height: 22, borderRadius: 11, background: on ? 'var(--green)' : '#D8D2C4', position: 'relative', border: 0, padding: 0 }}
                >
                  <span style={{ position: 'absolute', top: 2, left: on ? 20 : 2, width: 18, height: 18, borderRadius: '50%', background: '#fff', transition: 'left .15s' }} />
                </button>
              </div>
            );
          })}
        </section>
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
        <div style={{ borderRadius: 20, overflow: 'hidden', border: '1px solid var(--line)', background: '#fff' }}>
          <div style={{ display: 'flex', gap: 6, padding: '10px 14px', background: 'var(--sand)', alignItems: 'center' }}>
            <span style={{ width: 10, height: 10, borderRadius: '50%', background: 'var(--danger)' }} />
            <span style={{ width: 10, height: 10, borderRadius: '50%', background: 'var(--amber)' }} />
            <span style={{ width: 10, height: 10, borderRadius: '50%', background: 'var(--pulse)' }} />
            <span style={{ marginLeft: 10, fontSize: 12, color: 'var(--muted)', background: '#fff', padding: '4px 10px', borderRadius: 6 }}>{host}</span>
          </div>
          <div style={{ position: 'relative', height: 260 }}>
            <Photo src={initial.heroImageUrl} alt="" label=" " color={main} style={{ position: 'absolute', inset: 0 }} />
            <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,transparent,rgba(20,32,27,.8))' }} />
            <div style={{ position: 'absolute', left: 0, right: 0, top: 0, display: 'flex', gap: 10, alignItems: 'center', padding: '12px 16px', background: main }}>
              <div style={{ width: 26, height: 26, borderRadius: 8, background: acc, color: main, display: 'grid', placeItems: 'center', fontSize: 11, fontWeight: 800 }}>{initials}</div>
              <b style={{ color: '#fff', fontSize: 13 }}>{initial.name}</b>
            </div>
            <div className="display" style={{ position: 'absolute', left: 18, bottom: 18, right: 18, color: '#fff', fontSize: 30, lineHeight: 0.95 }}>
              {withBreaks(before)}
              {after !== null ? (
                <>
                  <span style={{ color: acc }}>&amp;</span>
                  {withBreaks(after)}
                </>
              ) : null}
            </div>
          </div>
          <div style={{ padding: '12px 16px', fontSize: 12, color: 'var(--muted)', display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 10, flexWrap: 'wrap' }}>
            <span>Aperçu en direct · publié après validation</span>
            <button type="submit" form={FORM} disabled={pending} className="btn btn-brand btn-sm">
              {pending ? 'Publication…' : 'Valider et publier'}
            </button>
          </div>
          {state.status !== 'idle' ? (
            <div role="status" style={{ padding: '0 16px 12px', fontSize: 13, fontWeight: 600, color: state.status === 'error' ? 'var(--danger-fg)' : 'var(--green)' }}>
              {state.message}
            </div>
          ) : null}
        </div>
        {children}
      </div>
    </div>
  );
}
