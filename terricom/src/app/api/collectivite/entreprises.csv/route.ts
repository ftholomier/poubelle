import { and, asc, eq, inArray, ne, sql, type SQL } from 'drizzle-orm';
import { NextResponse, type NextRequest } from 'next/server';
import { ESTABLISHMENT_STATUS, PLAN_LABELS, type EstablishmentStatus } from '@/lib/constants';
import { audit } from '@/server/audit';
import { csvResponse, toCsv } from '@/server/csv';
import { db } from '@/server/db';
import { categories, communes, companies, establishments } from '@/server/db/schema';
import { estScope } from '@/server/services/backoffice';
import { boApiContext } from '@/server/services/bo-api';
import { portalUrl } from '@/server/urls';

const STATUS_KEYS: Record<string, EstablishmentStatus> = {
  precreee: 'PRECREATED',
  'a-completer': 'TO_COMPLETE',
  revendiquee: 'CLAIMED',
  validee: 'VALIDATED',
  suspendue: 'SUSPENDED',
  archivee: 'ARCHIVED',
};

/** Export CSV des établissements du périmètre (filtres de la liste ou sélection). */
export async function GET(req: NextRequest) {
  const ctx = await boApiContext();
  if (!ctx) return new NextResponse('Non autorisé', { status: 401 });
  const sp = req.nextUrl.searchParams;
  const conds: SQL[] = [estScope(ctx)];
  const ids = (sp.get('ids') ?? '').split(',').filter((x) => /^[0-9a-f-]{36}$/.test(x)).slice(0, 5000);
  if (ids.length) conds.push(inArray(establishments.id, ids));
  const st = STATUS_KEYS[sp.get('statut') ?? ''];
  conds.push(st ? eq(establishments.status, st) : ne(establishments.status, 'ARCHIVED'));
  const q = (sp.get('q') ?? '').trim();
  if (q) conds.push(sql`f_unaccent(${establishments.name}) ilike f_unaccent(${`%${q}%`})`);
  const commune = sp.get('commune');
  if (commune && /^[0-9a-f-]{36}$/.test(commune)) conds.push(eq(establishments.communeId, commune));
  const rows = await db
    .select({
      name: establishments.name,
      siret: establishments.siret,
      status: establishments.status,
      street: establishments.street,
      postalCode: establishments.postalCode,
      phone: establishments.phone,
      email: establishments.email,
      website: establishments.website,
      completeness: establishments.completeness,
      lastActivityAt: establishments.lastActivityAt,
      slug: establishments.slug,
      communeName: communes.name,
      communeSlug: communes.slug,
      categoryName: categories.name,
      categorySlug: categories.slug,
      plan: companies.plan,
      managed: sql<boolean>`exists (select 1 from company_members m where m.company_id = "establishments"."company_id")`,
    })
    .from(establishments)
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .innerJoin(categories, eq(categories.id, establishments.categoryId))
    .innerJoin(companies, eq(companies.id, establishments.companyId))
    .where(and(...conds))
    .orderBy(asc(communes.name), asc(establishments.name))
    .limit(20_000);
  await audit({
    actor: { user: ctx.actor.user },
    category: 'RGPD',
    action: 'establishments.export',
    summary: `Export CSV de ${rows.length} fiches`,
    territoryId: ctx.territory.id,
  });
  const body = toCsv(
    ['Nom', 'SIRET', 'Statut', 'Commune', 'Catégorie', 'Adresse', 'Code postal', 'Téléphone', 'Email', 'Site web', 'Complétude (%)', 'Offre', 'Revendiquée', 'Dernière activité', 'Fiche publique'],
    rows.map((r) => [
      r.name,
      r.siret,
      ESTABLISHMENT_STATUS[r.status].label,
      r.communeName,
      r.categoryName,
      r.street,
      r.postalCode,
      r.phone,
      r.email,
      r.website,
      r.completeness,
      r.managed ? PLAN_LABELS[r.plan] : '',
      r.managed ? 'oui' : 'non',
      r.lastActivityAt ? r.lastActivityAt.toISOString().slice(0, 10) : '',
      portalUrl(ctx.territory, `/${r.communeSlug}/${r.categorySlug}/${r.slug}`),
    ]),
  );
  return csvResponse(body, `etablissements-${ctx.territory.slug}-${new Date().toISOString().slice(0, 10)}.csv`);
}
