/* ==========================================================================
   Renov'Est — interactions du site public (JavaScript natif, sans dépendance)
   Comportements repris de la maquette : header au scroll, barre de progression,
   parallaxe vidéo, apparitions, compteurs, barre CTA, bot + bulle d'accroche,
   popup de sortie, curseur, carrousel d'avis, devis en 3 étapes, filtres, visionneuse.
   ========================================================================== */
(function () {
  'use strict';

  var CFG = {};
  try { CFG = JSON.parse(document.getElementById('rv-config').textContent || '{}'); } catch (e) {}
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var isMobile = function () { return window.innerWidth < 1260; };
  var store = {
    get: function (k) { try { return sessionStorage.getItem(k); } catch (e) { return null; } },
    set: function (k, v) { try { sessionStorage.setItem(k, v); } catch (e) {} }
  };

  /* Anti-spam : preuve qu'un humain a utilisé la page (gestes réels : clic, touche, toucher).
     Le serveur l'attend avec chaque envoi ; un robot qui poste sans ouvrir la page ne l'a pas. */
  var gestures = 0;
  ['pointerdown', 'keydown', 'touchstart'].forEach(function (ev) {
    document.addEventListener(ev, function (e) { if (e.isTrusted) gestures++; }, { capture: true, passive: true });
  });
  function proof(token) { return String(token || '').slice(0, 10).split('').reverse().join('') + '.' + Math.min(gestures, 999); }

  /* ---------------------------------------------------------------- Apparitions au scroll (une seule fois) */
  var io = null;
  function revealAll() {
    $$('[data-reveal]').forEach(function (el) { el.classList.add('is-in'); });
  }
  function wireReveal(root) {
    var els = $$('[data-reveal]:not([data-rw])', root);
    if (!('IntersectionObserver' in window) || reduced) { els.forEach(function (el) { el.classList.add('is-in'); countUp(el, true); }); return; }
    if (!io) {
      io = new IntersectionObserver(function (entries) {
        entries.forEach(function (en) {
          if (!en.isIntersecting) return;
          var el = en.target, d = +(el.getAttribute('data-reveal-delay') || 0);
          el.style.transitionDelay = d + 'ms';
          el.classList.add('is-in');
          countUp(el, false);
          io.unobserve(el);
          // Une fois apparu, l'élément retrouve ses propres transitions (survols)
          setTimeout(function () { el.removeAttribute('data-reveal'); el.style.transitionDelay = ''; }, 820 + d);
        });
      }, { rootMargin: '0px 0px -8% 0px', threshold: 0.12 });
    }
    els.forEach(function (el) { el.setAttribute('data-rw', '1'); io.observe(el); });
  }

  /* ---------------------------------------------------------------- Compteurs (0 → valeur, 1,5 s, easeOutCubic) */
  function countUp(el, instant) {
    if (!el.hasAttribute('data-count')) return;
    var to = +el.getAttribute('data-count'), pre = el.getAttribute('data-prefix') || '', suf = el.getAttribute('data-suffix') || '';
    if (instant || reduced) { el.textContent = pre + to + suf; return; }
    var t0 = performance.now() + (+el.getAttribute('data-reveal-delay') || 0);
    el.textContent = pre + '0' + suf;
    var tick = function (t) {
      var k = Math.max(0, Math.min(1, (t - t0) / 1500));
      el.textContent = pre + Math.round(to * (1 - Math.pow(1 - k, 3))) + suf;
      if (k < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  }

  /* ---------------------------------------------------------------- Header, progression, parallaxe, barre CTA */
  var hdr = $('#rv-hdr'), prog = $('#rv-prog'), video = $('#rv-video'), cta = $('#rv-cta');
  var lastY = 0, ctaHidden = false, ticking = false;
  function onScroll() {
    ticking = false;
    var y = window.scrollY || document.documentElement.scrollTop || 0;
    var h = document.documentElement.scrollHeight - window.innerHeight;
    if (hdr) hdr.classList.toggle('is-scrolled', y > 40);
    if (prog) prog.style.transform = 'scaleX(' + (h > 0 ? Math.min(1, y / h) : 0).toFixed(4) + ')';
    if (video && y < 1400 && !reduced) video.style.transform = 'translate3d(0,' + (y * 0.3).toFixed(1) + 'px,0)';
    var dy = y - lastY;
    if (Math.abs(dy) > 8) { ctaHidden = dy > 0 && y > 320; lastY = y; }
    if (y < 320 || y > h - 60) ctaHidden = false;
    // Pendant un enregistrement ou la réécoute d'un vocal, la barre reste visible
    var vpEl = document.getElementById('rv-vpanel');
    if (cta && (cta.classList.contains('is-rec') || (vpEl && !vpEl.hidden))) ctaHidden = false;
    if (cta) cta.classList.toggle('is-hidden', ctaHidden);
    document.body.classList.toggle('cta-hidden', ctaHidden || !cta);
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });

  /* ---------------------------------------------------------------- Hauteur du héros = écran visible − header */
  var heroWrap = $('#rv-herowrap'), hdrBase = 0;
  function measureHero() {
    if (!heroWrap || !hdr) return;
    var y = window.scrollY || 0;
    if (!(y > 40 && hdrBase)) hdrBase = hdr.offsetTop + hdr.offsetHeight;
    var vh = (window.visualViewport && window.visualViewport.height) || window.innerHeight;
    heroWrap.style.setProperty('--hero-h', Math.max(300, Math.round(vh - (hdrBase || 120))) + 'px');
  }
  // Vidéo muette forcée (lecture automatique sur tous les navigateurs)
  function silence() {
    if (!video) return;
    video.muted = true; video.volume = 0; video.defaultMuted = true;
    if (reduced) { video.removeAttribute('autoplay'); video.pause(); return; }
    var p = video.play(); if (p && p.catch) p.catch(function () {});
  }

  /* ---------------------------------------------------------------- Menu mobile */
  var burger = $('#rv-burger'), mmenu = $('#rv-mmenu');
  if (burger && mmenu) {
    burger.addEventListener('click', function () {
      var open = mmenu.hasAttribute('hidden');
      if (open) mmenu.removeAttribute('hidden'); else mmenu.setAttribute('hidden', '');
      burger.setAttribute('aria-expanded', open ? 'true' : 'false');
      burger.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
    });
    window.addEventListener('resize', function () { if (window.innerWidth >= 1360) { mmenu.setAttribute('hidden', ''); burger.setAttribute('aria-expanded', 'false'); } });
  }

  /* ---------------------------------------------------------------- E-mails protégés */
  $$('[data-mail]').forEach(function (a) {
    var m = a.getAttribute('data-mail') + '@' + a.getAttribute('data-domain');
    a.textContent = m; a.setAttribute('href', 'mailto:' + m);
  });

  /* ---------------------------------------------------------------- Carrousel des avis (auto 5 s, pause au survol) */
  var avis = $('#rv-avis'), avisHold = false;
  function avisStep(dir) {
    if (!avis) return;
    var c = avis.children[0];
    var step = c ? c.getBoundingClientRect().width + 18 : 300;
    if (dir > 0 && avis.scrollLeft + avis.clientWidth >= avis.scrollWidth - 10) avis.scrollTo({ left: 0, behavior: 'smooth' });
    else if (dir < 0 && avis.scrollLeft <= 5) avis.scrollTo({ left: avis.scrollWidth, behavior: 'smooth' });
    else avis.scrollBy({ left: step * dir, behavior: 'smooth' });
  }
  if (avis) {
    avis.addEventListener('mouseenter', function () { avisHold = true; });
    avis.addEventListener('mouseleave', function () { avisHold = false; });
    avis.addEventListener('touchstart', function () { avisHold = true; }, { passive: true });
    avis.addEventListener('focusin', function () { avisHold = true; });
    avis.addEventListener('focusout', function (ev) { if (!avis.contains(ev.relatedTarget)) avisHold = false; });
    // Témoignages longs : ascenseur dans la carte, fondu en bas tant qu'il reste du texte à lire
    var avisTexts = $$('.acard__x', avis);
    var avisFit = function () {
      avisTexts.forEach(function (x) {
        var over = x.scrollHeight > x.clientHeight + 2;
        if (over) { x.setAttribute('tabindex', '0'); x.setAttribute('role', 'region'); x.setAttribute('aria-label', 'Texte de l\'avis'); }
        else { x.removeAttribute('tabindex'); x.removeAttribute('role'); x.removeAttribute('aria-label'); }
        x.toggleAttribute('data-more', over && x.scrollTop + x.clientHeight < x.scrollHeight - 4);
      });
    };
    avisTexts.forEach(function (x) { x.addEventListener('scroll', avisFit, { passive: true }); });
    avisFit();
    window.addEventListener('resize', avisFit);
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(avisFit);
    $$('[data-avis]').forEach(function (b) { b.addEventListener('click', function () { avisStep(+b.getAttribute('data-avis')); }); });
    if (!reduced) setInterval(function () { if (!avisHold && !document.hidden) avisStep(1); }, 5000);
  }

  /* ---------------------------------------------------------------- Filtres (actus, galerie photos) */
  function wireFilter(root, itemSel) {
    var chips = $$('.chip[data-filter]', root);
    chips.forEach(function (ch) {
      ch.addEventListener('click', function () {
        var f = ch.getAttribute('data-filter');
        chips.forEach(function (c) { c.classList.toggle('is-on', c === ch); });
        $$(itemSel, root).forEach(function (it) {
          var show = !f || it.getAttribute('data-cat') === f;
          it.classList.toggle('is-out', !show);
          it.style.display = show ? '' : 'none';
        });
        wireReveal(root);
      });
    });
  }
  $$('[data-news]').forEach(function (r) { wireFilter(r, '.ncard'); });
  $$('[data-gallery]').forEach(function (r) { wireFilter(r, '.gtile'); });

  /* ---------------------------------------------------------------- Visionneuse photos */
  var lb = null, lbList = [], lbIdx = 0;
  function lbShow(i) {
    lbIdx = (i + lbList.length) % lbList.length;
    $('img', lb).src = lbList[lbIdx];
    $('.lb__c', lb).textContent = (lbIdx + 1) + ' / ' + lbList.length;
  }
  function lbClose() { if (lb) { lb.remove(); lb = null; document.removeEventListener('keydown', lbKey); } }
  function lbKey(e) { if (e.key === 'Escape') lbClose(); else if (e.key === 'ArrowRight') lbShow(lbIdx + 1); else if (e.key === 'ArrowLeft') lbShow(lbIdx - 1); }
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('[data-lightbox] a');
    if (!a || e.metaKey || e.ctrlKey) return;
    e.preventDefault();
    var box = a.closest('[data-lightbox]');
    var links = $$('a', box).filter(function (x) { return x.offsetParent !== null || x === a; });
    lbList = links.map(function (x) { return x.getAttribute('href'); });
    lb = document.createElement('div');
    lb.className = 'lb'; lb.setAttribute('role', 'dialog'); lb.setAttribute('aria-modal', 'true');
    lb.innerHTML = '<img alt=""><button class="lb__x" type="button" aria-label="Fermer">×</button>' +
      (lbList.length > 1 ? '<button class="lb__p" type="button" aria-label="Précédente">←</button><button class="lb__n" type="button" aria-label="Suivante">→</button>' : '') + '<div class="lb__c"></div>';
    document.body.appendChild(lb);
    lb.addEventListener('click', function (ev) {
      if (ev.target.classList.contains('lb__n')) lbShow(lbIdx + 1);
      else if (ev.target.classList.contains('lb__p')) lbShow(lbIdx - 1);
      else if (ev.target.tagName !== 'IMG') lbClose();
    });
    document.addEventListener('keydown', lbKey);
    lbShow(links.indexOf(a));
    $('.lb__x', lb).focus();
  });

  /* ---------------------------------------------------------------- Partage : copier le lien */
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var u = b.getAttribute('data-copy');
      var ok = function () { b.textContent = 'Lien copié ✓'; };
      if (navigator.clipboard) navigator.clipboard.writeText(u).then(ok, ok); else ok();
    });
  });

  /* ---------------------------------------------------------------- Carte (chargée à la demande : aucun cookie tiers avant clic) */
  $$('[data-map]').forEach(function (m) {
    $('button', m).addEventListener('click', function () {
      var f = document.createElement('iframe');
      f.src = m.getAttribute('data-map'); f.loading = 'lazy'; f.title = 'Plan d\'accès'; f.referrerPolicy = 'no-referrer-when-downgrade';
      m.innerHTML = ''; m.appendChild(f);
    });
  });

  /* ---------------------------------------------------------------- Formulaires (JSON) */
  function post(url, data) {
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(data), credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Réponse inattendue du serveur.' }; }); });
  }
  function formData(form) {
    var d = {};
    $$('input,select,textarea', form).forEach(function (el) {
      if (!el.name || el.disabled) return;
      if ((el.type === 'checkbox' || el.type === 'radio') && !el.checked) return;
      if (el.name.slice(-2) === '[]') { var k = el.name.slice(0, -2); (d[k] = d[k] || []).push(el.value); }
      else d[el.name] = el.value;
    });
    d.page = location.pathname;
    d.pf = proof(d.token);
    return d;
  }
  function showErr(form, res) {
    var box = $('.dv__err', form);
    $$('.field', form).forEach(function (f) { f.classList.remove('is-err'); });
    if (res && res.fields) Object.keys(res.fields).forEach(function (k) { var el = form.elements[k]; if (el && el.closest) { var f = el.closest('.field'); if (f) f.classList.add('is-err'); } });
    if (box) { box.textContent = (res && res.error) || 'Envoi impossible. Réessayez ou appelez-nous.'; box.hidden = false; }
  }

  // Devis en 3 étapes
  var dv = $('#rv-devis');
  if (dv) {
    var step = +dv.getAttribute('data-step') || 1;
    var next = $('[data-dv="next"]', dv), back = $('[data-dv="back"]', dv);
    var valid = function () {
      if (step === 1) return $$('input[name="types[]"]:checked', dv).length > 0;
      if (step === 3) return dv.elements.nom.value.trim() !== '' && dv.elements.tel.value.trim() !== '';
      return true;
    };
    var paint = function () {
      $$('[data-pane]', dv).forEach(function (p) { p.hidden = +p.getAttribute('data-pane') !== step; });
      $$('[data-bar]', dv).forEach(function (b) { b.classList.toggle('is-on', +b.getAttribute('data-bar') <= step); });
      back.hidden = step === 1;
      next.textContent = step === 3 ? 'Envoyer ma demande' : 'Continuer →';
      next.classList.toggle('is-valid', valid());
    };
    $$('.dv__type input', dv).forEach(function (cb) {
      cb.addEventListener('change', function () { cb.closest('.dv__type').classList.toggle('is-on', cb.checked); paint(); });
    });
    dv.addEventListener('input', paint);
    back.addEventListener('click', function () { step = Math.max(1, step - 1); paint(); });
    dv.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!valid()) { if (step === 3) showErr(dv, { error: 'Indiquez votre nom et votre téléphone.', fields: { nom: 1, tel: 1 } }); return; }
      if (step < 3) { step++; paint(); var f = $('[data-pane="' + step + '"] input, [data-pane="' + step + '"] select', dv); if (f) f.focus({ preventScroll: true }); return; }
      next.disabled = true; next.textContent = 'Envoi…';
      post('/api/devis', formData(dv)).then(function (res) {
        next.disabled = false;
        if (res && res.ok) { dv.hidden = true; $('#rv-devis-ok').hidden = false; window.scrollTo({ top: 0, behavior: 'smooth' }); }
        else { paint(); showErr(dv, res); }
      }, function () { next.disabled = false; paint(); showErr(dv, null); });
    });
    paint();
  }

  // Contact
  var cf = $('#rv-contact');
  if (cf) {
    var when = $('[data-when="Devis"]', cf), lab = $('[data-label-msg]', cf);
    var sync = function () {
      var dev = (cf.querySelector('input[name="objet"]:checked') || {}).value === 'Devis';
      when.hidden = !dev; lab.textContent = lab.getAttribute(dev ? 'data-label-devis' : 'data-label-msg');
    };
    $$('input[name="objet"]', cf).forEach(function (r) { r.addEventListener('change', sync); });
    sync();
    cf.addEventListener('submit', function (e) {
      e.preventDefault();
      var btn = $('button[type="submit"]', cf); btn.disabled = true; btn.textContent = 'Envoi…';
      post('/api/contact', formData(cf)).then(function (res) {
        btn.disabled = false; btn.textContent = 'Envoyer';
        if (res && res.ok) { cf.hidden = true; cf.nextElementSibling.hidden = false; }
        else showErr(cf, res);
      }, function () { btn.disabled = false; btn.textContent = 'Envoyer'; showErr(cf, null); });
    });
  }

  /* ---------------------------------------------------------------- Bot IA */
  var bot = $('#rv-bot');
  var botP = $('#rv-botp'), teaser = $('#rv-teaser'), msgsBox = $('#rv-msgs'), launcher = $('#rv-botl');
  var chatLog = [], session = store.get('rv_bot_sid');
  if (!session) { session = Math.random().toString(36).slice(2) + Date.now().toString(36); store.set('rv_bot_sid', session); }
  try { chatLog = JSON.parse(store.get('rv_bot_hist') || '[]'); } catch (e) { chatLog = []; }
  function addMsg(role, text) {
    var d = document.createElement('div');
    var b = document.createElement('div');
    b.className = role === 'user' ? 'msg-u' : 'msg-b';
    b.textContent = text;
    // Liens internes cliquables (adresses du site)
    if (role !== 'user') b.innerHTML = b.innerHTML.replace(/(https?:\/\/[^\s<"'&]+[^\s<"'&.,;:!?)])/g, '<a href="$1" rel="noopener">$1</a>');
    d.appendChild(b); msgsBox.appendChild(d);
    msgsBox.scrollTop = msgsBox.scrollHeight;
    return d;
  }
  function saveHist() { store.set('rv_bot_hist', JSON.stringify(chatLog.slice(-20))); }
  function botOpen(open) {
    if (!bot) return;
    if (open === undefined) open = botP.hidden;
    botP.hidden = !open;
    bot.classList.toggle('is-open', open);
    launcher.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) { if (teaser) teaser.hidden = true; teaserDone = true; if (!msgsBox.children.length) renderHist(); setTimeout(function () { var i = $('#rv-botf input'); if (i && !isMobile()) i.focus(); }, 60); }
  }
  function renderHist() {
    msgsBox.innerHTML = '';
    addMsg('assistant', (CFG.bot && CFG.bot.welcome) || 'Bonjour 👋');
    chatLog.forEach(function (m) { addMsg(m.role, m.text); });
  }
  function ask(q, preset) {
    if (!q) return;
    if (botP.hidden) botOpen(true);
    addMsg('user', q);
    chatLog.push({ role: 'user', text: q });
    if (preset) { setTimeout(function () { addMsg('assistant', preset); chatLog.push({ role: 'assistant', text: preset }); saveHist(); }, 280); return; }
    var typing = addMsg('assistant', '');
    typing.firstChild.innerHTML = '<span class="msg-typing"><i></i><i></i><i></i></span>';
    post('/api/bot', { messages: chatLog, session: session, token: CFG.token, pf: proof(CFG.token) }).then(function (res) {
      typing.remove();
      var t = (res && (res.text || res.error)) || 'Je n\'ai pas pu répondre. Appelez-nous, nous vous répondrons avec plaisir.';
      addMsg('assistant', t);
      chatLog.push({ role: 'assistant', text: t }); saveHist();
    }, function () { typing.remove(); addMsg('assistant', 'Connexion impossible pour le moment.'); });
  }
  var teaserDone = !!store.get('rv_teaser_done');
  if (bot) {
    document.addEventListener('click', function (e) {
      var t = e.target.closest && e.target.closest('[data-act]');
      if (!t) return;
      var act = t.getAttribute('data-act');
      if (act === 'bot-toggle') botOpen();
      else if (act === 'teaser-close') teaser.hidden = true;
      else if (act === 'teaser-chat') { teaser.hidden = true; botOpen(true); }
      else if (act === 'teaser-call') teaser.hidden = true;
    });
    $$('[data-quick]', bot).forEach(function (b) {
      b.addEventListener('click', function () {
        var q = CFG.bot.quick[+b.getAttribute('data-quick')];
        if (q) ask(q.q, q.a);
      });
    });
    $('#rv-botf').addEventListener('submit', function (e) {
      e.preventDefault();
      var i = this.elements.q, v = i.value.trim();
      if (!v) return;
      i.value = ''; ask(v);
    });
    // « Poser une question » sur un produit : ouvre le bot avec la question pré-remplie
    document.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('[data-ask]');
      if (!a) return;
      var name = a.getAttribute('data-ask');
      ask((CFG.bot.ask || 'Une question sur {produit}').replace('{produit}', name), (CFG.bot.askAnswer || '').replace('{produit}', name));
    });
    // Bulle d'accroche : après N s sans activité, une seule fois par visite
    if (CFG.teaser && CFG.teaser.enabled && teaser) {
      var lastAct = Date.now();
      ['mousemove', 'scroll', 'keydown', 'touchstart'].forEach(function (ev) { window.addEventListener(ev, function () { lastAct = Date.now(); }, { passive: true }); });
      var idle = setInterval(function () {
        if (teaserDone || !botP.hidden || (exitBox && !exitBox.hidden)) return;
        if (Date.now() - lastAct > (CFG.teaser.delay || 20) * 1000) { teaser.hidden = false; teaserDone = true; store.set('rv_teaser_done', '1'); clearInterval(idle); }
      }, 1000);
    }
  } else {
    document.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('[data-ask]');
      if (a) location.href = CFG.contact;
    });
  }

  /* ---------------------------------------------------------------- Popup de sortie (une fois par session) */
  var exitBox = $('#rv-exit');
  function exitOpen() {
    if (!exitBox || !exitBox.hidden) return;
    if (cta && cta.classList.contains('is-rec')) return;
    var vp = document.getElementById('rv-vpanel'); if (vp && !vp.hidden) return;
    if (CFG.exit.mode !== 'always') { if (store.get('rv_exit_seen')) return; store.set('rv_exit_seen', '1'); }
    if (location.pathname === CFG.devis) return;
    exitBox.hidden = false;
    var i = $('input[name="tel"]', exitBox); if (i && !isMobile()) i.focus({ preventScroll: true });
  }
  function exitClose() { if (exitBox) exitBox.hidden = true; }
  if (exitBox && CFG.exit && CFG.exit.enabled) {
    // Ordinateur : la souris quitte la fenêtre par le haut
    document.addEventListener('mouseout', function (e) { if (e.clientY > 0 || e.relatedTarget) return; exitOpen(); });
    // Mobile : remontée rapide après avoir parcouru la page (geste de sortie)
    var mY = window.scrollY, mT = Date.now(), armed = false;
    window.addEventListener('scroll', function () {
      if (!('ontouchstart' in window)) return;
      var y = window.scrollY, t = Date.now();
      if (y > 1200) armed = true;
      if (armed && t - mT < 300 && mY - y > 450 && t > (window.__rvT0 || 0) + 15000) exitOpen();
      if (t - mT > 120) { mY = y; mT = t; }
    }, { passive: true });
    exitBox.addEventListener('click', function (e) {
      if (e.target === exitBox || (e.target.closest && e.target.closest('[data-act="exit-close"]'))) exitClose();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') exitClose(); });
  }

  /* ---------------------------------------------------------------- Message vocal (bouton rouge de la barre CTA) */
  var vp = $('#rv-vpanel');
  if (vp && cta && CFG.voice) {
    var V = CFG.voice;
    var vTitle = $('#rv-v-title'), vReview = $('#rv-v-review'), vSent = $('#rv-v-sent'), vErrorP = $('#rv-v-error');
    var vAudio = $('#rv-v-audio'), vTr = $('#rv-v-tr'), vSum = $('#rv-v-sum'), vSumTxt = $('#rv-v-sumtxt');
    var vNom = $('#rv-v-nom'), vTel = $('#rv-v-tel'), vErr = $('#rv-v-err'), vSend = $('#rv-v-send');
    var vTime = $('#rv-v-time'), vQ = $('#rv-v-q'), recBar = $('#rv-rec'), bars = $$('#rv-v-bars span');
    var phase = 'idle', mr = null, chunks = [], stream = null, actx = null, raf = 0, ti = 0, sr = null, secs = 0, vtext = '', vsum = '', blob = null, canceled = false, blobUrl = '';
    var fmtTime = function (n) { return Math.floor(n / 60) + ':' + String(n % 60).padStart(2, '0'); };
    var updTime = function () { vTime.textContent = fmtTime(secs); var qs = V.questions || []; if (qs.length) vQ.textContent = qs[Math.min(qs.length - 1, Math.floor(secs / 15))]; };
    var cleanup = function () {
      clearInterval(ti); cancelAnimationFrame(raf);
      try { if (sr) sr.stop(); } catch (e) {}
      sr = null;
      if (stream) stream.getTracks().forEach(function (t) { t.stop(); });
      stream = null;
      try { if (actx) actx.close(); } catch (e) {}
      actx = null;
      recBar.hidden = true; cta.classList.remove('is-rec');
      bars.forEach(function (b) { b.style.height = '4px'; });
    };
    var showPanel = function (which) {
      vp.hidden = false;
      if (botP && !botP.hidden) botOpen(false);
      vReview.hidden = which !== 'review'; vSent.hidden = which !== 'sent'; vErrorP.hidden = which !== 'error';
    };
    var closePanel = function () { vp.hidden = true; if (phase !== 'rec') phase = 'idle'; try { vAudio.pause(); } catch (e) {} };
    var voiceError = function () { cleanup(); phase = 'error'; vTitle.textContent = V.errorTitle; showPanel('error'); };
    var setErr = function (m) { vErr.textContent = m || ''; vErr.hidden = !m; };

    var startVoice = function () {
      if (phase === 'rec') return;
      cleanup();
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) { voiceError(); return; }
      navigator.mediaDevices.getUserMedia({ audio: { channelCount: 1, echoCancellation: true, noiseSuppression: true, autoGainControl: true } }).then(function (s) {
        stream = s;
        vp.hidden = true;
        if (exitBox) exitBox.hidden = true;
        chunks = []; canceled = false; vtext = ''; vsum = ''; blob = null;
        var types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
        var type = types.filter(function (t) { return MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t); })[0];
        // Qualité « voix » (32 kbit/s, comme un vocal de messagerie) : ~240 Ko par minute au lieu de ~900 Ko,
        // la parole reste parfaitement nette et le fichier tient sans peine en pièce jointe.
        var opts = { audioBitsPerSecond: 32000 };
        if (type) opts.mimeType = type;
        try { mr = new MediaRecorder(stream, opts); } catch (e) {
          try { mr = new MediaRecorder(stream, type ? { mimeType: type } : undefined); } catch (e2) { voiceError(); return; }
        }
        mr.ondataavailable = function (ev) { if (ev.data && ev.data.size) chunks.push(ev.data); };
        mr.onstop = function () { if (canceled) return; blob = new Blob(chunks, { type: (mr.mimeType || type || 'audio/webm').split(';')[0] }); review(); };
        mr.start(250);
        // Ondes : niveau du micro en direct
        var AC = window.AudioContext || window.webkitAudioContext;
        if (AC) {
          try {
            actx = new AC();
            var an = actx.createAnalyser(); an.fftSize = 256;
            actx.createMediaStreamSource(stream).connect(an);
            var buf = new Uint8Array(an.frequencyBinCount), hist = [], lastT = 0;
            for (var i = 0; i < bars.length; i++) hist.push(0);
            var loop = function (t) {
              an.getByteFrequencyData(buf);
              if (t - lastT > 70) {
                lastT = t;
                var sum = 0; for (var k = 2; k < 60; k++) sum += buf[k];
                hist.shift(); hist.push(Math.min(1, sum / 58 / 140));
                bars.forEach(function (b, j) { b.style.height = Math.max(4, hist[j] * 34) + 'px'; });
              }
              raf = requestAnimationFrame(loop);
            };
            raf = requestAnimationFrame(loop);
          } catch (e) {}
        }
        // Transcription en direct par le navigateur (Chrome, Edge, Safari)
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (SR) {
          try {
            sr = new SR(); sr.lang = 'fr-FR'; sr.continuous = true; sr.interimResults = true;
            sr.onresult = function (ev) { var t = ''; for (var i = 0; i < ev.results.length; i++) t += ev.results[i][0].transcript + ' '; vtext = t.trim(); };
            sr.onend = function () { if (phase === 'rec' && sr) { try { sr.start(); } catch (e) {} } };
            sr.start();
          } catch (e) { sr = null; }
        }
        phase = 'rec'; secs = 0; updTime();
        recBar.hidden = false; cta.classList.add('is-rec'); cta.classList.remove('is-hidden');
        ti = setInterval(function () { secs++; updTime(); if (secs >= (V.max || 120)) stopVoice(); }, 1000);
      }, function () { voiceError(); });
    };
    var stopVoice = function () { if (phase !== 'rec') return; phase = 'stopping'; var r = mr; cleanup(); try { r.stop(); } catch (e) { voiceError(); } };
    var cancelVoice = function () { canceled = true; var r = mr; cleanup(); try { if (r && r.state !== 'inactive') r.stop(); } catch (e) {} phase = 'idle'; };
    var review = function () {
      phase = 'review';
      vTitle.textContent = V.title;
      if (blobUrl) URL.revokeObjectURL(blobUrl);
      blobUrl = URL.createObjectURL(blob); vAudio.src = blobUrl;
      vTr.textContent = vtext || V.noTranscript; vTr.classList.toggle('is-empty', !vtext);
      vSum.hidden = true; setErr('');
      showPanel('review');
      if (vtext && vtext.split(/\s+/).length >= 6) {
        vSum.hidden = false; vSumTxt.textContent = 'Résumé en cours…';
        post('/api/vocal/resume', { transcript: vtext, token: CFG.token, pf: proof(CFG.token) }).then(function (r) {
          vsum = (r && r.summary) || '';
          if (vsum) vSumTxt.textContent = vsum; else vSum.hidden = true;
        }, function () { vSum.hidden = true; });
      }
      setTimeout(function () { if (!isMobile()) vNom.focus({ preventScroll: true }); }, 60);
    };
    vSend.addEventListener('click', function () {
      if (!blob || vSend.classList.contains('is-loading')) return;
      var tel = vTel.value.trim();
      vTel.classList.remove('is-err');
      if (!/^\+?[0-9 .\-()]{8,22}$/.test(tel) || tel.replace(/\D/g, '').length < 9) { vTel.classList.add('is-err'); vTel.focus(); setErr('Laissez votre numéro de téléphone pour que nous puissions vous rappeler.'); return; }
      setErr('');
      var ext = /mp4|m4a/.test(blob.type) ? 'm4a' : (/ogg/.test(blob.type) ? 'ogg' : 'webm');
      var fd = new FormData();
      fd.append('audio', blob, 'vocal.' + ext);
      fd.append('nom', vNom.value.trim()); fd.append('tel', tel);
      fd.append('transcript', vtext); fd.append('summary', vsum); fd.append('duration', fmtTime(secs));
      fd.append('token', CFG.token); fd.append('pf', proof(CFG.token)); fd.append('page', location.pathname);
      vSend.classList.add('is-loading');
      fetch('/api/vocal', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
        .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Envoi impossible, réessayez.' }; }); })
        .then(function (res) {
          vSend.classList.remove('is-loading');
          if (!res || !res.ok) { setErr((res && res.error) || 'Envoi impossible, réessayez.'); return; }
          phase = 'sent';
          var n = vNom.value.trim();
          vTitle.textContent = (V.sentTitle || 'Message reçu{name} !').replace('{name}', n ? ', ' + n.split(' ')[0] : '');
          showPanel('sent');
          try { vAudio.pause(); } catch (e) {}
        }, function () { vSend.classList.remove('is-loading'); setErr('Connexion impossible, réessayez.'); });
    });
    document.addEventListener('click', function (e) {
      var t = e.target.closest && e.target.closest('[data-voice-start],[data-voice-stop],[data-voice-cancel],[data-voice-close]');
      if (!t) return;
      e.preventDefault();
      if (t.hasAttribute('data-voice-start')) startVoice();
      else if (t.hasAttribute('data-voice-stop')) stopVoice();
      else if (t.hasAttribute('data-voice-cancel')) cancelVoice();
      else closePanel();
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { if (phase === 'rec') cancelVoice(); else if (!vp.hidden) closePanel(); } });
  }

  /* ---------------------------------------------------------------- Curseur personnalisé (souris uniquement) */
  var cur = $('#rv-cursor');
  if (cur && CFG.cursor && !reduced && window.matchMedia && window.matchMedia('(pointer:fine)').matches) {
    var cx = -100, cy = -100, mx = -100, my = -100, cs = 1, hot = false;
    window.addEventListener('mousemove', function (e) {
      mx = e.clientX; my = e.clientY;
      var t = e.target;
      hot = !!(t && t.nodeType === 1 && getComputedStyle(t).cursor === 'pointer');
      cur.style.opacity = '1';
    }, { passive: true });
    document.addEventListener('mouseleave', function () { cur.style.opacity = '0'; });
    (function loop() {
      cx += (mx - cx) * 0.24; cy += (my - cy) * 0.24;
      cs += ((hot ? 2.7 : 1) - cs) * 0.2;
      cur.style.transform = 'translate3d(' + (cx - 9).toFixed(1) + 'px,' + (cy - 9).toFixed(1) + 'px,0) scale(' + cs.toFixed(3) + ')';
      cur.style.background = hot ? 'rgba(255,242,0,.4)' : '#FFF200';
      cur.style.borderWidth = hot ? '1px' : '2px';
      requestAnimationFrame(loop);
    })();
  } else if (cur) { cur.remove(); }

  /* ---------------------------------------------------------------- Mosaïque (masonry) : page Avis */
  function masonry(g) {
    var items = Array.prototype.slice.call(g.children);
    // Hauteur réelle de chaque carte (la transformation d'apparition ne compte pas) → lignes de 1 px
    items.forEach(function (it) { it.style.gridRowEnd = 'auto'; });
    var hs = items.map(function (it) { return it.offsetHeight + (parseFloat(getComputedStyle(it).marginBottom) || 0); });
    items.forEach(function (it, i) { it.style.gridRowEnd = 'span ' + Math.max(1, Math.ceil(hs[i])); });
  }
  function masonryAll() {
    var grids = $$('[data-masonry]');
    if (!grids.length) return;
    var run = function () { grids.forEach(masonry); };
    run();
    var t = null;
    window.addEventListener('resize', function () { clearTimeout(t); t = setTimeout(run, 120); });
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(run);
    window.addEventListener('load', run);
  }

  /* ---------------------------------------------------------------- Démarrage */
  window.__rvT0 = Date.now();
  window.addEventListener('resize', measureHero);
  if (window.visualViewport) window.visualViewport.addEventListener('resize', measureHero);
  measureHero(); setTimeout(measureHero, 300);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(measureHero);
  silence();
  onScroll();
  wireReveal(document);
  masonryAll();
  
})();
