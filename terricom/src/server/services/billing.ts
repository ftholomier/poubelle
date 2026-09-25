import { and, desc, eq, sql } from 'drizzle-orm';
import type { PlanKey } from '@/lib/constants';
import { parisDate } from '@/lib/format';
import { db, type DbOrTx } from '../db';
import {
  companies,
  companySubscriptions,
  invoiceCounters,
  invoices,
  plans,
  territoryContracts,
  type InvoiceLine,
  type PlanLimits,
} from '../db/schema';

export type Plan = typeof plans.$inferSelect;

export async function getPlans(): Promise<Plan[]> {
  return db.select().from(plans).where(eq(plans.isActive, true)).orderBy(plans.sortOrder);
}

export async function getPlan(key: PlanKey): Promise<Plan> {
  const [p] = await db.select().from(plans).where(eq(plans.key, key)).limit(1);
  if (!p) throw new Error(`Offre inconnue : ${key}`);
  return p;
}

export async function planLimits(key: PlanKey): Promise<PlanLimits> {
  return (await getPlan(key)).limits;
}

function addDays(iso: string, n: number): string {
  const d = new Date(`${iso}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + n);
  return d.toISOString().slice(0, 10);
}

/** Numérotation continue et sans trou par année (obligation légale), sous verrou transactionnel. */
async function nextInvoiceNumber(tx: DbOrTx, year: number): Promise<string> {
  const res = await tx.execute<{ last_number: number }>(sql`
    INSERT INTO invoice_counters (year, last_number) VALUES (${year}, 1)
    ON CONFLICT (year) DO UPDATE SET last_number = invoice_counters.last_number + 1
    RETURNING last_number
  `);
  return `TC-${year}-${String(res.rows[0].last_number).padStart(5, '0')}`;
}

export function invoiceTotals(lines: InvoiceLine[]) {
  const totalHtCents = lines.reduce((s, l) => s + Math.round(l.quantity * l.unitCents), 0);
  const vatCents = lines.reduce((s, l) => s + Math.round((l.quantity * l.unitCents * l.vatRate) / 100), 0);
  return { totalHtCents, vatCents, totalTtcCents: totalHtCents + vatCents };
}

export async function createInvoice(p: {
  customerType: 'TERRITORY' | 'COMPANY';
  territoryId?: string | null;
  companyId?: string | null;
  customerName: string;
  customerAddress?: string | null;
  issuedAt?: string;
  dueDays?: number;
  lines: InvoiceLine[];
  status?: 'DRAFT' | 'ISSUED' | 'PAID';
  paidAt?: string;
  paymentMethod?: string;
  chorusRef?: string;
  notes?: string;
}) {
  const issuedAt = p.issuedAt ?? parisDate();
  return db.transaction(async (tx) => {
    const number = await nextInvoiceNumber(tx, Number(issuedAt.slice(0, 4)));
    const totals = invoiceTotals(p.lines);
    const [inv] = await tx
      .insert(invoices)
      .values({
        number,
        customerType: p.customerType,
        territoryId: p.territoryId ?? null,
        companyId: p.companyId ?? null,
        customerName: p.customerName,
        customerAddress: p.customerAddress ?? null,
        issuedAt,
        // Secteur public : délai global de paiement de 30 jours (art. R.2192-10 du code de la commande publique)
        dueAt: addDays(issuedAt, p.dueDays ?? 30),
        status: p.status ?? 'ISSUED',
        lines: p.lines,
        ...totals,
        paidAt: p.paidAt ?? null,
        paymentMethod: p.paymentMethod ?? null,
        chorusRef: p.chorusRef ?? null,
        notes: p.notes ?? null,
      })
      .returning();
    return inv;
  });
}

/** Changement d'offre d'une entreprise : clôt l'abonnement courant, en ouvre un nouveau, facture au prorata du mois. */
export async function changeCompanyPlan(companyId: string, plan: PlanKey, provider: 'MANUAL' | 'STRIPE' = 'MANUAL') {
  const target = await getPlan(plan);
  return db.transaction(async (tx) => {
    await tx
      .update(companySubscriptions)
      .set({ status: 'CANCELED', canceledAt: new Date() })
      .where(and(eq(companySubscriptions.companyId, companyId), eq(companySubscriptions.status, 'ACTIVE')));
    await tx.update(companies).set({ plan }).where(eq(companies.id, companyId));
    if (plan === 'ESSENTIEL') return null;
    const periodEnd = new Date();
    periodEnd.setMonth(periodEnd.getMonth() + 1);
    const [sub] = await tx
      .insert(companySubscriptions)
      .values({ companyId, plan, status: 'ACTIVE', provider, currentPeriodEnd: periodEnd })
      .returning();
    const [co] = await tx.select().from(companies).where(eq(companies.id, companyId)).limit(1);
    const year = new Date().getFullYear();
    const number = await nextInvoiceNumber(tx, year);
    const lines: InvoiceLine[] = [{ label: `Abonnement ${target.name} — 1 mois`, quantity: 1, unitCents: target.priceMonthlyCents, vatRate: 20 }];
    await tx.insert(invoices).values({
      number,
      customerType: 'COMPANY',
      companyId,
      customerName: co?.billingName ?? co?.tradeName ?? co?.legalName ?? 'Entreprise',
      customerAddress: co?.billingAddress ?? null,
      issuedAt: parisDate(),
      dueAt: addDays(parisDate(), 15),
      status: provider === 'STRIPE' ? 'PAID' : 'ISSUED',
      lines,
      ...invoiceTotals(lines),
      paidAt: provider === 'STRIPE' ? parisDate() : null,
      paymentMethod: provider === 'STRIPE' ? 'CARTE' : 'VIREMENT',
    });
    return sub;
  });
}

export async function companyInvoices(companyId: string) {
  return db.select().from(invoices).where(eq(invoices.companyId, companyId)).orderBy(desc(invoices.issuedAt));
}

export async function territoryInvoices(territoryId: string) {
  return db.select().from(invoices).where(eq(invoices.territoryId, territoryId)).orderBy(desc(invoices.issuedAt));
}

export async function territoryLicence(territoryId: string) {
  const [c] = await db
    .select()
    .from(territoryContracts)
    .where(and(eq(territoryContracts.territoryId, territoryId), eq(territoryContracts.kind, 'LICENCE'), eq(territoryContracts.status, 'ACTIVE')))
    .orderBy(desc(territoryContracts.startsAt))
    .limit(1);
  return c ?? null;
}

/** Revenu récurrent : licences annuelles actives + abonnements Premium actifs (annualisés). */
export async function recurringRevenue(): Promise<{ licencesCents: number; premiumCents: number; arrCents: number; premiumCount: number }> {
  const [lic] = await db
    .select({ n: sql<number>`coalesce(sum(${territoryContracts.amountCents}), 0)::int` })
    .from(territoryContracts)
    .where(and(eq(territoryContracts.kind, 'LICENCE'), eq(territoryContracts.status, 'ACTIVE')));
  const prem = await db.execute<{ cents: number; n: number }>(sql`
    SELECT coalesce(sum(p.price_monthly_cents), 0)::int AS cents, count(*)::int AS n
    FROM company_subscriptions s JOIN plans p ON p.key = s.plan
    WHERE s.status = 'ACTIVE'
  `);
  const premiumCents = Number(prem.rows[0]?.cents ?? 0) * 12;
  const licencesCents = Number(lic?.n ?? 0);
  return { licencesCents, premiumCents, arrCents: licencesCents + premiumCents, premiumCount: Number(prem.rows[0]?.n ?? 0) };
}
