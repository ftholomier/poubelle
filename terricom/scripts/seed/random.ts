/** Générateur pseudo-aléatoire déterministe (mulberry32) : un seed identique produit les mêmes données. */
export function rng(seed = 42) {
  let a = seed >>> 0;
  const next = () => {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
  return {
    next,
    int: (min: number, max: number) => Math.floor(next() * (max - min + 1)) + min,
    pick: <T>(arr: readonly T[]): T => arr[Math.floor(next() * arr.length)],
    chance: (p: number) => next() < p,
    shuffle: <T>(arr: T[]): T[] => {
      const a2 = [...arr];
      for (let i = a2.length - 1; i > 0; i--) {
        const j = Math.floor(next() * (i + 1));
        [a2[i], a2[j]] = [a2[j], a2[i]];
      }
      return a2;
    },
    sample: <T>(arr: readonly T[], n: number): T[] => {
      const copy = [...arr];
      const out: T[] = [];
      while (out.length < n && copy.length) out.push(copy.splice(Math.floor(next() * copy.length), 1)[0]);
      return out;
    },
    weighted: <T>(items: readonly [T, number][]): T => {
      const total = items.reduce((s, [, w]) => s + w, 0);
      let r = next() * total;
      for (const [v, w] of items) {
        r -= w;
        if (r <= 0) return v;
      }
      return items[items.length - 1][0];
    },
  };
}

export type Rng = ReturnType<typeof rng>;

export const SURNAMES = [
  'Cuenot', 'Faivre', 'Girard', 'Vuillemin', 'Jeunet', 'Bourgeois', 'Grosjean', 'Pernot', 'Bouvier', 'Guyon',
  'Pourcelot', 'Tissot', 'Chopard', 'Belin', 'Cattin', 'Duboz', 'Parrenin', 'Jacquet', 'Nicod', 'Bailly',
  'Mathez', 'Tournier', 'Bertin', 'Colin', 'Marmet', 'Pagnier', 'Petitjean', 'Gaulard', 'Voisard', 'Monnier',
  'Journot', 'Barbier', 'Humbert', 'Maillard', 'Clerc', 'Roussel', 'Perrin', 'Vionnet', 'Lançon', 'Bôle',
  'Demougeot', 'Magnin', 'Saillard', 'Vermot', 'Racine', 'Cordier', 'Musy', 'Pheulpin', 'Brischoux', 'Coulon',
];

export const FIRSTNAMES = [
  'Julie', 'Thomas', 'Camille', 'Nicolas', 'Léa', 'Antoine', 'Manon', 'Hugo', 'Chloé', 'Maxime',
  'Sarah', 'Lucas', 'Emma', 'Julien', 'Laura', 'Romain', 'Marie', 'Kevin', 'Céline', 'Pierre',
  'Élodie', 'Florian', 'Anaïs', 'Mathieu', 'Pauline', 'Jérôme', 'Audrey', 'Vincent', 'Sandrine', 'Damien',
];

export const PLACES = ['Pont', 'Moulin', 'Marché', 'Centre', 'Château', 'Plateau', 'Vallon', 'Village', 'Coteau', 'Val'];
export const STREETS = [
  'Grande Rue', 'rue de la Mairie', "rue de l'Église", 'rue du Moulin', 'place du Marché', 'rue des Tilleuls',
  'route de Besançon', 'rue de la Loue', 'chemin des Prés', 'rue du Château', 'rue Pasteur', 'rue Victor-Hugo',
  'rue des Écoles', 'avenue de la Gare', 'rue du Pont', 'place de la Fontaine', 'rue Jean-Jaurès', 'rue de Salins',
];

type NameFn = (r: Rng) => string;

/** Modèles de noms commerciaux par catégorie. */
export const NAME_PATTERNS: Record<string, NameFn[]> = {
  boulangerie: [(r) => `Boulangerie ${r.pick(SURNAMES)}`, (r) => `Au Fournil du ${r.pick(PLACES)}`, (r) => `Le Pain de ${r.pick(FIRSTNAMES)}`],
  fromagerie: [(r) => `Fruitière du ${r.pick(PLACES)}`, (r) => `Fromagerie ${r.pick(SURNAMES)}`],
  restaurant: [(r) => `La Table de ${r.pick(FIRSTNAMES)}`, (r) => `Restaurant du ${r.pick(PLACES)}`, (r) => `Le ${r.pick(['Comtois', 'Saint-Laurent', 'Relais du ' + r.pick(PLACES), 'Petit Gourmet', 'Cerisier'])}`],
  menuiserie: [(r) => `Menuiserie ${r.pick(SURNAMES)}`, (r) => `Atelier Bois ${r.pick(SURNAMES)}`],
  'plombier-chauffagiste': [(r) => `${r.pick(SURNAMES)} Chauffage`, (r) => `Plomberie ${r.pick(SURNAMES)}`],
  caviste: [(r) => `La Cave du ${r.pick(PLACES)}`, (r) => `Les Vins de ${r.pick(FIRSTNAMES)}`],
  maraicher: [(r) => `Les Jardins ${r.pick(SURNAMES)}`, (r) => `Ferme maraîchère du ${r.pick(PLACES)}`],
  'metiers-d-art': [(r) => `Atelier ${r.pick(FIRSTNAMES)} ${r.pick(SURNAMES)}`, (r) => `Terres du ${r.pick(PLACES)}`],
  chocolatier: [(r) => `Chocolaterie ${r.pick(SURNAMES)}`],
  auberge: [(r) => `Auberge du ${r.pick(PLACES)}`, (r) => `L'Auberge ${r.pick(SURNAMES)}`],
  apiculteur: [(r) => `Rucher ${r.pick(SURNAMES)}`, (r) => `Les Abeilles du ${r.pick(PLACES)}`],
  garage: [(r) => `Garage ${r.pick(SURNAMES)}`, (r) => `Garage du ${r.pick(PLACES)}`],
  epicerie: [(r) => `Épicerie du ${r.pick(PLACES)}`, (r) => `Chez ${r.pick(FIRSTNAMES)}`],
  distillerie: [(r) => `Distillerie ${r.pick(SURNAMES)}`],
  bistrot: [(r) => `Café du ${r.pick(PLACES)}`, (r) => `Le Bistrot de ${r.pick(FIRSTNAMES)}`],
  ebeniste: [(r) => `Ébénisterie ${r.pick(SURNAMES)}`],
  boucherie: [(r) => `Boucherie ${r.pick(SURNAMES)}`, (r) => `Maison ${r.pick(SURNAMES)}`],
  coiffure: [(r) => `${r.pick(FIRSTNAMES)} Coiffure`, (r) => `Salon ${r.pick(FIRSTNAMES)}`, (r) => `L'Atelier de ${r.pick(FIRSTNAMES)}`],
  'institut-beaute': [(r) => `Institut ${r.pick(['Belle Loue', 'Zen', 'Éclat', 'Harmonie', 'Douceur'])}`, (r) => `Beauté ${r.pick(FIRSTNAMES)}`],
  pharmacie: [(r) => `Pharmacie du ${r.pick(PLACES)}`, (r) => `Pharmacie ${r.pick(SURNAMES)}`],
  fleuriste: [(r) => `Fleurs de ${r.pick(FIRSTNAMES)}`, (r) => `L'Atelier Floral`],
  librairie: [(r) => `Librairie du ${r.pick(PLACES)}`, (r) => `Maison de la Presse`],
  electricien: [(r) => `${r.pick(SURNAMES)} Électricité`, (r) => `Élec ${r.pick(SURNAMES)}`],
  maconnerie: [(r) => `Maçonnerie ${r.pick(SURNAMES)}`, (r) => `${r.pick(SURNAMES)} Bâtiment`],
  couvreur: [(r) => `Toitures ${r.pick(SURNAMES)}`, (r) => `${r.pick(SURNAMES)} Couverture`],
  peintre: [(r) => `${r.pick(SURNAMES)} Peinture`, (r) => `Déco ${r.pick(SURNAMES)}`],
  ferme: [(r) => `Ferme ${r.pick(SURNAMES)}`, (r) => `GAEC du ${r.pick(PLACES)}`],
  'brasserie-artisanale': [(r) => `Brasserie du ${r.pick(PLACES)}`, (r) => `Bière de la Loue`],
  pizzeria: [(r) => `Pizzeria ${r.pick(['Da Luigi', 'Bella Loue', 'Le Four', 'Napoli', 'Chez Tonio'])}`],
  traiteur: [(r) => `Traiteur ${r.pick(SURNAMES)}`],
  hebergement: [(r) => `Gîte du ${r.pick(PLACES)}`, (r) => `Hôtel de la Loue`, (r) => `Chambres d'hôtes ${r.pick(SURNAMES)}`],
  superette: [(r) => `Proxi ${r.pick(PLACES)}`, (r) => `Vival du ${r.pick(PLACES)}`],
  'pret-a-porter': [(r) => `Boutique ${r.pick(FIRSTNAMES)}`, (r) => `L'Échoppe`],
  informatique: [(r) => `${r.pick(SURNAMES)} Informatique`, (r) => `Loue Numérique`],
  taxi: [(r) => `Taxi ${r.pick(SURNAMES)}`],
  conseil: [(r) => `Cabinet ${r.pick(SURNAMES)}`, (r) => `${r.pick(SURNAMES)} & Associés`],
  paysagiste: [(r) => `Jardins ${r.pick(SURNAMES)}`, (r) => `${r.pick(SURNAMES)} Paysage`],
  'auto-ecole': [(r) => `Auto-école du ${r.pick(PLACES)}`],
  bricolage: [(r) => `Quincaillerie ${r.pick(SURNAMES)}`],
  opticien: [(r) => `Optique ${r.pick(SURNAMES)}`],
  'tabac-presse': [(r) => `Tabac-presse du ${r.pick(PLACES)}`],
};

/** Répartition des catégories générées (poids). */
export const CATEGORY_WEIGHTS: [string, number][] = [
  // Répartition proche d'un bourg-centre rural : ~27 % commerces, 22 % artisans, 10 % producteurs, 15 % restauration, 26 % services.
  ['boulangerie', 6], ['boucherie', 5], ['coiffure', 7], ['institut-beaute', 4], ['pharmacie', 4], ['fleuriste', 3],
  ['librairie', 3], ['electricien', 4], ['maconnerie', 7], ['couvreur', 4], ['peintre', 5], ['plombier-chauffagiste', 4],
  ['menuiserie', 4], ['garage', 4], ['restaurant', 8], ['bistrot', 5], ['pizzeria', 3], ['traiteur', 2], ['auberge', 2],
  ['ferme', 5], ['maraicher', 3], ['apiculteur', 2], ['fromagerie', 3], ['brasserie-artisanale', 1], ['epicerie', 4],
  ['superette', 3], ['caviste', 2], ['pret-a-porter', 4], ['informatique', 2], ['taxi', 1], ['conseil', 3],
  ['hebergement', 3], ['paysagiste', 2], ['auto-ecole', 1], ['bricolage', 2], ['opticien', 2], ['tabac-presse', 3],
  ['metiers-d-art', 2], ['ebeniste', 1], ['chocolatier', 1], ['cordonnerie', 1],
];

type HoursTpl = [number, string, string][];

const wk = (from: number, to: number, o: string, c: string): HoursTpl =>
  Array.from({ length: to - from + 1 }, (_, i) => [from + i, o, c] as [number, string, string]);

/** Horaires types par famille d'activité. */
export function hoursFor(category: string, r: Rng): HoursTpl {
  switch (category) {
    case 'boulangerie':
      return [...wk(1, 5, '06:30', '19:00'), [6, '07:00', '12:30']];
    case 'restaurant':
    case 'auberge':
      return [...wk(1, 6, '12:00', '14:00'), ...wk(r.chance(0.5) ? 3 : 1, 5, '19:00', '22:00')];
    case 'bistrot':
      return wk(0, 5, '07:30', '20:00');
    case 'pizzeria':
      return [...wk(1, 6, '11:30', '13:30'), ...wk(1, 6, '18:00', '22:00')];
    case 'coiffure':
    case 'institut-beaute':
      return [...wk(1, 4, '09:00', '18:30'), [5, '08:30', '16:00']];
    case 'ferme':
    case 'maraicher':
    case 'apiculteur':
      return r.chance(0.6) ? [[4, '16:00', '19:00'], [5, '09:00', '12:00']] : [];
    case 'hebergement':
      return [];
    case 'pharmacie':
    case 'superette':
    case 'tabac-presse':
      return [...wk(0, 4, '08:30', '12:30'), ...wk(0, 4, '14:00', '19:00'), [5, '08:30', '12:30']];
    default:
      return r.chance(0.85) ? [...wk(0, 4, '08:00', '12:00'), ...wk(0, 4, '13:30', '18:00')] : [];
  }
}

/** Services et labels probables par catégorie. */
export function attributesFor(category: string, r: Rng): string[] {
  const out = new Set<string>();
  const add = (slug: string, p: number) => r.chance(p) && out.add(slug);
  const food = ['boulangerie', 'boucherie', 'fromagerie', 'chocolatier', 'epicerie', 'caviste', 'ferme', 'maraicher', 'apiculteur', 'brasserie-artisanale'];
  const resto = ['restaurant', 'bistrot', 'pizzeria', 'auberge', 'traiteur'];
  const build = ['electricien', 'maconnerie', 'couvreur', 'peintre', 'plombier-chauffagiste', 'menuiserie', 'paysagiste', 'ebeniste'];
  if (food.includes(category)) {
    add('fabrication-locale', 0.6);
    add('idees-cadeaux', 0.35);
    add('click-collect', 0.3);
    add('vente-directe', ['ferme', 'maraicher', 'apiculteur', 'fromagerie'].includes(category) ? 0.9 : 0);
    add('bio', 0.2);
  }
  if (resto.includes(category)) {
    add('terrasse', 0.5);
    add('produits-locaux', 0.5);
    add('tickets-resto', 0.6);
    add('livraison', category === 'pizzeria' ? 0.7 : 0.05);
  }
  if (build.includes(category)) {
    add('sur-devis', 0.9);
    add('rge', 0.35);
  }
  if (['coiffure', 'institut-beaute', 'garage', 'auto-ecole', 'conseil'].includes(category)) add('rdv-en-ligne', 0.5);
  if (['metiers-d-art', 'ebeniste'].includes(category)) {
    add('fabrication-locale', 0.95);
    add('idees-cadeaux', 0.6);
  }
  add('acces-pmr', 0.45);
  if (!build.includes(category) && category !== 'hebergement') {
    add('carte-bancaire', 0.85);
    add('especes', 0.8);
    add('cheque', 0.4);
  }
  return [...out];
}

export function phoneNumber(r: Rng): string {
  return `03816${r.int(0, 9)}${String(r.int(0, 9999)).padStart(4, '0')}`;
}

export function descriptionFor(name: string, activity: string, commune: string, r: Rng): string {
  const intro = r.pick([
    `${name} vous accueille à ${commune}.`,
    `À ${commune}, ${name} est une adresse de confiance pour tout ce qui touche à ${activity.toLowerCase()}.`,
    `Entreprise familiale installée à ${commune}, ${name} met son savoir-faire au service des habitants du territoire.`,
  ]);
  const middle = r.pick([
    'Conseils personnalisés, produits choisis avec soin et accueil chaleureux.',
    'Nous privilégions les fournisseurs de la région et le travail bien fait.',
    'Devis gratuit, intervention rapide et suivi après chaque prestation.',
    "Une équipe à l'écoute, disponible du mardi au samedi.",
  ]);
  const end = r.chance(0.5) ? ' Poussez la porte, on vous attend !' : '';
  return `${intro} ${middle}${end}`;
}
