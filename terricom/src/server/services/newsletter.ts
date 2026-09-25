import { and, desc, eq, gte, sql } from 'drizzle-orm';
import { sha256 } from '../crypto';
import { db } from '../db';
import { newsletterDeliveries, newsletters, subscribers } from '../db/schema';

/** Confirmation du double opt-in (lien reçu par email). */
export async function confirmSubscription(territoryId: string, token: string) {
  if (!/^[A-Za-z0-9_-]{16,64}$/.test(token)) return null;
  const [sub] = await db
    .select()
    .from(subscribers)
    .where(and(eq(subscribers.territoryId, territoryId), eq(subscribers.confirmTokenHash, sha256(token))))
    .limit(1);
  if (!sub) return null;
  if (sub.status !== 'CONFIRMED') {
    await db.update(subscribers).set({ status: 'CONFIRMED', confirmedAt: new Date(), confirmTokenHash: null }).where(eq(subscribers.id, sub.id));
  }
  return sub;
}

/** Désinscription en un clic (lien de chaque lettre, en-tête List-Unsubscribe). */
export async function unsubscribe(token: string) {
  if (!/^[A-Za-z0-9_-]{16,64}$/.test(token)) return null;
  const [sub] = await db.select().from(subscribers).where(eq(subscribers.unsubscribeToken, token)).limit(1);
  if (!sub) return null;
  if (sub.status !== 'UNSUBSCRIBED') {
    await db.update(subscribers).set({ status: 'UNSUBSCRIBED', unsubscribedAt: new Date() }).where(eq(subscribers.id, sub.id));
    // Attribuée à la dernière lettre reçue (statistique de désinscription).
    const [last] = await db
      .select({ newsletterId: newsletterDeliveries.newsletterId })
      .from(newsletterDeliveries)
      .where(and(eq(newsletterDeliveries.subscriberId, sub.id), gte(newsletterDeliveries.sentAt, new Date(Date.now() - 30 * 86_400_000))))
      .orderBy(desc(newsletterDeliveries.sentAt))
      .limit(1);
    if (last) await db.update(newsletters).set({ statsUnsubscribes: sql`${newsletters.statsUnsubscribes} + 1` }).where(eq(newsletters.id, last.newsletterId));
  }
  return sub;
}
