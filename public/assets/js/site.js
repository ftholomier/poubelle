/* =========================================================================
   Le iOiO — comportements du site public.
   JavaScript natif, sans dépendance. Tout dégrade proprement sans JS :
   les formulaires postent normalement, les contenus sont déjà dans le HTML.
   ========================================================================= */
(function () {
  'use strict';

  var root = document.documentElement;
  root.classList.add('js');

  var cfg = window.IOIO || {};
  var base = cfg.basePath || '';
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  function $(selector, scope) { return (scope || document).querySelector(selector); }
  function $$(selector, scope) { return Array.prototype.slice.call((scope || document).querySelectorAll(selector)); }

  /* ------------------------------------------------------------ mesure */

  function track(name, props) {
    try {
      if (typeof window.plausible === 'function') { window.plausible(name, { props: props || {} }); }
      if (window._paq && typeof window._paq.push === 'function') {
        window._paq.push(['trackEvent', 'iOiO', name, JSON.stringify(props || {})]);
      }
    } catch (e) { /* la mesure ne doit jamais casser la page */ }
  }
  window.ioioTrack = track;

  $$('[data-track]').forEach(function (el) {
    el.addEventListener('click', function () { track(el.getAttribute('data-track')); });
  });

  /* ------------------------------------------ apparition au scroll (reveal) */

  var seen = new WeakSet();

  function revealPass() {
    var h = window.innerHeight || 800;
    $$('[data-reveal]').forEach(function (el) {
      if (!seen.has(el)) {
        seen.add(el);
        var delay = parseInt(el.getAttribute('data-delay') || '0', 10);
        el.style.transitionDelay = Math.round(delay * 1.5) + 'ms';
      }
      if (el.dataset.shown) { return; }
      var r = el.getBoundingClientRect();
      if (r.top < h * 0.94 && r.bottom > -40) { el.dataset.shown = '1'; }
    });
  }

  /* ------------------------------------------- en-tête, progression, parallaxe */

  var header = $('[data-header]');
  var progress = $('[data-progress]');
  var parallaxEls = $$('[data-parallax]');
  var floatEls = $$('[data-float]');

  function onScroll() {
    var y = window.scrollY || window.pageYOffset || 0;
    revealPass();

    if (header) { header.classList.toggle('is-scrolled', y > 60); }

    if (progress) {
      var max = (document.documentElement.scrollHeight - window.innerHeight) || 1;
      progress.style.width = Math.min(100, (y / max) * 100).toFixed(2) + '%';
    }

    if (!reduced) {
      parallaxEls.forEach(function (el) {
        el.style.transform = 'translate3d(0,' + (y * parseFloat(el.getAttribute('data-parallax'))).toFixed(1) + 'px,0)';
      });
    }
  }

  function onMove(e) {
    if (reduced || !floatEls.length) { return; }
    var dx = (e.clientX / window.innerWidth - 0.5) * 42;
    var dy = (e.clientY / window.innerHeight - 0.5) * 30;
    floatEls.forEach(function (el, i) {
      var s = i % 2 === 0 ? 1 : -1;
      el.style.marginLeft = (dx * s).toFixed(1) + 'px';
      el.style.marginTop = (dy * s).toFixed(1) + 'px';
    });
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll, { passive: true });
  window.addEventListener('mousemove', onMove, { passive: true });
  [0, 60, 220, 600, 1200].forEach(function (t) { setTimeout(revealPass, t); });
  onScroll();

  /* -------------------------------------------------------- menu mobile */

  var navToggle = $('[data-nav-toggle]');
  var nav = $('[data-nav]');
  if (navToggle && nav) {
    navToggle.addEventListener('click', function () {
      var open = nav.classList.toggle('is-open');
      navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  /* ---------------------------------------------------------- diaporama */

  var slideshow = $('[data-slideshow]');
  if (slideshow) {
    var slides = $$('[data-slide]', slideshow);
    var dots = $$('[data-slide-dot]', slideshow);
    var index = slides.findIndex(function (s) { return s.classList.contains('is-active'); });
    if (index < 0) { index = 0; }
    var timer = null;

    function show(next) {
      index = (next + slides.length) % slides.length;
      slides.forEach(function (s, i) { s.classList.toggle('is-active', i === index); });
      dots.forEach(function (d, i) {
        d.classList.toggle('is-active', i === index);
        d.setAttribute('aria-selected', i === index ? 'true' : 'false');
      });
    }
    function play() {
      if (reduced || slides.length < 2) { return; }
      stop();
      timer = setInterval(function () { show(index + 1); }, 4200);
    }
    function stop() { if (timer) { clearInterval(timer); timer = null; } }

    dots.forEach(function (dot, i) {
      dot.addEventListener('click', function () { stop(); show(i); });
    });
    slideshow.addEventListener('mouseenter', stop);
    slideshow.addEventListener('mouseleave', play);
    document.addEventListener('visibilitychange', function () { document.hidden ? stop() : play(); });
    show(index);
    play();
  }

  /* ---------------------------------------------------------------- FAQ */

  $$('[data-faq]').forEach(function (item) {
    var button = $('[data-faq-toggle]', item);
    if (!button) { return; }
    button.addEventListener('click', function () {
      var willOpen = !item.classList.contains('is-open');
      $$('[data-faq]').forEach(function (other) {
        other.classList.remove('is-open');
        var b = $('[data-faq-toggle]', other);
        if (b) { b.setAttribute('aria-expanded', 'false'); }
      });
      item.classList.toggle('is-open', willOpen);
      button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
    });
  });

  /* ------------------------------------------------------ galerie fiche */

  var gallery = $('[data-gallery]');
  if (gallery) {
    var main = $('[data-gallery-main]', gallery);
    $$('[data-gallery-thumb]', gallery).forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        if (!main) { return; }
        var src = thumb.getAttribute('data-src');
        if (src) {
          main.src = src;
          main.alt = thumb.getAttribute('data-alt') || main.alt;
        }
        $$('[data-gallery-thumb]', gallery).forEach(function (t) { t.classList.remove('is-active'); });
        thumb.classList.add('is-active');
      });
    });
  }

  /* --------------------------------------------------- choix « besoin » */

  $$('[data-needs]').forEach(function (group) {
    var input = $('[data-needs-input]', group.closest('form') || document);
    $$('[data-need]', group).forEach(function (button) {
      button.addEventListener('click', function () {
        $$('[data-need]', group).forEach(function (b) {
          b.classList.remove('is-active');
          b.setAttribute('aria-pressed', 'false');
        });
        button.classList.add('is-active');
        button.setAttribute('aria-pressed', 'true');
        if (input) { input.value = button.getAttribute('data-need') || ''; }
      });
    });
  });

  /* ------------------------------------------------- formulaires en AJAX */

  function postForm(form) {
    var data = new FormData(form);
    return fetch(form.getAttribute('action'), {
      method: 'POST',
      body: data,
      headers: { 'X-Requested-With': 'fetch' },
      credentials: 'same-origin'
    }).then(function (r) { return r.json().catch(function () { return { ok: false }; }); });
  }

  function setAlert(form, message, ok) {
    var box = $('[data-form-alert]', form);
    if (!box) { return; }
    box.textContent = message;
    box.className = 'alert ' + (ok ? 'alert--ok' : 'alert--error');
    box.hidden = false;
  }

  $$('[data-ajax-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var submit = $('[type="submit"]', form);
      if (form.dataset.busy === '1') { return; }
      form.dataset.busy = '1';
      if (submit) { submit.disabled = true; }

      postForm(form).then(function (res) {
        if (res && res.ok) {
          setAlert(form, res.message || form.getAttribute('data-success') || 'Message envoyé.', true);
          track(form.getAttribute('data-event') || 'form_submit', { ref: res.ref || '' });
          form.reset();
          var needsInput = $('[data-needs-input]', form);
          if (needsInput) { needsInput.value = needsInput.getAttribute('data-default') || ''; }
        } else {
          setAlert(form, (res && res.error) || form.getAttribute('data-failure') || 'Envoi impossible.', false);
        }
      }).catch(function () {
        setAlert(form, form.getAttribute('data-failure') || 'Envoi impossible.', false);
      }).finally(function () {
        form.dataset.busy = '0';
        if (submit) { submit.disabled = false; }
        var ts = $('[name="ts"]', form);
        if (ts) { ts.value = String(Math.floor(Date.now() / 1000)); }
      });
    });
  });

  /* --------------------------------------------------- pop-up de sortie */

  var exitModal = $('[data-exit]');
  if (exitModal && cfg.exitIntent) {
    var shown = false;
    var seenKey = 'ioio_exit_seen';

    function alreadySeen() {
      try { return sessionStorage.getItem(seenKey) === '1'; } catch (e) { return false; }
    }
    function markSeen() {
      try { sessionStorage.setItem(seenKey, '1'); } catch (e) { /* navigation privée */ }
    }
    function openExit(source) {
      if (shown || alreadySeen()) { return; }
      shown = true;
      markSeen();
      exitModal.hidden = false;
      document.body.classList.add('is-locked');
      var field = $('input[type="email"]', exitModal);
      if (field) { setTimeout(function () { field.focus(); }, 120); }
      track('exit_popup_open', { source: source });
    }
    function closeExit() {
      exitModal.hidden = true;
      document.body.classList.remove('is-locked');
    }

    document.addEventListener('mouseout', function (e) {
      if (e.clientY > 6 || e.relatedTarget) { return; }
      openExit('mouseout');
    });

    // Variante mobile : retour arrière ou 45 s d'inactivité.
    var isTouch = window.matchMedia('(hover: none)').matches;
    if (isTouch) {
      try {
        history.pushState({ ioio: 1 }, '');
        window.addEventListener('popstate', function () { openExit('popstate'); });
      } catch (e) { /* certains navigateurs bloquent pushState */ }

      var idle = null;
      var delay = (cfg.exitInactivity || 45) * 1000;
      function resetIdle() {
        if (idle) { clearTimeout(idle); }
        idle = setTimeout(function () { openExit('inactivity'); }, delay);
      }
      ['touchstart', 'scroll', 'keydown'].forEach(function (evt) {
        window.addEventListener(evt, resetIdle, { passive: true });
      });
      resetIdle();
    }

    $$('[data-exit-close]', exitModal).forEach(function (b) { b.addEventListener('click', closeExit); });
    exitModal.addEventListener('click', function (e) { if (e.target === exitModal) { closeExit(); } });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !exitModal.hidden) { closeExit(); } });

    var exitForm = $('[data-exit-form]', exitModal);
    if (exitForm) {
      exitForm.addEventListener('submit', function (e) {
        e.preventDefault();
        postForm(exitForm).then(function (res) {
          setAlert(exitForm, (res && res.ok) ? (cfg.i18n.exitSent || 'Merci !') : ((res && res.error) || 'Envoi impossible.'), !!(res && res.ok));
          if (res && res.ok) {
            track('exit_popup_submit');
            setTimeout(closeExit, 2200);
          }
        }).catch(function () {
          setAlert(exitForm, 'Envoi impossible.', false);
        });
      });
    }
  }

  /* ----------------------------------------------------- assistant iOiO */

  var bot = $('[data-bot]');
  if (bot) {
    var panel = $('[data-bot-panel]', bot);
    var launcher = $('[data-bot-launcher]', bot);
    var log = $('[data-bot-log]', bot);
    var typing = $('[data-bot-typing]', bot);
    var form = $('[data-bot-form]', bot);
    var input = $('[data-bot-input]', bot);
    var history = [];
    var busy = false;

    function toggleBot(open) {
      var willOpen = typeof open === 'boolean' ? open : panel.hidden;
      panel.hidden = !willOpen;
      launcher.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      if (willOpen) {
        track('chat_open');
        if (input) { setTimeout(function () { input.focus(); }, 150); }
        scrollLog();
      }
    }
    function scrollLog() { if (log) { log.scrollTop = log.scrollHeight; } }

    function addMessage(text, mine, sources) {
      var wrap = document.createElement('div');
      wrap.className = 'bot__msg' + (mine ? ' bot__msg--me' : '');
      var span = document.createElement('span');
      span.textContent = text;
      wrap.appendChild(span);

      if (sources && sources.length) {
        var list = document.createElement('span');
        list.className = 'bot__sources';
        sources.forEach(function (source) {
          var label = typeof source === 'string' ? source : source.label;
          var url = typeof source === 'string' ? '' : source.url;
          var pill = document.createElement(url ? 'a' : 'span');
          pill.className = 'bot__source';
          pill.textContent = label;
          if (url) { pill.href = url; }
          list.appendChild(pill);
        });
        wrap.appendChild(list);
      }
      log.appendChild(wrap);
      scrollLog();
    }

    function ask(question) {
      if (busy || !question) { return; }
      busy = true;
      addMessage(question, true);
      history.push({ role: 'user', text: question });
      if (typing) { typing.hidden = false; }
      scrollLog();
      track('chat_question');

      fetch(base + '/api/chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'fetch' },
        credentials: 'same-origin',
        body: JSON.stringify({ q: question, lang: cfg.lang || 'fr', csrf: cfg.chatToken || '', history: history.slice(-6) })
      }).then(function (r) { return r.json(); }).then(function (res) {
        if (typing) { typing.hidden = true; }
        var answer = (res && res.answer) || (cfg.i18n.botError || '');
        addMessage(answer, false, (res && res.sources) || []);
        history.push({ role: 'model', text: answer });
      }).catch(function () {
        if (typing) { typing.hidden = true; }
        addMessage(cfg.i18n.botError || '', false, []);
      }).finally(function () { busy = false; });
    }

    if (launcher) { launcher.addEventListener('click', function () { toggleBot(); }); }
    $$('[data-bot-close]', bot).forEach(function (b) { b.addEventListener('click', function () { toggleBot(false); }); });
    $$('[data-bot-suggestion]', bot).forEach(function (b) {
      b.addEventListener('click', function () { ask(b.textContent.trim()); });
    });
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        var value = (input && input.value || '').trim();
        if (!value) { return; }
        input.value = '';
        ask(value);
      });
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && panel && !panel.hidden) { toggleBot(false); }
    });
    $$('[data-bot-open]').forEach(function (b) {
      b.addEventListener('click', function (e) { e.preventDefault(); toggleBot(true); });
    });
  }
})();
