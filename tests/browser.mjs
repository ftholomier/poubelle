/**
 * Tests de bout en bout, exécutés dans un vrai navigateur.
 *
 * Usage : node tests/browser.mjs [url] [chemin/vers/playwright] [adresse] [mot-de-passe]
 *
 * Ils vérifient ce que la suite PHP ne peut pas voir : le nuage s'affiche
 * réellement, il change de forme d'une section et d'une page à l'autre, la
 * mise en page ne déborde sur aucun écran, le site reste utilisable sans
 * WebGL, et le back-office fait ce qu'on attend de lui.
 */

const BASE = process.argv[2] || 'http://127.0.0.1:8000';
const PLAYWRIGHT = process.argv[3] || 'playwright';
const ADMIN_EMAIL = process.argv[4] || process.env.ADMIN_EMAIL || '';
const ADMIN_PASSWORD = process.argv[5] || process.env.ADMIN_PASSWORD || '';

const { chromium } = await import(PLAYWRIGHT);

let passed = 0;
const failures = [];

function check(label, result) {
  if (result === true) {
    passed++;
    console.log(`  \x1b[32m✓\x1b[0m ${label}`);
  } else {
    failures.push(`${label} : ${result}`);
    console.log(`  \x1b[31m✗\x1b[0m ${label} — ${result}`);
  }
}

function suite(name) {
  console.log(`\n\x1b[1m${name}\x1b[0m`);
}

/** Ignore les ressources externes, indisponibles hors ligne. */
const isExternal = (url = '') => /fonts\.(googleapis|gstatic)\.com/.test(url);

function watch(page, { ignore = [] } = {}) {
  const bag = [];
  const ignored = (text = '') => ignore.some((pattern) => pattern.test(text));

  page.on('pageerror', (e) => bag.push(`erreur JS : ${e.message}`));
  page.on('console', (m) => {
    const url = m.location()?.url || '';
    if (m.type() === 'error' && !isExternal(url) && !ignored(m.text()) && !ignored(url)) {
      bag.push(`console : ${m.text()}`);
    }
  });
  page.on('requestfailed', (r) => {
    // Quitter une page annule ce qu'elle chargeait encore : une police
    // interrompue par la navigation suivante n'est pas une panne du site.
    const cause = r.failure()?.errorText || '';
    if (cause.includes('ERR_ABORTED')) return;
    if (!isExternal(r.url())) bag.push(`requête échouée : ${r.url()} (${cause})`);
  });
  return bag;
}

/**
 * Amène une section à l'écran.
 *
 * scrollIntoView est inopérant ici : le contenu est déplacé par transformation
 * et ne défile pas lui-même. On reproduit donc ce que fait le site.
 */
async function scrollToSection(page, id) {
  await page.evaluate((sectionId) => {
    const element = document.getElementById(sectionId);
    window.scrollTo(0, element.getBoundingClientRect().top + window.scrollY);
  }, id);
}

/**
 * Amène à l'écran la section qui contient le premier élément visé.
 *
 * Les identifiants de section appartiennent au contenu : les écrire ici ferait
 * échouer la suite à la première réorganisation du site depuis le back-office.
 * On cherche donc l'effet — un bandeau, un compteur — et non un nom.
 */
async function scrollToFirst(page, selector) {
  return page.evaluate((sel) => {
    const target = document.querySelector(sel);
    const section = target?.closest('[data-section]');
    if (!section) return null;
    window.scrollTo(0, section.getBoundingClientRect().top + window.scrollY);
    return section.id;
  }, selector);
}

/**
 * Compte les pixels franchement colorés d'une zone.
 * Un statut « nuage calculé » ne prouve pas qu'il soit visible : seul le rendu le dit.
 */
async function litPixels(page, clip) {
  const shot = await page.screenshot({ clip });
  return page.evaluate(async (b64) => {
    const image = new Image();
    image.src = `data:image/png;base64,${b64}`;
    await image.decode();
    const canvas = document.createElement('canvas');
    canvas.width = image.width;
    canvas.height = image.height;
    const ctx = canvas.getContext('2d');
    ctx.drawImage(image, 0, 0);
    const data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
    let lit = 0;
    for (let i = 0; i < data.length; i += 4) {
      if (data[i] > 70 || data[i + 2] > 90) lit++;
    }
    return lit;
  }, shot.toString('base64'));
}

/**
 * Adresses du site, lues sur le site lui-même.
 *
 * Renommer ou réorganiser les pages depuis le back-office ne doit pas casser
 * la suite : elle demande la liste au menu plutôt que de la connaître.
 */
async function siteMap(browser) {
  const page = await browser.newPage();
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  const map = await page.evaluate(() => ({
    pages: [...document.querySelectorAll('.menu__link')].map((a) => a.getAttribute('href')),
    contact: document.querySelector('.menu__cta')?.getAttribute('href') || null,
  }));
  await page.close();
  return map;
}

const browser = await chromium.launch({
  executablePath: process.env.CHROMIUM_PATH || undefined,
  args: ['--use-gl=swiftshader', '--enable-unsafe-swiftshader', '--no-sandbox'],
});

const SITE = await siteMap(browser);
// Une page interne autre que l'accueil, pour les tests de navigation.
const AUTRE = SITE.pages.find((url) => url !== '/') || '/';

// ------------------------------------------------------- Le nuage suit le scroll

suite('Le nuage suit les sections');
{
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const problems = watch(page);
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(3200);

  check('Le moteur démarre', (await page.evaluate(() => !!window.__particules)) || 'window.__particules absent');

  const visible = await litPixels(page, { x: 880, y: 120, width: 480, height: 640 });
  check(`Le nuage est visible à l'écran (${visible} pixels allumés)`, visible > 2000 || `seulement ${visible}`);

  const dust = await page.evaluate(() => window.__particules?.dust?.count ?? 0);
  check(`La poussière d'ambiance est présente (${dust} grains)`, dust > 200 || `seulement ${dust}`);

  const ids = await page.evaluate(() =>
    [...document.querySelectorAll('main [data-section]')].map((s) => s.id)
  );
  check('Toutes les sections sont détectées', ids.length >= 2 || `${ids.length} section(s)`);

  for (const id of ids) {
    await scrollToSection(page, id);

    // Le morphing dure une seconde et demie, mais la forme doit d'abord être
    // téléchargée : on attend la fin réelle plutôt qu'un délai fixe, qui
    // deviendrait capricieux dès que la machine est chargée.
    const state = await page
      .waitForFunction(
        (wanted) => {
          const field = window.__particules;
          if (!field || field.currentId !== wanted || field.morphing) return null;
          return { shape: field.currentId, morphing: field.morphing };
        },
        id,
        { timeout: 12000, polling: 200 }
      )
      .then((handle) => handle.jsonValue())
      .catch(async () => page.evaluate(() => ({
        shape: window.__particules?.currentId,
        morphing: window.__particules?.morphing,
      })));

    check(
      `« ${id} » affiche sa forme, morphing terminé`,
      (state.shape === id && state.morphing === false) || `forme=${state.shape} morphing=${state.morphing}`
    );
  }

  check('Aucune erreur JavaScript', problems.length === 0 || problems.join(' | '));
  await page.close();
}

// ---------------------------------------------------------------- Animations

suite('Animations');
{
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2500);

  const outlines = await page.evaluate(() => document.querySelectorAll('.title__line--outline').length);
  check(`Des titres sont tracés au trait (${outlines})`, outlines > 0 || 'aucun titre en contour');

  const sectionBandeau = await scrollToFirst(page, '[data-marquee]');
  check('Une section porte un bandeau défilant', sectionBandeau !== null || 'aucun bandeau sur la page');
  await page.waitForTimeout(1200);
  const first = await page.evaluate(() => document.querySelector('.marquee__track')?.style.transform || '');
  await page.waitForTimeout(1400);
  const second = await page.evaluate(() => document.querySelector('.marquee__track')?.style.transform || '');
  check(
    'Le bandeau de texte défile',
    (first !== '' && second !== '' && first !== second) || `avant « ${first} », après « ${second} »`
  );

  await page.close();
}

{
  // Page neuve : le bloc précédent a défilé jusqu'au bandeau, en passant devant
  // les chiffres — leur montée était donc déjà jouée et terminée.
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2500);

  const sectionChiffres = await scrollToFirst(page, '[data-counter]');
  check('Une section porte des chiffres animés', sectionChiffres !== null || 'aucun compteur sur la page');

  // Zéro peut être la bonne réponse — « 0 mois d'engagement » est un argument.
  // On ne vérifie donc pas que les chiffres sont non nuls, mais qu'ils montent
  // puis s'arrêtent exactement sur la valeur annoncée.
  const releve = () => page.evaluate(() => {
    // PHP sépare les milliers par une espace ordinaire, toLocaleString par une
    // espace fine insécable : à l'œil c'est le même chiffre, on compare donc
    // les deux côtés normalisés.
    const normal = (t) => t.replace(/[\u202f\u00a0]/g, ' ').trim();
    return [...document.querySelectorAll('[data-counter]')].map((e) => ({
      lu: normal(e.textContent),
      attendu: normal(Number(e.dataset.counter).toLocaleString('fr-FR') + (e.dataset.suffix || '')),
    }));
  });

  // La montée dure 1,4 s : on l'échantillonne pendant qu'elle se joue.
  const pendant = [];
  for (let i = 0; i < 6; i++) {
    pendant.push(await releve());
    await page.waitForTimeout(180);
  }
  await page.waitForTimeout(2000);
  const apres = await releve();

  check(
    `Les compteurs s'arrêtent sur la bonne valeur (${apres.map((c) => c.lu).join(', ')})`,
    (apres.length > 0 && apres.every((c) => c.lu === c.attendu)) ||
      apres.filter((c) => c.lu !== c.attendu).map((c) => `« ${c.lu} » au lieu de « ${c.attendu} »`).join(', ')
  );
  check(
    'Les chiffres montent au lieu d\'apparaître',
    pendant.some((releve) => releve.some((c, i) => c.lu !== apres[i]?.lu)) ||
      `aucune valeur intermédiaire relevée : ${apres.map((c) => c.lu).join(', ')}`
  );

  await page.close();
}

// ------------------------------------------------------ Navigation multi-pages

suite('Navigation entre les pages');
{
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const problems = watch(page);
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(3000);

  // Un marqueur posé sur la fenêtre disparaîtrait à un vrai rechargement.
  await page.evaluate(() => { window.__pasDeRechargement = true; });

  const links = await page.evaluate(() =>
    [...document.querySelectorAll('[data-nav-link]')].map((a) => a.dataset.navLink)
  );
  check(`Le menu liste les pages (${links.join(', ')})`, links.length >= 2 || `${links.length} lien(s)`);

  for (const slug of links.slice(1)) {
    await page.click(`[data-nav-link="${slug}"]`);
    await page.waitForTimeout(2600);

    const state = await page.evaluate(() => ({
      path: location.pathname,
      page: document.getElementById('contenu')?.dataset.page,
      first: document.querySelector('main [data-section]')?.id,
      shape: window.__particules?.currentId,
      // Le contact est un bouton, pas une entrée de liste : on cherche le
      // marquage, pas la classe d'un seul des deux.
      active: document.querySelector('[data-nav-link].is-active')?.dataset.navLink,
      kept: window.__pasDeRechargement === true,
      scroll: Math.round(window.scrollY),
    }));

    check(`« ${slug} » : la page est remplacée sans rechargement`, state.kept || 'la page a été rechargée');
    check(`« ${slug} » : l'adresse et le menu suivent`, (state.page === slug && state.active === slug) || JSON.stringify(state));
    check(`« ${slug} » : on arrive en haut, sur la première forme`, (state.scroll === 0 && state.shape === state.first) || JSON.stringify(state));
  }

  await page.goBack();
  await page.waitForTimeout(2400);
  const back = await page.evaluate(() => ({
    path: location.pathname,
    page: document.getElementById('contenu')?.dataset.page,
  }));
  check('Le bouton « précédent » revient à la page précédente', back.page !== '' || JSON.stringify(back));

  check('Aucune erreur pendant la navigation', problems.length === 0 || problems.join(' | '));
  await page.close();
}

// ----------------------------------------------------------------- Mise en page

suite('Mise en page sur tous les écrans');
for (const size of [
  { width: 360, height: 780, name: 'téléphone étroit' },
  { width: 390, height: 844, name: 'téléphone' },
  { width: 768, height: 1024, name: 'tablette' },
  { width: 1024, height: 768, name: 'petit portable' },
  { width: 1280, height: 800, name: 'portable' },
  { width: 1440, height: 900, name: 'ordinateur' },
  { width: 1920, height: 1080, name: 'grand écran' },
]) {
  const page = await browser.newPage({ viewport: { width: size.width, height: size.height } });
  const overflow = [];

  for (const path of [...SITE.pages, SITE.contact]) {
    await page.goto(BASE + path, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1200);
    const bad = await page.evaluate((where) => {
      const out = [];
      // overflow-x: hidden masque un débordement sans le corriger : on compare
      // la largeur réelle du texte à celle de son conteneur.
      const watched = '.title__line, .card__title, .card__text, .formula, .quote, .section__body, .columns__item, .stats__label';
      for (const el of document.querySelectorAll(watched)) {
        if (el.scrollWidth > el.clientWidth + 1) {
          out.push(`${where} ${el.className} +${el.scrollWidth - el.clientWidth}px « ${el.textContent.trim().slice(0, 22)} »`);
        }
      }
      return out;
    }, path);
    overflow.push(...bad);
  }

  check(`${size.name} (${size.width}px) : aucun texte rogné`, overflow.length === 0 || overflow.join(' ; '));
  await page.close();
}

// ---------------------------------------------------------------------- Replis

suite('Replis');
{
  const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
  const problems = watch(page);
  await page.addInitScript(() => {
    HTMLCanvasElement.prototype.getContext = () => null;
    delete window.WebGLRenderingContext;
  });
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2000);

  const state = await page.evaluate(() => ({
    flagged: document.documentElement.classList.contains('no-webgl'),
    canvasGone: !document.getElementById('particles'),
    readable: !!document.querySelector('.title')?.offsetHeight,
  }));
  check('Sans WebGL, le site reste lisible', (state.flagged && state.canvasGone && state.readable) || JSON.stringify(state));
  check('Sans WebGL, aucune erreur', problems.length === 0 || problems.join(' | '));
  await page.close();
}
{
  const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2200);
  const state = await page.evaluate(() => ({
    revealed: document.querySelectorAll('[data-reveal].is-revealed').length,
    total: document.querySelectorAll('[data-reveal]').length,
    scatter: window.__particules?.uniforms.uScatter.value,
  }));
  check(
    'En mouvement réduit, tous les textes sont affichés sans animation',
    (state.revealed === state.total && state.scatter === 0) || JSON.stringify(state)
  );
  await page.close();
}

// ------------------------------------------------------ Aucune dépendance tierce

suite('Le site ne dépend que de lui-même');
{
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  const foreign = new Set();

  // Tout ce qui sort du domaine du site est relevé : polices, scripts, images.
  // Le projet revendique l'absence de CDN, et un appel direct à Google Fonts
  // transmet l'adresse IP du visiteur à un tiers sans son consentement.
  page.on('request', (request) => {
    const url = new URL(request.url());
    if (url.origin !== new URL(BASE).origin && url.protocol !== 'data:') {
      foreign.add(url.origin);
    }
  });

  for (const path of [...SITE.pages, SITE.contact]) {
    await page.goto(BASE + path, { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
  }

  check(
    'Aucune requête vers un domaine tiers',
    foreign.size === 0 || `appels sortants : ${[...foreign].join(', ')}`
  );

  const fontOk = await page.evaluate(() => document.fonts.check('900 100px Montserrat'));
  check('La police du site est bien chargée', fontOk || 'Montserrat indisponible');

  await page.close();
}

// -------------------------------------- Versions des modules JavaScript

suite('Chaque module porte sa version');
{
  const page = await browser.newPage({ viewport: { width: 1280, height: 800 } });
  const unversioned = new Set();

  // Un module chargé sans numéro de version reste en cache sans qu'on puisse
  // jamais l'invalider. Après une mise à jour, le navigateur exécute alors un
  // point d'entrée récent avec des dépendances périmées, et la page s'arrête
  // au premier appel manquant. Seuls les chargements de module comptent : les
  // requêtes fetch de la page de diagnostic visent volontairement l'URL nue.
  page.on('request', (request) => {
    if (request.resourceType() !== 'script') return;
    const url = new URL(request.url());
    if (url.pathname.startsWith('/assets/js/') && url.pathname.endsWith('.js') && !url.search) {
      unversioned.add(url.pathname);
    }
  });

  for (const path of [...SITE.pages, SITE.contact, '/diagnostic']) {
    await page.goto(BASE + path, { waitUntil: 'networkidle' });
    await page.waitForTimeout(1000);
  }

  check(
    'Aucun module chargé sans version',
    unversioned.size === 0 || `sans version : ${[...unversioned].join(', ')}`
  );

  const mapped = await page.evaluate(() => {
    const node = document.querySelector('script[type="importmap"]');
    if (!node) return 0;
    return Object.keys(JSON.parse(node.textContent).imports || {}).length;
  });
  check(`La carte d'imports couvre les modules (${mapped})`, mapped >= 8 || `seulement ${mapped}`);

  await page.close();
}

// ------------------------------------------- Isolation des sous-systèmes

suite('Une panne isolée ne doit rien emporter');

/**
 * La mise en route enchaîne défilement lissé, bandeaux, révélations, moteur et
 * poussière. Chacun doit pouvoir tomber seul : c'est ce qui a manqué lors de
 * l'ajout de la poussière d'ambiance, dont l'échec supprimait le canevas et
 * donc tout le décor.
 */
async function survivesFailureOf(scenario, install, expectations) {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await install(page);
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(4000);

  const state = await page.evaluate(() => ({
    engine: Boolean(window.__particules),
    shape: window.__particules?.currentId ?? null,
    dust: window.__particules?.dust?.count ?? 0,
    canvas: Boolean(document.getElementById('particles')),
    ready: document.documentElement.classList.contains('is-ready'),
    // Seuls les textes réellement à l'écran comptent.
    hidden: [...document.querySelectorAll('.eyebrow, .hero__subtitle, .section__body')]
      .filter((el) => {
        const box = el.getBoundingClientRect();
        return box.top < window.innerHeight && box.bottom > 0
          && getComputedStyle(el).opacity !== '1';
      }).length,
  }));

  await page.close();

  for (const [label, verdict] of Object.entries(expectations(state))) {
    check(`${scenario} : ${label}`, verdict === true ? true : `${verdict} — état ${JSON.stringify(state)}`);
  }
}

// La poussière est un décor secondaire : le dessin principal doit survivre.
await survivesFailureOf(
  "Poussière refusée par le pilote",
  (page) =>
    page.route('**/particles/DustField.js*', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'text/javascript',
        body: 'export class DustField { constructor(){ throw new Error("nuanceur refusé"); } }',
      })
    ),
  (s) => ({
    'le canevas reste en place': s.canvas || 'canevas retiré',
    'le dessin principal est affiché': s.shape !== null || 'aucune forme',
    'la poussière est simplement absente': s.dust === 0 || `${s.dust} grains`,
    'le texte reste lisible': s.hidden === 0 || `${s.hidden} masqué(s)`,
  })
);

// Le défilement lissé n'est qu'un confort : sans lui le site reste entier.
await survivesFailureOf(
  'Défilement lissé en panne',
  (page) =>
    page.addInitScript(() => {
      const real = Element.prototype.getBoundingClientRect;
      let first = true;
      Element.prototype.getBoundingClientRect = function () {
        if (this.id === 'smooth-content' && first) {
          first = false;
          throw new Error('panne simulée');
        }
        return real.call(this);
      };
    }),
  (s) => ({
    'le moteur démarre quand même': s.engine || 'moteur absent',
    'le dessin est affiché': s.shape !== null || 'aucune forme',
    'le texte reste lisible': s.hidden === 0 || `${s.hidden} masqué(s)`,
  })
);

// ------------------------------------------------- Le site sans JavaScript

suite('Dégradation quand le script échoue');

/**
 * Le texte est masqué en attendant son animation d'apparition. Si le script
 * ne démarre pas, ce masquage doit être levé — sans quoi la page se retrouve
 * à moitié vide, ce qui est bien pire qu'une page simplement figée.
 */
async function readabilityWithout(scenario, install) {
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await install(page);
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  // Au-delà du garde-fou posé par le gabarit.
  await page.waitForTimeout(7200);

  const state = await page.evaluate(() => {
    const hidden = [];
    for (const el of document.querySelectorAll('.eyebrow, .hero__subtitle, .section__body, .title__line, .stats__value')) {
      const box = el.getBoundingClientRect();
      // Seuls les éléments réellement à l'écran sont concernés.
      if (box.bottom < 0 || box.top > window.innerHeight) continue;
      if (getComputedStyle(el).opacity !== '1') hidden.push(el.className || el.tagName);
    }
    return { classes: document.documentElement.className, hidden };
  });

  await page.close();

  check(
    `${scenario} : le texte reste lisible`,
    state.hidden.length === 0 || `masqué : ${state.hidden.join(', ')} (classes : ${state.classes})`
  );
  check(
    `${scenario} : l'échec est signalé sur la page`,
    state.classes.includes('js-failed') || `classes : ${state.classes}`
  );
}

await readabilityWithout('Script bloqué', (page) =>
  page.route('**/assets/js/main.js*', (route) => route.abort())
);

// La panne la plus fréquente : un serveur qui n'envoie pas de type JavaScript.
// Le navigateur refuse alors le module, en silence côté serveur.
await readabilityWithout('Type MIME refusé', (page) =>
  page.route('**/assets/js/**', async (route) => {
    const response = await route.fetch();
    await route.fulfill({
      response,
      headers: { ...response.headers(), 'content-type': 'application/octet-stream' },
    });
  })
);

{
  const page = await browser.newPage({ viewport: { width: 1440, height: 1100 } });
  await page.goto(BASE + '/diagnostic', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(5200);
  const rows = await page.evaluate(() => {
    const cells = [...document.querySelectorAll('#client-checks tr')];
    return {
      total: cells.length,
      failed: cells.filter((r) => r.textContent.includes('❌')).map((r) => r.textContent.trim().slice(0, 90)),
    };
  });
  check(
    `La page de diagnostic teste chaque maillon (${rows.total} vérifications)`,
    rows.total >= 8 || `seulement ${rows.total}`
  );
  check('Le diagnostic ne relève aucun problème', rows.failed.length === 0 || rows.failed.join(' | '));
  await page.close();
}

// ------------------------------------------------------------------ Accessibilité

suite('Accessibilité du défilement');
{
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2200);

  // Le contenu étant déplacé par transformation, le navigateur ne sait pas
  // amener seul un élément à l'écran : le site doit s'en charger.
  const focused = await page.evaluate(async () => {
    const link = document.querySelector('.footer__nav a');
    if (!link) return 'aucun lien de pied de page';
    link.focus();

    // Le défilement est amorti : on attend qu'il se pose plutôt que de parier
    // sur une durée, qui dépend de la hauteur de la page et donc du contenu.
    const inView = () => {
      const box = link.getBoundingClientRect();
      return box.top > -50 && box.bottom < window.innerHeight + 50;
    };
    for (let i = 0; i < 60 && !inView(); i++) {
      await new Promise((r) => setTimeout(r, 100));
    }

    return inView() ? true : `lien hors écran : top=${Math.round(link.getBoundingClientRect().top)}`;
  });
  check('Un lien atteint au clavier est amené à l\'écran', focused);

  const stolen = await page.evaluate(() => document.getElementById('smooth-wrapper').scrollTop);
  check('Le conteneur de défilement ne se décale jamais', stolen === 0 || `scrollTop = ${stolen}`);
  await page.close();
}

// ------------------------------------------------------ Rappel de contact

suite('Rappel de contact');
{
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  const problems = watch(page);

  const state = () =>
    page.evaluate(() => {
      const el = document.querySelector('[data-quick-cta]');
      if (!el) return { present: false };
      const style = getComputedStyle(el);
      return {
        present: true,
        opacity: Number(style.opacity),
        visibility: style.visibility,
        href: el.getAttribute('href'),
        text: el.textContent.trim(),
      };
    });

  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(2600);

  const top = await state();
  check('Le rappel de contact est présent', top.present || 'lien absent de la page');
  // Caché par « visibility », donc hors du parcours au clavier : un lien
  // seulement transparent resterait cliquable et atteignable par tabulation.
  check(
    'Il est inerte en haut de page',
    (top.opacity === 0 && top.visibility === 'hidden') ||
      `opacité ${top.opacity}, visibilité ${top.visibility}`
  );

  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight * 0.3));
  await page.waitForTimeout(900);
  const scrolled = await state();
  check(
    'Il apparaît une fois la première section passée',
    (scrolled.opacity === 1 && scrolled.visibility === 'visible') ||
      `opacité ${scrolled.opacity}, visibilité ${scrolled.visibility}`
  );
  check('Il pointe vers la page de contact', scrolled.href === SITE.contact || `pointe sur ${scrolled.href}`);
  check('Il porte un texte', scrolled.text.length > 2 || `texte « ${scrolled.text} »`);

  // Il ferait doublon sur la page qu'il vise : la navigation sans rechargement
  // doit donc le cacher, sans attendre un rechargement complet.
  await page.click(`.menu__cta[href="${SITE.contact}"]`);
  await page.waitForTimeout(1800);
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight * 0.4));
  await page.waitForTimeout(900);
  const onContact = await state();
  check(
    'Il disparaît sur la page de contact',
    onContact.visibility === 'hidden' || `visibilité ${onContact.visibility}`
  );

  await page.click(`.menu__link[href="${AUTRE}"]`);
  await page.waitForTimeout(1800);
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight * 0.35));
  await page.waitForTimeout(900);
  const back = await state();
  check(
    'Il revient en quittant la page de contact',
    back.visibility === 'visible' || `visibilité ${back.visibility}`
  );

  check('Aucune erreur autour du rappel', problems.length === 0 || problems.join(' | '));
  await page.close();
}

{
  // Sans JavaScript, personne n'observe le scroll : le rappel doit être offert
  // d'emblée, sauf sur la page qu'il vise.
  const context = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();

  const visibility = async (path) => {
    await page.goto(BASE + path, { waitUntil: 'networkidle' });
    return page.evaluate(() => {
      const el = document.querySelector('[data-quick-cta]');
      return el ? getComputedStyle(el).visibility : 'absent';
    });
  };

  check('Sans script, le rappel reste offert', (await visibility('/')) === 'visible' || 'rappel caché');
  check(
    'Sans script, il ne s\'affiche pas sur la page de contact',
    (await visibility(SITE.contact)) === 'hidden' || 'rappel affiché sur sa propre page'
  );

  await context.close();
}

// -------------------------------------------------------------- Back-office

suite('Back-office');
{
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  // Deux réponses attendues que le navigateur journalise comme des erreurs :
  // le refus de connexion, volontairement 401, et le 404 que la suite provoque
  // elle-même pour vérifier qu'une page supprimée ne répond plus.
  const problems = watch(page, { ignore: [/401/, /404/] });

  await page.goto(BASE + '/admin', { waitUntil: 'domcontentloaded' });
  check(
    'Sans session, /admin renvoie vers la connexion',
    page.url().endsWith('/admin/connexion') || `arrivé sur ${page.url()}`
  );

  if (!ADMIN_EMAIL || !ADMIN_PASSWORD) {
    console.log('  \x1b[33m·\x1b[0m Suite du back-office ignorée : identifiants non fournis.');
  } else {
    // Une adresse valide mais un mauvais mot de passe : le refus doit être net,
    // et son message ne doit pas indiquer laquelle des deux valeurs était fausse.
    await page.fill('input[name="email"]', ADMIN_EMAIL);
    await page.fill('input[name="password"]', 'mauvais-mot-de-passe');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(600);
    const rejected = await page.evaluate(() => document.querySelector('[role="alert"]')?.textContent?.trim() || '');
    check('Un mauvais mot de passe est refusé', rejected.length > 0 || 'aucun message d\'erreur');
    check(
      'Le refus ne dit pas laquelle des deux valeurs est fausse',
      !/adresse|courriel|mail/i.test(rejected) || `message trop bavard : « ${rejected} »`
    );

    // L'adresse saisie est reproposée : seul le mot de passe est à retaper.
    const kept = await page.inputValue('input[name="email"]');
    check('L\'adresse saisie est conservée après un échec', kept === ADMIN_EMAIL || `champ à « ${kept} »`);

    await page.fill('input[name="password"]', ADMIN_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForTimeout(900);
    check('Le bon couple ouvre le back-office', page.url().endsWith('/admin') || `arrivé sur ${page.url()}`);

    const shown = await page.evaluate(() => document.querySelector('.admin__whoami')?.textContent?.trim() || '');
    check(
      'Le compte connecté est affiché',
      shown === ADMIN_EMAIL.toLowerCase() || `affiché « ${shown} »`
    );

    // Atelier de formes : l'aperçu doit vraiment s'afficher.
    await page.goto(BASE + '/admin/formes', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(3200);
    const preview = await litPixels(page, { x: 700, y: 180, width: 560, height: 520 });
    check(`L'atelier affiche son aperçu (${preview} pixels)`, preview > 2000 || `seulement ${preview}`);

    await page.selectOption('#type', 'preset');
    await page.waitForTimeout(2600);
    const afterChange = await litPixels(page, { x: 700, y: 180, width: 560, height: 520 });
    check(`Changer de forme redessine (${afterChange} pixels)`, afterChange > 2000 || `seulement ${afterChange}`);

    const snippet = await page.evaluate(() => document.querySelector('#snippet code')?.textContent || '');
    check(
      'Le bloc JSON proposé est valide',
      (() => {
        try {
          JSON.parse(`{${snippet}}`);
          return snippet.includes('"shape"') ? true : 'clé « shape » absente';
        } catch (e) {
          return `JSON invalide : ${e.message}`;
        }
      })()
    );

    // Couleur du site : l'aperçu interroge PHP et se met à jour.
    await page.goto(BASE + '/admin/theme', { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(1400);
    const before = await page.evaluate(() =>
      getComputedStyle(document.getElementById('theme-preview')).getPropertyValue('--p-accent').trim()
    );
    await page.evaluate(() => document.querySelector('.theme__preset[data-color="#ff6b00"]')?.click());
    await page.waitForTimeout(1200);
    const after = await page.evaluate(() =>
      getComputedStyle(document.getElementById('theme-preview')).getPropertyValue('--p-accent').trim()
    );
    check(
      `L'aperçu de couleur suit la sélection (${before} → ${after})`,
      (after !== '' && after !== before) || `resté sur ${before}`
    );
  }

    // ------------------------------------------------ Écriture du contenu
    //
    // Le parcours complet, tel qu'il sera vécu : créer une page, la voir
    // apparaître sur le site, en modifier une section, la retirer du menu,
    // puis la supprimer. La page d'essai est effacée en fin de parcours,
    // quel que soit le résultat des vérifications.
    const essai = 'Page d\'essai automatique';
    const essaiSlug = 'page-d-essai-automatique';
    try {
      await page.goto(BASE + '/admin/pages', { waitUntil: 'domcontentloaded' });
      await page.fill('input[name="title"]', essai);
      await page.fill('input[name="description"]', 'Créée par la suite de tests.');
      await page.selectOption('select[name="kind"]', 'statement');
      await page.click('button:has-text("Créer la page")');
      await page.waitForTimeout(700);

      check(
        'La page créée mène à son écran d\'édition',
        page.url().endsWith('/admin/page/' + essaiSlug) || `arrivé sur ${page.url()}`
      );

      // Le site la sert immédiatement, et le menu la reprend.
      const visitor = await browser.newPage({ viewport: { width: 1280, height: 900 } });
      await visitor.goto(BASE + '/' + essaiSlug, { waitUntil: 'domcontentloaded' });
      check(
        'Le site sert la page créée',
        (await visitor.title()).includes(essai) || `titre « ${await visitor.title()} »`
      );
      check(
        'Le menu reprend la page créée',
        (await visitor.locator(`nav a[href="/${essaiSlug}"]`).count()) > 0 || 'lien absent du menu'
      );

      // Modifier le contenu d'une section : ce que l'on écrit ici doit être
      // exactement ce que le visiteur lit.
      const sectionId = await page.evaluate(
        () => document.querySelector('.table__actions a[href*="/section/"]')?.getAttribute('href')?.split('/').pop() || ''
      );
      check('La page naît avec une section modifiable', sectionId !== '' || 'aucune section listée');

      await page.goto(BASE + `/admin/page/${essaiSlug}/section/${sectionId}`, { waitUntil: 'domcontentloaded' });
      // Les champs sont regroupés sous « champ[…] » : c'est ce préfixe qui
      // permet au contrôleur de distinguer le contenu du jeton anti-CSRF.
      await page.fill('[name="champ[title]"]', 'Titre venu du back-office');
      await page.fill('[name="champ[body]"]', 'Texte venu du back-office.');
      // Le bouton par son intitulé : « Déconnexion », posé par le gabarit
      // commun, est le premier bouton d'envoi de la page.
      await page.click('button:has-text("Enregistrer le contenu")');
      await page.waitForTimeout(700);

      await visitor.goto(BASE + '/' + essaiSlug, { waitUntil: 'domcontentloaded' });
      const rendu = await visitor.evaluate(() => document.querySelector('main')?.textContent || '');
      check(
        'Le texte saisi arrive sur le site',
        rendu.includes('Titre venu du back-office') && rendu.includes('Texte venu du back-office.')
          ? true
          : 'texte introuvable dans la page'
      );

      // Retirer du menu : la page reste servie, seul le lien disparaît.
      await page.goto(BASE + '/admin/page/' + essaiSlug, { waitUntil: 'domcontentloaded' });
      await page.uncheck('input[name="inNav"]');
      await page.click('button:has-text("Enregistrer les réglages")');
      await page.waitForTimeout(700);

      await visitor.goto(BASE + '/' + essaiSlug, { waitUntil: 'domcontentloaded' });
      check(
        'Une page masquée sort du menu',
        (await visitor.locator(`nav a[href="/${essaiSlug}"]`).count()) === 0 || 'lien encore présent'
      );
      check(
        'Une page masquée reste accessible par son adresse',
        (await visitor.title()).includes(essai) || 'la page ne répond plus'
      );

      // L'accueil, lui, doit résister : c'est lui qui répond à la racine.
      await page.goto(BASE + '/admin/page/accueil', { waitUntil: 'domcontentloaded' });
      const canDeleteHome = await page.evaluate(
        () => document.querySelectorAll('form[action="/admin/page/accueil/supprimer"] button').length
      );
      check(
        'L\'écran de l\'accueil n\'offre pas de suppression',
        canDeleteHome === 0 || `${canDeleteHome} bouton(s) de suppression proposé(s)`
      );

      await visitor.close();
    } finally {
      // Ménage : la suite ne doit pas laisser de page derrière elle.
      await page.goto(BASE + '/admin/page/' + essaiSlug, { waitUntil: 'domcontentloaded' });
      page.once('dialog', (d) => d.accept());
      // Le formulaire de la page, pas ceux des sections : ceux-là portent
      // « /section/<id>/supprimer » et le premier est désactivé.
      const removal = page.locator(`form[action="/admin/page/${essaiSlug}/supprimer"] button`);
      if (await removal.count()) {
        await removal.click();
        await page.waitForTimeout(700);
      }
      const gone = await page.evaluate(
        (slug) => fetch('/' + slug, { redirect: 'manual' }).then((r) => r.status),
        essaiSlug
      );
      check('La page supprimée ne répond plus', gone === 404 || `le site répond ${gone}`);
    }

  check('Aucune erreur dans le back-office', problems.length === 0 || problems.join(' | '));
  await page.close();
}

await browser.close();

console.log('\n' + '─'.repeat(62));
if (failures.length === 0) {
  console.log(`\x1b[32m${passed} tests navigateur réussis.\x1b[0m`);
  process.exit(0);
}
console.log(`\x1b[31m${failures.length} échec(s) sur ${passed + failures.length} tests :\x1b[0m`);
failures.forEach((f) => console.log(`  · ${f}`));
process.exit(1);
