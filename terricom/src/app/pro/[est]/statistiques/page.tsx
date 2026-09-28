import Link from 'next/link';
import type { ReactNode } from 'react';
import { Heatmap, LineChart } from '@/components/ui/Charts';
import { fmtDayMonth, fmtInt, WEEKDAYS_LONG } from '@/lib/format';
import { dailyViews, deltaLabel, establishmentKpis, loadProContext, searchQueries, viewHeatmap, viewSources } from '@/server/services/pro';

type Props = { params: Promise<{ est: string }>; searchParams: Promise<Record<string, string | undefined>> };

const PERIODS: [number, string][] = [
  [30, '30 jours'],
  [90, '3 mois'],
  [365, '12 mois'],
];
const HOURS = ['8h', '9h', '10h', '11h', '12h', '13h', '14h', '15h', '16h', '17h', '18h', '19h'];
const DAYS = ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'];

/** Bloc réservé à l'offre Premium : aperçu flouté et invitation. */
function Premium({ allowed, base, children }: { allowed: boolean; base: string; children: ReactNode }) {
  if (allowed) return <>{children}</>;
  return (
    <div style={{ position: 'relative' }}>
      <div aria-hidden="true" style={{ filter: 'blur(5px)', pointerEvents: 'none', userSelect: 'none', opacity: 0.7 }}>
        {children}
      </div>
      <div style={{ position: 'absolute', inset: 0, display: 'grid', placeItems: 'center' }}>
        <div className="card" style={{ borderRadius: 16, padding: '16px 18px', textAlign: 'center', boxShadow: 'var(--shadow-card)', maxWidth: 300 }}>
          <b>Statistiques avancées</b>
          <p style={{ margin: '6px 0 10px', fontSize: 13, color: 'var(--muted)' }}>
            Heures de visite, recherches qui mènent à vous : inclus dans l&apos;offre Premium.
          </p>
          <Link href={`${base}/offre`} className="btn btn-dark btn-sm">
            Découvrir Premium
          </Link>
        </div>
      </div>
    </div>
  );
}

export default async function StatsPage({ params, searchParams }: Props) {
  const { est: estId } = await params;
  const sp = await searchParams;
  const ctx = await loadProContext(estId);
  const { est, base, limits } = ctx;
  const requested = Number(sp.periode) || 30;
  const days = limits.advancedStats && [30, 90, 365].includes(requested) ? requested : 30;
  const [k, series, sources, heat, queries] = await Promise.all([
    establishmentKpis(est.id, days),
    dailyViews(est.id, days),
    viewSources(est.id, days),
    viewHeatmap(est.id, days),
    searchQueries(est.id, days, 6),
  ]);
  // Au-delà de 90 jours, regroupement par semaine pour une courbe lisible.
  const bucket = days > 90 ? 7 : 1;
  const group = (arr: number[]) =>
    bucket === 1 ? arr : arr.reduce<number[]>((acc, v, i) => (i % bucket ? ((acc[acc.length - 1] += v), acc) : [...acc, v]), []);
  const cur = group(series.map((s) => s.cur));
  const prev = group(series.map((s) => s.prev));
  const peak = cur.indexOf(Math.max(...cur));
  const peakDate = series[Math.min(series.length - 1, peak * bucket)]?.date;
  const kpis = [
    { label: 'Vues', v: k.views, color: 'var(--green)' },
    { label: 'Appels', v: k.calls, color: 'var(--f-artisan)' },
    { label: 'Itinéraires', v: k.directions, color: 'var(--f-commerce)' },
    { label: 'Messages', v: k.messages, color: 'var(--f-services)', abs: true },
    { label: 'Scans QR', v: k.qr, color: 'var(--danger)' },
  ];
  const best = heat.best.n ? `Le ${WEEKDAYS_LONG[heat.best.wd]} à ${heat.best.h}h, c'est votre heure de gloire` : null;

  return (
    <div className="app-content" style={{ gap: 18 }}>
      <div style={{ display: 'flex', gap: 6, alignItems: 'center', flexWrap: 'wrap' }}>
        {PERIODS.map(([d, l]) => {
          const on = d === days;
          const locked = !limits.advancedStats && d !== 30;
          return (
            <Link
              key={d}
              href={locked ? `${base}/offre` : `${base}/statistiques?periode=${d}`}
              aria-current={on ? 'page' : undefined}
              title={locked ? 'Offre Premium' : undefined}
              style={{
                background: on ? 'var(--ink)' : 'transparent',
                color: on ? '#fff' : locked ? 'var(--faint)' : 'var(--text)',
                border: on ? 0 : '1px solid var(--line)',
                padding: '7px 13px',
                borderRadius: 999,
                fontWeight: on ? 700 : 600,
                fontSize: 13,
              }}
            >
              {l}
              {locked ? ' 🔒' : ''}
            </Link>
          );
        })}
        <a href={`/api/pro/${est.id}/stats.csv?periode=${days}`} className="btn btn-outline btn-sm" style={{ marginLeft: 'auto' }}>
          Exporter (CSV)
        </a>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(170px,1fr))', gap: 12 }}>
        {kpis.map((x) => (
          <div key={x.label} className="card" style={{ borderRadius: 16, padding: 16, borderTop: `5px solid ${x.color}` }}>
            <div style={{ fontSize: 13, color: 'var(--muted)', fontWeight: 600 }}>{x.label}</div>
            <div className="display" style={{ fontSize: 32, letterSpacing: '-0.03em' }}>
              {fmtInt(x.v.cur)}
            </div>
            <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--green)' }}>
              {x.abs ? (x.v.cur - x.v.prev >= 0 ? `+${x.v.cur - x.v.prev}` : String(x.v.cur - x.v.prev)) : deltaLabel(x.v.cur, x.v.prev)}
            </div>
          </div>
        ))}
      </div>

      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.6fr) minmax(0,1fr)', ['--gap' as string]: '18px' }}>
        <div className="card" style={{ borderRadius: 20, padding: 20 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10, marginBottom: 12, flexWrap: 'wrap' }}>
            <b>Vues de la fiche</b>
            {peakDate && cur[peak] > 0 ? <span style={{ fontSize: 12, color: 'var(--muted)' }}>pic le {fmtDayMonth(peakDate)}</span> : null}
          </div>
          <LineChart current={cur} previous={prev} peakIndex={peak} />
          <div style={{ display: 'flex', gap: 16, fontSize: 12, color: 'var(--muted)', marginTop: 8 }}>
            <span>
              <b style={{ color: 'var(--green)' }}>—</b> {days === 30 ? 'ce mois' : 'cette période'}
            </span>
            <span>
              <b style={{ color: 'var(--sand-3)' }}>- -</b> {days === 30 ? 'mois précédent' : 'période précédente'}
            </span>
          </div>
        </div>
        <div className="card" style={{ borderRadius: 20, padding: 20, display: 'flex', flexDirection: 'column', gap: 12 }}>
          <b>D&apos;où viennent vos visiteurs</b>
          {sources.length ? (
            sources.slice(0, 6).map((so) => (
              <div key={so.label} style={{ display: 'flex', flexDirection: 'column', gap: 5 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13 }}>
                  <span style={{ fontWeight: 600 }}>{so.label}</span>
                  <b>{so.pct}%</b>
                </div>
                <div style={{ height: 9, background: 'var(--sand)', borderRadius: 5, overflow: 'hidden' }}>
                  <div style={{ height: '100%', width: `${so.pct}%`, background: so.color, borderRadius: 5 }} />
                </div>
              </div>
            ))
          ) : (
            <span style={{ fontSize: 13, color: 'var(--muted)' }}>Pas encore de visite sur cette période.</span>
          )}
        </div>
      </div>

      <Premium allowed={limits.advancedStats} base={base}>
        <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.6fr) minmax(0,1fr)', ['--gap' as string]: '18px' }}>
          <div className="card" style={{ borderRadius: 20, padding: 20 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 12, gap: 10, flexWrap: 'wrap' }}>
              <b>Quand vous cherche-t-on ?</b>
              {best ? <span style={{ fontSize: 13, background: 'var(--amber)', padding: '3px 10px', borderRadius: 999, fontWeight: 700 }}>{best}</span> : null}
            </div>
            <Heatmap rows={heat.grid.map((cells, i) => ({ day: DAYS[i], cells }))} hours={HOURS} />
          </div>
          <div className="card" style={{ borderRadius: 20, padding: 20, display: 'flex', flexDirection: 'column', gap: 2 }}>
            <b style={{ marginBottom: 8 }}>Ce que les gens tapent pour vous trouver</b>
            {queries.length ? (
              queries.map((q) => (
                <div
                  key={q.q}
                  style={{ display: 'flex', justifyContent: 'space-between', gap: 10, padding: '8px 0', borderTop: '1px solid var(--line-2)', fontSize: 14 }}
                >
                  <span>« {q.q} »</span>
                  <b>{fmtInt(Number(q.n))}</b>
                </div>
              ))
            ) : (
              <span style={{ fontSize: 13, color: 'var(--muted)' }}>Aucune recherche enregistrée sur cette période.</span>
            )}
          </div>
        </div>
      </Premium>
    </div>
  );
}
