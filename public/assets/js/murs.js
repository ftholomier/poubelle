/* Murs de photos : nouveau tirage et filtres sans recharger la page, agrandissement avec crédit et
   lien vers la fiche, loupe de la planche-contact, tirages à déplacer du vestiaire, mosaïque. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const wall = $('[data-wall]');
  if (!wall) return;
  const form = $('[data-wall-form]');
  const T = (fr, en) => ((document.documentElement.lang || 'fr') === 'en' ? en : fr);
  const fine = matchMedia('(hover: hover) and (pointer: fine)').matches;

  /* ---------------------------------------------------------- agrandissement */
  const items = () => {
    const seen = new Set();
    const list = [];
    $$('[data-photo]', wall).forEach(el => {
      if (seen.has(el.dataset.full)) return;
      seen.add(el.dataset.full);
      list.push({ src: el.dataset.full, alt: $('img', el)?.alt || '', caption: el.dataset.caption || el.dataset.title || '', credit: el.dataset.credit || '', href: el.dataset.href || '' });
    });
    return list;
  };
  const open = el => {
    const list = items();
    const i = Math.max(0, list.findIndex(it => it.src === el.dataset.full));
    if (window.SR && typeof window.SR.lightbox === 'function') window.SR.lightbox(list, i);
    else window.open(el.dataset.full, '_blank', 'noopener');
  };
  wall.addEventListener('click', e => {
    const el = e.target.closest('[data-photo]');
    if (!el || !wall.contains(el) || el.closest('.is-dragged')) return;
    e.preventDefault();
    open(el);
  });

  /* ---------------------------------------------------------- nouveau tirage et filtres */
  let busy = false;
  const query = () => {
    const p = new URLSearchParams(new FormData(form));
    [...p.keys()].forEach(k => { if (!p.get(k)) p.delete(k); });
    return p.toString();
  };
  const redraw = async () => {
    if (busy) return;
    busy = true;
    wall.classList.add('is-loading');
    const q = query();
    const url = form.action + (q ? '?' + q : '');
    try {
      const r = await fetch(url + (q ? '&' : '?') + 'partiel=1', { headers: { Accept: 'text/html' } });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      wall.innerHTML = await r.text();
      history.replaceState(null, '', url);
      counts();
      init();
    } catch (err) {
      location.href = url; // repli : la page entière
      return;
    } finally {
      busy = false;
      wall.classList.remove('is-loading');
    }
  };
  // Nombres de photos de chaque choix avec l'autre filtre ; un choix sans photo est grisé.
  const counts = () => {
    const el = $('[data-wall-counts]', wall);
    if (!el) return;
    let c = null;
    try { c = JSON.parse(el.textContent); } catch (err) { /* nombres inchangés */ }
    el.remove();
    if (!c) return;
    const update = (select, map) => select && [...select.options].forEach(o => {
      if (!o.value || !o.dataset.label) return;
      const n = map[o.value] || 0;
      o.textContent = o.dataset.label + ' (' + n + ')';
      o.disabled = !n && !o.selected;
    });
    update($('select[name="decennie"]', form), c.decades || {});
    update($('select[name="photographe"]', form), c.who || {});
  };
  if (form) {
    form.addEventListener('submit', e => { e.preventDefault(); redraw(); });
    $$('[data-wall-filter]', form).forEach(s => s.addEventListener('change', redraw));
  }

  /* ---------------------------------------------------------- planche-contact : loupe */
  let loupe = null;
  const initLoupe = () => {
    if (!fine || !$('.pc', wall)) return;
    if (!loupe) {
      loupe = document.createElement('div');
      loupe.className = 'pc__loupe';
      loupe.setAttribute('aria-hidden', 'true');
      document.body.appendChild(loupe);
      addEventListener('scroll', () => loupe.classList.remove('is-on'), { passive: true });
    }
    const Z = 2.6;
    $$('.pc__shot', wall).forEach(shot => {
      const img = $('img', shot);
      shot.addEventListener('pointerenter', () => {
        loupe.style.backgroundImage = 'url("' + (img.dataset.loupe || img.currentSrc || img.src) + '")';
        loupe.classList.add('is-on');
      });
      shot.addEventListener('pointerleave', () => loupe.classList.remove('is-on'));
      shot.addEventListener('pointermove', e => {
        const r = shot.getBoundingClientRect();
        const nw = img.naturalWidth || 3, nh = img.naturalHeight || 2;
        const s = Math.max(r.width / nw, r.height / nh); // image en « object-fit: cover »
        const cw = nw * s, ch = nh * s;
        const x = e.clientX - r.left + (cw - r.width) / 2, y = e.clientY - r.top + (ch - r.height) / 2;
        const half = loupe.offsetWidth / 2 || 120;
        loupe.style.left = e.clientX + 'px';
        loupe.style.top = e.clientY + 'px';
        loupe.style.backgroundSize = (cw * Z) + 'px ' + (ch * Z) + 'px';
        loupe.style.backgroundPosition = (half - x * Z) + 'px ' + (half - y * Z) + 'px';
      });
    });
  };

  /* ---------------------------------------------------------- vestiaire : tirages à déplacer */
  let top = 100;
  const initDrag = () => {
    const vm = $('.vm', wall);
    if (!vm || !fine) return;
    vm.classList.add('can-drag');
    $$('[data-print]', vm).forEach(pr => {
      let sx = 0, sy = 0, dx = 0, dy = 0, down = false, moved = false;
      pr.addEventListener('pointerdown', e => {
        if (e.button !== 0) return;
        down = true;
        moved = false;
        sx = e.clientX - dx;
        sy = e.clientY - dy;
      });
      pr.addEventListener('pointermove', e => {
        if (!down) return;
        const nx = e.clientX - sx, ny = e.clientY - sy;
        if (!moved) {
          if (Math.hypot(nx - dx, ny - dy) < 6) return;
          moved = true;
          pr.setPointerCapture(e.pointerId);
          pr.classList.add('is-dragging');
          pr.style.zIndex = ++top;
        }
        dx = nx;
        dy = ny;
        pr.style.setProperty('--dx', dx + 'px');
        pr.style.setProperty('--dy', dy + 'px');
      });
      const end = () => {
        if (!down) return;
        down = false;
        pr.classList.remove('is-dragging');
        if (moved) {
          // Le clic qui suit un déplacement n'ouvre pas la photo.
          pr.classList.add('is-dragged');
          setTimeout(() => pr.classList.remove('is-dragged'), 0);
        }
      };
      pr.addEventListener('pointerup', end);
      pr.addEventListener('pointercancel', end);
    });
  };

  /* ---------------------------------------------------------- mosaïque */
  const initMosaic = () => {
    const mo = $('[data-mosaic]', wall);
    if (!mo) return;
    const b = $('[data-mo-reveal]', mo);
    b?.addEventListener('click', () => {
      const on = mo.classList.toggle('is-revealed');
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
      b.textContent = on ? T('Revoir le motif', 'Show the pattern') : T('Voir les photos en couleurs', 'See the photos in colour');
    });
    $('[data-mo-first]', mo)?.addEventListener('click', () => {
      const grid = $$('.mo__grid', mo).find(g => g.offsetParent !== null);
      const first = grid && $('[data-photo]', grid);
      if (first) open(first);
    });
  };

  const init = () => { initLoupe(); initDrag(); initMosaic(); };
  init();
})();
