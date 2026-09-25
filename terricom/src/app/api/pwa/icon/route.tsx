import { ImageResponse } from 'next/og';
import { resolveTerritoryParam } from '@/server/services/territories';

/**
 * Icônes de l'application installable : symbole terricom, ou initiales aux couleurs du territoire.
 * Paramètres : size (48 à 1024), t (territoire), maskable=1 (zone de sécurité Android), badge=1 (monochrome).
 */
const LEAF =
  '<svg xmlns="http://www.w3.org/2000/svg" viewBox="-0.75 -0.75 1.5 1.5"><path d="M-0.5 0.36L-0.5 0A0.5 0.5 0 0 1 0 -0.5A0.5 0.5 0 0 1 0.5 0A0.5 0.5 0 0 1 0 0.5L-0.36 0.5A0.14 0.14 0 0 1 -0.5 0.36Z" transform="rotate(-45)" fill="FILL"/></svg>';

export async function GET(req: Request) {
  const url = new URL(req.url);
  const size = Math.max(48, Math.min(1024, Math.round(Number(url.searchParams.get('size')) || 192)));
  const maskable = url.searchParams.get('maskable') === '1';
  const badge = url.searchParams.get('badge') === '1';
  const param = url.searchParams.get('t') ?? req.headers.get('x-terricom-portal-param');
  const t = param ? await resolveTerritoryParam(param) : null;
  const bg = t?.colorPrimary ?? '#1F6B52';
  const fg = t?.colorAccent ?? '#F4B266';
  // Icône « masquable » : fond plein jusqu'au bord, motif dans les 80 % centraux.
  const inner = maskable ? size * 0.62 : size * 0.72;
  const glyph = t ? (
    <span style={{ fontSize: inner * 0.62, fontWeight: 700, color: badge ? '#ffffff' : fg, letterSpacing: -inner * 0.03 }}>{t.initials}</span>
  ) : (
    <img
      src={`data:image/svg+xml;base64,${Buffer.from(LEAF.replace('FILL', badge ? '#ffffff' : fg)).toString('base64')}`}
      width={inner}
      height={inner}
      alt=""
    />
  );
  return new ImageResponse(
    <div
      style={{
        width: size,
        height: size,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: badge ? 'transparent' : bg,
        borderRadius: badge || maskable ? 0 : size * 0.22,
      }}
    >
      {glyph}
    </div>,
    { width: size, height: size, headers: { 'cache-control': 'public, max-age=86400' } },
  );
}
