import { eq } from 'drizzle-orm';
import { NextResponse, type NextRequest } from 'next/server';
import { PUBLIC_STATUSES } from '@/lib/constants';
import { track } from '@/server/analytics';
import { db } from '@/server/db';
import { categories, communes, establishments, territories } from '@/server/db/schema';
import { publicOrigin } from '@/server/request';
import { stampVisitorPassportsAt } from '@/server/services/circuits';
import { portalUrl } from '@/server/urls';

/**
 * QR code imprimé d'une fiche (vitrine, flyer, carte de visite) : lien court permanent
 * qui mène à la fiche du professionnel et comptabilise le scan.
 */
export async function GET(req: NextRequest, { params }: { params: Promise<{ code: string }> }) {
  const { code } = await params;
  if (!/^[A-Za-z0-9_-]{4,16}$/.test(code)) return NextResponse.redirect(new URL('/', publicOrigin(req.headers)), 302);
  const [row] = await db
    .select({ e: establishments, c: communes, k: categories, t: territories })
    .from(establishments)
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .innerJoin(categories, eq(categories.id, establishments.categoryId))
    .innerJoin(territories, eq(territories.id, establishments.territoryId))
    .where(eq(establishments.qrCode, code))
    .limit(1);
  if (!row || !PUBLIC_STATUSES.includes(row.e.status)) return NextResponse.redirect(new URL('/', publicOrigin(req.headers)), 302);
  await track({
    type: 'QR_SCAN',
    territoryId: row.t.id,
    establishmentId: row.e.id,
    communeId: row.c.id,
    source: 'QR',
    userAgent: req.headers.get('user-agent'),
    ip: (req.headers.get('x-forwarded-for')?.split(',')[0] ?? '').trim() || null,
  });
  // Étape d'un circuit en cours pour ce visiteur : le scan vaut tampon.
  const stamped = await stampVisitorPassportsAt(row.e.id).catch(() => null);
  if (stamped) return NextResponse.redirect(portalUrl(row.t, `/circuits/${stamped.circuitSlug}?tampon=${stamped.position + 1}`), 302);
  const target = portalUrl(row.t, `/${row.c.slug}/${row.k.slug}/${row.e.slug}?src=qr`);
  return NextResponse.redirect(target, 302);
}
