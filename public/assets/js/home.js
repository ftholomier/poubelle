/* Accueil : slider plein écran (7 s, glisser au doigt, flèches, clavier) et grandes époques. */
(() => {
  'use strict';
  const root = document.querySelector('[data-slider]');
  if (root) {
    // Bas du slider calé sur le bas de l'écran : hauteur = écran moins ce qui est au-dessus (bandeau + menu).
    const ui = root.querySelector('.hero__ui');
    const fit = () => { if (ui) ui.style.minHeight = Math.max(480, window.innerHeight - (root.getBoundingClientRect().top + window.scrollY)) + 'px'; };
    fit();
    // Recalculée à chaque changement de taille de la fenêtre (largeur ou hauteur), sans à-coups.
    let raf = 0;
    addEventListener('resize', () => { cancelAnimationFrame(raf); raf = requestAnimationFrame(fit); });
    addEventListener('orientationchange', () => setTimeout(fit, 200));
    const slides = [...root.querySelectorAll('[data-slide]')];
    const copies = [...root.querySelectorAll('[data-copy]')];
    const thumbs = [...root.querySelectorAll('[data-goto]')];
    const num = root.querySelector('[data-hero-num]');
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    let cur = 0, timer = null;
    // Photos 2 à 5 : chargées à la demande (la suivante d'avance), pas toutes à l'arrivée.
    const load = (k) => {
      const img = slides[(k + slides.length) % slides.length]?.querySelector('img[data-src]');
      if (!img) return;
      if (img.dataset.srcset) img.srcset = img.dataset.srcset;
      img.src = img.dataset.src;
      img.removeAttribute('data-src');
      img.removeAttribute('data-srcset');
    };
    const preloadNext = () => load(cur + 1);
    addEventListener('load', () => ('requestIdleCallback' in window ? requestIdleCallback(preloadNext) : setTimeout(preloadNext, 1500)));
    const go = (i, user) => {
      const n = slides.length;
      cur = (i + n) % n;
      load(cur);
      setTimeout(preloadNext, 1000);
      slides.forEach((s, k) => { s.classList.toggle('is-on', k === cur); s.setAttribute('aria-hidden', k === cur ? 'false' : 'true'); });
      copies.forEach((c, k) => {
        c.classList.toggle('is-on', k === cur);
        c.querySelectorAll('a').forEach(a => (a.tabIndex = k === cur ? 0 : -1));
      });
      thumbs.forEach((t, k) => {
        t.classList.toggle('is-on', k === cur);
        t.setAttribute('aria-selected', k === cur ? 'true' : 'false');
        const bar = t.querySelector('.hero__bar i');
        if (bar) { bar.style.animation = 'none'; void bar.offsetWidth; bar.style.animation = ''; }
      });
      if (num) { num.textContent = String(cur + 1).padStart(2, '0'); num.classList.remove('is-anim'); void num.offsetWidth; num.classList.add('is-anim'); }
      if (user) start();
    };
    const start = () => {
      clearInterval(timer);
      if (reduce || slides.length < 2) { root.classList.add('is-paused'); return; }
      timer = setInterval(() => go(cur + 1), 7000);
    };
    root.querySelector('[data-prev]')?.addEventListener('click', () => go(cur - 1, true));
    root.querySelector('[data-next]')?.addEventListener('click', () => go(cur + 1, true));
    thumbs.forEach(t => t.addEventListener('click', () => go(+t.dataset.goto, true)));
    let tx = null;
    root.addEventListener('touchstart', e => { tx = e.touches[0].clientX; }, { passive: true });
    root.addEventListener('touchend', e => {
      if (tx === null) return;
      const dx = e.changedTouches[0].clientX - tx;
      if (Math.abs(dx) > 50) go(cur + (dx < 0 ? 1 : -1), true);
      tx = null;
    });
    root.addEventListener('keydown', e => { if (e.key === 'ArrowRight') go(cur + 1, true); if (e.key === 'ArrowLeft') go(cur - 1, true); });
    document.addEventListener('visibilitychange', () => (document.hidden ? clearInterval(timer) : start()));
    start();
  }

  const eras = document.querySelector('[data-eras]');
  if (eras) {
    const tabs = [...eras.querySelectorAll('[data-era]')];
    const panels = [...document.querySelectorAll('[data-era-panel]')];
    const select = (i) => {
      tabs.forEach((t, k) => { t.classList.toggle('is-on', k === i); t.setAttribute('aria-selected', k === i ? 'true' : 'false'); });
      panels.forEach((p, k) => { p.hidden = k !== i; p.classList.toggle('is-on', k === i); });
    };
    tabs.forEach((t, i) => t.addEventListener('click', () => select(i)));
    eras.addEventListener('keydown', e => {
      const i = tabs.indexOf(document.activeElement);
      if (i < 0) return;
      if (e.key === 'ArrowRight') { tabs[(i + 1) % tabs.length].focus(); select((i + 1) % tabs.length); }
      if (e.key === 'ArrowLeft') { tabs[(i - 1 + tabs.length) % tabs.length].focus(); select((i - 1 + tabs.length) % tabs.length); }
    });
  }
})();
