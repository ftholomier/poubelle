/* Sochaux Rétro — comportements communs (JS natif, sans dépendance). */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const motion = !matchMedia('(prefers-reduced-motion: reduce)').matches;
  const lang = document.documentElement.lang || 'fr';
  const T = (fr, en) => (lang === 'en' ? en : fr);
  window.SR = window.SR || {};

  /* ---------------------------------------------------------- méga-menus */
  const header = $('[data-header]');
  if (header) {
    let openKey = null, closeTimer = null;
    const megas = $$('[data-mega]', header);
    // Langue : jamais en même temps qu'un méga-menu (il recouvrirait la liste).
    const lg = $('[data-lang]', header);
    const setLang = (on) => {
      if (!lg) return;
      lg.classList.toggle('is-open', on);
      $('[data-lang-toggle]', lg)?.setAttribute('aria-expanded', on ? 'true' : 'false');
    };
    const setOpen = (key) => {
      openKey = key;
      if (key) setLang(false);
      megas.forEach(m => m.classList.toggle('is-open', m.dataset.mega === key));
      $$('[data-mega-trigger]', header).forEach(a => {
        const on = a.dataset.megaTrigger === key;
        a.classList.toggle('is-open', on);
        a.setAttribute('aria-expanded', on ? 'true' : 'false');
      });
    };
    $$('[data-mega-trigger]', header).forEach(a => {
      a.addEventListener('mouseenter', () => { clearTimeout(closeTimer); if (matchMedia('(min-width:1100px)').matches) setOpen(a.dataset.megaTrigger); });
      a.addEventListener('focus', () => setOpen(a.dataset.megaTrigger));
      a.addEventListener('click', (ev) => {
        // Écrans tactiles : premier appui = ouvrir le méga-menu, second = suivre le lien.
        if (matchMedia('(hover: none)').matches && openKey !== a.dataset.megaTrigger) { ev.preventDefault(); setOpen(a.dataset.megaTrigger); }
      });
    });
    $$('.mainnav > a:not([data-mega-trigger])', header).forEach(a => a.addEventListener('mouseenter', () => setOpen(null)));
    header.addEventListener('mouseleave', () => { closeTimer = setTimeout(() => setOpen(null), 180); });
    header.addEventListener('mouseenter', () => clearTimeout(closeTimer));
    $('.masthead__logo', header)?.addEventListener('mouseenter', () => setOpen(null));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { setOpen(null); setLang(false); closeSearch(); } });
    document.addEventListener('click', e => { if (!header.contains(e.target)) setOpen(null); });

    // Menu mobile
    const burger = $('[data-burger]', header), mm = $('[data-mobilemenu]', header);
    burger?.addEventListener('click', () => {
      const on = !mm.classList.contains('is-open');
      mm.classList.toggle('is-open', on);
      burger.textContent = on ? '✕' : '☰';
      burger.setAttribute('aria-expanded', on ? 'true' : 'false');
    });

    // Langue
    $('[data-lang-toggle]', lg || document)?.addEventListener('click', (e) => {
      e.stopPropagation();
      const on = !lg.classList.contains('is-open');
      if (on) setOpen(null);
      setLang(on);
    });
    document.addEventListener('click', () => setLang(false));

    // En-tête collant : hauteur de la barre (--head-h, pour décaler ancres et barres collantes)
    // et état « collé » (blason réduit) dès que la barre touche le haut de l'écran.
    const bar = $('.masthead', header);
    if (bar) {
      const setH = () => document.documentElement.style.setProperty('--head-h', bar.offsetHeight + 'px');
      setH();
      if ('ResizeObserver' in window) new ResizeObserver(setH).observe(bar); else addEventListener('resize', setH);
      let raf = 0;
      const stuck = () => { raf = 0; header.classList.toggle('is-stuck', scrollY > 0 && bar.getBoundingClientRect().top <= 0); };
      addEventListener('scroll', () => { if (!raf) raf = requestAnimationFrame(stuck); }, { passive: true });
      stuck();
    }
  }

  /* ---------------------------------------------------------- taille du texte */
  let zoom = 1;
  try { zoom = parseFloat(localStorage.getItem('fcsm-textsize')) || 1; } catch (e) {}
  // Le site est conçu en px (maquette) : on agrandit via zoom CSS, comme la maquette.
  const applyZoom = (z) => { document.documentElement.style.zoom = z > 1 ? z : ''; $$('[data-textsize-label]').forEach(l => (l.textContent = Math.round(z * 100) + '%')); };
  applyZoom(zoom);
  $$('[data-textsize]').forEach(b => b.addEventListener('click', () => {
    zoom = zoom >= 1.25 ? 1 : zoom >= 1.12 ? 1.25 : 1.12;
    try { localStorage.setItem('fcsm-textsize', String(zoom)); } catch (e) {}
    applyZoom(zoom);
  }));

  /* ---------------------------------------------------------- recherche plein écran */
  const layer = $('[data-searchlayer]');
  const input = $('[data-search-input]');
  const results = $('[data-search-results]');
  const count = $('[data-search-count]');
  let lastFocus = null, searchTimer = null, ctrl = null, active = -1;
  function openSearch(q) {
    if (!layer) return;
    lastFocus = document.activeElement;
    layer.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    if (q !== undefined) input.value = q;
    input.focus();
    runSearch(input.value);
  }
  function closeSearch() {
    if (!layer || !layer.classList.contains('is-open')) return;
    layer.classList.remove('is-open');
    document.body.style.overflow = '';
    lastFocus?.focus();
  }
  SR.openSearch = openSearch;
  $$('[data-search-open]').forEach(b => b.addEventListener('click', () => openSearch()));
  $('[data-search-close]')?.addEventListener('click', closeSearch);
  document.addEventListener('keydown', e => {
    if ((e.key === '/' || (e.key === 'k' && (e.metaKey || e.ctrlKey))) && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && !document.activeElement.isContentEditable) {
      e.preventDefault(); openSearch();
    }
  });
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  function render(list, q) {
    active = -1;
    results.innerHTML = list.map(r => `<a class="sresult" href="${esc(r.href)}"><span class="sresult__type">${esc(r.type)}</span><span class="sresult__label">${esc(r.label)}</span><span class="sresult__meta">${esc(r.meta || '')}</span></a>`).join('');
    count.textContent = q ? (list.length ? list.length + ' ' + T('résultat', 'result') + (list.length > 1 ? 's' : '') + (list.length >= 12 ? ' — ' + T('Entrée pour tout voir', 'Enter to see all') : '') : T('Aucun résultat. Essayez « Bonal », « 2007 » ou un nom de joueur.', 'No result. Try “Bonal”, “2007” or a player’s name.')) : T('Suggestions', 'Suggestions');
  }
  function runSearch(q) {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      ctrl?.abort(); ctrl = new AbortController();
      fetch('/api/recherche?suggest=1&lang=' + lang + '&q=' + encodeURIComponent(q.trim()), { signal: ctrl.signal })
        .then(r => r.json()).then(d => render(d.results || [], q.trim())).catch(() => {});
    }, q ? 120 : 0);
  }
  input?.addEventListener('input', () => runSearch(input.value));
  input?.addEventListener('keydown', e => {
    const links = $$('.sresult', results);
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      active = Math.max(-1, Math.min(links.length - 1, active + (e.key === 'ArrowDown' ? 1 : -1)));
      links.forEach((l, i) => l.classList.toggle('is-active', i === active));
      links[active]?.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter' && active >= 0 && links[active]) {
      e.preventDefault(); location.href = links[active].href;
    }
  });
  layer?.addEventListener('click', e => { if (e.target === layer) closeSearch(); });
  // Liens « ouvrir la recherche avec une requête »
  $$('[data-search-with]').forEach(f => f.addEventListener('submit', e => { e.preventDefault(); openSearch(new FormData(f).get('q') || ''); }));

  /* ---------------------------------------------------------- apparitions + compteurs */
  if ('IntersectionObserver' in window) {
    const pending = new Set($$('[data-reveal],[data-reveal-x]'));
    const reveal = (el, delay) => {
      if (!pending.delete(el)) return;
      io.unobserve(el);
      setTimeout(() => {
        el.classList.add('is-in');
        $$('[data-count]', el).concat(el.hasAttribute('data-count') ? [el] : []).forEach(c => countUp(c));
      }, delay);
    };
    // Seuil 0 : un bloc plus haut que l'écran apparaît aussi (avec 12 %, jamais).
    const io = new IntersectionObserver(entries => entries.forEach(en => {
      if (!en.isIntersecting) return;
      const el = en.target;
      const sibs = [...el.parentElement.children].filter(c => c.hasAttribute('data-reveal') || c.hasAttribute('data-reveal-x'));
      reveal(el, motion ? Math.min(Math.max(0, sibs.indexOf(el)) * 100, 600) : 0);
    }), { threshold: 0, rootMargin: '0px 0px -40px 0px' });
    pending.forEach(el => io.observe(el));
    // Filet de sécurité : un défilement très rapide peut faire passer un bloc d'un bord à
    // l'autre de l'écran entre deux images, sans que l'observateur le voie. À l'arrêt du
    // défilement, tout bloc déjà atteint (à l'écran ou au-dessus) est affiché.
    let sweepTimer = 0;
    const sweep = () => pending.forEach(el => { const r = el.getBoundingClientRect(); if ((r.width || r.height) && r.top < innerHeight) reveal(el, 0); });
    addEventListener('scroll', () => { clearTimeout(sweepTimer); if (pending.size) sweepTimer = setTimeout(sweep, 150); }, { passive: true });
    addEventListener('load', sweep);
  } else {
    $$('[data-reveal],[data-reveal-x]').forEach(el => el.classList.add('is-in'));
  }
  function countUp(el) {
    if (!motion || el.dataset.counted) return;
    el.dataset.counted = '1';
    const final = el.textContent.trim();
    const m = final.replace(/\s/g, '').match(/^(\d+)(.*)$/);
    if (!m) return;
    const to = +m[1], from = to > 100 ? to - Math.min(80, Math.round(to * .1)) : 0, t0 = performance.now();
    const fmt = n => (final.includes(' ') ? n.toLocaleString('fr-FR') : String(n)) + m[2];
    const step = now => {
      const p = Math.min(1, (now - t0) / 1300), e = 1 - Math.pow(1 - p, 3);
      el.textContent = fmt(Math.round(from + (to - from) * e));
      if (p < 1) requestAnimationFrame(step); else el.textContent = final;
    };
    requestAnimationFrame(step);
  }
  SR.countUp = countUp;

  /* ---------------------------------------------------------- comptes à rebours */
  $$('[data-countdown]').forEach(el => {
    const target = new Date(el.dataset.countdown).getTime();
    const cells = { d: $('[data-cd=d]', el), h: $('[data-cd=h]', el), m: $('[data-cd=m]', el), s: $('[data-cd=s]', el) };
    const pad = n => String(n).padStart(2, '0');
    const tick = () => {
      const left = Math.max(0, target - Date.now());
      if (cells.d) cells.d.textContent = String(Math.floor(left / 864e5));
      if (cells.h) cells.h.textContent = pad(Math.floor(left / 36e5) % 24);
      if (cells.m) cells.m.textContent = pad(Math.floor(left / 6e4) % 60);
      if (cells.s) cells.s.textContent = pad(Math.floor(left / 1e3) % 60);
    };
    tick(); setInterval(tick, 1000);
  });

  /* ---------------------------------------------------------- galeries + agrandissement */
  const lb = $('[data-lightbox]');
  let lbItems = [], lbIndex = 0;
  const lbShow = (i) => {
    lbIndex = (i + lbItems.length) % lbItems.length;
    const it = lbItems[lbIndex];
    $('[data-lb-img]', lb).src = it.src;
    $('[data-lb-img]', lb).alt = it.alt || '';
    $('[data-lb-caption]', lb).textContent = (it.caption ? it.caption + ' · ' : '') + (it.credit ? '© ' + it.credit + ' · ' : '') + (lbIndex + 1) + ' / ' + lbItems.length;
    // Photo d'un mur : lien vers la fiche qu'elle illustre.
    const link = $('[data-lb-link]', lb);
    if (link) {
      link.hidden = !it.href;
      link.href = it.href || '#';
    }
  };
  const lbOpen = (items, i) => { lbItems = items; lb.hidden = false; lb.classList.add('is-open'); document.body.style.overflow = 'hidden'; lbShow(i); $('[data-lb-close]', lb).focus(); };
  const lbClose = () => { lb.classList.remove('is-open'); lb.hidden = true; document.body.style.overflow = ''; };
  // Agrandissement ouvert par d'autres scripts (murs de photos) : [{src, alt, caption, credit, href}], rang.
  if (lb) window.SR.lightbox = (items, i) => { if (items.length) lbOpen(items, i || 0); };
  $$('[data-gallery]').forEach(g => {
    const links = $$('[data-lb]', g);
    const items = links.map(a => ({ src: a.getAttribute('href'), caption: a.dataset.caption || '', alt: $('img', a)?.alt || '' }));
    links.forEach((a, i) => a.addEventListener('click', e => { e.preventDefault(); lbOpen(items, i); }));
  });
  if (lb) {
    $('[data-lb-close]', lb).addEventListener('click', lbClose);
    $('[data-lb-prev]', lb).addEventListener('click', () => lbShow(lbIndex - 1));
    $('[data-lb-next]', lb).addEventListener('click', () => lbShow(lbIndex + 1));
    lb.addEventListener('click', e => { if (e.target === lb) lbClose(); });
    document.addEventListener('keydown', e => {
      if (!lb.classList.contains('is-open')) return;
      if (e.key === 'Escape') lbClose();
      if (e.key === 'ArrowRight') lbShow(lbIndex + 1);
      if (e.key === 'ArrowLeft') lbShow(lbIndex - 1);
    });
    let tx = null;
    lb.addEventListener('touchstart', e => { tx = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', e => { if (tx === null) return; const dx = e.changedTouches[0].clientX - tx; if (Math.abs(dx) > 50) lbShow(lbIndex + (dx < 0 ? 1 : -1)); tx = null; });
  }

  /* ---------------------------------------------------------- consentement cookies */
  const CK = 'fcsm-consent';
  const ck = $('[data-cookie]');
  // Choix valable 6 mois (recommandation de la CNIL), puis le bandeau réapparaît.
  const getConsent = () => {
    try {
      const c = JSON.parse(localStorage.getItem(CK) || 'null');
      return c && (!c.at || Date.now() - Date.parse(c.at) < 182 * 864e5) ? c : null;
    } catch (e) { return null; }
  };
  const setConsent = (c) => {
    c.at = new Date().toISOString(); c.v = 1;
    try { localStorage.setItem(CK, JSON.stringify(c)); } catch (e) {}
    navigator.sendBeacon?.('/api/consentement', new Blob([JSON.stringify(c)], { type: 'application/json' }));
    ck?.classList.remove('is-open');
    applyConsent();
  };
  SR.consent = () => getConsent() || {};
  function applyConsent() {
    const c = getConsent() || {};
    if (c.video) $$('[data-embed]').forEach(loadEmbed);
    document.dispatchEvent(new CustomEvent('sr:consent', { detail: c }));
  }
  function loadEmbed(box) {
    if (box.dataset.loaded) return;
    box.dataset.loaded = '1';
    const ifr = document.createElement('iframe');
    ifr.src = box.dataset.embed;
    ifr.title = box.dataset.title || 'Vidéo';
    ifr.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen';
    ifr.allowFullscreen = true;
    ifr.loading = 'lazy';
    box.innerHTML = '';
    box.appendChild(ifr);
  }
  $$('[data-embed]').forEach(box => {
    $('[data-embed-play]', box)?.addEventListener('click', () => {
      const c = getConsent() || {};
      if (!c.video) setConsent({ ...c, video: true, necessary: true });
      loadEmbed(box);
      const ifr = $('iframe', box); if (ifr && !ifr.src.includes('autoplay')) ifr.src += (ifr.src.includes('?') ? '&' : '?') + 'autoplay=1';
    });
  });
  if (ck) {
    if (!getConsent()) setTimeout(() => ck.classList.add('is-open'), 600);
    $('[data-cookie-accept]', ck).addEventListener('click', () => setConsent({ necessary: true, video: true, social: true, stats: true }));
    $('[data-cookie-refuse]', ck).addEventListener('click', () => setConsent({ necessary: true, video: false, social: false, stats: false }));
    $('[data-cookie-custom]', ck).addEventListener('click', () => {
      ck.classList.add('is-custom');
      $('[data-cookie-save]', ck).hidden = false;
      const c = getConsent() || {};
      $$('[data-consent]', ck).forEach(i => (i.checked = !!c[i.dataset.consent]));
    });
    $('[data-cookie-save]', ck).addEventListener('click', () => {
      const c = { necessary: true };
      $$('[data-consent]', ck).forEach(i => (c[i.dataset.consent] = i.checked));
      setConsent(c);
    });
    $$('[data-cookie-open]').forEach(a => a.addEventListener('click', e => {
      e.preventDefault();
      ck.classList.add('is-open', 'is-custom');
      $('[data-cookie-save]', ck).hidden = false;
      const c = getConsent() || {};
      $$('[data-consent]', ck).forEach(i => (i.checked = !!c[i.dataset.consent]));
    }));
  }
  applyConsent();

  /* ---------------------------------------------------------- petites aides */
  SR.toast = (msg) => {
    const t = document.createElement('div');
    t.className = 'toast'; t.textContent = msg; t.setAttribute('role', 'status');
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2600);
  };
  SR.share = async (data) => {
    try {
      if (navigator.share) { await navigator.share(data); return; }
      await navigator.clipboard.writeText(data.url || location.href);
      SR.toast(T('Lien copié !', 'Link copied!'));
    } catch (e) {}
  };
  $$('[data-share]').forEach(b => b.addEventListener('click', () => SR.share({ title: b.dataset.shareTitle || document.title, text: b.dataset.shareText || '', url: b.dataset.share || location.href })));

  // Onglets d'ancrage collants : soulignement de la section visible.
  const anchorNav = $('[data-anchornav]');
  if (anchorNav && 'IntersectionObserver' in window) {
    const links = $$('a[href^="#"]', anchorNav);
    const map = new Map(links.map(a => [a.getAttribute('href').slice(1), a]));
    const io2 = new IntersectionObserver(es => es.forEach(en => {
      if (en.isIntersecting) { links.forEach(l => l.classList.remove('is-on')); map.get(en.target.id)?.classList.add('is-on'); }
    }), { rootMargin: '-45% 0px -50% 0px' });
    map.forEach((_, id) => { const s = document.getElementById(id); if (s) io2.observe(s); });
  }

  // Formulaires : anti-double envoi (l'horodatage anti-robot est signé par le serveur)
  $$('form[data-protect]').forEach(f => {
    f.addEventListener('submit', () => { $$('button[type=submit]', f).forEach(b => { b.disabled = true; }); });
  });
  // Boutique : pastille du nombre d'articles du panier (cookie « sr_cart » posé par la boutique).
  const cartN = parseInt((document.cookie.match(/(?:^|;\s*)sr_cart=(\d+)/) || [])[1] || '0', 10);
  if (cartN > 0) {
    $$('[data-cart-badge]').forEach(b => { b.hidden = false; b.title = cartN + (cartN > 1 ? ' articles' : ' article') + ' dans le panier'; });
    $$('[data-cart-n]').forEach(n => { n.textContent = cartN > 99 ? '99+' : String(cartN); });
  }
})();
