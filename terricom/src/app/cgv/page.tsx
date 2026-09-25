import type { Metadata } from 'next';
import { LegalShell } from '@/components/portal/LegalShell';
import { SiteShell } from '@/components/site/SiteChrome';
import { fmtEuros } from '@/lib/format';
import { env } from '@/server/env';
import { getPlans } from '@/server/services/billing';

// Rendu à la demande : chiffres réels et configuration lue à l'exécution (jamais figés à la compilation).
export const dynamic = 'force-dynamic';

export const metadata: Metadata = { title: 'Conditions générales de vente', alternates: { canonical: '/cgv' } };

export default async function CgvPage() {
  const plans = (await getPlans()).filter((p) => p.priceMonthlyCents > 0);
  return (
    <SiteShell>
      <LegalShell eyebrow="Informations" title="Conditions générales de vente" updated="1er septembre 2026">
        <h2>1. Champ d’application</h2>
        <p>
          Les présentes conditions s’appliquent aux ventes conclues par {env.COMPANY_LEGAL_NAME} : options payantes souscrites par les professionnels depuis
          l’espace entreprise, licences et prestations commandées par les collectivités. Les prix sont exprimés hors taxes, TVA en sus au taux en vigueur.
        </p>
        <h2>2. Options des professionnels</h2>
        <p>
          {plans.map((p, i) => (
            <span key={p.key}>
              {i ? ' ; ' : ''}offre {p.name} : {fmtEuros(p.priceMonthlyCents, { decimals: true })} HT par mois
            </span>
          ))}
          . L’abonnement est mensuel, sans engagement, payable d’avance par carte bancaire ou prélèvement SEPA via notre prestataire de paiement. Il se
          renouvelle tacitement chaque mois et peut être résilié à tout moment depuis l’espace entreprise ; la résiliation prend effet à la fin de la période en
          cours.
        </p>
        <p>
          Les professionnels agissant pour les besoins de leur activité ne bénéficient pas du droit de rétractation prévu pour les consommateurs. En cas de
          retard de paiement, des pénalités au taux d’intérêt légal majoré de dix points et une indemnité forfaitaire de 40 € pour frais de recouvrement sont
          dues (article L. 441-10 du code de commerce) ; l’option peut être suspendue après relance restée sans effet.
        </p>
        <h2>3. Licences des collectivités</h2>
        <p>
          La licence est commandée par bon de commande ou dans le cadre d’un marché public, pour une durée de douze mois renouvelable. La mise en service
          (paramétrage, import, formation) est facturée à la commande. Les factures sont émises au format électronique et déposées sur Chorus Pro ; elles sont
          payables par mandat administratif dans le délai global de paiement de 30 jours. Tout retard ouvre droit aux intérêts moratoires et à l’indemnité
          forfaitaire prévus par le code de la commande publique.
        </p>
        <h2>4. Réversibilité</h2>
        <p>
          À l’échéance de la licence, la collectivité peut obtenir l’export de ses données (fiches, contenus, abonnés ayant consenti, statistiques agrégées)
          dans un format ouvert. Les données sont ensuite supprimées dans un délai de trois mois, sauf obligation légale de conservation.
        </p>
        <h2>5. Responsabilité</h2>
        <p>
          terricom est tenu d’une obligation de moyens. Sa responsabilité ne saurait excéder le montant payé au titre des douze derniers mois pour le service
          concerné, sauf faute lourde ou dolosive.
        </p>
        <h2>6. Réclamations</h2>
        <p>
          Toute réclamation relative à une facture est adressée à <a href="mailto:facturation@terricom.fr">facturation@terricom.fr</a>. Le droit français est
          applicable.
        </p>
      </LegalShell>
    </SiteShell>
  );
}
