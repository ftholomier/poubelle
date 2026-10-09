/* Écrans de connexion du back-office : un match en fond, pour le plaisir. Vu d'en haut, de nuit :
   le FCSM (en jaune) fait tourner, avance, tire et marque ; « BUT ! », confettis, filet qui
   tremble, et le tableau d'affichage suit (score, minute, fin du match). Terrain couché sur un
   écran large, debout sur un téléphone (le FCSM attaque vers le haut). Si le visiteur a demandé
   moins d'animations : une image fixe, rien ne bouge. */
(() => {
  'use strict';
  const cv = document.querySelector('[data-pitch]');
  if (!cv || !cv.getContext) return;
  const ctx = cv.getContext('2d');
  const still = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const board = document.querySelector('[data-board]');
  const out = (k) => board && board.querySelector('[data-' + k + ']');

  const L = 105, WD = 68, MARGIN = 6;
  const YEL = '#F6C400', CREAM = '#F3EDDF';
  let W = 0, H = 0, s = 1, rot = false, ox = 0, oy = 0;

  const resize = () => {
    const dpr = Math.min(2, window.devicePixelRatio || 1);
    W = window.innerWidth; H = window.innerHeight;
    cv.width = Math.round(W * dpr); cv.height = Math.round(H * dpr);
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    rot = H > W * 1.1;
    const len = rot ? H : W, wid = rot ? W : H;
    s = Math.max(len / (L + 2 * MARGIN), wid / (WD + 2 * MARGIN));
    ox = (len - L * s) / 2;
    oy = (wid - WD * s) / 2;
  };
  // Mètres du terrain → pixels de l'écran.
  const P = (x, y) => (rot ? [oy + y * s, H - (ox + x * s)] : [ox + x * s, oy + y * s]);
  const rnd = (a, b) => a + Math.random() * (b - a);
  const clamp = (v, a, b) => Math.max(a, Math.min(b, v));
  const dist = (a, b) => Math.hypot(a.x - b.x, a.y - b.y);

  /* ------------------------------------------------------------ les joueurs */
  const mk = (bx, by, gk = false) => ({ bx, by, x: bx, y: by, tx: bx, ty: by, gk });
  const home = [mk(4, 34, true), mk(20, 10), mk(18, 26), mk(18, 42), mk(20, 58), mk(35, 20), mk(32, 34), mk(35, 48), mk(50, 12), mk(52, 34), mk(50, 56)];
  const away = [mk(101.5, 34, true), mk(88, 12), mk(90, 27), mk(90, 41), mk(88, 56), mk(75, 14), mk(77, 28), mk(77, 40), mk(75, 54), mk(62, 26), mk(62, 42)];
  const ball = { x: 52.5, y: 34, fx: 52.5, fy: 34, toX: 52.5, toY: 34, t: 0, d: 1, lob: false, trail: [] };

  let state = 'kickoff', timer = 0.9, holder = home[9], receiver = null, passes = 0, scorer = null;
  let score = [0, 0], clock = 0, ended = 0, celebrate = 0, net = 0, flash = '';
  const confetti = [];

  const setBoard = () => {
    if (!board) return;
    out('home').textContent = score[0];
    out('away').textContent = score[1];
    out('min').textContent = ended ? 'Fin' : Math.floor(clock) + '’';
  };

  const kickoff = () => {
    state = 'kickoff'; timer = 0.9; passes = 0; receiver = null;
    holder = home[9];
    holder.x = 52.5; holder.y = 34;
    ball.x = ball.fx = ball.toX = 52.6; ball.y = ball.fy = ball.toY = 34; ball.trail.length = 0;
  };

  const pass = (to, shot = false) => {
    const lead = shot ? 0 : 2.5;
    ball.fx = ball.x; ball.fy = ball.y;
    ball.toX = shot ? 106.4 : clamp(to.x + lead, 2, 103);
    ball.toY = shot ? 34 + rnd(-2.8, 2.8) : to.y + rnd(-1, 1);
    const d = Math.hypot(ball.toX - ball.fx, ball.toY - ball.fy);
    ball.d = Math.max(0.45, d / (shot ? 32 : 17));
    ball.t = 0;
    ball.lob = !shot && d > 26;
    receiver = shot ? null : to;
    state = shot ? 'shot' : 'pass';
    passes++;
  };

  const choose = () => {
    if (holder.x > 76 && ((passes >= 3 && Math.random() < 0.55) || passes >= 8)) {
      scorer = holder;
      return pass(null, true);
    }
    const options = home.filter((p) => p !== holder && !p.gk && p.x > holder.x - 8 && dist(p, holder) > 8 && dist(p, holder) < 36);
    const pool = options.length ? options : home.filter((p) => p !== holder && !p.gk);
    let total = 0;
    const w = pool.map((p) => (total += 1 + Math.max(0, p.x - holder.x) / 9));
    const r = Math.random() * total;
    pass(pool[w.findIndex((v) => v >= r)]);
  };

  const goal = () => {
    score[0]++;
    state = 'goal'; celebrate = 3.2; net = 1;
    flash = 'BUT !';
    const [gx, gy] = P(105, 34);
    for (let i = 0; i < 90; i++) {
      const a = rnd(0, Math.PI * 2), v = rnd(120, 520);
      confetti.push({ x: gx, y: gy, vx: Math.cos(a) * v, vy: Math.sin(a) * v - 260, c: [YEL, CREAM, '#2B4BB8'][i % 3], r: rnd(0, 6), vr: rnd(-8, 8), life: rnd(1.6, 2.8) });
    }
    setBoard();
  };

  /* ------------------------------------------------------------ simulation */
  const step = (dt) => {
    // Le chronomètre : une minute toutes les 1,2 s ; à 90, fin du match, puis on rejoue.
    if (ended) {
      ended -= dt;
      if (ended <= 0) { ended = 0; clock = 0; score = [0, 0]; kickoff(); }
      setBoard();
    } else {
      const before = Math.floor(clock);
      clock += dt / 1.2;
      if (clock >= 90 && state !== 'goal') { clock = 90; ended = 4; }
      if (Math.floor(clock) !== before) setBoard();
    }

    // Positions visées : chaque équipe coulisse avec le ballon.
    const shiftH = clamp((ball.x - 35) * 0.6, -6, 38), shiftA = clamp((ball.x - 70) * 0.4, -16, 8);
    home.forEach((p) => {
      p.tx = p.gk ? 4 : p.bx + shiftH;
      p.ty = p.gk ? clamp(34 + (ball.y - 34) * 0.2, 30, 38) : clamp(p.by + (ball.y - 34) * 0.22, 3, 65);
    });
    away.forEach((p) => {
      p.tx = p.gk ? 101.5 : p.bx + shiftA;
      p.ty = p.gk ? clamp(34 + (ball.y - 34) * 0.35, 30.6, 37.4) : clamp(p.by + (ball.y - 34) * 0.28, 3, 65);
    });
    if (state === 'goal' || state === 'reset') {
      // Tout le monde saute sur le buteur ; les autres rentrent la tête basse.
      home.forEach((p, i) => {
        if (state === 'goal' && scorer && !p.gk) {
          const a = (i / 11) * Math.PI * 2;
          p.tx = scorer.x + Math.cos(a) * 2.2; p.ty = scorer.y + Math.sin(a) * 2.2;
        } else { p.tx = p.bx; p.ty = p.by; }
      });
      if (state === 'reset') away.forEach((p) => { p.tx = p.bx; p.ty = p.by; });
    } else {
      // Le plus proche presse le porteur ; le receveur file vers le ballon ; le porteur avance.
      if (holder && state !== 'shot') {
        let near = null;
        away.forEach((p) => { if (!p.gk && (!near || dist(p, ball) < dist(near, ball))) near = p; });
        if (near) {
          const d = dist(near, ball) || 1;
          near.tx = ball.x + ((near.x - ball.x) / d) * 2.2; near.ty = ball.y + ((near.y - ball.y) / d) * 2.2;
        }
      }
      if (receiver) { receiver.tx = ball.toX; receiver.ty = ball.toY; }
      if (state === 'hold' && holder) { holder.tx = holder.x + 5; holder.ty = holder.y + (34 - holder.y) * 0.15; }
      if (state === 'shot') { const k = away[0]; k.ty = clamp(ball.toY, 30.6, 37.4); }
    }
    const move = (p, speed) => {
      const dx = p.tx - p.x, dy = p.ty - p.y, d = Math.hypot(dx, dy);
      if (d > 0.01) { const m = Math.min(d, speed * dt); p.x += (dx / d) * m; p.y += (dy / d) * m; }
    };
    home.forEach((p) => move(p, p === receiver || state === 'goal' ? 8 : 6));
    away.forEach((p) => move(p, p.gk && state === 'shot' ? 3.5 : 5.5));

    // Le ballon.
    if (state === 'pass' || state === 'shot') {
      ball.t = Math.min(1, ball.t + dt / ball.d);
      const e = 1 - (1 - ball.t) * (1 - ball.t);
      ball.x = ball.fx + (ball.toX - ball.fx) * e;
      ball.y = ball.fy + (ball.toY - ball.fy) * e;
      if (ball.t >= 1) {
        if (state === 'shot') goal();
        else { holder = receiver; receiver = null; state = 'hold'; timer = rnd(0.35, 0.9); }
      }
    } else if ((state === 'hold' || state === 'kickoff') && holder) {
      ball.x = holder.x + 0.9; ball.y = holder.y;
      timer -= dt;
      if (timer <= 0 && !ended) choose();
    } else if (state === 'goal') {
      celebrate -= dt;
      if (celebrate <= 0) { state = 'reset'; timer = 1.8; }
    } else if (state === 'reset') {
      timer -= dt;
      ball.x += (52.6 - ball.x) * Math.min(1, dt * 3); ball.y += (34 - ball.y) * Math.min(1, dt * 3);
      if (timer <= 0) kickoff();
    }
    ball.trail.push([ball.x, ball.y]);
    if (ball.trail.length > 14) ball.trail.shift();
    net = Math.max(0, net - dt * 0.7);
    for (let i = confetti.length - 1; i >= 0; i--) {
      const c = confetti[i];
      c.vy += 620 * dt; c.vx *= 1 - dt * 0.8; c.x += c.vx * dt; c.y += c.vy * dt; c.r += c.vr * dt; c.life -= dt;
      if (c.life <= 0) confetti.splice(i, 1);
    }
  };

  /* ------------------------------------------------------------ dessin */
  const line = (pts) => { ctx.beginPath(); pts.forEach(([x, y], i) => { const [a, b] = P(x, y); i ? ctx.lineTo(a, b) : ctx.moveTo(a, b); }); ctx.stroke(); };
  const rect = (x1, y1, x2, y2) => line([[x1, y1], [x2, y1], [x2, y2], [x1, y2], [x1, y1]]);
  const arc = (cx, cy, r, a0, a1) => { const pts = []; for (let i = 0; i <= 32; i++) { const a = a0 + ((a1 - a0) * i) / 32; pts.push([cx + Math.cos(a) * r, cy + Math.sin(a) * r]); } line(pts); };
  const dot = (x, y, r) => { const [a, b] = P(x, y); ctx.beginPath(); ctx.arc(a, b, Math.max(1.5, r * s), 0, Math.PI * 2); ctx.fill(); };

  const draw = () => {
    ctx.clearRect(0, 0, W, H);
    // Pelouse tondue en bandes.
    for (let i = 0; i < 14; i++) {
      if (i % 2) continue;
      const x1 = -MARGIN + (i * (L + 2 * MARGIN)) / 14, x2 = -MARGIN + ((i + 1) * (L + 2 * MARGIN)) / 14;
      const pts = [P(x1, -MARGIN), P(x2, -MARGIN), P(x2, WD + MARGIN), P(x1, WD + MARGIN)];
      ctx.fillStyle = 'rgba(255,255,255,.028)';
      ctx.beginPath(); pts.forEach(([a, b], k) => (k ? ctx.lineTo(a, b) : ctx.moveTo(a, b))); ctx.fill();
    }
    // Lignes à la chaux.
    ctx.strokeStyle = 'rgba(243,237,223,.2)';
    ctx.lineWidth = Math.max(1, 0.14 * s);
    ctx.lineJoin = 'round';
    rect(0, 0, L, WD);
    line([[52.5, 0], [52.5, WD]]);
    arc(52.5, 34, 9.15, 0, Math.PI * 2);
    rect(0, 13.84, 16.5, 54.16); rect(L - 16.5, 13.84, L, 54.16);
    rect(0, 24.84, 5.5, 43.16); rect(L - 5.5, 24.84, L, 43.16);
    arc(11, 34, 9.15, -0.927, 0.927); arc(94, 34, 9.15, Math.PI - 0.927, Math.PI + 0.927);
    arc(0, 0, 1, 0, Math.PI / 2); arc(L, 0, 1, Math.PI / 2, Math.PI); arc(0, WD, 1, -Math.PI / 2, 0); arc(L, WD, 1, Math.PI, Math.PI * 1.5);
    ctx.fillStyle = 'rgba(243,237,223,.25)';
    dot(52.5, 34, 0.3); dot(11, 34, 0.25); dot(94, 34, 0.25);
    // Buts et filets (celui d'en face tremble après un but).
    ctx.strokeStyle = 'rgba(243,237,223,.35)';
    rect(-2, 30.34, 0, 37.66);
    rect(L, 30.34, L + 2, 37.66);
    ctx.strokeStyle = 'rgba(243,237,223,.14)';
    ctx.lineWidth = 1;
    for (let k = 1; k < 8; k++) {
      const y = 30.34 + (k * 7.32) / 8, wob = net * Math.sin(k * 1.7 + net * 18) * 0.6;
      line([[L, y], [L + 2 + wob, y]]);
      line([[-2, y], [0, y]]);
    }
    for (let k = 1; k < 3; k++) line([[L + (k * 2) / 3 + net * Math.sin(k * 2 + net * 20) * 0.5, 30.34], [L + (k * 2) / 3 + net * Math.sin(k * 3 + net * 20) * 0.5, 37.66]]);
    // Traînée et ballon.
    ball.trail.forEach(([x, y], i) => { ctx.fillStyle = `rgba(246,196,0,${(i / ball.trail.length) * 0.22})`; dot(x, y, 0.35); });
    // Joueurs.
    away.forEach((p) => { ctx.fillStyle = p.gk ? 'rgba(120,200,140,.9)' : 'rgba(243,237,223,.82)'; dot(p.x, p.y, 0.95); });
    home.forEach((p) => {
      ctx.fillStyle = p.gk ? '#3E6FD8' : YEL;
      ctx.shadowColor = 'rgba(246,196,0,.55)'; ctx.shadowBlur = p === holder && state !== 'goal' ? 14 : 0;
      dot(p.x, p.y, 1);
      ctx.shadowBlur = 0;
    });
    const lift = ball.lob ? 1 + Math.sin(Math.PI * ball.t) * 0.9 : 1;
    ctx.fillStyle = 'rgba(0,0,0,.35)';
    if (lift > 1.05) dot(ball.x + (lift - 1) * 0.8, ball.y + (lift - 1) * 0.8, 0.45);
    ctx.fillStyle = '#fff';
    ctx.shadowColor = 'rgba(255,255,255,.8)'; ctx.shadowBlur = 8;
    dot(ball.x, ball.y, 0.5 * lift);
    ctx.shadowBlur = 0;
    // « BUT ! » et confettis.
    if (state === 'goal' && flash) {
      // Dans la place libre à côté de la fenêtre de connexion (au-dessus sur un téléphone).
      const box = document.querySelector('.auth__box');
      const r = box ? box.getBoundingClientRect() : { left: W / 2, right: W / 2, top: H / 2, bottom: H / 2 };
      const top = board && getComputedStyle(board).display !== 'none' ? board.getBoundingClientRect().bottom + 6 : 8;
      let tx, ty, room, maxH;
      if (W - r.right > 160) { tx = (r.right + W) / 2; ty = H * 0.47; room = W - r.right - 24; maxH = 150; }
      else { tx = W / 2; ty = (top + r.top) / 2; room = W - 24; maxH = (r.top - top) * 0.9; }
      const age = 3.2 - celebrate, a = Math.min(1, age * 4) * Math.min(1, celebrate / 0.8);
      ctx.save();
      ctx.font = `900 100px 'Big Shoulders Display', 'Arial Narrow', sans-serif`;
      const size = Math.min(maxH, 150, (room / ctx.measureText(flash).width) * 100) * (1 + Math.max(0, 0.25 - age) * 1.6);
      if (size >= 30) {
        ctx.globalAlpha = a;
        ctx.font = `900 ${size}px 'Big Shoulders Display', 'Arial Narrow', sans-serif`;
        ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
        ctx.fillStyle = YEL;
        ctx.shadowColor = 'rgba(0,0,0,.45)'; ctx.shadowOffsetX = 5; ctx.shadowOffsetY = 5;
        ctx.translate(tx, ty - size * 0.18); ctx.rotate(-0.06);
        ctx.fillText(flash, 0, 0);
        // Et dessous, plus petit : pour qui.
        ctx.font = `800 ${Math.round(size * 0.3)}px 'Big Shoulders Display', 'Arial Narrow', sans-serif`;
        ctx.fillStyle = CREAM;
        ctx.shadowOffsetX = 3; ctx.shadowOffsetY = 3;
        ctx.fillText('POUR LE FCSM', 0, size * 0.66);
      }
      ctx.restore();
    }
    confetti.forEach((c) => {
      ctx.save(); ctx.globalAlpha = Math.min(1, c.life); ctx.translate(c.x, c.y); ctx.rotate(c.r);
      ctx.fillStyle = c.c; ctx.fillRect(-4, -2, 8, 4); ctx.restore();
    });
  };

  /* ------------------------------------------------------------ boucle */
  resize();
  kickoff();
  setBoard();
  addEventListener('resize', () => { resize(); if (still) draw(); });
  if (still) { draw(); return; }
  let last = performance.now();
  const frame = (now) => {
    const dt = Math.min(0.05, (now - last) / 1000);
    last = now;
    step(dt);
    draw();
    requestAnimationFrame(frame);
  };
  requestAnimationFrame(frame);
})();
