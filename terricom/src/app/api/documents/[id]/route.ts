import { eq } from 'drizzle-orm';
import { NextResponse } from 'next/server';
import { audit } from '@/server/audit';
import { canManageEstablishment, getActor, territoryAccess } from '@/server/authz';
import { db } from '@/server/db';
import { claims, establishments, media } from '@/server/db/schema';
import { storage } from '@/server/storage';

/**
 * Documents privés (CV des candidats, Kbis des revendications) : jamais publics,
 * servis uniquement aux personnes habilitées, et chaque consultation est journalisée.
 */
export async function GET(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  if (!/^[0-9a-f-]{36}$/.test(id)) return new NextResponse('Introuvable', { status: 404 });
  const actor = await getActor();
  if (!actor || (actor.user.mfaEnabled && !actor.session.mfaVerified)) return new NextResponse('Non autorisé', { status: 401 });
  const [doc] = await db.select().from(media).where(eq(media.id, id)).limit(1);
  if (!doc || doc.kind !== 'DOCUMENT' || !doc.storageKey) return new NextResponse('Introuvable', { status: 404 });
  let allowed = actor.isPlatformAdmin;
  if (!allowed && doc.ownerType === 'JOB_APPLICATION' && doc.establishmentId) {
    const [est] = await db.select().from(establishments).where(eq(establishments.id, doc.establishmentId)).limit(1);
    const role = est ? canManageEstablishment(actor, est) : null;
    allowed = role === 'OWNER' || role === 'MEMBER';
  }
  if (!allowed && doc.ownerType === 'CLAIM_KBIS') {
    const [claim] = await db.select().from(claims).where(eq(claims.kbisMediaId, doc.id)).limit(1);
    allowed = Boolean(claim && (territoryAccess(actor, claim.territoryId) || claim.userId === actor.user.id));
  }
  if (!allowed && doc.ownerType === 'DEAL_DOCUMENT') allowed = actor.isPlatformStaff;
  if (!allowed) return new NextResponse('Introuvable', { status: 404 });
  const file = await storage().get(doc.storageKey);
  if (!file) return new NextResponse('Introuvable', { status: 404 });
  await audit({
    actor: { user: actor.user },
    category: 'RGPD',
    action: 'document.viewed',
    summary: `Consultation d'un document privé (${doc.ownerType === 'CLAIM_KBIS' ? 'Kbis' : doc.ownerType === 'DEAL_DOCUMENT' ? 'document commercial' : 'CV'})`,
    territoryId: doc.territoryId,
    targetType: 'media',
    targetId: doc.id,
  });
  return new NextResponse(new Uint8Array(file.body), {
    headers: {
      'content-type': 'application/pdf',
      'content-disposition': 'inline; filename="document.pdf"',
      'cache-control': 'private, no-store',
      'x-content-type-options': 'nosniff',
      'content-security-policy': "default-src 'none'; sandbox",
    },
  });
}
