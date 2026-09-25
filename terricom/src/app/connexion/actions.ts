'use server';

import { eq } from 'drizzle-orm';
import { redirect } from 'next/navigation';
import { z } from 'zod';
import { audit } from '@/server/audit';
import { authenticate, createPasswordResetToken, findValidToken, homePathFor, safeNext, verifySecondFactor } from '@/server/auth/login';
import { rateLimit } from '@/server/auth/rate-limit';
import { createSession, destroySession, getSession, markMfaVerified, revokeOtherSessions } from '@/server/auth/session';
import { hashPassword, passwordIssues } from '@/server/crypto';
import { db } from '@/server/db';
import { sessions, tokens, users } from '@/server/db/schema';
import { sendEmail } from '@/server/mail/send';
import { passwordResetTemplate } from '@/server/mail/templates';
import { requestInfo } from '@/server/request';
import { appUrl } from '@/server/urls';

export type AuthState = { status: 'idle' | 'error' | 'ok'; message?: string };

const loginSchema = z.object({
  email: z.string().trim().email('Adresse email invalide').max(254),
  password: z.string().min(1, 'Saisissez votre mot de passe').max(200),
  next: z.string().optional(),
});

export async function loginAction(_prev: AuthState, form: FormData): Promise<AuthState> {
  const parsed = loginSchema.safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const res = await authenticate(parsed.data.email, parsed.data.password);
  if (!res.ok) return { status: 'error', message: res.message };
  await createSession(res.userId, { mfaVerified: !res.mfa });
  const next = safeNext(parsed.data.next);
  if (res.mfa) redirect(`/connexion/mfa${next ? `?next=${encodeURIComponent(next)}` : ''}`);
  redirect(next ?? (await homePathFor(res.userId)));
}

export async function mfaAction(_prev: AuthState, form: FormData): Promise<AuthState> {
  const s = await getSession();
  if (!s) redirect('/connexion');
  const code = String(form.get('code') ?? '');
  const ok = await verifySecondFactor(s.user.id, code);
  if (!ok) {
    const info = await requestInfo();
    await audit({ actor: { user: s.user }, category: 'SECURITE', action: 'auth.mfa_failed', summary: 'Code de double authentification refusé', targetType: 'user', targetId: s.user.id, ip: info.ip });
    return { status: 'error', message: 'Code incorrect ou expiré. Vérifiez l’heure de votre téléphone.' };
  }
  await markMfaVerified();
  const next = safeNext(form.get('next'));
  redirect(next ?? (await homePathFor(s.user.id)));
}

export async function logoutAction(): Promise<void> {
  const s = await getSession();
  if (s) await audit({ actor: { user: s.user }, category: 'AUTH', action: 'auth.logout', summary: 'Déconnexion', targetType: 'user', targetId: s.user.id });
  await destroySession();
  redirect('/connexion?au-revoir=1');
}

export async function requestResetAction(_prev: AuthState, form: FormData): Promise<AuthState> {
  const email = z.string().trim().toLowerCase().email().safeParse(form.get('email'));
  if (!email.success) return { status: 'error', message: 'Adresse email invalide.' };
  const info = await requestInfo();
  const limited = await rateLimit(`reset:${info.ip ?? 'anon'}`, 5, 3600);
  if (limited.ok) {
    const t = await createPasswordResetToken(email.data);
    if (t) await sendEmail(passwordResetTemplate({ to: email.data, url: appUrl(`/mot-de-passe/${t.token}`) }));
  }
  // Même réponse dans tous les cas : on ne révèle pas si un compte existe.
  return { status: 'ok', message: 'Si un compte existe pour cette adresse, un lien de réinitialisation vient de vous être envoyé (valable 1 heure).' };
}

export async function resetPasswordAction(_prev: AuthState, form: FormData): Promise<AuthState> {
  const token = String(form.get('token') ?? '');
  const password = String(form.get('password') ?? '');
  const confirm = String(form.get('confirm') ?? '');
  const row = await findValidToken('PASSWORD_RESET', token);
  if (!row?.userId) return { status: 'error', message: 'Ce lien a expiré. Refaites une demande de réinitialisation.' };
  const issue = passwordIssues(password);
  if (issue) return { status: 'error', message: issue };
  if (password !== confirm) return { status: 'error', message: 'Les deux mots de passe ne correspondent pas.' };
  await db.transaction(async (tx) => {
    await tx.update(users).set({ passwordHash: await hashPassword(password), passwordChangedAt: new Date(), failedLoginCount: 0, lockedUntil: null }).where(eq(users.id, row.userId!));
    await tx.update(tokens).set({ usedAt: new Date() }).where(eq(tokens.id, row.id));
    // Toutes les sessions ouvertes sont fermées après un changement de mot de passe.
    await tx.delete(sessions).where(eq(sessions.userId, row.userId!));
  });
  await audit({ actor: { id: row.userId, label: row.email ?? 'Utilisateur' }, category: 'SECURITE', action: 'auth.password_reset', summary: 'Mot de passe réinitialisé', targetType: 'user', targetId: row.userId });
  redirect('/connexion?mot-de-passe=1');
}

export async function changePasswordAction(_prev: AuthState, form: FormData): Promise<AuthState> {
  const s = await getSession();
  if (!s) redirect('/connexion');
  const { verifyPassword } = await import('@/server/crypto');
  const current = String(form.get('current') ?? '');
  const password = String(form.get('password') ?? '');
  if (!(await verifyPassword(current, s.user.passwordHash))) return { status: 'error', message: 'Mot de passe actuel incorrect.' };
  const issue = passwordIssues(password);
  if (issue) return { status: 'error', message: issue };
  await db.update(users).set({ passwordHash: await hashPassword(password), passwordChangedAt: new Date() }).where(eq(users.id, s.user.id));
  await revokeOtherSessions(s.user.id);
  await audit({ actor: { user: s.user }, category: 'SECURITE', action: 'auth.password_changed', summary: 'Mot de passe modifié', targetType: 'user', targetId: s.user.id });
  return { status: 'ok', message: 'Mot de passe modifié. Vos autres sessions ont été fermées.' };
}
