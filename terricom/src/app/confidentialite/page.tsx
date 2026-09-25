import type { Metadata } from 'next';
import Link from 'next/link';
import { LegalShell } from '@/components/portal/LegalShell';
import { SiteShell } from '@/components/site/SiteChrome';
import { RETENTION } from '@/lib/constants';
import { env } from '@/server/env';

export const metadata: Metadata = { title: 'Politique de confidentialité', alternates: { canonical: '/confidentialite' } };

export default function PrivacyPage() {
  return (
    <SiteShell>
      <LegalShell eyebrow="Informations" title="Politique de confidentialité" updated="1er septembre 2026">
        <h2>Qui est responsable ?</h2>
        <p>
          Pour les <b>portails des territoires</b> (fiches, newsletter, messages, candidatures, rendez-vous), la collectivité éditrice est responsable de
          traitement ; {env.COMPANY_LEGAL_NAME} agit comme sous-traitant (article 28 du RGPD), dans le cadre d’une convention de traitement. Pour{' '}
          <b>ce site, les comptes des professionnels, la facturation et les demandes de démonstration</b>, {env.COMPANY_LEGAL_NAME} est responsable de
          traitement.
        </p>
        <h2>Données traitées et finalités</h2>
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
              <td>Comptes des professionnels et des agents</td>
              <td>Identité, email, téléphone, rôles, journal de connexion</td>
              <td>Exécution du contrat</td>
              <td>Durée du compte, puis suppression (journal d’audit : {RETENTION.auditMonths} mois)</td>
            </tr>
            <tr>
              <td>Revendication d’une fiche</td>
              <td>SIRET, fonction, justificatif (Kbis) conservé chiffré et consultable par la seule collectivité</td>
              <td>Intérêt légitime (prévention des fraudes)</td>
              <td>Justificatif supprimé 12 mois après la décision</td>
            </tr>
            <tr>
              <td>Abonnements Premium et facturation</td>
              <td>Coordonnées de facturation, historique des paiements (les données de carte restent chez le prestataire de paiement)</td>
              <td>Exécution du contrat, obligation légale</td>
              <td>10 ans (pièces comptables)</td>
            </tr>
            <tr>
              <td>Demandes de démonstration</td>
              <td>Identité, fonction, collectivité, coordonnées, message</td>
              <td>Consentement</td>
              <td>3 ans après le dernier contact</td>
            </tr>
            <tr>
              <td>Lettres d’information des territoires</td>
              <td>Email, commune et centres d’intérêt facultatifs, preuve du consentement</td>
              <td>Consentement (double confirmation)</td>
              <td>Jusqu’à la désinscription, {RETENTION.subscribersInactiveMonths / 12} ans maximum sans ouverture</td>
            </tr>
            <tr>
              <td>Mesure d’audience</td>
              <td>Pages vues, provenance, type d’appareil, sans cookie ni identifiant persistant</td>
              <td>Intérêt légitime</td>
              <td>{RETENTION.analyticsRawMonths} mois, puis agrégats anonymes</td>
            </tr>
          </tbody>
        </table>
        <h2>Destinataires et sous-traitants</h2>
        <p>
          Les données sont hébergées en France. Nos prestataires, liés par des engagements de confidentialité, sont : l’hébergeur ({env.HOSTING_PROVIDER}), le
          service d’envoi d’emails, le prestataire de paiement pour les abonnements, et le fournisseur du modèle d’intelligence artificielle utilisé par les
          assistants (Anthropic). Les textes soumis à l’assistant ne doivent contenir aucune donnée personnelle ; les éventuels transferts hors de l’Union
          européenne sont encadrés par les clauses contractuelles types de la Commission européenne.
        </p>
        <h2>Sécurité</h2>
        <p>
          Chiffrement des échanges (HTTPS) et des données sensibles, mots de passe hachés, double authentification des administrateurs, droits d’accès par rôle,
          journal d’audit inaltérable, sauvegardes chiffrées quotidiennes, analyse antivirus des documents déposés.
        </p>
        <h2>Cookies</h2>
        <p>Aucun cookie publicitaire ni traceur tiers : seuls des cookies techniques strictement nécessaires (session, sécurité) sont utilisés.</p>
        <h2>Vos droits</h2>
        <p>
          Vous disposez des droits d’accès, de rectification, d’effacement, de limitation, d’opposition et de portabilité. Les titulaires d’un compte exportent
          et suppriment leurs données depuis <Link href="/compte/donnees">Mon compte</Link>. Pour toute autre demande, écrivez à{' '}
          <a href={`mailto:${env.DPO_EMAIL}`}>{env.DPO_EMAIL}</a> : nous répondons sous un mois. Vous pouvez introduire une réclamation auprès de la CNIL
          (cnil.fr).
        </p>
      </LegalShell>
    </SiteShell>
  );
}
