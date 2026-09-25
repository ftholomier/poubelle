import type { Metadata } from 'next';
import Link from 'next/link';
import { MkCta, MkFaq, MkFeature, MkGrid, MkHero, MkSection, MkSteps } from '@/components/site/Blocks';
import { SiteShell } from '@/components/site/SiteChrome';
import { MODULE_ORDER, MODULES, type ModuleKey } from '@/lib/constants';
import { fmtInt } from '@/lib/format';
import { pilotShowcase } from '@/server/services/marketing';

// Rendu à la demande : chiffres réels et configuration lue à l'exécution (jamais figés à la compilation).
export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Pour les collectivités',
  description:
    'Communautés de communes, agglomérations, communes : donnez une vitrine à chaque entreprise du territoire, animez le commerce local et pilotez son adoption. Hébergé en France.',
  alternates: { canonical: '/collectivites' },
};

const MODULE_TEXT: Record<ModuleKey, string> = {
  PORTAL: 'Un portail aux couleurs du territoire, une fiche optimisée pour Google par établissement, des pages communes.',
  MAP: 'Carte interactive filtrable (ouvert maintenant, catégories, labels), itinéraires et regroupement des repères.',
  NEWSLETTER: 'Lettre d’information du territoire : audiences par commune, double consentement, statistiques d’ouverture.',
  IMPORT: 'Import de la base SIRENE ou d’un fichier CSV : fiches précréées, doublons détectés, invitations par courrier ou email.',
  CAMPAIGNS: 'Campagnes commerciales (Noël, fête des mères, marchés) avec participation des pros et page dédiée.',
  AI: 'Assistant de rédaction pour les pros, audit de fiche, recherche en langage naturel et assistant de campagnes.',
  CIRCUITS: 'Parcours découverte avec passeport et tampons par QR code, pour faire circuler les visiteurs.',
  JOBS: 'Offres d’emploi et d’apprentissage des entreprises locales, candidatures avec CV sécurisées.',
  MULTILINGUAL: 'Portail et fiches traduits pour les visiteurs étrangers.',
  APPOINTMENTS: 'Prise de rendez-vous en ligne pour les prestataires de services.',
};

export default async function CollectivitesPage() {
  const pilot = await pilotShowcase();
  return (
    <SiteShell current="/collectivites">
      <MkHero
        eyebrow="Pour les collectivités"
        title={
          <>
            Toute l’économie de votre territoire, <span style={{ color: 'var(--green)' }}>en vitrine</span> dès le premier jour.
          </>
        }
        text="Communauté de communes, agglomération ou commune : chaque commerce, artisan et producteur obtient une fiche référencée, gratuitement. Vous animez, vous mesurez, vous décidez."
        actions={
          <>
            <Link href="/demo" className="btn btn-dark">
              Demander une démo
            </Link>
            {pilot ? (
              <a href={pilot.links.home} className="btn btn-outline" style={{ border: '1.5px solid var(--ink)', color: 'var(--ink)' }}>
                Voir le territoire pilote
              </a>
            ) : null}
          </>
        }
        image="https://images.unsplash.com/photo-1528605248644-14dd04022da1?w=1200&q=70&auto=format&fit=crop"
        sticker={pilot ? `${fmtInt(pilot.establishments)} fiches en ligne · ${pilot.territory.name}` : undefined}
      />

      <MkSection eyebrow="Mise en service" title="Quatre semaines pour passer en vitrine">
        <MkSteps
          steps={[
            ['Import SIRENE', 'Toutes les entreprises actives de vos communes sont précréées depuis la base de l’INSEE : personne n’est oublié.'],
            ['Invitation des pros', 'Courrier avec QR code, email et relances : chaque professionnel revendique sa fiche en 5 minutes, identité vérifiée.'],
            ['Animation', 'Campagnes, agenda, circuits et newsletter font revenir les habitants sur le portail… et dans les boutiques.'],
            ['Pilotage', 'Adoption par commune, recherches des habitants, signaux faibles et rapport PDF pour vos élus.'],
          ]}
        />
      </MkSection>

      <MkSection eyebrow="Modules" title="Une plateforme, des modules à activer selon vos besoins">
        <MkGrid>
          {MODULE_ORDER.map((m) => (
            <MkFeature
              key={m}
              title={MODULES[m].label}
              tag={MODULES[m].tag === 'MVP' ? 'Inclus' : 'Option'}
              tagBg={MODULES[m].tag === 'MVP' ? '#D6E8B4' : '#DCD3F3'}
            >
              {MODULE_TEXT[m]}
            </MkFeature>
          ))}
        </MkGrid>
      </MkSection>

      <MkSection eyebrow="Organisation" title="L’intercommunalité pilote, chaque commune agit">
        <MkGrid>
          <MkFeature title="Administrateurs territoriaux">
            Paramètrent le portail, valident les revendications, lancent campagnes et newsletters pour l’ensemble du territoire.
          </MkFeature>
          <MkFeature title="Administrateurs communaux">
            Gèrent les entreprises et l’agenda de leur commune, avec leurs propres statistiques : la mairie reste au plus près de ses commerçants.
          </MkFeature>
          <MkFeature title="Professionnels">
            Tiennent leur fiche à jour, publient actualités et promotions, suivent leurs visites. L’offre Essentiel est offerte par la collectivité.
          </MkFeature>
        </MkGrid>
      </MkSection>

      <MkSection eyebrow="Confiance" title="Hébergé en France, sécurisé, conforme">
        <MkGrid min={240}>
          <MkFeature title="Données en France">
            Hébergement en France, sauvegardes chiffrées quotidiennes, infrastructure redondée sur plusieurs zones.
          </MkFeature>
          <MkFeature title="RGPD">
            terricom agit comme sous-traitant (article 28) : convention de traitement, registre, durées de conservation, exercice des droits outillé.
          </MkFeature>
          <MkFeature title="Sécurité des accès">
            Double authentification obligatoire pour les administrateurs, rôles par territoire et commune, journal d’audit inaltérable.
          </MkFeature>
          <MkFeature title="Commande publique">
            Facturation conforme au secteur public : bon de commande ou marché, mandat administratif, dépôt sur Chorus Pro.
          </MkFeature>
        </MkGrid>
      </MkSection>

      <MkSection title="Questions fréquentes">
        <MkFaq
          items={[
            [
              'Les entreprises doivent-elles payer ?',
              'Non. La fiche complète, la revendication, la mise à jour et les publications de base sont gratuites pour tous les professionnels, financées par la licence de la collectivité. Des options Premium existent pour ceux qui veulent aller plus loin.',
            ],
            [
              'Que se passe-t-il pour les entreprises qui ne revendiquent pas leur fiche ?',
              'Leur fiche reste en ligne avec les informations publiques (nom, adresse, activité). Vous pouvez les compléter vous-même, les relancer par courrier avec QR code, ou les masquer si l’activité a cessé.',
            ],
            [
              'Peut-on utiliser notre propre nom de domaine ?',
              'Oui : le portail est servi sur votre adresse (par exemple commerces.votre-territoire.fr) avec certificat HTTPS automatique, ou sur votre-territoire.terricom.fr.',
            ],
            [
              'Combien de temps pour démarrer ?',
              'Quatre semaines en moyenne : paramétrage, import SIRENE, formation des administrateurs communaux, puis lancement auprès des professionnels.',
            ],
            [
              'Nos communes peuvent-elles changer d’intercommunalité ?',
              'Oui : le rattachement d’une commune est historisé, ses entreprises et leurs statistiques suivent la commune.',
            ],
          ]}
        />
      </MkSection>
      <MkCta />
    </SiteShell>
  );
}
