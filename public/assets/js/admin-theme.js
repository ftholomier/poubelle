/**
 * Aperçu en direct de la couleur du site.
 *
 * La palette n'est pas recalculée ici : elle est demandée à PHP, qui applique
 * exactement la dérivation utilisée par le site. Une seule implémentation,
 * donc aucun écart possible entre l'aperçu et le rendu réel.
 */

const hex = document.getElementById('dominant-hex');
const picker = document.getElementById('dominant-color');
const harmony = document.getElementById('harmony');
const preview = document.getElementById('theme-preview');
const values = preview.querySelector('.preview__values');

const LABELS = {
  accent: 'Accent',
  accent2: 'Accent 2',
  accent3: 'Accent 3',
  background: 'Fond',
  surface: 'Surface',
  foreground: 'Texte',
  muted: 'Texte secondaire',
};

let timer = null;
let token = 0;

function normalize(value) {
  const cleaned = value.trim().replace(/^#/, '');
  return /^[0-9a-fA-F]{6}$/.test(cleaned) ? `#${cleaned.toLowerCase()}` : null;
}

async function refresh() {
  const color = normalize(hex.value);
  if (!color) return;

  const mine = ++token;
  const params = new URLSearchParams({ dominant: color, harmony: harmony.value });

  try {
    const response = await fetch(`/admin/palette?${params}`, { headers: { Accept: 'application/json' } });
    if (!response.ok) return;
    const palette = await response.json();
    // Une réponse plus récente est peut-être déjà arrivée.
    if (mine !== token) return;
    apply(palette);
  } catch {
    // Aperçu indisponible : le formulaire reste utilisable, PHP fera foi.
  }
}

function apply(palette) {
  preview.style.setProperty('--p-accent', palette.accent);
  preview.style.setProperty('--p-accent-2', palette.accent2);
  preview.style.setProperty('--p-accent-3', palette.accent3);
  preview.style.setProperty('--p-bg', palette.background);
  preview.style.setProperty('--p-surface', palette.surface);
  preview.style.setProperty('--p-fg', palette.foreground);
  preview.style.setProperty('--p-muted', palette.muted);

  preview.querySelectorAll('[data-role]').forEach((swatch) => {
    swatch.style.background = palette[swatch.dataset.role] || 'transparent';
  });

  values.innerHTML = '';
  for (const [key, label] of Object.entries(LABELS)) {
    const dt = document.createElement('dt');
    dt.textContent = label;
    const dd = document.createElement('dd');
    dd.innerHTML = `<i style="background:${palette[key]}"></i><code>${palette[key]}</code>`;
    values.append(dt, dd);
  }
}

function schedule() {
  clearTimeout(timer);
  timer = setTimeout(refresh, 140);
}

// Les deux champs de couleur restent synchronisés dans les deux sens.
picker.addEventListener('input', () => {
  hex.value = picker.value;
  schedule();
});

hex.addEventListener('input', () => {
  const color = normalize(hex.value);
  if (color) picker.value = color;
  schedule();
});

harmony.addEventListener('change', refresh);

document.querySelectorAll('.theme__preset').forEach((button) => {
  button.addEventListener('click', () => {
    hex.value = button.dataset.color;
    picker.value = button.dataset.color;
    refresh();
  });
});

refresh();
