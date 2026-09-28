import type { Metadata } from 'next';
import Link from 'next/link';
import { confirmFollow, followedEstablishment } from '@/server/services/customers';
import { portalT } from '@/server/i18n';
import { getPortal } from '@/server/services/portal';

type Props = { params: Promise<{ territory: string }>; searchParams: Promise<Record<string, string | undefined>> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory } = await params;
  const tr = await portalT(await getPortal(territory));
  return { title: tr('follow.metaTitle'), robots: { index: false } };
}

export default async function FollowConfirmPage({ params, searchParams }: Props) {
  const { territory } = await params;
  const { token } = await searchParams;
  const portal = await getPortal(territory);
  const tr = await portalT(portal);
  const contact = token ? await confirmFollow(token) : null;
  const est = contact ? await followedEstablishment(contact) : null;
  return (
    <div className="container" style={{ paddingTop: 70, paddingBottom: 90, maxWidth: 720 }}>
      <div className="card" style={{ borderRadius: 24, padding: 32, display: 'flex', flexDirection: 'column', gap: 14, alignItems: 'flex-start' }}>
        {contact ? (
          <>
            <span className="stamp">{tr('common.confirmed')}</span>
            <h1 className="h-page" style={{ fontSize: 40, margin: 0 }}>
              {tr('follow.following', { name: est?.name ?? tr('follow.thisShop') })}
            </h1>
            <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>{tr('follow.text')}</p>
          </>
        ) : (
          <>
            <h1 className="h-page" style={{ fontSize: 36, margin: 0 }}>
              {tr('common.linkInvalid')}
            </h1>
            <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>{tr('follow.invalidText')}</p>
          </>
        )}
        <Link href={est ? `${portal.base}${est.path}` : portal.base || '/'} className="btn btn-dark">
          {est ? tr('follow.seeListing', { name: est.name }) : tr('common.backPortal')}
        </Link>
      </div>
    </div>
  );
}
