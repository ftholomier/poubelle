'use client';

import { useEffect } from 'react';

/**
 * Langue du document (<html lang>) alignée sur celle du portail. La mise en page racine est commune à tous
 * les espaces (en français) : le portail traduit la corrige à l'affichage et la rétablit en le quittant.
 */
export function HtmlLang({ locale }: { locale: string }) {
  useEffect(() => {
    const root = document.documentElement;
    root.lang = locale;
    return () => {
      root.lang = 'fr';
    };
  }, [locale]);
  return null;
}
