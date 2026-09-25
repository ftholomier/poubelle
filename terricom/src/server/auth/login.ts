import { and, eq, gt, isNull } from 'drizzle-orm';
import { audit } from '../audit';
import { decrypt, hashPassword, randomToken, sha256, verifyPassword } from '../crypto';
import { db } from '../db';
import { companyMembers, roleAssignments, tokens, users } from '../db/schema';
import { sendEmail } from '../mail/send';
import { securityAlertTemplate } from '../mail/templates';
import { requestInfo } from '../request';
import { verifyTotp } from './totp';
import { consumeOnce, rateLimit } from './rate-limit';

const MAX_FAILURES = 5;
const LOCK_MINUTES = 15;
// Empreinte factice : même coût de calcul que l'utilisateur existe ou non (pas d'énumération de comptes).
let dummyHash: Promise<string> | null = null;

export type LoginResult =
  | { ok: true; userId: string; mfa: boolean }
  | { ok: false; reason: 'invalid' | 'locked' | 'throttled' | 'disabled'; message: string };

/** Vérification des identifiants, avec verrouillage progressif et journalisation. */
export async function authenticate(emailRaw: string, password: string): Promise<LoginResult> {
  const email = emailRaw.trim().toLowerCase();
  const info = await requestInfo();
  const [byIp, byEmail] = await Promise.all([rateLimit(`login:ip:${info.ip ?? 'anon'}`, 30, 900), rateLimit(`login:email:${email}`, 12, 900)]);
  if (!byIp.ok || !byEmail.ok) return { ok: false, reason: 'throttled', message: 'Trop de tentatives. Réessayez dans quelques minutes.' };

  const [user] = await db.select().from(users).where(eq(users.email, email)).limit(1);
  if (!user || !user.passwordHash) {
    dummyHash ??= hashPassword(randomToken(12));
    await verifyPassword(password, await dummyHash);
    return { ok: false, reason: 'invalid', message: 'Email ou mot de passe incorrect.' };
  }
  if (user.status !== 'ACTIVE') return { ok: false, reason: 'disabled', message: 'Ce compte est désactivé. Contactez votre collectivité.' };
  if (user.lockedUntil && user.lockedUntil > new Date()) {
    const minutes = Math.ceil((user.lockedUntil.getTime() - Date.now()) / 60_000);
    return { ok: false, reason: 'locked', message: `Compte verrouillé après plusieurs échecs. Réessayez dans ${minutes} min.` };
  }
  const valid = await verifyPassword(password, user.passwordHash);
  if (!valid) {
    const failures = user.failedLoginCount + 1;
    const lock = failures >= MAX_FAILURES;
    await db
      .update(users)
      .set({ failedLoginCount: lock ? 0 : failures, lockedUntil: lock ? new Date(Date.now() + LOCK_MINUTES * 60_000) : null })
      .where(eq(users.id, user.id));
    if (lock) {
      await audit({
        actor: { user },
        category: 'SECURITE',
        action: 'auth.locked',
        summary: `Connexion refusée : ${MAX_FAILURES} tentatives, compte verrouillé ${LOCK_MINUTES} min`,
        targetType: 'user',
        targetId: user.id,
        ip: info.ip,
      });
      await sendEmail(
        securityAlertTemplate({
          to: user.email,
          title: 'Votre compte a été verrouillé temporairement',
          detail: `Plusieurs tentatives de connexion ont échoué. Par sécurité, votre compte est verrouillé ${LOCK_MINUTES} minutes. Si ce n'était pas vous, changez votre mot de passe.`,
        }),
      );
      return { ok: false, reason: 'locked', message: `Trop d'échecs : compte verrouillé ${LOCK_MINUTES} minutes.` };
    }
    return { ok: false, reason: 'invalid', message: 'Email ou mot de passe incorrect.' };
  }
  await db.update(users).set({ failedLoginCount: 0, lockedUntil: null, lastLoginAt: new Date() }).where(eq(users.id, user.id));
  await audit({ actor: { user }, category: 'AUTH', action: 'auth.login', summary: 'Connexion', targetType: 'user', targetId: user.id, ip: info.ip });
  return { ok: true, userId: user.id, mfa: user.mfaEnabled };
}

/** Vérifie un code d'application d'authentification (anti-rejeu) ou un code de secours. */
export async function verifySecondFactor(userId: string, code: string): Promise<boolean> {
  const [user] = await db.select().from(users).where(eq(users.id, userId)).limit(1);
  if (!user?.mfaEnabled || !user.mfaSecretEnc) return false;
  const limited = await rateLimit(`mfa:${userId}`, 8, 900);
  if (!limited.ok) return false;
  const clean = code.replace(/[\s-]/g, '');
  if (/^\d{6}$/.test(clean)) {
    const step = verifyTotp(decrypt(user.mfaSecretEnc), clean);
    return step !== null && (await consumeOnce(`totp:${userId}:${step}`, 120));
  }
  // Code de secours (usage unique)
  const hash = sha256(clean.toUpperCase());
  if (user.mfaRecoveryCodes.includes(hash)) {
    await db
      .update(users)
      .set({ mfaRecoveryCodes: user.mfaRecoveryCodes.filter((c) => c !== hash) })
      .where(eq(users.id, userId));
    await audit({ actor: { user }, category: 'SECURITE', action: 'auth.recovery_code', summary: 'Connexion avec un code de secours', targetType: 'user', targetId: userId });
    return true;
  }
  return false;
}

/** Page d'accueil selon le profil : console, back-office, espace pro ou compte. */
export async function homePathFor(userId: string): Promise<string> {
  const [roles, members] = await Promise.all([
    db.select({ role: roleAssignments.role }).from(roleAssignments).where(eq(roleAssignments.userId, userId)),
    db.select({ id: companyMembers.companyId }).from(companyMembers).where(eq(companyMembers.userId, userId)).limit(1),
  ]);
  if (roles.some((r) => r.role.startsWith('PLATFORM_'))) return '/console';
  if (roles.length) return '/collectivite';
  if (members.length) return '/pro';
  return '/compte';
}

/** N'accepte que des chemins internes (pas de redirection ouverte). */
export function safeNext(next: unknown): string | null {
  if (typeof next !== 'string' || !next.startsWith('/') || next.startsWith('//') || next.startsWith('/\\')) return null;
  return next.slice(0, 500);
}

/** Jeton de réinitialisation de mot de passe (1 h, usage unique). */
export async function createPasswordResetToken(email: string): Promise<{ token: string; userId: string } | null> {
  const [user] = await db.select().from(users).where(and(eq(users.email, email.trim().toLowerCase()), eq(users.status, 'ACTIVE'))).limit(1);
  if (!user) return null;
  const token = randomToken(32);
  await db.insert(tokens).values({
    kind: 'PASSWORD_RESET',
    tokenHash: sha256(token),
    userId: user.id,
    email: user.email,
    expiresAt: new Date(Date.now() + 3_600_000),
  });
  return { token, userId: user.id };
}

export async function findValidToken(kind: 'PASSWORD_RESET' | 'INVITE_MEMBER' | 'INVITE_STAFF' | 'EMAIL_VERIFY' | 'INVITE_CLAIM', token: string) {
  if (!/^[A-Za-z0-9_-]{20,80}$/.test(token)) return null;
  const [row] = await db
    .select()
    .from(tokens)
    .where(and(eq(tokens.tokenHash, sha256(token)), eq(tokens.kind, kind), isNull(tokens.usedAt), gt(tokens.expiresAt, new Date())))
    .limit(1);
  return row ?? null;
}
