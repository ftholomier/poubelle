import type { Metadata } from 'next';
import { RetryButton } from '@/components/pwa/RetryButton';

export const metadata: Metadata = { title: 'Hors connexion', robots: { index: false } };

/** Page de secours du service worker quand le réseau manque (espaces pro, collectivité, console). */
export default function OfflinePage() {
  return (
    <main id="contenu" className="container" style={{ paddingTop: 90, paddingBottom: 90, maxWidth: 640 }}>
      <div className="card" style={{ borderRadius: 24, padding: 32, display: 'flex', flexDirection: 'column', gap: 14, alignItems: 'flex-start' }}>
        <span className="stamp">Hors connexion</span>
        <h1 className="h-page" style={{ fontSize: 36, margin: 0 }}>
          Pas de réseau pour l’instant
        </h1>
        <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0, lineHeight: 1.55 }}>
          Les pages déjà consultées restent accessibles. Vos modifications n’ont pas été perdues : réessayez dès que la connexion revient.
        </p>
        <RetryButton />
      </div>
    </main>
  );
}
