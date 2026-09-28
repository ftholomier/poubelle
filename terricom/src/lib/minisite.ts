import type { CSSProperties } from 'react';
import { blend, readableOnLight } from './color';
import type { MiniSite, MiniSiteSection } from '@/server/db/schema';

/** Sections de la page d'accueil d'une fiche, dans l'ordre par défaut. */
export const DEFAULT_SECTIONS: MiniSiteSection[] = ['presentation', 'pages', 'offre', 'produits', 'actualites', 'formulaires', 'contact'];

export const SECTION_LABELS: Record<MiniSiteSection, string> = {
  presentation: 'Présentation et labels',
  pages: 'Liens vers vos pages',
  offre: 'Offre du moment',
  produits: 'Produits et savoir-faire',
  actualites: 'Actualités et événements',
  formulaires: 'Formulaires',
  contact: 'Contact et recrutement',
};

export const MAX_PAGES = 8;
export const MAX_FORMS = 5;
export const MAX_FIELDS = 20;

/** Sections visibles, dans l'ordre choisi (valeurs inconnues ignorées, doublons retirés). */
export function visibleSections(mini: MiniSite | null | undefined, active: boolean): MiniSiteSection[] {
  if (!active || !mini?.sections?.length) return [...DEFAULT_SECTIONS];
  const seen = new Set<MiniSiteSection>();
  for (const s of mini.sections) if (DEFAULT_SECTIONS.includes(s)) seen.add(s);
  return [...seen];
}

/** Couleurs de marque déclinées pour le mini-site (liens et accents lisibles, fonds teintés). */
export const THEME_PRESETS = ['#1f6b52', '#2c5a86', '#7a5bb5', '#b04a3e', '#c8702a', '#3b5a1f', '#14201b', '#a23b72'];

/** Variables CSS du mini-site : accents, fonds teintés et boutons à la couleur de l'établissement. */
export function themeStyle(theme: string | null): CSSProperties | undefined {
  if (!theme) return undefined;
  const strong = readableOnLight(theme);
  return { '--green': strong, '--brand': strong, '--mint': blend(theme, '#ffffff', 0.16), '--mint-2': blend(theme, '#ffffff', 0.07) } as CSSProperties;
}
