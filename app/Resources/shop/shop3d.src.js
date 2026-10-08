/**
 * Boutique : aperçu 3D d'un article, à faire tourner à la souris ou au doigt (Three.js).
 * Papier (poster, carte), sticker et écharpe sont construits ici même par le code ; mug, mug émaillé,
 * tote bag, t-shirt, sweat et casquette sont de vrais modèles 3D (Sketchfab, CC BY), teintés ici.
 * Le dessin vient du fichier d'impression (SVG vectoriel du serveur, mêmes tracés que le PDF) :
 * il est posé comme une décalcomanie sur la zone imprimable du produit. Unités : millimètres.
 *
 * Source de public/assets/js/shop3d.js : après modification, refaire le fichier avec
 * bin/build-shop3d.sh (esbuild, Three.js épinglé, tout dans un seul fichier minifié).
 */
import {
  WebGLRenderer, Scene, PerspectiveCamera, Group, Mesh, Color, SRGBColorSpace, ACESFilmicToneMapping,
  MeshStandardMaterial, MeshPhysicalMaterial, MeshBasicMaterial, CanvasTexture, DoubleSide,
  CylinderGeometry, BoxGeometry, PlaneGeometry, PMREMGenerator, DirectionalLight, Box3, Vector3, Sphere,
  BufferGeometry, Float32BufferAttribute, Raycaster, Euler,
} from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { DecalGeometry } from 'three/examples/jsm/geometries/DecalGeometry.js';
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

// ------------------------------------------------------------------ modèles 3D téléchargés

/*
 * Mug, mug émaillé, tote bag, t-shirt, sweat et casquette sont de vrais modèles 3D (Sketchfab,
 * licence CC BY : crédit des auteurs affiché sous la vue 3D, voir ShopPages::MODELS_3D). Ils ont
 * été allégés (bin/build-shop3d-models.sh : sans textures, maillage simplifié, mis à l'échelle en
 * millimètres, devant vers +z) ; ici on les teinte de la couleur du produit et on y projette le dessin.
 */
const loader = new GLTFLoader();
const models = new Map();
const loadModel = async url => {
  if (!models.has(url)) models.set(url, loader.loadAsync(url).then(g => g.scene));
  const m = (await models.get(url)).clone(true);
  m.traverse(o => { if (o.isMesh) o.geometry = o.geometry.clone(); });
  return m;
};
const meshesOf = root => { const l = []; root.updateMatrixWorld(true); root.traverse(o => { if (o.isMesh) l.push(o); }); return l; };

/** Teinte : chaque matière prend la couleur du produit, sauf les finitions gardées (cordons, liseré…). */
function tint(root, color, keep = {}, extra = {}) {
  for (const o of meshesOf(root)) {
    const name = o.material.name, fixed = keep[name];
    const c = fixed === true ? o.material.color.clone() : new Color(fixed || color);
    o.material = extra.glossy && !fixed
      ? new MeshPhysicalMaterial({ color: c, roughness: 0.18, clearcoat: 1, clearcoatRoughness: 0.08, side: DoubleSide })
      : new MeshStandardMaterial({ color: c, roughness: extra.roughness ?? 0.92, metalness: extra.metalness ?? 0, side: DoubleSide });
  }
}

/** Projection à plat (t-shirt, sweat, casquette, tote) : le dessin, centré en x, haut du dessin à yTop, de face ou de dos. */
async function stamp(r, g, root, f, x, yTop, back = false, mat = {}) {
  const meshes = meshesOf(root), dir = back ? -1 : 1;
  const hit = new Raycaster(new Vector3(x, yTop - f.h / 2, dir * 5000), new Vector3(0, 0, -dir)).intersectObjects(meshes)[0];
  if (!hit) return;
  const map = await faceTexture(r, f);
  for (const m of meshes) {
    const geo = new DecalGeometry(m, hit.point, new Euler(0, back ? Math.PI : 0, 0), new Vector3(f.w, f.h, 120));
    if (geo.attributes.position.count) g.add(new Mesh(geo, decal(map, { side: DoubleSide, ...mat })));
  }
}

/**
 * Projection cylindrique (mugs) : le dessin fait le tour, son centre face à nous, la jointure derrière
 * (l'anse). On reprend la paroi extérieure du modèle, à peine décollée, et la position du dessin est
 * calculée pixel par pixel (angle autour de l'axe, hauteur) : net même sur un maillage grossier.
 */
async function wrap(r, g, root, f, R, yc, mat) {
  const pos = [], p = new Vector3(), tri = [0, 0, 0].map(() => new Vector3()), n = new Vector3();
  const y0 = yc - f.h / 2, y1 = yc + f.h / 2;
  for (const m of meshesOf(root)) {
    const a = m.geometry.attributes.position, idx = m.geometry.index;
    const count = idx ? idx.count : a.count;
    for (let i = 0; i < count; i += 3) {
      for (let k = 0; k < 3; k++) tri[k].fromBufferAttribute(a, idx ? idx.getX(i + k) : i + k).applyMatrix4(m.matrixWorld);
      // paroi extérieure seulement (ni l'intérieur, ni le fond, ni l'anse), dans la hauteur du dessin
      if (tri.some(t => Math.abs(Math.hypot(t.x, t.z) - R) > R * 0.1) || tri.every(t => t.y < y0) || tri.every(t => t.y > y1)) continue;
      n.subVectors(tri[1], tri[0]).cross(p.subVectors(tri[2], tri[0])).normalize();
      const cx = (tri[0].x + tri[1].x + tri[2].x) / 3, cz = (tri[0].z + tri[1].z + tri[2].z) / 3;
      if (Math.abs(n.x * cx + n.z * cz) / Math.hypot(cx, cz) < 0.6) continue;
      for (const t of tri) { const s = (Math.hypot(t.x, t.z) + 0.25) / Math.hypot(t.x, t.z); pos.push(t.x * s, t.y, t.z * s); }
    }
  }
  const geo = new BufferGeometry();
  geo.setAttribute('position', new Float32BufferAttribute(pos, 3));
  geo.setAttribute('uv', new Float32BufferAttribute(new Float32Array(pos.length / 3 * 2), 2));
  geo.computeVertexNormals();
  const m = decal(await faceTexture(r, f), { side: DoubleSide, ...mat });
  m.onBeforeCompile = sh => {
    sh.uniforms.uWrap = { value: new Vector3(R / f.w, y0, 1 / f.h) };
    sh.vertexShader = 'varying vec3 vWrap;\n' + sh.vertexShader.replace('#include <begin_vertex>', '#include <begin_vertex>\nvWrap = transformed;');
    sh.fragmentShader = 'uniform vec3 uWrap;\nvarying vec3 vWrap;\n' + sh.fragmentShader.replace('#include <map_fragment>', `
      vec2 wuv = vec2(0.5 + atan(vWrap.x, vWrap.z) * uWrap.x, (vWrap.y - uWrap.y) * uWrap.z);
      if (wuv.x < 0.0 || wuv.x > 1.0 || wuv.y < 0.0 || wuv.y > 1.0) discard;
      diffuseColor *= texture2D(map, wuv);`);
  };
  g.add(new Mesh(geo, m));
}

/** Mug : mis à l'échelle pour que son tour corresponde à la largeur de la face « tour complet ». */
async function mug(r, faces, color, url, enamel) {
  const f = faces.tour || Object.values(faces)[0];
  const g = new Group(), root = await loadModel(url);
  // axe et rayon du corps, sous le bord et au-dessus du pied : l'anse, fine et derrière (vers -z),
  // ne touche ni aux côtés (x) ni au devant (z max)
  const box = new Box3().setFromObject(root), h = box.max.y - box.min.y;
  let x0 = Infinity, x1 = -Infinity, z1 = -Infinity;
  for (const m of meshesOf(root)) {
    const a = m.geometry.attributes.position, v = new Vector3();
    for (let i = 0; i < a.count; i++) {
      v.fromBufferAttribute(a, i).applyMatrix4(m.matrixWorld);
      if (v.y > box.min.y + h * 0.02 && v.y < box.max.y - h * 0.08) { x0 = Math.min(x0, v.x); x1 = Math.max(x1, v.x); z1 = Math.max(z1, v.z); }
    }
  }
  // le tour du mug = la largeur de la face, sa hauteur = celle du dessin plus les marges
  const rx = (x1 - x0) / 2, R = f.w / (2 * Math.PI), s = R / rx, sy = (f.h + (enamel ? 8 : 12)) / h;
  root.scale.set(s, sy, s); root.position.set(-(x0 + x1) / 2 * s, -box.min.y * sy, -(z1 - rx) * s);
  tint(root, color, enamel ? { material: '#0E1F4D' } : {}, enamel ? { roughness: 0.25, metalness: 0.15 } : { glossy: true });
  g.add(root);
  if (f.svg) await wrap(r, g, root, f, R, h * sy / 2 - (enamel ? 1 : 0), { roughness: 0.2 });
  g.userData.view = [0.8, 0.18]; // trois quarts : l'anse apparaît sur le côté
  return g;
}

/** Tote bag : le dessin au centre du devant, sous les anses. */
async function tote(r, faces, color, url) {
  const f = Object.values(faces)[0];
  const g = new Group(), root = await loadModel(url);
  tint(root, color);
  g.add(root);
  if (f.svg) await stamp(r, g, root, f, 0, PLACE.tote.avant);
  g.userData.view = [0.3, 0.1];
  return g;
}

/** Vêtements et casquette : la teinte, puis chaque face projetée à sa place (devant, dos). */
async function wear(r, faces, color, url, kind) {
  const g = new Group(), root = await loadModel(url);
  tint(root, color, KEEP[kind]);
  g.add(root);
  for (const [k, f] of Object.entries(faces)) {
    const y = PLACE[kind][k];
    if (f.svg && y) await stamp(r, g, root, f, 0, y, k === 'dos', { roughness: 0.9 });
  }
  g.userData.view = kind === 'cap' ? [0.6, 0.22] : [0.35, 0.12];
  return g;
}

/** Haut du dessin (mm depuis le bas du modèle) pour chaque face ; finitions qui gardent leur couleur. */
const PLACE = {
  tote: { avant: 430 },
  tee: { avant: 590, dos: 620 },
  hoodie: { avant: 500, dos: 520 },
  cap: { avant: 105 },
};
const KEEP = { hoodie: { Straps_FRONT_1944954: true, Material125269: true } };

const BUILD = {
  mug: (r, f, c, u) => mug(r, f, c, u, false), 'mug-email': (r, f, c, u) => mug(r, f, c, u, true),
  paper, sticker, scarf, tote,
  tee: (r, f, c, u) => wear(r, f, c, u, 'tee'), hoodie: (r, f, c, u) => wear(r, f, c, u, 'hoodie'), cap: (r, f, c, u) => wear(r, f, c, u, 'cap'),
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
 * Visionneuse dans un élément. set({kind, color, faces, model}) reconstruit l'objet (après un changement
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
    async set({ kind, color, faces, model }) {
      const my = ++n;
      const g = await (BUILD[kind] || paper)(renderer, faces, color, model);
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

/**
 * Photo d'un article en haute définition, sur fond transparent (livre des récits : « Ton maillot »).
 * view = [azimut, hauteur] en radians (π : vu de dos). Renvoie un Blob PNG de w × h pixels.
 */
export async function snapshot({ kind, color, faces, model }, { w = 1800, h = 2000, view = [Math.PI, 0.08], zoom = 1.04 } = {}) {
  const canvas = document.createElement('canvas');
  canvas.width = w; canvas.height = h;
  const renderer = new WebGLRenderer({ canvas, antialias: true, alpha: true, preserveDrawingBuffer: true });
  renderer.setPixelRatio(1);
  renderer.setSize(w, h, false);
  renderer.outputColorSpace = SRGBColorSpace;
  renderer.toneMapping = ACESFilmicToneMapping;
  renderer.toneMappingExposure = 0.95;
  const scene = new Scene();
  const pm = new PMREMGenerator(renderer);
  scene.environment = pm.fromScene(new RoomEnvironment(), 0.04).texture;
  scene.environmentIntensity = 0.6;
  const key = new DirectionalLight('#ffffff', 1.7);
  const g = await (BUILD[kind] || paper)(renderer, faces, color, model);
  scene.add(g);
  const box = new Box3().setFromObject(g), sph = box.getBoundingSphere(new Sphere()), c = box.getCenter(new Vector3());
  const camera = new PerspectiveCamera(26, w / h, 1, 20000);
  const d = sph.radius / Math.sin((camera.fov * Math.PI / 180) / 2) * zoom;
  const [az, el2] = view;
  camera.position.set(c.x + Math.sin(az) * Math.cos(el2) * d, c.y + Math.sin(el2) * d, c.z + Math.cos(az) * Math.cos(el2) * d);
  camera.lookAt(c);
  camera.near = d / 100; camera.far = d * 10; camera.updateProjectionMatrix();
  key.position.set(camera.position.x + 300, camera.position.y + 500, camera.position.z);
  scene.add(key);
  renderer.render(scene, camera);
  const blob = await new Promise(ok => canvas.toBlob(ok, 'image/png'));
  g.traverse(o => { o.geometry?.dispose(); [].concat(o.material || []).forEach(m => { m.map?.dispose(); m.dispose(); }); });
  renderer.dispose();
  return blob;
}
