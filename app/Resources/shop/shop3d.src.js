/**
 * Boutique : aperçu 3D d'un article, à faire tourner à la souris ou au doigt (Three.js).
 * Les objets sont construits ici même par le code (aucun modèle 3D extérieur, donc aucun droit à
 * régler) : mug, papier (poster, carte), sticker, écharpe, tote bag, t-shirt, sweat, casquette.
 * Le dessin vient du fichier d'impression (SVG vectoriel du serveur, mêmes tracés que le PDF) :
 * il est posé comme une décalcomanie sur la zone imprimable du produit. Unités : millimètres.
 *
 * Source de public/assets/js/shop3d.js : après modification, refaire le fichier avec
 * bin/build-shop3d.sh (esbuild, Three.js épinglé, tout dans un seul fichier minifié).
 */
import {
  WebGLRenderer, Scene, PerspectiveCamera, Group, Mesh, Color, SRGBColorSpace, ACESFilmicToneMapping,
  MeshStandardMaterial, MeshPhysicalMaterial, MeshBasicMaterial, CanvasTexture, DoubleSide, BackSide,
  CylinderGeometry, TorusGeometry, CircleGeometry, BoxGeometry, PlaneGeometry, SphereGeometry, ExtrudeGeometry,
  Shape, PMREMGenerator, DirectionalLight, Box3, Vector3, Sphere, BufferGeometry, Float32BufferAttribute, TubeGeometry, CatmullRomCurve3, LatheGeometry, Vector2,
} from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { RoomEnvironment } from 'three/examples/jsm/environments/RoomEnvironment.js';

const PX = 6; // pixels de texture par millimètre (net au zoom, sans être trop lourd)
const MAXPX = 4096;

/** SVG → image (sur fond transparent ou de couleur), en pixels. */
const svgImage = (svg, w, h, scale) => new Promise((ok, ko) => {
  const img = new Image();
  const url = URL.createObjectURL(new Blob([svg], { type: 'image/svg+xml' }));
  img.onload = () => { URL.revokeObjectURL(url); ok(img); };
  img.onerror = e => { URL.revokeObjectURL(url); ko(e); };
  img.width = Math.round(w * scale); img.height = Math.round(h * scale);
  img.src = url;
});

const canvasFor = (w, h) => {
  const s = Math.min(PX, MAXPX / Math.max(w, h));
  const c = document.createElement('canvas');
  c.width = Math.max(2, Math.round(w * s)); c.height = Math.max(2, Math.round(h * s));
  return [c, s];
};

const texture = (renderer, canvas) => {
  const t = new CanvasTexture(canvas);
  t.colorSpace = SRGBColorSpace;
  t.anisotropy = renderer.capabilities.getMaxAnisotropy();
  return t;
};

/** Texture d'une face : le dessin seul (transparent), ou sur un fond. */
async function faceTexture(renderer, f, fill = '') {
  const [c, s] = canvasFor(f.w, f.h);
  const g = c.getContext('2d');
  if (fill) { g.fillStyle = fill; g.fillRect(0, 0, c.width, c.height); }
  if (f.svg) g.drawImage(await svgImage(f.svg, f.w, f.h, s), 0, 0, c.width, c.height);
  return texture(renderer, c);
}

/** Sticker : le dessin bordé de blanc, découpé à la forme (liseré de 2 mm). */
async function stickerTexture(renderer, f) {
  const pad = 3;
  const [c, s] = canvasFor(f.w + 2 * pad, f.h + 2 * pad);
  const g = c.getContext('2d');
  const img = await svgImage(f.svg, f.w, f.h, s);
  const [x, y, w, h] = [pad * s, pad * s, f.w * s, f.h * s];
  for (let a = 0; a < 24; a++) {
    const r = 2 * s;
    g.drawImage(img, x + Math.cos(a / 24 * Math.PI * 2) * r, y + Math.sin(a / 24 * Math.PI * 2) * r, w, h);
  }
  g.globalCompositeOperation = 'source-in';
  g.fillStyle = '#ffffff'; g.fillRect(0, 0, c.width, c.height);
  g.globalCompositeOperation = 'source-over';
  g.drawImage(img, x, y, w, h);
  return [texture(renderer, c), f.w + 2 * pad, f.h + 2 * pad];
}

const decal = (map, extra = {}) => new MeshStandardMaterial({ map, transparent: true, roughness: 0.75, polygonOffset: true, polygonOffsetFactor: -2, depthWrite: false, ...extra });
const cloth = color => new MeshStandardMaterial({ color: new Color(color), roughness: 0.92, metalness: 0 });

// ------------------------------------------------------------------ objets

/** Mug : la face « tour complet » fait tout le tour ; le centre du dessin face à nous, l'anse aux bords. */
async function mug(r, faces, color, enamel) {
  const f = faces.tour || Object.values(faces)[0];
  const R = f.w / (2 * Math.PI), H = f.h + (enamel ? 6 : 10);
  const g = new Group();
  const body = enamel
    ? new MeshStandardMaterial({ color: new Color(color), roughness: 0.25, metalness: 0.15 })
    : new MeshPhysicalMaterial({ color: new Color(color), roughness: 0.18, clearcoat: 1, clearcoatRoughness: 0.08 });
  g.add(new Mesh(new CylinderGeometry(R, R, H, 128, 1, true), body));
  const inner = new Mesh(new CylinderGeometry(R - 2.4, R - 2.4, H - 0.5, 96, 1, true), body.clone());
  inner.material.side = BackSide; inner.material.color.multiplyScalar(0.94);
  g.add(inner);
  const rim = new Mesh(new TorusGeometry(R - 1.2, 1.2, 12, 128), enamel ? new MeshStandardMaterial({ color: '#0E1F4D', roughness: 0.3, metalness: 0.2 }) : body);
  rim.rotation.x = Math.PI / 2; rim.position.y = H / 2; g.add(rim);
  const bottom = new Mesh(new CircleGeometry(R, 96), body); bottom.rotation.x = Math.PI / 2; bottom.position.y = -H / 2; g.add(bottom);
  const floor = new Mesh(new CircleGeometry(R - 2.4, 96), inner.material); floor.rotation.x = -Math.PI / 2; floor.position.y = -H / 2 + 6; g.add(floor);
  // anse : un demi-anneau un peu aplati, derrière (là où se rejoignent les deux bords du dessin)
  const handle = new Mesh(new TorusGeometry(H * 0.3, 5, 20, 48, Math.PI), body);
  handle.rotation.z = -Math.PI / 2; handle.scale.set(1, 0.85, 1.25); handle.position.set(0, 0, -R - 1);
  handle.rotation.y = Math.PI / 2; g.add(handle);
  if (f.svg) {
    const ring = new Mesh(new CylinderGeometry(R + 0.12, R + 0.12, f.h, 192, 1, true, -Math.PI, Math.PI * 2), decal(await faceTexture(r, f), { roughness: 0.2 }));
    g.add(ring);
  }
  g.userData.view = [0.8, 0.18]; // trois quarts : l'anse apparaît sur le côté
  return g;
}

/** Poster, carte : une feuille, recto et verso. */
async function paper(r, faces) {
  const keys = Object.keys(faces), rec = faces[keys[0]], ver = faces[keys[1]] || null;
  const white = new MeshStandardMaterial({ color: '#fbfaf6', roughness: 0.85 });
  const front = new MeshStandardMaterial({ map: await faceTexture(r, rec, '#ffffff'), roughness: 0.6 });
  const back = ver ? new MeshStandardMaterial({ map: await faceTexture(r, ver, '#ffffff'), roughness: 0.6 }) : white;
  const g = new Group();
  g.add(new Mesh(new BoxGeometry(rec.w, rec.h, Math.max(0.4, rec.w / 600)), [white, white, white, white, front, back]));
  g.userData.view = [0.35, 0.08];
  return g;
}

async function sticker(r, faces) {
  const f = Object.values(faces)[0];
  const [map, w, h] = await stickerTexture(r, f);
  const g = new Group();
  const m = new MeshStandardMaterial({ map, transparent: true, alphaTest: 0.5, roughness: 0.35, side: DoubleSide });
  g.add(new Mesh(new PlaneGeometry(w, h), m));
  g.userData.view = [0.4, 0.1];
  return g;
}

/** Écharpe : la bande imprimée entière, qui ondule, avec des franges aux deux bouts. */
async function scarf(r, faces, color) {
  const f = Object.values(faces)[0];
  const geo = new PlaneGeometry(f.w, f.h, 240, 6);
  const pos = geo.attributes.position;
  for (let i = 0; i < pos.count; i++) {
    const x = pos.getX(i);
    pos.setZ(i, Math.sin(x / 190) * 55 + Math.sin(x / 70) * 6);
  }
  geo.computeVertexNormals();
  const g = new Group();
  const mat = new MeshStandardMaterial({ map: await faceTexture(r, f, f.bg || color || '#0E1F4D'), roughness: 0.95, side: DoubleSide });
  g.add(new Mesh(geo, mat));
  const fr = cloth(f.bg || color || '#0E1F4D');
  const n = Math.round(f.h / 9);
  for (const side of [-1, 1]) {
    for (let i = 0; i < n; i++) {
      const y = -f.h / 2 + (i + 0.5) * (f.h / n), x = side * f.w / 2;
      const t = new Mesh(new CylinderGeometry(1.6, 1.2, 70, 6), fr);
      t.rotation.z = Math.PI / 2; t.position.set(x + side * 35, y, Math.sin(x / 190) * 55 + Math.sin(x / 70) * 6);
      g.add(t);
    }
  }
  g.userData.view = [0.25, 0.35];
  return g;
}

/** Tote bag : un sac de toile plat, deux anses, le dessin au centre. */
async function tote(r, faces, color) {
  const f = Object.values(faces)[0];
  const W = 380, H = 420, D = 10;
  const g = new Group();
  const mat = cloth(color);
  g.add(new Mesh(new BoxGeometry(W, H, D, 1, 1, 1), mat));
  for (const z of [D / 2 - 1, -D / 2 + 1]) {
    const h = new Mesh(new TorusGeometry(85, 7, 12, 48, Math.PI), mat);
    h.position.set(0, H / 2, z); h.scale.set(1, 1.6, 0.4); g.add(h);
  }
  if (f.svg) {
    const p = new Mesh(new PlaneGeometry(f.w, f.h), decal(await faceTexture(r, f)));
    p.position.set(0, H / 2 - 45 - f.h / 2, D / 2 + 0.2); g.add(p);
  }
  g.userData.view = [0.3, 0.1];
  return g;
}

/**
 * Vêtement porté par un buste de couture sur pied (mannequin de couturière) : le vêtement épouse
 * le torse (coupe en ellipse), les manches tombent des épaules ; le dessin suit la poitrine et le dos.
 * Repères (mm) : bas du vêtement y = 0, poitrine ≈ 400, épaules ≈ 540, base du cou ≈ 580.
 */
const BODY = [[-30, 150], [0, 158], [120, 148], [200, 140], [300, 158], [400, 172], [470, 176], [520, 168], [550, 150], [572, 112], [582, 70], [586, 58]];
const DEPTH = 0.64; // épaisseur du torse / largeur
const lerpR = (y, pts) => {
  if (y <= pts[0][0]) return pts[0][1];
  for (let i = 1; i < pts.length; i++) if (y <= pts[i][0]) { const [y0, r0] = pts[i - 1], [y1, r1] = pts[i]; return r0 + (r1 - r0) * (y - y0) / (y1 - y0); }
  return pts[pts.length - 1][1];
};
const lathe = (y0, y1, off, n = 48, phi0 = 0, phiL = Math.PI * 2, seg = 96) => {
  const pts = [];
  for (let k = 0; k <= n; k++) { const y = y0 + (y1 - y0) * k / n; pts.push(new Vector2(lerpR(y, BODY) + off, y)); }
  const m = new LatheGeometry(pts, seg, phi0, phiL);
  return m;
};

async function garment(r, faces, color, hoodie) {
  const g = new Group();
  const form = new MeshStandardMaterial({ color: '#d8cfbd', roughness: 1 });
  const wood = new MeshStandardMaterial({ color: '#6b4a2b', roughness: 0.55 });
  const mat = new MeshStandardMaterial({ color: new Color(color), roughness: 0.93, side: DoubleSide });
  const ease = hoodie ? 14 : 8; // aisance du vêtement autour du buste
  const top = hoodie ? 560 : 566, hem = hoodie ? 10 : -10;
  // buste (visible au cou et sous le vêtement), cou, pied
  const torso = new Mesh(lathe(-60, 586, 0), form); torso.scale.z = DEPTH; g.add(torso);
  const neck = new Mesh(new CylinderGeometry(50, 56, 90, 40), form); neck.position.y = 625; neck.scale.z = 0.85; g.add(neck);
  const cap = new Mesh(new SphereGeometry(50, 32, 12, 0, Math.PI * 2, 0, Math.PI / 2), wood); cap.position.y = 670; cap.scale.set(1, 0.3, 0.85); g.add(cap);
  const bottom = new Mesh(new CircleGeometry(150, 48), form); bottom.rotation.x = Math.PI / 2; bottom.position.y = -60; bottom.scale.y = DEPTH; g.add(bottom);
  const pole = new Mesh(new CylinderGeometry(11, 11, 300, 16), wood); pole.position.y = -210; g.add(pole);
  for (let k = 0; k < 3; k++) {
    const leg = new Mesh(new CylinderGeometry(8, 9, 260, 12), wood);
    const a = k * Math.PI * 2 / 3 + Math.PI / 6;
    leg.position.set(Math.sin(a) * 100, -405, Math.cos(a) * 100); leg.rotation.set(Math.cos(a) * 1.05, 0, -Math.sin(a) * 1.05); g.add(leg);
  }
  // corps du vêtement
  const body = new Mesh(lathe(hem, top, ease), mat); body.scale.z = DEPTH; g.add(body);
  const shoulder = new Mesh(lathe(top - 2, 586, ease * 0.6, 12), mat); shoulder.scale.z = DEPTH; g.add(shoulder);
  // encolure en côte
  const rib = new Mesh(new TorusGeometry(hoodie ? 74 : 66, hoodie ? 9 : 6, 12, 64), mat);
  rib.rotation.x = Math.PI / 2 - 0.12; rib.position.set(0, 586, 4); rib.scale.set(1, 0.86, 1); g.add(rib);
  // manches : elles partent des épaules et tombent le long du buste (courtes ou longues)
  const sl = hoodie ? 560 : 210;
  for (const side of [-1, 1]) {
    const sleeve = new Mesh(new CylinderGeometry(hoodie ? 50 : 62, 66, sl, 40, 1, true), mat);
    sleeve.geometry.translate(0, -sl / 2, 0);
    sleeve.position.set(side * 150, 540, 0); sleeve.rotation.z = side * (hoodie ? 0.14 : 0.3); sleeve.scale.z = 0.8; g.add(sleeve);
    if (hoodie) { // poignet en côte
      const cuff = new Mesh(new CylinderGeometry(46, 50, 50, 32, 1, true), mat);
      cuff.position.set(side * (150 + Math.sin(0.14) * (sl + 20)), 540 - Math.cos(0.14) * (sl + 20), 0); cuff.rotation.z = side * 0.14; cuff.scale.z = 0.82; g.add(cuff);
    }
  }
  if (hoodie) {
    const waist = new Mesh(lathe(hem - 40, hem, ease - 6, 4), mat); waist.scale.z = DEPTH; g.add(waist);
    const hood = new Mesh(new SphereGeometry(140, 40, 24, 0, Math.PI * 2, 0, Math.PI * 0.55), mat);
    hood.position.set(0, 560, -95); hood.scale.set(0.9, 0.7, 0.62); hood.rotation.x = -1.15; g.add(hood);
    const pocket = new Mesh(lathe(hem + 30, hem + 190, ease + 4, 8, -0.6, 1.2, 32), mat); pocket.scale.z = DEPTH; g.add(pocket);
  }
  // dessin : un morceau du vêtement, un peu au-dessus, découpé à la taille de la face
  const place = { avant: [0, hoodie ? 488 : 500], dos: [Math.PI, hoodie ? 470 : 520] };
  for (const [k, f] of Object.entries(faces)) {
    if (!f.svg || !place[k]) continue;
    const [phiC, yTop] = place[k];
    const rr = lerpR(yTop - f.h / 2, BODY) + ease + 1.2;
    const pw = f.w / rr;
    const patch = new Mesh(lathe(yTop - f.h, yTop, ease + 1.2 + (hoodie && k === 'avant' ? 0 : 0), 40, phiC - pw / 2, pw, 64), decal(await faceTexture(r, f), { roughness: 0.9, side: DoubleSide }));
    patch.scale.z = DEPTH; g.add(patch);
  }
  g.userData.view = [0.35, 0.12];
  g.userData.focus = [new Vector3(0, hoodie ? 330 : 320, 0), hoodie ? 470 : 400]; // cadrer le vêtement, pas le pied
  return g;
}

/**
 * Casquette « baseball » : calotte haute à six panneaux (coutures, œillets, bouton), visière
 * incurvée qui plonge vers l'avant et sur les côtés ; le dessin épouse le panneau avant.
 */
async function cap(r, faces, color) {
  const R = 92, SY = 1.02, SZ = 1.08; // calotte : un peu plus haute et plus profonde que large
  const g = new Group();
  const mat = cloth(color);
  const dark = new MeshStandardMaterial({ color: new Color(color).multiplyScalar(0.62), roughness: 0.95 });
  const crown = new Mesh(new SphereGeometry(R, 96, 48, 0, Math.PI * 2, 0, Math.PI / 2), mat);
  crown.scale.set(1, SY, SZ); g.add(crown);
  const inside = new Mesh(new SphereGeometry(R - 1.5, 64, 24, 0, Math.PI * 2, 0, Math.PI / 2), new MeshStandardMaterial({ color: new Color(color).multiplyScalar(0.5), roughness: 1, side: BackSide }));
  inside.scale.set(1, SY, SZ); g.add(inside);
  // point de la calotte à l'azimut a (0 = devant) et à l'angle t depuis le sommet
  const at = (a, t, k = 1) => new Vector3(Math.sin(a) * Math.sin(t) * R * k, Math.cos(t) * R * SY * k, Math.cos(a) * Math.sin(t) * R * SZ * k);
  for (let i = 0; i < 6; i++) { // coutures entre les panneaux
    const a = Math.PI / 6 + i * Math.PI / 3, pts = [];
    for (let t = 0.03; t <= Math.PI / 2 + 0.001; t += Math.PI / 40) pts.push(at(a, t, 1.004));
    g.add(new Mesh(new TubeGeometry(new CatmullRomCurve3(pts), 40, 0.9, 6), dark));
    const eye = new Mesh(new TorusGeometry(2.6, 0.9, 8, 20), dark); // œillet d'aération
    eye.position.copy(at(a + Math.PI / 6, 0.62, 1.006)); eye.lookAt(at(a + Math.PI / 6, 0.62, 2)); g.add(eye);
  }
  const btn = new Mesh(new SphereGeometry(7, 20, 10), mat); btn.scale.y = 0.45; btn.position.y = R * SY; g.add(btn);
  // visière : collée au bas de l'avant de la calotte, elle avance de 75 mm et se courbe vers le bas
  const NA = 64, NV = 10, A = 1.2, L = 76, pos = [], idx = [];
  const brimPt = (a, v, off = 0) => {
    const fwd = L * Math.pow(Math.cos(a), 1.3) * v;
    const x = Math.sin(a) * R * (1 + 0.02 * v), z = Math.cos(a) * R * SZ + fwd;
    const y = 1 - 16 * Math.pow(Math.sin(a), 4) * Math.pow(v, 1.1) + off; // droite devant, incurvée sur les côtés
    return [x, y, z];
  };
  for (const off of [0, -2.6]) {
    const base = pos.length / 3;
    for (let i = 0; i <= NA; i++) for (let j = 0; j <= NV; j++) pos.push(...brimPt(-A + 2 * A * i / NA, j / NV, off));
    for (let i = 0; i < NA; i++) for (let j = 0; j < NV; j++) {
      const q = base + i * (NV + 1) + j;
      off ? idx.push(q, q + 1, q + NV + 1, q + 1, q + NV + 2, q + NV + 1) : idx.push(q, q + NV + 1, q + 1, q + 1, q + NV + 1, q + NV + 2);
    }
  }
  const bg = new BufferGeometry(); bg.setAttribute('position', new Float32BufferAttribute(pos, 3)); bg.setIndex(idx); bg.computeVertexNormals();
  g.add(new Mesh(bg, [mat][0]));
  const rimPts = []; for (let i = 0; i <= NA; i++) rimPts.push(new Vector3(...brimPt(-A + 2 * A * i / NA, 1, -1.3)));
  g.add(new Mesh(new TubeGeometry(new CatmullRomCurve3(rimPts), 96, 1.5, 8), dark));
  for (let k = 1; k <= 4; k++) { // surpiqûres de la visière
    const sp = []; for (let i = 0; i <= NA; i++) sp.push(new Vector3(...brimPt(-A + 2 * A * i / NA, 1 - k * 0.07, 0.35)));
    g.add(new Mesh(new TubeGeometry(new CatmullRomCurve3(sp), 96, 0.45, 5), dark));
  }
  const f = faces.avant || Object.values(faces)[0];
  if (f && f.svg) { // le dessin, posé sur le panneau avant, juste au-dessus de la visière
    const pl = f.w / R, tl = f.h / (R * SY), t0 = Math.PI / 2 - 0.1 - tl;
    const patch = new Mesh(new SphereGeometry(R + 0.5, 64, 32, Math.PI / 2 - pl / 2, pl, t0, tl), decal(await faceTexture(r, f), { roughness: 0.7 }));
    patch.scale.set(1, SY, SZ); g.add(patch);
  }
  g.userData.view = [0.6, 0.22];
  return g;
}

const BUILD = {
  mug: (r, f, c) => mug(r, f, c, false), 'mug-email': (r, f, c) => mug(r, f, c, true),
  paper, sticker, scarf, tote, tee: (r, f, c) => garment(r, f, c, false), hoodie: (r, f, c) => garment(r, f, c, true), cap,
};

/** Ombre douce au sol, sous l'objet. */
function contactShadow(size) {
  const c = document.createElement('canvas'); c.width = c.height = 128;
  const g = c.getContext('2d'), gr = g.createRadialGradient(64, 64, 4, 64, 64, 64);
  gr.addColorStop(0, 'rgba(14,31,77,.28)'); gr.addColorStop(1, 'rgba(14,31,77,0)');
  g.fillStyle = gr; g.fillRect(0, 0, 128, 128);
  const m = new Mesh(new PlaneGeometry(size, size * 0.5), new MeshBasicMaterial({ map: new CanvasTexture(c), transparent: true, depthWrite: false }));
  m.rotation.x = -Math.PI / 2;
  return m;
}

/**
 * Visionneuse dans un élément. set({kind, color, faces}) reconstruit l'objet (après un changement
 * de texte ou de couleur), en gardant l'angle de vue choisi par le client.
 */
export function viewer(el) {
  const renderer = new WebGLRenderer({ antialias: true, alpha: true, preserveDrawingBuffer: false });
  renderer.setPixelRatio(Math.min(2, window.devicePixelRatio || 1));
  renderer.outputColorSpace = SRGBColorSpace;
  renderer.toneMapping = ACESFilmicToneMapping;
  renderer.toneMappingExposure = 0.9;
  el.appendChild(renderer.domElement);
  const scene = new Scene();
  const pm = new PMREMGenerator(renderer);
  scene.environment = pm.fromScene(new RoomEnvironment(), 0.04).texture;
  scene.environmentIntensity = 0.55;
  const key = new DirectionalLight('#ffffff', 1.6); key.position.set(300, 500, 600); scene.add(key);
  const camera = new PerspectiveCamera(30, 1, 1, 20000);
  const controls = new OrbitControls(camera, renderer.domElement);
  Object.assign(controls, { enableDamping: true, dampingFactor: 0.08, enablePan: false, autoRotate: true, autoRotateSpeed: 1.2 });
  controls.addEventListener('start', () => { controls.autoRotate = false; });
  let obj = null, shadow = null, framed = false, alive = true, n = 0;
  const resize = () => {
    const w = el.clientWidth || 400, h = el.clientHeight || w;
    renderer.setSize(w, h, false); camera.aspect = w / h; camera.updateProjectionMatrix();
  };
  const ro = new ResizeObserver(resize); ro.observe(el); resize();
  const loop = () => { if (!alive) return; controls.update(); renderer.render(scene, camera); requestAnimationFrame(loop); };
  loop();
  return {
    async set({ kind, color, faces }) {
      const my = ++n;
      const g = await (BUILD[kind] || paper)(renderer, faces, color);
      if (my !== n || !alive) return;
      if (obj) { scene.remove(obj); obj.traverse(o => { o.geometry?.dispose(); [].concat(o.material || []).forEach(m => { m.map?.dispose(); m.dispose(); }); }); }
      if (shadow) scene.remove(shadow);
      obj = g; scene.add(g);
      const box = new Box3().setFromObject(g), sph = box.getBoundingSphere(new Sphere());
      const c = g.userData.focus ? g.userData.focus[0] : box.getCenter(new Vector3());
      if (g.userData.focus) sph.radius = g.userData.focus[1];
      shadow = contactShadow(sph.radius * 2.2); shadow.position.set(c.x, box.min.y - 1, 0); scene.add(shadow);
      if (!framed) {
        framed = true;
        const d = sph.radius / Math.sin((camera.fov * Math.PI / 180) / 2) * 1.08;
        const [az, el2] = g.userData.view || [0.3, 0.1];
        camera.position.set(c.x + Math.sin(az) * Math.cos(el2) * d, c.y + Math.sin(el2) * d, c.z + Math.cos(az) * Math.cos(el2) * d);
        controls.target.copy(c); controls.minDistance = d * 0.35; controls.maxDistance = d * 2;
        camera.near = d / 100; camera.far = d * 10; camera.updateProjectionMatrix();
      }
    },
    destroy() { alive = false; ro.disconnect(); controls.dispose(); renderer.dispose(); renderer.domElement.remove(); },
  };
}
