/* Quiz du club-house : grand écran de l'animateur. Interroge l'état chaque seconde, affiche la
   salle d'attente, les questions (compte à rebours), la réponse, le classement et le podium.
   Espace, Entrée, flèche droite ou le bouton : étape suivante. Clic sur un pseudo : le retirer. */
(function () {
  'use strict';
  var D = JSON.parse(document.getElementById('qls-data').textContent);
  var T = D.t;
  var P = D.lang === 'en' ? '/en' : '';
  var $ = function (s) { return document.querySelector(s); };
  var SHAPES = ['▲', '◆', '●', '■'];
  var last = null, busy = false, clockAt = 0, leftAt = 0, waitAt = 0, tm = null, shownIdx = null;
  var nextBtn = $('[data-qls-next]');

  function fmt(s, v) { return String(s).replace(/\{(\w+)\}/g, function (_, k) { return v[k] !== undefined ? v[k] : ''; }); }
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text !== undefined) e.textContent = text; return e; }

  function api(body) {
    body.code = D.code; body.key = D.key; body.lang = D.lang;
    return fetch(P + '/api/quiz-live', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function view(name) {
    document.querySelectorAll('[data-qls-view]').forEach(function (v) { v.hidden = v.getAttribute('data-qls-view') !== name; });
  }

  function tick() {
    if (busy) return;
    busy = true;
    api({ action: 'ecran' }).then(function (s) { busy = false; if (s.ok) render(s); }).catch(function () { busy = false; });
  }

  function next() {
    nextBtn.disabled = true;
    api({ action: 'suivant' }).then(function (s) { nextBtn.disabled = false; if (s.ok) render(s); }).catch(function () { nextBtn.disabled = false; });
  }

  function clock() {
    var c = $('[data-qls-clock]');
    var now = Date.now() - clockAt;
    var wait = Math.max(0, waitAt - now);
    if (wait > 0) { c.textContent = ''; c.className = 'qls__clock is-wait'; return; }
    var left = Math.max(0, leftAt - now);
    c.textContent = Math.ceil(left / 1000);
    c.className = 'qls__clock' + (left < 5000 ? ' is-hot' : '');
    c.style.setProperty('--p', (left / (1000 * last.duration)).toFixed(3));
  }

  function render(s) {
    // Classement et podium : construits une fois (leurs animations ne repartent pas à chaque seconde).
    var same = last && last.phase === s.phase && last.idx === s.idx && (s.phase === 'board' || s.phase === 'end');
    last = s;
    if (same) return;
    $('[data-qls-progress]').textContent = s.phase === 'lobby' ? fmt(s.players === 1 ? T.player : T.players, { n: s.players }) : (s.idx >= 0 ? fmt(T.qn, { i: s.idx + 1, n: s.n }) + ' · ' + fmt(s.players === 1 ? T.player : T.players, { n: s.players }) : '');
    clearInterval(tm);
    if (s.phase === 'lobby') {
      view('lobby');
      $('[data-qls-count]').textContent = s.players ? fmt(s.players === 1 ? T.player : T.players, { n: s.players }) : T.none;
      var ul = $('[data-qls-names]');
      var have = Array.prototype.map.call(ul.children, function (li) { return li.getAttribute('data-id'); }).join(',');
      if (have !== (s.names || []).map(function (n) { return n.id; }).join(',')) {
        ul.innerHTML = '';
        (s.names || []).forEach(function (n) {
          var li = el('li', null, n.name);
          li.setAttribute('data-id', n.id);
          li.title = fmt(T.kick, { p: n.name });
          li.addEventListener('click', function (e) {
            e.stopPropagation();
            if (confirm(fmt(T.kick, { p: n.name }))) api({ action: 'retirer', pid: n.id }).then(function (r) { if (r.ok) render(r); });
          });
          ul.appendChild(li);
        });
      }
      nextBtn.textContent = T.start;
      nextBtn.disabled = !s.players;
      return;
    }
    nextBtn.disabled = false;
    if ((s.phase === 'question' || s.phase === 'reveal') && s.q) {
      view('question');
      var ol = $('[data-qls-answers]');
      if (shownIdx !== s.idx) {
        shownIdx = s.idx;
        $('[data-qls-kind]').textContent = T.kinds[s.q.kind] || '';
        $('[data-qls-q]').textContent = s.q.q;
        $('[data-qls-q]').classList.toggle('is-long', s.q.q.length > 95);
        ol.innerHTML = '';
        s.q.a.forEach(function (a, i) {
          var li = el('li', 'qls__a qls__a--' + i);
          li.appendChild(el('span', 'qls__shape', SHAPES[i])).setAttribute('aria-hidden', 'true');
          li.appendChild(el('span', 'qls__txt', a));
          li.appendChild(el('span', 'qls__n'));
          li.appendChild(el('span', 'qls__bar'));
          ol.appendChild(li);
        });
        $('[data-qls-fact]').hidden = true;
      }
      $('[data-qls-answered]').textContent = fmt(T.answered, { a: s.answered || 0, n: s.players });
      if (s.phase === 'question') {
        ol.classList.remove('is-reveal');
        clockAt = Date.now(); leftAt = s.left; waitAt = s.wait;
        clock();
        tm = setInterval(clock, 200);
        nextBtn.textContent = T.close;
      } else {
        var c = $('[data-qls-clock]');
        c.textContent = '';
        c.className = 'qls__clock is-done';
        ol.classList.add('is-reveal');
        var max = Math.max.apply(null, s.q.counts.concat([1]));
        Array.prototype.forEach.call(ol.children, function (li, i) {
          li.classList.toggle('is-good', i === s.q.c);
          li.querySelector('.qls__n').textContent = s.q.counts[i] || 0;
          li.querySelector('.qls__bar').style.width = (100 * (s.q.counts[i] || 0) / max) + '%';
        });
        var f = $('[data-qls-fact]');
        f.textContent = s.q.fact || '';
        f.hidden = !s.q.fact;
        nextBtn.textContent = s.idx >= s.n - 1 ? T.podium : T.board;
      }
      return;
    }
    if (s.phase === 'board') {
      view('board');
      $('[data-qls-board-t]').textContent = fmt(T.top, { i: s.idx + 1 });
      ranking($('[data-qls-rank]'), s.top || []);
      nextBtn.textContent = T.next;
      return;
    }
    if (s.phase === 'end') {
      view('end');
      var top = s.top || [];
      var pod = $('[data-qls-podium]');
      pod.innerHTML = '';
      [1, 0, 2].forEach(function (k) {
        var p = top[k];
        if (!p) return;
        var b = el('div', 'qls__step qls__step--' + (k + 1));
        b.appendChild(el('b', 'qls__pname', p.name));
        b.appendChild(el('span', 'qls__pscore', fmt(T.pts, { n: p.score })));
        b.appendChild(el('span', 'qls__prank', String(p.rank)));
        pod.appendChild(b);
      });
      ranking($('[data-qls-rest]'), top.slice(3));
      nextBtn.hidden = true;
      $('.qls__hint').hidden = true;
      clearInterval(poller);
    }
  }

  function ranking(ol, rows) {
    ol.innerHTML = '';
    rows.forEach(function (r) {
      var li = el('li');
      li.appendChild(el('span', 'qls__r', String(r.rank)));
      li.appendChild(el('b', null, r.name));
      if (r.gain) li.appendChild(el('span', 'qls__gain', '+' + r.gain));
      li.appendChild(el('span', 'qls__s', fmt(T.pts, { n: r.score })));
      ol.appendChild(li);
    });
  }

  nextBtn.addEventListener('click', function (e) { e.stopPropagation(); next(); });
  document.addEventListener('keydown', function (e) {
    if ((e.key === ' ' || e.key === 'Enter' || e.key === 'ArrowRight') && !nextBtn.disabled && !nextBtn.hidden) { e.preventDefault(); next(); }
  });
  $('[data-qls-fs]').addEventListener('click', function (e) {
    e.stopPropagation();
    if (document.fullscreenElement) document.exitFullscreen(); else if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen();
  });
  tick();
  var poller = setInterval(tick, 1000);
})();
