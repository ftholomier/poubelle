import type { Metadata } from 'next';
import Link from 'next/link';
import type { TerritorySettings } from '@/server/db/schema';
import { confirmSubscription } from '@/server/services/newsletter';
import { getPortal } from '@/server/services/portal';

type Props = { params: Promise<{ territory: string }>; searchParams: Promise<Record<string, string | undefined>> };

export const metadata: Metadata = { title: 'Confirmation de votre inscription', robots: { index: false } };

export default async function ConfirmPage({ params, searchParams }: Props) {
  const { territory } = await params;
  const { token } = await searchParams;
  const portal = await getPortal(territory);
  const settings = (portal.territory.settings ?? {}) as TerritorySettings;
  const sub = token ? await confirmSubscription(portal.territory.id, token) : null;
  return (
    <div className="container" style={{ paddingTop: 70, paddingBottom: 90, maxWidth: 720 }}>
      <div className="card" style={{ borderRadius: 24, padding: 32, display: 'flex', flexDirection: 'column', gap: 14, alignItems: 'flex-start' }}>
        {sub ? (
          <>
            <span className="stamp">C&apos;est confirmé !</span>
            <h1 className="h-page" style={{ fontSize: 40, margin: 0 }}>
              Bienvenue dans {settings.newsletterName ?? `la lettre de ${portal.territory.name}`}.
            </h1>
            <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>
              Premier envoi très bientôt. Vous pourrez vous désinscrire en un clic depuis chaque lettre.
            </p>
          </>
        ) : (
          <>
            <h1 className="h-page" style={{ fontSize: 36, margin: 0 }}>
              Ce lien n&apos;est plus valide
            </h1>
            <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>
              Il a peut-être déjà été utilisé. Vous pouvez vous réinscrire depuis la page d&apos;accueil.
            </p>
          </>
        )}
        <Link href={portal.base || '/'} className="btn btn-dark">
          Retour au portail
        </Link>
      </div>
    </div>
  );
}
