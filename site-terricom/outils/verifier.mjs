// Vérifie le site construit : liens et ressources internes, débordements horizontaux, erreurs de page ;
// produit des captures pleine page (bureau et mobile) pour relecture visuelle.
// Usage : node outils/verifier.mjs [--captures dossier]   (Playwright est pris dans ../terricom/node_modules)
import { createRequire } from 'node:module';
import { createServer } from 'node:http';
import { existsSync, mkdirSync, readFileSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const ICI = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const PUB = path.join(ICI, 'public');
const require = createRequire(path.join(ICI, '..', 'terricom', 'package.json'));
const { chromium } = require('@playwright/test');
const TYPES = { html: 'text/html', css: 'text/css', js: 'text/javascript', json: 'application/json', webp: 'image/webp', jpg: 'image/jpeg', png: 'image/png', svg: 'image/svg+xml', woff2: 'font/woff2', mp4: 'video/mp4', webm: 'video/webm', xml: 'application/xml', txt: 'text/plain', webmanifest: 'application/manifest+json' };

// 1. Liens et ressources
const pages = readdirSync(PUB).filter((f) => f.endsWith('.html'));
let erreurs = 0;
for (const p of pages) {
  const html = readFileSync(path.join(PUB, p), 'utf8');
  for (const m of html.matchAll(/(?:href|src|poster)="([^"]+)"/g)) {
    const u = m[1];
    if (/^(https?:|mailto:|tel:|#|data:)/.test(u)) continue;
    const [f, hash] = u.split('#');
    const file = path.join(PUB, f.split('?')[0]);
    if (!existsSync(file)) {
      console.log(`✗ ${p} → ${u} introuvable`);
      erreurs++;
    } else if (hash && f.endsWith('.html')) {
      const cible = readFileSync(file, 'utf8');
      if (!cible.includes(`id="${hash}"`)) {
        console.log(`✗ ${p} → ${u} : ancre absente`);
        erreurs++;
      }
    }
  }
  for (const m of html.matchAll(/srcset="([^"]+)"/g))
    for (const part of m[1].split(','))
      if (!existsSync(path.join(PUB, part.trim().split(' ')[0]))) {
        console.log(`✗ ${p} → ${part.trim()} introuvable`);
        erreurs++;
      }
  if (/\{\{[a-z]/.test(html)) {
    console.log(`✗ ${p} : raccourci non remplacé`);
    erreurs++;
  }
}
console.log(`${pages.length} pages, ${erreurs} erreur(s) de lien`);

// 2. Rendu dans le navigateur
const server = createServer((req, res) => {
  let f = path.join(PUB, decodeURIComponent(new URL(req.url, 'http://x').pathname));
  if (existsSync(f) && statSync(f).isDirectory()) f = path.join(f, 'index.html');
  if (!existsSync(f)) return res.writeHead(404).end();
  res.writeHead(200, { 'content-type': TYPES[f.split('.').pop()] ?? 'application/octet-stream' }).end(readFileSync(f));
}).listen(8790);
const i = process.argv.indexOf('--captures');
const out = i > 0 ? path.resolve(process.argv[i + 1]) : null;
if (out) mkdirSync(out, { recursive: true });
const browser = await chromium.launch();
for (const [nom, vp] of [
  ['bureau', { width: 1440, height: 900 }],
  ['mobile', { width: 390, height: 844 }],
]) {
  const ctx = await browser.newContext({ viewport: vp, deviceScaleFactor: 1, reducedMotion: 'reduce' });
  for (const p of pages) {
    const page = await ctx.newPage();
    const errs = [];
    page.on('pageerror', (e) => errs.push(e.message));
    page.on('response', (r) => r.status() >= 400 && errs.push(`${r.status()} ${r.url()}`));
    await page.goto(`http://localhost:8790/${p}`, { waitUntil: 'networkidle' });
    const over = await page.evaluate(() => {
      const w = document.documentElement.clientWidth;
      return [...document.querySelectorAll('body *')]
        .filter((el) => {
          const r = el.getBoundingClientRect();
          return r.right > w + 1 && getComputedStyle(el).position !== 'fixed' && !el.closest('.marquee, .table-wrap, .nav, .mark-deco');
        })
        .slice(0, 3)
        .map((el) => `${el.tagName.toLowerCase()}.${el.className}`);
    });
    if (over.length) console.log(`⚠ ${nom} ${p} déborde : ${over.join(', ')}`);
    if (errs.length) console.log(`⚠ ${nom} ${p} : ${errs.slice(0, 3).join(' | ')}`);
    if (out) {
      await page.evaluate(async () => {
        for (let y = 0; y < document.body.scrollHeight; y += 700) {
          window.scrollTo(0, y);
          await new Promise((r) => setTimeout(r, 30));
        }
        window.scrollTo(0, 0);
      });
      await page.waitForTimeout(300);
      await page.screenshot({ path: path.join(out, `${nom}-${p.replace('.html', '')}.png`), fullPage: true });
    }
    await page.close();
  }
  await ctx.close();
}
await browser.close();
server.close();
process.exitCode = erreurs ? 1 : 0;
