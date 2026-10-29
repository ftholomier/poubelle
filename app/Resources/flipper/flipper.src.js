// Le flipper du musée « au stade » : page /interactif/flipper/. Relie la physique (physics.js), les
// règles du match (rules.js), le rendu 3D du plateau et du stade (render.js, stadium.js) et les sons
// (audio.js) à la page : écran à points et tableau de score du match, adversaire tiré de l'histoire
// du club, commandes (écran tactile, clavier, souris, secousse du téléphone), écrans de départ, de
// pause et de fin, tableau d'honneur (API /api/flipper). Compilé avec Three.js par bin/build-flipper.sh.
import { buildTable } from './table.js';
import { Physics } from './physics.js';
import { Game, MULTS } from './rules.js';
import { Scene3D } from './render.js';
import { Sound } from './audio.js';

const root = document.querySelector('[data-flipper]');
if (root) boot().catch(err => { console.error(err); fail(); });

function fail() {
  root.querySelectorAll('[data-fl-ov]').forEach(o => { o.hidden = o.dataset.flOv !== 'nogl'; });
}

function store(k, v) {
  try { if (v === undefined) return localStorage.getItem('fl:' + k); localStorage.setItem('fl:' + k, v); } catch (e) { return null; }
  return null;
}

async function boot() {
  const D = JSON.parse(document.getElementById('fl-data').textContent);
  const U = D.ui;
  const $ = s => root.querySelector(s);
  const ov = name => root.querySelectorAll('[data-fl-ov]').forEach(o => { o.hidden = o.dataset.flOv !== name; });
  const fmt = n => String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  const api = body => fetch(D.api, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify({ lang: D.lang, ...body }) }).then(r => r.json());

  // WebGL disponible ?
  const test = document.createElement('canvas');
  if (!(test.getContext('webgl2') || test.getContext('webgl'))) { ov('nogl'); return; }
  await Promise.race([document.fonts.load('900 40px "Big Shoulders Display"'), new Promise(r => setTimeout(r, 2500))]);
  const logo = await new Promise(res => { const i = new Image(); i.onload = () => res(i); i.onerror = () => res(null); i.src = D.logo; });

  const table = buildTable();
  let phys = new Physics(table);
  const sound = new Sound();
  // Adversaires : des clubs de l'histoire du musée ; les règles les enchaînent au fil de la Coupe.
  const opps = D.opponents && D.opponents.length ? D.opponents : [{ name: U.visitors, ini: U.visitors.slice(0, 3).toUpperCase(), kit: null }];
  let opp = opps[Math.floor(Math.random() * opps.length)];
  const scene = new Scene3D($('[data-fl-canvas]'), table, {
    colors: D.colors, kit: D.kit, opp, title: D.title, site: D.site, short: D.short, logo, labels: D.labels,
  });
  let game = null;
  let token = null;
  let started = 0;
  let paused = false;

  // ---------------------------------------------------------------- taille de l'écran
  const stage = $('[data-fl-stage]');
  const resize = () => { const r = stage.getBoundingClientRect(); scene.resize(Math.max(1, r.width), Math.max(1, r.height)); };
  new ResizeObserver(resize).observe(stage);
  resize();

  // ---------------------------------------------------------------- écran à points
  const dmd = { big: $('[data-fl-big]'), small: $('[data-fl-small]'), l: $('[data-fl-dl]'), r: $('[data-fl-dr]'), box: $('[data-fl-dmd]') };
  let lastSmall = '';
  function drawDmd() {
    if (!game || game.state === 'attract') {
      dmd.big.textContent = D.title; dmd.l.textContent = ''; dmd.r.textContent = '';
      setSmall(store('best') ? U.record + ' ' + fmt(+store('best')) : U.insertCoin);
      return;
    }
    if (game.state === 'over') {
      const best = game.players.reduce((m, p) => Math.max(m, p.score), 0);
      dmd.big.textContent = fmt(best); dmd.box.dataset.kind = ''; dmd.l.textContent = ''; dmd.r.textContent = '';
      setSmall(U.over);
      return;
    }
    const s = game.screen(), pl = game.pl, m = game.match();
    dmd.big.textContent = s.big;
    dmd.box.dataset.kind = s.kind || '';
    // Coins : le score du match (le club contre l'adversaire), le chrono, la bille en cours, le multiplicateur.
    dmd.l.textContent = (game.players.length > 1 ? U.p + pl.n + ' · ' : '') + D.short + ' ' + m.home + '–' + m.away + ' ' + (m.opp.ini || m.opp.name || '');
    dmd.r.textContent = m.clock + ' · ' + U.ball + ' ' + Math.min(pl.ball, game.cfg.balls) + '/' + game.cfg.balls + (MULTS[pl.mult] > 1 ? ' · ×' + MULTS[pl.mult] : '');
    setSmall(s.small);
  }
  function setSmall(text) {
    if (text === lastSmall) return;
    lastSmall = text;
    dmd.small.textContent = text;
    dmd.small.classList.remove('is-scroll');
    requestAnimationFrame(() => {
      const over = dmd.small.scrollWidth - dmd.small.parentElement.clientWidth;
      if (over > 4) { dmd.small.style.setProperty('--fl-scroll', -over - 24 + 'px'); dmd.small.style.setProperty('--fl-dur', Math.max(4, over / 40) + 's'); dmd.small.classList.add('is-scroll'); }
    });
  }

  // ---------------------------------------------------------------- joueurs
  const playersBox = $('[data-fl-players]');
  function drawPlayers() {
    if (!game || game.players.length < 2) { playersBox.hidden = true; return; }
    playersBox.hidden = false;
    playersBox.innerHTML = game.players.map((p, i) => `<span class="${i === game.cur ? 'is-on' : ''}">${U.p}${p.n} <b>${fmt(p.score)}</b></span>`).join('');
  }

  // ---------------------------------------------------------------- partie
  function newGame(n) {
    phys = new Physics(table);
    // Les adversaires de la Coupe, dans un ordre tiré au hasard à chaque partie.
    const list = opps.slice().sort(() => Math.random() - 0.5);
    game = new Game(phys, table, { balls: 3, text: D.text, facts: D.facts, opps: list }, {
      fx: (name, data) => {
        // Nouvel adversaire (coup d'envoi d'un match, joueur suivant) : le décor prend ses couleurs.
        if (name === 'opp') { if (data !== opp) { opp = data; scene.setOpp(opp); } return; }
        sound.play(name, data); scene.fx(name, data); if (name === 'bumper' && navigator.vibrate && sound.on) navigator.vibrate(8);
      },
      over: res => setTimeout(() => gameOver(res), 600),
    });
    game.start(n);
    sound.ambience(true);
    sound.play('start');
    started = performance.now();
    token = null;
    if (n === 1) api({ action: 'commencer' }).then(r => { token = r.tok || null; }).catch(() => {});
    ov('');
    paused = false;
  }

  // ---------------------------------------------------------------- commandes
  const keys = { L: new Set(['ShiftLeft', 'ArrowLeft', 'KeyZ', 'KeyA', 'KeyQ']), R: new Set(['ShiftRight', 'ArrowRight', 'KeyM', 'Slash', 'KeyP']) };
  let charge = null; // lanceur tiré : instant du début
  const gauge = $('[data-fl-gauge]');
  const plungerBtn = $('[data-fl-plunger]');
  const pullNow = () => (charge === null ? 0 : Math.min(1, (performance.now() - charge) / 1100));
  const startPull = () => { if (game && game.waiting && !paused && charge === null) charge = performance.now(); };
  const release = () => { if (charge === null) return; const p = pullNow(); charge = null; if (game) game.launch(0.55 + p * 0.45); };

  window.addEventListener('keydown', e => {
    if (!game || paused || e.repeat || e.target.closest('input,textarea,select')) return;
    if (keys.L.has(e.code)) { game.input('L', true); e.preventDefault(); }
    else if (keys.R.has(e.code)) { game.input('R', true); e.preventDefault(); }
    else if (['Space', 'Enter', 'ArrowDown'].includes(e.code)) { startPull(); e.preventDefault(); }
    else if (['ArrowUp', 'KeyN', 'KeyB'].includes(e.code)) { game.nudge(0); e.preventDefault(); }
  });
  window.addEventListener('keyup', e => {
    if (!game) return;
    if (keys.L.has(e.code)) game.input('L', false);
    else if (keys.R.has(e.code)) game.input('R', false);
    else if (['Space', 'Enter', 'ArrowDown'].includes(e.code)) release();
  });
  // Écran tactile : moitié gauche / moitié droite (plusieurs doigts à la fois).
  const touches = new Map();
  for (const zone of root.querySelectorAll('[data-fl-touch]')) {
    const side = zone.dataset.flTouch;
    zone.addEventListener('pointerdown', e => { if (!game || paused || e.pointerType === 'mouse') return; zone.setPointerCapture(e.pointerId); touches.set(e.pointerId, side); game.input(side, true); zone.classList.add('is-on'); e.preventDefault(); });
    const up = e => { if (!touches.has(e.pointerId)) return; touches.delete(e.pointerId); if (![...touches.values()].includes(side)) { game && game.input(side, false); zone.classList.remove('is-on'); } };
    zone.addEventListener('pointerup', up);
    zone.addEventListener('pointercancel', up);
    zone.addEventListener('contextmenu', e => e.preventDefault());
  }
  // Souris (ordinateur) : clic gauche et clic droit = batteurs gauche et droit, n'importe où sur le
  // plateau. Bouton du milieu (molette) : bille sur le lanceur → tenu puis relâché, la bille part ;
  // pendant la partie → on bourre la machine (comme « Bousculer »).
  const mouseSide = { 0: 'L', 2: 'R' };
  // Le navigateur ne détourne ni le bouton du milieu (défilement automatique) ni la molette, sauf
  // dans les fenêtres (règles, scores) qui doivent pouvoir défiler.
  root.addEventListener('mousedown', e => { if (e.button === 1) e.preventDefault(); });
  root.addEventListener('wheel', e => { if (!e.target.closest('.fl__ov')) e.preventDefault(); }, { passive: false });
  stage.addEventListener('mousedown', e => {
    if (!game || paused || e.target.closest('button,a,input,form,.fl__ov')) return;
    if (e.button === 1) {
      e.preventDefault();
      if (game.waiting) startPull(); else game.nudge(Math.random() < 0.5 ? -1 : 1);
      return;
    }
    if (mouseSide[e.button]) { game.input(mouseSide[e.button], true); e.preventDefault(); }
  });
  window.addEventListener('mouseup', e => {
    if (!game) return;
    if (e.button === 1) release();
    else if (mouseSide[e.button]) game.input(mouseSide[e.button], false);
  });
  stage.addEventListener('contextmenu', e => { if (!e.target.closest('.fl__ov')) e.preventDefault(); });
  stage.addEventListener('auxclick', e => { if (e.button === 1) e.preventDefault(); });
  plungerBtn.addEventListener('pointerdown', e => { plungerBtn.setPointerCapture(e.pointerId); startPull(); e.preventDefault(); e.stopPropagation(); });
  plungerBtn.addEventListener('pointerup', e => { release(); e.stopPropagation(); });
  plungerBtn.addEventListener('pointercancel', () => release());
  $('[data-fl-nudge]').addEventListener('click', () => game && !paused && game.nudge(Math.random() < 0.5 ? -1 : 1));
  // Secousse du téléphone : bousculer.
  let lastShake = 0;
  const motion = e => {
    const a = e.acceleration || {};
    const m = Math.hypot(a.x || 0, a.y || 0, a.z || 0);
    if (m > 13 && performance.now() - lastShake > 400 && game && !paused) { lastShake = performance.now(); game.nudge(Math.sign(a.x || 0)); }
  };
  async function askMotion() {
    try {
      if (window.DeviceMotionEvent && typeof DeviceMotionEvent.requestPermission === 'function') {
        if ((await DeviceMotionEvent.requestPermission()) !== 'granted') return;
      }
      window.addEventListener('devicemotion', motion);
    } catch (e) { /* refusé : le bouton « bousculer » reste */ }
  }

  // ---------------------------------------------------------------- boutons du haut
  // Son allumé d'office ; le bouton le coupe ou le rallume (choix gardé sur l'appareil). Le
  // navigateur ne laisse démarrer le son qu'après un geste : il part au clic sur « Jouer ».
  const soundBtn = $('[data-fl-sound]');
  let wantSound = store('son') !== '0';
  const showSound = () => { soundBtn.setAttribute('aria-pressed', wantSound ? 'true' : 'false'); soundBtn.title = wantSound ? U.soundOff : U.soundOn; soundBtn.querySelector('span').textContent = wantSound ? '🔊' : '🔇'; };
  const setSound = on => { wantSound = on; store('son', on ? '1' : '0'); sound.enable(on); if (on) sound.ambience(!!game && game.state === 'play' && !paused); showSound(); };
  soundBtn.addEventListener('click', () => setSound(!wantSound));
  showSound();
  $('[data-fl-full]').addEventListener('click', () => {
    if (document.fullscreenElement) document.exitFullscreen(); else if (root.requestFullscreen) root.requestFullscreen().catch(() => {});
  });
  $('[data-fl-pause]').addEventListener('click', () => { if (game && game.state !== 'over') { paused = true; ov('pause'); sound.ambience(false); } });
  root.querySelectorAll('[data-fl-go]').forEach(b => b.addEventListener('click', () => {
    const go = b.dataset.flGo;
    if (go === 'resume') { paused = false; ov(''); last = performance.now(); sound.ambience(true); return; }
    if (go === 'quit') { game = null; ov('start'); sound.ambience(false); return; }
    if (go === 'rules') { ov('rules'); return; }
    if (go === 'scores') { showScores(); return; }
    if (go === 'back') { ov(game && game.state === 'over' ? 'over' : game ? 'pause' : 'start'); return; }
  }));
  let nPlayers = 1;
  root.querySelectorAll('[data-fl-n]').forEach(b => b.addEventListener('click', () => {
    nPlayers = +b.dataset.flN;
    root.querySelectorAll('[data-fl-n]').forEach(x => x.setAttribute('aria-pressed', x === b ? 'true' : 'false'));
  }));
  root.querySelectorAll('[data-fl-play]').forEach(b => b.addEventListener('click', () => {
    if (wantSound && !sound.on) sound.enable(true);
    askMotion();
    newGame(b.dataset.flPlay === 'again' ? (game ? game.players.length : nPlayers) : nPlayers);
  }));
  document.addEventListener('visibilitychange', () => { if (document.hidden && game && game.state !== 'over' && game.state !== 'attract') { paused = true; ov('pause'); sound.ambience(false); } });

  // ---------------------------------------------------------------- fin de partie, tableau d'honneur
  const overBox = $('[data-fl-ov="over"]');
  async function gameOver(res) {
    ov('over');
    sound.ambience(false);
    const solo = res.length === 1, me = res[0];
    const best = +(store('best') || 0);
    // Parcours en Coupe (en solo) : coupes gagnées, chaque match joué, puis le match interrompu par la dernière bille.
    const rbox = overBox.querySelector('[data-fl-result]');
    rbox.hidden = !solo;
    if (solo) {
      rbox.dataset.kind = me.cups ? 'win' : '';
      rbox.querySelector('[data-fl-rkey]').textContent = me.cups ? U.cups.replace('{n}', me.cups) : U.cupRun;
      const row = (round, o, h, a, label, kind) => `<li class="is-${kind}"><i>${escapeHtml(D.text[round] || '')}</i><span>${escapeHtml(D.short)} ${h} – ${a} ${escapeHtml(o)}</span><em>${escapeHtml(label)}</em></li>`;
      const c = me.current;
      rbox.querySelector('[data-fl-matches]').innerHTML = me.matches.map(x => row(x.round, x.opp, x.home, x.away, U[x.result], x.result)).join('')
        + row(c.round, c.opp, c.home, c.away, U.stopped, 'stop');
    }
    if (solo && me.score > best) store('best', String(me.score));
    overBox.querySelector('[data-fl-final]').textContent = solo ? fmt(me.score) : '';
    overBox.querySelector('[data-fl-multi]').innerHTML = solo ? '' : res.slice().sort((a, b) => b.score - a.score).map((p, i) => `<li><b>${i + 1}.</b> ${U.p}${p.n} <span>${fmt(p.score)}</span></li>`).join('');
    overBox.querySelector('[data-fl-rec]').textContent = solo && me.score > best && best > 0 ? U.newRecord : solo && best ? U.record + ' ' + fmt(Math.max(best, me.score)) : '';
    const done = new Set(res.flatMap(p => p.done));
    overBox.querySelector('[data-fl-missions]').innerHTML = Object.entries(U.missions).map(([k, l]) => `<li class="${done.has(k) ? 'is-ok' : ''}"><i>${done.has(k) ? '✓' : '·'}</i>${l}</li>`).join('');
    const facts = D.facts;
    overBox.querySelector('[data-fl-fact]').textContent = facts.length ? facts[Math.floor(Math.random() * facts.length)] : '';
    const rankBox = overBox.querySelector('[data-fl-rank]');
    rankBox.textContent = '';
    overBox.querySelector('[data-fl-join]').hidden = true;
    lastScore = me.score;
    if (!solo) { rankBox.textContent = U.multiNoRank; return; }
    if (!token) return;
    pending = { tok: token, score: me.score, missions: [...done], dur: Math.round((performance.now() - started) / 1000) };
    token = null;
    await submit();
  }
  let pending = null;
  async function submit() {
    const rankBox = overBox.querySelector('[data-fl-rank]');
    try {
      const r = await api({ action: 'score', ...pending });
      if (r.ranked) { rankBox.textContent = r.label || ''; pending = null; }
      else if (r.error) { rankBox.textContent = r.error; pending = null; }
      overBox.querySelector('[data-fl-join]').hidden = !r.join;
      if (r.top) fillTable(overBox.querySelector('[data-fl-top]'), r.top, true);
    } catch (e) { /* hors ligne : le score reste sur l'appareil */ }
  }
  let lastScore = 0;
  function fillTable(el, rows, me) {
    el.innerHTML = rows.length ? rows.map(r => `<li class="${me && r.me ? 'is-me' : ''}"><b>${r.rank}</b><span>${escapeHtml(r.pseudo)}</span><em>${fmt(r.score)}</em></li>`).join('') : `<li class="is-empty">${U.empty}</li>`;
  }
  function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]); }
  async function showScores() {
    ov('scores');
    const box = $('[data-fl-ov="scores"]');
    box.querySelector('[data-fl-week]').innerHTML = `<li class="is-empty">${U.loading}</li>`;
    try {
      const r = await api({ action: 'classement' });
      fillTable(box.querySelector('[data-fl-week]'), r.week || [], true);
      fillTable(box.querySelector('[data-fl-all]'), r.all || [], true);
    } catch (e) { box.querySelector('[data-fl-week]').innerHTML = `<li class="is-empty">${U.offline}</li>`; }
  }
  $('[data-fl-share]').addEventListener('click', async () => {
    const text = U.shareText.replace('{n}', fmt(lastScore)) + ' ' + D.url;
    try {
      if (navigator.share) await navigator.share({ title: D.title, text, url: D.url });
      else { await navigator.clipboard.writeText(text); $('[data-fl-shared]').textContent = U.copied; }
    } catch (e) { /* partage annulé */ }
  });
  const joinForm = $('[data-fl-join]');
  joinForm.addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(joinForm), msg = joinForm.querySelector('[data-fl-joinmsg]');
    msg.textContent = '…';
    try {
      const r = await api({ action: 'inscrire', email: fd.get('email') || '', name: fd.get('name') || '' });
      msg.textContent = r.error || (r.member ? U.joined : U.mailed);
      // Compte ouvert sur cet appareil : le score de la partie qui vient de finir compte aussi.
      if (r.member && pending) { joinForm.hidden = true; await submit(); }
    } catch (err) { msg.textContent = U.offline; }
  });

  // ---------------------------------------------------------------- boucle
  let last = performance.now(), frames = 0, slow = 0;
  const plungerVisible = () => game && game.waiting && game.state === 'play';
  function loop(now) {
    // Jamais de temps négatif (première image : l'horodatage peut précéder « last »), ni de saut de plus de 50 ms.
    const dt = Math.max(0, Math.min(0.05, (now - last) / 1000));
    last = now;
    if (game && !paused) game.update(dt);
    const p = game ? game.p : phys;
    scene.frame(p, game ? game.lamps() : null, dt, now / 1000, pullNow());
    sound.update(game && !paused ? p.balls : []);
    scene.render();
    drawDmd();
    drawPlayers();
    const show = !!plungerVisible();
    if (plungerBtn.hidden === show) plungerBtn.hidden = !show;
    gauge.style.transform = `scaleY(${pullNow()})`;
    // Qualité automatique, mesurée pendant la partie seulement (au chargement, la préparation des
    // effets ralentit les premières images) : sur 120 images, plus de la moitié lentes → moins d'effets.
    if (game && game.state === 'play' && !paused && now - started > 2500) {
      frames++;
      if (dt > 0.028) slow++;
      if (frames >= 120) {
        if (slow / frames > 0.5 && scene.quality > 0) scene.setQuality(scene.quality - 1);
        frames = 0; slow = 0;
      }
    }
    requestAnimationFrame(loop);
  }
  ov('start');
  requestAnimationFrame(loop);
  root.classList.add('is-ready');
}
