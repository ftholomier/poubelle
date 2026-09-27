// Rendu Chromium : HTML → PDF A4, contrôle des débordements, positions des champs.
// Usage : node render.cjs <entrée.html> <sortie.pdf> [--boxes champs.json]
const path = require('path');
const fs = require('fs');
const { chromium } = require('playwright'); // installé globalement : build.sh renseigne NODE_PATH

(async () => {
  const [input, output] = process.argv.slice(2);
  const boxesIdx = process.argv.indexOf('--boxes');
  const browser = await chromium.launch();
  const page = await browser.newPage();
  await page.emulateMedia({ media: 'print' });
  await page.goto('file://' + path.resolve(input), { waitUntil: 'load' });
  await page.evaluate(() => document.fonts.ready);

  const report = await page.evaluate(() => {
    const res = [];
    for (const p of document.querySelectorAll('.page')) {
      const body = p.querySelector('.page-body');
      const kids = [...body.children];
      const bottom = Math.max(...kids.map(k => k.getBoundingClientRect().bottom));
      const room = body.getBoundingClientRect().bottom - bottom;
      res.push({ id: p.id, overflow: body.scrollHeight - body.clientHeight, freePx: Math.round(room) });
    }
    const fonts = [...document.fonts].map(f => `${f.family}:${f.status}`);
    return { pages: res, fonts };
  });
  console.log(JSON.stringify(report));

  if (boxesIdx > -1) {
    const boxes = await page.evaluate(() => {
      const out = [];
      for (const p of document.querySelectorAll('.page')) {
        const pr = p.getBoundingClientRect();
        for (const el of p.querySelectorAll('[data-field]')) {
          const r = el.getBoundingClientRect();
          out.push({ page: p.id, name: el.dataset.field, kind: el.classList.contains('cb') ? 'checkbox'
            : el.classList.contains('sig-line') ? 'signature' : 'text',
            x: r.left - pr.left, y: r.top - pr.top, w: r.width, h: r.height });
        }
      }
      return out;
    });
    fs.writeFileSync(process.argv[boxesIdx + 1], JSON.stringify(boxes, null, 1));
    console.log('champs :', boxes.length);
  }

  await page.pdf({ path: output, preferCSSPageSize: true, printBackground: true, tagged: true, outline: true });
  await browser.close();
  console.log('écrit', output);
})();
