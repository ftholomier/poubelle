import { resolveTerritoryParam } from '@/server/services/territories';

/**
 * Manifeste de l'application installable : portail d'un territoire (sur son domaine ou sous
 * /<territoire>, paramètre ?territoire=) ou espace professionnel de la plateforme.
 */
export async function GET(req: Request) {
  const url = new URL(req.url);
  const hostParam = req.headers.get('x-terricom-portal-param');
  const slug = url.searchParams.get('territoire');
  const t = hostParam ? await resolveTerritoryParam(hostParam) : slug && /^[a-z0-9-]{2,60}$/.test(slug) ? await resolveTerritoryParam(slug) : null;
  const icons = (q: string) => [
    { src: `/api/pwa/icon?size=192${q}`, sizes: '192x192', type: 'image/png', purpose: 'any' },
    { src: `/api/pwa/icon?size=512${q}`, sizes: '512x512', type: 'image/png', purpose: 'any' },
    { src: `/api/pwa/icon?size=512&maskable=1${q}`, sizes: '512x512', type: 'image/png', purpose: 'maskable' },
  ];
  const manifest = t
    ? {
        id: hostParam ? '/' : `/${t.slug}`,
        name: t.name,
        short_name: t.name.length > 14 ? t.initials : t.name,
        description: t.heroSubtitle ?? `Commerces, artisans et producteurs de ${t.name}.`,
        lang: 'fr',
        start_url: hostParam ? '/?source=pwa' : `/${t.slug}?source=pwa`,
        scope: hostParam ? '/' : `/${t.slug}/`,
        display: 'standalone',
        background_color: '#F7F4EC',
        theme_color: t.colorPrimary,
        icons: icons(hostParam ? '' : `&t=${t.slug}`),
        shortcuts: [
          { name: 'Carte', url: hostParam ? '/explorer' : `/${t.slug}/explorer` },
          { name: 'Agenda', url: hostParam ? '/agenda' : `/${t.slug}/agenda` },
        ],
      }
    : {
        id: '/pro',
        name: 'terricom — espace professionnel',
        short_name: 'terricom',
        description: 'Votre vitrine sur le portail de votre territoire : fiche, publications, messages, statistiques.',
        lang: 'fr',
        start_url: '/pro?source=pwa',
        scope: '/',
        display: 'standalone',
        background_color: '#F7F4EC',
        theme_color: '#14201B',
        icons: icons(''),
        shortcuts: [
          { name: 'Messages', url: '/pro?vers=messages' },
          { name: 'Nouvelle publication', url: '/pro?vers=publications' },
        ],
      };
  return new Response(JSON.stringify(manifest), {
    headers: { 'content-type': 'application/manifest+json; charset=utf-8', 'cache-control': 'public, max-age=3600' },
  });
}
