/* Fiches : carte à retourner, tableau de statistiques, onglets et « afficher plus » des matchs. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  // Carte de collection : au survol de la souris (CSS) ; sur écran tactile, au toucher.
  const hover = matchMedia('(hover: hover) and (pointer: fine)');
  $$('[data-flip]').forEach(card => {
    card.addEventListener('click', () => {
      if (hover.matches) return;
      const on = card.classList.toggle('is-flipped');
      card.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  });

  // Statistiques : vue saison par saison ⇄ tableau d'origine.
  $$('[data-statsbox]').forEach(box => {
    const btn = $('[data-stats-toggle]', box);
    if (!btn) return;
    btn.addEventListener('click', () => {
      const long = $('[data-stats-long]', box), raw = $('[data-stats-raw]', box);
      const showRaw = raw.hidden;
      raw.hidden = !showRaw; long.hidden = showRaw;
      const label = btn.textContent; btn.textContent = btn.dataset.alt; btn.dataset.alt = label;
    });
  });

  // Tous ses matchs : onglets joueur / entraîneur.
  $$('[data-mtab]').forEach(tab => tab.addEventListener('click', () => {
    $$('[data-mtab]').forEach(t => { t.classList.toggle('is-on', t === tab); t.setAttribute('aria-selected', t === tab ? 'true' : 'false'); });
    $$('[data-mpanel]').forEach(p => { p.hidden = p.dataset.mpanel !== tab.dataset.mtab; });
  }));

  // Album du centenaire : visiter la fiche débloque la carte.
  const albumCard = $('[data-album-card]');
  if (albumCard) {
    try {
      const got = JSON.parse(localStorage.getItem('fcsm-album') || '[]');
      const id = albumCard.dataset.albumCard;
      if (!got.map(String).includes(id)) {
        got.push(id);
        localStorage.setItem('fcsm-album', JSON.stringify(got));
        const en = document.documentElement.lang === 'en';
        setTimeout(() => window.SR?.toast?.((en ? 'Album: card #' : 'Album : carte n° ') + albumCard.dataset.albumN + (en ? ' unlocked!' : ' débloquée !')), 900);
      }
    } catch (e) {}
  }

  // Afficher les matchs suivants.
  $$('[data-more-btn]').forEach(btn => btn.addEventListener('click', () => {
    $$('tr[data-more]', btn.parentElement).forEach(tr => { tr.hidden = false; });
    btn.remove();
  }));
})();
