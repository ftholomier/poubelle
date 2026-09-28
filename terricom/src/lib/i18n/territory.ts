import type { TerritorySettings, TerritoryTexts } from '@/server/db/schema';
import type { Locale } from './index';

/** Texte d'un territoire dans la langue du visiteur, ou en français à défaut de traduction. */
export function territoryText(
  t: { settings: unknown; tagline?: string | null; heroTitle?: string | null; heroSubtitle?: string | null },
  key: keyof TerritoryTexts,
  locale: Locale,
): string | null {
  const settings = (t.settings ?? {}) as TerritorySettings;
  if (locale !== 'fr') {
    const tr = settings.translations?.[locale]?.[key];
    if (tr) return tr;
  }
  if (key === 'tagline') return t.tagline ?? null;
  if (key === 'heroTitle') return t.heroTitle ?? null;
  if (key === 'heroSubtitle') return t.heroSubtitle ?? null;
  return (settings as Record<string, unknown>)[key] as string | null;
}
