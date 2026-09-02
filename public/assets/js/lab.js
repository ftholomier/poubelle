import { ParticleField, supportsWebGL } from './particles/ParticleField.js';
import { textToPoints, fallbackSphere } from './particles/shapeLoader.js';

/**
 * Laboratoire de formes : chaque réglage relance immédiatement le calcul du
 * nuage, et le bloc JSON prêt à coller se met à jour en même temps.
 */

const form = document.getElementById('lab-form');
const status = document.getElementById('lab-status');
const snippet = document.querySelector('#snippet code');
const canvas = document.getElementById('particles');

const OUTPUTS = {
  count: (v) => v,
  depth: (v) => Number(v).toFixed(2),
  scale: (v) => Number(v).toFixed(2),
  spin: (v) => Number(v).toFixed(2),
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

  refresh();
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
  const config = readForm();
  renderSnippet(config);

  if (!field) return;
  const token = ++requestToken;
  say('Calcul du nuage…');

  try {
    const started = performance.now();
    const cloud = config.type === 'text'
      ? textToPoints(config)
      : await fetchCloud(config);

    // Un réglage plus récent a déjà été demandé : ce résultat est périmé.
    if (token !== requestToken) return;

    applyCloud(cloud, config);
    say(`${(cloud.length / 3).toLocaleString('fr-FR')} particules · ${Math.round(performance.now() - started)} ms`);
  } catch (error) {
    if (token === requestToken) say(error.message, 'error');
  }
}

async function fetchCloud(config) {
  const params = new URLSearchParams({
    type: config.type,
    count: String(config.count),
    depth: String(config.depth),
    scale: String(config.scale),
    seed: String(config.seed),
    format: 'bin',
  });
  if (config.src) params.set('src', config.src);
  if (config.preset) params.set('preset', config.preset);
  if (config.mode) params.set('mode', config.mode);
  if (config.fillRule) params.set('fillRule', config.fillRule);
  if (config.criterion) params.set('criterion', config.criterion);

  const response = await fetch(`/api/preview?${params}`);
  if (!response.ok) {
    const detail = await response.json().catch(() => ({}));
    throw new Error(detail.error || `L'API a répondu ${response.status}`);
  }

  return new Float32Array(await response.arrayBuffer());
}

/** Injecte le nuage dans le champ de particules via un descripteur pré-résolu. */
function applyCloud(cloud, config) {
  field.morphTo({
    id: `apercu-${requestToken}`,
    type: 'preloaded',
    count: config.count,
    spin: config.spin,
    spinAxis: config.spinAxis,
    cloud,
  });
}

function readForm() {
  const data = Object.fromEntries(new FormData(form));
  const type = data.type;

  const config = {
    type,
    count: Number(data.count),
    depth: Number(data.depth),
    scale: Number(data.scale),
    spin: Number(data.spin),
    spinAxis: data.spinAxis,
    seed: Number(data.seed),
  };

  if (type === 'svg') {
    config.src = data.src;
    config.mode = data.mode;
    if (data.fillRule !== 'nonzero') config.fillRule = data.fillRule;
  } else if (type === 'image') {
    config.src = data.src;
    if (data.criterion !== 'auto') config.criterion = data.criterion;
  } else if (type === 'preset') {
    config.preset = data.preset;
  } else if (type === 'text') {
    config.text = data.text;
  }

  return config;
}

/** Construit le bloc JSON exact à coller dans content/sections.json. */
function renderSnippet(config) {
  const block = { ...config };
  // Les valeurs par défaut n'ont pas à encombrer le fichier de contenu.
  if (block.spin === 0) delete block.spin;
  if (!block.spin || block.spinAxis === 'y') delete block.spinAxis;
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
