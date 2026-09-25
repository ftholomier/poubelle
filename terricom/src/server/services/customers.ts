import { and, asc, count, desc, eq, gte, isNotNull, ne, sql } from 'drizzle-orm';
import { randomToken, sha256 } from '../crypto';
import { db } from '../db';
import { categories, communes, companyContacts, establishments, newsletterDeliveries, newsletters, type NewsletterBlock } from '../db/schema';
import { enqueue } from '../queue';

/**
 * Clients abonnés d'une entreprise (offre Communication) : abonnement depuis la fiche en double
 * opt-in, ajout manuel sur attestation de consentement, désinscription en un clic, lettres de
 * l'entreprise envoyées par la file de tâches.
 */

/** Lettres de l'entreprise autorisées sur 30 jours glissants. */
export const CUSTOMER_LETTERS_PER_30_DAYS = 4;

const active = and(eq(companyContacts.subscribed, true), isNotNull(companyContacts.confirmedAt));

export async function contactStats(companyId: string) {
  const [r] = await db
    .select({
      active: sql<number>`count(*) filter (where ${companyContacts.subscribed} and ${companyContacts.confirmedAt} is not null)::int`,
      pending: sql<number>`count(*) filter (where ${companyContacts.subscribed} and ${companyContacts.confirmedAt} is null)::int`,
      unsubscribed: sql<number>`count(*) filter (where not ${companyContacts.subscribed})::int`,
    })
    .from(companyContacts)
    .where(eq(companyContacts.companyId, companyId));
  return { active: r?.active ?? 0, pending: r?.pending ?? 0, unsubscribed: r?.unsubscribed ?? 0 };
}

export async function listContacts(companyId: string, limit = 200) {
  return db.select().from(companyContacts).where(eq(companyContacts.companyId, companyId)).orderBy(desc(companyContacts.createdAt)).limit(limit);
}

/** Demande d'abonnement depuis la fiche : l'adresse reste en attente jusqu'à la confirmation. */
export async function requestFollow(input: { companyId: string; establishmentId: string; email: string; fullName: string | null; consentText: string }) {
  const token = randomToken(24);
  const [existing] = await db
    .select()
    .from(companyContacts)
    .where(and(eq(companyContacts.companyId, input.companyId), eq(companyContacts.email, input.email)))
    .limit(1);
  if (existing?.subscribed && existing.confirmedAt) return { token: null, already: true };
  if (existing)
    await db
      .update(companyContacts)
      .set({
        subscribed: true,
        unsubscribedAt: null,
        confirmTokenHash: sha256(token),
        confirmedAt: null,
        consentText: input.consentText,
        consentAt: new Date(),
        source: 'FICHE',
        establishmentId: input.establishmentId,
        fullName: input.fullName ?? existing.fullName,
      })
      .where(eq(companyContacts.id, existing.id));
  else
    await db.insert(companyContacts).values({
      companyId: input.companyId,
      establishmentId: input.establishmentId,
      email: input.email,
      fullName: input.fullName,
      source: 'FICHE',
      consentText: input.consentText,
      consentAt: new Date(),
      confirmTokenHash: sha256(token),
      unsubscribeToken: randomToken(24),
    });
  return { token, already: false };
}

export async function confirmFollow(token: string) {
  if (!/^[A-Za-z0-9_-]{16,64}$/.test(token)) return null;
  const [c] = await db
    .select()
    .from(companyContacts)
    .where(eq(companyContacts.confirmTokenHash, sha256(token)))
    .limit(1);
  if (!c) return null;
  await db.update(companyContacts).set({ confirmedAt: new Date(), confirmTokenHash: null, subscribed: true }).where(eq(companyContacts.id, c.id));
  return c;
}

/** Fiche suivie par un contact (celle de l'abonnement, sinon la première de l'entreprise). */
export async function followedEstablishment(c: { companyId: string; establishmentId: string | null }) {
  const [row] = await db
    .select({ id: establishments.id, name: establishments.name, slug: establishments.slug, communeSlug: communes.slug, categorySlug: categories.slug })
    .from(establishments)
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .innerJoin(categories, eq(categories.id, establishments.categoryId))
    .where(and(eq(establishments.companyId, c.companyId), c.establishmentId ? eq(establishments.id, c.establishmentId) : undefined))
    .orderBy(asc(establishments.createdAt))
    .limit(1);
  return row ? { id: row.id, name: row.name, path: `/${row.communeSlug}/${row.categorySlug}/${row.slug}` } : null;
}

export async function contactByUnsubscribeToken(token: string) {
  if (!/^[A-Za-z0-9_-]{16,64}$/.test(token)) return null;
  const [c] = await db.select().from(companyContacts).where(eq(companyContacts.unsubscribeToken, token)).limit(1);
  return c ?? null;
}

/** Désinscription (lien de chaque lettre, en-tête List-Unsubscribe). */
export async function unfollow(token: string) {
  if (!/^[A-Za-z0-9_-]{16,64}$/.test(token)) return null;
  const [c] = await db.select().from(companyContacts).where(eq(companyContacts.unsubscribeToken, token)).limit(1);
  if (!c) return null;
  if (c.subscribed) {
    await db.update(companyContacts).set({ subscribed: false, unsubscribedAt: new Date() }).where(eq(companyContacts.id, c.id));
    const [last] = await db
      .select({ newsletterId: newsletterDeliveries.newsletterId })
      .from(newsletterDeliveries)
      .where(and(eq(newsletterDeliveries.contactId, c.id), gte(newsletterDeliveries.sentAt, new Date(Date.now() - 30 * 86_400_000))))
      .orderBy(desc(newsletterDeliveries.sentAt))
      .limit(1);
    if (last)
      await db
        .update(newsletters)
        .set({ statsUnsubscribes: sql`${newsletters.statsUnsubscribes} + 1` })
        .where(eq(newsletters.id, last.newsletterId));
  }
  return c;
}

/** Ajout par le professionnel d'un client qui a donné son accord (attestation conservée). */
export async function addContact(companyId: string, email: string, fullName: string | null, attestedBy: string) {
  await db
    .insert(companyContacts)
    .values({
      companyId,
      email,
      fullName,
      source: 'MANUAL',
      consentText: `Accord du client attesté par ${attestedBy} (ajout manuel)`,
      consentAt: new Date(),
      confirmedAt: new Date(),
      unsubscribeToken: randomToken(24),
    })
    .onConflictDoNothing();
}

export async function lettersLast30Days(companyId: string): Promise<number> {
  const [r] = await db
    .select({ n: count() })
    .from(newsletters)
    .where(and(eq(newsletters.companyId, companyId), gte(newsletters.createdAt, new Date(Date.now() - 30 * 86_400_000)), ne(newsletters.status, 'DRAFT')));
  return Number(r?.n ?? 0);
}

/** Crée la lettre de l'entreprise et prépare un envoi par client actif ; le worker envoie par lots. */
export async function sendCustomerLetter(input: {
  territoryId: string;
  companyId: string;
  establishmentId: string;
  subject: string;
  title: string;
  intro: string;
  blocks: NewsletterBlock[];
  createdById: string;
}): Promise<{ id: string; recipients: number }> {
  const [n] = await db
    .insert(newsletters)
    .values({
      territoryId: input.territoryId,
      companyId: input.companyId,
      establishmentId: input.establishmentId,
      subject: input.subject,
      title: input.title,
      intro: input.intro,
      blocks: input.blocks,
      status: 'SENDING',
      scheduledAt: new Date(),
      createdById: input.createdById,
    })
    .returning();
  const rows = await db
    .select({ id: companyContacts.id, email: companyContacts.email })
    .from(companyContacts)
    .where(and(eq(companyContacts.companyId, input.companyId), active));
  for (let i = 0; i < rows.length; i += 500)
    await db
      .insert(newsletterDeliveries)
      .values(rows.slice(i, i + 500).map((r) => ({ newsletterId: n.id, contactId: r.id, email: r.email, token: randomToken(24) })))
      .onConflictDoNothing();
  await db.update(newsletters).set({ statsRecipients: rows.length }).where(eq(newsletters.id, n.id));
  if (rows.length) await enqueue('newsletter.send-batch', { newsletterId: n.id }, { dedupeKey: `nl-batch:${n.id}:0` });
  else await db.update(newsletters).set({ status: 'SENT', sentAt: new Date() }).where(eq(newsletters.id, n.id));
  return { id: n.id, recipients: rows.length };
}

export async function customerLetters(companyId: string) {
  return db
    .select({
      id: newsletters.id,
      subject: newsletters.subject,
      status: newsletters.status,
      sentAt: newsletters.sentAt,
      createdAt: newsletters.createdAt,
      recipients: newsletters.statsRecipients,
      opens: newsletters.statsOpens,
      clicks: newsletters.statsClicks,
      unsubscribes: newsletters.statsUnsubscribes,
    })
    .from(newsletters)
    .where(eq(newsletters.companyId, companyId))
    .orderBy(desc(newsletters.createdAt))
    .limit(12);
}
