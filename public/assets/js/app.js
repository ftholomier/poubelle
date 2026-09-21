/**
 * intermittent.fr — comportements du front public.
 *
 * Amélioration progressive : tout ce qui compte (recherche, filtres, dépôt,
 * navigation) fonctionne sans ce fichier. Il n'ajoute que du confort.
 */
(function () {
  'use strict';

  document.documentElement.classList.remove('no-js');

  var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $  = function (sel, root) { return (root || document).querySelector(sel); };
  var $$ = function (sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); };

  /** sessionStorage peut lever (navigation privée) : on ne casse jamais la page. */
  function session(key, value) {
    try {
      if (value === undefined) { return window.sessionStorage.getItem(key); }
      window.sessionStorage.setItem(key, value);
    } catch (e) { /* stockage indisponible */ }
    return null;
  }

  /* ------------------------------------------------ apparition au scroll */

  function initReveal() {
    var nodes = $$('[data-reveal]');
    if (!nodes.length) { return; }

    if (reduceMotion || !('IntersectionObserver' in window)) {
      nodes.forEach(function (n) { n.classList.add('is-in'); });
      return;
    }

    var shown = 0;
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) { return; }
        // Cascade de 70 ms par groupe de cinq.
        entry.target.style.transitionDelay = (shown % 5) * 70 + 'ms';
        entry.target.classList.add('is-in');
        shown++;
        observer.unobserve(entry.target);
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -60px 0px' });

    nodes.forEach(function (n) { observer.observe(n); });
  }

  /* --------------------------------------------------- menus de l'en-tête */

  function initHeader() {
    var langBtn = $('[data-lang-toggle]');
    var langMenu = $('[data-lang-menu]');
    if (langBtn && langMenu) {
      langMenu.hidden = true;
      langBtn.setAttribute('aria-expanded', 'false');
      langBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        var open = langMenu.hidden;
        langMenu.hidden = !open;
        langBtn.setAttribute('aria-expanded', String(open));
      });
      document.addEventListener('click', function (e) {
        if (!langMenu.hidden && !langMenu.contains(e.target)) {
          langMenu.hidden = true;
          langBtn.setAttribute('aria-expanded', 'false');
        }
      });
    }

    var navBtn = $('[data-nav-toggle]');
    var nav = $('#site-nav');
    if (navBtn && nav) {
      navBtn.addEventListener('click', function () {
        var open = nav.classList.toggle('is-open');
        navBtn.setAttribute('aria-expanded', String(open));
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') { return; }
      if (langMenu && !langMenu.hidden) { langMenu.hidden = true; langBtn.setAttribute('aria-expanded', 'false'); }
      closeRegie();
      closeExit();
    });
  }

  /* ------------------------------------------- onglets « comment ça marche » */

  function initTabs() {
    $$('[data-tabs]').forEach(function (group) {
      var buttons = $$('[data-tab]', group);
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          if (btn.tagName === 'A') { e.preventDefault(); }
          var name = btn.getAttribute('data-tab');

          buttons.forEach(function (b) { b.setAttribute('aria-selected', String(b === btn)); });
          $$('[data-tab-panel]', group.parentNode || document).forEach(function (panel) {
            var active = panel.getAttribute('data-tab-panel') === name;
            panel.hidden = !active;
            if (active && !reduceMotion) {
              // Les cartes rejouent popIn à chaque changement d'onglet.
              $$('.step-card', panel).forEach(function (card) {
                card.style.animation = 'none';
                void card.offsetWidth;
                card.style.animation = '';
              });
            }
          });
        });
      });
    });
  }

  /* ------------------------------------------------------ pop-up de sortie */

  var exitEl = null;

  function closeExit() {
    if (exitEl) { exitEl.hidden = true; }
  }

  function initExitIntent() {
    exitEl = $('[data-exit-popup]');
    if (!exitEl) { return; }
    exitEl.hidden = true;

    if (session('imtt_exit_seen') === '1') { return; }

    document.addEventListener('mouseout', function (e) {
      if (e.clientY >= 8 || e.relatedTarget !== null) { return; }
      if (session('imtt_exit_seen') === '1') { return; }
      session('imtt_exit_seen', '1');
      exitEl.hidden = false;
      var focusable = $('a, button', exitEl);
      if (focusable) { focusable.focus(); }
    });

    $$('[data-exit-close]', exitEl).forEach(function (btn) {
      btn.addEventListener('click', closeExit);
    });
    exitEl.addEventListener('click', function (e) {
      if (e.target === exitEl) { closeExit(); }
    });
  }

  /* --------------------------------------------------- assistant « Régie » */

  var regiePanel = null;
  var regieLauncher = null;

  function closeRegie() {
    if (regiePanel && !regiePanel.hidden) {
      regiePanel.hidden = true;
      if (regieLauncher) { regieLauncher.hidden = false; regieLauncher.focus(); }
    }
  }

  function initRegie() {
    var root = $('[data-regie]');
    if (!root) { return; }

    regieLauncher = $('[data-regie-open]', root);
    regiePanel = $('[data-regie-panel]', root);
    if (!regieLauncher || !regiePanel) { return; }

    var thread = $('[data-regie-thread]', regiePanel);
    var form = $('[data-regie-form]', regiePanel);
    var input = form ? $('input', form) : null;
    var busy = false;

    regiePanel.hidden = true;

    regieLauncher.addEventListener('click', function () {
      regiePanel.hidden = false;
      regieLauncher.hidden = true;
      if (input) { input.focus(); }
    });
    $$('[data-regie-close]', regiePanel).forEach(function (b) { b.addEventListener('click', closeRegie); });

    function bubble(text, who, source) {
      var el = document.createElement('div');
      el.className = 'bubble bubble-' + who;
      el.textContent = text;
      if (source) {
        var s = document.createElement('span');
        s.className = 'source';
        s.textContent = source;
        el.appendChild(s);
      }
      thread.appendChild(el);
      thread.scrollTop = thread.scrollHeight;
      return el;
    }

    function ask(question) {
      if (busy || !question.trim()) { return; }
      busy = true;
      bubble(question, 'user');
      if (input) { input.value = ''; }
      var pending = bubble('…', 'bot');

      fetch(root.getAttribute('data-endpoint'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ question: question, _csrf: root.getAttribute('data-csrf') })
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          pending.textContent = data.answer || root.getAttribute('data-offline');
          if (data.source) {
            var s = document.createElement('span');
            s.className = 'source';
            s.textContent = data.source;
            pending.appendChild(s);
          }
        })
        .catch(function () { pending.textContent = root.getAttribute('data-offline'); })
        .then(function () { busy = false; thread.scrollTop = thread.scrollHeight; });
    }

    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        ask(input ? input.value : '');
      });
    }
    $$('[data-regie-suggest]', regiePanel).forEach(function (btn) {
      btn.addEventListener('click', function () { ask(btn.textContent.trim()); });
    });
  }

  /* --------------------------------------------------------- formulaires */

  function initForms() {
    // Zone de dépôt : glisser-déposer + nom du fichier choisi.
    $$('[data-dropzone]').forEach(function (zone) {
      var input = $('input[type="file"]', zone);
      var name = $('[data-dz-name]', zone);
      if (!input) { return; }

      ['dragenter', 'dragover'].forEach(function (evt) {
        zone.addEventListener(evt, function (e) { e.preventDefault(); zone.classList.add('is-over'); });
      });
      ['dragleave', 'drop'].forEach(function (evt) {
        zone.addEventListener(evt, function (e) { e.preventDefault(); zone.classList.remove('is-over'); });
      });
      zone.addEventListener('drop', function (e) {
        if (e.dataTransfer && e.dataTransfer.files.length) {
          input.files = e.dataTransfer.files;
          input.dispatchEvent(new Event('change'));
        }
      });
      input.addEventListener('change', function () {
        if (name) { name.textContent = input.files.length ? input.files[0].name : ''; }
      });
    });

    // Nuage de compétences : les puces alimentent un champ caché.
    $$('[data-skills]').forEach(function (cloud) {
      var hidden = $('input[type="hidden"]', cloud);
      if (!hidden) { return; }
      var selected = hidden.value ? hidden.value.split(',').filter(Boolean) : [];

      $$('[data-skill]', cloud).forEach(function (chip) {
        var value = chip.getAttribute('data-skill');
        chip.setAttribute('aria-pressed', String(selected.indexOf(value) !== -1));
        chip.addEventListener('click', function (e) {
          e.preventDefault();
          var at = selected.indexOf(value);
          if (at === -1) { selected.push(value); } else { selected.splice(at, 1); }
          chip.setAttribute('aria-pressed', String(at === -1));
          hidden.value = selected.join(',');
        });
      });
    });

    // Frise d'étapes : révèle les sections du formulaire sans quitter la page.
    $$('[data-steps]').forEach(function (bar) {
      var steps = $$('[data-step]', bar);
      steps.forEach(function (step, index) {
        step.addEventListener('click', function () {
          steps.forEach(function (s, i) {
            s.classList.toggle('is-current', i === index);
            s.classList.toggle('is-done', i < index);
          });
          var target = document.getElementById(step.getAttribute('data-step'));
          if (target) { target.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' }); }
        });
      });
    });

    // Filtres : envoi automatique à la coche (le bouton reste là sans JS).
    $$('[data-autosubmit]').forEach(function (form) {
      $$('input[type="checkbox"], select', form).forEach(function (field) {
        field.addEventListener('change', function () { form.submit(); });
      });
      var submit = $('[data-filter-submit]', form);
      if (submit) { submit.classList.add('visually-hidden'); }
    });
  }

  /* ------------------------------------------- consentement et publicité */

  function initConsent() {
    var banner = $('[data-cmp]');
    var consent = null;
    try { consent = window.localStorage.getItem('imtt_consent'); } catch (e) { /* bloqué */ }

    if (consent === 'all') { loadAds(); }
    if (!banner) { return; }
    banner.hidden = consent !== null;

    $$('[data-cmp-choice]', banner).forEach(function (btn) {
      btn.addEventListener('click', function () {
        var choice = btn.getAttribute('data-cmp-choice');
        try { window.localStorage.setItem('imtt_consent', choice); } catch (e) { /* bloqué */ }
        banner.hidden = true;
        if (choice === 'all') { loadAds(); }
      });
    });
  }

  /** Les scripts publicitaires ne sont chargés qu'après consentement explicite. */
  function loadAds() {
    var slots = $$('.ad-slot[data-client]');
    if (!slots.length || window.__imttAds) { return; }
    window.__imttAds = true;

    var client = slots[0].getAttribute('data-client');
    if (!client) { return; }

    var script = document.createElement('script');
    script.async = true;
    script.crossOrigin = 'anonymous';
    script.src = 'https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=' + encodeURIComponent(client);
    document.head.appendChild(script);

    slots.forEach(function (slot) {
      var frame = $('.ad-frame', slot);
      var unit = $('ins.adsbygoogle', slot);
      if (frame && unit) { frame.hidden = true; }
      if (unit) {
        (window.adsbygoogle = window.adsbygoogle || []).push({});
      }
    });
  }

  /* ----------------------------------------------------------- démarrage */

  function boot() {
    initReveal();
    initHeader();
    initTabs();
    initExitIntent();
    initRegie();
    initForms();
    initConsent();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
