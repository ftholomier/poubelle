// Le stade autour du flipper (Three.js) : pelouse, panneaux lumineux, tribunes et leur foule (une
// seule géométrie dupliquée, qui saute quand le club marque), banderoles aux couleurs du club avec le
// logo du musée, pylônes d'éclairage, écran géant (titre, score du match), deux joueurs géants en
// tenue du club à côté de la machine, banc de touche. Tout est dessiné ici (formes et textures
// peintes) : aucune image ni personnage repris d'un autre jeu.
import * as THREE from 'three';
import { mergeGeometries } from 'three/examples/jsm/utils/BufferGeometryUtils.js';

const FONT = '"Big Shoulders Display", "Arial Narrow", sans-serif';

function canvas(w, h) { const c = document.createElement('canvas'); c.width = w; c.height = h; return [c, c.getContext('2d')]; }
function tex(c, aniso = 4) { const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = aniso; return t; }
function txt(g, s, x, y, size, color, o = {}) {
  g.save(); g.translate(x, y); if (o.rot) g.rotate(o.rot);
  g.font = `${o.weight || 800} ${size}px ${FONT}`; if ('letterSpacing' in g) g.letterSpacing = (o.ls || 0) + 'px';
  g.textAlign = o.align || 'center'; g.textBaseline = 'middle'; g.fillStyle = color;
  if (o.glow) { g.shadowColor = o.glow; g.shadowBlur = o.blur || 12; }
  if (o.max) { const w = g.measureText(s).width; if (w > o.max) g.scale(o.max / w, 1); }
  g.fillText(s, 0, 0); g.restore();
}
function logoAt(g, img, x, y, h) {
  if (!img) return;
  const w = h * (img.width / img.height || 0.88);
  g.drawImage(img, x - w / 2, y - h / 2, w, h);
}

export class Stadium {
  /**
   * cfg : { colors: {navy, yellow, blue}, kit: {shirt, sleeves, shorts, socks}, logo (Image), title,
   * site (nom du musée), short (sigle du club), opp: {name, ini} }.
   * box : emprise de la machine (x1, x2, z1, z2) ; ground : hauteur du sol.
   */
  constructor(scene, cfg, box) {
    this.scene = scene;
    this.cfg = cfg;
    this.box = box;
    this.ground = box.ground;
    this.root = new THREE.Group();
    scene.add(this.root);
    this.hype = 0;
    this.time = 0;
    this.sky();
    this.pitch();
    this.boards();
    this.stands();
    this.towers();
    this.screenBuild();
    this.figures = [this.figure(-1), this.figure(1)];
    this.bench();
  }

  // ------------------------------------------------------------------ ciel, pelouse

  sky() {
    const [c, g] = canvas(16, 256), gr = g.createLinearGradient(0, 0, 0, 256);
    gr.addColorStop(0, '#01030a'); gr.addColorStop(0.55, '#06112e'); gr.addColorStop(1, '#0d1f4a');
    g.fillStyle = gr; g.fillRect(0, 0, 16, 256);
    this.scene.background = tex(c);
    this.scene.fog = new THREE.Fog(0x050b1e, 170, 420);
  }

  pitch() {
    const [c, g] = canvas(512, 512);
    for (let i = 0; i < 8; i++) { g.fillStyle = i % 2 ? '#2f7f37' : '#378f40'; g.fillRect(0, i * 64, 512, 64); }
    // Brins d'herbe : un léger bruit pour que la pelouse ne soit pas une couleur plate.
    for (let i = 0; i < 9000; i++) { g.fillStyle = `rgba(${Math.random() < 0.5 ? '0,0,0' : '255,255,255'},${0.03 + Math.random() * 0.04})`; g.fillRect(Math.random() * 512, Math.random() * 512, 1.5, 3); }
    const t = tex(c, 8); t.wrapS = t.wrapT = THREE.RepeatWrapping; t.repeat.set(10, 10);
    const m = new THREE.Mesh(new THREE.PlaneGeometry(420, 420), new THREE.MeshStandardMaterial({ map: t, roughness: 0.95 }));
    m.rotation.x = -Math.PI / 2; m.position.y = this.ground; m.receiveShadow = true;
    this.root.add(m);
    // Lignes blanches autour de la machine (comme une surface de réparation tracée sur la pelouse).
    const b = this.box, lm = new THREE.MeshBasicMaterial({ color: 0xdfe8df, transparent: true, opacity: 0.55 });
    const line = (x, z, w, d) => { const l = new THREE.Mesh(new THREE.PlaneGeometry(w, d), lm); l.rotation.x = -Math.PI / 2; l.position.set(x, this.ground + 0.02, z); this.root.add(l); };
    const pad = 9, x1 = b.x1 - pad, x2 = b.x2 + pad, z1 = b.z1 - pad, z2 = b.z2 + pad;
    line((x1 + x2) / 2, z1, x2 - x1, 0.5); line(x1, (z1 + z2) / 2, 0.5, z2 - z1); line(x2, (z1 + z2) / 2, 0.5, z2 - z1);
    const ring = new THREE.Mesh(new THREE.RingGeometry(17.5, 18, 64, 1, Math.PI, Math.PI), lm);
    ring.rotation.x = -Math.PI / 2; ring.position.set(0, this.ground + 0.02, z1); this.root.add(ring);
  }

  // ------------------------------------------------------------------ panneaux lumineux

  boards() {
    const cfg = this.cfg, Y = cfg.colors.yellow, N = cfg.colors.navy;
    const [c, g] = canvas(2048, 128);
    const items = [cfg.site, cfg.title, cfg.short, cfg.site, cfg.title, cfg.short];
    const w = 2048 / items.length;
    items.forEach((s, i) => {
      g.fillStyle = i % 2 ? N : Y; g.fillRect(i * w, 0, w, 128);
      txt(g, String(s || '').toUpperCase(), i * w + w / 2, 66, 74, i % 2 ? Y : N, { weight: 900, ls: 4, max: w - 40 });
    });
    const t = tex(c); t.wrapS = THREE.RepeatWrapping;
    const frame = new THREE.MeshStandardMaterial({ color: 0x0a0f1e, roughness: 0.6 });
    const b = this.box, hgt = 3.2, y = this.ground + hgt / 2;
    const add = (len, x, z, ry, rep) => {
      const tt = t.clone(); tt.needsUpdate = true; tt.repeat.set(rep, 1); this.boardTexs = (this.boardTexs || []).concat(tt);
      const m = new THREE.Mesh(new THREE.BoxGeometry(len, hgt, 0.6), [frame, frame, frame, frame, new THREE.MeshStandardMaterial({ map: tt, emissiveMap: tt, emissive: 0xffffff, emissiveIntensity: 0.9, roughness: 0.5 }), frame]);
      m.position.set(x, y, z); m.rotation.y = ry; this.root.add(m);
    };
    const zb = b.z1 - 30, xs = 52;
    add(2 * xs, 0, zb, 0, 2);
    add(b.z2 - zb + 20, -xs, (zb + b.z2 + 20) / 2, Math.PI / 2, 2);
    add(b.z2 - zb + 20, xs, (zb + b.z2 + 20) / 2, -Math.PI / 2, 2);
  }

  // ------------------------------------------------------------------ tribunes et foule

  stands() {
    const cfg = this.cfg, b = this.box;
    const concrete = new THREE.MeshStandardMaterial({ color: 0x2a3142, roughness: 0.9 });
    const seatA = new THREE.MeshStandardMaterial({ color: new THREE.Color(cfg.colors.yellow).multiplyScalar(0.6), roughness: 0.7 });
    const seatB = new THREE.MeshStandardMaterial({ color: new THREE.Color(cfg.colors.blue).multiplyScalar(0.6), roughness: 0.7 });
    const ROWS = 14, STEP = 2.6, RISE = 1.35;
    const spots = []; // places des spectateurs : x, y, z, angle
    // Tribune du fond (derrière la machine) et tribunes des côtés, en marches.
    const sides = [
      { len: 150, base: [0, b.z1 - 36], dir: [0, -1], ry: 0 },
      { len: 170, base: [-58, (b.z1 + b.z2) / 2 - 10], dir: [-1, 0], ry: Math.PI / 2 },
      { len: 170, base: [58, (b.z1 + b.z2) / 2 - 10], dir: [1, 0], ry: -Math.PI / 2 },
    ];
    for (const s of sides) {
      for (let i = 0; i < ROWS; i++) {
        const d = i * STEP, y = this.ground + 1 + i * RISE;
        const cx = s.base[0] + s.dir[0] * d, cz = s.base[1] + s.dir[1] * d;
        const step = new THREE.Mesh(new THREE.BoxGeometry(s.dir[0] ? STEP : s.len, 1 + i * RISE + 1, s.dir[0] ? s.len : STEP), concrete);
        step.position.set(cx, this.ground + (1 + i * RISE + 1) / 2 - 0.5, cz); this.root.add(step);
        // Rangée de sièges (jaunes et bleus en bandes).
        const seats = new THREE.Mesh(new THREE.BoxGeometry(s.dir[0] ? 0.9 : s.len, 0.7, s.dir[0] ? s.len : 0.9), i % 4 < 2 ? seatA : seatB);
        seats.position.set(cx, y + 0.9, cz); this.root.add(seats);
        for (let k = 0; k < s.len / 1.45; k++) {
          if (Math.random() < 0.12) continue; // quelques places vides
          const t = -s.len / 2 + 0.8 + k * 1.45 + (Math.random() - 0.5) * 0.3;
          spots.push([cx + (s.dir[0] ? 0 : t), y + 1.2, cz + (s.dir[0] ? t : 0), s.ry]);
        }
      }
      // Parapet du premier rang : banderoles.
      const front = new THREE.Mesh(new THREE.BoxGeometry(s.dir[0] ? 0.5 : s.len, 3, s.dir[0] ? s.len : 0.5), concrete);
      front.position.set(s.base[0] - s.dir[0] * 1.6, this.ground + 1.5, s.base[1] - s.dir[1] * 1.6); this.root.add(front);
      this.banners(s);
      // Toit : une avancée sombre, avec une rampe de lumière sur son bord.
      const d = ROWS * STEP, roofY = this.ground + ROWS * RISE + 9;
      const roof = new THREE.Mesh(new THREE.BoxGeometry(s.dir[0] ? d * 0.8 : s.len + 8, 0.8, s.dir[0] ? s.len + 8 : d * 0.8), new THREE.MeshStandardMaterial({ color: 0x0b1020, roughness: 0.7 }));
      roof.position.set(s.base[0] + s.dir[0] * d * 0.62, roofY, s.base[1] + s.dir[1] * d * 0.62); this.root.add(roof);
      const strip = new THREE.Mesh(new THREE.BoxGeometry(s.dir[0] ? 0.4 : s.len + 8, 0.4, s.dir[0] ? s.len + 8 : 0.4), new THREE.MeshBasicMaterial({ color: 0xfff6dc }));
      strip.position.set(s.base[0] + s.dir[0] * d * 0.22, roofY - 0.6, s.base[1] + s.dir[1] * d * 0.22); this.root.add(strip);
    }
    this.crowd(spots);
  }

  /** Les spectateurs : un corps et une tête, une seule géométrie dupliquée ; ils sautent (shader). */
  crowd(spots) {
    const body = new THREE.BoxGeometry(0.95, 1.5, 0.6); body.translate(0, 0.75, 0);
    // Tête à 8 faces : de si loin, on ne la distingue pas d'une sphère, et la foule compte des
    // milliers de spectateurs (une sphère détaillée y pesait 80 % des triangles de la scène).
    const head = new THREE.OctahedronGeometry(0.42, 0); head.translate(0, 1.85, 0);
    // Couleur par sommet : corps blanc (teinté par instance), tête couleur peau.
    const paint = (geo, r, g, b) => { const n = geo.attributes.position.count, a = new Float32Array(n * 3); for (let i = 0; i < n; i++) a.set([r, g, b], i * 3); geo.setAttribute('color', new THREE.BufferAttribute(a, 3)); };
    paint(body, 1, 1, 1); paint(head, 0.85, 0.66, 0.52);
    const geo = mergeGeometries([body.toNonIndexed(), head]); // l'octaèdre est déjà sans index
    this.crowdU = { uTime: { value: 0 }, uHype: { value: 0 } };
    const mat = new THREE.MeshLambertMaterial({ vertexColors: true });
    mat.onBeforeCompile = sh => {
      sh.uniforms.uTime = this.crowdU.uTime; sh.uniforms.uHype = this.crowdU.uHype;
      sh.vertexShader = sh.vertexShader
        .replace('#include <common>', '#include <common>\nattribute float aPhase;\nuniform float uTime;\nuniform float uHype;')
        .replace('#include <begin_vertex>', '#include <begin_vertex>\n  float ph = aPhase * 6.2831;\n  transformed.y += abs(sin(uTime * (2.0 + uHype * 6.0) + ph)) * (0.08 + uHype * 1.1) * step(0.35, fract(aPhase * 7.13) + uHype);\n  transformed.x += sin(uTime * 1.3 + ph) * 0.05;');
    };
    const im = new THREE.InstancedMesh(geo, mat, spots.length);
    const phase = new Float32Array(spots.length);
    const cols = [this.cfg.colors.yellow, this.cfg.colors.yellow, this.cfg.colors.blue, this.cfg.colors.blue, this.cfg.colors.navy, '#e8e8e8', '#20242c', '#b8bcc4'].map(c => new THREE.Color(c));
    const m4 = new THREE.Matrix4(), q = new THREE.Quaternion(), e = new THREE.Euler(), s = new THREE.Vector3();
    spots.forEach(([x, y, z, ry], i) => {
      const k = 0.9 + Math.random() * 0.2;
      e.set(0, ry + (Math.random() - 0.5) * 0.3, 0); q.setFromEuler(e); s.set(k, k, k);
      m4.compose(new THREE.Vector3(x, y, z), q, s); im.setMatrixAt(i, m4);
      im.setColorAt(i, cols[Math.floor(Math.random() * cols.length)]);
      phase[i] = Math.random();
    });
    geo.setAttribute('aPhase', new THREE.InstancedBufferAttribute(phase, 1));
    im.frustumCulled = false;
    this.root.add(im);
    this.crowdMesh = im;
  }

  /** Banderoles du parapet : bandes jaunes et bleues, logo du musée, sigle du club. */
  banners(s) {
    const cfg = this.cfg, n = Math.floor(s.len / 26);
    for (let i = 0; i < n; i++) {
      const [c, g] = canvas(512, 160), kind = i % 3;
      if (kind === 0) {
        for (let k = 0; k < 8; k++) { g.fillStyle = k % 2 ? cfg.colors.blue : cfg.colors.yellow; g.fillRect(k * 64, 0, 64, 160); }
        g.fillStyle = 'rgba(0,0,0,.25)'; g.fillRect(0, 0, 512, 160);
        logoAt(g, cfg.logo, 256, 80, 140);
      } else if (kind === 1) {
        g.fillStyle = cfg.colors.navy; g.fillRect(0, 0, 512, 160);
        g.fillStyle = cfg.colors.yellow; g.fillRect(0, 0, 512, 14); g.fillRect(0, 146, 512, 14);
        txt(g, String(cfg.short || '').toUpperCase(), 256, 84, 110, cfg.colors.yellow, { weight: 900, ls: 10, max: 470 });
      } else {
        g.fillStyle = cfg.colors.yellow; g.fillRect(0, 0, 512, 160);
        logoAt(g, cfg.logo, 70, 80, 120);
        txt(g, String(cfg.site || '').toUpperCase(), 290, 84, 64, cfg.colors.navy, { weight: 900, ls: 3, max: 380 });
      }
      const m = new THREE.Mesh(new THREE.PlaneGeometry(22, 6.8), new THREE.MeshStandardMaterial({ map: tex(c), roughness: 0.8, side: THREE.DoubleSide }));
      const t = -s.len / 2 + 13 + i * 26;
      m.position.set(s.base[0] - s.dir[0] * 2 + (s.dir[0] ? 0 : t), this.ground + 2.6, s.base[1] - s.dir[1] * 2 + (s.dir[0] ? t : 0));
      m.rotation.y = s.ry;
      this.root.add(m);
    }
  }

  // ------------------------------------------------------------------ pylônes, écran géant

  towers() {
    const b = this.box, pole = new THREE.MeshStandardMaterial({ color: 0x3a4255, metalness: 0.6, roughness: 0.5 });
    const [c, g] = canvas(128, 64);
    g.fillStyle = '#1a1f2c'; g.fillRect(0, 0, 128, 64);
    for (let i = 0; i < 6; i++) for (let j = 0; j < 3; j++) { const gr = g.createRadialGradient(12 + i * 21, 11 + j * 21, 1, 12 + i * 21, 11 + j * 21, 10); gr.addColorStop(0, '#ffffff'); gr.addColorStop(0.5, '#fff2c8'); gr.addColorStop(1, 'rgba(255,240,200,0)'); g.fillStyle = gr; g.fillRect(i * 21 + 2, j * 21 + 1, 20, 20); }
    const head = new THREE.MeshBasicMaterial({ map: tex(c) });
    this.glows = [];
    for (const sx of [-1, 1]) {
      const x = sx * 80, z = b.z1 - 62;
      const p = new THREE.Mesh(new THREE.CylinderGeometry(0.9, 1.4, 70, 10), pole); p.position.set(x, this.ground + 35, z); this.root.add(p);
      const h = new THREE.Mesh(new THREE.BoxGeometry(16, 8, 1), [pole, pole, pole, pole, head, pole]);
      h.position.set(x, this.ground + 72, z + 1); h.rotation.x = 0.35; h.rotation.y = -sx * 0.45; this.root.add(h);
      // Halo des projecteurs (taches lumineuses qui « bavent » avec l'effet de halo).
      const [gc, gg] = canvas(128, 128), gr = gg.createRadialGradient(64, 64, 2, 64, 64, 64);
      gr.addColorStop(0, 'rgba(255,250,235,1)'); gr.addColorStop(0.25, 'rgba(255,240,200,.45)'); gr.addColorStop(1, 'rgba(255,240,200,0)');
      gg.fillStyle = gr; gg.fillRect(0, 0, 128, 128);
      const sp = new THREE.Sprite(new THREE.SpriteMaterial({ map: tex(gc), transparent: true, depthWrite: false, blending: THREE.AdditiveBlending, fog: false }));
      sp.scale.set(40, 26, 1); sp.position.set(x, this.ground + 72, z + 3); this.root.add(sp);
      this.glows.push(sp);
    }
  }

  /** Écran géant derrière la machine : titre, score du match et minute (repeint quand ils changent). */
  screenBuild() {
    const b = this.box;
    [this.scrCanvas, this.scrG] = canvas(1024, 300);
    this.scrTex = tex(this.scrCanvas, 8);
    const face = new THREE.MeshStandardMaterial({ map: this.scrTex, emissiveMap: this.scrTex, emissive: 0xffffff, emissiveIntensity: 0.85, roughness: 0.4 });
    const dark = new THREE.MeshStandardMaterial({ color: 0x0a0f1e, roughness: 0.6 });
    const m = new THREE.Mesh(new THREE.BoxGeometry(50, 14.6, 1.2), [dark, dark, dark, dark, face, dark]);
    m.position.set(0, this.ground + 24, b.z1 - 20); m.rotation.x = -0.12; this.root.add(m);
    // Minute du match : sa propre petite toile devant l'écran (elle change sans cesse, le score rarement).
    [this.minCanvas, this.minG] = canvas(256, 64);
    this.minTex = tex(this.minCanvas, 4);
    const mm = new THREE.Mesh(new THREE.PlaneGeometry(50 * 256 / 1024, 14.6 * 64 / 300), new THREE.MeshStandardMaterial({ map: this.minTex, emissiveMap: this.minTex, emissive: 0xffffff, emissiveIntensity: 0.85, roughness: 0.4 }));
    mm.position.set(0, 14.6 * (150 - 262) / 300, 0.61); m.add(mm);
    for (const sx of [-1, 1]) { const leg = new THREE.Mesh(new THREE.BoxGeometry(1.2, 18, 1.2), dark); leg.position.set(sx * 20, this.ground + 9, b.z1 - 20.5); this.root.add(leg); }
    this.setScore(0, 0, '', true);
  }

  setScore(home, away, minute, force = false) {
    if (minute !== this.minKey || force) {
      this.minKey = minute;
      this.minG.fillStyle = '#050a18'; this.minG.fillRect(0, 0, 256, 64);
      if (minute) txt(this.minG, minute, 128, 36, 54, this.cfg.colors.yellow, { weight: 800, ls: 4 });
      this.minTex.needsUpdate = true;
    }
    const key = home + ':' + away;
    if (key === this.scrKey && !force) return;
    this.scrKey = key;
    const g = this.scrG, cfg = this.cfg, Y = cfg.colors.yellow;
    g.fillStyle = '#050a18'; g.fillRect(0, 0, 1024, 300);
    g.fillStyle = cfg.colors.navy; g.fillRect(0, 0, 1024, 70);
    logoAt(g, cfg.logo, 50, 35, 58); logoAt(g, cfg.logo, 974, 35, 58);
    txt(g, String(cfg.title || '').toUpperCase(), 512, 38, 50, Y, { weight: 900, ls: 6, max: 820, glow: 'rgba(246,196,0,.6)', blur: 10 });
    // Tableau : sigle du club, score, adversaire, minute.
    const opp = cfg.opp || {};
    g.fillStyle = Y; g.fillRect(40, 96, 300, 130);
    txt(g, String(cfg.short || '').toUpperCase(), 190, 162, 92, cfg.colors.navy, { weight: 900, max: 270 });
    g.fillStyle = '#e9edf5'; g.fillRect(684, 96, 300, 130);
    txt(g, String(opp.ini || opp.name || '').toUpperCase(), 834, 162, 92, '#0a0f1e', { weight: 900, max: 270 });
    txt(g, home + ' – ' + away, 512, 160, 140, '#fff', { weight: 900, glow: 'rgba(255,255,255,.5)', blur: 14 });
    this.scrTex.needsUpdate = true;
  }

  // ------------------------------------------------------------------ joueurs géants, banc

  /** Un joueur géant en tenue du club, à gauche (côté -1, il jongle) ou à droite (côté 1, il applaudit). */
  figure(side) {
    const k = this.cfg.kit, b = this.box;
    const m = c => new THREE.MeshStandardMaterial({ color: c, roughness: 0.55 });
    const shirt = m(k.shirt), sleeve = m(k.sleeves), shorts = m(k.shorts), socks = m(k.socks), skin = m('#d9a07a'), hair = m(side < 0 ? '#2a1a10' : '#6b4423'), boot = m('#111318');
    const root = new THREE.Group(), S = 1;
    // Pas d'ombre portée : les joueurs géants sont loin du plateau (rendu plus léger).
    const part = (geo, mat, x, y, z, parent = root) => { const o = new THREE.Mesh(geo, mat); o.position.set(x, y, z); parent.add(o); return o; };
    // Jambes : hanche (pivot), cuisse dans le short, chaussette, chaussure.
    const leg = sx => {
      const hip = new THREE.Group(); hip.position.set(sx * 1.25, 10.2 * S, 0); root.add(hip);
      part(new THREE.CapsuleGeometry(0.95, 3.2, 4, 10), skin, 0, -2.4, 0, hip);
      part(new THREE.CylinderGeometry(1.05, 0.95, 4.6, 12), socks, 0, -6.6, 0, hip);
      part(new THREE.BoxGeometry(1.6, 1, 2.8), boot, 0, -9.3, 0.6, hip);
      return hip;
    };
    const legL = leg(-1), legR = leg(1);
    part(new THREE.CylinderGeometry(2.55, 2.4, 3.4, 16), shorts, 0, 10.4, 0);
    const torso = part(new THREE.CapsuleGeometry(2.45, 4.4, 6, 16), shirt, 0, 14.6, 0);
    torso.scale.set(1.05, 1, 0.72);
    // Logo du musée sur le maillot (côté poitrine, face à la caméra).
    if (this.cfg.logo) {
      const [c, g] = canvas(128, 128); logoAt(g, this.cfg.logo, 64, 64, 120);
      const badge = new THREE.Mesh(new THREE.PlaneGeometry(1.5, 1.5), new THREE.MeshStandardMaterial({ map: tex(c), transparent: true, roughness: 0.5 }));
      badge.position.set(side * -0.9, 16, 1.8); root.add(badge);
    }
    part(new THREE.CylinderGeometry(0.75, 0.9, 1.2, 10), skin, 0, 18.6, 0);
    const head = part(new THREE.SphereGeometry(1.7, 18, 14), skin, 0, 20.4, 0.1);
    head.scale.set(0.92, 1.08, 0.95);
    const hr = part(new THREE.SphereGeometry(1.78, 18, 10, 0, Math.PI * 2, 0, Math.PI / 2.1), hair, 0, 20.75, -0.05);
    hr.rotation.x = -0.25;
    // Bras : épaule (pivot), manche, avant-bras, main.
    const arm = sx => {
      const sh = new THREE.Group(); sh.position.set(sx * 3.05, 17.4, 0); root.add(sh);
      part(new THREE.CapsuleGeometry(0.85, 1.8, 4, 10), sleeve, 0, -1.4, 0, sh);
      part(new THREE.CapsuleGeometry(0.7, 2.6, 4, 10), skin, 0, -4.4, 0, sh);
      part(new THREE.SphereGeometry(0.75, 10, 8), skin, 0, -6.2, 0, sh);
      sh.rotation.z = sx * 0.18;
      return sh;
    };
    const armL = arm(-1), armR = arm(1);
    let ball = null;
    if (side < 0) {
      const [c, g] = canvas(128, 64);
      g.fillStyle = '#f4f4f4'; g.fillRect(0, 0, 128, 64);
      g.fillStyle = '#16181d'; for (let i = 0; i < 6; i++) for (let j = 0; j < 3; j++) { g.beginPath(); g.arc(10 + i * 22 + (j % 2) * 11, 10 + j * 22, 6, 0, Math.PI * 2); g.fill(); }
      ball = part(new THREE.SphereGeometry(1.3, 20, 14), new THREE.MeshStandardMaterial({ map: tex(c), roughness: 0.45 }), 1.25, 3, 2.5);
    }
    // Pose fixe (aucune animation, pour alléger le rendu) : à gauche le ballon sur le genou levé,
    // à droite les mains jointes devant lui.
    if (side < 0) { legR.rotation.x = -0.9; ball.position.set(1.25, 9, 3.8); armL.rotation.z = -0.43; armR.rotation.z = 0.43; } else { armL.rotation.x = -0.9; armR.rotation.x = -0.9; armL.rotation.z = 0.17; armR.rotation.z = -0.17; }
    root.position.set(side < 0 ? b.x1 - 15 : b.x2 + 15, this.ground, b.z1 + 26);
    root.rotation.y = -side * 0.45;
    root.scale.setScalar(1.5);
    this.root.add(root);
    // Immobile : ses matrices sont calculées une fois pour toutes.
    root.updateMatrixWorld(true);
    root.traverse(o => { o.matrixAutoUpdate = false; });
    return { root, side };
  }

  /** Banc de touche : abri transparent, sièges aux couleurs du club. */
  bench() {
    const b = this.box, g = new THREE.Group(), cfg = this.cfg;
    const base = new THREE.Mesh(new THREE.BoxGeometry(6, 1, 16), new THREE.MeshStandardMaterial({ color: 0x2a3142, roughness: 0.8 }));
    base.position.y = 0.5; g.add(base);
    for (let i = 0; i < 4; i++) {
      const c = i % 2 ? cfg.colors.blue : cfg.colors.yellow, mat = new THREE.MeshStandardMaterial({ color: c, roughness: 0.4 });
      const seat = new THREE.Mesh(new THREE.BoxGeometry(2.6, 0.6, 2.8), mat); seat.position.set(0.2, 1.6, -5.4 + i * 3.6); g.add(seat);
      const back = new THREE.Mesh(new THREE.BoxGeometry(0.5, 3.2, 2.8), mat); back.position.set(1.4, 3.1, -5.4 + i * 3.6); back.rotation.z = -0.12; g.add(back);
    }
    const shell = new THREE.Mesh(new THREE.CylinderGeometry(5.6, 5.6, 17, 24, 1, true, 0, Math.PI), new THREE.MeshStandardMaterial({ color: 0xbfd6ff, transparent: true, opacity: 0.22, roughness: 0.1, metalness: 0.2, side: THREE.DoubleSide, depthWrite: false }));
    shell.rotation.x = Math.PI / 2; shell.rotation.y = Math.PI / 2; shell.position.set(-0.5, 1, 0); g.add(shell);
    g.position.set(b.x2 + 16, this.ground, b.z2 - 26);
    this.root.add(g);
  }

  // ------------------------------------------------------------------ animation

  /** Le club marque : la foule saute (les joueurs géants restent immobiles, pour alléger le rendu). */
  celebrate(k = 1) { this.hype = Math.max(this.hype, k); }

  update(dt, time) {
    this.time = time;
    this.hype = Math.max(0, this.hype - dt * 0.22);
    this.crowdU.uTime.value = time;
    this.crowdU.uHype.value = Math.min(1, this.hype);
    for (const t of this.boardTexs || []) t.offset.x = (time * 0.025) % 1;
  }

  setQuality(q) {
    if (this.crowdMesh) this.crowdMesh.visible = q >= 1;
  }
}
