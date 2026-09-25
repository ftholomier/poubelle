import { and, eq, inArray, or, sql } from 'drizzle-orm';
import webpush from 'web-push';
import { db } from './db';
import { companyMembers, pushSubscriptions, roleAssignments } from './db/schema';
import { env } from './env';
import { logger } from './logger';
import { enqueue } from './queue';

/**
 * Notifications push (Web Push, VAPID) : nouveaux messages, rendez-vous et candidatures pour les
 * professionnels, revendications à valider pour la collectivité. Envoi différé par la file de tâches ;
 * les abonnements expirés (404/410) ou en échec répété sont supprimés.
 */

export type PushPayload = { title: string; body: string; url: string; tag?: string };

export function pushConfigured(): boolean {
  return Boolean(env.VAPID_PUBLIC_KEY && env.VAPID_PRIVATE_KEY);
}

export function vapidPublicKey(): string | null {
  return pushConfigured() ? env.VAPID_PUBLIC_KEY! : null;
}

let configured = false;
function setup() {
  if (!configured && pushConfigured()) {
    webpush.setVapidDetails(env.VAPID_SUBJECT, env.VAPID_PUBLIC_KEY!, env.VAPID_PRIVATE_KEY!);
    configured = true;
  }
  return configured;
}

export async function savePushSubscription(userId: string, sub: { endpoint: string; keys: { p256dh: string; auth: string } }, userAgent: string | null) {
  await db
    .insert(pushSubscriptions)
    .values({ userId, endpoint: sub.endpoint, p256dh: sub.keys.p256dh, auth: sub.keys.auth, userAgent: userAgent?.slice(0, 255) ?? null })
    .onConflictDoUpdate({
      target: pushSubscriptions.endpoint,
      set: { userId, p256dh: sub.keys.p256dh, auth: sub.keys.auth, userAgent: userAgent?.slice(0, 255) ?? null, failures: 0 },
    });
}

export async function deletePushSubscription(userId: string, endpoint: string) {
  await db.delete(pushSubscriptions).where(and(eq(pushSubscriptions.userId, userId), eq(pushSubscriptions.endpoint, endpoint)));
}

export async function userSubscriptionCount(userId: string): Promise<number> {
  const [r] = await db
    .select({ n: sql<number>`count(*)::int` })
    .from(pushSubscriptions)
    .where(eq(pushSubscriptions.userId, userId));
  return Number(r?.n ?? 0);
}

/** Envoie immédiatement aux appareils des utilisateurs (utilisé par la tâche « push.send »). */
export async function deliverPush(userIds: string[], payload: PushPayload): Promise<{ sent: number; removed: number }> {
  if (!userIds.length || !setup()) return { sent: 0, removed: 0 };
  const subs = await db.select().from(pushSubscriptions).where(inArray(pushSubscriptions.userId, userIds));
  let sent = 0;
  let removed = 0;
  const body = JSON.stringify(payload);
  for (const s of subs) {
    try {
      await webpush.sendNotification({ endpoint: s.endpoint, keys: { p256dh: s.p256dh, auth: s.auth } }, body, {
        TTL: 86_400,
        urgency: 'normal',
        topic: payload.tag?.slice(0, 32),
      });
      await db.update(pushSubscriptions).set({ failures: 0, lastSuccessAt: new Date() }).where(eq(pushSubscriptions.id, s.id));
      sent++;
    } catch (err) {
      const status = (err as { statusCode?: number }).statusCode;
      if (status === 404 || status === 410 || s.failures >= 4) {
        await db.delete(pushSubscriptions).where(eq(pushSubscriptions.id, s.id));
        removed++;
      } else {
        await db
          .update(pushSubscriptions)
          .set({ failures: sql`${pushSubscriptions.failures} + 1` })
          .where(eq(pushSubscriptions.id, s.id));
        logger.warn('push.failed', { status, err: err instanceof Error ? err.message : String(err) });
      }
    }
  }
  return { sent, removed };
}

/** Programme une notification (sans bloquer la requête en cours). */
export async function notifyUsers(userIds: string[], payload: PushPayload): Promise<void> {
  const ids = [...new Set(userIds)];
  if (!ids.length || !pushConfigured()) return;
  try {
    await enqueue('push.send', { userIds: ids, payload }, { maxAttempts: 2 });
  } catch (err) {
    logger.warn('push.enqueue_failed', { err: err instanceof Error ? err.message : String(err) });
  }
}

/** Membres de l'entreprise (propriétaires et collaborateurs). */
export async function notifyCompany(companyId: string, payload: PushPayload): Promise<void> {
  if (!pushConfigured()) return;
  const rows = await db.select({ userId: companyMembers.userId }).from(companyMembers).where(eq(companyMembers.companyId, companyId));
  await notifyUsers(
    rows.map((r) => r.userId),
    payload,
  );
}

/** Équipe du territoire (administrateurs et éditeurs) et, si précisé, agents de la commune concernée. */
export async function notifyTerritoryStaff(territoryId: string, payload: PushPayload, communeId?: string | null): Promise<void> {
  if (!pushConfigured()) return;
  const rows = await db
    .select({ userId: roleAssignments.userId })
    .from(roleAssignments)
    .where(
      or(
        and(eq(roleAssignments.territoryId, territoryId), inArray(roleAssignments.role, ['TERRITORY_ADMIN', 'TERRITORY_EDITOR'])),
        communeId ? and(eq(roleAssignments.communeId, communeId), inArray(roleAssignments.role, ['COMMUNE_ADMIN', 'COMMUNE_EDITOR'])) : undefined,
      ),
    );
  await notifyUsers(
    rows.map((r) => r.userId),
    payload,
  );
}
