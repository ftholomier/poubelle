import { cookies, headers } from 'next/headers';
import { cache } from 'react';
import type { ModuleKey } from '@/lib/constants';
import { DEFAULT_LOCALE, isLocale, LOCALE_COOKIE, translator, type Locale, type Translate } from '@/lib/i18n';

/**
 * Langue du visiteur sur un portail : paramètre ?lang= (transmis par le proxy dans x-terricom-lang,
 * pour des adresses partageables et indexables), sinon cookie de préférence, sinon français.
 * Sans le module MULTILINGUAL, le portail reste en français.
 */
export const getPortalLocale = cache(async (multilingual: boolean): Promise<Locale> => {
  if (!multilingual) return DEFAULT_LOCALE;
  const fromQuery = (await headers()).get('x-terricom-lang');
  if (isLocale(fromQuery)) return fromQuery;
  const fromCookie = (await cookies()).get(LOCALE_COOKIE)?.value;
  return isLocale(fromCookie) ? fromCookie : DEFAULT_LOCALE;
});

/** Traducteur du portail pour la requête en cours. */
export async function portalT(portal: { modules: Set<ModuleKey> }): Promise<Translate> {
  return translator(await getPortalLocale(portal.modules.has('MULTILINGUAL')));
}
