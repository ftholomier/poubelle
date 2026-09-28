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
  establishmentForms,
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
import { notifyCompany } from '@/server/push';
import { requestFollow } from '@/server/services/customers';
import { getTerritoryCommunes } from '@/server/services/territories';
import {
  applicationAckTemplate,
  appointmentRequestTemplate,
  contactMessageTemplate,
  followConfirmTemplate,
  jobApplicationTemplate,
  newsletterConfirmTemplate,
} from '@/server/mail/templates';
import { MediaError, saveDocumentUpload } from '@/server/media';
import { requestInfo } from '@/server/request';
import { INTL, translator, type Translate } from '@/lib/i18n';
import { territoryText } from '@/lib/i18n/territory';
import { getPortalLocale } from '@/server/i18n';
import { appUrl, portalUrl } from '@/server/urls';

export type FormState = { status: 'idle' | 'ok' | 'error'; message?: string; fieldErrors?: Record<string, string> };
export type SubscribeState = FormState;

/** Langue du visiteur pour les messages renvoyés (paramètre ?lang= ou préférence mémorisée). */
async function actionT(): Promise<Translate> {
  return translator(await getPortalLocale(true));
}

const emailOf = (t: Translate) => z.string().trim().toLowerCase().email(t('act.emailInvalid')).max(254);

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

/** Établissement public et offre de son entreprise (contrôle des fonctions payantes côté serveur). */
async function publicEstablishment(id: string) {
  const [row] = await db
    .select({ est: establishments, plan: companies.plan })
    .from(establishments)
    .innerJoin(companies, eq(companies.id, establishments.companyId))
    .where(eq(establishments.id, id))
    .limit(1);
  if (!row || !PUBLIC_STATUSES.includes(row.est.status)) return null;
  const { planLimits } = await import('@/server/services/billing');
  return { ...row, limits: await planLimits(row.plan) };
}

// ─── Newsletter : inscription en double opt-in ─────────────────────────────
export async function subscribeNewsletter(_prev: SubscribeState, form: FormData): Promise<SubscribeState> {
  const tr = await actionT();
  if (form.get('website')) return { status: 'ok', message: tr('act.noted') }; // pot de miel anti-robots
  const parsed = z
    .object({ territoryId: z.string().uuid(), email: emailOf(tr), consent: z.literal('on', { message: tr('act.consent') }) })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message ?? tr('act.invalidForm') };
  if (!(await throttle('nl-subscribe', 5, 3600))) return { status: 'error', message: tr('act.tooManyHour') };
  const { territoryId } = parsed.data;
  const [t] = await db.select().from(territories).where(eq(territories.id, territoryId)).limit(1);
  if (!t) return { status: 'error', message: tr('act.unknownTerritory') };
  // Commune facultative, retenue seulement si elle fait partie du territoire (lettres par zone).
  const rawCommune = String(form.get('communeId') ?? '');
  const communeId = /^[0-9a-f-]{36}$/.test(rawCommune) && (await getTerritoryCommunes(territoryId)).some((c) => c.id === rawCommune) ? rawCommune : null;
  const newsletterName = territoryText(t, 'newsletterName', tr.locale) ?? tr('home.newsletterDefault', { name: t.name });
  // Preuve du consentement : le texte exact affiché au visiteur, dans sa langue.
  const consentText = tr('home.newsletterConsent', { name: t.name });
  const info = await requestInfo();
  const token = randomToken(24);
  const [existing] = await db
    .select()
    .from(subscribers)
    .where(and(eq(subscribers.territoryId, territoryId), eq(subscribers.email, parsed.data.email)))
    .limit(1);
  if (existing?.status === 'CONFIRMED') {
    return { status: 'ok', message: tr('act.nlAlready') };
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
      url: portalUrl(t, `/newsletter/confirmer?token=${token}${tr.locale === 'fr' ? '' : `&lang=${tr.locale}`}`),
      lang: tr.locale,
    }),
    territoryId,
  });
  return {
    status: 'ok',
    message: tr('act.nlConfirm'),
  };
}

// ─── Message à un établissement ────────────────────────────────────────────
export async function sendContactMessage(_prev: FormState, form: FormData): Promise<FormState> {
  const tr = await actionT();
  if (form.get('website')) return { status: 'ok', message: tr('act.msgSent') };
  const parsed = z
    .object({
      establishmentId: z.string().uuid(),
      name: z.string().trim().min(2, tr('act.nameRequired')).max(120),
      email: emailOf(tr).optional().or(z.literal('')),
      phone: z.string().trim().max(32).optional(),
      body: z.string().trim().min(5, tr('act.msgShort')).max(3000),
      consent: z.literal('on', { message: tr('act.msgConsent') }),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  if (!d.email && !d.phone) return { status: 'error', message: tr('act.contactNeeded') };
  if (!(await throttle('contact', 6, 3600))) return { status: 'error', message: tr('act.tooManyMessages') };
  const [est] = await db.select().from(establishments).where(eq(establishments.id, d.establishmentId)).limit(1);
  if (!est || !['PRECREATED', 'TO_COMPLETE', 'CLAIMED', 'VALIDATED'].includes(est.status)) return { status: 'error', message: tr('act.estNotFound') };
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
  await notifyCompany(est.companyId, {
    title: `Nouveau message pour ${est.name}`,
    body: `${d.name} : ${d.body.slice(0, 120)}`,
    url: `/pro/${est.id}/messages`,
    tag: `messages-${est.id}`,
  });
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
    message: recipients.size ? tr('act.msgDelivered', { name: est.name }) : tr('act.msgStored', { name: est.name }),
  };
}

// ─── Candidature à une offre d'emploi ──────────────────────────────────────
export async function applyToJob(_prev: FormState, form: FormData): Promise<FormState> {
  const tr = await actionT();
  if (form.get('website')) return { status: 'ok' };
  const parsed = z
    .object({
      jobId: z.string().uuid(),
      fullName: z.string().trim().min(3, tr('act.fullNameRequired')).max(160),
      email: emailOf(tr),
      phone: z.string().trim().max(32).optional(),
      message: z.string().trim().max(3000).optional(),
      consent: z.literal('on', { message: tr('act.applyConsent') }),
    })
    .safeParse(Object.fromEntries([...form.entries()].filter(([, v]) => typeof v === 'string')));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  if (!(await throttle('apply', 8, 3600))) return { status: 'error', message: tr('act.tooManyApplications') };
  const d = parsed.data;
  const [job] = await db
    .select({ job: jobs, est: establishments })
    .from(jobs)
    .innerJoin(establishments, eq(establishments.id, jobs.establishmentId))
    .where(and(eq(jobs.id, d.jobId), eq(jobs.status, 'PUBLISHED')))
    .limit(1);
  if (!job) return { status: 'error', message: tr('act.jobGone') };
  let cvMediaId: string | null = null;
  const cv = form.get('cv');
  if (cv instanceof File && cv.size > 0) {
    try {
      const m = await saveDocumentUpload(cv, { ownerType: 'JOB_APPLICATION', territoryId: job.est.territoryId, establishmentId: job.est.id });
      cvMediaId = m.id;
    } catch (err) {
      return { status: 'error', message: err instanceof MediaError ? err.message : tr('act.cvFailed') };
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
  await notifyCompany(job.est.companyId, {
    title: `Nouvelle candidature : ${job.job.title}`,
    body: `${d.fullName} a postulé.`,
    url: `/pro/${job.est.id}/emploi`,
    tag: `emploi-${job.est.id}`,
  });
  const info = await requestInfo();
  await track({ type: 'JOB_APPLY', territoryId: job.est.territoryId, establishmentId: job.est.id, refId: job.job.id, userAgent: info.userAgent, ip: info.ip });
  return { status: 'ok', message: tr('act.applied', { name: job.est.name }) };
}

// ─── Demande de rendez-vous (offre Premium) ────────────────────────────────
export async function requestAppointment(_prev: FormState, form: FormData): Promise<FormState> {
  const tr = await actionT();
  if (form.get('website')) return { status: 'ok' };
  const parsed = z
    .object({
      establishmentId: z.string().uuid(),
      fullName: z.string().trim().min(3, tr('act.nameRequired')).max(160),
      email: emailOf(tr),
      phone: z.string().trim().max(32).optional(),
      service: z.string().trim().max(200).optional(),
      date: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, tr('act.chooseDate')),
      time: z.string().regex(/^\d{2}:\d{2}$/, tr('act.chooseTime')),
      message: z.string().trim().max(2000).optional(),
      consent: z.literal('on', { message: tr('act.requestConsent') }),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  if (!(await throttle('rdv', 6, 3600))) return { status: 'error', message: tr('act.tooManyRequests') };
  const d = parsed.data;
  const [est] = await db.select().from(establishments).where(eq(establishments.id, d.establishmentId)).limit(1);
  if (!est || !est.appointmentsEnabled || !PUBLIC_STATUSES.includes(est.status)) return { status: 'error', message: tr('act.noBooking') };
  // Contrôles côté serveur : module activé par le territoire et offre de l'entreprise.
  const [{ getEnabledModules }, { planLimits }] = await Promise.all([import('@/server/services/territories'), import('@/server/services/billing')]);
  const [co] = await db.select({ plan: companies.plan }).from(companies).where(eq(companies.id, est.companyId)).limit(1);
  if (!(await getEnabledModules(est.territoryId)).has('APPOINTMENTS') || !co || !(await planLimits(co.plan)).appointments)
    return { status: 'error', message: tr('act.noBooking') };
  const preferredAt = fromParisLocal(d.date, d.time);
  if (preferredAt.getTime() < Date.now()) return { status: 'error', message: tr('act.futureSlot') };
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
  await notifyCompany(est.companyId, {
    title: 'Nouvelle demande de rendez-vous',
    body: `${d.fullName}, ${when}${d.service ? ` · ${d.service}` : ''}`,
    url: `/pro/${est.id}/rendez-vous`,
    tag: `rdv-${est.id}`,
  });
  const info = await requestInfo();
  await track({ type: 'APPOINTMENT_REQUEST', territoryId: est.territoryId, establishmentId: est.id, userAgent: info.userAgent, ip: info.ip });
  const whenLocal =
    tr.locale === 'fr'
      ? when
      : new Intl.DateTimeFormat(INTL[tr.locale], { dateStyle: 'full', timeStyle: 'short', timeZone: 'Europe/Paris' }).format(preferredAt);
  return { status: 'ok', message: tr('act.bookingSent', { name: est.name, when: whenLocal }) };
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

// ─── Suivre un commerce (newsletter client, offre Communication) ───────────
export async function followEstablishment(_prev: FormState, form: FormData): Promise<FormState> {
  const tr = await actionT();
  if (form.get('website')) return { status: 'ok', message: tr('act.noted') };
  const parsed = z
    .object({
      establishmentId: z.string().uuid(),
      email: emailOf(tr),
      fullName: z.string().trim().max(120).optional(),
      consent: z.literal('on', { message: tr('act.consent') }),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message ?? tr('act.invalidForm') };
  if (!(await throttle('follow', 6, 3600))) return { status: 'error', message: tr('act.tooManyHour') };
  const d = parsed.data;
  const row = await publicEstablishment(d.establishmentId);
  if (!row || !row.limits.customerNewsletter) return { status: 'error', message: tr('act.noLetter') };
  const [t] = await db.select().from(territories).where(eq(territories.id, row.est.territoryId)).limit(1);
  if (!t) return { status: 'error', message: tr('act.unknownTerritory') };
  const consentText = tr('fx.followConsent', { name: row.est.name });
  const res = await requestFollow({
    companyId: row.est.companyId,
    establishmentId: row.est.id,
    email: d.email,
    fullName: d.fullName || null,
    consentText,
  });
  if (res.already) return { status: 'ok', message: tr('act.alreadyFollowing', { name: row.est.name }) };
  await sendEmail({
    ...followConfirmTemplate({
      to: d.email,
      territory: t,
      establishmentName: row.est.name,
      url: portalUrl(t, `/suivre/confirmer?token=${res.token}${tr.locale === 'fr' ? '' : `&lang=${tr.locale}`}`),
      lang: tr.locale,
    }),
    territoryId: t.id,
  });
  return { status: 'ok', message: tr('act.followConfirm') };
}

// ─── Formulaires personnalisés (offre Premium) ─────────────────────────────
export async function submitCustomForm(_prev: FormState, form: FormData): Promise<FormState> {
  const tr = await actionT();
  if (form.get('website')) return { status: 'ok', message: tr('act.formSent') };
  const base = z
    .object({
      formId: z.string().uuid(),
      name: z.string().trim().min(2, tr('act.nameRequired')).max(120),
      email: emailOf(tr),
      phone: z.string().trim().max(32).optional(),
      consent: z.literal('on', { message: tr('act.requestConsent') }),
    })
    .safeParse({
      formId: form.get('formId'),
      name: form.get('name'),
      email: form.get('email'),
      phone: form.get('phone') ?? undefined,
      consent: form.get('consent') ?? undefined,
    });
  if (!base.success) return { status: 'error', message: base.error.issues[0]?.message };
  const d = base.data;
  const [f] = await db
    .select()
    .from(establishmentForms)
    .where(and(eq(establishmentForms.id, d.formId), eq(establishmentForms.isActive, true)))
    .limit(1);
  if (!f) return { status: 'error', message: tr('act.formGone') };
  const row = await publicEstablishment(f.establishmentId);
  if (!row || !row.limits.customForms) return { status: 'error', message: tr('act.formGone') };
  // Validation champ par champ d'après la définition enregistrée par le professionnel.
  const answers: { label: string; value: string }[] = [];
  for (const field of f.fields) {
    const raw = form.get(`f_${field.id}`);
    let value = typeof raw === 'string' ? raw.trim() : '';
    if (field.type === 'checkbox') value = raw === 'on' ? 'Oui' : '';
    if (!value) {
      if (field.required) return { status: 'error', message: tr('act.fieldRequired', { label: field.label }) };
      continue;
    }
    const max = field.type === 'textarea' ? 3000 : 300;
    if (value.length > max) return { status: 'error', message: tr('act.fieldTooLong', { label: field.label }) };
    if (field.type === 'email' && !z.string().email().safeParse(value).success)
      return { status: 'error', message: tr('act.fieldEmail', { label: field.label }) };
    if (field.type === 'number' && !/^-?\d+([.,]\d+)?$/.test(value)) return { status: 'error', message: tr('act.fieldNumber', { label: field.label }) };
    if (field.type === 'date' && !/^\d{4}-\d{2}-\d{2}$/.test(value)) return { status: 'error', message: tr('act.fieldDate', { label: field.label }) };
    if (field.type === 'select' && !(field.options ?? []).includes(value)) return { status: 'error', message: tr('act.fieldChoice', { label: field.label }) };
    answers.push({ label: field.label, value });
  }
  if (!(await throttle('custom-form', 6, 3600))) return { status: 'error', message: tr('act.tooManyRequests') };
  const est = row.est;
  const info = await requestInfo();
  const body = answers.map((a) => `${a.label} : ${a.value}`).join('\n') || '(aucune précision)';
  await db.insert(messages).values({
    establishmentId: est.id,
    territoryId: est.territoryId,
    source: 'FORM',
    senderName: d.name,
    senderEmail: d.email,
    senderPhone: d.phone || null,
    subject: f.title,
    body,
    formId: f.id,
    answers,
    ipHash: ipHash(info.ip),
  });
  const recipients = new Set([...(await ownersEmails(est.companyId)), ...(est.email ? [est.email] : [])]);
  for (const to of recipients) {
    const mail = contactMessageTemplate({
      to,
      establishmentName: est.name,
      senderName: d.name,
      senderEmail: d.email,
      senderPhone: d.phone || null,
      body,
      url: appUrl(`/pro/${est.id}/messages`),
    });
    await sendEmail({ ...mail, subject: `${f.title} : nouvelle demande de ${d.name}`, template: 'custom-form', territoryId: est.territoryId });
  }
  await notifyCompany(est.companyId, {
    title: `${f.title} : nouvelle demande`,
    body: `${d.name} a rempli le formulaire.`,
    url: `/pro/${est.id}/messages`,
    tag: `messages-${est.id}`,
  });
  await track({
    type: 'CONTACT_SENT',
    territoryId: est.territoryId,
    establishmentId: est.id,
    communeId: est.communeId,
    userAgent: info.userAgent,
    ip: info.ip,
  });
  return { status: 'ok', message: (tr.locale === 'fr' ? f.successText : null) || tr('act.formReceived', { name: est.name }) };
}
