// Captures fixes de l'application pour le site commercial (site-terricom/) : même environnement que le teaser
// (fond de carte réel, photos libres), sans curseur. Préalable : serveur de production et scripts/teaser/preparer.sh.
// Usage : node scripts/teaser/captures-site.mjs [dossier de sortie]   (ONLY=nom1,nom2 pour n'en refaire que certaines)
import { mkdirSync } from 'node:fs';
import path from 'node:path';
import { context, geoPx, goto, launch, saveMissing } from './capture.mjs';
import { RACINE } from './commun.mjs';

const OUT = path.resolve(process.argv[2] ?? path.join(RACINE, '..', 'site-terricom', 'sources', 'captures'));
mkdirSync(OUT, { recursive: true });
const ONLY = process.env.ONLY ? new Set(process.env.ONLY.split(',')) : null;

const wait = async (page, n = 20) => {
  for (let i = 0; i < n; i++) {
    await page.clock.runFor(100);
    await new Promise((r) => setTimeout(r, 40));
  }
};
const hideCursor = (page) => page.evaluate(() => window.__curSet?.(-100, -100, false, false, false));

// [nom, espace, chemin, action facultative]
const SHOTS = [
  ['portail-accueil', null, '/haut-doubs'],
  ['portail-ouvert', null, '/haut-doubs', (p) => p.evaluate(() => window.scrollTo(0, 620))],
  ['portail-explorer', null, '/haut-doubs/explorer'],
  ['portail-fromagerie', null, '/haut-doubs/explorer?q=fromagerie'],
  [
    'carte-metabief',
    null,
    '/haut-doubs/explorer?q=fromagerie',
    async (p) => {
      for (let i = 0; i < 3; i++) {
        const pt = await geoPx(p, 6.3585, 46.7672);
        await p.mouse.move(pt.x, pt.y);
        await p.mouse.wheel(0, -120);
        await wait(p, 8);
      }
    },
  ],
  ['commune-metabief', null, '/haut-doubs/metabief'],
  ['commune-metabief-bas', null, '/haut-doubs/metabief', (p) => p.evaluate(() => window.scrollTo(0, 700))],
  ['communes', null, '/haut-doubs/communes'],
  ['fiche', null, '/haut-doubs/jougne/boulangerie/boulangerie-de-demonstration', (p) => p.evaluate(() => window.scrollTo(0, 380))],
  ['campagne', null, '/haut-doubs/campagnes/exemple-noel-chez-vos-commercants'],
  ['circuit', null, '/haut-doubs/circuits/exemple-circuit-des-savoir-faire'],
  ['agenda', null, '/haut-doubs/agenda'],
  ['emploi', null, '/haut-doubs/emploi'],
  ['bo-tableau', 'haut-doubs', '/collectivite'],
  [
    'bo-ia',
    'haut-doubs',
    '/collectivite/campagnes',
    async (p) => {
      await p.fill('#ai-prompt', 'Noël dans le Haut-Doubs : un calendrier de l’Avent avec les commerces des 32 communes');
      await p.getByRole('button', { name: /Préparer/ }).click();
      await wait(p, 30);
      await p.evaluate(() => window.scrollTo(0, 260));
    },
  ],
  ['bo-sirene', 'haut-doubs', '/collectivite/entreprises/sirene'],
  ['bo-entreprises', 'haut-doubs', '/collectivite/entreprises'],
  ['bo-stats', 'haut-doubs', '/collectivite/statistiques'],
  ['bo-newsletter', 'haut-doubs', '/collectivite/newsletter'],
  ['bo-personnalisation', 'haut-doubs', '/collectivite/personnalisation'],
  ['mairie', 'haut-doubs-commune', '/collectivite'],
  ['pro-tableau', 'pro', '/demo/entrer/pro'],
  [
    'pro-publications',
    'pro',
    `/demo/entrer/pro?vers=${encodeURIComponent('/pro/{est}/publications')}`,
    async (p) => {
      await p.locator('input[placeholder^="ex."]').first().fill('Arrivage de mont-d’or au lait cru pour les fêtes');
      await p.getByRole('button', { name: /Rédiger pour tous mes canaux/ }).click();
      await wait(p, 30);
    },
  ],
  ['pro-kit', 'pro', `/demo/entrer/pro?vers=${encodeURIComponent('/pro/{est}/kit')}`],
  ['mobile-accueil', null, '/haut-doubs', null, true],
  ['mobile-fiche', null, '/haut-doubs/jougne/boulangerie/boulangerie-de-demonstration', (p) => p.evaluate(() => window.scrollTo(0, 260)), true],
  ['mobile-explorer', null, '/haut-doubs/explorer?q=fromagerie', null, true],
  ['mobile-campagne', null, '/haut-doubs/campagnes/exemple-noel-chez-vos-commercants', null, true],
];

const browser = await launch();
const ctxs = {};
for (const [name, space, url, action, mobile] of SHOTS) {
  if (ONLY && !ONLY.has(name)) continue;
  const key = `${space}:${mobile ? 'm' : 'd'}`;
  ctxs[key] ??= await context(browser, mobile ? { space, w: 390, h: 844, dpr: 3, mobile: true } : { space, w: 1440, h: 900, dpr: 1.5 });
  const page = await ctxs[key].newPage();
  await goto(page, url);
  await wait(page, 10);
  if (action) await action(page);
  await wait(page, 25);
  await page.evaluate(() => window.__urls?.());
  await hideCursor(page);
  await page.screenshot({ path: path.join(OUT, `${name}.png`) });
  console.log(name, page.url().replace(/^https?:\/\/[^/]+/, ''));
  await page.close();
}
for (const c of Object.values(ctxs)) saveMissing(c, 'site');
await browser.close();
