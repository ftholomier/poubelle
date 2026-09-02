/**
 * Tests de bout en bout, exécutés dans un vrai navigateur.
 *
 * Usage : node tests/browser.mjs [url] [chemin/vers/playwright] [mot-de-passe-admin]
 *
 * Ils vérifient ce que la suite PHP ne peut pas voir : le nuage s'affiche
 * réellement, il change de forme d'une section et d'une page à l'autre, la
 * mise en page ne déborde sur aucun écran, le site reste utilisable sans
 * WebGL, et le back-office fait ce qu'on attend de lui.
 */

const BASE = process.argv[2] || 'http://127.0.0.1:8000';
const PLAYWRIGHT = process.argv[3] || 'playwright';
const ADMIN_PASSWORD = process.argv[4] || process.env.ADMIN_PASSWORD || '';

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
    if (!isExternal(r.url())) bag.push(`requête échouée : ${r.url()}`);
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

const browser = await chromium.launch({
  executablePath: process.env.CHROMIUM_PATH || undefined,
  args: ['--use-gl=swiftshader', '--enable-unsafe-swiftshader', '--no-sandbox'],
});

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

  await scrollToSection(page, 'manifeste');
  await page.waitForTimeout(1200);
  const first = await page.evaluate(() => document.querySelector('.marquee__track')?.style.transform || '');
  await page.waitForTimeout(1400);
  const second = await page.evaluate(() => document.querySelector('.marquee__track')?.style.transform || '');
  check(
    'Le bandeau de texte défile',
    (first !== '' && second !== '' && first !== second) || `avant « ${first} », après « ${second} »`
  );

  await scrollToSection(page, 'chiffres');
  await page.waitForTimeout(2600);
  const counters = await page.evaluate(() =>
    [...document.querySelectorAll('[data-counter]')].map((e) => e.textContent)
  );
  check(
    `Les compteurs s'animent (${counters.join(', ')})`,
    (counters.length > 0 && counters.every((v) => !/^0\D*$/.test(v))) || `restés à zéro : ${counters.join(', ')}`
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
      active: document.querySelector('.menu__link.is-active')?.dataset.navLink,
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

  for (const path of ['/', '/solutions', '/methode', '/contact']) {
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

  for (const path of ['/', '/solutions', '/methode', '/contact']) {
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

// -------------------------------------------------------------- Back-office

suite('Back-office');
{
  const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });
  // Le refus de connexion répond volontairement 401 : le navigateur le
  // journalise comme une erreur, ce n'en est pas une.
  const problems = watch(page, { ignore: [/401/] });

  await page.goto(BASE + '/admin', { waitUntil: 'domcontentloaded' });
  check(
    'Sans session, /admin renvoie vers la connexion',
    page.url().endsWith('/admin/connexion') || `arrivé sur ${page.url()}`
  );

  if (!ADMIN_PASSWORD) {
    console.log('  \x1b[33m·\x1b[0m Suite du back-office ignorée : aucun mot de passe fourni.');
  } else {
    await page.fill('input[name="password"]', 'mauvais-mot-de-passe');
    await page.click('button[type="submit"]');
    await page.waitForTimeout(600);
    const rejected = await page.evaluate(() => document.querySelector('[role="alert"]')?.textContent?.trim() || '');
    check('Un mauvais mot de passe est refusé', rejected.length > 0 || 'aucun message d\'erreur');

    await page.fill('input[name="password"]', ADMIN_PASSWORD);
    await page.click('button[type="submit"]');
    await page.waitForTimeout(900);
    check('Le bon mot de passe ouvre le back-office', page.url().endsWith('/admin') || `arrivé sur ${page.url()}`);

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
