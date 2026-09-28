import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { AuthShell } from '@/components/auth/AuthShell';
import { LoginForm } from '@/components/auth/AuthForms';
import { homePathFor, safeNext } from '@/server/auth/login';
import { getSession } from '@/server/auth/session';

export const metadata: Metadata = { title: 'Connexion', robots: { index: false } };

type Props = { searchParams: Promise<Record<string, string | undefined>> };

export default async function LoginPage({ searchParams }: Props) {
  const sp = await searchParams;
  const next = safeNext(sp.next) ?? undefined;
  const s = await getSession();
  if (s && (!s.user.mfaEnabled || s.session.mfaVerified)) redirect(next ?? (await homePathFor(s.user.id)));
  const notice = sp['au-revoir'] ? 'Vous êtes déconnecté·e. À bientôt !' : sp['mot-de-passe'] ? 'Mot de passe modifié : connectez-vous avec le nouveau.' : null;
  return (
    <AuthShell>
      <LoginForm next={next} notice={notice} />
    </AuthShell>
  );
}
