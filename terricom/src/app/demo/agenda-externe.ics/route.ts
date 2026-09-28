import { demoExternalAgendaIcs } from '@/server/demo/external-agenda';
import { env } from '@/server/env';

/** Agenda « externe » de démonstration (office de tourisme fictif), servi uniquement en mode démo. */
export async function GET() {
  if (!env.DEMO_MODE) return new Response('Introuvable', { status: 404 });
  return new Response(demoExternalAgendaIcs(), { headers: { 'content-type': 'text/calendar; charset=utf-8', 'cache-control': 'no-store' } });
}
