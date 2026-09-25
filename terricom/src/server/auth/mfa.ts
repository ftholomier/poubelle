import { eq } from 'drizzle-orm';
import QRCode from 'qrcode';
import { audit } from '../audit';
import { decrypt, encrypt, randomToken, sha256 } from '../crypto';
import { db } from '../db';
import { users } from '../db/schema';
import { consumeOnce, rateLimit } from './rate-limit';
import { markMfaVerified } from './session';
import { generateSecret, otpauthUrl, verifyTotp } from './totp';

/**
 * Double authentification (TOTP) : enrôlement en deux temps (secret provisoire puis
 * confirmation par un premier code), codes de secours à usage unique.
 */

export type Enrollment = { secret: string; qrSvg: string; otpauth: string };

function formatSecret(secret: string): string {
  return secret.replace(/(.{4})/g, '$1 ').trim();
}

/** Prépare (ou reprend) un enrôlement : le secret reste inactif tant qu'il n'est pas confirmé. */
export async function startEnrollment(userId: string): Promise<Enrollment | null> {
  const [user] = await db.select().from(users).where(eq(users.id, userId)).limit(1);
  if (!user || user.mfaEnabled) return null;
  let secret = user.mfaSecretEnc ? decrypt(user.mfaSecretEnc) : null;
  if (!secret) {
    secret = generateSecret();
    await db.update(users).set({ mfaSecretEnc: encrypt(secret) }).where(eq(users.id, userId));
  }
  const otpauth = otpauthUrl(secret, user.email);
  const qrSvg = await QRCode.toString(otpauth, { type: 'svg', margin: 1, errorCorrectionLevel: 'M', color: { dark: '#14201B', light: '#FFFFFF' } });
  return { secret: formatSecret(secret), qrSvg, otpauth };
}

/** Génère 10 codes de secours (affichés une seule fois, stockés hachés). */
function newRecoveryCodes(): { plain: string[]; hashes: string[] } {
  const plain = Array.from({ length: 10 }, () => {
    const raw = randomToken(8).replace(/[^A-Za-z0-9]/g, '').toUpperCase().padEnd(8, 'X').slice(0, 8);
    return `${raw.slice(0, 4)}-${raw.slice(4)}`;
  });
  return { plain, hashes: plain.map((c) => sha256(c.replace('-', ''))) };
}

/** Confirme l'enrôlement avec un premier code ; renvoie les codes de secours. */
export async function confirmEnrollment(userId: string, code: string): Promise<{ ok: true; recoveryCodes: string[] } | { ok: false; message: string }> {
  const limited = await rateLimit(`mfa-enroll:${userId}`, 10, 900);
  if (!limited.ok) return { ok: false, message: 'Trop d’essais. Réessayez dans quelques minutes.' };
  const [user] = await db.select().from(users).where(eq(users.id, userId)).limit(1);
  if (!user?.mfaSecretEnc) return { ok: false, message: 'Recommencez l’activation.' };
  if (user.mfaEnabled) return { ok: false, message: 'La double authentification est déjà active.' };
  const step = verifyTotp(decrypt(user.mfaSecretEnc), code);
  if (step === null || !(await consumeOnce(`totp:${userId}:${step}`, 120)))
    return { ok: false, message: 'Code incorrect. Vérifiez l’heure de votre téléphone et réessayez.' };
  const codes = newRecoveryCodes();
  await db.update(users).set({ mfaEnabled: true, mfaRecoveryCodes: codes.hashes }).where(eq(users.id, userId));
  await markMfaVerified();
  await audit({ actor: { user }, category: 'SECURITE', action: 'mfa.enabled', summary: 'Double authentification activée', targetType: 'user', targetId: userId });
  return { ok: true, recoveryCodes: codes.plain };
}

/** Nouveaux codes de secours (les anciens sont invalidés). */
export async function regenerateRecoveryCodes(userId: string): Promise<string[] | null> {
  const [user] = await db.select().from(users).where(eq(users.id, userId)).limit(1);
  if (!user?.mfaEnabled) return null;
  const codes = newRecoveryCodes();
  await db.update(users).set({ mfaRecoveryCodes: codes.hashes }).where(eq(users.id, userId));
  await audit({ actor: { user }, category: 'SECURITE', action: 'mfa.recovery_regenerated', summary: 'Nouveaux codes de secours générés', targetType: 'user', targetId: userId });
  return codes.plain;
}

/** Désactivation (impossible pour les comptes dont le rôle l'impose). */
export async function disableMfa(userId: string, code: string): Promise<{ ok: boolean; message: string }> {
  const [user] = await db.select().from(users).where(eq(users.id, userId)).limit(1);
  if (!user?.mfaEnabled || !user.mfaSecretEnc) return { ok: false, message: 'La double authentification n’est pas active.' };
  const limited = await rateLimit(`mfa:${userId}`, 8, 900);
  if (!limited.ok) return { ok: false, message: 'Trop d’essais. Réessayez dans quelques minutes.' };
  const step = verifyTotp(decrypt(user.mfaSecretEnc), code);
  if (step === null || !(await consumeOnce(`totp:${userId}:${step}`, 120))) return { ok: false, message: 'Code incorrect.' };
  await db.update(users).set({ mfaEnabled: false, mfaSecretEnc: null, mfaRecoveryCodes: [] }).where(eq(users.id, userId));
  await audit({ actor: { user }, category: 'SECURITE', action: 'mfa.disabled', summary: 'Double authentification désactivée', targetType: 'user', targetId: userId });
  return { ok: true, message: 'Double authentification désactivée.' };
}
