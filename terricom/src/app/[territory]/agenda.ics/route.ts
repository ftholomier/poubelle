import { calendarIcs } from '@/lib/ics';
import { feedResponse, territoryCalendar } from '@/server/services/feeds';
import { getPortal } from '@/server/services/portal';

/** Agenda du territoire au format iCalendar : abonnement depuis un agenda personnel ou reprise sur un site. */
export async function GET(_req: Request, { params }: { params: Promise<{ territory: string }> }) {
  const { territory } = await params;
  const { territory: t } = await getPortal(territory);
  const events = await territoryCalendar(t);
  const body = calendarIcs(`Agenda · ${t.name}`, events, { description: `Les rendez-vous des commerces, artisans et producteurs de ${t.name}.` });
  return feedResponse(body, 'ics', `agenda-${t.slug}.ics`);
}
