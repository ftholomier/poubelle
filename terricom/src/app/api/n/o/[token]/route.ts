import { trackOpen } from '@/server/services/newsletters';

const PIXEL = Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64');

/** Pixel de mesure d'ouverture d'une lettre (aucun cookie, aucune donnée personnelle ajoutée). */
export async function GET(_req: Request, { params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  await trackOpen(token.replace(/\.gif$/, '')).catch(() => {});
  return new Response(new Uint8Array(PIXEL), {
    headers: { 'content-type': 'image/gif', 'cache-control': 'no-store, max-age=0', 'content-length': String(PIXEL.length) },
  });
}
