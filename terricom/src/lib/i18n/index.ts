import { MESSAGES, type MessageKey } from './messages';

/**
 * Portail multilingue (module MULTILINGUAL) : français par défaut, anglais et allemand pour les visiteurs.
 * Les espaces pro, collectivité et console restent en français.
 */
export const LOCALES = ['fr', 'en', 'de'] as const;
export type Locale = (typeof LOCALES)[number];
export const DEFAULT_LOCALE: Locale = 'fr';
export const LOCALE_NAMES: Record<Locale, string> = { fr: 'Français', en: 'English', de: 'Deutsch' };
export const LOCALE_COOKIE = 'tc_lang';

export function isLocale(v: unknown): v is Locale {
  return typeof v === 'string' && (LOCALES as readonly string[]).includes(v);
}

/** Paramètres régionaux Intl de chaque langue. */
export const INTL: Record<Locale, string> = { fr: 'fr-FR', en: 'en-GB', de: 'de-DE' };

/** Paramètres régionaux Open Graph. */
export const OG_LOCALE: Record<Locale, string> = { fr: 'fr_FR', en: 'en_GB', de: 'de_DE' };

/** Adresse d'une page dans une langue (?lang= hors français) : canonique propre à chaque langue, cohérente avec hreflang. */
export function withLang(url: string, locale: Locale): string {
  if (locale === 'fr') return url;
  return `${url}${url.includes('?') ? '&' : '?'}lang=${locale}`;
}

export type Vars = Record<string, string | number>;
export type Translate = ((key: MessageKey, vars?: Vars) => string) & {
  locale: Locale;
  /** Accord en nombre : clé.one / clé.other selon la langue. */
  n: (key: string, count: number, vars?: Vars) => string;
};

function interpolate(s: string, vars?: Vars): string {
  return vars ? s.replace(/\{(\w+)\}/g, (_, k: string) => (k in vars ? String(vars[k]) : `{${k}}`)) : s;
}

/** Singulier en français pour 0 et 1, en anglais et en allemand pour 1 seulement. */
export function pluralForm(locale: Locale, count: number): 'one' | 'other' {
  return locale === 'fr' ? (Math.abs(count) < 2 ? 'one' : 'other') : Math.abs(count) === 1 ? 'one' : 'other';
}

export function translator(locale: Locale): Translate {
  const dict = MESSAGES[locale] as Record<string, string>;
  const fr = MESSAGES.fr as Record<string, string>;
  const t = ((key: MessageKey, vars?: Vars) => interpolate(dict[key] ?? fr[key] ?? key, vars)) as Translate;
  t.locale = locale;
  t.n = (key, count, vars) => {
    const k = `${key}.${pluralForm(locale, count)}`;
    return interpolate(dict[k] ?? fr[k] ?? key, { n: count, ...vars });
  };
  return t;
}

export type { MessageKey };
