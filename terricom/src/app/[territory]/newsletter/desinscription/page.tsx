import type { Metadata } from 'next';
import Link from 'next/link';
import { unsubscribe } from '@/server/services/newsletter';
import { getPortal } from '@/server/services/portal';

type Props = { params: Promise<{ territory: string }>; searchParams: Promise<Record<string, string | undefined>> };

export const metadata: Metadata = { title: 'Désinscription', robots: { index: false } };

export default async function UnsubscribePage({ params, searchParams }: Props) {
  const { territory } = await params;
  const { token } = await searchParams;
  const portal = await getPortal(territory);
  const sub = token ? await unsubscribe(token) : null;
  return (
    <div className="container" style={{ paddingTop: 70, paddingBottom: 90, maxWidth: 720 }}>
      <div className="card" style={{ borderRadius: 24, padding: 32, display: 'flex', flexDirection: 'column', gap: 14, alignItems: 'flex-start' }}>
        <h1 className="h-page" style={{ fontSize: 36, margin: 0 }}>
          {sub ? 'Vous êtes désinscrit·e.' : 'Lien invalide'}
        </h1>
        <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>
          {sub
            ? 'Vous ne recevrez plus nos lettres. Vos données sont conservées uniquement pour garantir cette désinscription.'
            : 'Ce lien de désinscription est invalide ou a expiré.'}
        </p>
        <Link href={portal.base || '/'} className="btn btn-dark">
          Retour au portail
        </Link>
      </div>
    </div>
  );
}
