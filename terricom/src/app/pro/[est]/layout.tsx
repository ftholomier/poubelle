import Link from 'next/link';
import type { ReactNode } from 'react';
import { AppNav, AppTitle, type NavItem } from '@/components/app/AppNav';
import { UserMenu } from '@/components/app/UserMenu';
import { Icon } from '@/components/ui/Icon';
import { Photo } from '@/components/ui/Photo';
import { PLAN_LABELS } from '@/lib/constants';
import { fullName } from '@/lib/format';
import { sized } from '@/lib/images';
import { managedEstablishments, loadProContext } from '@/server/services/pro';
import { portalUrl } from '@/server/urls';

type Props = { children: ReactNode; params: Promise<{ est: string }> };

export default async function ProAppLayout({ children, params }: Props) {
  const { est: estId } = await params;
  const ctx = await loadProContext(estId);
  const { est, base, completeness, modules, campaign, actor } = ctx;
  const others = await managedEstablishments(actor.user.id);
  const notifications = ctx.unreadMessages + ctx.pendingAppointments + ctx.newApplications;

  const items: NavItem[] = [
    { href: base, label: 'Tableau de bord', exact: true },
    { href: `${base}/fiche`, label: 'Ma fiche', badge: completeness.score < 100 ? `${completeness.score}%` : null, badgeBg: 'var(--amber)' },
    { href: `${base}/publications`, label: 'Publications', badge: 'IA', badgeBg: 'var(--lilac)' },
    { href: `${base}/statistiques`, label: 'Statistiques' },
    { href: `${base}/kit`, label: 'Kit vitrine & QR' },
    { href: `${base}/offre`, label: 'Mon offre' },
    { separator: true },
    { href: `${base}/messages`, label: 'Messages', badge: ctx.unreadMessages ? String(ctx.unreadMessages) : null, badgeBg: 'var(--rose)' },
    { href: `${base}/evenements`, label: 'Événements' },
    ...(modules.has('JOBS')
      ? [{ href: `${base}/emploi`, label: 'Recrutement', badge: ctx.newApplications ? String(ctx.newApplications) : null, badgeBg: 'var(--leaf)' }]
      : []),
    ...(modules.has('APPOINTMENTS')
      ? [{ href: `${base}/rendez-vous`, label: 'Rendez-vous', badge: ctx.pendingAppointments ? String(ctx.pendingAppointments) : null, badgeBg: 'var(--sky)' }]
      : []),
    ...(ctx.role !== 'MEMBER' ? [{ href: `${base}/equipe`, label: 'Équipe' }] : []),
  ];
  const titles: [string, string][] = [
    [base, 'Tableau de bord'],
    [`${base}/fiche`, 'Ma fiche'],
    [`${base}/publications`, 'Publications'],
    [`${base}/statistiques`, 'Statistiques'],
    [`${base}/kit`, 'Kit vitrine & QR code'],
    [`${base}/offre`, 'Mon offre'],
    [`${base}/messages`, 'Messages'],
    [`${base}/evenements`, 'Événements'],
    [`${base}/emploi`, 'Recrutement'],
    [`${base}/rendez-vous`, 'Rendez-vous'],
    [`${base}/equipe`, 'Équipe'],
  ];
  const publicUrl = ['PRECREATED', 'TO_COMPLETE', 'CLAIMED', 'VALIDATED'].includes(est.status) ? portalUrl(ctx.territory, est.path) : null;

  return (
    <div className="app-shell">
      <aside className="app-aside">
        <div style={{ display: 'flex', gap: 10, alignItems: 'center', padding: '6px 8px 18px' }}>
          <div style={{ width: 42, height: 42, borderRadius: 12, overflow: 'hidden', flexShrink: 0 }}>
            <Photo src={sized(est.coverUrl, 120, 120)} alt="" label={est.name} color={est.color} />
          </div>
          <div style={{ minWidth: 0 }}>
            <div style={{ fontWeight: 700, fontSize: 14, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{est.name}</div>
            <div style={{ fontSize: 12, color: 'var(--muted)' }}>
              {est.commune.name} · {PLAN_LABELS[ctx.plan]}
            </div>
          </div>
        </div>
        <AppNav items={items} label="Espace professionnel" />
        {campaign ? (
          <Link
            href={`${base}#campagne`}
            className="app-aside-extra"
            style={{
              marginTop: 'auto',
              background: 'var(--ink)',
              color: 'var(--cream)',
              borderRadius: 14,
              padding: 14,
              display: 'flex',
              flexDirection: 'column',
              gap: 6,
            }}
          >
            <span style={{ fontSize: 11, fontWeight: 800, letterSpacing: '0.08em', color: 'var(--amber)' }}>CAMPAGNE EN COURS</span>
            <span style={{ fontWeight: 700, fontSize: 14 }}>{campaign.name}</span>
            <span style={{ fontSize: 12, color: 'var(--sage)' }}>
              {campaign.status === 'JOINED'
                ? `Vous participez${campaign.offerLabel ? ' · offre publiée' : ''}`
                : campaign.status === 'DECLINED'
                  ? 'Invitation déclinée'
                  : 'Invitation en attente'}
            </span>
          </Link>
        ) : null}
      </aside>
      <div className="app-main">
        <header className="app-topbar">
          <AppTitle titles={titles} fallback="Espace professionnel" />
          <div style={{ marginLeft: 'auto', display: 'flex', gap: 10, alignItems: 'center' }}>
            {publicUrl ? (
              <a
                href={publicUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="btn btn-outline btn-sm hide-sm"
                style={{ border: '1.5px solid var(--ink)', color: 'var(--ink)' }}
              >
                Voir ma fiche publique ↗
              </a>
            ) : null}
            <Link href={`${base}/messages`} className="icon-btn" aria-label={`${notifications} notification${notifications > 1 ? 's' : ''}`}>
              <Icon name="bell" size={17} />
              {notifications ? <span className="dot-count">{notifications > 99 ? '99+' : notifications}</span> : null}
            </Link>
            <UserMenu
              name={fullName(actor.user)}
              email={actor.user.email}
              avatarUrl={actor.user.avatarUrl}
              links={[
                { href: '/compte', label: 'Mon compte et sécurité' },
                ...(others.length > 1 ? [{ href: '/pro?choisir=1', label: 'Changer d’établissement' }] : []),
                ...(ctx.role !== 'STAFF' ? [{ href: `/pro/inscription?siren=${ctx.est.siret?.slice(0, 9) ?? ''}`, label: 'Ajouter un établissement' }] : []),
                ...(ctx.role === 'STAFF' ? [{ href: '/collectivite', label: 'Retour au back-office' }] : []),
              ]}
            />
          </div>
        </header>
        {ctx.role === 'STAFF' ? (
          <div className="alert alert-info" style={{ margin: '14px 28px 0' }}>
            Vous consultez cette fiche en tant qu&apos;agent de la collectivité : vos modifications sont historisées à votre nom.
          </div>
        ) : null}
        {children}
      </div>
    </div>
  );
}
