import type { Metadata } from 'next';
import { LegalShell } from '@/components/portal/LegalShell';
import type { TerritorySettings } from '@/server/db/schema';
import { getPortal } from '@/server/services/portal';
import { appUrl, portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory } = await params;
  const { territory: t } = await getPortal(territory);
  return { title: 'Mentions légales', alternates: { canonical: portalUrl(t, '/mentions-legales') } };
}

export default async function LegalPage({ params }: Props) {
  const { territory } = await params;
  const { territory: t } = await getPortal(territory);
  const s = (t.settings ?? {}) as TerritorySettings;
  return (
    <LegalShell eyebrow="Informations" title="Mentions légales">
      <h2>Éditeur du portail</h2>
      <p>
        {s.legalPublisher ?? t.legalName}
        <br />
        Contact : {t.contactEmail ? <a href={`mailto:${t.contactEmail}`}>{t.contactEmail}</a> : 'voir la page de la collectivité'}
      </p>
      <p>Le directeur ou la directrice de la publication est le représentant légal de la collectivité éditrice.</p>
      <h2>Plateforme et hébergement</h2>
      <p>
        Ce portail est propulsé par la plateforme <a href={appUrl('/')}>terricom</a>, qui en assure la conception, la maintenance et l&apos;hébergement pour le
        compte de la collectivité.
      </p>
      <p>
        {s.hostingNotice ??
          'Les données sont hébergées en France, sur une infrastructure redondée (plusieurs zones de disponibilité) avec sauvegardes chiffrées quotidiennes.'}
      </p>
      <h2>Contenus des fiches</h2>
      <p>
        Les fiches sont initialisées à partir de la base SIRENE de l&apos;INSEE (données publiques, Licence Ouverte Etalab 2.0), puis complétées et mises à jour
        par les professionnels eux-mêmes. Chaque professionnel est responsable des informations qu&apos;il publie ; la collectivité assure une modération.
      </p>
      <p>Une information vous semble erronée ? Utilisez le lien « Signaler une information erronée » présent sur chaque fiche.</p>
      <h2>Cartographie</h2>
      <p>
        Fonds de carte © contributeurs OpenStreetMap, données disponibles sous licence ODbL. Les repères et informations affichés sont issus des fiches du
        portail.
      </p>
      <h2>Propriété intellectuelle</h2>
      <p>
        Les textes et photographies publiés par les professionnels restent leur propriété. Toute reproduction des contenus du portail à des fins commerciales
        est interdite sans autorisation.
      </p>
    </LegalShell>
  );
}
