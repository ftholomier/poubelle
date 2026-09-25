import { eventIcs } from '@/lib/ics';
import { getPortal, getPublicEvent } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

/** Fichier .ics d'un événement (« Ajouter à mon agenda »). */
export async function GET(_req: Request, { params }: { params: Promise<{ territory: string; slug: string }> }) {
  const { territory, slug } = await params;
  const portal = await getPortal(territory);
  const data = await getPublicEvent(portal.territory.id, slug);
  if (!data) return new Response('Événement introuvable', { status: 404 });
  const ev = data.event;
  const body = eventIcs({
    uid: `${ev.id}@terricom.fr`,
    title: ev.title,
    description: [ev.summary, ev.description].filter(Boolean).join('\n\n'),
    location: [ev.locationName, ev.address].filter(Boolean).join(', '),
    url: portalUrl(portal.territory, `/agenda/${ev.slug}`),
    startsAt: ev.startsAt,
    endsAt: ev.endsAt,
    lat: ev.lat,
    lng: ev.lng,
  });
  return new Response(body, {
    headers: {
      'content-type': 'text/calendar; charset=utf-8',
      'content-disposition': `attachment; filename="${ev.slug}.ics"`,
      'cache-control': 'public, max-age=300',
    },
  });
}
