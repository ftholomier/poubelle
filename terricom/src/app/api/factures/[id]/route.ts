import { eq } from 'drizzle-orm';
import { NextResponse } from 'next/server';
import { getActor, isCompanyMember, territoryAccess } from '@/server/authz';
import { db } from '@/server/db';
import { invoices } from '@/server/db/schema';
import { invoicePdf } from '@/server/print/invoice';

/** Téléchargement d'une facture : entreprise cliente, collectivité cliente ou exploitant. */
export async function GET(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const { id } = await params;
  const clean = id.replace(/\.pdf$/, '');
  if (!/^[0-9a-f-]{36}$/.test(clean)) return new NextResponse('Introuvable', { status: 404 });
  const actor = await getActor();
  if (!actor || (actor.user.mfaEnabled && !actor.session.mfaVerified)) return new NextResponse('Non autorisé', { status: 401 });
  const [inv] = await db.select().from(invoices).where(eq(invoices.id, clean)).limit(1);
  if (!inv) return new NextResponse('Introuvable', { status: 404 });
  const allowed =
    actor.isPlatformStaff ||
    (inv.companyId && isCompanyMember(actor, inv.companyId, 'OWNER')) ||
    (inv.territoryId && territoryAccess(actor, inv.territoryId) === 'ADMIN');
  if (!allowed) return new NextResponse('Introuvable', { status: 404 });
  return new NextResponse(new Uint8Array(await invoicePdf(inv)), {
    headers: { 'content-type': 'application/pdf', 'content-disposition': `inline; filename="facture-${inv.number}.pdf"`, 'cache-control': 'private, no-store' },
  });
}
