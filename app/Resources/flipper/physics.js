// Physique du flipper : bille(s) sur un plateau incliné vu de dessus, à pas fixe de 1 ms (pas de
// bille qui traverse un mur, même lancée fort). Murs et rails (segments épais), poteaux et bumpers
// (cercles), batteurs (segments effilés en rotation, dont le batteur du haut), cibles qui tombent,
// capteurs (couloirs, spinner, orbite), rampes (la bille suit un chemin en hauteur), soucoupe qui
// retient la bille. Aucun rendu ici : testable dans Node.
import { R } from './table.js';

const DT = 1 / 1000;
export const G = 1500; // gravité le long du plateau (px/s²)
const G_RAMP = 3000; // gravité « verticale » dans la rampe (il faut un tir franc pour la passer)
const RAMP_SLOPE = 0.45; // part de la pente du plateau ressentie dans la rampe
const VMAX = 3600;
const MU = 0.1; // frottement bille / murs (loi de Coulomb)
const MU_FLIP = 0.1; // frottement bille / batteurs (caoutchouc)
/**
 * Vitesse du jeu : tout le plateau vit au ralenti (temps de la physique = temps réel × SPEED), pour un
 * jeu jouable sur téléphone. Les batteurs, eux, bougent en temps réel et très vite (FLIP_UP, FLIP_DOWN
 * en rad/s réels : réactifs dès l'appui) ; la force de leur coup sur la bille est réglée à part
 * (HIT_UP, HIT_DOWN, en rad/s du temps de la physique).
 */
export const SPEED = 0.65;
const FLIP_UP = 45, FLIP_DOWN = 26;
const HIT_UP = 38, HIT_DOWN = 20;
/**
 * Bumpers et lance-pierres : coup un peu variable (force ±10 %, angle ±6°), comme sur une vraie
 * machine, pour qu'une trajectoire ne se répète jamais à l'identique. Une bille qui n'est pas
 * redescendue vers les batteurs du bas ni n'a touché un batteur depuis LOOP_MAX s (temps de la
 * physique) n'est plus relancée : elle perd son élan et retombe (pas de ping-pong sans fin).
 */
const KICK_MIN = 60, LOOP_MAX = 10;

function seg(ax, ay, bx, by, o = {}) {
  const dx = bx - ax, dy = by - ay, len = Math.hypot(dx, dy) || 1;
  return { k: 'seg', ax, ay, bx, by, dx: dx / len, dy: dy / len, len, nx: -dy / len, ny: dx / len, r: 3, e: 0.45, kick: 0, on: true, ...o };
}

export class Physics {
  /** opt.speed : vitesse du jeu (1 : temps réel ; par défaut SPEED). */
  constructor(table, opt = {}) {
    this.t = table;
    this.speed = opt.speed ?? SPEED;
    this.balls = [];
    this.segs = [];
    this.circs = [];
    this.sensors = [];
    this.time = 0;
    this.acc = 0;
    this.on = () => {};
    this.nextId = 1;
    for (const w of table.walls) this.polyline(w.pts, { r: w.r, e: w.e, tag: 'wall' });
    for (const w of table.rails) this.polyline(w.pts, { r: w.r, e: w.e, tag: 'rail' });
    for (const p of table.posts) this.circs.push({ k: 'circ', x: p.x, y: p.y, r: p.r, e: 0.75, kick: 0, tag: 'post', on: true });
    table.bumpers.forEach((b, i) => this.circs.push({ k: 'circ', x: b.x, y: b.y, r: b.r, e: 0.5, kick: 760, tag: 'bumper', ref: i, on: true }));
    table.slings.forEach((s, i) => {
      const [a, b, c] = [s.a, s.b, s.c];
      // Face active : de a vers c, normale tournée vers le centre du plateau.
      const f = seg(a[0], a[1], c[0], c[1], { r: 4, e: 0.7, tag: 'sling', ref: i });
      if ((s.side === 'L') !== (f.nx > 0)) { f.nx = -f.nx; f.ny = -f.ny; }
      f.kick = 620; f.kn = [f.nx, f.ny];
      this.segs.push(f, seg(a[0], a[1], b[0], b[1], { r: 4, e: 0.6, tag: 'rubber' }), seg(b[0], b[1], c[0], c[1], { r: 4, e: 0.6, tag: 'rubber' }));
    });
    table.targets.forEach((g, i) => this.segs.push(seg(g.x, g.y1, g.x, g.y2, { r: 4, e: 0.4, tag: 'target', ref: i, front: g.nx })));
    // Butée du lanceur et portillon (la bille sort du couloir, ne peut pas y redescendre).
    this.segs.push(seg(363, table.plunger.top, 389, table.plunger.top, { r: 1, e: 0.2, tag: 'plunger' }));
    this.segs.push(seg(table.gate.a[0], table.gate.a[1], table.gate.b[0], table.gate.b[1], { r: 2, e: 0.3, tag: 'gate', oneway: true }));
    this.flippers = table.flippers.map((f, i) => ({ ...f, id: i, ang: f.rest, w: 0, hit: 0, sweep: 0, pressed: false }));
    table.lanes.forEach((l, i) => this.sensors.push({ x: l.x, y: l.y, r: 9, tag: 'lane', ref: i, inside: new Set() }));
    const s = table.saucer;
    this.sensors.push({ x: s.x, y: s.y, r: s.r, tag: 'saucer', inside: new Set() });
    const k = table.kickback;
    this.sensors.push({ x: k.x, y: k.y, r: k.r, tag: 'kickback', inside: new Set() });
    // Spinner de l'orbite et capteur de l'orbite (haut de l'arche).
    if (table.spinner) this.sensors.push({ x: table.spinner.x, y: table.spinner.y, r: table.spinner.r, tag: 'spinner', inside: new Set() });
    if (table.orbit) this.sensors.push({ x: table.orbit.x, y: table.orbit.y, r: table.orbit.r, tag: 'orbit', inside: new Set() });
  }

  polyline(pts, o) {
    for (let i = 0; i < pts.length - 1; i++) this.segs.push(seg(pts[i][0], pts[i][1], pts[i + 1][0], pts[i + 1][1], o));
  }

  addBall(x, y, vx = 0, vy = 0) {
    const b = { id: this.nextId++, x, y, vx, vy, px: x, py: y, r: R, h: 0, state: 'play', rs: 0, rv: 0, still: 0, hit: 0, lowAt: this.time };
    this.balls.push(b);
    return b;
  }

  removeBall(b) {
    this.balls = this.balls.filter(o => o !== b);
    for (const s of this.sensors) s.inside.delete(b);
  }

  setFlipper(side, pressed) {
    for (const f of this.flippers) {
      if (f.side !== side) continue;
      if (pressed && !f.pressed) f.sweep++; // nouveau coup de batteur
      f.pressed = pressed;
    }
  }

  /** Coup sur la caisse : toutes les billes en jeu reçoivent une petite poussée. */
  nudge(dx, dy) {
    for (const b of this.balls) if (b.state === 'play') { b.vx += dx; b.vy += dy; }
  }

  /** Bille posée sur le lanceur ? */
  onPlunger(b) {
    return b.state === 'play' && b.x > 362 && b.y > this.t.plunger.ball - 12 && Math.abs(b.vy) < 80;
  }

  /** Lâcher du lanceur : force de 0 à 1. */
  launch(power) {
    let n = 0;
    for (const b of this.balls) {
      if (this.onPlunger(b)) {
        b.vy = -(1100 + 540 * Math.max(0, Math.min(1, power)));
        b.vx = 0;
        n++;
        this.on('launch', b, power);
      }
    }
    return n;
  }

  hold(b, x, y) { b.state = 'held'; b.x = x; b.y = y; b.vx = b.vy = 0; }

  release(b, vx, vy) { b.state = 'play'; b.vx = vx; b.vy = vy; b.still = 0; b.lowAt = this.time; }

  /** La bille entre dans la rampe n° id (0 : rampe 1, 1 : rampe 2). */
  enterRamp(b, id = 0) {
    b.ramp = this.t.ramps[id] ? id : 0;
    const p = this.t.ramps[b.ramp].pts;
    const dx = p[1].x - p[0].x, dy = p[1].y - p[0].y, l = Math.hypot(dx, dy);
    b.state = 'ramp';
    b.rs = 0;
    b.rv = Math.max(0, (b.vx * dx + b.vy * dy) / l);
    b.apex = false;
  }

  /** Avance de frame secondes réelles (la physique avance de frame × speed). */
  update(frame) {
    this.acc = Math.min(this.acc + frame * this.speed, 0.1); // onglet en arrière-plan : on ne rattrape pas plus de 0,1 s
    while (this.acc >= DT) {
      this.step(DT);
      this.acc -= DT;
    }
  }

  step(dt) {
    this.time += dt;
    for (const f of this.flippers) {
      const target = f.pressed ? f.up : f.rest, dir = Math.sign(target - f.ang);
      const speed = (f.pressed ? FLIP_UP : FLIP_DOWN) / this.speed;
      const next = f.ang + dir * speed * dt;
      if (dir === 0 || Math.sign(target - next) !== dir) { f.w = 0; f.hit = 0; f.ang = target; } else { f.w = dir * speed; f.hit = dir * (f.pressed ? HIT_UP : HIT_DOWN); f.ang = next; }
    }
    for (const b of this.balls) {
      if (b.state === 'ramp') { this.rampStep(b, dt); continue; }
      if (b.state !== 'play') continue;
      b.px = b.x; b.py = b.y;
      if (b.y > 690) b.lowAt = this.time; // près des batteurs : la bille est jouable
      b.vy += G * dt;
      const damp = 1 - 0.06 * dt;
      b.vx *= damp; b.vy *= damp;
      b.x += b.vx * dt; b.y += b.vy * dt;
      for (const f of this.flippers) this.hitFlipper(b, f);
      for (const s of this.segs) if (s.on) this.hitSeg(b, s);
      for (const c of this.circs) if (c.on) this.hitCirc(b, c);
      const v = Math.hypot(b.vx, b.vy);
      if (v > VMAX) { b.vx *= VMAX / v; b.vy *= VMAX / v; }
      this.sense(b);
      if (b.y > this.t.drainY) { b.state = 'gone'; this.on('drain', b); }
      // Bille immobile hors du lanceur (coincée) : petite secousse automatique, sauf si elle est tenue
      // sur un batteur levé (voulu par le joueur).
      const held = this.flippers.some(f => f.pressed && Math.hypot(b.x - f.px, b.y - f.py) < f.len + 24);
      b.still = v < 6 && !this.onPlunger(b) && !held ? b.still + dt : 0;
      if (b.still > 1.2) { b.vx += (Math.random() < 0.5 ? -1 : 1) * (80 + Math.random() * 120); b.vy -= 150; b.still = 0; }
    }
    // Chocs entre billes (multiball).
    const live = this.balls.filter(b => b.state === 'play');
    for (let i = 0; i < live.length; i++) {
      for (let j = i + 1; j < live.length; j++) {
        const a = live[i], c = live[j], dx = c.x - a.x, dy = c.y - a.y, d = Math.hypot(dx, dy);
        if (d > 0 && d < a.r + c.r) {
          const nx = dx / d, ny = dy / d, o = (a.r + c.r - d) / 2;
          a.x -= nx * o; a.y -= ny * o; c.x += nx * o; c.y += ny * o;
          const rel = (c.vx - a.vx) * nx + (c.vy - a.vy) * ny;
          if (rel < 0) {
            const imp = -(1 + 0.9) * rel / 2;
            a.vx -= imp * nx; a.vy -= imp * ny; c.vx += imp * nx; c.vy += imp * ny;
          }
        }
      }
    }
  }

  /** Choc avec un obstacle : la bille ressort, rebondit (e), frotte (mu), reçoit un coup (kick). */
  bounce(b, qx, qy, rad, o, ux = 0, uy = 0, fallback = null) {
    let nx = b.x - qx, ny = b.y - qy;
    const d2 = nx * nx + ny * ny, Rr = b.r + rad;
    if (d2 >= Rr * Rr) return false;
    let d = Math.sqrt(d2);
    if (d < 1e-6) { [nx, ny] = fallback || [0, -1]; d = 0; } else { nx /= d; ny /= d; }
    if (o.oneway && (ny > -0.5 || b.vy < 0)) return false; // portillon : ne bloque que la bille qui redescend par-dessus
    b.x = qx + nx * Rr; b.y = qy + ny * Rr;
    const rx = b.vx - ux, ry = b.vy - uy, vn = rx * nx + ry * ny;
    if (vn >= 0) return false;
    let tx = rx - vn * nx, ty = ry - vn * ny;
    // Frottement proportionnel à l'appui (loi de Coulomb) : presque rien pour une bille qui roule le
    // long d'une pente (elle ne s'arrête pas en route), davantage au choc ou dans un virage pris vite.
    const e = o.e ?? 0.45, tl = Math.hypot(tx, ty);
    if (tl > 0) { const k = Math.max(0, 1 - (o.mu ?? MU) * (1 + e) * -vn / tl); tx *= k; ty *= k; }
    let nvx = tx - vn * e * nx, nvy = ty - vn * e * ny;
    if (o.kick && -vn > KICK_MIN && (!o.kn || nx * o.kn[0] + ny * o.kn[1] > 0.5) && this.time - (b.lowAt ?? this.time) < LOOP_MAX) {
      const a = (Math.random() - 0.5) * 0.2, k = o.kick * (0.9 + Math.random() * 0.2), c = Math.cos(a), si = Math.sin(a);
      nvx += (nx * c - ny * si) * k; nvy += (nx * si + ny * c) * k;
    }
    b.vx = nvx + ux; b.vy = nvy + uy;
    return -vn;
  }

  hitSeg(b, s) {
    const t = Math.max(0, Math.min(s.len, (b.x - s.ax) * s.dx + (b.y - s.ay) * s.dy));
    const qx = s.ax + s.dx * t, qy = s.ay + s.dy * t;
    const imp = this.bounce(b, qx, qy, s.r, s, 0, 0, [s.nx, s.ny]);
    if (imp === false) return;
    if (s.tag === 'target' && imp > 60 && Math.sign(b.x - s.ax) === s.front) { s.on = false; this.on('target', s.ref, b, imp); return; }
    if (imp > 40) this.on('hit', s.tag, b, imp, s.ref);
  }

  hitCirc(b, c) {
    const imp = this.bounce(b, c.x, c.y, c.r, c);
    if (imp !== false && imp > 20) this.on(c.tag === 'bumper' ? 'bumper' : 'hit', c.tag === 'bumper' ? c.ref : c.tag, b, imp);
  }

  hitFlipper(b, f) {
    const tx = f.px + Math.cos(f.ang) * f.len, ty = f.py + Math.sin(f.ang) * f.len;
    const dx = (tx - f.px) / f.len, dy = (ty - f.py) / f.len;
    const t = Math.max(0, Math.min(f.len, (b.x - f.px) * dx + (b.y - f.py) * dy));
    const qx = f.px + dx * t, qy = f.py + dy * t, rad = f.r1 + (f.r2 - f.r1) * t / f.len;
    // Un seul coup par mouvement de batteur : ensuite, la bille déjà frappée suit le batteur (plus
    // rapide qu'elle) sans être frappée de nouveau, ce qui fausserait la visée.
    const key = f.id + ':' + f.sweep, again = f.w !== 0 && b.flip === key;
    // Vitesse de la surface du batteur au point de contact (force du coup : HIT_UP).
    const u = again ? 0 : f.hit, ux = -u * (qy - f.py), uy = u * (qx - f.px);
    const imp = this.bounce(b, qx, qy, rad, again ? { e: 0, mu: 0 } : { e: 0.32, mu: MU_FLIP }, ux, uy, [dy, -dx]);
    if (imp === false || again) return;
    b.lowAt = this.time; // frappée par un batteur : le joueur a la main, ce n'est pas une boucle
    if (f.w !== 0) b.flip = key;
    if (imp > 50) this.on('flip-hit', f.side, b, imp);
  }

  sense(b) {
    for (const s of this.sensors) {
      const inside = (b.x - s.x) ** 2 + (b.y - s.y) ** 2 < s.r * s.r;
      if (inside && !s.inside.has(b)) { s.inside.add(b); this.on('sensor', s.tag, b, s.ref); } else if (!inside) s.inside.delete(b);
    }
    // Entrée d'une rampe : la bille franchit la ligne en montant.
    this.t.ramps.forEach((r, id) => {
      const m = r.mouth;
      if (b.py >= m.y && b.y < m.y && b.x > m.x1 && b.x < m.x2) this.on('ramp-mouth', b, -b.vy, id);
    });
  }

  /** La bille suit la rampe : elle monte, ralentit, redescend si elle n'a pas assez d'élan. */
  rampStep(b, dt) {
    const r = this.t.ramps[b.ramp || 0], p = r.pts, L = r.length;
    let i = 1;
    while (i < p.length - 1 && p[i].s < b.rs) i++;
    const a = p[i - 1], c = p[i], seglen = (c.s - a.s) || 1, k = Math.max(0, Math.min(1, (b.rs - a.s) / seglen));
    const dirx = (c.x - a.x) / seglen, diry = (c.y - a.y) / seglen, dhds = (c.h - a.h) / seglen;
    b.rv += (G * RAMP_SLOPE * diry - G_RAMP * dhds - 0.35 * b.rv) * dt;
    b.rs += b.rv * dt;
    b.x = a.x + (c.x - a.x) * k; b.y = a.y + (c.y - a.y) * k; b.h = a.h + (c.h - a.h) * k;
    if (!b.apex && i - 1 >= r.apex) { b.apex = true; this.on('ramp-apex', b, b.ramp || 0); }
    if (b.rs >= L) {
      const e = p[p.length - 1], f = p[p.length - 2], l = Math.hypot(e.x - f.x, e.y - f.y) || 1, v = Math.max(b.rv, 260);
      b.state = 'play'; b.h = 0; b.x = e.x; b.y = e.y; b.vx = (e.x - f.x) / l * v; b.vy = (e.y - f.y) / l * v;
      this.on('ramp-done', b, b.ramp || 0);
    } else if (b.rs <= 0 && b.rv <= 0) {
      const q = p[1], l = Math.hypot(q.x - p[0].x, q.y - p[0].y) || 1, v = Math.max(-b.rv, 120);
      b.state = 'play'; b.h = 0; b.x = p[0].x; b.y = p[0].y + 2; b.vx = -(q.x - p[0].x) / l * v; b.vy = -(q.y - p[0].y) / l * v;
      this.on('ramp-fail', b, b.ramp || 0);
    }
  }

  /** Remet les cibles debout. */
  resetTargets() {
    for (const s of this.segs) if (s.tag === 'target') s.on = true;
  }
}
