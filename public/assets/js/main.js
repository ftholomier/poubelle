import { ParticleField, supportsWebGL } from './particles/ParticleField.js';
import { SmoothScroll, SectionWatcher, setupReveal, setupNavigation } from './ui.js';

/**
 * Assemblage de la page : le nuage de particules suit la section à l'écran,
 * chaque section ayant son dessin déclaré dans content/sections.json.
 */

const boot = async () => {
  const root = document.documentElement;
  const canvas = document.getElementById('particles');

  setupReveal();
  const nav = setupNavigation();

  const wrapper = document.getElementById('smooth-wrapper');
  const content = document.getElementById('smooth-content');
  const scroller = new SmoothScroll(wrapper, content);

  const sections = Array.from(document.querySelectorAll('main [data-section]'));

  // Déclaré avant l'observateur : sa fonction de rappel peut se déclencher
  // pendant l'initialisation, et même ne jamais avoir de champ à piloter
  // lorsque WebGL est indisponible.
  let field = null;

  // Les descriptions de formes sont déposées dans la page par PHP :
  // aucune requête supplémentaire n'est nécessaire pour savoir quoi dessiner.
  const descriptors = readDescriptors();

  const watcher = new SectionWatcher(sections, (id) => {
    nav.setActive(id);
    root.dataset.activeSection = id;
    announceShape(descriptors[id]);
    field?.morphTo(descriptors[id]);
  });

  if (!canvas || !supportsWebGL()) {
    // Sans WebGL, le site reste entièrement lisible : seul le décor disparaît.
    root.classList.add('no-webgl');
    canvas?.remove();
    markReady();
    return;
  }

  try {
    const theme = JSON.parse(document.getElementById('theme-data')?.textContent || '{}');
    field = new ParticleField(canvas, theme);
    field.allocate(maxCount(descriptors));
    field.resize();
    field.start();
  } catch (error) {
    console.error('[particules] initialisation impossible', error);
    root.classList.add('no-webgl');
    canvas.remove();
    markReady();
    return;
  }

  // Première forme affichée : celle de la section en haut de page.
  const first = sections[0]?.id;
  if (first && descriptors[first]) {
    await field.morphTo(descriptors[first]);
    announceShape(descriptors[first]);
  }

  window.addEventListener('resize', () => field.resize(), { passive: true });

  window.addEventListener('pointermove', (event) => {
    field.setPointer(
      (event.clientX / window.innerWidth) * 2 - 1,
      -((event.clientY / window.innerHeight) * 2 - 1)
    );
  }, { passive: true });

  window.addEventListener('pointerleave', () => field.clearPointer(), { passive: true });

  window.addEventListener('scroll', () => {
    field.setScroll(scroller.progress);
    updateProgressBar(scroller.progress);
  }, { passive: true });

  // Onglet en arrière-plan : inutile de consommer du processeur graphique.
  document.addEventListener('visibilitychange', () => {
    document.hidden ? field.stop() : field.start();
  });

  markReady();
  window.__particules = field;
};

/**
 * @returns {Record<string, object>} descripteur de forme par identifiant de section
 */
function readDescriptors() {
  const node = document.getElementById('shapes-data');
  if (!node) return {};
  try {
    return JSON.parse(node.textContent);
  } catch (error) {
    console.error('[particules] descripteurs de formes illisibles', error);
    return {};
  }
}

function maxCount(descriptors) {
  const counts = Object.values(descriptors).map((shape) => shape.count || 0);
  // Une marge minimale garantit un nuage dense même si le JSON est frugal.
  return Math.max(4000, ...counts);
}

/** Affiche le nom du dessin en cours, en bas de l'écran. */
function announceShape(descriptor) {
  const badge = document.querySelector('[data-shape-label]');
  if (!badge) return;
  const label = descriptor?.label || '';
  badge.textContent = label;
  badge.classList.toggle('is-visible', Boolean(label));
}

function updateProgressBar(ratio) {
  document.querySelector('[data-progress]')?.style.setProperty('--progress', ratio.toFixed(4));
}

function markReady() {
  document.documentElement.classList.add('is-ready');
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
  boot();
}
