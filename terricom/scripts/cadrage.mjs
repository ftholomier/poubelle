// Génère la note de cadrage « Fonctionnalités à développer » en PDF (A4) à partir de docs/cadrage/fonctionnalites.html,
// lui-même produit par docs/cadrage/generer.py. Refuse de produire le PDF si un bloc déborde d'une page.
// Usage : python3 docs/cadrage/generer.py && node scripts/cadrage.mjs [--apercus <dossier>]
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { chromium } from '@playwright/test';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = path.join(root, 'docs/cadrage/fonctionnalites.html');
const output = path.join(root, 'docs/cadrage/terricom-fonctionnalites-a-developper.pdf');
const i = process.argv.indexOf('--apercus');
const previews = i > 0 ? path.resolve(process.argv[i + 1] ?? 'apercus') : null;

const browser = await chromium.launch();
try {
  const page = await browser.newPage({ viewport: { width: 1000, height: 1200 }, deviceScaleFactor: previews ? 1.4 : 1 });
  await page.goto(pathToFileURL(source).href, { waitUntil: 'load' });
  const report = await page.evaluate(async () => {
    await document.fonts.ready;
    const mm = 96 / 25.4;
    return [...document.querySelectorAll('.page')].map((p, n) => {
      const box = p.getBoundingClientRect();
      const limit = box.bottom - (p.querySelector('.foot') ? 20 * mm : 12 * mm);
      let lowest = box.top;
      const bad = [];
      for (const el of p.querySelectorAll('*')) {
        if (el.closest('.blob, .foot')) continue;
        const r = el.getBoundingClientRect();
        if (!r.width || !r.height) continue;
        lowest = Math.max(lowest, r.bottom);
        if (r.right > box.right - 8 * mm + 1 || r.bottom > limit) bad.push(`${el.tagName.toLowerCase()} « ${(el.textContent || '').trim().slice(0, 40)} »`);
      }
      return { page: n + 1, free: Math.round((limit - lowest) / mm), bad: bad.slice(0, 3) };
    });
  });
  for (const p of report) console.log(`page ${p.page} : ${p.free} mm libres${p.bad.length ? '  ← déborde : ' + p.bad.join(' | ') : ''}`);
  const errors = report.filter((p) => p.bad.length);
  if (previews) {
    mkdirSync(previews, { recursive: true });
    const pages = page.locator('.page');
    for (let n = 0; n < report.length; n++) await pages.nth(n).screenshot({ path: path.join(previews, `page-${String(n + 1).padStart(2, '0')}.png`) });
  }
  if (errors.length) {
    console.error(`${errors.length} page(s) à corriger.`);
    process.exitCode = 1;
  } else {
    await page.emulateMedia({ media: 'print' });
    await page.pdf({ path: output, printBackground: true, preferCSSPageSize: true });
    console.log(`${report.length} pages → ${path.relative(root, output)}`);
  }
} finally {
  await browser.close();
}
