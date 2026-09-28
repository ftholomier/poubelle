// Captures d'écran du dossier de réalisation (docs/dossier/captures), prises sur une compilation de production
// lancée en mode démonstration (DEMO_MODE=true) ; la barre de démonstration est masquée.
// Usage : node scripts/dossier-captures.mjs [base] [dossier]   (ONLY=nom1,nom2 : ne refaire que ces captures)
import { chromium } from '@playwright/test';
const BASE = process.argv[2] ?? 'http://localhost:3100';
const OUT = process.argv[3] ?? 'docs/dossier/captures';
const HIDE =
  'body { --demo-bar-h: 0px !important; } .demo-bar, nextjs-portal { display: none !important; } * { animation: none !important; transition: none !important; caret-color: transparent !important; }';

const SHOTS = [
  // [nom, espace (null = public), chemin, largeur, hauteur, échelle, sélecteur facultatif]
  ['p-accueil', null, '/haut-doubs', 1440, 900, 1.5],
  ['p-ouvert', null, '/haut-doubs', 1440, 900, 1.5, 'section:has(h2:text("Ouvert près de vous"))'],
  ['p-explorer', null, '/haut-doubs/explorer', 1440, 900, 1.5],
  ['p-fiche', null, '/haut-doubs/jougne/boulangerie/boulangerie-de-demonstration', 1440, 900, 1.5],
  ['p-campagne', null, '/haut-doubs/campagnes/exemple-noel-chez-vos-commercants', 1440, 900, 1.5],
  ['p-circuit', null, '/haut-doubs/circuits/exemple-circuit-des-savoir-faire', 1440, 900, 1.5],
  ['p-agenda', null, '/haut-doubs/agenda', 1440, 900, 1.5],
  ['m-accueil', null, '/haut-doubs', 390, 844, 2],
  ['m-fiche', null, '/haut-doubs/jougne/boulangerie/boulangerie-de-demonstration', 390, 844, 2],
  ['m-explorer-de', null, '/haut-doubs/explorer?lang=de', 390, 844, 2],
  ['e-tableau', 'pro', '/pro/{est}', 1440, 900, 1.5],
  ['e-publications', 'pro', '/pro/{est}/publications', 1440, 900, 1.5],
  ['e-fiche', 'pro', '/pro/{est}/fiche', 1440, 900, 1.5],
  ['e-minisite', 'pro-communication', '/pro/{est}/site', 1440, 900, 1.5],
  ['c-tableau', 'collectivite', '/collectivite', 1440, 900, 1.5],
  ['c-campagnes', 'collectivite', '/collectivite/campagnes', 1440, 900, 1.5],
  ['c-newsletter', 'collectivite', '/collectivite/newsletter', 1440, 900, 1.5],
  ['c-entreprises', 'collectivite', '/collectivite/entreprises', 1440, 900, 1.5],
  ['s-console', 'console', '/console', 1440, 900, 1.5],
  ['s-territoires', 'console', '/console/territoires', 1440, 900, 1.5],
  ['s-simulateur', 'console', '/console/simulateur', 1440, 900, 1.5],
  ['m-site', null, '/', 1440, 900, 1.5],
  ['m-marque', null, '/marque', 1440, 900, 1.5],
  ['p-minisite', null, '/haut-doubs/metabief/epicerie/commerce-de-demonstration', 1440, 900, 1.5],
  ['e-synchro', 'pro-communication', '/pro/{est}/synchronisation', 1440, 900, 1.5],
  ['c-personnalisation', 'collectivite', '/collectivite/personnalisation', 1440, 900, 1.5],
  ['c-circuits', 'collectivite', '/collectivite/circuits', 1440, 900, 1.5],
  ['c-statistiques', 'collectivite', '/collectivite/statistiques', 1440, 900, 1.5],
  ['s-audit', 'console', '/console/audit', 1440, 900, 1.5],
  ['s-facturation', 'console', '/console/facturation', 1440, 900, 1.5],
  ['s-ia', 'console', '/console/ia', 1440, 900, 1.5],
  ['p-commune', null, '/haut-doubs/metabief', 1440, 900, 1.5],
  ['p-emploi', null, '/haut-doubs/emploi', 1440, 900, 1.5],
];
// ONLY=nom1,nom2 : ne refaire que ces captures.
const ONLY = process.env.ONLY ? new Set(process.env.ONLY.split(',')) : null;

const browser = await chromium.launch();
const report = [];
const contexts = {};
async function ctxFor(space, w, h, scale) {
  const key = `${space ?? 'public'}:${w}:${scale}`;
  if (contexts[key]) return contexts[key];
  const ctx = await browser.newContext({
    viewport: { width: w, height: h },
    deviceScaleFactor: scale,
    locale: 'fr-FR',
    timezoneId: 'Europe/Paris',
    reducedMotion: 'reduce',
  });
  if (space) {
    const page = await ctx.newPage();
    await page.goto(`${BASE}/demo/entrer/${space}`, { waitUntil: 'domcontentloaded' });
    await page.close();
  }
  contexts[key] = ctx;
  return ctx;
}
for (const [name, space, path, w, h, scale, selector] of SHOTS) {
  if (ONLY && !ONLY.has(name)) continue;
  const ctx = await ctxFor(space, w, h, scale);
  const page = await ctx.newPage();
  let url = `${BASE}${path}`;
  if (path.includes('{est}')) {
    await page.goto(`${BASE}/demo/entrer/${space}?vers=${encodeURIComponent(path)}`, { waitUntil: 'networkidle' });
  } else {
    await page.goto(url, { waitUntil: 'networkidle' }).catch(() => undefined);
  }
  await page.addStyleTag({ content: HIDE });
  await page.waitForTimeout(700);
  const file = `${OUT}/${name}.jpg`;
  try {
    if (selector) {
      const el = page.locator(selector).first();
      await el.scrollIntoViewIfNeeded();
      await page.waitForTimeout(300);
      await el.screenshot({ path: file, type: 'jpeg', quality: 84 });
    } else {
      await page.screenshot({ path: file, type: 'jpeg', quality: 84 });
    }
    report.push(`${name} ok ${page.url().replace(BASE, '')}`);
  } catch (e) {
    report.push(`${name} ERREUR ${String(e).slice(0, 120)}`);
  }
  await page.close();
}
console.log(report.join('\n'));
await browser.close();
