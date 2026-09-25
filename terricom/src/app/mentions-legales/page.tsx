import type { Metadata } from 'next';
import Link from 'next/link';
import { LegalShell } from '@/components/portal/LegalShell';
import { SiteShell } from '@/components/site/SiteChrome';
import { env } from '@/server/env';

export const metadata: Metadata = { title: 'Mentions légales', alternates: { canonical: '/mentions-legales' } };

export default function PlatformLegalPage() {
  return (
    <SiteShell>
      <LegalShell eyebrow="Informations" title="Mentions légales">
        <h2>Éditeur</h2>
        <p>
          {env.COMPANY_LEGAL_NAME}
          <br />
          {env.COMPANY_ADDRESS}
          {env.COMPANY_SIREN ? (
            <>
              <br />
              SIREN {env.COMPANY_SIREN}
            </>
          ) : null}
          {env.COMPANY_VAT_NUMBER ? (
            <>
              <br />
              TVA intracommunautaire {env.COMPANY_VAT_NUMBER}
            </>
          ) : null}
          <br />
          Contact : <a href="mailto:bonjour@terricom.fr">bonjour@terricom.fr</a>
        </p>
        <p>Directeur ou directrice de la publication : {env.PUBLICATION_DIRECTOR ?? 'le représentant légal de la société éditrice'}.</p>
        <h2>Hébergement</h2>
        <p>{env.HOSTING_PROVIDER}. Les données sont hébergées en France, sur une infrastructure redondée avec sauvegardes chiffrées.</p>
        <h2>Portails des territoires</h2>
        <p>
          Chaque portail de territoire est édité par la collectivité concernée, qui en est responsable ; ses mentions légales figurent en pied de page du
          portail. terricom en assure la conception, l’hébergement et la maintenance en qualité de prestataire et de sous-traitant au sens du RGPD.
        </p>
        <h2>Propriété intellectuelle</h2>
        <p>
          La marque terricom, son logo, sa charte graphique et le logiciel de la plateforme sont protégés. Les contenus des fiches appartiennent à leurs auteurs
          (professionnels, collectivités) ; les données d’entreprises issues de la base SIRENE sont réutilisées sous Licence Ouverte Etalab 2.0.
        </p>
        <h2>Données personnelles</h2>
        <p>
          Voir la <Link href="/confidentialite">politique de confidentialité</Link>. Délégué à la protection des données :{' '}
          <a href={`mailto:${env.DPO_EMAIL}`}>{env.DPO_EMAIL}</a>.
        </p>
      </LegalShell>
    </SiteShell>
  );
}
