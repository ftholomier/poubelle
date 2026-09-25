import type { Metadata } from 'next';
import { ExplorerClient } from '@/components/portal/ExplorerClient';
import { parseExplorerParams, toSearchParams } from '@/lib/explorer';
import { allCards, getPortal, toMapPoints } from '@/server/services/portal';
import { searchTerritory, toExplorerItem } from '@/server/services/search';
import { getTerritoryCommunes } from '@/server/services/territories';
import { portalUrl } from '@/server/urls';

type Props = {
  params: Promise<{ territory: string }>;
  searchParams: Promise<Record<string, string | string[] | undefined>>;
};

export async function generateMetadata({ params, searchParams }: Props): Promise<Metadata> {
  const { territory } = await params;
  const state = parseExplorerParams(await searchParams);
  const { territory: t } = await getPortal(territory);
  const filtered = Boolean(state.q || state.family || state.toggles.length || state.commune);
  return {
    title: state.q ? `« ${state.q} »` : 'Explorer la carte',
    description: `Commerces, artisans, producteurs, restaurants et services de ${t.name} : une carte libre, filtrable en un geste.`,
    alternates: { canonical: portalUrl(t, '/explorer') },
    robots: filtered ? { index: false, follow: true } : undefined,
  };
}

export default async function ExplorerPage({ params, searchParams }: Props) {
  const { territory } = await params;
  const portal = await getPortal(territory);
  const state = parseExplorerParams(await searchParams);
  const t = portal.territory;
  const ai = portal.modules.has('AI');
  const [res, cards, communes] = await Promise.all([
    searchTerritory(t, { ...toSearchParams(state), limit: 5000, ai }),
    allCards(t.id),
    getTerritoryCommunes(t.id),
  ]);
  const filtered = Boolean(state.q || state.family || state.toggles.length || state.commune);
  return (
    <>
      <h1 className="sr-only">
        {state.q ? `Résultats pour « ${state.q} » · ${t.name}` : `Explorer la carte des commerces, artisans et producteurs · ${t.name}`}
      </h1>
      <ExplorerClient
        territorySlug={t.slug}
        base={portal.base}
        initialState={state}
        initial={{ total: res.total, ids: res.items.map((i) => i.id), items: res.items.slice(0, 40).map(toExplorerItem), answer: res.answer }}
        filtered={filtered}
        points={toMapPoints(cards, portal.base)}
        map={portal.mapConfig}
        aiEnabled={ai}
        communes={communes.map((c) => ({ slug: c.slug, name: c.name }))}
      />
    </>
  );
}
