// Le babyfoot du musée : page /interactif/babyfoot/. Relie la physique (physics.js), l'ordinateur
// (ai.js), les règles (rules.js), le rendu 3D (render.js) et les sons (audio.js) à la page : écran
// d'accueil (façon de jouer, adversaire, durée), commandes (pouce gauche pour glisser, pouce droit
// pour tirer, souris, clavier, à deux sur le même écran), tableau de score, ralenti des buts, écran
// de fin (vrai résultat, statistiques, partage, tableau d'honneur : API /api/babyfoot).
// Compilé avec Three.js par bin/build-babyfoot.sh.
import { Physics } from './physics.js';
import { Brain, LEVELS, ASSIST, activeRod } from './ai.js';
import { Match } from './rules.js';
import { Scene3D, luminance } from './render.js';
import { Sound } from './audio.js';

const root = document.querySelector('[data-babyfoot]');
if (root) boot().catch(err => { console.error(err); fail(); });

function fail() {
  root.querySelectorAll('[data-bf-ov]').forEach(o => { o.hidden = o.dataset.bfOv !== 'nogl'; });
}

function store(k, v) {
  try { if (v === undefined) return localStorage.getItem('bf:' + k); localStorage.setItem('bf:' + k, v); } catch (e) { return null; }
  return null;
}

const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
// Suivre le doigt même s'il sort de la zone (certains navigateurs refusent : on continue sans).
const capture = (el, id) => { try { el.setPointerCapture(id); } catch (e) { /* rien */ } };
const esc = s => String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const ink = hex => (luminance(hex) > 0.55 ? '#0E1F4D' : '#ffffff');

async function boot() {
  const D = JSON.parse(document.getElementById('bf-data').textContent);
  const U = D.ui;
  const $ = s => root.querySelector(s);
  const $$ = s => [...root.querySelectorAll(s)];
  const ov = name => $$('[data-bf-ov]').forEach(o => { o.hidden = o.dataset.bfOv !== name; });
  const api = body => fetch(D.api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ lang: D.lang, ...body }) }).then(r => r.json());
  const fill = (s, o) => s.replace(/\{(\w+)\}/g, (m, k) => (k in o ? o[k] : m));

  const test = document.createElement('canvas');
  if (!(test.getContext('webgl2') || test.getContext('webgl'))) { ov('nogl'); return; }
  await Promise.race([document.fonts.load('900 40px "Big Shoulders Display"'), new Promise(r => setTimeout(r, 2500))]);
  const logo = await new Promise(res => { const i = new Image(); i.onload = () => res(i); i.onerror = () => res(null); i.src = D.logo; });

  // ---------------------------------------------------------------- réglages de l'accueil
  const touch = matchMedia('(pointer: coarse)').matches;
  const S = {
    mode: 'ai',
    level: clamp(+(store('niveau') ?? 1) || 0, 0, 2),
    format: store('duree') === 'buts' ? 'buts' : 'temps',
    opp: 0,
    hist: 0,
    view: ['joueur', 'dessus', 'basse'].includes(store('vue')) ? store('vue') : 'joueur',
  };
  const histSel = $('[data-bf-hist]');
  if (histSel) S.hist = +histSel.value;
  const savedOpp = D.opponents.findIndex(o => o.id === store('adversaire'));
  if (savedOpp >= 0) S.opp = savedOpp;
  const DUO_GLOW = '#9fd3ff';

  const scene = new Scene3D($('[data-bf-canvas]'), { title: D.title + ' · ' + D.site, logo, kits: [D.us.kit, D.opponents[0] ? D.opponents[0].kit : D.us.kit], glow: [D.us.glow, DUO_GLOW] });
  const sound = new Sound();
  const stage = $('[data-bf-stage]');
  const resize = () => { const r = stage.getBoundingClientRect(); scene.resize(Math.max(1, r.width), Math.max(1, r.height)); };
  new ResizeObserver(resize).observe(stage);
  resize();

  // Match de démonstration derrière l'accueil (deux ordinateurs, sans son).
  let demo = null;
  function newDemo() {
    const p = new Physics();
    const m = new Match(p, { format: 'buts', goals: 99 });
    demo = { p, m, brains: [new Brain(p, 0, LEVELS[2]), new Brain(p, 1, LEVELS[1])] };
    m.start();
  }
  newDemo();

  /** Adversaire et maillots selon la façon de jouer choisie. */
  const histMatch = () => (S.mode === 'histoire' && D.history.length ? D.history[S.hist] : null);
  const opponent = () => (histMatch() ? histMatch().opp : D.opponents[S.opp] || { short: '?', ini: '?', kit: { shirt: '#ECECEC', sleeves: '#ECECEC', shorts: '#3A3A3A', socks: '#ECECEC' } });
  let kitKey = '';
  function applyKits() {
    const o = opponent(), h = histMatch();
    const key = o.kit.shirt + o.kit.shorts + (h ? h.id : '');
    if (key === kitKey) return;
    kitKey = key;
    scene.setKits([D.us.kit, o.kit]);
    scene.setNames(h && h.names ? h.names : null);
  }

  // ---------------------------------------------------------------- écran d'accueil
  const badge = $('[data-bf-badge]'), oppName = $('[data-bf-oppname]');
  function drawHome() {
    $$('[data-bf-mode]').forEach(m => m.setAttribute('aria-checked', m.dataset.bfMode === S.mode ? 'true' : 'false'));
    $$('[data-bf-level]').forEach(b => b.setAttribute('aria-pressed', +b.dataset.bfLevel === S.level ? 'true' : 'false'));
    $$('[data-bf-format]').forEach(b => b.setAttribute('aria-pressed', b.dataset.bfFormat === S.format ? 'true' : 'false'));
    const o = opponent();
    badge.textContent = o.ini || '';
    badge.style.background = o.kit.shirt; badge.style.color = ink(o.kit.shirt);
    oppName.textContent = o.short;
    $('.bf__opp').classList.toggle('is-fixed', !!histMatch());
    applyKits();
  }
  $$('[data-bf-mode]').forEach(m => {
    const pick = () => { S.mode = m.dataset.bfMode; drawHome(); };
    m.addEventListener('click', e => { if (!e.target.closest('select')) pick(); });
    m.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); pick(); } });
  });
  $$('[data-bf-level]').forEach(b => b.addEventListener('click', e => { e.stopPropagation(); S.level = +b.dataset.bfLevel; store('niveau', String(S.level)); if (S.mode === 'duo') S.mode = 'ai'; drawHome(); }));
  $$('[data-bf-format]').forEach(b => b.addEventListener('click', () => { S.format = b.dataset.bfFormat; store('duree', S.format); drawHome(); }));
  $$('[data-bf-opp]').forEach(b => b.addEventListener('click', () => {
    if (histMatch() || !D.opponents.length) return;
    S.opp = (S.opp + +b.dataset.bfOpp + D.opponents.length) % D.opponents.length;
    store('adversaire', D.opponents[S.opp].id);
    drawHome();
  }));
  if (histSel) histSel.addEventListener('change', () => { S.hist = +histSel.value; S.mode = 'histoire'; drawHome(); });
  drawHome();

  // ---------------------------------------------------------------- partie
  let game = null;      // { p, match, brains, duo, solo, opp, hist, level, format, token, started }
  let mode = 'menu';    // menu | play | replay | over
  let paused = false;
  let rec = [];         // instantanés pour le ralenti
  let replay = null;
  let lastGoal = null;
  // Par équipe humaine : barre tenue, doigt posé, dernière commande, tir en cours, touches.
  const H = [0, 1].map(() => ({ rod: -1, drag: null, last: -9, ctl: -9, shootAt: null, wound: -1, key: 0 }));
  const now = () => performance.now() / 1000;

  /** Barre tenue par le joueur de l'équipe t (-1 : l'aide place toutes les barres). */
  function held(t) {
    const h = H[t];
    const on = h.drag !== null || h.shootAt !== null || h.key !== 0 || now() - h.last < 1.2;
    return on ? h.rod : -1;
  }

  function newGame() {
    const duo = S.mode === 'duo', hist = histMatch(), opp = opponent();
    const p = new Physics();
    const names0 = hist && hist.names ? Object.fromEntries(Object.entries(hist.names).map(([i, arr]) => [i, arr.map(x => x.name)])) : null;
    const match = new Match(p, {
      format: S.format, half: 180, goals: 5, golden: !!(hist && hist.golden), replay: true,
      first: duo ? (Math.random() < 0.5 ? 0 : 1) : 0, names: [names0, null],
    }, { fx: onFx, over: onOver });
    const brains = [
      new Brain(p, 0, ASSIST, { human: () => held(0) }),
      duo ? new Brain(p, 1, ASSIST, { human: () => held(1) }) : new Brain(p, 1, LEVELS[S.level]),
    ];
    H.forEach(h => Object.assign(h, { rod: -1, drag: null, last: -9, ctl: -9, shootAt: null, wound: -1, key: 0 }));
    game = { p, match, brains, duo, solo: !duo, opp, hist, level: S.level, format: S.format, token: null, started: performance.now() };
    applyKits();
    root.classList.toggle('is-duo', duo);
    $$('[data-bf-glide="1"], [data-bf-shoot="1"]').forEach(x => { x.hidden = !duo; });
    scene.setView(duo ? 'dessus' : S.view);
    $('[data-bf-name="0"]').textContent = D.us.short;
    $('[data-bf-name="1"]').textContent = opp.short;
    const n0 = $('[data-bf-name="0"]'), n1 = $('[data-bf-name="1"]');
    n0.style.background = D.us.kit.shirt; n0.style.color = ink(D.us.kit.shirt);
    n1.style.background = opp.kit.shirt; n1.style.color = ink(opp.kit.shirt);
    rec = []; replay = null; lastGoal = null;
    match.start();
    mode = 'play';
    paused = false;
    ov('');
    $('[data-bf-hud]').hidden = false;
    $('[data-bf-replay]').hidden = true;
    sound.ambience(true);
    sound.play('start');
    banner(U.kickoff, 1100, true);
    if (game.solo) api({ action: 'commencer', level: game.level, format: game.format }).then(r => { if (game) game.token = r.tok || null; }).catch(() => {});
    rotateHint();
  }

  // ---------------------------------------------------------------- événements du match
  function onFx(name, data) {
    if (!game) return;
    const duoFlag = game.duo;
    if (name === 'goal') {
      lastGoal = { ...data, wall: game.match.wall, side: data.team === 0 ? 1 : -1 };
      sound.play('goal', { team: data.team, duo: duoFlag });
      scene.fx('goal', data);
      if (navigator.vibrate && sound.on && (data.team === 0 || duoFlag)) navigator.vibrate([30, 40, 60]);
      const who = data.own ? U.ownGoal : data.name || (data.num ? fill(U.num, { n: data.num }) : '');
      const team = data.team === 0 ? D.us.short : game.opp.short;
      banner(`${esc(U.goal)}<small>${esc(team)}${who ? ' · ' + esc(who) : ''} · ${esc(data.at)}</small>`, 1500);
      return;
    }
    if (name === 'replay') { startReplay(); return; }
    if (name === 'half') { banner(U.half, 2000); }
    if (name === 'golden') { banner(`${esc(U.golden)}<small>${esc(U.goldenSub)}</small>`, 2200); }
    if (name === 'dead') { toast(U.dead); }
    sound.play(name, data);
    scene.fx(name, data);
  }

  function onOver(res) {
    mode = 'over';
    sound.ambience(false);
    setTimeout(() => showEnd(res), 1400);
  }

  // ---------------------------------------------------------------- ralenti des buts
  function startReplay() {
    if (!lastGoal || !rec.length) { game.match.replayDone(); return; }
    const t1 = lastGoal.wall + 0.45, t0 = lastGoal.wall - 2.4;
    const clip = rec.filter(f => f.t >= t0 && f.t <= t1);
    if (clip.length < 10) { game.match.replayDone(); return; }
    replay = { clip, t: clip[0].t, side: lastGoal.side };
    mode = 'replay';
    root.classList.add('is-replay');
    scene.resetBall();
    $('[data-bf-replay]').hidden = false;
    sound.play('replay');
  }
  function endReplay() {
    if (mode !== 'replay') return;
    replay = null;
    mode = 'play';
    root.classList.remove('is-replay');
    scene.resetBall();
    $('[data-bf-replay]').hidden = true;
    game.match.replayDone();
  }
  $('[data-bf-skip]').addEventListener('click', endReplay);
  /** Image du ralenti à l'instant t (interpolée entre deux instantanés). */
  function replayFrame(t) {
    const c = replay.clip;
    let i = 0;
    while (i < c.length - 2 && c[i + 1].t < t) i++;
    const a = c[i], b = c[i + 1] || a, k = b.t > a.t ? clamp((t - a.t) / (b.t - a.t), 0, 1) : 0;
    const L = (x, y) => x + (y - x) * k;
    return {
      rods: a.s.r.map((r, j) => ({ z: L(r[0], b.s.r[j][0]), ang: L(r[1], b.s.r[j][1]) })),
      ball: { x: L(a.s.b[0], b.s.b[0]), z: L(a.s.b[1], b.s.b[1]), goal: a.s.b[3], live: true },
    };
  }

  // ---------------------------------------------------------------- bandeaux et messages
  const bannerEl = $('[data-bf-banner]');
  let bannerTimer = 0;
  function banner(html, ms, small = false) {
    bannerEl.innerHTML = html;
    bannerEl.classList.toggle('is-small', small);
    bannerEl.classList.add('is-on');
    clearTimeout(bannerTimer);
    bannerTimer = setTimeout(() => bannerEl.classList.remove('is-on'), ms);
  }
  const toastEl = $('[data-bf-toast]');
  let toastTimer = 0;
  function toast(text) {
    toastEl.textContent = text;
    toastEl.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toastEl.classList.remove('is-on'), 1400);
  }

  // ---------------------------------------------------------------- commandes
  /** Déplacer la barre tenue de dz (cm le long de la barre). */
  function slideBy(t, dz) {
    const h = H[t];
    if (!game || h.rod < 0) return;
    const r = game.p.rods[h.rod];
    // Geste qui continue : on part de la position voulue ; sinon (l'aide plaçait la barre) de la
    // position réelle, pour que la figurine ne saute pas.
    const base = now() - h.ctl < 0.25 ? r.target : r.z;
    game.p.slideTo(h.rod, base + dz);
    h.last = h.ctl = now();
  }
  /** Souris : la figurine la mieux placée vient sous le pointeur. */
  function slideToZ(t, z) {
    const h = H[t];
    if (!game || h.rod < 0) return;
    const r = game.p.rods[h.rod];
    let best = null;
    for (const o of r.offs) {
      const off = clamp(z - o, -r.travel, r.travel);
      const cost = Math.abs(off - (z - o)) * 3 + Math.abs(off - r.z);
      if (!best || cost < best.cost) best = { cost, off };
    }
    game.p.slideTo(h.rod, best.off);
    h.last = h.ctl = now();
  }
  function shootDown(t) {
    const h = H[t];
    if (!game || paused || mode !== 'play' || h.shootAt !== null || h.rod < 0) return;
    h.shootAt = now();
    h.wound = h.rod;
    game.p.windup(h.rod);
    if (sound.on === false && wantSound) sound.enable(true);
  }
  function shootUp(t) {
    const h = H[t];
    if (h.shootAt === null) return;
    const hold = now() - h.shootAt;
    h.shootAt = null;
    if (!game || mode !== 'play') return;
    // Appui bref : tir moyen ; appui long (une demi-seconde) : pleine puissance.
    const power = 0.3 + 0.7 * clamp((hold - 0.08) / 0.5, 0, 1);
    if (h.wound >= 0 && h.wound !== h.rod) game.p.relax(h.wound);
    if (h.rod >= 0) game.p.strike(h.rod, power);
    h.wound = -1;
    h.last = now();
  }
  const gauges = [0, 1].map(t => $(`[data-bf-gauge="${t}"]`));
  const shootBtns = [0, 1].map(t => $(`[data-bf-shoot="${t}"]`));

  // Pouces : zones « Glisse » (le doigt entraîne la figurine) et boutons « Tir ».
  const GAIN = 1.35;
  for (const zone of $$('[data-bf-glide]')) {
    const t = +zone.dataset.bfGlide;
    zone.addEventListener('pointerdown', e => {
      if (e.pointerType === 'mouse' && !game?.duo) return;
      if (!game || paused || H[t].drag !== null) return;
      capture(zone, e.pointerId);
      H[t].drag = { id: e.pointerId, x: e.clientX, y: e.clientY };
      zone.classList.add('is-on');
      e.preventDefault();
    });
    zone.addEventListener('pointermove', e => {
      const d = H[t].drag;
      if (!d || d.id !== e.pointerId || !game) return;
      const dx = e.clientX - d.x, dy = e.clientY - d.y;
      d.x = e.clientX; d.y = e.clientY;
      if (H[t].rod < 0) return;
      const ax = scene.screenAxis(H[t].rod);
      slideBy(t, (dx * ax.dx + dy * ax.dy) / ax.ppu * GAIN);
    });
    const up = e => { const d = H[t].drag; if (!d || d.id !== e.pointerId) return; H[t].drag = null; H[t].last = now(); zone.classList.remove('is-on'); };
    zone.addEventListener('pointerup', up);
    zone.addEventListener('pointercancel', up);
  }
  const shootPointers = [new Set(), new Set()];
  function bindShoot(el, t) {
    el.addEventListener('pointerdown', e => {
      if (e.pointerType === 'mouse' && !game?.duo && el.dataset.bfShootzone !== undefined) return;
      if (!game || paused) return;
      capture(el, e.pointerId);
      shootPointers[t].add(e.pointerId);
      if (shootPointers[t].size === 1) shootDown(t);
      shootBtns[t].classList.add('is-on');
      e.preventDefault(); e.stopPropagation();
    });
    const up = e => {
      if (!shootPointers[t].delete(e.pointerId)) return;
      if (!shootPointers[t].size) { shootUp(t); shootBtns[t].classList.remove('is-on'); }
    };
    el.addEventListener('pointerup', up);
    el.addEventListener('pointercancel', up);
    el.addEventListener('contextmenu', e => e.preventDefault());
  }
  shootBtns.forEach((b, t) => bindShoot(b, t));
  $$('[data-bf-shootzone]').forEach(z => bindShoot(z, +z.dataset.bfShootzone));

  // Souris (seul contre l'ordinateur) : la figurine suit le pointeur, clic gauche pour tirer.
  const canvasEl = $('[data-bf-canvas]');
  stage.addEventListener('pointermove', e => {
    if (e.pointerType !== 'mouse' || !game || game.duo || paused || mode !== 'play' || H[0].rod < 0) return;
    if (e.target.closest('button,a,.bf__ov')) return;
    const r = canvasEl.getBoundingClientRect();
    const hit = scene.pick(e.clientX - r.left, e.clientY - r.top);
    if (hit) slideToZ(0, hit.z);
  });
  stage.addEventListener('mousedown', e => {
    if (!game || game.duo || paused || e.button !== 0 || e.target.closest('button,a,input,select,.bf__ov')) return;
    shootDown(0);
    e.preventDefault();
  });
  window.addEventListener('mouseup', e => { if (e.button === 0 && game && !game.duo) shootUp(0); });
  stage.addEventListener('contextmenu', e => { if (!e.target.closest('.bf__ov')) e.preventDefault(); });
  root.addEventListener('wheel', e => { if (!e.target.closest('.bf__ov')) e.preventDefault(); }, { passive: false });
  // Ralenti : un clic ou une touche le passe.
  stage.addEventListener('pointerdown', e => { if (mode === 'replay' && !e.target.closest('button')) endReplay(); });

  // Clavier. Seul : ↑ ↓ (ou Z S / W S), Espace pour tirer. À deux : Z S + D (joueur 1), ↑ ↓ + ← (joueur 2).
  const KEYS = {
    up: { KeyW: 0, ArrowUp: 1 }, down: { KeyS: 0, ArrowDown: 1 },
    shoot: { KeyD: 0, Space: 0, KeyF: 0, ArrowLeft: 1, Enter: 1, Numpad0: 1, ShiftRight: 1 },
  };
  const teamOf = code => (game && !game.duo ? 0 : null);
  window.addEventListener('keydown', e => {
    if (e.target.closest('input,textarea,select')) return;
    if (e.code === 'Escape' || e.code === 'KeyP') { if (game && mode !== 'over' && !paused) pause(); else if (paused) resume(); return; }
    if (!game || paused) return;
    if (mode === 'replay') { endReplay(); e.preventDefault(); return; }
    if (e.code === 'KeyC') { nextView(); return; }
    if (e.repeat) { if (e.code in KEYS.shoot || e.code in KEYS.up || e.code in KEYS.down) e.preventDefault(); return; }
    const solo = teamOf(e.code);
    if (e.code in KEYS.up) { H[solo ?? KEYS.up[e.code]].key = -1; e.preventDefault(); }
    else if (e.code in KEYS.down) { H[solo ?? KEYS.down[e.code]].key = 1; e.preventDefault(); }
    else if (e.code in KEYS.shoot) { shootDown(solo ?? KEYS.shoot[e.code]); e.preventDefault(); }
  });
  window.addEventListener('keyup', e => {
    if (!game) return;
    const solo = teamOf(e.code);
    if (e.code in KEYS.up) { const t = solo ?? KEYS.up[e.code]; if (H[t].key === -1) { H[t].key = 0; H[t].last = now(); } }
    else if (e.code in KEYS.down) { const t = solo ?? KEYS.down[e.code]; if (H[t].key === 1) { H[t].key = 0; H[t].last = now(); } }
    else if (e.code in KEYS.shoot) shootUp(solo ?? KEYS.shoot[e.code]);
  });
  /** Touches ↑ ↓ : la figurine monte ou descend à l'écran, quelle que soit la caméra. */
  function keySlide(t, dt) {
    const h = H[t];
    if (!h.key || h.rod < 0) return;
    const ax = scene.screenAxis(h.rod);
    const sign = Math.abs(ax.dy) >= Math.abs(ax.dx) ? Math.sign(ax.dy) : -Math.sign(ax.dx);
    slideBy(t, h.key * sign * 85 * dt);
  }

  // ---------------------------------------------------------------- boutons du haut, pause, caméra
  const soundBtn = $('[data-bf-sound]');
  let wantSound = store('son') !== '0';
  const showSound = () => { soundBtn.setAttribute('aria-pressed', wantSound ? 'true' : 'false'); soundBtn.title = wantSound ? U.soundOff : U.soundOn; soundBtn.querySelector('span').textContent = wantSound ? '🔊' : '🔇'; };
  soundBtn.addEventListener('click', () => { wantSound = !wantSound; store('son', wantSound ? '1' : '0'); sound.enable(wantSound); if (wantSound) sound.ambience(!!game && mode !== 'over' && !paused); showSound(); });
  showSound();
  const VIEWS = ['joueur', 'dessus', 'basse'];
  function nextView() {
    const v = VIEWS[(VIEWS.indexOf(scene.view) + 1) % VIEWS.length];
    scene.setView(v);
    if (game && !game.duo) { S.view = v; store('vue', v); }
    toast(U.cams[v]);
  }
  $('[data-bf-cam]').addEventListener('click', nextView);
  function pause() { if (!game || mode === 'over') return; paused = true; ov('pause'); sound.ambience(false); [0, 1].forEach(t => { H[t].shootAt = null; H[t].key = 0; }); }
  function resume() { paused = false; ov(''); last = performance.now(); sound.ambience(true); }
  $('[data-bf-pause]').addEventListener('click', pause);
  document.addEventListener('visibilitychange', () => { if (document.hidden && game && mode !== 'over' && !paused) pause(); });

  function fullscreen() {
    if (document.fullscreenElement) return;
    const el = document.documentElement;
    if (el.requestFullscreen) {
      el.requestFullscreen().then(() => { if (touch && screen.orientation && screen.orientation.lock) screen.orientation.lock('landscape').catch(() => {}); }).catch(() => {});
    }
  }
  let navStack = 'start';
  $$('[data-bf-go]').forEach(b => b.addEventListener('click', () => {
    const go = b.dataset.bfGo;
    if (go === 'resume') { resume(); return; }
    if (go === 'quit' || go === 'home') { toHome(); return; }
    if (go === 'rules') { navStack = game && mode !== 'over' ? 'pause' : game && mode === 'over' ? 'over' : 'start'; ov('rules'); return; }
    if (go === 'scores') { navStack = 'start'; showScores(S.level); return; }
    if (go === 'full') { if (document.fullscreenElement) document.exitFullscreen(); else fullscreen(); return; }
    if (go === 'back') { ov(navStack); return; }
  }));
  function toHome() {
    game = null; mode = 'menu'; paused = false;
    $('[data-bf-hud]').hidden = true;
    root.classList.remove('is-duo');
    sound.ambience(false);
    stopConfetti();
    newDemo();
    drawHome();
    ov('start');
  }
  $$('[data-bf-play]').forEach(b => b.addEventListener('click', () => {
    if (wantSound && !sound.on) sound.enable(true);
    if (touch) fullscreen();
    stopConfetti();
    newGame();
  }));

  // Téléphone tenu droit : on conseille de le tourner (le jeu marche quand même).
  const rotateEl = $('[data-bf-rotate]');
  let rotateOff = false;
  function rotateHint() { rotateEl.hidden = rotateOff || !touch || !game || innerWidth > innerHeight; }
  $('[data-bf-rotok]').addEventListener('click', () => { rotateOff = true; rotateHint(); });
  addEventListener('resize', rotateHint);

  // ---------------------------------------------------------------- fin du match
  const endBox = $('[data-bf-ov="over"]');
  let lastRes = null;
  function showEnd(res) {
    if (!game) return;
    lastRes = res;
    ov('over');
    const g = game, us = res.score[0], them = res.score[1];
    const solo = g.solo, h = g.hist;
    const kick = [U.endKick];
    if (h) kick.push(fill(U.replayed, { m: h.short }));
    else kick.push(g.duo ? U.duo : fill(U.vs, { l: D.levels[g.level] }));
    endBox.querySelector('[data-bf-kick]').textContent = kick.join(' · ');
    const verdict = g.duo ? (res.winner === null ? U.draw : fill(U.winDuo, { t: res.winner === 0 ? D.us.short : g.opp.short })) : (res.winner === 0 ? U.win : res.winner === 1 ? U.lose : U.draw);
    endBox.querySelector('[data-bf-verdict]').textContent = verdict;
    const f0 = endBox.querySelector('[data-bf-fname="0"]'), f1 = endBox.querySelector('[data-bf-fname="1"]');
    f0.textContent = D.us.short; f0.style.background = D.us.kit.shirt; f0.style.color = ink(D.us.kit.shirt);
    f1.textContent = g.opp.short; f1.style.background = g.opp.kit.shirt; f1.style.color = ink(g.opp.kit.shirt);
    endBox.querySelector('[data-bf-fscore]').textContent = `${us} – ${them}`;
    // Rejoue l'histoire : le vrai résultat, et la comparaison.
    const realEl = endBox.querySelector('[data-bf-real]');
    if (h) {
      const mine = res.winner === 0 ? 'V' : res.winner === 1 ? 'D' : 'N';
      const same = mine === h.real.result;
      const cmp = mine === 'V' ? (same ? U.sameWin : U.thisTimeWin) : mine === 'D' ? (same ? U.sameLose : U.thisTimeLose) : (same ? U.sameDraw : U.thisTimeDraw);
      realEl.innerHTML = `${esc(h.real.text)} ${esc(cmp)} <a href="${esc(h.url)}">${esc(U.readMatch)} ›</a>`;
    } else realEl.textContent = '';
    // Statistiques (notre équipe ; à deux, celles du joueur 1).
    const st = res.stats, li = [];
    li.push(st.shots[0] === 1 ? U.shot1 : fill(U.shots, { n: st.shots[0] }));
    li.push(st.saves[0] === 1 ? U.save1 : fill(U.saves, { n: st.saves[0] }));
    const best = st.best[0];
    if (best) li.push(fill(U.best, { w: best.name || (best.num ? fill(U.num, { n: best.num }) : U.csc), a: best.at }));
    endBox.querySelector('[data-bf-stats]').innerHTML = li.map(x => `<li>${esc(x)}</li>`).join('');
    // Tableau d'honneur (seul contre l'ordinateur).
    const hof = endBox.querySelector('[data-bf-hof]');
    const rankBox = endBox.querySelector('[data-bf-rank]');
    endBox.querySelector('[data-bf-join]').hidden = true;
    endBox.querySelector('[data-bf-shared]').textContent = '';
    rankBox.textContent = '';
    hof.hidden = !solo;
    if (!solo) endBox.querySelector('[data-bf-shared]').textContent = U.duoNoRank;
    endBox.querySelector('[data-bf-hofsub]').textContent = fill(U.boardSub, { l: D.levels[g.level] });
    if (solo) {
      fillTop(endBox.querySelector('[data-bf-top]'), null);
      pending = g.token ? { tok: g.token, gf: us, ga: them, level: g.level } : null;
      g.token = null;
      if (pending) submit(); else loadTop(g.level);
    }
    if (res.winner === 0 || (g.duo && res.winner !== null)) { confetti(); sound.play('end', { win: true }); }
  }
  let pending = null;
  async function submit() {
    const rankBox = endBox.querySelector('[data-bf-rank]');
    try {
      const r = await api({ action: 'resultat', ...pending });
      if (r.ranked) { rankBox.textContent = r.label || ''; pending = null; }
      else if (r.error) { rankBox.textContent = r.error; pending = null; }
      endBox.querySelector('[data-bf-join]').hidden = !r.join;
      fillTop(endBox.querySelector('[data-bf-top]'), r.top || []);
    } catch (e) { fillTop(endBox.querySelector('[data-bf-top]'), [], U.offline); }
  }
  async function loadTop(level) {
    try { const r = await api({ action: 'classement', level }); fillTop(endBox.querySelector('[data-bf-top]'), r.week || []); } catch (e) { fillTop(endBox.querySelector('[data-bf-top]'), [], U.offline); }
  }
  function fillTop(el, rows, msg) {
    if (rows === null) { el.innerHTML = `<li class="is-empty">${esc(U.loading)}</li>`; return; }
    el.innerHTML = rows.length ? rows.slice(0, 10).map(r => `<li class="${r.me ? 'is-me' : ''}"><b>${r.rank}</b><span>${esc(r.pseudo)}</span><em>${esc(r.wins === 1 ? U.win1 : fill(U.wins, { n: r.wins }))}</em></li>`).join('') : `<li class="is-empty">${esc(msg || U.empty)}</li>`;
  }
  async function showScores(level) {
    ov('scores');
    const box = $('[data-bf-ov="scores"]');
    box.querySelectorAll('[data-bf-board]').forEach(b => b.setAttribute('aria-pressed', +b.dataset.bfBoard === level ? 'true' : 'false'));
    fillTop(box.querySelector('[data-bf-week]'), null);
    fillTop(box.querySelector('[data-bf-all]'), null);
    try {
      const r = await api({ action: 'classement', level });
      fillTop(box.querySelector('[data-bf-week]'), r.week || []);
      fillTop(box.querySelector('[data-bf-all]'), r.all || []);
    } catch (e) { fillTop(box.querySelector('[data-bf-week]'), [], U.offline); fillTop(box.querySelector('[data-bf-all]'), [], U.offline); }
  }
  $$('[data-bf-board]').forEach(b => b.addEventListener('click', () => showScores(+b.dataset.bfBoard)));
  const joinForm = $('[data-bf-join]');
  joinForm.addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(joinForm), msg = joinForm.querySelector('[data-bf-joinmsg]');
    msg.textContent = '…';
    try {
      const r = await api({ action: 'inscrire', email: fd.get('email') || '', name: fd.get('name') || '' });
      msg.textContent = r.error || (r.member ? U.joined : U.mailed);
      if (r.member && pending) { joinForm.hidden = true; await submit(); }
    } catch (err) { msg.textContent = U.offline; }
  });

  // Partager : une image du score (envoyée telle quelle si le téléphone le permet, sinon enregistrée).
  $('[data-bf-share]').addEventListener('click', async () => {
    if (!lastRes || !game) return;
    const blob = await scoreImage(lastRes, game);
    const text = fill(U.shareText, { a: D.us.short, s: `${lastRes.score[0]}-${lastRes.score[1]}`, b: game.opp.short }) + ' ' + D.url;
    const file = blob ? new File([blob], 'babyfoot.png', { type: 'image/png' }) : null;
    const out = $('[data-bf-shared]');
    try {
      if (file && navigator.canShare && navigator.canShare({ files: [file] })) { await navigator.share({ files: [file], text, title: D.title }); return; }
      if (navigator.share && touch) { await navigator.share({ title: D.title, text, url: D.url }); return; }
      if (blob) {
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob); a.download = 'babyfoot-' + D.us.short.toLowerCase() + '.png';
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 4000);
        out.textContent = U.saved;
      } else { await navigator.clipboard.writeText(text); out.textContent = U.copied; }
    } catch (e) { /* partage annulé */ }
  });
  function scoreImage(res, g) {
    const c = document.createElement('canvas'); c.width = 1200; c.height = 630;
    const x = c.getContext('2d');
    const grd = x.createLinearGradient(0, 0, 1200, 630);
    grd.addColorStop(0, D.colors.navy); grd.addColorStop(1, '#050b22');
    x.fillStyle = grd; x.fillRect(0, 0, 1200, 630);
    x.fillStyle = 'rgba(255,255,255,.05)';
    for (let i = 0; i < 12; i++) x.fillRect(i * 100, 0, 50, 630);
    if (logo) x.drawImage(logo, 70, 60, 110, 110 * (logo.height / logo.width || 1.13));
    const F = w => `${w} "Big Shoulders Display", "Arial Narrow", sans-serif`;
    x.fillStyle = D.colors.yellow; x.font = F('900 64px'); x.textBaseline = 'alphabetic';
    x.fillText(D.title.toUpperCase(), 210, 128);
    x.fillStyle = '#cfd6ea'; x.font = F('800 30px');
    x.fillText((g.hist ? fill(U.replayed, { m: g.hist.label }) : g.duo ? U.duo : fill(U.vs, { l: D.levels[g.level] })).toUpperCase(), 212, 172);
    const chip = (txt, bg, fg, cx, w) => { x.fillStyle = bg; x.fillRect(cx, 250, w, 130); x.fillStyle = fg; x.font = F('900 78px'); x.textAlign = 'center'; x.fillText(txt, cx + w / 2, 345); x.textAlign = 'left'; };
    chip(D.us.short, D.us.kit.shirt, ink(D.us.kit.shirt), 150, 300);
    chip(`${res.score[0]} – ${res.score[1]}`, '#ffffff', D.colors.navy, 450, 300);
    chip(g.opp.short, g.opp.kit.shirt, ink(g.opp.kit.shirt), 750, 300);
    x.fillStyle = '#fff'; x.font = F('900 70px'); x.textAlign = 'center';
    x.fillText(endBox.querySelector('[data-bf-verdict]').textContent.toUpperCase(), 600, 480);
    x.fillStyle = '#9aa6c8'; x.font = F('800 30px');
    x.fillText(D.url.replace(/^https?:\/\//, ''), 600, 570);
    return new Promise(r => c.toBlob(b => r(b), 'image/png'));
  }

  // Confettis aux couleurs du club pour une victoire.
  const confCanvas = $('[data-bf-confetti]');
  let confRaf = 0;
  function confetti() {
    const cx = confCanvas.getContext('2d');
    const w = confCanvas.width = confCanvas.clientWidth, h = confCanvas.height = confCanvas.clientHeight;
    const cols = [D.us.kit.shirt, D.us.kit.shorts, '#ffffff'];
    const parts = Array.from({ length: 140 }, () => ({ x: Math.random() * w, y: -Math.random() * h, vx: (Math.random() - 0.5) * 60, vy: 80 + Math.random() * 120, a: Math.random() * 6, va: (Math.random() - 0.5) * 8, c: cols[Math.floor(Math.random() * cols.length)], s: 6 + Math.random() * 6 }));
    let t0 = performance.now(), prev = t0;
    cancelAnimationFrame(confRaf);
    const step = t => {
      const dt = Math.min(0.05, (t - prev) / 1000); prev = t;
      cx.clearRect(0, 0, w, h);
      for (const p of parts) {
        p.x += p.vx * dt; p.y += p.vy * dt; p.a += p.va * dt;
        if (p.y > h + 20 && t - t0 < 3500) { p.y = -10; p.x = Math.random() * w; }
        cx.save(); cx.translate(p.x, p.y); cx.rotate(p.a); cx.fillStyle = p.c; cx.fillRect(-p.s / 2, -p.s / 4, p.s, p.s / 2); cx.restore();
      }
      if (t - t0 < 6000) confRaf = requestAnimationFrame(step); else cx.clearRect(0, 0, w, h);
    };
    confRaf = requestAnimationFrame(step);
  }
  function stopConfetti() { cancelAnimationFrame(confRaf); const cx = confCanvas.getContext('2d'); cx.clearRect(0, 0, confCanvas.width, confCanvas.height); }

  // ---------------------------------------------------------------- tableau de score
  const scoreEl = $('[data-bf-score]'), clockEl = $('[data-bf-clock]');
  let hudKey = '';
  function drawHud() {
    if (!game) return;
    const m = game.match;
    const k = m.score.join('-') + m.clockText();
    if (k === hudKey) return;
    hudKey = k;
    scoreEl.textContent = `${m.score[0]} – ${m.score[1]}`;
    clockEl.textContent = m.clockText();
    clockEl.classList.toggle('is-late', m.o.format === 'temps' && m.half === 2 && !m.goldenOn && m.o.half - m.clockT < 20);
  }
  const arrows = $$('[data-bf-arrow]');
  let arrowT = 0;

  // ---------------------------------------------------------------- boucle
  let last = performance.now(), frames = 0, slow = 0;
  const bootAt = last;
  let windowAt = last;
  function loop(nowMs) {
    const dt = Math.max(0, Math.min(0.05, (nowMs - last) / 1000));
    last = nowMs;
    const t = nowMs / 1000;
    let view;
    if (mode === 'menu' || !game) {
      demo.brains.forEach(b => b.update(dt));
      demo.m.update(dt);
      if (demo.m.state === 'over') newDemo();
      view = { rods: demo.p.rods, ball: demo.p.ball, active: [-1, -1], mode: 'menu' };
    } else {
      const g = game, p = g.p;
      if (!paused && mode === 'play') {
        for (let tm = 0; tm < 2; tm++) {
          const human = tm === 0 || g.duo;
          if (!human) continue;
          if (p.ball.live && !p.ball.goal) H[tm].rod = activeRod(p, tm, H[tm].rod);
          keySlide(tm, dt);
        }
        g.brains.forEach(b => b.update(dt));
        g.match.update(dt);
        rec.push({ t: g.match.wall, s: p.snapshot() });
        while (rec.length && rec[0].t < g.match.wall - 5) rec.shift();
      } else if (!paused && mode === 'replay' && replay) {
        replay.t += dt * 0.4;
        if (replay.t >= replay.clip[replay.clip.length - 1].t) endReplay();
      }
      if (mode === 'replay' && replay) {
        const f = replayFrame(replay.t);
        view = { rods: f.rods, ball: f.ball, active: [-1, -1], mode: 'replay', replay: { side: replay.side } };
      } else {
        view = { rods: p.rods, ball: p.ball, active: [H[0].rod, g.duo ? H[1].rod : -1], mode: 'play' };
      }
      // Jauge de tir.
      [0, 1].forEach(tm => { const s = H[tm].shootAt; gauges[tm].parentElement.style.setProperty('--p', s === null ? 0 : clamp((now() - s - 0.08) / 0.5, 0, 1)); });
      drawHud();
      sound.update(mode === 'play' && !paused ? Math.hypot(p.ball.vx, p.ball.vz) : 0);
      if (t - arrowT > 0.5 && H[0].rod >= 0) {
        arrowT = t;
        arrows.forEach((a, i) => { const rod = H[i] && H[i].rod >= 0 ? H[i].rod : H[0].rod; const ax = scene.screenAxis(rod); a.textContent = Math.abs(ax.dx) > Math.abs(ax.dy) ? '↔' : '↕'; });
      }
    }
    scene.frame(view, dt, t);
    scene.render();
    // Qualité automatique (après les premières secondes, où la préparation des effets ralentit
    // tout) : sur chaque période de 2 s, plus de la moitié des images lentes → moins d'effets.
    if (nowMs - bootAt > 3000 && !paused && (mode === 'menu' || mode === 'play')) {
      frames++;
      if (dt > 0.028) slow++;
      if (nowMs - windowAt > 2000) {
        if (frames && slow / frames > 0.5 && scene.quality > 0) scene.setQuality(scene.quality - 1);
        frames = 0; slow = 0; windowAt = nowMs;
      }
    }
    requestAnimationFrame(loop);
  }
  ov('start');
  requestAnimationFrame(loop);
  root.classList.add('is-ready');
}
