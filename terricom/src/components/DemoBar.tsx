import { inArray } from 'drizzle-orm';
import { cache } from 'react';
import { db } from '@/server/db';
import { territories } from '@/server/db/schema';
import { env } from '@/server/env';

type Space = 'presentation' | 'portail' | 'pro' | 'collectivite' | 'console' | 'marque' | 'haut-doubs';

/** Jeu de démonstration chargé : territoire réel seul (par défaut) ou jeu fictif des tests (Val de Loue). */
const demoTerritories = cache(async () => {
  const rows = await db
    .select({ slug: territories.slug })
    .from(territories)
    .where(inArray(territories.slug, ['valdeloue', 'haut-doubs']))
    .catch(() => []);
  const slugs = new Set(rows.map((r) => r.slug));
  return { fictif: slugs.has('valdeloue'), real: slugs.has('haut-doubs') };
});

/**
 * Barre de démonstration (mode DEMO_MODE uniquement) : navigation rapide entre les espaces, avec connexion
 * automatique au compte de démonstration correspondant. `real` : page du territoire réel (Haut-Doubs).
 */
export async function DemoBar({ active, right, real }: { active?: Space; right?: React.ReactNode; real?: boolean }) {
  if (!env.DEMO_MODE) return null;
  const base = env.APP_URL.replace(/\/$/, '');
  const { fictif, real: hasReal } = await demoTerritories();
  // Démonstration réelle : le portail et le back-office sont ceux du Haut-Doubs ; le jeu fictif n'existe que
  // pour les tests, où le territoire réel garde son propre lien.
  const links: { key: Space; label: string; href: string }[] = [
    { key: 'presentation', label: 'Présentation', href: '/' },
    { key: 'portail', label: 'Portail public', href: fictif ? '/valdeloue' : '/haut-doubs' },
    { key: 'pro', label: 'Espace entreprise', href: '/demo/entrer/pro' },
    { key: 'collectivite', label: 'Back-office collectivité', href: '/demo/entrer/collectivite' },
    { key: 'console', label: 'Console plateforme', href: '/demo/entrer/console' },
    { key: 'marque', label: 'La marque', href: '/marque' },
    ...(fictif && hasReal ? [{ key: 'haut-doubs' as const, label: 'Haut-Doubs (données réelles)', href: '/haut-doubs' }] : []),
  ];
  const current: Space | undefined = real && fictif ? 'haut-doubs' : active;
  const note = right ?? (fictif ? 'Démonstration · données fictives' : 'Entreprises réelles (SIRENE) · exemples signalés');
  return (
    <div className="no-print demo-bar" lang="fr">
      <a href={`${base}/`} style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 17, color: '#F7F4EC' }}>
        terricom<span style={{ color: '#F4B266' }}>.</span>
      </a>
      <nav aria-label="Espaces de démonstration" className="demo-bar-nav">
        {links.map((l) => (
          <a
            key={l.key}
            href={`${base}${current === 'haut-doubs' && l.key === 'collectivite' ? '/demo/entrer/haut-doubs' : l.href}`}
            aria-current={l.key === current ? 'page' : undefined}
            style={
              l.key === current
                ? { color: '#14201B', background: '#F4B266', padding: '6px 12px', borderRadius: 999, fontWeight: 600 }
                : { color: '#AEBDB5', padding: '6px 12px', borderRadius: 999 }
            }
          >
            {l.label}
          </a>
        ))}
      </nav>
      <div className="demo-bar-note">{note}</div>
    </div>
  );
}
