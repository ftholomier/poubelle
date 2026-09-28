import type { BoContext } from './backoffice';
import { adoptionFunnel, communeStats, consolidatedKpis, monthlyVisitors, topSearches, weakSignal, type StatsFilter } from './bo-stats';

export const PERIODS = {
  '30j': { days: 30, label: '30 derniers jours' },
  '3m': { days: 91, label: '3 derniers mois' },
  '12m': { days: 365, label: '12 derniers mois' },
} as const;

export type PeriodKey = keyof typeof PERIODS;

export function periodOf(raw: string | null | undefined): PeriodKey {
  return raw && raw in PERIODS ? (raw as PeriodKey) : '12m';
}

/** Données de la page Statistiques, du rapport PDF et de l'export CSV. */
export async function statsBundle(ctx: BoContext, period: PeriodKey, communeId: string | null) {
  const commune = communeId && ctx.communes.some((c) => c.id === communeId) ? communeId : null;
  const f: StatsFilter = { since: new Date(Date.now() - PERIODS[period].days * 86_400_000), communeId: commune };
  const [kpis, months, searches, signal, communesData, funnel] = await Promise.all([
    consolidatedKpis(ctx, f),
    monthlyVisitors(ctx, commune),
    topSearches(ctx, f),
    weakSignal(ctx, f),
    communeStats(ctx),
    adoptionFunnel(ctx),
  ]);
  const launched = months.findIndex((m) => m.value > 0);
  const first = launched >= 0 ? months[launched].value : 0;
  const lastFull = months.length >= 2 ? months[months.length - 2].value : 0;
  const growth = launched >= 0 && launched < months.length - 2 && first > 0 ? Math.round(((lastFull - first) / first) * 100) : null;
  return { f, commune, kpis, months, launchedLabel: launched >= 0 ? months[launched].label : null, growth, searches, signal, communesData, funnel };
}
