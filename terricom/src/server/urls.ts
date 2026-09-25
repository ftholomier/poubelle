import { env } from './env';

/** URL absolue sur le domaine de la plateforme (espace pro, back-office, console). */
export function appUrl(path = '/'): string {
  return `${env.APP_URL.replace(/\/$/, '')}${path.startsWith('/') ? path : `/${path}`}`;
}

/** URL publique canonique d'une page de portail de territoire. */
export function portalUrl(territory: { slug: string; primaryHost: string | null }, path = ''): string {
  const p = path && !path.startsWith('/') ? `/${path}` : path;
  if (territory.primaryHost) {
    const proto = territory.primaryHost.includes('localhost') ? 'http' : 'https';
    return `${proto}://${territory.primaryHost}${p || '/'}`;
  }
  return appUrl(`/${territory.slug}${p}`);
}

/** Chemin d'une fiche : /commune/categorie/etablissement (préfixé par le territoire hors domaine dédié). */
export function establishmentPath(e: { communeSlug: string; categorySlug: string; slug: string }): string {
  return `/${e.communeSlug}/${e.categorySlug}/${e.slug}`;
}
