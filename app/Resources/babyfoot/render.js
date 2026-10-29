// Rendu 3D du babyfoot (Three.js) : table en bois, tapis, cages, barres chromées, figurines aux
// couleurs des deux équipes (noms au dos dans « Rejoue l'histoire »), balle, lampe de bistrot.
// Repère : x et z comme dans table.js, la hauteur en y (le tapis à y = 0).
import * as THREE from 'three';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';
import { mergeGeometries } from 'three/examples/jsm/utils/BufferGeometryUtils.js';
import { EffectComposer } from 'three/examples/jsm/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/examples/jsm/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/examples/jsm/postprocessing/UnrealBloomPass.js';
import { OutputPass } from 'three/examples/jsm/postprocessing/OutputPass.js';
import { LX, LZ, R, GOAL, DEPTH, ROD_Y, buildRods } from './table.js';

const FONT = '"Big Shoulders Display", "Arial Narrow", sans-serif';
let ANISO = 8;
function canvas(w, h) { const c = document.createElement('canvas'); c.width = w; c.height = h; return [c, c.getContext('2d')]; }
function tex(c) { const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = ANISO; return t; }

/** Bois : veinures tirées au hasard (graine fixe : même table à chaque partie). */
function woodTex(base, seed = 7) {
  let s = seed;
  const rnd = () => (s = (s * 16807) % 2147483647) / 2147483647;
  const [c, g] = canvas(1024, 256);
  g.fillStyle = base; g.fillRect(0, 0, 1024, 256);
  for (let i = 0; i < 90; i++) {
    const y = rnd() * 256, a = 2 + rnd() * 6, f = 0.004 + rnd() * 0.01, ph = rnd() * 7;
    g.strokeStyle = `rgba(${rnd() < 0.5 ? '60,30,10' : '190,130,70'},${0.08 + rnd() * 0.14})`;
    g.lineWidth = 1 + rnd() * 2.5; g.beginPath();
    for (let x = 0; x <= 1024; x += 8) g.lineTo(x, y + Math.sin(x * f + ph) * a);
    g.stroke();
  }
  const t = tex(c); t.wrapS = t.wrapT = THREE.RepeatWrapping; return t;
}

/** Les vues proposées par le bouton « Caméra » (paysage, et téléphone tenu droit). */
const VIEWS = {
  joueur: { land: { pos: [0, 92, 104], at: [0, -3, 7] }, port: { pos: [-128, 112, 0], at: [6, -6, 0] } },
  dessus: { land: { pos: [0, 175, 0.01], at: [0, 0, 0], up: [0, 0, -1] }, port: { pos: [0.01, 175, 0], at: [0, 0, 0], up: [1, 0, 0] } },
  // Vue basse : au ras de la bande avant ; seule la largeur de la table compte pour le recul.
  basse: { land: { pos: [0, 40, 76], at: [0, 0, -6], fit: 'x' }, port: { pos: [-100, 36, 0], at: [10, 0, 0], fit: 'x' } },
};

export class Scene3D {
  /**
   * cfg : { title, logo (Image), kits: [kit0, kit1] ({ shirt, sleeves, shorts, socks }),
   * glow: [couleur barre active équipe 0, équipe 1] }
   */
  constructor(el, cfg) {
    this.el = el;
    this.cfg = cfg;
    this.quality = 2;
    this.view = 'joueur';
    this.shake = 0;
    this.rods = buildRods();
    this.renderer = new THREE.WebGLRenderer({ canvas: el, antialias: true, powerPreference: 'high-performance', preserveDrawingBuffer: false });
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    ANISO = Math.min(16, this.renderer.capabilities.getMaxAnisotropy() || 8);
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFShadowMap;
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.toneMappingExposure = 1.05;
    this.renderer.outputColorSpace = THREE.SRGBColorSpace;
    this.scene = new THREE.Scene();
    const pm = new THREE.PMREMGenerator(this.renderer);
    this.scene.environment = pm.fromScene(new RoomEnvironment(), 0.04).texture;
    this.scene.environmentIntensity = 0.42;
    this.camera = new THREE.PerspectiveCamera(38, 1, 1, 1200);
    this.camPos = new THREE.Vector3(0, 92, 104);
    this.camAt = new THREE.Vector3();
    this.build();
  }

  // ------------------------------------------------------------------ construction

  build() {
    const sc = this.scene;
    const [bc, bg] = canvas(256, 256), grad = bg.createRadialGradient(128, 96, 10, 128, 128, 190);
    grad.addColorStop(0, '#3a2412'); grad.addColorStop(0.5, '#140c07'); grad.addColorStop(1, '#050302');
    bg.fillStyle = grad; bg.fillRect(0, 0, 256, 256);
    sc.background = tex(bc);
    sc.fog = new THREE.Fog(0x0a0604, 260, 520);
    this.mat = {
      wood: new THREE.MeshStandardMaterial({ map: woodTex('#9a6434'), roughness: 0.55, metalness: 0.05 }),
      dark: new THREE.MeshStandardMaterial({ map: woodTex('#6b4122', 11), roughness: 0.6 }),
      chrome: new THREE.MeshStandardMaterial({ color: 0xe8ecf2, metalness: 1, roughness: 0.16 }),
      rubber: new THREE.MeshStandardMaterial({ color: 0x141414, roughness: 0.8 }),
      black: new THREE.MeshStandardMaterial({ color: 0x0a0a0a, roughness: 1 }),
    };
    const box = (w, h, d, mat, x, y, z, cast = true) => {
      const m = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), mat);
      m.position.set(x, y, z); m.castShadow = cast; m.receiveShadow = true; sc.add(m); return m;
    };
    // Sol du bistrot et caisson de la table.
    const floor = new THREE.Mesh(new THREE.PlaneGeometry(900, 900), new THREE.MeshStandardMaterial({ color: 0x1a120c, roughness: 0.92 }));
    floor.rotation.x = -Math.PI / 2; floor.position.y = -95; floor.receiveShadow = true; sc.add(floor);
    this.felt();
    box(132, 30, 80, this.mat.dark, 0, -15.2, 0);
    for (const [x, z] of [[-58, -30], [58, -30], [-58, 30], [58, 30]]) box(7, 66, 7, this.mat.dark, x, -62, z);
    // Bandes : la bande avant (côté joueur) porte la gravure.
    const [pc, pg] = canvas(2048, 160);
    pg.fillStyle = '#a36c3a'; pg.fillRect(0, 0, 2048, 160);
    pg.font = `800 74px ${FONT}`; pg.textAlign = 'center'; pg.textBaseline = 'middle';
    if ('letterSpacing' in pg) pg.letterSpacing = '16px';
    pg.fillStyle = 'rgba(50,25,8,.72)'; pg.fillText(this.cfg.title.toUpperCase(), 1024, 86);
    const front = new THREE.MeshStandardMaterial({ map: tex(pc), roughness: 0.55 });
    const s1 = new THREE.Mesh(new THREE.BoxGeometry(132, 14, 4), [this.mat.wood, this.mat.wood, this.mat.wood, this.mat.wood, front, this.mat.wood]);
    s1.position.set(0, 5, LZ + 2); s1.castShadow = s1.receiveShadow = true; sc.add(s1);
    box(132, 14, 4, this.mat.wood, 0, 5, -LZ - 2);
    // Fonds de terrain avec la bouche des buts, et les cages (filet) derrière.
    this.nets = [];
    const [nc, ng] = canvas(256, 128);
    ng.strokeStyle = 'rgba(240,240,240,.85)'; ng.lineWidth = 2;
    for (let i = 0; i <= 256; i += 16) { ng.beginPath(); ng.moveTo(i, 0); ng.lineTo(i, 128); ng.stroke(); }
    for (let i = 0; i <= 128; i += 16) { ng.beginPath(); ng.moveTo(0, i); ng.lineTo(256, i); ng.stroke(); }
    const netMat = new THREE.MeshStandardMaterial({ map: tex(nc), transparent: true, side: THREE.DoubleSide, roughness: 1, depthWrite: false });
    for (const sx of [-1, 1]) {
      const side = (LZ - GOAL);
      box(4, 14, side, this.mat.wood, sx * (LX + 2), 5, GOAL + side / 2);
      box(4, 14, side, this.mat.wood, sx * (LX + 2), 5, -GOAL - side / 2);
      box(4, 7, GOAL * 2, this.mat.wood, sx * (LX + 2), 8.5, 0);
      // Cage : fond noir, plancher, côtés.
      box(0.6, 7, GOAL * 2 + 1, this.mat.black, sx * (LX + DEPTH + 0.3), 2.5, 0, false);
      box(DEPTH + 1, 0.4, GOAL * 2 + 1, this.mat.black, sx * (LX + DEPTH / 2), -0.2, 0, false);
      box(DEPTH, 7, 0.6, this.mat.black, sx * (LX + DEPTH / 2 + 0.5), 2.5, GOAL + 0.3, false);
      box(DEPTH, 7, 0.6, this.mat.black, sx * (LX + DEPTH / 2 + 0.5), 2.5, -GOAL - 0.3, false);
      const net = new THREE.Mesh(new THREE.PlaneGeometry(GOAL * 2, 5.4, 12, 4), netMat);
      net.rotation.y = Math.PI / 2; net.position.set(sx * (LX + DEPTH - 0.3), 2.9, 0); sc.add(net);
      this.nets.push({ mesh: net, base: net.geometry.attributes.position.array.slice(), amp: 0, side: sx });
      // Poteaux chromés.
      for (const pz of [-GOAL, GOAL]) {
        const post = new THREE.Mesh(new THREE.CylinderGeometry(0.7, 0.7, 5, 16), this.mat.chrome);
        post.position.set(sx * LX, 2.5, pz); post.castShadow = true; sc.add(post);
      }
      const bar = new THREE.Mesh(new THREE.CylinderGeometry(0.5, 0.5, GOAL * 2, 16), this.mat.chrome);
      bar.rotation.x = Math.PI / 2; bar.position.set(sx * LX, 5, 0); sc.add(bar);
    }
    // Pans coupés dans les coins.
    for (const sx of [-1, 1]) for (const sz of [-1, 1]) {
      const m = new THREE.Mesh(new THREE.BoxGeometry(7.1, 6, 1.5), this.mat.wood);
      m.position.set(sx * (LX - 2.5), 3, sz * (LZ - 2.5)); m.rotation.y = sx * sz * Math.PI / 4; m.castShadow = m.receiveShadow = true; sc.add(m);
    }
    this.rodsBuild();
    // Balle de liège blanche (quelques marques pour voir qu'elle roule).
    const [bcv, bgx] = canvas(256, 128);
    bgx.fillStyle = '#f7f5ef'; bgx.fillRect(0, 0, 256, 128);
    bgx.fillStyle = 'rgba(160,150,130,.35)';
    for (let i = 0; i < 26; i++) { bgx.beginPath(); bgx.arc((i * 97) % 256, (i * 53) % 128, 3 + (i % 3), 0, Math.PI * 2); bgx.fill(); }
    bgx.fillStyle = 'rgba(14,31,77,.55)'; bgx.fillRect(0, 60, 256, 8);
    this.ball = new THREE.Mesh(new THREE.SphereGeometry(R, 32, 24), new THREE.MeshStandardMaterial({ map: tex(bcv), roughness: 0.38 }));
    this.ball.castShadow = true; sc.add(this.ball);
    this.ballQ = new THREE.Quaternion();
    this.lastBall = null;
    // Traînée de la balle (tirs forts).
    this.trail = Array.from({ length: 5 }, (_, i) => {
      const m = new THREE.Mesh(new THREE.SphereGeometry(R * 0.96, 16, 12), new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0, depthWrite: false }));
      sc.add(m); return { m, i };
    });
    this.trailPts = [];
    // Lumières : lampe de bistrot au-dessus, contre-jour bleuté.
    sc.add(new THREE.HemisphereLight(0xc9d6ff, 0x1a1208, 0.32));
    this.spot = new THREE.SpotLight(0xfff1d6, 5200, 0, 0.66, 0.55, 1.6);
    this.spot.position.set(0, 130, 12); this.spot.target.position.set(0, 0, 0);
    this.spot.castShadow = true; this.spot.shadow.mapSize.set(2048, 2048); this.spot.shadow.bias = -0.0004;
    sc.add(this.spot, this.spot.target);
    const rim = new THREE.DirectionalLight(0x6f8cff, 0.55); rim.position.set(-80, 40, -120); sc.add(rim);
    const shadeMat = new THREE.MeshStandardMaterial({ color: new THREE.Color(this.cfg.glow[0]), side: THREE.DoubleSide, roughness: 0.5, emissive: 0x332200 });
    // Lampe (cachée quand la caméra passe au-dessus : vue de dessus).
    this.lamp = new THREE.Group();
    const shade = new THREE.Mesh(new THREE.ConeGeometry(16, 10, 40, 1, true), shadeMat);
    shade.position.set(0, 134, 12);
    const bulb = new THREE.Mesh(new THREE.SphereGeometry(3.2, 20, 14), new THREE.MeshBasicMaterial({ color: 0xfff3d0 }));
    bulb.position.set(0, 129.5, 12);
    const cord = new THREE.Mesh(new THREE.CylinderGeometry(0.3, 0.3, 80, 6), this.mat.rubber);
    cord.position.set(0, 179, 12);
    this.lamp.add(shade, bulb, cord);
    sc.add(this.lamp);
  }

  /** Tapis : bandes de pelouse, lignes, logo du club au rond central. */
  felt() {
    const big = Math.min(screen.width || 0, screen.height || 0) * Math.min(window.devicePixelRatio || 1, 2) >= 700;
    const k = big ? 24 : 16, w = 120 * k, h = 68 * k, [c, g] = canvas(w, h);
    for (let i = 0; i < 12; i++) { g.fillStyle = i % 2 ? '#1f7a30' : '#258838'; g.fillRect(i * w / 12, 0, w / 12 + 1, h); }
    const grd = g.createRadialGradient(w / 2, h / 2, 50, w / 2, h / 2, w * 0.6);
    grd.addColorStop(0, 'rgba(255,255,255,.06)'); grd.addColorStop(1, 'rgba(0,0,0,.25)');
    g.fillStyle = grd; g.fillRect(0, 0, w, h);
    const logo = this.cfg.logo;
    if (logo) { g.globalAlpha = 0.22; const lw = 15 * k, lh = lw * (logo.height / logo.width || 1.13); g.drawImage(logo, w / 2 - lw / 2, h / 2 - lh / 2, lw, lh); g.globalAlpha = 1; }
    g.strokeStyle = 'rgba(255,255,255,.9)'; g.lineWidth = 0.45 * k;
    const rect = (x, y, ww, hh) => g.strokeRect(x * k, y * k, ww * k, hh * k);
    rect(1.5, 1.5, 117, 65);
    g.beginPath(); g.moveTo(60 * k, 1.5 * k); g.lineTo(60 * k, 66.5 * k); g.stroke();
    g.beginPath(); g.arc(60 * k, 34 * k, 9 * k, 0, Math.PI * 2); g.stroke();
    g.fillStyle = '#fff'; g.beginPath(); g.arc(60 * k, 34 * k, 0.8 * k, 0, Math.PI * 2); g.fill();
    rect(1.5, 18, 13, 32); rect(105.5, 18, 13, 32); rect(1.5, 25, 5, 18); rect(113.5, 25, 5, 18);
    g.beginPath(); g.arc(14.5 * k, 34 * k, 6 * k, -1.1, 1.1); g.stroke();
    g.beginPath(); g.arc(105.5 * k, 34 * k, 6 * k, Math.PI - 1.1, Math.PI + 1.1); g.stroke();
    const m = new THREE.Mesh(new THREE.PlaneGeometry(120, 68), new THREE.MeshStandardMaterial({ map: tex(c), roughness: 0.92 }));
    m.rotation.x = -Math.PI / 2; m.receiveShadow = true; this.scene.add(m);
  }

  /** Une figurine (tournée vers +x), en un seul maillage coloré : tête, maillot, short, chaussettes, chaussures. */
  figureGeo(kit) {
    const parts = [];
    const col = c => new THREE.Color(c);
    const add = (geo, color, x, y, z) => {
      let g = geo.index ? geo.toNonIndexed() : geo;
      g.translate(x, y, z);
      const n = g.attributes.position.count, arr = new Float32Array(n * 3), cc = col(color);
      for (let i = 0; i < n; i++) { arr[i * 3] = cc.r; arr[i * 3 + 1] = cc.g; arr[i * 3 + 2] = cc.b; }
      g.setAttribute('color', new THREE.BufferAttribute(arr, 3));
      g.deleteAttribute('uv');
      parts.push(g);
    };
    add(new THREE.SphereGeometry(1.75, 24, 18), '#e2a97e', 0, 5.6, 0);
    add(new THREE.SphereGeometry(1.82, 24, 10, 0, Math.PI * 2, 0, Math.PI * 0.42), '#3a2414', 0, 5.75, 0);
    add(new RoundedBoxGeometry(3.4, 6.2, 4.6, 3, 0.8), kit.shirt, 0, 0.6, 0);
    add(new RoundedBoxGeometry(2.6, 2.2, 1.5, 2, 0.5), kit.sleeves, 0, 2.6, 2.9);
    add(new RoundedBoxGeometry(2.6, 2.2, 1.5, 2, 0.5), kit.sleeves, 0, 2.6, -2.9);
    add(new RoundedBoxGeometry(3.2, 2.2, 4.2, 2, 0.5), kit.shorts, 0, -3.4, 0);
    add(new RoundedBoxGeometry(1.5, 3.4, 1.5, 2, 0.4), kit.socks, 0, -5.6, 1.1);
    add(new RoundedBoxGeometry(1.5, 3.4, 1.5, 2, 0.4), kit.socks, 0, -5.6, -1.1);
    add(new RoundedBoxGeometry(3.6, 1.3, 4.0, 2, 0.4), '#151515', 0.5, -7.25, 0);
    const g = mergeGeometries(parts);
    g.scale(1.12, 1, 1.1);
    return g;
  }

  /** Barres, poignées, butées et figurines (reconstruites quand les maillots changent). */
  rodsBuild() {
    if (this.rodGroup) this.scene.remove(this.rodGroup);
    this.rodGroup = new THREE.Group();
    this.scene.add(this.rodGroup);
    const geos = this.cfg.kits.map(k => this.figureGeo(k));
    const figMat = new THREE.MeshStandardMaterial({ vertexColors: true, roughness: 0.42, metalness: 0 });
    const glowCol = this.cfg.glow.map(c => new THREE.Color(c));
    this.rodObjs = this.rods.map(r => {
      const g = new THREE.Group();
      g.position.set(r.x, ROD_Y, 0);
      const hz = r.team === 0 ? 1 : -1;
      // Barre : elle sort des deux bandes même en butée ; poignée côté joueur.
      const near = LZ + 11 + r.travel, far = LZ + 5 + r.travel, len = near + 5.5 + far, mid = hz * (near + 5.5 - far) / 2;
      const rod = new THREE.Mesh(new THREE.CylinderGeometry(0.75, 0.75, len, 20), this.mat.chrome);
      rod.rotation.x = Math.PI / 2; rod.position.z = mid; rod.castShadow = true; g.add(rod);
      const glowMat = new THREE.MeshBasicMaterial({ color: glowCol[r.team].clone().multiplyScalar(2.6), transparent: true, opacity: 0, depthWrite: false });
      const glow = new THREE.Mesh(new THREE.CylinderGeometry(1.3, 1.3, LZ * 2, 20), glowMat);
      glow.rotation.x = Math.PI / 2; g.add(glow);
      const handleMat = new THREE.MeshStandardMaterial({ color: 0x141414, roughness: 0.75, emissive: glowCol[r.team], emissiveIntensity: 0 });
      const handle = new THREE.Mesh(new THREE.CylinderGeometry(1.9, 1.9, 11, 20), handleMat);
      handle.rotation.x = Math.PI / 2; handle.position.z = hz * near; handle.castShadow = true; g.add(handle);
      for (const sz of [-1, 1]) {
        const stop = new THREE.Mesh(new THREE.CylinderGeometry(1.4, 1.4, 1.6, 16), this.mat.rubber);
        stop.rotation.x = Math.PI / 2; stop.position.z = sz * (r.offs[r.n - 1] + 3.2); g.add(stop);
      }
      const figs = r.offs.map(o => {
        const holder = new THREE.Group();
        holder.position.z = o;
        if (r.team === 1) holder.rotation.y = Math.PI;
        const pivot = new THREE.Group();
        const mesh = new THREE.Mesh(geos[r.team], figMat);
        mesh.castShadow = true; mesh.receiveShadow = true;
        pivot.add(mesh); holder.add(pivot); g.add(holder);
        return { holder, pivot };
      });
      this.rodGroup.add(g);
      return { g, glow, glowMat, handleMat, figs };
    });
    this.backs = [];
  }

  /** Maillots des deux équipes. */
  setKits(kits) {
    this.cfg.kits = kits;
    this.rodsBuild();
  }

  /**
   * Noms au dos des figurines (Rejoue l'histoire) : names[rod][k] = { name, num } pour l'équipe 0,
   * ou null pour les retirer.
   */
  setNames(names) {
    for (const b of this.backs) b.parent.remove(b);
    this.backs = [];
    if (!names) return;
    const kit = this.cfg.kits[0];
    this.rods.forEach((r, i) => {
      if (r.team !== 0 || !names[i]) return;
      r.offs.forEach((o, k) => {
        const n = names[i][k];
        if (!n) return;
        const [c, g] = canvas(256, 256);
        g.fillStyle = kit.shirt; g.fillRect(0, 0, 256, 256);
        // Encre sombre sur maillot clair, blanche sur maillot sombre.
        g.fillStyle = luminance(kit.shirt) > 0.5 ? '#0b1533' : '#ffffff';
        g.textAlign = 'center'; g.textBaseline = 'middle';
        let size = 84; g.font = `900 ${size}px ${FONT}`;
        while (g.measureText(n.name).width > 244 && size > 30) { size -= 2; g.font = `900 ${size}px ${FONT}`; }
        // Numéro seulement s'il figure sur la feuille de match.
        if (n.num != null) { g.fillText(n.name, 128, 56); g.font = `900 140px ${FONT}`; g.fillText(String(n.num), 128, 170); }
        else g.fillText(n.name, 128, 118);
        const plane = new THREE.Mesh(new THREE.PlaneGeometry(4.6, 4.6), new THREE.MeshStandardMaterial({ map: tex(c), roughness: 0.45, transparent: true }));
        plane.rotation.y = -Math.PI / 2; plane.position.set(-1.92 * 1.12, 0.9, 0);
        plane.scale.set(1.1, 1, 1);
        this.rodObjs[i].figs[k].pivot.add(plane);
        this.backs.push(plane);
      });
    });
  }

  // ------------------------------------------------------------------ caméra

  resize(w, h) {
    this.w = w; this.h = h;
    this.renderer.setSize(w, h, false);
    this.camera.aspect = w / h;
    this.camera.updateProjectionMatrix();
    if (this.composer) { this.composer.setSize(w, h); this.bloom.resolution.set(w, h); }
    else this.makeComposer();
  }

  makeComposer() {
    const rt = new THREE.WebGLRenderTarget(1, 1, { type: THREE.HalfFloatType, samples: 4 });
    this.composer = new EffectComposer(this.renderer, rt);
    this.composer.setSize(this.w || 800, this.h || 450);
    this.composer.addPass(new RenderPass(this.scene, this.camera));
    this.bloom = new UnrealBloomPass(new THREE.Vector2(this.w || 800, this.h || 450), 0.2, 0.45, 0.96);
    this.composer.addPass(this.bloom);
    this.composer.addPass(new OutputPass());
  }

  setQuality(q) {
    this.quality = q;
    this.renderer.shadowMap.enabled = q >= 1;
    this.spot.castShadow = q >= 1;
    if (q === 0) { this.renderer.setPixelRatio(1); this.resize(this.w, this.h); }
    this.scene.traverse(o => { if (o.material) [].concat(o.material).forEach(m => { m.needsUpdate = true; }); });
  }

  setView(v) { this.view = v; }

  /** Position de caméra d'une vue, reculée juste assez pour voir toute la table. */
  viewPose(name) {
    const portrait = this.h > this.w * 1.05;
    const v = VIEWS[name][portrait ? 'port' : 'land'];
    const at = new THREE.Vector3(...v.at), pos = new THREE.Vector3(...v.pos);
    const up = new THREE.Vector3(...(v.up || [0, 1, 0]));
    // Recul : on cherche le facteur qui fait tenir les coins de la table dans l'écran.
    const cam = this.camera.clone();
    cam.up.copy(up);
    const corners = [];
    for (const x of [-LX - 4, LX + 4]) for (const z of [-LZ - 4, LZ + 4]) for (const y of [0, 12]) corners.push(new THREE.Vector3(x, y, z));
    const fits = k => {
      cam.position.copy(at).addScaledVector(pos.clone().sub(at), k);
      cam.lookAt(at); cam.updateMatrixWorld(); cam.updateProjectionMatrix();
      return corners.every(c => { const p = c.clone().project(cam); return Math.abs(p.x) < 0.94 && (v.fit === 'x' || Math.abs(p.y) < 0.9) && p.z < 1; });
    };
    let lo = 0.3, hi = 4;
    for (let i = 0; i < 18; i++) { const m = (lo + hi) / 2; if (fits(m)) hi = m; else lo = m; }
    return { pos: at.clone().addScaledVector(pos.clone().sub(at), hi), at, up };
  }

  // ------------------------------------------------------------------ image par image

  /**
   * s : { rods: [{ z, ang }], ball: { x, z, goal, live }, active: [barre équipe 0, barre équipe 1],
   *   mode: 'menu' | 'play' | 'replay', replay: { side, t } }
   */
  frame(s, dt, time) {
    const cam = this.camera;
    // Barres et figurines.
    this.rods.forEach((r, i) => {
      const o = this.rodObjs[i], st = s.rods[i];
      o.g.position.z = st.z;
      // Angle positif : le pied part vers l'avant (le support de l'équipe 1 est retourné).
      for (const f of o.figs) f.pivot.rotation.z = st.ang;
      const on = s.active && s.active[r.team] === i;
      const a = on ? 0.5 + 0.12 * Math.sin(time * 6) : 0;
      o.glowMat.opacity += (a - o.glowMat.opacity) * Math.min(1, dt * 12);
      o.handleMat.emissiveIntensity = o.glowMat.opacity * 1.8;
    });
    // Balle : roule selon son déplacement.
    const b = s.ball;
    this.ball.visible = !!b.live || !!b.goal;
    const bp = new THREE.Vector3(b.x, R, b.z);
    if (this.lastBall) {
      const dx = bp.x - this.lastBall.x, dz = bp.z - this.lastBall.z, d = Math.hypot(dx, dz);
      if (d > 1e-4 && d < 20) this.ballQ.premultiply(new THREE.Quaternion().setFromAxisAngle(new THREE.Vector3(dz / d, 0, -dx / d), d / R));
      // Traînée sur les tirs forts.
      const v = dt > 0 ? d / dt : 0;
      this.trailPts.unshift({ p: bp.clone(), v });
      this.trailPts.length = Math.min(this.trailPts.length, 12);
    }
    this.lastBall = bp.clone();
    this.ball.position.copy(bp);
    this.ball.quaternion.copy(this.ballQ);
    this.trail.forEach(({ m, i }) => {
      const tp = this.trailPts[(i + 1) * 2];
      const strong = tp && tp.v > 130 && this.ball.visible;
      m.visible = !!strong;
      if (strong) { m.position.copy(tp.p); m.material.opacity = (0.3 - i * 0.05) * Math.min(1, (tp.v - 130) / 60); }
    });
    // Filets qui tremblent après un but.
    for (const n of this.nets) {
      if (n.amp < 0.01) continue;
      n.amp *= Math.exp(-dt * 2.4);
      const pos = n.mesh.geometry.attributes.position;
      for (let i = 0; i < pos.count; i++) {
        const x = n.base[i * 3], y = n.base[i * 3 + 1];
        const edge = (1 - Math.abs(x) / GOAL) * (1 - Math.abs(y) / 2.7);
        pos.array[i * 3 + 2] = n.base[i * 3 + 2] + Math.sin(time * 26 + x * 0.6) * n.amp * edge;
      }
      pos.needsUpdate = true;
    }
    // Caméra : vue choisie, ralenti au ras de la table, ou tour lent de la table (menus).
    let want;
    if (s.mode === 'replay' && s.replay) {
      // De loin, en longue focale, par-dessus la bande près du but : la bouche du but, le gardien,
      // la balle qui arrive, le filet qui tremble, sans figurine géante au premier plan.
      const sx = s.replay.side;
      want = { pos: new THREE.Vector3(sx * 36, 50, 70), at: new THREE.Vector3(sx * 56 + (b.x - sx * 56) * 0.25, 1, 2 + b.z * 0.25), up: new THREE.Vector3(0, 1, 0) };
      cam.fov = 24;
    } else if (s.mode === 'menu') {
      const a = time * 0.07;
      want = { pos: new THREE.Vector3(Math.sin(a) * 150, 78, Math.cos(a) * 150), at: new THREE.Vector3(0, -6, 0), up: new THREE.Vector3(0, 1, 0) };
      cam.fov = 38;
    } else {
      if (!this.pose || this.poseKey !== this.view + this.w + 'x' + this.h) { this.pose = this.viewPose(this.view); this.poseKey = this.view + this.w + 'x' + this.h; }
      want = this.pose;
      cam.fov = 38;
    }
    const k = s.mode === 'replay' && this.lastMode !== 'replay' ? 1 : Math.min(1, dt * (s.mode === 'menu' ? 1.5 : 5));
    this.camPos.lerp(want.pos, k);
    this.camAt.lerp(want.at, k);
    cam.up.lerp(want.up, Math.min(1, dt * 6)).normalize();
    cam.position.copy(this.camPos);
    if (this.shake > 0) { this.shake = Math.max(0, this.shake - dt * 2.2); cam.position.x += (Math.random() - 0.5) * this.shake * 2.2; cam.position.y += (Math.random() - 0.5) * this.shake * 2.2; }
    cam.lookAt(this.camAt);
    cam.updateProjectionMatrix();
    this.lamp.visible = cam.position.y < 120;
    this.lastMode = s.mode;
  }

  fx(name, data) {
    if (name === 'goal') {
      const n = this.nets.find(x => x.side === (data.team === 0 ? 1 : -1));
      if (n) n.amp = 1.6;
      this.shake = 0.9;
    } else if (name === 'kick' && data && data.dv > 150) {
      this.shake = Math.max(this.shake, 0.18);
    } else if (name === 'poteau') {
      this.shake = Math.max(this.shake, 0.25);
    }
  }

  /** Remet la balle à zéro pour la rotation (nouvel engagement, début du ralenti). */
  resetBall() { this.lastBall = null; this.trailPts = []; }

  render() {
    if (this.quality >= 2 && this.composer) this.composer.render(); else this.renderer.render(this.scene, this.camera);
  }

  // ------------------------------------------------------------------ commandes

  /**
   * Sur l'écran, sens et échelle de l'axe des barres (z) à l'endroit de la barre i : glisser le
   * doigt dans ce sens déplace la figurine sous le doigt. { dx, dy } unitaire, ppu : pixels par cm.
   */
  screenAxis(i) {
    const r = this.rods[i], cam = this.camera;
    const a = new THREE.Vector3(r.x, 2, -10).project(cam), b = new THREE.Vector3(r.x, 2, 10).project(cam);
    const ax = (b.x - a.x) * this.w / 2, ay = -(b.y - a.y) * this.h / 2;
    const L = Math.hypot(ax, ay) || 1;
    return { dx: ax / L, dy: ay / L, ppu: L / 20 };
  }

  /** Point du tapis sous la souris (coordonnées dans le canvas), ou null. */
  pick(px, py) {
    const ray = new THREE.Raycaster();
    ray.setFromCamera(new THREE.Vector2(px / this.w * 2 - 1, -(py / this.h) * 2 + 1), this.camera);
    const hit = new THREE.Vector3();
    return ray.ray.intersectPlane(new THREE.Plane(new THREE.Vector3(0, 1, 0), -2), hit) ? { x: hit.x, z: hit.z } : null;
  }
}

/** Clarté d'une couleur (0 sombre → 1 claire). */
export function luminance(hex) {
  const c = new THREE.Color(hex);
  return 0.2126 * c.r + 0.7152 * c.g + 0.0722 * c.b;
}
