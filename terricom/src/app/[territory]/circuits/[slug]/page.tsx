import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { cache } from 'react';
import { JsonLd } from '@/components/JsonLd';
import { MapView } from '@/components/maps/MapView';
import { Beacon } from '@/components/portal/Beacon';
import { PassportPhone } from '@/components/portal/PassportPhone';
import { Photo } from '@/components/ui/Photo';
import { fmtDecimal } from '@/lib/format';
import { sized } from '@/lib/images';
import type { TerritorySettings } from '@/server/db/schema';
import { env } from '@/server/env';
import { qrDataUrl } from '@/server/qr';
import { getCircuitDetail, getVisitorPassport, listPublishedCircuits } from '@/server/services/circuits';
import { getPortal } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string; slug: string }>; searchParams: Promise<Record<string, string | undefined>> };

const load = cache(async (territoryParam: string, slug: string) => {
  const portal = await getPortal(territoryParam);
  if (!portal.modules.has('CIRCUITS')) return { portal, detail: null };
  return { portal, detail: await getCircuitDetail(portal.territory.id, slug) };
});

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory, slug } = await params;
  const { portal, detail } = await load(territory, slug);
  if (!detail) return { title: 'Circuit introuvable', robots: { index: false } };
  const c = detail.circuit;
  return {
    title: `${c.name} — circuit`,
    description: c.description || `${detail.stops.length} étapes : ${c.meta ?? ''}`,
    alternates: { canonical: portalUrl(portal.territory, `/circuits/${c.slug}`) },
    openGraph: { title: c.name, description: c.meta ?? undefined, images: c.imageUrl ? [{ url: sized(c.imageUrl, 1200)! }] : undefined },
  };
}

export default async function CircuitPage({ params, searchParams }: Props) {
  const { territory, slug } = await params;
  const sp = await searchParams;
  const { portal, detail } = await load(territory, slug);
  if (!detail) notFound();
  const { base, territory: t } = portal;
  const { circuit: c, stops } = detail;
  const settings = (t.settings ?? {}) as TerritorySettings;
  const [all, passport, qr] = await Promise.all([
    listPublishedCircuits(t.id),
    getVisitorPassport(c.id),
    qrDataUrl(portalUrl(t, `/circuits/${c.slug}`), { withSymbol: true }),
  ]);
  const stamped = passport?.stamped ?? [];
  const points = stops
    .filter((s) => s.lat && s.lng)
    .map((s) => ({
      id: s.id,
      lat: s.lat!,
      lng: s.lng!,
      name: s.name,
      color: s.color,
      subtitle: `${s.activity} · ${s.communeName}`,
      href: s.path ? `${base}${s.path}` : undefined,
    }));
  const justStamped = sp.tampon && /^\d+$/.test(sp.tampon) ? Number(sp.tampon) : null;

  return (
    <div className="container" style={{ paddingTop: 36, paddingBottom: 60 }}>
      <Beacon type="CIRCUIT_VIEW" territoryId={t.id} refId={c.id} />
      <JsonLd
        data={{
          '@context': 'https://schema.org',
          '@type': 'TouristTrip',
          name: c.name,
          description: c.description || c.meta,
          url: portalUrl(t, `/circuits/${c.slug}`),
          itinerary: {
            '@type': 'ItemList',
            numberOfItems: stops.length,
            itemListElement: stops.map((s, i) => ({ '@type': 'ListItem', position: i + 1, item: { '@type': 'Place', name: s.name, address: s.communeName } })),
          },
        }}
      />
      {justStamped ? (
        <div className="alert alert-ok" role="status" style={{ marginBottom: 18 }}>
          Étape {justStamped} tamponnée !{' '}
          {passport?.rewardCode
            ? 'Circuit réussi : votre code de récompense vous attend dans le passeport.'
            : 'Continuez le circuit pour débloquer la récompense.'}
        </div>
      ) : sp.tampon === 'invalide' ? (
        <div className="alert alert-warn" role="status" style={{ marginBottom: 18 }}>
          Ce QR code n&apos;est pas reconnu. Vérifiez qu&apos;il s&apos;agit bien d&apos;une étape d&apos;un circuit en cours.
        </div>
      ) : null}

      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'end', gap: 16, flexWrap: 'wrap', marginBottom: 22 }}>
        <div>
          <div className="eyebrow" style={{ marginBottom: 6 }}>
            Circuits du territoire
          </div>
          <h1 className="display" style={{ fontSize: 'clamp(38px,5vw,52px)', letterSpacing: '-0.035em', margin: 0, lineHeight: 1 }}>
            {settings.circuitsTitle ?? 'Balades & circuits'}
          </h1>
        </div>
        <nav aria-label="Choisir un circuit" style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
          {all.map((x) => {
            const on = x.id === c.id;
            return (
              <Link
                key={x.id}
                href={`${base}/circuits/${x.slug}`}
                aria-current={on ? 'page' : undefined}
                style={{
                  border: '1.5px solid var(--ink)',
                  padding: '9px 14px',
                  borderRadius: 999,
                  fontWeight: 700,
                  fontSize: 13,
                  background: on ? 'var(--ink)' : 'transparent',
                  color: on ? '#fff' : 'var(--ink)',
                }}
              >
                {x.name}
              </Link>
            );
          })}
        </nav>
      </div>

      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) 340px', ['--align' as string]: 'start' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 18, minWidth: 0 }}>
          <div style={{ height: 460, borderRadius: 24, overflow: 'hidden', border: '1px solid var(--line)' }}>
            <MapView
              mode="circuit"
              points={points}
              tileUrl={portal.mapConfig.tileUrl}
              attribution={portal.mapConfig.attribution}
              ariaLabel={`Carte du circuit ${c.name}`}
              style={{ width: '100%', height: '100%' }}
            />
          </div>
          <div style={{ display: 'flex', gap: 18, flexWrap: 'wrap', fontSize: 14 }}>
            {c.distanceKm ? (
              <span>
                <b>{fmtDecimal(Number(c.distanceKm), Number(c.distanceKm) % 1 ? 1 : 0)}</b> km
              </span>
            ) : null}
            {c.durationText ? (
              <span>
                <b>{c.durationText}</b>
              </span>
            ) : null}
            <span>
              <b>{stops.length}</b> étapes
            </span>
            {c.travelMode ? <span style={{ color: 'var(--muted)' }}>{c.travelMode}</span> : null}
          </div>
          {c.description ? <p style={{ fontSize: 16, lineHeight: 1.6, margin: 0, maxWidth: 760 }}>{c.description}</p> : null}
          <ol style={{ display: 'flex', flexDirection: 'column', listStyle: 'none', padding: 0, margin: 0 }}>
            {stops.map((s, i) => {
              const on = stamped.includes(s.id);
              const inner = (
                <>
                  <div
                    className="display"
                    style={{
                      width: 36,
                      height: 36,
                      borderRadius: '50%',
                      background: 'var(--ink)',
                      color: 'var(--amber)',
                      display: 'grid',
                      placeItems: 'center',
                      fontSize: 15,
                    }}
                  >
                    {i + 1}
                  </div>
                  <div style={{ width: 80, height: 60, borderRadius: 10, overflow: 'hidden' }}>
                    <Photo src={sized(s.coverUrl, 200, 150)} alt="" color={s.color} label={s.name} />
                  </div>
                  <div>
                    <div style={{ fontWeight: 700 }}>{s.name}</div>
                    <div style={{ fontSize: 13, color: 'var(--muted)' }}>{[s.activity, s.communeName].filter(Boolean).join(' · ')}</div>
                  </div>
                  <span
                    style={{
                      fontSize: 12,
                      fontWeight: 700,
                      padding: '4px 9px',
                      borderRadius: 999,
                      background: on ? 'var(--leaf)' : 'var(--sand)',
                      whiteSpace: 'nowrap',
                    }}
                  >
                    {on ? 'Tamponné' : 'À visiter'}
                  </span>
                </>
              );
              return (
                <li key={s.id}>
                  {s.path ? (
                    <Link href={`${base}${s.path}?src=circuit`} className="stop-row">
                      {inner}
                    </Link>
                  ) : (
                    <div className="stop-row">{inner}</div>
                  )}
                </li>
              );
            })}
          </ol>
        </div>

        <div className="sticky-aside" style={{ display: 'flex', flexDirection: 'column', gap: 14, alignItems: 'center', position: 'sticky', top: 90 }}>
          <PassportPhone
            circuitId={c.id}
            name={c.name}
            reward={c.rewardText}
            threshold={c.rewardThreshold ?? stops.length}
            stops={stops.map((s) => ({ id: s.id, name: s.name }))}
            stamped={stamped}
            hasPassport={Boolean(passport)}
            rewardCode={passport?.rewardCode ?? null}
            demo={env.DEMO_MODE}
          />
          <div className="card" style={{ display: 'flex', gap: 12, alignItems: 'center', borderRadius: 16, padding: 12, width: 320, maxWidth: '100%' }}>
            <img src={qr} alt={`QR code du circuit ${c.name}`} width={72} height={72} style={{ flexShrink: 0 }} />
            <div style={{ fontSize: 13 }}>
              <b>Partager le circuit</b>
              <div style={{ color: 'var(--muted)' }}>À imprimer à l&apos;office de tourisme ou sur les panneaux.</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
