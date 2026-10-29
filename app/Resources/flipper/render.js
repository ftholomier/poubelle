// Rendu 3D du flipper « au stade » (Three.js), d'après le schéma de principe validé : plateau peint
// comme une pelouse (rond central, surface de réparation, hexagones des joueurs adverses), murs et
// guides lumineux, bumpers coiffés du logo du musée, slingshots et leurs écrans HOME / AWAY, kicker
// latéral, poches LOCK, trou TRICK, trappe, couloirs UNE-DEUX, rampes PASSE, DRIBBLE, CENTRE et BALLON
// LONG (ruban à murets puis rails en fil), mini-terrain surélevé (but et filets, gardien, trois
// rangées de joueurs adverses mobiles, trous d'éjection, mini-flippers), flippers TACKLE, couloir de
// lancement et ses multiplicateurs, tablier (chrono, jauge COMBO, maillots) ; autour, le stade
// (stadium.js). Le plan 2D de table.js est posé à plat (x → X, y → Z), la hauteur en Y.
import * as THREE from 'three';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';
import { EffectComposer } from 'three/examples/jsm/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/examples/jsm/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/examples/jsm/postprocessing/UnrealBloomPass.js';
import { OutputPass } from 'three/examples/jsm/postprocessing/OutputPass.js';
import { W, H, R, HM } from './table.js';
import { Stadium } from './stadium.js';

const S = 50 / W;
const X = x => (x - W / 2) * S;
const Z = y => (y - H / 2) * S;
const P = (x, y, h = 0) => new THREE.Vector3(X(x), h, Z(y));
const FONT = '"Big Shoulders Display", "Arial Narrow", sans-serif';
/** Hauteur du mini-terrain (unités 3D). */
const MH = HM * S;
/** Haut du tablier (sous les flippers) et largeur de la zone de jeu (sans le couloir de lancement). */
const APRON_Y = 700, PF_W = 366;

function canvas(w, h) { const c = document.createElement('canvas'); c.width = w; c.height = h; return [c, c.getContext('2d')]; }
// Filtrage des textures vues de biais : le maximum de la carte graphique (lu au démarrage).
let ANISO = 8;
function tex(c) { const t = new THREE.CanvasTexture(c); t.colorSpace = THREE.SRGBColorSpace; t.anisotropy = ANISO; return t; }
function txt(g, s, x, y, size, color, o = {}) {
  g.save(); g.translate(x, y); if (o.rot) g.rotate(o.rot);
  g.font = `${o.weight || 800} ${size}px ${FONT}`; if ('letterSpacing' in g) g.letterSpacing = (o.ls || 0) + 'px';
  g.textAlign = o.align || 'center'; g.textBaseline = 'middle'; g.fillStyle = color;
  if (o.glow) { g.shadowColor = o.glow; g.shadowBlur = o.blur || 12; }
  if (o.max) { const w = g.measureText(s).width; if (w > o.max) g.scale(o.max / w, 1); }
  g.fillText(s, 0, 0); g.restore();
}
function poly(g, pts) { g.beginPath(); pts.forEach(([x, y], i) => g[i ? 'lineTo' : 'moveTo'](x, y)); g.closePath(); }
function hexPath(g, x, y, r) { poly(g, Array.from({ length: 6 }, (_, i) => [x + Math.cos(i * Math.PI / 3) * r, y + Math.sin(i * Math.PI / 3) * r])); }
function pentaPath(g, x, y, r) { poly(g, Array.from({ length: 5 }, (_, i) => [x + Math.cos(-Math.PI / 2 + i * 2 * Math.PI / 5) * r, y + Math.sin(-Math.PI / 2 + i * 2 * Math.PI / 5) * r])); }
function logoAt(g, img, x, y, h) {
  if (!img) return;
  const w = h * (img.width / img.height || 0.88);
  g.drawImage(img, x - w / 2, y - h / 2, w, h);
}
/** Afficheur à LED : chiffres lumineux sur fond noir, avec les segments éteints en filigrane. */
function led(g, s, x, y, size, color) {
  txt(g, s.replace(/[0-9]/g, '8'), x, y, size, 'rgba(255,255,255,.07)', { weight: 900, ls: 4 });
  txt(g, s, x, y, size, color, { weight: 900, ls: 4, glow: color, blur: 14 });
}
/** Petit maillot dessiné (hexagones, tablier) aux couleurs d'une équipe. */
function jersey(g, x, y, s, shirt, sleeve, num, numColor) {
  g.save(); g.translate(x, y); g.scale(s, s);
  poly(g, [[-14, -16], [-22, -8], [-17, 0], [-13, -3], [-13, 20], [13, 20], [13, -3], [17, 0], [22, -8], [14, -16], [6, -16], [0, -11], [-6, -16]]);
  g.fillStyle = shirt; g.fill();
  g.fillStyle = sleeve; g.fillRect(-22, -9, 6, 5); g.fillRect(16, -9, 6, 5);
  if (num) txt(g, String(num), 0, 5, 18, numColor, { weight: 900 });
  g.restore();
}

export class Scene3D {
  /**
   * cfg : { colors: {navy, yellow, blue}, kit, opp: {name, ini, kit}, title, site, short, logo (Image),
   * labels: {passe, dribble, centre, ballonLong, bumpers, plunger, home, away, combo, balls, half,
   * lines: {att, mil, def}, modes: {jackpot, super, skill, extra, time}, trick, trap, attack, superJackpot, tackle, onetwo, mini} }
   */
  constructor(canvasEl, table, cfg) {
    this.t = table;
    this.cfg = cfg;
    this.lb = cfg.labels || {};
    this.el = canvasEl;
    this.col = { navy: new THREE.Color(cfg.colors.navy), yellow: new THREE.Color(cfg.colors.yellow), blue: new THREE.Color(cfg.colors.blue) };
    this.Y = cfg.colors.yellow;
    this.N = cfg.colors.navy;
    this.B = cfg.colors.blue;
    this.shake = 0;
    this.quality = 2;
    this.renderer = new THREE.WebGLRenderer({ canvas: canvasEl, antialias: true, powerPreference: 'high-performance' });
    this.renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    ANISO = Math.min(16, this.renderer.capabilities.getMaxAnisotropy() || 8);
    this.renderer.shadowMap.enabled = true;
    this.renderer.shadowMap.type = THREE.PCFShadowMap;
    this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
    this.renderer.outputColorSpace = THREE.SRGBColorSpace;
    this.scene = new THREE.Scene();
    const pm = new THREE.PMREMGenerator(this.renderer);
    this.scene.environment = pm.fromScene(new RoomEnvironment(), 0.04).texture;
    this.scene.environmentIntensity = 0.45;
    this.camera = new THREE.PerspectiveCamera(39, 1, 1, 1200);
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
      blue: new THREE.MeshStandardMaterial({ color: this.col.blue, roughness: 0.35, emissive: this.col.blue, emissiveIntensity: 0.15 }),
      hole: new THREE.MeshBasicMaterial({ color: 0x01030a }),
    };
    this.playfield();
    this.lampsBuild();
    for (const w of t.walls) if (!w.block) w.wire ? this.wire(w.pts, w.h) : this.wall(w.pts, w.h, w.r * S * 2.2, w.neon);
    this.bumpersBuild();
    this.slingsBuild();
    this.kickerBuild();
    this.holesBuild();
    this.spinnerBuild();
    this.subwayBuild();
    this.flippersBuild();
    // Nom de chaque rampe, à l'endroit du plan : f = position le long de la partie pleine, flip = sens de lecture.
    const names = { passe: [this.lb.passe, 0.55], dribble: [this.lb.dribble, 0.72, true], centre: [this.lb.centre, 0.2], 'ballon-long': [this.lb.ballonLong, 0.22, true] };
    this.t.ramps.forEach(r => this.rampBuild(r, ...(names[r.name] || [''])));
    this.plungerBuild();
    this.miniBuild();
    this.apronBuild();
    // Caisse de la machine : flancs aux couleurs du club, liseré lumineux, pieds jusqu'au sol du stade.
    const ground = -9, side = new THREE.MeshStandardMaterial({ color: this.col.navy, roughness: 0.45, metalness: 0.2 });
    const zc = (Z(0) + Z(H)) / 2, len = H * S + 4;
    for (const sx of [-1, 1]) {
      const m = new THREE.Mesh(new THREE.BoxGeometry(3, 7, len), side);
      m.position.set(sx * (W * S / 2 + 1.6), 1.5, zc); m.castShadow = m.receiveShadow = true; sc.add(m);
      const strip = new THREE.Mesh(new THREE.BoxGeometry(0.35, 0.3, len), this.mat.neon);
      strip.position.set(sx * (W * S / 2 + 2.9), 5.05, zc); sc.add(strip);
    }
    const body = new THREE.Mesh(new THREE.BoxGeometry(W * S + 6, -ground - 2, len), new THREE.MeshStandardMaterial({ color: 0x08102c, roughness: 0.6 }));
    body.position.set(0, (ground - 2) / 2, zc); sc.add(body);
    for (const z of [Z(0) - 1.5, Z(H) + 1.5]) { const m = new THREE.Mesh(new THREE.BoxGeometry(W * S + 6, 7, 3), side); m.position.set(0, 1.5, z); sc.add(m); }
    // Le stade.
    this.stadium = new Stadium(sc, {
      colors: this.cfg.colors, kit: this.cfg.kit, logo: this.cfg.logo, title: this.cfg.title, site: this.cfg.site, short: this.cfg.short, opp: this.cfg.opp,
    }, { x1: -W * S / 2 - 3, x2: W * S / 2 + 3, z1: Z(0) - 3, z2: Z(H) + 3, ground });
    // Billes (jusqu'à 4 en multiball) et billes verrouillées dans les poches.
    const ballMat = new THREE.MeshStandardMaterial({ color: 0xffffff, metalness: 1, roughness: 0.06 });
    this.balls = Array.from({ length: 5 }, () => { const m = new THREE.Mesh(new THREE.SphereGeometry(R * S, 32, 24), ballMat); m.castShadow = true; m.visible = false; sc.add(m); return m; });
    this.lockBalls = t.sensors.filter(s => s.tag === 'lock').map(s => { const m = new THREE.Mesh(new THREE.SphereGeometry(R * S, 24, 16), ballMat); m.position.copy(P(s.x, s.y, R * S - 0.3)); m.visible = false; sc.add(m); return m; });
    // Lumières : projecteur principal (ombres), lumière froide des pylônes, reflets chauds.
    sc.add(new THREE.AmbientLight(0x8090c0, 0.16));
    sc.add(new THREE.HemisphereLight(0x8fa8ff, 0x1d4a22, 0.35));
    this.key = new THREE.SpotLight(0xfff4e0, 500, 0, 0.8, 0.6, 1.5);
    this.key.position.set(0, 82, 30); this.key.target.position.set(0, 0, -4);
    this.key.castShadow = true; this.key.shadow.mapSize.set(2048, 2048); this.key.shadow.bias = -0.0005;
    sc.add(this.key, this.key.target);
    const flood = new THREE.DirectionalLight(0xdfe8ff, 1.1); flood.position.set(-60, 90, -80); sc.add(flood);
    const flood2 = new THREE.DirectionalLight(0xdfe8ff, 0.7); flood2.position.set(70, 80, -60); sc.add(flood2);
    const warm = new THREE.PointLight(0xffb020, 40, 70, 1.6); warm.position.set(18, 14, 26); sc.add(warm);
  }

  /** Décor peint du plateau : pelouse rayée, lignes du terrain, bords sombres, dessins des inserts, textes. */
  playfield() {
    // Grand écran (ordinateur, tablette) : décor en plus haute définition, net jusqu'au bas du plateau.
    const big = Math.min(screen.width || 0, screen.height || 0) * Math.min(window.devicePixelRatio || 1, 2) >= 700;
    const k = big ? 4 : 2.5, [c, g] = canvas(W * k, H * k), t = this.t, d = t.deco, Yc = this.Y, lb = this.lb;
    g.scale(k, k);
    // Pelouse : bandes de tonte, plus claire au centre.
    for (let i = 0; i < 22; i++) { g.fillStyle = i % 2 ? '#2f8a3b' : '#3a9a45'; g.fillRect(0, i * 40, W, 40); }
    const vg = g.createRadialGradient(185, 520, 60, 185, 520, 560);
    vg.addColorStop(0, 'rgba(255,255,200,.10)'); vg.addColorStop(0.6, 'rgba(0,0,0,0)'); vg.addColorStop(1, 'rgba(0,10,30,.55)');
    g.fillStyle = vg; g.fillRect(0, 0, W, H);
    for (let i = 0; i < 16000; i++) { g.fillStyle = `rgba(${Math.random() < 0.5 ? '0,0,0' : '255,255,255'},${0.03 + Math.random() * 0.04})`; g.fillRect(Math.random() * W, Math.random() * H, 0.6, 1.4); }
    // Zones hors jeu (sous les rampes, autour du mini-terrain, couloir de lancement) : plus sombres.
    g.fillStyle = 'rgba(6,14,40,.55)';
    g.fillRect(0, 0, W, 240); g.fillRect(366, 240, 34, H);
    // Lignes blanches : ligne médiane et rond central, surface de réparation et son arc, au-dessus des flippers.
    g.strokeStyle = 'rgba(255,255,255,.75)'; g.lineWidth = 2;
    g.beginPath(); g.moveTo(10, d.circle.y); g.lineTo(358, d.circle.y); g.stroke();
    g.beginPath(); g.arc(d.circle.x, d.circle.y, d.circle.r, 0, Math.PI * 2); g.stroke();
    g.beginPath(); g.arc(d.circle.x, d.circle.y, 2.5, 0, Math.PI * 2); g.fillStyle = 'rgba(255,255,255,.8)'; g.fill();
    const bx = d.box;
    g.beginPath(); g.moveTo(bx.x1, H); g.lineTo(bx.x1, bx.y1); g.lineTo(bx.x2, bx.y1); g.lineTo(bx.x2, H); g.stroke();
    g.beginPath(); g.arc(d.circle.x, bx.y1, 26, Math.PI, 0); g.stroke();
    // Logo du musée en grand, discret, dans le rond central.
    if (this.cfg.logo) { g.globalAlpha = 0.2; logoAt(g, this.cfg.logo, d.circle.x, d.circle.y, 70); g.globalAlpha = 1; }
    // Fonds sombres des inserts (les lampes s'allument par-dessus).
    const socket = (draw, stroke = Yc) => { draw(); g.fillStyle = 'rgba(6,12,34,.88)'; g.fill(); g.strokeStyle = stroke; g.lineWidth = 1.3; g.stroke(); };
    for (const [x, y] of d.hexes) socket(() => hexPath(g, x, y, 18.5));
    socket(() => hexPath(g, d.goalie[0], d.goalie[1], 19));
    for (const [x, y] of d.smallHexes) socket(() => hexPath(g, x, y, 9.5), 'rgba(255,255,255,.55)');
    for (const [x, y] of d.pentagons) socket(() => pentaPath(g, x, y, 11), 'rgba(255,255,255,.55)');
    for (const [x, y] of d.dots) socket(() => { g.beginPath(); g.arc(x, y, 7.5, 0, Math.PI * 2); }, 'rgba(255,255,255,.55)');
    for (const [x, y] of d.modes) socket(() => { g.beginPath(); g.roundRect(x - 17, y - 5.5, 34, 11, 5.5); });
    // Plaque de la trappe gauche, avec sa fente.
    const pt = d.plate;
    g.fillStyle = '#d9dee6'; g.beginPath(); g.roundRect(pt.x1, pt.y1, pt.x2 - pt.x1, pt.y2 - pt.y1, 5); g.fill();
    g.fillStyle = '#05070c'; g.beginPath(); g.roundRect(30, 476, 8, 36, 4); g.fill();
    // Textes peints.
    const paint = (s, x, y, size, rot = 0, color = '#FFF3C2', ls = 1.5) => txt(g, String(s || '').toUpperCase(), x, y, size, color, { rot, ls, weight: 900 });
    paint(lb.bumpers, 88, 567, 7, 0, 'rgba(255,243,194,.95)', 2.5);
    paint(lb.trick, 40, 360, 6.5, -Math.PI / 2);
    paint(lb.trap, 46, 523, 6);
    paint(lb.onetwo, 202, 300, 6, -Math.PI / 2);
    paint(lb.onetwo, 340, 392, 6, -1.2);
    paint(lb.attack, 68, 600, 5.5, -Math.PI / 2);
    paint(lb.attack, 302, 600, 5.5, Math.PI / 2);
    paint(lb.tackle, 28, 712, 6.5, 0.6);
    paint(lb.tackle, 341, 712, 6.5, -0.6);
    // Couloir de lancement : fond sombre où s'allument les multiplicateurs.
    g.fillStyle = 'rgba(4,8,24,.6)'; g.fillRect(369, 480, 24, 330);
    const tx = tex(c);
    const m = new THREE.Mesh(new THREE.PlaneGeometry(W * S, H * S), new THREE.MeshStandardMaterial({ map: tx, roughness: 0.45, metalness: 0.05 }));
    m.rotation.x = -Math.PI / 2; m.receiveShadow = true; this.scene.add(m);
  }

  /** Une lampe (pastille, hexagone, pentagone ou pilule) du plateau : texte peint, allumée par son émission. */
  lamp(x, y, r, label, size = 11, shape = 'round', h = 0.03, o = {}) {
    const w = shape === 'pill' ? 256 : 128, [c, g] = canvas(w, 128);
    g.fillStyle = o.color || this.Y;
    if (shape === 'hex') hexPath(g, 64, 64, 62); else if (shape === 'penta') pentaPath(g, 64, 66, 62);
    else if (shape === 'pill') { g.beginPath(); g.roundRect(4, 24, 248, 80, 40); } else { g.beginPath(); g.arc(64, 64, 60, 0, Math.PI * 2); }
    g.fill();
    if (label) txt(g, label, w / 2, 68, size * 5.2, o.ink || this.N, { weight: 900, max: w - 20 });
    const t = tex(c);
    const mat = new THREE.MeshStandardMaterial({ map: t, emissiveMap: t, emissive: 0xffffff, emissiveIntensity: 0, roughness: 0.35, transparent: true, color: 0x6a5a20 });
    const geo = shape === 'pill' ? new THREE.PlaneGeometry(r * 2 * S * 2, r * S * 1.25) : shape === 'hex' ? new THREE.CircleGeometry(r * S, 6) : shape === 'penta' ? new THREE.CircleGeometry(r * S, 5, Math.PI / 2) : new THREE.CircleGeometry(r * S, 32);
    if (shape === 'pill') geo.rotateX(-Math.PI / 2); else geo.rotateX(-Math.PI / 2);
    const m = new THREE.Mesh(geo, mat);
    m.position.copy(P(x, y, h));
    this.scene.add(m);
    return { m, mat, c, g, t, on: 0 };
  }

  lampsBuild() {
    const t = this.t, d = t.deco, lb = this.lb;
    // Les 10 joueurs adverses (hexagones) : maillot de l'adversaire, numéro ; éteints quand ils sont éliminés.
    this.L = {
      hexes: d.hexes.map(([x, y]) => this.lamp(x, y, 17.5, '', 8, 'hex')),
      save: this.lamp(d.goalie[0], d.goalie[1], 18, '', 8, 'hex'),
      small: [...d.smallHexes.map(([x, y]) => this.lamp(x, y, 9, '', 8, 'hex', 0.03, { color: '#ffffff' })),
        ...d.pentagons.map(([x, y]) => this.lamp(x, y, 10.5, '', 8, 'penta', 0.03, { color: '#ffffff' })),
        ...d.dots.map(([x, y]) => this.lamp(x, y, 7, '', 8, 'round', 0.03, { color: '#ffffff' }))],
      mode: this.lamp(d.modes[0][0], d.modes[0][1], 8.6, '', 8, 'pill'),
      modeLabel: this.lamp(d.modes[1][0], d.modes[1][1], 8.6, String(lb.trick || '').toUpperCase(), 8, 'pill'),
      extra: this.lamp(d.lamp[0], d.lamp[1], 8, '+1', 9),
      mults: d.mults.map(([s, y]) => this.lamp(381, y, 10, s, s.length > 3 ? 6 : 8.5, 'hex')),
      locks: [[97, 296], [246, 392], [343, 522]].map(([x, y], i) => this.lamp(x, y, 11.5, String(lb.lines ? lb.lines[['att', 'mil', 'def'][i]] : '').toUpperCase(), 3.6, 'pill')),
      lockArrows: d.chevrons.map(([x, y, a]) => this.chevron(x, y, a)),
      // Flèches des tirs : CENTRE, BALLON LONG, accès mini-terrain, UNE-DEUX central et droit.
      shots: { r2: [this.chevron(112, 392, -0.35), this.chevron(118, 408, -0.35)], r3: [this.chevron(272, 418, 0.4), this.chevron(266, 434, 0.4)], r4: [this.chevron(146, 302, 0), this.chevron(146, 318, 0)],
        onetwo0: [this.chevron(202, 352, 0)], onetwo1: [this.chevron(322, 420, -0.3)] },
      inlanes: t.sensors.filter(s => s.tag === 'inlane').map(s => this.lamp(s.x, s.y + 22, 8, '', 8)),
      supers: [[42, 609], [327, 609]].map(([x, y]) => this.lamp(x, y + 30, 7, 'SJ', 7)),
      tackles: [[28.7, 697], [340.5, 697]].map(([x, y]) => this.lamp(x, y, 8.6, '', 8, 'hex')),
    };
    this.paintHexes();
    this.paintMode('jackpot');
    // Gardien dessiné dans l'hexagone du bas (bille sauvée) : le logo du musée.
    if (this.cfg.logo) {
      const [c, g] = canvas(128, 128); logoAt(g, this.cfg.logo, 64, 64, 100);
      const m = new THREE.Mesh(new THREE.CircleGeometry(12 * S, 32), new THREE.MeshBasicMaterial({ map: tex(c), transparent: true }));
      m.rotation.x = -Math.PI / 2; m.position.copy(P(d.goalie[0], d.goalie[1], 0.05)); this.scene.add(m);
    }
  }

  /** Hexagones des joueurs adverses : maillot aux couleurs de l'adversaire, numéro du poste. */
  paintHexes() {
    const ok = this.cfg.opp && this.cfg.opp.kit ? this.cfg.opp.kit : { shirt: '#e9edf5', sleeves: '#e9edf5' };
    const light = new THREE.Color(ok.shirt).getHSL({}).l > 0.6;
    const nums = [9, 10, 6, 8, 7, 11, 2, 4, 5, 3];
    this.L.hexes.forEach((l, i) => {
      const g = l.g;
      g.clearRect(0, 0, 128, 128);
      hexPath(g, 64, 64, 62); g.fillStyle = '#f4f1e6'; g.fill();
      hexPath(g, 64, 64, 56); g.fillStyle = '#1c2a4a'; g.fill();
      jersey(g, 64, 66, 2.2, ok.shirt, ok.sleeves || ok.shirt, nums[i], light ? '#14161b' : '#ffffff');
      l.t.needsUpdate = true;
    });
  }

  /** Insert de mode : la récompense que donnera le prochain geste technique. */
  paintMode(mode) {
    if (mode === this.modeShown) return;
    this.modeShown = mode;
    const l = this.L.mode, g = l.g, lb = this.lb.modes || {};
    g.clearRect(0, 0, 256, 128);
    g.fillStyle = this.Y; g.beginPath(); g.roundRect(4, 24, 248, 80, 40); g.fill();
    txt(g, String(lb[mode] || mode).toUpperCase(), 128, 68, 40, this.N, { weight: 900, max: 230 });
    l.t.needsUpdate = true;
  }

  chevron(x, y, rot = 0) {
    const sh = new THREE.Shape(); sh.moveTo(-7, 6); sh.lineTo(0, -4); sh.lineTo(7, 6); sh.lineTo(7, 10); sh.lineTo(0, 0); sh.lineTo(-7, 10); sh.closePath();
    const geo = new THREE.ShapeGeometry(sh); geo.rotateZ(-rot); geo.scale(S, S, S); geo.rotateX(-Math.PI / 2);
    const mat = new THREE.MeshStandardMaterial({ color: 0x6a5a20, emissive: this.col.yellow, emissiveIntensity: 0, roughness: 0.4, side: THREE.DoubleSide });
    const m = new THREE.Mesh(geo, mat); m.position.copy(P(x, y, 0.04)); this.scene.add(m);
    return { m, mat, on: 0 };
  }

  wall(pts, h, th, glow, y0 = 0, mat = this.mat.wall) {
    for (let i = 0; i < pts.length - 1; i++) {
      const a = P(...pts[i]), b = P(...pts[i + 1]), len = a.distanceTo(b);
      if (len < 0.01) continue;
      const m = new THREE.Mesh(new THREE.BoxGeometry(th, h, len + th * 0.6), mat);
      m.position.set((a.x + b.x) / 2, y0 + h / 2, (a.z + b.z) / 2); m.rotation.y = Math.atan2(b.x - a.x, b.z - a.z);
      m.castShadow = m.receiveShadow = true; this.scene.add(m);
    }
    if (glow) {
      const curve = new THREE.CatmullRomCurve3(pts.map(p => P(p[0], p[1], y0 + h + 0.05)), false, 'catmullrom', 0);
      this.scene.add(new THREE.Mesh(new THREE.TubeGeometry(curve, pts.length * 4, 0.2, 8), this.mat.neon));
    }
  }

  /** Guide en fil métallique : deux fils chromés superposés, tenus par de petits poteaux. */
  wire(pts, h = 1.6, y0 = 0) {
    for (const hh of [0.55, h]) {
      const curve = new THREE.CatmullRomCurve3(pts.map(p => P(p[0], p[1], y0 + hh)), false, 'catmullrom', 0);
      this.scene.add(new THREE.Mesh(new THREE.TubeGeometry(curve, pts.length * 6, 0.14, 6), this.mat.chrome));
    }
    for (const p of [pts[0], pts[pts.length - 1]]) { const post = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.18, h, 8), this.mat.chrome); post.position.copy(P(p[0], p[1], y0 + h / 2)); this.scene.add(post); }
  }

  /** Bumpers [18] : piliers coiffés d'un grand disque au logo du musée, bande au nom du musée, jupe lumineuse. */
  bumpersBuild() {
    const [c, g] = canvas(256, 256);
    g.fillStyle = this.N; g.beginPath(); g.arc(128, 128, 128, 0, Math.PI * 2); g.fill();
    g.fillStyle = this.Y; g.beginPath(); g.arc(128, 128, 118, 0, Math.PI * 2); g.fill();
    g.fillStyle = this.B; g.beginPath(); g.arc(128, 128, 100, 0, Math.PI * 2); g.fill();
    if (this.cfg.logo) logoAt(g, this.cfg.logo, 128, 128, 170); else txt(g, this.lb.bumperCap || '', 128, 132, 64, this.Y, { weight: 900 });
    const cap = tex(c);
    const [c2, g2] = canvas(512, 64);
    g2.fillStyle = this.N; g2.fillRect(0, 0, 512, 64);
    for (let i = 0; i < 2; i++) txt(g2, String(this.cfg.site || '').toUpperCase(), 128 + i * 256, 34, 34, this.Y, { weight: 900, ls: 3, max: 240 });
    const band = tex(c2); band.wrapS = THREE.RepeatWrapping;
    this.bumpers = this.t.bumpers.map(b => {
      const p = P(b.x, b.y), r = b.r * S, R2 = r * 1.75;
      const base = new THREE.Mesh(new THREE.CylinderGeometry(r * 1.1, r * 1.1, 0.5, 40), this.mat.rubber); base.position.set(p.x, 0.25, p.z); this.scene.add(base);
      const ringMat = new THREE.MeshBasicMaterial({ color: this.col.yellow.clone().multiplyScalar(1.5) });
      const ring = new THREE.Mesh(new THREE.TorusGeometry(r * 1.0, 0.3, 12, 48), ringMat); ring.rotation.x = Math.PI / 2; ring.position.set(p.x, 1.0, p.z); this.scene.add(ring);
      const light = new THREE.PointLight(0xffcc33, 6, 12, 2); light.position.set(p.x, 4, p.z); this.scene.add(light);
      const post = new THREE.Mesh(new THREE.CylinderGeometry(r * 0.75, r * 0.75, 3.4, 32), new THREE.MeshStandardMaterial({ color: this.col.blue, emissive: this.col.blue, emissiveIntensity: 0.35, roughness: 0.3 }));
      post.position.set(p.x, 1.9, p.z); post.castShadow = true; this.scene.add(post);
      const drum = new THREE.Mesh(new THREE.CylinderGeometry(R2, R2, 1.3, 48, 1, true), new THREE.MeshStandardMaterial({ map: band, roughness: 0.35, emissiveMap: band, emissive: 0xffffff, emissiveIntensity: 0.3 }));
      drum.position.set(p.x, 4.15, p.z); drum.castShadow = true; this.scene.add(drum);
      const discMat = new THREE.MeshStandardMaterial({ map: cap, emissiveMap: cap, emissive: 0xffffff, emissiveIntensity: 0.3, roughness: 0.3 });
      const disc = new THREE.Mesh(new THREE.CircleGeometry(R2, 48), discMat); disc.rotation.x = -Math.PI / 2; disc.position.set(p.x, 4.81, p.z); this.scene.add(disc);
      const under = new THREE.Mesh(new THREE.CircleGeometry(R2, 48), this.mat.wall); under.rotation.x = Math.PI / 2; under.position.set(p.x, 3.5, p.z); this.scene.add(under);
      return { ringMat, discMat, light, flash: 0 };
    });
  }

  /** Slingshots HOME / AWAY [23] ; leurs écrans à LED sont posés dessus. */
  slingsBuild() {
    this.slings = this.t.slings.map(s => {
      // Triangle aux coins arrondis (comme le caoutchouc qui l'entoure).
      const pts = [s.a, s.b, s.c].map(([x, y]) => new THREE.Vector2(X(x), -Z(y))), sh = new THREE.Shape(), rr = 4 * S;
      pts.forEach((p, i) => {
        const prev = pts[(i + 2) % 3], next = pts[(i + 1) % 3];
        const p1 = p.clone().add(prev.clone().sub(p).setLength(rr)), p2 = p.clone().add(next.clone().sub(p).setLength(rr));
        if (i === 0) sh.moveTo(p1.x, p1.y); else sh.lineTo(p1.x, p1.y);
        sh.quadraticCurveTo(p.x, p.y, p2.x, p2.y);
      });
      sh.closePath();
      const geo = new THREE.ExtrudeGeometry(sh, { depth: 2.2, bevelEnabled: true, bevelSize: 0.25, bevelThickness: 0.2, bevelSegments: 2 });
      const mat = new THREE.MeshStandardMaterial({ color: this.col.yellow, emissive: this.col.yellow, emissiveIntensity: 0.15, roughness: 0.3 });
      const m = new THREE.Mesh(geo, [mat, this.mat.rubber]); m.rotation.x = -Math.PI / 2; m.castShadow = true; this.scene.add(m);
      return { mat, flash: 0 };
    });
    this.leds = this.t.slings.map(s => {
      const [c, g] = canvas(256, 176), tx = tex(c);
      const face = new THREE.MeshStandardMaterial({ map: tx, emissiveMap: tx, emissive: 0xffffff, emissiveIntensity: 0.9, roughness: 0.4 });
      const frame = new THREE.MeshStandardMaterial({ color: 0x14161b, roughness: 0.4 });
      const box = new THREE.Mesh(new THREE.BoxGeometry(2.5, 1.7, 0.6), [frame, frame, frame, frame, face, frame]);
      box.position.copy(P(s.screen[0], s.screen[1], 3.1)); box.rotation.x = -0.7; box.castShadow = true; this.scene.add(box);
      return { c, g, tx, side: s.side, val: null, flash: 0 };
    });
    this.drawLeds(0, 0, true);
  }

  drawLeds(home, away, force = false) {
    for (const l of this.leds) {
      const v = l.side === 'L' ? home : away, key = v + ':' + (l.flash > 0 ? Math.floor(l.flash * 8) % 2 : 0);
      if (key === l.val && !force) continue;
      l.val = key;
      const g = l.g, opp = this.cfg.opp || {};
      g.fillStyle = '#05070c'; g.fillRect(0, 0, 256, 176);
      const label = l.side === 'L' ? (this.cfg.short || this.lb.home || '') : (opp.ini || this.lb.away || '');
      g.fillStyle = l.side === 'L' ? this.Y : '#e9edf5'; g.fillRect(0, 128, 256, 48);
      txt(g, String(label).toUpperCase(), 128, 153, 40, l.side === 'L' ? this.N : '#0a0f1e', { weight: 900, ls: 3, max: 236 });
      const blink = l.flash > 0 && Math.floor(l.flash * 8) % 2;
      led(g, String(v).padStart(2, '0'), 128, 66, 110, blink ? '#ffffff' : '#7ff0a0');
      l.tx.needsUpdate = true;
    }
  }

  /** Kicker latéral [20] : élastique blanc tendu entre deux plots. */
  kickerBuild() {
    const k = this.t.kicker, a = P(k.a[0], k.a[1], 1.1), b = P(k.b[0], k.b[1], 1.1);
    const len = a.distanceTo(b);
    const band = new THREE.Mesh(new THREE.BoxGeometry(0.9, 1.4, len), this.mat.rubber);
    band.position.copy(a.clone().add(b).multiplyScalar(0.5)); band.rotation.y = Math.atan2(b.x - a.x, b.z - a.z); band.castShadow = true; this.scene.add(band);
    this.kickerMat = new THREE.MeshStandardMaterial({ color: this.col.yellow, emissive: this.col.yellow, emissiveIntensity: 0.2, roughness: 0.3 });
    for (const p of [a, b]) { const post = new THREE.Mesh(new THREE.CylinderGeometry(0.55, 0.55, 2.2, 16), this.kickerMat); post.position.set(p.x, 1.1, p.z); post.castShadow = true; this.scene.add(post); }
    this.kickFlash = 0;
  }

  /** Trous des poches LOCK, du TRICK et de la trappe : creux sombres cerclés de chrome. */
  holesBuild() {
    for (const s of this.t.sensors.filter(o => ['lock', 'trick'].includes(o.tag))) {
      const h = new THREE.Mesh(new THREE.CircleGeometry(s.r * S * 1.15, 32), this.mat.hole); h.rotation.x = -Math.PI / 2; h.position.copy(P(s.x, s.y, 0.05)); this.scene.add(h);
      const ring = new THREE.Mesh(new THREE.TorusGeometry(s.r * S * 1.2, 0.12, 8, 32), this.mat.chrome); ring.rotation.x = Math.PI / 2; ring.position.copy(P(s.x, s.y, 0.08)); this.scene.add(ring);
    }
    for (const s of this.t.sensors.filter(o => ['inlane', 'outlane', 'onetwo'].includes(o.tag))) {
      const btn = new THREE.Mesh(new THREE.CylinderGeometry(0.42, 0.5, 0.25, 16), this.mat.chrome); btn.position.copy(P(s.x, s.y, 0.12)); this.scene.add(btn);
    }
  }

  /** Roulette (spinner) : une plaque jaune au nom de la roulette, sur un axe entre deux plots, qui tourne au passage de la bille. */
  spinnerBuild() {
    const sp = this.t.spinner;
    if (!sp) return;
    const [ax, ay] = sp.a, [bx, by] = sp.b, len = Math.hypot(bx - ax, by - ay) * S, h = 2.6;
    for (const [x, y] of [sp.a, sp.b]) {
      const post = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.32, h + 0.4, 12), this.mat.chrome);
      post.position.copy(P(x, y, (h + 0.4) / 2)); this.scene.add(post);
    }
    const axle = new THREE.Mesh(new THREE.CylinderGeometry(0.08, 0.08, len, 8), this.mat.chrome);
    axle.rotation.z = Math.PI / 2; axle.rotation.y = -Math.atan2(by - ay, bx - ax);
    axle.position.copy(P((ax + bx) / 2, (ay + by) / 2, h)); this.scene.add(axle);
    const [c, g] = canvas(256, 64);
    g.fillStyle = this.Y; g.fillRect(0, 0, 256, 64);
    g.fillStyle = this.N; g.fillRect(0, 0, 256, 6); g.fillRect(0, 58, 256, 6);
    txt(g, String(this.lb.spinner || '').toUpperCase(), 128, 33, 34, this.N, { weight: 900, ls: 4, max: 236 });
    const geo = new THREE.BoxGeometry(len * 0.92, 1.5, 0.08);
    geo.translate(0, -0.75, 0);
    const plate = new THREE.Mesh(geo, new THREE.MeshStandardMaterial({ map: tex(c), roughness: 0.35, metalness: 0.2, emissive: this.col.yellow, emissiveIntensity: 0.12 }));
    // Pivot sur l'axe : la plaque pend, tournée face au joueur, et tourne autour de l'axe.
    this.spinner = new THREE.Group();
    this.spinner.position.copy(P((ax + bx) / 2, (ay + by) / 2, h));
    this.spinner.rotation.y = -Math.atan2(by - ay, bx - ax);
    this.spinnerPlate = plate; this.spinner.add(plate); this.scene.add(this.spinner);
  }

  /** Entrée du souterrain, en haut du couloir de lancement : un trou sombre sous une petite voûte. */
  subwayBuild() {
    const sw = this.t.subway;
    if (!sw) return;
    const r = sw.r * S;
    const hole = new THREE.Mesh(new THREE.CircleGeometry(r * 1.1, 32), this.mat.hole); hole.rotation.x = -Math.PI / 2; hole.position.copy(P(sw.x, sw.y, 0.05)); this.scene.add(hole);
    const ring = new THREE.Mesh(new THREE.TorusGeometry(r * 1.15, 0.14, 8, 32), this.mat.chrome); ring.rotation.x = Math.PI / 2; ring.position.copy(P(sw.x, sw.y, 0.08)); this.scene.add(ring);
    const arch = new THREE.Mesh(new THREE.TorusGeometry(r * 1.2, 0.22, 10, 24, Math.PI), this.mat.yellow);
    arch.position.copy(P(sw.x, sw.y - sw.r * 0.6, 0)); this.scene.add(arch);
  }

  flippersBuild() {
    this.flippers = this.t.flippers.map(f => {
      const L = f.len * S, r1 = f.r1 * S, r2 = f.r2 * S, y0 = f.lv ? MH : 0;
      const sh = new THREE.Shape(); sh.absarc(0, 0, r1, Math.PI / 2, Math.PI * 1.5, false); sh.lineTo(L, -r2); sh.absarc(L, 0, r2, -Math.PI / 2, Math.PI / 2, false); sh.lineTo(0, r1);
      const geo = new THREE.ExtrudeGeometry(sh, { depth: f.mini ? 0.9 : 1.3, bevelEnabled: true, bevelSize: 0.15, bevelThickness: 0.15, bevelSegments: 2 });
      geo.rotateX(-Math.PI / 2);
      const m = new THREE.Mesh(geo, f.tackle ? this.mat.blue : this.mat.yellow); m.position.copy(P(f.px, f.py, y0 + 0.2)); m.castShadow = true; this.scene.add(m);
      // Caoutchouc autour du flipper.
      const rub = new THREE.Mesh(new THREE.ExtrudeGeometry(sh, { depth: 0.35, bevelEnabled: false }), f.tackle ? this.mat.yellow : this.mat.blue);
      rub.geometry.rotateX(-Math.PI / 2); rub.scale.set(1.04, 1, 1.12); rub.position.y = 0.45; m.add(rub);
      const pin = new THREE.Mesh(new THREE.CylinderGeometry(0.4, 0.4, 1.7, 16), this.mat.chrome); pin.position.copy(P(f.px, f.py, y0 + 0.85)); this.scene.add(pin);
      return m;
    });
  }

  /**
   * Une rampe : ruban (pelouse rayée, nom de la rampe) bordé de murets jaunes à lisse bleue, puis, à
   * partir de ramp.wire, rails en fil chromés ; les rails des trous d'éjection sont tout en fil.
   */
  rampBuild(ramp, label, f = 0.5, flip = false) {
    const pts = ramp.pts, Wd = ramp.width, solidEnd = ramp.wire === null ? pts.length - 1 : Math.min(pts.length - 1, ramp.wire);
    const frame = i => {
      const p = pts[i], n1 = pts[Math.min(i + 1, pts.length - 1)], p0 = pts[Math.max(i - 1, 0)];
      const d = new THREE.Vector3(n1.x - p0.x, 0, n1.y - p0.y).normalize(), n = new THREE.Vector3(-d.z, 0, d.x);
      return { c: P(p.x, p.y, p.h * S + 0.25), n };
    };
    if (solidEnd > 1) {
      const pos = [], uv = [], ind = [], edgeL = [], edgeR = [], rimL = [], rimR = [];
      for (let i = 0; i <= solidEnd; i++) {
        const { c, n } = frame(i), l = c.clone().addScaledVector(n, Wd), r = c.clone().addScaledVector(n, -Wd);
        pos.push(l.x, l.y, l.z, r.x, r.y, r.z); uv.push(1, pts[i].s / pts[solidEnd].s, 0, pts[i].s / pts[solidEnd].s);
        edgeL.push({ c: l, n }); edgeR.push({ c: r, n: n.clone().negate() });
        rimL.push(l.clone().addScaledVector(n, 0.3).setY(l.y + 1.7)); rimR.push(r.clone().addScaledVector(n, -0.3).setY(r.y + 1.7));
        if (i) { const k = i * 2; ind.push(k - 2, k - 1, k, k - 1, k + 1, k); }
      }
      const geo = new THREE.BufferGeometry(); geo.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3)); geo.setAttribute('uv', new THREE.Float32BufferAttribute(uv, 2)); geo.setIndex(ind); geo.computeVertexNormals();
      const [c, g] = canvas(128, 1024);
      for (let i = 0; i < 16; i++) { g.fillStyle = i % 2 ? '#4fb84f' : '#5fca5c'; g.fillRect(0, i * 64, 128, 64); }
      g.fillStyle = this.N; g.fillRect(0, 0, 10, 1024); g.fillRect(118, 0, 10, 1024);
      g.fillStyle = 'rgba(255,255,255,.9)'; g.fillRect(12, 0, 4, 1024); g.fillRect(112, 0, 4, 1024);
      if (label) txt(g, String(label).toUpperCase(), 64, 1024 * (1 - f), label.length > 9 ? 46 : 60, this.Y, { rot: flip ? Math.PI / 2 : -Math.PI / 2, ls: 10, weight: 900, glow: 'rgba(0,10,40,.9)', blur: 8, max: 520 });
      const m = new THREE.Mesh(geo, new THREE.MeshStandardMaterial({ map: tex(c), side: THREE.DoubleSide, roughness: 0.5 }));
      m.castShadow = true; m.receiveShadow = true; this.scene.add(m);
      // Murets épais le long des deux bords (section rectangulaire : face jaune, dessus bleu).
      const wm = new THREE.MeshStandardMaterial({ color: this.col.yellow, emissive: this.col.yellow, emissiveIntensity: 0.16, roughness: 0.35, side: THREE.DoubleSide });
      const sweep = edge => {
        const p = [], ix = [], prof = [[0, -0.6], [0, 1.6], [0.55, 1.6], [0.55, -0.6]];
        edge.forEach(({ c: q0, n }) => { for (const [o, h] of prof) { const q = q0.clone().addScaledVector(n, o); p.push(q.x, q.y + h, q.z); } });
        for (let i = 1; i < edge.length; i++) for (let k = 0; k < 3; k++) { const a = (i - 1) * 4 + k, b = i * 4 + k; ix.push(a, b, a + 1, b, b + 1, a + 1); }
        const sg = new THREE.BufferGeometry(); sg.setAttribute('position', new THREE.Float32BufferAttribute(p, 3)); sg.setIndex(ix); sg.computeVertexNormals();
        const o = new THREE.Mesh(sg, wm); o.castShadow = true; this.scene.add(o);
      };
      sweep(edgeL); sweep(edgeR);
      for (const r of [rimL, rimR]) this.scene.add(new THREE.Mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(r), r.length * 2, 0.36, 8), this.mat.blue));
    }
    // Rails en fil : deux en bas, deux sur les côtés.
    if (solidEnd < pts.length - 1) {
      const from = Math.max(0, solidEnd - 1);
      for (const [o, hh] of [[-0.5, 0.05], [0.5, 0.05], [-0.95, 0.8], [0.95, 0.8]]) {
        const v = [];
        for (let i = from; i < pts.length; i++) { const { c, n } = frame(i); v.push(c.clone().addScaledVector(n, o).setY(c.y + hh)); }
        this.scene.add(new THREE.Mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(v), v.length * 3, 0.12, 6), this.mat.chrome));
      }
    }
    // Piliers : de loin en loin, du plateau jusque sous la rampe (pas sur le mini-terrain).
    const b = this.t.block;
    for (let i = 6; i < pts.length - 3; i += 14) {
      const p = pts[i];
      if (p.h < 14) continue;
      if (p.x > b.x1 && p.x < b.x2 && p.y > b.y1 && p.y < b.y2) continue;
      const hgt = p.h * S;
      const post = new THREE.Mesh(new THREE.CylinderGeometry(0.15, 0.15, hgt, 8), this.mat.chrome); post.position.copy(P(p.x, p.y, hgt / 2)); this.scene.add(post);
    }
  }

  plungerBuild() {
    const p = this.t.plunger, x = X(p.x), z = Z(p.top);
    this.knob = new THREE.Mesh(new THREE.CylinderGeometry(1.0, 1.0, 2.4, 24), this.mat.yellow); this.knob.rotation.x = Math.PI / 2; this.knob.position.set(x, 1.2, z + 2.2); this.scene.add(this.knob);
    const spring = new THREE.Mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(Array.from({ length: 50 }, (_, i) => new THREE.Vector3(Math.cos(i * 0.8) * 0.75, 1.2 + Math.sin(i * 0.8) * 0.75, i * 0.08))), 120, 0.09, 6), this.mat.chrome);
    spring.position.set(x, 0, z + 3.4); this.scene.add(spring);
    this.plungerZ = z + 2.2;
  }

  /**
   * Le mini-terrain surélevé [1] : socle en pointe (flancs sombres, liseré jaune), pelouse avec sa
   * surface de réparation, but et filets [2], gardien, joueurs adverses sur leurs rails [3], trous
   * d'éjection [4], rambardes.
   */
  miniBuild() {
    const m = this.t.mini, sc = this.scene;
    if (!m) return;
    const foot = this.t.walls.find(w => w.block).pts.slice(0, -1);
    const shape = new THREE.Shape(foot.map(([x, y]) => new THREE.Vector2(X(x), -Z(y))));
    const geo = new THREE.ExtrudeGeometry(shape, { depth: MH, bevelEnabled: false });
    geo.rotateX(-Math.PI / 2);
    // Coordonnées de texture du dessus : la boîte englobante du mini-terrain.
    const pos = geo.attributes.position, uvs = geo.attributes.uv;
    const x1 = X(m.x1), x2 = X(m.x2), z1 = Z(m.y1), z2 = Z(m.y2);
    for (let i = 0; i < pos.count; i++) uvs.setXY(i, (pos.getX(i) - x1) / (x2 - x1), 1 - (pos.getZ(i) - z1) / (z2 - z1));
    const [pc, pg] = canvas(512, 640), u = x => (x - m.x1) / (m.x2 - m.x1) * 512, v = y => (y - m.y1) / (m.y2 - m.y1) * 640;
    for (let i = 0; i < 10; i++) { pg.fillStyle = i % 2 ? '#2f8a3b' : '#3d9e48'; pg.fillRect(0, i * 64, 512, 64); }
    pg.strokeStyle = 'rgba(255,255,255,.85)'; pg.lineWidth = 4;
    pg.strokeRect(u(148), v(98), u(310) - u(148), v(226) - v(98));
    pg.strokeRect(u(200), v(98), u(258) - u(200), v(131) - v(98));
    pg.beginPath(); pg.arc(u(229.5), v(226), 60, 0, Math.PI); pg.stroke();
    pg.fillStyle = 'rgba(255,255,255,.85)'; pg.beginPath(); pg.arc(u(229.5), v(190), 6, 0, Math.PI * 2); pg.fill();
    // Rails des rangées de joueurs (peints) et sigle du club au centre.
    pg.fillStyle = 'rgba(10,16,40,.55)';
    for (const r of m.rows) { const half = Math.max(...r.offs) + r.amp + 10; pg.fillRect(u(m.center - half), v(r.y) - 5, u(m.center + half) - u(m.center - half), 10); }
    const top = new THREE.MeshStandardMaterial({ map: tex(pc), roughness: 0.55 });
    const sideMat = new THREE.MeshStandardMaterial({ color: this.col.navy, roughness: 0.45, metalness: 0.2 });
    const block = new THREE.Mesh(geo, [top, sideMat]);
    block.castShadow = block.receiveShadow = true; sc.add(block);
    // Liseré jaune lumineux autour du socle.
    const rim = foot.concat([foot[0]]).map(([x, y]) => P(x, y, MH + 0.05));
    sc.add(new THREE.Mesh(new THREE.TubeGeometry(new THREE.CatmullRomCurve3(rim, true, 'catmullrom', 0), 80, 0.18, 6, true), this.mat.neon));
    // Bandeau lumineux sur la pointe (face au joueur) : le sigle du club, « BUT ! » quand on marque.
    const [fc, fg] = canvas(512, 96);
    this.miniFront = { c: fc, g: fg, tx: tex(fc), key: '' };
    const front = new THREE.Mesh(new THREE.PlaneGeometry((245 - 212.6) * S * 1.6, MH * 0.55), new THREE.MeshStandardMaterial({ map: this.miniFront.tx, emissiveMap: this.miniFront.tx, emissive: 0xffffff, emissiveIntensity: 0.9 }));
    front.position.copy(P(228.8, 299.4, MH * 0.5)); sc.add(front);
    this.drawMiniFront(false);
    // Rambardes du mini-terrain (blanches) ; ouverture à gauche pour la rampe DRIBBLE.
    const rail = new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.3 });
    for (const wl of m.walls) if (!wl.net) this.wall(wl.pts, 1.1, wl.r * S * 2, false, MH, rail);
    for (const p of m.posts.slice(2)) { const o = new THREE.Mesh(new THREE.CylinderGeometry(p.r * S, p.r * S, 1.6, 16), this.mat.rubber); o.position.copy(P(p.x, p.y, MH + 0.8)); sc.add(o); }
    // Trous d'éjection.
    for (const h of m.holes) {
      const o = new THREE.Mesh(new THREE.CircleGeometry(h.r * S * 1.15, 32), this.mat.hole); o.rotation.x = -Math.PI / 2; o.position.copy(P(h.x, h.y, MH + 0.04)); sc.add(o);
      const ring = new THREE.Mesh(new THREE.TorusGeometry(h.r * S * 1.25, 0.22, 10, 32), this.mat.chrome); ring.rotation.x = Math.PI / 2; ring.position.copy(P(h.x, h.y, MH + 0.1)); sc.add(ring);
    }
    // But : poteaux et transversale blancs, filets (texture en mailles transparente).
    const gx1 = X(m.posts[0].x), gx2 = X(m.posts[1].x), gz = Z(m.posts[0].y), gb = Z(m.y1 + 2), gh = 2.6;
    const postMat = new THREE.MeshStandardMaterial({ color: 0xffffff, roughness: 0.25, emissive: 0xffffff, emissiveIntensity: 0.15 });
    for (const x of [gx1, gx2]) { const p = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.22, gh, 12), postMat); p.position.set(x, MH + gh / 2, gz); p.castShadow = true; sc.add(p); }
    const bar = new THREE.Mesh(new THREE.CylinderGeometry(0.22, 0.22, gx2 - gx1, 12), postMat); bar.rotation.z = Math.PI / 2; bar.position.set((gx1 + gx2) / 2, MH + gh, gz); sc.add(bar);
    const [nc, ng] = canvas(256, 256);
    ng.strokeStyle = 'rgba(255,255,255,.75)'; ng.lineWidth = 3;
    for (let i = 0; i <= 256; i += 16) { ng.beginPath(); ng.moveTo(i, 0); ng.lineTo(i, 256); ng.stroke(); ng.beginPath(); ng.moveTo(0, i); ng.lineTo(256, i); ng.stroke(); }
    const nt = tex(nc); nt.wrapS = nt.wrapT = THREE.RepeatWrapping;
    const netMat = new THREE.MeshStandardMaterial({ map: nt, transparent: true, side: THREE.DoubleSide, depthWrite: false, roughness: 0.8, alphaTest: 0.05 });
    const plane = (wd, ht, x, y, z, rx, ry) => { const tt = nt.clone(); tt.needsUpdate = true; tt.repeat.set(wd / 1.2, ht / 1.2); const mt = netMat.clone(); mt.map = tt; const o = new THREE.Mesh(new THREE.PlaneGeometry(wd, ht), mt); o.position.set(x, y, z); o.rotation.set(rx, ry, 0); sc.add(o); return o; };
    this.nets = [
      plane(gx2 - gx1, gh, (gx1 + gx2) / 2, MH + gh / 2, gb, 0, 0),
      plane(gz - gb, gh, gx1, MH + gh / 2, (gz + gb) / 2, 0, Math.PI / 2),
      plane(gz - gb, gh, gx2, MH + gh / 2, (gz + gb) / 2, 0, Math.PI / 2),
      plane(gx2 - gx1, gz - gb, (gx1 + gx2) / 2, MH + gh, (gz + gb) / 2, -Math.PI / 2, 0),
    ];
    this.netBack = gb;
    this.netShake = 0;
    // Gardien adverse (tenue de gardien, gants) ; il glisse le long de sa ligne.
    this.keeper = this.player({ shirt: '#22b36b', sleeves: '#1a8d54', shorts: '#14161b', socks: '#22b36b' }, 0.17, true);
    this.keeper.position.copy(P((m.keeper.min + m.keeper.max) / 2, m.keeper.y, MH));
    sc.add(this.keeper);
    // Joueurs adverses : maillots plantés sur leurs rails (couleurs de l'adversaire, numéros), qui tombent quand ils sont éliminés.
    const shirtShape = new THREE.Shape();
    [[-0.5, -0.5], [0.5, -0.5], [0.5, 0.18], [0.78, 0.05], [0.95, 0.32], [0.55, 0.62], [0.2, 0.62], [0, 0.48], [-0.2, 0.62], [-0.55, 0.62], [-0.95, 0.32], [-0.78, 0.05], [-0.5, 0.18]].forEach(([x, y], i) => shirtShape[i ? 'lineTo' : 'moveTo'](x, y));
    const nums = [2, 4, 5, 3, 6, 8, 7, 11, 9, 10];
    this.players = [];
    m.rows.forEach(r => r.offs.forEach(() => {
      const [c, g] = canvas(128, 128), t = tex(c);
      const geo2 = new THREE.ExtrudeGeometry(shirtShape, { depth: 0.25, bevelEnabled: false });
      const sw = 3.6 / 1.9, sh = 2.4;
      geo2.scale(sw, sh, 1); geo2.translate(0, 0.5 * sh, -0.12);
      const pp = geo2.attributes.position, uu = geo2.attributes.uv;
      for (let i = 0; i < pp.count; i++) uu.setXY(i, pp.getX(i) / (1.9 * sw) + 0.5, pp.getY(i) / (1.12 * sh));
      const o = new THREE.Mesh(geo2, new THREE.MeshStandardMaterial({ map: t, roughness: 0.5 }));
      o.position.copy(P(m.center, r.y, MH)); o.castShadow = true; sc.add(o);
      this.players.push({ m: o, y: MH, up: MH, c, g, t, n: nums[this.players.length] });
    }));
    this.paintPlayers();
  }

  /** Maillots des joueurs adverses aux couleurs de l'adversaire (numéro lisible sur le maillot). */
  paintPlayers() {
    const ok = this.cfg.opp && this.cfg.opp.kit ? this.cfg.opp.kit : { shirt: '#e9edf5', sleeves: '#e9edf5', shorts: '#3a3a3a' };
    const light = new THREE.Color(ok.shirt).getHSL({}).l > 0.6;
    for (const d of this.players || []) {
      const g = d.g;
      g.fillStyle = ok.shirt; g.fillRect(0, 0, 128, 128);
      g.fillStyle = ok.sleeves || ok.shirt; g.fillRect(0, 0, 18, 128); g.fillRect(110, 0, 18, 128);
      txt(g, String(d.n), 64, 76, 70, light ? '#14161b' : '#ffffff', { weight: 900 });
      d.t.needsUpdate = true;
    }
  }

  /** Nouvel adversaire (à chaque match) : joueurs du mini-terrain, hexagones, écran AWAY, tablier, écran géant. */
  setOpp(opp) {
    if (!opp || opp === this.cfg.opp) return;
    this.cfg.opp = opp;
    this.paintPlayers();
    this.paintHexes();
    this.drawLeds(0, 0, true);
    this.apron.key = '';
    this.stadium.cfg.opp = opp;
    this.stadium.setScore(0, 0, '', true);
  }

  drawMiniFront(goal) {
    const key = String(goal);
    if (key === this.miniFront.key) return;
    this.miniFront.key = key;
    const g = this.miniFront.g;
    g.fillStyle = '#04060c'; g.fillRect(0, 0, 512, 96);
    g.strokeStyle = this.Y; g.lineWidth = 6; g.strokeRect(3, 3, 506, 90);
    txt(g, goal ? String(this.lb.goal || 'BUT !').toUpperCase() : String(this.cfg.short || '').toUpperCase(), 256, 50, 64, goal ? '#fff3b0' : this.Y, { weight: 900, ls: 8, glow: goal ? '#ffd23a' : null, blur: 18, max: 470 });
    this.miniFront.tx.needsUpdate = true;
  }

  /** Petit joueur (gardien du mini-terrain) : mêmes formes que les géants du stade, en petit. */
  player(kit, k, keeper = false) {
    const g = new THREE.Group(), mk = c => new THREE.MeshStandardMaterial({ color: c, roughness: 0.55 });
    const shirt = mk(kit.shirt), shorts = mk(kit.shorts), socks = mk(kit.socks), skin = mk('#d9a07a'), glove = mk('#f2f2f2');
    const add = (geo, mat, x, y, z, p = g) => { const o = new THREE.Mesh(geo, mat); o.position.set(x, y, z); o.castShadow = true; p.add(o); return o; };
    for (const sx of [-1, 1]) { add(new THREE.CapsuleGeometry(0.9, 6, 4, 8), socks, sx * 1.3, 4, 0).rotation.z = sx * 0.25; }
    add(new THREE.CylinderGeometry(2.4, 2.3, 3, 12), shorts, 0, 9.4, 0);
    add(new THREE.CapsuleGeometry(2.3, 4.2, 4, 12), shirt, 0, 13.4, 0).scale.set(1.05, 1, 0.7);
    add(new THREE.SphereGeometry(1.6, 14, 10), skin, 0, 19, 0);
    this.keeperArms = [];
    for (const sx of [-1, 1]) {
      const sh = new THREE.Group(); sh.position.set(sx * 2.8, 16, 0); g.add(sh);
      add(new THREE.CapsuleGeometry(0.8, 4.4, 4, 8), shirt, 0, -2.6, 0, sh);
      add(new THREE.SphereGeometry(1.0, 10, 8), keeper ? glove : skin, 0, -5.4, 0, sh);
      sh.rotation.z = sx * 1.0;
      this.keeperArms.push(sh);
    }
    g.scale.setScalar(k);
    return g;
  }

  /** Tablier : chrono du match, jauge COMBO, maillots des joueurs adverses, écusson et titre. */
  apronBuild() {
    // La toile couvre la zone sous le bord bas (de y APRON_Y au bas de la machine), transparente au-dessus du bord.
    const [c, g] = canvas(1024, Math.round(1024 * (H - APRON_Y) / PF_W));
    this.apron = { c, g, tx: tex(c), key: '' };
    const mat = new THREE.MeshStandardMaterial({ map: this.apron.tx, emissiveMap: this.apron.tx, emissive: 0xffffff, emissiveIntensity: 0.5, roughness: 0.5, transparent: true });
    const m = new THREE.Mesh(new THREE.PlaneGeometry(PF_W * S, (H - APRON_Y) * S), mat);
    m.rotation.x = -Math.PI / 2; m.position.set(X(PF_W / 2), 0.06, Z((H + APRON_Y) / 2)); m.receiveShadow = true; this.scene.add(m);
    this.drawApron({ clock: '45:00', half: 1, combo: 0, comboMax: 5, hexes: Array(10).fill(true), ball: 1, balls: 3 });
  }

  drawApron(s) {
    const key = [s.clock, s.half, s.combo, s.hexes.join(''), s.ball, s.balls].join(':');
    if (key === this.apron.key) return;
    this.apron.key = key;
    const { c, g } = this.apron, k = c.width / PF_W, Y = this.Y, lb = this.lb, d = this.t.deco;
    g.setTransform(1, 0, 0, 1, 0, 0);
    g.clearRect(0, 0, c.width, c.height);
    g.setTransform(k, 0, 0, k, 0, -APRON_Y * k);
    // Forme du tablier : sous les bords bas, de part et d'autre de l'évacuation.
    poly(g, [[0, 708], [2, 722], [30, 744], [56, 752], [162, 810], [184.8, 818], [207, 810], [313, 752], [339, 744], [367, 722], [366, H], [0, H]]);
    const gr = g.createLinearGradient(0, 740, 0, H); gr.addColorStop(0, '#0b1640'); gr.addColorStop(1, '#050a1e');
    g.fillStyle = gr; g.fill();
    g.strokeStyle = Y; g.lineWidth = 1.2; g.stroke();
    // Chrono du match.
    const [cx, cy, cr] = d.clock;
    g.fillStyle = '#2a3142'; g.beginPath(); g.arc(cx, cy, cr, 0, Math.PI * 2); g.fill();
    g.fillStyle = '#04060c'; g.beginPath(); g.arc(cx, cy, cr - 3, 0, Math.PI * 2); g.fill();
    led(g, s.clock, cx, cy - 1, 13, '#7ff0a0');
    txt(g, String((lb.halves || [])[s.half - 1] || '').toUpperCase(), cx, cy + 12, 5, '#9aa6c0', { ls: 1 });
    // Écusson et titre.
    logoAt(g, this.cfg.logo, 184.8, 834, 24);
    txt(g, String(this.cfg.title || '').toUpperCase(), 184.8, 857, 7, Y, { weight: 900, ls: 1.5, max: 110 });
    // Jauge COMBO : cinq cases.
    const cb = d.combo, n = s.comboMax || 5, w = (cb.x2 - cb.x1) / n;
    txt(g, String(lb.combo || '').toUpperCase(), cb.x1, cb.y1 - 5, 6, Y, { align: 'left', weight: 900, ls: 2 });
    for (let i = 0; i < n; i++) {
      const on = i < s.combo;
      g.fillStyle = on ? Y : '#1a2236'; g.fillRect(cb.x1 + i * w + 1, cb.y1, w - 2, cb.y2 - cb.y1);
      if (on) { g.fillStyle = 'rgba(255,255,255,.35)'; g.fillRect(cb.x1 + i * w + 1, cb.y1, w - 2, 3); }
    }
    // Maillots des joueurs adverses (2 attaquants, 4 milieux, 4 défenseurs) : éteints quand ils sont éliminés.
    const ok = this.cfg.opp && this.cfg.opp.kit ? this.cfg.opp.kit : { shirt: '#e9edf5', sleeves: '#e9edf5' };
    const jr = d.jerseys, grid = [[2, 3], [0, 1, 2, 3], [0, 1, 2, 3]], order = [[0, 1], [2, 3, 4, 5], [6, 7, 8, 9]];
    grid.forEach((cols, row) => cols.forEach((col, j) => {
      const i = order[row][j], on = s.hexes[i];
      g.globalAlpha = on ? 1 : 0.18;
      jersey(g, jr.x + col * jr.dx - 6, jr.y + row * jr.dy, 0.24, ok.shirt, ok.sleeves || ok.shirt, '', '#000');
      g.globalAlpha = 1;
    }));
    // Billes restantes.
    for (let i = 0; i < s.balls; i++) {
      const on = i >= s.ball - 1;
      g.fillStyle = on ? '#e9edf5' : '#1a2236'; g.beginPath(); g.arc(124 + i * 10, 848, 3.2, 0, Math.PI * 2); g.fill();
    }
    txt(g, String(lb.balls || '').toUpperCase(), 119, 838, 5, '#9aa6c0', { align: 'left', ls: 1 });
    this.apron.tx.needsUpdate = true;
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
    // Rendu intermédiaire avec anticrénelage (MSAA) : sans lui, le halo rend les bords en escalier.
    const rt = new THREE.WebGLRenderTarget(1, 1, { type: THREE.HalfFloatType, samples: 4 });
    this.composer = new EffectComposer(this.renderer, rt);
    this.composer.setSize(this.w || 400, this.h || 800);
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
    const pitch = this.pitch ?? (a < 0.8 ? 0.98 : a < 1.3 ? 0.9 : 0.74);
    cam.fov = a < 0.8 ? 39 : 36;
    const look = new THREE.Vector3(0, 0, Z(H * 0.55));
    const pts = [P(-6, -6, 14), P(W + 6, -6, 14), P(-6, H), P(W + 6, H), P(-6, H * 0.5), P(W + 6, H * 0.5)];
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

  /** Qualité : 2 = ombres et halo, 1 = sans halo, 0 = sans ombres ni foule, en définition simple. */
  setQuality(q) {
    this.quality = q;
    this.renderer.shadowMap.enabled = q >= 1;
    this.key.castShadow = q >= 1;
    this.stadium.setQuality(q);
    if (q === 0) { this.renderer.setPixelRatio(1); this.resize(this.w, this.h); }
    this.scene.traverse(o => { if (o.material) [].concat(o.material).forEach(m => { m.needsUpdate = true; }); });
  }

  /**
   * Avant d'afficher : textures envoyées à la carte graphique et shaders de toute la scène compilés
   * d'avance (même ce qui n'apparaît qu'en cours de partie), puis une image pour les effets. Sans cela,
   * la carte graphique les reçoit au fil des premières images, qui saccadent quelques secondes.
   */
  async warmup() {
    const r = this.renderer;
    const up = t => { if (t && t.isTexture) r.initTexture(t); };
    up(this.scene.background);
    this.scene.traverse(o => [].concat(o.material || []).forEach(m => Object.keys(m).forEach(k => up(m[k]))));
    try { await r.compileAsync(this.scene, this.camera); } catch (e) { /* compilés au premier rendu */ }
    this.render();
  }

  /** Compile en arrière-plan les shaders d'une autre qualité : le passage se fait ensuite sans à-coup. */
  prepareQuality(q) {
    const r = this.renderer, was = [r.shadowMap.enabled, this.key.castShadow];
    r.shadowMap.enabled = this.key.castShadow = q >= 1;
    let p;
    try { p = r.compileAsync(this.scene, this.camera); } catch (e) { p = Promise.resolve(); }
    [r.shadowMap.enabled, this.key.castShadow] = was;
    return p.catch(() => {});
  }

  // ------------------------------------------------------------------ animation

  /** Effet visuel ponctuel (appelé par les règles). */
  fx(name, data) {
    if (name === 'bumper' && this.bumpers[data]) this.bumpers[data].flash = 1;
    else if (name === 'sling' && this.slings[data]) this.slings[data].flash = 1;
    else if (name === 'kicker') this.kickFlash = 1;
    else if (name === 'nudge') this.shake = 0.5;
    else if (name === 'tilt') this.shake = 1;
    else if (name === 'goal') { this.strobe = 2.4; this.netShake = 1; this.leds[0].flash = 2.4; this.goalShow = 3; this.stadium.celebrate(1); }
    else if (name === 'awaygoal') { this.leds[1].flash = 1.6; this.stadium.hype = 0; }
    else if (name === 'save') this.keeperDive = 1;
    else if (name === 'win' || name === 'cupwon') this.stadium.celebrate(1);
    else if (['jackpot', 'multiball', 'combo', 'skill', 'extra', 'super', 'locked'].includes(name)) { this.strobe = 1.6; if (name === 'multiball' || name === 'jackpot') this.stadium.celebrate(0.7); }
  }

  frame(phys, lamps, dt, time, plungerPull = 0) {
    dt = Math.max(0, dt);
    // billes
    const live = phys.balls.filter(b => b.state !== 'gone' && b.state !== 'subway');
    this.balls.forEach((m, i) => {
      const b = live[i];
      m.visible = !!b;
      if (b) m.position.set(X(b.x), R * S + (b.h || 0) * S + (b.state === 'ramp' ? 0.25 : 0), Z(b.y));
    });
    // flippers
    phys.flippers.forEach((f, i) => { this.flippers[i].rotation.y = -f.ang; });
    // roulette
    if (this.spinner && phys.spinner) this.spinnerPlate.rotation.x = phys.spinner.ang;
    // joueurs adverses (vont et viennent, tombent quand ils sont éliminés) et gardien
    if (phys.players && this.players) {
      phys.players.forEach((p, i) => {
        const d = this.players[i], s = p.seg, want = s.on ? d.up : d.up - 2.8;
        d.y += (want - d.y) * Math.min(1, dt * 14);
        d.m.position.set(X((s.ax + s.bx) / 2), d.y, Z(s.ay));
      });
    }
    if (phys.keeper && this.keeper) {
      const k = phys.keeper;
      this.keeper.position.x = X(k.x);
      this.keeperDive = Math.max(0, (this.keeperDive || 0) - dt * 3);
      this.keeper.rotation.z = -Math.max(-0.5, Math.min(0.5, k.vx / 160)) - Math.sign(k.vx || 1) * this.keeperDive * 0.6;
      this.keeperArms.forEach((a, i) => { a.rotation.z = (i ? 1 : -1) * (1.0 + this.keeperDive * 0.9 + Math.sin(time * 5) * 0.08); });
    }
    if (this.nets) {
      this.netShake = Math.max(0, this.netShake - dt * 1.5);
      this.nets[0].position.z = this.netBack - Math.abs(Math.sin(time * 40) * 0.12 * this.netShake) * 3;
    }
    this.goalShow = Math.max(0, (this.goalShow || 0) - dt);
    this.drawMiniFront(this.goalShow > 0 && Math.sin(time * 10) > 0);
    // bumpers, slingshots, kicker
    const lion = lamps && lamps.lion;
    for (const b of this.bumpers) {
      b.flash = Math.max(0, b.flash - dt * 5);
      const k = b.flash + (lion ? 0.4 + 0.3 * Math.sin(time * 10) : 0);
      b.discMat.emissiveIntensity = 0.3 + k * 1.6;
      b.ringMat.color.copy(this.col.yellow).multiplyScalar(1.5 + k * 2.5);
      b.light.intensity = 6 + k * 40;
    }
    for (const s of this.slings) { s.flash = Math.max(0, s.flash - dt * 6); s.mat.emissiveIntensity = 0.15 + s.flash * 2; }
    this.kickFlash = Math.max(0, this.kickFlash - dt * 6); this.kickerMat.emissiveIntensity = 0.2 + this.kickFlash * 2;
    // lampes
    this.strobe = Math.max(0, (this.strobe || 0) - dt);
    const blink = Math.sin(time * 12) > 0, st = this.strobe > 0 && Math.sin(time * 30) > 0;
    const set = (l, on) => { const v = st ? 1.4 : on === 'blink' ? (blink ? 1.4 : 0.05) : on ? 1.2 : 0.02; l.mat.emissiveIntensity += (v - l.mat.emissiveIntensity) * Math.min(1, dt * 20); };
    for (const l of this.leds) l.flash = Math.max(0, l.flash - dt);
    // Inserts décoratifs : une vague lumineuse qui parcourt le plateau.
    this.L.small.forEach((l, i) => set(l, Math.sin(time * 3 - i * 0.7) > 0.6));
    if (lamps) {
      this.L.hexes.forEach((l, i) => set(l, lamps.hexes[i]));
      set(this.L.save, lamps.save ? 'blink' : false);
      this.paintMode(lamps.mode);
      set(this.L.mode, 'blink');
      set(this.L.modeLabel, true);
      set(this.L.extra, false);
      this.L.mults.forEach((l, i) => set(l, i < 5 ? lamps.mult >= 5 - i : lamps.hold));
      this.L.locks.forEach((l, i) => set(l, lamps.locked[i] ? true : lamps.lit[i] ? 'blink' : false));
      this.L.lockArrows.forEach((l, i) => set(l, lamps.lit[i] ? 'blink' : false));
      this.lockBalls.forEach((m, i) => { m.visible = lamps.locked[i]; });
      for (const [k, arr] of Object.entries(this.L.shots)) {
        const hot = lamps.skill === k || (lamps.multiball && (k === 'r2' || k === 'r3'));
        arr.forEach((l, i) => set(l, hot ? 'blink' : (Math.floor(time * 4) % 3) === (arr.length - 1 - i)));
      }
      this.L.inlanes.forEach(l => set(l, lamps.attack ? 'blink' : false));
      this.L.supers.forEach(l => set(l, lamps.superLit ? 'blink' : false));
      this.L.tackles.forEach(l => set(l, true));
      this.drawLeds(lamps.home, lamps.away);
      this.drawApron(lamps);
      this.stadium.setScore(lamps.home, lamps.away, lamps.clock);
    } else {
      this.drawLeds(0, 0);
      this.L.hexes.forEach((l, i) => set(l, Math.sin(time * 2 + i) > 0));
    }
    this.stadium.update(dt, time);
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
