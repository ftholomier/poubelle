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
  Shape, PMREMGenerator, DirectionalLight, Box3, Vector3, Sphere,
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

/** Silhouette de vêtement (vue de face, mm) : t-shirt, ou sweat à manches longues. */
function garmentShape(long) {
  const s = new Shape();
  const P = long
    ? [[95, 330], [215, 310], [385, -170], [315, -205], [255, 150], [260, -330]]
    : [[95, 345], [215, 325], [335, 205], [280, 140], [255, 205], [262, -360]];
  const [neck, shoulder, sleeveOut, sleeveIn, armpit, hem] = P;
  s.moveTo(-neck[0], neck[1]);
  s.quadraticCurveTo(0, neck[1] - 85, neck[0], neck[1]);
  for (const p of [shoulder, sleeveOut, sleeveIn, armpit, hem]) s.lineTo(p[0], p[1]);
  s.quadraticCurveTo(0, hem[1] - 8, -hem[0], hem[1]);
  for (const p of [armpit, sleeveIn, sleeveOut, shoulder]) s.lineTo(-p[0], p[1]);
  s.lineTo(-neck[0], neck[1]);
  return s;
}

async function garment(r, faces, color, hoodie) {
  const depth = 6, bevel = 16;
  const geo = new ExtrudeGeometry(garmentShape(hoodie), { depth, bevelEnabled: true, bevelThickness: bevel, bevelSize: 14, bevelSegments: 8, curveSegments: 24 });
  geo.translate(0, 0, -depth / 2);
  const g = new Group();
  const mat = cloth(color);
  g.add(new Mesh(geo, mat));
  const front = depth / 2 + bevel;
  // encolure (côte)
  const collar = new Mesh(new TorusGeometry(hoodie ? 92 : 88, 9, 12, 64, Math.PI), mat);
  collar.rotation.z = Math.PI; collar.position.set(0, (hoodie ? 330 : 345) - 2, front - 22); collar.scale.set(1, 0.95, 1); g.add(collar);
  if (hoodie) {
    const hood = new Mesh(new SphereGeometry(150, 40, 24, 0, Math.PI * 2, 0, Math.PI * 0.62), mat);
    hood.position.set(0, 320, -30); hood.scale.set(0.95, 0.85, 0.5); hood.rotation.x = -0.35; g.add(hood);
    const pocket = new Mesh(new BoxGeometry(300, 150, 6), mat);
    pocket.position.set(0, -230, front + 1); g.add(pocket);
  }
  const place = { avant: [hoodie ? 250 : 280, 1], dos: [hoodie ? 230 : 270, -1] };
  for (const [k, f] of Object.entries(faces)) {
    if (!f.svg) continue;
    const side = (place[k] || [0, 1])[1];
    const top = (side > 0 ? (hoodie ? 230 : 250) : (hoodie ? 260 : 300));
    const p = new Mesh(new PlaneGeometry(f.w, f.h), decal(await faceTexture(r, f), { roughness: 0.9 }));
    p.position.set(0, top - f.h / 2, side * (front + 0.4));
    if (side < 0) p.rotation.y = Math.PI;
    g.add(p);
  }
  g.userData.view = [0.25, 0.1];
  return g;
}

/** Casquette : calotte, visière, bouton ; le dessin épouse l'avant de la calotte. */
async function cap(r, faces, color) {
  const R = 95, sy = 0.78;
  const g = new Group();
  const mat = cloth(color);
  const crown = new Mesh(new SphereGeometry(R, 72, 36, 0, Math.PI * 2, 0, Math.PI / 2), mat);
  crown.scale.y = sy; g.add(crown);
  const band = new Mesh(new CylinderGeometry(R, R, 8, 72, 1, true), new MeshStandardMaterial({ color: new Color(color).multiplyScalar(0.8), roughness: 0.95, side: DoubleSide }));
  band.position.y = 3; g.add(band);
  const bs = new Shape();
  bs.absellipse(0, 0, 92, 78, Math.PI, 0, true);
  bs.lineTo(-92, 0);
  const brim = new Mesh(new ExtrudeGeometry(bs, { depth: 3, bevelEnabled: true, bevelThickness: 1, bevelSize: 1, bevelSegments: 2, curveSegments: 48 }), mat);
  brim.rotation.x = Math.PI / 2 - 0.18; brim.position.set(0, 1, R - 18); g.add(brim);
  const btn = new Mesh(new SphereGeometry(6, 16, 8), mat); btn.position.y = R * sy; g.add(btn);
  for (let i = 0; i < 6; i++) { // coutures des six panneaux
    const s = new Mesh(new TorusGeometry(R + 0.3, 0.5, 4, 64, Math.PI / 2), new MeshStandardMaterial({ color: new Color(color).multiplyScalar(0.7), roughness: 1 }));
    s.rotation.set(0, i * Math.PI / 3, Math.PI / 2); s.scale.set(sy, 1, 1); s.rotation.order = 'YXZ'; g.add(s);
  }
  const f = faces.avant || Object.values(faces)[0];
  if (f && f.svg) {
    const pl = f.w / R, tl = f.h / (R * sy) * 0.95, t0 = Math.PI / 2 - 0.14 - tl;
    const patch = new Mesh(new SphereGeometry(R + 0.6, 64, 32, Math.PI / 2 - pl / 2, pl, t0, tl), decal(await faceTexture(r, f), { roughness: 0.6 }));
    patch.scale.y = sy; g.add(patch);
  }
  g.userData.view = [0.35, 0.35];
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
      const box = new Box3().setFromObject(g), sph = box.getBoundingSphere(new Sphere()), c = box.getCenter(new Vector3());
      shadow = contactShadow(sph.radius * 2.2); shadow.position.set(c.x, box.min.y - 1, c.z); scene.add(shadow);
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
