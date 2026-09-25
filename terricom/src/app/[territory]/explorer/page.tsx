import type { Metadata } from 'next';
import { ExplorerClient } from '@/components/portal/ExplorerClient';
import { isFilteredState, parseExplorerParams, toSearchParams } from '@/lib/explorer';
import { allCards, explorerFilters, getPortal, mapOverlays, toMapPoints } from '@/server/services/portal';
import { searchTerritory, toExplorerItem } from '@/server/services/search';
import { getTerritoryCommunes } from '@/server/services/territories';
import { attributeNameL } from '@/lib/i18n/format';
import { withLang } from '@/lib/i18n';
import { portalT } from '@/server/i18n';
import { portalUrl } from '@/server/urls';

type Props = {
  params: Promise<{ territory: string }>;
  searchParams: Promise<Record<string, string | string[] | undefined>>;
};

export async function generateMetadata({ params, searchParams }: Props): Promise<Metadata> {
  const { territory } = await params;
  const state = parseExplorerParams(await searchParams);
  const portal = await getPortal(territory);
  const t = portal.territory;
  const tr = await portalT(portal);
  const filtered = isFilteredState(state);
  return {
    title: state.q ? `« ${state.q} »` : tr('explorer.metaTitle'),
    description: tr('explorer.metaDesc', { name: t.name }),
    alternates: { canonical: withLang(portalUrl(t, '/explorer'), tr.locale) },
    robots: filtered ? { index: false, follow: true } : undefined,
  };
}

export default async function ExplorerPage({ params, searchParams }: Props) {
  const { territory } = await params;
  const portal = await getPortal(territory);
  const state = parseExplorerParams(await searchParams);
  const t = portal.territory;
  const ai = portal.modules.has('AI');
  const tr = await portalT(portal);
  const L = tr.locale;
  const [res, cards, communes, filters, overlays] = await Promise.all([
    searchTerritory(t, { ...toSearchParams(state), limit: 5000, ai }),
    allCards(t.id),
    getTerritoryCommunes(t.id),
    explorerFilters(t.id),
    mapOverlays(t.id, portal.base, L),
  ]);
  const filtered = isFilteredState(state);
  return (
    <>
      <h1 className="sr-only">{state.q ? tr('explorer.h1Results', { q: state.q, name: t.name }) : tr('explorer.h1', { name: t.name })}</h1>
      <ExplorerClient
        territorySlug={t.slug}
        base={portal.base}
        initialState={state}
        initial={{
          total: res.total,
          ids: res.items.map((i) => i.id),
          items: res.items.slice(0, 40).map((i) => toExplorerItem(i, L)),
          answer: L === 'fr' ? res.answer : null,
        }}
        filtered={filtered}
        points={toMapPoints(cards, portal.base, L)}
        map={portal.mapConfig}
        aiEnabled={ai}
        communes={communes.map((c) => ({ slug: c.slug, name: c.name }))}
        filters={
          L === 'fr'
            ? filters
            : filters.map((g) => ({
                ...g,
                label: tr(`attrGroup.${g.group}` as Parameters<typeof tr>[0]),
                options: g.options.map((o) => ({ ...o, label: attributeNameL(o.slug, o.label, L) })),
              }))
        }
        overlays={overlays}
      />
    </>
  );
}
