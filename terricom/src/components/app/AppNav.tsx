'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

export type NavItem = { href: string; label: string; badge?: string | null; badgeBg?: string; exact?: boolean } | { separator: true };

/** Navigation latérale des espaces connectés (élément actif selon l'URL). */
export function AppNav({ items, label }: { items: NavItem[]; label: string }) {
  const pathname = usePathname();
  return (
    <nav className="app-nav" aria-label={label}>
      {items.map((item, i) => {
        if ('separator' in item) return <div key={`sep-${i}`} className="app-nav-sep" role="separator" />;
        const on = item.exact ? pathname === item.href : pathname === item.href || pathname.startsWith(`${item.href}/`);
        return (
          <Link key={item.href} href={item.href} className="app-nav-item" aria-current={on ? 'page' : undefined}>
            <span>{item.label}</span>
            {item.badge ? (
              <span className="app-nav-badge" style={{ background: item.badgeBg ?? 'var(--amber)' }}>
                {item.badge}
              </span>
            ) : null}
          </Link>
        );
      })}
    </nav>
  );
}

/** Titre de la barre supérieure, déduit de la page affichée. */
export function AppTitle({ titles, fallback }: { titles: [string, string][]; fallback: string }) {
  const pathname = usePathname();
  const hit = titles
    .filter(([prefix]) => pathname === prefix || pathname.startsWith(`${prefix}/`))
    .sort((a, b) => b[0].length - a[0].length)[0];
  return <h1 className="app-title">{hit?.[1] ?? fallback}</h1>;
}
