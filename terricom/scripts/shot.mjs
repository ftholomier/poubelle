// Captures d'écran de vérification visuelle : node scripts/shot.mjs <url> <fichier.png> [largeur] [hauteur] [cookie]
import { chromium } from '@playwright/test';
const [, , url, out, w = '1440', h = '900', cookie, full = '1'] = process.argv;
const browser = await chromium.launch();
const ctx = await browser.newContext({ viewport: { width: Number(w), height: Number(h) }, deviceScaleFactor: 1, locale: 'fr-FR', timezoneId: 'Europe/Paris' });
if (cookie) {
  const u = new URL(url);
  await ctx.addCookies([{ name: 'tc_session', value: cookie, domain: u.hostname, path: '/' }]);
}
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
const res = await page.goto(url, { waitUntil: 'networkidle', timeout: 60000 }).catch((e) => { errors.push(String(e)); return null; });
await page.waitForTimeout(600);
await page.screenshot({ path: out, fullPage: full === '1' });
console.log(JSON.stringify({ status: res?.status(), errors: errors.filter((e) => !/Failed to load resource|ERR_|net::/.test(e)).slice(0, 8) }));
await browser.close();
