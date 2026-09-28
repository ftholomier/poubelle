import type { ReactNode } from 'react';
import type { Locale } from '@/lib/i18n';

/**
 * Mise en page des pages d'information (mentions légales, données personnelles, accessibilité).
 * Ces textes font foi en français : sur le portail multilingue, un avertissement est affiché dans la langue du visiteur.
 */
export function LegalShell({
  eyebrow,
  title,
  updated,
  locale = 'fr',
  frenchOnlyNote,
  children,
}: {
  eyebrow: string;
  title: string;
  updated?: string;
  locale?: Locale;
  frenchOnlyNote?: string;
  children: ReactNode;
}) {
  return (
    <div className="container" style={{ paddingTop: 40, paddingBottom: 70 }} lang={locale === 'fr' ? undefined : 'fr'}>
      {locale !== 'fr' && frenchOnlyNote ? (
        <p className="alert alert-info" lang={locale} style={{ marginBottom: 18 }}>
          {frenchOnlyNote}
        </p>
      ) : null}
      <div className="eyebrow" style={{ marginBottom: 6 }}>
        {eyebrow}
      </div>
      <h1 className="h-page" style={{ margin: '0 0 8px' }}>
        {title}
      </h1>
      {updated ? <p style={{ color: 'var(--muted)', fontSize: 14, margin: '0 0 20px' }}>Mise à jour : {updated}</p> : null}
      <div className="prose">{children}</div>
    </div>
  );
}
