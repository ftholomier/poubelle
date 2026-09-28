'use server';

import { eq, sql } from 'drizzle-orm';
import { redirect } from 'next/navigation';
import { z } from 'zod';
import { audit } from '@/server/audit';
import { rateLimit } from '@/server/auth/rate-limit';
import { getSession } from '@/server/auth/session';
import { createAccountFromForm } from '@/server/auth/signup';
import { db } from '@/server/db';
import { communes, companies, establishments } from '@/server/db/schema';
import { env } from '@/server/env';
import { communesByPostalCode, isValidSiret, lookupSiret } from '@/server/integrations/public-data';
import { requestInfo } from '@/server/request';
import { changeCompanyPlan } from '@/server/services/billing';
import { ClaimError } from '@/server/services/claims';
import { activateDirectMember, coverageOfCommune, createDirectMember, type Coverage } from '@/server/services/direct';
import { createCheckoutSession } from '@/server/services/stripe';

export type DirectLookup = {
  ok: boolean;
  message: string;
  name?: string;
  street?: string;
  inseeCode?: string | null;
  city?: string | null;
  postalCode?: string | null;
  coverage?: Coverage;
  existingId?: string;
};

export type CommuneChoice = { inseeCode: string; name: string; coverage: Coverage };

export type DirectState = { status: 'idle' | 'error'; message?: string; existingId?: string; coveredSlug?: string; needsLogin?: boolean; redirectUrl?: string };

async function limited(key: string, max: number): Promise<boolean> {
  const info = await requestInfo();
  return !(await rateLimit(`${key}:${info.ip ?? 'anon'}`, max, 3600)).ok;
}

/** SIRET → établissement (base SIRENE), et couverture de sa commune par une collectivité partenaire. */
export async function directLookupAction(siret: string): Promise<DirectLookup> {
  if (await limited('direct-sirene', 30)) return { ok: false, message: 'Trop de recherches : réessayez plus tard.' };
  const clean = siret.replace(/\s/g, '');
  if (!isValidSiret(clean)) return { ok: false, message: 'Numéro SIRET invalide (14 chiffres, clé de contrôle).' };
  const [existing] = await db.select({ id: establishments.id }).from(establishments).where(eq(establishments.siret, clean)).limit(1);
  if (existing) return { ok: false, message: 'Cette entreprise a déjà une fiche sur terricom.', existingId: existing.id };
  const r = await lookupSiret(clean);
  if (!r) return { ok: false, message: 'Base SIRENE injoignable ou établissement introuvable : indiquez votre code postal et complétez les champs.' };
  if (!r.active) return { ok: false, message: 'Cet établissement est fermé dans la base SIRENE.' };
  const coverage = r.inseeCode ? await coverageOfCommune(r.inseeCode) : ({ status: 'free' } as Coverage);
  return {
    ok: true,
    message: `Trouvé : ${r.name}${r.city ? ` (${r.city})` : ''}`,
    name: r.name,
    street: r.address?.replace(/\s\d{5}\s.*$/, '') ?? '',
    inseeCode: r.inseeCode,
    city: r.city,
    postalCode: r.postalCode,
    coverage,
  };
}

/** Communes d'un code postal (référentiel terricom d'abord, puis API Géo), avec leur couverture. */
export async function communesByPostalCodeAction(postalCode: string): Promise<CommuneChoice[]> {
  if (!/^\d{5}$/.test(postalCode) || (await limited('direct-cp', 60))) return [];
  const local = await db
    .select({ inseeCode: communes.inseeCode, name: communes.name })
    .from(communes)
    .where(sql`${postalCode} = ANY(${communes.postalCodes})`);
  const list = local.length
    ? local.map((c) => ({ inseeCode: c.inseeCode, name: c.name }))
    : ((await communesByPostalCode(postalCode)) ?? []).map((c) => ({ inseeCode: c.code, name: c.nom }));
  return Promise.all(list.map(async (c) => ({ ...c, coverage: await coverageOfCommune(c.inseeCode) })));
}

/** Adhésion directe : compte, fiche (vitrine nationale), abonnement ; paiement par carte ou facture. */
export async function joinDirectAction(_prev: DirectState, form: FormData): Promise<DirectState> {
  const parsed = z
    .object({
      siret: z.string().trim().min(14, 'Indiquez votre numéro SIRET (14 chiffres)'),
      name: z.string().trim().min(2, 'Indiquez le nom de votre établissement').max(160),
      categoryId: z.string().uuid('Choisissez une catégorie'),
      activityLabel: z.string().trim().max(160).optional(),
      street: z.string().trim().min(3, 'Indiquez l’adresse').max(255),
      inseeCode: z.string().regex(/^[0-9AB]{5}$/, 'Choisissez votre commune'),
      phone: z
        .string()
        .trim()
        .max(32)
        .regex(/^[+0-9 .()-]*$/, 'Téléphone invalide')
        .optional(),
      plan: z.enum(['PREMIUM', 'COMMUNICATION'], { message: 'Choisissez votre formule' }),
      interval: z.enum(['MONTH', 'YEAR']).default('MONTH'),
      billingName: z.string().trim().min(2, 'Indiquez la raison sociale à facturer').max(255),
      billingEmail: z.string().trim().email('Email de facturation invalide'),
      billingAddress: z.string().trim().min(5, 'Indiquez l’adresse de facturation').max(500),
      accept: z.literal('on', { message: 'Merci d’accepter les conditions générales de vente.' }),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  const siret = d.siret.replace(/\s/g, '');
  if (!isValidSiret(siret)) return { status: 'error', message: 'Numéro SIRET invalide (14 chiffres, clé de contrôle).' };

  let user = (await getSession())?.user ?? null;
  if (!user) {
    const res = await createAccountFromForm(form, 'adhésion directe');
    if (!res.ok) return { status: 'error', message: res.message, needsLogin: res.exists };
    user = res.user;
  }
  if (!(await rateLimit(`direct-join:${user.id}`, 5, 86_400)).ok) return { status: 'error', message: 'Plusieurs adhésions aujourd’hui : réessayez demain.' };

  let created: { estId: string; companyId: string };
  try {
    created = await createDirectMember({
      userId: user.id,
      siret,
      name: d.name,
      categoryId: d.categoryId,
      activityLabel: d.activityLabel || null,
      street: d.street,
      inseeCode: d.inseeCode,
      phone: d.phone || null,
      email: null,
      website: null,
      sirene: await lookupSiret(siret),
    });
  } catch (err) {
    if (err instanceof ClaimError) {
      if (err.message.startsWith('EXISTS:'))
        return { status: 'error', message: 'Cette entreprise a déjà une fiche sur terricom.', existingId: err.message.slice(7) };
      if (err.message.startsWith('COVERED:'))
        return {
          status: 'error',
          message: 'Bonne nouvelle : votre collectivité est partenaire de terricom, votre fiche y est offerte.',
          coveredSlug: err.message.slice(8),
        };
      return { status: 'error', message: err.message };
    }
    throw err;
  }
  await db
    .update(companies)
    .set({ billingName: d.billingName, billingEmail: d.billingEmail, billingAddress: d.billingAddress })
    .where(eq(companies.id, created.companyId));

  if (env.STRIPE_SECRET_KEY) {
    const url = await createCheckoutSession({
      companyId: created.companyId,
      plan: d.plan,
      estId: created.estId,
      email: d.billingEmail,
      direct: true,
      interval: d.interval,
    });
    if (url) return { status: 'idle', redirectUrl: url };
  }
  // Sans paiement par carte : facture à régler par virement, la fiche est publiée tout de suite.
  await changeCompanyPlan(created.companyId, d.plan, 'MANUAL', { direct: true, interval: d.interval });
  await activateDirectMember(created.companyId);
  await audit({
    actor: { user },
    category: 'FACTURATION',
    action: 'direct.joined',
    summary: `Adhésion directe : ${d.name} (${d.plan === 'PREMIUM' ? 'Adhésion' : 'Adhésion Communication'}, ${d.interval === 'YEAR' ? 'annuelle' : 'mensuelle'})`,
    targetType: 'company',
    targetId: created.companyId,
  });
  redirect(`/pro/${created.estId}/offre?adhesion=ok`);
}
