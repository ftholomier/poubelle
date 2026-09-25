'use server';

import { and, desc, eq } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { z } from 'zod';
import type { ActionState } from '../actions';
import { actorOf, proCtx } from '../helpers';
import { audit } from '@/server/audit';
import { db } from '@/server/db';
import { companyContacts, posts, type NewsletterBlock } from '@/server/db/schema';
import { addContact, CUSTOMER_LETTERS_PER_30_DAYS, contactStats, lettersLast30Days, sendCustomerLetter } from '@/server/services/customers';
import type { ProContext } from '@/server/services/pro';

/** Clients de l'entreprise : réservé à l'entreprise (pas aux agents de la collectivité) et à l'offre Communication. */
async function clientsCtx(estId: unknown): Promise<ProContext | string> {
  const ctx = await proCtx(estId);
  if (ctx.role === 'STAFF') return 'Les clients d’une entreprise ne sont accessibles qu’à l’entreprise elle-même.';
  if (!ctx.limits.customerNewsletter) return 'La newsletter clients est incluse dans l’offre Communication.';
  return ctx;
}

export async function sendLetterAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await clientsCtx(form.get('estId'));
  if (typeof ctx === 'string') return { status: 'error', message: ctx };
  const parsed = z
    .object({
      subject: z.string().trim().min(3, 'Indiquez un objet').max(150),
      title: z.string().trim().min(3, 'Indiquez un titre').max(150),
      message: z.string().trim().min(10, 'Votre message est un peu court').max(5000),
      ctaLabel: z.string().trim().max(40).optional(),
      ctaUrl: z
        .string()
        .trim()
        .max(500)
        .regex(/^(https:\/\/\S+)?$/, 'Le lien du bouton doit commencer par https://')
        .optional(),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  if (d.ctaLabel && !d.ctaUrl) return { status: 'error', message: 'Indiquez le lien du bouton.' };
  const used = await lettersLast30Days(ctx.est.companyId);
  if (used >= CUSTOMER_LETTERS_PER_30_DAYS)
    return { status: 'error', message: `Vous avez envoyé ${used} lettres ces 30 derniers jours : c’est le maximum, pour ne pas lasser vos clients.` };
  const stats = await contactStats(ctx.est.companyId);
  if (!stats.active) return { status: 'error', message: 'Aucun client abonné pour l’instant : partagez votre fiche pour en gagner.' };
  const blocks: NewsletterBlock[] = [{ type: 'text', text: d.message }];
  if (form.get('withPosts') === 'on') {
    const last = await db
      .select({ id: posts.id })
      .from(posts)
      .where(and(eq(posts.establishmentId, ctx.est.id), eq(posts.status, 'PUBLISHED')))
      .orderBy(desc(posts.publishedAt))
      .limit(3);
    if (last.length) blocks.push({ type: 'posts', ids: last.map((p) => p.id), title: 'Nos dernières nouvelles' });
  }
  if (d.ctaLabel && d.ctaUrl) blocks.push({ type: 'cta', label: d.ctaLabel, url: d.ctaUrl });
  const res = await sendCustomerLetter({
    territoryId: ctx.est.territoryId,
    companyId: ctx.est.companyId,
    establishmentId: ctx.est.id,
    subject: d.subject,
    title: d.title,
    intro: '',
    blocks,
    createdById: ctx.actor.user.id,
  });
  await audit({
    actor: actorOf(ctx),
    category: 'ENVOI',
    action: 'customer_letter.send',
    summary: `Lettre « ${d.subject} » de ${ctx.est.name} envoyée à ${res.recipients} client${res.recipients > 1 ? 's' : ''}`,
    territoryId: ctx.est.territoryId,
    targetType: 'newsletter',
    targetId: res.id,
  });
  revalidatePath(`${ctx.base}/clients`);
  return { status: 'ok', message: `Lettre en cours d’envoi à ${res.recipients} client${res.recipients > 1 ? 's' : ''}.` };
}

export async function addContactAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await clientsCtx(form.get('estId'));
  if (typeof ctx === 'string') return { status: 'error', message: ctx };
  const parsed = z
    .object({
      email: z.string().trim().toLowerCase().email('Adresse email invalide').max(254),
      fullName: z.string().trim().max(120).optional(),
      attest: z.literal('on', { message: 'Cochez l’attestation : le client doit avoir donné son accord.' }),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  const [existing] = await db
    .select({ id: companyContacts.id, subscribed: companyContacts.subscribed })
    .from(companyContacts)
    .where(and(eq(companyContacts.companyId, ctx.est.companyId), eq(companyContacts.email, d.email)))
    .limit(1);
  // Une personne désinscrite ne peut pas être réinscrite par le commerce : elle seule peut se réabonner.
  if (existing && !existing.subscribed)
    return { status: 'error', message: 'Cette personne s’est désinscrite : elle seule peut se réabonner depuis votre fiche.' };
  if (existing) return { status: 'error', message: 'Ce client est déjà dans votre liste.' };
  const who = `${ctx.actor.user.firstName} ${ctx.actor.user.lastName}`.trim() || ctx.actor.user.email;
  await addContact(ctx.est.companyId, d.email, d.fullName || null, who);
  await audit({
    actor: actorOf(ctx),
    category: 'RGPD',
    action: 'contact.add',
    summary: `Client ajouté à la liste de ${ctx.est.name} (accord attesté)`,
    territoryId: ctx.est.territoryId,
    targetType: 'establishment',
    targetId: ctx.est.id,
  });
  revalidatePath(`${ctx.base}/clients`);
  return { status: 'ok', message: 'Client ajouté.' };
}

export async function removeContactAction(form: FormData): Promise<void> {
  const ctx = await clientsCtx(form.get('estId'));
  if (typeof ctx === 'string') return;
  const id = z.string().uuid().parse(form.get('contactId'));
  const removed = await db
    .delete(companyContacts)
    .where(and(eq(companyContacts.id, id), eq(companyContacts.companyId, ctx.est.companyId)))
    .returning({ id: companyContacts.id });
  if (removed.length)
    await audit({
      actor: actorOf(ctx),
      category: 'RGPD',
      action: 'contact.delete',
      summary: `Client supprimé de la liste de ${ctx.est.name}`,
      territoryId: ctx.est.territoryId,
      targetType: 'establishment',
      targetId: ctx.est.id,
    });
  revalidatePath(`${ctx.base}/clients`);
}
