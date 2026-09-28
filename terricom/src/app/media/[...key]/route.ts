import { storage } from '@/server/storage';

/**
 * Médias publics (photos, visuels) servis depuis le stockage.
 * Les documents privés (Kbis, CV) ne sont jamais servis ici : ils passent par une route authentifiée.
 */
export async function GET(_req: Request, { params }: { params: Promise<{ key: string[] }> }) {
  const { key } = await params;
  const path = key.map(decodeURIComponent).join('/');
  if (!path || path.startsWith('private/') || path.includes('..')) return new Response('Introuvable', { status: 404 });
  const obj = await storage().get(path);
  if (!obj) return new Response('Introuvable', { status: 404 });
  return new Response(new Uint8Array(obj.body), {
    headers: {
      'content-type': obj.contentType,
      'cache-control': 'public, max-age=31536000, immutable',
      'x-content-type-options': 'nosniff',
      'content-security-policy': "default-src 'none'; style-src 'unsafe-inline'; sandbox",
    },
  });
}
