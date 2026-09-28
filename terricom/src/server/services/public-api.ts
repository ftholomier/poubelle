import { and, asc, count, desc, eq, gte, ilike, inArray, isNull, lte, or, sql, type SQL } from 'drizzle-orm';
import { PUBLIC_STATUSES } from '@/lib/constants';
import { parisDate } from '@/lib/format';
import { openStatus, type HoursException, type HoursSlot } from '@/lib/hours';
import { rateLimit } from '../auth/rate-limit';
import { randomToken, sha256, shortCode } from '../crypto';
import { db } from '../db';
import {
  apiKeys,
  attributes,
  categories,
  communes,
  establishmentAttributes,
  establishments,
  events,
  exceptionalHours,
  media,
  openingHours,
  posts,
  territoryCategories,
  type Socials,
} from '../db/schema';
import { portalUrl } from '../urls';
import { categoryDisplayName } from './categories';
import { getTerritoryById, getTerritoryCommunes, type Territory } from './territories';

/**
 * API publique v1 (lecture seule) : données publiées d'un territoire pour l'open data, les sites
 * en marque blanche et les connecteurs (office de tourisme, application mobile, borne…).
 * Authentification par clé propre au territoire, limitation de débit par clé.
 */

export const API_RATE_LIMIT = 120; // requêtes par minute et par clé
export const API_MAX_PER_PAGE = 100;

// ─── Clés d'accès ───────────────────────────────────────────────────────────

/** Crée une clé : la valeur complète n'est affichée qu'une fois, seule son empreinte est conservée. */
export async function createApiKey(territoryId: string, name: string): Promise<{ id: string; key: string }> {
  const prefix = shortCode(8).toLowerCase();
  const key = `tc_${prefix}_${randomToken(24)}`;
  const [row] = await db
    .insert(apiKeys)
    .values({ territoryId, name, prefix, keyHash: sha256(key), scopes: ['read'] })
    .returning({ id: apiKeys.id });
  return { id: row.id, key };
}

export async function listApiKeys(territoryId: string) {
  return db.select().from(apiKeys).where(eq(apiKeys.territoryId, territoryId)).orderBy(desc(apiKeys.createdAt));
}

export async function revokeApiKey(territoryId: string, id: string) {
  const [row] = await db
    .update(apiKeys)
    .set({ revokedAt: new Date() })
    .where(and(eq(apiKeys.id, id), eq(apiKeys.territoryId, territoryId), isNull(apiKeys.revokedAt)))
    .returning({ name: apiKeys.name });
  return row ?? null;
}

// ─── Réponses ───────────────────────────────────────────────────────────────

const CORS = {
  'access-control-allow-origin': '*',
  'access-control-allow-methods': 'GET, OPTIONS',
  'access-control-allow-headers': 'authorization, x-api-key, content-type',
  'access-control-max-age': '86400',
};

export function apiJson(body: unknown, status = 200, headers: Record<string, string> = {}): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'content-type': 'application/json; charset=utf-8', 'cache-control': 'private, max-age=60', ...CORS, ...headers },
  });
}

export function apiError(status: number, code: string, message: string, headers: Record<string, string> = {}): Response {
  return apiJson({ error: { code, message } }, status, { 'cache-control': 'no-store', ...headers });
}

export function apiOptions(): Response {
  return new Response(null, { status: 204, headers: CORS });
}

export type ApiContext = { territory: Territory; keyId: string; rateHeaders: Record<string, string> };

/** Vérifie la clé (en-tête Authorization: Bearer … ou X-Api-Key) et la limite de débit. */
export async function authenticate(req: Request): Promise<ApiContext | Response> {
  const auth = req.headers.get('authorization') ?? '';
  const raw = (auth.toLowerCase().startsWith('bearer ') ? auth.slice(7) : (req.headers.get('x-api-key') ?? '')).trim();
  if (!/^tc_[a-z0-9]{8}_[A-Za-z0-9_-]{20,64}$/.test(raw))
    return apiError(401, 'unauthorized', 'Clé d’API absente ou mal formée (en-tête Authorization: Bearer <clé>).');
  const [key] = await db
    .select()
    .from(apiKeys)
    .where(eq(apiKeys.keyHash, sha256(raw)))
    .limit(1);
  if (!key || key.revokedAt) return apiError(401, 'unauthorized', 'Clé d’API inconnue ou révoquée.');
  const limit = await rateLimit(`api:${key.id}`, API_RATE_LIMIT, 60);
  const rateHeaders = {
    'x-ratelimit-limit': String(API_RATE_LIMIT),
    'x-ratelimit-remaining': String(limit.remaining),
    'x-ratelimit-reset': String(Math.ceil(limit.resetAt.getTime() / 1000)),
  };
  if (!limit.ok)
    return apiError(429, 'rate_limited', `Limite de ${API_RATE_LIMIT} requêtes par minute atteinte.`, {
      ...rateHeaders,
      'retry-after': String(Math.max(1, Math.ceil((limit.resetAt.getTime() - Date.now()) / 1000))),
    });
  const territory = await getTerritoryById(key.territoryId);
  if (!territory || territory.status === 'SUSPENDED' || territory.status === 'CHURNED') return apiError(403, 'forbidden', 'Territoire indisponible.');
  // Date de dernière utilisation, mise à jour au plus toutes les 5 minutes.
  if (!key.lastUsedAt || Date.now() - key.lastUsedAt.getTime() > 300_000)
    await db.update(apiKeys).set({ lastUsedAt: new Date() }).where(eq(apiKeys.id, key.id));
  return { territory, keyId: key.id, rateHeaders };
}

export function pagination(url: URL): { page: number; perPage: number; offset: number } {
  const page = Math.max(1, Math.min(10_000, Number(url.searchParams.get('page')) || 1));
  const perPage = Math.max(1, Math.min(API_MAX_PER_PAGE, Number(url.searchParams.get('per_page')) || 20));
  return { page, perPage, offset: (page - 1) * perPage };
}

// ─── Données ────────────────────────────────────────────────────────────────

export async function apiTerritory(t: Territory) {
  const [list, [e]] = await Promise.all([
    getTerritoryCommunes(t.id),
    db
      .select({ n: count() })
      .from(establishments)
      .where(and(eq(establishments.territoryId, t.id), inArray(establishments.status, PUBLIC_STATUSES))),
  ]);
  return {
    id: t.id,
    slug: t.slug,
    name: t.name,
    legal_name: t.legalName,
    tagline: t.tagline,
    url: portalUrl(t, '/'),
    colors: { primary: t.colorPrimary, accent: t.colorAccent },
    logo_url: t.logoUrl,
    communes: list.length,
    establishments: Number(e?.n ?? 0),
  };
}

export async function apiCommunes(t: Territory) {
  const list = await getTerritoryCommunes(t.id);
  const counts = list.length
    ? await db
        .select({ communeId: establishments.communeId, n: count() })
        .from(establishments)
        .where(and(eq(establishments.territoryId, t.id), inArray(establishments.status, PUBLIC_STATUSES)))
        .groupBy(establishments.communeId)
    : [];
  return list.map((c) => ({
    id: c.id,
    slug: c.slug,
    name: c.name,
    insee_code: c.inseeCode,
    postal_codes: c.postalCodes,
    geo: c.lat !== null && c.lng !== null ? { lat: c.lat, lng: c.lng } : null,
    establishments: Number(counts.find((x) => x.communeId === c.id)?.n ?? 0),
    url: portalUrl(t, `/${c.slug}`),
  }));
}

export async function apiCategories(t: Territory) {
  const rows = await db
    .select({
      slug: categories.slug,
      name: categoryDisplayName,
      family: categories.family,
      establishments: sql<number>`count(${establishments.id})::int`,
    })
    .from(categories)
    .leftJoin(territoryCategories, and(eq(territoryCategories.categoryId, categories.id), eq(territoryCategories.territoryId, t.id)))
    .innerJoin(
      establishments,
      and(eq(establishments.categoryId, categories.id), eq(establishments.territoryId, t.id), inArray(establishments.status, PUBLIC_STATUSES)),
    )
    .groupBy(categories.id, categories.slug, categories.family, territoryCategories.label)
    .orderBy(asc(categories.sortOrder));
  return rows.map((r) => ({ slug: r.slug, name: r.name, family: r.family, establishments: Number(r.establishments) }));
}

const estSelect = {
  e: establishments,
  communeSlug: communes.slug,
  communeName: communes.name,
  communeInsee: communes.inseeCode,
  categorySlug: categories.slug,
  categoryName: categoryDisplayName,
};

type EstRow = {
  e: typeof establishments.$inferSelect;
  communeSlug: string;
  communeName: string;
  communeInsee: string;
  categorySlug: string;
  categoryName: string;
};

/** Fiches publiées : filtres commune, catégorie, texte et date de modification (synchronisation). */
export async function apiEstablishments(t: Territory, url: URL) {
  const { page, perPage, offset } = pagination(url);
  const filters: (SQL | undefined)[] = [eq(establishments.territoryId, t.id), inArray(establishments.status, PUBLIC_STATUSES)];
  const commune = url.searchParams.get('commune');
  if (commune) filters.push(eq(communes.slug, commune));
  const category = url.searchParams.get('category');
  if (category) filters.push(eq(categories.slug, category));
  const q = url.searchParams.get('q')?.trim().slice(0, 100);
  if (q) filters.push(or(ilike(establishments.name, `%${q}%`), ilike(establishments.activityLabel, `%${q}%`)));
  const since = url.searchParams.get('updated_since');
  if (since) {
    const d = new Date(since);
    if (Number.isNaN(d.getTime())) return { error: 'Paramètre updated_since invalide (date ISO 8601 attendue).' } as const;
    filters.push(gte(establishments.updatedAt, d));
  }
  const where = and(...filters);
  const base = db
    .select(estSelect)
    .from(establishments)
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .innerJoin(categories, eq(categories.id, establishments.categoryId))
    .leftJoin(territoryCategories, and(eq(territoryCategories.categoryId, categories.id), eq(territoryCategories.territoryId, t.id)));
  const [rows, [total]] = await Promise.all([
    base.where(where).orderBy(asc(establishments.name)).limit(perPage).offset(offset),
    db
      .select({ n: count() })
      .from(establishments)
      .innerJoin(communes, eq(communes.id, establishments.communeId))
      .innerJoin(categories, eq(categories.id, establishments.categoryId))
      .where(where),
  ]);
  return { data: await serializeEstablishments(t, rows), meta: { page, per_page: perPage, total: Number(total?.n ?? 0) } };
}

export async function apiEstablishment(t: Territory, id: string) {
  if (!/^[0-9a-f-]{36}$/.test(id)) return null;
  const rows = await db
    .select(estSelect)
    .from(establishments)
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .innerJoin(categories, eq(categories.id, establishments.categoryId))
    .leftJoin(territoryCategories, and(eq(territoryCategories.categoryId, categories.id), eq(territoryCategories.territoryId, t.id)))
    .where(and(eq(establishments.id, id), eq(establishments.territoryId, t.id), inArray(establishments.status, PUBLIC_STATUSES)))
    .limit(1);
  if (!rows.length) return null;
  return (await serializeEstablishments(t, rows))[0];
}

async function serializeEstablishments(t: Territory, rows: EstRow[]) {
  if (!rows.length) return [];
  const ids = rows.map((r) => r.e.id);
  const today = parisDate();
  const [hours, exceptions, attrs, photos] = await Promise.all([
    db.select().from(openingHours).where(inArray(openingHours.establishmentId, ids)).orderBy(asc(openingHours.weekday), asc(openingHours.opensAt)),
    db
      .select()
      .from(exceptionalHours)
      .where(and(inArray(exceptionalHours.establishmentId, ids), gte(exceptionalHours.date, today))),
    db
      .select({ establishmentId: establishmentAttributes.establishmentId, slug: attributes.slug, label: attributes.label, group: attributes.group })
      .from(establishmentAttributes)
      .innerJoin(attributes, eq(attributes.id, establishmentAttributes.attributeId))
      .where(inArray(establishmentAttributes.establishmentId, ids)),
    db
      .select({ establishmentId: media.establishmentId, url: media.url, variants: media.variants, alt: media.alt })
      .from(media)
      .where(and(inArray(media.establishmentId, ids), eq(media.kind, 'IMAGE'), eq(media.isPrivate, false)))
      .orderBy(asc(media.sortOrder)),
  ]);
  const now = new Date();
  return rows.map(({ e, ...r }) => {
    const slots: HoursSlot[] = hours.filter((h) => h.establishmentId === e.id).map((h) => ({ weekday: h.weekday, opensAt: h.opensAt, closesAt: h.closesAt }));
    const exc: HoursException[] = exceptions
      .filter((x) => x.establishmentId === e.id)
      .map((x) => ({ date: x.date, closed: x.closed, opensAt: x.opensAt, closesAt: x.closesAt, label: x.label }));
    const open = openStatus(slots, exc, now);
    const socials = (e.socials ?? {}) as Socials;
    return {
      id: e.id,
      slug: e.slug,
      name: e.name,
      url: portalUrl(t, `/${r.communeSlug}/${r.categorySlug}/${e.slug}`),
      activity: e.activityLabel ?? r.categoryName,
      category: { slug: r.categorySlug, name: r.categoryName },
      commune: { slug: r.communeSlug, name: r.communeName, insee_code: r.communeInsee },
      siret: e.siret,
      status: e.status === 'VALIDATED' ? 'verified' : e.status === 'CLAIMED' ? 'claimed' : 'unclaimed',
      tagline: e.tagline,
      description: e.description,
      // Traductions publiées (portail multilingue) : texte seul, sans les informations internes de suivi.
      translations: Object.fromEntries(
        Object.entries(e.translations ?? {}).map(([l, x]) => [l, { tagline: x?.tagline ?? null, description: x?.description ?? null }]),
      ),
      address: { street: e.street, postal_code: e.postalCode, city: r.communeName },
      geo: e.lat !== null && e.lng !== null ? { lat: e.lat, lng: e.lng } : null,
      contact: { phone: e.phone, email: e.email, website: e.website, socials },
      logo_url: e.logoUrl,
      cover_url: e.coverUrl,
      photos: photos
        .filter((m) => m.establishmentId === e.id)
        .slice(0, 8)
        .map((m) => ({ url: ((m.variants ?? {}) as Record<string, string>).w1280 ?? m.url, alt: m.alt })),
      hours: slots.map((s) => ({ weekday: s.weekday, opens: s.opensAt.slice(0, 5), closes: s.closesAt.slice(0, 5) })),
      exceptional_hours: exc.map((x) => ({
        date: x.date,
        closed: x.closed,
        opens: x.opensAt?.slice(0, 5) ?? null,
        closes: x.closesAt?.slice(0, 5) ?? null,
        label: x.label ?? null,
      })),
      open_now: open.unknown ? null : open.open,
      attributes: attrs.filter((a) => a.establishmentId === e.id).map((a) => ({ slug: a.slug, label: a.label, group: a.group.toLowerCase() })),
      price_info: e.priceInfo,
      accessibility_info: e.accessibilityInfo,
      updated_at: e.updatedAt.toISOString(),
    };
  });
}

/** Événements publiés à venir (ou dans l'intervalle from/to). */
export async function apiEvents(t: Territory, url: URL) {
  const { page, perPage, offset } = pagination(url);
  const from = url.searchParams.get('from');
  const to = url.searchParams.get('to');
  const fromDate = from ? new Date(from) : new Date(Date.now() - 86_400_000);
  const toDate = to ? new Date(to) : null;
  if (Number.isNaN(fromDate.getTime()) || (toDate && Number.isNaN(toDate.getTime())))
    return { error: 'Paramètres from/to invalides (dates ISO 8601).' } as const;
  const where = and(
    eq(events.territoryId, t.id),
    eq(events.status, 'PUBLISHED'),
    or(gte(events.startsAt, fromDate), gte(events.endsAt, fromDate)),
    toDate ? lte(events.startsAt, toDate) : undefined,
  );
  const [rows, [total]] = await Promise.all([
    db
      .select({ ev: events, communeName: communes.name, communeSlug: communes.slug })
      .from(events)
      .leftJoin(communes, eq(communes.id, events.communeId))
      .where(where)
      .orderBy(asc(events.startsAt))
      .limit(perPage)
      .offset(offset),
    db.select({ n: count() }).from(events).where(where),
  ]);
  return {
    data: rows.map(({ ev, communeName, communeSlug }) => ({
      id: ev.id,
      slug: ev.slug,
      title: ev.title,
      kind: ev.kind.toLowerCase(),
      summary: ev.summary,
      description: ev.description,
      starts_at: ev.startsAt.toISOString(),
      ends_at: ev.endsAt?.toISOString() ?? null,
      all_day: ev.allDay,
      location: { name: ev.locationName, address: ev.address, commune: communeSlug ? { slug: communeSlug, name: communeName } : null },
      geo: ev.lat !== null && ev.lng !== null ? { lat: ev.lat, lng: ev.lng } : null,
      price: ev.priceText,
      accessibility: ev.accessibilityText,
      registration_url: ev.registrationUrl,
      image_url: ev.imageUrl,
      establishment_id: ev.establishmentId,
      organizer: ev.organizerName,
      url: portalUrl(t, `/agenda/${ev.slug}`),
      updated_at: ev.updatedAt.toISOString(),
    })),
    meta: { page, per_page: perPage, total: Number(total?.n ?? 0) },
  };
}

/** Actualités et offres publiées par les commerces et la collectivité. */
export async function apiPosts(t: Territory, url: URL) {
  const { page, perPage, offset } = pagination(url);
  const kind = url.searchParams.get('kind')?.toUpperCase();
  const now = new Date();
  const where = and(
    eq(posts.territoryId, t.id),
    eq(posts.status, 'PUBLISHED'),
    or(isNull(posts.expiresAt), gte(posts.expiresAt, now)),
    kind && ['NEWS', 'PROMO', 'NOUVEAUTE', 'EVENT', 'HOURS', 'JOB'].includes(kind) ? eq(posts.kind, kind as 'NEWS') : undefined,
  );
  const [rows, [total]] = await Promise.all([
    db.select().from(posts).where(where).orderBy(desc(posts.publishedAt)).limit(perPage).offset(offset),
    db.select({ n: count() }).from(posts).where(where),
  ]);
  return {
    data: rows.map((p) => ({
      id: p.id,
      kind: p.kind.toLowerCase(),
      title: p.title,
      body: p.body,
      image_url: p.imageUrl,
      promo: p.promoLabel ? { label: p.promoLabel, valid_from: p.validFrom, valid_to: p.validTo } : null,
      cta: p.ctaUrl ? { label: p.ctaLabel, url: p.ctaUrl } : null,
      author: p.authorType.toLowerCase(),
      establishment_id: p.establishmentId,
      published_at: p.publishedAt?.toISOString() ?? null,
    })),
    meta: { page, per_page: perPage, total: Number(total?.n ?? 0) },
  };
}

/** Exécute un point d'accès : authentification, réponse JSON avec en-têtes de débit, erreurs normalisées. */
export async function handleApi(req: Request, fn: (ctx: ApiContext, url: URL) => Promise<unknown>): Promise<Response> {
  const ctx = await authenticate(req);
  if (ctx instanceof Response) return ctx;
  try {
    const out = await fn(ctx, new URL(req.url));
    if (out === null) return apiError(404, 'not_found', 'Ressource introuvable.', ctx.rateHeaders);
    if (out && typeof out === 'object' && 'error' in out && typeof out.error === 'string') return apiError(400, 'bad_request', out.error, ctx.rateHeaders);
    return apiJson(out && typeof out === 'object' && 'data' in out ? out : { data: out }, 200, ctx.rateHeaders);
  } catch (err) {
    const { logger } = await import('../logger');
    logger.error('api.v1_failed', { path: new URL(req.url).pathname, err: err instanceof Error ? err.message : String(err) });
    return apiError(500, 'internal_error', 'Erreur interne, réessayez plus tard.', ctx.rateHeaders);
  }
}
