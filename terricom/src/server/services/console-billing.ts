import { and, desc, eq, sql, type SQL } from 'drizzle-orm';
import { parisDate } from '@/lib/format';
import { db } from '../db';
import { invoices, territories, territoryContracts } from '../db/schema';
import { createInvoice } from './billing';

/** Console plateforme — facturation : factures, relances, renouvellement des licences. */

export type InvoiceFilter = { status?: 'ISSUED' | 'PAID' | 'OVERDUE' | 'CANCELLED' | 'DRAFT'; customerType?: 'TERRITORY' | 'COMPANY'; territoryId?: string };

export async function invoiceKpis() {
  const [r] = (
    await db.execute<{ billed: number; paid: number; pending: number; overdue: number; overdue_n: number }>(sql`
      select
        coalesce(sum(total_ttc_cents) filter (where status <> 'CANCELLED' and status <> 'DRAFT' and issued_at >= current_date - interval '12 months'), 0)::bigint as billed,
        coalesce(sum(total_ttc_cents) filter (where status = 'PAID' and paid_at >= current_date - interval '12 months'), 0)::bigint as paid,
        coalesce(sum(total_ttc_cents) filter (where status = 'ISSUED'), 0)::bigint as pending,
        coalesce(sum(total_ttc_cents) filter (where status = 'OVERDUE'), 0)::bigint as overdue,
        count(*) filter (where status = 'OVERDUE')::int as overdue_n
      from invoices
    `)
  ).rows;
  return {
    billed: Number(r?.billed ?? 0),
    paid: Number(r?.paid ?? 0),
    pending: Number(r?.pending ?? 0),
    overdue: Number(r?.overdue ?? 0),
    overdueCount: r?.overdue_n ?? 0,
  };
}

export async function listInvoices(f: InvoiceFilter, limit = 100) {
  const conds: SQL[] = [];
  if (f.status) conds.push(eq(invoices.status, f.status));
  if (f.customerType) conds.push(eq(invoices.customerType, f.customerType));
  if (f.territoryId) conds.push(eq(invoices.territoryId, f.territoryId));
  return db
    .select()
    .from(invoices)
    .where(conds.length ? and(...conds) : undefined)
    .orderBy(desc(invoices.issuedAt), desc(invoices.number))
    .limit(limit);
}

/** Passe en « en retard » les factures émises dont l'échéance est dépassée (appelé aussi par le worker). */
export async function flagOverdueInvoices(): Promise<number> {
  const res = await db.execute(sql`update invoices set status = 'OVERDUE' where status = 'ISSUED' and due_at < current_date`);
  return res.rowCount ?? 0;
}

/** Licences actives arrivant à échéance dans les N jours (ou déjà échues). */
export async function licencesToRenew(days = 90) {
  return db
    .select({
      id: territoryContracts.id,
      territoryId: territoryContracts.territoryId,
      territoryName: territories.name,
      legalName: territories.legalName,
      amountCents: territoryContracts.amountCents,
      startsAt: territoryContracts.startsAt,
      endsAt: territoryContracts.endsAt,
    })
    .from(territoryContracts)
    .innerJoin(territories, eq(territories.id, territoryContracts.territoryId))
    .where(
      and(
        eq(territoryContracts.kind, 'LICENCE'),
        eq(territoryContracts.status, 'ACTIVE'),
        sql`${territoryContracts.endsAt} is not null and ${territoryContracts.endsAt} <= current_date + ${days}::int`,
      ),
    )
    .orderBy(territoryContracts.endsAt);
}

function addDaysIso(iso: string, n: number): string {
  const d = new Date(`${iso}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + n);
  return d.toISOString().slice(0, 10);
}

/** Renouvelle une licence pour 12 mois : nouveau contrat à la suite du précédent et facture correspondante. */
export async function renewLicence(contractId: string, amountCents?: number) {
  const [c] = await db.select().from(territoryContracts).where(eq(territoryContracts.id, contractId)).limit(1);
  if (!c || c.kind !== 'LICENCE' || c.status !== 'ACTIVE' || !c.endsAt) return null;
  const [t] = await db.select().from(territories).where(eq(territories.id, c.territoryId)).limit(1);
  if (!t) return null;
  const startsAt = addDaysIso(c.endsAt, 1);
  const endsAt = addDaysIso(startsAt, 364);
  const amount = amountCents ?? c.amountCents;
  await db.transaction(async (tx) => {
    await tx.update(territoryContracts).set({ status: 'EXPIRED' }).where(eq(territoryContracts.id, c.id));
    await tx.insert(territoryContracts).values({
      territoryId: c.territoryId,
      kind: 'LICENCE',
      label: c.label,
      amountCents: amount,
      startsAt,
      endsAt,
      status: 'ACTIVE',
      signedAt: parisDate(),
    });
  });
  const fr = (iso: string) => new Date(`${iso}T12:00:00Z`).toLocaleDateString('fr-FR', { month: 'long', year: 'numeric', timeZone: 'Europe/Paris' });
  const inv = await createInvoice({
    customerType: 'TERRITORY',
    territoryId: t.id,
    customerName: t.legalName,
    lines: [{ label: `Licence annuelle terricom (${fr(startsAt)} – ${fr(endsAt)})`, quantity: 1, unitCents: amount, vatRate: 20 }],
  });
  return { territory: t, invoice: inv, startsAt, endsAt };
}
