// Flipper : physique et règles, sans navigateur (app/Resources/flipper/). Lancer, rampe, cibles,
// soucoupe, bille sauvée, multiball et jackpot, tilt, fin de partie, joueurs à tour de rôle, bille
// jamais coincée au pied des batteurs, jeu au ralenti mais batteurs vifs, un coup par mouvement de
// batteur, et un robot qui joue des billes entières : jamais de bille hors du plateau ni coincée.
// Usage : node tests/flipper.mjs (code de sortie 1 en cas d'échec).
import { buildTable } from '../app/Resources/flipper/table.js';
import { Physics, SPEED } from '../app/Resources/flipper/physics.js';
import { Game, PTS } from '../app/Resources/flipper/rules.js';

let fail = 0, ok = 0;
const eq = (label, got, exp) => {
  const pass = JSON.stringify(got) === JSON.stringify(exp);
  pass ? ok++ : fail++;
  if (!pass) console.log('ÉCHEC ' + label + '\n  obtenu : ' + JSON.stringify(got) + '\n  attendu : ' + JSON.stringify(exp));
};
const run = (ph, sec, each) => { for (let i = 0; i < sec * 1000; i++) { if (each) each(i); ph.step(0.001); } };

const t = buildTable();
eq('lettres et chiffres du plateau', [t.letters, t.digits, t.targets.length, t.lanes.map(l => l.digit).join('')], ['SOCHAUX', '1928', 7, '1928']);
eq('autres lettres (Paris)', buildTable({ letters: 'paris', digits: '1970' }).targets.map(g => g.letter).join(''), 'PARIS');

// Lancer : trop faible, la bille redescend sur le lanceur ; assez fort, elle atteint le haut du plateau.
{
  const ph = new Physics(t), b = ph.addBall(376, 789);
  run(ph, 0.3); ph.launch(0); run(ph, 3);
  eq('lancer trop faible : la bille revient sur le lanceur', ph.onPlunger(b), true);
  for (const p of [0.85, 1]) {
    const q = new Physics(t), c = q.addBall(376, 789); let top = false;
    q.on = (ev, a) => { if (ev === 'sensor' && a === 'lane') top = true; if (ev === 'bumper' || (ev === 'hit' && a === 'rail')) top = true; };
    run(q, 0.3); q.launch(p); run(q, 4);
    eq(`lancer à ${p} : couloirs du haut, leurs séparations ou bumpers atteints`, top, true);
  }
}

// Portillon : une bille qui retombe dessus roule vers le plateau, elle ne reste pas posée.
{
  const ph = new Physics(t), b = ph.addBall(380, 150, 0, 0);
  run(ph, 2);
  eq('pas de bille posée sur le portillon', b.y > 230 && b.x < 362, true);
}

// Bille posée au-dessus d'un batteur au repos, ou au bas d'un guide de couloir : elle roule et part.
{
  for (const [x, y, label] of [[115, 722, 'pied du batteur gauche'], [259, 722, 'pied du batteur droit'], [70, 700, 'guide du couloir gauche']]) {
    const ph = new Physics(t), b = ph.addBall(x, y);
    run(ph, 1);
    eq(`bille posée au ${label} : elle ne reste pas coincée`, Math.hypot(b.x - x, b.y - y) > 80 || b.state === 'gone', true);
  }
}

// Vitesse : la bille vit au ralenti, les batteurs montent en temps réel en moins de 25 ms.
{
  const ph = new Physics(t);
  ph.update(0.05);
  eq('jeu au ralenti (SPEED)', Math.abs(ph.time - 0.05 * SPEED) < 0.002, true);
  const q = new Physics(t), f = q.flippers[0];
  q.setFlipper('L', true);
  for (let i = 0; i < 5; i++) q.update(0.005);
  eq('batteur levé en 25 ms réelles', f.ang, f.up);
}

// Un seul coup par mouvement de batteur : la bille frappée n'est pas re-frappée pendant la montée.
{
  const ph = new Physics(t), b = ph.addBall(150, 742);
  let hits = 0;
  ph.on = ev => { if (ev === 'flip-hit') hits++; };
  ph.setFlipper('L', true);
  run(ph, 0.2);
  eq('un coup par mouvement de batteur', [hits, b.vy < -500], [1, true]);
}

// Rampe : franchie avec un tir franc, redescend sinon.
{
  const res = v => { const ph = new Physics(t); let r = ''; ph.on = ev => { if (ev === 'ramp-done' || ev === 'ramp-fail') r = ev; }; const b = ph.addBall(76, 452, 0, -v); ph.enterRamp(b); run(ph, 3); return r; };
  eq('rampe : tir faible', res(900), 'ramp-fail');
  eq('rampe : tir franc', res(1800), 'ramp-done');
}

// Batteur : un tir depuis le batteur droit peut franchir la rampe.
{
  let reached = false;
  for (let d = 24; d <= 44 && !reached; d += 2) {
    const ph = new Physics(t), b = ph.addBall(318, 600);
    let fired = false;
    ph.on = (ev, a, v) => { if (ev === 'ramp-mouth' && v > 150) ph.enterRamp(a); if (ev === 'ramp-done') reached = true; };
    run(ph, 3, () => {
      const f = ph.flippers[1], along = (b.x - f.px) * Math.cos(f.ang) + (b.y - f.py) * Math.sin(f.ang);
      if (!fired && b.y > 690 && along >= d) { ph.setFlipper('R', true); fired = true; }
    });
  }
  eq('batteur droit : la rampe est franchissable', reached, true);
}

// Cible : tombe quand on la frappe de face, pas de dos.
{
  const ph = new Physics(t); let hit = -1;
  ph.on = (ev, a) => { if (ev === 'target') hit = a; };
  ph.addBall(160, 310, -900, 0); run(ph, 0.2);
  eq('cible de gauche frappée de face', [hit, ph.segs.find(s => s.tag === 'target' && s.ref === 0).on], [0, false]);
  const q = new Physics(t); let h2 = -1;
  q.on = (ev, a) => { if (ev === 'target') h2 = a; };
  q.addBall(90, 310, 900, 0); run(q, 0.1);
  eq('cible frappée de dos : reste debout', h2, -1);
}

// Règles : partie complète, missions, multiball et jackpot.
const newGame = (n = 1) => {
  const ph = new Physics(t); const fx = []; let over = null;
  const g = new Game(ph, t, { balls: 3, facts: ['FAIT'], text: {} }, { fx: (k) => fx.push(k), over: r => { over = r; } });
  g.start(n);
  return { ph, g, fx, get over() { return over; } };
};
const tick = (g, sec) => { for (let i = 0; i < sec * 100; i++) g.update(0.01); };
{
  const { ph, g } = newGame();
  tick(g, 0.5);
  eq('début : bille sur le lanceur', [g.waiting, ph.balls.length, ph.onPlunger(ph.balls[0])], [true, 1, true]);
  g.launch(1); tick(g, 0.05);
  eq('lancée : bille sauvée allumée, « Tirez le lanceur » effacé', [g.waiting, g.lamps().save, g.screen().kind], [false, true, 'score']);
  // Couloirs : les quatre allumés → bonus ×2.
  const b = ph.balls[0];
  [0, 1, 2, 3].forEach(i => g.event('sensor', 'lane', b, undefined, i));
  eq('1-9-2-8 complet : multiplicateur ×2', [g.pl.mult, g.pl.done.has('lanes')], [2, true]);
  // Cibles : toutes abattues → centre de formation rallumé ; la 2e fois, une bille en plus.
  g.pl.kickback = false;
  t.targets.forEach((_, i) => g.event('target', i, b, 100));
  eq('lettres complètes : centre de formation rallumé', [g.pl.kickback, g.pl.lettersDone], [true, 1]);
  tick(g, 1);
  t.targets.forEach((_, i) => g.event('target', i, b, 100));
  eq('lettres deux fois : bille en plus', g.pl.extra, 1);
  // Rampe ×5 → finale allumée ; Coupe (soucoupe) → multiball à 3 billes.
  for (let i = 0; i < 5; i++) g.event('ramp-done', b);
  eq('5 rampes : finale allumée', [g.pl.cup, g.lamps().finale], [5, true]);
  b.vx = 0; b.vy = 100; g.event('sensor', 'saucer', b);
  tick(g, 3.5);
  eq('Coupe en finale : multiball, 3 billes', [g.multiball, ph.balls.filter(x => x.state !== 'gone').length], [true, 3]);
  const before = g.pl.score;
  g.event('ramp-done', ph.balls[0]);
  eq('rampe en multiball : jackpot', [g.pl.score - before >= PTS.jackpot, g.pl.done.has('jackpot')], [true, true]);
  // Fin du multiball : il ne reste qu'une bille → Coupe gagnée.
  g.saveUntil = 0;
  for (let k = 0; k < 2; k++) { const x = ph.balls.find(o => o.state !== 'gone'); x.state = 'gone'; g.event('drain', x); }
  eq('fin du multiball : Coupe gagnée', [g.multiball, g.pl.done.has('coupe'), g.pl.cupsWon, g.pl.cup], [false, true, 1, 0]);
}

// Bille sauvée, puis tilt, puis fin de partie après 3 billes (la bille en plus compte).
{
  const state = newGame(), { ph, g } = state;
  tick(g, 0.3); g.launch(1); tick(g, 0.05);
  let b = ph.balls[0]; b.state = 'gone'; g.event('drain', b);
  tick(g, 1.5);
  eq('perdue dans les 10 s : bille sauvée (rendue)', [g.pl.ball, ph.balls.filter(x => x.state !== 'gone').length], [1, 1]);
  g.saveUntil = 0;
  for (let i = 0; i < 5; i++) g.nudge(1);
  eq('trop bousculé : TILT', [g.tilted, g.lamps().tilted], [true, true]);
  let guard = 0;
  while (!state.over && guard++ < 20) {
    for (const x of ph.balls.filter(x => x.state !== 'gone')) { x.state = 'gone'; g.event('drain', x); }
    tick(g, 3.2);
    g.saveUntil = 0;
  }
  eq('fin de partie après 3 billes', [!!state.over, g.state, g.pl.ball], [true, 'over', 4]);
}

// Deux joueurs : à tour de rôle.
{
  const { ph, g } = newGame(2);
  tick(g, 0.3); g.saveUntil = 0;
  const order = [];
  for (let k = 0; k < 4; k++) {
    order.push(g.pl.n);
    for (const x of ph.balls.filter(x => x.state !== 'gone')) { x.state = 'gone'; g.saveUntil = 0; g.event('drain', x); }
    tick(g, 3.2);
  }
  eq('deux joueurs : J1, J2, J1, J2', order, [1, 2, 1, 2]);
}

// Robot : des billes entières, jamais hors du plateau ni coincées.
{
  let escaped = 0, stuck = 0, events = 0;
  for (let n = 0; n < 12; n++) {
    const ph = new Physics(t), b = ph.addBall(376, 789);
    ph.on = (ev, a, bb, c) => { events++; if (ev === 'ramp-mouth' && bb > 150) ph.enterRamp(a); if (ev === 'sensor' && a === 'saucer') { ph.hold(bb, t.saucer.x, t.saucer.y); bb.release = ph.time + 1; } };
    run(ph, 0.3); ph.launch(0.8 + (n % 5) * 0.05);
    let lastMove = ph.time, lx = b.x, ly = b.y, hold = { L: 0, R: 0 };
    for (let i = 0; i < 60000 && b.state !== 'gone'; i++) {
      if (b.state === 'held' && ph.time > b.release) ph.release(b, -160, 260);
      for (const side of ['L', 'R']) {
        const want = b.state === 'play' && b.y > 650 && b.y < 765 && b.vy > -150 && (side === 'L' ? b.x < 187 && b.x > 80 : b.x >= 187 && b.x < 295);
        if (want && !hold[side]) { hold[side] = ph.time; ph.setFlipper(side, true); }
        if (hold[side] && ph.time - hold[side] > 0.22) { ph.setFlipper(side, false); if (ph.time - hold[side] > 0.5) hold[side] = 0; }
      }
      ph.step(0.001);
      if (b.x < -20 || b.x > 420 || b.y < -20 || Number.isNaN(b.x)) { escaped++; break; }
      if (Math.hypot(b.x - lx, b.y - ly) > 3) { lastMove = ph.time; lx = b.x; ly = b.y; }
      if (ph.time - lastMove > 6 && b.state === 'play' && !ph.onPlunger(b)) { stuck++; break; }
    }
  }
  eq('robot : aucune bille hors du plateau, aucune coincée', [escaped, stuck], [0, 0]);
  eq('robot : la bille vit sa vie (événements)', events > 100, true);
}

console.log(`${ok} réussis, ${fail} échecs`);
process.exit(fail ? 1 : 0);
