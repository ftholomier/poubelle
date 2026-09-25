import type { Metadata } from 'next';
import Link from 'next/link';
import { MapView } from '@/components/maps/MapView';
import { fmtInt } from '@/lib/format';
import { communeCounts, getPortal } from '@/server/services/portal';
import { getTerritoryCommunes } from '@/server/services/territories';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory } = await params;
  const { territory: t, communesCount } = await getPortal(territory);
  return {
    title: 'Les communes',
    description: `Les ${communesCount} communes de ${t.name} et leurs commerces, artisans, producteurs et services.`,
    alternates: { canonical: portalUrl(t, '/communes') },
  };
}

export default async function CommunesPage({ params }: Props) {
  const { territory } = await params;
  const portal = await getPortal(territory);
  const { base, territory: t } = portal;
  const [communes, counts] = await Promise.all([getTerritoryCommunes(t.id), communeCounts(t.id)]);
  const list = communes.map((c) => ({ ...c, pros: counts.get(c.id) ?? 0 })).sort((a, b) => b.pros - a.pros || a.name.localeCompare(b.name, 'fr'));
  const max = Math.max(1, ...list.map((c) => c.pros));
  return (
    <div className="container" style={{ paddingTop: 40, paddingBottom: 60 }}>
      <div className="eyebrow" style={{ marginBottom: 6 }}>
        {list.length} communes · {fmtInt(portal.prosCount)} professionnels
      </div>
      <h1 className="h-page" style={{ margin: '0 0 10px' }}>
        Un territoire, {list.length} communes.
      </h1>
      <p style={{ fontSize: 18, color: 'var(--muted)', maxWidth: 640, margin: '0 0 30px' }}>
        Du bourg-centre aux plus petits villages : choisissez une commune pour découvrir ses commerces, ses artisans, ses producteurs et ses marchés.
      </p>
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.2fr) minmax(0,1fr)', ['--align' as string]: 'start' }}>
        <div className="auto-grid" style={{ ['--min' as string]: '220px', ['--gap' as string]: '12px' }}>
          {list.map((c) => (
            <Link
              key={c.id}
              href={`${base}/${c.slug}`}
              className="card card-link card-lift"
              style={{ borderRadius: 16, padding: 18, display: 'flex', flexDirection: 'column', gap: 8 }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', gap: 8 }}>
                <span className="display" style={{ fontSize: 20 }}>
                  {c.name}
                </span>
                <span style={{ fontSize: 12, color: 'var(--muted)' }}>{c.postalCodes[0]}</span>
              </div>
              <div className="bar" aria-hidden="true">
                <span style={{ width: `${Math.max(4, (c.pros / max) * 100)}%` }} />
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, color: 'var(--muted)' }}>
                <span>
                  <b style={{ color: 'var(--text)' }}>{fmtInt(c.pros)}</b> professionnels
                </span>
                {c.population ? <span>{fmtInt(c.population)} hab.</span> : null}
              </div>
            </Link>
          ))}
        </div>
        <div
          className="sticky-aside"
          style={{ position: 'sticky', top: 90, height: 560, borderRadius: 20, overflow: 'hidden', border: '1px solid var(--line)' }}
        >
          <MapView
            mode="heat"
            heat={list.filter((c) => c.lat && c.lng).map((c) => ({ name: c.name, lat: c.lat!, lng: c.lng!, value: c.pros }))}
            tileUrl={portal.mapConfig.tileUrl}
            attribution={portal.mapConfig.attribution}
            ariaLabel="Densité de professionnels par commune"
            style={{ width: '100%', height: '100%' }}
          />
        </div>
      </div>
    </div>
  );
}
