import type { ReactNode } from 'react';

/** Mise en page des pages d'information (mentions légales, données personnelles, accessibilité). */
export function LegalShell({ eyebrow, title, updated, children }: { eyebrow: string; title: string; updated?: string; children: ReactNode }) {
  return (
    <div className="container" style={{ paddingTop: 40, paddingBottom: 70 }}>
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
