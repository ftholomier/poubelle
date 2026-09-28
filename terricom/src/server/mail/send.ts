import { eq } from 'drizzle-orm';
import nodemailer, { type Transporter } from 'nodemailer';
import { db } from '../db';
import { emails } from '../db/schema';
import { env } from '../env';
import { logger } from '../logger';
import { enqueue } from '../queue';

export type OutgoingEmail = {
  to: string;
  subject: string;
  html: string;
  text: string;
  template?: string;
  territoryId?: string | null;
  headers?: Record<string, string>;
};

/**
 * Enregistre l'email puis le confie au worker (envoi SMTP avec nouvelles tentatives).
 * En mode « outbox », l'email est conservé en base et consultable dans la console.
 */
export async function sendEmail(mail: OutgoingEmail): Promise<string> {
  const [row] = await db
    .insert(emails)
    .values({
      to: mail.to,
      subject: mail.subject,
      html: mail.html,
      text: mail.text,
      template: mail.template ?? null,
      territoryId: mail.territoryId ?? null,
      headers: mail.headers ?? {},
      status: env.MAIL_DRIVER === 'outbox' ? 'OUTBOX' : 'QUEUED',
    })
    .returning({ id: emails.id });
  if (env.MAIL_DRIVER === 'smtp') await enqueue('email.send', { emailId: row.id });
  else logger.info('email.outbox', { to: mail.to, subject: mail.subject, template: mail.template });
  return row.id;
}

let transporter: Transporter | null = null;

function getTransporter(): Transporter {
  if (!transporter) {
    if (!env.SMTP_URL) throw new Error('SMTP_URL non configuré');
    transporter = nodemailer.createTransport(env.SMTP_URL);
  }
  return transporter;
}

/** Envoi effectif (appelé par le worker). */
export async function deliverEmail(emailId: string): Promise<void> {
  const [mail] = await db.select().from(emails).where(eq(emails.id, emailId)).limit(1);
  if (!mail || mail.status === 'SENT') return;
  try {
    const info = await getTransporter().sendMail({
      from: env.MAIL_FROM,
      to: mail.to,
      subject: mail.subject,
      html: mail.html,
      text: mail.text,
      headers: mail.headers,
    });
    await db
      .update(emails)
      .set({ status: 'SENT', sentAt: new Date(), providerId: info.messageId ?? null, attempts: mail.attempts + 1 })
      .where(eq(emails.id, emailId));
  } catch (err) {
    await db
      .update(emails)
      .set({ status: 'FAILED', error: err instanceof Error ? err.message : String(err), attempts: mail.attempts + 1 })
      .where(eq(emails.id, emailId));
    throw err;
  }
}

/** Envoi direct (worker newsletter) : pas de ligne « emails » pour chaque destinataire. */
export async function deliverRaw(mail: Omit<OutgoingEmail, 'template' | 'territoryId'>): Promise<string | null> {
  if (env.MAIL_DRIVER === 'outbox') return null;
  const info = await getTransporter().sendMail({
    from: env.MAIL_FROM,
    to: mail.to,
    subject: mail.subject,
    html: mail.html,
    text: mail.text,
    headers: mail.headers,
  });
  return info.messageId ?? null;
}
