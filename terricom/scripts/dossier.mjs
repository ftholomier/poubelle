// Génère le dossier de réalisation en PDF à partir de docs/dossier/dossier.html (Chromium, via Playwright).
// Usage : node scripts/dossier.mjs [--apercus <dossier>]   (aperçus PNG page par page, pour relecture)
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { chromium } from '@playwright/test';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const source = path.join(root, 'docs/dossier/dossier.html');
const output = path.join(root, 'docs/dossier/terricom-dossier-de-realisation.pdf');
const i = process.argv.indexOf('--apercus');
const previews = i > 0 ? path.resolve(process.argv[i + 1] ?? 'apercus') : null;

const browser = await chromium.launch();
try {
  const page = await browser.newPage({ viewport: { width: 1000, height: 1200 }, deviceScaleFactor: previews ? 1.25 : 1 });
  await page.goto(pathToFileURL(source).href, { waitUntil: 'load' });
  const report = await page.evaluate(async () => {
    await document.fonts.ready;
    return { total: window.__dossier.total, pages: window.__measure() };
  });

  // Un bloc qui dépasse est coupé à l'impression : on refuse de produire le PDF.
  const errors = report.pages.filter((p) => p.free < 0 || p.wide.length);
  for (const p of report.pages) {
    const flag = p.free < 0 ? '  ← dépasse' : p.wide.length ? '  ← déborde sur le côté' : '';
    console.log(`page ${String(p.page).padStart(2, '0')} : ${p.free} mm libres${flag}`);
    for (const w of p.wide) console.log(`    ${w}`);
  }
  if (errors.length) {
    console.error(`\n${errors.length} page(s) à corriger avant de produire le PDF.`);
    process.exitCode = 1;
  }

  if (previews) {
    mkdirSync(previews, { recursive: true });
    const sections = page.locator('.page');
    for (let n = 0; n < report.total; n++) {
      await sections.nth(n).screenshot({ path: path.join(previews, `page-${String(n + 1).padStart(2, '0')}.png`) });
    }
    console.log(`Aperçus : ${previews}`);
  }

  if (!errors.length) {
    await page.emulateMedia({ media: 'print' });
    await page.pdf({ path: output, printBackground: true, preferCSSPageSize: true });
    console.log(`\n${report.total} pages → ${path.relative(root, output)}`);
  }
} finally {
  await browser.close();
}
