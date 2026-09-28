import { NextResponse, type NextRequest } from 'next/server';

/**
 * Proxy (anciennement middleware) :
 *  1. Aiguillage multi-domaines : valdeloue.terricom.fr et commerces.valdeloue.fr servent
 *     le portail du territoire (réécriture vers /valdeloue/… ou /~hôte/…) ;
 *     pro.terricom.fr redirige vers l'espace entreprise.
 *  2. Politique de sécurité du contenu (CSP) stricte avec nonce par requête.
 */
const PLATFORM_DOMAIN = (process.env.PLATFORM_DOMAIN ?? 'terricom.fr').toLowerCase();
const PLATFORM_HOSTS = new Set(
  [PLATFORM_DOMAIN, `www.${PLATFORM_DOMAIN}`, ...(process.env.PLATFORM_HOSTS ?? 'localhost:3000,127.0.0.1:3000').split(',')]
    .map((h) => h.trim().toLowerCase())
    .filter(Boolean),
);
const RESERVED_SUBDOMAINS = new Set(['www', 'pro', 'app', 'api', 'admin', 'console', 'static', 'cdn', 'mail']);
const PASSTHROUGH = /^\/(_next|api|media|fonts|q\/|favicon|icon|apple-icon|robots\.txt|sitemap\.xml|manifest|sw\.js|opengraph|\.well-known)/;

type HostMode = { kind: 'platform' } | { kind: 'pro' } | { kind: 'territory'; param: string };

function resolveHost(host: string): HostMode {
  if (!host || PLATFORM_HOSTS.has(host)) return { kind: 'platform' };
  const hostname = host.split(':')[0];
  if (hostname === `pro.${PLATFORM_DOMAIN}`) return { kind: 'pro' };
  if (hostname.endsWith(`.${PLATFORM_DOMAIN}`)) {
    const sub = hostname.slice(0, -PLATFORM_DOMAIN.length - 1);
    if (RESERVED_SUBDOMAINS.has(sub) || sub.includes('.')) return { kind: 'platform' };
    return { kind: 'territory', param: sub };
  }
  for (const platformHost of PLATFORM_HOSTS) {
    if (host.endsWith(`.${platformHost}`)) {
      const sub = host.slice(0, -platformHost.length - 1);
      if (!sub.includes('.') && !RESERVED_SUBDOMAINS.has(sub)) return { kind: 'territory', param: sub };
    }
  }
  return { kind: 'territory', param: `~${hostname}` };
}

function buildCsp(nonce: string): string {
  const dev = process.env.NODE_ENV !== 'production';
  return [
    "default-src 'self'",
    `script-src 'self' 'nonce-${nonce}' 'strict-dynamic'${dev ? " 'unsafe-eval'" : ''}`,
    "style-src 'self' 'unsafe-inline'",
    "img-src 'self' data: blob: https:",
    "font-src 'self' data:",
    `connect-src 'self'${dev ? ' ws: wss:' : ''}`,
    "media-src 'self' https:",
    "frame-src 'self'",
    "frame-ancestors 'self'",
    "form-action 'self'",
    "base-uri 'self'",
    "object-src 'none'",
    "manifest-src 'self'",
    "worker-src 'self' blob:",
    ...(dev ? [] : ['upgrade-insecure-requests']),
  ].join('; ');
}

export function proxy(req: NextRequest) {
  const host = (req.headers.get('x-forwarded-host') ?? req.headers.get('host') ?? '').toLowerCase();
  const url = req.nextUrl;
  const mode = resolveHost(host);

  if (mode.kind === 'pro') {
    const target = new URL(`https://${PLATFORM_DOMAIN}/pro${url.pathname === '/' ? '' : url.pathname}${url.search}`);
    return NextResponse.redirect(target, 308);
  }

  const nonce = Buffer.from(crypto.randomUUID()).toString('base64');
  const csp = buildCsp(nonce);
  const requestHeaders = new Headers(req.headers);
  requestHeaders.set('x-nonce', nonce);
  requestHeaders.set('content-security-policy', csp);
  requestHeaders.set('x-pathname', url.pathname);
  requestHeaders.delete('x-terricom-portal-mode');
  // Langue demandée par l'adresse (?lang=en) : lue par les composants serveur du portail.
  requestHeaders.delete('x-terricom-lang');
  const lang = url.searchParams.get('lang');
  if (lang && /^(fr|en|de)$/.test(lang)) requestHeaders.set('x-terricom-lang', lang);

  let res: NextResponse;
  if (mode.kind === 'territory' && !PASSTHROUGH.test(url.pathname)) {
    requestHeaders.set('x-terricom-portal-mode', 'host');
    requestHeaders.set('x-terricom-portal-param', mode.param);
    const rewritten = url.clone();
    rewritten.pathname = `/${encodeURIComponent(mode.param)}${url.pathname === '/' ? '' : url.pathname}`;
    res = NextResponse.rewrite(rewritten, { request: { headers: requestHeaders } });
  } else {
    if (mode.kind === 'territory') requestHeaders.set('x-terricom-portal-param', mode.param);
    res = NextResponse.next({ request: { headers: requestHeaders } });
  }
  res.headers.set('content-security-policy', csp);
  return res;
}

export const config = {
  // Les préchargements (prefetch) passent aussi par le proxy : ils doivent être réécrits
  // vers le bon territoire lorsque le portail est servi sur un domaine dédié.
  matcher: ['/((?!_next/static|_next/image|favicon.ico|fonts/|media/).*)'],
};
