// Le plateau du flipper : coordonnées du plan en pixels (x de 0 à 400 de gauche à droite, y de 0 à
// 840 du fond vers le joueur). Partagé par la physique (collisions) et le rendu 3D (décor).

export const W = 400;
export const H = 840;
/** Rayon de la bille. */
export const R = 10;

/** Points d'une courbe de Bézier cubique. */
export function bezier(p0, p1, p2, p3, n = 16) {
  const out = [];
  for (let i = 0; i <= n; i++) {
    const t = i / n, u = 1 - t;
    out.push([
      u * u * u * p0[0] + 3 * u * u * t * p1[0] + 3 * u * t * t * p2[0] + t * t * t * p3[0],
      u * u * u * p0[1] + 3 * u * u * t * p1[1] + 3 * u * t * t * p2[1] + t * t * t * p3[1],
    ]);
  }
  return out;
}

/** Courbe de Catmull-Rom passant par les points (x, y, h). */
function spline(pts, per = 10) {
  const out = [];
  for (let i = 0; i < pts.length - 1; i++) {
    const p0 = pts[Math.max(i - 1, 0)], p1 = pts[i], p2 = pts[i + 1], p3 = pts[Math.min(i + 2, pts.length - 1)];
    for (let k = 0; k < per; k++) {
      const t = k / per, t2 = t * t, t3 = t2 * t;
      out.push([0, 1, 2].map(j => 0.5 * ((2 * p1[j]) + (-p0[j] + p2[j]) * t + (2 * p0[j] - 5 * p1[j] + 4 * p2[j] - p3[j]) * t2 + (-p0[j] + 3 * p1[j] - 3 * p2[j] + p3[j]) * t3)));
    }
  }
  out.push(pts[pts.length - 1].slice());
  return out;
}

/**
 * Le plateau. opt.letters : lettres des cibles (nom du club, 3 à 8), opt.digits : année de
 * naissance du club (4 chiffres des couloirs du haut).
 */
export function buildTable(opt = {}) {
  const letters = String(opt.letters || 'SOCHAUX').toUpperCase().replace(/[^A-Z]/g, '').slice(0, 8) || 'LION';
  const digits = String(opt.digits || '1928').replace(/\D/g, '').slice(0, 4).padEnd(4, '0');
  const arch = [...bezier([390, 160], [390, 62], [310, 12], [200, 12]), ...bezier([200, 12], [90, 12], [12, 62], [12, 160]).slice(1)];

  // Murs : tracés (polylignes). h : hauteur au rendu ; neon : liseré lumineux ; r : demi-épaisseur.
  const walls = [
    { id: 'tour', pts: [[390, 840], ...arch, [12, 840]], r: 3, e: 0.42, h: 3.6, neon: true },
    { id: 'lanceur', pts: [[362, 840], [362, 200]], r: 3, e: 0.42, h: 2.6, neon: true },
    { id: 'rampe-g', pts: [[52, 478], [52, 404]], r: 3, e: 0.5, h: 2.2, neon: false },
    // Rail droit plus court que les cibles de gauche : pas de creux où la bille se pose entre les deux.
    { id: 'rampe-d', pts: [[100, 478], [100, 428]], r: 3, e: 0.5, h: 2.2, neon: false },
    { id: 'poche-haut', pts: [[306, 312], [362, 292]], r: 3, e: 0.45, h: 2.4, neon: true },
    { id: 'poche-bas', pts: [[306, 408], [362, 430]], r: 3, e: 0.45, h: 2.4, neon: true },
    // Fond de la poche, juste derrière les cibles de droite : une cible tombée n'ouvre pas de trou.
    { id: 'poche-fond', pts: [[313, 310], [313, 410]], r: 3, e: 0.4, h: 2.4, neon: false },
    // Guides des couloirs du bas : leur bord arrive à hauteur du pied du batteur (pas de marche où
    // la bille s'arrête en descendant).
    { id: 'couloir-g', pts: [[44, 562], [44, 690], [112.6, 734.9]], r: 3, e: 0.45, h: 2.2, neon: true },
    { id: 'couloir-d', pts: [[330, 562], [330, 690], [261.4, 734.9]], r: 3, e: 0.45, h: 2.2, neon: true },
  ];
  // Rails des couloirs du haut (chiffres de l'année de naissance).
  const rails = [118, 158, 198, 238, 278].map(x => ({ pts: [[x, 66], [x, 108]], r: 3, e: 0.5 }));
  const lanes = [138, 178, 218, 258].map((x, i) => ({ x, y: 92, digit: digits[i] }));
  // Poteaux en caoutchouc (haut des couloirs du bas).
  const posts = [{ x: 44, y: 560, r: 6 }, { x: 330, y: 560, r: 6 }];
  const bumpers = [{ x: 158, y: 192, r: 23 }, { x: 242, y: 192, r: 23 }, { x: 200, y: 264, r: 23 }];
  // Lance-pierres : face active (a → c) qui renvoie la bille. Pointe du bas courte et loin des
  // batteurs : un tir de batteur ne cogne pas dedans.
  const slings = [
    { side: 'L', a: [76, 604], b: [76, 652], c: [96, 664] },
    { side: 'R', a: [298, 604], b: [298, 652], c: [278, 664] },
  ];
  // Cibles : deux rangées verticales, faces tournées vers le centre.
  const targets = [];
  const nl = Math.ceil(letters.length / 2);
  const left = letters.slice(0, nl), right = letters.slice(nl);
  // Rangée de gauche : y 296 → 408 ; rangée de droite : fond de la poche, y 318 → 402 (fermée).
  const lh = 112 / left.length, rh = right.length ? 84 / right.length : 0;
  [...left].forEach((c, i) => targets.push({ letter: c, side: 'L', x: 118, y1: 296 + i * lh + 1.5, y2: 296 + (i + 1) * lh - 1.5, nx: 1 }));
  [...right].forEach((c, i) => targets.push({ letter: c, side: 'R', x: 306, y1: 318 + i * rh + 1.5, y2: 318 + (i + 1) * rh - 1.5, nx: -1 }));
  const flippers = [
    { side: 'L', px: 112, py: 744, len: 64, r1: 11, r2: 6, rest: 30 * Math.PI / 180, up: -28 * Math.PI / 180 },
    { side: 'R', px: 262, py: 744, len: 64, r1: 11, r2: 6, rest: 150 * Math.PI / 180, up: 208 * Math.PI / 180 },
  ];
  // Rampe : (x, y, hauteur) de l'entrée à la sortie ; elle passe au-dessus du plateau.
  const rampPts = spline([[76, 452, 0], [72, 380, 9], [70, 300, 24], [76, 220, 40], [96, 152, 52], [130, 114, 56], [160, 128, 38], [176, 152, 0]], 8);
  let L = 0;
  const ramp = rampPts.map((p, i) => {
    if (i) L += Math.hypot(p[0] - rampPts[i - 1][0], p[1] - rampPts[i - 1][1]);
    return { x: p[0], y: p[1], h: Math.max(0, p[2]), s: L };
  });
  const apex = ramp.reduce((m, p, i) => (p.h > ramp[m].h ? i : m), 0);
  return {
    letters, digits, walls, rails, lanes, posts, bumpers, slings, targets, flippers,
    ramp: { pts: ramp, length: L, apex, mouth: { x1: 54, x2: 98, y: 448 } },
    saucer: { x: 200, y: 440, r: 13 },
    kickback: { x: 28, y: 760, r: 14 },
    plunger: { x: 376, top: 800, ball: 790 },
    // Portillon : part du haut du mur du couloir (pas de creux entre les deux où la bille s'arrête).
    gate: { a: [362, 200], b: [390, 172] },
    drainY: 832,
  };
}
