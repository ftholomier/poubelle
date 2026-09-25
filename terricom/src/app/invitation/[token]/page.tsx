import { eq } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { logoutAction } from '@/app/connexion/actions';
import { AuthShell } from '@/components/auth/AuthShell';
import { InvitationForm } from '@/components/auth/InvitationForm';
import { fullName } from '@/lib/format';
import { STAFF_ROLES, type StaffRole } from '@/lib/constants';
import { findValidToken } from '@/server/auth/login';
import { getSession } from '@/server/auth/session';
import { db } from '@/server/db';
import { communes, companies, establishments, territories, users } from '@/server/db/schema';

export const metadata: Metadata = { title: 'Invitation', robots: { index: false } };

type Props = { params: Promise<{ token: string }> };

export default async function InvitationPage({ params }: Props) {
  const { token } = await params;
  const row = (await findValidToken('INVITE_MEMBER', token)) ?? (await findValidToken('INVITE_STAFF', token));
  if (!row || !row.email) {
    return (
      <AuthShell>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14, maxWidth: 460 }}>
          <h2 className="h-page" style={{ fontSize: 38, margin: 0 }}>
            Invitation expirée
          </h2>
          <p style={{ margin: 0, color: 'var(--muted)' }}>
            Ce lien n&apos;est plus valable : il a déjà été utilisé ou a dépassé sa durée de 7 jours. Demandez une nouvelle invitation à la personne qui vous l&apos;a
            envoyée.
          </p>
          <Link href="/connexion" className="btn btn-outline" style={{ alignSelf: 'flex-start' }}>
            Aller à la connexion
          </Link>
        </div>
      </AuthShell>
    );
  }
  const payload = row.payload as { companyId?: string; establishmentId?: string; role?: string; staffRole?: StaffRole; territoryId?: string; communeId?: string | null };
  const [inviter, session, existing] = await Promise.all([
    row.createdById ? db.select().from(users).where(eq(users.id, row.createdById)).limit(1).then((r) => r[0] ?? null) : Promise.resolve(null),
    getSession(),
    db.select({ id: users.id }).from(users).where(eq(users.email, row.email)).limit(1).then((r) => r[0] ?? null),
  ]);
  let scope = '';
  let detail = '';
  if (row.kind === 'INVITE_MEMBER') {
    const [est] = payload.establishmentId
      ? await db.select({ name: establishments.name }).from(establishments).where(eq(establishments.id, payload.establishmentId)).limit(1)
      : await db.select({ name: companies.tradeName }).from(companies).where(eq(companies.id, payload.companyId ?? '')).limit(1);
    scope = `gérer « ${est?.name ?? 'l’entreprise'} »`;
    detail = payload.role === 'OWNER' ? 'Vous aurez les mêmes droits que le ou la titulaire, abonnement compris.' : 'Vous pourrez modifier la fiche, publier des actualités et consulter les statistiques.';
  } else {
    const [t] = payload.territoryId ? await db.select({ name: territories.name }).from(territories).where(eq(territories.id, payload.territoryId)).limit(1) : [];
    const [c] = payload.communeId ? await db.select({ name: communes.name }).from(communes).where(eq(communes.id, payload.communeId)).limit(1) : [];
    scope = `rejoindre ${c ? `la commune de ${c.name}` : (t?.name ?? 'la collectivité')}`;
    detail = `Rôle : ${payload.staffRole ? STAFF_ROLES[payload.staffRole] : 'agent'}. La double authentification est obligatoire pour les agents des collectivités.`;
  }
  const sameUser = session && session.user.email.toLowerCase() === row.email.toLowerCase();
  const otherUser = session && !sameUser;

  return (
    <AuthShell title={<>Bienvenue dans l&apos;équipe.</>} subtitle="Un accès personnel et sécurisé : chacun ses identifiants, toutes les actions sont tracées.">
      <div style={{ display: 'flex', flexDirection: 'column', gap: 14, maxWidth: 460 }}>
        <span style={{ fontSize: 12, fontWeight: 800, letterSpacing: '0.08em', color: 'var(--green)', textTransform: 'uppercase' }}>Invitation</span>
        <h2 className="h-page" style={{ fontSize: 36, margin: 0, lineHeight: 1.05 }}>
          {inviter ? `${fullName(inviter)} vous invite à ${scope}` : `Vous êtes invité·e à ${scope}`}
        </h2>
        <p style={{ margin: 0, color: 'var(--muted)' }}>{detail}</p>
        {otherUser ? (
          <div className="alert alert-info" role="status">
            Vous êtes connecté·e avec {session.user.email}. Cette invitation est destinée à {row.email}.
            <form action={logoutAction} style={{ marginTop: 8 }}>
              <button type="submit" className="btn btn-outline btn-sm">
                Me déconnecter
              </button>
            </form>
          </div>
        ) : !sameUser && existing ? (
          <>
            <p style={{ margin: 0 }}>
              Un compte existe déjà pour <b>{row.email}</b> : connectez-vous pour accepter l&apos;invitation.
            </p>
            <Link href={`/connexion?next=${encodeURIComponent(`/invitation/${token}`)}`} className="btn btn-brand" style={{ justifyContent: 'center', padding: 14 }}>
              Se connecter
            </Link>
          </>
        ) : (
          <InvitationForm token={token} email={row.email} needsAccount={!sameUser} />
        )}
      </div>
    </AuthShell>
  );
}
