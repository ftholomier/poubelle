/* Rétro-Direct : le match rejoué minute par minute (à l'horloge du serveur, ou en accéléré). */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const motion = !matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------------------------------------------------------- programme : compte à rebours */
  const next = $('[data-rd-countdown-at]');
  if (next) {
    const at = +next.dataset.rdCountdownAt, off = +next.dataset.rdNow - Date.now() / 1000, out = $('[data-rd-countdown-text]', next);
    const lang = document.documentElement.lang || 'fr';
    const tick = () => {
      const s = Math.max(0, Math.round(at - (Date.now() / 1000 + off)));
      const d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
      out.textContent = s <= 0 ? '' : '(' + (lang === 'en' ? 'in ' : 'dans ') + (d ? d + (lang === 'en' ? ' d ' : ' j ') : '') + (d || h ? h + ' h ' : '') + m + ' min)';
    };
    tick();
    setInterval(tick, 30000);
  }

  const root = $('[data-rd]');
  if (!root) return;
  const D = JSON.parse($('[data-rd-data]', root).textContent);
  const L = D.labels;
  const fmt = (s, v) => s.replace(/\{(\w+)\}/g, (_, k) => (v[k] ?? ''));
  const ev = D.events, M = D.marks;
  const teams = [D.home, D.away];
  const sochauxSide = D.sh ? 0 : 1;
  const feed = $('[data-rd-feed]'), say = $('[data-rd-say]'), clock = $('[data-rd-clock]'), phase = $('[data-rd-phase]');
  const sc = [$('[data-rd-s="0"]', root), $('[data-rd-s="1"]', root)];
  const extra = $('[data-rd-extra]'), bar = $('[data-rd-bar]'), fill = $('[data-rd-bar-fill]');
  const wait = $('[data-rd-wait]'), after = $('[data-rd-after]'), photos = $('[data-rd-photos]');
  const t0 = performance.now(), serverAt = D.now;
  const serverNow = () => serverAt + (performance.now() - t0) / 1000;

  /* ---------------------------------------------------------- horloge du match */
  // Minute affichée et phase à $e secondes du coup d'envoi.
  function clockAt(e) {
    const min = (base, from, cap) => { const n = base + Math.floor((e - from) / 60) + 1; return n > cap ? cap + '+' + (n - cap) : String(n); };
    if (e < 0) return ['', ''];
    if (e >= M.end) return [L.end, ''];
    if (e < M.halftime) return [min(0, 0, 45) + "'", '1re'];
    if (e < M.kickoff2) return [L.ht, L.halftime];
    if (e < M.fulltime90) return [min(45, M.kickoff2, 90) + "'", '2e'];
    if (M.extratime !== null && e < M.extratime) return [L.pause, L.fulltime90];
    if (M.extratime !== null && e < M.et_end) return [min(90, M.extratime, 120) + "'", L.aet];
    if (M.pens !== null && e < M.pens) return [L.pause, L.pens];
    return [L.tabShort, L.pens];
  }
  const phaseLabel = p => (p === '1re' ? (D.lang === 'en' ? 'First half' : '1re mi-temps') : p === '2e' ? (D.lang === 'en' ? 'Second half' : '2e mi-temps') : p);

  /* ---------------------------------------------------------- fil des temps forts */
  const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };
  const link = (name, href) => { if (!href) return document.createTextNode(name); const a = el('a', null, name); a.href = href; return a; };
  function item(x, score) {
    const li = el('li', 'rdev rdev--' + x.type);
    const mn = el('span', 'rdev__min', x.min ? x.min + "'" : ({ kickoff: '0\'', halftime: L.ht, kickoff2: "46'", fulltime90: "90'", extratime: "91'", pens: L.tabShort, fulltime: L.end })[x.type] || '');
    if ((mn.textContent || '').length > 4) mn.classList.add('is-long');
    li.appendChild(mn);
    const body = el('div', 'rdev__body');
    const head = t => body.appendChild(el('span', 'rdev__head', t));
    const txt = t => { if (t) body.appendChild(el('span', 'rdev__text', t)); };
    let speak = '';
    switch (x.type) {
      case 'kickoff': head(L.kickoff); txt(teams.join(' – ')); speak = L.kickoff; break;
      case 'goal': {
        const side = x.side;
        if (side === sochauxSide) li.classList.add('is-sochaux');
        head(L.goal + ' ' + x.score[0] + '-' + x.score[1]);
        if (x.who || side !== null) body.appendChild(el('span', 'rdev__who', (x.who ? x.who : '') + (side !== null ? (x.who ? ' · ' : '') + teams[side] : '')));
        txt(x.text);
        speak = (x.who && side !== null ? fmt(L.goalFor, { who: x.who, team: teams[side] }) : side !== null ? fmt(L.goalTeam, { team: teams[side] }) : L.goal) + ', ' + x.score[0] + '-' + x.score[1];
        break;
      }
      case 'action': txt(x.text); speak = x.min + "' " + x.text; break;
      case 'sub': {
        head(L.sub);
        const p = el('span', 'rdev__text');
        if (x.out) { const parts = L.replaces.split(/(\{a\}|\{b\})/); parts.forEach(s => p.appendChild(s === '{a}' ? link(x.who, x.href) : s === '{b}' ? document.createTextNode(x.out) : document.createTextNode(s))); }
        else { const parts = L.enters.split(/(\{a\})/); parts.forEach(s => p.appendChild(s === '{a}' ? link(x.who, x.href) : document.createTextNode(s))); }
        body.appendChild(p);
        speak = p.textContent;
        break;
      }
      case 'yellow': case 'red': head(x.type === 'red' ? L.red : L.yellow); body.appendChild(el('span', 'rdev__text')).appendChild(link(x.who, x.href)); speak = (x.type === 'red' ? L.red : L.yellow) + ' : ' + x.who; break;
      case 'halftime':
        head(L.halftime + ' : ' + teams[0] + ' ' + score[0] + '-' + score[1] + ' ' + teams[1]);
        if (photos) body.appendChild(photos.content.cloneNode(true));
        speak = L.halftime + ', ' + score[0] + '-' + score[1];
        break;
      case 'kickoff2': head(L.kickoff2); speak = L.kickoff2; break;
      case 'fulltime90': head(L.fulltime90 + ' : ' + score[0] + '-' + score[1]); speak = L.fulltime90; break;
      case 'extratime': head(L.extratime); speak = L.extratime; break;
      case 'pens': head(L.pens); speak = L.pens; break;
      case 'fulltime': {
        const f = x.score || score;
        head(L.fulltime);
        body.appendChild(el('span', 'rdev__final', teams[0] + ' ' + f[0] + '-' + f[1] + ' ' + teams[1]));
        if (x.pens) txt(fmt(L.pensWin, { team: teams[x.pens[0] > x.pens[1] ? 0 : 1], a: Math.max(...x.pens), b: Math.min(...x.pens) }));
        speak = L.fulltime + ' : ' + teams[0] + ' ' + f[0] + '-' + f[1] + ' ' + teams[1];
        break;
      }
    }
    li.appendChild(body);
    return [li, speak];
  }

  let shown = 0, score = [0, 0], started = false, finished = false, lastPhase = null;
  function setScore(s, animate) {
    s.forEach((v, i) => {
      if (sc[i].textContent !== String(v)) {
        sc[i].textContent = v;
        if (animate && motion) { sc[i].classList.remove('is-new'); void sc[i].offsetWidth; sc[i].classList.add('is-new'); }
      }
    });
  }
  // Affiche l'état du match à $e secondes ; $live : annoncer et animer ce qui arrive.
  function render(e, live) {
    let target = 0;
    while (target < ev.length && ev[target].t <= e) target++;
    if (target < shown) { feed.textContent = ''; shown = 0; score = [0, 0]; finished = false; after.hidden = true; }
    const fresh = [];
    for (; shown < target; shown++) {
      const x = ev[shown];
      if (x.type === 'goal') score = x.score.slice();
      if (x.type === 'fulltime' && x.score) score = x.score.slice();
      const [li, speak] = item(x, score);
      if (live && motion) li.classList.add('is-new');
      feed.prepend(li);
      fresh.push([x, speak]);
    }
    const isStarted = e >= 0;
    if (isStarted !== started) { started = isStarted; if (wait) wait.hidden = started; }
    setScore(started ? score : ['–', '–'], live && fresh.some(([x]) => x.type === 'goal'));
    const [c, p] = clockAt(e);
    clock.textContent = c;
    if (p !== lastPhase) { lastPhase = p; phase.textContent = phaseLabel(p); }
    if (bar) { bar.hidden = !started; fill.style.width = Math.min(100, Math.max(0, e / M.end * 100)) + '%'; }
    const end = ev[ev.length - 1];
    if (e >= M.end && !finished) {
      finished = true;
      after.hidden = false;
      if (end.pens) { extra.hidden = false; extra.textContent = fmt(L.pensWin, { team: teams[end.pens[0] > end.pens[1] ? 0 : 1], a: Math.max(...end.pens), b: Math.min(...end.pens) }); }
      else if (D.aet) { extra.hidden = false; extra.textContent = L.aetDone; }
    } else if (e < M.end) { extra.hidden = true; }
    // Lecteur d'écran : tout en direct ; en accéléré, seulement les buts et les grands moments.
    if (live && fresh.length) {
      const keep = fresh.filter(([x]) => D.mode === 'live' ? true : !['action', 'sub', 'yellow'].includes(x.type)).map(([, s]) => s).filter(Boolean);
      if (keep.length) say.textContent = keep.slice(-3).join('. ');
    }
  }

  /* ---------------------------------------------------------- les trois modes */
  if (D.mode === 'live' || D.mode === 'upcoming') {
    const elapsed = () => serverNow() - D.start;
    const cd = $('[data-rd-countdown-v]');
    let reloadAt = 0;
    const tick = first => {
      const e = elapsed();
      if (D.mode === 'upcoming') {
        const s = Math.max(0, Math.ceil(-e));
        const d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60), sec = s % 60;
        const pad = n => String(n).padStart(2, '0');
        cd.textContent = s > 0 ? (d ? d + ' ' + L.days + ' ' : '') + pad(h) + ':' + pad(m) + ':' + pad(sec) : L.soon;
        // Coup d'envoi : la page se recharge (en direct), avec un léger décalage pour ne pas arriver tous ensemble.
        if (s <= 0 && !reloadAt) { reloadAt = Date.now() + Math.random() * 6000; }
        if (reloadAt && Date.now() >= reloadAt) { location.reload(); reloadAt = Infinity; }
        return;
      }
      render(e, !first);
    };
    tick(true);
    setInterval(() => tick(false), 1000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(false); });
  } else {
    // En accéléré : position du match (secondes), vitesse ×1, ×10, ×60 ; les pauses passent en 4 secondes.
    let pos = -1, speed = 10, playing = false, last = 0, pauseUntil = 0;
    const play = $('[data-rd-play]'), nextBtn = $('[data-rd-next]'), restart = $('[data-rd-restart]');
    const pauses = [[M.halftime, M.kickoff2], [M.fulltime90, M.extratime], [M.et_end, M.pens]].filter(([a, b]) => a !== null && b !== null && b > a);
    const setPlay = on => {
      playing = on;
      play.textContent = on ? L.pauseBtn : (pos < 0 ? L.play : pos >= M.end ? L.restart : L.resume);
      play.setAttribute('aria-pressed', on ? 'true' : 'false');
      nextBtn.disabled = pos >= M.end;
      restart.hidden = pos <= 0;
    };
    const loop = now => {
      if (!playing) return;
      const dt = Math.min(1, (now - last) / 1000);
      last = now;
      const p = pauses.find(([a, b]) => pos >= a && pos < b);
      if (p) {
        if (!pauseUntil) pauseUntil = now + 4000;
        if (now >= pauseUntil) { pos = p[1]; pauseUntil = 0; }
      } else {
        pos += dt * speed;
      }
      if (pos >= M.end) { pos = M.end; render(pos, true); setPlay(false); return; }
      render(pos, true);
      requestAnimationFrame(loop);
    };
    const start = () => {
      if (pos < 0 || pos >= M.end) { pos = 0; render(-1, false); }
      last = performance.now();
      setPlay(true);
      requestAnimationFrame(loop);
    };
    play.addEventListener('click', () => (playing ? setPlay(false) : start()));
    $$('[data-rd-speed]').forEach(b => b.addEventListener('click', () => {
      speed = +b.dataset.rdSpeed;
      $$('[data-rd-speed]').forEach(x => x.setAttribute('aria-checked', x === b ? 'true' : 'false'));
    }));
    nextBtn.addEventListener('click', () => {
      if (pos < 0) pos = 0;
      const n = ev.find(x => x.t > pos + 0.5 && x.type !== 'kickoff');
      pos = n ? n.t : M.end;
      pauseUntil = 0;
      render(pos, true);
      setPlay(playing && pos < M.end);
      if (playing) { last = performance.now(); }
    });
    restart.addEventListener('click', () => { pos = 0; render(-1, false); start(); });
    render(-1, false);
    setPlay(false);
  }

  /* ---------------------------------------------------------- spectateurs et réactions (direct) */
  const api = body => fetch('/api/retro-direct', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
    .then(r => r.json()).catch(() => null);
  const viewers = $('[data-rd-viewers]');
  const apply = r => {
    if (!r || !r.ok) return;
    if (viewers && r.viewers) viewers.textContent = fmt(r.viewers > 1 ? L.viewersN : L.viewers1, { n: r.viewers });
    Object.entries(r.reactions || {}).forEach(([k, n]) => { const b = $('[data-rd-count="' + k + '"]'); if (b && +b.textContent < n) b.textContent = n; });
  };
  if (D.mode === 'live') {
    const token = Math.random().toString(36).slice(2, 12) + Math.random().toString(36).slice(2, 6);
    const ping = () => { if (!document.hidden && serverNow() < D.close) api({ action: 'presence', id: D.id, date: D.date, token }).then(apply); };
    ping();
    setInterval(ping, 30000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) ping(); });
    let lastReact = 0;
    $$('[data-rd-react]').forEach(b => b.addEventListener('click', () => {
      const now = Date.now();
      if (now - lastReact < 1200) return;
      lastReact = now;
      const n = $('[data-rd-count]', b);
      n.textContent = +n.textContent + 1;
      if (motion) {
        const f = el('span', 'rdfloat', b.firstElementChild.textContent);
        f.style.left = (b.offsetLeft + b.offsetWidth / 2 - 14 + (Math.random() * 30 - 15)) + 'px';
        b.parentElement.appendChild(f);
        setTimeout(() => f.remove(), 1600);
      }
      api({ action: 'react', id: D.id, date: D.date, kind: b.dataset.rdReact }).then(apply);
    }));
  }

  /* ---------------------------------------------------------- « J'y étais ! » */
  const etais = $('[data-rd-etais]');
  if (etais) {
    const key = 'rd-etais-' + D.id;
    let done = false;
    try { done = localStorage.getItem(key) === '1'; } catch (e) {}
    const thank = () => { etais.disabled = true; etais.textContent = '✓ ' + etais.textContent.replace(/^✓ /, ''); $('[data-rd-etais-more]').hidden = false; };
    if (done) thank();
    etais.addEventListener('click', () => {
      if (etais.disabled) return;
      api({ action: 'etais', id: D.id }).then(r => {
        if (r && r.etais) $('[data-rd-etais-n]').textContent = fmt(r.etais > 1 ? L.etaisN : L.etais1, { n: r.etais });
        try { localStorage.setItem(key, '1'); } catch (e) {}
        thank();
      });
    });
  }
})();
