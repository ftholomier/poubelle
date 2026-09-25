import { and, eq } from 'drizzle-orm';
import { NextResponse } from 'next/server';
import { db } from '@/server/db';
import { campaigns, communes } from '@/server/db/schema';
import { campaignPosterA5 } from '@/server/print/kit';
import { qrPng, qrSvg } from '@/server/qr';
import { boApiContext } from '@/server/services/bo-api';
import { campaignScope, shortLegalName } from '@/server/services/backoffice';
import { portalUrl } from '@/server/urls';

const FILES = ['affiche.pdf', 'qr.png', 'qr.svg'] as const;
const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

function period(start: string, end: string): string {
  const [, ms, ds] = start.split('-').map(Number);
  const [, me, de] = end.split('-').map(Number);
  const d = (n: number) => (n === 1 ? '1er' : String(n));
  return ms === me ? `Du ${d(ds)} au ${de} ${MONTHS[me - 1]}` : `Du ${d(ds)} ${MONTHS[ms - 1]} au ${de} ${MONTHS[me - 1]}`;
}

/** QR code et affiche d'une campagne (lien vers la page publique, mesuré comme visite « qr »). */
export async function GET(_req: Request, { params }: { params: Promise<{ id: string; file: string }> }) {
  const { id, file } = await params;
  if (!/^[0-9a-f-]{36}$/.test(id) || !FILES.includes(file as (typeof FILES)[number])) return new NextResponse('Introuvable', { status: 404 });
  const ctx = await boApiContext();
  if (!ctx) return new NextResponse('Non autorisé', { status: 401 });
  const [c] = await db
    .select()
    .from(campaigns)
    .where(and(eq(campaigns.id, id), campaignScope(ctx)))
    .limit(1);
  if (!c) return new NextResponse('Introuvable', { status: 404 });
  const url = portalUrl(ctx.territory, `/campagnes/${c.slug}?src=qr`);
  const send = (body: Uint8Array | Buffer | string, type: string, name: string) =>
    new NextResponse(typeof body === 'string' ? body : new Uint8Array(body), {
      headers: { 'content-type': type, 'content-disposition': `attachment; filename="${name}"`, 'cache-control': 'private, max-age=300' },
    });
  if (file === 'qr.png') return send(await qrPng(url, { width: 2048 }), 'image/png', `${c.slug}-qr.png`);
  if (file === 'qr.svg') return send(await qrSvg(url, { withSymbol: true }), 'image/svg+xml', `${c.slug}-qr.svg`);
  const [owner] = c.communeId ? await db.select({ name: communes.name }).from(communes).where(eq(communes.id, c.communeId)).limit(1) : [];
  const light = (() => {
    const n = parseInt(c.colorBg.slice(1), 16);
    return 0.299 * ((n >> 16) & 255) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255) > 170;
  })();
  const pdf = await campaignPosterA5({
    name: c.name,
    tagline: c.tagline,
    period: period(c.startsAt, c.endsAt),
    territoryName: ctx.territory.name,
    organizer: owner ? `Mairie de ${owner.name}` : shortLegalName(ctx.territory.legalName),
    bg: c.colorBg,
    fg: light ? '#14201B' : c.colorText,
    url,
    displayUrl: portalUrl(ctx.territory, `/campagnes/${c.slug}`).replace(/^https?:\/\//, ''),
  });
  return send(pdf, 'application/pdf', `${c.slug}-affiche.pdf`);
}
