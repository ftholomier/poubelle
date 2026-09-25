import { and, asc, desc, eq, gte, inArray, isNull, lte, ne, or, sql, type SQL } from 'drizzle-orm';
import { FAMILIES, PUBLIC_STATUSES, type EstablishmentStatus, type Family, type PlanKey } from '@/lib/constants';
import { parisDate } from '@/lib/format';
import { addDaysIso, openStatus, type HoursException, type HoursSlot, type OpenStatus } from '@/lib/hours';
import { computeCompleteness } from '../completeness';
import { db, type DbOrTx } from '../db';
import {
  attributes,
  campaignParticipants,
  campaigns,
  categories,
  communes,
  companies,
  establishmentAttributes,
  establishmentCategories,
  establishmentPages,
  establishmentRevisions,
  establishments,
  events,
  exceptionalHours,
  jobs,
  media,
  openingHours,
  posts,
  products,
  territoryCategories,
  type RevisionChange,
} from '../db/schema';
import { categoryDisplayName } from './categories';

export type Establishment = typeof establishments.$inferSelect;

export type AttributeTag = { slug: string; label: string; group: string };

export type EstablishmentCard = {
  id: string;
  slug: string;
  name: string;
  path: string;
  family: Family;
  familyLabel: string;
  color: string;
  activity: string;
  categoryId: string;
  categorySlug: string;
  categoryName: string;
  communeId: string;
  communeName: string;
  communeSlug: string;
  coverUrl: string | null;
  logoUrl: string | null;
  lat: number | null;
  lng: number | null;
  phone: string | null;
  tags: string[];
  attributeSlugs: string[];
  open: OpenStatus;
  isFeatured: boolean;
  completeness: number;
  status: EstablishmentStatus;
  companyId: string;
  updatedAt: Date;
};

export const publicStatusFilter = () => inArray(establishments.status, PUBLIC_STATUSES);

const TAG_GROUPS = new Set(['SERVICE', 'LABEL', 'HIGHLIGHT']);

/** Charge des cartes d'établissements (liste, carte, recherche) avec horaires et étiquettes. */
export async function loadCards(
  where: SQL | undefined,
  opts: { limit?: number; offset?: number; orderBy?: SQL[]; now?: Date } = {},
): Promise<EstablishmentCard[]> {
  const now = opts.now ?? new Date();
  const rows = await db
    .select({
      e: {
        id: establishments.id,
        slug: establishments.slug,
        name: establishments.name,
        activityLabel: establishments.activityLabel,
        coverUrl: establishments.coverUrl,
        logoUrl: establishments.logoUrl,
        lat: establishments.lat,
        lng: establishments.lng,
        phone: establishments.phone,
        isFeatured: establishments.isFeatured,
        completeness: establishments.completeness,
        status: establishments.status,
        companyId: establishments.companyId,
        updatedAt: establishments.updatedAt,
      },
      c: { id: communes.id, name: communes.name, slug: communes.slug },
      k: { id: categories.id, name: categoryDisplayName, slug: categories.slug, family: categories.family },
    })
    .from(establishments)
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .innerJoin(categories, eq(categories.id, establishments.categoryId))
    .leftJoin(territoryCategories, and(eq(territoryCategories.territoryId, establishments.territoryId), eq(territoryCategories.categoryId, establishments.categoryId)))
    .where(where)
    .orderBy(...(opts.orderBy ?? [desc(establishments.isFeatured), desc(establishments.completeness), asc(establishments.name)]))
    .limit(opts.limit ?? 500)
    .offset(opts.offset ?? 0);
  if (!rows.length) return [];
  const ids = rows.map((r) => r.e.id);
  const today = parisDate(now);
  const [hours, exceptions, attrs] = await Promise.all([
    db
      .select({
        establishmentId: openingHours.establishmentId,
        weekday: openingHours.weekday,
        opensAt: openingHours.opensAt,
        closesAt: openingHours.closesAt,
      })
      .from(openingHours)
      .where(inArray(openingHours.establishmentId, ids)),
    db
      .select()
      .from(exceptionalHours)
      .where(
        and(
          inArray(exceptionalHours.establishmentId, ids),
          gte(exceptionalHours.date, addDaysIso(today, -1)),
          lte(exceptionalHours.date, addDaysIso(today, 8)),
        ),
      ),
    db
      .select({
        establishmentId: establishmentAttributes.establishmentId,
        slug: attributes.slug,
        label: attributes.label,
        group: attributes.group,
        sortOrder: attributes.sortOrder,
      })
      .from(establishmentAttributes)
      .innerJoin(attributes, eq(attributes.id, establishmentAttributes.attributeId))
      .where(inArray(establishmentAttributes.establishmentId, ids))
      .orderBy(asc(attributes.sortOrder)),
  ]);
  const hoursBy = groupBy(hours, (h) => h.establishmentId);
  const excBy = groupBy(exceptions, (x) => x.establishmentId);
  const attrBy = groupBy(attrs, (a) => a.establishmentId);

  return rows.map(({ e, c, k }) => {
    const family = k.family as Family;
    const st = openStatus(
      (hoursBy.get(e.id) ?? []) as HoursSlot[],
      (excBy.get(e.id) ?? []).map((x) => ({ date: x.date, closed: x.closed, opensAt: x.opensAt, closesAt: x.closesAt })),
      now,
    );
    const a = attrBy.get(e.id) ?? [];
    const tags = a.filter((x) => TAG_GROUPS.has(x.group)).map((x) => x.label);
    if (st.openTonight && family === 'RESTAURATION' && !tags.includes('Ouvert ce soir')) tags.unshift('Ouvert ce soir');
    return {
      id: e.id,
      slug: e.slug,
      name: e.name,
      path: `/${c.slug}/${k.slug}/${e.slug}`,
      family,
      familyLabel: FAMILIES[family].label,
      color: FAMILIES[family].color,
      activity: e.activityLabel ?? k.name,
      categoryId: k.id,
      categorySlug: k.slug,
      categoryName: k.name,
      communeId: c.id,
      communeName: c.name,
      communeSlug: c.slug,
      coverUrl: e.coverUrl,
      logoUrl: e.logoUrl,
      lat: e.lat,
      lng: e.lng,
      phone: e.phone,
      tags,
      attributeSlugs: a.map((x) => x.slug),
      open: st,
      isFeatured: e.isFeatured,
      completeness: e.completeness,
      status: e.status as EstablishmentStatus,
      companyId: e.companyId,
      updatedAt: e.updatedAt,
    };
  });
}

export function groupBy<T, K>(items: T[], key: (t: T) => K): Map<K, T[]> {
  const m = new Map<K, T[]>();
  for (const it of items) {
    const k = key(it);
    const arr = m.get(k);
    if (arr) arr.push(it);
    else m.set(k, [it]);
  }
  return m;
}

/** Détail complet d'une fiche publique. */
export async function getPublicEstablishment(territoryId: string, communeSlug: string, slug: string) {
  const rows = await db
    .select({ e: establishments, c: communes, k: categories, co: companies })
    .from(establishments)
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .innerJoin(categories, eq(categories.id, establishments.categoryId))
    .innerJoin(companies, eq(companies.id, establishments.companyId))
    .where(and(eq(establishments.territoryId, territoryId), eq(communes.slug, communeSlug), eq(establishments.slug, slug), publicStatusFilter()))
    .limit(1);
  const row = rows[0];
  if (!row) return null;
  return loadEstablishmentDetail(row);
}

export async function getEstablishmentDetailById(id: string) {
  const rows = await db
    .select({ e: establishments, c: communes, k: categories, co: companies })
    .from(establishments)
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .innerJoin(categories, eq(categories.id, establishments.categoryId))
    .innerJoin(companies, eq(companies.id, establishments.companyId))
    .where(eq(establishments.id, id))
    .limit(1);
  const row = rows[0];
  if (!row) return null;
  return loadEstablishmentDetail(row);
}

async function loadEstablishmentDetail(row: {
  e: Establishment;
  c: typeof communes.$inferSelect;
  k: typeof categories.$inferSelect;
  co: typeof companies.$inferSelect;
}) {
  const { e } = row;
  const now = new Date();
  const today = parisDate(now);
  // Nom de catégorie personnalisé par le territoire.
  const [override] = await db
    .select({ label: territoryCategories.label })
    .from(territoryCategories)
    .where(and(eq(territoryCategories.territoryId, e.territoryId), eq(territoryCategories.categoryId, e.categoryId)))
    .limit(1);
  if (override?.label) row = { ...row, k: { ...row.k, name: override.label } };
  const [photos, prods, hours, exceptions, attrs, news, upcomingEvents, openJobs, pages, offers, secondary] = await Promise.all([
    db
      .select()
      .from(media)
      .where(and(eq(media.establishmentId, e.id), eq(media.kind, 'IMAGE'), eq(media.isPrivate, false)))
      .orderBy(asc(media.sortOrder), asc(media.createdAt)),
    db.select().from(products).where(eq(products.establishmentId, e.id)).orderBy(asc(products.sortOrder)),
    db.select().from(openingHours).where(eq(openingHours.establishmentId, e.id)).orderBy(asc(openingHours.weekday), asc(openingHours.opensAt)),
    db
      .select()
      .from(exceptionalHours)
      .where(and(eq(exceptionalHours.establishmentId, e.id), gte(exceptionalHours.date, addDaysIso(today, -1))))
      .orderBy(asc(exceptionalHours.date)),
    db
      .select({ id: attributes.id, slug: attributes.slug, label: attributes.label, group: attributes.group, sortOrder: attributes.sortOrder })
      .from(establishmentAttributes)
      .innerJoin(attributes, eq(attributes.id, establishmentAttributes.attributeId))
      .where(eq(establishmentAttributes.establishmentId, e.id))
      .orderBy(asc(attributes.sortOrder)),
    db
      .select()
      .from(posts)
      .where(and(eq(posts.establishmentId, e.id), eq(posts.status, 'PUBLISHED'), or(isNull(posts.expiresAt), gte(posts.expiresAt, now))))
      .orderBy(desc(posts.publishedAt))
      .limit(6),
    db
      .select()
      .from(events)
      .where(and(eq(events.establishmentId, e.id), eq(events.status, 'PUBLISHED'), gte(events.startsAt, new Date(now.getTime() - 86_400_000))))
      .orderBy(asc(events.startsAt))
      .limit(4),
    db
      .select()
      .from(jobs)
      .where(and(eq(jobs.establishmentId, e.id), eq(jobs.status, 'PUBLISHED')))
      .orderBy(desc(jobs.publishedAt)),
    db
      .select()
      .from(establishmentPages)
      .where(and(eq(establishmentPages.establishmentId, e.id), eq(establishmentPages.published, true)))
      .orderBy(asc(establishmentPages.sortOrder)),
    db
      .select({ p: campaignParticipants, cp: campaigns })
      .from(campaignParticipants)
      .innerJoin(campaigns, eq(campaigns.id, campaignParticipants.campaignId))
      .where(
        and(
          eq(campaignParticipants.establishmentId, e.id),
          eq(campaignParticipants.status, 'JOINED'),
          inArray(campaigns.status, ['ACTIVE', 'SCHEDULED']),
          gte(campaigns.endsAt, today),
        ),
      )
      .limit(1),
    db
      .select({ id: categories.id, name: categories.name, slug: categories.slug })
      .from(establishmentCategories)
      .innerJoin(categories, eq(categories.id, establishmentCategories.categoryId))
      .where(eq(establishmentCategories.establishmentId, e.id)),
  ]);
  const slots: HoursSlot[] = hours.map((h) => ({ weekday: h.weekday, opensAt: h.opensAt, closesAt: h.closesAt }));
  const exc: HoursException[] = exceptions.map((x) => ({ date: x.date, closed: x.closed, opensAt: x.opensAt, closesAt: x.closesAt, label: x.label }));
  const family = row.k.family as Family;
  return {
    ...e,
    family,
    familyLabel: FAMILIES[family].label,
    color: FAMILIES[family].color,
    activity: e.activityLabel ?? row.k.name,
    commune: row.c,
    category: row.k,
    company: row.co,
    plan: row.co.plan as PlanKey,
    photos,
    products: prods,
    hours: slots,
    exceptions: exc,
    attributes: attrs,
    news,
    events: upcomingEvents,
    jobs: openJobs,
    pages,
    campaignOffer: offers[0] ? { ...offers[0].p, campaign: offers[0].cp } : null,
    secondaryCategories: secondary,
    open: openStatus(slots, exc, now),
    path: `/${row.c.slug}/${row.k.slug}/${e.slug}`,
  };
}

export type EstablishmentDetail = NonNullable<Awaited<ReturnType<typeof getEstablishmentDetailById>>>;

/** Met à jour les mots-clés de recherche dérivés (catégories, synonymes, services, produits). */
export async function refreshSearchKeywords(establishmentId: string, tx: DbOrTx = db): Promise<void> {
  await tx.execute(sql`
    UPDATE establishments e SET search_keywords = trim(concat_ws(' ',
      (SELECT k.name || ' ' || array_to_string(k.synonyms, ' ') FROM categories k WHERE k.id = e.category_id),
      (SELECT string_agg(k.name || ' ' || array_to_string(k.synonyms, ' '), ' ') FROM establishment_categories ec JOIN categories k ON k.id = ec.category_id WHERE ec.establishment_id = e.id),
      (SELECT string_agg(a.label, ' ') FROM establishment_attributes ea JOIN attributes a ON a.id = ea.attribute_id WHERE ea.establishment_id = e.id),
      (SELECT string_agg(p.name, ' ') FROM products p WHERE p.establishment_id = e.id),
      (SELECT c.name FROM communes c WHERE c.id = e.commune_id)
    ))
    WHERE e.id = ${establishmentId}
  `);
}

/** Recalcule et enregistre le score de complétude d'une fiche. */
export async function refreshCompleteness(establishmentId: string, tx: DbOrTx = db): Promise<number> {
  const input = await completenessInput(establishmentId, tx);
  if (!input) return 0;
  const { score } = computeCompleteness(input);
  await tx.update(establishments).set({ completeness: score }).where(eq(establishments.id, establishmentId));
  return score;
}

export async function completenessInput(establishmentId: string, tx: DbOrTx = db) {
  const [e] = await tx.select().from(establishments).where(eq(establishments.id, establishmentId)).limit(1);
  if (!e) return null;
  // Dans une transaction, un seul client : les requêtes sont enchaînées plutôt que parallèles.
  const run = <T>(fns: (() => Promise<T>)[]): Promise<T[]> =>
    tx === db ? Promise.all(fns.map((f) => f())) : fns.reduce<Promise<T[]>>(async (acc, f) => [...(await acc), await f()], Promise.resolve([]));
  const [photos, hoursCount, exc, prodCount, attrs, lastPost] = (await run<unknown>([
    () =>
      tx
        .select({ tag: media.tag })
        .from(media)
        .where(and(eq(media.establishmentId, establishmentId), eq(media.kind, 'IMAGE'), eq(media.isPrivate, false))),
    () =>
      tx
        .select({ n: sql<number>`count(*)::int` })
        .from(openingHours)
        .where(eq(openingHours.establishmentId, establishmentId)),
    () => tx.select({ date: exceptionalHours.date }).from(exceptionalHours).where(eq(exceptionalHours.establishmentId, establishmentId)),
    () =>
      tx
        .select({ n: sql<number>`count(*)::int` })
        .from(products)
        .where(eq(products.establishmentId, establishmentId)),
    () =>
      tx
        .select({ group: attributes.group })
        .from(establishmentAttributes)
        .innerJoin(attributes, eq(attributes.id, establishmentAttributes.attributeId))
        .where(eq(establishmentAttributes.establishmentId, establishmentId)),
    () =>
      tx
        .select({ at: sql<Date | null>`max(${posts.publishedAt})` })
        .from(posts)
        .where(and(eq(posts.establishmentId, establishmentId), eq(posts.status, 'PUBLISHED'))),
  ])) as [{ tag: string | null }[], { n: number }[], { date: string }[], { n: number }[], { group: string }[], { at: Date | null }[]];
  return {
    name: e.name,
    street: e.street,
    phone: e.phone,
    email: e.email,
    website: e.website,
    socials: e.socials as Record<string, string | undefined>,
    description: e.description,
    logoUrl: e.logoUrl,
    coverUrl: e.coverUrl,
    photos,
    hoursCount: Number(hoursCount[0]?.n ?? 0),
    exceptionalDates: exc.map((x) => x.date),
    hoursConfirmedAt: e.hoursConfirmedAt,
    productsCount: Number(prodCount[0]?.n ?? 0),
    paymentCount: attrs.filter((a) => a.group === 'PAYMENT').length,
    serviceCount: attrs.filter((a) => a.group !== 'PAYMENT').length,
    lastPostAt: lastPost[0]?.at ? new Date(lastPost[0].at) : null,
  };
}

const FIELD_LABELS: Record<string, string> = {
  name: 'Nom commercial',
  description: 'Description',
  tagline: 'Accroche',
  activityLabel: 'Activité',
  categoryId: 'Catégorie principale',
  street: 'Adresse',
  postalCode: 'Code postal',
  phone: 'Téléphone',
  email: 'Email',
  website: 'Site internet',
  socials: 'Réseaux sociaux',
  priceInfo: 'Tarifs indicatifs',
  serviceArea: "Zone d'intervention",
  accessibilityInfo: 'Accessibilité',
  status: 'Statut',
  lat: 'Position',
  lng: 'Position',
  hours: 'Horaires',
  attributes: 'Services & labels',
};

/** Compare deux états et historise les changements significatifs. */
export async function recordRevision(
  establishmentId: string,
  before: Record<string, unknown>,
  after: Record<string, unknown>,
  meta: { userId: string | null; source: 'PRO' | 'COLLECTIVITE' | 'IMPORT' | 'SYSTEM'; summary?: string },
  tx: DbOrTx = db,
): Promise<RevisionChange[]> {
  const changes: RevisionChange[] = [];
  for (const [field, to] of Object.entries(after)) {
    const from = before[field];
    if (JSON.stringify(from ?? null) === JSON.stringify(to ?? null)) continue;
    changes.push({ field, label: FIELD_LABELS[field] ?? field, from: from ?? null, to: to ?? null });
  }
  if (!changes.length) return changes;
  const summary = meta.summary ?? `Modification : ${[...new Set(changes.map((c) => c.label))].slice(0, 4).join(', ')}${changes.length > 4 ? '…' : ''}`;
  await tx.insert(establishmentRevisions).values({
    establishmentId,
    userId: meta.userId,
    source: meta.source,
    summary,
    changes,
  });
  return changes;
}

/** Établissements d'une commune (suggestions « Aussi à … »). */
export async function relatedCards(territoryId: string, communeId: string, excludeId: string, limit = 4) {
  return loadCards(
    and(eq(establishments.territoryId, territoryId), eq(establishments.communeId, communeId), ne(establishments.id, excludeId), publicStatusFilter()),
    { limit },
  );
}
