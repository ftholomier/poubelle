import { eq } from 'drizzle-orm';
import { NextResponse } from 'next/server';
import { audit } from '@/server/audit';
import { getActor } from '@/server/authz';
import { db } from '@/server/db';
import { privacyRequests } from '@/server/db/schema';
import { personalDataFor } from '@/server/services/privacy';

/** Export JSON des données d'une personne (demande d'accès), à transmettre au demandeur. */
export async function GET(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  if (!/^[0-9a-f-]{36}$/.test(id)) return new NextResponse('Introuvable', { status: 404 });
  const actor = await getActor();
  if (!actor || !actor.isPlatformStaff || !actor.session.mfaVerified) return new NextResponse('Non autorisé', { status: 401 });
  if (!actor.isPlatformAdmin && !actor.roles.some((r) => r.role === 'PLATFORM_SUPPORT')) return new NextResponse('Introuvable', { status: 404 });
  const [req] = await db.select().from(privacyRequests).where(eq(privacyRequests.id, id)).limit(1);
  if (!req) return new NextResponse('Introuvable', { status: 404 });
  const data = await personalDataFor(req.email);
  await audit({
    actor: { user: actor.user },
    category: 'RGPD',
    action: 'privacy.exported',
    summary: `Export généré pour la demande RGPD n°${req.number}`,
    territoryId: req.territoryId,
    targetType: 'privacy_request',
    targetId: req.id,
  });
  return new NextResponse(JSON.stringify({ request: { number: req.number, kind: req.kind, receivedAt: req.createdAt }, ...data }, null, 2), {
    headers: {
      'content-type': 'application/json; charset=utf-8',
      'content-disposition': `attachment; filename="demande-rgpd-${req.number}.json"`,
      'cache-control': 'private, no-store',
    },
  });
}
