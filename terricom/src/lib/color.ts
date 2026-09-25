/** Petites opérations sur les couleurs hexadécimales (#rrggbb), sans dépendance. */

function rgb(hex: string): [number, number, number] | null {
  const m = /^#?([0-9a-f]{6})/i.exec(hex.trim());
  if (!m) return null;
  const n = parseInt(m[1], 16);
  return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
}

const toHex = (c: number[]) =>
  `#${c
    .map((v) =>
      Math.max(0, Math.min(255, Math.round(v)))
        .toString(16)
        .padStart(2, '0'),
    )
    .join('')}`;

export function isHexColor(v: string | null | undefined): v is string {
  return !!v && /^#[0-9a-f]{6}$/i.test(v);
}

/** Mélange a et b (w = part de a). */
export function blend(a: string, b: string, w: number): string {
  const x = rgb(a);
  const y = rgb(b);
  if (!x || !y) return a;
  return toHex(x.map((v, i) => v * w + y[i] * (1 - w)));
}

/** Couleur claire au sens de la luminance perçue. */
export function isLight(hex: string): boolean {
  const c = rgb(hex);
  if (!c) return false;
  return 0.299 * c[0] + 0.587 * c[1] + 0.114 * c[2] > 170;
}

/** Assombrit d'un facteur (0,78 par défaut). */
export function darken(hex: string, f = 0.78): string {
  const c = rgb(hex);
  return c ? toHex(c.map((v) => v * f)) : hex;
}

/** Contraste WCAG entre deux couleurs (1 à 21). */
export function contrastRatio(a: string, b: string): number {
  const lum = (hex: string) => {
    const c = rgb(hex) ?? [0, 0, 0];
    const [r, g, bl] = c.map((v) => {
      const s = v / 255;
      return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4;
    });
    return 0.2126 * r + 0.7152 * g + 0.0722 * bl;
  };
  const [l1, l2] = [lum(a), lum(b)].sort((x, y) => y - x);
  return (l1 + 0.05) / (l2 + 0.05);
}

/** Variante lisible d'une couleur de marque sur fond clair (texte, liens) : assombrie jusqu'à 4,5:1. */
export function readableOnLight(hex: string, bg = '#fffdf8'): string {
  let c = hex;
  for (let i = 0; i < 8 && contrastRatio(c, bg) < 4.5; i++) c = darken(c, 0.85);
  return c;
}
