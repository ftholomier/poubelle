import { headers } from 'next/headers';

/** Informations sur la requête courante (IP réelle derrière l'ingress, user-agent, hôte, chemin). */
export async function requestInfo() {
  const h = await headers();
  const forwarded = h.get('x-forwarded-for');
  const ip = (forwarded?.split(',')[0] ?? h.get('x-real-ip') ?? '').trim() || null;
  return {
    ip,
    userAgent: h.get('user-agent') ?? '',
    host: (h.get('x-forwarded-host') ?? h.get('host') ?? '').toLowerCase(),
    pathname: h.get('x-pathname') ?? '/',
    portalMode: (h.get('x-terricom-portal-mode') as 'host' | null) ?? null,
    referer: h.get('referer') ?? null,
  };
}

export function isBot(userAgent: string): boolean {
  return /bot|crawl|spider|slurp|facebookexternalhit|embedly|preview|lighthouse|headless|monitor|curl|wget|python-requests|go-http-client/i.test(userAgent);
}

export function deviceType(userAgent: string): 'mobile' | 'tablet' | 'desktop' {
  if (/ipad|tablet/i.test(userAgent)) return 'tablet';
  if (/mobi|iphone|android/i.test(userAgent)) return 'mobile';
  return 'desktop';
}

/** Origine publique de la requête (derrière l'ingress : en-têtes X-Forwarded-*). */
export function publicOrigin(h: Headers): string {
  const host = (h.get('x-forwarded-host') ?? h.get('host') ?? 'localhost:3000').split(',')[0].trim();
  const proto =
    (h.get('x-forwarded-proto') ?? '').split(',')[0].trim() ||
    (/^(localhost|127\.|\[::1\])/.test(host) || host.endsWith('.localhost') || host.includes('.localhost:') ? 'http' : 'https');
  return `${proto}://${host}`;
}
