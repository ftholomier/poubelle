import { NextResponse, type NextRequest } from 'next/server';
import { fmtInt } from '@/lib/format';
import { audit } from '@/server/audit';
import { activityReportPdf } from '@/server/print/report';
import { boApiContext } from '@/server/services/bo-api';
import { PERIODS, periodOf, statsBundle } from '@/server/services/bo-report';

/** Rapport d'activité PDF pour le conseil communautaire (ou municipal). */
export async function GET(req: NextRequest) {
  const ctx = await boApiContext();
  if (!ctx) return new NextResponse('Non autorisé', { status: 401 });
  const period = periodOf(req.nextUrl.searchParams.get('periode'));
  const s = await statsBundle(ctx, period, req.nextUrl.searchParams.get('commune'));
  const commune = s.commune ? ctx.communes.find((c) => c.id === s.commune) : null;
  const nlShare = s.kpis.searches ? Math.round((s.kpis.nl_searches / s.kpis.searches) * 100) : 0;
  const bytes = await activityReportPdf({
    territoryName: ctx.territory.name,
    scopeName: commune ? `${ctx.scopeName} · ${commune.name}` : ctx.scopeName,
    legalName: ctx.territory.legalName,
    color: ctx.territory.colorPrimary,
    accent: ctx.territory.colorAccent,
    periodLabel: PERIODS[period].label,
    kpis: [
      {
        label: 'Visiteurs',
        value: fmtInt(s.kpis.visitors),
        detail: s.growth !== null ? `${s.growth >= 0 ? '+' : ''}${s.growth} % depuis le lancement` : undefined,
      },
      { label: 'Recherches', value: fmtInt(s.kpis.searches), detail: `dont ${nlShare} % en langage naturel` },
      { label: 'Fiches consultées', value: fmtInt(s.kpis.est_views) },
      { label: 'Clics téléphone', value: fmtInt(s.kpis.calls) },
      { label: 'Itinéraires', value: fmtInt(s.kpis.directions) },
      { label: 'Contacts générés', value: fmtInt(s.kpis.calls + s.kpis.messages), detail: 'messages + appels' },
    ].map((k) => ({ ...k, value: k.value.replace(/ /g, ' ') })),
    funnel: [
      { label: 'Référencés', value: s.funnel.total },
      { label: 'Revendiqués', value: s.funnel.claimed },
      { label: 'Fiches complètes', value: s.funnel.complete },
      { label: 'Actifs ce mois', value: s.funnel.active },
    ],
    months: s.months,
    searches: s.searches,
    signal: s.signal,
    communes: [...s.communesData].sort((a, b) => b.total - a.total).filter((c) => !s.commune || c.id === s.commune),
  });
  await audit({
    actor: { user: ctx.actor.user },
    category: 'CONFIGURATION',
    action: 'report.generated',
    summary: `Rapport d'activité généré (${PERIODS[period].label})`,
    territoryId: ctx.territory.id,
  });
  return new NextResponse(Buffer.from(bytes), {
    headers: {
      'content-type': 'application/pdf',
      'content-disposition': `inline; filename="rapport-${ctx.territory.slug}-${new Date().toISOString().slice(0, 10)}.pdf"`,
      'cache-control': 'private, no-store',
    },
  });
}
