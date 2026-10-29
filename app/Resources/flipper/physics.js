// Physique du flipper : bille(s) sur un plateau incliné vu de dessus, à pas fixe de 1 ms (pas de
// bille qui traverse un mur, même lancée fort). Deux niveaux : le plateau (0) et le mini-terrain
// surélevé (1) ; une bille ne touche que les obstacles de son niveau. Murs et guides (segments épais),
// bumpers (cercles), slingshots et kicker (faces qui renvoient la bille), flippers (segments effilés
// en rotation : principaux, TACKLE, mini-flippers), joueurs adverses du mini-terrain (trois rangées
// qui vont et viennent) et gardien, capteurs (couloirs, poches, trous, but), rampes et rails (la
// bille suit un chemin en hauteur, d'un niveau à l'autre), portillon du couloir de lancement.
// Aucun rendu ici : testable dans Node.
import { R, HM } from './table.js';

const DT = 1 / 1000;
export const G = 1500; // gravité le long du plateau (px/s²)
const G_RAMP = 1600; // gravité « verticale » dans la rampe (il faut un tir franc pour la passer)
const RAMP_SLOPE = 0.25; // part de la pente du plateau ressentie dans la rampe
const VMAX = 3600;
const MU = 0.1; // frottement bille / murs (loi de Coulomb)
const MU_FLIP = 0.1; // frottement bille / flippers (caoutchouc)
/**
 * Vitesse du jeu : tout le plateau vit au ralenti (temps de la physique = temps réel × SPEED), pour un
 * jeu jouable sur téléphone. Les flippers, eux, bougent en temps réel et très vite (FLIP_UP, FLIP_DOWN
 * en rad/s réels : réactifs dès l'appui) ; la force de leur coup sur la bille est réglée à part
 * (HIT_UP, HIT_DOWN, en rad/s du temps de la physique).
 */
export const SPEED = 0.65;
const FLIP_UP = 45, FLIP_DOWN = 26;
const HIT_UP = 42, HIT_DOWN = 20;
/**
 * Bumpers, slingshots et kicker : coup un peu variable (force ±10 %, angle ±6°), comme sur une vraie
 * machine, pour qu'une trajectoire ne se répète jamais à l'identique. Une bille qui n'est pas
 * redescendue vers les flippers du bas ni n'a touché un flipper depuis LOOP_MAX s (temps de la
 * physique) n'est plus relancée : elle perd son élan et retombe (pas de ping-pong sans fin).
 */
const KICK_MIN = 60, LOOP_MAX = 10;
/** Gardien du mini-terrain : vitesse de base (px/s) ; le jeu l'accélère au fil des buts (keeperSkill). */
const KEEPER_V = 70;

function seg(ax, ay, bx, by, o = {}) {
  const dx = bx - ax, dy = by - ay, len = Math.hypot(dx, dy) || 1;
  return { k: 'seg', ax, ay, bx, by, dx: dx / len, dy: dy / len, len, nx: -dy / len, ny: dx / len, r: 3, e: 0.45, kick: 0, on: true, lv: 0, vx: 0, ...o };
}

/** Face qui renvoie la bille (slingshot, kicker) : normale tournée vers le point « toward ». */
function kickFace(a, c, toward, o) {
  const f = seg(a[0], a[1], c[0], c[1], o);
  if ((toward[0] - a[0]) * f.nx + (toward[1] - a[1]) * f.ny < 0) { f.nx = -f.nx; f.ny = -f.ny; }
  f.kn = [f.nx, f.ny];
  return f;
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
    this.keeperSkill = 0;
    for (const w of table.walls) this.polyline(w.pts, { r: w.r, e: w.e, tag: 'wall' });
    table.bumpers.forEach((b, i) => this.circs.push({ k: 'circ', x: b.x, y: b.y, r: b.r, e: 0.5, kick: 760, tag: 'bumper', ref: i, on: true, lv: 0 }));
    table.slings.forEach((s, i) => {
      const toward = s.side === 'L' ? [400, s.a[1]] : [0, s.a[1]];
      this.segs.push(kickFace(s.a, s.c, toward, { r: 4, e: 0.7, tag: 'sling', ref: i, kick: 620 }),
        seg(s.a[0], s.a[1], s.b[0], s.b[1], { r: 4, e: 0.6, tag: 'rubber' }), seg(s.b[0], s.b[1], s.c[0], s.c[1], { r: 4, e: 0.6, tag: 'rubber' }));
    });
    if (table.kicker) {
      const k = table.kicker;
      this.segs.push(kickFace(k.a, k.b, [200, 480], { r: 4, e: 0.7, tag: 'kicker', kick: 560 }));
      for (const p of [k.a, k.b]) this.circs.push({ k: 'circ', x: p[0], y: p[1], r: 4, e: 0.6, kick: 0, tag: 'post', on: true, lv: 0 });
    }
    // Butée du lanceur et portillon (la bille sort du couloir, ne peut pas y redescendre).
    const pl = table.plunger;
    this.segs.push(seg(367, pl.top, 395, pl.top, { r: 1, e: 0.2, tag: 'plunger' }));
    const g = table.gate;
    this.segs.push(seg(g.a[0], g.a[1], g.b[0], g.b[1], { r: 2, e: 0.3, tag: 'gate', oneway: g.n }));
    for (const s of table.sensors) this.sensors.push({ ...s, inside: new Set(), lv: 0 });
    // Mini-terrain (niveau 1) : murs, filets, poteaux, joueurs adverses, gardien, trous d'éjection.
    const m = table.mini;
    this.players = [];
    if (m) {
      for (const w of m.walls) this.polyline(w.pts, { r: w.r, e: w.e, tag: w.net ? 'net' : 'wall', lv: 1, mu: w.net ? 0.4 : MU });
      for (const p of m.posts) this.circs.push({ k: 'circ', x: p.x, y: p.y, r: p.r, e: 0.6, kick: 0, tag: 'goalpost', on: true, lv: 1 });
      this.rows = m.rows.map((r, ri) => ({ ...r, x: m.center, vx: 0, idx: ri }));
      this.rows.forEach((r, ri) => r.offs.forEach((off, k) => {
        const s = seg(m.center + off - m.player.hw, r.y, m.center + off + m.player.hw, r.y, { r: m.player.r, e: 0.5, tag: 'player', lv: 1, ref: this.players.length, row: ri, off });
        this.players.push({ seg: s, line: r.line, row: ri, k });
        this.segs.push(s);
      }));
      const k = m.keeper, x = (k.min + k.max) / 2;
      this.keeper = seg(x - k.hw, k.y, x + k.hw, k.y, { r: k.r, e: 0.55, tag: 'keeper', lv: 1, x, min: k.min, max: k.max, hw: k.hw });
      this.segs.push(this.keeper);
      m.holes.forEach((h, i) => this.sensors.push({ tag: 'hole', ref: i, x: h.x, y: h.y, r: h.r, inside: new Set(), lv: 1 }));
    }
    this.flippers = table.flippers.map((f, i) => ({ lv: 0, ...f, id: i, ang: f.rest, w: 0, hit: 0, sweep: 0, pressed: false }));
    // Obstacles rangés par niveau (une bille ne regarde que ceux du sien).
    this.lv = [0, 1].map(l => ({
      segs: this.segs.filter(o => o.lv === l), circs: this.circs.filter(o => o.lv === l),
      flippers: this.flippers.filter(o => o.lv === l), sensors: this.sensors.filter(o => o.lv === l),
      mouths: table.ramps.map((r, id) => ({ r, id })).filter(o => o.r.mouth && o.r.mouth.lv === l).map(o => ({ id: o.id, ...this.mouth(o.r) })),
    }));
  }

  /** Ligne d'entrée d'une rampe, avec sa normale tournée vers l'intérieur de la rampe. */
  mouth(r) {
    const { a, b } = r.mouth, dx = b[0] - a[0], dy = b[1] - a[1], L = Math.hypot(dx, dy);
    let nx = -dy / L, ny = dx / L;
    const p = r.pts, k = Math.min(4, p.length - 1);
    if ((p[k].x - p[0].x) * nx + (p[k].y - p[0].y) * ny < 0) { nx = -nx; ny = -ny; }
    return { ax: a[0], ay: a[1], ux: dx / L, uy: dy / L, len: L, nx, ny, lv: r.mouth.lv };
  }

  polyline(pts, o) {
    for (let i = 0; i < pts.length - 1; i++) this.segs.push(seg(pts[i][0], pts[i][1], pts[i + 1][0], pts[i + 1][1], o));
  }

  addBall(x, y, vx = 0, vy = 0, lv = 0) {
    const b = { id: this.nextId++, x, y, vx, vy, px: x, py: y, r: R, h: lv ? HM : 0, hv: 0, lv, state: 'play', rs: 0, rv: 0, still: 0, hit: 0, lowAt: this.time };
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
      if (pressed && !f.pressed) f.sweep++; // nouveau coup de flipper
      f.pressed = pressed;
    }
  }

  /** Coup sur la caisse : toutes les billes en jeu reçoivent une petite poussée. */
  nudge(dx, dy) {
    for (const b of this.balls) if (b.state === 'play') { b.vx += dx; b.vy += dy; }
  }

  /** Bille posée sur le lanceur ? */
  onPlunger(b) {
    return b.state === 'play' && b.lv === 0 && b.x > 366 && b.y > this.t.plunger.ball - 12 && Math.abs(b.vy) < 80;
  }

  /** Lâcher du lanceur : force de 0 à 1. */
  launch(power) {
    let n = 0;
    for (const b of this.balls) {
      if (this.onPlunger(b)) {
        b.vy = -(950 + 750 * Math.max(0, Math.min(1, power)));
        b.vx = 0;
        n++;
        this.on('launch', b, power);
      }
    }
    return n;
  }

  hold(b, x, y) { b.state = 'held'; b.x = x; b.y = y; b.vx = b.vy = 0; }

  release(b, vx, vy) { b.state = 'play'; b.vx = vx; b.vy = vy; b.still = 0; b.lowAt = this.time; }

  /** Pose la bille sur un niveau (0 : plateau, 1 : mini-terrain). */
  place(b, lv, x, y) {
    for (const s of this.sensors) s.inside.delete(b);
    b.lv = lv; b.x = b.px = x; b.y = b.py = y; b.scored = false;
    if (lv) { b.h = HM; b.hv = 0; }
  }

  /** Une poche (lock, trou) éjecte la bille : elle repart de ce point avec cette vitesse. */
  eject(b, x, y, vx, vy, lv = 0) {
    this.place(b, lv, x, y);
    this.release(b, vx, vy);
  }

  /** La bille entre dans la rampe (ou le rail) n° id ; rv : vitesse de départ imposée (rails des trous). */
  enterRamp(b, id, rv = null) {
    const r = this.t.ramps[id], p = r.pts;
    const dx = p[1].x - p[0].x, dy = p[1].y - p[0].y, l = Math.hypot(dx, dy) || 1;
    for (const s of this.sensors) s.inside.delete(b);
    b.ramp = id;
    b.state = 'ramp';
    b.rs = 0;
    b.rv = rv ?? Math.max(0, (b.vx * dx + b.vy * dy) / l);
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
    if (this.keeper) { this.keeperStep(dt); this.rowsStep(); }
    const m = this.t.mini;
    for (const b of this.balls) {
      if (b.state === 'ramp') { this.rampStep(b, dt); continue; }
      if (b.state !== 'play') continue;
      const L = this.lv[b.lv];
      b.px = b.x; b.py = b.y;
      if (b.lv === 0 && b.y > 690) b.lowAt = this.time; // près des flippers : la bille est jouable
      // Bille qui retombe du mini-terrain ou d'une rampe : hauteur au rendu seulement.
      if (b.lv === 0 && b.h > 0) { b.hv += 3000 * dt; b.h = Math.max(0, b.h - b.hv * dt); if (!b.h) b.hv = 0; }
      b.vy += G * dt;
      const damp = 1 - 0.06 * dt;
      b.vx *= damp; b.vy *= damp;
      b.x += b.vx * dt; b.y += b.vy * dt;
      for (const f of L.flippers) this.hitFlipper(b, f);
      for (const s of L.segs) if (s.on) this.hitSeg(b, s);
      for (const c of L.circs) if (c.on) this.hitCirc(b, c);
      const v = Math.hypot(b.vx, b.vy);
      if (v > VMAX) { b.vx *= VMAX / v; b.vy *= VMAX / v; }
      this.sense(b, L);
      if (b.state !== 'play') continue;
      if (b.lv === 1) {
        // But : la bille franchit la ligne entre les poteaux. Bord avant : elle retombe sur le plateau.
        if (b.y < m.goal.y && b.x > m.goal.x1 && b.x < m.goal.x2 && !b.scored) { b.scored = true; this.on('goal', b); }
        if (b.state === 'play' && b.y > m.drainY) {
          const d = m.drop;
          this.place(b, 0, Math.max(d.x1, Math.min(d.x2, b.x)), d.y);
          b.h = HM; b.hv = 0; b.vy = Math.max(b.vy, 120); b.vx *= 0.5;
          this.on('mini-out', b);
        }
      } else if (b.y > this.t.drainY) { b.state = 'gone'; this.on('drain', b); }
      // Bille immobile hors du lanceur (coincée) : petite secousse automatique, sauf si elle est tenue
      // sur un flipper levé (voulu par le joueur).
      const held = L.flippers.some(f => f.pressed && Math.hypot(b.x - f.px, b.y - f.py) < f.len + 24);
      b.still = v < 6 && !this.onPlunger(b) && !held ? b.still + dt : 0;
      if (b.still > 1.2) { b.vx += (Math.random() < 0.5 ? -1 : 1) * (80 + Math.random() * 120); b.vy -= 150; b.still = 0; }
    }
    // Chocs entre billes (multiball), sur un même niveau.
    const live = this.balls.filter(b => b.state === 'play');
    for (let i = 0; i < live.length; i++) {
      for (let j = i + 1; j < live.length; j++) {
        const a = live[i], c = live[j];
        if (a.lv !== c.lv) continue;
        const dx = c.x - a.x, dy = c.y - a.y, d = Math.hypot(dx, dy);
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

  /**
   * Le gardien balaie sa ligne d'un poteau à l'autre ; plus keeperSkill est grand (0 à 1), plus il
   * suit la bille qui monte vers lui, et plus il va vite.
   */
  keeperStep(dt) {
    const k = this.keeper, mid = (k.min + k.max) / 2, half = (k.max - k.min) / 2;
    let target = mid + half * Math.sin(this.time * (1.8 + this.keeperSkill));
    const b = this.balls.find(o => o.state === 'play' && o.lv === 1 && o.vy < 0);
    if (b && this.keeperSkill > 0) target += (Math.max(k.min, Math.min(k.max, b.x)) - target) * Math.min(0.6, this.keeperSkill);
    const vmax = KEEPER_V * (1 + this.keeperSkill), step = Math.max(-vmax * dt, Math.min(vmax * dt, target - k.x));
    k.vx = step / dt;
    k.x += step;
    k.ax = k.x - k.hw; k.bx = k.x + k.hw;
  }

  /** Les trois rangées de joueurs adverses vont et viennent sur leur rail, chacune à son rythme. */
  rowsStep() {
    const m = this.t.mini, hw = m.player.hw;
    for (const r of this.rows) {
      r.x = m.center + r.amp * Math.sin(this.time * r.w + r.phase);
      r.vx = r.amp * r.w * Math.cos(this.time * r.w + r.phase);
    }
    for (const p of this.players) {
      const s = p.seg, r = this.rows[p.row];
      s.ax = r.x + s.off - hw; s.bx = r.x + s.off + hw; s.vx = r.vx;
    }
  }

  /** Remet tous les joueurs adverses sur le mini-terrain (ou seulement ceux d'une ligne). */
  resetPlayers(line = null) {
    for (const p of this.players) if (!line || p.line === line) p.seg.on = true;
  }

  /** Choc avec un obstacle : la bille ressort, rebondit (e), frotte (mu), reçoit un coup (kick). */
  bounce(b, qx, qy, rad, o, ux = 0, uy = 0, fallback = null) {
    let nx = b.x - qx, ny = b.y - qy;
    const d2 = nx * nx + ny * ny, Rr = b.r + rad;
    if (d2 >= Rr * Rr) return false;
    let d = Math.sqrt(d2);
    if (d < 1e-6) { [nx, ny] = fallback || [0, -1]; d = 0; } else { nx /= d; ny /= d; }
    // Portillon : ne bloque que la bille qui revient vers le couloir (contre sa normale « sortie »).
    if (o.oneway && b.vx * o.oneway[0] + b.vy * o.oneway[1] > 0) return false;
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
    const imp = this.bounce(b, qx, qy, s.r, s, s.vx || 0, 0, [s.nx, s.ny]);
    if (imp === false) return;
    if (s.tag === 'keeper' && imp > 40) { this.on('save', b, imp); return; }
    if (s.tag === 'player' && imp > 40) { this.on('player', s.ref, b, imp); return; }
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
    // Un seul coup par mouvement de flipper : ensuite, la bille déjà frappée suit le flipper (plus
    // rapide qu'elle) sans être frappée de nouveau, ce qui fausserait la visée.
    const key = f.id + ':' + f.sweep, again = f.w !== 0 && b.flip === key;
    // Vitesse de la surface du flipper au point de contact (force du coup : HIT_UP).
    const u = again ? 0 : f.hit, ux = -u * (qy - f.py), uy = u * (qx - f.px);
    const imp = this.bounce(b, qx, qy, rad, again ? { e: 0, mu: 0 } : { e: 0.32, mu: MU_FLIP }, ux, uy, [dy, -dx]);
    if (imp === false || again) return;
    b.lowAt = this.time; // frappée par un flipper : le joueur a la main, ce n'est pas une boucle
    if (f.w !== 0) b.flip = key;
    if (imp > 50) this.on('flip-hit', f.side, b, imp, f.mini ? 'mini' : f.tackle ? 'tackle' : '');
  }

  /** Capteurs du niveau de la bille, et entrées des rampes franchies dans le bon sens. */
  sense(b, L) {
    for (const s of L.sensors) {
      const inside = (b.x - s.x) ** 2 + (b.y - s.y) ** 2 < s.r * s.r;
      if (inside && !s.inside.has(b)) { s.inside.add(b); this.on('sensor', s.tag, b, s.ref); } else if (!inside) s.inside.delete(b);
      if (b.state !== 'play') return;
    }
    for (const m of L.mouths) {
      const s0 = (b.px - m.ax) * m.nx + (b.py - m.ay) * m.ny, s1 = (b.x - m.ax) * m.nx + (b.y - m.ay) * m.ny;
      if (!(s0 < 0 && s1 >= 0)) continue;
      const along = (b.x - m.ax) * m.ux + (b.y - m.ay) * m.uy;
      if (along < -2 || along > m.len + 2) continue;
      const v = b.vx * m.nx + b.vy * m.ny;
      // Sur le mini-terrain, l'entrée de la rampe est une ouverture dans le mur : on y entre toujours.
      if (v > 150 || m.lv === 1) { this.enterRamp(b, m.id); this.on('ramp-in', b, v, m.id); return; }
    }
  }

  /**
   * La bille suit la rampe : elle monte, ralentit, redescend si elle n'a pas assez d'élan. Au bout,
   * elle est posée sur le mini-terrain (rampe PASSE, accès) ou rendue au plateau.
   */
  rampStep(b, dt) {
    const id = b.ramp || 0, r = this.t.ramps[id], p = r.pts, L = r.length;
    let i = 1;
    while (i < p.length - 1 && p[i].s < b.rs) i++;
    const a = p[i - 1], c = p[i], seglen = (c.s - a.s) || 1, k = Math.max(0, Math.min(1, (b.rs - a.s) / seglen));
    const diry = (c.y - a.y) / seglen, dhds = (c.h - a.h) / seglen;
    b.rv += (G * RAMP_SLOPE * diry - G_RAMP * dhds - 0.2 * b.rv) * dt;
    b.rs += b.rv * dt;
    b.x = a.x + (c.x - a.x) * k; b.y = a.y + (c.y - a.y) * k; b.h = a.h + (c.h - a.h) * k;
    if (!b.apex && i - 1 >= r.apex) { b.apex = true; this.on('ramp-apex', b, id); }
    if (b.rs >= L) {
      const e = p[p.length - 1], f = p[p.length - 2], l = Math.hypot(e.x - f.x, e.y - f.y) || 1;
      if (r.end) {
        const sp = Math.max(0.7, Math.min(1.3, b.rv / 420));
        b.state = 'play';
        this.place(b, r.end.lv, r.end.x, r.end.y);
        b.vx = r.end.vx * sp; b.vy = r.end.vy * sp; b.still = 0; b.lowAt = this.time;
      } else {
        // Bout de la rampe : un frein (comme sur les rails en fil) rend la bille à vitesse raisonnable.
        const v = Math.max(260, Math.min(b.rv, 700));
        b.state = 'play'; b.h = e.h; b.hv = 0; b.lv = 0; b.x = b.px = e.x; b.y = b.py = e.y; b.vx = (e.x - f.x) / l * v; b.vy = (e.y - f.y) / l * v;
      }
      this.on('ramp-done', b, id);
    } else if (b.rs <= 0 && b.rv <= 0) {
      const q = p[1], l = Math.hypot(q.x - p[0].x, q.y - p[0].y) || 1, v = Math.max(-b.rv, 120), lv = r.mouth ? r.mouth.lv : 0;
      b.state = 'play'; b.lv = lv; b.h = lv ? HM : 0; b.hv = 0;
      b.x = b.px = p[0].x - (q.x - p[0].x) / l * 3; b.y = b.py = p[0].y - (q.y - p[0].y) / l * 3;
      b.vx = -(q.x - p[0].x) / l * v; b.vy = -(q.y - p[0].y) / l * v;
      this.on('ramp-fail', b, id);
    }
  }
}
