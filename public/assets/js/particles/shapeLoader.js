/**
 * Récupération des nuages de points.
 *
 * Les formes vectorielles, les images et les formes mathématiques sont calculées
 * par PHP et transférées en Float32 brut. Seul le texte est tracé ici : il a
 * besoin des polices réellement chargées par le navigateur.
 */

const cache = new Map();

/**
 * @param {object} descriptor  entrée « shape » renvoyée par /api/sections
 * @returns {Promise<Float32Array>} positions à plat, x/y/z par particule
 */
export async function loadShape(descriptor) {
  // Le laboratoire calcule déjà le nuage lui-même : rien à récupérer ni à mettre en cache.
  if (descriptor.cloud instanceof Float32Array) return descriptor.cloud;

  const key = descriptor.shapeUrl || JSON.stringify(descriptor);
  if (cache.has(key)) return cache.get(key);

  const promise = build(descriptor).catch((error) => {
    // Une forme absente ne doit jamais faire tomber la page : on retombe sur une sphère.
    console.warn('[particules] forme indisponible, repli sur une sphère', error);
    return fallbackSphere(descriptor.count || 8000);
  });

  cache.set(key, promise);
  return promise;
}

async function build(descriptor) {
  if (descriptor.type === 'text') {
    return textToPoints(descriptor);
  }

  // Le format binaire évite l'analyse d'un tableau JSON de 48 000 nombres.
  const response = await fetch(`${descriptor.shapeUrl}?format=bin`, {
    headers: { Accept: 'application/octet-stream' },
  });
  if (!response.ok) {
    throw new Error(`${descriptor.shapeUrl} a répondu ${response.status}`);
  }

  const buffer = await response.arrayBuffer();
  return new Float32Array(buffer);
}

/**
 * Trace le texte dans un canevas hors écran, puis tire des particules
 * proportionnellement à l'opacité des pixels.
 */
export function textToPoints({ text, font, count = 12000, scale = 1, depth = 0.08, seed = 1337 }) {
  const canvas = document.createElement('canvas');
  const ctx = canvas.getContext('2d', { willReadFrequently: true });
  const fontSpec = font || '700 200px Montserrat, system-ui, sans-serif';

  ctx.font = fontSpec;
  const metrics = ctx.measureText(text);
  const ascent = metrics.actualBoundingBoxAscent || 150;
  const descent = metrics.actualBoundingBoxDescent || 50;
  const padding = 24;

  canvas.width = Math.max(8, Math.ceil(metrics.width) + padding * 2);
  canvas.height = Math.max(8, Math.ceil(ascent + descent) + padding * 2);

  // Redéfinir la taille du canevas réinitialise le contexte : on repose la police.
  ctx.font = fontSpec;
  ctx.fillStyle = '#fff';
  ctx.textBaseline = 'alphabetic';
  ctx.fillText(text, padding, padding + ascent);

  const { data, width, height } = ctx.getImageData(0, 0, canvas.width, canvas.height);

  // Table cumulative des opacités, pour un tirage pondéré par la densité d'encre.
  const weights = [];
  const pixels = [];
  let total = 0;
  for (let i = 3; i < data.length; i += 4) {
    const alpha = data[i] / 255;
    if (alpha < 0.08) continue;
    total += alpha;
    weights.push(total);
    pixels.push((i - 3) / 4);
  }

  if (!pixels.length) return fallbackSphere(count);

  const rng = mulberry32(seed);
  const positions = new Float32Array(count * 3);
  const longest = Math.max(width, height);
  const factor = (2 / longest) * scale;
  let cursor = 0;

  for (let i = 0; i < count; i++) {
    const target = ((i + rng()) / count) * total;
    while (cursor < weights.length - 1 && weights[cursor] < target) cursor++;
    const index = pixels[cursor];
    const x = (index % width) + rng();
    const y = Math.floor(index / width) + rng();

    positions[i * 3] = (x - width / 2) * factor;
    positions[i * 3 + 1] = -(y - height / 2) * factor;
    positions[i * 3 + 2] = (rng() * 2 - 1) * depth;
  }

  return positions;
}

/** Sphère de secours, servie si une forme échoue. */
export function fallbackSphere(count) {
  const positions = new Float32Array(count * 3);
  const golden = Math.PI * (3 - Math.sqrt(5));
  for (let i = 0; i < count; i++) {
    const y = 1 - (i / Math.max(1, count - 1)) * 2;
    const radius = Math.sqrt(Math.max(0, 1 - y * y));
    const theta = golden * i;
    positions[i * 3] = Math.cos(theta) * radius;
    positions[i * 3 + 1] = y;
    positions[i * 3 + 2] = Math.sin(theta) * radius;
  }
  return positions;
}

/** Même générateur pseudo-aléatoire que côté PHP : rendus identiques. */
function mulberry32(seed) {
  let a = seed >>> 0;
  return function next() {
    a = (a + 0x6d2b79f5) >>> 0;
    let t = a;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
}
