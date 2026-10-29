// Le plateau du flipper « au stade », relevé repère par repère sur le schéma de principe validé
// (« Football Super League », vue de dessus) : coordonnées du plan en pixels, x de 0 à 400 de gauche
// à droite (couloir de lancement compris), y de 0 à 874 du fond vers le joueur (tablier compris).
// Deux niveaux : le plateau (niveau 0) et, en haut, le mini-terrain surélevé (niveau 1 : but et
// gardien, trois rangées de joueurs adverses mobiles, trous d'éjection, mini-flippers). Les numéros
// entre crochets renvoient aux repères du schéma. Le schéma n'étant pas à l'échelle, quelques couloirs
// sont élargis juste assez pour laisser passer la bille. Partagé par la physique et le rendu 3D.

export const W = 400;
export const H = 874;
/** Rayon de la bille. */
export const R = 10;
/** Hauteur du mini-terrain au-dessus du plateau (pixels du plan). */
export const HM = 56;

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

/** Fin commune des rails qui ramènent la bille vers l'inlane droit [10]. */
const RAIL_D = [[132, 280, 70], [200, 308, 62], [270, 337, 54], [330, 362, 46], [378, 376, 40], [388, 402, 34], [388, 442, 28], [380, 500, 20], [360, 550, 12], [334, 582, 6], [313, 604, 2], [302, 618, 0]];

export function buildTable() {
  // Murs : tracés (polylignes). h : hauteur au rendu ; neon : liseré lumineux ; r : demi-épaisseur.
  // wire : guide en fil métallique (fin) ; block : pied du mini-terrain (dessiné à part).
  const walls = [
    // Cadre gauche, du haut de la zone de jeu jusqu'au flipper TACKLE, puis bords bas vers l'évacuation.
    { id: 'cadre-g', pts: [[3, 292], [3, 708]], r: 3, e: 0.42, h: 3.6, neon: true },
    { id: 'bas-g', pts: [[2, 722], [30, 744], [56, 752], [162, 810]], r: 3, e: 0.4, h: 2.4, neon: true },
    { id: 'bas-d', pts: [[367, 722], [339, 744], [313, 752], [207, 810]], r: 3, e: 0.4, h: 2.4, neon: true },
    // Couloir de lancement [28] : paroi intérieure (qui borde aussi l'outlane droite) et paroi
    // extérieure, toutes deux recourbées en haut vers le plateau.
    { id: 'lanceur-int', pts: [[366, 874], [366, 482], [362, 466], [352, 454], [339, 447]], r: 3, e: 0.42, h: 2.6, neon: true },
    { id: 'lanceur-ext', pts: [[396, 874], [396, 470], [392, 446], [382, 426], [368, 404], [358, 380], [358, 362]], r: 3, e: 0.42, h: 3.6, neon: true },
    // Couloir UNE-DEUX droit [16], sous la sortie de la rampe CENTRE, dans le prolongement du couloir
    // de lancement ; son mur gauche est aussi le rail droit de l'entrée BALLON LONG [9].
    { id: 'une-deux-d', pts: [[303, 404], [320, 352], [324, 346], [348, 350], [358, 362]], r: 3, e: 0.45, h: 2.2, neon: false },
    // Entrée BALLON LONG [9] : rail gauche, fermé contre le fer à cheval.
    { id: 'ballon-long-g', pts: [[258, 385], [284, 334], [281.4, 327]], r: 3, e: 0.45, h: 2.2, neon: false },
    { id: 'coin-fer', pts: [[259.4, 373.6], [258, 385]], r: 3, e: 0.45, h: 1.2, neon: false },
    // Poche LOCK MILIEU [15] : fer à cheval en fil, ouvert vers le bas.
    { id: 'fer-a-cheval', pts: [[224, 360], [244.8, 331.3], [253.6, 321.1], [266.7, 310.8], [279.9, 316.7], [281.4, 327], [259.4, 373.6]], r: 2.5, e: 0.45, h: 1.6, wire: true },
    { id: 'fer-haut', pts: [[266.7, 310.8], [262, 288]], r: 3, e: 0.45, h: 1.2, neon: false },
    // Couloir UNE-DEUX central [14], sous la pointe du mini-terrain : deux guides en fil.
    { id: 'une-deux-c-g', pts: [[177, 279], [180, 344]], r: 2.5, e: 0.45, h: 1.6, wire: true },
    { id: 'une-deux-c-d', pts: [[226, 300], [224, 360]], r: 2.5, e: 0.45, h: 1.6, wire: true },
    { id: 'acces-une-deux', pts: [[163, 284], [177, 279]], r: 3, e: 0.45, h: 1.2, neon: false },
    // Accès mini-terrain [13] : rampe courte entre deux rails.
    { id: 'acces-g', pts: [[128, 284], [130, 264]], r: 3, e: 0.45, h: 2, neon: false },
    { id: 'acces-d', pts: [[163, 284], [161, 264]], r: 3, e: 0.45, h: 2, neon: false },
    { id: 'poche-acces', pts: [[112, 273], [128, 283]], r: 3, e: 0.45, h: 1.6, neon: false },
    // Poche LOCK ATTACKER [12] : ouverte vers le bas.
    { id: 'poche-attaquant', pts: [[82, 276], [82, 240], [88, 232], [106, 232], [112, 240], [112, 273]], r: 3, e: 0.4, h: 2.4, neon: true },
    // Entrée de la rampe CENTRE [8] : rails gauche et droit (l'entrée est en biais).
    { id: 'centre-d', pts: [[115, 356], [98, 312], [82, 276]], r: 3, e: 0.45, h: 2.2, neon: false },
    { id: 'centre-g', pts: [[70, 372], [60, 330], [54, 289]], r: 3, e: 0.45, h: 2.2, neon: false },
    // Entrée de la rampe PASSE [7] : deux rails ; à gauche, le bas du rail DRIBBLE.
    { id: 'passe-g', pts: [[22, 289], [22, 250]], r: 3, e: 0.45, h: 2.2, neon: false },
    { id: 'passe-d', pts: [[54, 289], [54, 250]], r: 3, e: 0.45, h: 2.2, neon: false },
    { id: 'coin-g', pts: [[3, 292], [22, 289]], r: 3, e: 0.45, h: 2.2, neon: false },
    // TRICK [11] : trou sur le flanc gauche, dans un guide en U ouvert vers le haut.
    { id: 'trick', pts: [[8, 376], [9, 398], [19, 404], [29, 401], [34, 386]], r: 2.5, e: 0.35, h: 1.6, wire: true },
    // Couloirs du bas : guides des inlanes vers les flippers principaux, plots des outlanes [25].
    { id: 'inlane-g', pts: [[32, 671], [125, 744]], r: 3, e: 0.45, h: 2.2, neon: true },
    { id: 'inlane-d', pts: [[337, 671], [244, 744]], r: 3, e: 0.45, h: 2.2, neon: true },
    { id: 'plot-g', pts: [[32, 671], [32, 594], [42, 584], [52, 594], [52, 671]], r: 3, e: 0.45, h: 2.4, neon: true },
    { id: 'plot-d', pts: [[317, 671], [317, 594], [327, 584], [337, 594], [337, 671]], r: 3, e: 0.45, h: 2.4, neon: true },
    // Pied du mini-terrain [1] (niveau 0) : un bloc en pointe vers le bas.
    { id: 'mini', pts: [[140, 78], [319, 78], [319, 240], [245, 299], [212.6, 299], [140, 240], [140, 78]], r: 3, e: 0.45, h: 0, block: true },
  ];
  // Bumpers [18] : groupe de trois en triangle, à gauche.
  const bumpers = [{ x: 72, y: 410, r: 15 }, { x: 35, y: 440, r: 15 }, { x: 100, y: 480, r: 15 }];
  // Slingshots HOME / AWAY [23] : triangles hauts et étroits ; face active a → c (vers le centre).
  const slings = [
    { side: 'L', a: [88, 588], b: [84, 668], c: [114, 684], screen: [96, 630] },
    { side: 'R', a: [281, 588], b: [285, 668], c: [255, 684], screen: [273, 630] },
  ];
  // Kicker latéral [20] : élastique tendu entre deux plots, côté droit (face active vers le centre).
  const kicker = { a: [334, 454], b: [315, 504] };
  // Flippers : principaux [27], TACKLE en bas des outlanes [26], mini-flippers du mini-terrain [5].
  const deg = Math.PI / 180;
  const flippers = [
    { side: 'L', px: 129.4, py: 754.3, len: 47, r1: 10, r2: 5, rest: 34 * deg, up: -24 * deg },
    { side: 'R', px: 239.5, py: 754.3, len: 47, r1: 10, r2: 5, rest: 146 * deg, up: 204 * deg },
    { side: 'L', px: 8, py: 716, len: 42, r1: 7, r2: 4.5, rest: 34 * deg, up: -22 * deg, tackle: true },
    { side: 'R', px: 361, py: 716, len: 42, r1: 7, r2: 4.5, rest: 146 * deg, up: 202 * deg, tackle: true },
    { side: 'L', px: 170, py: 259, len: 48, r1: 7, r2: 4.5, rest: 31 * deg, up: -25 * deg, lv: 1, mini: true },
    { side: 'R', px: 290, py: 261, len: 48, r1: 7, r2: 4.5, rest: 149 * deg, up: 205 * deg, lv: 1, mini: true },
  ];
  // Capteurs du plateau : inlanes LAUNCH ATTACK [24], outlanes [25], couloirs UNE-DEUX [14] [16],
  // poches LOCK [12] [15] [17], TRICK [11], trappe gauche [19].
  const sensors = [
    { tag: 'inlane', ref: 0, x: 67, y: 622, r: 9 }, { tag: 'inlane', ref: 1, x: 302, y: 622, r: 9 },
    { tag: 'outlane', ref: 0, x: 17, y: 640, r: 9 }, { tag: 'outlane', ref: 1, x: 352, y: 640, r: 9 },
    { tag: 'onetwo', ref: 0, x: 202, y: 318, r: 12 }, { tag: 'onetwo', ref: 1, x: 332, y: 376, r: 11 },
    { tag: 'lock', ref: 0, x: 97, y: 254, r: 8 }, { tag: 'lock', ref: 1, x: 262.3, y: 337, r: 8 }, { tag: 'lock', ref: 2, x: 343, y: 492, r: 8 },
    { tag: 'trick', x: 18.7, y: 391, r: 8 },
    { tag: 'trap', x: 34, y: 494, r: 8 },
  ];
  // Le mini-terrain (niveau 1) [1] : murs (ouverture à gauche pour la rampe DRIBBLE), but et
  // gardien [2], trois rangées de joueurs adverses mobiles [3] (4 défenseurs, 4 milieux, 2
  // attaquants), trous d'éjection [4], mini-flippers [5], bord avant (la bille qui passe entre les
  // mini-flippers retombe dans le couloir UNE-DEUX central).
  const mini = {
    x1: 140, x2: 319, y1: 78, y2: 299,
    walls: [
      // Bas des murets dans l'alignement des mini-flippers au repos : la bille roule sur le batteur,
      // elle ne se cale pas contre son axe.
      { pts: [[140, 126], [140, 78], [319, 78], [319, 236], [290, 254.5]], r: 3, e: 0.45 },
      { pts: [[140, 147], [140, 236], [170, 254.5]], r: 3, e: 0.45 },
      // Côtés du but (filets) : la bille qui entre reste dans la cage.
      { pts: [[181, 78], [181, 98]], r: 2, e: 0.1, net: true },
      { pts: [[277, 78], [277, 98]], r: 2, e: 0.1, net: true },
    ],
    posts: [{ x: 181, y: 98, r: 3 }, { x: 277, y: 98, r: 3 }, { x: 145, y: 232, r: 4 }, { x: 313, y: 232, r: 4 }],
    goal: { x1: 183, x2: 275, y: 95, net: 80 },
    keeper: { y: 118, hw: 7, r: 5, min: 201, max: 258 },
    // Rangées : ligne, y, positions des joueurs par rapport au centre de la rangée, amplitude et vitesse du va-et-vient.
    rows: [
      { line: 'def', y: 157, offs: [-66, -22, 22, 66], amp: 16, w: 1.3, phase: 0 },
      { line: 'mil', y: 183, offs: [-66, -22, 22, 66], amp: 16, w: 1.7, phase: 2.1 },
      { line: 'att', y: 208, offs: [-24, 24], amp: 26, w: 1.1, phase: 4 },
    ],
    // Joueurs fins, assez espacés pour qu'un tir puisse passer entre deux (but difficile, pas impossible).
    player: { hw: 3, r: 3 },
    center: 229.5,
    holes: [{ x: 162, y: 114, r: 9 }, { x: 295, y: 114, r: 9 }],
    drainY: 292,
    // La bille qui passe entre les mini-flippers retombe ici (couloir UNE-DEUX central).
    drop: { x1: 190, x2: 214, y: 312 },
  };
  // Rampes et rails [6] à [10] et [13] : (x, y, hauteur) de l'entrée à la sortie. mouth : ligne
  // d'entrée (franchie dans le sens de la rampe) et niveau ; end : pose sur le mini-terrain (lv 1),
  // sinon retour au plateau au dernier point ; wire : la rampe devient un rail en fil à partir de ce
  // point de la liste.
  const ramps = [
    // 0 · PASSE [7] : monte à gauche, épingle, débouche sur le mini-terrain.
    makeRamp('passe', [
      [39, 285, 0], [39, 240, 12], [39, 195, 26], [40, 165, 36], [46, 148, 42], [58, 140, 45], [72, 146, 48], [90, 165, 51], [106, 195, 53], [117, 218, 55], [124, 230, 56],
    ], { a: [22, 283], b: [54, 283] }, { lv: 1, x: 152, y: 226, vx: 150, vy: 70 }, 1.3),
    // 1 · DRIBBLE [6] : part du mini-terrain (côté gauche), redescend tout à gauche, rail [10] vers l'inlane gauche.
    makeRamp('dribble', [
      [138, 137, 56], [124, 130, 58], [106, 122, 60], [84, 115, 60], [56, 112, 58], [34, 114, 54], [20, 124, 50], [13, 150, 46], [13, 200, 40], [13, 271, 32],
      [12, 340, 24], [27, 362, 20], [12, 392, 17], [7, 450, 13], [7, 560, 8], [20, 600, 4], [50, 612, 1], [64, 618, 0],
    ], { a: [140, 147], b: [140, 126], lv: 1 }, null, 1.3, 9),
    // 2 · CENTRE [8] : grande boucle extérieure, sortie côté droit (dans le couloir UNE-DEUX droit).
    makeRamp('centre', [
      [92, 362, 0], [86, 332, 10], [78, 290, 26], [70, 240, 46], [66, 190, 66], [66, 150, 82], [70, 113, 92], [80, 85, 98], [93, 69, 102], [122, 40, 106],
      [172, 25, 108], [260, 19, 110], [347, 22, 108], [372, 45, 104], [378, 90, 98], [378, 150, 90], [376, 210, 78], [370, 270, 60], [360, 318, 36], [352, 348, 16], [342, 368, 0],
    ], { a: [70, 372], b: [115, 356] }, null, 1.85),
    // 3 · BALLON LONG [9] : monte à droite, revient par le fond du plateau, puis rail vers l'inlane droit.
    makeRamp('ballon-long', [
      [280, 394, 0], [292, 366, 8], [305, 333, 18], [316, 300, 28], [326, 262, 40], [334, 215, 52], [340, 165, 64], [342, 125, 74], [336, 90, 82], [318, 64, 88],
      [290, 52, 90], [250, 48, 92], [200, 48, 92], [160, 50, 90], [134, 62, 88], [120, 84, 84], [116, 110, 80], [116, 125, 78], [116, 170, 76], [117, 220, 74], [120, 262, 72], ...RAIL_D,
    ], { a: [258, 385], b: [303, 404] }, null, 1.85, 17),
    // 4 · Accès mini-terrain [13] : rampe courte sous le mini-flipper gauche.
    makeRamp('acces', [[146, 284, 0], [148, 270, 22], [152, 258, 42], [156, 248, 56]], { a: [128, 282], b: [163, 282] }, { lv: 1, x: 158, y: 232, vx: 70, vy: 40 }, 1.6),
    // 5 et 6 · rails des trous d'éjection [4] (gauche et droit), vers l'inlane droit.
    makeRamp('trou-g', [[162, 114, 56], [140, 112, 62], [124, 114, 70], [117, 140, 74], [117, 220, 72], [120, 262, 71], ...RAIL_D], null, null, 1, 0),
    makeRamp('trou-d', [[295, 114, 56], [320, 116, 58], [343, 124, 58], [343, 200, 54], [344, 255, 50], [352, 290, 47], [368, 330, 44], ...RAIL_D.slice(4)], null, null, 1, 0),
  ];
  return {
    walls, bumpers, slings, kicker, flippers, sensors, ramps, mini,
    block: { x1: 140, x2: 319, y1: 78, y2: 299 },
    plunger: { x: 381, top: 800, ball: 790 },
    // Sortie du couloir de lancement : portillon (la bille sort, ne redescend pas dans le couloir).
    gate: { a: [339, 447], b: [384, 428], n: [-0.39, -0.92] },
    drainY: 818,
    // Repères du décor (lampes et dessins du plateau, sans effet sur la bille).
    deco: {
      hexes: [[161.9, 592.6], [207.3, 592.6], [121.8, 622.5], [161.9, 629.8], [207.3, 629.8], [248.3, 622.5], [127.7, 662.9], [163.7, 668.8], [205, 668.8], [240.4, 662.9]],
      // Ligne de chacun des 10 joueurs adverses (même ordre que les hexagones) : 2 attaquants, 4 milieux, 4 défenseurs.
      hexLine: ['att', 'att', 'mil', 'mil', 'mil', 'mil', 'def', 'def', 'def', 'def'],
      goalie: [184.8, 747],
      circle: { x: 184.8, y: 596, r: 43 },
      box: { x1: 121.8, x2: 247.7, y1: 709.5, y2: 804 },
      dots: [[156.4, 335], [201.4, 352.5], [130.6, 373], [148.7, 390.3], [168, 401.7], [274, 420.5], [317.4, 433], [263.2, 443.3], [257.4, 464.4], [148.2, 540.5], [146.7, 561], [39, 305]],
      smallHexes: [[41.9, 328.2], [160.5, 357.5], [195, 376.5], [308.6, 456], [42, 609], [327, 609], [28.7, 697], [340.5, 697]],
      pentagons: [[130.6, 314.5], [227.8, 394], [303.9, 555]],
      chevrons: [[114.5, 291.6, -0.6], [247.7, 376.5, 0.6], [323.8, 521.5, 0.35]],
      modes: [[172.2, 480.5], [175, 501.3]],
      lamp: [64.7, 531.7],
      plate: { x1: 26, x2: 66, y1: 472, y2: 516 },
      mults: [['10X', 543], ['8X', 582], ['6X', 622.5], ['4X', 663], ['2X', 703], ['HOLD', 741]],
      clock: [88, 841, 23],
      combo: { x1: 270, x2: 350, y1: 829, y2: 841 },
      jerseys: { x: 325, y: 781, dx: 13.2, dy: 15.4 },
    },
  };
}

/**
 * Rampe : points de la courbe (avec longueur cumulée s), sommet, longueur, ligne d'entrée, sortie,
 * demi-largeur du ruban au rendu (unités 3D), point où elle devient un rail en fil (indice dans la
 * courbe).
 */
function makeRamp(name, pts, mouth, end, width, wireFrom = null) {
  const per = 8, sp = spline(pts, per);
  let L = 0;
  const out = sp.map((p, i) => {
    if (i) L += Math.hypot(p[0] - sp[i - 1][0], p[1] - sp[i - 1][1]);
    return { x: p[0], y: p[1], h: Math.max(0, p[2]), s: L };
  });
  const apex = out.reduce((m, p, i) => (p.h > out[m].h ? i : m), 0);
  return { name, pts: out, length: L, apex, mouth: mouth ? { lv: 0, ...mouth } : null, end, width, wire: wireFrom === null ? null : wireFrom * per };
}
