import type { Metadata } from 'next';
import { LegalShell } from '@/components/portal/LegalShell';
import { SiteShell } from '@/components/site/SiteChrome';

// Rendu à la demande : chiffres réels et configuration lue à l'exécution (jamais figés à la compilation).
export const dynamic = 'force-dynamic';

export const metadata: Metadata = { title: 'Accessibilité', alternates: { canonical: '/accessibilite' } };

export default function AccessibilityPage() {
  return (
    <SiteShell>
      <LegalShell eyebrow="Informations" title="Déclaration d’accessibilité" updated="1er septembre 2026">
        <p>
          terricom s’engage à rendre ses services accessibles conformément à l’article 47 de la loi n° 2005-102 du 11 février 2005 et au référentiel général
          d’amélioration de l’accessibilité (RGAA 4.1). Cette déclaration s’applique au site terricom.fr et aux portails des territoires ; chaque collectivité
          publie sa propre déclaration pour son portail.
        </p>
        <h2>État de conformité</h2>
        <p>
          Le site n’a pas encore fait l’objet d’un audit de conformité externe : il est donc déclaré <b>non conforme</b> au sens réglementaire, dans l’attente
          de cet audit. Il a toutefois été conçu selon les critères du RGAA : structure sémantique, navigation au clavier, contrastes conformes à la charte,
          alternatives textuelles, formulaires étiquetés, respect de la préférence « animations réduites ».
        </p>
        <h2>Technologies utilisées</h2>
        <p>HTML5, CSS, JavaScript (React). Les cartes interactives disposent d’une alternative sous forme de liste.</p>
        <h2>Retour d’information et contact</h2>
        <p>
          Si vous n’arrivez pas à accéder à un contenu ou à un service, écrivez à <a href="mailto:accessibilite@terricom.fr">accessibilite@terricom.fr</a> :
          nous vous proposerons une alternative accessible.
        </p>
        <h2>Voies de recours</h2>
        <p>
          Si vous constatez un défaut d’accessibilité vous empêchant d’accéder à un contenu et que vous n’obtenez pas de réponse satisfaisante, vous pouvez
          écrire au Défenseur des droits, le contacter via son formulaire en ligne ou par courrier gratuit : Défenseur des droits, Libre réponse 71120, 75342
          Paris CEDEX 07.
        </p>
      </LegalShell>
    </SiteShell>
  );
}
