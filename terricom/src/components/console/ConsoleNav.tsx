'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

const TABS = [
  { href: '/console', label: 'Vue d’ensemble', exact: true },
  { href: '/console/territoires', label: 'Territoires & abonnements' },
  { href: '/console/audit', label: 'Audit & sécurité' },
  { href: '/console/simulateur', label: 'Simulateur économique' },
];

const SUB = [
  { href: '/console/crm', label: 'Suivi commercial' },
  { href: '/console/facturation', label: 'Facturation' },
  { href: '/console/support', label: 'Support' },
  { href: '/console/emails', label: 'Emails' },
  { href: '/console/ia', label: 'Usage IA' },
  { href: '/console/taches', label: 'Tâches de fond' },
];

function isOn(pathname: string, href: string, exact?: boolean) {
  return exact ? pathname === href : pathname === href || pathname.startsWith(`${href}/`);
}

/** Titre de la console ; la section courante est précisée pour les lecteurs d'écran. */
export function ConsoleTitle() {
  const pathname = usePathname();
  const current = TABS.find((t) => isOn(pathname, t.href, t.exact)) ?? SUB.find((t) => isOn(pathname, t.href));
  return (
    <h1 className="display" style={{ fontSize: 32, letterSpacing: '-0.025em', margin: 0 }}>
      Console terricom
      {current ? <span className="sr-only"> : {current.label}</span> : null}
    </h1>
  );
}

export function ConsoleTabs() {
  const pathname = usePathname();
  return (
    <nav className="console-tabs" aria-label="Console">
      {TABS.map((t) => (
        <Link key={t.href} href={t.href} aria-current={isOn(pathname, t.href, t.exact) ? 'page' : undefined}>
          {t.label}
        </Link>
      ))}
    </nav>
  );
}

export function ConsoleSubNav() {
  const pathname = usePathname();
  return (
    <nav className="console-sub" aria-label="Outils de la console">
      {SUB.map((t) => (
        <Link key={t.href} href={t.href} aria-current={isOn(pathname, t.href) ? 'page' : undefined}>
          {t.label}
        </Link>
      ))}
    </nav>
  );
}
