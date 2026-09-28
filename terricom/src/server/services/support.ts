import { and, asc, desc, eq, inArray, sql, type SQL } from 'drizzle-orm';
import { db } from '../db';
import { supportTickets, territories, ticketMessages, users } from '../db/schema';
import { fullName } from '@/lib/format';

/** Support : tickets ouverts par les collectivités, fil d'échanges et suivi par l'équipe terricom. */

export type TicketStatus = 'OPEN' | 'PENDING' | 'RESOLVED';

export const TICKET_STATUS: Record<TicketStatus, { label: string; bg: string }> = {
  OPEN: { label: 'Ouvert', bg: '#F4B266' },
  PENDING: { label: 'En attente client', bg: '#CDE3F2' },
  RESOLVED: { label: 'Résolu', bg: '#D6E8B4' },
};

export const TICKET_PRIORITY: Record<string, { label: string; color: string }> = {
  LOW: { label: 'Basse', color: '#9A9F95' },
  NORMAL: { label: 'Normale', color: '#3D443F' },
  HIGH: { label: 'Haute', color: '#C8702A' },
  URGENT: { label: 'Urgente', color: '#D95C4E' },
};

export async function listTickets(f: { status?: TicketStatus[]; territoryId?: string }) {
  const conds: SQL[] = [];
  if (f.status?.length) conds.push(inArray(supportTickets.status, f.status));
  if (f.territoryId) conds.push(eq(supportTickets.territoryId, f.territoryId));
  return db
    .select({
      id: supportTickets.id,
      number: supportTickets.number,
      subject: supportTickets.subject,
      status: supportTickets.status,
      priority: supportTickets.priority,
      createdAt: supportTickets.createdAt,
      updatedAt: supportTickets.updatedAt,
      territoryId: supportTickets.territoryId,
      territoryName: territories.name,
      assigneeId: supportTickets.assigneeId,
      messages: sql<number>`(select count(*)::int from ticket_messages m where m.ticket_id = "support_tickets"."id" and not m.internal)`,
      lastAt: sql<Date>`coalesce((select max(m.created_at) from ticket_messages m where m.ticket_id = "support_tickets"."id"), "support_tickets"."created_at")`,
    })
    .from(supportTickets)
    .leftJoin(territories, eq(territories.id, supportTickets.territoryId))
    .where(conds.length ? and(...conds) : undefined)
    .orderBy(
      sql`case "support_tickets"."status" when 'OPEN' then 0 when 'PENDING' then 1 else 2 end`,
      sql`case "support_tickets"."priority" when 'URGENT' then 0 when 'HIGH' then 1 when 'NORMAL' then 2 else 3 end`,
      desc(supportTickets.createdAt),
    )
    .limit(200);
}

export async function ticketDetail(id: string) {
  const [t] = await db
    .select({ ticket: supportTickets, territoryName: territories.name, territorySlug: territories.slug })
    .from(supportTickets)
    .leftJoin(territories, eq(territories.id, supportTickets.territoryId))
    .where(eq(supportTickets.id, id))
    .limit(1);
  if (!t) return null;
  const ids = [t.ticket.createdById, t.ticket.assigneeId].filter((x): x is string => Boolean(x));
  const [messages, people] = await Promise.all([
    db.select().from(ticketMessages).where(eq(ticketMessages.ticketId, id)).orderBy(asc(ticketMessages.createdAt)),
    ids.length
      ? db.select({ id: users.id, firstName: users.firstName, lastName: users.lastName, email: users.email }).from(users).where(inArray(users.id, ids))
      : Promise.resolve([]),
  ]);
  const creator = people.find((p) => p.id === t.ticket.createdById) ?? null;
  const assignee = people.find((p) => p.id === t.ticket.assigneeId) ?? null;
  return { ...t, messages, creator, assignee };
}

export async function createTicket(p: {
  territoryId: string;
  subject: string;
  body: string;
  priority: string;
  user: { id: string; firstName: string | null; lastName: string | null; email: string };
}) {
  return db.transaction(async (tx) => {
    const [t] = await tx
      .insert(supportTickets)
      .values({ territoryId: p.territoryId, subject: p.subject, body: p.body, priority: p.priority, createdById: p.user.id, status: 'OPEN' })
      .returning();
    await tx.insert(ticketMessages).values({ ticketId: t.id, authorId: p.user.id, authorLabel: fullName(p.user), body: p.body });
    return t;
  });
}

export async function addTicketMessage(p: {
  ticketId: string;
  user: { id: string; firstName: string | null; lastName: string | null; email: string };
  body: string;
  fromSupport: boolean;
  internal?: boolean;
}) {
  await db.insert(ticketMessages).values({
    ticketId: p.ticketId,
    authorId: p.user.id,
    authorLabel: p.fromSupport ? `${fullName(p.user)} · support terricom` : fullName(p.user),
    fromSupport: p.fromSupport,
    internal: p.internal ?? false,
    body: p.body,
  });
  await db.update(supportTickets).set({ updatedAt: new Date() }).where(eq(supportTickets.id, p.ticketId));
}
