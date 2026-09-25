import { NextResponse, type NextRequest } from 'next/server';
import { boApiContext } from '@/server/services/bo-api';
import { invitationLetters } from '@/server/services/letters';

/** Courriers d'invitation (PDF) pour une sélection de fiches. */
export async function GET(req: NextRequest) {
  const ctx = await boApiContext();
  if (!ctx) return new NextResponse('Non autorisé', { status: 401 });
  const ids = (req.nextUrl.searchParams.get('ids') ?? '').split(',').filter((x) => /^[0-9a-f-]{36}$/.test(x));
  const pdf = await invitationLetters(ctx, ids);
  if (!pdf) return new NextResponse('Aucune fiche sélectionnée', { status: 404 });
  return new NextResponse(Buffer.from(pdf), {
    headers: { 'content-type': 'application/pdf', 'content-disposition': 'inline; filename="courriers-invitation.pdf"', 'cache-control': 'private, no-store' },
  });
}
