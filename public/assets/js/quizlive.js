/* Quiz du club-house : page du joueur. Rejoindre (code + pseudo), puis interroger l'état chaque
   seconde et répondre avec les 4 boutons. Jeton du joueur gardé sur l'appareil (rechargement). */
(function () {
  'use strict';
  var root = document.querySelector('[data-ql]');
  if (!root) return;
  var T = JSON.parse(document.getElementById('ql-i18n').textContent);
  var lang = root.getAttribute('data-lang') || 'fr';
  var P = lang === 'en' ? '/en' : '';
  var $ = function (s) { return root.querySelector(s); };
  var form = $('[data-ql-form]'), err = $('[data-ql-err]');
  if (!form) return;
  var guest = false;
  var gbtn = $('[data-ql-guest]');
  if (gbtn) gbtn.addEventListener('click', function () {
    guest = true;
    $('[data-ql-me]').hidden = true;
    $('[data-ql-namebox]').hidden = false;
    form.name.required = true;
    form.name.focus();
  });
  var me = null, code = '', last = null, timer = null, poll = null, clockAt = 0, leftAt = 0, busy = false;
  var SHAPES = ['▲', '◆', '●', '■'];

  function fmt(s, v) { return String(s).replace(/\{(\w+)\}/g, function (_, k) { return v[k] !== undefined ? v[k] : ''; }); }
  function store(k, v) { try { if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
  function load(k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } }

  function api(body) {
    body.lang = lang;
    body.code = code;
    if (me) { body.pid = me.pid; body.tok = me.tok; }
    return fetch(P + '/api/quiz-live', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), credentials: 'same-origin' })
      .then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
  }

  function show(step) {
    root.querySelectorAll('[data-step]').forEach(function (el) { el.hidden = el.getAttribute('data-step') !== step; });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    err.hidden = true;
    code = form.code.value.replace(/\D/g, '');
    var btn = form.querySelector('button');
    btn.disabled = true;
    api({ action: 'rejoindre', name: form.name.value, email: form.email ? form.email.value : '', guest: guest }).then(function (j) {
      btn.disabled = false;
      if (!j.ok) { err.textContent = j.error || T.error; err.hidden = false; return; }
      me = { pid: j.pid, tok: j.tok };
      store('ql:' + code, me);
      if (form.name.value) store('ql:name', form.name.value);
      start(j);
      if (j.mailed && !j.member) info(T.pending);
      else if (j.mailed) info(T.mailed);
    }).catch(function () { btn.disabled = false; err.textContent = T.error; err.hidden = false; });
  });

  function start(state) {
    show('play');
    root.scrollIntoView({ block: 'start' });
    render(state);
    schedule();
  }

  // Interrogation : chaque seconde pendant une question, toutes les 2 à 2,5 s sinon (salle
  // d'attente, réponse, classement) ; plus rien à la fin. Un peu de hasard évite que 200
  // téléphones frappent à la même milliseconde. Mesuré : 200 joueurs, 4 à 8 ms par appel.
  function delay() {
    var ph = last ? last.phase : 'lobby';
    var base = ph === 'question' ? 1000 : (ph === 'lobby' ? 2500 : 2000);
    return base + Math.random() * 600 - 150;
  }
  function schedule() {
    clearTimeout(poll);
    if (!me || (last && (last.phase === 'end' || last.phase === 'gone'))) return;
    poll = setTimeout(tick, delay());
  }
  function tick() {
    if (busy) { schedule(); return; }
    busy = true;
    api({ action: 'etat' }).then(function (j) { busy = false; render(j); schedule(); })
      .catch(function () { busy = false; $('[data-ql-msg]').textContent = T.error; schedule(); });
  }

  function clock() {
    var t = $('[data-ql-timer]');
    var left = Math.max(0, leftAt - (Date.now() - clockAt));
    t.firstElementChild.style.width = (100 * left / (1000 * (last.duration || 20))) + '%';
  }

  function info(text) {
    var p = $('[data-ql-info]');
    p.textContent = text || '';
    p.hidden = !text;
  }

  function render(s) {
    if (!s || s.phase === 'gone') {
      clearTimeout(poll);
      store('ql:' + code, null);
      me = null;
      show('join');
      err.textContent = T.gone;
      err.hidden = false;
      return;
    }
    last = s;
    $('[data-ql-name]').textContent = (s.member ? '★ ' : '') + (s.name || '');
    if (s.member && $('[data-ql-info]').textContent === T.pending) info(''); // lien ouvert : la partie compte
    if (s.pending && !$('[data-ql-info]').textContent) info(T.pending);
    $('[data-ql-score]').textContent = fmt(T.score, { n: s.score || 0 });
    var num = $('[data-ql-num]'), q = $('[data-ql-q]'), box = $('[data-ql-answers]'), msg = $('[data-ql-msg]'), tm = $('[data-ql-timer]');
    num.textContent = s.idx >= 0 && s.phase !== 'lobby' && s.phase !== 'end' ? fmt(T.qn, { i: s.idx + 1, n: s.n }) : '';
    var rank = s.rankLabel || '';
    tm.hidden = true;
    clearInterval(timer);
    if (s.phase === 'lobby') {
      q.textContent = T.lobby;
      box.innerHTML = '';
      box.removeAttribute('data-idx');
      msg.textContent = T.wait;
      return;
    }
    if (s.phase === 'question' && s.q) {
      q.textContent = s.q.q;
      if (box.getAttribute('data-idx') !== String(s.idx) || !box.querySelector('button')) {
        box.innerHTML = '';
        box.setAttribute('data-idx', String(s.idx));
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
      var waiting = s.wait > 0;
      var done = s.answered !== null && s.answered !== undefined;
      box.querySelectorAll('button').forEach(function (b, i) {
        b.disabled = waiting || done;
        b.classList.toggle('is-picked', done && i === s.answered);
        b.classList.toggle('is-off', done && i !== s.answered);
      });
      msg.textContent = waiting ? T.ready : (done ? T.sent : '');
      if (!waiting && !done) {
        tm.hidden = false;
        clockAt = Date.now();
        leftAt = s.left;
        clock();
        timer = setInterval(clock, 100);
      }
      return;
    }
    if (s.phase === 'reveal' && s.q) {
      q.textContent = s.q.q;
      if (box.getAttribute('data-idx') !== String(s.idx)) {
        box.innerHTML = '';
        box.setAttribute('data-idx', String(s.idx));
        s.q.a.forEach(function (a, i) {
          var d = document.createElement('div');
          d.className = 'ql__a ql__a--' + i;
          d.innerHTML = '<span aria-hidden="true">' + SHAPES[i] + '</span>';
          d.appendChild(document.createTextNode(a));
          box.appendChild(d);
        });
      }
      Array.prototype.forEach.call(box.children, function (b, i) {
        if (b.tagName === 'BUTTON') b.disabled = true;
        b.classList.toggle('is-good', i === s.c);
        b.classList.toggle('is-off', i !== s.c);
        b.classList.toggle('is-picked', i === s.answered);
      });
      var verdict = s.answered === null || s.answered === undefined ? T.none : (s.answered === s.c ? T.good : T.bad);
      msg.innerHTML = '';
      var v = document.createElement('b');
      v.className = s.answered === s.c ? 'is-good' : 'is-bad';
      v.textContent = verdict + (s.gain ? ' ' + fmt(T.pts, { n: s.gain }) : '');
      msg.appendChild(v);
      msg.appendChild(document.createTextNode(rank ? ' · ' + rank : ''));
      return;
    }
    box.innerHTML = '';
    box.removeAttribute('data-idx');
    if (s.phase === 'board') {
      q.textContent = T.board;
      msg.textContent = rank;
    } else if (s.phase === 'end') {
      clearTimeout(poll);
      q.textContent = T.end;
      msg.innerHTML = '';
      var r = document.createElement('b');
      r.className = 'ql__final';
      r.textContent = rank;
      msg.appendChild(r);
      msg.appendChild(document.createTextNode(fmt(T.score, { n: s.score || 0 })));
      if (s.champ) {
        var c = document.createElement('b');
        c.className = 'ql__final ql__final--champ';
        c.textContent = '★ ' + s.champ.label;
        msg.appendChild(c);
        info('');
      } else if (!s.member) {
        info(T.guestEnd);
      }
      $('[data-ql-champ]').hidden = false;
      store('ql:' + code, null);
    }
  }

  function answer(i) {
    box().querySelectorAll('button').forEach(function (b, k) { b.disabled = true; b.classList.toggle('is-picked', k === i); b.classList.toggle('is-off', k !== i); });
    if (navigator.vibrate) navigator.vibrate(30);
    api({ action: 'repondre', choice: i }).then(function (j) {
      if (j.error) $('[data-ql-msg]').textContent = j.error;
      render(j);
    }).catch(function () {});
  }
  function box() { return $('[data-ql-answers]'); }

  // Reprise après rechargement (même code dans l'adresse, jeton gardé sur l'appareil).
  var name = load('ql:name');
  if (name && !form.name.value) form.name.value = name;
  code = form.code.value.replace(/\D/g, '');
  var saved = code ? load('ql:' + code) : null;
  if (saved) {
    me = saved;
    api({ action: 'etat' }).then(function (j) { if (j.ok) start(j); else { me = null; store('ql:' + code, null); } }).catch(function () {});
  } else if (code) {
    form.name.focus();
  }
})();
