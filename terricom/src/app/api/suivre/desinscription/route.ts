import { NextResponse, type NextRequest } from 'next/server';
import { unfollow } from '@/server/services/customers';

/** Désinscription « en un clic » des lettres d'une entreprise (RFC 8058, List-Unsubscribe-Post). */
export async function POST(req: NextRequest) {
  await unfollow(req.nextUrl.searchParams.get('token') ?? '');
  return new NextResponse(null, { status: 204 });
}
