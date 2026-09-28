// Génère la présentation aux élus en PDF paysage à partir de docs/presentation/presentation.html.
// Chaque diapositive « construite » (data-build) donne une page par étape, pour simuler l'animation.
// Usage : node scripts/presentation.mjs [--apercus <dossier>]
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { chromium } from '@playwright/test';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = path.join(root, 'docs/presentation/presentation.html');
const output = path.join(root, 'docs/presentation/terricom-presentation-elus.pdf');
const i = process.argv.indexOf('--apercus');
const previews = i > 0 ? path.resolve(process.argv[i + 1] ?? 'apercus') : null;

const browser = await chromium.launch();
try {
  const page = await browser.newPage({ viewport: { width: 1280, height: 720 } });
  await page.goto(`${pathToFileURL(source).href}?print=1`, { waitUntil: 'load' });
  const report = await page.evaluate(async () => {
    await document.fonts.ready;
    return [...document.querySelectorAll('.slide')].map((s, n) => {
      const box = s.getBoundingClientRect();
      const limit = s.hasAttribute('data-nonum') ? box.bottom : box.bottom - 48;
      const bad = [];
      for (const el of s.querySelectorAll('*')) {
        if (el.closest('.blob, .foot, .later')) continue;
        const r = el.getBoundingClientRect();
        if (!r.width || !r.height) continue;
        if (r.right > box.right + 1 || r.left < box.left - 1 || r.bottom > limit)
          bad.push(`${el.tagName.toLowerCase()}.${el.className} « ${(el.textContent || '').trim().slice(0, 40)} »`);
      }
      return { n: n + 1, bad: bad.slice(0, 4) };
    });
  });
  const errors = report.filter((r) => r.bad.length);
  for (const r of errors) console.log(`diapositive ${r.n} :\n  ${r.bad.join('\n  ')}`);
  if (previews) {
    mkdirSync(previews, { recursive: true });
    const slides = page.locator('.slide');
    for (let n = 0; n < report.length; n++) await slides.nth(n).screenshot({ path: path.join(previews, `d-${String(n + 1).padStart(2, '0')}.png`) });
  }
  if (errors.length) {
    console.error(`${errors.length} diapositive(s) à corriger.`);
    process.exitCode = 1;
  } else {
    await page.emulateMedia({ media: 'print' });
    await page.pdf({ path: output, printBackground: true, preferCSSPageSize: true });
    console.log(`${report.length} pages → ${path.relative(root, output)}`);
  }
} finally {
  await browser.close();
}
