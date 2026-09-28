import { randomUUID } from 'node:crypto';
import { asc, eq } from 'drizzle-orm';
import { decrypt, encrypt, hmacSha256, randomToken } from '../crypto';
import { db } from '../db';
import { categories, communes, companies, establishments, events, jobs, openingHours, posts, territories } from '../db/schema';
import { logger } from '../logger';
import { OutboundError, safeFetch } from '../net';
import { enqueue } from '../queue';
import { portalUrl } from '../urls';
import { planLimits } from './billing';

/**
 * Connecteur de synchronisation d'une entreprise (offres Premium et Communication).
 *
 * À chaque contenu publié, la plateforme appelle l'adresse choisie par l'entreprise (scénario Make, Zapier,
 * n8n, son propre site…) avec un message JSON signé : l'entreprise reprend ainsi ses publications, sa fiche,
 * ses événements et ses offres d'emploi sur ses réseaux sociaux, sa fiche Google ou son site, sans ressaisie.
 * Envoi par la file de tâches (5 tentatives), adresse publique uniquement (protection SSRF).
 */

export const CONNECTOR_EVENTS = {
  'post.published': 'Publication',
  'listing.updated': 'Fiche modifiée',
  'event.published': 'Événement',
  'job.published': 'Offre d’emploi',
  test: 'Essai',
} as const;
export type ConnectorEvent = keyof typeof CONNECTOR_EVENTS;

type Connector = { url: string | null; secret: string | null; allowed: boolean };

async function connectorOf(companyId: string): Promise<Connector> {
  const [c] = await db
    .select({ url: companies.socialWebhookUrl, secret: companies.webhookSecret, plan: companies.plan })
    .from(companies)
    .where(eq(companies.id, companyId))
    .limit(1);
  if (!c) return { url: null, secret: null, allowed: false };
  const limits = await planLimits(c.plan);
  let secret: string | null = null;
  try {
    secret = c.secret ? decrypt(c.secret) : null;
  } catch {
    secret = null;
  }
  return { url: c.url, secret, allowed: Boolean(limits.contentSync) };
}

/** Nouveau secret de signature (affiché à l'entreprise, conservé chiffré). */
export function newConnectorSecret(): { plain: string; stored: string } {
  const plain = `whsec_${randomToken(24)}`;
  return { plain, stored: encrypt(plain) };
}

export function readConnectorSecret(stored: string | null): string | null {
  if (!stored) return null;
  try {
    return decrypt(stored);
  } catch {
    return null;
  }
}

/** Signature d'un message : HMAC-SHA256 de « horodatage.corps » avec le secret de l'entreprise. */
export function signConnectorPayload(secret: string, timestamp: number, body: string): string {
  return `sha256=${hmacSha256(secret, `${timestamp}.${body}`)}`;
}

async function establishmentRef(id: string) {
  const [row] = await db
    .select({ e: establishments, t: territories, c: communes, k: categories })
    .from(establishments)
    .innerJoin(territories, eq(territories.id, establishments.territoryId))
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .innerJoin(categories, eq(categories.id, establishments.categoryId))
    .where(eq(establishments.id, id))
    .limit(1);
  if (!row) return null;
  const path = `/${row.c.slug}/${row.k.slug}/${row.e.slug}`;
  return { ...row, path, url: portalUrl(row.t, path) };
}

/** Met en file un événement pour le connecteur de l'entreprise (sans effet s'il n'est pas configuré ou pas inclus). */
export async function emitConnectorEvent(
  companyId: string,
  establishmentId: string | null,
  type: ConnectorEvent,
  data: Record<string, unknown>,
): Promise<boolean> {
  const c = await connectorOf(companyId);
  if (!c.url || !c.secret || !c.allowed) return false;
  await enqueue(
    'connector.deliver',
    { companyId, establishmentId, type, data, deliveryId: randomUUID(), createdAt: new Date().toISOString() },
    { maxAttempts: 5 },
  );
  return true;
}

type Delivery = {
  companyId: string;
  establishmentId: string | null;
  type: ConnectorEvent;
  data: Record<string, unknown>;
  deliveryId: string;
  createdAt: string;
};

async function send(companyId: string, c: Connector, d: Delivery): Promise<{ ok: boolean; status: string; permanent: boolean }> {
  const est = d.establishmentId ? await establishmentRef(d.establishmentId) : null;
  const body = JSON.stringify({
    id: d.deliveryId,
    type: d.type,
    created_at: d.createdAt,
    territory: est ? { slug: est.t.slug, name: est.t.name } : null,
    establishment: est ? { id: est.e.id, name: est.e.name, url: est.url } : null,
    data: d.data,
  });
  const ts = Math.floor(Date.now() / 1000);
  let result: { ok: boolean; status: string; permanent: boolean };
  try {
    const res = await safeFetch(c.url!, {
      method: 'POST',
      body,
      headers: {
        'content-type': 'application/json',
        'user-agent': 'terricom-connecteur/1.0',
        'x-terricom-event': d.type,
        'x-terricom-delivery': d.deliveryId,
        'x-terricom-timestamp': String(ts),
        'x-terricom-signature': signConnectorPayload(c.secret!, ts, body),
      },
      timeoutMs: 10_000,
      maxBytes: 200_000,
    });
    const permanent = !res.ok && res.status >= 400 && res.status < 500 && ![408, 425, 429].includes(res.status);
    result = { ok: res.ok, status: `HTTP ${res.status}`, permanent };
  } catch (err) {
    if (!(err instanceof OutboundError)) throw err;
    result = { ok: false, status: err.message, permanent: err.permanent };
  }
  await db
    .update(companies)
    .set({ webhookLastAt: new Date(), webhookLastStatus: `${CONNECTOR_EVENTS[d.type]} · ${result.ok ? 'reçu' : 'échec'} (${result.status})`.slice(0, 120) })
    .where(eq(companies.id, companyId));
  return result;
}

/** Envoi d'un événement (tâche « connector.deliver ») : une erreur temporaire relance la tâche. */
export async function deliverConnectorEvent(d: Delivery): Promise<'sent' | 'skipped' | 'rejected'> {
  const c = await connectorOf(d.companyId);
  if (!c.url || !c.secret || !c.allowed) return 'skipped';
  const r = await send(d.companyId, c, d);
  if (r.ok) return 'sent';
  if (r.permanent) {
    logger.warn('connector.rejected', { companyId: d.companyId, type: d.type, status: r.status });
    return 'rejected';
  }
  throw new Error(`Connecteur : ${r.status}`);
}

/** Essai immédiat depuis l'espace pro (réponse affichée à l'utilisateur). */
export async function sendConnectorTest(companyId: string, establishmentId: string): Promise<{ ok: boolean; status: string }> {
  const c = await connectorOf(companyId);
  if (!c.allowed) return { ok: false, status: 'La synchronisation est incluse dans les offres Premium et Communication.' };
  if (!c.url || !c.secret) return { ok: false, status: 'Renseignez d’abord l’adresse du connecteur.' };
  const r = await send(companyId, c, {
    companyId,
    establishmentId,
    type: 'test',
    data: { message: 'Essai du connecteur terricom : la signature est à vérifier avec votre secret.' },
    deliveryId: randomUUID(),
    createdAt: new Date().toISOString(),
  });
  return { ok: r.ok, status: r.status };
}

// ─── Contenus transmis ─────────────────────────────────────────────────────

const iso = (d: Date | null | undefined) => (d ? d.toISOString() : null);

/** Fiche à jour (identité, coordonnées, horaires) : de quoi mettre à jour une fiche Google ou un site. */
export async function listingData(establishmentId: string): Promise<Record<string, unknown> | null> {
  const ref = await establishmentRef(establishmentId);
  if (!ref) return null;
  const hours = await db
    .select()
    .from(openingHours)
    .where(eq(openingHours.establishmentId, establishmentId))
    .orderBy(asc(openingHours.weekday), asc(openingHours.opensAt));
  const e = ref.e;
  return {
    name: e.name,
    activity: e.activityLabel ?? ref.k.name,
    category: ref.k.slug,
    tagline: e.tagline,
    description: e.description,
    address: { street: e.street, postal_code: e.postalCode, city: ref.c.name },
    geo: e.lat !== null && e.lng !== null ? { lat: e.lat, lng: e.lng } : null,
    phone: e.phone,
    email: e.email,
    website: e.website,
    socials: e.socials,
    // Jours : 0 = lundi … 6 = dimanche.
    hours: hours.map((h) => ({ weekday: h.weekday, opens: h.opensAt.slice(0, 5), closes: h.closesAt.slice(0, 5) })),
    url: ref.url,
    updated_at: iso(e.updatedAt),
  };
}

/** Publication mise en ligne : notifie le connecteur de l'entreprise auteure. */
export async function emitPostPublished(postId: string): Promise<boolean> {
  const [row] = await db
    .select({ p: posts, companyId: establishments.companyId })
    .from(posts)
    .innerJoin(establishments, eq(establishments.id, posts.establishmentId))
    .where(eq(posts.id, postId))
    .limit(1);
  if (!row || row.p.status !== 'PUBLISHED') return false;
  const p = row.p;
  const ref = await establishmentRef(p.establishmentId!);
  return emitConnectorEvent(row.companyId, p.establishmentId, 'post.published', {
    id: p.id,
    kind: p.kind,
    title: p.title,
    body: p.body,
    promo_label: p.promoLabel,
    image_url: p.imageUrl,
    url: ref ? `${ref.url}#actualites` : null,
    published_at: iso(p.publishedAt),
    expires_at: iso(p.expiresAt),
    channels: p.channels,
    // Textes adaptés par canal (assistant de rédaction), à publier tels quels.
    variants: p.variants,
  });
}

export async function emitEventPublished(eventId: string): Promise<boolean> {
  const [row] = await db
    .select({ ev: events, companyId: establishments.companyId, t: territories })
    .from(events)
    .innerJoin(establishments, eq(establishments.id, events.establishmentId))
    .innerJoin(territories, eq(territories.id, events.territoryId))
    .where(eq(events.id, eventId))
    .limit(1);
  if (!row || row.ev.status !== 'PUBLISHED') return false;
  const ev = row.ev;
  return emitConnectorEvent(row.companyId, ev.establishmentId, 'event.published', {
    id: ev.id,
    title: ev.title,
    kind: ev.kind,
    summary: ev.summary,
    description: ev.description,
    starts_at: iso(ev.startsAt),
    ends_at: iso(ev.endsAt),
    all_day: ev.allDay,
    location: ev.locationName,
    address: ev.address,
    price: ev.priceText,
    registration_url: ev.registrationUrl,
    image_url: ev.imageUrl,
    url: portalUrl(row.t, `/agenda/${ev.slug}`),
    ics_url: portalUrl(row.t, `/agenda/${ev.slug}/ics`),
  });
}

export async function emitJobPublished(jobId: string): Promise<boolean> {
  const [row] = await db
    .select({ j: jobs, companyId: establishments.companyId, t: territories })
    .from(jobs)
    .innerJoin(establishments, eq(establishments.id, jobs.establishmentId))
    .innerJoin(territories, eq(territories.id, jobs.territoryId))
    .where(eq(jobs.id, jobId))
    .limit(1);
  if (!row || row.j.status !== 'PUBLISHED') return false;
  const j = row.j;
  return emitConnectorEvent(row.companyId, j.establishmentId, 'job.published', {
    id: j.id,
    title: j.title,
    contract: j.contractType,
    description: j.description,
    missions: j.missions,
    profile: j.profile,
    start: j.startText,
    salary: j.salaryText,
    work_time: j.workTimeText,
    published_at: iso(j.publishedAt),
    expires_at: iso(j.expiresAt),
    url: portalUrl(row.t, `/emploi/${j.slug}`),
  });
}

export async function emitListingUpdated(establishmentId: string, companyId: string): Promise<boolean> {
  const data = await listingData(establishmentId);
  return data ? emitConnectorEvent(companyId, establishmentId, 'listing.updated', data) : false;
}
