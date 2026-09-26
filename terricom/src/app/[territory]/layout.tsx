import '@/components/portal/portal.css';
import type { Metadata, Viewport } from 'next';
import type { CSSProperties, ReactNode } from 'react';
import { DemoBar } from '@/components/DemoBar';
import { HtmlLang } from '@/components/portal/HtmlLang';
import { PortalOffline } from '@/components/pwa/PortalOffline';
import { I18nProvider } from '@/components/portal/I18n';
import { PageViewTracker } from '@/components/portal/PageViewTracker';
import { PortalFooter, PortalHeader, sectionFromPath } from '@/components/portal/PortalChrome';
import { ToastProvider } from '@/components/ui/Feedback';
import { LOCALES, OG_LOCALE, withLang } from '@/lib/i18n';
import { territoryText } from '@/lib/i18n/territory';
import { portalT } from '@/server/i18n';
import { requestInfo } from '@/server/request';
import { getPortal } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

type Props = { children: ReactNode; params: Promise<{ territory: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory: param } = await params;
  const portal = await getPortal(param);
  const { territory: t, base } = portal;
  const tr = await portalT(portal);
  const L = tr.locale;
  // Application installable propre au portail (sur son domaine, ou sous /<territoire>).
  const q = base ? `?territoire=${t.slug}` : '';
  return {
    title: { default: `${t.name} — ${territoryText(t, 'tagline', L) ?? t.tagline}`, template: `%s · ${t.name}` },
    description: territoryText(t, 'heroSubtitle', L) ?? tr('layout.metaDesc', { name: t.name }),
    openGraph: { siteName: t.name, locale: OG_LOCALE[L], type: 'website', url: withLang(portalUrl(t, '/'), L) },
    alternates: { canonical: withLang(portalUrl(t, '/'), L) },
    applicationName: t.name,
    manifest: `/manifest.webmanifest${q}`,
    appleWebApp: { capable: true, title: t.name, statusBarStyle: 'default' },
    icons: { apple: `/api/pwa/icon?size=180${base ? `&t=${t.slug}` : ''}` },
  };
}

export async function generateViewport({ params }: Props): Promise<Viewport> {
  const { territory: param } = await params;
  const { territory: t } = await getPortal(param);
  return { themeColor: t.colorPrimary };
}

export default async function PortalLayout({ children, params }: Props) {
  const { territory: param } = await params;
  const portal = await getPortal(param);
  const info = await requestInfo();
  let section = sectionFromPath(info.pathname, portal.base);
  // L'entrée « campagne » du menu ne désigne que la campagne à la une, pas les autres opérations.
  if (section === 'campagne' && !info.pathname.split('/').includes(portal.featuredCampaign?.slug ?? '')) section = 'autre';
  const t = portal.territory;
  const tr = await portalT(portal);
  const multilingual = portal.modules.has('MULTILINGUAL');
  // Adresses de chaque langue (hreflang) : même page, paramètre ?lang=.
  const rel = portal.base && info.pathname.startsWith(portal.base) ? info.pathname.slice(portal.base.length) || '/' : info.pathname;
  const pageUrl = portalUrl(t, rel);
  return (
    <div lang={tr.locale} style={{ '--brand': t.colorPrimary, '--brand-accent': t.colorAccent } as CSSProperties}>
      {multilingual ? (
        <>
          {LOCALES.map((l) => (
            <link key={l} rel="alternate" hrefLang={l} href={l === 'fr' ? pageUrl : `${pageUrl}?lang=${l}`} />
          ))}
          <link rel="alternate" hrefLang="x-default" href={pageUrl} />
        </>
      ) : null}
      {/* Flux publics : actualités (RSS) et agenda (iCal), découvrables par les navigateurs et agrégateurs. */}
      <link rel="alternate" type="application/rss+xml" title={tr('feeds.newsTitle', { name: t.name })} href={portalUrl(t, '/actualites.xml')} />
      <link rel="alternate" type="text/calendar" title={tr('feeds.agendaTitle', { name: t.name })} href={portalUrl(t, '/agenda.ics')} />
      {/* Le conteneur porte déjà la langue ; le document entier la reprend dès l'hydratation. */}
      {tr.locale !== 'fr' ? <HtmlLang locale={tr.locale} /> : null}
      <PortalOffline base={portal.base} />
      <a href="#contenu" className="skip-link">
        {tr('common.skip')}
      </a>
      <I18nProvider locale={tr.locale}>
        <ToastProvider>
          {t.slug === 'haut-doubs' ? (
            <DemoBar active="haut-doubs" right="Entreprises réelles (SIRENE) · fiches précréées" />
          ) : (
            <DemoBar active="portail" right="Maquette interactive · démonstration" />
          )}
          <PortalHeader portal={portal} section={section} t={tr} />
          <main id="contenu">{children}</main>
          <PortalFooter portal={portal} t={tr} />
        </ToastProvider>
      </I18nProvider>
      <PageViewTracker territoryId={t.id} />
    </div>
  );
}
