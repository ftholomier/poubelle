import { and, eq } from 'drizzle-orm';
import { NextResponse } from 'next/server';
import { db } from '@/server/db';
import { importBatches } from '@/server/db/schema';
import { boApiContext } from '@/server/services/bo-api';
import { invitationLetters } from '@/server/services/letters';

/** Courriers d'invitation des fiches créées sans email par un import. */
export async function GET(_req: Request, { params }: { params: Promise<{ id: string }> }) {
  const ctx = await boApiContext();
  if (!ctx) return new NextResponse('Non autorisé', { status: 401 });
  const { id } = await params;
  if (!/^[0-9a-f-]{36}$/.test(id)) return new NextResponse('Introuvable', { status: 404 });
  const [batch] = await db.select({ report: importBatches.report }).from(importBatches).where(and(eq(importBatches.id, id), eq(importBatches.territoryId, ctx.territory.id))).limit(1);
  const pdf = batch ? await invitationLetters(ctx, batch.report.letterIds ?? []) : null;
  if (!pdf) return new NextResponse('Aucun courrier à imprimer', { status: 404 });
  return new NextResponse(Buffer.from(pdf), {
    headers: { 'content-type': 'application/pdf', 'content-disposition': 'inline; filename="courriers-import.pdf"', 'cache-control': 'private, no-store' },
  });
}
