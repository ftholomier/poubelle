/**
 * Le repère terricom : un carré aux trois coins arrondis (50 %) et un coin à 14 %,
 * pivoté à −45° — la forme des épingles de la carte. Tracé normalisé (côté 1, centré en 0,0).
 */
export const SYMBOL_PATH =
  'M-0.5 0.36L-0.5 0A0.5 0.5 0 0 1 0 -0.5A0.5 0.5 0 0 1 0.5 0A0.5 0.5 0 0 1 0 0.5L-0.36 0.5A0.14 0.14 0 0 1 -0.5 0.36Z';

/** SVG autonome du symbole (favicon, icônes, documents imprimés). */
export function symbolSvg(opts: { size?: number; bg?: string | null; mark?: string; letter?: string | null; letterColor?: string; radius?: number } = {}): string {
  const size = opts.size ?? 64;
  const mark = opts.mark ?? '#F4B266';
  const bg = opts.bg === undefined ? '#14201B' : opts.bg;
  const scale = bg ? 0.56 : 0.7;
  const letter = opts.letter
    ? `<text x="0" y="0.16" font-family="Bricolage Grotesque, Arial, sans-serif" font-weight="800" font-size="0.64" text-anchor="middle" fill="${opts.letterColor ?? '#14201B'}">${opts.letter}</text>`
    : '';
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${size} ${size}" width="${size}" height="${size}">${
    bg ? `<rect width="${size}" height="${size}" rx="${(opts.radius ?? 0.25) * size}" fill="${bg}"/>` : ''
  }<g transform="translate(${size / 2} ${size / 2}) scale(${size * scale})"><path d="${SYMBOL_PATH}" transform="rotate(-45)" fill="${mark}"/>${letter}</g></svg>`;
}
