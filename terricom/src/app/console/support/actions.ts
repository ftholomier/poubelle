'use server';

import { eq } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { z } from 'zod';
import type { ActionState } from '@/app/pro/[est]/actions';
import { audit } from '@/server/audit';
import { requirePlatformStaff } from '@/server/authz';
import { db } from '@/server/db';
import { supportTickets, users } from '@/server/db/schema';
import { sendEmail } from '@/server/mail/send';
import { ticketReplyTemplate } from '@/server/mail/templates';
import { addTicketMessage } from '@/server/services/support';

export async function replyTicketAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await requirePlatformStaff();
  const d = z
    .object({
      ticketId: z.string().uuid(),
      body: z.string().trim().min(2, 'Réponse vide').max(10_000),
      internal: z.string().optional(),
      resolve: z.string().optional(),
    })
    .safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: d.error.issues[0]?.message };
  const [t] = await db.select().from(supportTickets).where(eq(supportTickets.id, d.data.ticketId)).limit(1);
  if (!t) return { status: 'error', message: 'Ticket introuvable.' };
  const internal = d.data.internal === 'on';
  const resolve = d.data.resolve === 'on' && !internal;
  await addTicketMessage({ ticketId: t.id, user: actor.user, body: d.data.body, fromSupport: true, internal });
  await db
    .update(supportTickets)
    .set({ status: resolve ? 'RESOLVED' : internal ? t.status : 'PENDING', assigneeId: t.assigneeId ?? actor.user.id, updatedAt: new Date() })
    .where(eq(supportTickets.id, t.id));
  if (!internal && t.createdById) {
    const [u] = await db.select({ email: users.email }).from(users).where(eq(users.id, t.createdById)).limit(1);
    if (u)
      await sendEmail({
        ...ticketReplyTemplate({ to: u.email, number: t.number, subject: t.subject, reply: d.data.body, resolved: resolve }),
        territoryId: t.territoryId,
      });
  }
  if (resolve)
    await audit({
      actor: { user: actor.user },
      category: 'SUPPORT',
      action: 'ticket.resolved',
      summary: `Ticket n°${t.number} résolu : ${t.subject}`,
      territoryId: t.territoryId,
      targetType: 'ticket',
      targetId: t.id,
    });
  revalidatePath('/console/support');
  return { status: 'ok', message: internal ? 'Note interne ajoutée.' : resolve ? 'Réponse envoyée, ticket résolu.' : 'Réponse envoyée à la collectivité.' };
}

export async function updateTicketAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await requirePlatformStaff();
  const d = z
    .object({
      ticketId: z.string().uuid(),
      status: z.enum(['OPEN', 'PENDING', 'RESOLVED']),
      priority: z.enum(['LOW', 'NORMAL', 'HIGH', 'URGENT']),
      assign: z.string().optional(),
    })
    .safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: 'Valeurs invalides.' };
  const [t] = await db.select().from(supportTickets).where(eq(supportTickets.id, d.data.ticketId)).limit(1);
  if (!t) return { status: 'error', message: 'Ticket introuvable.' };
  await db
    .update(supportTickets)
    .set({
      status: d.data.status,
      priority: d.data.priority,
      assigneeId: d.data.assign === 'me' ? actor.user.id : d.data.assign === 'none' ? null : t.assigneeId,
      updatedAt: new Date(),
    })
    .where(eq(supportTickets.id, t.id));
  revalidatePath('/console/support');
  revalidatePath('/console');
  return { status: 'ok', message: 'Ticket mis à jour.' };
}
