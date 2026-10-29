// La table du babyfoot, vue de dessus : x le long du terrain (de -60, but de l'équipe 0, à 60, but
// de l'équipe 1), z en travers (de -34, côté des poignées de l'équipe 1, à 34, côté des poignées de
// l'équipe 0). Unités : le centimètre d'une vraie table (terrain 120 × 68, balle de 3,4 cm).
// Partagé par la physique (collisions), l'ordinateur (ai.js) et le rendu 3D.

export const LX = 60;
export const LZ = 34;
/** Rayon de la balle. */
export const R = 1.7;
/** Demi-ouverture des buts, profondeur de la cage. */
export const GOAL = 9.5;
export const DEPTH = 7;
/** Hauteur des barres au-dessus du terrain. */
export const ROD_Y = 8;
/** Distance de la barre au point de contact du pied (bas de la figurine). */
export const FOOT = 6.3;
/** Pied (vu de dessus) : segment de demi-longueur FA le long de la barre, épaissi de FR. */
export const FA = 0.9;
export const FR = 1.1;
/** Au-delà de cet angle (figurine levée), le pied passe au-dessus de la balle. */
export const LIFT = 0.9;

// Les 8 barres d'un babyfoot classique, du but de l'équipe 0 à celui de l'équipe 1 : gardien (1),
// défense (2), attaque adverse (3), milieu (5), milieu adverse (5), attaque (3), défense adverse (2),
// gardien adverse (1). num : numéros des figurines (dos des maillots).
const SPEC = [
  { x: -52.5, team: 0, role: 'G', n: 1, gap: 0, travel: 11, num: [1] },
  { x: -37.5, team: 0, role: 'D', n: 2, gap: 24, num: [2, 3] },
  { x: -22.5, team: 1, role: 'A', n: 3, gap: 18.5, num: [9, 10, 11] },
  { x: -7.5, team: 0, role: 'M', n: 5, gap: 12, num: [4, 5, 6, 7, 8] },
  { x: 7.5, team: 1, role: 'M', n: 5, gap: 12, num: [4, 5, 6, 7, 8] },
  { x: 22.5, team: 0, role: 'A', n: 3, gap: 18.5, num: [9, 10, 11] },
  { x: 37.5, team: 1, role: 'D', n: 2, gap: 24, num: [2, 3] },
  { x: 52.5, team: 1, role: 'G', n: 1, gap: 0, travel: 11, num: [1] },
];

/** Les barres : position, équipe, sens d'attaque, figurines (décalage le long de la barre), course. */
export function buildRods() {
  // Les figurines du bord vont jusqu'à toucher une balle collée à la bande.
  const reach = LZ - FA - FR - 0.3;
  return SPEC.map((s, i) => {
    const offs = Array.from({ length: s.n }, (_, k) => (k - (s.n - 1) / 2) * s.gap);
    const travel = s.travel ?? reach - offs[offs.length - 1];
    return { i, x: s.x, team: s.team, dir: s.team === 0 ? 1 : -1, role: s.role, n: s.n, gap: s.gap, offs, travel, num: s.num };
  });
}

/**
 * Bandes (segments) : côtés, pans coupés dans les coins (la balle n'y reste pas morte), fonds de
 * terrain de part et d'autre de la bouche des buts, et intérieur des cages.
 */
export function buildWalls() {
  const C = 5; // pan coupé des coins
  const w = [];
  const seg = (ax, az, bx, bz, e = 0.72, kind = 'bande') => w.push({ ax, az, bx, bz, e, kind });
  seg(-LX + C, LZ, LX - C, LZ);
  seg(-LX + C, -LZ, LX - C, -LZ);
  for (const sx of [-1, 1]) {
    seg(sx * (LX - C), LZ, sx * LX, LZ - C, 0.6);
    seg(sx * (LX - C), -LZ, sx * LX, -LZ + C, 0.6);
    seg(sx * LX, LZ - C, sx * LX, GOAL, 0.66);
    seg(sx * LX, -GOAL, sx * LX, -LZ + C, 0.66);
    // Cage : côtés et fond (le filet amortit).
    seg(sx * LX, GOAL, sx * (LX + DEPTH), GOAL, 0.3, 'cage');
    seg(sx * LX, -GOAL, sx * (LX + DEPTH), -GOAL, 0.3, 'cage');
    seg(sx * (LX + DEPTH), -GOAL, sx * (LX + DEPTH), GOAL, 0.12, 'filet');
  }
  return w;
}

/** Poteaux (ronds) de chaque côté de la bouche des buts. */
export const POSTS = [[-LX, GOAL], [-LX, -GOAL], [LX, GOAL], [LX, -GOAL]].map(([x, z]) => ({ x, z, r: 0.7 }));
