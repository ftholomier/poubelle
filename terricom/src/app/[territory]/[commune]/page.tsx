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
import { fmtTimeShort } from '@/lib/format';
import { familyL, intL, postKindL, relativeL, shortDateL, timeL, WEEKDAY_SHORT_NAMES } from '@/lib/i18n/format';
import { withLang } from '@/lib/i18n';
import { portalT } from '@/server/i18n';
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
  const tr = await portalT(portal);
  if (!commune) return { title: tr('commune.notFound'), robots: { index: false } };
  const counts = await familyCounts(portal.territory.id, commune.id);
  const title = tr('commune.metaTitle', { name: commune.name });
  const description = tr('commune.metaDesc', { n: intL(counts.total, tr.locale), name: commune.name, cp: commune.postalCodes[0] ?? '' });
  return {
    title,
    description,
    alternates: { canonical: withLang(portalUrl(portal.territory, `/${commune.slug}`), tr.locale) },
    openGraph: { title, description, images: commune.heroImageUrl ? [{ url: sized(commune.heroImageUrl, 1200)! }] : undefined },
  };
}

export default async function CommunePage({ params }: Props) {
  const { territory, commune: slug } = await params;
  const { portal, commune } = await load(territory, slug);
  if (!commune) notFound();
  const { base, territory: t } = portal;
  const tr = await portalT(portal);
  const L = tr.locale;
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
    L,
  );
  // L'accroche rédigée par la commune est en français : traduction générique dans les autres langues.
  const tagline = (L === 'fr' && commune.tagline ? commune.tagline : tr('commune.taglineDefault', { n: '{pros}', name: commune.name })).replace(
    '{pros}',
    intL(counts.total, L),
  );
  const tiles = [
    { value: counts.total, label: tr.n('common.pros', counts.total), color: 'var(--ink)', href: `${base}/explorer?commune=${commune.slug}` },
    ...counts.byFamily.map((f) => ({
      value: f.count,
      label: familyL(f.family, f.label, L),
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
            {tr('commune.badge')}
            {commune.population ? ` · ${tr('commune.inhabitants', { n: intL(commune.population, L) })}` : ''}
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
                {intL(tile.value, L)}
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
            {tr('commune.headline', { name: commune.name })}
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
                      {postKindL(f.kind, f.kindLabel, L)}
                    </span>
                    <div style={{ fontWeight: 700, fontSize: 16 }}>{f.title}</div>
                    <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                      {f.who} · {relativeL(f.publishedAt, L)}
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
              {tr('commune.noNews', { name: commune.name })}
            </div>
          )}
          {events.length ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10, marginTop: 8 }}>
              <h3 className="h3" style={{ margin: 0 }}>
                {tr('commune.nextEvents')}
              </h3>
              {events.map(({ ev, c }) => (
                <EventRow
                  key={ev.id}
                  href={`${base}/agenda/${ev.slug}`}
                  title={ev.title}
                  where={[ev.locationName, c?.name].filter(Boolean).join(' · ')}
                  kind={ev.kind as EventKind}
                  startsAt={ev.startsAt}
                  L={L}
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
              <div style={{ fontSize: 12, fontWeight: 800, letterSpacing: '0.08em', color: 'var(--amber)' }}>{tr('commune.mayorWord')}</div>
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
              <div style={{ fontWeight: 700 }}>{tr('commune.operations')}</div>
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
                    {o.own ? tr('commune.opOwn', { name: commune.name }) : tr.n('commune.opJoined', o.joined, { name: commune.name })}
                    {' · '}
                    {shortDateL(o.startsAt, L)} → {shortDateL(o.endsAt, L)}
                  </span>
                </Link>
              ))}
            </div>
          ) : null}
          {markets.length ? (
            <div className="card" style={{ borderRadius: 20, padding: 22 }}>
              <div style={{ fontWeight: 700, marginBottom: 12 }}>{tr('commune.markets')}</div>
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
                    {WEEKDAY_SHORT_NAMES[L][m.weekday]} {L === 'fr' ? fmtTimeShort(m.startTime) : timeL(m.startTime, L)}–
                    {L === 'fr' ? fmtTimeShort(m.endTime) : timeL(m.endTime, L)}
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
              ariaLabel={tr('commune.mapAria', { name: commune.name })}
              style={{ width: '100%', height: '100%' }}
            />
          </div>
          <Link href={`${base}/explorer?commune=${commune.slug}`} className="btn btn-dark" style={{ alignSelf: 'flex-start' }}>
            {tr('commune.explore', { name: commune.name })}
          </Link>
        </div>
      </section>
    </div>
  );
}
