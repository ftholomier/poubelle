'use server';

import { and, eq } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { z } from 'zod';
import type { ActionState } from '@/app/pro/[est]/actions';
import { fullName } from '@/lib/format';
import { rateLimit } from '@/server/auth/rate-limit';
import { db } from '@/server/db';
import { supportTickets } from '@/server/db/schema';
import { env } from '@/server/env';
import { sendEmail } from '@/server/mail/send';
import { ticketCreatedTemplate } from '@/server/mail/templates';
import { loadBoContext } from '@/server/services/backoffice';
import { addTicketMessage, createTicket } from '@/server/services/support';
import { appUrl } from '@/server/urls';

export async function openTicketAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await loadBoContext();
  const d = z
    .object({
      subject: z.string().trim().min(4, 'Précisez l’objet de la demande').max(200),
      body: z.string().trim().min(10, 'Décrivez votre demande en quelques phrases').max(10_000),
      priority: z.enum(['LOW', 'NORMAL', 'HIGH', 'URGENT']),
    })
    .safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: d.error.issues[0]?.message };
  if (!(await rateLimit(`ticket:${ctx.actor.user.id}`, 10, 86_400)).ok)
    return { status: 'error', message: 'Trop de demandes aujourd’hui : répondez plutôt sur un ticket existant.' };
  const body = ctx.commune ? `[Commune de ${ctx.commune.name}]\n${d.data.body}` : d.data.body;
  const t = await createTicket({ territoryId: ctx.territory.id, subject: d.data.subject, body, priority: d.data.priority, user: ctx.actor.user });
  await sendEmail(
    ticketCreatedTemplate({
      to: env.SUPPORT_EMAIL,
      number: t.number,
      subject: t.subject,
      territory: ctx.territory.name,
      author: fullName(ctx.actor.user),
      body,
      url: appUrl(`/console/support?vue=ouverts&ticket=${t.id}`),
    }),
  );
  revalidatePath('/collectivite/support');
  redirect(`/collectivite/support?ticket=${t.id}`);
}

export async function replyOwnTicketAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const ctx = await loadBoContext();
  const d = z.object({ ticketId: z.string().uuid(), body: z.string().trim().min(2, 'Message vide').max(10_000) }).safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: d.error.issues[0]?.message };
  const [t] = await db
    .select()
    .from(supportTickets)
    .where(and(eq(supportTickets.id, d.data.ticketId), eq(supportTickets.territoryId, ctx.territory.id)))
    .limit(1);
  if (!t) return { status: 'error', message: 'Ticket introuvable.' };
  await addTicketMessage({ ticketId: t.id, user: ctx.actor.user, body: d.data.body, fromSupport: false });
  await db.update(supportTickets).set({ status: 'OPEN', updatedAt: new Date() }).where(eq(supportTickets.id, t.id));
  revalidatePath('/collectivite/support');
  return { status: 'ok', message: t.status === 'RESOLVED' ? 'Message envoyé : le ticket est rouvert.' : 'Message envoyé au support.' };
}
