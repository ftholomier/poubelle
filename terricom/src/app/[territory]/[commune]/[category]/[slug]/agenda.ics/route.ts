import { calendarIcs } from '@/lib/ics';
import { getPublicEstablishment } from '@/server/services/establishments';
import { establishmentCalendar, feedResponse } from '@/server/services/feeds';
import { getPortal } from '@/server/services/portal';

type Params = { params: Promise<{ territory: string; commune: string; category: string; slug: string }> };

/** Événements d'un établissement (iCalendar), à reprendre sur son propre site ou dans un agenda. */
export async function GET(_req: Request, { params }: Params) {
  const { territory, commune, slug } = await params;
  const { territory: t } = await getPortal(territory);
  const e = await getPublicEstablishment(t.id, commune, slug);
  if (!e) return new Response('Établissement introuvable', { status: 404 });
  const body = calendarIcs(`${e.name} · ${t.name}`, await establishmentCalendar(t, e.id), { description: `Les événements de ${e.name}.` });
  return feedResponse(body, 'ics', `agenda-${e.slug}.ics`);
}
