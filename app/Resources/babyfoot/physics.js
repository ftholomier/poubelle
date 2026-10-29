// Physique du babyfoot : la balle roule sur le terrain (frottement), rebondit sur les bandes, les
// poteaux et les pieds des figurines. Chaque barre glisse en travers (course limitée par ses butées)
// et tourne sur elle-même : armer (figurine levée en arrière), frapper (elle pivote vers l'avant,
// d'autant plus vite que le tir est fort), revenir. Le pied qui frappe donne sa vitesse à la balle ;
// levé au-delà de LIFT, il passe au-dessus. Pas fixe de 1 ms : aucune balle ne traverse un pied.
import { LX, LZ, R, GOAL, DEPTH, FOOT, FA, FR, LIFT, buildRods, buildWalls, POSTS } from './table.js';

const STEP = 0.001;
/** Frottement du tapis : ralentissement proportionnel à la vitesse (par s) + ralentissement fixe. */
const DRAG = 0.32;
const ROLL = 5;
/** Vitesse maximale de la balle (cm/s). */
export const VMAX = 230;
/** Pente douce le long des bandes et des fonds (la balle ne meurt pas contre un bord). */
const SLOPE = 30;
/** Au-delà (entre la défense et le but), les coins penchent vers le centre : le gardien y arrive. */
const CORNER = 41;
/** Rebond sur un pied, frottement de glissement au contact. */
const E_FOOT = 0.45;
const MU_FOOT = 0.12;
/** Armer : angle (en arrière) et vitesse ; retour à la verticale. */
export const WIND = -0.8;
const WIND_SPEED = 11;
const BACK_SPEED = 7;
const RELAX = 10;
/** Barre levée (figurines à l'horizontale vers l'avant) : la balle passe dessous. */
const LIFTED = 1.35;
const LIFT_SPEED = 14;
/** Frappe : fin du geste (figurine levée en avant). Vitesse de rotation : de KICK_MIN à KICK_MIN + KICK_ADD rad/s. */
const STRIKE_END = 1.3;
export const KICK_MIN = 9;
export const KICK_ADD = 13;

export class Physics {
  constructor() {
    this.rods = buildRods().map(r => ({ ...r, z: 0, vz: 0, target: 0, maxV: 240, ang: 0, av: 0, mode: 'idle', omega: 0, kickAt: -1 }));
    this.walls = buildWalls();
    this.ball = { x: 0, z: 0, vx: 0, vz: 0, live: false, goal: 0, spin: 0 };
    this.events = [];
    this.time = 0;
    this.acc = 0;
    /** Dernière figurine à avoir touché la balle : { team, rod, k, t } ; et la dernière de chaque équipe. */
    this.touch = null;
    this.touchBy = [null, null];
  }

  // ------------------------------------------------------------------ commandes

  /** Position voulue d'une barre (décalage le long de son axe), bornée par les butées. */
  slideTo(i, z) {
    const r = this.rods[i];
    r.target = Math.max(-r.travel, Math.min(r.travel, z));
  }

  /** Armer : la figurine se lève en arrière (bouton tenu). */
  windup(i) {
    const r = this.rods[i];
    if (r.mode === 'idle' || r.mode === 'back') r.mode = 'wind';
  }

  /**
   * Lever la barre (on = true) pour laisser passer une balle qui arrive de derrière (passe ou tir
   * d'un coéquipier), ou la reposer (on = false).
   */
  lift(i, on) {
    const r = this.rods[i];
    if (on && (r.mode === 'idle' || r.mode === 'back')) r.mode = 'lift';
    else if (!on && r.mode === 'lift') r.mode = 'back';
  }

  /** Barre armée ou levée qu'on n'utilise plus : elle revient doucement à la verticale. */
  relax(i) {
    const r = this.rods[i];
    if (r.mode === 'wind' || r.mode === 'lift') r.mode = 'idle';
  }

  /** Frapper (bouton lâché) : power de 0 (petite touche) à 1 (pleine puissance). */
  strike(i, power = 0.6) {
    const r = this.rods[i];
    if (r.mode === 'strike') return;
    r.mode = 'strike';
    r.omega = KICK_MIN + KICK_ADD * Math.max(0, Math.min(1, power));
    r.kickAt = this.time;
  }

  /** Engagement : la balle entre par le trou de la bande (côté side = ±1) et roule en travers. */
  serve(x, side = 1, speed = 24) {
    const b = this.ball;
    Object.assign(b, { x, z: side * (LZ - R - 0.5), vx: 0, vz: -side * speed, live: true, goal: 0 });
    this.touch = null;
    this.touchBy = [null, null];
    this.events.push({ type: 'serve', x, side });
  }

  /** Balle posée (balle morte rendue au gardien, tests). */
  place(x, z, vx = 0, vz = 0) {
    Object.assign(this.ball, { x, z, vx, vz, live: true, goal: 0 });
  }

  // ------------------------------------------------------------------ simulation

  update(dt) {
    this.acc += dt;
    let n = 0;
    while (this.acc >= STEP && n < 60) { this.step(STEP); this.acc -= STEP; n++; }
    if (n === 60) this.acc = 0;
  }

  step(dt) {
    this.time += dt;
    for (const r of this.rods) this.moveRod(r, dt);
    const b = this.ball;
    if (!b.live) return;
    // Frottement du tapis.
    let v = Math.hypot(b.vx, b.vz);
    if (v > 0) {
      const nv = Math.max(0, v * (1 - DRAG * dt) - ROLL * dt);
      b.vx *= nv / v; b.vz *= nv / v; v = nv;
    }
    // Bords légèrement relevés (pas dans la cage) ; coins des surfaces en pente vers le gardien.
    if (!b.goal) {
      if (Math.abs(b.z) > LZ - R - 2.5) b.vz -= Math.sign(b.z) * SLOPE * dt;
      if (Math.abs(b.x) > LX - R - 4 && Math.abs(b.z) > GOAL + 1) b.vx -= Math.sign(b.x) * SLOPE * dt;
      if (Math.abs(b.x) > CORNER && Math.abs(b.z) > GOAL + 2) b.vz -= Math.sign(b.z) * SLOPE * dt;
    }
    // Dans la cage, la balle s'arrête vite et ne ressort pas.
    if (b.goal) {
      const k = Math.max(0, 1 - 5 * dt);
      b.vx *= k; b.vz *= k;
    }
    b.x += b.vx * dt;
    b.z += b.vz * dt;
    if (b.goal && b.goal * b.x < LX + R * 0.4) { b.x = b.goal * (LX + R * 0.4); if (b.goal * b.vx < 0) b.vx = 0; }
    b.spin += v * dt / R;
    for (const w of this.walls) this.wallHit(b, w);
    for (const p of POSTS) this.postHit(b, p);
    if (!b.goal) for (const r of this.rods) if (Math.abs(b.x - r.x) < FOOT + FR + R + 1) this.rodHit(b, r);
    // But : la balle a franchi la ligne entre les poteaux.
    if (!b.goal && Math.abs(b.x) > LX + R * 0.4 && Math.abs(b.z) < GOAL) {
      b.goal = Math.sign(b.x);
      const team = b.goal > 0 ? 0 : 1, by = this.touchBy[team];
      // Buteur : la dernière figurine de l'équipe qui marque (une déviation adverse ne lui retire pas
      // le but) ; personne de l'équipe depuis 4 s : contre son camp.
      this.events.push({ type: 'goal', side: b.goal, team, speed: Math.hypot(b.vx, b.vz), touch: by && this.time - by.t < 4 ? by : this.touch });
    }
    // Sécurité : balle sortie du jeu (ne devrait pas arriver).
    if (Math.abs(b.z) > LZ + 3 || Math.abs(b.x) > LX + DEPTH + 3) {
      b.live = false;
      this.events.push({ type: 'out' });
    }
    const s = Math.hypot(b.vx, b.vz);
    if (s > VMAX) { b.vx *= VMAX / s; b.vz *= VMAX / s; }
  }

  moveRod(r, dt) {
    const dz = r.target - r.z, max = r.maxV * dt;
    const step = Math.max(-max, Math.min(max, dz));
    r.z += step;
    r.vz = step / dt;
    const a0 = r.ang;
    switch (r.mode) {
      case 'wind': r.ang = Math.max(WIND, r.ang - WIND_SPEED * dt); break;
      case 'lift': r.ang = Math.min(LIFTED, r.ang + LIFT_SPEED * dt); break;
      case 'strike':
        r.ang += r.omega * dt;
        if (r.ang >= STRIKE_END) { r.ang = STRIKE_END; r.mode = 'back'; }
        break;
      case 'back':
        r.ang = Math.max(0, r.ang - BACK_SPEED * dt);
        if (r.ang === 0) r.mode = 'idle';
        break;
      default:
        r.ang += Math.max(-RELAX * dt, Math.min(RELAX * dt, -r.ang));
    }
    r.av = (r.ang - a0) / dt;
  }

  /** Contact balle / pied (segment épaissi, mobile : glissement de la barre et rotation). */
  rodHit(b, r) {
    if (Math.abs(r.ang) > LIFT) return;
    const fx = r.x + r.dir * FOOT * Math.sin(r.ang);
    const fvx = r.dir * FOOT * Math.cos(r.ang) * r.av;
    for (let k = 0; k < r.n; k++) {
      const fz = r.z + r.offs[k];
      const pz = Math.max(fz - FA, Math.min(fz + FA, b.z));
      let nx = b.x - fx, nz = b.z - pz;
      const d = Math.hypot(nx, nz), min = FR + R;
      if (d >= min) continue;
      if (d < 1e-6) { nx = r.dir; nz = 0; } else { nx /= d; nz /= d; }
      // Balle repoussée hors du pied.
      b.x += nx * (min - d);
      b.z += nz * (min - d);
      const rx = b.vx - fvx, rz = b.vz - r.vz;
      const vn = rx * nx + rz * nz;
      if (vn >= 0) continue;
      const tx = rx - vn * nx, tz = rz - vn * nz;
      const nrx = rx - (1 + E_FOOT) * vn * nx - MU_FOOT * tx;
      const nrz = rz - (1 + E_FOOT) * vn * nz - MU_FOOT * tz;
      const ox = b.vx, oz = b.vz;
      b.vx = nrx + fvx; b.vz = nrz + r.vz;
      const dv = Math.hypot(b.vx - ox, b.vz - oz);
      this.touch = this.touchBy[r.team] = { team: r.team, rod: r.i, k, t: this.time };
      if (dv > 6) this.events.push({ type: 'hit', team: r.team, rod: r.i, k, dv, kick: r.mode === 'strike', vx: b.vx, vz: b.vz, x: b.x, z: b.z });
    }
  }

  wallHit(b, w) {
    const ex = w.bx - w.ax, ez = w.bz - w.az, L2 = ex * ex + ez * ez;
    let t = ((b.x - w.ax) * ex + (b.z - w.az) * ez) / L2;
    t = Math.max(0, Math.min(1, t));
    const px = w.ax + ex * t, pz = w.az + ez * t;
    let nx = b.x - px, nz = b.z - pz;
    const d = Math.hypot(nx, nz);
    if (d >= R || d < 1e-9) return;
    nx /= d; nz /= d;
    b.x += nx * (R - d); b.z += nz * (R - d);
    const vn = b.vx * nx + b.vz * nz;
    if (vn >= 0) return;
    b.vx -= (1 + w.e) * vn * nx;
    b.vz -= (1 + w.e) * vn * nz;
    // Un peu de vitesse perdue le long de la bande.
    b.vx *= 0.985; b.vz *= 0.985;
    if (-vn > 15) this.events.push({ type: w.kind, speed: -vn, x: b.x, z: b.z });
  }

  postHit(b, p) {
    let nx = b.x - p.x, nz = b.z - p.z;
    const d = Math.hypot(nx, nz), min = R + p.r;
    if (d >= min || d < 1e-9) return;
    nx /= d; nz /= d;
    b.x += nx * (min - d); b.z += nz * (min - d);
    const vn = b.vx * nx + b.vz * nz;
    if (vn >= 0) return;
    b.vx -= 1.6 * vn * nx;
    b.vz -= 1.6 * vn * nz;
    if (-vn > 15) this.events.push({ type: 'poteau', speed: -vn, x: b.x, z: b.z });
  }

  /** Position (vue de dessus) du pied de la figurine k de la barre i. */
  foot(i, k) {
    const r = this.rods[i];
    return { x: r.x + r.dir * FOOT * Math.sin(r.ang), z: r.z + r.offs[k], low: Math.abs(r.ang) <= LIFT };
  }

  /** Instantané (ralenti du but) : balle et barres. */
  snapshot() {
    const b = this.ball;
    return { b: [b.x, b.z, b.spin, b.goal], r: this.rods.map(r => [r.z, r.ang]) };
  }
}
