import type { Metadata } from 'next';
import Link from 'next/link';
import { FeedCard } from '@/components/portal/Cards';
import { POST_KINDS, POST_KIND_ORDER, type PostKind } from '@/lib/constants';
import { postKindL } from '@/lib/i18n/format';
import { withLang } from '@/lib/i18n';
import { portalT } from '@/server/i18n';
import { getFeed, getPortal } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string }>; searchParams: Promise<Record<string, string | undefined>> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory } = await params;
  const portal = await getPortal(territory);
  const { territory: t } = portal;
  const tr = await portalT(portal);
  return {
    title: tr('news.metaTitle'),
    description: tr('news.metaDesc', { name: t.name }),
    alternates: { canonical: withLang(portalUrl(t, '/actualites'), tr.locale) },
  };
}

export default async function NewsPage({ params, searchParams }: Props) {
  const { territory } = await params;
  const sp = await searchParams;
  const portal = await getPortal(territory);
  const { base, territory: t } = portal;
  const tr = await portalT(portal);
  const L = tr.locale;
  const kind = POST_KIND_ORDER.find((k) => k.toLowerCase() === sp.type) ?? null;
  const limit = Math.min(120, Math.max(24, Number(sp.n) || 24));
  const feed = await getFeed(t.id, { limit: limit + 1, channel: 'TERRITOIRE' });
  const filtered = kind ? feed.filter((f) => f.kind === kind) : feed;
  const shown = filtered.slice(0, limit);
  return (
    <div className="container" style={{ paddingTop: 36, paddingBottom: 60 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'end', gap: 16, flexWrap: 'wrap', marginBottom: 24 }}>
        <div>
          <div className="eyebrow" style={{ marginBottom: 6 }}>
            {tr('home.feedEyebrow')}
          </div>
          <h1 className="display" style={{ fontSize: 'clamp(38px,5vw,52px)', letterSpacing: '-0.035em', margin: 0, lineHeight: 1 }}>
            {tr('home.feedTitle')}
          </h1>
        </div>
        <nav aria-label={tr('agenda.filterAria')} style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
          <Link href={`${base}/actualites`} className={`chip${!kind ? ' is-active' : ''}`} aria-current={!kind ? 'page' : undefined}>
            {tr('agenda.all')}
          </Link>
          {(['PROMO', 'NOUVEAUTE', 'EVENT', 'HOURS', 'JOB', 'NEWS'] as PostKind[]).map((k) => (
            <Link
              key={k}
              href={`${base}/actualites?type=${k.toLowerCase()}`}
              className={`chip${kind === k ? ' is-active' : ''}`}
              aria-current={kind === k ? 'page' : undefined}
            >
              <span className="chip-dot" style={{ background: POST_KINDS[k].bg }} />
              {postKindL(k, POST_KINDS[k].short, L)}
            </Link>
          ))}
          <a href={portalUrl(t, '/actualites.xml')} className="chip" title={tr('feeds.newsTitle', { name: t.name })}>
            {tr('news.rss')}
          </a>
        </nav>
      </div>
      {shown.length ? (
        <div className="auto-grid" style={{ ['--min' as string]: '300px', ['--gap' as string]: '18px' }}>
          {shown.map((f) => (
            <FeedCard key={f.id} f={f} base={base} L={L} />
          ))}
        </div>
      ) : (
        <div className="card card-pad" style={{ color: 'var(--muted)' }}>
          {tr('news.empty')}
        </div>
      )}
      {feed.length > limit && !kind ? (
        <div style={{ textAlign: 'center', marginTop: 24 }}>
          <Link href={`${base}/actualites?n=${limit + 24}`} className="btn btn-outline" scroll={false}>
            {tr('news.more')}
          </Link>
        </div>
      ) : null}
    </div>
  );
}
