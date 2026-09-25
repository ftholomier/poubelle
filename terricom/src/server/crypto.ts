import {
  createCipheriv,
  createDecipheriv,
  createHash,
  createHmac,
  randomBytes,
  randomInt,
  scrypt,
  timingSafeEqual,
  type ScryptOptions,
} from 'node:crypto';
import { env } from './env';

/** Jeton aléatoire URL-safe (256 bits par défaut). */
export function randomToken(bytes = 32): string {
  return randomBytes(bytes).toString('base64url');
}

export function sha256(input: string | Buffer): string {
  return createHash('sha256').update(input).digest('hex');
}

export function hmacSha256(key: string | Buffer, input: string): string {
  return createHmac('sha256', key).update(input).digest('hex');
}

export function safeEqual(a: string, b: string): boolean {
  const ba = Buffer.from(a);
  const bb = Buffer.from(b);
  if (ba.length !== bb.length) return false;
  return timingSafeEqual(ba, bb);
}

const CODE_ALPHABET = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // sans 0/O/1/I ambigus

/** Code court lisible (QR codes, codes de récompense). */
export function shortCode(length = 8): string {
  let out = '';
  for (let i = 0; i < length; i++) out += CODE_ALPHABET[randomInt(CODE_ALPHABET.length)];
  return out;
}

/** Code numérique (vérification par courrier/SMS). */
export function numericCode(length = 6): string {
  let out = '';
  for (let i = 0; i < length; i++) out += String(randomInt(10));
  return out;
}

// ─── Mots de passe (scrypt, sel aléatoire, paramètres encodés) ─────────────
const SCRYPT: ScryptOptions & { N: number; r: number; p: number } = { N: 1 << 15, r: 8, p: 1, maxmem: 64 * 1024 * 1024 };

function scryptAsync(password: string, salt: Buffer, keylen: number, opts: ScryptOptions): Promise<Buffer> {
  return new Promise((resolve, reject) =>
    scrypt(password.normalize('NFKC'), salt, keylen, opts, (err, key) => (err ? reject(err) : resolve(key))),
  );
}

export async function hashPassword(password: string): Promise<string> {
  const salt = randomBytes(16);
  const key = await scryptAsync(password, salt, 64, SCRYPT);
  return `scrypt$${SCRYPT.N}$${SCRYPT.r}$${SCRYPT.p}$${salt.toString('base64')}$${key.toString('base64')}`;
}

export async function verifyPassword(password: string, stored: string | null | undefined): Promise<boolean> {
  if (!stored) return false;
  const [algo, n, r, p, saltB64, keyB64] = stored.split('$');
  if (algo !== 'scrypt' || !saltB64 || !keyB64) return false;
  const expected = Buffer.from(keyB64, 'base64');
  const key = await scryptAsync(password, Buffer.from(saltB64, 'base64'), expected.length, {
    N: Number(n),
    r: Number(r),
    p: Number(p),
    maxmem: 128 * 1024 * 1024,
  });
  return key.length === expected.length && timingSafeEqual(key, expected);
}

/** Politique de mot de passe : 10 caractères minimum, au moins 3 types de caractères. */
export function passwordIssues(password: string): string | null {
  if (password.length < 10) return 'Le mot de passe doit contenir au moins 10 caractères.';
  const classes = [/[a-z]/, /[A-Z]/, /\d/, /[^A-Za-z0-9]/].filter((re) => re.test(password)).length;
  if (classes < 3) return 'Mélangez au moins trois types : minuscules, majuscules, chiffres, symboles.';
  if (/^(.)\1+$/.test(password)) return 'Ce mot de passe est trop simple.';
  return null;
}

// ─── Chiffrement des données sensibles (AES-256-GCM) ───────────────────────
function dataKey(): Buffer {
  const raw = Buffer.from(env.DATA_ENCRYPTION_KEY, 'base64');
  return raw.length === 32 ? raw : createHash('sha256').update(env.DATA_ENCRYPTION_KEY).digest();
}

export function encrypt(plain: string): string {
  const iv = randomBytes(12);
  const cipher = createCipheriv('aes-256-gcm', dataKey(), iv);
  const enc = Buffer.concat([cipher.update(plain, 'utf8'), cipher.final()]);
  const tag = cipher.getAuthTag();
  return `v1.${iv.toString('base64url')}.${tag.toString('base64url')}.${enc.toString('base64url')}`;
}

export function decrypt(payload: string): string {
  const [v, ivB, tagB, encB] = payload.split('.');
  if (v !== 'v1') throw new Error('Format chiffré inconnu');
  const decipher = createDecipheriv('aes-256-gcm', dataKey(), Buffer.from(ivB, 'base64url'));
  decipher.setAuthTag(Buffer.from(tagB, 'base64url'));
  return Buffer.concat([decipher.update(Buffer.from(encB, 'base64url')), decipher.final()]).toString('utf8');
}

/** Empreinte non réversible d'une adresse IP (journalisation, anti-abus). */
export function ipHash(ip: string | null | undefined): string | null {
  if (!ip) return null;
  return hmacSha256(env.SESSION_SECRET, `ip:${ip}`).slice(0, 32);
}
