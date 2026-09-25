import type { CSSProperties, ReactNode } from 'react';

/** Mini-histogramme des tuiles KPI (la dernière barre en vert Loue). */
export function MiniBars({ values, height = 34 }: { values: number[]; height?: number }) {
  const max = Math.max(1, ...values);
  return (
    <div style={{ display: 'flex', alignItems: 'flex-end', gap: 3, height }} aria-hidden="true">
      {values.map((v, i) => (
        <div
          key={i}
          style={{
            width: 6,
            borderRadius: 2,
            height: `${Math.max(6, (v / max) * 100)}%`,
            background: i === values.length - 1 ? 'var(--green)' : 'var(--mint-4)',
          }}
        />
      ))}
    </div>
  );
}

/** Courbe « ce mois / mois précédent » avec point de pic (statistiques entreprise). */
export function LineChart({
  current,
  previous,
  peakIndex,
  height = 220,
}: {
  current: number[];
  previous?: number[];
  peakIndex?: number | null;
  height?: number;
}) {
  const mx = Math.max(10, ...current, ...(previous ?? [])) * 1.1;
  const n = Math.max(2, current.length);
  const pts = (a: number[]) =>
    a.map((v, i) => `${((i / (n - 1)) * 600).toFixed(1)},${(195 - (v / mx) * 180).toFixed(1)}`).join(' ');
  const area = `M0,200 L${pts(current).split(' ').join(' L')} L600,200 Z`;
  const pk = peakIndex ?? current.indexOf(Math.max(...current));
  return (
    <svg viewBox="0 0 600 200" preserveAspectRatio="none" style={{ width: '100%', height, display: 'block' }} role="img" aria-label="Évolution des vues">
      <path d={area} fill="#E1ECE5" />
      <polyline points={pts(current)} fill="none" stroke="#1F6B52" strokeWidth={3} strokeLinejoin="round" vectorEffect="non-scaling-stroke" />
      {previous ? (
        <polyline points={pts(previous)} fill="none" stroke="#C9C2B2" strokeWidth={2} strokeDasharray="4 5" vectorEffect="non-scaling-stroke" />
      ) : null}
      {pk >= 0 && current.length ? (
        <circle cx={(pk / (n - 1)) * 600} cy={195 - (current[pk] / mx) * 180} r={6} fill="#F4B266" stroke="#14201B" strokeWidth={2} vectorEffect="non-scaling-stroke" />
      ) : null}
    </svg>
  );
}

/** Histogramme mensuel (visiteurs du portail). */
export function MonthBars({
  data,
  height = 220,
  highlightLast = true,
}: {
  data: { label: string; value: number; display?: string; muted?: boolean }[];
  height?: number;
  highlightLast?: boolean;
}) {
  const max = Math.max(1, ...data.map((d) => d.value));
  return (
    <div style={{ display: 'grid', gridTemplateColumns: `repeat(${data.length}, 1fr)`, gap: 8, alignItems: 'end', height }}>
      {data.map((d, i) => (
        <div key={i} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6, height: '100%', justifyContent: 'flex-end' }}>
          <span style={{ fontSize: 10, fontWeight: 700, color: 'var(--muted)' }}>{d.display ?? ''}</span>
          <div
            style={{
              width: '100%',
              height: `${Math.max(2, (d.value / max) * 100)}%`,
              background: d.muted ? 'var(--line)' : highlightLast && i === data.length - 1 ? 'var(--amber)' : 'var(--green)',
              borderRadius: '8px 8px 3px 3px',
            }}
            title={`${d.label} : ${d.display ?? d.value}`}
          />
          <span style={{ fontSize: 11, color: 'var(--muted)' }}>{d.label}</span>
        </div>
      ))}
    </div>
  );
}

/** Carte de chaleur jour × heure. */
export function Heatmap({ rows, hours }: { rows: { day: string; cells: number[] }[]; hours: string[] }) {
  const max = Math.max(1, ...rows.flatMap((r) => r.cells));
  return (
    <div style={{ display: 'grid', gridTemplateColumns: `40px repeat(${hours.length}, 1fr)`, gap: 4, fontSize: 11, color: 'var(--muted)' }}>
      <span />
      {hours.map((h) => (
        <span key={h} style={{ textAlign: 'center' }}>
          {h}
        </span>
      ))}
      {rows.map((r) => [
        <span key={`${r.day}-l`} style={{ fontWeight: 700, alignSelf: 'center' }}>
          {r.day}
        </span>,
        ...r.cells.map((v, c) => (
          <div
            key={`${r.day}-${c}`}
            title={`${r.day} ${hours[c]} : ${v}`}
            style={{ height: 26, borderRadius: 5, background: `rgba(31,107,82,${(0.08 + (v / max) * 0.92).toFixed(2)})` }}
          />
        )),
      ])}
    </div>
  );
}

/** Anneau de complétude (conic-gradient), comme sur le tableau de bord pro. */
export function Ring({
  value,
  size = 128,
  thickness = 14,
  color = 'var(--amber)',
  track = 'rgba(255,255,255,.18)',
  inner = 'var(--green)',
  children,
}: {
  value: number;
  size?: number;
  thickness?: number;
  color?: string;
  track?: string;
  inner?: string;
  children?: ReactNode;
}) {
  return (
    <div
      style={{
        width: size,
        height: size,
        borderRadius: '50%',
        background: `conic-gradient(${color} ${Math.round(value * 3.6)}deg, ${track} 0)`,
        display: 'grid',
        placeItems: 'center',
        flexShrink: 0,
      }}
      role="img"
      aria-label={`${value} %`}
    >
      <div
        style={{
          width: size - thickness * 2,
          height: size - thickness * 2,
          borderRadius: '50%',
          background: inner,
          display: 'grid',
          placeItems: 'center',
          textAlign: 'center',
        }}
      >
        {children}
      </div>
    </div>
  );
}

/** Barre horizontale étiquetée (sources de trafic, podium). */
export function LabeledBar({ label, value, suffix = '%', color = 'var(--green)', max = 100, right }: { label: ReactNode; value: number; suffix?: string; color?: string; max?: number; right?: ReactNode }) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 5 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, gap: 8 }}>
        <span style={{ fontWeight: 600 }}>{label}</span>
        {right ?? (
          <b>
            {value}
            {suffix}
          </b>
        )}
      </div>
      <div className="bar" style={{ height: 9, borderRadius: 5 }}>
        <span style={{ width: `${Math.min(100, (value / max) * 100)}%`, background: color, borderRadius: 5 }} />
      </div>
    </div>
  );
}

export function KpiTile({
  label,
  value,
  delta,
  color,
  children,
  style,
}: {
  label: string;
  value: ReactNode;
  delta?: ReactNode;
  color?: string;
  children?: ReactNode;
  style?: CSSProperties;
}) {
  return (
    <div className="card" style={{ borderRadius: 16, padding: 16, borderTop: color ? `5px solid ${color}` : undefined, ...style }}>
      <div style={{ fontSize: 13, color: 'var(--muted)', fontWeight: 600 }}>{label}</div>
      <div style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 32, letterSpacing: '-0.03em', lineHeight: 1.15 }}>{value}</div>
      {delta ? <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--green)' }}>{delta}</div> : null}
      {children}
    </div>
  );
}
