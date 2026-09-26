import { env } from '@/server/env';

type Space = 'presentation' | 'portail' | 'pro' | 'collectivite' | 'console' | 'marque' | 'haut-doubs';

const LINKS: { key: Space; label: string; href: string }[] = [
  { key: 'presentation', label: 'Présentation', href: '/' },
  { key: 'portail', label: 'Portail public', href: '/valdeloue' },
  { key: 'pro', label: 'Espace entreprise', href: '/demo/entrer/pro' },
  { key: 'collectivite', label: 'Back-office collectivité', href: '/demo/entrer/collectivite' },
  { key: 'console', label: 'Console plateforme', href: '/demo/entrer/console' },
  { key: 'marque', label: 'La marque', href: '/marque' },
  { key: 'haut-doubs', label: 'Haut-Doubs (données réelles)', href: '/haut-doubs' },
];

/**
 * Barre de démonstration (mode DEMO_MODE uniquement) : navigation rapide entre les quatre
 * espaces, avec connexion automatique au compte de démonstration correspondant.
 */
export function DemoBar({ active, right }: { active?: Space; right?: React.ReactNode }) {
  if (!env.DEMO_MODE) return null;
  const base = env.APP_URL.replace(/\/$/, '');
  return (
    <div className="no-print demo-bar" lang="fr">
      <a href={`${base}/`} style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 17, color: '#F7F4EC' }}>
        terricom<span style={{ color: '#F4B266' }}>.</span>
      </a>
      <nav aria-label="Espaces de démonstration" className="demo-bar-nav">
        {LINKS.map((l) => (
          <a
            key={l.key}
            href={`${base}${active === 'haut-doubs' && l.key === 'collectivite' ? '/demo/entrer/haut-doubs' : l.href}`}
            aria-current={l.key === active ? 'page' : undefined}
            style={
              l.key === active
                ? { color: '#14201B', background: '#F4B266', padding: '6px 12px', borderRadius: 999, fontWeight: 600 }
                : { color: '#AEBDB5', padding: '6px 12px', borderRadius: 999 }
            }
          >
            {l.label}
          </a>
        ))}
      </nav>
      <div className="demo-bar-note">{right ?? 'Démonstration · données fictives'}</div>
    </div>
  );
}
