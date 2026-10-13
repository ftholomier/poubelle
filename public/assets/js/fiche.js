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

/* Album Sochini : première visite de cette fiche par un collectionneur → chance de vignette bonus (tirage côté serveur). */
(function () {
  var el = document.querySelector('[data-vig-pid]');
  var on = false;
  try { on = localStorage.getItem('sr-vig') === '1'; } catch (e) {}
  if (!el || !on) return;
  fetch('/api/album-vignettes', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action: 'bonus', kind: 'fiche', pid: +el.getAttribute('data-vig-pid'), lang: document.documentElement.lang })
  }).then(function (r) { return r.json(); }).then(function (r) {
    if (!r.ok || !r.pack || !r.pack.length) return;
    var a = document.createElement('a');
    a.href = el.getAttribute('data-vig-href'); a.textContent = el.getAttribute('data-vig-msg'); a.setAttribute('role', 'status');
    a.style.cssText = 'position:fixed;left:16px;right:16px;bottom:16px;z-index:999;max-width:420px;margin:auto;background:var(--navy);color:var(--yellow);padding:14px 18px;font-weight:700;border:3px solid var(--yellow);text-decoration:none';
    document.body.appendChild(a);
    setTimeout(function () { a.remove(); }, 8000);
  }).catch(function () {});
})();
