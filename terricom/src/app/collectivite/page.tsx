import Link from 'next/link';
import { MapView } from '@/components/maps/MapView';
import { fmtInt, relativeTime } from '@/lib/format';
import { env } from '@/server/env';
import { adoptionFunnel, categoryPodium, commerceWeather, communeStats, liveFeed, scopePoints, todoCounts } from '@/server/services/bo-stats';
import { FAMILIES, type Family } from '@/lib/constants';
import { loadBoContext } from '@/server/services/backoffice';
import { db } from '@/server/db';
import { campaigns } from '@/server/db/schema';
import { and, eq } from 'drizzle-orm';

export default async function BackOfficeDashboard() {
  const ctx = await loadBoContext();
  const [funnel, weather, communesData, todo, feed, [campaign]] = await Promise.all([
    adoptionFunnel(ctx),
    commerceWeather(ctx),
    communeStats(ctx),
    todoCounts(ctx),
    liveFeed(ctx),
    db
      .select({ name: campaigns.name })
      .from(campaigns)
      .where(and(eq(campaigns.territoryId, ctx.territory.id), eq(campaigns.status, 'ACTIVE')))
      .limit(1),
  ]);
  const total = Math.max(1, funnel.total);
  const bars = [
    { l: 'Établissements référencés', v: funnel.total, c: 'var(--ink)' },
    { l: 'Fiches revendiquées', v: funnel.claimed, c: 'var(--green)' },
    { l: 'Fiches complètes', v: funnel.complete, c: '#5FA37E' },
    { l: 'Actifs ce mois', v: funnel.active, c: 'var(--amber)' },
  ];
  const goalPct = ctx.settings.adoptionGoalPct ?? 60;
  const goalLabel = ctx.settings.adoptionGoalLabel ?? `objectif : ${goalPct} % de fiches revendiquées`;
  const w = weather.territory;
  const weatherText = [
    `Indice d'activité`,
    null,
    ` · ${w.posts >= 0 ? '+' : ''}${fmtInt(w.posts)} publication${w.posts > 1 ? 's' : ''} cette semaine, ${fmtInt(w.newClaims)} nouvelle${w.newClaims > 1 ? 's' : ''} fiche${w.newClaims > 1 ? 's' : ''} revendiquée${w.newClaims > 1 ? 's' : ''}`,
    w.searchTrend === null || w.searchTrend === 0
      ? ', recherches stables.'
      : `, recherches ${w.searchTrend > 0 ? 'en hausse' : 'en baisse'} de ${Math.abs(w.searchTrend)}\u00a0%${campaign && w.searchTrend > 0 ? ` avec la campagne « ${campaign.name} »` : ''}.`,
  ];
  const todos = [
    { v: todo.claims, l: 'revendications à valider', c: 'var(--brick)', href: '/collectivite/moderation' },
    { v: todo.stale, l: 'fiches aux horaires obsolètes', c: 'var(--danger)', href: '/collectivite/entreprises?filtre=horaires' },
    { v: todo.posts, l: 'publications à modérer', c: '#7A5BB5', href: '/collectivite/moderation?onglet=publications' },
    { v: todo.letters, l: todo.letters > 1 ? 'newsletters en préparation' : 'newsletter prête à envoyer', c: 'var(--green)', href: '/collectivite/newsletter' },
    ...(todo.sirene ? [{ v: todo.sirene, l: 'mises à jour SIRENE à valider', c: 'var(--amber-fg)', href: '/collectivite/entreprises/sirene' }] : []),
  ];
  const communal = ctx.level === 'COMMUNE';
  const [catPodium, points] = communal ? await Promise.all([categoryPodium(ctx), scopePoints(ctx)]) : [[], []];
  const podium = (
    communal
      ? catPodium.map((c) => ({ id: c.id, name: c.name, total: c.total, claimed: c.claimed }))
      : communesData.filter((c) => c.total >= 5).map((c) => ({ id: c.id, name: c.name, total: c.total, claimed: c.claimed }))
  )
    .map((c) => ({ ...c, rate: Math.round((c.claimed / c.total) * 100) }))
    .sort((a, b) => b.rate - a.rate || b.total - a.total)
    .slice(0, 7);
  const medal = ['var(--amber)', '#D8D4CB', '#E2B48A'];

  return (
    <div className="app-content">
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(340px,1fr))', gap: 18 }}>
        <section className="bo-card">
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 16, gap: 10, flexWrap: 'wrap' }}>
            <b style={{ fontSize: 16 }}>Adoption de la plateforme</b>
            <span style={{ fontSize: 12, color: 'var(--muted)' }}>{goalLabel}</span>
          </div>
          <div className="bo-adoption">
            {bars.map((b) => {
              const pct = Math.round((b.v / total) * 100);
              return (
                <div key={b.l} style={{ display: 'flex', flexDirection: 'column', gap: 8, minWidth: 0 }}>
                  <div className="display" style={{ fontSize: 'clamp(24px,2.4vw,34px)', letterSpacing: '-0.03em', lineHeight: 1 }}>
                    {fmtInt(b.v)}
                  </div>
                  <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--muted)', minHeight: 34 }}>{b.l}</div>
                  <div style={{ height: 120, display: 'flex', alignItems: 'flex-end' }}>
                    <div
                      style={{
                        width: '100%',
                        height: `${Math.max(4, pct)}%`,
                        background: b.c,
                        borderRadius: '10px 10px 4px 4px',
                        display: 'flex',
                        alignItems: 'flex-start',
                        justifyContent: 'center',
                        paddingTop: 8,
                        fontSize: 12,
                        fontWeight: 800,
                        color: '#fff',
                      }}
                    >
                      {pct >= 20 ? `${pct}%` : ''}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </section>
        <section
          style={{
            background: 'var(--amber)',
            borderRadius: 22,
            padding: 22,
            display: 'flex',
            flexDirection: 'column',
            gap: 10,
            position: 'relative',
            overflow: 'hidden',
          }}
        >
          <div
            aria-hidden="true"
            style={{ position: 'absolute', right: -40, top: -40, width: 170, height: 170, borderRadius: '50%', background: 'var(--amber-soft)' }}
          />
          <div style={{ position: 'relative', fontSize: 12, fontWeight: 800, letterSpacing: '0.08em', color: 'var(--amber-fg)' }}>MÉTÉO DU COMMERCE LOCAL</div>
          <div className="display" style={{ position: 'relative', fontSize: 44, letterSpacing: '-0.03em', lineHeight: 0.95, color: 'var(--ink)' }}>
            {w.label}
            <br />
            sur {w.commune}
          </div>
          <div style={{ position: 'relative', fontSize: 14, color: 'var(--amber-fg-2)' }}>
            {weatherText[0]} <b>{w.index}/100</b>
            {weatherText[2]}
            {weatherText[3]}
          </div>
          <div style={{ position: 'relative', display: 'flex', gap: 6, marginTop: 'auto', flexWrap: 'wrap' }}>
            {weather.top.slice(1, 3).map((c, i) => (
              <span
                key={c.commune}
                style={{
                  background: i === 0 ? 'var(--ink)' : 'rgba(20,32,27,.12)',
                  color: i === 0 ? 'var(--amber)' : 'var(--ink)',
                  fontSize: 12,
                  fontWeight: 700,
                  padding: '5px 10px',
                  borderRadius: 999,
                }}
              >
                {c.commune} : {c.label.toLowerCase()}
              </span>
            ))}
          </div>
        </section>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(170px,1fr))', gap: 12 }}>
        {todos.map((t) => (
          <Link
            key={t.l}
            href={t.href}
            className="card-link"
            style={{
              background: 'var(--paper)',
              border: '1px solid var(--line)',
              borderRadius: 16,
              padding: 16,
              display: 'flex',
              gap: 12,
              alignItems: 'center',
              color: 'var(--text)',
            }}
          >
            <span className="display" style={{ fontSize: 30, color: t.c }}>
              {fmtInt(t.v)}
            </span>
            <span style={{ fontSize: 13, fontWeight: 600, lineHeight: 1.3 }}>{t.l}</span>
          </Link>
        ))}
      </div>

      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.5fr) minmax(0,1fr)', ['--gap' as string]: '18px' }}>
        <section
          style={{
            background: 'var(--paper)',
            border: '1px solid var(--line)',
            borderRadius: 22,
            overflow: 'hidden',
            display: 'flex',
            flexDirection: 'column',
          }}
        >
          <div style={{ display: 'flex', justifyContent: 'space-between', padding: '16px 20px', gap: 10, flexWrap: 'wrap' }}>
            <b>{communal ? 'Les établissements de la commune' : 'Densité économique par commune'}</b>
            <span style={{ fontSize: 12, color: 'var(--muted)' }}>établissements référencés</span>
          </div>
          {communal ? (
            <MapView
              mode="explore"
              tileUrl={env.MAP_TILE_URL}
              attribution={env.MAP_TILE_ATTRIBUTION}
              points={points.map((p) => ({
                id: p.id,
                lat: p.lat,
                lng: p.lng,
                name: p.name,
                color: FAMILIES[p.family as Family]?.color ?? '#1F6B52',
                subtitle: p.activity ?? undefined,
                href: `/collectivite/entreprises/${p.id}`,
              }))}
              style={{ height: 380 }}
              ariaLabel="Carte des établissements de la commune"
            />
          ) : (
            <MapView
              mode="heat"
              tileUrl={env.MAP_TILE_URL}
              attribution={env.MAP_TILE_ATTRIBUTION}
              heat={communesData
                .filter((c) => c.lat !== null && c.lng !== null && c.total > 0)
                .map((c) => ({ name: c.name, lat: c.lat!, lng: c.lng!, value: c.total }))}
              style={{ height: 380 }}
              ariaLabel="Carte de densité des établissements par commune"
            />
          )}
        </section>
        <section className="bo-card" style={{ padding: 20, display: 'flex', flexDirection: 'column', gap: 10 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between' }}>
            <b>{communal ? 'Le podium des activités' : 'Le podium des communes'}</b>
            <span style={{ fontSize: 12, color: 'var(--muted)' }}>taux de revendication</span>
          </div>
          {podium.map((p, i) => (
            <div key={p.id} style={{ display: 'grid', gridTemplateColumns: '30px minmax(0,1fr) 44px', gap: 10, alignItems: 'center' }}>
              <div
                style={{
                  width: 28,
                  height: 28,
                  borderRadius: '50%',
                  background: medal[i] ?? 'var(--sand)',
                  color: 'var(--ink)',
                  display: 'grid',
                  placeItems: 'center',
                  fontWeight: 800,
                  fontSize: 12,
                }}
              >
                {i + 1}
              </div>
              <div style={{ minWidth: 0 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, fontWeight: 600, marginBottom: 4, gap: 8 }}>
                  <span style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{p.name}</span>
                  <span style={{ color: 'var(--muted)', fontWeight: 500, whiteSpace: 'nowrap' }}>{fmtInt(p.total)} fiches</span>
                </div>
                <div style={{ height: 8, background: 'var(--sand)', borderRadius: 4, overflow: 'hidden' }}>
                  <div style={{ height: '100%', width: `${p.rate}%`, background: 'var(--green)', borderRadius: 4 }} />
                </div>
              </div>
              <b style={{ fontSize: 13, textAlign: 'right' }}>{p.rate}%</b>
            </div>
          ))}
          {!podium.length ? <span style={{ fontSize: 13, color: 'var(--muted)' }}>Pas encore assez de fiches pour établir un classement.</span> : null}
        </section>
      </div>

      <section className="bo-card" style={{ padding: 20 }}>
        <b>En direct du territoire</b>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(260px,1fr))', gap: '0 24px', marginTop: 8 }}>
          {feed.map((f, i) => (
            <div
              key={i}
              style={{
                display: 'grid',
                gridTemplateColumns: '10px 1fr auto',
                gap: 10,
                alignItems: 'center',
                padding: '10px 0',
                borderTop: '1px solid var(--line-2)',
                fontSize: 13,
              }}
            >
              <span style={{ width: 8, height: 8, borderRadius: '50%', background: f.color }} />
              <span>{f.text}</span>
              <span style={{ color: 'var(--muted)', fontSize: 12, whiteSpace: 'nowrap' }}>{relativeTime(f.at)}</span>
            </div>
          ))}
          {!feed.length ? <span style={{ fontSize: 13, color: 'var(--muted)', padding: '10px 0' }}>Aucune activité récente.</span> : null}
        </div>
      </section>
    </div>
  );
}
