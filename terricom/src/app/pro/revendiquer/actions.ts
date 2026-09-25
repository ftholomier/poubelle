'use server';

import { redirect } from 'next/navigation';
import { z } from 'zod';
import { confirmEnrollment } from '@/server/auth/mfa';
import { rateLimit } from '@/server/auth/rate-limit';
import { getSession } from '@/server/auth/session';
import { createAccountFromForm } from '@/server/auth/signup';
import { env } from '@/server/env';
import { MediaError, saveDocumentUpload } from '@/server/media';
import {
  addClaimDocument,
  approveClaim,
  checkSiretFor,
  ClaimError,
  claimForUser,
  loadClaimTarget,
  submitClaim,
  verifyClaimCode,
  type SiretVerdict,
} from '@/server/services/claims';

export type ClaimFormState = { status: 'idle' | 'ok' | 'error'; message?: string; recoveryCodes?: string[] };

const uuid = z.string().uuid();

async function requireUser(next: string) {
  const s = await getSession();
  if (!s) redirect(`/connexion?next=${encodeURIComponent(next)}`);
  return s.user;
}

// ─── Étape « Compte » ──────────────────────────────────────────────────────

export async function createClaimAccount(_prev: ClaimFormState, form: FormData): Promise<ClaimFormState> {
  const estId = uuid.safeParse(form.get('estId'));
  if (!estId.success) return { status: 'error', message: 'Fiche introuvable.' };
  const res = await createAccountFromForm(form, 'revendication de fiche');
  if (!res.ok) return { status: 'error', message: res.message };
  const mfa = form.get('mfa') === 'on';
  redirect(`/pro/revendiquer/${estId.data}?etape=${mfa ? 'securite' : 'identite'}`);
}

/** Activation de la double authentification pendant le parcours. */
export async function confirmClaimMfa(_prev: ClaimFormState, form: FormData): Promise<ClaimFormState> {
  const user = await requireUser('/pro');
  const res = await confirmEnrollment(user.id, String(form.get('code') ?? ''));
  if (!res.ok) return { status: 'error', message: res.message };
  return { status: 'ok', message: 'Double authentification activée.', recoveryCodes: res.recoveryCodes };
}

// ─── Étape « Identité » ────────────────────────────────────────────────────

export async function checkSiretAction(estId: string, siret: string): Promise<SiretVerdict> {
  const s = await getSession();
  const limited = await rateLimit(`siret-check:${s?.user.id ?? 'anon'}`, 30, 3600);
  if (!limited.ok) return { ok: null, message: 'Trop de vérifications : réessayez dans quelques minutes.', holder: null, strong: false };
  if (!uuid.safeParse(estId).success) return { ok: false, message: 'Fiche introuvable.', holder: null, strong: false };
  return checkSiretFor(estId, siret, s ? s.user : null);
}

export async function submitClaimAction(_prev: ClaimFormState, form: FormData): Promise<ClaimFormState> {
  const estId = uuid.safeParse(form.get('estId'));
  if (!estId.success) return { status: 'error', message: 'Fiche introuvable.' };
  const user = await requireUser(`/pro/revendiquer/${estId.data}?etape=identite`);
  const limited = await rateLimit(`claim:${user.id}`, 8, 86_400);
  if (!limited.ok) return { status: 'error', message: 'Vous avez déposé beaucoup de demandes aujourd’hui. Réessayez demain.' };
  const method = z.enum(['SIRET', 'CODE', 'KBIS']).safeParse(form.get('method'));
  if (!method.success) return { status: 'error', message: 'Choisissez une méthode de vérification.' };
  const target = await loadClaimTarget(estId.data);
  if (!target) return { status: 'error', message: 'Fiche introuvable.' };
  let kbisMediaId: string | null = null;
  const file = form.get('kbis');
  if (method.data === 'KBIS') {
    if (!(file instanceof File) || file.size === 0) return { status: 'error', message: 'Déposez votre extrait Kbis (PDF de moins de 3 mois).' };
    try {
      const doc = await saveDocumentUpload(file, {
        ownerType: 'CLAIM_KBIS',
        territoryId: target.est.territoryId,
        establishmentId: target.est.id,
        uploadedById: user.id,
      });
      kbisMediaId = doc.id;
    } catch (err) {
      return { status: 'error', message: err instanceof MediaError ? err.message : 'Le document n’a pas pu être enregistré.' };
    }
  }
  let claimId: string;
  try {
    const res = await submitClaim({
      estId: estId.data,
      user,
      method: method.data,
      siret: String(form.get('siret') ?? '').trim() || null,
      claimantRole: user.jobTitle ?? 'Gérant·e',
      kbisMediaId,
    });
    claimId = res.claimId;
  } catch (err) {
    if (err instanceof ClaimError) return { status: 'error', message: err.message };
    throw err;
  }
  redirect(`/pro/revendiquer/suivi/${claimId}`);
}

// ─── Suivi ─────────────────────────────────────────────────────────────────

export async function verifyCodeAction(_prev: ClaimFormState, form: FormData): Promise<ClaimFormState> {
  const claimId = uuid.safeParse(form.get('claimId'));
  if (!claimId.success) return { status: 'error', message: 'Demande introuvable.' };
  const user = await requireUser(`/pro/revendiquer/suivi/${claimId.data}`);
  const res = await verifyClaimCode(claimId.data, user.id, String(form.get('code') ?? ''));
  if (!res.ok) return { status: 'error', message: res.message };
  redirect(`/pro/revendiquer/suivi/${claimId.data}`);
}

export async function addDocumentAction(_prev: ClaimFormState, form: FormData): Promise<ClaimFormState> {
  const claimId = uuid.safeParse(form.get('claimId'));
  if (!claimId.success) return { status: 'error', message: 'Demande introuvable.' };
  const user = await requireUser(`/pro/revendiquer/suivi/${claimId.data}`);
  const ctx = await claimForUser(claimId.data, user.id);
  if (!ctx) return { status: 'error', message: 'Demande introuvable.' };
  const file = form.get('kbis');
  if (!(file instanceof File) || file.size === 0) return { status: 'error', message: 'Déposez votre document (PDF).' };
  try {
    const doc = await saveDocumentUpload(file, {
      ownerType: 'CLAIM_KBIS',
      territoryId: ctx.est.territoryId,
      establishmentId: ctx.est.id,
      uploadedById: user.id,
    });
    await addClaimDocument(claimId.data, user.id, doc.id);
  } catch (err) {
    if (err instanceof MediaError || err instanceof ClaimError) return { status: 'error', message: err.message };
    throw err;
  }
  redirect(`/pro/revendiquer/suivi/${claimId.data}`);
}

/** Démonstration : la mairie valide la demande (DEMO_MODE uniquement). */
export async function simulateApprovalAction(form: FormData): Promise<void> {
  if (!env.DEMO_MODE) throw new Error('Disponible uniquement en démonstration');
  const claimId = uuid.parse(form.get('claimId'));
  const user = await requireUser(`/pro/revendiquer/suivi/${claimId}`);
  const ctx = await claimForUser(claimId, user.id);
  if (ctx && (ctx.claim.status === 'PENDING' || ctx.claim.status === 'NEEDS_INFO'))
    await approveClaim(claimId, 'Démonstration', 'Validation simulée (démonstration)');
  redirect(`/pro/revendiquer/suivi/${claimId}`);
}
