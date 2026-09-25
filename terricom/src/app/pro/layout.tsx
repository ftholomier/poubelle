import Link from 'next/link';
import type { Metadata } from 'next';
import type { ReactNode } from 'react';
import { DemoBar } from '@/components/DemoBar';
import { ToastProvider } from '@/components/ui/Feedback';

export const metadata: Metadata = { title: { default: 'Espace professionnel', template: '%s · Espace pro terricom' }, robots: { index: false } };

export default function ProLayout({ children }: { children: ReactNode }) {
  return (
    <ToastProvider>
      <DemoBar
        active="pro"
        right={
          <>
            Parcours :
            <Link href="/pro/revendiquer?territoire=valdeloue&q=Boulangerie%20Ornans" style={{ color: 'var(--amber)', fontWeight: 600 }}>
              1. Revendiquer sa fiche
            </Link>
            ·
            {/* Gestionnaire de route (connexion de démonstration) : navigation complète, pas de préchargement. */}
            {/* eslint-disable-next-line @next/next/no-html-link-for-pages */}
            <a href="/demo/entrer/pro" style={{ color: 'var(--amber)', fontWeight: 600 }}>
              2. Gérer au quotidien
            </a>
          </>
        }
      />
      {children}
    </ToastProvider>
  );
}
