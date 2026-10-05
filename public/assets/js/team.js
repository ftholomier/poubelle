/* Cartes de l'équipe : au survol de la souris (CSS) ; sur écran tactile, au toucher. */
(() => {
  'use strict';
  const hover = matchMedia('(hover: hover) and (pointer: fine)');
  document.querySelectorAll('[data-tflip]').forEach(card => {
    card.addEventListener('click', () => {
      if (hover.matches) return;
      const on = card.classList.toggle('is-flipped');
      card.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  });
})();
