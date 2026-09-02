import { ParticleField, supportsWebGL } from './particles/ParticleField.js';
import {
  SmoothScroll,
  SectionWatcher,
  MarqueeGroup,
  setupReveal,
  setupCounters,
  setupNavigation,
} from './ui.js';

/**
 * Assemblage du site.
 *
 * Le nuage de particules vit en dehors du contenu : il survit aux changements
 * de page, qui ne remplacent que le corps du document. C'est ce qui permet au
 * dessin de se transformer d'une page à l'autre au lieu de disparaître.
 */

const root = document.documentElement;

let field = null;
let scroller = null;
let watcher = null;
let marquees = null;
let nav = null;
let descriptors = readDescriptors();
let currentPage = document.getElementById('contenu')?.dataset.page || '';

/**
 * Désamorce le garde-fou posé par le gabarit : le script est bien là et les
 * animations d'apparition peuvent rester armées.
 */
function confirmScriptRunning() {
  clearTimeout(window.__revealGuard);
  root.classList.add('js');
  root.classList.remove('js-failed');
}

/** Rend tout le contenu visible : le script ne pourra pas l'animer. */
function giveUpAnimations(error) {
  console.error('[particules] démarrage impossible, contenu affiché sans animation', error);
  clearTimeout(window.__revealGuard);
  root.classList.remove('js');
  root.classList.add('js-failed');
}

/** Étapes qui n'ont pas abouti, relevées pour la page de diagnostic. */
const failures = [];

/**
 * Exécute une étape de la mise en route sans laisser son échec emporter les
 * suivantes. Chaque partie du site — défilement, bandeaux, révélations,
 * particules, poussière — doit pouvoir tomber seule : un décor qui ne se charge
 * pas ne doit jamais rendre le contenu illisible ni faire disparaître le reste.
 *
 * @param {string} label   nom de l'étape, pour le journal
 * @param {Function} step  ce qu'elle fait
 * @returns {*} le retour de l'étape, ou null si elle a échoué
 */
function safely(label, step) {
  try {
    return step();
  } catch (error) {
    failures.push(label);
    console.error(`[particules] « ${label} » a échoué`, error);
    return null;
  }
}

const boot = async () => {
  const canvas = document.getElementById('particles');

  // L'ordre reste le même, mais aucune étape ne peut plus bloquer les autres.
  nav = safely('navigation', () => setupNavigation());

  scroller = safely('défilement lissé', () =>
    new SmoothScroll(
      document.getElementById('smooth-wrapper'),
      document.getElementById('smooth-content')
    )
  );

  marquees = safely('bandeaux défilants', () => {
    const group = new MarqueeGroup(document);
    group.start();
    return group;
  });

  safely('révélation des textes', () => activateContent(document));
  safely('navigation interne', () => setupInternalNavigation());

  // Les textes sont branchés : on peut désarmer le garde-fou du gabarit.
  confirmScriptRunning();
  markReady();

  if (!canvas || !supportsWebGL()) {
    // Sans WebGL, le site reste entièrement lisible : seul le décor disparaît.
    root.classList.add('no-webgl');
    canvas?.remove();
    return;
  }

  field = safely('moteur de particules', () => {
    const theme = JSON.parse(document.getElementById('theme-data')?.textContent || '{}');
    const engine = new ParticleField(canvas, theme);
    engine.allocate(maxCount(descriptors));
    engine.resize();
    engine.start();
    return engine;
  });

  if (!field) {
    // Le moteur lui-même n'a pas démarré : le décor en dégradé prend le relais.
    root.classList.add('no-webgl');
    canvas.remove();
    return;
  }

  // Exposé dès maintenant : la page de diagnostic doit pouvoir interroger le
  // moteur même si une étape ultérieure échoue.
  window.__particules = field;
  window.__particulesEchecs = failures;

  // La poussière est un décor du décor : son échec ne doit rien emporter.
  safely("poussière d'ambiance", () => field.enableDust());

  await showFirstShape().catch((error) => {
    failures.push('première forme');
    console.error('[particules] première forme illisible', error);
  });

  window.addEventListener('resize', () => field.resize(), { passive: true });

  window.addEventListener('pointermove', (event) => {
    field.setPointer(
      (event.clientX / window.innerWidth) * 2 - 1,
      -((event.clientY / window.innerHeight) * 2 - 1)
    );
  }, { passive: true });

  window.addEventListener('pointerleave', () => field.clearPointer(), { passive: true });

  window.addEventListener('scroll', () => {
    if (scroller) field.setScroll(scroller.progress);
    field.setScrollDistance(window.scrollY || 0);
    updateProgressBar(scroller?.progress ?? 0);
  }, { passive: true });

  // Onglet en arrière-plan : inutile de consommer du processeur graphique.
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      field.stop();
      marquees?.stop();
    } else {
      field.start();
      marquees?.start();
    }
  });
};

/**
 * Branche les comportements sur le contenu affiché : révélations, compteurs,
 * observation des sections.
 */
function activateContent(scope) {
  setupReveal(scope);
  setupCounters(scope);

  const sections = Array.from(document.querySelectorAll('main [data-section]'));
  const onChange = (id) => {
    nav?.setCurrentPage(currentPage);
    root.dataset.activeSection = id;
    announceShape(descriptors[id]);
    field?.morphTo(descriptors[id]);
  };

  if (watcher) {
    watcher.onChange = onChange;
    watcher.refresh(sections);
  } else {
    watcher = new SectionWatcher(sections, onChange);
  }
}

async function showFirstShape() {
  const first = document.querySelector('main [data-section]')?.id;
  if (!field || !first || !descriptors[first]) return;

  await field.morphTo(descriptors[first]);
  announceShape(descriptors[first]);
}

// ------------------------------------------------- Navigation entre les pages

/**
 * Passe d'une page à l'autre sans recharger : seul le corps du document est
 * remplacé, le nuage de particules et l'en-tête restent en place. Au moindre
 * incident, on laisse le navigateur suivre le lien normalement.
 */
function setupInternalNavigation() {
  document.addEventListener('click', (event) => {
    if (event.defaultPrevented || event.button !== 0) return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const link = event.target.closest('a[data-internal]');
    if (!link) return;

    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin) return;
    if (url.pathname === location.pathname) {
      event.preventDefault();
      window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }

    event.preventDefault();
    navigate(url.pathname, true);
  });

  window.addEventListener('popstate', () => navigate(location.pathname, false));
}

let navigating = false;

async function navigate(path, pushState) {
  if (navigating) return;
  navigating = true;

  const veil = document.querySelector('[data-page-veil]');
  veil?.classList.add('is-active');

  try {
    const response = await fetch(path, {
      headers: { 'X-Requested-With': 'fragment' },
      credentials: 'same-origin',
    });
    if (!response.ok) throw new Error(`statut ${response.status}`);

    const html = await response.text();
    const title = decodeURIComponent(response.headers.get('X-Page-Title') || document.title);
    const slug = response.headers.get('X-Page-Slug') || '';
    const shapes = response.headers.get('X-Page-Shapes');

    // Le voile couvre l'écran le temps de la substitution.
    await wait(260);

    const main = document.getElementById('contenu');
    main.innerHTML = html;
    main.dataset.page = slug;
    document.title = title;
    currentPage = slug;

    descriptors = shapes ? JSON.parse(decodeURIComponent(shapes)) : {};
    if (pushState) history.pushState({ path }, '', path);

    // La hauteur du nouveau contenu d'abord, le saut ensuite : sans quoi le
    // navigateur bride la remontée à la hauteur de l'ancienne page.
    scroller?.resize();
    scroller ? scroller.jumpTo(0) : window.scrollTo(0, 0);

    safely('bandeaux défilants', () => marquees?.refresh(document));
    nav?.setCurrentPage(slug);
    safely('révélation des textes', () => activateContent(main));
    await showFirstShape().catch((error) => console.error('[particules] forme illisible', error));
  } catch (error) {
    // Rien ne doit empêcher d'atteindre la page : on recharge franchement.
    console.warn('[navigation] bascule vers un chargement classique', error);
    location.href = path;
    return;
  } finally {
    navigating = false;
    veil?.classList.remove('is-active');
  }
}

const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// ------------------------------------------------------------------- Outils

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

function maxCount(shapes) {
  const counts = Object.values(shapes).map((shape) => shape.count || 0);
  // Le tampon est alloué une seule fois : il doit tenir la plus dense des
  // formes de tout le site, pas seulement de la page ouverte.
  return Math.max(18000, ...counts);
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
  root.classList.add('is-ready');
}

/** Démarre, et retombe sur un affichage sans animation en cas de pépin. */
const start = () => {
  boot().catch(giveUpAnimations);
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', start, { once: true });
} else {
  start();
}
