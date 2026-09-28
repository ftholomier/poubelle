'use server';

import { eq } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { z } from 'zod';
import type { ActionState } from '@/app/pro/[est]/actions';
import { audit } from '@/server/audit';
import { requirePlatformStaff } from '@/server/authz';
import { db } from '@/server/db';
import { emails } from '@/server/db/schema';
import { env } from '@/server/env';
import { enqueue } from '@/server/queue';

export async function resendEmailAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await requirePlatformStaff();
  const id = z.string().uuid().safeParse(form.get('id'));
  if (!id.success) return { status: 'error', message: 'Email invalide.' };
  const [mail] = await db.select().from(emails).where(eq(emails.id, id.data)).limit(1);
  if (!mail || mail.status !== 'FAILED') return { status: 'error', message: 'Seuls les emails en échec se renvoient.' };
  if (env.MAIL_DRIVER !== 'smtp') return { status: 'error', message: 'Envoi SMTP non configuré (mode boîte d’envoi).' };
  await db.update(emails).set({ status: 'QUEUED', error: null }).where(eq(emails.id, mail.id));
  await enqueue('email.send', { emailId: mail.id });
  await audit({
    actor: { user: actor.user },
    category: 'ENVOI',
    action: 'email.resend',
    summary: `Renvoi de l’email « ${mail.subject} » à ${mail.to}`,
    territoryId: mail.territoryId,
    targetType: 'email',
    targetId: mail.id,
  });
  revalidatePath('/console/emails');
  return { status: 'ok', message: 'Email remis en file d’envoi.' };
}
