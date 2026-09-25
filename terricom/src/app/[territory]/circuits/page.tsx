import { notFound, redirect } from 'next/navigation';
import { listPublishedCircuits } from '@/server/services/circuits';
import { getPortal } from '@/server/services/portal';

type Props = { params: Promise<{ territory: string }>; searchParams: Promise<Record<string, string | undefined>> };

/** La rubrique Circuits s'ouvre sur le premier circuit (les onglets permettent de passer aux autres). */
export default async function CircuitsIndex({ params, searchParams }: Props) {
  const { territory } = await params;
  const portal = await getPortal(territory);
  if (!portal.modules.has('CIRCUITS')) notFound();
  const list = await listPublishedCircuits(portal.territory.id);
  if (!list.length) notFound();
  const sp = await searchParams;
  redirect(`${portal.base}/circuits/${list[0].slug}${sp.tampon ? `?tampon=${encodeURIComponent(sp.tampon)}` : ''}`);
}
