import type { Metadata } from 'next';
import Link from 'next/link';
import { FeedCard } from '@/components/portal/Cards';
import { POST_KINDS, POST_KIND_ORDER, type PostKind } from '@/lib/constants';
import { getFeed, getPortal } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string }>; searchParams: Promise<Record<string, string | undefined>> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory } = await params;
  const { territory: t } = await getPortal(territory);
  return {
    title: 'Actualités des pros',
    description: `Nouveautés, promotions, événements et recrutements des commerçants, artisans et producteurs de ${t.name}.`,
    alternates: { canonical: portalUrl(t, '/actualites') },
  };
}

export default async function NewsPage({ params, searchParams }: Props) {
  const { territory } = await params;
  const sp = await searchParams;
  const portal = await getPortal(territory);
  const { base, territory: t } = portal;
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
            Le fil du territoire
          </div>
          <h1 className="display" style={{ fontSize: 'clamp(38px,5vw,52px)', letterSpacing: '-0.035em', margin: 0, lineHeight: 1 }}>
            Quoi de neuf chez vos pros
          </h1>
        </div>
        <nav aria-label="Filtrer par type" style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
          <Link href={`${base}/actualites`} className={`chip${!kind ? ' is-active' : ''}`} aria-current={!kind ? 'page' : undefined}>
            Tout
          </Link>
          {(['PROMO', 'NOUVEAUTE', 'EVENT', 'HOURS', 'JOB', 'NEWS'] as PostKind[]).map((k) => (
            <Link
              key={k}
              href={`${base}/actualites?type=${k.toLowerCase()}`}
              className={`chip${kind === k ? ' is-active' : ''}`}
              aria-current={kind === k ? 'page' : undefined}
            >
              <span className="chip-dot" style={{ background: POST_KINDS[k].bg }} />
              {POST_KINDS[k].short}
            </Link>
          ))}
        </nav>
      </div>
      {shown.length ? (
        <div className="auto-grid" style={{ ['--min' as string]: '300px', ['--gap' as string]: '18px' }}>
          {shown.map((f) => (
            <FeedCard key={f.id} f={f} base={base} />
          ))}
        </div>
      ) : (
        <div className="card card-pad" style={{ color: 'var(--muted)' }}>
          Aucune publication de ce type pour le moment.
        </div>
      )}
      {feed.length > limit && !kind ? (
        <div style={{ textAlign: 'center', marginTop: 24 }}>
          <Link href={`${base}/actualites?n=${limit + 24}`} className="btn btn-outline" scroll={false}>
            Voir plus d&apos;actualités
          </Link>
        </div>
      ) : null}
    </div>
  );
}
