import type { Metadata } from 'next';
import Link from 'next/link';
import type { ReactNode } from 'react';
import { switchScopeAction } from './actions';
import { logoutAction } from '@/app/connexion/actions';
import { AppNav, AppTitle, type NavItem } from '@/components/app/AppNav';
import { DemoBar } from '@/components/DemoBar';
import { TerritoryBadge } from '@/components/ui/Brand';
import { ToastProvider } from '@/components/ui/Feedback';
import { Photo } from '@/components/ui/Photo';
import { fmtInt, fullName } from '@/lib/format';
import { env } from '@/server/env';
import { boCounts, loadBoContext } from '@/server/services/backoffice';
import { portalUrl } from '@/server/urls';

export const metadata: Metadata = { title: { default: 'Back-office', template: '%s · Back-office terricom' }, robots: { index: false } };

const TITLES: [string, string][] = [
  ['/collectivite', 'Tableau de bord'],
  ['/collectivite/entreprises', 'Entreprises'],
  ['/collectivite/entreprises/sirene', 'Mises à jour SIRENE'],
  ['/collectivite/moderation', 'Revendications & modération'],
  ['/collectivite/campagnes', 'Campagnes'],
  ['/collectivite/agenda', 'Agenda & actualités'],
  ['/collectivite/newsletter', 'Newsletter territoriale'],
  ['/collectivite/circuits', 'Circuits & parcours'],
  ['/collectivite/statistiques', 'Statistiques'],
  ['/collectivite/personnalisation', 'Personnalisation & rôles'],
  ['/collectivite/personnalisation/categories', 'Catégories du portail'],
  ['/collectivite/personnalisation/langues', 'Langues du portail'],
  ['/collectivite/api', 'API & données ouvertes'],
  ['/collectivite/support', 'Aide & support'],
];

export default async function BackOfficeLayout({ children }: { children: ReactNode }) {
  const ctx = await loadBoContext();
  const counts = await boCounts(ctx);
  const t = ctx.territory;
  const items: NavItem[] = [
    { href: '/collectivite', label: 'Tableau de bord', exact: true },
    { href: '/collectivite/entreprises', label: 'Entreprises', badge: fmtInt(counts.establishments), badgeBg: 'var(--dark-4)', badgeFg: 'var(--sage-4)' },
    ...(counts.pendingSirene
      ? [{ href: '/collectivite/entreprises/sirene', label: 'Mises à jour SIRENE', badge: String(counts.pendingSirene), badgeBg: 'var(--amber)' }]
      : []),
    { href: '/collectivite/moderation', label: 'Revendications', badge: counts.pendingClaims ? String(counts.pendingClaims) : null, badgeBg: 'var(--amber)' },
    { href: '/collectivite/campagnes', label: 'Campagnes', badge: 'IA', badgeBg: 'var(--lilac)' },
    { href: '/collectivite/agenda', label: 'Agenda & actualités' },
    { href: '/collectivite/newsletter', label: 'Newsletters' },
    { href: '/collectivite/circuits', label: 'Circuits' },
    { href: '/collectivite/statistiques', label: 'Statistiques' },
    ...(ctx.level === 'TERRITORY'
      ? [
          { href: '/collectivite/personnalisation', label: 'Personnalisation' },
          { href: '/collectivite/api', label: 'API & données' },
        ]
      : []),
  ];
  const sub =
    ctx.level === 'TERRITORY'
      ? `${ctx.communes.length} communes · ${fmtInt(counts.establishments)} établissements`
      : `1 commune · ${fmtInt(counts.establishments)} établissements`;
  const portal = ctx.commune ? portalUrl(t, `/${ctx.commune.slug}`) : portalUrl(t);
  const name = fullName(ctx.actor.user);
  const demoKey = t.slug === 'haut-doubs' ? 'haut-doubs' : 'collectivite';

  return (
    <ToastProvider>
      {ctx.territory.slug === 'haut-doubs' ? (
        <DemoBar active="haut-doubs" right="Entreprises réelles (SIRENE) · fiches précréées" />
      ) : (
        <DemoBar active="collectivite" />
      )}
      <div className="app-shell bo">
        <aside className="app-aside">
          <div style={{ display: 'flex', gap: 10, alignItems: 'center', padding: '4px 8px 14px' }}>
            <TerritoryBadge initials={t.initials} logoUrl={t.logoUrl} bg={t.colorPrimary} fg={t.colorAccent} />
            <div style={{ minWidth: 0 }}>
              <div style={{ fontWeight: 700, fontSize: 14 }}>{ctx.scopeName}</div>
              <div style={{ fontSize: 12, color: 'var(--sage-2)' }}>{sub}</div>
            </div>
          </div>
          {env.DEMO_MODE ? (
            <nav className="bo-scope-tabs" aria-label="Périmètre">
              {}
              <a href={`/demo/entrer/${demoKey}`} aria-current={ctx.level === 'TERRITORY' ? 'true' : undefined}>
                Territoire
              </a>
              {}
              <a
                href={`/demo/entrer/${demoKey === 'haut-doubs' ? 'haut-doubs-commune' : 'commune'}`}
                aria-current={ctx.level === 'COMMUNE' ? 'true' : undefined}
              >
                Commune
              </a>
            </nav>
          ) : ctx.scopes.length > 1 ? (
            <form action={switchScopeAction} className="bo-scope-tabs" style={{ gridTemplateColumns: `repeat(${Math.min(ctx.scopes.length, 2)},1fr)` }}>
              {ctx.scopes.slice(0, 4).map((s) => (
                <button key={s.key} type="submit" name="scope" value={s.key} aria-current={s.key === ctx.scopeKey ? 'true' : undefined} title={s.label}>
                  {s.sub}
                </button>
              ))}
            </form>
          ) : null}
          <AppNav items={items} label="Back-office" />
          <Link href="/collectivite/support" className="bo-help">
            <span aria-hidden="true">?</span> Aide & support
          </Link>
          <div className="bo-user">
            <Link href="/compte" style={{ display: 'flex', gap: 10, alignItems: 'center', minWidth: 0, flex: 1 }}>
              <span style={{ width: 34, height: 34, borderRadius: '50%', overflow: 'hidden', flexShrink: 0 }}>
                <Photo src={ctx.actor.user.avatarUrl} alt="" label={name} color="#7A5BB5" />
              </span>
              <span style={{ minWidth: 0 }}>
                <b style={{ display: 'block', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{name}</b>
                <span style={{ color: 'var(--sage-2)' }}>{ctx.roleLabel}</span>
              </span>
            </Link>
            <form action={logoutAction}>
              <button
                type="submit"
                aria-label="Se déconnecter"
                title="Se déconnecter"
                style={{ background: 'none', border: 0, color: 'var(--sage-2)', fontSize: 16, cursor: 'pointer', padding: 4 }}
              >
                ⎋
              </button>
            </form>
          </div>
        </aside>
        <div className="app-main">
          <header className="app-topbar">
            <div>
              <div style={{ fontSize: 12, color: 'var(--muted)' }}>{ctx.scopeName}</div>
              <AppTitle titles={TITLES} fallback="Back-office" />
            </div>
            <div style={{ marginLeft: 'auto', display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
              <form action="/collectivite/entreprises" role="search" className="bo-search">
                <span aria-hidden="true">⌕</span>
                <label htmlFor="bo-q" className="sr-only">
                  Rechercher une entreprise
                </label>
                <input id="bo-q" name="q" placeholder="Rechercher une entreprise…" />
              </form>
              <a
                href={portal}
                target="_blank"
                rel="noopener noreferrer"
                className="btn btn-outline btn-sm"
                style={{ border: '1.5px solid var(--ink)', color: 'var(--ink)' }}
              >
                Voir le portail ↗
              </a>
            </div>
          </header>
          {ctx.actor.impersonation ? (
            <div className="alert alert-info" style={{ margin: '14px 30px 0' }}>
              Accès support temporaire à {t.name} (ticket {ctx.actor.impersonation.ticket ?? '—'}) : chaque action est journalisée.
            </div>
          ) : null}
          {children}
        </div>
      </div>
    </ToastProvider>
  );
}
