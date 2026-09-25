import { NextResponse, type NextRequest } from 'next/server';
import { fmtPhone } from '@/lib/format';
import { canManageEstablishment, getActor } from '@/server/authz';
import { businessCard, KIT_STYLES, posterA5, stickerRound, type KitStyle } from '@/server/print/kit';
import { qrPng, qrSvg } from '@/server/qr';
import { getEstablishmentDetailById } from '@/server/services/establishments';
import { getTerritoryById } from '@/server/services/territories';
import { portalUrl, qrUrl } from '@/server/urls';

const FILES = ['affichette.pdf', 'autocollant.pdf', 'carte.pdf', 'qr.png', 'qr.svg'] as const;

/** Téléchargements du kit vitrine (PDF prêts à imprimer, QR code haute définition). */
export async function GET(req: NextRequest, { params }: { params: Promise<{ est: string; file: string }> }) {
  const { est: estId, file } = await params;
  if (!/^[0-9a-f-]{36}$/.test(estId) || !FILES.includes(file as (typeof FILES)[number])) return new NextResponse('Introuvable', { status: 404 });
  const actor = await getActor();
  if (!actor || (actor.user.mfaEnabled && !actor.session.mfaVerified)) return new NextResponse('Non autorisé', { status: 401 });
  const est = await getEstablishmentDetailById(estId);
  if (!est || !canManageEstablishment(actor, est)) return new NextResponse('Introuvable', { status: 404 });
  const t = await getTerritoryById(est.territoryId);
  if (!t) return new NextResponse('Introuvable', { status: 404 });
  const styleParam = req.nextUrl.searchParams.get('style') ?? 'vert';
  const style = (styleParam in KIT_STYLES ? styleParam : 'vert') as KitStyle;
  const url = qrUrl(t, est.qrCode);
  const input = {
    name: est.name,
    activity: est.activity,
    address: [est.street, [est.postalCode ?? est.commune.postalCodes[0], est.commune.name].filter(Boolean).join(' ')].filter(Boolean).join(', ') || null,
    phone: est.phone ? fmtPhone(est.phone) : null,
    territoryName: t.name,
    url,
    displayUrl: portalUrl(t, est.path).replace(/^https?:\/\//, ''),
    shortUrl: url.replace(/^https?:\/\//, ''),
  };
  const base = `${est.slug}-${file.replace('.', '-')}`;
  const send = (body: Uint8Array | Buffer | string, type: string, name: string) =>
    new NextResponse(typeof body === 'string' ? body : new Uint8Array(body), {
      headers: { 'content-type': type, 'content-disposition': `attachment; filename="${name}"`, 'cache-control': 'private, max-age=300' },
    });
  switch (file) {
    case 'affichette.pdf':
      return send(await posterA5(input, style), 'application/pdf', `${base}-${style}.pdf`);
    case 'autocollant.pdf':
      return send(await stickerRound(input, style), 'application/pdf', `${base}-${style}.pdf`);
    case 'carte.pdf':
      return send(await businessCard(input, style), 'application/pdf', `${base}-${style}.pdf`);
    case 'qr.png':
      return send(await qrPng(url, { width: 2048 }), 'image/png', `${est.slug}-qr.png`);
    default:
      return send(await qrSvg(url, { withSymbol: true }), 'image/svg+xml', `${est.slug}-qr.svg`);
  }
}
