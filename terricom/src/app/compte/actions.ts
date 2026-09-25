'use server';

import { and, eq, inArray, ne } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { z } from 'zod';
import { audit } from '@/server/audit';
import { confirmEnrollment, disableMfa, regenerateRecoveryCodes } from '@/server/auth/mfa';
import { destroySession, getSession, revokeOtherSessions, revokeSession } from '@/server/auth/session';
import { sha256, verifyPassword } from '@/server/crypto';
import { db } from '@/server/db';
import { companyMembers, privacyRequests, roleAssignments, sessions, users } from '@/server/db/schema';

export type AccountState = { status: 'idle' | 'ok' | 'error'; message?: string; codes?: string[] };

async function requireSession() {
  const s = await getSession();
  if (!s) redirect('/connexion?next=/compte');
  if (s.user.mfaEnabled && !s.session.mfaVerified) redirect('/connexion/mfa?next=/compte');
  return s;
}

export async function updateProfileAction(_prev: AccountState, form: FormData): Promise<AccountState> {
  const { user } = await requireSession();
  const parsed = z
    .object({
      firstName: z.string().trim().min(1, 'Prénom requis').max(120),
      lastName: z.string().trim().min(1, 'Nom requis').max(120),
      phone: z
        .string()
        .trim()
        .max(32)
        .regex(/^[+0-9 .()-]*$/, 'Numéro de téléphone invalide'),
      jobTitle: z.string().trim().max(160),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  await db
    .update(users)
    .set({ firstName: d.firstName, lastName: d.lastName, phone: d.phone || null, jobTitle: d.jobTitle || null, updatedAt: new Date() })
    .where(eq(users.id, user.id));
  await audit({
    actor: { user },
    category: 'MODIFICATION',
    action: 'user.profile_updated',
    summary: 'Profil mis à jour',
    targetType: 'user',
    targetId: user.id,
  });
  revalidatePath('/compte', 'layout');
  return { status: 'ok', message: 'Profil enregistré.' };
}

export async function confirmMfaAction(_prev: AccountState, form: FormData): Promise<AccountState> {
  const { user } = await requireSession();
  const res = await confirmEnrollment(user.id, String(form.get('code') ?? ''));
  if (!res.ok) return { status: 'error', message: res.message };
  revalidatePath('/compte', 'layout');
  return { status: 'ok', message: 'Double authentification activée.', codes: res.recoveryCodes };
}

export async function disableMfaAction(_prev: AccountState, form: FormData): Promise<AccountState> {
  const { user } = await requireSession();
  const [staff] = await db.select({ id: roleAssignments.id }).from(roleAssignments).where(eq(roleAssignments.userId, user.id)).limit(1);
  if (staff) return { status: 'error', message: 'La double authentification est obligatoire pour les agents des collectivités et de la plateforme.' };
  const res = await disableMfa(user.id, String(form.get('code') ?? ''));
  if (res.ok) revalidatePath('/compte', 'layout');
  return { status: res.ok ? 'ok' : 'error', message: res.message };
}

export async function regenerateCodesAction(_prev: AccountState, form: FormData): Promise<AccountState> {
  const { user } = await requireSession();
  if (!(await verifyPassword(String(form.get('password') ?? ''), user.passwordHash))) return { status: 'error', message: 'Mot de passe incorrect.' };
  const codes = await regenerateRecoveryCodes(user.id);
  if (!codes) return { status: 'error', message: 'Activez d’abord la double authentification.' };
  return { status: 'ok', message: 'Nouveaux codes générés : les anciens ne fonctionnent plus.', codes };
}

export async function revokeSessionAction(form: FormData): Promise<void> {
  const { user, session } = await requireSession();
  const id = z
    .string()
    .regex(/^[0-9a-f]{64}$/)
    .parse(form.get('sessionId'));
  if (id === session.id) {
    await destroySession();
    redirect('/connexion');
  }
  await revokeSession(user.id, id);
  await audit({
    actor: { user },
    category: 'SECURITE',
    action: 'session.revoked',
    summary: 'Session fermée à distance',
    targetType: 'user',
    targetId: user.id,
  });
  revalidatePath('/compte/securite');
}

export async function revokeOthersAction(): Promise<void> {
  const { user } = await requireSession();
  await revokeOtherSessions(user.id);
  await audit({
    actor: { user },
    category: 'SECURITE',
    action: 'session.revoked_all',
    summary: 'Toutes les autres sessions fermées',
    targetType: 'user',
    targetId: user.id,
  });
  revalidatePath('/compte/securite');
}

/**
 * Suppression du compte (droit à l'effacement) : le compte est anonymisé, les accès retirés.
 * Les fiches d'établissement restent publiées (données d'entreprise) mais ne sont plus gérées.
 */
export async function deleteAccountAction(_prev: AccountState, form: FormData): Promise<AccountState> {
  const { user } = await requireSession();
  if (!(await verifyPassword(String(form.get('password') ?? ''), user.passwordHash))) return { status: 'error', message: 'Mot de passe incorrect.' };
  if (
    String(form.get('confirm') ?? '')
      .trim()
      .toUpperCase() !== 'SUPPRIMER'
  )
    return { status: 'error', message: 'Tapez SUPPRIMER pour confirmer.' };
  const roles = await db.select({ role: roleAssignments.role }).from(roleAssignments).where(eq(roleAssignments.userId, user.id));
  if (roles.some((r) => r.role === 'PLATFORM_ADMIN'))
    return { status: 'error', message: 'Un super administrateur ne peut pas supprimer son propre compte : demandez-le à un autre administrateur.' };
  // Titulaire unique d'une entreprise ayant d'autres collaborateurs : transmettre d'abord.
  const owned = await db
    .select({ companyId: companyMembers.companyId })
    .from(companyMembers)
    .where(and(eq(companyMembers.userId, user.id), eq(companyMembers.role, 'OWNER')));
  if (owned.length) {
    const others = await db
      .select({ companyId: companyMembers.companyId, role: companyMembers.role })
      .from(companyMembers)
      .where(
        and(
          inArray(
            companyMembers.companyId,
            owned.map((o) => o.companyId),
          ),
          ne(companyMembers.userId, user.id),
        ),
      );
    const orphan = owned.find((o) => others.some((x) => x.companyId === o.companyId) && !others.some((x) => x.companyId === o.companyId && x.role === 'OWNER'));
    if (orphan)
      return { status: 'error', message: 'Nommez d’abord un autre titulaire dans « Équipe » : vos collaborateurs perdraient sinon l’accès à la fiche.' };
  }
  const anonymous = `supprime-${sha256(user.id).slice(0, 12)}@invalid.terricom.fr`;
  await db.transaction(async (tx) => {
    await tx.delete(companyMembers).where(eq(companyMembers.userId, user.id));
    await tx.delete(roleAssignments).where(eq(roleAssignments.userId, user.id));
    await tx.delete(sessions).where(eq(sessions.userId, user.id));
    await tx
      .insert(privacyRequests)
      .values({ email: user.email, kind: 'DELETE', status: 'DONE', note: 'Suppression par l’utilisateur depuis son compte', completedAt: new Date() });
    await tx
      .update(users)
      .set({
        email: anonymous,
        firstName: '',
        lastName: '',
        phone: null,
        avatarUrl: null,
        jobTitle: null,
        passwordHash: null,
        mfaEnabled: false,
        mfaSecretEnc: null,
        mfaRecoveryCodes: [],
        status: 'DELETED',
        deletedAt: new Date(),
      })
      .where(eq(users.id, user.id));
  });
  await audit({
    actor: { id: user.id, label: 'Compte supprimé' },
    category: 'RGPD',
    action: 'user.deleted',
    summary: 'Compte supprimé à la demande de son titulaire',
    targetType: 'user',
    targetId: user.id,
  });
  await destroySession();
  redirect('/connexion?compte-supprime=1');
}
