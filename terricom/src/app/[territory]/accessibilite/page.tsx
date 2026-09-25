import type { Metadata } from 'next';
import { LegalShell } from '@/components/portal/LegalShell';
import { getPortal } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory } = await params;
  const { territory: t } = await getPortal(territory);
  return { title: 'Accessibilité', alternates: { canonical: portalUrl(t, '/accessibilite') } };
}

export default async function AccessibilityPage({ params }: Props) {
  const { territory } = await params;
  const { territory: t } = await getPortal(territory);
  return (
    <LegalShell eyebrow="Informations" title="Déclaration d'accessibilité">
      <p>
        {t.legalName} s&apos;engage à rendre ce portail accessible conformément à l&apos;article 47 de la loi n° 2005-102 du 11 février 2005 et au
        Référentiel général d&apos;amélioration de l&apos;accessibilité (RGAA 4.1).
      </p>
      <h2>État de conformité</h2>
      <p>
        Le portail est <b>partiellement conforme</b> au RGAA 4.1 : l&apos;audit de conformité complet est programmé. La plateforme a été conçue pour
        l&apos;accessibilité : navigation au clavier, lien d&apos;évitement, contrastes renforcés, textes alternatifs, formulaires étiquetés, respect des
        préférences de réduction des animations.
      </p>
      <h2>Contenus non accessibles</h2>
      <ul>
        <li>Les cartes interactives ne sont pas pleinement utilisables au clavier et aux lecteurs d&apos;écran ; chaque carte est accompagnée d&apos;une liste équivalente.</li>
        <li>Certaines photographies publiées par les professionnels peuvent être dépourvues de description.</li>
      </ul>
      <h2>Retour d&apos;information et contact</h2>
      <p>
        Si vous n&apos;arrivez pas à accéder à un contenu ou à un service, contactez-nous
        {t.contactEmail ? (
          <>
            {' '}
            à <a href={`mailto:${t.contactEmail}`}>{t.contactEmail}</a>
          </>
        ) : null}{' '}
        pour être orienté·e vers une alternative accessible ou obtenir le contenu sous une autre forme.
      </p>
      <h2>Voies de recours</h2>
      <p>
        Si vous constatez un défaut d&apos;accessibilité vous empêchant d&apos;accéder à un contenu, et que vous n&apos;avez pas obtenu de réponse
        satisfaisante, vous pouvez saisir le Défenseur des droits (formulaire en ligne, délégué territorial ou courrier gratuit : Défenseur des droits,
        Libre réponse 71120, 75342 Paris CEDEX 07).
      </p>
    </LegalShell>
  );
}
