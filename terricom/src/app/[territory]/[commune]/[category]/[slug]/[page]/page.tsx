import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound, permanentRedirect } from 'next/navigation';
import { cache } from 'react';
import { JsonLd } from '@/components/JsonLd';
import { Beacon } from '@/components/portal/Beacon';
import { EstSiteNav } from '@/components/portal/EstSiteNav';
import { FicheActions } from '@/components/portal/FicheClient';
import { FollowCard } from '@/components/portal/FicheExtras';
import { RichText } from '@/components/portal/RichText';
import { Photo } from '@/components/ui/Photo';
import { isHexColor } from '@/lib/color';
import { themeStyle } from '@/lib/minisite';
import { directionsHref, fmtPhone, truncate } from '@/lib/format';
import { sized } from '@/lib/images';
import type { MiniSite, TerritorySettings } from '@/server/db/schema';
import { breadcrumbJsonLd } from '@/server/seo';
import { planLimits } from '@/server/services/billing';
import { getPublicEstablishment } from '@/server/services/establishments';
import { getPortal } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string; commune: string; category: string; slug: string; page: string }> };

const load = cache(async (territoryParam: string, commune: string, slug: string, pageSlug: string) => {
  const portal = await getPortal(territoryParam);
  const e = await getPublicEstablishment(portal.territory.id, commune, slug);
  if (!e) return { portal, e: null, page: null, limits: null };
  const limits = await planLimits(e.plan);
  const page = limits.extraPages ? (e.pages.find((p) => p.slug === pageSlug) ?? null) : null;
  return { portal, e, page, limits };
});

const plain = (body: string) =>
  body
    .replace(/[#*[\]()]/g, '')
    .replace(/\s+/g, ' ')
    .trim();

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory, commune, slug, page: pageSlug } = await params;
  const { portal, e, page } = await load(territory, commune, slug, pageSlug);
  if (!e || !page) return { title: 'Page introuvable', robots: { index: false } };
  const url = portalUrl(portal.territory, `${e.path}/${page.slug}`);
  const title = `${page.title} — ${e.name}`;
  const description = truncate(plain(page.body) || `${e.name}, ${e.activity} à ${e.commune.name}.`, 158);
  return {
    title: { absolute: `${title} · ${portal.territory.name}` },
    description,
    alternates: { canonical: url },
    openGraph: {
      type: 'article',
      title,
      description,
      url,
      siteName: portal.territory.name,
      locale: 'fr_FR',
      images: page.coverUrl ? [{ url: sized(page.coverUrl, 1400)! }] : undefined,
    },
  };
}

export default async function EstablishmentExtraPage({ params }: Props) {
  const { territory, commune, category, slug, page: pageSlug } = await params;
  const { portal, e, page, limits } = await load(territory, commune, slug, pageSlug);
  if (!e || !page || !limits) notFound();
  const { base, territory: t } = portal;
  if (category !== e.category.slug) permanentRedirect(`${base}${e.path}/${page.slug}`);
  const settings = (t.settings ?? {}) as TerritorySettings;
  const mini = (e.miniSite ?? {}) as MiniSite;
  const site = Boolean(limits.miniSite && mini.enabled);
  const theme = site && isHexColor(e.themeColor) ? e.themeColor : null;
  const themeVars = themeStyle(theme);
  const pages = e.pages;
  const url = portalUrl(t, `${e.path}/${page.slug}`);
  const claimed = ['CLAIMED', 'VALIDATED'].includes(e.status);

  return (
    <div style={themeVars} className={site ? 'est-site' : undefined}>
      <JsonLd
        data={breadcrumbJsonLd([
          { name: t.name, url: portalUrl(t, '/') },
          { name: e.commune.name, url: portalUrl(t, `/${e.commune.slug}`) },
          { name: e.name, url: portalUrl(t, e.path) },
          { name: page.title, url },
        ])}
      />
      <Beacon type="EST_VIEW" establishmentId={e.id} territoryId={t.id} communeId={e.communeId} />
      <div
        className="container"
        style={{ paddingTop: 18, display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap', fontSize: 13, color: 'var(--muted)' }}
      >
        <Link href={`${base}${e.path}`} style={{ color: 'var(--green)', fontWeight: 700 }}>
          ← {e.name}
        </Link>
        <nav aria-label="Fil d'Ariane">
          <Link href={base || '/'} style={{ color: 'inherit' }}>
            {t.name}
          </Link>{' '}
          ›{' '}
          <Link href={`${base}/${e.commune.slug}`} style={{ color: 'inherit' }}>
            {e.commune.name}
          </Link>{' '}
          ›{' '}
          <Link href={`${base}${e.path}`} style={{ color: 'inherit' }}>
            {e.name}
          </Link>
        </nav>
      </div>
      <EstSiteNav base={base} path={e.path} name={e.name} pages={pages} current={page.slug} />

      <div
        className="container split"
        style={{ ['--cols' as string]: 'minmax(0,1fr) 340px', ['--gap' as string]: '40px', ['--align' as string]: 'start', paddingTop: 26, paddingBottom: 60 }}
      >
        <article style={{ display: 'flex', flexDirection: 'column', gap: 18, minWidth: 0 }}>
          <div style={{ display: 'flex', gap: 14, alignItems: 'center' }}>
            {e.logoUrl ? (
              <img
                src={sized(e.logoUrl, 120, 120) ?? e.logoUrl}
                alt=""
                width={48}
                height={48}
                style={{ width: 48, height: 48, borderRadius: 14, objectFit: 'contain', background: '#fff', border: '1px solid var(--line)' }}
              />
            ) : null}
            <span style={{ fontSize: 12, fontWeight: 800, color: e.color, textTransform: 'uppercase', letterSpacing: '0.07em' }}>
              {e.name} · {e.commune.name}
            </span>
          </div>
          <h1 className="display" style={{ fontSize: 'clamp(36px,4.2vw,58px)', letterSpacing: '-0.035em', lineHeight: 0.98, margin: 0 }}>
            {page.title}
          </h1>
          {page.coverUrl ? (
            <div style={{ height: 'clamp(200px,32vw,380px)', borderRadius: 22, overflow: 'hidden' }}>
              <Photo src={sized(page.coverUrl, 1400, 600)} alt="" color={e.color} label={page.title} eager />
            </div>
          ) : null}
          <RichText text={page.body} />
          <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', marginTop: 10 }}>
            <Link href={`${base}${e.path}`} className="btn btn-outline btn-sm">
              Voir toute la fiche
            </Link>
            <Link href={`${base}${e.path}#message`} className="btn btn-brand btn-sm">
              Écrire à {e.name}
            </Link>
          </div>
        </article>
        <aside className="sticky-aside" style={{ position: 'sticky', top: 84, display: 'flex', flexDirection: 'column', gap: 14 }}>
          <div className="card" style={{ borderRadius: 20, padding: 20, display: 'flex', flexDirection: 'column', gap: 14 }}>
            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 8,
                fontWeight: 700,
                color: e.open.unknown ? 'var(--muted)' : e.open.open ? 'var(--open)' : 'var(--closed)',
              }}
            >
              <span
                className={e.open.open ? 'live-dot' : undefined}
                style={{
                  width: 9,
                  height: 9,
                  borderRadius: '50%',
                  background: e.open.unknown ? 'var(--faint)' : e.open.open ? 'var(--open)' : 'var(--closed)',
                }}
              />
              {e.open.unknown ? 'Horaires non renseignés' : e.open.longLabel}
            </div>
            <FicheActions
              establishmentId={e.id}
              territoryId={t.id}
              phone={e.phone}
              directionsUrl={e.lat && e.lng ? directionsHref(e.lat, e.lng, settings.directionsProvider) : null}
              website={e.website}
              shareUrl={url}
              name={e.name}
            />
            <address style={{ fontSize: 14, lineHeight: 1.5, fontStyle: 'normal' }}>
              <div style={{ fontWeight: 600 }}>
                {[e.street, [e.postalCode ?? e.commune.postalCodes?.[0], e.commune.name].filter(Boolean).join(' ')].filter(Boolean).join(', ')}
              </div>
              {e.phone ? <div style={{ color: 'var(--muted)' }}>{fmtPhone(e.phone)}</div> : null}
            </address>
          </div>
          {limits.customerNewsletter && claimed ? <FollowCard establishmentId={e.id} name={e.name} /> : null}
        </aside>
      </div>
    </div>
  );
}
