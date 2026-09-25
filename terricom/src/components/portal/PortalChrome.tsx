import Link from 'next/link';
import type { CSSProperties } from 'react';
import { TerritoryBadge } from '@/components/ui/Brand';
import type { Translate } from '@/lib/i18n';
import { territoryText } from '@/lib/i18n/territory';
import { LangSwitch } from './I18n';
import type { TerritorySettings } from '@/server/db/schema';
import { campaignNavLabel, type PortalContext } from '@/server/services/portal';
import { appUrl } from '@/server/urls';

export type PortalSection = 'home' | 'explorer' | 'communes' | 'agenda' | 'circuits' | 'campagne' | 'emploi' | 'autre';

/** Déduit la rubrique active à partir du chemin visible. */
export function sectionFromPath(pathname: string, base: string): PortalSection {
  const rest = (base && pathname.startsWith(base) ? pathname.slice(base.length) : pathname).split('/').filter(Boolean);
  if (!rest.length) return 'home';
  const [first] = rest;
  if (first === 'explorer' || first === 'recherche') return 'explorer';
  if (first === 'communes') return 'communes';
  if (first === 'agenda') return 'agenda';
  if (first === 'circuits' || first === 'passeport') return 'circuits';
  if (first === 'campagnes') return 'campagne';
  if (first === 'emploi') return 'emploi';
  if (['actualites', 'newsletter', 'mentions-legales', 'donnees-personnelles', 'accessibilite'].includes(first)) return 'autre';
  return rest.length > 1 ? 'explorer' : 'communes';
}

export function PortalHeader({ portal, section, t: tr }: { portal: PortalContext; section: PortalSection; t: Translate }) {
  const { territory: t, base, modules, featuredCampaign } = portal;
  const items: { key: PortalSection; label: string; href: string }[] = [
    { key: 'explorer', label: tr('nav.explorer'), href: `${base}/explorer` },
    { key: 'communes', label: tr('nav.communes'), href: `${base}/communes` },
    { key: 'agenda', label: tr('nav.agenda'), href: `${base}/agenda` },
  ];
  if (modules.has('CIRCUITS')) items.push({ key: 'circuits', label: tr('nav.circuits'), href: `${base}/circuits` });
  if (featuredCampaign && modules.has('CAMPAIGNS'))
    items.push({ key: 'campagne', label: `${campaignNavLabel(featuredCampaign.name)} ✦`, href: `${base}/campagnes/${featuredCampaign.slug}` });
  if (modules.has('JOBS')) items.push({ key: 'emploi', label: tr('nav.jobs'), href: `${base}/emploi` });

  return (
    <header style={{ background: 'var(--paper)', borderBottom: '1px solid var(--line)', position: 'sticky', top: 0, zIndex: 40 }}>
      <div className="container portal-header">
        <Link
          href={base || '/'}
          style={{ display: 'flex', alignItems: 'center', gap: 11, color: 'var(--text)' }}
          aria-label={`${t.name} — ${tr('common.home')}`}
        >
          <TerritoryBadge initials={t.initials} logoUrl={t.logoUrl} />
          <div>
            <div style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 18, lineHeight: 1, letterSpacing: '-0.01em' }}>{t.name}</div>
            <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 2 }}>{territoryText(t, 'tagline', tr.locale)}</div>
          </div>
        </Link>
        <nav aria-label={tr('nav.aria')} className="portal-nav">
          {items.map((n) => {
            const on = n.key === section;
            const style: CSSProperties = {
              padding: '8px 12px',
              borderRadius: 999,
              background: on ? 'var(--ink)' : 'transparent',
              color: on ? 'var(--cream)' : 'var(--text)',
              whiteSpace: 'nowrap',
            };
            return (
              <Link key={n.key} href={n.href} style={style} aria-current={on ? 'page' : undefined}>
                {n.label}
              </Link>
            );
          })}
        </nav>
        <div className="portal-header-cta">
          {modules.has('MULTILINGUAL') ? <LangSwitch /> : null}
          <a href={appUrl(`/pro?territoire=${t.slug}`)} className="btn btn-outline btn-sm" style={{ fontSize: 13 }}>
            {tr('nav.pro')}
          </a>
        </div>
      </div>
    </header>
  );
}

export function PortalFooter({ portal, t: tr }: { portal: PortalContext; t: Translate }) {
  const { territory: t, base, modules } = portal;
  const settings = t.settings as TerritorySettings;
  const col: CSSProperties = { display: 'flex', flexDirection: 'column', gap: 6 };
  return (
    <footer style={{ background: 'var(--ink)', color: 'var(--sage)' }}>
      <div
        className="container"
        style={{ paddingTop: 44, paddingBottom: 44, display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: 28, fontSize: 14 }}
      >
        <div>
          <div style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 22, color: 'var(--cream)' }}>{t.name}</div>
          <div style={{ marginTop: 8, lineHeight: 1.5 }}>
            {territoryText(t, 'footerText', tr.locale) ??
              (portal.communesCount > 1
                ? tr('footer.initiativeCommunes', { legal: t.legalName, n: portal.communesCount })
                : tr('footer.initiative', { legal: t.legalName }))}
          </div>
        </div>
        <div style={col}>
          <b style={{ color: 'var(--cream)' }}>{tr('footer.discover')}</b>
          <Link href={`${base}/explorer`} style={{ color: 'inherit' }}>
            {tr('footer.map')}
          </Link>
          {modules.has('CIRCUITS') ? (
            <Link href={`${base}/circuits`} style={{ color: 'inherit' }}>
              {tr('nav.circuits')}
            </Link>
          ) : null}
          <Link href={`${base}/agenda`} style={{ color: 'inherit' }}>
            {tr('nav.agenda')}
          </Link>
          {modules.has('JOBS') ? (
            <Link href={`${base}/emploi`} style={{ color: 'inherit' }}>
              {tr('nav.jobs')}
            </Link>
          ) : null}
          <Link href={`${base}/actualites`} style={{ color: 'inherit' }}>
            {tr('footer.news')}
          </Link>
        </div>
        <div style={col}>
          <b style={{ color: 'var(--cream)' }}>{tr('footer.pros')}</b>
          <a href={appUrl(`/pro/inscription?territoire=${t.slug}`)} style={{ color: 'inherit' }}>
            {tr('footer.register')}
          </a>
          <a href={appUrl(`/pro/revendiquer?territoire=${t.slug}`)} style={{ color: 'inherit' }}>
            {tr('footer.claim')}
          </a>
          <a href={appUrl('/tarifs#professionnels')} style={{ color: 'inherit' }}>
            {tr('footer.offers')}
          </a>
        </div>
        <div style={col}>
          <b style={{ color: 'var(--cream)' }}>{tr('footer.info')}</b>
          <Link href={`${base}/mentions-legales`} style={{ color: 'inherit' }}>
            {tr('footer.legal')}
          </Link>
          <Link href={`${base}/donnees-personnelles`} style={{ color: 'inherit' }}>
            {tr('footer.privacy')}
          </Link>
          <Link href={`${base}/accessibilite`} style={{ color: 'inherit' }}>
            {tr('footer.accessibility')}
          </Link>
          {settings.whiteLabel ? null : (
            <a href={appUrl('/')} style={{ color: 'var(--sage-3)' }}>
              {tr('footer.poweredBy')}
            </a>
          )}
        </div>
      </div>
    </footer>
  );
}
