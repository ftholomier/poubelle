import type { Metadata } from 'next';
import Link from 'next/link';
import { unsubscribe } from '@/server/services/newsletter';
import { portalT } from '@/server/i18n';
import { getPortal } from '@/server/services/portal';

type Props = { params: Promise<{ territory: string }>; searchParams: Promise<Record<string, string | undefined>> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory } = await params;
  const tr = await portalT(await getPortal(territory));
  return { title: tr('unsub.metaTitle'), robots: { index: false } };
}

export default async function UnsubscribePage({ params, searchParams }: Props) {
  const { territory } = await params;
  const { token } = await searchParams;
  const portal = await getPortal(territory);
  const tr = await portalT(portal);
  const sub = token ? await unsubscribe(token) : null;
  return (
    <div className="container" style={{ paddingTop: 70, paddingBottom: 90, maxWidth: 720 }}>
      <div className="card" style={{ borderRadius: 24, padding: 32, display: 'flex', flexDirection: 'column', gap: 14, alignItems: 'flex-start' }}>
        <h1 className="h-page" style={{ fontSize: 36, margin: 0 }}>
          {sub ? tr('unsub.done') : tr('unsub.invalid')}
        </h1>
        <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>{sub ? tr('unsub.doneText') : tr('unsub.invalidText')}</p>
        <Link href={portal.base || '/'} className="btn btn-dark">
          {tr('common.backPortal')}
        </Link>
      </div>
    </div>
  );
}
