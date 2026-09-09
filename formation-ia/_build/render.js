// Rendu des documents : HTML -> PDF A4 paginé, ou HTML -> PNG haute densité.
// Usage : node render.js pdf  entree.html sortie.pdf "Titre du pied de page"
//         node render.js png  entree.html sortie.png [selecteur]
const { chromium } = require('playwright');
const path = require('path');

const [, , mode, input, output, arg4] = process.argv;

(async () => {
  const browser = await chromium.launch({
    executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome',
    args: ['--no-sandbox', '--font-render-hinting=none'],
  });
  const page = await browser.newPage({ deviceScaleFactor: mode === 'png' ? 2 : 1 });
  await page.goto('file://' + path.resolve(input), { waitUntil: 'load' });
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(250);

  if (mode === 'pdfraw') {
    // pagination pilotée par le CSS @page du document (planches en paysage)
    await page.pdf({ path: output, printBackground: true, preferCSSPageSize: true });
  } else if (mode === 'pdf') {
    const foot = (arg4 || '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
    await page.pdf({
      path: output,
      format: 'A4',
      printBackground: true,
      displayHeaderFooter: true,
      headerTemplate: '<div></div>',
      footerTemplate: `
        <div style="width:100%;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                    font-size:7pt;color:#8A959D;padding:0 18mm;
                    display:flex;justify-content:space-between;align-items:center;
                    -webkit-print-color-adjust:exact;">
          <span style="letter-spacing:.06em;">${foot}</span>
          <span style="font-variant-numeric:tabular-nums;">
            <span class="pageNumber"></span> / <span class="totalPages"></span>
          </span>
        </div>`,
      margin: { top: '17mm', right: '18mm', bottom: '20mm', left: '18mm' },
    });
  } else {
    const sel = arg4 || '.canvas';
    const el = await page.$(sel);
    await (el || page).screenshot({ path: output, ...(el ? {} : { fullPage: true }) });
  }

  await browser.close();
  console.log('✓', path.basename(output));
})().catch((e) => { console.error('✗', e.message); process.exit(1); });
