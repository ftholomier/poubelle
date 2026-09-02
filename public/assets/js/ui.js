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

    // Le contenu étant déplacé par transformation, le navigateur ne sait plus
    // amener un élément à l'écran tout seul : un lien atteint à la tabulation
    // resterait invisible. On s'en charge à sa place.
    document.addEventListener('focusin', (event) => {
      const element = event.target;
      if (!(element instanceof HTMLElement) || !this.content.contains(element)) return;
      const box = element.getBoundingClientRect();
      const margin = 80;
      if (box.top >= margin && box.bottom <= window.innerHeight - margin) return;
      this.scrollToElement(element);
    });
  }

  /**
   * Amène un élément dans la fenêtre. À utiliser partout où l'on écrirait
   * scrollIntoView, sans effet ici puisque le contenu ne défile pas lui-même.
   */
  scrollToElement(element, { behavior = 'smooth', offset = 120 } = {}) {
    const top = element.getBoundingClientRect().top + (window.scrollY || 0) - offset;
    window.scrollTo({ top: Math.max(0, top), behavior: REDUCED ? 'auto' : behavior });
  }

  resize() {
    if (!this.enabled) return;
    // Le corps de page conserve la hauteur réelle : la barre de défilement reste juste.
    document.body.style.height = `${this.content.getBoundingClientRect().height}px`;
  }

  /**
   * Repositionne la page instantanément, sans l'amortissement habituel.
   *
   * Au changement de page, laisser le lissage remonter depuis l'ancienne
   * position ferait défiler toute la page précédente à toute vitesse — et le
   * nuage de particules essaierait de dessiner chaque section traversée.
   */
  jumpTo(y = 0) {
    window.scrollTo(0, y);
    if (!this.enabled) return;
    this.current = y;
    this.target = y;
    this.wrapper.scrollTop = 0;
    this.content.style.transform = `translate3d(0, ${-y}px, 0)`;
  }

  #tick = () => {
    requestAnimationFrame(this.#tick);

    // Filet de sécurité : sur les navigateurs sans « overflow: clip », le
    // conteneur peut encore être décalé par le navigateur lui-même — au moment
    // de donner le focus à un lien hors écran, par exemple.
    if (this.wrapper.scrollTop !== 0) {
      const stolen = this.wrapper.scrollTop;
      this.wrapper.scrollTop = 0;
      window.scrollTo(0, (window.scrollY || 0) + stolen);
    }

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

    this.observe(sections);
  }

  observe(sections) {
    sections.forEach((section) => this.observer.observe(section));
  }

  /**
   * Repart de zéro sur un nouveau jeu de sections, après un changement de page.
   */
  refresh(sections) {
    this.observer.disconnect();
    this.ratios.clear();
    this.active = null;
    this.observe(sections);
  }
}

/**
 * Découpe les titres en mots et les fait monter un par un à l'entrée dans l'écran.
 */
export function setupReveal(root = document) {
  const targets = root.querySelectorAll('[data-reveal]');
  if (targets.length === 0) return;

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

  document.addEventListener('click', (event) => {
    const anchor = event.target.closest('a[href^="#"]');
    if (!anchor) return;
    const target = document.getElementById(anchor.getAttribute('href').slice(1));
    if (!target) return;
    event.preventDefault();
    closeMenu();
    // getBoundingClientRect tient compte de la transformation du défilement lissé.
    const top = target.getBoundingClientRect().top + (window.scrollY || 0);
    window.scrollTo({ top, behavior: REDUCED ? 'auto' : 'smooth' });
    onNavigate?.(anchor.getAttribute('href').slice(1));
  });

  return {
    closeMenu,
    /** Met en avant la page courante dans le menu. */
    setCurrentPage(slug) {
      document.querySelectorAll('[data-nav-link]').forEach((link) => {
        const active = link.dataset.navLink === slug;
        link.classList.toggle('is-active', active);
        active ? link.setAttribute('aria-current', 'page') : link.removeAttribute('aria-current');
      });
    },
  };
}


/**
 * Bandeaux de texte défilant.
 *
 * Deux pistes identiques se suivent ; on translate les deux du même décalage,
 * qui repart de zéro dès qu'il atteint la largeur d'une piste. La boucle est
 * ainsi invisible, sans clonage ni saut. La vitesse s'ajoute à celle du
 * défilement de la page : le bandeau accélère quand on descend, ralentit
 * quand on remonte, et repart doucement à l'arrêt.
 */
export class MarqueeGroup {
  constructor(root = document) {
    this.items = [];
    this.velocity = 0;
    this.lastScroll = window.scrollY || 0;
    this.lastFrame = performance.now();
    this.running = false;

    this.collect(root);

    if ('ResizeObserver' in window) {
      this.observer = new ResizeObserver(() => this.measure());
      this.items.forEach((item) => this.observer.observe(item.tracks[0]));
    }
    window.addEventListener('resize', () => this.measure(), { passive: true });
  }

  collect(root) {
    root.querySelectorAll('[data-marquee]').forEach((element) => {
      const tracks = Array.from(element.querySelectorAll('.marquee__track'));
      if (tracks.length < 2) return;
      this.items.push({
        element,
        tracks,
        speed: Number.parseFloat(element.dataset.speed) || 0.5,
        offset: 0,
        width: 0,
      });
    });
    this.measure();
  }

  /** Reconstruit la liste après un changement de page. */
  refresh(root = document) {
    this.observer?.disconnect();
    this.items = [];
    this.collect(root);
    this.items.forEach((item) => this.observer?.observe(item.tracks[0]));
  }

  measure() {
    this.items.forEach((item) => {
      item.width = item.tracks[0].getBoundingClientRect().width || 0;
    });
  }

  start() {
    if (this.running || this.items.length === 0) return;
    this.running = true;
    this.lastFrame = performance.now();
    requestAnimationFrame(this.tick);
  }

  stop() {
    this.running = false;
  }

  tick = (now) => {
    if (!this.running) return;
    requestAnimationFrame(this.tick);

    const delta = Math.min((now - this.lastFrame) / 1000, 0.05);
    this.lastFrame = now;

    const scroll = window.scrollY || 0;
    const moved = scroll - this.lastScroll;
    this.lastScroll = scroll;

    // La vitesse retombe progressivement : le bandeau garde son élan un instant.
    this.velocity += (moved / Math.max(delta, 0.001) - this.velocity) * 0.12;

    for (const item of this.items) {
      if (item.width <= 0) continue;
      const speed = item.speed * 90 + this.velocity * 0.55;
      item.offset += speed * delta;
      // Le décalage reste dans [0, largeur d'une piste) — la translation boucle.
      item.offset = ((item.offset % item.width) + item.width) % item.width;
      const transform = `translate3d(${-item.offset.toFixed(2)}px, 0, 0)`;
      item.tracks[0].style.transform = transform;
      item.tracks[1].style.transform = transform;
    }
  };
}

/**
 * Compteurs chiffrés, animés lorsqu'ils entrent dans l'écran.
 */
export function setupCounters(root = document) {
  const targets = root.querySelectorAll('[data-counter]');
  if (targets.length === 0) return;

  // En mouvement réduit, la valeur déjà écrite dans la page suffit.
  if (REDUCED) return;

  // Le chiffre final est dans le HTML pour rester lisible sans script :
  // c'est donc au script de le ramener à zéro avant de le faire monter.
  targets.forEach((el) => {
    el.textContent = `0${el.dataset.suffix || ''}`;
  });

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        observer.unobserve(entry.target);
        countUp(entry.target);
      });
    },
    { threshold: 0.4 }
  );

  targets.forEach((el) => observer.observe(el));
}

function countUp(element) {
  const target = Number.parseFloat(element.dataset.counter) || 0;
  const suffix = element.dataset.suffix || '';
  const duration = 1400;
  const started = performance.now();

  const step = (now) => {
    const progress = Math.min(1, (now - started) / duration);
    // Départ franc, arrivée en douceur : le chiffre se pose au lieu de s'arrêter net.
    const eased = 1 - Math.pow(1 - progress, 3);
    element.textContent = Math.round(target * eased).toLocaleString('fr-FR') + suffix;
    if (progress < 1) requestAnimationFrame(step);
  };

  requestAnimationFrame(step);
}

export const prefersReducedMotion = REDUCED;
export const isCoarsePointer = COARSE;
