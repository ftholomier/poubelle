import Link from 'next/link';
import type { CSSProperties } from 'react';
import { TerritoryBadge } from '@/components/ui/Brand';
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

export function PortalHeader({ portal, section }: { portal: PortalContext; section: PortalSection }) {
  const { territory: t, base, modules, featuredCampaign } = portal;
  const items: { key: PortalSection; label: string; href: string }[] = [
    { key: 'explorer', label: 'Explorer', href: `${base}/explorer` },
    { key: 'communes', label: 'Communes', href: `${base}/communes` },
    { key: 'agenda', label: 'Agenda', href: `${base}/agenda` },
  ];
  if (modules.has('CIRCUITS')) items.push({ key: 'circuits', label: 'Circuits', href: `${base}/circuits` });
  if (featuredCampaign && modules.has('CAMPAIGNS'))
    items.push({ key: 'campagne', label: `${campaignNavLabel(featuredCampaign.name)} ✦`, href: `${base}/campagnes/${featuredCampaign.slug}` });
  if (modules.has('JOBS')) items.push({ key: 'emploi', label: 'Emploi', href: `${base}/emploi` });

  return (
    <header style={{ background: 'var(--paper)', borderBottom: '1px solid var(--line)', position: 'sticky', top: 0, zIndex: 40 }}>
      <div className="container portal-header">
        <Link href={base || '/'} style={{ display: 'flex', alignItems: 'center', gap: 11, color: 'var(--text)' }} aria-label={`${t.name} — accueil`}>
          <TerritoryBadge initials={t.initials} logoUrl={t.logoUrl} />
          <div>
            <div style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 18, lineHeight: 1, letterSpacing: '-0.01em' }}>{t.name}</div>
            <div style={{ fontSize: 12, color: 'var(--muted)', marginTop: 2 }}>{t.tagline}</div>
          </div>
        </Link>
        <nav aria-label="Navigation du portail" className="portal-nav">
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
          <a href={appUrl(`/pro?territoire=${t.slug}`)} className="btn btn-outline btn-sm" style={{ fontSize: 13 }}>
            Vous êtes pro ?
          </a>
        </div>
      </div>
    </header>
  );
}

export function PortalFooter({ portal }: { portal: PortalContext }) {
  const { territory: t, base, modules } = portal;
  const settings = t.settings as { footerText?: string };
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
            {settings.footerText ?? `Une initiative de ${t.legalName}${portal.communesCount > 1 ? ` et de ses ${portal.communesCount} communes` : ''}.`}
          </div>
        </div>
        <div style={col}>
          <b style={{ color: 'var(--cream)' }}>Découvrir</b>
          <Link href={`${base}/explorer`} style={{ color: 'inherit' }}>
            Carte
          </Link>
          {modules.has('CIRCUITS') ? (
            <Link href={`${base}/circuits`} style={{ color: 'inherit' }}>
              Circuits
            </Link>
          ) : null}
          <Link href={`${base}/agenda`} style={{ color: 'inherit' }}>
            Agenda
          </Link>
          {modules.has('JOBS') ? (
            <Link href={`${base}/emploi`} style={{ color: 'inherit' }}>
              Emploi
            </Link>
          ) : null}
          <Link href={`${base}/actualites`} style={{ color: 'inherit' }}>
            Actualités
          </Link>
        </div>
        <div style={col}>
          <b style={{ color: 'var(--cream)' }}>Professionnels</b>
          <a href={appUrl(`/pro/inscription?territoire=${t.slug}`)} style={{ color: 'inherit' }}>
            Référencer mon activité
          </a>
          <a href={appUrl(`/pro/revendiquer?territoire=${t.slug}`)} style={{ color: 'inherit' }}>
            Revendiquer ma fiche
          </a>
          <a href={appUrl('/tarifs#professionnels')} style={{ color: 'inherit' }}>
            Offres Premium
          </a>
        </div>
        <div style={col}>
          <b style={{ color: 'var(--cream)' }}>Informations</b>
          <Link href={`${base}/mentions-legales`} style={{ color: 'inherit' }}>
            Mentions légales
          </Link>
          <Link href={`${base}/donnees-personnelles`} style={{ color: 'inherit' }}>
            Données personnelles
          </Link>
          <Link href={`${base}/accessibilite`} style={{ color: 'inherit' }}>
            Accessibilité
          </Link>
          <a href={appUrl('/')} style={{ color: 'var(--sage-3)' }}>
            Propulsé par terricom.
          </a>
        </div>
      </div>
    </footer>
  );
}
