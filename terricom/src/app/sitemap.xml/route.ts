import { and, eq, inArray } from 'drizzle-orm';
import { PUBLIC_STATUSES } from '@/lib/constants';
import { db } from '@/server/db';
import { campaigns, categories, circuits, communes, establishments, events, jobs, territories } from '@/server/db/schema';
import { env } from '@/server/env';
import { publicOrigin } from '@/server/request';
import { getTerritoryCommunes, resolveTerritoryParam, type Territory } from '@/server/services/territories';

type Url = { loc: string; lastmod?: Date | null; priority?: number };

async function territoryUrls(t: Territory, base: string): Promise<Url[]> {
  const [ests, coms, evs, jbs, circs, camps] = await Promise.all([
    db
      .select({
        slug: establishments.slug,
        c: communes.slug,
        k: categories.slug,
        updatedAt: establishments.updatedAt,
        desc: establishments.description,
        status: establishments.status,
      })
      .from(establishments)
      .innerJoin(communes, eq(communes.id, establishments.communeId))
      .innerJoin(categories, eq(categories.id, establishments.categoryId))
      .where(and(eq(establishments.territoryId, t.id), inArray(establishments.status, PUBLIC_STATUSES))),
    getTerritoryCommunes(t.id),
    db
      .select({ slug: events.slug, updatedAt: events.updatedAt })
      .from(events)
      .where(and(eq(events.territoryId, t.id), eq(events.status, 'PUBLISHED'))),
    db
      .select({ slug: jobs.slug, updatedAt: jobs.updatedAt })
      .from(jobs)
      .where(and(eq(jobs.territoryId, t.id), eq(jobs.status, 'PUBLISHED'))),
    db
      .select({ slug: circuits.slug, updatedAt: circuits.updatedAt })
      .from(circuits)
      .where(and(eq(circuits.territoryId, t.id), eq(circuits.status, 'PUBLISHED'))),
    db
      .select({ slug: campaigns.slug, updatedAt: campaigns.updatedAt })
      .from(campaigns)
      .where(and(eq(campaigns.territoryId, t.id), inArray(campaigns.status, ['ACTIVE', 'SCHEDULED']))),
  ]);
  return [
    { loc: `${base}/`, priority: 1 },
    { loc: `${base}/explorer`, priority: 0.8 },
    { loc: `${base}/communes`, priority: 0.7 },
    { loc: `${base}/agenda`, priority: 0.7 },
    { loc: `${base}/actualites`, priority: 0.6 },
    { loc: `${base}/emploi`, priority: 0.6 },
    ...coms.map((c) => ({ loc: `${base}/${c.slug}`, priority: 0.7 })),
    // Les fiches précréées jamais complétées ne sont pas proposées aux moteurs (contenu trop pauvre).
    ...ests.filter((e) => e.status !== 'PRECREATED' || e.desc).map((e) => ({ loc: `${base}/${e.c}/${e.k}/${e.slug}`, lastmod: e.updatedAt, priority: 0.9 })),
    ...evs.map((e) => ({ loc: `${base}/agenda/${e.slug}`, lastmod: e.updatedAt, priority: 0.6 })),
    ...jbs.map((j) => ({ loc: `${base}/emploi/${j.slug}`, lastmod: j.updatedAt, priority: 0.6 })),
    ...circs.map((c) => ({ loc: `${base}/circuits/${c.slug}`, lastmod: c.updatedAt, priority: 0.5 })),
    ...camps.map((c) => ({ loc: `${base}/campagnes/${c.slug}`, lastmod: c.updatedAt, priority: 0.6 })),
  ];
}

function xml(urls: Url[]): string {
  const esc = (s: string) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;');
  return `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">\n${urls
    .map(
      (u) =>
        `  <url><loc>${esc(u.loc)}</loc>${u.lastmod ? `<lastmod>${u.lastmod.toISOString().slice(0, 10)}</lastmod>` : ''}${u.priority !== undefined ? `<priority>${u.priority.toFixed(1)}</priority>` : ''}</url>`,
    )
    .join('\n')}\n</urlset>\n`;
}

/** Plan du site propre à l'hôte : portail d'un territoire, ou site de la plateforme et portails servis par chemin. */
export async function GET(req: Request) {
  const origin = publicOrigin(req.headers);
  const param = req.headers.get('x-terricom-portal-param');
  let urls: Url[] = [];
  if (param) {
    const t = await resolveTerritoryParam(param);
    if (t && t.status !== 'CHURNED' && t.status !== 'SUSPENDED') urls = await territoryUrls(t, origin);
  } else {
    urls = [
      '/',
      '/collectivites',
      '/professionnels',
      '/territoires',
      '/tarifs',
      '/demo',
      '/marque',
      '/pro/revendiquer',
      '/mentions-legales',
      '/cgu',
      '/cgv',
      '/confidentialite',
      '/accessibilite',
    ].map((p, i) => ({ loc: `${origin}${p}`, priority: i === 0 ? 1 : i < 6 ? 0.8 : 0.4 }));
    const list = await db
      .select()
      .from(territories)
      .where(inArray(territories.status, ['ACTIVE', 'ONBOARDING']));
    for (const t of list) {
      // Un territoire servi sur son propre domaine est référencé sur ce domaine, pas ici.
      if (t.primaryHost && !env.DEMO_MODE) continue;
      urls.push(...(await territoryUrls(t, `${origin}/${t.slug}`)));
    }
  }
  return new Response(xml(urls), { headers: { 'content-type': 'application/xml; charset=utf-8', 'cache-control': 'public, max-age=3600' } });
}
