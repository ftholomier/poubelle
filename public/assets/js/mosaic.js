/* Mosaïques : « Afficher plus » sans rechargement, recherche instantanée dans la rubrique. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const grid = $('[data-mitems]');
  const count = $('[data-mcount]');
  const motion = !matchMedia('(prefers-reduced-motion: reduce)').matches;

  const fragUrl = (href) => { const u = new URL(href, location.href); u.searchParams.set('fragment', '1'); return u; };
  const animateNew = (from) => {
    if (!grid || !motion) return;
    [...grid.children].slice(from).forEach((el, i) => { el.classList.add('is-new'); el.style.animationDelay = Math.min(i, 12) * 35 + 'ms'; });
  };

  // Afficher plus : ajoute la page suivante à la grille.
  function bindMore() {
    const more = $('[data-mmore]');
    if (!more || !grid) return;
    more.addEventListener('click', async (e) => {
      e.preventDefault();
      more.setAttribute('aria-busy', 'true');
      try {
        const r = await fetch(fragUrl(more.href), { headers: { Accept: 'application/json' } });
        const d = await r.json();
        const before = grid.children.length;
        grid.insertAdjacentHTML('beforeend', d.html);
        animateNew(before);
        if (d.next) { more.href = d.next; more.removeAttribute('aria-busy'); } else more.remove();
      } catch (err) { location.href = more.href; }
    });
  }
  bindMore();

  // Recherche instantanée (formulaire classique sans JavaScript).
  const form = $('[data-msearch]');
  if (form && grid) {
    const input = $('input[name=q]', form);
    let timer = null, ctrl = null;
    const run = async () => {
      const u = new URL(form.action, location.href);
      new FormData(form).forEach((v, k) => { if (v) u.searchParams.set(k, v); });
      const shown = new URL(u); shown.searchParams.delete('fragment');
      ctrl?.abort(); ctrl = new AbortController();
      grid.classList.add('is-loading');
      try {
        const r = await fetch(fragUrl(u), { signal: ctrl.signal, headers: { Accept: 'application/json' } });
        const d = await r.json();
        grid.innerHTML = d.html || '';
        animateNew(0);
        if (count) count.textContent = d.count;
        history.replaceState(null, '', shown.pathname + shown.search);
        const pager = $('.mpager');
        if (pager) {
          pager.innerHTML = d.next ? '<a class="btn btn--shadow mpager__more" href="' + d.next + '" rel="next" data-mmore>' + (document.documentElement.lang === 'en' ? 'Show more' : 'Afficher plus') + '</a>' : '';
          bindMore();
        }
      } catch (err) { /* requête annulée */ }
      grid.classList.remove('is-loading');
    };
    input?.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(run, 320); });
    form.addEventListener('submit', (e) => { e.preventDefault(); clearTimeout(timer); run(); });
  }
})();
