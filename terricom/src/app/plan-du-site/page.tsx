import type { Metadata } from 'next';
import Link from 'next/link';
import { LegalShell } from '@/components/portal/LegalShell';
import { SiteShell } from '@/components/site/SiteChrome';
import type { ModuleKey } from '@/lib/constants';
import { liveTerritories } from '@/server/services/marketing';
import { getEnabledModules } from '@/server/services/territories';
import { portalUrl } from '@/server/urls';

// Rendu à la demande : la liste des territoires est lue à l'exécution (jamais figée à la compilation).
export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Plan du site',
  description: 'Toutes les pages de terricom.fr et les portails des territoires en ligne.',
  alternates: { canonical: '/plan-du-site' },
};

const SECTIONS: { title: string; links: [string, string][] }[] = [
  {
    title: 'Découvrir terricom',
    links: [
      ['/', 'Présentation'],
      ['/collectivites', 'Pour les collectivités'],
      ['/professionnels', 'Pour les professionnels'],
      ['/territoires', 'Territoires en ligne'],
      ['/tarifs', 'Tarifs'],
      ['/marque', 'La marque et sa charte'],
      ['/demo', 'Demander une démonstration'],
    ],
  },
  {
    title: 'Espaces',
    links: [
      ['/pro/revendiquer', 'Trouver et revendiquer ma fiche'],
      ['/pro/inscription', 'Inscrire mon activité'],
      ['/connexion', 'Se connecter'],
      ['/mot-de-passe-oublie', 'Mot de passe oublié'],
    ],
  },
  {
    title: 'Informations',
    links: [
      ['/mentions-legales', 'Mentions légales'],
      ['/cgu', 'Conditions générales d’utilisation'],
      ['/cgv', 'Conditions générales de vente'],
      ['/confidentialite', 'Politique de confidentialité'],
      ['/accessibilite', 'Déclaration d’accessibilité'],
    ],
  },
];

// Pages d'un portail, avec le module dont elles dépendent le cas échéant.
const PORTAL_PAGES: [string, string, ModuleKey?][] = [
  ['', 'Accueil du portail'],
  ['/explorer', 'Carte et recherche'],
  ['/communes', 'Communes'],
  ['/actualites', 'Actualités des pros'],
  ['/agenda', 'Agenda et marchés'],
  ['/circuits', 'Circuits', 'CIRCUITS'],
  ['/emploi', 'Emploi local', 'JOBS'],
];

export default async function SiteMapPage() {
  const territories = await Promise.all((await liveTerritories()).map(async (t) => ({ ...t, modules: await getEnabledModules(t.id) })));
  return (
    <SiteShell>
      <LegalShell eyebrow="Navigation" title="Plan du site">
        {SECTIONS.map((s) => (
          <section key={s.title}>
            <h2>{s.title}</h2>
            <ul>
              {s.links.map(([href, label]) => (
                <li key={href}>
                  <Link href={href}>{label}</Link>
                </li>
              ))}
            </ul>
          </section>
        ))}
        <section>
          <h2>Portails des territoires</h2>
          {territories.map((t) => (
            <div key={t.id}>
              <h3>{t.name}</h3>
              <ul>
                {PORTAL_PAGES.filter(([, , m]) => !m || t.modules.has(m)).map(([path, label]) => (
                  <li key={path}>
                    <a href={portalUrl({ slug: t.slug, primaryHost: t.primary_host }, path)}>{label}</a>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </section>
      </LegalShell>
    </SiteShell>
  );
}
