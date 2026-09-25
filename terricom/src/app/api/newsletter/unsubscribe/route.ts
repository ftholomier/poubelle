import { NextResponse, type NextRequest } from 'next/server';
import { unsubscribe } from '@/server/services/newsletter';

/** Désinscription « en un clic » des clients de messagerie (RFC 8058, List-Unsubscribe-Post). */
export async function POST(req: NextRequest) {
  const token = req.nextUrl.searchParams.get('token') ?? '';
  await unsubscribe(token);
  return new NextResponse(null, { status: 204 });
}
