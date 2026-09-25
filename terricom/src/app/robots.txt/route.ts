import { publicOrigin } from '@/server/request';

/** robots.txt propre à chaque hôte (plateforme ou portail sur domaine dédié). */
export function GET(req: Request) {
  const origin = publicOrigin(req.headers);
  const body = [
    'User-agent: *',
    'Allow: /',
    'Disallow: /api/',
    'Disallow: /pro/',
    'Disallow: /collectivite/',
    'Disallow: /console/',
    'Disallow: /connexion',
    'Disallow: /*/tampon/',
    'Disallow: /*?*q=',
    '',
    `Sitemap: ${origin}/sitemap.xml`,
    '',
  ].join('\n');
  return new Response(body, { headers: { 'content-type': 'text/plain; charset=utf-8', 'cache-control': 'public, max-age=3600' } });
}
