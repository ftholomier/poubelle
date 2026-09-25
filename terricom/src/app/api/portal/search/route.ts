import { NextResponse, type NextRequest } from 'next/server';
import { parseExplorerParams, toSearchParams, type ExplorerResponse } from '@/lib/explorer';
import { track } from '@/server/analytics';
import { rateLimit } from '@/server/auth/rate-limit';
import { isLocale } from '@/lib/i18n';
import { searchTerritory, toExplorerItem } from '@/server/services/search';
import { getEnabledModules, resolveTerritoryParam } from '@/server/services/territories';

const PAGE = 40;

/** Recherche de l'explorateur (liste + carte), appelée à chaque changement de filtre. */
export async function GET(req: NextRequest) {
  const sp = req.nextUrl.searchParams;
  const param = sp.get('t') ?? req.headers.get('x-terricom-portal-param') ?? '';
  const territory = param ? await resolveTerritoryParam(param) : null;
  if (!territory || territory.status === 'CHURNED' || territory.status === 'SUSPENDED') {
    return NextResponse.json({ error: 'Territoire introuvable' }, { status: 404 });
  }
  const state = parseExplorerParams(sp);
  const ip = (req.headers.get('x-forwarded-for')?.split(',')[0] ?? req.headers.get('x-real-ip') ?? '').trim() || 'anon';
  // Les requêtes en langage naturel sollicitent l'IA : quota plus serré.
  const limited = await rateLimit(`search:${state.q ? 'q' : 'f'}:${ip}`, state.q ? 40 : 150, 60);
  if (!limited.ok) {
    return NextResponse.json(
      { error: 'Trop de recherches, réessayez dans un instant.' },
      { status: 429, headers: { 'retry-after': String(Math.ceil((limited.resetAt.getTime() - Date.now()) / 1000)) } },
    );
  }
  const offset = Math.max(0, Math.min(5000, Number(sp.get('offset')) || 0));
  const modules = await getEnabledModules(territory.id);
  const lang = sp.get('lang');
  const locale = modules.has('MULTILINGUAL') && isLocale(lang) ? lang : 'fr';
  const res = await searchTerritory(territory, { ...toSearchParams(state), limit: 5000, withAnswer: offset === 0, ai: modules.has('AI') });
  if (offset === 0 && state.q) {
    await track({
      type: 'SEARCH',
      territoryId: territory.id,
      query: state.q,
      resultCount: res.total,
      path: '/explorer',
      userAgent: req.headers.get('user-agent'),
      ip,
    });
  }
  const body: ExplorerResponse = {
    total: res.total,
    ids: res.items.map((i) => i.id),
    items: res.items.slice(offset, offset + PAGE).map((i) => toExplorerItem(i, locale)),
    // La réponse de l'assistant est rédigée en français : elle n'est affichée qu'en français.
    answer: locale === 'fr' ? res.answer : null,
  };
  return NextResponse.json(body, { headers: { 'cache-control': 'private, no-store' } });
}
