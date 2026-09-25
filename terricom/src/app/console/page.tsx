import type { Metadata } from 'next';
import Link from 'next/link';
import { CrmPanel, type CrmTab } from '@/components/console/CrmPanel';
import { RouteModal } from '@/components/console/RouteModal';
import { MapView } from '@/components/maps/MapView';
import { fmtDecimal, fmtEuros, fmtInt } from '@/lib/format';
import { requirePlatformStaff } from '@/server/authz';
import { env } from '@/server/env';
import { mapLabels, mrrSeries, pipeline, platformKpis, serviceHealth, territoryEconomics } from '@/server/services/console';

export const metadata: Metadata = { title: 'Vue d’ensemble' };

const CRM_TABS: CrmTab[] = ['act', 'onb', 'neg', 'pro', 'lost'];

type Props = { searchParams: Promise<{ crm?: string; deal?: string }> };

export default async function ConsoleOverview({ searchParams }: Props) {
  const sp = await searchParams;
  const actor = await requirePlatformStaff();
  const crmTab = CRM_TABS.find((t) => t === sp.crm);
  const [k, mrr, pipe, labels, health, eco] = await Promise.all([platformKpis(), mrrSeries(), pipeline(), mapLabels(), serviceHealth(), territoryEconomics()]);
  const kpis = [
    {
      l: 'ARR',
      v: fmtEuros(k.arrCents),
      d: k.growth !== null ? `${k.growth >= 0 ? '+' : ''}${k.growth} % sur 12 mois` : 'revenu récurrent annuel',
      bg: 'var(--ink)',
      fg: 'var(--amber)',
    },
    { l: 'Territoires actifs', v: fmtInt(k.active), d: `+${k.onboarding} en onboarding`, bg: '#fff', fg: 'var(--text)' },
    { l: 'Établissements', v: fmtInt(k.establishments), d: `dont ${fmtInt(k.claimed)} revendiqués`, bg: '#fff', fg: 'var(--text)' },
    { l: 'Conversion Premium', v: `${fmtDecimal(k.conversion * 100, 1)} %`, d: `${fmtInt(k.premium)} abonnés`, bg: '#fff', fg: 'var(--text)' },
    { l: 'Churn Premium', v: `${fmtDecimal(k.churn * 100, 1)} %`, d: 'mensuel', bg: '#fff', fg: 'var(--text)' },
  ];
  const max = Math.max(1, ...mrr.map((m) => m.licences + m.premium));
  // Usage et rentabilité (30 jours) : panier moyen des offres, entreprises actives, campagnes, coûts.
  const sum = (f: (e: (typeof eco)[number]) => number) => eco.reduce((n, e) => n + f(e), 0);
  const premiumCompanies = sum((e) => e.premiumCompanies);
  const usage = [
    {
      l: 'Panier moyen des offres',
      v: premiumCompanies ? `${fmtEuros(Math.round(sum((e) => e.premiumMonthlyCents) / premiumCompanies))}` : '—',
      d: 'par entreprise abonnée et par mois',
    },
    { l: 'Entreprises actives', v: fmtInt(sum((e) => e.activeCompanies)), d: `sur 30 jours · ${fmtInt(k.claimed)} fiches revendiquées` },
    { l: 'Vues des campagnes', v: fmtInt(sum((e) => e.campaignViews)), d: 'sur 30 jours, tous territoires' },
    {
      l: 'Coût d’exploitation',
      v: eco.length ? fmtEuros(Math.round(sum((e) => e.costMonthlyCents) / eco.length)) : '—',
      d: 'par territoire et par mois (estimé)',
    },
  ];
  const healthRows = [
    {
      l: 'Disponibilité 30 j',
      v: health.uptime !== null ? `${fmtDecimal(health.uptime * 100, 2)} %` : '—',
      c: health.uptime === null || health.uptime >= 0.999 ? 'var(--green)' : 'var(--brick)',
    },
    {
      l: 'Temps de réponse',
      v: health.latency !== null ? `${health.latency} ms` : '—',
      c: health.latency === null || health.latency < 400 ? 'var(--green)' : 'var(--brick)',
    },
    { l: 'Tickets ouverts', v: fmtInt(health.tickets), c: health.tickets ? 'var(--brick)' : 'var(--green)' },
  ];

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 12 }}>
        {kpis.map((x) => (
          <div
            key={x.l}
            style={{ background: x.bg, color: x.fg, borderRadius: 18, padding: 18, border: x.bg === '#fff' ? '1px solid var(--console-line)' : 0 }}
          >
            <div style={{ fontSize: 13, fontWeight: 600, opacity: 0.75 }}>{x.l}</div>
            <div className="display" style={{ fontSize: 36, letterSpacing: '-0.03em', lineHeight: 1.1 }}>
              {x.v}
            </div>
            <div style={{ fontSize: 12, fontWeight: 700, opacity: 0.85 }}>{x.d}</div>
          </div>
        ))}
      </div>
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.3fr) minmax(0,1fr)', ['--gap' as string]: '18px' }}>
        <section className="console-card" style={{ overflow: 'hidden' }}>
          <div style={{ display: 'flex', gap: 12, padding: '14px 18px', flexWrap: 'wrap', alignItems: 'center' }}>
            <b>Territoires</b>
            <span style={{ display: 'flex', gap: 10, fontSize: 12, color: 'var(--muted)', marginLeft: 'auto', flexWrap: 'wrap' }}>
              <span>
                <b style={{ color: '#1F6B52' }}>●</b> Actif
              </span>
              <span>
                <b style={{ color: '#3E6FB0' }}>●</b> Onboarding
              </span>
              <span>
                <b style={{ color: '#C8892A' }}>●</b> Négociation
              </span>
              <span>
                <b style={{ color: '#9A9F95' }}>●</b> Prospect
              </span>
            </span>
          </div>
          <MapView
            mode="france"
            tileUrl={env.MAP_TILE_URL}
            attribution={env.MAP_TILE_ATTRIBUTION}
            labels={labels}
            center={[46.6, 2.6]}
            zoom={6}
            style={{ height: 440 }}
            ariaLabel="Carte des territoires clients et prospects"
          />
        </section>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
          <section className="console-card" style={{ padding: 20 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 12 }}>
              <b>Revenu récurrent mensuel</b>
              <span style={{ fontSize: 12, color: 'var(--muted)' }}>licences + Premium</span>
            </div>
            <div
              style={{ display: 'grid', gridTemplateColumns: 'repeat(12,1fr)', gap: 6, alignItems: 'end', height: 160 }}
              role="img"
              aria-label="Revenu récurrent mensuel sur 12 mois"
            >
              {mrr.map((m) => (
                <div
                  key={m.month}
                  title={`${m.label} : ${fmtEuros(m.licences + m.premium)}`}
                  style={{ display: 'flex', flexDirection: 'column', justifyContent: 'flex-end', height: '100%', gap: 2 }}
                >
                  <div style={{ height: `${(m.premium / max) * 80}%`, background: '#7A5BB5', borderRadius: '4px 4px 0 0' }} />
                  <div style={{ height: `${(m.licences / max) * 80}%`, background: 'var(--green)', borderRadius: '0 0 3px 3px' }} />
                  <span style={{ fontSize: 10, color: 'var(--muted)', textAlign: 'center' }}>{m.label}</span>
                </div>
              ))}
            </div>
            <div style={{ display: 'flex', gap: 14, fontSize: 12, marginTop: 8, color: 'var(--muted)' }}>
              <span>
                <b style={{ color: 'var(--green)' }}>■</b> Licences collectivités
              </span>
              <span>
                <b style={{ color: '#7A5BB5' }}>■</b> Abonnements Premium
              </span>
            </div>
          </section>
          <section className="console-card" style={{ padding: 20 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap' }}>
              <b>Pipeline commercial</b>
              <span style={{ fontSize: 12, color: 'var(--muted)' }}>Cliquez sur une étape pour ouvrir le suivi</span>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 8, marginTop: 12 }}>
              {pipe.map((p) => (
                <Link
                  key={p.key}
                  href={`/console?crm=${p.key}`}
                  scroll={false}
                  className="card-link"
                  style={{ background: p.bg, borderRadius: 12, padding: 12, color: 'var(--text)', display: 'block' }}
                >
                  <div className="display" style={{ fontSize: 28 }}>
                    {p.n}
                  </div>
                  <div style={{ fontSize: 12, fontWeight: 700 }}>{p.label}</div>
                  <div style={{ fontSize: 11, color: 'var(--muted-3)' }}>{p.key === 'pro' ? `${p.qualified} qualifiés` : `${fmtEuros(p.licenceCents)}/an`}</div>
                </Link>
              ))}
            </div>
          </section>
          <section className="console-card" style={{ padding: '18px 20px', display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 10 }}>
            {healthRows.map((h) => (
              <div key={h.l}>
                <div style={{ fontSize: 12, color: 'var(--muted)' }}>{h.l}</div>
                <div style={{ fontWeight: 800, fontSize: 18, color: h.c }}>{h.v}</div>
              </div>
            ))}
          </section>
        </div>
      </div>
      <section className="console-card" style={{ padding: 20, display: 'flex', flexDirection: 'column', gap: 14 }} aria-labelledby="usage-title">
        <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap', alignItems: 'baseline' }}>
          <b id="usage-title">Usage et rentabilité par territoire</b>
          <span style={{ fontSize: 12, color: 'var(--muted)' }}>30 derniers jours · coûts estimés : IA, emails, stockage et part d’infrastructure</span>
        </div>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(170px,1fr))', gap: 10 }}>
          {usage.map((u) => (
            <div key={u.l} style={{ background: 'var(--console-bg)', borderRadius: 14, padding: '12px 14px' }}>
              <div style={{ fontSize: 12, fontWeight: 600, color: 'var(--muted)' }}>{u.l}</div>
              <div className="display" style={{ fontSize: 26, letterSpacing: '-0.02em' }}>
                {u.v}
              </div>
              <div style={{ fontSize: 11, fontWeight: 700, color: 'var(--muted-3)' }}>{u.d}</div>
            </div>
          ))}
        </div>
        <div className="table-wrap">
          <table className="data-table">
            <thead>
              <tr>
                <th scope="col">Territoire</th>
                <th scope="col">Fiches</th>
                <th scope="col">Entreprises actives</th>
                <th scope="col">Abonnées</th>
                <th scope="col">Vues campagnes</th>
                <th scope="col">Emails</th>
                <th scope="col">Revenu mensuel</th>
                <th scope="col">Coût mensuel</th>
                <th scope="col">Marge</th>
              </tr>
            </thead>
            <tbody>
              {eco.map((e) => {
                const margin = e.revenueMonthlyCents - e.costMonthlyCents;
                return (
                  <tr key={e.id}>
                    <td>
                      <Link href={`/console/territoires?t=${e.id}`} style={{ fontWeight: 700, color: 'inherit' }}>
                        {e.name}
                      </Link>
                      {e.status === 'ONBOARDING' ? <span style={{ fontSize: 11, color: 'var(--muted)' }}> · onboarding</span> : null}
                    </td>
                    <td>{fmtInt(e.establishments)}</td>
                    <td>{fmtInt(e.activeCompanies)}</td>
                    <td>{fmtInt(e.premiumCompanies)}</td>
                    <td>{fmtInt(e.campaignViews)}</td>
                    <td>{fmtInt(e.emails)}</td>
                    <td>{fmtEuros(e.revenueMonthlyCents)}</td>
                    <td
                      title={`IA ${fmtEuros(e.costDetail.ai)} · emails ${fmtEuros(e.costDetail.emails)} · stockage ${fmtEuros(e.costDetail.storage)} · infrastructure ${fmtEuros(e.costDetail.infra)}`}
                    >
                      {fmtEuros(e.costMonthlyCents)}
                    </td>
                    <td style={{ fontWeight: 700, color: margin >= 0 ? 'var(--green)' : 'var(--danger-fg)' }}>{fmtEuros(margin)}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </section>
      {crmTab ? (
        <RouteModal closeHref="/console" label="Suivi commercial">
          <CrmPanel
            tab={crmTab}
            dealId={sp.deal}
            base="/console"
            closeHref="/console"
            canEdit={actor.isPlatformAdmin || actor.roles.some((r) => r.role === 'PLATFORM_SALES')}
          />
        </RouteModal>
      ) : null}
    </div>
  );
}
