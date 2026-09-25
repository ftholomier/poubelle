import type { Metadata } from 'next';
import Link from 'next/link';
import { RetryButton } from '@/components/pwa/RetryButton';
import { getPortal } from '@/server/services/portal';

type Props = { params: Promise<{ territory: string }> };

export const metadata: Metadata = { title: 'Hors connexion', robots: { index: false } };

/** Page de secours du portail quand le réseau manque : les pages déjà vues restent consultables. */
export default async function PortalOfflinePage({ params }: Props) {
  const { territory } = await params;
  const portal = await getPortal(territory);
  return (
    <div className="container" style={{ paddingTop: 70, paddingBottom: 90, maxWidth: 720 }}>
      <div className="card" style={{ borderRadius: 24, padding: 32, display: 'flex', flexDirection: 'column', gap: 14, alignItems: 'flex-start' }}>
        <span className="stamp">Hors connexion</span>
        <h1 className="h-page" style={{ fontSize: 36, margin: 0 }}>
          Pas de réseau pour l’instant
        </h1>
        <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0, lineHeight: 1.55 }}>
          Les fiches et les pages de {portal.territory.name} déjà consultées restent accessibles hors connexion. Réessayez dès que le réseau revient.
        </p>
        <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
          <RetryButton />
          <Link href={portal.base || '/'} className="btn btn-outline">
            Accueil du portail
          </Link>
        </div>
      </div>
    </div>
  );
}
