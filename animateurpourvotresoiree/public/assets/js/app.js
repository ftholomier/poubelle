/* animateurpourvotresoirée — interactions du site (JavaScript natif, sans dépendance). */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const cfgEl = $('#apvs-config');
  const CFG = cfgEl ? JSON.parse(cfgEl.textContent || '{}') : {};
  const store = {
    get(k, d) { try { const v = localStorage.getItem('apvs:' + k); return v === null ? d : JSON.parse(v); } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem('apvs:' + k, JSON.stringify(v)); } catch (e) { /* stockage indisponible */ } },
  };
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

  const APVS = (window.APVS = window.APVS || {});
  APVS.cfg = CFG;
  APVS.store = store;
  APVS.esc = esc;

  /* Tâches planifiées sans cron serveur : quand l'hébergement ne peut pas les lancer après la page,
     un petit signal (au plus une fois par minute et par navigateur) suffit à les déclencher. */
  if (CFG.tick && navigator.sendBeacon && Date.now() - Number(store.get('tick', 0)) > 60000) {
    store.set('tick', Date.now());
    try { navigator.sendBeacon('/api/tick'); } catch (e) { /* ignoré */ }
  }

  /* ------------------------------------------------------------- toasts */
  APVS.toast = (msg, ms = 2600) => {
    const zone = $('.toast-zone');
    if (!zone) return;
    const t = document.createElement('div');
    t.className = 'toast';
    t.textContent = msg;
    zone.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); }, ms);
  };

  APVS.post = async (url, data) => {
    const headers = { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    if (CFG.csrf) headers['X-CSRF-Token'] = CFG.csrf;
    const res = await fetch(url, { method: 'POST', headers, body: JSON.stringify(data || {}), credentials: 'same-origin' });
    let body = null;
    try { body = await res.json(); } catch (e) { body = null; }
    if (!res.ok) throw Object.assign(new Error((body && (body.error || body.message)) || 'Erreur réseau'), { status: res.status, body });
    return body;
  };

  APVS.track = (type, pro, extra) => {
    try {
      const data = JSON.stringify({ type, pro: pro || 0, extra: extra || '' });
      if (navigator.sendBeacon) navigator.sendBeacon('/api/track', new Blob([data], { type: 'application/json' }));
      else fetch('/api/track', { method: 'POST', body: data, headers: { 'Content-Type': 'application/json' }, keepalive: true });
    } catch (e) { /* statistique facultative */ }
  };

  /* ------------------------------------------------------------- menu mobile */
  const burger = $('[data-burger]');
  if (burger) {
    burger.addEventListener('click', () => {
      const open = document.body.classList.toggle('nav-open');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    $$('#main-nav a').forEach((a) => a.addEventListener('click', () => { document.body.classList.remove('nav-open'); burger.setAttribute('aria-expanded', 'false'); }));
  }

  /* ------------------------------------------------------------- favoris */
  const favs = () => store.get('favs', []);
  const paintFavs = () => {
    const list = favs();
    $$('[data-fav]').forEach((b) => {
      const on = list.includes(Number(b.dataset.fav));
      b.classList.toggle('is-on', on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
    $$('[data-fav-count]').forEach((c) => { c.textContent = String(list.length); c.classList.toggle('hidden', list.length === 0); });
  };
  APVS.paintFavs = paintFavs;
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-fav]');
    if (!b) return;
    e.preventDefault();
    e.stopPropagation();
    const id = Number(b.dataset.fav);
    let list = favs();
    if (list.includes(id)) {
      list = list.filter((x) => x !== id);
      APVS.toast('Retiré de vos favoris');
    } else {
      list = [id, ...list].slice(0, 60);
      APVS.toast('♥ Ajouté à vos favoris');
      APVS.track('fav', id);
    }
    store.set('favs', list);
    paintFavs();
    document.dispatchEvent(new CustomEvent('apvs:favs'));
  });
  paintFavs();

  /* ------------------------------------------------------ autocomplétion des villes */
  const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };
  APVS.communeAutocomplete = (input) => {
    if (input.dataset.acReady) return;
    input.dataset.acReady = '1';
    const scope = input.closest('form') || input.parentElement;
    const hidden = $('[data-commune-insee]', scope);
    const wrap = input.closest('.autocomplete') || input.parentElement;
    wrap.style.position = wrap.style.position || 'relative';
    const list = document.createElement('ul');
    list.className = 'ac-list hidden';
    list.setAttribute('role', 'listbox');
    list.id = 'ac-' + Math.random().toString(36).slice(2, 8);
    input.setAttribute('role', 'combobox');
    input.setAttribute('aria-autocomplete', 'list');
    input.setAttribute('aria-controls', list.id);
    input.setAttribute('aria-expanded', 'false');
    wrap.appendChild(list);
    let items = [];
    let idx = -1;
    const close = () => { list.classList.add('hidden'); input.setAttribute('aria-expanded', 'false'); idx = -1; };
    const choose = (it) => {
      input.value = it.label;
      if (hidden) hidden.value = it.insee;
      input.dataset.lat = it.lat;
      input.dataset.lng = it.lng;
      close();
      input.dispatchEvent(new CustomEvent('commune:chosen', { detail: it, bubbles: true }));
    };
    const render = () => {
      list.innerHTML = items.map((it, i) => `<li role="option" id="${list.id}-${i}" aria-selected="${i === idx}" data-i="${i}">${esc(it.name)} <small>${esc(it.cp)} · ${esc(it.dep)}</small></li>`).join('');
      list.classList.toggle('hidden', items.length === 0);
      input.setAttribute('aria-expanded', items.length ? 'true' : 'false');
      if (idx >= 0) input.setAttribute('aria-activedescendant', `${list.id}-${idx}`);
    };
    const search = debounce(async (q) => {
      if (q.length < 2) { items = []; render(); return; }
      try {
        const r = await fetch('/api/communes?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
        items = r.ok ? (await r.json()).items || [] : [];
      } catch (e) { items = []; }
      idx = -1;
      render();
    }, 160);
    input.addEventListener('input', () => { if (hidden) hidden.value = ''; search(input.value.trim()); });
    input.addEventListener('keydown', (e) => {
      if (list.classList.contains('hidden')) return;
      if (e.key === 'ArrowDown') { idx = Math.min(items.length - 1, idx + 1); render(); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { idx = Math.max(0, idx - 1); render(); e.preventDefault(); }
      else if (e.key === 'Enter' && idx >= 0) { choose(items[idx]); e.preventDefault(); }
      else if (e.key === 'Escape') close();
    });
    list.addEventListener('mousedown', (e) => { const li = e.target.closest('li'); if (li) { e.preventDefault(); choose(items[Number(li.dataset.i)]); } });
    input.addEventListener('blur', () => setTimeout(close, 150));
  };
  $$('[data-commune-input]').forEach(APVS.communeAutocomplete);

  /* ------------------------------------------------- accueil : filtres par métier */
  const homeGrid = $('[data-home-grid]');
  if (homeGrid) {
    const label = $('[data-result-label]');
    const empty = $('[data-home-empty]');
    let req = 0;
    $$('[data-home-cat]').forEach((chip) => chip.addEventListener('click', async () => {
      $$('[data-home-cat]').forEach((c) => { const on = c === chip; c.classList.toggle('is-on', on); c.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      const id = ++req;
      homeGrid.style.opacity = '.5';
      try {
        const r = await fetch('/api/home-pros?cat=' + encodeURIComponent(chip.dataset.homeCat), { headers: { Accept: 'application/json' } });
        const d = await r.json();
        if (id !== req) return;
        homeGrid.innerHTML = d.html;
        if (label) label.textContent = d.label;
        if (empty) empty.classList.toggle('hidden', d.count > 0);
        paintFavs();
      } catch (e) { APVS.toast('Oups, réessayez dans un instant.'); }
      homeGrid.style.opacity = '';
    }));
  }

  /* --------------------------------------------- anti-spam : preuve de travail */
  const sha256Hex = async (str) => {
    const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(str));
    return Array.from(new Uint8Array(buf)).map((b) => b.toString(16).padStart(2, '0')).join('');
  };
  const leadingZeroBits = (hex) => {
    let bits = 0;
    for (const ch of hex) {
      const v = parseInt(ch, 16);
      if (v === 0) { bits += 4; continue; }
      bits += Math.clz32(v) - 28;
      break;
    }
    return bits;
  };
  const solve = async (token, difficulty) => {
    let n = 0;
    const start = Date.now();
    for (;;) {
      const h = await sha256Hex(token + n);
      if (leadingZeroBits(h) >= difficulty) return n;
      n++;
      if (n % 2000 === 0) await new Promise((r) => setTimeout(r, 0));
      if (Date.now() - start > 30000) return n;
    }
  };
  const protectForm = (form) => {
    if (form.dataset.protectReady) return;
    form.dataset.protectReady = '1';
    let pending = null;
    const field = form.querySelector('input[name="_pow"]');
    const status = form.querySelector('.pow-status');
    const run = () => {
      if (pending || !field || !window.crypto || !crypto.subtle) return pending;
      pending = (async () => {
        try {
          const ch = await APVS.post('/api/challenge', { form: form.dataset.protect });
          const n = await solve(ch.token, ch.difficulty);
          field.value = ch.token + '|' + n;
          if (status) status.textContent = '✓ Formulaire vérifié';
        } catch (e) { pending = null; }
      })();
      return pending;
    };
    // une preuve de travail ne sert qu'une fois : on en recalcule une après chaque envoi
    form.powReset = () => { if (!field) return; field.value = ''; pending = null; if (status) status.textContent = ''; run(); };
    form.addEventListener('focusin', run, { once: true });
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((en) => { if (en.some((x) => x.isIntersecting)) { run(); io.disconnect(); } });
      io.observe(form);
    }
    form.addEventListener('submit', async (e) => {
      if (field && !field.value && window.crypto && crypto.subtle) {
        e.preventDefault();
        const btn = form.querySelector('[type=submit]');
        if (btn) btn.classList.add('is-loading');
        if (status) status.textContent = 'Vérification anti-spam…';
        await run();
        if (btn) btn.classList.remove('is-loading');
        if (form.dataset.ajax !== undefined) form.dispatchEvent(new Event('submit', { cancelable: true }));
        else form.submit();
      }
    }, true);
  };
  APVS.protectForm = protectForm;
  $$('form[data-protect]').forEach(protectForm);

  /* Turnstile (facultatif) */
  if (CFG.turnstile && $('[data-turnstile]')) {
    const s = document.createElement('script');
    s.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js';
    s.async = true;
    document.head.appendChild(s);
  }

  /* Formulaires envoyés en arrière-plan (data-ajax) */
  $$('form[data-ajax]').forEach((form) => form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const pow = form.querySelector('input[name="_pow"]');
    if (pow && !pow.value && window.crypto && crypto.subtle) return; // la preuve de travail relancera l'envoi
    const btn = form.querySelector('[type=submit]');
    const out = form.querySelector('[data-form-result]');
    $$('.field-error', form).forEach((x) => x.remove());
    $$('.has-error', form).forEach((x) => x.classList.remove('has-error'));
    if (btn) btn.classList.add('is-loading');
    try {
      const fd = new FormData(form);
      const res = await fetch(form.action, { method: 'POST', body: fd, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
      const d = await res.json().catch(() => ({}));
      if (res.ok && d.ok) {
        if (d.redirect) { location.href = d.redirect; return; }
        if (out) { out.innerHTML = `<div class="alert alert-success">${esc(d.message || 'Merci !')}</div>`; }
        form.reset();
        if (form.powReset) form.powReset();
      } else {
        if (form.powReset) form.powReset();
        const errs = d.errors || {};
        Object.keys(errs).forEach((k) => {
          const input = form.querySelector(`[name="${CSS.escape(k)}"]`);
          const fieldEl = input ? input.closest('.field') : null;
          if (fieldEl) {
            fieldEl.classList.add('has-error');
            fieldEl.insertAdjacentHTML('beforeend', `<span class="field-error">${esc(errs[k])}</span>`);
          }
        });
        if (out) out.innerHTML = `<div class="alert alert-error">${esc(d.error || 'Merci de corriger les champs indiqués.')}</div>`;
        const first = form.querySelector('.has-error input, .has-error textarea, .has-error select');
        if (first) first.focus();
      }
    } catch (err) {
      if (out) out.innerHTML = '<div class="alert alert-error">Connexion impossible, réessayez dans un instant.</div>';
    }
    if (btn) btn.classList.remove('is-loading');
  }));

  /* ------------------------------------------------- fiche pro : téléphone, galerie, vidéos */
  document.addEventListener('click', async (e) => {
    const rev = e.target.closest('[data-reveal-phone]');
    if (rev) {
      e.preventDefault();
      rev.classList.add('is-loading');
      try {
        const d = await APVS.post('/api/pros/' + rev.dataset.revealPhone + '/phone', {});
        const a = document.createElement('a');
        a.className = rev.className.replace('is-loading', '');
        a.href = 'tel:' + d.tel;
        a.innerHTML = '📞 ' + esc(d.phone);
        rev.replaceWith(a);
      } catch (err) { APVS.toast(err.message || 'Numéro indisponible'); rev.classList.remove('is-loading'); }
      return;
    }
    const vid = e.target.closest('[data-video]');
    if (vid) {
      const iframe = document.createElement('iframe');
      iframe.src = vid.dataset.video;
      iframe.allow = 'accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture; fullscreen';
      iframe.allowFullscreen = true;
      iframe.title = vid.getAttribute('aria-label') || 'Vidéo';
      vid.replaceWith(iframe);
      return;
    }
    const share = e.target.closest('[data-share]');
    if (share) {
      e.preventDefault();
      const data = { title: document.title, url: share.dataset.share || location.href };
      if (navigator.share) { try { await navigator.share(data); } catch (er) { /* annulé */ } }
      else { try { await navigator.clipboard.writeText(data.url); APVS.toast('Lien copié !'); } catch (er) { prompt('Copiez le lien :', data.url); } }
      return;
    }
    const tr = e.target.closest('[data-track]');
    if (tr) APVS.track(tr.dataset.track, Number(tr.dataset.pro || 0));
  });

  const gallery = $('[data-gallery]');
  if (gallery) {
    const main = $('[data-gallery-main]', gallery);
    const thumbs = $$('[data-gallery-thumb]', gallery);
    const srcs = thumbs.length ? thumbs.map((t) => ({ src: t.dataset.full, alt: t.dataset.alt || '' })) : (main ? [{ src: main.dataset.full || main.src, alt: main.alt }] : []);
    let cur = 0;
    const show = (i) => {
      cur = (i + srcs.length) % srcs.length;
      if (main) { main.src = thumbs[cur] ? thumbs[cur].dataset.src : main.src; main.alt = srcs[cur].alt; }
      thumbs.forEach((t, k) => t.classList.toggle('is-on', k === cur));
    };
    thumbs.forEach((t, i) => t.addEventListener('click', () => show(i)));
    const openLb = () => {
      if (!srcs.length) return;
      const lb = document.createElement('div');
      lb.className = 'lightbox';
      lb.setAttribute('role', 'dialog');
      lb.setAttribute('aria-modal', 'true');
      lb.innerHTML = `<img src="${esc(srcs[cur].src)}" alt="${esc(srcs[cur].alt)}"><button class="icon-btn" aria-label="Fermer">✕</button>${srcs.length > 1 ? '<button class="icon-btn nav-l" aria-label="Précédente">←</button><button class="icon-btn nav-r" aria-label="Suivante">→</button>' : ''}`;
      const img = $('img', lb);
      const go = (d) => { cur = (cur + d + srcs.length) % srcs.length; img.src = srcs[cur].src; img.alt = srcs[cur].alt; };
      const close = () => { lb.remove(); document.removeEventListener('keydown', key); };
      const key = (ev) => { if (ev.key === 'Escape') close(); if (ev.key === 'ArrowRight') go(1); if (ev.key === 'ArrowLeft') go(-1); };
      lb.addEventListener('click', (ev) => { if (ev.target === lb || ev.target.closest('.icon-btn:not(.nav-l):not(.nav-r)')) close(); });
      $('.nav-l', lb)?.addEventListener('click', () => go(-1));
      $('.nav-r', lb)?.addEventListener('click', () => go(1));
      document.addEventListener('keydown', key);
      document.body.appendChild(lb);
      $('.icon-btn', lb).focus();
    };
    if (main) main.addEventListener('click', openLb);
  }

  /* ------------------------------------------------- mini-carte (fiche, pages locales) */
  APVS.loadLeaflet = () => {
    if (window.L && window.L.markerClusterGroup) return Promise.resolve(window.L);
    if (APVS._leaflet) return APVS._leaflet;
    const css = (href) => { if (!document.querySelector(`link[href="${href}"]`)) { const l = document.createElement('link'); l.rel = 'stylesheet'; l.href = href; document.head.appendChild(l); } };
    const js = (src) => new Promise((ok, ko) => { const s = document.createElement('script'); s.src = src; s.onload = ok; s.onerror = ko; document.head.appendChild(s); });
    css('/assets/vendor/leaflet/leaflet.css');
    css('/assets/vendor/markercluster/MarkerCluster.css');
    APVS._leaflet = js('/assets/vendor/leaflet/leaflet.js').then(() => js('/assets/vendor/markercluster/leaflet.markercluster.js')).then(() => window.L);
    return APVS._leaflet;
  };
  APVS.pinIcon = (L, color, letter, big) => L.divIcon({
    className: '',
    iconSize: big ? [46, 46] : [32, 32],
    iconAnchor: big ? [23, 46] : [16, 32],
    popupAnchor: [0, big ? -42 : -30],
    html: `<div class="apvs-pin${big ? ' big' : ''}" style="--c:${esc(color)}"><span>${esc(letter)}</span></div>`,
  });
  $$('[data-mini-map]').forEach((el) => {
    const run = async () => {
      const L = await APVS.loadLeaflet();
      const d = JSON.parse(el.dataset.miniMap);
      const map = L.map(el, { scrollWheelZoom: false, zoomControl: true, attributionControl: true });
      L.tileLayer(CFG.map.tiles, { attribution: CFG.map.attribution, maxZoom: 19, subdomains: 'abc' }).addTo(map);
      L.marker([d.lat, d.lng], { icon: APVS.pinIcon(L, d.color || '#ff4f3a', d.letter || '★', true) }).addTo(map);
      if (d.radius) L.circle([d.lat, d.lng], { radius: d.radius * 1000, color: '#1c1233', weight: 2, fillColor: '#ffd23f', fillOpacity: 0.15 }).addTo(map);
      map.setView([d.lat, d.lng], d.zoom || 10);
    };
    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver((en) => { if (en.some((x) => x.isIntersecting)) { io.disconnect(); run(); } }, { rootMargin: '200px' });
      io.observe(el);
    } else run();
  });

  /* ------------------------------------------------------------- consentement */
  const consentCfg = CFG.consent || {};
  const needsConsent = !!(consentCfg.ga4 || consentCfg.pixel || consentCfg.ads);
  const loadTrackers = (choice) => {
    if (!choice || !choice.stats) return;
    if (consentCfg.ga4 && !window.gtag) {
      const s = document.createElement('script');
      s.async = true;
      s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(consentCfg.ga4);
      document.head.appendChild(s);
      window.dataLayer = window.dataLayer || [];
      window.gtag = function () { window.dataLayer.push(arguments); };
      window.gtag('js', new Date());
      window.gtag('config', consentCfg.ga4, { anonymize_ip: true });
    }
    if (consentCfg.pixel && !window.fbq && choice.ads) {
      const f = (window.fbq = function () { f.callMethod ? f.callMethod.apply(f, arguments) : f.queue.push(arguments); });
      f.queue = []; f.loaded = true; f.version = '2.0';
      const s = document.createElement('script');
      s.async = true;
      s.src = 'https://connect.facebook.net/fr_FR/fbevents.js';
      document.head.appendChild(s);
      window.fbq('init', consentCfg.pixel);
      window.fbq('track', 'PageView');
    }
  };
  /* Publicité avec notre propre bandeau : chargée après le choix (non personnalisée sans accord) */
  const loadAds = (choice) => {
    if (!consentCfg.ads || !consentCfg.adsClient || !choice || window.__apvsAds) return;
    window.__apvsAds = true;
    window.adsbygoogle = window.adsbygoogle || [];
    if (!choice.ads) window.adsbygoogle.requestNonPersonalizedAds = 1;
    const s = document.createElement('script');
    s.async = true;
    s.crossOrigin = 'anonymous';
    s.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' + encodeURIComponent(consentCfg.adsClient);
    s.onload = () => { if (APVS.pushAds) APVS.pushAds(); };
    document.head.appendChild(s);
  };
  const showConsent = () => {
    if ($('.consent')) return;
    const box = document.createElement('div');
    box.className = 'consent';
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-label', 'Cookies');
    box.innerHTML = '<p><strong>🍪 Petits cookies ?</strong> Nous utilisons des cookies de mesure d\'audience et de publicité pour financer ce service gratuit. Vous pouvez accepter, refuser ou choisir.</p><div class="row"><button class="btn btn-sm btn-ink" data-c="all">Tout accepter</button><button class="btn btn-sm" data-c="none">Tout refuser</button><button class="btn btn-sm btn-ghost" data-c="stats">Mesure d\'audience seulement</button></div>';
    box.addEventListener('click', (e) => {
      const b = e.target.closest('[data-c]');
      if (!b) return;
      const c = b.dataset.c === 'all' ? { stats: true, ads: true } : b.dataset.c === 'stats' ? { stats: true, ads: false } : { stats: false, ads: false };
      store.set('consent', { ...c, at: Date.now() });
      box.remove();
      loadTrackers(c); loadAds(c);
    });
    document.body.appendChild(box);
  };
  $$('[data-consent-open]').forEach((b) => {
    if (!needsConsent) { b.classList.add('hidden'); return; }
    b.addEventListener('click', showConsent);
  });
  if (needsConsent) {
    const c = store.get('consent', null);
    if (!c) setTimeout(showConsent, 900); else { loadTrackers(c); loadAds(c); }
  }

  /* ------------------------------------------------------------- publicité */
  const pushAds = () => { $$('ins.adsbygoogle:not([data-pushed])').forEach((ins) => { ins.dataset.pushed = '1'; try { (window.adsbygoogle = window.adsbygoogle || []).push({}); } catch (e) { /* bloqueur */ } }); };
  APVS.pushAds = pushAds;
  if ($('ins.adsbygoogle')) window.addEventListener('load', pushAds);

  /* ------------------------------------------------------------- PWA */
  if (CFG.pwa && 'serviceWorker' in navigator && location.protocol === 'https:' || (CFG.pwa && 'serviceWorker' in navigator && location.hostname === 'localhost')) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
  }
  let deferredPrompt = null;
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    document.documentElement.classList.add('install-ready');
  });
  $$('[data-install]').forEach((b) => b.addEventListener('click', async () => {
    if (!deferredPrompt) return;
    deferredPrompt.prompt();
    await deferredPrompt.userChoice;
    deferredPrompt = null;
    document.documentElement.classList.remove('install-ready');
  }));
  const offline = () => {
    let bar = $('.offline-banner');
    if (!navigator.onLine && !bar) { bar = document.createElement('div'); bar.className = 'offline-banner'; bar.textContent = 'Vous êtes hors ligne — certaines fonctions sont indisponibles.'; document.body.appendChild(bar); }
    if (navigator.onLine && bar) bar.remove();
  };
  window.addEventListener('online', offline);
  window.addEventListener('offline', offline);

  /* Notifications push (espace pro / back-office) */
  APVS.subscribePush = async (endpoint) => {
    if (!('serviceWorker' in navigator) || !('PushManager' in window) || !CFG.vapid) throw new Error('Notifications non prises en charge sur cet appareil.');
    const perm = await Notification.requestPermission();
    if (perm !== 'granted') throw new Error('Autorisation refusée.');
    const reg = await navigator.serviceWorker.register('/sw.js');
    await navigator.serviceWorker.ready;
    const key = Uint8Array.from(atob(CFG.vapid.replace(/-/g, '+').replace(/_/g, '/') + '==='.slice((CFG.vapid.length + 3) % 4)), (c) => c.charCodeAt(0));
    const sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
    return APVS.post(endpoint, { subscription: sub.toJSON() });
  };

  /* Compteurs de caractères */
  $$('[data-charcount]').forEach((el) => {
    const out = document.createElement('span');
    out.className = 'hint';
    el.insertAdjacentElement('afterend', out);
    const max = Number(el.getAttribute('maxlength') || el.dataset.charcount || 0);
    const upd = () => { out.textContent = `${el.value.length}${max ? ' / ' + max : ''} caractères`; };
    el.addEventListener('input', upd);
    upd();
  });

  /* Nombre maximal de cases cochées (ex. 3 métiers) */
  $$('[data-max-check]').forEach((box) => {
    const max = Number(box.dataset.maxCheck);
    const sync = () => {
      const boxes = $$('input[type=checkbox]', box);
      const n = boxes.filter((b) => b.checked).length;
      boxes.forEach((b) => { b.disabled = !b.checked && n >= max; b.closest('.choice')?.classList.toggle('is-disabled', b.disabled); });
    };
    box.addEventListener('change', sync);
    sync();
  });

  /* Indicateur de robustesse des mots de passe */
  $$('input[data-strength]').forEach((input) => {
    const bar = document.createElement('span');
    bar.className = 'strength';
    bar.innerHTML = '<i></i><b></b>';
    (input.closest('.field') || input.parentNode).appendChild(bar);
    const upd = () => {
      const v = input.value;
      let s = 0;
      if (v.length >= 10) s++;
      if (v.length >= 14) s++;
      if (/[a-z]/.test(v) && /[A-Z]/.test(v)) s++;
      if (/\d/.test(v)) s++;
      if (/[^a-zA-Z0-9]/.test(v)) s++;
      const lvl = v ? Math.min(4, Math.max(1, s)) : 0;
      bar.dataset.level = String(lvl);
      $('b', bar).textContent = v ? ['', 'faible', 'moyen', 'bon', 'excellent'][lvl] : '';
    };
    input.addEventListener('input', upd);
    upd();
  });

  /* Copier dans le presse-papiers */
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-copy]');
    if (!b) return;
    e.preventDefault();
    try { await navigator.clipboard.writeText(b.dataset.copy); APVS.toast('Copié !'); } catch (er) { prompt('Copiez :', b.dataset.copy); }
  });

  /* Confirmation sur un bouton précis (ex. supprimer une photo) */
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-confirm-click]');
    if (b && !confirm(b.dataset.confirmClick)) { e.preventDefault(); e.stopPropagation(); }
  }, true);

  /* Abonnement aux notifications push */
  $$('[data-push-subscribe]').forEach((b) => b.addEventListener('click', async () => {
    b.classList.add('is-loading');
    try { await APVS.subscribePush(b.dataset.pushSubscribe); APVS.toast('Notifications activées sur cet appareil 🔔'); b.textContent = '✓ Notifications activées'; }
    catch (err) { APVS.toast(err.message || 'Activation impossible'); }
    b.classList.remove('is-loading');
  }));

  /* Sélection multiple confortable : <select multiple data-multi> devient une liste de puces avec recherche */
  $$('select[multiple][data-multi]').forEach((sel) => {
    const box = document.createElement('div');
    box.className = 'multi-box';
    const chips = document.createElement('div');
    chips.className = 'multi-chips';
    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'input';
    search.placeholder = 'Ajouter… (tapez un nom ou un numéro)';
    search.setAttribute('aria-label', sel.getAttribute('aria-label') || 'Ajouter');
    const list = document.createElement('ul');
    list.className = 'ac-list hidden';
    const wrapS = document.createElement('div');
    wrapS.className = 'autocomplete';
    wrapS.append(search, list);
    box.append(chips, wrapS);
    sel.hidden = true;
    sel.insertAdjacentElement('afterend', box);
    const paint = () => {
      chips.innerHTML = '';
      Array.from(sel.options).filter((o) => o.selected).forEach((o) => {
        const c = document.createElement('button');
        c.type = 'button';
        c.className = 'chip chip-sm is-on';
        c.innerHTML = esc(o.textContent) + ' <span aria-hidden="true">✕</span>';
        c.setAttribute('aria-label', 'Retirer ' + o.textContent);
        c.addEventListener('click', () => { o.selected = false; paint(); sel.dispatchEvent(new Event('change', { bubbles: true })); });
        chips.appendChild(c);
      });
      if (!chips.children.length) chips.innerHTML = '<span class="muted small">Aucune sélection</span>';
    };
    const show = () => {
      const q = search.value.trim().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
      const opts = Array.from(sel.options).filter((o) => !o.selected && (!q || o.textContent.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').includes(q))).slice(0, 12);
      list.innerHTML = opts.map((o) => '<li data-v="' + esc(o.value) + '">' + esc(o.textContent) + '</li>').join('');
      list.classList.toggle('hidden', !opts.length);
    };
    search.addEventListener('input', show);
    search.addEventListener('focus', show);
    search.addEventListener('blur', () => setTimeout(() => list.classList.add('hidden'), 150));
    search.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); const f = list.querySelector('li'); if (f) f.dispatchEvent(new MouseEvent('mousedown', { bubbles: true })); } });
    list.addEventListener('mousedown', (e) => {
      const li = e.target.closest('li');
      if (!li) return;
      e.preventDefault();
      const o = Array.from(sel.options).find((x) => x.value === li.dataset.v);
      if (o) { o.selected = true; paint(); sel.dispatchEvent(new Event('change', { bubbles: true })); }
      search.value = '';
      list.classList.add('hidden');
    });
    paint();
  });

  /* Confirmation des actions sensibles */
  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (f.dataset.confirm && !confirm(f.dataset.confirm)) e.preventDefault();
  });
})();
