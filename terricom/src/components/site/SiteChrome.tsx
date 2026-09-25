import Link from 'next/link';
import type { ReactNode } from 'react';
import { DemoBar } from '@/components/DemoBar';
import { Logo, Symbol } from '@/components/ui/Brand';
import { env } from '@/server/env';

const NAV = [
  { href: '/collectivites', label: 'Collectivités' },
  { href: '/professionnels', label: 'Professionnels' },
  { href: '/territoires', label: 'Territoires' },
  { href: '/tarifs', label: 'Tarifs' },
];

/** En-tête du site de la marque (terricom.fr), d'après la charte : logo, rubriques, démo. */
export function SiteHeader({ current }: { current?: string }) {
  return (
    <header className="site-header">
      <div className="site-header-inner">
        <Link href="/" aria-label="terricom, accueil" style={{ color: 'inherit' }}>
          <Logo size={24} />
        </Link>
        <nav aria-label="Rubriques" className="site-nav">
          {NAV.map((n) => (
            <Link key={n.href} href={n.href} aria-current={current === n.href ? 'page' : undefined}>
              {n.label}
            </Link>
          ))}
        </nav>
        <div className="site-header-actions">
          <Link href="/connexion" className="site-login">
            Se connecter
          </Link>
          <Link href="/demo" className="btn btn-dark btn-sm" style={{ borderRadius: 10 }}>
            Demander une démo
          </Link>
        </div>
        <details className="site-burger">
          <summary aria-label="Menu">☰</summary>
          <nav aria-label="Menu mobile">
            {NAV.map((n) => (
              <Link key={n.href} href={n.href}>
                {n.label}
              </Link>
            ))}
            <Link href="/connexion">Se connecter</Link>
            <Link href="/demo" className="btn btn-dark btn-sm">
              Demander une démo
            </Link>
          </nav>
        </details>
      </div>
    </header>
  );
}

export function SiteFooter() {
  const cols: { title: string; links: [string, string][] }[] = [
    {
      title: 'Plateforme',
      links: [
        ['/collectivites', 'Pour les collectivités'],
        ['/professionnels', 'Pour les professionnels'],
        ['/territoires', 'Territoires en ligne'],
        ['/tarifs', 'Tarifs'],
      ],
    },
    {
      title: 'Accès',
      links: [
        ['/pro/revendiquer', 'Trouver ma fiche'],
        ['/connexion', 'Se connecter'],
        ['/demo', 'Demander une démo'],
        ['/marque', 'La marque'],
      ],
    },
    {
      title: 'Informations',
      links: [
        ['/mentions-legales', 'Mentions légales'],
        ['/cgu', 'Conditions d’utilisation'],
        ['/cgv', 'Conditions de vente'],
        ['/confidentialite', 'Confidentialité'],
        ['/accessibilite', 'Accessibilité'],
      ],
    },
  ];
  return (
    <footer className="site-footer">
      <div className="site-footer-inner">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 12, maxWidth: 320 }}>
          <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <Symbol size={24} bg="var(--amber)" letter={null} />
            <b className="display" style={{ fontSize: 22, color: 'var(--cream)', letterSpacing: '-0.05em' }}>
              terricom.
            </b>
          </span>
          <span className="display" style={{ fontSize: 26, color: 'var(--amber)', letterSpacing: '-0.03em' }}>
            Le territoire, en vitrine.
          </span>
          <span style={{ fontSize: 13 }}>Plateforme française, hébergée en France. Gratuite pour chaque professionnel, financée par la collectivité.</span>
        </div>
        {cols.map((c) => (
          <nav key={c.title} aria-label={c.title} style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 14 }}>
            <b style={{ color: 'var(--cream)', fontSize: 13, letterSpacing: '.06em', textTransform: 'uppercase' }}>{c.title}</b>
            {c.links.map(([href, label]) => (
              <Link key={href} href={href}>
                {label}
              </Link>
            ))}
          </nav>
        ))}
      </div>
      <div className="site-footer-bottom">
        <span>
          © {new Date().getFullYear()} {env.COMPANY_LEGAL_NAME} · terricom.fr
        </span>
        <a href="mailto:bonjour@terricom.fr">bonjour@terricom.fr</a>
      </div>
    </footer>
  );
}

/**
 * Coque des pages du site de la marque : barre de démonstration (mode démo),
 * en-tête du site, contenu et pied de page.
 */
export function SiteShell({ children, current, header = true }: { children: ReactNode; current?: string; header?: boolean }) {
  return (
    <>
      <DemoBar active={current === '/marque' ? 'marque' : current === '/' ? 'presentation' : undefined} />
      {header ? <SiteHeader current={current} /> : null}
      <main id="contenu" className="site-main">
        {children}
      </main>
      <SiteFooter />
    </>
  );
}
