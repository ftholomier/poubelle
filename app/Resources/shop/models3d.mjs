/**
 * Modèles 3D de la boutique : téléchargés depuis Sketchfab (licence CC BY, crédit dans
 * ShopPages::MODELS_3D) puis allégés pour l'aperçu : sans textures (le produit est teinté par
 * shop3d.js), maillage soudé et simplifié, positions quantifiées, mis en millimètres, posé à y = 0,
 * le devant vers +z. Lancé par bin/build-shop3d-models.sh (il faut SKETCHFAB_TOKEN).
 */
import { NodeIO, getBounds } from '@gltf-transform/core';
import { ALL_EXTENSIONS } from '@gltf-transform/extensions';
import { flatten, join, weld, simplify, dedup, prune, quantize, transformMesh, clearNodeTransform } from '@gltf-transform/functions';
import { MeshoptSimplifier } from 'meshoptimizer';
import { mat4 } from 'gl-matrix';
import { writeFileSync } from 'node:fs';

// uid Sketchfab ; part de triangles gardés ; rotation (degrés) pour mettre le devant vers +z ; hauteur (mm)
const MODELS = {
  mug: { uid: 'd0d4a48e5f5e4f1a9044c001007f18e4', ratio: 0.4, height: 96 },
  'mug-emaille': { uid: '669f3a1e56a94be69ba155d6bc08c762', rotY: 90, height: 80 },
  tote: { uid: '12b908ac5af54e79bb0aed793e7c66c7', height: 620 },
  tshirt: { uid: 'c1a3e5eb9b5445f4b7d4be82f1127eba', ratio: 0.08, error: 0.01, rotY: 20, height: 720 },
  sweat: { uid: '97611a53e3b846f69e0655b210f72b2f', ratio: 0.1, error: 0.01, height: 700 },
  casquette: { uid: '75d11d363e1c4884a714a776049ea4a0', ratio: 0.2, height: 120 },
};
const [out] = process.argv.slice(2);
const token = process.env.SKETCHFAB_TOKEN;
const io = new NodeIO().registerExtensions(ALL_EXTENSIONS);

for (const [name, c] of Object.entries(MODELS)) {
  const info = await (await fetch(`https://api.sketchfab.com/v3/models/${c.uid}`)).json();
  const dl = await (await fetch(`https://api.sketchfab.com/v3/models/${c.uid}/download`, { headers: { Authorization: `Token ${token}` } })).json();
  if (!dl.glb) throw new Error(`${name} : pas de GLB (${JSON.stringify(dl)})`);
  const doc = await io.readBinary(new Uint8Array(await (await fetch(dl.glb.url)).arrayBuffer()));
  const root = doc.getRoot(), scene = root.getDefaultScene() || root.listScenes()[0];
  for (const t of root.listTextures()) t.dispose();
  await doc.transform(dedup(), flatten());
  for (const n of root.listNodes()) if (n.getMesh()) clearNodeTransform(n);
  for (const m of root.listMeshes()) for (const p of m.listPrimitives()) p.setAttribute('TEXCOORD_0', null);
  await doc.transform(join({ keepNamed: false }), weld(), simplify({ simplifier: MeshoptSimplifier, ratio: c.ratio ?? 1, error: c.error ?? 0.002 }), prune());
  const rot = mat4.create();
  mat4.rotateY(rot, rot, (c.rotY || 0) * Math.PI / 180);
  for (const m of root.listMeshes()) transformMesh(m, rot);
  const b = getBounds(scene), s = c.height / (b.max[1] - b.min[1]), fit = mat4.create();
  mat4.scale(fit, fit, [s, s, s]);
  mat4.translate(fit, fit, [-(b.min[0] + b.max[0]) / 2, -b.min[1], -(b.min[2] + b.max[2]) / 2]);
  for (const m of root.listMeshes()) transformMesh(m, fit);
  await doc.transform(prune(), quantize({ quantizePosition: 14, quantizeNormal: 8 }));
  root.getAsset().extras = { title: info.name, author: info.user.displayName, source: info.viewerUrl, license: info.license?.label };
  writeFileSync(`${out}/${name}.glb`, await io.writeBinary(doc));
  console.log(name, '—', info.name, 'par', info.user.displayName, '·', info.license?.label);
}
