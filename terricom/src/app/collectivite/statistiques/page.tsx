import type { Metadata } from 'next';
import Link from 'next/link';
import { AutoSubmitSelect } from '@/components/ui/AutoSubmitSelect';
import { fmtInt } from '@/lib/format';
import { loadBoContext } from '@/server/services/backoffice';
import { PERIODS, periodOf, statsBundle } from '@/server/services/bo-report';

export const metadata: Metadata = { title: 'Statistiques' };

type Props = { searchParams: Promise<Record<string, string | undefined>> };

const MONTH_NAMES: Record<string, string> = {
  jan: 'janvier',
  fév: 'février',
  mar: 'mars',
  avr: 'avril',
  mai: 'mai',
  juin: 'juin',
  juil: 'juillet',
  août: 'août',
  sep: 'septembre',
  oct: 'octobre',
  nov: 'novembre',
  déc: 'décembre',
};

function k(n: number): string {
  if (n >= 1000) return `${(Math.round(n / 100) / 10).toLocaleString('fr-FR')}k`;
  return String(n);
}

export default async function StatsPage({ searchParams }: Props) {
  const ctx = await loadBoContext();
  const sp = await searchParams;
  const period = periodOf(sp.periode);
  const s = await statsBundle(ctx, period, sp.commune ?? null);
  const q = (patch: Record<string, string | null>) => {
    const p = new URLSearchParams();
    const merged = { periode: period === '12m' ? null : period, commune: s.commune, ...patch };
    for (const [key, v] of Object.entries(merged)) if (v) p.set(key, v);
    const str = p.toString();
    return str ? `?${str}` : '';
  };
  const nlShare = s.kpis.searches ? Math.round((s.kpis.nl_searches / s.kpis.searches) * 100) : 0;
  const kpis = [
    { l: 'Visiteurs', v: s.kpis.visitors, d: s.growth !== null ? `${s.growth >= 0 ? '+' : ''}${s.growth} % depuis le lancement` : '', c: 'var(--green)' },
    { l: 'Recherches', v: s.kpis.searches, d: s.kpis.searches ? `dont ${nlShare} % en langage naturel` : '', c: 'var(--f-artisan)' },
    { l: 'Fiches consultées', v: s.kpis.est_views, d: '', c: 'var(--f-commerce)' },
    { l: 'Clics téléphone', v: s.kpis.calls, d: '', c: 'var(--danger)' },
    { l: 'Itinéraires', v: s.kpis.directions, d: '', c: '#7A5BB5' },
    { l: 'Contacts générés', v: s.kpis.calls + s.kpis.messages, d: 'messages + appels', c: 'var(--ink)' },
  ];
  const max = Math.max(1, ...s.months.map((m) => m.value));
  const communeRows = [...s.communesData].sort((a, b) => b.total - a.total).filter((c) => !s.commune || c.id === s.commune);
  const exportQs = q({});

  return (
    <div className="app-content">
      <div style={{ display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap' }}>
        {(Object.keys(PERIODS) as (keyof typeof PERIODS)[]).map((p) => (
          <Link
            key={p}
            href={`/collectivite/statistiques${q({ periode: p === '12m' ? null : p })}`}
            className="bo-chip"
            aria-current={p === period ? 'true' : undefined}
          >
            {PERIODS[p].label}
          </Link>
        ))}
        {ctx.level === 'TERRITORY' ? (
          <form style={{ display: 'flex', gap: 6 }}>
            {period !== '12m' ? <input type="hidden" name="periode" value={period} /> : null}
            <AutoSubmitSelect name="commune" defaultValue={s.commune ?? ''} className="bo-chip" style={{ paddingRight: 28 }} label="Commune">
              <option value="">Toutes communes</option>
              {ctx.communes.map((c) => (
                <option key={c.id} value={c.id}>
                  {c.name}
                </option>
              ))}
            </AutoSubmitSelect>
            <noscript>
              <button type="submit" className="btn-link">
                Filtrer
              </button>
            </noscript>
          </form>
        ) : null}
        <a href={`/api/collectivite/rapport.pdf${exportQs}`} className="btn btn-amber btn-sm" style={{ marginLeft: 'auto' }}>
          Générer le rapport pour le conseil {ctx.level === 'TERRITORY' ? 'communautaire' : 'municipal'} (PDF)
        </a>
        <a
          href={`/api/collectivite/statistiques.csv${exportQs}`}
          className="btn btn-outline btn-sm"
          style={{ border: '1.5px solid var(--ink)', color: 'var(--ink)' }}
        >
          Export CSV
        </a>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(160px,1fr))', gap: 12 }}>
        {kpis.map((x) => (
          <div key={x.l} style={{ background: 'var(--paper)', border: '1px solid var(--line)', borderRadius: 16, padding: 16, borderTop: `5px solid ${x.c}` }}>
            <div style={{ fontSize: 13, color: 'var(--muted)', fontWeight: 600 }}>{x.l}</div>
            <div className="display" style={{ fontSize: 30, letterSpacing: '-0.03em' }}>
              {fmtInt(x.v)}
            </div>
            <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--green)', minHeight: 16 }}>{x.d}</div>
          </div>
        ))}
      </div>

      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.6fr) minmax(0,1fr)', ['--gap' as string]: '18px' }}>
        <section className="bo-card" style={{ padding: 20, borderRadius: 20 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 14, gap: 10 }}>
            <b>Visiteurs du portail par mois</b>
            {s.launchedLabel ? (
              <span style={{ fontSize: 12, color: 'var(--muted)' }}>lancement pilote : {MONTH_NAMES[s.launchedLabel] ?? s.launchedLabel}</span>
            ) : null}
          </div>
          <div
            style={{ display: 'grid', gridTemplateColumns: 'repeat(12,1fr)', gap: 8, alignItems: 'end', height: 220 }}
            role="img"
            aria-label="Histogramme des visiteurs mensuels"
          >
            {s.months.map((m, i) => (
              <div key={m.key} style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6, height: '100%', justifyContent: 'flex-end' }}>
                <span style={{ fontSize: 10, fontWeight: 700, color: 'var(--muted)' }}>{m.value ? k(m.value) : ''}</span>
                <div
                  style={{
                    width: '100%',
                    height: `${Math.max(2, (m.value / max) * 100)}%`,
                    background: i === s.months.length - 1 ? 'var(--amber)' : m.value ? 'var(--green)' : 'var(--line)',
                    borderRadius: '8px 8px 3px 3px',
                  }}
                />
                <span style={{ fontSize: 11, color: 'var(--muted)' }}>{m.label}</span>
              </div>
            ))}
          </div>
        </section>
        <section className="bo-card" style={{ padding: 20, borderRadius: 20, display: 'flex', flexDirection: 'column', gap: 2 }}>
          <b style={{ marginBottom: 8 }}>Top recherches des habitants</b>
          {s.searches.map((t, i) => (
            <div
              key={t.q}
              style={{ display: 'grid', gridTemplateColumns: '22px 1fr auto', gap: 8, padding: '8px 0', borderTop: '1px solid var(--line-2)', fontSize: 14 }}
            >
              <b style={{ color: 'var(--brick)' }}>{i + 1}</b>
              <span>{t.q}</span>
              <b>{fmtInt(t.n)}</b>
            </div>
          ))}
          {!s.searches.length ? <span style={{ fontSize: 13, color: 'var(--muted)' }}>Pas encore de recherches sur la période.</span> : null}
          {s.signal ? (
            <div style={{ marginTop: 10, background: 'var(--amber)', borderRadius: 12, padding: 12, fontSize: 13, color: 'var(--ink)' }}>
              <b>Signal faible :</b> {fmtInt(s.signal.n)} recherches « {s.signal.q} », aucun référencé sur le territoire. Une piste pour l&apos;installation
              d&apos;une nouvelle activité ?
            </div>
          ) : null}
        </section>
      </div>

      <section style={{ background: 'var(--paper)', border: '1px solid var(--line)', borderRadius: 20, overflow: 'hidden' }}>
        <div style={{ overflowX: 'auto' }}>
          <div style={{ minWidth: 640 }}>
            <div
              className="bo-table-head"
              style={{ display: 'grid', gridTemplateColumns: '1.6fr repeat(5,1fr)', gap: 12, padding: '12px 18px', borderBottom: '1px solid var(--line)' }}
            >
              <span>Commune</span>
              <span>Fiches</span>
              <span>Revendiquées</span>
              <span>Vues</span>
              <span>Appels</span>
              <span>Itinéraires</span>
            </div>
            {communeRows.map((c) => (
              <div
                key={c.id}
                style={{
                  display: 'grid',
                  gridTemplateColumns: '1.6fr repeat(5,1fr)',
                  gap: 12,
                  padding: '11px 18px',
                  fontSize: 14,
                  borderBottom: '1px solid var(--line-3)',
                }}
              >
                <b>{c.name}</b>
                <span>{fmtInt(c.total)}</span>
                <span>{fmtInt(c.claimed)}</span>
                <span>{fmtInt(c.views)}</span>
                <span>{fmtInt(c.calls)}</span>
                <span>{fmtInt(c.directions)}</span>
              </div>
            ))}
          </div>
        </div>
      </section>
    </div>
  );
}
