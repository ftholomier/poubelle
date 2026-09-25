'use server';

import { redirect } from 'next/navigation';
import { z } from 'zod';
import { rateLimit } from '@/server/auth/rate-limit';
import { getSession } from '@/server/auth/session';
import { createAccountFromForm } from '@/server/auth/signup';
import { isValidSiret, lookupSiret } from '@/server/integrations/public-data';
import { requestInfo } from '@/server/request';
import { ClaimError, submitClaim } from '@/server/services/claims';
import { createPendingEstablishment } from '@/server/services/signup';

export type SignupState = { status: 'idle' | 'error'; message?: string; existingId?: string; needsLogin?: boolean };

export type SiretLookup = { ok: boolean; message: string; name?: string; street?: string; inseeCode?: string | null };

/** Préremplissage depuis la base SIRENE. */
export async function lookupSiretAction(siret: string): Promise<SiretLookup> {
  const info = await requestInfo();
  if (!(await rateLimit(`sirene:${info.ip ?? 'anon'}`, 30, 3600)).ok) return { ok: false, message: 'Trop de recherches : réessayez plus tard.' };
  const clean = siret.replace(/\s/g, '');
  if (!isValidSiret(clean)) return { ok: false, message: 'Numéro SIRET invalide (14 chiffres, clé de contrôle).' };
  const r = await lookupSiret(clean);
  if (!r) return { ok: false, message: 'Base SIRENE injoignable ou établissement introuvable : complétez les champs vous-même.' };
  if (!r.active) return { ok: false, message: 'Cet établissement est fermé dans la base SIRENE.' };
  return { ok: true, message: `Trouvé : ${r.name}${r.city ? ` (${r.city})` : ''}`, name: r.name, street: r.address?.replace(/\s\d{5}\s.*$/, '') ?? '', inseeCode: r.inseeCode };
}

export async function registerAction(_prev: SignupState, form: FormData): Promise<SignupState> {
  const parsed = z
    .object({
      siret: z.string().trim().min(14, 'Indiquez votre numéro SIRET (14 chiffres)'),
      name: z.string().trim().min(2, 'Indiquez le nom de votre établissement').max(160),
      categoryId: z.string().uuid('Choisissez une catégorie'),
      activityLabel: z.string().trim().max(160).optional(),
      street: z.string().trim().min(3, 'Indiquez l’adresse').max(255),
      communeId: z.string().uuid('Choisissez votre commune'),
      phone: z.string().trim().max(32).regex(/^[+0-9 .()-]*$/, 'Téléphone invalide').optional(),
      publicEmail: z.union([z.literal(''), z.string().trim().email('Email public invalide')]).optional(),
      website: z.union([z.literal(''), z.string().trim().url('Adresse du site invalide (https://…)')]).optional(),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  const siret = d.siret.replace(/\s/g, '');
  if (!isValidSiret(siret)) return { status: 'error', message: 'Numéro SIRET invalide (14 chiffres, clé de contrôle).' };

  let session = await getSession();
  let user = session?.user ?? null;
  if (!user) {
    const res = await createAccountFromForm(form, 'inscription d’une activité');
    if (!res.ok) return { status: 'error', message: res.message, needsLogin: res.exists };
    user = res.user;
    session = await getSession();
  }
  if (!(await rateLimit(`signup-est:${user.id}`, 5, 86_400)).ok) return { status: 'error', message: 'Vous avez créé plusieurs fiches aujourd’hui : réessayez demain.' };

  let claimId: string;
  try {
    const estId = await createPendingEstablishment({
      userId: user.id,
      siret,
      name: d.name,
      categoryId: d.categoryId,
      activityLabel: d.activityLabel || null,
      street: d.street,
      communeId: d.communeId,
      phone: d.phone || null,
      email: d.publicEmail || null,
      website: d.website || null,
      sirene: await lookupSiret(siret),
    });
    const res = await submitClaim({ estId, user, method: 'SIRET', siret, claimantRole: user.jobTitle ?? 'Gérant·e', kbisMediaId: null, creation: true });
    claimId = res.claimId;
  } catch (err) {
    if (err instanceof ClaimError) {
      if (err.message.startsWith('EXISTS:'))
        return { status: 'error', message: 'Cette entreprise a déjà une fiche : revendiquez-la plutôt que d’en créer une nouvelle.', existingId: err.message.slice(7) };
      return { status: 'error', message: err.message };
    }
    throw err;
  }
  redirect(`/pro/revendiquer/suivi/${claimId}`);
}
