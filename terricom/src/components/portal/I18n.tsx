'use client';

import { createContext, useContext, useMemo, type ReactNode } from 'react';
import { LOCALE_COOKIE, LOCALE_NAMES, LOCALES, translator, type Locale } from '@/lib/i18n';

const LocaleContext = createContext<Locale>('fr');

/** Langue du portail pour les composants client. */
export function I18nProvider({ locale, children }: { locale: Locale; children: ReactNode }) {
  return <LocaleContext.Provider value={locale}>{children}</LocaleContext.Provider>;
}

export function useLocale(): Locale {
  return useContext(LocaleContext);
}

export function useT() {
  const locale = useLocale();
  return useMemo(() => translator(locale), [locale]);
}

/** Mémorise la langue choisie (un an) et recharge la page dans cette langue. */
function switchTo(l: Locale) {
  document.cookie = `${LOCALE_COOKIE}=${l}; path=/; max-age=31536000; samesite=lax`;
  const url = new URL(window.location.href);
  if (l === 'fr') url.searchParams.delete('lang');
  else url.searchParams.set('lang', l);
  window.location.assign(url.toString());
}

/** Sélecteur de langue du portail. */
export function LangSwitch() {
  const locale = useLocale();
  const t = useT();
  const choose = switchTo;
  return (
    <div className="lang-switch" role="group" aria-label={t('lang.switch')}>
      {LOCALES.map((l) => (
        <button key={l} type="button" lang={l} aria-pressed={l === locale} title={LOCALE_NAMES[l]} onClick={() => l !== locale && choose(l)}>
          {l.toUpperCase()}
        </button>
      ))}
    </div>
  );
}
