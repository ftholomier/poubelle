import { and, asc, eq } from 'drizzle-orm';
import { NextResponse } from 'next/server';
import { rgb } from 'pdf-lib';
import { db } from '@/server/db';
import { circuitStops, circuits, establishments } from '@/server/db/schema';
import { drawQr, hex, newDoc, wrap } from '@/server/print/kit';
import { boApiContext } from '@/server/services/bo-api';
import { portalUrl } from '@/server/urls';

const MM = 2.8346;

/** Planche des tampons d'un circuit : un QR code par étape, à coller en vitrine. */
export async function GET(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const ctx = await boApiContext();
  if (!ctx) return new NextResponse('Non autorisé', { status: 401 });
  const { id } = await params;
  if (!/^[0-9a-f-]{36}$/.test(id)) return new NextResponse('Introuvable', { status: 404 });
  const [c] = await db
    .select()
    .from(circuits)
    .where(and(eq(circuits.id, id), eq(circuits.territoryId, ctx.territory.id)))
    .limit(1);
  if (!c) return new NextResponse('Introuvable', { status: 404 });
  const stops = await db
    .select({ secret: circuitStops.stampSecret, position: circuitStops.position, name: establishments.name })
    .from(circuitStops)
    .innerJoin(establishments, eq(establishments.id, circuitStops.establishmentId))
    .where(eq(circuitStops.circuitId, c.id))
    .orderBy(asc(circuitStops.position));
  const { pdf, fonts } = await newDoc();
  const W = 210 * MM;
  const H = 297 * MM;
  const cardW = 85 * MM;
  const cardH = 88 * MM;
  const color = hex(ctx.territory.colorPrimary);
  stops.forEach((s, i) => {
    const slot = i % 6;
    const page = slot === 0 ? pdf.addPage([W, H]) : pdf.getPages()[pdf.getPageCount() - 1];
    const col = slot % 2;
    const row = Math.floor(slot / 2);
    const x = 15 * MM + col * (cardW + 10 * MM);
    const y = H - 12 * MM - (row + 1) * cardH - row * 4 * MM;
    page.drawRectangle({ x, y, width: cardW, height: cardH, borderColor: hex('#C9C2B2'), borderWidth: 0.8, borderDashArray: [4, 3] });
    page.drawRectangle({ x, y: y + cardH - 16 * MM, width: cardW, height: 16 * MM, color });
    page.drawText(`Étape ${i + 1}`, { x: x + 5 * MM, y: y + cardH - 8 * MM, size: 13, font: fonts.display, color: rgb(1, 1, 1) });
    const title = wrap(c.name, fonts.body, 8, cardW - 10 * MM)[0] ?? '';
    page.drawText(title, { x: x + 5 * MM, y: y + cardH - 13 * MM, size: 8, font: fonts.body, color: rgb(1, 1, 1) });
    drawQr(page, portalUrl(ctx.territory, `/tampon/${s.secret}`), x + (cardW - 44 * MM) / 2, y + 20 * MM, 44 * MM);
    const name = wrap(s.name, fonts.bold, 10, cardW - 10 * MM)[0] ?? s.name;
    page.drawText(name, { x: x + (cardW - fonts.bold.widthOfTextAtSize(name, 10)) / 2, y: y + 12 * MM, size: 10, font: fonts.bold, color: hex('#14201B') });
    const hint = 'Scannez pour tamponner votre passeport';
    page.drawText(hint, { x: x + (cardW - fonts.body.widthOfTextAtSize(hint, 8)) / 2, y: y + 6 * MM, size: 8, font: fonts.body, color: hex('#5E655F') });
  });
  if (!stops.length) pdf.addPage([W, H]);
  const bytes = await pdf.save();
  return new NextResponse(Buffer.from(bytes), {
    headers: { 'content-type': 'application/pdf', 'content-disposition': `inline; filename="tampons-${c.slug}.pdf"`, 'cache-control': 'private, no-store' },
  });
}
