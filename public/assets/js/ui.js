/**
 * Comportements d'interface : défilement lissé, révélation du texte,
 * détection de la section active et navigation.
 * Aucune bibliothèque externe.
 */

const REDUCED = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const COARSE = window.matchMedia('(pointer: coarse)').matches;

/**
 * Défilement lissé par transformation.
 *
 * La barre de défilement native reste maîtresse — on ne détourne aucun
 * événement — mais le contenu rejoint sa position par amortissement, ce qui
 * donne le glissé caractéristique des sites d'agence. Sur écran tactile et en
 * mouvement réduit, on rend la main au navigateur.
 */
export class SmoothScroll {
  constructor(wrapper, content, { damping = 0.085 } = {}) {
    this.wrapper = wrapper;
    this.content = content;
    this.damping = damping;
    this.current = 0;
    this.target = 0;
    this.enabled = !REDUCED && !COARSE;

    if (!this.enabled) {
      document.documentElement.classList.add('is-native-scroll');
      return;
    }

    document.documentElement.classList.add('is-smooth-scroll');
    this.resize();
    this.#tick();

    window.addEventListener('resize', () => this.resize(), { passive: true });
    // Le contenu grandit quand les images et les polices arrivent.
    if ('ResizeObserver' in window) {
      new ResizeObserver(() => this.resize()).observe(this.content);
    }
  }

  resize() {
    if (!this.enabled) return;
    // Le corps de page conserve la hauteur réelle : la barre de défilement reste juste.
    document.body.style.height = `${this.content.getBoundingClientRect().height}px`;
  }

  #tick = () => {
    requestAnimationFrame(this.#tick);
    this.target = window.scrollY || window.pageYOffset;
    this.current += (this.target - this.current) * this.damping;
    // En dessous du dixième de pixel, l'écart n'est plus perceptible.
    if (Math.abs(this.target - this.current) < 0.1) this.current = this.target;
    this.content.style.transform = `translate3d(0, ${-this.current.toFixed(2)}px, 0)`;
  };

  get progress() {
    const max = document.body.scrollHeight - window.innerHeight;
    return max > 0 ? Math.min(1, Math.max(0, (window.scrollY || 0) / max)) : 0;
  }
}

/**
 * Prévient dès qu'une nouvelle section occupe le centre de l'écran.
 */
export class SectionWatcher {
  constructor(sections, onChange) {
    this.onChange = onChange;
    this.active = null;
    this.ratios = new Map();

    this.observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          this.ratios.set(entry.target.id, entry.isIntersecting ? entry.intersectionRatio : 0);
        }
        // La section la plus visible l'emporte : pas de clignotement à la frontière.
        let best = null;
        let bestRatio = 0;
        for (const [id, ratio] of this.ratios) {
          if (ratio > bestRatio) {
            best = id;
            bestRatio = ratio;
          }
        }
        if (best && best !== this.active) {
          this.active = best;
          this.onChange(best);
        }
      },
      { threshold: [0, 0.15, 0.35, 0.55, 0.75, 1] }
    );

    sections.forEach((section) => this.observer.observe(section));
  }
}

/**
 * Découpe les titres en mots et les fait monter un par un à l'entrée dans l'écran.
 */
export function setupReveal(root = document) {
  const targets = root.querySelectorAll('[data-reveal]');

  targets.forEach((element) => {
    if (element.dataset.revealReady === '1') return;
    element.dataset.revealReady = '1';

    if (element.dataset.reveal === 'words') {
      splitWords(element);
    }
  });

  if (REDUCED) {
    targets.forEach((element) => element.classList.add('is-revealed'));
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-revealed');
        // Une révélation ne se rejoue pas : on libère l'observation.
        observer.unobserve(entry.target);
      });
    },
    { threshold: 0.18, rootMargin: '0px 0px -8% 0px' }
  );

  targets.forEach((element) => observer.observe(element));
}

function splitWords(element) {
  const words = element.textContent.trim().split(/\s+/);
  element.textContent = '';
  words.forEach((word, index) => {
    const outer = document.createElement('span');
    outer.className = 'word';
    const inner = document.createElement('span');
    inner.className = 'word__inner';
    inner.textContent = word;
    // Le retard croissant crée la cascade, plafonné pour les titres longs.
    inner.style.transitionDelay = `${Math.min(index * 55, 700)}ms`;
    outer.appendChild(inner);
    element.appendChild(outer);
    if (index < words.length - 1) element.appendChild(document.createTextNode(' '));
  });
}

/**
 * Navigation : lien actif, défilement doux à l'ancre, menu mobile.
 */
export function setupNavigation({ onNavigate } = {}) {
  const links = Array.from(document.querySelectorAll('[data-nav-link]'));
  const toggle = document.querySelector('[data-nav-toggle]');
  const panel = document.querySelector('[data-nav-panel]');

  const closeMenu = () => {
    document.documentElement.classList.remove('nav-open');
    toggle?.setAttribute('aria-expanded', 'false');
  };

  toggle?.addEventListener('click', () => {
    const open = document.documentElement.classList.toggle('nav-open');
    toggle.setAttribute('aria-expanded', String(open));
  });

  panel?.addEventListener('click', (event) => {
    if (event.target.closest('[data-nav-link]')) closeMenu();
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeMenu();
  });

  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', (event) => {
      const id = anchor.getAttribute('href').slice(1);
      const target = document.getElementById(id);
      if (!target) return;
      event.preventDefault();
      closeMenu();
      // getBoundingClientRect tient compte de la transformation du défilement lissé.
      const top = target.getBoundingClientRect().top + (window.scrollY || 0);
      window.scrollTo({ top, behavior: REDUCED ? 'auto' : 'smooth' });
      onNavigate?.(id);
    });
  });

  return {
    setActive(id) {
      links.forEach((link) => {
        link.classList.toggle('is-active', link.dataset.navLink === id);
      });
    },
  };
}

export const prefersReducedMotion = REDUCED;
export const isCoarsePointer = COARSE;
