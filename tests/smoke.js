/**
 * Parcours de contrôle : ouvre les principales pages du site et du back-office et signale
 * les pages en erreur, les erreurs JavaScript et les blocages de la politique CSP.
 *
 * Prérequis : Node.js et Playwright (npm install playwright).
 * Usage :
 *   SR_BASE=http://127.0.0.1:8080 node tests/smoke.js
 *   SR_EMAIL=… SR_PASSWORD=… node tests/smoke.js     (ajoute les écrans du back-office)
 * Avec un compte, la connexion se fait d'abord : le site est alors parcouru comme le voit
 * l'équipe, ce qui permet de le vérifier même fermé au public (page d'attente, mot de passe).
 * Code de sortie 1 si un problème est trouvé.
 */
const { chromium } = require('playwright');

const BASE = (process.env.SR_BASE || 'http://127.0.0.1:8080').replace(/\/$/, '');
const FRONT = ['/', '/matchs/', '/nos-lions/', '/nos-lions/joueurs/', '/supporters/', '/infrastructures/', '/symboles/',
  '/saisons/', '/face-a-face/', '/records/', '/recherche/?q=bonal', '/interactif/', '/interactif/quiz/',
  '/interactif/album/', '/interactif/maillots/', '/interactif/frise/', '/interactif/carto/', '/centenaire/',
  '/centenaire/100-moments/', '/reserves/', '/contact/', '/contribuer/', '/faire-un-don/', '/newsletter/',
  '/mentions-legales/', '/confidentialite/', '/cookies/', '/en/', '/en/matchs/', '/reserves/', '/bilans/coupe-de-france/',
  '/interactif/carto/', '/en/interactif/quiz/', '/matchs/annees-90/', '/records/', '/bilans/stade-auguste-bonal/',
  '/interactif/retro-direct/', '/interactif/retro-direct/sochaux-le-puy-division-2-28-02-1988/', '/en/interactif/retro-direct/',
  '/interactif/fil-jaune/', '/interactif/fil-jaune/franck-sauzee/', '/interactif/fil-jaune/franck-sauzee/gilles-rousset/', '/en/interactif/fil-jaune/', '/joueurs/franck-sauzee/',
  '/interactif/souvenirs/', '/en/interactif/souvenirs/', '/matchs/1996-1997/sochaux-toulon-division-2-19-10-1996/',
  '/chiffres/', '/en/chiffres/', '/interactif/planche-contact/', '/interactif/le-lion-illustre/', '/interactif/mur-du-vestiaire/',
  '/interactif/mosaique/', '/interactif/mosaique/?motif=1928&decennie=1990', '/en/interactif/le-lion-illustre/'];
const ADMIN = ['/admin', '/admin/qualite', '/admin/qualite?nouveau=1', '/admin/qualite?cat=site', '/admin/qualite?cat=orthographe', '/admin/collection/dictionnaire', '/admin/journal', '/admin/matchs', '/admin/personnes', '/admin/articles',
  '/admin/objets', '/admin/referentiels', '/admin/medias', '/admin/accueil', '/admin/moments', '/admin/rubriques',
  '/admin/redirections', '/admin/page-attente', '/admin/interactif', '/admin/onze', '/admin/retro-direct', '/admin/souvenirs', '/admin/murs-photos', '/admin/medias?murs=sans-auteur', '/admin/aide/interactif', '/admin/contributions',
  '/admin/messages', '/admin/newsletter', '/admin/dons', '/admin/traductions', '/admin/assistant', '/admin/couts-ia', '/admin/reglages?groupe=couts', '/admin/audio', '/admin/reglages?groupe=audio',
  '/admin/sauvegardes', '/admin/taches', '/admin/profil', '/admin/fiche/nouvelle/match', '/admin/fiche/nouvelle/personne',
  '/admin/fiche/nouvelle/article', '/admin/fiche/nouvelle/objet', '/admin/fiche/nouvelle/moment', '/admin/corbeille', '/admin/audience',
  '/admin/album', '/admin/collection/quiz', '/admin/collection/frise', '/admin/collection/maillots', '/admin/collection/epopees',
  '/admin/collection/lieux', '/admin/collection/partenaires', '/admin/collection/dons', '/admin/utilisateurs', '/admin/reglages',
  '/admin/aide', '/admin/aide/prise-en-main', '/admin/aide/matchs', '/admin/aide/faq', '/admin/aide?q=composition', '/admin/aide/imprimer', '/admin/aide/memo'];

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  const problems = [];
  page.on('pageerror', e => problems.push('[JS] ' + page.url() + ' : ' + e.message));
  page.on('console', m => {
    if (m.type() === 'error' && /Content Security Policy|Refused to/i.test(m.text())) problems.push('[CSP] ' + page.url() + ' : ' + m.text().slice(0, 200));
  });
  const visit = async (u) => {
    const res = await page.goto(BASE + u, { waitUntil: 'networkidle' }).catch(e => null);
    const code = res ? res.status() : 0;
    if (code >= 400 || code === 0) problems.push('[HTTP ' + code + '] ' + u);
    console.log(String(code).padEnd(4) + u);
  };

  // Connexion d'abord (si un compte est fourni) : le site fermé au public reste visible de l'équipe.
  let logged = false;
  if (process.env.SR_EMAIL && process.env.SR_PASSWORD) {
    await page.goto(BASE + '/admin/connexion');
    await page.fill('input[name=email]', process.env.SR_EMAIL);
    await page.fill('input[name=password]', process.env.SR_PASSWORD);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    logged = !page.url().includes('/connexion');
    if (!logged) problems.push('[ADMIN] connexion refusée');
  }

  // Une fiche match et une fiche personne prises dans les mosaïques.
  for (const u of FRONT) await visit(u);
  for (const sel of ['/matchs/', '/nos-lions/joueurs/']) {
    await page.goto(BASE + sel, { waitUntil: 'networkidle' });
    const href = await page.locator('main a[href^="/matchs/20"], main a[href^="/matchs/19"], main a[href^="/joueurs/"]').first().getAttribute('href').catch(() => null);
    if (href) await visit(href);
  }

  if (logged) {
    for (const u of ADMIN) await visit(u);
  }

  await browser.close();
  console.log(problems.length ? '\n' + problems.join('\n') : '\nAucun problème.');
  process.exit(problems.length ? 1 : 0);
})();
