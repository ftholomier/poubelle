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

  /* ------------------------------------------------------ consentement */

  var CONSENT_COOKIE = 'ioio_consent';
  var CONSENT_VERSION = 1;

  function readConsent() {
    var match = document.cookie.match(new RegExp('(?:^|; )' + CONSENT_COOKIE + '=([^;]*)'));
    if (!match) { return null; }
    try {
      var value = JSON.parse(decodeURIComponent(match[1]));
      return value && value.v === CONSENT_VERSION ? value : null;
    } catch (e) { return null; }
  }

  function consentAllows(category) {
    var value = readConsent();
    return !!(value && value[category]);
  }
  window.ioioConsent = consentAllows;

  function writeConsent(choices) {
    var value = {
      v: CONSENT_VERSION, at: new Date().toISOString(),
      analytics: !!choices.analytics, ai: !!choices.ai
    };
    var attrs = '; path=' + (base || '/') + '; max-age=33696000; samesite=lax' + (location.protocol === 'https:' ? '; secure' : '');
    document.cookie = CONSENT_COOKIE + '=' + encodeURIComponent(JSON.stringify(value)) + attrs;
    if (value.analytics) { loadAnalytics(); }
  }

  /* Le script de mesure n'est injecté qu'après acceptation, sans rechargement. */
  function loadAnalytics() {
    var cfg = (window.IOIO && window.IOIO.analytics) || {};
    if (!cfg.provider || cfg.provider === 'none' || document.querySelector('[data-analytics]')) { return; }
    var script = document.createElement('script');
    script.defer = true;
    script.setAttribute('data-analytics', cfg.provider);
    if (cfg.provider === 'plausible' && cfg.domain) {
      script.dataset.domain = cfg.domain;
      script.src = 'https://plausible.io/js/script.js';
    } else if (cfg.provider === 'matomo' && cfg.src) {
      window._paq = window._paq || [];
      window._paq.push(['trackPageView'], ['enableLinkTracking']);
      script.src = cfg.src;
    } else {
      return;
    }
    document.head.appendChild(script);
  }

  var consentBanner = $('[data-consent]');
  var consentPanel = $('[data-consent-panel]');

  function closeConsent() {
    if (consentBanner) { consentBanner.hidden = true; }
    if (consentPanel) { consentPanel.hidden = true; }
    document.body.classList.remove('is-locked');
  }

  function openConsentPanel() {
    if (!consentPanel) { return; }
    var current = readConsent() || {};
    $$('[data-consent-toggle]', consentPanel).forEach(function (input) {
      input.checked = !!current[input.getAttribute('data-consent-toggle')];
    });
    consentPanel.hidden = false;
    document.body.classList.add('is-locked');
    var first = consentPanel.querySelector('button, input');
    if (first) { setTimeout(function () { first.focus(); }, 120); }
  }

  function decide(choices, keepPanel) {
    writeConsent(choices);
    if (consentBanner) { consentBanner.hidden = true; }
    if (!keepPanel) { closeConsent(); return; }
    var saved = $('[data-consent-saved]', consentPanel);
    if (saved) {
      saved.hidden = false;
      setTimeout(function () { closeConsent(); }, 1100);
    } else {
      closeConsent();
    }
  }

  $$('[data-consent-accept]').forEach(function (b) {
    b.addEventListener('click', function () { decide({ analytics: true, ai: true }, b.closest('[data-consent-panel]') !== null); });
  });
  $$('[data-consent-refuse]').forEach(function (b) {
    b.addEventListener('click', function () { decide({ analytics: false, ai: false }, b.closest('[data-consent-panel]') !== null); });
  });
  $$('[data-consent-open]').forEach(function (b) {
    b.addEventListener('click', function (e) { e.preventDefault(); openConsentPanel(); });
  });
  $$('[data-consent-close]').forEach(function (b) {
    b.addEventListener('click', function () {
      consentPanel.hidden = true;
      document.body.classList.remove('is-locked');
    });
  });
  $$('[data-consent-save]').forEach(function (b) {
    b.addEventListener('click', function () {
      var choices = {};
      $$('[data-consent-toggle]', consentPanel).forEach(function (input) {
        choices[input.getAttribute('data-consent-toggle')] = input.checked;
      });
      decide(choices, true);
    });
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && consentPanel && !consentPanel.hidden) {
      consentPanel.hidden = true;
      document.body.classList.remove('is-locked');
    }
  });

  if (consentAllows('analytics')) { loadAnalytics(); }

  /* ------------------------------------------------------------ mesure */

  function track(name, props) {
    if (!consentAllows('analytics')) { return; }
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

  /* --------------------------------- compteur de disponibilités (page d'accueil) */

  /* Le chiffre monte de 0 à sa valeur : c'est ce qui accroche l'œil à l'arrivée.
     Sans animation demandée, on laisse simplement la valeur affichée par PHP. */
  $$('[data-count-to]').forEach(function (el) {
    var target = parseInt(el.getAttribute('data-count-to') || '0', 10);
    if (reduced || !(target > 0)) { return; }

    var started = false;
    function run() {
      if (started) { return; }
      started = true;
      var t0 = 0;
      var span = 900;
      function step(now) {
        if (!t0) { t0 = now; }
        var p = Math.min(1, (now - t0) / span);
        el.textContent = String(Math.round(target * (1 - Math.pow(1 - p, 3))));
        if (p < 1) { requestAnimationFrame(step); }
      }
      el.textContent = '0';
      requestAnimationFrame(step);
    }

    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) { run(); io.disconnect(); }
        });
      }, { threshold: 0.6 });
      io.observe(el);
    } else {
      run();
    }
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

    /* Le grand format et la vignette échangent leurs places. On déplace la
       source complète — src ET srcset, sinon le navigateur continue de servir
       la candidate du srcset et l'image ne change pas — mais chaque emplacement
       garde son propre attribut sizes, qui décrit sa taille d'affichage. */
    function grab(img) {
      return {
        src: img.getAttribute('src') || '',
        srcset: img.getAttribute('srcset') || '',
        width: img.getAttribute('width') || '',
        height: img.getAttribute('height') || '',
        alt: img.getAttribute('alt') || ''
      };
    }
    function put(img, data) {
      if (data.srcset) { img.setAttribute('srcset', data.srcset); } else { img.removeAttribute('srcset'); }
      if (data.width) { img.setAttribute('width', data.width); } else { img.removeAttribute('width'); }
      if (data.height) { img.setAttribute('height', data.height); } else { img.removeAttribute('height'); }
      img.setAttribute('alt', data.alt);
      img.setAttribute('src', data.src);
    }

    $$('[data-gallery-thumb]', gallery).forEach(function (thumb) {
      thumb.addEventListener('click', function () {
        if (!main) { return; }
        var small = thumb.querySelector('img');

        if (small) {
          var wasMain = grab(main);
          var wasThumb = grab(small);
          put(main, wasThumb);
          put(small, wasMain);
          thumb.setAttribute('data-src', wasMain.src);
          thumb.setAttribute('data-alt', wasMain.alt);
        } else {
          // Vignette rendue en pastille (photo trop petite) : pas d'échange possible.
          var src = thumb.getAttribute('data-src');
          if (!src) { return; }
          main.removeAttribute('srcset');
          main.setAttribute('alt', thumb.getAttribute('data-alt') || main.getAttribute('alt') || '');
          main.setAttribute('src', src);
        }

        main.classList.remove('is-swapping');
        void main.offsetWidth;              // relance l'animation à chaque échange
        main.classList.add('is-swapping');
        $$('[data-gallery-thumb]', gallery).forEach(function (t) { t.classList.remove('is-active'); });
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

  /* Un envoi jugé automatique n'est pas refusé : le serveur renvoie une
     question simple, qu'on insère au-dessus du bouton. Le visiteur répond et
     renvoie son message ; personne d'autre ne voit jamais ce champ. */
  function showChallenge(form, challenge) {
    var block = $('[data-challenge]', form);
    if (!block) {
      block = document.createElement('div');
      block.className = 'challenge';
      block.setAttribute('data-challenge', '');
      block.innerHTML =
        '<label class="challenge__label"></label>' +
        '<input class="field" type="text" name="challenge" inputmode="numeric" autocomplete="off" required>' +
        '<p class="challenge__hint"></p>';
      var submit = $('[type="submit"]', form);
      if (submit) { form.insertBefore(block, submit); } else { form.appendChild(block); }
    }
    $('.challenge__label', block).textContent = challenge.question || '';
    $('.challenge__hint', block).textContent = challenge.hint || '';
    var input = $('[name="challenge"]', block);
    input.value = '';
    input.focus();
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
          var asked = $('[data-challenge]', form);
          if (asked) { asked.remove(); }
        } else if (res && res.challenge) {
          showChallenge(form, res.challenge);
        } else {
          setAlert(form, (res && res.error) || form.getAttribute('data-failure') || 'Envoi impossible.', false);
        }
      }).catch(function () {
        setAlert(form, form.getAttribute('data-failure') || 'Envoi impossible.', false);
      }).finally(function () {
        form.dataset.busy = '0';
        if (submit) { submit.disabled = false; }
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

  /* --------------------------------------------------- album photo (lightbox) */

  var lightbox = $('[data-lightbox]');
  if (lightbox) {
    var lbImg = $('[data-lightbox-img]', lightbox);
    var lbCaption = $('[data-lightbox-caption]', lightbox);
    var lbCounter = $('[data-lightbox-counter]', lightbox);
    var album = [];
    var current = 0;

    function collect(button) {
      var grid = button.closest('[data-album]');
      return $$('[data-album-open]', grid).map(function (b) {
        return { src: b.getAttribute('data-full'), caption: b.getAttribute('data-caption') || '' };
      });
    }

    function show(index) {
      if (!album.length) { return; }
      current = (index + album.length) % album.length;
      var photo = album[current];
      lbImg.src = photo.src;
      lbImg.alt = photo.caption;
      lbCaption.textContent = photo.caption;
      lbCounter.textContent = (current + 1) + ' / ' + album.length;
    }

    function openLightbox(button) {
      album = collect(button);
      lightbox.hidden = false;
      document.body.classList.add('is-locked');
      show(parseInt(button.getAttribute('data-album-open') || '0', 10));
      var close = $('[data-lightbox-close]', lightbox);
      if (close) { close.focus(); }
      track('album_open');
    }

    function closeLightbox() {
      lightbox.hidden = true;
      document.body.classList.remove('is-locked');
      lbImg.removeAttribute('src');
    }

    $$('[data-album-open]').forEach(function (button) {
      button.addEventListener('click', function () { openLightbox(button); });
    });
    $$('[data-lightbox-close]', lightbox).forEach(function (b) { b.addEventListener('click', closeLightbox); });
    $$('[data-lightbox-prev]', lightbox).forEach(function (b) { b.addEventListener('click', function () { show(current - 1); }); });
    $$('[data-lightbox-next]', lightbox).forEach(function (b) { b.addEventListener('click', function () { show(current + 1); }); });
    lightbox.addEventListener('click', function (e) {
      if (e.target === lightbox || e.target.classList.contains('lightbox__stage')) { closeLightbox(); }
    });
    document.addEventListener('keydown', function (e) {
      if (lightbox.hidden) { return; }
      if (e.key === 'Escape') { closeLightbox(); }
      if (e.key === 'ArrowLeft') { show(current - 1); }
      if (e.key === 'ArrowRight') { show(current + 1); }
    });

    // Balayage horizontal sur mobile.
    var touchX = null;
    lightbox.addEventListener('touchstart', function (e) { touchX = e.touches[0].clientX; }, { passive: true });
    lightbox.addEventListener('touchend', function (e) {
      if (touchX === null) { return; }
      var delta = e.changedTouches[0].clientX - touchX;
      if (Math.abs(delta) > 50) { show(current + (delta < 0 ? 1 : -1)); }
      touchX = null;
    }, { passive: true });
  }

  /* ------------------------------------------------- carrousel des avis */

  /* Le défilement, l'accroche et le glissement tactile viennent du CSS. Le
     script ajoute l'avance automatique, les flèches et les pastilles, et
     s'efface dès que le visiteur prend la main. */

  $$('[data-carousel]').forEach(function (carousel) {
    var track = $('[data-carousel-track]', carousel);
    var items = $$('[data-carousel-item]', carousel);
    var dots = $$('[data-carousel-dot]', carousel);
    var prev = $('[data-carousel-prev]', carousel);
    var next = $('[data-carousel-next]', carousel);
    if (!track || items.length < 2) { return; }

    var timer = null;
    var paused = false;
    var DELAY = 5200;

    function step() { return items[1].offsetLeft - items[0].offsetLeft; }
    function maxScroll() { return track.scrollWidth - track.clientWidth; }

    /* Quatre cartes tiennent à l'écran : il n'y a donc pas huit positions
       d'arrêt mais huit moins trois. Les pastilles en trop sont masquées, sinon
       les dernières pointeraient toutes vers la même vue. */
    function stops() {
      var unit = step() || 1;
      var visible = Math.max(1, Math.round(track.clientWidth / unit));
      return Math.max(1, items.length - visible + 1);
    }

    function current() {
      var unit = step() || 1;
      return Math.min(stops() - 1, Math.round(track.scrollLeft / unit));
    }

    function goTo(index) {
      var last = stops() - 1;
      track.scrollLeft = Math.min(maxScroll(), Math.max(0, Math.min(last, index) * step()));
    }

    function advance() {
      // Arrivé au bout, on repart du début : le défilement ne s'arrête jamais.
      if (track.scrollLeft >= maxScroll() - 2) { goTo(0); } else { goTo(current() + 1); }
    }

    function sync() {
      var index = current();
      var last = stops();
      dots.forEach(function (dot, i) {
        dot.hidden = i >= last;
        dot.classList.toggle('is-active', i === index);
        dot.setAttribute('aria-selected', i === index ? 'true' : 'false');
      });
      if (prev) { prev.disabled = track.scrollLeft <= 2; }
      if (next) { next.disabled = track.scrollLeft >= maxScroll() - 2; }
    }

    function play() {
      if (reduced || paused || timer) { return; }
      timer = setInterval(advance, DELAY);
    }
    function stop() {
      if (timer) { clearInterval(timer); timer = null; }
    }

    if (prev) { prev.addEventListener('click', function () { goTo(current() - 1); }); }
    if (next) { next.addEventListener('click', function () { goTo(current() + 1); }); }
    dots.forEach(function (dot, i) { dot.addEventListener('click', function () { goTo(i); }); });

    var ticking = null;
    track.addEventListener('scroll', function () {
      if (ticking) { return; }
      ticking = requestAnimationFrame(function () { ticking = null; sync(); });
    }, { passive: true });

    // On ne bouge pas sous les yeux du visiteur qui lit ou qui manipule.
    ['mouseenter', 'focusin', 'touchstart'].forEach(function (event) {
      carousel.addEventListener(event, function () { paused = true; stop(); }, { passive: true });
    });
    ['mouseleave', 'focusout'].forEach(function (event) {
      carousel.addEventListener(event, function () { paused = false; play(); });
    });
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) { stop(); } else { play(); }
    });

    /* Un témoignage trop long défile dans son pavé : on le signale par un
       dégradé, qui s'efface quand le visiteur arrive au bout. */
    function markOverflow() {
      $$('.review__text', carousel).forEach(function (text) {
        text.classList.toggle('is-scrollable', text.scrollHeight > text.clientHeight + 2);
      });
    }
    $$('.review__text', carousel).forEach(function (text) {
      text.addEventListener('scroll', function () {
        text.classList.toggle('is-end', text.scrollTop + text.clientHeight >= text.scrollHeight - 2);
      }, { passive: true });
    });

    window.addEventListener('resize', function () { sync(); markOverflow(); }, { passive: true });
    sync();
    markOverflow();
    play();
  });

  /* ------------------------------------------------------------- cartes */

  /* L'iframe du plan est rendue par PHP : elle est présente dès l'ouverture de
     la page. Le JS ne sert qu'à passer d'une adresse à l'autre sur la page
     contact, quand on clique une pastille de lieu. */

  $$('[data-map]').forEach(function (map) {
    $$('[data-map-place]', map).forEach(function (tag) {
      tag.addEventListener('click', function () {
        var url = tag.getAttribute('data-embed');
        var label = tag.getAttribute('data-label') || tag.textContent.trim();
        if (!url) { return; }

        $$('[data-map-place]', map).forEach(function (t) { t.classList.remove('is-active'); });
        tag.classList.add('is-active');

        var frame = $('.map__frame', map);
        if (!frame) {
          frame = document.createElement('iframe');
          frame.className = 'map__frame';
          frame.loading = 'lazy';
          frame.referrerPolicy = 'no-referrer-when-downgrade';
          frame.setAttribute('allowfullscreen', '');
          map.insertBefore(frame, map.firstChild);
          map.classList.add('is-loaded');
        }
        frame.src = url;
        frame.title = label;
        track('map_open', { place: label });
      });
    });
  });

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

    /* La conversation suit le visiteur de page en page : elle est gardée dans
       sessionStorage, donc pour la durée de l'onglet, et jamais au-delà. */
    var STORE = 'ioio_chat';
    var MAX_KEPT = 40;

    function readStore() {
      try {
        var raw = sessionStorage.getItem(STORE);
        var data = raw ? JSON.parse(raw) : null;
        return data && Array.isArray(data.messages) ? data : { messages: [], open: false };
      } catch (e) { return { messages: [], open: false }; }
    }
    function writeStore(data) {
      try { sessionStorage.setItem(STORE, JSON.stringify(data)); } catch (e) { /* mode privé : on continue sans mémoire */ }
    }
    function remember(entry) {
      var data = readStore();
      data.messages.push(entry);
      if (data.messages.length > MAX_KEPT) { data.messages = data.messages.slice(-MAX_KEPT); }
      writeStore(data);
    }
    function rememberOpen(open) {
      var data = readStore();
      data.open = !!open;
      writeStore(data);
    }

    function toggleBot(open) {
      var willOpen = typeof open === 'boolean' ? open : panel.hidden;
      panel.hidden = !willOpen;
      launcher.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
      rememberOpen(willOpen);
      if (willOpen) {
        track('chat_open');
        if (input) { setTimeout(function () { input.focus(); }, 150); }
        scrollLog();
      }
    }
    function scrollLog() { if (log) { log.scrollTop = log.scrollHeight; } }

    /* Liste de pastilles : sources consultées ou boutons de navigation. */
    function pills(items, className) {
      var list = document.createElement('span');
      list.className = className;
      items.forEach(function (item) {
        var label = typeof item === 'string' ? item : item.label;
        var url = typeof item === 'string' ? '' : item.url;
        if (!label) { return; }
        var pill = document.createElement(url ? 'a' : 'span');
        pill.className = className === 'bot__actions' ? 'bot__action' : 'bot__source';
        pill.textContent = label;
        if (url) { pill.href = url; }
        list.appendChild(pill);
      });
      return list.childNodes.length ? list : null;
    }

    function addMessage(text, mine, sources, actions) {
      var wrap = document.createElement('div');
      wrap.className = 'bot__msg' + (mine ? ' bot__msg--me' : '');
      var span = document.createElement('span');
      span.textContent = text;
      wrap.appendChild(span);

      if (sources && sources.length) {
        var s = pills(sources, 'bot__sources');
        if (s) { wrap.appendChild(s); }
      }
      if (actions && actions.length) {
        var a = pills(actions, 'bot__actions');
        if (a) { wrap.appendChild(a); }
      }
      log.appendChild(wrap);
      scrollLog();
    }

    /* Restitution de l'échange en cours au chargement de chaque page. */
    (function restore() {
      var data = readStore();
      data.messages.forEach(function (m) {
        addMessage(m.text, m.mine, m.sources, m.actions);
        history.push({ role: m.mine ? 'user' : 'model', text: m.text });
      });
      if (data.open) { toggleBot(true); }
    })();

    function ask(question) {
      if (busy || !question) { return; }
      busy = true;
      addMessage(question, true);
      remember({ text: question, mine: true });
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
        var sources = (res && res.sources) || [];
        var actions = (res && res.actions) || [];
        addMessage(answer, false, sources, actions);
        remember({ text: answer, mine: false, sources: sources, actions: actions });
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
