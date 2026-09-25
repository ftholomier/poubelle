import { env } from '@/server/env';

type Space = 'presentation' | 'portail' | 'pro' | 'collectivite' | 'console' | 'marque';

const LINKS: { key: Space; label: string; href: string }[] = [
  { key: 'presentation', label: 'Présentation', href: '/' },
  { key: 'portail', label: 'Portail public', href: '/valdeloue' },
  { key: 'pro', label: 'Espace entreprise', href: '/demo/entrer/pro' },
  { key: 'collectivite', label: 'Back-office collectivité', href: '/demo/entrer/collectivite' },
  { key: 'console', label: 'Console plateforme', href: '/demo/entrer/console' },
  { key: 'marque', label: 'La marque', href: '/marque' },
];

/**
 * Barre de démonstration (mode DEMO_MODE uniquement) : navigation rapide entre les quatre
 * espaces, avec connexion automatique au compte de démonstration correspondant.
 */
export function DemoBar({ active, right }: { active?: Space; right?: React.ReactNode }) {
  if (!env.DEMO_MODE) return null;
  const base = env.APP_URL.replace(/\/$/, '');
  return (
    <div
      className="no-print"
      style={{
        background: '#14201B',
        color: '#F7F4EC',
        display: 'flex',
        alignItems: 'center',
        gap: 18,
        padding: '9px 20px',
        flexWrap: 'wrap',
        fontSize: 13,
        borderBottom: '1px solid #2A3A33',
      }}
    >
      <a href={`${base}/`} style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 17, color: '#F7F4EC' }}>
        terricom<span style={{ color: '#F4B266' }}>.</span>
      </a>
      <nav aria-label="Espaces de démonstration" style={{ display: 'flex', gap: 4, flexWrap: 'wrap' }}>
        {LINKS.map((l) => (
          <a
            key={l.key}
            href={`${base}${l.href}`}
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
      <div style={{ marginLeft: 'auto', color: '#7F9087', display: 'flex', gap: 6, alignItems: 'center', flexWrap: 'wrap' }}>
        {right ?? 'Démonstration · données fictives'}
      </div>
    </div>
  );
}
