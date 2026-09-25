import { eq } from 'drizzle-orm';
import { NextResponse } from 'next/server';
import { getActor } from '@/server/authz';
import { db } from '@/server/db';
import { emails } from '@/server/db/schema';

/** Aperçu HTML d'un email émis, servi dans un cadre isolé (aucun script, aucune ressource active). */
export async function GET(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  if (!/^[0-9a-f-]{36}$/.test(id)) return new NextResponse('Introuvable', { status: 404 });
  const actor = await getActor();
  if (!actor || !actor.isPlatformStaff || !actor.session.mfaVerified) return new NextResponse('Non autorisé', { status: 401 });
  const [mail] = await db.select({ html: emails.html }).from(emails).where(eq(emails.id, id)).limit(1);
  if (!mail) return new NextResponse('Introuvable', { status: 404 });
  return new NextResponse(mail.html, {
    headers: {
      'content-type': 'text/html; charset=utf-8',
      'cache-control': 'private, no-store',
      'content-security-policy': "default-src 'none'; img-src https: data:; style-src 'unsafe-inline'; font-src https: data:; sandbox",
      'x-content-type-options': 'nosniff',
    },
  });
}
