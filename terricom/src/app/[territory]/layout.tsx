import '@/components/portal/portal.css';
import type { Metadata } from 'next';
import type { CSSProperties, ReactNode } from 'react';
import { DemoBar } from '@/components/DemoBar';
import { PageViewTracker } from '@/components/portal/PageViewTracker';
import { PortalFooter, PortalHeader, sectionFromPath } from '@/components/portal/PortalChrome';
import { ToastProvider } from '@/components/ui/Feedback';
import { requestInfo } from '@/server/request';
import { getPortal } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

type Props = { children: ReactNode; params: Promise<{ territory: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory: param } = await params;
  const { territory: t } = await getPortal(param);
  return {
    title: { default: `${t.name} — ${t.tagline}`, template: `%s · ${t.name}` },
    description: t.heroSubtitle ?? `Commerces, artisans et producteurs de ${t.name}.`,
    openGraph: { siteName: t.name, locale: 'fr_FR', type: 'website', url: portalUrl(t, '/') },
    alternates: { canonical: portalUrl(t, '/') },
  };
}

export default async function PortalLayout({ children, params }: Props) {
  const { territory: param } = await params;
  const portal = await getPortal(param);
  const info = await requestInfo();
  let section = sectionFromPath(info.pathname, portal.base);
  // L'entrée « campagne » du menu ne désigne que la campagne à la une, pas les autres opérations.
  if (section === 'campagne' && !info.pathname.split('/').includes(portal.featuredCampaign?.slug ?? '')) section = 'autre';
  const t = portal.territory;
  return (
    <div style={{ '--brand': t.colorPrimary, '--brand-accent': t.colorAccent } as CSSProperties}>
      <a href="#contenu" className="skip-link">
        Aller au contenu
      </a>
      <ToastProvider>
        <DemoBar active="portail" right="Maquette interactive · démonstration" />
        <PortalHeader portal={portal} section={section} />
        <main id="contenu">{children}</main>
        <PortalFooter portal={portal} />
      </ToastProvider>
      <PageViewTracker territoryId={t.id} />
    </div>
  );
}
