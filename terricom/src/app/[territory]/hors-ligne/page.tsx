import type { Metadata } from 'next';
import Link from 'next/link';
import { RetryButton } from '@/components/pwa/RetryButton';
import { portalT } from '@/server/i18n';
import { getPortal } from '@/server/services/portal';

type Props = { params: Promise<{ territory: string }> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory } = await params;
  const tr = await portalT(await getPortal(territory));
  return { title: tr('off.metaTitle'), robots: { index: false } };
}

/** Page de secours du portail quand le réseau manque : les pages déjà vues restent consultables. */
export default async function PortalOfflinePage({ params }: Props) {
  const { territory } = await params;
  const portal = await getPortal(territory);
  const tr = await portalT(portal);
  return (
    <div className="container" style={{ paddingTop: 70, paddingBottom: 90, maxWidth: 720 }}>
      <div className="card" style={{ borderRadius: 24, padding: 32, display: 'flex', flexDirection: 'column', gap: 14, alignItems: 'flex-start' }}>
        <span className="stamp">{tr('off.metaTitle')}</span>
        <h1 className="h-page" style={{ fontSize: 36, margin: 0 }}>
          {tr('off.title')}
        </h1>
        <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0, lineHeight: 1.55 }}>{tr('off.text', { name: portal.territory.name })}</p>
        <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
          <RetryButton label={tr('off.retry')} />
          <Link href={portal.base || '/'} className="btn btn-outline">
            {tr('off.home')}
          </Link>
        </div>
      </div>
    </div>
  );
}
