/** Adapte une URL d'image (Unsplash du jeu de démonstration) à la taille d'affichage. */
export function sized(url: string | null | undefined, w: number, h?: number): string | null {
  if (!url) return null;
  if (url.includes('images.unsplash.com')) {
    try {
      const u = new URL(url);
      u.searchParams.set('w', String(w));
      if (h) u.searchParams.set('h', String(h));
      else u.searchParams.delete('h');
      u.searchParams.set('fit', 'crop');
      u.searchParams.set('q', '70');
      u.searchParams.set('auto', 'format');
      return u.toString();
    } catch {
      return url;
    }
  }
  return url;
}

type Variants = Partial<Record<'w320' | 'w640' | 'w1280' | 'w1920', string>>;

/** Meilleure déclinaison d'une image téléversée pour une largeur d'affichage donnée. */
export function variantUrl(m: { url: string; variants?: Variants | null }, w: number): string {
  const v = m.variants ?? {};
  for (const size of [320, 640, 1280, 1920] as const) {
    const key = `w${size}` as const;
    if (size >= w && v[key]) return v[key]!;
  }
  return sized(m.url, w) ?? m.url;
}
