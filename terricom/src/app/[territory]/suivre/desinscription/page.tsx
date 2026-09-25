import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { contactByUnsubscribeToken, followedEstablishment, unfollow } from '@/server/services/customers';
import { portalT } from '@/server/i18n';
import { getPortal } from '@/server/services/portal';

type Props = { params: Promise<{ territory: string }>; searchParams: Promise<Record<string, string | undefined>> };

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory } = await params;
  const tr = await portalT(await getPortal(territory));
  return { title: tr('unsub.metaTitle'), robots: { index: false } };
}

/** Désinscription des lettres d'un commerce : confirmée par un bouton (les liens peuvent être ouverts par des robots de messagerie). */
export default async function FollowUnsubscribePage({ params, searchParams }: Props) {
  const { territory } = await params;
  const { token, fait } = await searchParams;
  const portal = await getPortal(territory);
  const tr = await portalT(portal);
  const langParam = tr.locale === 'fr' ? '' : `&lang=${tr.locale}`;
  const contact = token ? await contactByUnsubscribeToken(token) : null;
  const est = contact ? await followedEstablishment(contact) : null;

  async function confirm(form: FormData) {
    'use server';
    const tk = String(form.get('token') ?? '');
    await unfollow(tk);
    redirect(`${portal.base}/suivre/desinscription?token=${encodeURIComponent(tk)}&fait=1${langParam}`);
  }

  const done = contact && (!contact.subscribed || fait === '1');
  const name = est?.name ?? tr('follow.thisShop');
  return (
    <div className="container" style={{ paddingTop: 70, paddingBottom: 90, maxWidth: 720 }}>
      <div className="card" style={{ borderRadius: 24, padding: 32, display: 'flex', flexDirection: 'column', gap: 14, alignItems: 'flex-start' }}>
        {!contact ? (
          <>
            <h1 className="h-page" style={{ fontSize: 36, margin: 0 }}>
              {tr('unsub.invalid')}
            </h1>
            <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>{tr('unsub.invalidText')}</p>
          </>
        ) : done ? (
          <>
            <h1 className="h-page" style={{ fontSize: 36, margin: 0 }}>
              {tr('unsub.done')}
            </h1>
            <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>{tr('follow.doneText', { name })}</p>
          </>
        ) : (
          <>
            <h1 className="h-page" style={{ fontSize: 36, margin: 0 }}>
              {tr('follow.ask', { name })}
            </h1>
            <p style={{ fontSize: 17, color: 'var(--muted)', margin: 0 }}>{tr('follow.address', { email: contact.email })}</p>
            <form action={confirm}>
              <input type="hidden" name="token" value={token} />
              <SubmitButton className="btn btn-brand" pendingLabel={tr('follow.unsubscribing')}>
                {tr('follow.unsubscribe')}
              </SubmitButton>
            </form>
          </>
        )}
        <Link href={est ? `${portal.base}${est.path}` : portal.base || '/'} className="btn btn-outline btn-sm">
          {est ? tr('follow.seeListing', { name: est.name }) : tr('common.backPortal')}
        </Link>
      </div>
    </div>
  );
}
