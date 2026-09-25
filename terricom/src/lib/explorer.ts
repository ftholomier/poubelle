import { FAMILIES, FAMILY_ORDER, familyFromSlug, type Family } from './constants';

/** Filtres rapides de l'explorateur (maquette P2). */
export const EXPLORER_TOGGLES = [
  { key: 'ouvert', label: 'Ouvert maintenant' },
  { key: 'click-collect', label: 'Click & collect', attribute: 'click-collect' },
  { key: 'fabrication-locale', label: 'Fabrication locale', attribute: 'fabrication-locale' },
  { key: 'idees-cadeaux', label: 'Idées cadeaux', attribute: 'idees-cadeaux' },
  { key: 'soir', label: 'Ouvert ce soir' },
] as const;

export type ExplorerToggle = (typeof EXPLORER_TOGGLES)[number]['key'];

/** Groupe de filtres avancés (attributs présents sur les fiches du territoire, avec leur nombre). */
export type ExplorerFilterGroup = { group: string; label: string; options: { slug: string; label: string; count: number }[] };

export type ExplorerState = {
  q: string;
  family: Family | null;
  toggles: ExplorerToggle[];
  /** Filtres avancés : services, labels, paiement, accessibilité (identifiants d'attributs). */
  attrs: string[];
  commune: string | null;
  near: { lat: number; lng: number } | null;
};

/** Élément de résultat transmis au navigateur (sans données internes). */
export type ExplorerItem = {
  id: string;
  name: string;
  path: string;
  activity: string;
  color: string;
  communeName: string;
  coverUrl: string | null;
  isOpen: boolean;
  openLabel: string;
  tags: string[];
  distance: string;
};

export type ExplorerResponse = {
  total: number;
  ids: string[];
  items: ExplorerItem[];
  answer: { text: string; meta: string; source: 'ai' | 'rules' } | null;
};

type Params = URLSearchParams | Record<string, string | string[] | undefined>;

function get(p: Params, key: string): string | null {
  if (p instanceof URLSearchParams) return p.get(key);
  const v = p[key];
  return (Array.isArray(v) ? v[0] : v) ?? null;
}

const TOGGLE_KEYS = new Set<string>(EXPLORER_TOGGLES.map((t) => t.key));
const ATTR_SLUG = /^[a-z0-9][a-z0-9-]{1,79}$/;

/** Attributs déjà proposés en filtres rapides (exclus des filtres avancés). */
export const QUICK_ATTRIBUTES = new Set<string>(EXPLORER_TOGGLES.flatMap((t) => ('attribute' in t ? [t.attribute] : [])));

/** Filtres actifs (hors recherche textuelle et position). */
export function isFilteredState(s: ExplorerState): boolean {
  return Boolean(s.q || s.family || s.toggles.length || s.attrs.length || s.commune);
}

export function parseExplorerParams(p: Params): ExplorerState {
  const lat = Number(get(p, 'lat'));
  const lng = Number(get(p, 'lng'));
  return {
    q: (get(p, 'q') ?? '').trim().slice(0, 200),
    family: familyFromSlug(get(p, 'famille')),
    toggles: (get(p, 'filtres') ?? '')
      .split(',')
      .map((s) => s.trim())
      .filter((s): s is ExplorerToggle => TOGGLE_KEYS.has(s)),
    attrs: [
      ...new Set(
        (get(p, 'attributs') ?? '')
          .split(',')
          .map((s) => s.trim())
          .filter((s) => ATTR_SLUG.test(s) && !QUICK_ATTRIBUTES.has(s)),
      ),
    ].slice(0, 12),
    commune: get(p, 'commune')?.slice(0, 80) || null,
    near: Number.isFinite(lat) && Number.isFinite(lng) && lat !== 0 && Math.abs(lat) <= 90 && Math.abs(lng) <= 180 ? { lat, lng } : null,
  };
}

export function explorerQueryString(s: ExplorerState, extra: Record<string, string> = {}): string {
  const u = new URLSearchParams();
  if (s.q) u.set('q', s.q);
  if (s.family) u.set('famille', FAMILIES[s.family].slug);
  if (s.toggles.length) u.set('filtres', s.toggles.join(','));
  if (s.attrs.length) u.set('attributs', s.attrs.join(','));
  if (s.commune) u.set('commune', s.commune);
  if (s.near) {
    u.set('lat', s.near.lat.toFixed(4));
    u.set('lng', s.near.lng.toFixed(4));
  }
  for (const [k, v] of Object.entries(extra)) u.set(k, v);
  const str = u.toString();
  return str ? `?${str}` : '';
}

/** Traduction de l'état de l'explorateur en paramètres du moteur de recherche. */
export function toSearchParams(s: ExplorerState) {
  return {
    q: s.q || undefined,
    families: s.family ? [s.family] : undefined,
    attributeSlugs: [...EXPLORER_TOGGLES.flatMap((t) => ('attribute' in t && s.toggles.includes(t.key) ? [t.attribute] : [])), ...s.attrs],
    communeSlugs: s.commune ? [s.commune] : undefined,
    openNow: s.toggles.includes('ouvert'),
    openTonight: s.toggles.includes('soir'),
    near: s.near,
  };
}

export const EXPLORER_FAMILIES: { key: Family | null; label: string; color: string }[] = [
  { key: null, label: 'Tous', color: '#14201B' },
  ...FAMILY_ORDER.map((f) => ({ key: f, label: FAMILIES[f].label, color: FAMILIES[f].color })),
];
