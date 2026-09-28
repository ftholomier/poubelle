import type { Metadata } from 'next';
import Link from 'next/link';
import type { ReactNode } from 'react';
import { stopImpersonationAction } from './actions';
import { ConsoleSubNav, ConsoleTabs, ConsoleTitle } from '@/components/console/ConsoleNav';
import { DemoBar } from '@/components/DemoBar';
import { UserMenu } from '@/components/app/UserMenu';
import { ToastProvider } from '@/components/ui/Feedback';
import { fullName, nowMs } from '@/lib/format';
import { requirePlatformStaff } from '@/server/authz';
import { getTerritoryById } from '@/server/services/territories';

export const metadata: Metadata = { title: { default: 'Console', template: '%s · Console terricom' }, robots: { index: false } };

export default async function ConsoleLayout({ children }: { children: ReactNode }) {
  const actor = await requirePlatformStaff();
  const imp = actor.impersonation;
  const impTerritory = imp ? await getTerritoryById(imp.territoryId) : null;
  const minutes = imp ? Math.max(0, Math.round((imp.expiresAt.getTime() - nowMs()) / 60_000)) : 0;
  return (
    <ToastProvider>
      <DemoBar active="console" />
      {imp && impTerritory ? (
        <div
          style={{
            background: 'var(--danger)',
            color: '#fff',
            padding: '10px 24px',
            display: 'flex',
            gap: 12,
            alignItems: 'center',
            fontSize: 14,
            fontWeight: 600,
            flexWrap: 'wrap',
          }}
          role="status"
        >
          Accès support actif · contexte « {impTerritory.name} » · expire dans {minutes} min · toutes les actions sont journalisées
          <Link href="/collectivite" style={{ color: '#fff', textDecoration: 'underline' }}>
            Ouvrir le back-office
          </Link>
          <form action={stopImpersonationAction} style={{ marginLeft: 'auto' }}>
            <button
              type="submit"
              style={{ border: 0, background: '#fff', color: 'var(--danger-fg)', padding: '6px 12px', borderRadius: 8, fontWeight: 800, cursor: 'pointer' }}
            >
              Quitter
            </button>
          </form>
        </div>
      ) : null}
      <div className="console-page">
        <div className="console-wrap">
          <div style={{ display: 'flex', alignItems: 'center', gap: 16, flexWrap: 'wrap' }}>
            <div>
              <div style={{ fontSize: 12, color: 'var(--muted)', fontWeight: 700, letterSpacing: '0.08em', textTransform: 'uppercase' }}>
                Super administration
              </div>
              <ConsoleTitle />
            </div>
            <ConsoleTabs />
            <UserMenu
              name={fullName(actor.user)}
              email={actor.user.email}
              avatarUrl={actor.user.avatarUrl}
              links={[{ href: '/compte', label: 'Mon compte et sécurité' }]}
            />
          </div>
          <ConsoleSubNav />
          {children}
        </div>
      </div>
    </ToastProvider>
  );
}
