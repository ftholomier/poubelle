/**
 * Tests de bout en bout, exécutés dans un vrai navigateur.
 *
 * Usage : node tests/browser.mjs [url] [chemin/vers/playwright]
 *
 * Ils vérifient ce que la suite PHP ne peut pas voir : le nuage s'affiche
 * réellement, il change de forme d'une section à l'autre, la mise en page ne
 * déborde sur aucun écran, et le site reste utilisable sans WebGL.
 */

const BASE = process.argv[2] || 'http://127.0.0.1:8000';
const PLAYWRIGHT = process.argv[3] || 'playwright';

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

function watch(page) {
  const bag = [];
  page.on('pageerror', (e) => bag.push(`erreur JS : ${e.message}`));
  page.on('console', (m) => {
    if (m.type() === 'error' && !isExternal(m.location()?.url)) bag.push(`console : ${m.text()}`);
  });
  page.on('requestfailed', (r) => {
    if (!isExternal(r.url())) bag.push(`requête échouée : ${r.url()}`);
  });
  return bag;
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

  check('Le moteur démarre', await page.evaluate(() => !!window.__particules) || 'window.__particules absent');

  const visible = await litPixels(page, { x: 900, y: 120, width: 460, height: 640 });
  check(`Le nuage est visible à l'écran (${visible} pixels allumés)`, visible > 2000 || `seulement ${visible}`);

  const ids = await page.evaluate(() =>
    [...document.querySelectorAll('main [data-section]')].map((s) => s.id)
  );
  check('Toutes les sections sont détectées', ids.length >= 2 || `${ids.length} section(s)`);

  for (const id of ids) {
    await page.evaluate((i) => document.getElementById(i).scrollIntoView({ block: 'center' }), id);
    await page.waitForTimeout(2400);
    const state = await page.evaluate(() => ({
      shape: window.__particules?.currentId,
      morphing: window.__particules?.morphing,
    }));
    check(
      `« ${id} » affiche sa forme, morphing terminé`,
      (state.shape === id && state.morphing === false) || `forme=${state.shape} morphing=${state.morphing}`
    );
  }

  check('Aucune erreur JavaScript', problems.length === 0 || problems.join(' | '));
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
  await page.goto(BASE + '/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(1500);

  const overflow = await page.evaluate(() => {
    // overflow-x: hidden masque un débordement sans le corriger :
    // on compare la largeur réelle du texte à celle de son conteneur.
    const bad = [];
    const watched = '.title__line, .card__title, .card__text, .formula, .quote, .section__body';
    for (const el of document.querySelectorAll(watched)) {
      if (el.scrollWidth > el.clientWidth + 1) {
        const excerpt = el.textContent.trim().slice(0, 24);
        bad.push(`${el.className} déborde de ${el.scrollWidth - el.clientWidth}px (« ${excerpt} »)`);
      }
    }
    return bad;
  });

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

// -------------------------------------------------------- Laboratoire de formes

suite('Laboratoire de formes');
{
  const page = await browser.newPage({ viewport: { width: 1400, height: 880 } });
  const problems = watch(page);
  await page.goto(BASE + '/labo', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(3200);

  const zone = { x: 700, y: 180, width: 520, height: 520 };
  const first = await litPixels(page, zone);
  check(`L'aperçu s'affiche au chargement (${first} pixels)`, first > 2000 || `seulement ${first}`);

  const options = await page.evaluate(() => ({
    sources: document.getElementById('src')?.options.length ?? 0,
    presets: document.getElementById('preset')?.options.length ?? 0,
  }));
  check('Le catalogue est chargé', (options.sources > 0 && options.presets > 0) || JSON.stringify(options));

  await page.selectOption('#src', { index: 2 });
  await page.waitForTimeout(2600);
  const second = await litPixels(page, zone);
  check(`Changer de source redessine (${second} pixels)`, second > 2000 || `seulement ${second}`);

  await page.selectOption('#type', 'preset');
  await page.waitForTimeout(2600);
  const third = await litPixels(page, zone);
  check(`Une forme mathématique s'affiche (${third} pixels)`, third > 2000 || `seulement ${third}`);

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

  check('Aucune erreur dans le laboratoire', problems.length === 0 || problems.join(' | '));
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
