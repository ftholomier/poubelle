import type { Metadata } from 'next';
import Link from 'next/link';
import { AuthShell } from '@/components/auth/AuthShell';
import { ResetPasswordForm } from '@/components/auth/AuthForms';
import { findValidToken } from '@/server/auth/login';

export const metadata: Metadata = { title: 'Nouveau mot de passe', robots: { index: false } };

export default async function ResetPage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  const valid = await findValidToken('PASSWORD_RESET', token);
  return (
    <AuthShell>
      {valid ? (
        <ResetPasswordForm token={token} />
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14, maxWidth: 460 }}>
          <h2 className="h-page" style={{ fontSize: 36, margin: 0 }}>
            Lien expiré
          </h2>
          <p style={{ color: 'var(--muted)', margin: 0 }}>Ce lien de réinitialisation n&apos;est plus valable. Faites une nouvelle demande.</p>
          <Link href="/mot-de-passe-oublie" className="btn btn-brand" style={{ alignSelf: 'flex-start' }}>
            Nouvelle demande
          </Link>
        </div>
      )}
    </AuthShell>
  );
}
