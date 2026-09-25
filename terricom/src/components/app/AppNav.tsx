'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';

export type NavItem = { href: string; label: string; badge?: string | null; badgeBg?: string; badgeFg?: string; exact?: boolean } | { separator: true };

/** Navigation latérale des espaces connectés (élément actif selon l'URL). */
export function AppNav({ items, label }: { items: NavItem[]; label: string }) {
  const pathname = usePathname();
  const matches = (item: Exclude<NavItem, { separator: true }>) =>
    item.exact ? pathname === item.href : pathname === item.href || pathname.startsWith(`${item.href}/`);
  // Élément le plus précis : « Mises à jour SIRENE » plutôt que « Entreprises » sur /collectivite/entreprises/sirene.
  const active = items
    .filter((i): i is Exclude<NavItem, { separator: true }> => !('separator' in i) && matches(i))
    .sort((a, b) => b.href.length - a.href.length)[0]?.href;
  return (
    <nav className="app-nav" aria-label={label}>
      {items.map((item, i) => {
        if ('separator' in item) return <div key={`sep-${i}`} className="app-nav-sep" role="separator" />;
        const on = item.href === active;
        return (
          <Link key={item.href} href={item.href} className="app-nav-item" aria-current={on ? 'page' : undefined}>
            <span>{item.label}</span>
            {item.badge ? (
              <span className="app-nav-badge" style={{ background: item.badgeBg ?? 'var(--amber)', color: item.badgeFg }}>
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
  const hit = titles.filter(([prefix]) => pathname === prefix || pathname.startsWith(`${prefix}/`)).sort((a, b) => b[0].length - a[0].length)[0];
  return <h1 className="app-title">{hit?.[1] ?? fallback}</h1>;
}
