import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { contactByUnsubscribeToken, followedEstablishment, unfollow } from '@/server/services/customers';
import { getPortal } from '@/server/services/portal';

type Props = { params: Promise<{ territory: string }>; searchParams: Promise<Record<string, string | undefined>> };

export const metadata: Metadata = { title: 'Désinscription', robots: { index: false } };

/** Désinscription des lettres d'un commerce : confirmée par un bouton (les liens peuvent être ouverts par des robots de messagerie). */
export default async function FollowUnsubscribePage({ params, searchParams }: Props) {
  const { territory } = await params;
  const { token, fait } = await searchParams;
  const portal = await getPortal(territory);
  const contact = token ? await contactByUnsubscribeToken(token) : null;
  const est = contact ? await followedEstablishment(contact) : null;

  async function confirm(form: FormData) {
    'use server';
    const tk = String(form.get('token') ?? '');
    await unfollow(tk);
    redirect(`${portal.base}/suivre/desinscription?token=${encodeURIComponent(tk)}&fait=1`);
  }

  const done = contact && (!contact.subscribed || fait === '1');
  return (
    <div className="container" style={{ paddingTop: 70, paddingBottom: 90, maxWidth: 720 }}>
      <div className="card" style={{ borderRadius: 24, padding: 32, display: 'flex', flexDirection: 'column', gap: 14, alignItems: 'flex-start' }}>
        {!contact ? (
          <>
            <h1 className="h-page" style={{ fontSize: 36, margin: 0 }}>
              Lien invalide
            </h1>
            <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>Ce lien de désinscription est invalide ou a expiré.</p>
          </>
        ) : done ? (
          <>
            <h1 className="h-page" style={{ fontSize: 36, margin: 0 }}>
              Vous êtes désinscrit·e.
            </h1>
            <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>
              Vous ne recevrez plus les emails de {est?.name ?? 'ce commerce'}. Votre adresse est conservée uniquement pour garantir cette désinscription.
            </p>
          </>
        ) : (
          <>
            <h1 className="h-page" style={{ fontSize: 36, margin: 0 }}>
              Ne plus recevoir les emails de {est?.name ?? 'ce commerce'} ?
            </h1>
            <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>Adresse concernée : {contact.email}</p>
            <form action={confirm}>
              <input type="hidden" name="token" value={token} />
              <SubmitButton className="btn btn-brand" pendingLabel="Désinscription…">
                Me désinscrire
              </SubmitButton>
            </form>
          </>
        )}
        <Link href={est ? `${portal.base}${est.path}` : portal.base || '/'} className="btn btn-outline btn-sm">
          {est ? `Voir la fiche de ${est.name}` : 'Retour au portail'}
        </Link>
      </div>
    </div>
  );
}
