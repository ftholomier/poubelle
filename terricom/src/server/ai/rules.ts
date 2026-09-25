import { FAMILIES, type Family } from '@/lib/constants';
import { normalizeText } from '@/lib/slug';

/**
 * Interprétation déterministe des recherches en langage naturel (repli sans IA,
 * et premier filtre instantané même lorsque l'IA est active).
 */
export type SearchIntent = {
  families: Family[];
  categorySlugs: string[];
  attributeSlugs: string[];
  communeSlugs: string[];
  openNow: boolean;
  openTonight: boolean;
  keywords: string[];
  kind: 'tonight' | 'gift' | 'repair' | 'local' | 'generic';
  natural: boolean;
};

export type SearchVocabulary = {
  categories: { slug: string; name: string; family: Family; synonyms: string[] }[];
  attributes: { slug: string; label: string }[];
  communes: { slug: string; name: string }[];
};

const STOPWORDS = new Set(
  (
    'a au aux avec ce ces cette chez dans de des du elle en et est il je la le les leur lui ma me mes moi mon ne nos notre nous on ou où par pas pour qu que qui sa se ses son sur ta te tes toi ton tu un une vos votre vous y ' +
    'trouver trouve cherche chercher acheter besoin veux voudrais aimerais peux peut quel quels quelle quelles quoi comment ' +
    'pres proche proximite autour ici maison moi capable faire bon bonne bons bonnes meilleur meilleure un une des'
  ).split(' '),
);

const ATTRIBUTE_RULES: { slug: string; re: RegExp }[] = [
  { slug: 'idees-cadeaux', re: /\b(offrir|cadeaux?|noel|fete des (meres|peres)|anniversaire)\b/ },
  { slug: 'fabrication-locale', re: /\b(fait ici|fait main|fabrication locale|fabrique|made in|artisanal)\b/ },
  { slug: 'bio', re: /\b(bio|biologique)\b/ },
  { slug: 'click-collect', re: /\b(click|clic|retrait|a emporter|commande en ligne)\b/ },
  { slug: 'livraison', re: /\blivr/ },
  { slug: 'acces-pmr', re: /\b(pmr|fauteuil|handicap|accessible)\b/ },
  { slug: 'terrasse', re: /\bterrasse\b/ },
  { slug: 'sans-gluten', re: /\bgluten\b/ },
  { slug: 'vente-directe', re: /\b(vente directe|a la ferme|circuit court)\b/ },
  { slug: 'rdv-en-ligne', re: /\b(rendez-vous|rdv)\b/ },
];

const FAMILY_RULES: { family: Family; re: RegExp }[] = [
  { family: 'RESTAURATION', re: /\b(restau\w*|manger|diner|dejeuner|brasserie|bistrot|table|repas|resto)\b/ },
  { family: 'PRODUCTEUR', re: /\b(producteurs?|fermes?|maraich\w*|produits? (locaux|du terroir)|terroir)\b/ },
  { family: 'ARTISAN', re: /\b(artisans?|ateliers?|metiers? d.art)\b/ },
  { family: 'SERVICES', re: /\b(repar\w*|depann\w*|plomb\w*|chauff\w*|chaudiere|electric\w*|garagiste|mecanicien|fuite)\b/ },
];

export function interpretQuery(query: string, vocab: SearchVocabulary): SearchIntent {
  const q = normalizeText(query);
  const words = q.split(' ').filter(Boolean);
  const intent: SearchIntent = {
    families: [],
    categorySlugs: [],
    attributeSlugs: [],
    communeSlugs: [],
    openNow: false,
    openTonight: false,
    keywords: [],
    kind: 'generic',
    natural: words.length >= 3 || /\?$/.test(query.trim()),
  };
  const consumed = new Set<string>();
  const consume = (re: RegExp) => {
    for (const w of words) if (re.test(w)) consumed.add(w);
  };

  if (/\b(ce soir|soiree|diner|cette nuit)\b/.test(q)) {
    intent.openTonight = true;
    intent.kind = 'tonight';
    consume(/^(ce|soir|soiree|diner|cette|nuit)$/);
  }
  if (/\b(ouverts?|ouvertes?|maintenant|en ce moment|actuellement)\b/.test(q) && !intent.openTonight) {
    intent.openNow = true;
    consume(/^(ouverts?|ouvertes?|maintenant|moment|actuellement)$/);
  }

  for (const rule of ATTRIBUTE_RULES) {
    if (rule.re.test(q) && vocab.attributes.some((a) => a.slug === rule.slug)) {
      intent.attributeSlugs.push(rule.slug);
      for (const w of words) if (rule.re.test(w)) consumed.add(w);
    }
  }
  if (intent.attributeSlugs.includes('idees-cadeaux')) intent.kind = 'gift';
  if (/\blocal(e|es|aux)?\b/.test(q)) {
    consumed.add('local');
    consumed.add('locale');
    consumed.add('locaux');
    consumed.add('locales');
    if (intent.kind === 'generic') intent.kind = 'local';
  }

  for (const rule of FAMILY_RULES) {
    if (rule.re.test(q)) {
      intent.families.push(rule.family);
      consume(rule.re);
    }
  }
  if (intent.families.includes('SERVICES')) intent.kind = 'repair';

  // Catégories citées (nom ou synonyme, tolérance sur les terminaisons)
  for (const c of vocab.categories) {
    const terms = [c.name, ...c.synonyms].map(normalizeText).filter((t) => t.length >= 4);
    const hit = terms.find((t) => {
      const stem = t.slice(0, Math.max(4, Math.min(t.length, 6)));
      return q.includes(t) || words.some((w) => w.length >= 4 && (w.startsWith(stem) || t.startsWith(w)));
    });
    if (hit) {
      intent.categorySlugs.push(c.slug);
      for (const w of words) if (w.length >= 4 && (hit.startsWith(w) || w.startsWith(hit.slice(0, 5)))) consumed.add(w);
    }
  }

  for (const c of vocab.communes) {
    const name = normalizeText(c.name);
    if (name.length >= 3 && q.includes(name)) {
      intent.communeSlugs.push(c.slug);
      for (const w of name.split(' ')) consumed.add(w);
    }
  }

  intent.keywords = words.filter((w) => !consumed.has(w) && !STOPWORDS.has(w) && w.length > 1);
  // Une recherche simple (« boulangerie ») reste une recherche plein texte.
  if (!intent.natural && intent.categorySlugs.length) intent.keywords = [];
  intent.families = [...new Set(intent.families)];
  intent.categorySlugs = [...new Set(intent.categorySlugs)];
  return intent;
}

export type AnswerResult = {
  name: string;
  activity: string;
  communeName: string;
  family: Family;
  openLabel: string;
  until: string | null;
  distance: string;
  offer?: string | null;
  campaign?: string | null;
};

function listFr(items: string[]): string {
  if (items.length <= 1) return items.join('');
  return `${items.slice(0, -1).join(', ')} et ${items[items.length - 1]}`;
}

/** Réponse rédigée à partir des résultats (sans IA). */
export function composeAnswer(query: string, intent: SearchIntent, results: AnswerResult[]): { text: string; meta: string } | null {
  if (!intent.natural) return null;
  const n = results.length;
  if (!n) {
    return {
      text: `Je n'ai trouvé aucune adresse correspondant à « ${query.trim()} ». Essayez une formulation plus simple, ou parcourez la carte par catégorie.`,
      meta: '0 résultat · pensez à élargir la recherche',
    };
  }
  const top = results.slice(0, 3);
  if (intent.kind === 'tonight') {
    const parts = top.map((r, i) =>
      i === 0 ? `${r.name} à ${r.communeName}${r.until ? ` (jusqu'à ${r.until})` : ''}` : `${r.name}${r.until ? ` (jusqu'à ${r.until})` : ''}`,
    );
    return {
      text: `${n} ${n > 1 ? 'tables ouvertes' : 'table ouverte'} ce soir : ${listFr(parts)}.`,
      meta: `${n} restaurant${n > 1 ? 's' : ''} · triés par distance`,
    };
  }
  if (intent.kind === 'gift') {
    const inCampaign = results.filter((r) => r.campaign);
    const tail = inCampaign.length
      ? ` ${inCampaign.length} d'entre eux participent à « ${inCampaign[0].campaign} ».`
      : '';
    return {
      text: `Pour offrir local : ${listFr(results.slice(0, 4).map((r) => `${r.name} (${r.activity.toLowerCase()}, ${r.communeName})`))}.${tail}`,
      meta: `${n} idée${n > 1 ? 's' : ''}${inCampaign.length ? ` · ${inCampaign.length} offre${inCampaign.length > 1 ? 's' : ''} en cours` : ''}`,
    };
  }
  if (intent.kind === 'repair') {
    const r = top[0];
    const openTxt = r.openLabel === 'Ouvert' && r.until ? `Il est ouvert jusqu'à ${r.until}` : 'Il est actuellement fermé';
    return {
      text: `${r.name}${r.distance ? `, à ${r.distance}` : ''}, ${r.activity.toLowerCase()} à ${r.communeName}. ${openTxt} : appelez directement depuis sa fiche.${n > 1 ? ` ${n - 1} autre${n > 2 ? 's' : ''} professionnel${n > 2 ? 's' : ''} qualifié${n > 2 ? 's' : ''} dans la liste.` : ''}`,
      meta: `${n} professionnel${n > 1 ? 's' : ''} · ${r.communeName}`,
    };
  }
  return {
    text: `${n} adresse${n > 1 ? 's correspondent' : ' correspond'} : ${listFr(top.map((r) => `${r.name} (${r.activity.toLowerCase()}, ${r.communeName})`))}${n > 3 ? '…' : '.'}`,
    meta: `${n} résultat${n > 1 ? 's' : ''} · les plus proches d'abord`,
  };
}

export function familyWord(f: Family): string {
  return FAMILIES[f].plural;
}
