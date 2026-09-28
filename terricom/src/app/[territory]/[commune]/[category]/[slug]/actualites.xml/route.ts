import { rssFeed } from '@/lib/rss';
import { getPublicEstablishment } from '@/server/services/establishments';
import { establishmentNews, feedResponse } from '@/server/services/feeds';
import { getPortal } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

type Params = { params: Promise<{ territory: string; commune: string; category: string; slug: string }> };

/** Actualités d'un établissement (RSS 2.0), à reprendre sur son propre site. */
export async function GET(_req: Request, { params }: Params) {
  const { territory, commune, slug } = await params;
  const { territory: t } = await getPortal(territory);
  const e = await getPublicEstablishment(t.id, commune, slug);
  if (!e) return new Response('Établissement introuvable', { status: 404 });
  const body = rssFeed(
    {
      title: `${e.name} · ${t.name}`,
      link: portalUrl(t, e.path),
      description: `Les actualités de ${e.name}.`,
      selfUrl: portalUrl(t, `${e.path}/actualites.xml`),
    },
    await establishmentNews(t, { id: e.id, path: e.path }),
  );
  return feedResponse(body, 'rss', `actualites-${e.slug}.xml`);
}
