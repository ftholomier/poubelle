import type { Metadata } from 'next';
import { MkCta, MkSection } from '@/components/site/Blocks';
import { SiteShell } from '@/components/site/SiteChrome';
import { MapView } from '@/components/maps/MapView';
import { TerritoryBadge } from '@/components/ui/Brand';
import { Photo } from '@/components/ui/Photo';
import { TERRITORY_KINDS, type TerritoryKind } from '@/lib/constants';
import { fmtInt } from '@/lib/format';
import { env } from '@/server/env';
import { liveTerritories, platformNumbers } from '@/server/services/marketing';

export const metadata: Metadata = {
  title: 'Territoires en ligne',
  description: 'Les communautés de communes et communes qui mettent leur économie locale en vitrine avec terricom.',
  alternates: { canonical: '/territoires' },
};

export default async function TerritoiresPage() {
  const [list, nums] = await Promise.all([liveTerritories(), platformNumbers()]);
  const labels = list
    .filter((t) => t.center_lat !== null && t.center_lng !== null)
    .map((t) => ({
      name: t.name,
      lat: t.center_lat!,
      lng: t.center_lng!,
      color: t.color_primary,
      tooltip: `${fmtInt(t.establishments)} établissements`,
      href: t.url,
    }));
  return (
    <SiteShell current="/territoires">
      <section className="mk-wrap" style={{ paddingTop: 56 }}>
        <div className="mk-eyebrow" style={{ color: 'var(--green)', marginBottom: 8 }}>
          Territoires en ligne
        </div>
        <h1 className="display" style={{ fontSize: 'clamp(40px,5vw,68px)', letterSpacing: '-0.04em', lineHeight: 0.95, margin: '0 0 12px' }}>
          {fmtInt(nums.territories)} territoires, {fmtInt(nums.communes)} communes, {fmtInt(nums.establishments)} établissements en vitrine.
        </h1>
        <p style={{ margin: 0, fontSize: 18, color: '#4A514C', maxWidth: 720 }}>
          Chaque portail est aux couleurs de sa collectivité, animé par ses équipes et gratuit pour ses professionnels.
        </p>
      </section>
      {labels.length ? (
        <section className="mk-wrap" style={{ paddingTop: 0 }}>
          <div className="mk-card" style={{ overflow: 'hidden' }}>
            <MapView
              mode="france"
              tileUrl={env.MAP_TILE_URL}
              attribution={env.MAP_TILE_ATTRIBUTION}
              labels={labels}
              center={[46.6, 2.6]}
              zoom={6}
              style={{ height: 420 }}
              ariaLabel="Carte des territoires en ligne"
            />
          </div>
        </section>
      ) : null}
      <MkSection title="Découvrir un portail">
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(300px,1fr))', gap: 18 }}>
          {list.map((t) => (
            <a
              key={t.id}
              href={t.url}
              className="mk-card card-link"
              style={{ overflow: 'hidden', display: 'flex', flexDirection: 'column', color: 'var(--ink)' }}
            >
              <div style={{ position: 'relative', height: 170 }}>
                <Photo src={t.hero_image_url} alt="" label=" " color={t.color_primary} style={{ width: '100%', height: '100%' }} />
                {t.is_pilot ? (
                  <span
                    className="display"
                    style={{
                      position: 'absolute',
                      right: 14,
                      top: 14,
                      background: 'var(--amber)',
                      fontSize: 13,
                      padding: '5px 10px',
                      borderRadius: 8,
                      transform: 'rotate(4deg)',
                    }}
                  >
                    Territoire pilote
                  </span>
                ) : null}
              </div>
              <div style={{ padding: 18, display: 'flex', gap: 12, alignItems: 'center' }}>
                <TerritoryBadge initials={t.initials} bg={t.color_primary} fg={t.color_accent} />
                <div style={{ minWidth: 0 }}>
                  <b className="display" style={{ fontSize: 22, display: 'block' }}>
                    {t.name} →
                  </b>
                  <span style={{ fontSize: 13, color: 'var(--muted)' }}>
                    {TERRITORY_KINDS[t.kind as TerritoryKind]?.label} · {fmtInt(t.communes)} communes · {fmtInt(t.establishments)} établissements
                  </span>
                </div>
              </div>
            </a>
          ))}
        </div>
      </MkSection>
      <MkCta title="Et si votre territoire était le prochain ?" />
    </SiteShell>
  );
}
