import { sql } from 'drizzle-orm';
import type { Metadata } from 'next';
import { fmtDecimal, fmtInt, fmtMonthYear } from '@/lib/format';
import { requirePlatformStaff } from '@/server/authz';
import { db } from '@/server/db';
import { env } from '@/server/env';

export const metadata: Metadata = { title: 'Usage IA' };

const FEATURES: Record<string, string> = {
  WRITER: 'Rédaction des publications',
  IMPROVE: 'Amélioration de texte',
  AUDIT: 'Audit de fiche',
  TERRITORIAL: 'Assistant territorial (campagnes)',
  SEARCH: 'Recherche en langage naturel',
  TRANSLATE: 'Traduction',
};

export default async function AiUsagePage() {
  await requirePlatformStaff();
  const [totals, byTerritory, byFeature, daily] = await Promise.all([
    db.execute<{ calls: number; credits: number; tin: number; tout: number; fallback: number; companies: number }>(sql`
      select count(*)::int as calls, coalesce(sum(credits), 0)::int as credits, coalesce(sum(input_tokens), 0)::bigint as tin,
        coalesce(sum(output_tokens), 0)::bigint as tout, count(*) filter (where fallback)::int as fallback, count(distinct company_id)::int as companies
      from ai_usage where created_at >= date_trunc('month', now())`),
    db.execute<{ id: string; name: string; quota: number; credits: number; calls: number; fallback: number }>(sql`
      select t.id, t.name, t.quota_ai_credits_monthly as quota,
        coalesce(sum(u.credits), 0)::int as credits, count(u.id)::int as calls, count(u.id) filter (where u.fallback)::int as fallback
      from territories t left join ai_usage u on u.territory_id = t.id and u.created_at >= date_trunc('month', now())
      group by t.id order by credits desc, t.name`),
    db.execute<{ feature: string; calls: number; credits: number }>(sql`
      select feature, count(*)::int as calls, coalesce(sum(credits), 0)::int as credits
      from ai_usage where created_at >= date_trunc('month', now()) group by feature order by calls desc`),
    db.execute<{ day: string; calls: number }>(sql`
      with d as (select generate_series(current_date - 29, current_date, interval '1 day')::date as day)
      select to_char(d.day, 'YYYY-MM-DD') as day, (select count(*)::int from ai_usage u where u.created_at::date = d.day) as calls from d order by d.day`),
  ]);
  const k = totals.rows[0];
  const inCost = env.AI_COST_INPUT_PER_MTOK;
  const outCost = env.AI_COST_OUTPUT_PER_MTOK;
  const cost = (Number(k?.tin ?? 0) / 1e6) * inCost + (Number(k?.tout ?? 0) / 1e6) * outCost;
  const maxDay = Math.max(1, ...daily.rows.map((d) => d.calls));
  const maxFeature = Math.max(1, ...byFeature.rows.map((f) => f.calls));
  const tiles = [
    { l: 'Appels ce mois-ci', v: fmtInt(k?.calls ?? 0), d: `${fmtInt(k?.companies ?? 0)} entreprises utilisatrices` },
    { l: 'Crédits consommés', v: fmtInt(k?.credits ?? 0), d: 'sur les quotas des territoires' },
    { l: 'Jetons (entrée / sortie)', v: `${fmtDecimal(Number(k?.tin ?? 0) / 1e6, 2)} M`, d: `${fmtDecimal(Number(k?.tout ?? 0) / 1e6, 2)} M en sortie` },
    { l: 'Coût estimé', v: `${fmtDecimal(cost, 2)} €`, d: `tarifs configurés : ${inCost} € / ${outCost} € par million` },
    {
      l: 'Repli sans IA',
      v: k?.calls ? `${fmtDecimal((k.fallback / k.calls) * 100, 1)} %` : '—',
      d: env.ANTHROPIC_API_KEY ? `modèle ${env.AI_MODEL}` : 'clé API absente : mode règles',
    },
  ];
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 12 }}>
        {tiles.map((x) => (
          <div key={x.l} className="console-card" style={{ borderRadius: 18, padding: 18 }}>
            <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--muted)' }}>{x.l}</div>
            <div className="display" style={{ fontSize: 30, letterSpacing: '-0.03em' }}>
              {x.v}
            </div>
            <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--muted)' }}>{x.d}</div>
          </div>
        ))}
      </div>
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.3fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <section className="console-card" style={{ borderRadius: 20, overflow: 'hidden' }}>
          <div style={{ padding: '14px 18px' }}>
            <b>Consommation par territoire · {fmtMonthYear()}</b>
          </div>
          <table className="console-table" style={{ minWidth: 0 }}>
            <thead>
              <tr>
                <th>Territoire</th>
                <th>Crédits / quota</th>
                <th style={{ textAlign: 'right' }}>Appels</th>
                <th style={{ textAlign: 'right' }}>Repli</th>
              </tr>
            </thead>
            <tbody>
              {byTerritory.rows.map((t) => {
                const pct = t.quota ? Math.min(100, (t.credits / t.quota) * 100) : 0;
                return (
                  <tr key={t.id}>
                    <td>
                      <b>{t.name}</b>
                    </td>
                    <td style={{ minWidth: 200 }}>
                      <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 12, marginBottom: 4 }}>
                        <span>
                          {fmtInt(t.credits)} / {fmtInt(t.quota)}
                        </span>
                        <span style={{ color: 'var(--muted)' }}>{fmtDecimal(pct, 0)} %</span>
                      </div>
                      <div style={{ height: 6, background: 'var(--console-bg)', borderRadius: 3, overflow: 'hidden' }}>
                        <div style={{ height: '100%', width: `${pct}%`, background: pct >= 90 ? 'var(--danger)' : '#7A5BB5' }} />
                      </div>
                    </td>
                    <td style={{ textAlign: 'right' }}>{fmtInt(t.calls)}</td>
                    <td style={{ textAlign: 'right' }}>{t.calls ? `${fmtDecimal((t.fallback / t.calls) * 100, 0)} %` : '—'}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </section>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <section className="console-card" style={{ borderRadius: 20, padding: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <b>Par fonctionnalité</b>
            {byFeature.rows.length === 0 ? <span style={{ fontSize: 13, color: 'var(--muted)' }}>Aucun appel ce mois-ci.</span> : null}
            {byFeature.rows.map((f) => (
              <div key={f.feature}>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, marginBottom: 4 }}>
                  <span>{FEATURES[f.feature] ?? f.feature}</span>
                  <b>{fmtInt(f.calls)}</b>
                </div>
                <div style={{ height: 7, background: 'var(--console-bg)', borderRadius: 4, overflow: 'hidden' }}>
                  <div style={{ height: '100%', width: `${(f.calls / maxFeature) * 100}%`, background: '#7A5BB5' }} />
                </div>
              </div>
            ))}
          </section>
          <section className="console-card" style={{ borderRadius: 20, padding: 18 }}>
            <b>Appels quotidiens (30 jours)</b>
            <div
              style={{ display: 'grid', gridTemplateColumns: 'repeat(30,1fr)', gap: 3, alignItems: 'end', height: 120, marginTop: 12 }}
              role="img"
              aria-label="Appels à l’IA par jour sur 30 jours"
            >
              {daily.rows.map((d) => (
                <div
                  key={d.day}
                  title={`${d.day} : ${d.calls} appels`}
                  style={{
                    height: `${Math.max(2, (d.calls / maxDay) * 100)}%`,
                    background: d.calls ? '#7A5BB5' : 'var(--console-track)',
                    borderRadius: '3px 3px 0 0',
                  }}
                />
              ))}
            </div>
          </section>
          <section className="console-card" style={{ borderRadius: 20, padding: 18, fontSize: 13, color: 'var(--muted)', lineHeight: 1.5 }}>
            Les textes transmis au modèle ne contiennent pas de données personnelles des habitants. Sans clé API ou en cas d’indisponibilité, chaque assistant
            bascule sur des règles déterministes (repli) : le service reste rendu.
          </section>
        </div>
      </div>
    </div>
  );
}
