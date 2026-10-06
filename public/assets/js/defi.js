/* Défi du jour : 10 questions en solo. Le serveur garde la partie (temps, points, bonne réponse
   donnée seulement après la réponse) ; ici, on affiche et on envoie. Invité : jeton sur l'appareil. */
(function () {
  'use strict';
  var root = document.querySelector('[data-df]');
  if (!root) return;
  var T = JSON.parse(document.getElementById('df-i18n').textContent);
  var lang = root.getAttribute('data-lang') || 'fr';
  var P = lang === 'en' ? '/en' : '';
  var $ = function (s) { return root.querySelector(s); };
  var SHAPES = ['▲', '◆', '●', '■'];
  var tok = null, tm = null, state = null, busy = false;

  function fmt(s, v) { return String(s).replace(/\{(\w+)\}/g, function (_, k) { return v[k] !== undefined ? v[k] : ''; }); }
  function num(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, lang === 'en' ? ',' : ' '); }
  function show(step) { root.querySelectorAll('[data-df-step]').forEach(function (el) { el.hidden = el.getAttribute('data-df-step') !== step; }); }
  function guestTok() {
    var k = 'df:tok';
    try { tok = localStorage.getItem(k); } catch (e) {}
    if (!tok || !/^[a-f0-9]{32}$/.test(tok)) {
      var a = new Uint8Array(16); crypto.getRandomValues(a);
      tok = Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
      try { localStorage.setItem(k, tok); } catch (e) {}
    }
  }
  function api(body) {
    body.lang = lang;
    body.date = root.getAttribute('data-date') || '';
    if (tok) body.tok = tok;
    return fetch(P + '/api/defi', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'same-origin' })
      .then(function (r) { return r.json(); });
  }

  function render(s) {
    state = s;
    clearInterval(tm);
    if (!s || s.phase === 'intro') { show('intro'); return; }
    if (s.phase === 'end') { end(s); return; }
    show('play');
    $('[data-df-num]').textContent = fmt(T.qn, { i: s.i + 1, n: s.n }) + ' · ' + fmt(T.score, { n: num(s.score) });
    $('[data-df-q]').textContent = s.q.q;
    var box = $('[data-df-answers]');
    if (box.getAttribute('data-i') !== String(s.i)) {
      box.setAttribute('data-i', String(s.i));
      box.innerHTML = '';
      s.q.a.forEach(function (a, i) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'ql__a ql__a--' + i;
        b.innerHTML = '<span aria-hidden="true">' + SHAPES[i] + '</span>';
        b.appendChild(document.createTextNode(a));
        b.addEventListener('click', function () { answer(i); });
        box.appendChild(b);
      });
    }
    var v = $('[data-df-verdict]'), t = $('[data-df-timer]');
    if (s.phase === 'question') {
      v.hidden = true;
      t.hidden = false;
      var at = Date.now(), left = s.left;
      var bar = t.firstElementChild;
      var tick = function () {
        var l = Math.max(0, left - (Date.now() - at));
        bar.style.width = (100 * l / (1000 * s.duration)) + '%';
        if (l <= 0) { clearInterval(tm); answer(-1); }
      };
      tick();
      tm = setInterval(tick, 100);
      box.querySelectorAll('button').forEach(function (b) { b.disabled = false; b.className = b.className.replace(/ is-\w+/g, ''); });
      return;
    }
    // Verdict
    t.hidden = true;
    box.querySelectorAll('button').forEach(function (b, i) {
      b.disabled = true;
      b.classList.toggle('is-good', i === s.c);
      b.classList.toggle('is-off', i !== s.c);
      b.classList.toggle('is-picked', i === s.choice);
    });
    var m = $('[data-df-msg]');
    m.innerHTML = '';
    var w = document.createElement('b');
    w.className = s.pts > 0 ? 'is-good' : 'is-bad';
    w.textContent = s.pts > 0 ? T.good + ' ' + fmt(T.pts, { n: num(s.pts) }) : (s.choice < 0 ? T.late : T.bad);
    m.appendChild(w);
    $('[data-df-fact]').textContent = s.fact || '';
    var nb = $('[data-df-next]');
    nb.textContent = s.last ? T.end : T.next;
    v.hidden = false;
    nb.focus();
  }

  function end(s) {
    show('end');
    $('[data-df-score]').textContent = fmt(T.score, { n: num(s.score) });
    $('[data-df-good]').textContent = fmt(T.goodOf, { g: s.good, n: s.n });
    var g = $('[data-df-grid]');
    g.innerHTML = '';
    s.grid.forEach(function (x) { var sp = document.createElement('span'); if (x) sp.className = 'is-good'; g.appendChild(sp); });
    var lbl = [s.rankLabel, s.streakLabel].filter(Boolean).join(' · ');
    if (lbl || root.getAttribute('data-member') !== '1') $('[data-df-rank]').textContent = root.getAttribute('data-member') === '1' ? lbl : T.guest;
    var sh = $('[data-df-share]');
    sh.setAttribute('data-grid', s.grid.join(''));
    sh.setAttribute('data-pts', s.score);
  }

  function answer(i) {
    if (busy || !state || state.phase !== 'question') return;
    busy = true;
    clearInterval(tm);
    if (navigator.vibrate) navigator.vibrate(25);
    api({ action: 'repondre', choice: i }).then(function (s) { busy = false; render(s); }).catch(function () {
      // Réseau coupé : on relit la partie (le serveur garde le temps), la question reprend où elle en est.
      setTimeout(function () { api({ action: 'etat' }).then(function (s) { busy = false; if (s.ok) render(s); }).catch(function () { busy = false; }); }, 1000);
    });
  }

  function start() {
    api({ action: 'commencer' }).then(function (s) {
      if (s.error) { var e = $('[data-df-err]'); if (e) { e.textContent = s.error; e.hidden = false; } return; }
      render(s);
    }).catch(function () {});
  }

  $('[data-df-next]').addEventListener('click', function () {
    var b = this;
    b.disabled = true;
    api({ action: 'suivante' }).then(function (s) { b.disabled = false; render(s); }).catch(function () { b.disabled = false; });
  });
  var sb = $('[data-df-start]');
  if (sb) sb.addEventListener('click', start);
  var gb = $('[data-df-guest]');
  if (gb) gb.addEventListener('click', function () { guestTok(); start(); });
  var form = $('[data-df-form]');
  if (form) form.addEventListener('submit', function (e) {
    e.preventDefault();
    var err = $('[data-df-err]');
    err.hidden = true;
    var btn = form.querySelector('button');
    btn.disabled = true;
    api({ action: 'inscrire', name: form.name.value, email: form.email ? form.email.value : '' }).then(function (j) {
      btn.disabled = false;
      if (!j.ok) { err.textContent = j.error || T.error; err.hidden = false; return; }
      if (!j.member) { err.textContent = T.mailed; err.hidden = false; form.reset(); return; }
      location.reload(); // compte ouvert sur ce téléphone : le défi se lance depuis la page
    }).catch(function () { btn.disabled = false; err.textContent = T.error; err.hidden = false; });
  });

  // Partager : la grille façon « mots du jour », sans dévoiler les réponses.
  $('[data-df-share]').addEventListener('click', function () {
    var grid = (this.getAttribute('data-grid') || '').split('').map(function (x) { return x === '1' ? '🟨' : '⬛'; }).join('');
    var good = (this.getAttribute('data-grid') || '').split('').filter(function (x) { return x === '1'; }).length;
    var text = fmt(T.shareText, { d: T.date }) + '\n' + grid + '\n' + fmt(T.goodOf, { g: good, n: 10 }) + ' · ' + fmt(T.score, { n: num(this.getAttribute('data-pts') || 0) }) + '\n' + T.url;
    var msg = $('[data-df-share-msg]');
    if (navigator.share) {
      navigator.share({ title: T.shareTitle, text: text }).catch(function () {});
    } else if (navigator.clipboard) {
      navigator.clipboard.writeText(text).then(function () { msg.textContent = T.copied; });
    }
  });

  // Reprise : une partie commencée (compte, ou invité de cet appareil) continue où elle en était.
  // Le jeton d'invité part aussi pour un compte : une partie jouée en invité aujourd'hui sur cet
  // appareil est reprise, hors classement.
  if (root.getAttribute('data-member') === '1') { try { tok = localStorage.getItem('df:tok'); } catch (e) {} if (tok && !/^[a-f0-9]{32}$/.test(tok)) tok = null; }
  if (root.getAttribute('data-member') === '1' && root.getAttribute('data-done') !== '1') {
    api({ action: 'etat' }).then(function (s) { if (s.ok && s.phase !== 'intro') render(s); }).catch(function () {});
  } else if (root.getAttribute('data-member') !== '1') {
    try { tok = localStorage.getItem('df:tok'); } catch (e) {}
    if (tok) api({ action: 'etat' }).then(function (s) { if (s.ok && s.phase !== 'intro') render(s); else tok = null; }).catch(function () { tok = null; });
  }
})();
