'use server';

import { z } from 'zod';
import { TERRITORY_KINDS, type TerritoryKind } from '@/lib/constants';
import { audit } from '@/server/audit';
import { rateLimit } from '@/server/auth/rate-limit';
import { sha256 } from '@/server/crypto';
import { db } from '@/server/db';
import { dealContacts, deals } from '@/server/db/schema';
import { env } from '@/server/env';
import { renderEmail } from '@/server/mail/layout';
import { sendEmail } from '@/server/mail/send';
import { demoRequestAckTemplate } from '@/server/mail/templates';
import { requestInfo } from '@/server/request';
import { addDealTask, logDealActivity, STAGE_PROBABILITY } from '@/server/services/crm';
import { appUrl } from '@/server/urls';

export type DemoState = { status: 'idle' | 'ok' | 'error'; message?: string; name?: string };

const schema = z.object({
  firstName: z.string().trim().min(1, 'Votre prénom').max(80),
  lastName: z.string().trim().min(1, 'Votre nom').max(80),
  role: z.string().trim().max(160).optional(),
  organization: z.string().trim().min(2, 'Le nom de votre collectivité').max(200),
  kind: z.enum(['CC', 'CA', 'CU', 'METROPOLE', 'COMMUNE', 'PETR', 'OFFICE', 'AUTRE']),
  communes: z.coerce
    .number()
    .int()
    .min(1)
    .max(500)
    .optional()
    .or(z.literal('').transform(() => undefined)),
  email: z.string().trim().toLowerCase().email('Adresse email invalide'),
  phone: z.string().trim().max(32).optional(),
  message: z.string().trim().max(3000).optional(),
  consent: z.literal('on', { error: 'Merci d’accepter d’être recontacté·e' }),
  website: z.string().max(0).optional(),
});

/** Demande de démonstration depuis le site : crée une affaire dans le suivi commercial et accuse réception. */
export async function requestDemoAction(_prev: DemoState, form: FormData): Promise<DemoState> {
  if (String(form.get('website') ?? '')) return { status: 'ok', name: '' }; // piège à robots : on ne dit rien
  const parsed = schema.safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message ?? 'Formulaire incomplet.' };
  const d = parsed.data;
  const info = await requestInfo();
  const who = sha256(`${info.ip ?? 'inconnu'}:${env.ANALYTICS_SALT}`).slice(0, 24);
  if (!(await rateLimit(`demo:${who}`, 3, 3600)).ok || !(await rateLimit(`demo-mail:${d.email}`, 2, 86_400)).ok)
    return { status: 'error', message: 'Votre demande a déjà été enregistrée : nous revenons vers vous très vite.' };

  const now = new Date();
  const [deal] = await db
    .insert(deals)
    .values({
      name: d.organization,
      kind: d.kind,
      communesCount: d.communes ?? 1,
      stage: 'PROSPECT',
      probability: STAGE_PROBABILITY.PROSPECT,
      source: 'Formulaire terricom.fr',
      notes: d.message || null,
      contactEmail: d.email,
      contactPhone: d.phone || null,
      lastInteractionAt: now,
    })
    .returning();
  await db
    .insert(dealContacts)
    .values({ dealId: deal.id, name: `${d.firstName} ${d.lastName}`, role: d.role || null, tag: 'Décideur', email: d.email, phone: d.phone || null });
  await logDealActivity(deal.id, 'Premier contact', `Demande de démo reçue via terricom.fr${d.message ? ` : « ${d.message.slice(0, 180)} »` : ''}`, null, now);
  await addDealTask(deal.id, 'Rappeler pour caler la démo', 'sous 48 h');
  await addDealTask(deal.id, 'Préparer la démo avec les entreprises SIRENE du territoire', 'avant la démo');

  await sendEmail(demoRequestAckTemplate({ to: d.email, name: d.firstName }));
  const { html, text } = renderEmail({
    eyebrow: 'Nouvelle demande de démo',
    title: `${d.organization} (${TERRITORY_KINDS[d.kind as TerritoryKind].label})`,
    paragraphs: [
      `${d.firstName} ${d.lastName}${d.role ? `, ${d.role}` : ''} · ${d.email}${d.phone ? ` · ${d.phone}` : ''}`,
      d.communes ? `${d.communes} commune(s)` : '',
      d.message ?? '',
    ].filter(Boolean),
    cta: { label: 'Ouvrir le suivi commercial', url: appUrl(`/console?crm=pro&deal=${deal.id}`) },
  });
  await sendEmail({ to: env.SALES_EMAIL, subject: `Demande de démo : ${d.organization}`, html, text, template: 'demo-request' });
  await audit({
    actor: 'Système',
    category: 'MODIFICATION',
    action: 'crm.demo_request',
    summary: `Demande de démo reçue : ${d.organization}`,
    targetType: 'deal',
    targetId: deal.id,
  });
  return { status: 'ok', name: d.firstName };
}
