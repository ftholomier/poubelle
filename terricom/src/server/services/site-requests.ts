import { and, desc, eq, ne } from 'drizzle-orm';
import { TERRITORY_KINDS, type TerritoryKind } from '@/lib/constants';
import { audit } from '@/server/audit';
import { rateLimit } from '@/server/auth/rate-limit';
import { sha256 } from '@/server/crypto';
import { db } from '@/server/db';
import { dealContacts, deals } from '@/server/db/schema';
import { env } from '@/server/env';
import { renderEmail } from '@/server/mail/layout';
import { sendEmail } from '@/server/mail/send';
import { contactAckTemplate, demoRequestAckTemplate } from '@/server/mail/templates';
import { addDealTask, logDealActivity, STAGE_PROBABILITY } from '@/server/services/crm';
import { appUrl } from '@/server/urls';

/**
 * Demandes arrivant des sites de terricom (le site de l'application et le site commercial statique terricom.fr) :
 * démonstrations et messages de contact. Chaque demande d'une collectivité rejoint le suivi commercial de la
 * console ; l'équipe est prévenue par courriel et l'internaute reçoit un accusé de réception.
 */

export type DemoRequest = {
  firstName: string;
  lastName: string;
  role?: string;
  organization: string;
  kind: TerritoryKind;
  communes?: number;
  email: string;
  phone?: string;
  message?: string;
};

export type ContactRequest = {
  name: string;
  email: string;
  organization?: string;
  subject: string;
  message: string;
  /** Vrai si le message vient d'une collectivité : il rejoint alors le suivi commercial. */
  lead: boolean;
};

export type RequestResult = { ok: true; dealId: string | null } | { ok: false; message: string };

const LIMITED = 'Votre demande a déjà été enregistrée : nous revenons vers vous très vite.';

async function limited(kind: string, ip: string | null, email: string): Promise<boolean> {
  const who = sha256(`${ip ?? 'inconnu'}:${env.ANALYTICS_SALT}`).slice(0, 24);
  return !(await rateLimit(`${kind}:${who}`, 3, 3600)).ok || !(await rateLimit(`${kind}-mail:${email}`, 2, 86_400)).ok;
}

const fullName = (first: string, last: string) => `${first} ${last}`.trim();

/**
 * Affaire de la personne : celle qui est encore ouverte pour la même adresse, sinon une nouvelle affaire au stade
 * « prospect ». Évite les doublons quand une même personne écrit plusieurs fois.
 */
async function upsertDeal(p: {
  organization: string;
  kind: TerritoryKind;
  communes?: number;
  email: string;
  phone?: string;
  contactName: string;
  role?: string;
  source: string;
  notes?: string;
}): Promise<{ id: string; created: boolean }> {
  const now = new Date();
  const [open] = await db
    .select({ id: deals.id })
    .from(deals)
    .where(and(eq(deals.contactEmail, p.email), ne(deals.stage, 'LOST')))
    .orderBy(desc(deals.createdAt))
    .limit(1);
  if (open) {
    const [known] = await db
      .select({ id: dealContacts.id })
      .from(dealContacts)
      .where(and(eq(dealContacts.dealId, open.id), eq(dealContacts.email, p.email)))
      .limit(1);
    if (!known)
      await db
        .insert(dealContacts)
        .values({ dealId: open.id, name: p.contactName, role: p.role ?? null, tag: 'Décideur', email: p.email, phone: p.phone ?? null });
    return { id: open.id, created: false };
  }
  const [deal] = await db
    .insert(deals)
    .values({
      name: p.organization,
      kind: p.kind,
      communesCount: p.communes ?? 1,
      stage: 'PROSPECT',
      probability: STAGE_PROBABILITY.PROSPECT,
      source: p.source,
      notes: p.notes ?? null,
      contactEmail: p.email,
      contactPhone: p.phone ?? null,
      lastInteractionAt: now,
    })
    .returning({ id: deals.id });
  await db.insert(dealContacts).values({ dealId: deal.id, name: p.contactName, role: p.role ?? null, tag: 'Décideur', email: p.email, phone: p.phone ?? null });
  return { id: deal.id, created: true };
}

/** Demande de démonstration : affaire dans le suivi commercial, tâches de rappel, courriels. */
export async function recordDemoRequest(d: DemoRequest, opts: { source: string; ip: string | null }): Promise<RequestResult> {
  if (await limited('demo', opts.ip, d.email)) return { ok: false, message: LIMITED };
  const name = fullName(d.firstName, d.lastName);
  const deal = await upsertDeal({
    organization: d.organization,
    kind: d.kind,
    communes: d.communes,
    email: d.email,
    phone: d.phone,
    contactName: name,
    role: d.role,
    source: opts.source,
    notes: d.message,
  });
  const quote = d.message ? ` : « ${d.message.slice(0, 180)} »` : '';
  await logDealActivity(deal.id, 'Premier contact', `Demande de démo reçue (${opts.source})${quote}`, null);
  await addDealTask(deal.id, 'Rappeler pour caler la démo', 'sous 48 h');
  if (deal.created) await addDealTask(deal.id, 'Préparer la démo avec les entreprises SIRENE du territoire', 'avant la démo');

  await sendEmail(demoRequestAckTemplate({ to: d.email, name: d.firstName || name }));
  const { html, text } = renderEmail({
    eyebrow: deal.created ? 'Nouvelle demande de démo' : 'Nouvelle demande de démo (affaire existante)',
    title: `${d.organization} (${TERRITORY_KINDS[d.kind].label})`,
    paragraphs: [
      `${name}${d.role ? `, ${d.role}` : ''} · ${d.email}${d.phone ? ` · ${d.phone}` : ''}`,
      d.communes ? `${d.communes} commune(s)` : '',
      d.message ?? '',
      `Source : ${opts.source}`,
    ].filter(Boolean),
    cta: { label: 'Ouvrir le suivi commercial', url: appUrl(`/console?crm=pro&deal=${deal.id}`) },
  });
  await sendEmail({
    to: env.SALES_EMAIL,
    subject: `Demande de démo : ${d.organization}`,
    html,
    text,
    template: 'demo-request',
    headers: { 'Reply-To': d.email },
  });
  await audit({
    actor: 'Système',
    category: 'MODIFICATION',
    action: 'crm.demo_request',
    summary: `Demande de démo reçue : ${d.organization}`,
    targetType: 'deal',
    targetId: deal.id,
  });
  return { ok: true, dealId: deal.id };
}

/** Message de contact : courriel à l'équipe (réponse directe à l'expéditeur), accusé de réception, suivi commercial. */
export async function recordContactMessage(c: ContactRequest, opts: { source: string; ip: string | null }): Promise<RequestResult> {
  if (await limited('contact', opts.ip, c.email)) return { ok: false, message: LIMITED };
  let dealId: string | null = null;
  if (c.lead && c.organization) {
    const deal = await upsertDeal({
      organization: c.organization,
      kind: 'AUTRE',
      email: c.email,
      contactName: c.name,
      source: opts.source,
      notes: c.message,
    });
    dealId = deal.id;
    await logDealActivity(deal.id, 'Email', `${c.subject} (${opts.source}) : « ${c.message.slice(0, 180)} »`, null);
    await addDealTask(deal.id, 'Répondre au message reçu sur terricom.fr', 'sous 48 h');
  }
  await sendEmail(contactAckTemplate({ to: c.email, name: c.name }));
  const { html, text } = renderEmail({
    eyebrow: 'Message reçu sur terricom.fr',
    title: c.subject,
    paragraphs: [`${c.name}${c.organization ? ` (${c.organization})` : ''} · ${c.email}`, c.message, `Source : ${opts.source}`],
    cta: dealId ? { label: 'Ouvrir le suivi commercial', url: appUrl(`/console?crm=pro&deal=${dealId}`) } : undefined,
  });
  await sendEmail({
    to: env.SALES_EMAIL,
    subject: `${c.subject} : ${c.organization ?? c.name}`,
    html,
    text,
    template: 'contact-message',
    headers: { 'Reply-To': c.email },
  });
  await audit({
    actor: 'Système',
    category: 'MODIFICATION',
    action: 'crm.contact_message',
    summary: `Message reçu : ${c.subject} (${c.organization ?? c.name})`,
    targetType: dealId ? 'deal' : undefined,
    targetId: dealId ?? undefined,
  });
  return { ok: true, dealId };
}
