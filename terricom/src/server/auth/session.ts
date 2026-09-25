import { and, eq, gt, lt, or, sql } from 'drizzle-orm';
import { cookies } from 'next/headers';
import { cache } from 'react';
import { db } from '../db';
import { sessions, users } from '../db/schema';
import { env, isProd } from '../env';
import { randomToken, sha256 } from '../crypto';
import { requestInfo } from '../request';

export const SESSION_COOKIE = 'tc_session';
const IDLE_DAYS = 14;
const ABSOLUTE_DAYS = 30;
const DAY = 86_400_000;

export type SessionUser = typeof users.$inferSelect;
export type SessionRow = typeof sessions.$inferSelect;

/** Crée une session en base et pose le cookie (uniquement depuis une Server Action ou un Route Handler). */
export async function createSession(userId: string, opts: { mfaVerified: boolean }): Promise<string> {
  const token = randomToken(32);
  const info = await requestInfo();
  await db.insert(sessions).values({
    id: sha256(token),
    userId,
    expiresAt: new Date(Date.now() + IDLE_DAYS * DAY),
    ip: info.ip,
    userAgent: info.userAgent.slice(0, 500),
    mfaVerified: opts.mfaVerified,
  });
  const jar = await cookies();
  jar.set(SESSION_COOKIE, token, {
    httpOnly: true,
    secure: isProd || env.APP_URL.startsWith('https://'),
    sameSite: 'lax',
    path: '/',
    maxAge: ABSOLUTE_DAYS * 24 * 3600,
  });
  return token;
}

async function readToken(): Promise<string | null> {
  const jar = await cookies();
  return jar.get(SESSION_COOKIE)?.value ?? null;
}

/** Session courante (mise en cache pour la durée de la requête). */
export const getSession = cache(async (): Promise<{ session: SessionRow; user: SessionUser } | null> => {
  const token = await readToken();
  if (!token) return null;
  const id = sha256(token);
  const rows = await db
    .select({ session: sessions, user: users })
    .from(sessions)
    .innerJoin(users, eq(users.id, sessions.userId))
    .where(and(eq(sessions.id, id), gt(sessions.expiresAt, new Date())))
    .limit(1);
  const row = rows[0];
  if (!row) return null;
  if (row.user.status !== 'ACTIVE') return null;
  if (row.session.createdAt.getTime() < Date.now() - ABSOLUTE_DAYS * DAY) return null;
  // Expiration glissante : on prolonge au plus une fois toutes les 5 minutes.
  if (Date.now() - row.session.lastSeenAt.getTime() > 5 * 60_000) {
    db.update(sessions)
      .set({ lastSeenAt: new Date(), expiresAt: new Date(Date.now() + IDLE_DAYS * DAY) })
      .where(eq(sessions.id, id))
      .catch(() => {});
  }
  return row;
});

export async function currentSessionId(): Promise<string | null> {
  const token = await readToken();
  return token ? sha256(token) : null;
}

export async function markMfaVerified(): Promise<void> {
  const id = await currentSessionId();
  if (!id) return;
  await db.update(sessions).set({ mfaVerified: true }).where(eq(sessions.id, id));
}

export async function destroySession(): Promise<void> {
  const id = await currentSessionId();
  if (id) await db.delete(sessions).where(eq(sessions.id, id));
  const jar = await cookies();
  jar.delete(SESSION_COOKIE);
}

export async function revokeSession(userId: string, sessionId: string): Promise<void> {
  await db.delete(sessions).where(and(eq(sessions.id, sessionId), eq(sessions.userId, userId)));
}

export async function revokeOtherSessions(userId: string): Promise<void> {
  const current = await currentSessionId();
  await db.delete(sessions).where(and(eq(sessions.userId, userId), current ? sql`${sessions.id} <> ${current}` : sql`true`));
}

export async function listSessions(userId: string) {
  return db
    .select()
    .from(sessions)
    .where(and(eq(sessions.userId, userId), gt(sessions.expiresAt, new Date())))
    .orderBy(sql`${sessions.lastSeenAt} desc`);
}

export async function purgeExpiredSessions(): Promise<number> {
  const res = await db.delete(sessions).where(or(lt(sessions.expiresAt, new Date()), lt(sessions.createdAt, new Date(Date.now() - ABSOLUTE_DAYS * DAY))));
  return res.rowCount ?? 0;
}

/** Démarre un accès support temporaire (30 min) au contexte d'un territoire. */
export async function startImpersonation(territoryId: string, ticket: string, minutes = 30): Promise<void> {
  const id = await currentSessionId();
  if (!id) return;
  await db
    .update(sessions)
    .set({
      impersonationTerritoryId: territoryId,
      impersonationExpiresAt: new Date(Date.now() + minutes * 60_000),
      impersonationTicket: ticket,
    })
    .where(eq(sessions.id, id));
}

export async function stopImpersonation(): Promise<void> {
  const id = await currentSessionId();
  if (!id) return;
  await db.update(sessions).set({ impersonationTerritoryId: null, impersonationExpiresAt: null, impersonationTicket: null }).where(eq(sessions.id, id));
}
