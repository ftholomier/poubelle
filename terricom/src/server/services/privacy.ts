import { eq, inArray, sql } from 'drizzle-orm';
import { db } from '../db';
import { appointments, companyContacts, jobApplications, media, messages, newsletterDeliveries, subscribers, users } from '../db/schema';
import { deleteMediaFiles } from '../media';

/**
 * Exercice des droits des personnes (habitants, candidats, contacts) traité par l'exploitant
 * pour le compte des collectivités : export des données (accès, portabilité) et effacement.
 * Les titulaires d'un compte exportent et suppriment eux-mêmes leurs données depuis leur espace.
 */

export async function personalDataFor(email: string) {
  const e = email.trim().toLowerCase();
  const [subs, contacts, msgs, apps, appts, deliveries, account] = await Promise.all([
    db.execute(sql`
      select s.email, s.first_name, s.status, s.source, s.consent_text, s.consent_at, s.confirmed_at, s.unsubscribed_at, t.name as territory, c.name as commune
      from subscribers s join territories t on t.id = s.territory_id left join communes c on c.id = s.commune_id where s.email = ${e}`),
    db.execute(sql`
      select cc.email, cc.full_name, cc.source, cc.consent_text, cc.consent_at, cc.subscribed, co.trade_name as company
      from company_contacts cc join companies co on co.id = cc.company_id where cc.email = ${e}`),
    db.execute(sql`
      select m.created_at, m.sender_name, m.sender_email, m.sender_phone, m.subject, m.body, es.name as establishment
      from messages m join establishments es on es.id = m.establishment_id where m.sender_email = ${e}`),
    db.execute(sql`
      select a.created_at, a.full_name, a.email, a.phone, a.message, a.status, j.title as job, es.name as establishment, (a.cv_media_id is not null) as cv_provided
      from job_applications a join jobs j on j.id = a.job_id join establishments es on es.id = a.establishment_id where a.email = ${e}`),
    db.execute(sql`
      select a.created_at, a.full_name, a.email, a.phone, a.service, a.preferred_at, a.message, a.status, es.name as establishment
      from appointments a join establishments es on es.id = a.establishment_id where a.email = ${e}`),
    db.execute(sql`
      select n.subject as newsletter, d.status, d.sent_at, d.opened_at, d.clicked_at
      from newsletter_deliveries d join newsletters n on n.id = d.newsletter_id where d.email = ${e} order by d.sent_at desc nulls last limit 500`),
    db.select({ id: users.id }).from(users).where(eq(users.email, e)).limit(1),
  ]);
  return {
    exportedAt: new Date().toISOString(),
    email: e,
    notice:
      'Données personnelles détenues sur la plateforme terricom pour le compte des collectivités. ' +
      (account.length ? 'Un compte utilisateur existe également : son titulaire peut exporter ses données depuis « Mon compte ».' : ''),
    newsletterSubscriptions: subs.rows,
    companyNewsletterContacts: contacts.rows,
    messagesToBusinesses: msgs.rows,
    jobApplications: apps.rows,
    appointmentRequests: appts.rows,
    newsletterDeliveries: deliveries.rows,
    hasAccount: account.length > 0,
  };
}

/** Efface ou anonymise toutes les données rattachées à une adresse email (hors compte utilisateur). */
export type ErasureReport = {
  subscribers: number;
  contacts: number;
  deliveries: number;
  messages: number;
  applications: number;
  appointments: number;
  cvs: number;
  hasAccount: boolean;
};

export async function erasePersonalData(email: string): Promise<ErasureReport> {
  const e = email.trim().toLowerCase();
  const anon = `efface-${Date.now().toString(36)}@anonyme.invalid`;
  const cvs = await db
    .select({ id: media.id, storageKey: media.storageKey, kind: media.kind, variants: media.variants })
    .from(jobApplications)
    .innerJoin(media, eq(media.id, jobApplications.cvMediaId))
    .where(eq(jobApplications.email, e));
  const result = await db.transaction(async (tx) => {
    const s = await tx.delete(subscribers).where(eq(subscribers.email, e));
    const c = await tx.delete(companyContacts).where(eq(companyContacts.email, e));
    const d = await tx.update(newsletterDeliveries).set({ email: anon }).where(eq(newsletterDeliveries.email, e));
    const m = await tx
      .update(messages)
      .set({ senderName: 'Personne anonymisée', senderEmail: null, senderPhone: null, body: '[Message effacé à la demande de son auteur]', subject: null })
      .where(eq(messages.senderEmail, e));
    const a = await tx.delete(jobApplications).where(eq(jobApplications.email, e));
    const r = await tx
      .update(appointments)
      .set({ fullName: 'Personne anonymisée', email: anon, phone: null, message: null, responseNote: null })
      .where(eq(appointments.email, e));
    if (cvs.length)
      await tx.delete(media).where(
        inArray(
          media.id,
          cvs.map((x) => x.id),
        ),
      );
    return {
      subscribers: s.rowCount ?? 0,
      contacts: c.rowCount ?? 0,
      deliveries: d.rowCount ?? 0,
      messages: m.rowCount ?? 0,
      applications: a.rowCount ?? 0,
      appointments: r.rowCount ?? 0,
    };
  });
  for (const cv of cvs) await deleteMediaFiles({ storageKey: cv.storageKey, kind: cv.kind, variants: cv.variants }).catch(() => undefined);
  const [account] = await db.select({ id: users.id }).from(users).where(eq(users.email, e)).limit(1);
  return { ...result, cvs: cvs.length, hasAccount: Boolean(account) };
}
