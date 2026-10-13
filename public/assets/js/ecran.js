/* Mode écran du musée (/ecran/<playlist>) : scène fixe mise à l'échelle de l'écran, slides
   enchaînées (fondu, barre de progression), quiz (compte à rebours puis réponse), comptes à
   rebours, horloge ; rechargement toutes les X minutes seulement si le musée répond (sinon la
   page continue de tourner hors connexion) ; service worker /ecran/sw.js pour repartir sans
   réseau. Ni son, ni cookie, ni donnée personnelle. ?slide=N fige la slide N (aperçus),
   ?tour=K en montre la variante du tour K et ?reponse=1 montre la réponse d'un quiz.
   Variantes : les sections d'un même data-group sont les contenus d'une slide pour des tours
   successifs ; à chaque nouveau tour de boucle, la variante suivante. Leurs images (data-src)
   ne sont chargées qu'au tour d'avant (préchargement de la seule variante suivante). */
(function () {
  'use strict';
  var stage = document.querySelector('.ec-stage');
  var all = [].slice.call(document.querySelectorAll('.ec-slide'));
  // Groupes : une entrée par slide de la playlist, avec ses variantes (une par tour).
  var groups = [], byKey = {};
  all.forEach(function (el) {
    var g = el.getAttribute('data-group');
    if (g === null) { groups.push([el]); return; }
    if (!byKey[g]) { byKey[g] = []; groups.push(byKey[g]); }
    byKey[g].push(el);
  });
  var slides = groups;
  var turn = Math.max(0, parseInt(q0('tour') || '1', 10) - 1);
  function q0(k) { return new URLSearchParams(location.search).get(k); }
  function load(el) {
    if (!el) return;
    [].slice.call(el.querySelectorAll('img[data-src]')).forEach(function (im) { im.src = im.getAttribute('data-src'); im.removeAttribute('data-src'); });
  }
  function pick(g, t) { return g[t % g.length]; }
  var bar = document.querySelector('.ec-progress i');
  var q = new URLSearchParams(location.search);
  var freeze = parseInt(q.get('slide') || '0', 10);
  var cur = -1, timer = null, tick = null, wantReload = false;

  function fit() {
    var w = +stage.getAttribute('data-w'), h = +stage.getAttribute('data-h');
    var k = Math.min(innerWidth / w, innerHeight / h);
    stage.style.transform = 'translate(' + (innerWidth - w * k) / 2 + 'px,' + (innerHeight - h * k) / 2 + 'px) scale(' + k + ')';
  }
  addEventListener('resize', fit);
  fit();

  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function countdowns(root) {
    var els = [].slice.call(root.querySelectorAll('[data-countdown]'));
    if (root.hasAttribute('data-countdown')) els.push(root);
    els.forEach(function (el) {
      var left = Math.max(0, +el.getAttribute('data-countdown') * 1000 - Date.now());
      var set = function (k, v) { var b = el.querySelector('[data-cd="' + k + '"]'); if (b) b.textContent = v; };
      set('j', Math.floor(left / 864e5));
      set('h', pad(Math.floor(left / 36e5) % 24));
      set('m', pad(Math.floor(left / 6e4) % 60));
    });
  }

  function show(i) {
    if (i === 0 && cur >= 0 && !freeze) turn++;
    if (wantReload && i === 0 && slides.length > 1) { location.reload(); return; }
    var s = pick(slides[i], turn);
    load(s);
    // Précharge la variante du tour suivant de cette slide (une seule image à la fois).
    if (slides[i].length > 1) load(pick(slides[i], turn + 1));
    all.forEach(function (x) { x.classList.toggle('is-on', x === s); x.classList.remove('is-answer'); });
    if (document.body) document.body.setAttribute('data-turn', String(turn + 1));
    cur = i;
    var dur = Math.max(2, +s.getAttribute('data-duration')) * 1000;
    clearInterval(tick);
    var t0 = Date.now();
    var ans = s.hasAttribute('data-answer') ? dur - (+s.getAttribute('data-answer')) * 1000 : 0;
    var tEl = s.querySelector('[data-timer]');
    var step = function () {
      var el = Date.now() - t0;
      if (bar) bar.style.width = Math.min(100, 100 * el / dur) + '%';
      if (ans) {
        if (el >= ans || q.get('reponse') === '1') s.classList.add('is-answer');
        if (tEl) tEl.textContent = Math.max(0, Math.ceil((ans - el) / 1000));
      }
      countdowns(s);
    };
    step();
    if (freeze) { if (bar) bar.style.width = '35%'; return; }
    tick = setInterval(step, 250);
    clearTimeout(timer);
    timer = setTimeout(function () { show((cur + 1) % slides.length); }, dur);
  }

  // Horloge facultative
  var clock = document.querySelector('[data-clock]');
  if (clock) setInterval(function () { var d = new Date(); clock.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes()); }, 5000);

  // Rechargement : seulement si le musée répond ; appliqué au début de la boucle suivante.
  var every = Math.max(5, +document.body.getAttribute('data-reload') || 30) * 60000;
  function check() {
    if (!window.fetch) return;
    fetch(location.href, { cache: 'no-store', credentials: 'same-origin' }).then(function (r) {
      if (r.ok) { wantReload = true; if (slides.length <= 1) location.reload(); }
    }).catch(function () { setTimeout(check, 60000); });
  }
  if (!freeze) setTimeout(function loop() { check(); setTimeout(loop, every); }, every);

  // Service worker de l'écran : dernière version gardée pour repartir sans réseau.
  if ('serviceWorker' in navigator && document.body.getAttribute('data-sw') && !freeze) {
    navigator.serviceWorker.register(document.body.getAttribute('data-sw'), { scope: '/ecran/' }).catch(function () {});
  }
  // L'écran reste allumé (si le navigateur le permet).
  if (navigator.wakeLock && !freeze) {
    var lock = function () { navigator.wakeLock.request('screen').catch(function () {}); };
    lock();
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') lock(); });
  }

  if (slides.length) show(freeze ? Math.min(slides.length, freeze) - 1 : 0);
})();
