import { NextResponse, type NextRequest } from 'next/server';
import { csvResponse, toCsv } from '@/server/csv';
import { boApiContext } from '@/server/services/bo-api';
import { PERIODS, periodOf, statsBundle } from '@/server/services/bo-report';

/** Export CSV des statistiques consolidées (indicateurs, mois, recherches, communes). */
export async function GET(req: NextRequest) {
  const ctx = await boApiContext();
  if (!ctx) return new NextResponse('Non autorisé', { status: 401 });
  const period = periodOf(req.nextUrl.searchParams.get('periode'));
  const s = await statsBundle(ctx, period, req.nextUrl.searchParams.get('commune'));
  const rows: (string | number)[][] = [
    ['Indicateurs', PERIODS[period].label, '', '', '', ''],
    ['Visiteurs', s.kpis.visitors, '', '', '', ''],
    ['Recherches', s.kpis.searches, '', '', '', ''],
    ['Fiches consultées', s.kpis.est_views, '', '', '', ''],
    ['Clics téléphone', s.kpis.calls, '', '', '', ''],
    ['Itinéraires', s.kpis.directions, '', '', '', ''],
    ['Messages envoyés', s.kpis.messages, '', '', '', ''],
    ['', '', '', '', '', ''],
    ['Mois', 'Visiteurs', '', '', '', ''],
    ...s.months.map((m) => [m.key, m.value, '', '', '', '']),
    ['', '', '', '', '', ''],
    ['Recherche', 'Occurrences', '', '', '', ''],
    ...s.searches.map((q) => [q.q, q.n, '', '', '', '']),
    ['', '', '', '', '', ''],
    ['Commune', 'Fiches', 'Revendiquées', 'Vues', 'Appels', 'Itinéraires'],
    ...s.communesData.map((c) => [c.name, c.total, c.claimed, c.views, c.calls, c.directions]),
  ];
  return csvResponse(toCsv(['Rubrique', 'Valeur', '', '', '', ''], rows), `statistiques-${ctx.territory.slug}-${new Date().toISOString().slice(0, 10)}.csv`);
}
