import type { Metadata } from 'next';
import { LegalShell } from '@/components/portal/LegalShell';
import type { TerritorySettings } from '@/server/db/schema';
import { getPortal } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory } = await params;
  const { territory: t } = await getPortal(territory);
  return { title: 'Données personnelles', alternates: { canonical: portalUrl(t, '/donnees-personnelles') } };
}

export default async function PrivacyPage({ params }: Props) {
  const { territory } = await params;
  const { territory: t, modules } = await getPortal(territory);
  const s = (t.settings ?? {}) as TerritorySettings;
  const dpo = s.dpoEmail ?? t.contactEmail;
  return (
    <LegalShell eyebrow="Informations" title="Vos données personnelles">
      <p>
        {t.legalName}, responsable de ce portail, s&apos;engage à ne collecter que les données strictement nécessaires et à ne jamais les revendre. La
        plateforme terricom agit en qualité de sous-traitant, conformément à l&apos;article 28 du RGPD.
      </p>
      <h2>Ce que nous collectons, et pourquoi</h2>
      <table>
        <thead>
          <tr>
            <th>Traitement</th>
            <th>Données</th>
            <th>Base légale</th>
            <th>Conservation</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>Mesure d&apos;audience</td>
            <td>Pages vues, provenance, type d&apos;appareil. Aucun cookie : un identifiant anonyme, renouvelé chaque jour, est calculé côté serveur.</td>
            <td>Intérêt légitime (exemption de consentement CNIL)</td>
            <td>13 mois, puis agrégats anonymes</td>
          </tr>
          {modules.has('NEWSLETTER') ? (
            <tr>
              <td>Lettre d&apos;information</td>
              <td>Adresse email, commune et centres d&apos;intérêt (facultatifs), preuve du consentement</td>
              <td>Consentement (double confirmation)</td>
              <td>Jusqu&apos;à la désinscription, 3 ans maximum sans ouverture</td>
            </tr>
          ) : null}
          <tr>
            <td>Messages aux professionnels</td>
            <td>Nom, coordonnées, message</td>
            <td>Consentement</td>
            <td>3 ans après le dernier échange</td>
          </tr>
          {modules.has('JOBS') ? (
            <tr>
              <td>Candidatures</td>
              <td>Identité, coordonnées, CV, message — transmis au seul employeur</td>
              <td>Mesures précontractuelles</td>
              <td>2 ans maximum</td>
            </tr>
          ) : null}
          {modules.has('APPOINTMENTS') ? (
            <tr>
              <td>Demandes de rendez-vous</td>
              <td>Identité, coordonnées, créneau souhaité</td>
              <td>Mesures précontractuelles</td>
              <td>12 mois</td>
            </tr>
          ) : null}
          {modules.has('CIRCUITS') ? (
            <tr>
              <td>Passeport des circuits</td>
              <td>Un jeton anonyme (cookie technique) et les étapes tamponnées, sans aucune donnée d&apos;identité</td>
              <td>Exécution du service demandé</td>
              <td>12 mois</td>
            </tr>
          ) : null}
        </tbody>
      </table>
      <h2>Cookies</h2>
      <p>
        Ce portail n&apos;utilise aucun cookie publicitaire ni traceur tiers. Seuls des cookies techniques strictement nécessaires peuvent être déposés (session
        des professionnels connectés, passeport de circuit) : ils ne requièrent pas de consentement.
      </p>
      {modules.has('AI') ? (
        <>
          <h2>Recherche assistée par intelligence artificielle</h2>
          <p>
            Lorsque vous formulez une recherche en langage naturel, le texte de votre question (sans donnée d&apos;identification) est transmis à notre
            prestataire d&apos;intelligence artificielle pour être interprété. N&apos;y indiquez pas d&apos;informations personnelles.
          </p>
        </>
      ) : null}
      <h2>Vos droits</h2>
      <p>
        Vous disposez d&apos;un droit d&apos;accès, de rectification, d&apos;effacement, de limitation, d&apos;opposition et de portabilité de vos données. Pour
        les exercer, écrivez à {dpo ? <a href={`mailto:${dpo}`}>{dpo}</a> : 'la collectivité'}. Vous pouvez également introduire une réclamation auprès de la
        CNIL (cnil.fr).
      </p>
      <h2>Sécurité</h2>
      <p>
        Les données sont hébergées en France, chiffrées en transit et au repos, sauvegardées quotidiennement. Les accès des agents et des professionnels sont
        nominatifs, protégés par double authentification pour les administrateurs, et journalisés.
      </p>
    </LegalShell>
  );
}
