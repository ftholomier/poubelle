// Rendu 3D du flipper (Three.js) : plateau, murs lumineux, bumpers, cibles, rampe, batteurs, billes,
// fronton. Le plan 2D de table.js est posé à plat (x → X, y → Z), la hauteur en Y.
import * as THREE from 'three';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { EffectComposer } from 'three/examples/jsm/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/examples/jsm/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/examples/jsm/postprocessing/UnrealBloomPass.js';
import { OutputPass } from 'three/examples/jsm/postprocessing/OutputPass.js';
import { W, H, R } from './table.js';

const S = 50 / W;
const X = x => (x - W / 2) * S;
const Z = y => (y - H / 2) * S;
const P = (x, y, h = 0) => new THREE.Vector3(X(x), h, Z(y));
const FONT = '"Big Shoulders Display", "Arial Narrow", sans-serif';

function canvas(w, h) { const c = document.createElement('canvas'); c.width = w; c.height = h; return [c, c.getContext('2d')]; }
function tex(c) { const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = 8; return t; }
function txt(g, s, x, y, size, color, o = {}) {
  g.save(); g.translate(x, y); if (o.rot) g.rotate(o.rot);
  g.font = `${o.weight || 800} ${size}px ${FONT}`; if ('letterSpacing' in g) g.letterSpacing = (o.ls || 0) + 'px';
  g.textAlign = o.align || 'center'; g.textBaseline = 'middle'; g.fillStyle = color;
  if (o.glow) { g.shadowColor = o.glow; g.shadowBlur = o.blur || 12; }
  g.fillText(s, 0, 0); g.restore();
}

export class Scene3D {
  /** cfg : { colors: {navy, yellow, blue}, title, subtitle, ramp, bumpers, targetsLabel, cupLabel, kickbackLabel, logo (Image) } */
  constructor(canvasEl, table, cfg) {
    this.t = table;
    this.cfg = cfg;
    this.el = canvasEl;
    this.col = { navy: new THREE.Color(cfg.colors.navy), yellow: new THREE.Color(cfg.colors.yellow), blue: new THREE.Color(cfg.colors.blue) };
    this.Y = cfg.colors.yellow;
    this.fxs = [];
    this.shake = 0;
    this.quality = 2;
    this.renderer = new THREE.WebGLRenderer({ canvas: canvasEl, antialias: true, powerPreference: 'high-performance' });
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFShadowMap;
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.outputColorSpace = THREE.SRGBColorSpace;
    this.scene = new THREE.Scene();
    this.scene.background = new THREE.Color(0x02040c);
    const pm = new THREE.PMREMGenerator(this.renderer);
    this.scene.environment = pm.fromScene(new RoomEnvironment(), 0.04).texture;
    this.scene.environmentIntensity = 0.45;
    this.camera = new THREE.PerspectiveCamera(39, 1, 1, 1000);
    this.build();
  }

  // ------------------------------------------------------------------ construction

  build() {
    const sc = this.scene, t = this.t, Y = this.col.yellow;
    this.mat = {
      wall: new THREE.MeshStandardMaterial({ color: 0x0b1640, roughness: 0.5 }),
      chrome: new THREE.MeshStandardMaterial({ color: 0xdfe5ee, metalness: 1, roughness: 0.15 }),
      neon: new THREE.MeshBasicMaterial({ color: Y.clone().multiplyScalar(1.5) }),
      rubber: new THREE.MeshStandardMaterial({ color: 0xf2f2f2, roughness: 0.6 }),
      yellow: new THREE.MeshStandardMaterial({ color: Y, roughness: 0.3, emissive: Y, emissiveIntensity: 0.12 }),
    };
    this.playfield();
    this.lampsBuild();
    for (const w of t.walls) this.wall(w.pts, w.h, w.r * S * 2.2, w.neon);
    for (const r of t.rails) { const a = P(...r.pts[0]), b = P(...r.pts[1]); const m = new THREE.Mesh(new THREE.BoxGeometry(0.45, 1.8, a.distanceTo(b)), this.mat.chrome); m.position.set((a.x + b.x) / 2, 0.9, (a.z + b.z) / 2); m.castShadow = true; sc.add(m); }
    for (const p of t.posts) { const m = new THREE.Mesh(new THREE.CylinderGeometry(p.r * S, p.r * S, 2.2, 20), this.mat.rubber); m.position.copy(P(p.x, p.y, 1.1)); m.castShadow = true; sc.add(m); }
    this.bumpersBuild();
    this.slingsBuild();
    this.targetsBuild();
    this.flippersBuild();
    this.rampBuild();
    this.saucerBuild();
    this.plungerBuild();
    this.backboard();
    // Caisse de la machine : flancs aux couleurs du club, liseré lumineux.
    const side = new THREE.MeshStandardMaterial({ color: this.col.navy, roughness: 0.45, metalness: 0.2 });
    for (const sx of [-1, 1]) {
      const m = new THREE.Mesh(new THREE.BoxGeometry(3, 7, H * S + 8), side);
      m.position.set(sx * (W * S / 2 + 1.6), 1.5, (Z(0) + Z(H)) / 2 + 1); m.castShadow = m.receiveShadow = true; sc.add(m);
      const strip = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.3, H * S + 8), this.mat.neon);
      strip.position.set(sx * (W * S / 2 + 2.9), 5.05, (Z(0) + Z(H)) / 2 + 1); sc.add(strip);
    }
    // Fond : halo bleu derrière la machine.
    const [bc, bg] = canvas(256, 256), grad = bg.createRadialGradient(128, 110, 10, 128, 128, 180);
    grad.addColorStop(0, '#16307a'); grad.addColorStop(0.55, '#070f2e'); grad.addColorStop(1, '#02040c');
    bg.fillStyle = grad; bg.fillRect(0, 0, 256, 256);
    sc.background = tex(bc);
    const apron = new THREE.Mesh(new THREE.BoxGeometry(W * S, 1.6, 4), new THREE.MeshStandardMaterial({ color: this.col.navy, roughness: 0.5 }));
    apron.position.set(0, 0.8, Z(H) + 1.6); sc.add(apron);
    // Billes (jusqu'à 4 en multiball).
    const ballMat = new THREE.MeshStandardMaterial({ color: 0xffffff, metalness: 1, roughness: 0.06 });
    this.balls = Array.from({ length: 5 }, () => { const m = new THREE.Mesh(new THREE.SphereGeometry(R * S, 32, 24), ballMat); m.castShadow = true; m.visible = false; sc.add(m); return m; });
    // Lumières
    sc.add(new THREE.AmbientLight(0x8090c0, 0.12));
    this.key = new THREE.SpotLight(0xfff4e0, 420, 0, 0.75, 0.6, 1.5);
    this.key.position.set(0, 74, 34); this.key.target.position.set(0, 0, -2);
    this.key.castShadow = true; this.key.shadow.mapSize.set(2048, 2048); this.key.shadow.bias = -0.0005;
    sc.add(this.key, this.key.target);
    const blue = new THREE.PointLight(0x4a6dff, 60, 80, 1.6); blue.position.set(-20, 18, -32); sc.add(blue);
    const warm = new THREE.PointLight(0xffb020, 40, 70, 1.6); warm.position.set(18, 14, 22); sc.add(warm);
  }

  /** Décor peint du plateau. */
  playfield() {
    const k = 2.5, [c, g] = canvas(W * k, H * k), t = this.t, Yc = this.Y;
    g.scale(k, k);
    const bg = g.createRadialGradient(190, 380, 20, 190, 380, 520);
    bg.addColorStop(0, this.cfg.colors.blue); bg.addColorStop(0.45, '#132a6e'); bg.addColorStop(1, '#050b22');
    g.fillStyle = bg; g.fillRect(0, 0, W, H);
    for (let i = 0; i < 16; i++) { const a = i / 16 * Math.PI * 2; g.strokeStyle = 'rgba(255,255,255,.04)'; g.lineWidth = 20; g.beginPath(); g.moveTo(190, 380); g.lineTo(190 + Math.cos(a) * 600, 380 + Math.sin(a) * 600); g.stroke(); }
    if (this.cfg.logo) { g.globalAlpha = 0.18; const lw = 190, lh = lw * (this.cfg.logo.height / this.cfg.logo.width || 1.13); g.drawImage(this.cfg.logo, 187 - lw / 2, 560 - lh / 2, lw, lh); g.globalAlpha = 1; }
    txt(g, this.cfg.bumpers, 200, 312, 10, 'rgba(255,243,194,.85)', { ls: 3 });
    txt(g, this.cfg.targetsLabel, 187, 522, 8.5, 'rgba(255,243,194,.8)', { ls: 1.5 });
    txt(g, this.cfg.cupLabel, 200, 470, 12, '#fff', { ls: 3, weight: 900 });
    // trou de la Coupe
    g.fillStyle = '#01030a'; g.beginPath(); g.arc(t.saucer.x, t.saucer.y, 12, 0, Math.PI * 2); g.fill();
    // couloirs du bas : flèches de retour
    g.strokeStyle = 'rgba(246,196,0,.35)'; g.lineWidth = 2;
    for (const [x, y] of [[74, 616], [300, 616]]) { g.beginPath(); g.moveTo(x - 6, y + 5); g.lineTo(x, y - 4); g.lineTo(x + 6, y + 5); g.stroke(); }
    txt(g, this.cfg.kickbackLabel, 28, 640, 8, '#FFF3C2', { rot: -Math.PI / 2, ls: 1.2 });
    txt(g, this.cfg.plungerLabel, 376, 640, 9, '#FFF3C2', { rot: -Math.PI / 2, ls: 2 });
    const tx = tex(c);
    const m = new THREE.Mesh(new THREE.PlaneGeometry(W * S, H * S), new THREE.MeshStandardMaterial({ map: tx, roughness: 0.45, metalness: 0.05 }));
    m.rotation.x = -Math.PI / 2; m.receiveShadow = true; this.scene.add(m);
  }

  /** Une lampe (pastille) du plateau : texte peint, allumée par son émission. */
  lamp(x, y, r, label, size = 11) {
    const [c, g] = canvas(128, 128);
    g.fillStyle = this.Y; g.beginPath(); g.arc(64, 64, 60, 0, Math.PI * 2); g.fill();
    if (label) txt(g, label, 64, 68, size * 5.2, this.cfg.colors.navy, { weight: 900 });
    const t = tex(c);
    const mat = new THREE.MeshStandardMaterial({ map: t, emissiveMap: t, emissive: 0xffffff, emissiveIntensity: 0, roughness: 0.35, transparent: true, color: 0x6a5a20 });
    const m = new THREE.Mesh(new THREE.CircleGeometry(r * S, 32), mat);
    m.rotation.x = -Math.PI / 2; m.position.copy(P(x, y, 0.03));
    const ring = new THREE.Mesh(new THREE.RingGeometry(r * S, (r + 1.6) * S, 32), new THREE.MeshBasicMaterial({ color: this.col.yellow }));
    ring.rotation.x = -Math.PI / 2; ring.position.copy(P(x, y, 0.025));
    this.scene.add(m, ring);
    return { m, mat, on: 0 };
  }

  lampsBuild() {
    const t = this.t;
    this.L = {
      lanes: t.lanes.map(l => this.lamp(l.x, 120, 11, l.digit, 15)),
      cup: ['32', '16', '8', '¼', '½', 'F'].map((s, i) => this.lamp(125 + i * 30, 496, 11, s, s.length > 1 ? 10 : 12)),
      letters: t.targets.map((g, i) => this.lamp(187 + (i - (t.targets.length - 1) / 2) * 26, 545, 10, g.letter, 12)),
      mult: ['×2', '×3', '×4', '×5'].map((s, i) => this.lamp(142 + i * 30, 590, 10, s, 10)),
      kickback: this.lamp(28, 700, 7, ''),
      save: this.lamp(187, 800, 9, '', 9),
      ramp: [0, 1, 2].map(i => this.chevron(76, 520 - i * 22)),
    };
  }

  chevron(x, y) {
    const sh = new THREE.Shape(); sh.moveTo(-7, 6); sh.lineTo(0, -4); sh.lineTo(7, 6); sh.lineTo(7, 10); sh.lineTo(0, 0); sh.lineTo(-7, 10); sh.closePath();
    const geo = new THREE.ShapeGeometry(sh); geo.scale(S, S, S); geo.rotateX(-Math.PI / 2);
    const mat = new THREE.MeshStandardMaterial({ color: 0x6a5a20, emissive: this.col.yellow, emissiveIntensity: 0, roughness: 0.4 });
    const m = new THREE.Mesh(geo, mat); m.position.copy(P(x, y, 0.03)); this.scene.add(m);
    return { m, mat, on: 0 };
  }

  wall(pts, h, th, glow) {
    for (let i = 0; i < pts.length - 1; i++) {
      const a = P(...pts[i]), b = P(...pts[i + 1]), len = a.distanceTo(b);
      if (len < 0.01) continue;
      const m = new THREE.Mesh(new THREE.BoxGeometry(th, h, len + th * 0.6), this.mat.wall);
      m.position.set((a.x + b.x) / 2, h / 2, (a.z + b.z) / 2); m.rotation.y = Math.atan2(b.x - a.x, b.z - a.z);
      m.castShadow = m.receiveShadow = true; this.scene.add(m);
    }
    if (glow) {
      const curve = new THREE.CatmullRomCurve3(pts.map(p => P(p[0], p[1], h + 0.05)), false, 'catmullrom', 0);
      this.scene.add(new THREE.Mesh(new THREE.TubeGeometry(curve, pts.length * 4, 0.2, 8), this.mat.neon));
    }
  }

  bumpersBuild() {
    const [c, g] = canvas(256, 256);
    g.fillStyle = this.Y; g.fillRect(0, 0, 256, 256);
    g.fillStyle = this.cfg.colors.navy; g.beginPath(); g.arc(128, 128, 84, 0, Math.PI * 2); g.fill();
    txt(g, this.cfg.bumperCap, 128, 132, 64, this.Y, { weight: 900 });
    const cap = tex(c);
    this.bumpers = this.t.bumpers.map(b => {
      const p = P(b.x, b.y), r = b.r * S;
      const base = new THREE.Mesh(new THREE.CylinderGeometry(r * 1.12, r * 1.12, 0.5, 40), this.mat.rubber); base.position.set(p.x, 0.25, p.z); this.scene.add(base);
      const body = new THREE.Mesh(new THREE.CylinderGeometry(r * 0.78, r * 0.78, 2.4, 40), new THREE.MeshStandardMaterial({ color: this.col.blue, emissive: this.col.blue, emissiveIntensity: 0.35, roughness: 0.3 }));
      body.position.set(p.x, 1.6, p.z); body.castShadow = true; this.scene.add(body);
      const ringMat = new THREE.MeshBasicMaterial({ color: this.col.yellow.clone().multiplyScalar(1.5) });
      const ring = new THREE.Mesh(new THREE.TorusGeometry(r * 1.02, 0.32, 12, 48), ringMat); ring.rotation.x = Math.PI / 2; ring.position.set(p.x, 1.1, p.z); this.scene.add(ring);
      const top = new THREE.Mesh(new THREE.CylinderGeometry(r * 1.1, r * 1.1, 0.7, 40), this.mat.yellow); top.position.set(p.x, 3.15, p.z); top.castShadow = true; this.scene.add(top);
      const discMat = new THREE.MeshStandardMaterial({ map: cap, emissiveMap: cap, emissive: 0xffffff, emissiveIntensity: 0.25, roughness: 0.3 });
      const disc = new THREE.Mesh(new THREE.CircleGeometry(r * 1.08, 48), discMat); disc.rotation.x = -Math.PI / 2; disc.position.set(p.x, 3.52, p.z); this.scene.add(disc);
      const light = new THREE.PointLight(0xffcc33, 6, 12, 2); light.position.set(p.x, 4, p.z); this.scene.add(light);
      return { ringMat, discMat, light, flash: 0 };
    });
  }

  slingsBuild() {
    this.slings = this.t.slings.map(s => {
      const sh = new THREE.Shape([s.a, s.b, s.c].map(([x, y]) => new THREE.Vector2(X(x), -Z(y))));
      const geo = new THREE.ExtrudeGeometry(sh, { depth: 2.2, bevelEnabled: true, bevelSize: 0.25, bevelThickness: 0.2, bevelSegments: 2 });
      const mat = new THREE.MeshStandardMaterial({ color: this.col.yellow, emissive: this.col.yellow, emissiveIntensity: 0.15, roughness: 0.3 });
      const m = new THREE.Mesh(geo, [mat, this.mat.rubber]); m.rotation.x = -Math.PI / 2; m.castShadow = true; this.scene.add(m);
      return { mat, flash: 0 };
    });
  }

  targetsBuild() {
    this.targets = this.t.targets.map(g => {
      const [c, gx] = canvas(128, 128);
      gx.fillStyle = this.Y; gx.fillRect(0, 0, 128, 128);
      txt(gx, g.letter, 64, 70, 104, this.cfg.colors.navy, { weight: 900 });
      const t = tex(c);
      const len = (g.y2 - g.y1) * S;
      const side = new THREE.MeshStandardMaterial({ color: this.col.yellow, roughness: 0.35 });
      const face = new THREE.MeshStandardMaterial({ map: t, emissiveMap: t, emissive: 0xffffff, emissiveIntensity: 0.2, roughness: 0.35 });
      const mats = g.nx > 0 ? [face, side, side, side, side, side] : [side, face, side, side, side, side];
      const m = new THREE.Mesh(new THREE.BoxGeometry(0.5, 2.2, len), mats);
      m.position.copy(P(g.x, (g.y1 + g.y2) / 2, 1.1)); m.castShadow = true; this.scene.add(m);
      return { m, y: 1.1 };
    });
  }

  flippersBuild() {
    this.flippers = this.t.flippers.map(f => {
      const L = f.len * S, r1 = f.r1 * S, r2 = f.r2 * S;
      const sh = new THREE.Shape(); sh.absarc(0, 0, r1, Math.PI / 2, Math.PI * 1.5, false); sh.lineTo(L, -r2); sh.absarc(L, 0, r2, -Math.PI / 2, Math.PI / 2, false); sh.lineTo(0, r1);
      const geo = new THREE.ExtrudeGeometry(sh, { depth: 1.3, bevelEnabled: true, bevelSize: 0.15, bevelThickness: 0.15, bevelSegments: 2 });
      geo.rotateX(-Math.PI / 2);
      const m = new THREE.Mesh(geo, this.mat.yellow); m.position.copy(P(f.px, f.py, 0.2)); m.castShadow = true; this.scene.add(m);
      const pin = new THREE.Mesh(new THREE.CylinderGeometry(0.45, 0.45, 1.9, 16), this.mat.chrome); pin.position.copy(P(f.px, f.py, 0.9)); this.scene.add(pin);
      return m;
    });
  }

  rampBuild() {
    const pts = this.t.ramp.pts, Wd = 2.6, pos = [], uv = [], idx = [], railL = [], railR = [];
    pts.forEach((p, i) => {
      const n1 = pts[Math.min(i + 1, pts.length - 1)], p0 = pts[Math.max(i - 1, 0)];
      const d = new THREE.Vector3(n1.x - p0.x, 0, n1.y - p0.y).normalize(), n = new THREE.Vector3(-d.z, 0, d.x);
      const c = P(p.x, p.y, p.h * S + 0.25), l = c.clone().addScaledVector(n, Wd), r = c.clone().addScaledVector(n, -Wd);
      pos.push(l.x, l.y, l.z, r.x, r.y, r.z); uv.push(1, p.s / this.t.ramp.length, 0, p.s / this.t.ramp.length);
      railL.push(l.clone().setY(l.y + 1.1)); railR.push(r.clone().setY(r.y + 1.1));
      if (i) { const k = i * 2; idx.push(k - 2, k - 1, k, k - 1, k + 1, k); }
    });
    const geo = new THREE.BufferGeometry(); geo.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3)); geo.setAttribute('uv', new THREE.Float32BufferAttribute(uv, 2)); geo.setIndex(idx); geo.computeVertexNormals();
    const [c, g] = canvas(128, 1024);
    g.fillStyle = 'rgba(160,190,255,.55)'; g.fillRect(0, 0, 128, 1024);
    txt(g, this.cfg.ramp, 64, 420, 66, 'rgba(255,255,255,.95)', { rot: -Math.PI / 2, ls: 8, weight: 900 });
    const m = new THREE.Mesh(geo, new THREE.MeshStandardMaterial({ map: tex(c), transparent: true, opacity: 0.8, side: THREE.DoubleSide, roughness: 0.15, metalness: 0.1 }));
    m.castShadow = true; this.scene.add(m);
    for (const r of [railL, railR]) this.scene.add(new THREE.Mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(r), 90, 0.16, 8), this.mat.chrome));
  }

  saucerBuild() {
    const s = this.t.saucer;
    this.saucerRing = new THREE.MeshBasicMaterial({ color: this.col.yellow.clone().multiplyScalar(0.6) });
    const ring = new THREE.Mesh(new THREE.TorusGeometry(13.5 * S, 0.22, 10, 40), this.saucerRing); ring.rotation.x = Math.PI / 2; ring.position.copy(P(s.x, s.y, 0.12)); this.scene.add(ring);
    const prof = [[0, 0], [1.1, 0], [1.1, 0.25], [0.35, 0.45], [0.3, 1.6], [0.9, 2.0], [1.6, 2.9], [1.75, 3.9], [1.6, 3.95], [0, 3.95]].map(([a, b]) => new THREE.Vector2(a, b));
    const gold = new THREE.MeshStandardMaterial({ color: 0xffd34d, metalness: 1, roughness: 0.2, emissive: 0x3a2a00 });
    this.cup = new THREE.Group();
    this.cup.add(new THREE.Mesh(new THREE.LatheGeometry(prof, 48), gold));
    for (const sx of [-1, 1]) { const h = new THREE.Mesh(new THREE.TorusGeometry(0.7, 0.16, 10, 24, Math.PI), gold); h.position.set(sx * 1.7, 3.0, 0); h.rotation.z = sx > 0 ? -Math.PI / 2 : Math.PI / 2; this.cup.add(h); }
    this.cup.position.copy(P(s.x, s.y - 34, 0)); this.cup.scale.setScalar(0.85);
    this.cup.traverse(o => { o.castShadow = true; });
    this.scene.add(this.cup);
    this.goldMat = gold;
  }

  plungerBuild() {
    const p = this.t.plunger, x = X(p.x), z = Z(p.top);
    this.knob = new THREE.Mesh(new THREE.CylinderGeometry(1.05, 1.05, 2.4, 24), this.mat.yellow); this.knob.rotation.x = Math.PI / 2; this.knob.position.set(x, 1.2, z + 2.2); this.scene.add(this.knob);
    const spring = new THREE.Mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(Array.from({ length: 60 }, (_, i) => new THREE.Vector3(Math.cos(i * 0.8) * 0.8, 1.2 + Math.sin(i * 0.8) * 0.8, i * 0.08))), 140, 0.09, 6), this.mat.chrome);
    spring.position.set(x, 0, z + 3.4); this.scene.add(spring);
    this.plungerZ = z + 2.2;
  }

  backboard() {
    const [c, g] = canvas(1400, 300), Yc = this.Y;
    const gr = g.createLinearGradient(0, 0, 0, 300); gr.addColorStop(0, this.cfg.colors.blue); gr.addColorStop(1, '#070f2e'); g.fillStyle = gr; g.fillRect(0, 0, 1400, 300);
    for (let i = 0; i < 18; i++) { const a = Math.PI + i / 17 * Math.PI; g.strokeStyle = 'rgba(246,196,0,.08)'; g.lineWidth = 26; g.beginPath(); g.moveTo(700, 340); g.lineTo(700 + Math.cos(a) * 900, 340 + Math.sin(a) * 900); g.stroke(); }
    if (this.cfg.logo) { const lh = 240, lw = lh * (this.cfg.logo.width / this.cfg.logo.height || 0.88); g.drawImage(this.cfg.logo, 40, 30, lw, lh); g.drawImage(this.cfg.logo, 1360 - lw, 30, lw, lh); }
    txt(g, this.cfg.title.toUpperCase(), 700, 150, this.cfg.title.length > 20 ? 104 : 124, Yc, { weight: 900, ls: 4, glow: 'rgba(246,196,0,.8)', blur: 24 });
    txt(g, this.cfg.subtitle.toUpperCase(), 700, 252, 34, '#FFF3C2', { ls: 12 });
    g.strokeStyle = Yc; g.lineWidth = 10; g.strokeRect(12, 12, 1376, 276);
    const t = tex(c);
    const face = new THREE.MeshStandardMaterial({ map: t, emissiveMap: t, emissive: 0xffffff, emissiveIntensity: 0.55, roughness: 0.4 });
    const m = new THREE.Mesh(new THREE.BoxGeometry(W * S + 6, 12, 1.5), [this.mat.wall, this.mat.wall, this.mat.wall, this.mat.wall, face, this.mat.wall]);
    m.rotation.x = -0.5; m.position.set(0, 6 * Math.cos(0.5), Z(0) - 2 - 6 * Math.sin(0.5)); this.scene.add(m);
  }

  // ------------------------------------------------------------------ caméra, taille, qualité

  resize(w, h) {
    this.w = w; this.h = h;
    this.renderer.setSize(w, h, false);
    this.camera.aspect = w / h;
    this.fit();
    if (this.composer) { this.composer.setSize(w, h); this.bloom.resolution.set(w, h); }
    else this.makeComposer();
  }

  makeComposer() {
    this.composer = new EffectComposer(this.renderer);
    this.composer.addPass(new RenderPass(this.scene, this.camera));
    this.bloom = new UnrealBloomPass(new THREE.Vector2(this.w || 400, this.h || 800), 0.4, 0.5, 0.9);
    this.composer.addPass(this.bloom);
    this.composer.addPass(new OutputPass());
  }

  /** Caméra penchée sur le plateau, reculée juste assez pour tout montrer (écran haut ou large). */
  fit() {
    const cam = this.camera, a = cam.aspect;
    // Angle sous l'horizontale : écran haut (téléphone), plateau vu presque de dessus ; écran large
    // (ordinateur), vu comme debout devant la machine, en perspective.
    const pitch = this.pitch ?? (a < 0.8 ? 0.98 : a < 1.3 ? 0.9 : 0.72);
    cam.fov = a < 0.8 ? 39 : 36;
    const look = new THREE.Vector3(0, 0, Z(H * 0.53));
    const pts = [P(-14, -16, 9), P(W + 14, -16, 9), P(-14, H + 12), P(W + 14, H + 12), P(-14, H * 0.5), P(W + 14, H * 0.5)];
    const dir = new THREE.Vector3(0, Math.sin(pitch), Math.cos(pitch));
    let lo = 40, hi = 400;
    for (let i = 0; i < 30; i++) {
      const d = (lo + hi) / 2;
      cam.position.copy(look).addScaledVector(dir, d); cam.lookAt(look); cam.updateProjectionMatrix(); cam.updateMatrixWorld();
      const ok = pts.every(p => { const v = p.clone().project(cam); return Math.abs(v.x) < 0.985 && Math.abs(v.y) < 0.985; });
      if (ok) hi = d; else lo = d;
    }
    cam.position.copy(look).addScaledVector(dir, hi); cam.lookAt(look); cam.updateProjectionMatrix();
    this.camBase = cam.position.clone(); this.camLook = look;
  }

  /** Qualité : 2 = ombres et halo, 1 = sans halo, 0 = sans ombres, en définition simple. */
  setQuality(q) {
    this.quality = q;
    this.renderer.shadowMap.enabled = q >= 1;
    this.key.castShadow = q >= 1;
    if (q === 0) { this.renderer.setPixelRatio(1); this.resize(this.w, this.h); }
    this.scene.traverse(o => { if (o.material) o.material.needsUpdate = true; });
  }

  // ------------------------------------------------------------------ animation

  /** Effet visuel ponctuel (appelé par les règles). */
  fx(name, data) {
    if (name === 'bumper' && this.bumpers[data]) this.bumpers[data].flash = 1;
    else if (name === 'sling' && this.slings[data]) this.slings[data].flash = 1;
    else if (name === 'nudge') this.shake = 0.5;
    else if (name === 'tilt') this.shake = 1;
    else if (['jackpot', 'multiball', 'cupwon', 'letters', 'lanes', 'skill', 'extra'].includes(name)) this.strobe = 1.6;
  }

  frame(phys, lamps, dt, time, plungerPull = 0) {
    dt = Math.max(0, dt);
    // billes
    const live = phys.balls.filter(b => b.state !== 'gone');
    this.balls.forEach((m, i) => {
      const b = live[i];
      m.visible = !!b;
      if (b) m.position.set(X(b.x), R * S + (b.state === 'ramp' ? b.h * S + 0.25 : 0), Z(b.y));
    });
    // batteurs
    phys.flippers.forEach((f, i) => { this.flippers[i].rotation.y = -f.ang; });
    // cibles (tombent dans le plateau)
    let ti = 0;
    for (const s of phys.segs) {
      if (s.tag !== 'target') continue;
      const tg = this.targets[ti++];
      const want = s.on ? 1.1 : -1.3;
      tg.y += (want - tg.y) * Math.min(1, dt * 18);
      tg.m.position.y = tg.y;
    }
    // bumpers et lance-pierres
    const lion = lamps && lamps.lion;
    for (const b of this.bumpers) {
      b.flash = Math.max(0, b.flash - dt * 5);
      const k = b.flash + (lion ? 0.4 + 0.3 * Math.sin(time * 10) : 0);
      b.discMat.emissiveIntensity = 0.25 + k * 1.6;
      b.ringMat.color.copy(this.col.yellow).multiplyScalar(1.5 + k * 2.5);
      b.light.intensity = 6 + k * 40;
    }
    for (const s of this.slings) { s.flash = Math.max(0, s.flash - dt * 6); s.mat.emissiveIntensity = 0.15 + s.flash * 2; }
    // lampes
    this.strobe = Math.max(0, (this.strobe || 0) - dt);
    const blink = Math.sin(time * 12) > 0, slow = Math.sin(time * 5) > 0, st = this.strobe > 0 && Math.sin(time * 30) > 0;
    const set = (l, on) => { const v = st ? 1.4 : on === 'blink' ? (blink ? 1.4 : 0.05) : on ? 1.2 : 0.02; l.mat.emissiveIntensity += (v - l.mat.emissiveIntensity) * Math.min(1, dt * 20); };
    if (lamps) {
      this.L.lanes.forEach((l, i) => set(l, lamps.skill === i ? 'blink' : !!lamps.lanes[i]));
      this.L.cup.forEach((l, i) => set(l, i < lamps.cup ? true : i === lamps.cup ? (i === 5 ? 'blink' : slow ? 'blink' : false) : false));
      this.L.letters.forEach((l, i) => set(l, !!lamps.letters[i]));
      this.L.mult.forEach((l, i) => set(l, lamps.mult >= i + 2));
      set(this.L.kickback, lamps.kickback);
      set(this.L.save, lamps.save ? 'blink' : false);
      this.L.ramp.forEach((l, i) => set(l, lamps.multiball ? 'blink' : (Math.floor(time * 4) % 3) === 2 - i));
      const fin = lamps.finale || lamps.multiball;
      this.saucerRing.color.copy(this.col.yellow).multiplyScalar(fin ? (blink ? 3 : 0.8) : 0.6);
      this.goldMat.emissiveIntensity = fin ? 1.2 : 0.15;
      this.goldMat.emissive.setHex(fin ? 0xffb000 : 0x3a2a00);
    }
    this.cup.rotation.y = time * 0.6;
    this.cup.position.y = 0.4 + Math.sin(time * 1.6) * 0.25;
    // lanceur
    this.knob.position.z = this.plungerZ + plungerPull * 3;
    // secousse
    this.shake = Math.max(0, this.shake - dt * 2.5);
    if (this.camBase) {
      const s = this.shake * 0.6;
      this.camera.position.set(this.camBase.x + (Math.random() - 0.5) * s, this.camBase.y, this.camBase.z + (Math.random() - 0.5) * s);
      this.camera.lookAt(this.camLook);
    }
  }

  render() {
    if (this.quality >= 2 && this.composer) this.composer.render(); else this.renderer.render(this.scene, this.camera);
  }
}
