// Plan topographique sombre du territoire (relief, courbes de niveau, lacs), en grande image fixe pour la
// séquence de marque et la coda. Usage : node scripts/teaser/sombre.mjs [largeur hauteur lng lat zoom]
import { writeFileSync } from 'node:fs';
import path from 'node:path';
import { chromium } from '@playwright/test';
import { GL, routeLocal, W } from './commun.mjs';

const [w, h, lng, lat, zoom] = (process.argv.length > 2 ? process.argv.slice(2) : ['3840', '2160', '6.255', '46.712', '11.25']).map(Number);
const browser = await chromium.launch({ args: GL });
const ctx = await browser.newContext({ viewport: { width: w, height: h } });
await routeLocal(ctx);
const page = await ctx.newPage();
page.on('pageerror', (e) => console.log('erreur', e.message));
await page.goto('https://local/sombre.html');
await page.evaluate((o) => window.go(o), { w, h, center: [lng, lat], zoom });
await page.screenshot({ path: path.join(W, 'topo.png') });
writeFileSync(path.join(W, 'topo.json'), JSON.stringify({ w, h, lng, lat, zoom }));
await browser.close();
console.log(`plan topographique ${w} × ${h} → ${path.relative(process.cwd(), path.join(W, 'topo.png'))}`);
