/**
 * Diagnostic exécuté dans le navigateur.
 *
 * Il vérifie ce que le serveur ne peut pas voir : le type MIME réellement
 * envoyé pour les modules — un navigateur refuse d'exécuter un module servi
 * autrement qu'en JavaScript —, la disponibilité de WebGL, et la bonne
 * exécution de chaque module du site.
 */

const rows = [];
const body = document.getElementById('client-checks');
const report = document.querySelector('#report code');

function add(label, ok, detail) {
  rows.push({ label, ok, detail });
  render();
}

function render() {
  body.dataset.filled = '1';
  // Le tableau du haut ne montre que les vérifications générales ; celles de la
  // sonde ont leur propre tableau, mais rejoignent le rapport à copier.
  body.innerHTML = rows
    .filter((r) => !r.scoped)
    .map(
      (r) =>
        `<tr><td style="width:2.2rem">${r.ok ? '✅' : '❌'}</td>` +
        `<td><strong>${escapeHtml(r.label)}</strong></td>` +
        `<td>${escapeHtml(r.detail)}</td></tr>`
    )
    .join('');

  renderReport();
}

function renderReport() {
  report.textContent = [
    `Navigateur : ${navigator.userAgent}`,
    `Adresse    : ${location.origin}`,
    '',
    ...rows.map((r) => `${r.ok ? 'OK  ' : 'ECHEC'} ${r.label} — ${r.detail}`),
  ].join('\n');
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
}

// --------------------------------------------------------------- Types MIME

/**
 * Un navigateur refuse d'exécuter un module dont le type MIME n'est pas un
 * type JavaScript. C'est la panne la plus fréquente sur un serveur mal
 * configuré, et elle ne laisse aucune trace côté serveur.
 */
const JS_TYPES = ['text/javascript', 'application/javascript', 'application/ecmascript', 'text/ecmascript'];

async function checkAsset(path, expectJs) {
  try {
    const response = await fetch(path, { cache: 'no-store' });
    if (!response.ok) {
      add(path, false, `le serveur répond ${response.status}`);
      return;
    }

    const type = (response.headers.get('Content-Type') || '').split(';')[0].trim().toLowerCase();
    if (expectJs && !JS_TYPES.includes(type)) {
      add(
        path,
        false,
        `servi en « ${type || 'type absent'} » : le navigateur refusera de l'exécuter. ` +
          'Configurez le serveur pour envoyer text/javascript sur les fichiers .js.'
      );
      return;
    }

    const size = response.headers.get('Content-Length');
    add(path, true, `${type || 'type non précisé'}${size ? `, ${Number(size).toLocaleString('fr-FR')} octets` : ''}`);
  } catch (error) {
    add(path, false, `requête impossible : ${error.message}`);
  }
}

// ------------------------------------------------------------------ WebGL

function checkWebGL() {
  try {
    const canvas = document.createElement('canvas');
    const gl = canvas.getContext('webgl2') || canvas.getContext('webgl');
    if (!gl) {
      add('WebGL', false, "aucun contexte disponible : les particules ne peuvent pas s'afficher. " +
        "Vérifiez que l'accélération matérielle est activée dans le navigateur.");
      return;
    }
    const info = gl.getExtension('WEBGL_debug_renderer_info');
    const renderer = info ? gl.getParameter(info.UNMASKED_RENDERER_WEBGL) : 'non communiqué';
    add('WebGL', true, `contexte ${gl instanceof WebGL2RenderingContext ? '2' : '1'} — ${renderer}`);
  } catch (error) {
    add('WebGL', false, error.message);
  }
}

// ------------------------------------------------------- Modules du site

async function checkModule(label, path) {
  try {
    await import(path);
    add(label, true, 'chargé et exécuté');
  } catch (error) {
    add(label, false, error.message);
  }
}

// -------------------------------------------------- Essai complet du moteur

/**
 * Reproduit exactement ce que fait le site : instanciation du moteur, allocation,
 * poussière d'ambiance, chargement de la vraie forme, rendu. Puis on lit les
 * pixels du canevas — seule preuve qu'il s'est réellement passé quelque chose.
 */
async function runEngine() {
  const status = document.getElementById('engine-status');
  const canvas = document.getElementById('engine-canvas');
  const say = (text) => { status.textContent = text; };

  // Un nuanceur refusé par le pilote n'interrompt pas le programme : Three.js
  // se contente de l'écrire dans la console. Sans cette interception, la panne
  // resterait invisible pour qui ne pense pas à ouvrir les outils du navigateur.
  const shaderErrors = [];
  const realError = console.error;
  console.error = (...args) => {
    const text = args.map((a) => (a instanceof Error ? a.message : String(a))).join(' ');
    if (/shader|glsl|program|compil/i.test(text)) {
      shaderErrors.push(text.slice(0, 400));
    }
    realError.apply(console, args);
  };

  try {
    const [{ ParticleField, supportsWebGL }] = await Promise.all([
      import('/assets/js/particles/ParticleField.js'),
    ]);

    if (!supportsWebGL()) {
      add('Essai du moteur', false, 'WebGL indisponible : le moteur ne peut pas démarrer');
      say('WebGL indisponible.');
      return;
    }

    say('Instanciation…');
    const theme = await fetch('/api/site').then((r) => r.json()).then((d) => d.theme || {});
    const field = new ParticleField(canvas, theme);

    field.allocate(16000);
    field.enableDust({ count: 400 });
    field.resize();
    field.start();

    say('Chargement de la forme…');
    const page = await fetch('/api/pages').then((r) => r.json());
    const slug = page.pages?.[0]?.slug || 'accueil';
    const section = page.pages?.[0]?.sections?.[0] || 'hero';

    await field.morphTo({
      id: 'essai',
      type: 'svg',
      count: 16000,
      shapeUrl: `/api/shape/${encodeURIComponent(slug)}/${encodeURIComponent(section)}`,
    });

    // Le morphing dure une seconde et demie ; on laisse le rendu se poser.
    await new Promise((resolve) => setTimeout(resolve, 2600));

    // Un contexte WebGL vide son tampon de dessin après composition : sans un
    // rendu déclenché à l'instant même, la relecture ne verrait que du noir.
    field.renderer.render(field.scene, field.camera);
    const lit = countLitPixels(canvas);
    const ok = lit > 300;
    add(
      'Essai du moteur',
      ok,
      ok
        ? `${lit.toLocaleString('fr-FR')} pixels allumés : le moteur fonctionne`
        : `le canevas est resté vide (${lit} pixels) — le rendu ne produit rien`
    );
    say(ok ? `Particules affichées (${lit.toLocaleString('fr-FR')} pixels allumés).` : 'Aucune particule rendue.');
  } catch (error) {
    add('Essai du moteur', false, `${error.name} : ${error.message}`);
    say(`Échec : ${error.message}`);
    realError.call(console, '[diagnostic] essai du moteur', error);
  } finally {
    console.error = realError;
    if (shaderErrors.length) {
      add(
        'Compilation des nuanceurs',
        false,
        `refusés par le pilote graphique : ${shaderErrors.join(' — ')}`
      );
    } else {
      add('Compilation des nuanceurs', true, 'acceptés par le pilote graphique');
    }
  }
}

/** Relit le canevas WebGL et compte les pixels non noirs. */
function countLitPixels(canvas) {
  const copy = document.createElement('canvas');
  copy.width = canvas.width;
  copy.height = canvas.height;
  const ctx = copy.getContext('2d');
  ctx.drawImage(canvas, 0, 0);
  const data = ctx.getImageData(0, 0, copy.width, copy.height).data;
  let lit = 0;
  for (let i = 0; i < data.length; i += 4) {
    if (data[i] > 40 || data[i + 1] > 40 || data[i + 2] > 40) lit++;
  }
  return lit;
}

// -------------------------------------------- La page d'accueil, de l'intérieur

/**
 * Charge la page d'accueil dans un cadre isolé et l'interroge sur son état réel.
 * C'est la seule façon de savoir, depuis ici, si sa mise en route aboutit.
 */
function probeHomePage() {
  const frame = document.getElementById('page-probe');
  const body = document.getElementById('page-checks');

  const report = (lines) => {
    body.innerHTML = lines
      .map(
        (l) =>
          `<tr><td style="width:2.2rem">${l.ok ? '✅' : '❌'}</td>` +
          `<td><strong>${escapeHtml(l.label)}</strong></td>` +
          `<td>${escapeHtml(l.detail)}</td></tr>`
      )
      .join('');
    rows.push(...lines.map((l) => ({ ...l, scoped: true, label: `page d'accueil — ${l.label}` })));
    renderReport();
  };

  const inspect = () => {
    try {
      const win = frame.contentWindow;
      const doc = frame.contentDocument;
      if (!doc) throw new Error('cadre inaccessible');

      const classes = doc.documentElement.className;
      const engine = win.__particules;
      const revealed = doc.querySelectorAll('[data-reveal].is-revealed').length;
      const total = doc.querySelectorAll('[data-reveal]').length;
      // Seuls les textes réellement dans la fenêtre du cadre doivent être
      // révélés : ceux d'en dessous attendent légitimement le défilement.
      const hidden = [...doc.querySelectorAll('.eyebrow, .hero__subtitle, .section__body')]
        .filter((el) => {
          const box = el.getBoundingClientRect();
          if (box.bottom < 0 || box.top > win.innerHeight) return false;
          return win.getComputedStyle(el).opacity !== '1';
        }).length;

      report([
        {
          label: 'Mise en route',
          ok: classes.includes('is-ready'),
          detail: `classes appliquées : « ${classes || 'aucune'} »`,
        },
        {
          label: 'Moteur de particules',
          ok: Boolean(engine),
          detail: engine
            ? `démarré — forme « ${engine.currentId} », ${engine.dust?.count ?? 0} grains de poussière`
            : "absent : la mise en route s'est interrompue avant de le créer",
        },
        {
          label: 'Textes révélés',
          ok: hidden === 0,
          detail: `${revealed}/${total} révélés au total, ${hidden} masqué(s) parmi ceux à l'écran`,
        },
        {
          label: 'Canevas',
          ok: Boolean(doc.getElementById('particles')),
          detail: (() => {
            const c = doc.getElementById('particles');
            if (!c) return 'retiré de la page';
            const cs = win.getComputedStyle(c);
            return `${c.width}×${c.height}, opacité ${cs.opacity}, affichage ${cs.display}`;
          })(),
        },
      ]);
    } catch (error) {
      report([{ label: 'Sonde', ok: false, detail: error.message }]);
    }
  };

  let done = false;
  const inspectOnce = () => {
    if (done) return;
    done = true;
    inspect();
  };

  // Le cadre commence à charger dès l'analyse de la page, souvent bien avant
  // que cette fonction s'exécute : attendre son événement « load » ne suffit
  // pas, il faut aussi traiter le cas où il a déjà fini.
  const ready = frame.contentDocument?.readyState === 'complete';
  if (ready) {
    setTimeout(inspectOnce, 4000);
  } else {
    frame.addEventListener('load', () => setTimeout(inspectOnce, 4000), { once: true });
  }

  // Filet, au cas où l'événement n'arriverait jamais.
  setTimeout(inspectOnce, 12000);
}

// ------------------------------------------------------------------ Départ

// Volontairement dans une fonction : le « await » de premier niveau réclame un
// navigateur récent, or c'est justement ce que cette page ne doit pas supposer.
async function run() {
  add('Modules JavaScript', true, 'ce module s\'exécute, la syntaxe moderne est comprise');

  checkWebGL();

  await checkAsset('/assets/js/main.js', true);
  await checkAsset('/assets/js/vendor/three.module.min.js', true);
  await checkAsset('/assets/css/app.css', false);

  await checkModule('Bibliothèque Three.js', '/assets/js/vendor/three.module.min.js');
  await checkModule('Moteur de particules', '/assets/js/particles/ParticleField.js');
  await checkModule('Poussière d\'ambiance', '/assets/js/particles/DustField.js');
  await checkModule('Interface', '/assets/js/ui.js');

  try {
    const response = await fetch('/api/shape/accueil/hero?format=bin');
    const buffer = await response.arrayBuffer();
    add(
      'API des formes',
      response.ok && buffer.byteLength > 0,
      response.ok ? `${(buffer.byteLength / 3 / 4).toLocaleString('fr-FR')} particules reçues` : `statut ${response.status}`
    );
  } catch (error) {
    add('API des formes', false, error.message);
  }

  await runEngine();
  probeHomePage();
}

run();

document.getElementById('copy-report').addEventListener('click', async () => {
  try {
    await navigator.clipboard.writeText(report.textContent);
    document.getElementById('copy-report').textContent = 'Copié !';
  } catch {
    const range = document.createRange();
    range.selectNodeContents(report);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
  }
});
