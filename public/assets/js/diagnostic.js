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
  body.innerHTML = rows
    .map(
      (r) =>
        `<tr><td style="width:2.2rem">${r.ok ? '✅' : '❌'}</td>` +
        `<td><strong>${escapeHtml(r.label)}</strong></td>` +
        `<td>${escapeHtml(r.detail)}</td></tr>`
    )
    .join('');

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
