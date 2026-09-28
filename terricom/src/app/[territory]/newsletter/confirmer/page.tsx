import type { Metadata } from 'next';
import Link from 'next/link';
import { territoryText } from '@/lib/i18n/territory';
import { portalT } from '@/server/i18n';
import { confirmSubscription } from '@/server/services/newsletter';
import { getPortal } from '@/server/services/portal';

type Props = { params: Promise<{ territory: string }>; searchParams: Promise<Record<string, string | undefined>> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory } = await params;
  const tr = await portalT(await getPortal(territory));
  return { title: tr('nlc.metaTitle'), robots: { index: false } };
}

export default async function ConfirmPage({ params, searchParams }: Props) {
  const { territory } = await params;
  const { token } = await searchParams;
  const portal = await getPortal(territory);
  const tr = await portalT(portal);
  const sub = token ? await confirmSubscription(portal.territory.id, token) : null;
  return (
    <div className="container" style={{ paddingTop: 70, paddingBottom: 90, maxWidth: 720 }}>
      <div className="card" style={{ borderRadius: 24, padding: 32, display: 'flex', flexDirection: 'column', gap: 14, alignItems: 'flex-start' }}>
        {sub ? (
          <>
            <span className="stamp">{tr('common.confirmed')}</span>
            <h1 className="h-page" style={{ fontSize: 40, margin: 0 }}>
              {tr('nlc.welcome', {
                name:
                  territoryText(portal.territory, 'newsletterName', tr.locale) ??
                  (tr.locale === 'fr' ? `la lettre de ${portal.territory.name}` : tr('home.newsletterDefault', { name: portal.territory.name })),
              })}
            </h1>
            <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>{tr('nlc.first')}</p>
          </>
        ) : (
          <>
            <h1 className="h-page" style={{ fontSize: 36, margin: 0 }}>
              {tr('common.linkInvalid')}
            </h1>
            <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>{tr('nlc.invalidText')}</p>
          </>
        )}
        <Link href={portal.base || '/'} className="btn btn-dark">
          {tr('common.backPortal')}
        </Link>
      </div>
    </div>
  );
}
