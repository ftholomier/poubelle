// playwright local, global, ou chemin explicite via PLAYWRIGHT_PATH
const pw = (() => {
  for (const p of [process.env.PLAYWRIGHT_PATH, 'playwright',
                   '/opt/node22/lib/node_modules/playwright']) {
    if (!p) continue;
    try { return require(p); } catch (e) { /* suivant */ }
  }
  throw new Error('playwright introuvable — npm i -g playwright');
})();
const { chromium } = pw;
const fs = require('fs');
const path = require('path');

const ICI = process.env.SORTIE || path.join(__dirname, 'build');
const noms = JSON.parse(fs.readFileSync(path.join(ICI, 'html', 'noms.json'), 'utf8'));

(async () => {
  const nav = await chromium.launch();
  const page = await nav.newPage({ viewport: { width: 1920, height: 1080 },
                                   deviceScaleFactor: 1 });
  await page.goto('file://' + path.join(ICI, 'html', 'cartons.html'));
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(600);

  let n = 0;
  for (const nom of noms.cartons) {
    await page.locator('#' + nom).screenshot({
      path: path.join(ICI, 'cards', nom + '.png') });
    n++;
  }
  for (const nom of noms.incrusts) {
    await page.locator('#' + nom).screenshot({
      path: path.join(ICI, 'cards', nom + '.png'), omitBackground: true });
    n++;
  }
  await nav.close();
  console.log(n + ' images rendues');
})();
