import { eq } from 'drizzle-orm';
import { z } from 'zod';
import { audit } from '../audit';
import { hashPassword, passwordIssues } from '../crypto';
import { db } from '../db';
import { users } from '../db/schema';
import { requestInfo } from '../request';
import { rateLimit } from './rate-limit';
import { createSession } from './session';

const accountSchema = z.object({
  firstName: z.string().trim().min(1, 'Indiquez votre prénom').max(120),
  lastName: z.string().trim().min(1, 'Indiquez votre nom').max(120),
  email: z.string().trim().toLowerCase().email('Adresse email invalide').max(255),
  password: z.string().max(200),
  role: z.string().trim().max(120).optional(),
  website: z.string().max(0, 'Formulaire invalide').optional(), // pot de miel
});

export type NewAccount = { ok: true; user: typeof users.$inferSelect } | { ok: false; message: string; exists?: boolean };

/**
 * Création d'un compte professionnel depuis un formulaire (revendication, inscription) :
 * limitation par IP, pot de miel, mot de passe robuste, CGU acceptées, session ouverte.
 */
export async function createAccountFromForm(form: FormData, context: string): Promise<NewAccount> {
  const info = await requestInfo();
  const limited = await rateLimit(`signup:ip:${info.ip ?? 'anon'}`, 10, 3600);
  if (!limited.ok) return { ok: false, message: 'Trop de créations de compte depuis cette connexion. Réessayez plus tard.' };
  const parsed = accountSchema.safeParse(Object.fromEntries(form));
  if (!parsed.success) return { ok: false, message: parsed.error.issues[0]?.message ?? 'Formulaire incomplet.' };
  const d = parsed.data;
  const issue = passwordIssues(d.password);
  if (issue) return { ok: false, message: issue };
  if (!form.get('cgu')) return { ok: false, message: 'Acceptez les conditions d’utilisation pour continuer.' };
  const [existing] = await db.select({ id: users.id }).from(users).where(eq(users.email, d.email)).limit(1);
  if (existing) return { ok: false, exists: true, message: 'Un compte existe déjà avec cette adresse : connectez-vous pour continuer votre demande.' };
  const [user] = await db
    .insert(users)
    .values({ email: d.email, firstName: d.firstName, lastName: d.lastName, jobTitle: d.role || null, passwordHash: await hashPassword(d.password), passwordChangedAt: new Date() })
    .returning();
  await audit({ actor: { user }, category: 'AUTH', action: 'user.signup', summary: `Création de compte (${context})`, targetType: 'user', targetId: user.id, ip: info.ip });
  await createSession(user.id, { mfaVerified: true });
  return { ok: true, user };
}
