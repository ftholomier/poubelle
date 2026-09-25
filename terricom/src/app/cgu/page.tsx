import type { Metadata } from 'next';
import Link from 'next/link';
import { LegalShell } from '@/components/portal/LegalShell';
import { SiteShell } from '@/components/site/SiteChrome';
import { env } from '@/server/env';

export const metadata: Metadata = { title: 'Conditions générales d’utilisation', alternates: { canonical: '/cgu' } };

export default function CguPage() {
  return (
    <SiteShell>
      <LegalShell eyebrow="Informations" title="Conditions générales d’utilisation" updated="1er septembre 2026">
        <h2>1. Objet</h2>
        <p>
          Les présentes conditions encadrent l’utilisation de la plateforme terricom, éditée par {env.COMPANY_LEGAL_NAME}, par les professionnels (espace
          entreprise), les agents des collectivités (back-office) et les visiteurs des portails de territoire.
        </p>
        <h2>2. Accès et comptes</h2>
        <p>
          La consultation des portails est libre et gratuite. La gestion d’une fiche ou d’un back-office nécessite un compte nominatif, protégé par un mot de
          passe et, pour les administrateurs, par une double authentification. Chaque utilisateur est responsable de la confidentialité de ses identifiants et
          s’engage à signaler sans délai toute utilisation frauduleuse.
        </p>
        <h2>3. Fiches des établissements</h2>
        <p>
          Les fiches peuvent être créées par la collectivité à partir de données publiques (base SIRENE). Un professionnel peut revendiquer la fiche de son
          établissement après vérification de son identité et de son lien avec l’entreprise (SIRET, code de vérification, Kbis). Toute revendication abusive
          entraîne la suppression du compte.
        </p>
        <p>
          L’offre Essentiel est gratuite pour les professionnels, financée par la collectivité. Les options payantes sont régies par les{' '}
          <Link href="/cgv">conditions générales de vente</Link>.
        </p>
        <h2>4. Contenus publiés</h2>
        <p>
          Le professionnel est seul responsable des informations, textes, photos et offres qu’il publie. Il garantit détenir les droits nécessaires et
          s’interdit tout contenu illicite, trompeur, discriminatoire ou portant atteinte aux droits de tiers. Il concède à la collectivité et à terricom le
          droit de diffuser ces contenus sur le portail, la newsletter et les supports de communication du territoire, pour la durée de la publication.
        </p>
        <p>
          La collectivité assure la modération des contenus et peut suspendre une fiche ou retirer une publication manifestement non conforme. Tout contenu
          signalé est examiné dans les meilleurs délais.
        </p>
        <h2>5. Assistant de rédaction</h2>
        <p>
          Les textes proposés par l’assistant d’intelligence artificielle sont des suggestions : le professionnel les relit et reste responsable de leur
          publication. Aucune donnée personnelle ne doit être saisie dans les demandes adressées à l’assistant.
        </p>
        <h2>6. Disponibilité</h2>
        <p>
          terricom met en œuvre les moyens raisonnables pour assurer la disponibilité et la sécurité du service (hébergement redondé en France, sauvegardes,
          supervision). Des interruptions pour maintenance peuvent intervenir, annoncées lorsque c’est possible.
        </p>
        <h2>7. Suspension et suppression</h2>
        <p>
          Un compte peut être suspendu en cas de manquement aux présentes conditions. L’utilisateur peut supprimer son compte à tout moment depuis « Mon compte
          » ; la fiche de l’établissement reste alors gérée par la collectivité.
        </p>
        <h2>8. Données personnelles</h2>
        <p>
          Le traitement des données est décrit dans la <Link href="/confidentialite">politique de confidentialité</Link>.
        </p>
        <h2>9. Droit applicable</h2>
        <p>Les présentes conditions sont soumises au droit français. À défaut d’accord amiable, les tribunaux compétents sont ceux du siège de l’éditeur.</p>
      </LegalShell>
    </SiteShell>
  );
}
