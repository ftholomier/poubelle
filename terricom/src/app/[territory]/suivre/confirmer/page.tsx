import type { Metadata } from 'next';
import Link from 'next/link';
import { confirmFollow, followedEstablishment } from '@/server/services/customers';
import { getPortal } from '@/server/services/portal';

type Props = { params: Promise<{ territory: string }>; searchParams: Promise<Record<string, string | undefined>> };

export const metadata: Metadata = { title: 'Confirmation de votre abonnement', robots: { index: false } };

export default async function FollowConfirmPage({ params, searchParams }: Props) {
  const { territory } = await params;
  const { token } = await searchParams;
  const portal = await getPortal(territory);
  const contact = token ? await confirmFollow(token) : null;
  const est = contact ? await followedEstablishment(contact) : null;
  return (
    <div className="container" style={{ paddingTop: 70, paddingBottom: 90, maxWidth: 720 }}>
      <div className="card" style={{ borderRadius: 24, padding: 32, display: 'flex', flexDirection: 'column', gap: 14, alignItems: 'flex-start' }}>
        {contact ? (
          <>
            <span className="stamp">C&apos;est confirmé !</span>
            <h1 className="h-page" style={{ fontSize: 40, margin: 0 }}>
              Vous suivez {est?.name ?? 'ce commerce'}.
            </h1>
            <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>
              Vous recevrez ses nouveautés et ses offres, quatre emails par mois au plus. Désinscription en un clic depuis chaque email.
            </p>
          </>
        ) : (
          <>
            <h1 className="h-page" style={{ fontSize: 36, margin: 0 }}>
              Ce lien n&apos;est plus valide
            </h1>
            <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>
              Il a peut-être déjà été utilisé. Vous pouvez vous abonner à nouveau depuis la fiche du commerce.
            </p>
          </>
        )}
        <Link href={est ? `${portal.base}${est.path}` : portal.base || '/'} className="btn btn-dark">
          {est ? `Voir la fiche de ${est.name}` : 'Retour au portail'}
        </Link>
      </div>
    </div>
  );
}
