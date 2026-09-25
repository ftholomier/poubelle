'use server';

import { and, eq } from 'drizzle-orm';
import { z } from 'zod';
import { track } from '@/server/analytics';
import { rateLimit } from '@/server/auth/rate-limit';
import { ipHash, randomToken, sha256 } from '@/server/crypto';
import { PUBLIC_STATUSES } from '@/lib/constants';
import { fromParisLocal } from '@/lib/format';
import { db } from '@/server/db';
import {
  appointments,
  audiences,
  circuitStops,
  circuits,
  companies,
  companyMembers,
  establishments,
  jobApplications,
  jobs,
  messages,
  subscriberAudiences,
  subscribers,
  territories,
  users,
} from '@/server/db/schema';
import { env } from '@/server/env';
import { sendEmail } from '@/server/mail/send';
import { ensureVisitorPassport, stampPassport } from '@/server/services/circuits';
import { getTerritoryCommunes } from '@/server/services/territories';
import {
  applicationAckTemplate,
  appointmentRequestTemplate,
  contactMessageTemplate,
  jobApplicationTemplate,
  newsletterConfirmTemplate,
} from '@/server/mail/templates';
import { MediaError, saveDocumentUpload } from '@/server/media';
import { requestInfo } from '@/server/request';
import { appUrl, portalUrl } from '@/server/urls';

export type FormState = { status: 'idle' | 'ok' | 'error'; message?: string; fieldErrors?: Record<string, string> };
export type SubscribeState = FormState;

const email = z.string().trim().toLowerCase().email('Adresse email invalide').max(254);

async function throttle(key: string, limit: number, windowSec: number): Promise<boolean> {
  const info = await requestInfo();
  const res = await rateLimit(`${key}:${ipHash(info.ip) ?? 'anon'}`, limit, windowSec);
  return res.ok;
}

async function ownersEmails(companyId: string): Promise<string[]> {
  const rows = await db
    .select({ email: users.email })
    .from(companyMembers)
    .innerJoin(users, eq(users.id, companyMembers.userId))
    .where(eq(companyMembers.companyId, companyId));
  return rows.map((r) => r.email);
}

// ─── Newsletter : inscription en double opt-in ─────────────────────────────
export async function subscribeNewsletter(_prev: SubscribeState, form: FormData): Promise<SubscribeState> {
  if (form.get('website')) return { status: 'ok', message: "C'est noté !" }; // pot de miel anti-robots
  const parsed = z
    .object({ territoryId: z.string().uuid(), email, consent: z.literal('on', { message: 'Merci de cocher la case de consentement.' }) })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message ?? 'Formulaire invalide.' };
  if (!(await throttle('nl-subscribe', 5, 3600))) return { status: 'error', message: 'Trop de tentatives, réessayez dans une heure.' };
  const { territoryId } = parsed.data;
  const [t] = await db.select().from(territories).where(eq(territories.id, territoryId)).limit(1);
  if (!t) return { status: 'error', message: 'Territoire inconnu.' };
  // Commune facultative, retenue seulement si elle fait partie du territoire (lettres par zone).
  const rawCommune = String(form.get('communeId') ?? '');
  const communeId = /^[0-9a-f-]{36}$/.test(rawCommune) && (await getTerritoryCommunes(territoryId)).some((c) => c.id === rawCommune) ? rawCommune : null;
  const settings = t.settings as { newsletterName?: string };
  const newsletterName = settings.newsletterName ?? `La lettre de ${t.name}`;
  const consentText = `J'accepte de recevoir « ${newsletterName} » de ${t.name}. Mes données ne sont jamais revendues (RGPD).`;
  const info = await requestInfo();
  const token = randomToken(24);
  const [existing] = await db
    .select()
    .from(subscribers)
    .where(and(eq(subscribers.territoryId, territoryId), eq(subscribers.email, parsed.data.email)))
    .limit(1);
  if (existing?.status === 'CONFIRMED') {
    return { status: 'ok', message: "C'est noté ! Premier envoi vendredi à 8h. Désinscription en un clic." };
  }
  let subscriberId: string;
  if (existing) {
    await db
      .update(subscribers)
      .set({
        status: 'PENDING',
        confirmTokenHash: sha256(token),
        consentText,
        consentAt: new Date(),
        consentIpHash: ipHash(info.ip),
        unsubscribedAt: null,
        ...(communeId ? { communeId } : {}),
      })
      .where(eq(subscribers.id, existing.id));
    subscriberId = existing.id;
  } else {
    const [row] = await db
      .insert(subscribers)
      .values({
        territoryId,
        email: parsed.data.email,
        communeId,
        status: 'PENDING',
        source: 'PORTAL',
        consentText,
        consentIpHash: ipHash(info.ip),
        confirmTokenHash: sha256(token),
        unsubscribeToken: randomToken(24),
      })
      .returning({ id: subscribers.id });
    subscriberId = row.id;
    const defaults = await db
      .select({ id: audiences.id })
      .from(audiences)
      .where(and(eq(audiences.territoryId, territoryId), eq(audiences.isDefault, true)));
    if (defaults.length)
      await db
        .insert(subscriberAudiences)
        .values(defaults.map((a) => ({ subscriberId, audienceId: a.id })))
        .onConflictDoNothing();
  }
  await sendEmail({
    ...newsletterConfirmTemplate({
      to: parsed.data.email,
      territory: t,
      newsletterName,
      url: portalUrl(t, `/newsletter/confirmer?token=${token}`),
    }),
    territoryId,
  });
  return {
    status: 'ok',
    message: "C'est noté ! Confirmez votre inscription grâce au lien reçu par email. Désinscription en un clic.",
  };
}

// ─── Message à un établissement ────────────────────────────────────────────
export async function sendContactMessage(_prev: FormState, form: FormData): Promise<FormState> {
  if (form.get('website')) return { status: 'ok', message: 'Message envoyé !' };
  const parsed = z
    .object({
      establishmentId: z.string().uuid(),
      name: z.string().trim().min(2, 'Indiquez votre nom').max(120),
      email: email.optional().or(z.literal('')),
      phone: z.string().trim().max(32).optional(),
      body: z.string().trim().min(5, 'Votre message est un peu court').max(3000),
      consent: z.literal('on', { message: 'Merci d’accepter la transmission de votre message.' }),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  if (!d.email && !d.phone) return { status: 'error', message: 'Laissez un email ou un téléphone pour être recontacté·e.' };
  if (!(await throttle('contact', 6, 3600))) return { status: 'error', message: 'Trop de messages envoyés, réessayez plus tard.' };
  const [est] = await db.select().from(establishments).where(eq(establishments.id, d.establishmentId)).limit(1);
  if (!est || !['PRECREATED', 'TO_COMPLETE', 'CLAIMED', 'VALIDATED'].includes(est.status)) return { status: 'error', message: 'Établissement introuvable.' };
  const info = await requestInfo();
  await db.insert(messages).values({
    establishmentId: est.id,
    territoryId: est.territoryId,
    senderName: d.name,
    senderEmail: d.email || null,
    senderPhone: d.phone || null,
    body: d.body,
    ipHash: ipHash(info.ip),
  });
  const recipients = new Set([...(await ownersEmails(est.companyId)), ...(est.email ? [est.email] : [])]);
  for (const to of recipients) {
    await sendEmail({
      ...contactMessageTemplate({
        to,
        establishmentName: est.name,
        senderName: d.name,
        senderEmail: d.email || null,
        senderPhone: d.phone || null,
        body: d.body,
        url: appUrl(`/pro/${est.id}/messages`),
      }),
      territoryId: est.territoryId,
    });
  }
  await track({
    type: 'CONTACT_SENT',
    territoryId: est.territoryId,
    establishmentId: est.id,
    communeId: est.communeId,
    userAgent: info.userAgent,
    ip: info.ip,
  });
  return {
    status: 'ok',
    message: recipients.size
      ? `Message envoyé à ${est.name}. Réponse directement par email ou téléphone.`
      : `Message enregistré. ${est.name} le recevra dès qu'il aura activé sa fiche.`,
  };
}

// ─── Candidature à une offre d'emploi ──────────────────────────────────────
export async function applyToJob(_prev: FormState, form: FormData): Promise<FormState> {
  if (form.get('website')) return { status: 'ok' };
  const parsed = z
    .object({
      jobId: z.string().uuid(),
      fullName: z.string().trim().min(3, 'Indiquez vos prénom et nom').max(160),
      email,
      phone: z.string().trim().max(32).optional(),
      message: z.string().trim().max(3000).optional(),
      consent: z.literal('on', { message: 'Merci d’accepter la transmission de votre candidature.' }),
    })
    .safeParse(Object.fromEntries([...form.entries()].filter(([, v]) => typeof v === 'string')));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  if (!(await throttle('apply', 8, 3600))) return { status: 'error', message: 'Trop de candidatures envoyées, réessayez plus tard.' };
  const d = parsed.data;
  const [job] = await db
    .select({ job: jobs, est: establishments })
    .from(jobs)
    .innerJoin(establishments, eq(establishments.id, jobs.establishmentId))
    .where(and(eq(jobs.id, d.jobId), eq(jobs.status, 'PUBLISHED')))
    .limit(1);
  if (!job) return { status: 'error', message: "Cette offre n'est plus disponible." };
  let cvMediaId: string | null = null;
  const cv = form.get('cv');
  if (cv instanceof File && cv.size > 0) {
    try {
      const m = await saveDocumentUpload(cv, { ownerType: 'JOB_APPLICATION', territoryId: job.est.territoryId, establishmentId: job.est.id });
      cvMediaId = m.id;
    } catch (err) {
      return { status: 'error', message: err instanceof MediaError ? err.message : 'Le CV n’a pas pu être enregistré.' };
    }
  }
  await db.insert(jobApplications).values({
    jobId: job.job.id,
    establishmentId: job.est.id,
    fullName: d.fullName,
    email: d.email,
    phone: d.phone || null,
    message: d.message || null,
    cvMediaId,
  });
  const recipients = new Set([...(await ownersEmails(job.est.companyId)), ...(job.job.applyEmail ? [job.job.applyEmail] : [])]);
  for (const to of recipients) {
    await sendEmail({
      ...jobApplicationTemplate({ to, jobTitle: job.job.title, candidate: d.fullName, url: appUrl(`/pro/${job.est.id}/emploi`) }),
      territoryId: job.est.territoryId,
    });
  }
  await sendEmail({ ...applicationAckTemplate({ to: d.email, jobTitle: job.job.title, companyName: job.est.name }), territoryId: job.est.territoryId });
  const info = await requestInfo();
  await track({ type: 'JOB_APPLY', territoryId: job.est.territoryId, establishmentId: job.est.id, refId: job.job.id, userAgent: info.userAgent, ip: info.ip });
  return { status: 'ok', message: `${job.est.name} a bien reçu votre candidature. Réponse en moyenne sous 5 jours.` };
}

// ─── Demande de rendez-vous (offre Premium) ────────────────────────────────
export async function requestAppointment(_prev: FormState, form: FormData): Promise<FormState> {
  if (form.get('website')) return { status: 'ok' };
  const parsed = z
    .object({
      establishmentId: z.string().uuid(),
      fullName: z.string().trim().min(3, 'Indiquez votre nom').max(160),
      email,
      phone: z.string().trim().max(32).optional(),
      service: z.string().trim().max(200).optional(),
      date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Choisissez une date'),
      time: z.string().regex(/^\d{2}:\d{2}$/, 'Choisissez une heure'),
      message: z.string().trim().max(2000).optional(),
      consent: z.literal('on', { message: 'Merci d’accepter la transmission de votre demande.' }),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  if (!(await throttle('rdv', 6, 3600))) return { status: 'error', message: 'Trop de demandes, réessayez plus tard.' };
  const d = parsed.data;
  const [est] = await db.select().from(establishments).where(eq(establishments.id, d.establishmentId)).limit(1);
  if (!est || !est.appointmentsEnabled || !PUBLIC_STATUSES.includes(est.status))
    return { status: 'error', message: 'La prise de rendez-vous n’est pas proposée ici.' };
  // Contrôles côté serveur : module activé par le territoire et offre de l'entreprise.
  const [{ getEnabledModules }, { planLimits }] = await Promise.all([import('@/server/services/territories'), import('@/server/services/billing')]);
  const [co] = await db.select({ plan: companies.plan }).from(companies).where(eq(companies.id, est.companyId)).limit(1);
  if (!(await getEnabledModules(est.territoryId)).has('APPOINTMENTS') || !co || !(await planLimits(co.plan)).appointments)
    return { status: 'error', message: 'La prise de rendez-vous n’est pas proposée ici.' };
  const preferredAt = fromParisLocal(d.date, d.time);
  if (preferredAt.getTime() < Date.now()) return { status: 'error', message: 'Choisissez un créneau à venir.' };
  await db.insert(appointments).values({
    establishmentId: est.id,
    fullName: d.fullName,
    email: d.email,
    phone: d.phone || null,
    service: d.service || null,
    preferredAt,
    message: d.message || null,
  });
  const when = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'full', timeStyle: 'short', timeZone: 'Europe/Paris' }).format(preferredAt);
  for (const to of await ownersEmails(est.companyId)) {
    await sendEmail({
      ...appointmentRequestTemplate({ to, establishmentName: est.name, client: d.fullName, when, url: appUrl(`/pro/${est.id}/rendez-vous`) }),
      territoryId: est.territoryId,
    });
  }
  const info = await requestInfo();
  await track({ type: 'APPOINTMENT_REQUEST', territoryId: est.territoryId, establishmentId: est.id, userAgent: info.userAgent, ip: info.ip });
  return { status: 'ok', message: `Demande envoyée à ${est.name} pour le ${when}. Vous recevrez une confirmation par email.` };
}

// ─── Passeport de circuit ──────────────────────────────────────────────────

/** Ouvre (ou retrouve) le passeport anonyme du visiteur pour un circuit. */
export async function startPassport(circuitId: string): Promise<{ ok: boolean }> {
  if (!z.string().uuid().safeParse(circuitId).success) return { ok: false };
  const [c] = await db.select({ id: circuits.id }).from(circuits).where(eq(circuits.id, circuitId)).limit(1);
  if (!c) return { ok: false };
  await ensureVisitorPassport(circuitId);
  return { ok: true };
}

/**
 * Mode démonstration uniquement : simule le scan du QR code d'une étape.
 * En production, une étape ne se tamponne qu'en scannant le QR code apposé en vitrine.
 */
export async function demoToggleStamp(stopId: string): Promise<{ ok: boolean }> {
  if (!env.DEMO_MODE || !z.string().uuid().safeParse(stopId).success) return { ok: false };
  const [stop] = await db.select({ circuitId: circuitStops.circuitId }).from(circuitStops).where(eq(circuitStops.id, stopId)).limit(1);
  if (!stop) return { ok: false };
  const passportId = await ensureVisitorPassport(stop.circuitId);
  await stampPassport(passportId, stopId, { toggle: true });
  return { ok: true };
}
