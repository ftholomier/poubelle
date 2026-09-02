import { ParticleField, supportsWebGL } from './particles/ParticleField.js';
import { textToPoints, fallbackSphere } from './particles/shapeLoader.js';

/**
 * Atelier de formes du back-office.
 *
 * Chaque réglage relance le calcul du nuage, l'aperçu se met à jour en direct,
 * et le dessin obtenu peut être affecté à n'importe quelle section du site.
 */

const form = document.getElementById('shape-form');
const status = document.getElementById('status');
const snippet = document.querySelector('#snippet code');
const canvas = document.getElementById('particles');

const target = document.getElementById('target');
const config = JSON.parse(document.getElementById('studio-config')?.textContent || '{}');

const OUTPUTS = {
  count: (v) => v,
  depth: (v) => Number(v).toFixed(2),
  scale: (v) => Number(v).toFixed(2),
  spin: (v) => Number(v).toFixed(2),
  offsetX: (v) => Number(v).toFixed(2),
  offsetY: (v) => Number(v).toFixed(2),
  seed: (v) => v,
};

let field = null;
let requestToken = 0;

init();

async function init() {
  if (!supportsWebGL()) {
    say("Ce navigateur n'expose pas WebGL : l'aperçu est indisponible.", 'error');
    return;
  }

  // Le canevas naît transparent et n'apparaît qu'une fois le moteur prêt :
  // c'est la page d'accueil qui pose habituellement cette classe.
  document.documentElement.classList.add('is-ready');

  const theme = JSON.parse(document.getElementById('theme-data')?.textContent || '{}');
  field = new ParticleField(canvas, theme);
  // Capacité maximale allouée d'emblée : changer le nombre de particules ne réalloue rien.
  field.allocate(40000);
  field.resize();
  field.start();

  window.addEventListener('resize', () => field.resize(), { passive: true });
  window.addEventListener('pointermove', (event) => {
    field.setPointer(
      (event.clientX / window.innerWidth) * 2 - 1,
      -((event.clientY / window.innerHeight) * 2 - 1)
    );
  }, { passive: true });

  await loadCatalogue();
  syncVisibility();
  syncOutputs();

  form.addEventListener('input', (event) => {
    syncOutputs();
    if (event.target.id === 'type') syncVisibility();
    schedule();
  });

  document.getElementById('copy').addEventListener('click', copySnippet);
  document.getElementById('assign').addEventListener('click', assign);

  // Changer de section cible recharge ses réglages : on repart de l'existant
  // plutôt que d'écraser une forme déjà en place par les valeurs par défaut.
  target?.addEventListener('change', () => {
    loadShapeInto(config.shapes?.[target.value]);
    refresh();
  });

  loadShapeInto(config.shapes?.[target?.value]);
  refresh();
}

/**
 * Reporte une forme enregistrée dans les champs du formulaire.
 *
 * @param {object|undefined} shape
 */
function loadShapeInto(shape) {
  if (!shape) return;

  form.type.value = shape.type || 'preset';
  syncVisibility();

  if (shape.src) form.src.value = shape.src;
  if (shape.preset) form.preset.value = shape.preset;
  if (shape.text) form.text.value = shape.text;
  if (shape.mode) form.mode.value = shape.mode;
  form.fillRule.value = shape.fillRule || 'nonzero';
  form.criterion.value = shape.criterion || 'auto';
  form.count.value = shape.count ?? 14000;
  form.depth.value = shape.depth ?? 0.12;
  form.scale.value = shape.scale ?? 1;
  form.spin.value = shape.spin ?? 0;
  form.spinAxis.value = shape.spinAxis === 'z' ? 'z' : 'y';
  form.offsetX.value = shape.offsetX ?? 0;
  form.offsetY.value = shape.offsetY ?? 0;
  form.seed.value = shape.seed ?? 1337;
  form.label.value = shape.label || '';

  syncOutputs();
}

/** Enregistre le dessin courant sur la section choisie. */
async function assign() {
  const [page, section] = (target?.value || '').split('|');
  if (!page || !section) {
    say('Choisissez une section cible.', 'error');
    return;
  }

  const button = document.getElementById('assign');
  button.disabled = true;
  say('Enregistrement…');

  try {
    const response = await fetch('/admin/formes', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': config.csrf },
      body: JSON.stringify({ csrf: config.csrf, page, section, shape: readForm() }),
    });
    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
      throw new Error(data.error || `L'enregistrement a échoué (${response.status}).`);
    }

    // La forme enregistrée devient la nouvelle référence de cette section.
    config.shapes = config.shapes || {};
    config.shapes[`${page}|${section}`] = readForm();
    say(data.message || 'Forme enregistrée.');
  } catch (error) {
    say(error.message, 'error');
  } finally {
    button.disabled = false;
  }
}

/** Remplit les listes déroulantes depuis GET /api/shapes. */
async function loadCatalogue() {
  try {
    const response = await fetch('/api/shapes');
    if (!response.ok) throw new Error(`statut ${response.status}`);
    const data = await response.json();

    const src = document.getElementById('src');
    src.innerHTML = '';
    data.files.forEach((file) => {
      const option = document.createElement('option');
      option.value = file.src;
      option.textContent = `${file.src} (${file.type})`;
      option.dataset.type = file.type;
      src.appendChild(option);
    });

    const preset = document.getElementById('preset');
    preset.innerHTML = '';
    Object.entries(data.presets).forEach(([key, label]) => {
      const option = document.createElement('option');
      option.value = key;
      option.textContent = `${key} — ${label}`;
      preset.appendChild(option);
    });
  } catch (error) {
    say(`Catalogue indisponible : ${error.message}`, 'error');
  }
}

/** N'affiche que les réglages qui concernent le type sélectionné. */
function syncVisibility() {
  const type = form.type.value;
  form.querySelectorAll('[data-when]').forEach((field) => {
    field.hidden = !field.dataset.when.split(' ').includes(type);
  });

  // La liste des sources se limite au format pertinent.
  const src = document.getElementById('src');
  let firstVisible = null;
  Array.from(src.options).forEach((option) => {
    const matches = type === 'image' ? option.dataset.type === 'image' : option.dataset.type === 'svg';
    option.hidden = !matches;
    if (matches && !firstVisible) firstVisible = option;
  });
  if (firstVisible && src.selectedOptions[0]?.hidden) src.value = firstVisible.value;
}

function syncOutputs() {
  Object.entries(OUTPUTS).forEach(([name, format]) => {
    const output = document.getElementById(`${name}-out`);
    if (output && form[name]) output.textContent = format(form[name].value);
  });
}

let timer = null;
function schedule() {
  // Les curseurs émettent en continu : on attend une pause avant de recalculer.
  clearTimeout(timer);
  timer = setTimeout(refresh, 180);
}

async function refresh() {
  const shape = readForm();
  renderSnippet(shape);

  if (!field) return;
  const token = ++requestToken;
  say('Calcul du nuage…');

  try {
    const started = performance.now();
    const cloud = shape.type === 'text'
      ? textToPoints(shape)
      : await fetchCloud(shape);

    // Un réglage plus récent a déjà été demandé : ce résultat est périmé.
    if (token !== requestToken) return;

    applyCloud(cloud, shape);
    say(`${(cloud.length / 3).toLocaleString('fr-FR')} particules · ${Math.round(performance.now() - started)} ms`);
  } catch (error) {
    if (token === requestToken) say(error.message, 'error');
  }
}

async function fetchCloud(shape) {
  const params = new URLSearchParams({
    type: shape.type,
    count: String(shape.count),
    depth: String(shape.depth),
    scale: String(shape.scale),
    seed: String(shape.seed),
    format: 'bin',
  });
  if (shape.src) params.set('src', shape.src);
  if (shape.preset) params.set('preset', shape.preset);
  if (shape.mode) params.set('mode', shape.mode);
  if (shape.fillRule) params.set('fillRule', shape.fillRule);
  if (shape.criterion) params.set('criterion', shape.criterion);

  const response = await fetch(`/api/preview?${params}`);
  if (!response.ok) {
    const detail = await response.json().catch(() => ({}));
    throw new Error(detail.error || `L'API a répondu ${response.status}`);
  }

  return new Float32Array(await response.arrayBuffer());
}

/** Injecte le nuage dans le champ de particules via un descripteur pré-résolu. */
function applyCloud(cloud, shape) {
  field.morphTo({
    id: `apercu-${requestToken}`,
    type: 'preloaded',
    count: shape.count,
    spin: shape.spin,
    spinAxis: shape.spinAxis,
    offsetX: shape.offsetX,
    offsetY: shape.offsetY,
    cloud,
  });
}

/**
 * Lit les réglages du formulaire et en fait une déclaration de forme,
 * dans le format attendu par content/pages/*.json.
 *
 * @returns {object}
 */
function readForm() {
  const data = Object.fromEntries(new FormData(form));
  const type = data.type;

  const shape = {
    type,
    count: Number(data.count),
    depth: Number(data.depth),
    scale: Number(data.scale),
    spin: Number(data.spin),
    spinAxis: data.spinAxis,
    offsetX: Number(data.offsetX),
    offsetY: Number(data.offsetY),
    seed: Number(data.seed),
  };

  if (type === 'svg') {
    shape.src = data.src;
    shape.mode = data.mode;
    if (data.fillRule !== 'nonzero') shape.fillRule = data.fillRule;
  } else if (type === 'image') {
    shape.src = data.src;
    if (data.criterion !== 'auto') shape.criterion = data.criterion;
  } else if (type === 'preset') {
    shape.preset = data.preset;
  } else if (type === 'text') {
    shape.text = data.text;
  }

  const label = (data.label || '').trim();
  if (label) shape.label = label;

  return shape;
}

/** Construit le bloc JSON exact, tel qu'il sera écrit dans content/pages/. */
function renderSnippet(shape) {
  const block = { ...shape };
  // Les valeurs par défaut n'ont pas à encombrer le fichier de contenu.
  if (block.spin === 0) delete block.spin;
  if (!block.spin || block.spinAxis === 'y') delete block.spinAxis;
  if (block.offsetX === 0) delete block.offsetX;
  if (block.offsetY === 0) delete block.offsetY;
  if (block.seed === 1337) delete block.seed;
  if (block.scale === 1) delete block.scale;

  snippet.textContent = `"shape": ${JSON.stringify(block, null, 2)}`;
}

async function copySnippet() {
  try {
    await navigator.clipboard.writeText(snippet.textContent);
    say('Bloc copié dans le presse-papiers.');
  } catch {
    // Le presse-papiers est refusé hors contexte sécurisé : on sélectionne le texte.
    const range = document.createRange();
    range.selectNodeContents(snippet);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);
    say('Copie automatique refusée : le bloc est sélectionné, faites Ctrl+C.');
  }
}

function say(message, state = 'ok') {
  status.textContent = message;
  status.dataset.state = state;
}
