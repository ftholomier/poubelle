'use server';

import { eq } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { z } from 'zod';
import type { ActionState } from '@/app/pro/[est]/actions';
import { PLAN_LABELS, type PlanKey } from '@/lib/constants';
import { fmtEuros, fmtShortDate, parisDate } from '@/lib/format';
import { audit } from '@/server/audit';
import { requirePlatformStaff, type Actor } from '@/server/authz';
import { invalidate } from '@/server/cache';
import { db } from '@/server/db';
import { companies, invoices, plans, territories, type InvoiceLine, type PlanLimits } from '@/server/db/schema';
import { sendEmail } from '@/server/mail/send';
import { invoiceReminderTemplate } from '@/server/mail/templates';
import { createInvoice } from '@/server/services/billing';
import { renewLicence } from '@/server/services/console-billing';
import { appUrl } from '@/server/urls';

async function billingActor(): Promise<Actor | null> {
  const actor = await requirePlatformStaff();
  return actor.isPlatformAdmin ? actor : null;
}

const DENIED: ActionState = { status: 'error', message: 'Réservé aux super administrateurs.' };

export async function markInvoicePaidAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await billingActor();
  if (!actor) return DENIED;
  const d = z
    .object({
      id: z.string().uuid(),
      method: z.enum(['MANDAT_ADMINISTRATIF', 'VIREMENT', 'CARTE', 'PRELEVEMENT', 'CHEQUE']),
      paidAt: z.string().regex(/^\d{4}-\d{2}-\d{2}$/),
    })
    .safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: 'Paiement invalide.' };
  const [inv] = await db.select().from(invoices).where(eq(invoices.id, d.data.id)).limit(1);
  if (!inv || inv.status === 'PAID' || inv.status === 'CANCELLED') return { status: 'error', message: 'Facture introuvable ou déjà soldée.' };
  await db.update(invoices).set({ status: 'PAID', paidAt: d.data.paidAt, paymentMethod: d.data.method }).where(eq(invoices.id, inv.id));
  await audit({
    actor: { user: actor.user },
    category: 'FACTURATION',
    action: 'invoice.paid',
    summary: `Facture ${inv.number} encaissée (${fmtEuros(inv.totalTtcCents)})`,
    territoryId: inv.territoryId,
    targetType: 'invoice',
    targetId: inv.id,
  });
  revalidatePath('/console/facturation');
  return { status: 'ok', message: `Facture ${inv.number} marquée payée.` };
}

export async function cancelInvoiceAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await billingActor();
  if (!actor) return DENIED;
  const id = z.string().uuid().safeParse(form.get('id'));
  if (!id.success) return { status: 'error', message: 'Facture invalide.' };
  const [inv] = await db.select().from(invoices).where(eq(invoices.id, id.data)).limit(1);
  if (!inv || inv.status === 'PAID' || inv.status === 'CANCELLED') return { status: 'error', message: 'Une facture payée ne s’annule pas : émettez un avoir.' };
  // Numérotation continue : la facture reste, marquée annulée (pas de suppression).
  await db
    .update(invoices)
    .set({ status: 'CANCELLED', notes: `${inv.notes ? `${inv.notes}\n` : ''}Annulée le ${fmtShortDate(new Date())}` })
    .where(eq(invoices.id, inv.id));
  await audit({
    actor: { user: actor.user },
    category: 'FACTURATION',
    action: 'invoice.cancelled',
    summary: `Facture ${inv.number} annulée`,
    territoryId: inv.territoryId,
    targetType: 'invoice',
    targetId: inv.id,
  });
  revalidatePath('/console/facturation');
  return { status: 'ok', message: `Facture ${inv.number} annulée.` };
}

export async function remindInvoiceAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await billingActor();
  if (!actor) return DENIED;
  const id = z.string().uuid().safeParse(form.get('id'));
  if (!id.success) return { status: 'error', message: 'Facture invalide.' };
  const [inv] = await db.select().from(invoices).where(eq(invoices.id, id.data)).limit(1);
  if (!inv || (inv.status !== 'ISSUED' && inv.status !== 'OVERDUE')) return { status: 'error', message: 'Seules les factures en attente se relancent.' };
  let to: string | null = null;
  if (inv.territoryId) [{ to }] = await db.select({ to: territories.contactEmail }).from(territories).where(eq(territories.id, inv.territoryId)).limit(1);
  else if (inv.companyId) [{ to }] = await db.select({ to: companies.billingEmail }).from(companies).where(eq(companies.id, inv.companyId)).limit(1);
  if (!to) return { status: 'error', message: 'Aucune adresse de facturation connue pour ce client.' };
  await sendEmail({
    ...invoiceReminderTemplate({
      to,
      customerName: inv.customerName,
      number: inv.number,
      amount: fmtEuros(inv.totalTtcCents, { decimals: true }),
      dueAt: fmtShortDate(inv.dueAt),
      url: appUrl(`/api/factures/${inv.id}.pdf`),
    }),
    territoryId: inv.territoryId,
  });
  await audit({
    actor: { user: actor.user },
    category: 'FACTURATION',
    action: 'invoice.reminded',
    summary: `Relance de la facture ${inv.number} envoyée à ${to}`,
    territoryId: inv.territoryId,
    targetType: 'invoice',
    targetId: inv.id,
  });
  return { status: 'ok', message: `Relance envoyée à ${to}.` };
}

export async function renewLicenceAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await billingActor();
  if (!actor) return DENIED;
  const d = z.object({ id: z.string().uuid(), amount: z.coerce.number().min(0).max(1_000_000) }).safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: 'Montant invalide.' };
  const res = await renewLicence(d.data.id, Math.round(d.data.amount * 100));
  if (!res) return { status: 'error', message: 'Contrat introuvable ou déjà renouvelé.' };
  await audit({
    actor: { user: actor.user },
    category: 'FACTURATION',
    action: 'licence.renewed',
    summary: `Licence de ${res.territory.name} renouvelée jusqu’au ${fmtShortDate(res.endsAt)} (facture ${res.invoice.number})`,
    territoryId: res.territory.id,
    targetType: 'invoice',
    targetId: res.invoice.id,
  });
  revalidatePath('/console', 'layout');
  return { status: 'ok', message: `Licence renouvelée : facture ${res.invoice.number} émise.` };
}

export async function createManualInvoiceAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await billingActor();
  if (!actor) return DENIED;
  const territoryId = z.string().uuid().safeParse(form.get('territoryId'));
  if (!territoryId.success) return { status: 'error', message: 'Choisissez le territoire client.' };
  const [t] = await db.select().from(territories).where(eq(territories.id, territoryId.data)).limit(1);
  if (!t) return { status: 'error', message: 'Territoire introuvable.' };
  const lines: InvoiceLine[] = [];
  for (let i = 0; i < 3; i++) {
    const label = String(form.get(`label${i}`) ?? '').trim();
    const qty = Number(form.get(`qty${i}`) ?? 1);
    const unit = Number(String(form.get(`unit${i}`) ?? '').replace(',', '.'));
    if (!label) continue;
    if (!Number.isFinite(unit) || unit < 0 || !Number.isFinite(qty) || qty <= 0)
      return { status: 'error', message: `Ligne ${i + 1} : quantité ou prix invalide.` };
    lines.push({ label: label.slice(0, 200), quantity: qty, unitCents: Math.round(unit * 100), vatRate: 20 });
  }
  if (!lines.length) return { status: 'error', message: 'Ajoutez au moins une ligne.' };
  const issuedAt = String(form.get('issuedAt') ?? '') || parisDate();
  const inv = await createInvoice({
    customerType: 'TERRITORY',
    territoryId: t.id,
    customerName: t.legalName,
    issuedAt,
    lines,
    notes: String(form.get('notes') ?? '').trim() || undefined,
  });
  await audit({
    actor: { user: actor.user },
    category: 'FACTURATION',
    action: 'invoice.created',
    summary: `Facture ${inv.number} émise pour ${t.name} (${fmtEuros(inv.totalTtcCents)} TTC)`,
    territoryId: t.id,
    targetType: 'invoice',
    targetId: inv.id,
  });
  revalidatePath('/console/facturation');
  return { status: 'ok', message: `Facture ${inv.number} émise.` };
}

export async function updatePlanAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await billingActor();
  if (!actor) return DENIED;
  const d = z
    .object({
      key: z.enum(['ESSENTIEL', 'PREMIUM', 'COMMUNICATION']),
      name: z.string().trim().min(2).max(80),
      price: z.coerce.number().min(0).max(10_000),
      tagline: z.string().trim().max(255),
      features: z.string().max(4000),
      postsPerMonth: z.string().optional(),
      aiPerMonth: z.string().optional(),
    })
    .safeParse(Object.fromEntries(form));
  if (!d.success) return { status: 'error', message: 'Offre invalide.' };
  const [plan] = await db.select().from(plans).where(eq(plans.key, d.data.key)).limit(1);
  if (!plan) return { status: 'error', message: 'Offre inconnue.' };
  const num = (v?: string) => (v === undefined || v.trim() === '' ? null : Math.max(0, Math.round(Number(v))));
  const flags: (keyof PlanLimits)[] = [
    'scheduling',
    'newsletterChannel',
    'socialChannel',
    'advancedStats',
    'jobs',
    'appointments',
    'miniSite',
    'customerNewsletter',
    'contactsExport',
    'customQr',
    'customForms',
    'extraPages',
    'contentSync',
  ];
  const limits: PlanLimits = {
    ...plan.limits,
    postsPerMonth: num(d.data.postsPerMonth),
    aiPerMonth: num(d.data.aiPerMonth),
    ...Object.fromEntries(flags.map((f) => [f, form.get(`f_${f}`) === 'on'])),
  } as PlanLimits;
  await db
    .update(plans)
    .set({
      name: d.data.name,
      priceMonthlyCents: Math.round(d.data.price * 100),
      tagline: d.data.tagline,
      features: d.data.features
        .split('\n')
        .map((l) => l.trim())
        .filter(Boolean)
        .slice(0, 20),
      limits,
      updatedAt: new Date(),
    })
    .where(eq(plans.key, plan.key));
  invalidate('plans');
  await audit({
    actor: { user: actor.user },
    category: 'CONFIGURATION',
    action: 'plan.updated',
    summary: `Offre « ${PLAN_LABELS[plan.key as PlanKey]} » modifiée (${fmtEuros(Math.round(d.data.price * 100), { decimals: true })} / mois)`,
  });
  revalidatePath('/', 'layout');
  return { status: 'ok', message: 'Offre enregistrée : les nouveaux prix s’appliquent aux prochains abonnements.' };
}
