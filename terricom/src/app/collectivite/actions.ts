'use server';

import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import { BO_SCOPE_COOKIE, loadBoContext } from '@/server/services/backoffice';

/** Change le périmètre affiché (territoire ou commune) parmi ceux de l'agent. */
export async function switchScopeAction(form: FormData): Promise<void> {
  const ctx = await loadBoContext();
  const key = String(form.get('scope') ?? '');
  if (!ctx.scopes.some((s) => s.key === key)) return;
  const jar = await cookies();
  jar.set(BO_SCOPE_COOKIE, key, { httpOnly: true, sameSite: 'lax', secure: process.env.NODE_ENV === 'production', path: '/collectivite', maxAge: 60 * 60 * 24 * 90 });
  redirect('/collectivite');
}
