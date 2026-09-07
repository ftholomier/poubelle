/* =============================================================
   SUISSE IMMO — interactions front (vanilla, sans dépendance)
   ============================================================= */
(function () {
  'use strict';

  const $  = (sel, ctx) => (ctx || document).querySelector(sel);
  const $$ = (sel, ctx) => Array.from((ctx || document).querySelectorAll(sel));
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------------------------------------------- Utilitaires */

  const euro = (n) => new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(n);
  const num  = (n) => new Intl.NumberFormat('fr-FR').format(Math.round(n));

  function toast(message, type) {
    let el = $('.toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'toast';
      el.setAttribute('role', 'status');
      document.body.appendChild(el);
    }
    el.className = 'toast toast--' + (type || 'success');
    el.innerHTML = '<span>' + message + '</span>';
    requestAnimationFrame(() => el.classList.add('is-visible'));
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('is-visible'), 5200);
  }
  window.siToast = toast;

  function track(event, meta) {
    try {
      // Le jeton voyage dans le corps : sendBeacon ne peut pas poser d'en-tête.
      const body = JSON.stringify(Object.assign({ _csrf: SI.csrf, event: event, page: location.pathname }, meta || {}));
      if (navigator.sendBeacon) {
        navigator.sendBeacon(SI.base + '/api/track', new Blob([body], { type: 'application/json' }));
      } else {
        fetch(SI.base + '/api/track', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body, keepalive: true });
      }
    } catch (e) { /* la mesure ne doit jamais casser la page */ }
  }
  window.siTrack = track;

  /* --------------------------------------------------- Apparitions */

  function initReveal() {
    const items = $$('[data-reveal]');
    if (!items.length) return;
    if (reduced || !('IntersectionObserver' in window)) {
      items.forEach((el) => el.classList.add('in'));
      return;
    }
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('in');
        io.unobserve(entry.target);
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

    items.forEach((el) => {
      // Décalage automatique entre frères pour un effet d'escalier.
      if (!el.style.getPropertyValue('--d') && el.parentElement) {
        const sibs = Array.from(el.parentElement.children).filter((c) => c.hasAttribute('data-reveal'));
        const i = sibs.indexOf(el);
        if (i > 0) el.style.setProperty('--d', Math.min(i * 70, 420) + 'ms');
      }
      io.observe(el);
    });
  }

  /* ------------------------------------------------------ Compteurs */

  function initCounters() {
    const els = $$('[data-count]');
    if (!els.length) return;
    if (reduced || !('IntersectionObserver' in window)) {
      els.forEach((el) => { el.textContent = num(parseFloat(el.dataset.count)); });
      return;
    }
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        const el = entry.target;
        io.unobserve(el);
        const target = parseFloat(el.dataset.count) || 0;
        const dur = 1500;
        const start = performance.now();
        (function step(now) {
          const p = Math.min((now - start) / dur, 1);
          const eased = 1 - Math.pow(1 - p, 3);
          el.textContent = num(target * eased);
          if (p < 1) requestAnimationFrame(step);
        })(start);
      });
    }, { threshold: 0.4 });
    els.forEach((el) => io.observe(el));
  }

  /* ------------------------------------------------------- En-tête */

  function initHeader() {
    const header = $('.header');
    const progress = $('.progress');
    const burger = $('.burger');
    const mobile = $('.mobile-nav');

    // L'en-tête ne se masque jamais : il se densifie seulement dès que
    // le contenu commence à défiler derrière lui.
    function onScroll() {
      const y = window.scrollY;
      if (header) header.classList.toggle('is-stuck', y > 24);
      if (progress) {
        const h = document.documentElement.scrollHeight - window.innerHeight;
        progress.style.setProperty('--p', h > 0 ? (y / h).toFixed(4) : 0);
      }
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    if (burger && mobile) {
      burger.addEventListener('click', () => {
        const open = burger.getAttribute('aria-expanded') === 'true';
        burger.setAttribute('aria-expanded', String(!open));
        mobile.classList.toggle('is-open', !open);
        document.body.style.overflow = !open ? 'hidden' : '';
      });
      $$('a', mobile).forEach((a) => a.addEventListener('click', () => {
        burger.setAttribute('aria-expanded', 'false');
        mobile.classList.remove('is-open');
        document.body.style.overflow = '';
      }));
      document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && mobile.classList.contains('is-open')) burger.click();
      });
    }
  }

  /* ------------------------------------------- Titre à mots tournants */

  function initRotator() {
    const rot = $('.hero__rotator');
    if (!rot) return;
    const words = $$('span', rot);
    // Les mots défilent visuellement ; le titre accessible n'en contient
    // qu'un seul, posé dans le HTML (span.sr-only).
    rot.setAttribute('aria-hidden', 'true');
    if (words.length < 2) { words[0]?.classList.add('is-active'); return; }
    let i = 0;
    words[0].classList.add('is-active');
    if (reduced) return;
    setInterval(() => {
      words[i].classList.remove('is-active');
      words[i].classList.add('is-out');
      const prev = words[i];
      setTimeout(() => prev.classList.remove('is-out'), 700);
      i = (i + 1) % words.length;
      words[i].classList.add('is-active');
    }, 2600);
  }

  /* --------------------------------------- Lueur suivant le curseur */

  function initPointerGlow() {
    if (reduced || window.matchMedia('(hover: none)').matches) return;
    const targets = $$('.card, .btn--magnet');
    targets.forEach((el) => {
      el.addEventListener('pointermove', (e) => {
        const r = el.getBoundingClientRect();
        el.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100) + '%');
        el.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100) + '%');
      });
    });
  }

  /* ---------------------------------------------- Frise progressive */

  function initTimeline() {
    const tl = $('.timeline');
    if (!tl) return;
    const items = $$('.tl-item', tl);
    function onScroll() {
      const r = tl.getBoundingClientRect();
      const p = Math.max(0, Math.min(1, (window.innerHeight * 0.72 - r.top) / r.height));
      tl.style.setProperty('--tp', p.toFixed(3));
      items.forEach((it) => {
        it.classList.toggle('in', it.getBoundingClientRect().top < window.innerHeight * 0.78);
      });
    }
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ------------------------------------------------ Onglets missions */

  function initTabs() {
    $$('[data-tabs]').forEach((wrap) => {
      const tabs = $$('[role="tab"]', wrap);
      const panels = $$('[role="tabpanel"]', wrap);
      function select(idx) {
        tabs.forEach((t, i) => {
          t.setAttribute('aria-selected', String(i === idx));
          // Un seul onglet dans l'ordre de tabulation : la flèche sert à
          // circuler entre eux, la tabulation à sortir du groupe.
          t.setAttribute('tabindex', i === idx ? '0' : '-1');
        });
        panels.forEach((p, i) => { p.hidden = i !== idx; });
      }
      tabs.forEach((tab, i) => {
        tab.addEventListener('click', () => select(i));
        tab.addEventListener('keydown', (e) => {
          const map = { ArrowDown: 1, ArrowRight: 1, ArrowUp: -1, ArrowLeft: -1 };
          if (!map[e.key]) return;
          e.preventDefault();
          const next = (i + map[e.key] + tabs.length) % tabs.length;
          tabs[next].focus();
          select(next);
        });
      });
      select(0);
    });
  }

  /* -------------------------------------------------- Simulateur */

  function initSimulator() {
    const sim = $('#simulator');
    if (!sim) return;
    const price = $('#sim-price', sim);
    const sales = $('#sim-sales', sim);
    const cfg = JSON.parse(sim.dataset.config || '{}');
    let used = false;

    function tier(n) {
      const tiers = cfg.tiers || [];
      let found = tiers[tiers.length - 1] || { rate: 0, name: '' };
      tiers.forEach((t) => {
        if (n >= Number(t.from) && n <= Number(t.to)) found = t;
      });
      return found;
    }

    function fill(input) {
      const min = Number(input.min), max = Number(input.max);
      const p = ((Number(input.value) - min) / (max - min)) * 100;
      input.style.setProperty('--fill', p + '%');
    }

    function render() {
      const p = Number(price.value);
      const s = Number(sales.value);
      const t = tier(s);
      const feePerSale = p * Number(cfg.agency_fee_rate || 4.5) / 100;
      const gross = feePerSale * s;
      const net = gross * Number(t.rate || 0) / 100;

      $('#sim-price-out', sim).textContent = euro(p);
      $('#sim-sales-out', sim).textContent = s + (s > 1 ? ' ventes' : ' vente');
      $('#sim-amount', sim).textContent = euro(net);
      $('#sim-month', sim).textContent = euro(net / 12);
      $('#sim-fee', sim).textContent = euro(feePerSale);
      $('#sim-gross', sim).textContent = euro(gross);
      $('#sim-rate', sim).textContent = num(t.rate) + ' %';
      $('#sim-tier', sim).textContent = 'Palier ' + (t.name || '');
      $$('#sim-ladder li', sim).forEach((li) => {
        li.classList.toggle('is-active', li.dataset.tier === t.name);
      });
      fill(price); fill(sales);

      try {
        sessionStorage.setItem('si_sim', JSON.stringify({ price: p, sales: s, net: Math.round(net), tier: t.name }));
      } catch (e) { /* mode privé */ }

      if (!used) { used = true; track('simulator_used'); }
    }

    [price, sales].forEach((input) => {
      input.addEventListener('input', render);
      fill(input);
    });
    render();
    used = false;
  }

  /* ------------------------------------------- Barre CTA collante */

  function initStickyCta() {
    const bar = $('.sticky-cta');
    if (!bar) return;
    if (sessionStorage.getItem('si_cta_closed') === '1') { bar.remove(); return; }

    let armed = false;
    let dismissed = false;

    // La bulle de l'assistant se cale au-dessus de la barre : elle doit
    // connaître sa hauteur réelle, qui change entre mobile (empilée) et
    // desktop, et retomber à zéro quand la barre disparaît.
    function publishHeight() {
      const visible = bar.classList.contains('is-visible');
      const h = visible ? Math.round(bar.getBoundingClientRect().height) + 12 : 0;
      document.documentElement.style.setProperty('--sticky-h', h + 'px');
    }

    // La barre s'efface en bas de page pour ne pas masquer le CTA final.
    function update() {
      if (!armed || dismissed) return;
      const doc = document.documentElement;
      const nearBottom = window.scrollY + window.innerHeight > doc.scrollHeight - 420;
      bar.classList.toggle('is-visible', !nearBottom);
      publishHeight();
    }
    window.addEventListener('resize', publishHeight, { passive: true });

    // Affichage automatique 2 s après le chargement — ou dès le premier
    // défilement si le visiteur va plus vite que ça.
    function arm() {
      if (armed) return;
      armed = true;
      update();
    }
    setTimeout(arm, 2000);

    const close = $('.sticky-cta__close', bar);
    close && close.addEventListener('click', () => {
      dismissed = true;
      bar.classList.remove('is-visible');
      publishHeight();
      try { sessionStorage.setItem('si_cta_closed', '1'); } catch (e) {}
    });

    window.addEventListener('scroll', () => {
      if (window.scrollY > 40) arm();
      update();
    }, { passive: true });
  }

  /* --------------------------------------------- Pop-in de sortie */

  function initExitIntent() {
    const modal = $('#exit-modal');
    if (!modal) return;

    // Deux verrous : la session (une fois par onglet) et 24 h (un visiteur
    // qui a déjà refusé ne doit pas revoir la fenêtre le lendemain matin).
    const DAY = 86400000;
    let shown = false;
    try {
      shown = sessionStorage.getItem('si_exit') === '1'
        || Number(localStorage.getItem('si_exit_at') || 0) > Date.now() - DAY;
    } catch (e) {}

    let lastFocus = null;
    const touch = window.matchMedia('(hover: none)').matches;
    const start = Date.now();
    let deepest = 0;
    let idleTimer = null;

    function open() {
      if (shown) return;
      shown = true;
      try {
        sessionStorage.setItem('si_exit', '1');
        localStorage.setItem('si_exit_at', String(Date.now()));
      } catch (e) {}
      lastFocus = document.activeElement;
      modal.classList.add('is-open');
      // La fenêtre passe de visibility:hidden à visible : le focus n'est
      // accepté qu'une fois le style appliqué, d'où la trame suivante.
      requestAnimationFrame(() => {
        const items = focusables();
        items.length && items[0].focus();
      });
    }

    // Éléments réellement atteignables au clavier : les champs cachés et
    // le pot de miel (tabindex -1) doivent rester hors du parcours.
    function focusables() {
      return $$('button, input, select, textarea, a[href]', modal).filter((el) =>
        el.type !== 'hidden' && el.tabIndex >= 0 && el.offsetParent !== null && !el.disabled);
    }

    function close() {
      modal.classList.remove('is-open');
      // Le focus revient à son point de départ ; s'il n'est plus
      // atteignable, on le retire de la fenêtre qui vient de disparaître.
      if (lastFocus && lastFocus.isConnected && typeof lastFocus.focus === 'function' && lastFocus.tabIndex >= 0) {
        lastFocus.focus();
      } else if (modal.contains(document.activeElement)) {
        document.activeElement.blur();
      }
    }

    if (!touch) {
      // Desktop : le curseur quitte la fenêtre par le haut, vers les onglets.
      document.addEventListener('mouseout', (e) => {
        if (!e.relatedTarget && e.clientY < 12) open();
      });
    } else {
      // Mobile : aucun signal d'intention de sortie n'existe. On attend
      // trois conditions réunies plutôt que d'interrompre une lecture —
      // un simple retour vers le haut de page n'en est pas une.
      function armIdle() {
        clearTimeout(idleTimer);
        const engaged = Date.now() - start > 45000 && deepest > 0.6;
        if (!engaged || shown) return;
        idleTimer = setTimeout(open, 8000);
      }
      window.addEventListener('scroll', () => {
        const doc = document.documentElement;
        const max = doc.scrollHeight - window.innerHeight;
        if (max > 0) deepest = Math.max(deepest, (window.scrollY + window.innerHeight) / doc.scrollHeight);
        armIdle();
      }, { passive: true });
      window.addEventListener('touchstart', armIdle, { passive: true });
      setTimeout(armIdle, 46000);
    }

    $$('[data-close]', modal).forEach((b) => b.addEventListener('click', close));
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
    document.addEventListener('keydown', (e) => {
      if (!modal.classList.contains('is-open')) return;
      if (e.key === 'Escape') { close(); return; }
      // Piège de focus : la tabulation ne sort pas de la fenêtre modale.
      if (e.key === 'Tab') {
        const items = focusables();
        if (!items.length) return;
        const first = items[0], last = items[items.length - 1];
        // Le focus part de l'extérieur (ouverture au clavier, clic sur le
        // fond) : on le ramène dans la fenêtre avant de boucler.
        if (!modal.contains(document.activeElement)) {
          e.preventDefault();
          (e.shiftKey ? last : first).focus();
        } else if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
  }

  /* ------------------------------------------- Formulaires en AJAX */

  function initAjaxForms() {
    $$('form[data-ajax]').forEach((form) => {
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = $('[type="submit"]', form);
        const label = btn ? btn.innerHTML : '';
        if (btn) { btn.disabled = true; btn.innerHTML = 'Envoi…'; }
        $$('.field.has-error', form).forEach((f) => f.classList.remove('has-error'));
        $$('.field__error', form).forEach((f) => f.remove());

        try {
          const res = await fetch(form.action, {
            method: 'POST',
            headers: { 'X-CSRF-Token': SI.csrf, 'Accept': 'application/json' },
            body: new FormData(form)
          });
          const data = await res.json();
          if (data.ok) {
            form.reset();
            if (data.redirect) { location.href = data.redirect; return; }
            toast(form.dataset.success || 'Message envoyé, merci !', 'success');
            const done = form.dataset.done;
            if (done) { const el = $(done); if (el) { form.hidden = true; el.hidden = false; } }
          } else {
            Object.keys(data.errors || {}).forEach((name) => {
              const input = form.elements[name];
              const field = input && input.closest ? input.closest('.field') : null;
              if (field) {
                field.classList.add('has-error');
                const p = document.createElement('p');
                p.className = 'field__error';
                p.textContent = data.errors[name];
                field.appendChild(p);
              }
            });
            toast(data.error || 'Une erreur est survenue.', 'error');
          }
        } catch (err) {
          toast('Connexion impossible. Réessayez dans un instant.', 'error');
        } finally {
          if (btn) { btn.disabled = false; btn.innerHTML = label; }
        }
      });
    });
  }

  /* ------------------------------------------------ Clics mesurés */

  function initCtaTracking() {
    $$('[data-cta]').forEach((el) => {
      el.addEventListener('click', () => track('cta_click', { source: el.dataset.cta }));
    });
  }

  /* -------------------------------------------------- Démarrage */

  function boot() {
    document.body.classList.add('is-loaded');
    initHeader();
    initReveal();
    initCounters();
    initRotator();
    initPointerGlow();
    initTimeline();
    initTabs();
    initSimulator();
    initStickyCta();
    initExitIntent();
    initAjaxForms();
    initCtaTracking();
    // La vue de page est comptée par le serveur (SiteController) : elle
    // est ainsi fiable même sans JavaScript, et n'est pas comptée deux fois.
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
