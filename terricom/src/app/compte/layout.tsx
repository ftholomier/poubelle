import type { Metadata } from 'next';
import { redirect } from 'next/navigation';
import type { ReactNode } from 'react';
import { AppNav, AppTitle, type NavItem } from '@/components/app/AppNav';
import { UserMenu } from '@/components/app/UserMenu';
import { DemoBar } from '@/components/DemoBar';
import { ToastProvider } from '@/components/ui/Feedback';
import { Photo } from '@/components/ui/Photo';
import { fullName } from '@/lib/format';
import { getActor } from '@/server/authz';
import { managedEstablishments } from '@/server/services/pro';

export const metadata: Metadata = { title: { default: 'Mon compte', template: '%s · Mon compte terricom' }, robots: { index: false } };

export default async function AccountLayout({ children }: { children: ReactNode }) {
  const actor = await getActor();
  if (!actor) redirect('/connexion?next=/compte');
  if (actor.user.mfaEnabled && !actor.session.mfaVerified) redirect('/connexion/mfa?next=/compte');
  const ests = await managedEstablishments(actor.user.id);
  const territorial = actor.roles.some((r) => !r.role.startsWith('PLATFORM_'));
  const spaces: NavItem[] = [
    ...ests.slice(0, 6).map((e) => ({ href: `/pro/${e.id}`, label: e.name })),
    ...(territorial ? [{ href: '/collectivite', label: 'Back-office collectivité' }] : []),
    ...(actor.isPlatformStaff ? [{ href: '/console', label: 'Console plateforme' }] : []),
  ];
  const items: NavItem[] = [
    { href: '/compte', label: 'Mon profil', exact: true },
    { href: '/compte/securite', label: 'Sécurité', badge: actor.user.mfaEnabled ? null : '!', badgeBg: 'var(--amber)' },
    { href: '/compte/donnees', label: 'Mes données' },
    ...(spaces.length ? [{ separator: true } as const, ...spaces] : []),
  ];
  const name = fullName(actor.user);
  return (
    <ToastProvider>
      <DemoBar />
      <div className="app-shell">
        <aside className="app-aside">
          <div style={{ display: 'flex', gap: 10, alignItems: 'center', padding: '6px 8px 18px' }}>
            <div style={{ width: 42, height: 42, borderRadius: '50%', overflow: 'hidden', flexShrink: 0 }}>
              <Photo src={actor.user.avatarUrl} alt="" label={name} color="#7A5BB5" />
            </div>
            <div style={{ minWidth: 0 }}>
              <div style={{ fontWeight: 700, fontSize: 14, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{name}</div>
              <div style={{ fontSize: 12, color: 'var(--muted)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{actor.user.email}</div>
            </div>
          </div>
          <AppNav items={items} label="Mon compte" />
        </aside>
        <div className="app-main">
          <header className="app-topbar">
            <AppTitle
              titles={[
                ['/compte', 'Mon profil'],
                ['/compte/securite', 'Sécurité du compte'],
                ['/compte/donnees', 'Mes données personnelles'],
              ]}
              fallback="Mon compte"
            />
            <div style={{ marginLeft: 'auto' }}>
              <UserMenu name={name} email={actor.user.email} avatarUrl={actor.user.avatarUrl} links={spaces.flatMap((s) => ('href' in s ? [{ href: s.href, label: s.label }] : []))} />
            </div>
          </header>
          {children}
        </div>
      </div>
    </ToastProvider>
  );
}
