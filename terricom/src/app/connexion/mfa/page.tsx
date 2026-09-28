import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import { AuthShell } from '@/components/auth/AuthShell';
import { MfaForm } from '@/components/auth/AuthForms';
import { safeNext } from '@/server/auth/login';
import { getSession } from '@/server/auth/session';

export const metadata: Metadata = { title: 'Double authentification', robots: { index: false } };

type Props = { searchParams: Promise<Record<string, string | undefined>> };

export default async function MfaPage({ searchParams }: Props) {
  const sp = await searchParams;
  const s = await getSession();
  if (!s) redirect('/connexion');
  if (!s.user.mfaEnabled || s.session.mfaVerified) redirect(safeNext(sp.next) ?? '/');
  return (
    <AuthShell title="Une sécurité en plus." subtitle="La double authentification protège votre fiche, vos données et celles de vos clients.">
      <MfaForm next={safeNext(sp.next) ?? undefined} />
    </AuthShell>
  );
}
