import type { Metadata } from 'next';
import { AuthShell } from '@/components/auth/AuthShell';
import { ResetRequestForm } from '@/components/auth/AuthForms';

export const metadata: Metadata = { title: 'Mot de passe oublié', robots: { index: false } };

export default function ForgotPage() {
  return (
    <AuthShell>
      <ResetRequestForm />
    </AuthShell>
  );
}
