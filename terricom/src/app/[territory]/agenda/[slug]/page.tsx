import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { cache } from 'react';
import { JsonLd } from '@/components/JsonLd';
import { MapView } from '@/components/maps/MapView';
import { Beacon } from '@/components/portal/Beacon';
import { DateBox } from '@/components/portal/Cards';
import { EventActions } from '@/components/portal/EventActions';
import { Photo } from '@/components/ui/Photo';
import { EVENT_KINDS, EVENT_PROGRAM_TEMPLATES, type EventKind } from '@/lib/constants';
import { directionsHref, truncate } from '@/lib/format';
import { activityL, dayMonthL, EVENT_PROGRAM_NAMES, eventHoursL, eventKindL, longDateL } from '@/lib/i18n/format';
import { withLang } from '@/lib/i18n';
import { portalT } from '@/server/i18n';
import { sized } from '@/lib/images';
import type { TerritorySettings } from '@/server/db/schema';
import { eventJsonLd } from '@/server/seo';
import { getPortal, getPublicEvent, upcomingEvents } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string; slug: string }> };

const load = cache(async (territoryParam: string, slug: string) => {
  const portal = await getPortal(territoryParam);
  return { portal, data: await getPublicEvent(portal.territory.id, slug) };
});

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory, slug } = await params;
  const { portal, data } = await load(territory, slug);
  const tr = await portalT(portal);
  if (!data) return { title: tr('event.notFound'), robots: { index: false } };
  const ev = data.event;
  const L = tr.locale;
  const description = truncate(
    `${longDateL(ev.startsAt, L)}, ${eventHoursL(ev.startsAt, ev.endsAt, L)} · ${ev.locationName ?? ''}. ${ev.summary ?? ev.description}`,
    158,
  );
  const image = sized(ev.imageUrl ?? data.organizer?.coverUrl, 1200);
  return {
    title: ev.title,
    description,
    alternates: { canonical: withLang(portalUrl(portal.territory, `/agenda/${ev.slug}`), tr.locale) },
    openGraph: { title: ev.title, description, type: 'website', images: image ? [{ url: image }] : undefined },
    robots: ev.endsAt && ev.endsAt.getTime() < Date.now() - 30 * 86_400_000 ? { index: false, follow: true } : undefined,
  };
}

export default async function EventPage({ params }: Props) {
  const { territory, slug } = await params;
  const { portal, data } = await load(territory, slug);
  if (!data) notFound();
  const { base, territory: t } = portal;
  const tr = await portalT(portal);
  const L = tr.locale;
  const { event: ev, organizer, commune } = data;
  const kind = ev.kind as EventKind;
  const k = EVENT_KINDS[kind];
  const kindLabel = eventKindL(kind, L, EVENT_KINDS);
  const url = portalUrl(t, `/agenda/${ev.slug}`);
  const settings = (t.settings ?? {}) as TerritorySettings;
  const lat = ev.lat ?? organizer?.lat ?? null;
  const lng = ev.lng ?? organizer?.lng ?? null;
  const program = ev.program.length ? ev.program : ((L === 'fr' ? EVENT_PROGRAM_TEMPLATES : EVENT_PROGRAM_NAMES[L])[kind] ?? []);
  const others = (await upcomingEvents(t.id, { limit: 12 })).filter((r) => r.ev.id !== ev.id).slice(0, 4);
  const where = organizer ? `${organizer.name} · ${organizer.communeName}` : [ev.locationName, commune?.name].filter(Boolean).join(' · ');
  const image = ev.imageUrl ?? organizer?.coverUrl ?? null;

  return (
    <div>
      <Beacon type="EVENT_VIEW" territoryId={t.id} establishmentId={organizer?.id} refId={ev.id} />
      <JsonLd
        data={eventJsonLd({
          title: ev.title,
          description: ev.summary ?? ev.description,
          startsAt: ev.startsAt,
          endsAt: ev.endsAt,
          url,
          image: sized(image, 1400),
          locationName: ev.locationName,
          address: ev.address,
          lat,
          lng,
          priceText: ev.priceText,
          organizer: organizer ? { name: organizer.name, url: portalUrl(t, organizer.path) } : ev.organizerName ? { name: ev.organizerName } : null,
        })}
      />
      <div
        className="container"
        style={{ paddingTop: 18, display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap', fontSize: 13, color: 'var(--muted)' }}
      >
        <Link href={`${base}/agenda`} style={{ color: 'var(--green)', fontWeight: 700 }}>
          {tr('event.back')}
        </Link>
        <span>
          {tr('agenda.eyebrow')} › {kindLabel} › {ev.title}
        </span>
      </div>
      <div className="container split" style={{ marginTop: 14, ['--cols' as string]: 'minmax(0,1.6fr) minmax(320px,1fr)', ['--gap' as string]: '18px' }}>
        <div style={{ position: 'relative', borderRadius: 24, overflow: 'hidden', minHeight: 440, color: '#fff' }}>
          <Photo src={sized(image, 1400, 800)} alt="" eager color="#2A3A33" label=" " style={{ position: 'absolute', inset: 0 }} />
          <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,rgba(20,32,27,0) 35%,rgba(20,32,27,.9))' }} />
          <span
            className="display"
            style={{
              position: 'absolute',
              left: 22,
              top: 22,
              background: k.bg,
              color: 'var(--ink)',
              fontSize: 14,
              padding: '7px 12px',
              borderRadius: 10,
              transform: 'rotate(-3deg)',
            }}
          >
            {kindLabel}
          </span>
          <div style={{ position: 'absolute', left: 28, right: 28, bottom: 26 }}>
            <h1 className="display" style={{ fontSize: 'clamp(36px,4.4vw,60px)', letterSpacing: '-0.035em', lineHeight: 0.95, margin: '0 0 10px' }}>
              {ev.title}
            </h1>
            <div style={{ fontSize: 16, color: 'var(--sage-4)' }}>{where}</div>
          </div>
        </div>
        <aside className="card" style={{ borderRadius: 24, padding: 24, display: 'flex', flexDirection: 'column', gap: 16 }}>
          <div style={{ display: 'flex', gap: 16, alignItems: 'center' }}>
            <DateBox date={ev.startsAt} kind={kind} big L={L} />
            <div>
              <div style={{ fontWeight: 700, fontSize: 17 }}>{longDateL(ev.startsAt, L)}</div>
              <div style={{ color: 'var(--muted)', fontSize: 15 }}>{ev.allDay ? tr('event.allDay') : eventHoursL(ev.startsAt, ev.endsAt, L)}</div>
            </div>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 14, borderTop: '1px solid var(--line-2)', paddingTop: 14 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10 }}>
              <span style={{ color: 'var(--muted)' }}>{tr('event.place')}</span>
              <b style={{ textAlign: 'right' }}>{ev.address ?? ev.locationName ?? commune?.name}</b>
            </div>
            {ev.priceText ? (
              <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10 }}>
                <span style={{ color: 'var(--muted)' }}>{tr('event.price')}</span>
                <b style={{ textAlign: 'right' }}>{ev.priceText}</b>
              </div>
            ) : null}
            {ev.accessibilityText ? (
              <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10 }}>
                <span style={{ color: 'var(--muted)' }}>{tr('fiche.accessibility')}</span>
                <b style={{ textAlign: 'right' }}>{ev.accessibilityText}</b>
              </div>
            ) : null}
            {ev.capacity ? (
              <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10 }}>
                <span style={{ color: 'var(--muted)' }}>{tr('event.capacity')}</span>
                <b>{ev.capacity}</b>
              </div>
            ) : null}
          </div>
          <EventActions
            id={ev.id}
            icsHref={`${base}/agenda/${ev.slug}/ics`}
            directionsUrl={lat && lng ? directionsHref(lat, lng, settings.directionsProvider) : null}
            shareUrl={url}
            title={ev.title}
          />
          {ev.registrationUrl ? (
            <a href={ev.registrationUrl} target="_blank" rel="noopener noreferrer" className="btn btn-dark" style={{ justifyContent: 'center' }}>
              {tr('event.register')}
            </a>
          ) : null}
          {lat && lng ? (
            <div style={{ height: 170, borderRadius: 14, overflow: 'hidden' }}>
              <MapView
                mode="fiche"
                focusId={ev.id}
                points={[{ id: ev.id, lat, lng, name: ev.title, color: '#C8702A' }]}
                tileUrl={portal.mapConfig.tileUrl}
                attribution={portal.mapConfig.attribution}
                ariaLabel={tr('event.mapAria')}
                style={{ width: '100%', height: '100%' }}
              />
            </div>
          ) : null}
        </aside>
      </div>

      <div
        className="container split"
        style={{ paddingTop: 30, paddingBottom: 60, ['--cols' as string]: 'minmax(0,1.6fr) minmax(320px,1fr)', ['--align' as string]: 'start' }}
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: 26, minWidth: 0 }}>
          <div>
            <p lang={L === 'fr' ? undefined : 'fr'} style={{ fontSize: 19, lineHeight: 1.6, margin: 0, textWrap: 'pretty', whiteSpace: 'pre-line' }}>
              {ev.description || ev.summary}
            </p>
            {L !== 'fr' && (ev.description || ev.summary) ? (
              <p style={{ fontSize: 12, color: 'var(--muted)', margin: '8px 0 0' }}>{tr('common.originalFrench')}</p>
            ) : null}
          </div>
          {program.length ? (
            <div lang={L !== 'fr' && ev.program.length ? 'fr' : undefined}>
              <h2 className="h3" style={{ fontSize: 26, margin: '0 0 12px' }}>
                {tr('event.program')}
              </h2>
              {program.map((p, i) => (
                <div
                  key={i}
                  style={{ display: 'grid', gridTemplateColumns: '140px 1fr', gap: 16, padding: '14px 0', borderTop: '1px solid var(--line)', fontSize: 15 }}
                >
                  <b style={{ color: 'var(--brick)' }}>{p.label}</b>
                  <span>{p.text}</span>
                </div>
              ))}
            </div>
          ) : null}
          {organizer ? (
            <Link
              href={`${base}${organizer.path}`}
              className="card card-link"
              style={{ display: 'grid', gridTemplateColumns: '72px 1fr auto', gap: 16, alignItems: 'center', borderRadius: 18, padding: 14 }}
            >
              <div style={{ width: 72, height: 72, borderRadius: 14, overflow: 'hidden' }}>
                <Photo src={sized(organizer.coverUrl, 160, 160)} alt="" color={organizer.color} label={organizer.name} />
              </div>
              <div>
                <div style={{ fontSize: 12, fontWeight: 800, color: 'var(--muted)', textTransform: 'uppercase', letterSpacing: '0.06em' }}>
                  {tr('event.organizedBy')}
                </div>
                <div className="display" style={{ fontSize: 20 }}>
                  {organizer.name}
                </div>
                <div style={{ fontSize: 13, color: 'var(--muted)' }}>
                  {activityL(organizer, L)} · {organizer.communeName}
                </div>
              </div>
              <span style={{ fontWeight: 700, color: 'var(--green)', fontSize: 14 }}>{tr('event.seeListing')}</span>
            </Link>
          ) : ev.organizerName ? (
            <div className="card" style={{ borderRadius: 18, padding: 18 }}>
              <div style={{ fontSize: 12, fontWeight: 800, color: 'var(--muted)', textTransform: 'uppercase', letterSpacing: '0.06em' }}>
                {tr('event.organizedBy')}
              </div>
              <div className="display" style={{ fontSize: 20 }}>
                {ev.organizerName}
              </div>
            </div>
          ) : null}
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          {others.length ? <b style={{ fontSize: 16 }}>{tr('event.also')}</b> : null}
          {others.map(({ ev: o, e, c }) => (
            <Link
              key={o.id}
              href={`${base}/agenda/${o.slug}`}
              className="card card-link"
              style={{ display: 'grid', gridTemplateColumns: '64px minmax(0,1fr)', gap: 12, alignItems: 'center', borderRadius: 14, padding: 8 }}
            >
              <div style={{ width: 64, height: 64, borderRadius: 10, overflow: 'hidden' }}>
                <Photo src={sized(o.imageUrl ?? e?.coverUrl, 200, 200)} alt="" color="#C8702A" label={o.title} />
              </div>
              <div style={{ minWidth: 0 }}>
                <div style={{ fontSize: 12, fontWeight: 800, color: 'var(--brick)' }}>
                  {dayMonthL(o.startsAt, L)} · {eventKindL(o.kind as EventKind, L, EVENT_KINDS)}
                </div>
                <div style={{ fontWeight: 700, fontSize: 14 }}>{o.title}</div>
                <div style={{ fontSize: 12, color: 'var(--muted)' }}>{[e?.name ?? o.locationName, c?.name].filter(Boolean).join(' · ')}</div>
              </div>
            </Link>
          ))}
        </div>
      </div>
    </div>
  );
}
