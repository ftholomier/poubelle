import { NextResponse, type NextRequest } from 'next/server';
import { track } from '@/server/analytics';
import { rateLimit } from '@/server/auth/rate-limit';
import { ensureVisitorPassport, stampPassport, stopsBySecret } from '@/server/services/circuits';
import { publicOrigin } from '@/server/request';
import { resolveTerritoryParam } from '@/server/services/territories';

/**
 * Scan du QR code d'une étape de circuit (affiché en vitrine) : tamponne le passeport
 * anonyme du visiteur puis l'emmène sur la page du circuit.
 */
export async function GET(req: NextRequest, { params }: { params: Promise<{ territory: string; secret: string }> }) {
  const { territory: param, secret } = await params;
  const territory = await resolveTerritoryParam(param);
  const hostMode = req.headers.get('x-terricom-portal-mode') === 'host';
  const base = hostMode || !territory ? '' : `/${territory.slug}`;
  const ip = (req.headers.get('x-forwarded-for')?.split(',')[0] ?? '').trim() || 'anon';
  const limited = await rateLimit(`stamp:${ip}`, 30, 3600);
  const found = territory && limited.ok && /^[A-Za-z0-9_-]{8,40}$/.test(secret) ? await stopsBySecret(secret) : null;
  if (!territory || !found || found.circuit.territoryId !== territory.id || found.circuit.status !== 'PUBLISHED') {
    return NextResponse.redirect(new URL(`${base}/circuits?tampon=invalide`, publicOrigin(req.headers)), 303);
  }
  const passportId = await ensureVisitorPassport(found.circuit.id);
  const res = await stampPassport(passportId, found.stop.id);
  await track({
    type: 'STAMP',
    territoryId: territory.id,
    establishmentId: found.stop.establishmentId,
    refId: found.circuit.id,
    source: 'QR',
    userAgent: req.headers.get('user-agent'),
    ip,
  });
  const target = new URL(`${base}/circuits/${found.circuit.slug}?tampon=${(res?.position ?? 0) + 1}`, publicOrigin(req.headers));
  return NextResponse.redirect(target, 303);
}
