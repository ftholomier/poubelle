import { and, asc, eq, inArray, sql } from 'drizzle-orm';
import { cookies } from 'next/headers';
import { FAMILIES, type Family, PUBLIC_STATUSES } from '@/lib/constants';
import { PASSPORT_COOKIE } from '@/lib/cookies';
import { randomToken, sha256, shortCode } from '../crypto';
import { db } from '../db';
import { categories, circuitStops, circuits, communes, establishments, passports, passportStamps } from '../db/schema';

export type CircuitStopView = {
  id: string;
  position: number;
  name: string;
  activity: string;
  communeName: string;
  color: string;
  coverUrl: string | null;
  lat: number | null;
  lng: number | null;
  path: string | null;
  note: string | null;
};

export async function listPublishedCircuits(territoryId: string) {
  return db
    .select()
    .from(circuits)
    .where(and(eq(circuits.territoryId, territoryId), eq(circuits.status, 'PUBLISHED')))
    .orderBy(asc(circuits.sortOrder), asc(circuits.name));
}

/** Circuit publié et ses étapes (les fiches non publiques restent listées, sans lien). */
export async function getCircuitDetail(territoryId: string, slug: string) {
  const [circuit] = await db
    .select()
    .from(circuits)
    .where(and(eq(circuits.territoryId, territoryId), eq(circuits.slug, slug), eq(circuits.status, 'PUBLISHED')))
    .limit(1);
  if (!circuit) return null;
  const rows = await db
    .select({
      s: circuitStops,
      e: {
        name: establishments.name,
        slug: establishments.slug,
        status: establishments.status,
        activityLabel: establishments.activityLabel,
        coverUrl: establishments.coverUrl,
        lat: establishments.lat,
        lng: establishments.lng,
      },
      c: { name: communes.name, slug: communes.slug },
      k: { name: categories.name, slug: categories.slug, family: categories.family },
    })
    .from(circuitStops)
    .leftJoin(establishments, eq(establishments.id, circuitStops.establishmentId))
    .leftJoin(communes, eq(communes.id, establishments.communeId))
    .leftJoin(categories, eq(categories.id, establishments.categoryId))
    .where(eq(circuitStops.circuitId, circuit.id))
    .orderBy(asc(circuitStops.position));
  const stops: CircuitStopView[] = rows.map(({ s, e, c, k }) => {
    const isPublic = Boolean(e && PUBLIC_STATUSES.includes(e.status as (typeof PUBLIC_STATUSES)[number]));
    return {
      id: s.id,
      position: s.position,
      name: e?.name ?? s.name ?? `Étape ${s.position + 1}`,
      activity: e?.activityLabel ?? k?.name ?? '',
      communeName: c?.name ?? '',
      color: k ? FAMILIES[k.family as Family].color : '#1F6B52',
      coverUrl: e?.coverUrl ?? null,
      lat: s.lat ?? e?.lat ?? null,
      lng: s.lng ?? e?.lng ?? null,
      path: isPublic && e && c && k ? `/${c.slug}/${k.slug}/${e.slug}` : null,
      note: s.note,
    };
  });
  return { circuit, stops };
}

/** Jetons de passeport du visiteur (cookie httpOnly : { circuitId: jeton }). */
async function passportTokens(): Promise<Record<string, string>> {
  const jar = await cookies();
  try {
    const parsed = JSON.parse(jar.get(PASSPORT_COOKIE)?.value ?? '{}');
    return parsed && typeof parsed === 'object' ? (parsed as Record<string, string>) : {};
  } catch {
    return {};
  }
}

export type PassportView = { id: string; stamped: string[]; completedAt: Date | null; rewardCode: string | null };

export async function getVisitorPassport(circuitId: string): Promise<PassportView | null> {
  const token = (await passportTokens())[circuitId];
  if (!token) return null;
  const [p] = await db
    .select()
    .from(passports)
    .where(eq(passports.tokenHash, sha256(token)))
    .limit(1);
  if (!p || p.circuitId !== circuitId) return null;
  const stamps = await db.select({ stopId: passportStamps.stopId }).from(passportStamps).where(eq(passportStamps.passportId, p.id));
  return { id: p.id, stamped: stamps.map((s) => s.stopId), completedAt: p.completedAt, rewardCode: p.rewardCode };
}

/** Retrouve ou ouvre le passeport du visiteur (à appeler depuis une action ou un gestionnaire de route). */
export async function ensureVisitorPassport(circuitId: string): Promise<string> {
  const jar = await cookies();
  const tokens = await passportTokens();
  const existing = tokens[circuitId];
  if (existing) {
    const [p] = await db
      .select({ id: passports.id })
      .from(passports)
      .where(eq(passports.tokenHash, sha256(existing)))
      .limit(1);
    if (p) {
      await db.update(passports).set({ lastSeenAt: new Date() }).where(eq(passports.id, p.id));
      return p.id;
    }
  }
  const token = randomToken(24);
  const [row] = await db
    .insert(passports)
    .values({ circuitId, tokenHash: sha256(token), lastSeenAt: new Date() })
    .returning({ id: passports.id });
  tokens[circuitId] = token;
  jar.set(PASSPORT_COOKIE, JSON.stringify(tokens), {
    httpOnly: true,
    sameSite: 'lax',
    path: '/',
    maxAge: 60 * 60 * 24 * 365,
    secure: process.env.NODE_ENV === 'production',
  });
  return row.id;
}

/** Tamponne une étape ; renvoie l'état du passeport (et le code de récompense une fois le seuil atteint). */
export async function stampPassport(passportId: string, stopId: string, opts: { toggle?: boolean } = {}) {
  const [stop] = await db.select().from(circuitStops).where(eq(circuitStops.id, stopId)).limit(1);
  if (!stop) return null;
  const already = await db
    .select()
    .from(passportStamps)
    .where(and(eq(passportStamps.passportId, passportId), eq(passportStamps.stopId, stopId)))
    .limit(1);
  let added = false;
  if (already.length && opts.toggle) {
    await db.delete(passportStamps).where(and(eq(passportStamps.passportId, passportId), eq(passportStamps.stopId, stopId)));
  } else if (!already.length) {
    await db.insert(passportStamps).values({ passportId, stopId });
    added = true;
  }
  const [circuit] = await db.select().from(circuits).where(eq(circuits.id, stop.circuitId)).limit(1);
  const [{ n }] = await db
    .select({ n: sql<number>`count(*)::int` })
    .from(passportStamps)
    .where(eq(passportStamps.passportId, passportId));
  const [p] = await db.select().from(passports).where(eq(passports.id, passportId)).limit(1);
  const threshold =
    circuit.rewardThreshold ?? (await db.select({ id: circuitStops.id }).from(circuitStops).where(eq(circuitStops.circuitId, circuit.id))).length;
  if (Number(n) >= threshold && !p.completedAt) {
    await db
      .update(passports)
      .set({ completedAt: new Date(), rewardCode: shortCode(6) })
      .where(eq(passports.id, passportId));
  }
  return { added, count: Number(n), threshold, position: stop.position, circuit };
}

export async function stopsBySecret(secret: string) {
  const [row] = await db
    .select({ stop: circuitStops, circuit: circuits })
    .from(circuitStops)
    .innerJoin(circuits, eq(circuits.id, circuitStops.circuitId))
    .where(eq(circuitStops.stampSecret, secret))
    .limit(1);
  return row ?? null;
}

export async function circuitIdsForStops(stopIds: string[]) {
  if (!stopIds.length) return [];
  return db.select({ id: circuitStops.id, circuitId: circuitStops.circuitId }).from(circuitStops).where(inArray(circuitStops.id, stopIds));
}

/**
 * Scan du QR code de vitrine d'un établissement : si le visiteur a ouvert le passeport
 * d'un circuit dont c'est une étape, l'étape est tamponnée.
 */
export async function stampVisitorPassportsAt(establishmentId: string): Promise<{ circuitSlug: string; position: number } | null> {
  const tokens = await passportTokens();
  const ids = Object.keys(tokens).filter((id) => /^[0-9a-f-]{36}$/.test(id));
  if (!ids.length) return null;
  const rows = await db
    .select({ stop: circuitStops, circuit: circuits })
    .from(circuitStops)
    .innerJoin(circuits, eq(circuits.id, circuitStops.circuitId))
    .where(and(eq(circuitStops.establishmentId, establishmentId), inArray(circuitStops.circuitId, ids), eq(circuits.status, 'PUBLISHED')));
  let first: { circuitSlug: string; position: number } | null = null;
  for (const r of rows) {
    const [p] = await db
      .select({ id: passports.id, circuitId: passports.circuitId })
      .from(passports)
      .where(eq(passports.tokenHash, sha256(tokens[r.circuit.id])))
      .limit(1);
    if (!p || p.circuitId !== r.circuit.id) continue;
    await stampPassport(p.id, r.stop.id);
    first ??= { circuitSlug: r.circuit.slug, position: r.stop.position };
  }
  return first;
}
