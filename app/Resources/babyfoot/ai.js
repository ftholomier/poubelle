// L'ordinateur du babyfoot (quatre niveaux) et l'aide au joueur humain.
// Chaque barre suit la balle : elle vise l'endroit où la balle croisera sa ligne (rebonds sur les
// bandes compris), avec un temps de réaction, une vitesse de glissement et une petite erreur qui
// dépendent du niveau. Quand la balle est devant une figurine, l'ordinateur frappe ; aux niveaux
// Ligue 2 et Ligue 1, il décale d'abord la figurine pour tirer en coin, loin du gardien.
// Les barres qui voient arriver une balle de derrière (tir d'un coéquipier) se lèvent pour la
// laisser passer. Pour le joueur humain, Brain ne pilote que les barres qu'il ne tient pas (aide
// au placement), sans jamais frapper à sa place.
import { LX, LZ, R, GOAL, FOOT, FA, FR, LIFT } from './table.js';

export const LEVELS = [
  // Débutant : le joueur n'a qu'à tirer (ses barres se placent seules) ; l'ordinateur joue
  // correctement pour que le match reste disputé (réglé par simulation : un joueur hésitant gagne
  // environ 3 matchs sur 4).
  { speed: 60, react: 0.17, err: 2, kick: 0.78, delay: 0.16, power: [0.42, 0.88], aim: 0.45, think: 0.27 },
  // À partir d'Amateur, le joueur glisse lui-même ses barres. Amateur : lent, en retard, imprécis,
  // tire mou et tard (la balle a souvent filé).
  { speed: 30, react: 0.34, err: 3.9, kick: 0.45, delay: 0.36, power: [0.12, 0.5], aim: 0, think: 0.55 },
  // Ligue 2
  { speed: 40, react: 0.26, err: 3, kick: 0.58, delay: 0.27, power: [0.28, 0.72], aim: 0.22, think: 0.4 },
  // Ligue 1 : rapide, précis, tire fort et en coin.
  { speed: 48, react: 0.21, err: 2.5, kick: 0.68, delay: 0.21, power: [0.45, 0.9], aim: 0.5, think: 0.3 },
];
/** Niveau Débutant (commandes simples) : l'aide place toutes les barres du joueur. */
export const SIMPLE = { speed: 42, react: 0.22, err: 2.6, kick: 0, delay: 0, power: [0, 0], aim: 0, think: 0.3 };
/** Aide aux barres que l'humain ne tient pas. */
export const ASSIST = { speed: 55, react: 0.15, err: 1.8, kick: 0, delay: 0, power: [0, 0], aim: 0, think: 0.3 };

/** Portée d'un pied vers l'avant (distance balle-barre la plus grande pour une frappe). */
export const REACH = FOOT * Math.sin(LIFT) + FR + R;

/** z d'une balle partie de z à la vitesse vz, au bout de t secondes (rebonds sur les bandes compris). */
export function crossZ(z, vz, t) {
  const lim = LZ - R, span = 4 * lim;
  let p = ((z + vz * t + lim) % span + span) % span;
  if (p > 2 * lim) p = span - p;
  return p - lim;
}

export class Brain {
  /**
   * phys : la physique ; team : 0 ou 1 ; lv : réglages (LEVELS[n] ou ASSIST) ;
   * opt.human : fonction () => indice de la barre tenue par l'humain (-1 si aucune).
   */
  constructor(phys, team, lv, opt = {}) {
    this.p = phys;
    this.team = team;
    this.lv = lv;
    this.human = opt.human || null;
    this.rods = phys.rods.filter(r => r.team === team);
    this.hist = [];
    this.err = new Map();
    this.cool = new Map();
    this.plan = new Map();
    this.seenAt = new Map();
    this.t = 0;
    this.rand = opt.rand || Math.random;
  }

  /** La balle telle que l'ordinateur la « voit » (avec son temps de réaction). */
  seen() {
    const want = this.t - this.lv.react;
    let s = this.hist[0];
    for (const h of this.hist) { if (h.t <= want) s = h; else break; }
    return s || { x: this.p.ball.x, z: this.p.ball.z, vx: 0, vz: 0 };
  }

  update(dt) {
    const p = this.p, b = p.ball;
    this.t += dt;
    this.hist.push({ t: this.t, x: b.x, z: b.z, vx: b.vx, vz: b.vz });
    while (this.hist.length > 2 && this.hist[1].t < this.t - this.lv.react - 0.05) this.hist.shift();
    const held = this.human ? this.human() : -1;
    const s = this.seen();
    for (const r of this.rods) {
      r.maxV = r.i === held ? 260 : this.lv.speed;
      this.liftRod(r, b);
      if (r.i === held) continue;
      if (!b.live || b.goal) { p.slideTo(r.i, r.z * 0.98); continue; }
      // Petite erreur de placement, retirée de temps en temps.
      const e = this.err.get(r.i);
      if (!e || e.until < this.t) this.err.set(r.i, { v: (this.rand() * 2 - 1) * this.lv.err, until: this.t + this.lv.think * (1 + this.rand()) });
      const zAim = this.aimZ(r, s);
      p.slideTo(r.i, zAim + this.err.get(r.i).v);
      if (this.lv.kick > 0) this.tryKick(r, b);
    }
  }

  /** Décalage de barre voulu : la meilleure figurine sous la balle (ou en position de tir). */
  aimZ(r, s) {
    let z = s.z;
    const toward = (r.x - s.x) * s.vx > 0 && Math.abs(s.vx) > 8;
    if (toward) {
      const t = (r.x - s.x) / s.vx;
      const zc = crossZ(s.z, s.vz, t);
      // Balle lointaine : on se place à mi-chemin entre la balle et le point d'arrivée.
      z = t < 1.2 ? zc : s.z + (zc - s.z) * (1.2 / t);
    }
    const d = (s.x - r.x) * r.dir;
    // Gardien et défense : on garde le but quand la balle est loin.
    if (r.role === 'G') z = Math.max(-GOAL - 1, Math.min(GOAL + 1, z * (d > 30 ? 0.5 : 1)));
    // Ligue 2 / Ligue 1 : balle lente devant une figurine qui attaque → on décale pour tirer en coin.
    const plan = this.plan.get(r.i);
    if (plan && plan.until > this.t) return plan.z;
    let best = null;
    for (let k = 0; k < r.n; k++) {
      const want = z - r.offs[k];
      const off = Math.max(-r.travel, Math.min(r.travel, want));
      const miss = Math.abs(off - want);
      const cost = miss * 3 + Math.abs(off - r.z);
      if (!best || cost < best.cost) best = { cost, off };
    }
    return best.off;
  }

  /** Lever la barre quand la balle arrive de derrière (tir ou passe d'un coéquipier). */
  liftRod(r, b) {
    const d = (b.x - r.x) * r.dir;           // < 0 : balle derrière
    const v = b.vx * r.dir;                   // > 0 : elle avance
    const coming = b.live && !b.goal && d < -(FR + R) && d > -(4 + v * 0.14) && v > 25;
    const passing = b.live && r.mode === 'lift' && Math.abs(d) < FOOT + 3 && v > 5;
    this.p.lift(r.i, coming || passing);
  }

  tryKick(r, b) {
    const p = this.p, lv = this.lv;
    if (r.mode === 'wind') {
      // Passe en retrait finie : on relâche doucement.
      if (this.t > (this.cool.get(r.i) || 0) - 0.3) p.strike(r.i, 0);
      return;
    }
    if (r.mode === 'strike' || r.mode === 'lift' || !b.live || b.goal) return;
    if ((this.cool.get(r.i) || 0) > this.t) return;
    const d = (b.x - r.x) * r.dir;
    // Balle lente juste derrière, qu'aucune barre de l'équipe n'a devant elle : passe en retrait
    // (la figurine armée la repousse vers la barre de derrière).
    if (d < -(FR + R) + 0.6 && d > -(REACH - 0.6) && Math.hypot(b.vx, b.vz) < 15 &&
        r.offs.some(o => Math.abs(b.z - (r.z + o)) < FA + FR + R - 0.5) &&
        !this.rods.some(q => { const dq = (b.x - q.x) * q.dir; return dq > 0 && dq < REACH; })) {
      p.windup(r.i);
      this.cool.set(r.i, this.t + 0.6);
      return;
    }
    let k = -1;
    if (d >= FR + R - 0.6 && d <= REACH - 0.4) for (let j = 0; j < r.n; j++) if (Math.abs(b.z - (r.z + r.offs[j])) < FA + FR + R - 0.5) k = j;
    if (k < 0) { this.seenAt.delete(r.i); return; }
    // Temps de réaction avant la frappe (la balle qui roule a parfois déjà filé).
    if (!this.seenAt.has(r.i)) this.seenAt.set(r.i, this.t);
    if (this.t - this.seenAt.get(r.i) < lv.delay) return;
    // Visée en coin (balle presque arrêtée devant une figurine qui peut tirer).
    if (lv.aim > 0 && Math.hypot(b.vx, b.vz) < 40 && r.role !== 'G' && this.rand() < lv.aim) {
      const cur = this.plan.get(r.i);
      if (!cur || cur.ball !== Math.round(b.x * 4) + ':' + Math.round(b.z * 4)) {
        const keeper = p.rods.find(q => q.team !== this.team && q.role === 'G');
        const gz = (keeper && keeper.z > 0 ? -1 : 1) * GOAL * (0.45 + 0.25 * lv.aim);
        const gx = r.dir * LX;
        const slope = (gz - b.z) / Math.abs(gx - b.x);
        const L = FR + R, dz = Math.sign(slope) * Math.min(1.9, Math.abs(slope) * L / Math.sqrt(1 + slope * slope) * 1.25);
        const fz = Math.abs(dz) < 0.15 ? b.z : b.z - Math.sign(dz) * FA - dz;
        const off = Math.max(-r.travel, Math.min(r.travel, fz - r.offs[k]));
        this.plan.set(r.i, { z: off, until: this.t + 0.5, ball: Math.round(b.x * 4) + ':' + Math.round(b.z * 4) });
      }
      const plan = this.plan.get(r.i);
      if (Math.abs(r.z - plan.z) > 0.35 && plan.until > this.t) return;
    }
    this.plan.delete(r.i);
    this.cool.set(r.i, this.t + 0.35 + (1 - lv.kick) * 0.4);
    if (this.rand() > lv.kick) return;
    const [lo, hi] = lv.power;
    p.strike(r.i, lo + (hi - lo) * this.rand());
  }
}

/**
 * Barre que tient le joueur humain : celle de son équipe qui peut jouer la balle (la balle un peu
 * devant elle de préférence), en tenant compte de l'endroit où la balle va. Pour éviter qu'elle
 * clignote d'une barre à l'autre, on ne change que pour une barre nettement mieux placée.
 */
export function activeRod(phys, team, current = -1) {
  const b = phys.ball;
  const x = b.x + Math.max(-12, Math.min(12, b.vx * 0.08));
  let best = -1, bestCost = Infinity, curCost = Infinity;
  for (const r of phys.rods) {
    if (r.team !== team) continue;
    const d = (x - r.x) * r.dir;
    // Balle devant, à portée : idéal vers 2,5 ; derrière : moins bien ; loin devant : la barre suivante.
    const cost = d >= 0 ? Math.abs(d - 2.5) : 4 - d * 1.6;
    if (cost < bestCost) { bestCost = cost; best = r.i; }
    if (r.i === current) curCost = cost;
  }
  return current >= 0 && curCost - bestCost < 2.5 ? current : best;
}

/**
 * Commandes simples : la barre de l'équipe qui peut frapper la balle tout de suite (balle devant
 * une figurine, à portée), la mieux placée ; -1 si aucune.
 */
export function kickable(phys, team) {
  const b = phys.ball;
  if (!b.live || b.goal) return -1;
  let best = -1, bestCost = Infinity;
  for (const r of phys.rods) {
    if (r.team !== team || r.mode === 'strike' || r.mode === 'lift') continue;
    const d = (b.x - r.x) * r.dir;
    if (d < FR + R - 0.8 || d > REACH) continue;
    if (!r.offs.some(o => Math.abs(b.z - (r.z + o)) < FA + FR + R)) continue;
    const cost = Math.abs(d - 3.5);
    if (cost < bestCost) { bestCost = cost; best = r.i; }
  }
  return best;
}
