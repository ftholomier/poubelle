import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { cache } from 'react';
import { JsonLd } from '@/components/JsonLd';
import { MapView } from '@/components/maps/MapView';
import { Beacon } from '@/components/portal/Beacon';
import { EventRow } from '@/components/portal/Cards';
import { Photo } from '@/components/ui/Photo';
import { FAMILIES, type EventKind } from '@/lib/constants';
import { fmtInt, fmtTimeShort, relativeTime, WEEKDAYS_SHORT } from '@/lib/format';
import { sized } from '@/lib/images';
import { breadcrumbJsonLd } from '@/server/seo';
import { allCards, communeCampaigns, communeMarkets, familyCounts, getFeed, getPortal, toMapPoints, upcomingEvents } from '@/server/services/portal';
import { getCommuneInTerritory } from '@/server/services/territories';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string; commune: string }> };

const load = cache(async (territoryParam: string, communeSlug: string) => {
  const portal = await getPortal(territoryParam);
  const commune = await getCommuneInTerritory(portal.territory.id, communeSlug);
  return { portal, commune };
});

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory, commune: slug } = await params;
  const { portal, commune } = await load(territory, slug);
  if (!commune) return { title: 'Commune introuvable', robots: { index: false } };
  const counts = await familyCounts(portal.territory.id, commune.id);
  const title = `Commerces, artisans et producteurs à ${commune.name}`;
  const description = `${fmtInt(counts.total)} professionnels à ${commune.name} (${commune.postalCodes[0] ?? ''}) : horaires, adresses, actualités, marchés et bons plans locaux.`;
  return {
    title,
    description,
    alternates: { canonical: portalUrl(portal.territory, `/${commune.slug}`) },
    openGraph: { title, description, images: commune.heroImageUrl ? [{ url: sized(commune.heroImageUrl, 1200)! }] : undefined },
  };
}

export default async function CommunePage({ params }: Props) {
  const { territory, commune: slug } = await params;
  const { portal, commune } = await load(territory, slug);
  if (!commune) notFound();
  const { base, territory: t } = portal;
  const [counts, feed, markets, events, cards, operations] = await Promise.all([
    familyCounts(t.id, commune.id),
    getFeed(t.id, { communeId: commune.id, limit: 4, channel: 'COMMUNE' }),
    communeMarkets(commune.id),
    upcomingEvents(t.id, { communeId: commune.id, limit: 3 }),
    allCards(t.id),
    portal.modules.has('CAMPAIGNS') ? communeCampaigns(t.id, commune.id) : Promise.resolve([]),
  ]);
  const points = toMapPoints(
    cards.filter((c) => c.communeId === commune.id),
    base,
  );
  const tagline = (commune.tagline ?? `Les ${'{pros}'} professionnels de ${commune.name} : commerces, artisans, producteurs et services.`).replace(
    '{pros}',
    fmtInt(counts.total),
  );
  const tiles = [
    { value: counts.total, label: 'professionnels', color: 'var(--ink)', href: `${base}/explorer?commune=${commune.slug}` },
    ...counts.byFamily.map((f) => ({
      value: f.count,
      label: f.label,
      color: f.color,
      href: `${base}/explorer?commune=${commune.slug}&famille=${FAMILIES[f.family].slug}`,
    })),
  ];

  return (
    <div>
      <JsonLd
        data={breadcrumbJsonLd([
          { name: t.name, url: portalUrl(t, '/') },
          { name: commune.name, url: portalUrl(t, `/${commune.slug}`) },
        ])}
      />
      <Beacon type="PAGE_VIEW" territoryId={t.id} communeId={commune.id} />
      <section style={{ position: 'relative', height: 420, overflow: 'hidden', background: 'var(--ink)' }}>
        <Photo src={sized(commune.heroImageUrl ?? t.heroImageUrl, 2000)} alt="" eager style={{ position: 'absolute', inset: 0 }} color="#1F6B52" label=" " />
        <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(90deg,rgba(20,32,27,.85),rgba(20,32,27,.1))' }} />
        <div
          className="container"
          style={{ position: 'relative', paddingTop: 70, paddingBottom: 70, color: '#fff', display: 'flex', flexDirection: 'column', gap: 14 }}
        >
          <span
            style={{
              alignSelf: 'flex-start',
              fontSize: 12,
              fontWeight: 800,
              letterSpacing: '0.08em',
              background: 'rgba(255,255,255,.18)',
              padding: '5px 10px',
              borderRadius: 6,
              textTransform: 'uppercase',
            }}
          >
            Commune{commune.population ? ` · ${fmtInt(commune.population)} habitants` : ''}
          </span>
          <h1 className="display" style={{ fontSize: 'clamp(56px,7vw,104px)', letterSpacing: '-0.04em', lineHeight: 0.9, margin: 0 }}>
            {commune.name}
          </h1>
          <p style={{ fontSize: 18, maxWidth: 520, color: '#E0E8E3', margin: 0 }}>{tagline}</p>
        </div>
      </section>

      <section className="container" style={{ marginTop: -50, position: 'relative' }}>
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(170px,1fr))', gap: 12 }}>
          {tiles.map((tile) => (
            <Link
              key={tile.label}
              href={tile.href}
              className="card-lift"
              style={{
                background: 'var(--paper)',
                borderRadius: 16,
                padding: 18,
                boxShadow: '0 10px 30px rgba(20,32,27,.12)',
                borderTop: `5px solid ${tile.color}`,
                color: 'inherit',
                transition: 'transform .15s',
              }}
            >
              <div className="display" style={{ fontSize: 36, letterSpacing: '-0.03em' }}>
                {fmtInt(tile.value)}
              </div>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 14, fontWeight: 600, color: 'var(--muted)' }}>
                <span>{tile.label}</span>
                <span style={{ color: 'var(--green)' }}>→</span>
              </div>
            </Link>
          ))}
        </div>
      </section>

      <section className="container split" style={{ ['--cols' as string]: 'minmax(0,1.3fr) minmax(0,1fr)', paddingTop: 44, paddingBottom: 60 }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
          <h2 className="h-section" style={{ fontSize: 32, margin: 0 }}>
            À la une à {commune.name}
          </h2>
          {feed.length ? (
            feed.map((f) => {
              const inner = (
                <>
                  <div style={{ width: 160, height: 120, position: 'relative' }}>
                    <Photo src={sized(f.image, 320, 240)} alt="" label={f.who} color="var(--brand)" style={{ position: 'absolute', inset: 0 }} />
                  </div>
                  <div style={{ padding: '14px 14px 14px 0', display: 'flex', flexDirection: 'column', gap: 6 }}>
                    <span className="tag" style={{ alignSelf: 'flex-start', background: f.bg }}>
                      {f.kindLabel}
                    </span>
                    <div style={{ fontWeight: 700, fontSize: 16 }}>{f.title}</div>
                    <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                      {f.who} · {relativeTime(f.publishedAt)}
                    </div>
                  </div>
                </>
              );
              const style = {
                display: 'grid',
                gridTemplateColumns: '160px minmax(0,1fr)',
                gap: 16,
                background: 'var(--paper)',
                border: '1px solid var(--line)',
                borderRadius: 16,
                overflow: 'hidden',
                color: 'inherit',
              } as const;
              return f.whoPath ? (
                <Link key={f.id} href={`${base}${f.whoPath}#actualites`} style={style} className="card-link">
                  {inner}
                </Link>
              ) : (
                <div key={f.id} style={style}>
                  {inner}
                </div>
              );
            })
          ) : (
            <div className="card card-pad" style={{ color: 'var(--muted)' }}>
              Pas encore d&apos;actualité publiée à {commune.name}. Les professionnels de la commune peuvent publier depuis leur espace.
            </div>
          )}
          {events.length ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginTop: 8 }}>
              <h3 className="h3" style={{ margin: 0 }}>
                Prochains rendez-vous
              </h3>
              {events.map(({ ev, c }) => (
                <EventRow
                  key={ev.id}
                  href={`${base}/agenda/${ev.slug}`}
                  title={ev.title}
                  where={[ev.locationName, c?.name].filter(Boolean).join(' · ')}
                  kind={ev.kind as EventKind}
                  startsAt={ev.startsAt}
                />
              ))}
            </div>
          ) : null}
        </div>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
          {commune.mayorQuote ? (
            <figure
              style={{
                background: 'var(--ink)',
                color: 'var(--cream)',
                borderRadius: 20,
                padding: 24,
                display: 'flex',
                flexDirection: 'column',
                gap: 10,
                margin: 0,
              }}
            >
              <div style={{ fontSize: 12, fontWeight: 800, letterSpacing: '0.08em', color: 'var(--amber)' }}>LE MOT DE LA MAIRIE</div>
              <blockquote className="display" style={{ fontWeight: 700, fontSize: 21, lineHeight: 1.3, margin: 0 }}>
                « {commune.mayorQuote.replace(/^«\s*|\s*»$/g, '')} »
              </blockquote>
              {commune.mayorName ? (
                <figcaption style={{ display: 'flex', gap: 10, alignItems: 'center', marginTop: 6 }}>
                  <div style={{ width: 44, height: 44, borderRadius: '50%', overflow: 'hidden', flexShrink: 0 }}>
                    <Photo src={commune.mayorPhotoUrl} alt="" label={commune.mayorName} color="#2A3A33" />
                  </div>
                  <div style={{ fontSize: 13 }}>
                    <b>{commune.mayorName}</b>
                    {commune.mayorRole ? <div style={{ color: 'var(--sage)' }}>{commune.mayorRole}</div> : null}
                  </div>
                </figcaption>
              ) : null}
            </figure>
          ) : null}
          {operations.length ? (
            <div className="card" style={{ borderRadius: 20, padding: 22, display: 'flex', flexDirection: 'column', gap: 10 }}>
              <div style={{ fontWeight: 700 }}>Opérations commerciales</div>
              {operations.map((o) => (
                <Link
                  key={o.id}
                  href={`${base}/campagnes/${o.slug}`}
                  className="card-link"
                  style={{
                    background: o.colorBg,
                    color: o.colorText,
                    borderRadius: 14,
                    padding: '12px 14px',
                    display: 'flex',
                    flexDirection: 'column',
                    gap: 2,
                  }}
                >
                  <b className="display" style={{ fontSize: 17 }}>
                    {o.name}
                  </b>
                  <span style={{ fontSize: 12, opacity: 0.9 }}>
                    {o.own
                      ? `Opération de ${commune.name}`
                      : `${fmtInt(o.joined)} professionnel${o.joined > 1 ? 's' : ''} de ${commune.name} participe${o.joined > 1 ? 'nt' : ''}`}
                    {' · '}
                    {o.startsAt.split('-').reverse().slice(0, 2).join('/')} → {o.endsAt.split('-').reverse().slice(0, 2).join('/')}
                  </span>
                </Link>
              ))}
            </div>
          ) : null}
          {markets.length ? (
            <div className="card" style={{ borderRadius: 20, padding: 22 }}>
              <div style={{ fontWeight: 700, marginBottom: 12 }}>Marchés hebdomadaires</div>
              {markets.map((m) => (
                <div
                  key={m.id}
                  style={{ display: 'flex', justifyContent: 'space-between', gap: 12, padding: '10px 0', borderTop: '1px solid var(--line-2)', fontSize: 14 }}
                >
                  <span>
                    {m.name}
                    {m.place ? ` · ${m.place}` : ''}
                  </span>
                  <b style={{ whiteSpace: 'nowrap' }}>
                    {WEEKDAYS_SHORT[m.weekday]} {fmtTimeShort(m.startTime)}–{fmtTimeShort(m.endTime)}
                  </b>
                </div>
              ))}
            </div>
          ) : null}
          <div style={{ height: 260, borderRadius: 20, overflow: 'hidden', border: '1px solid var(--line)' }}>
            <MapView
              mode="mini"
              points={points}
              center={commune.lat && commune.lng ? [commune.lat, commune.lng] : undefined}
              zoom={14}
              tileUrl={portal.mapConfig.tileUrl}
              attribution={portal.mapConfig.attribution}
              ariaLabel={`Carte des professionnels de ${commune.name}`}
              style={{ width: '100%', height: '100%' }}
            />
          </div>
          <Link href={`${base}/explorer?commune=${commune.slug}`} className="btn btn-dark" style={{ alignSelf: 'flex-start' }}>
            Explorer {commune.name} sur la carte
          </Link>
        </div>
      </section>
    </div>
  );
}
