import type { Metadata } from 'next';
import Link from 'next/link';
import { EventRow } from '@/components/portal/Cards';
import { Icon } from '@/components/ui/Icon';
import { Photo } from '@/components/ui/Photo';
import { EVENT_KINDS, type EventKind } from '@/lib/constants';
import { truncate } from '@/lib/format';
import { eventBadgeL, eventKindL } from '@/lib/i18n/format';
import { withLang } from '@/lib/i18n';
import { portalT } from '@/server/i18n';
import { sized } from '@/lib/images';
import { getPortal, upcomingEvents } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string }>; searchParams: Promise<Record<string, string | undefined>> };

const FILTERS: (EventKind | null)[] = [null, 'MARCHE', 'PORTES_OUVERTES', 'DEGUSTATION', 'ATELIER'];

const slugOf = (k: EventKind) => k.toLowerCase().replace(/_/g, '-');

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory } = await params;
  const portal = await getPortal(territory);
  const { territory: t } = portal;
  const tr = await portalT(portal);
  return {
    title: tr('agenda.metaTitle'),
    description: tr('agenda.metaDesc', { name: t.name }),
    alternates: { canonical: withLang(portalUrl(t, '/agenda'), tr.locale) },
  };
}

export default async function AgendaPage({ params, searchParams }: Props) {
  const { territory } = await params;
  const sp = await searchParams;
  const portal = await getPortal(territory);
  const { base, territory: t } = portal;
  const tr = await portalT(portal);
  const L = tr.locale;
  const all = await upcomingEvents(t.id, { limit: 200 });
  const active = FILTERS.find((f) => f && slugOf(f) === sp.type) ?? null;
  const list = active ? all.filter((r) => r.ev.kind === active) : all;
  const featured = all.find((r) => r.ev.isFeatured) ?? all[0];
  const others = list.filter((r) => r.ev.id !== featured?.ev.id);

  return (
    <div className="container" style={{ paddingTop: 36, paddingBottom: 60 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'end', gap: 16, flexWrap: 'wrap', marginBottom: 26 }}>
        <div>
          <div className="eyebrow" style={{ marginBottom: 6 }}>
            {tr('agenda.eyebrow')}
          </div>
          <h1 className="display" style={{ fontSize: 'clamp(38px,5vw,52px)', letterSpacing: '-0.035em', margin: 0, lineHeight: 1 }}>
            {tr('agenda.h1')}
          </h1>
          <a
            href={portalUrl(t, '/agenda.ics').replace(/^https?:/, 'webcal:')}
            className="btn-link"
            title={tr('agenda.subscribeHint')}
            style={{ display: 'inline-flex', gap: 6, alignItems: 'center', marginTop: 10, fontSize: 14 }}
          >
            <Icon name="calendar" size={15} /> {tr('agenda.subscribe')}
          </a>
        </div>
        <nav aria-label={tr('agenda.filterAria')} style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
          {FILTERS.map((f) => {
            const on = f === active;
            const n = f ? all.filter((r) => r.ev.kind === f).length : all.length;
            return (
              <Link
                key={f ?? 'all'}
                href={f ? `${base}/agenda?type=${slugOf(f)}` : `${base}/agenda`}
                aria-current={on ? 'page' : undefined}
                scroll={false}
                style={{
                  display: 'flex',
                  gap: 6,
                  alignItems: 'center',
                  border: `1.5px solid ${on ? 'var(--ink)' : 'var(--line)'}`,
                  background: on ? 'var(--ink)' : 'transparent',
                  color: on ? '#fff' : 'var(--text)',
                  padding: '8px 14px',
                  borderRadius: 999,
                  fontWeight: 700,
                  fontSize: 13,
                }}
              >
                {f ? eventKindL(f, L, EVENT_KINDS, true) : tr('agenda.all')}
                <span style={{ fontSize: 11, opacity: 0.7 }}>{n}</span>
              </Link>
            );
          })}
        </nav>
      </div>

      {featured ? (
        <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.3fr) minmax(0,1fr)', ['--gap' as string]: '22px', marginBottom: 30 }}>
          <Link
            href={`${base}/agenda/${featured.ev.slug}`}
            className="card-lift"
            style={{ position: 'relative', borderRadius: 24, overflow: 'hidden', minHeight: 380, color: '#fff', display: 'block' }}
          >
            <Photo
              src={sized(featured.ev.imageUrl ?? featured.e?.coverUrl, 1400)}
              alt=""
              eager
              color="#2A3A33"
              label=" "
              style={{ position: 'absolute', inset: 0 }}
            />
            <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,transparent 30%,rgba(20,32,27,.9))' }} />
            <div style={{ position: 'absolute', left: 26, bottom: 24, right: 26 }}>
              <span style={{ background: 'var(--amber)', color: 'var(--ink)', fontWeight: 800, fontSize: 12, padding: '5px 10px', borderRadius: 6 }}>
                {eventBadgeL(featured.ev.startsAt, featured.ev.endsAt, L)}
              </span>
              <div className="display" style={{ fontSize: 40, letterSpacing: '-0.03em', marginTop: 10, lineHeight: 1 }}>
                {featured.ev.title}
              </div>
              <div style={{ color: 'var(--sage-4)', marginTop: 6 }}>{featured.ev.summary ?? truncate(featured.ev.description, 110)}</div>
            </div>
          </Link>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            {others.length ? (
              others.map(({ ev, e, c }) => (
                <EventRow
                  key={ev.id}
                  href={`${base}/agenda/${ev.slug}`}
                  title={ev.title}
                  where={[e?.name ?? ev.locationName, c?.name].filter(Boolean).join(' · ')}
                  kind={ev.kind as EventKind}
                  startsAt={ev.startsAt}
                  L={L}
                />
              ))
            ) : (
              <div className="card card-pad" style={{ color: 'var(--muted)' }}>
                {active
                  ? tr('agenda.noOtherKind', { kind: L === 'fr' ? EVENT_KINDS[active].label.toLowerCase() : eventKindL(active, L, EVENT_KINDS) })
                  : tr('agenda.noOther')}
              </div>
            )}
          </div>
        </div>
      ) : (
        <div className="card card-pad" style={{ color: 'var(--muted)' }}>
          {tr('agenda.none')}
        </div>
      )}
    </div>
  );
}
