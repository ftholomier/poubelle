import { NextResponse, type NextRequest } from 'next/server';
import { env } from '@/server/env';
import { trackClick, verifyClick } from '@/server/services/newsletters';

/** Lien mesuré d'une lettre : l'URL de destination est signée (pas de redirection ouverte). */
export async function GET(req: NextRequest, { params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  const url = req.nextUrl.searchParams.get('u') ?? '';
  const sig = req.nextUrl.searchParams.get('s') ?? '';
  if (!/^https?:\/\//.test(url) || !verifyClick(token, url, sig)) return NextResponse.redirect(env.APP_URL, 302);
  await trackClick(token).catch(() => {});
  return NextResponse.redirect(url, 302);
}
