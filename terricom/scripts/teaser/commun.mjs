// Chemins et utilitaires communs à la fabrication du teaser.
import { existsSync, readFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const ICI = path.dirname(fileURLToPath(import.meta.url));
export const RACINE = path.resolve(ICI, '../..');
/** Dossier de travail (données, tuiles, captures, images) : volumineux, jamais versionné. */
export const W = path.resolve(process.env.TEASER_DIR ?? path.join(RACINE, '.teaser'));

const TYPES = {
  html: 'text/html',
  js: 'text/javascript',
  css: 'text/css',
  json: 'application/json',
  png: 'image/png',
  jpg: 'image/jpeg',
  woff2: 'font/woff2',
  mp3: 'audio/mpeg',
};
export const type = (f) => TYPES[f.split('.').pop()] ?? 'application/octet-stream';

const FONTS = {
  'bricolage-grotesque-latin-opsz-normal.woff2': 'node_modules/@fontsource-variable/bricolage-grotesque/files',
  'instrument-sans-latin-wght-normal.woff2': 'node_modules/@fontsource-variable/instrument-sans/files',
  'instrument-sans-latin-wght-italic.woff2': 'node_modules/@fontsource-variable/instrument-sans/files',
};

/** Résout un chemin demandé par les pages du teaser : pages et polices du dépôt, le reste dans le dossier de travail. */
export function resoudre(p) {
  const nom = path.basename(p);
  if (p.startsWith('fonts/') && FONTS[nom]) return path.join(RACINE, FONTS[nom], nom);
  if (['carte.html', 'sombre.html'].includes(p)) return path.join(ICI, p);
  if (p === 'comp/teaser.html') return path.join(RACINE, 'docs/teaser/teaser.html');
  if (p === 'maplibre-gl.js' || p === 'maplibre-gl.css') return path.join(W, 'node_modules/maplibre-gl/dist', p);
  if (p === 'mlcontour.js') return path.join(W, 'node_modules/maplibre-contour/dist/index.min.js');
  const f = path.join(W, p);
  return f.startsWith(W) ? f : null;
}

/** Sert https://local/… (pages de rendu de carte) depuis le disque. */
export async function routeLocal(ctx) {
  await ctx.route('https://local/**', (route) => {
    const f = resoudre(decodeURIComponent(new URL(route.request().url()).pathname.slice(1)));
    if (!f || !existsSync(f)) return route.fulfill({ status: 404, body: '' });
    route.fulfill({ status: 200, body: readFileSync(f), headers: { 'content-type': type(f), 'access-control-allow-origin': '*' } });
  });
}

export const GL = ['--enable-unsafe-swiftshader', '--ignore-gpu-blocklist'];
