import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { cache } from 'react';
import { JsonLd } from '@/components/JsonLd';
import { MapView } from '@/components/maps/MapView';
import { ResultRow } from '@/components/portal/Cards';
import { categoryNameL, intL } from '@/lib/i18n/format';
import { withLang } from '@/lib/i18n';
import { portalT } from '@/server/i18n';
import { breadcrumbJsonLd } from '@/server/seo';
import { territoryCategoryList } from '@/server/services/categories';
import { allCards, getPortal, toMapPoints } from '@/server/services/portal';
import { getCommuneInTerritory } from '@/server/services/territories';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string; commune: string; category: string }> };

const load = cache(async (territoryParam: string, communeSlug: string, categorySlug: string) => {
  const portal = await getPortal(territoryParam);
  const commune = await getCommuneInTerritory(portal.territory.id, communeSlug);
  // Catégorie commune ou propre au territoire, avec le nom affiché choisi par la collectivité.
  const category = (await territoryCategoryList(portal.territory.id)).find((c) => c.slug === categorySlug) ?? null;
  if (!commune || !category) return { portal, commune: null, category: null, items: [] };
  const cards = await allCards(portal.territory.id);
  const items = cards
    .filter((c) => c.communeId === commune.id && c.categorySlug === category.slug)
    .sort((a, b) => Number(b.open.open) - Number(a.open.open) || b.completeness - a.completeness);
  return { portal, commune, category, items };
});

/** Page de destination « catégorie × commune » (ex. : boulangeries à Ornans), utile au référencement local. */
export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory, commune, category } = await params;
  const d = await load(territory, commune, category);
  const tr = await portalT(d.portal);
  if (!d.commune || !d.category) return { title: tr('common.notFound'), robots: { index: false } };
  const catName = categoryNameL(d.category.slug, d.category.name, tr.locale);
  const title = tr('category.title', { category: catName, commune: d.commune.name });
  return {
    title,
    description:
      tr.locale === 'fr'
        ? `${intL(d.items.length, 'fr')} ${d.category.name.toLowerCase()} à ${d.commune.name} : horaires d'ouverture, adresses, téléphones et actualités.`
        : tr('category.metaDesc', { n: intL(d.items.length, tr.locale), category: catName, commune: d.commune.name }),
    alternates: { canonical: withLang(portalUrl(d.portal.territory, `/${d.commune.slug}/${d.category.slug}`), tr.locale) },
    robots: d.items.length ? undefined : { index: false, follow: true },
  };
}

export default async function CategoryPage({ params }: Props) {
  const { territory, commune, category } = await params;
  const d = await load(territory, commune, category);
  if (!d.commune || !d.category) notFound();
  const { base, territory: t } = d.portal;
  const tr = await portalT(d.portal);
  const L = tr.locale;
  const categoryName = categoryNameL(d.category.slug, d.category.name, L);
  return (
    <div className="container" style={{ paddingTop: 28, paddingBottom: 60 }}>
      <JsonLd
        data={breadcrumbJsonLd([
          { name: t.name, url: portalUrl(t, '/') },
          { name: d.commune.name, url: portalUrl(t, `/${d.commune.slug}`) },
          { name: categoryName, url: portalUrl(t, `/${d.commune.slug}/${d.category.slug}`) },
        ])}
      />
      <nav aria-label={tr('fiche.breadcrumb')} style={{ fontSize: 13, color: 'var(--muted)', marginBottom: 14 }}>
        <Link href={base || '/'} style={{ color: 'inherit' }}>
          {t.name}
        </Link>{' '}
        ›{' '}
        <Link href={`${base}/${d.commune.slug}`} style={{ color: 'inherit' }}>
          {d.commune.name}
        </Link>{' '}
        › {categoryName}
      </nav>
      <div className="eyebrow" style={{ marginBottom: 6 }}>
        {tr.n('common.places', d.items.length, { n: intL(d.items.length, L) })}
      </div>
      <h1 className="h-page" style={{ margin: '0 0 24px' }}>
        {tr('category.title', { category: categoryName, commune: d.commune.name })}
      </h1>
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1fr)', ['--align' as string]: 'start' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          {d.items.length ? (
            d.items.map((e) => <ResultRow key={e.id} e={e} href={`${base}${e.path}`} L={L} />)
          ) : (
            <div className="card card-pad" style={{ color: 'var(--muted)' }}>
              {tr('category.empty')} <Link href={`${base}/explorer?commune=${d.commune.slug}`}>{tr('category.seeAll', { name: d.commune.name })}</Link>
            </div>
          )}
        </div>
        <div
          className="sticky-aside"
          style={{ position: 'sticky', top: 90, height: 460, borderRadius: 20, overflow: 'hidden', border: '1px solid var(--line)' }}
        >
          <MapView
            mode="mini"
            points={toMapPoints(d.items, base, L)}
            center={d.commune.lat && d.commune.lng ? [d.commune.lat, d.commune.lng] : undefined}
            zoom={14}
            tileUrl={d.portal.mapConfig.tileUrl}
            attribution={d.portal.mapConfig.attribution}
            ariaLabel={tr('category.mapAria', { category: categoryName, commune: d.commune.name })}
            style={{ width: '100%', height: '100%' }}
          />
        </div>
      </div>
    </div>
  );
}
