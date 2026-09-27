// Tuiles raster du fond de carte (512 px, affichées en 256 par Leaflet, donc nettes à l'écran), rendues par
// MapLibre depuis carte.html, par métatuiles de 4 × 4. Deux modes :
//   node scripts/teaser/tuiles.mjs --zone '[{"z":12,"bbox":[5.85,46.45,6.75,47.0]}]'
//   node scripts/teaser/tuiles.mjs --liste .teaser/manquantes.txt   (tuiles relevées pendant les captures)
// Jeu de données : SET=core (détaillé, zooms 13 et plus) ou SET=wide (petites échelles).
import { existsSync, mkdirSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';
import { GL, routeLocal, W } from './commun.mjs';

const SET = process.env.SET ?? 'core';
const SIZE = 2048;
const arg = (k) => {
  const i = process.argv.indexOf(k);
  return i > 0 ? process.argv[i + 1] : null;
};
const T = (lng, lat, z) => {
  const n = 2 ** z;
  return [Math.floor(((lng + 180) / 360) * n), Math.floor(((1 - Math.asinh(Math.tan((lat * Math.PI) / 180)) / Math.PI) / 2) * n)];
};

// Métatuiles à rendre
const metas = new Set();
if (arg('--zone')) {
  for (const { z, bbox } of JSON.parse(arg('--zone'))) {
    const [x0, y0] = T(bbox[0], bbox[3], z);
    const [x1, y1] = T(bbox[2], bbox[1], z);
    for (let x = x0; x <= x1; x++) for (let y = y0; y <= y1; y++) metas.add(`${z}/${Math.floor(x / 4)}/${Math.floor(y / 4)}`);
  }
}
if (arg('--liste')) {
  for (const l of readFileSync(arg('--liste'), 'utf8').split('\n').filter(Boolean)) {
    const [z, x, y] = l.split('/').map(Number);
    if ((SET === 'core') !== z >= 13) continue;
    metas.add(`${z}/${Math.floor(x / 4)}/${Math.floor(y / 4)}`);
  }
}
const todo = [...metas].filter((k) => {
  const [z, mx, my] = k.split('/').map(Number);
  for (let i = 0; i < 4; i++)
    for (let j = 0; j < 4; j++) if (!existsSync(path.join(W, 'tuiles', String(z), String(mx * 4 + i), `${my * 4 + j}.jpg`))) return true;
  return false;
});
console.log(`${todo.length} métatuiles à rendre (jeu ${SET})`);
if (!todo.length) process.exit(0);

const browser = await chromium.launch({ args: GL });
const ctx = await browser.newContext({ viewport: { width: SIZE, height: SIZE } });
await routeLocal(ctx);
const page = await ctx.newPage();
await page.goto(`https://local/carte.html?set=${SET}`);
await page.evaluate(() => window.ready);
await page.evaluate((s) => window.setup(s), SIZE);
const t0 = Date.now();
let n = 0;
for (const k of todo) {
  const [z, mx, my] = k.split('/').map(Number);
  await page.evaluate(([z, cx, cy, s]) => window.render(z, cx, cy, s), [z, mx * SIZE, my * SIZE, SIZE]);
  for (let i = 0; i < 4; i++)
    for (let j = 0; j < 4; j++) {
      const f = path.join(W, 'tuiles', String(z), String(mx * 4 + i), `${my * 4 + j}.jpg`);
      if (existsSync(f)) continue;
      mkdirSync(path.dirname(f), { recursive: true });
      await page.screenshot({ path: f, type: 'jpeg', quality: 90, clip: { x: i * 512, y: j * 512, width: 512, height: 512 } });
    }
  if (++n % 5 === 0) console.log(`${n} / ${todo.length} (${((Date.now() - t0) / 1000).toFixed(0)} s)`);
}
await browser.close();
