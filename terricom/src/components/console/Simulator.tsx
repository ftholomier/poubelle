'use client';

import { useState } from 'react';

type Values = { ent: number; lic: number; conv: number; price: number };
type PresetKey = 'prudent' | 'cible' | 'mature' | 'reel';

const PRESETS: Record<Exclude<PresetKey, 'reel'>, Values & { label: string }> = {
  prudent: { label: 'Pilote prudent', ent: 400, lic: 6000, conv: 5, price: 180 },
  cible: { label: 'Territoire cible', ent: 800, lic: 9000, conv: 10, price: 240 },
  mature: { label: 'Territoire mature', ent: 1500, lic: 15000, conv: 15, price: 300 },
};

const eur = (v: number) => `${Math.round(v).toLocaleString('fr-FR')} €`;
const compact = (v: number) => (v >= 1e6 ? `${(v / 1e6).toFixed(1).replace('.', ',')} M€` : `${Math.round(v / 1000).toLocaleString('fr-FR')} k€`);

/**
 * Simulateur économique (S4) : revenu récurrent d'un territoire selon le nombre d'entreprises,
 * la licence, la conversion Premium et son prix, puis effet de mutualisation sur N territoires.
 */
export function Simulator({ real }: { real: (Values & { territories: number }) | null }) {
  const [v, setV] = useState<Values>({ ent: PRESETS.cible.ent, lic: PRESETS.cible.lic, conv: PRESETS.cible.conv, price: PRESETS.cible.price });
  const [nterr, setNterr] = useState(10);
  const [preset, setPreset] = useState<PresetKey | ''>('cible');

  const nPrem = Math.round((v.ent * v.conv) / 100);
  const prem = nPrem * v.price;
  const total = v.lic + prem;
  const scale = [1, 3, nterr, 25, 50].map((n, i) => ({ n, v: n * total, h: Math.max(3, (n / 50) * 100), c: i === 2 ? 'var(--amber)' : 'var(--green)' }));

  const presets: { key: PresetKey; label: string; values: Values }[] = [
    ...(Object.keys(PRESETS) as Exclude<PresetKey, 'reel'>[]).map((k) => ({ key: k as PresetKey, label: PRESETS[k].label, values: PRESETS[k] })),
    ...(real ? [{ key: 'reel' as const, label: 'Moyenne réelle', values: real }] : []),
  ];

  const sliders: { key: keyof Values | 'nterr'; label: string; min: number; max: number; step: number; value: number; disp: string }[] = [
    { key: 'ent', label: 'Entreprises référencées', min: 100, max: 3000, step: 50, value: v.ent, disp: v.ent.toLocaleString('fr-FR') },
    { key: 'lic', label: 'Licence territoire (€/an)', min: 1200, max: 20000, step: 100, value: v.lic, disp: eur(v.lic) },
    { key: 'conv', label: 'Conversion Premium (%)', min: 1, max: 30, step: 1, value: v.conv, disp: `${v.conv} %` },
    { key: 'price', label: 'Prix Premium (€/an)', min: 120, max: 720, step: 12, value: v.price, disp: eur(v.price) },
    { key: 'nterr', label: 'Territoires (projection)', min: 1, max: 50, step: 1, value: nterr, disp: String(nterr) },
  ];

  return (
    <div className="split" style={{ ['--cols' as string]: 'minmax(300px,420px) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
      <div className="console-card" style={{ padding: 22, display: 'flex', flexDirection: 'column', gap: 18 }}>
        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }} role="group" aria-label="Hypothèses types">
          {presets.map((p) => (
            <button
              key={p.key}
              type="button"
              aria-pressed={preset === p.key}
              onClick={() => {
                setV({ ent: p.values.ent, lic: p.values.lic, conv: p.values.conv, price: p.values.price });
                setPreset(p.key);
              }}
              className="sim-preset"
            >
              {p.label}
            </button>
          ))}
        </div>
        {sliders.map((s) => (
          <label key={s.key} style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 14 }}>
              <span style={{ fontWeight: 600 }}>{s.label}</span>
              <b>{s.disp}</b>
            </div>
            <input
              type="range"
              className="sim-range"
              min={s.min}
              max={s.max}
              step={s.step}
              value={s.value}
              onChange={(e) => {
                const n = Number(e.target.value);
                if (s.key === 'nterr') setNterr(n);
                else {
                  setV((x) => ({ ...x, [s.key]: n }));
                  setPreset('');
                }
              }}
            />
          </label>
        ))}
        {real ? (
          <p style={{ margin: 0, fontSize: 12, color: 'var(--muted)' }}>
            Moyenne réelle : {real.territories} territoires actifs, {real.ent.toLocaleString('fr-FR')} fiches, conversion {real.conv} %, Premium{' '}
            {eur(real.price)}/an.
          </p>
        ) : null}
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
        <div
          style={{
            background: 'var(--ink)',
            color: 'var(--cream)',
            borderRadius: 24,
            padding: 28,
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))',
            gap: 20,
            alignItems: 'end',
          }}
          aria-live="polite"
        >
          <div>
            <div style={{ fontSize: 13, color: '#8FA197', fontWeight: 700 }}>CA récurrent annuel · par territoire</div>
            <div className="display" style={{ fontSize: 64, letterSpacing: '-0.04em', lineHeight: 1, color: 'var(--amber)' }}>
              {eur(total)}
            </div>
          </div>
          <div>
            <div style={{ fontSize: 13, color: '#8FA197' }}>Licence</div>
            <div className="display" style={{ fontSize: 28 }}>
              {eur(v.lic)}
            </div>
          </div>
          <div>
            <div style={{ fontSize: 13, color: '#8FA197' }}>Premium ({nPrem.toLocaleString('fr-FR')} entreprises)</div>
            <div className="display" style={{ fontSize: 28 }}>
              {eur(prem)}
            </div>
          </div>
        </div>
        <div className="console-card" style={{ padding: 22 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 14, gap: 10, flexWrap: 'wrap' }}>
            <b>Effet de mutualisation</b>
            <span style={{ fontSize: 13, color: 'var(--muted)' }}>même infrastructure, territoires multipliés</span>
          </div>
          <div
            style={{ display: 'grid', gridTemplateColumns: 'repeat(5,1fr)', gap: 12, alignItems: 'end', height: 240 }}
            role="img"
            aria-label="Revenu annuel selon le nombre de territoires"
          >
            {scale.map((b, i) => (
              <div key={i} style={{ display: 'flex', flexDirection: 'column', gap: 6, justifyContent: 'flex-end', height: '100%', alignItems: 'center' }}>
                <b style={{ fontSize: 13 }}>{compact(b.v)}</b>
                <div style={{ width: '100%', height: `${b.h}%`, background: b.c, borderRadius: '10px 10px 4px 4px', transition: 'height .2s' }} />
                <span style={{ fontSize: 12, color: 'var(--muted)' }}>
                  {b.n} territoire{b.n > 1 ? 's' : ''}
                </span>
              </div>
            ))}
          </div>
        </div>
        <div style={{ fontSize: 12, color: 'var(--muted)' }}>
          Hypothèses de travail issues du cahier des charges, hors frais de mise en service (2 000 à 8 000 € HT), à valider avec le pilote.
        </div>
      </div>
    </div>
  );
}
