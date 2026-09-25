import { rssFeed } from '@/lib/rss';
import { feedResponse, territoryNews } from '@/server/services/feeds';
import { getPortal } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

/** Actualités du territoire (RSS 2.0) : reprise sur le site de la collectivité ou dans un agrégateur. */
export async function GET(_req: Request, { params }: { params: Promise<{ territory: string }> }) {
  const { territory } = await params;
  const { territory: t } = await getPortal(territory);
  const items = await territoryNews(t);
  const body = rssFeed(
    {
      title: `Actualités · ${t.name}`,
      link: portalUrl(t, '/actualites'),
      description: `Nouveautés, offres et événements des commerces, artisans et producteurs de ${t.name}.`,
      selfUrl: portalUrl(t, '/actualites.xml'),
    },
    items,
  );
  return feedResponse(body, 'rss', `actualites-${t.slug}.xml`);
}
