'use server';

import { and, eq } from 'drizzle-orm';
import { redirect } from 'next/navigation';
import { z } from 'zod';
import { audit } from '@/server/audit';
import { findValidToken } from '@/server/auth/login';
import { createSession, getSession } from '@/server/auth/session';
import { hashPassword, passwordIssues } from '@/server/crypto';
import { db } from '@/server/db';
import { companyMembers, roleAssignments, tokens, users } from '@/server/db/schema';

export type InviteState = { status: 'idle' | 'error'; message?: string };

/** Acceptation d'une invitation (collaborateur d'entreprise ou agent de collectivité). */
export async function acceptInvitation(_prev: InviteState, form: FormData): Promise<InviteState> {
  const token = String(form.get('token') ?? '');
  const row = (await findValidToken('INVITE_MEMBER', token)) ?? (await findValidToken('INVITE_STAFF', token));
  if (!row || !row.email) return { status: 'error', message: 'Cette invitation a expiré. Demandez-en une nouvelle.' };
  const session = await getSession();
  let userId: string;
  if (session && session.user.email.toLowerCase() === row.email.toLowerCase()) {
    userId = session.user.id;
  } else {
    const [existing] = await db.select().from(users).where(eq(users.email, row.email)).limit(1);
    if (existing) return { status: 'error', message: `Un compte existe déjà pour ${row.email} : connectez-vous d'abord, puis rouvrez ce lien.` };
    const parsed = z
      .object({ firstName: z.string().trim().min(1, 'Prénom requis').max(120), lastName: z.string().trim().min(1, 'Nom requis').max(120), password: z.string() })
      .safeParse(Object.fromEntries(form));
    if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
    const issue = passwordIssues(parsed.data.password);
    if (issue) return { status: 'error', message: issue };
    const [u] = await db
      .insert(users)
      .values({ email: row.email, firstName: parsed.data.firstName, lastName: parsed.data.lastName, passwordHash: await hashPassword(parsed.data.password), emailVerifiedAt: new Date() })
      .returning();
    userId = u.id;
  }
  const payload = row.payload as { companyId?: string; role?: string; establishmentId?: string; staffRole?: string; territoryId?: string; communeId?: string | null };
  await db.transaction(async (tx) => {
    if (row.kind === 'INVITE_MEMBER' && payload.companyId) {
      await tx
        .insert(companyMembers)
        .values({ companyId: payload.companyId, userId, role: payload.role === 'OWNER' ? 'OWNER' : 'EDITOR' })
        .onConflictDoNothing();
    }
    if (row.kind === 'INVITE_STAFF' && payload.staffRole && payload.territoryId) {
      const exists = await tx
        .select()
        .from(roleAssignments)
        .where(and(eq(roleAssignments.userId, userId), eq(roleAssignments.territoryId, payload.territoryId)))
        .limit(1);
      if (!exists.length)
        await tx.insert(roleAssignments).values({
          userId,
          role: payload.staffRole as 'TERRITORY_ADMIN',
          territoryId: payload.territoryId,
          communeId: payload.communeId ?? null,
        });
    }
    await tx.update(tokens).set({ usedAt: new Date() }).where(eq(tokens.id, row.id));
  });
  await audit({ actor: { id: userId, label: row.email }, category: 'SECURITE', action: 'invitation.accepted', summary: `Invitation acceptée par ${row.email}`, territoryId: payload.territoryId ?? null, targetType: 'user', targetId: userId });
  if (!session || session.user.id !== userId) await createSession(userId, { mfaVerified: true });
  if (row.kind === 'INVITE_STAFF') redirect('/compte/securite?mfa=obligatoire');
  redirect(payload.establishmentId ? `/pro/${payload.establishmentId}` : '/pro');
}
