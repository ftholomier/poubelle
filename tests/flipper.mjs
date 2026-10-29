// Flipper « au stade » (plan « Football Super League ») : plateau, physique et règles, sans navigateur
// (app/Resources/flipper/). Repères du plan présents, lancer, rampes (franchies, ratées), rails des
// trous, mini-terrain (but, gardien, joueurs adverses mobiles, bille qui retombe), poches, TRICK qui
// renvoie dans la rampe PASSE, match (chrono, mi-temps, résultat), Coupe (tours, élimination, coupe
// gagnée), passes et COMBO, attaque, verrous et multiball, multiplicateurs et HOLD, coup d'envoi,
// bille sauvée, tilt, fin de partie, joueurs à tour de rôle, batteurs vifs, bille jamais coincée au
// pied des batteurs, et un robot qui joue des parties entières : jamais de bille hors du plateau ni
// coincée.
// Usage : node tests/flipper.mjs (code de sortie 1 en cas d'échec).
import { buildTable, W, H } from '../app/Resources/flipper/table.js';
import { Physics, SPEED } from '../app/Resources/flipper/physics.js';
import { Game, PTS, SHOTS, MULTS, MODES, hexToPlayer } from '../app/Resources/flipper/rules.js';

let fail = 0, ok = 0;
const eq = (label, got, exp) => {
  const pass = JSON.stringify(got) === JSON.stringify(exp);
  pass ? ok++ : fail++;
  if (!pass) console.log('ÉCHEC ' + label + '\n  obtenu : ' + JSON.stringify(got) + '\n  attendu : ' + JSON.stringify(exp));
};
const run = (ph, sec, each) => { for (let i = 0; i < sec * 1000; i++) { if (each && each(i) === true) break; ph.step(0.001); } };
const tick = (g, sec, step = 1 / 60) => { for (let s = 0; s < sec; s += step) g.update(step); };

const t = buildTable();
const names = t.ramps.map(r => r.name);
const count = (arr, f) => arr.filter(f).length;

// ------------------------------------------------------------------ les repères du plan
eq('plan : 6 batteurs (2 principaux [27], 2 TACKLE [26], 2 mini-flippers [5])',
  [t.flippers.length, count(t.flippers, f => !f.tackle && !f.mini), count(t.flippers, f => f.tackle), count(t.flippers, f => f.mini && f.lv === 1)], [6, 2, 2, 2]);
eq('plan : 3 bumpers en triangle à gauche [18], 2 slingshots avec afficheur [23], kicker à droite [20]',
  [t.bumpers.length, t.bumpers.every(b => b.x < 150), t.slings.length, t.slings.every(s => !!s.screen), !!t.kicker, t.kicker.a[0] > 300], [3, true, 2, true, true, true]);
eq('plan : rampes PASSE [7], DRIBBLE [6], CENTRE [8], BALLON LONG [9], accès mini-terrain [13], rails des trous [4]',
  names, ['passe', 'dribble', 'centre', 'ballon-long', 'acces', 'trou-g', 'trou-d']);
eq('plan : PASSE et accès mènent au mini-terrain, DRIBBLE en part', [t.ramps[0].end.lv, t.ramps[4].end.lv, t.ramps[1].mouth.lv], [1, 1, 1]);
eq('plan : capteurs (inlanes [24], outlanes [25], UNE-DEUX [14] [16], LOCK [12] [15] [17], TRICK [11], trappe [19])',
  ['inlane', 'outlane', 'onetwo', 'lock', 'trick', 'trap'].map(tag => count(t.sensors, s => s.tag === tag)), [2, 2, 2, 3, 1, 1]);
eq('plan : poches LOCK attaquant à gauche, milieu au centre, défenseur à droite',
  t.sensors.filter(s => s.tag === 'lock').sort((a, b) => a.ref - b.ref).map(s => s.x).every((x, i, a) => !i || x > a[i - 1]), true);
eq('plan : mini-terrain [1] avec but et gardien [2], 10 joueurs sur 3 rangées [3], 2 trous [4]',
  [t.mini.rows.map(r => r.line + r.offs.length).join(' '), !!t.mini.goal, !!t.mini.keeper, t.mini.holes.length], ['def4 mil4 att2', true, true, 2]);
eq('plan : 10 hexagones de joueurs adverses [21], multiplicateurs du couloir de lancement [28], chrono [30], jauge COMBO [31]',
  [t.deco.hexes.length, t.deco.mults.map(m => m[0]).join(' '), !!t.deco.clock, !!t.deco.combo], [10, '10X 8X 6X 4X 2X HOLD', true, true]);
eq('plan : dimensions et lanceur [29] à droite', [W, H, t.plunger.x > 366, t.drainY < H], [400, 874, true, true]);
eq('hexagone → joueur du mini-terrain : attaquants, milieux, défenseurs', [0, 1, 2, 5, 6, 9].map(hexToPlayer), [8, 9, 4, 7, 0, 3]);

// ------------------------------------------------------------------ lancer
{
  const ph = new Physics(t), b = ph.addBall(t.plunger.x, t.plunger.ball);
  run(ph, 0.3); ph.launch(0); run(ph, 3);
  eq('lancer à vide : la bille revient sur le lanceur', ph.onPlunger(b), true);
  for (const p of [0.55, 0.8, 1]) {
    const q = new Physics(t), c = q.addBall(t.plunger.x, t.plunger.ball); let out = false;
    run(q, 0.3); q.launch(p); run(q, 4, () => { if (c.x < 330 && c.y < 600) out = true; });
    eq(`lancer à ${p} : la bille sort du couloir et entre en jeu`, [out, q.onPlunger(c)], [true, false]);
  }
}

// Portillon du couloir de lancement : une bille qui revient vers le couloir est arrêtée.
{
  const ph = new Physics(t), b = ph.addBall(345, 420, 300, 280);
  let back = false;
  run(ph, 0.6, () => { if (b.x > 368 && b.y > 470) back = true; });
  eq('portillon : la bille ne redescend pas dans le couloir de lancement', back, false);
}

// ------------------------------------------------------------------ rampes et rails
// Bille posée à l'entrée de chaque rampe, avec un élan donné : franchie (assez d'élan) ou ratée.
const rampTry = (id, v) => {
  const r = t.ramps[id], ph = new Physics(t);
  const b = ph.addBall(r.pts[0].x, r.pts[0].y, 0, 0, r.mouth ? r.mouth.lv : 0);
  let res = '';
  ph.on = ev => { if (!res && (ev === 'ramp-done' || ev === 'ramp-fail')) res = ev; };
  ph.enterRamp(b, id, v);
  run(ph, 8, () => !!res);
  return { res, b };
};
for (const [id, v, lv] of [[0, 1100, 1], [2, 1400, 0], [3, 1400, 0], [4, 900, 1]]) {
  const { res, b } = rampTry(id, v);
  eq(`rampe ${names[id]} prise avec élan : franchie, bille au niveau ${lv}`, [res, b.lv, b.state], ['ramp-done', lv, 'play']);
}
for (const id of [0, 2, 3, 4]) {
  const { res, b } = rampTry(id, 250);
  eq(`rampe ${names[id]} prise trop mollement : la bille redescend sur le plateau`, [res, b.lv, b.state], ['ramp-fail', 0, 'play']);
}
{
  const { res, b } = rampTry(1, 200);
  eq('rampe DRIBBLE : du mini-terrain jusqu’à l’inlane gauche', [res, b.lv, b.x < 100 && b.y > 580], ['ramp-done', 0, true]);
  for (const id of [5, 6]) {
    const ph = new Physics(t), c = ph.addBall(0, 0, 0, 0, 1); let res2 = '';
    ph.on = ev => { if (ev === 'ramp-done') res2 = ev; };
    ph.enterRamp(c, id, 520); run(ph, 8, () => !!res2);
    eq(`rail du trou ${id === 5 ? 'gauche' : 'droit'} : la bille revient à l’inlane droit`, [res2, c.lv, c.x > 280 && c.y > 580], ['ramp-done', 0, true]);
  }
}
// Entrée de rampe effleurée sans élan : pas d'entrée.
{
  const m = t.ramps[2].mouth, mx = (m.a[0] + m.b[0]) / 2, my = (m.a[1] + m.b[1]) / 2;
  const ph = new Physics(t); let entered = false;
  ph.on = ev => { if (ev === 'ramp-in') entered = true; };
  ph.addBall(mx + 4, my + 14, 0, -100); run(ph, 0.3);
  eq('entrée de rampe CENTRE effleurée sans élan : la bille n’y entre pas', entered, false);
}
// Tirs depuis le bas du plateau : chaque grande rampe se prend d'un batteur (tir droit, bonne vitesse).
const shoot = (from, to, v) => {
  const ph = new Physics(t), dx = to[0] - from[0], dy = to[1] - from[1], L = Math.hypot(dx, dy);
  ph.addBall(from[0], from[1], dx / L * v, dy / L * v); let first = '';
  ph.on = (ev, a, x) => { if (!first && ev === 'ramp-done') first = names[x]; };
  run(ph, 4, () => !!first);
  return first;
};
{
  const hits = name => (from, tos) => tos.flatMap(to => [1600, 2000, 2400].map(v => shoot(from, to, v))).filter(n => n === name).length;
  eq('CENTRE se prend du batteur droit', hits('centre')([205, 712], [[96, 364], [100, 362], [104, 361]]) > 0, true);
  eq('BALLON LONG se prend du batteur gauche', hits('ballon-long')([165, 712], [[276, 394], [282, 397], [288, 400]]) > 0, true);
  eq('l’accès du mini-terrain se prend du batteur gauche', hits('acces')([160, 712], [[143, 283], [146, 283], [149, 283]]) > 0, true);
}

// ------------------------------------------------------------------ mini-terrain
{
  const ph = new Physics(t);
  const x0 = ph.players.map(p => p.seg.ax);
  run(ph, 1);
  eq('les trois rangées de joueurs adverses vont et viennent', ph.players.every((p, i) => p.seg.ax !== x0[i]), true);
  // But : la voie dégagée (joueurs écartés, gardien sur le côté), tir vers la cage.
  const q = new Physics(t); let goal = 0;
  q.on = ev => { if (ev === 'goal') goal++; };
  for (const p of q.players) p.seg.on = false;
  q.keeper.min = q.keeper.max = 258; q.keeper.x = 258;
  q.addBall(215, 200, 0, -700, 1); run(q, 1.5);
  eq('but : la bille franchit la ligne entre les poteaux (une seule fois)', goal, 1);
  // Gardien : la bille tirée droit sur lui est arrêtée.
  const k = new Physics(t); let save = 0, goal2 = 0;
  k.on = ev => { if (ev === 'save') save++; if (ev === 'goal') goal2++; };
  for (const p of k.players) p.seg.on = false;
  k.keeper.min = k.keeper.max = 229; k.keeper.x = 229;
  k.addBall(229, 200, 0, -700, 1); run(k, 0.4);
  eq('gardien : arrêt sur un tir droit sur lui', [save > 0, goal2], [true, 0]);
  // Joueur adverse : la bille rebondit dessus.
  const j = new Physics(t); let hitP = -1;
  j.on = (ev, ref) => { if (ev === 'player' && hitP < 0) hitP = ref; };
  j.rowsStep();
  const p0 = j.players[0].seg; j.addBall((p0.ax + p0.bx) / 2, 240, 0, -500, 1); run(j, 0.15);
  eq('joueur adverse : la bille rebondit dessus', hitP >= 0, true);
  // Bille qui passe entre les mini-flippers : elle retombe sur le plateau, sous le mini-terrain.
  const d = new Physics(t); let out = false;
  d.on = ev => { if (ev === 'mini-out') out = true; };
  const b = d.addBall(230, 270, 0, 200, 1); run(d, 0.5);
  eq('bille qui passe entre les mini-flippers : elle retombe sur le plateau, sous le mini-terrain', [out, b.lv, b.y > 299], [true, 0, true]);
  // Les mini-flippers et les TACKLE bougent avec les batteurs du bas.
  const f = new Physics(t);
  f.setFlipper('L', true); f.setFlipper('R', true); run(f, 0.05);
  eq('un appui lève les 3 batteurs du même côté (principal, TACKLE, mini-flipper)', f.flippers.every(o => Math.abs(o.ang - o.up) < 1e-9), true);
}

// ------------------------------------------------------------------ batteurs vifs, bille jamais coincée
{
  const ph = new Physics(t), f = ph.flippers[0];
  ph.setFlipper('L', true); let ms = 0;
  while (Math.abs(f.ang - f.up) > 1e-9 && ms < 200) { ph.update(0.001); ms++; }
  eq('batteur levé en moins de 25 ms (temps réel)', ms < 25, true);
  eq('jeu au ralenti (vitesse de la physique)', SPEED < 1, true);
}
for (const [x, y, label] of [[118, 722, 'pied du batteur gauche'], [251, 722, 'pied du batteur droit'], [70, 690, 'bas de l’inlane gauche'], [300, 690, 'bas de l’inlane droit']]) {
  const ph = new Physics(t), b = ph.addBall(x, y);
  run(ph, 2);
  eq(`bille posée au ${label} : elle ne reste pas coincée`, Math.hypot(b.x - x, b.y - y) > 60 || b.state === 'gone', true);
}

// ------------------------------------------------------------------ règles
const opps = [{ name: 'Metz', ini: 'FCM' }, { name: 'Nancy', ini: 'ASNL' }, { name: 'Lens', ini: 'RCL' }, { name: 'Lyon', ini: 'OL' }, { name: 'Nantes', ini: 'FCN' }];
const newGame = (n = 1, extra = {}) => {
  const fx = [], over = [];
  const g = new Game(new Physics(t), t, { balls: 3, facts: ['Fait'], opps, ...extra }, { fx: (k, d) => fx.push([k, d]), over: r => over.push(r) });
  g.start(n);
  return { g, fx, over };
};
// Bille lancée et en jeu : le chrono du match tourne.
const inPlay = g => { g.launch(0.8); tick(g, 0.6); };
const live = g => g.p.balls.find(o => o.state === 'play') || g.p.balls[0];
// La bille en jeu tombe dans un capteur du plateau (poche, trou) : la physique le signale aux règles.
const into = (g, tag, ref) => {
  const s = t.sensors.find(o => o.tag === tag && (ref === undefined || o.ref === ref)), b = live(g);
  g.p.place(b, 0, s.x, s.y); b.vx = 0; b.vy = 0; b.state = 'play';
  g.update(1 / 60);
  return b;
};

// TRICK : la bille éjectée remonte le flanc gauche, droit dans la rampe PASSE.
{
  const { g } = newGame();
  inPlay(g);
  const prev = g.p.on;
  let res = '';
  g.p.on = (ev, a, x, ...r) => { if (ev === 'ramp-done' && !res) res = names[x]; prev(ev, a, x, ...r); };
  into(g, 'trick');
  tick(g, 8, 1 / 120);
  eq('TRICK : la bille ressort dans la rampe PASSE et monte sur le mini-terrain', res, 'passe');
}
{
  const { g, fx } = newGame();
  eq('début : bille sur le lanceur, 8es de finale, 45:00, adversaire annoncé', [g.waiting, g.lamps().round, g.clock(), fx.some(f => f[0] === 'opp')], [true, 0, '45:00', true]);
  // Coup d'envoi : les batteurs choisissent le tir avant le lancer.
  const s0 = g.skill; g.input('R', true); g.input('R', false);
  eq('coup d’envoi : le batteur droit change le tir proposé', g.skill, (s0 + 1) % SHOTS.length);
  g.input('L', true); g.input('L', false);
  eq('coup d’envoi : le batteur gauche revient au tir précédent', g.skill, s0);
  inPlay(g);
  eq('bille lancée : le chrono tourne', g.clock() < '45:00', true);
  const before = g.pl.score;
  g.shot(SHOTS[g.skill]);
  eq('coup d’envoi réussi : 500 000 points', g.pl.score - before, PTS.skill);
}
// Le chrono ne tourne pas quand la bille attend sur le lanceur.
{
  const { g } = newGame();
  tick(g, 3);
  eq('bille sur le lanceur : le chrono ne tourne pas', g.clock(), '45:00');
}
// Mi-temps puis fin du match (victoire : tour suivant ; nul : à rejouer ; défaite : retour en 8es).
{
  const { g, fx } = newGame();
  inPlay(g);
  const end = () => { g.pl.clock = 0.1; tick(g, 0.05); };
  end();
  eq('45:00 écoulées : mi-temps, la 2e repart de 45:00', [g.pl.half, g.clock() >= '44:55'], [2, true]);
  g.pl.home = 2; g.pl.away = 1;
  const opp1 = g.opp().name, sc = g.pl.score; end();
  eq('victoire 2-1 : tour suivant (quarts), nouvel adversaire, score remis à 0-0', [g.pl.round, g.opp().name !== opp1, g.pl.home, g.pl.away, g.pl.half], [1, true, 0, 0, 1]);
  eq('victoire : 1 000 000 par but d’écart, match noté', [g.pl.score - sc >= PTS.win, g.pl.matches[0].result, g.pl.matches[0].round], [true, 'win', 'r8']);
  eq('victoire : coup de sifflet et fête', fx.some(f => f[0] === 'win'), true);
  const opp2 = g.opp().name;
  g.pl.half = 2; g.pl.home = 1; g.pl.away = 1; end();
  eq('match nul : à rejouer contre le même adversaire, même tour', [g.pl.round, g.opp().name, g.pl.matches[1].result], [1, opp2, 'draw']);
  g.pl.half = 2; g.pl.home = 0; g.pl.away = 2; end();
  eq('défaite : éliminés, retour en 8es contre un nouvel adversaire', [g.pl.round, g.pl.matches[2].result, g.opp().name !== opp2], [0, 'loss', true]);
  g.pl.round = 3; g.pl.half = 2; g.pl.home = 1; g.pl.away = 0; const sc2 = g.pl.score; end();
  eq('finale gagnée : coupe, 5 000 000 de points, nouvelle épopée en 8es', [g.pl.cups, g.pl.score - sc2 >= PTS.cup, g.pl.round, g.pl.done.has('coupe')], [1, true, 0, true]);
}
// But : score, multiplicateur, HOLD, joueurs adverses revenus, bille rendue par le rail du trou droit.
{
  const { g, fx } = newGame();
  inPlay(g);
  const b = live(g);
  g.pl.elim = new Set([0, 1]); g.sync();
  g.p.place(b, 1, 229, 100); b.state = 'play';
  g.goal(b);
  eq('but : 1-0, minute notée, 250 000 points, multiplicateur 2X', [g.pl.home, g.pl.goals.length, g.pl.score >= PTS.goal, MULTS[g.pl.mult]], [1, 1, true, 2]);
  eq('but : fête (son, lumières)', fx.some(f => f[0] === 'goal'), true);
  tick(g, 2.7);
  eq('après le but : l’équipe adverse revient au complet, la bille redescend par le rail', [g.pl.elim.size, g.p.players.every(p => p.seg.on), b.state, b.ramp], [0, true, 'ramp', 6]);
  for (let i = 0; i < 6; i++) g.goal(b);
  eq('multiplicateur au maximum (10X) : HOLD allumé', [MULTS[g.pl.mult], g.pl.hold], [10, true]);
}
// Passes : un joueur éliminé (deux pendant l'attaque), COMBO, ligne éliminée → poche allumée.
{
  const { g } = newGame();
  inPlay(g);
  const b = live(g);
  g.sensor('onetwo', { vy: -300 }, 0);
  eq('UNE-DEUX en montant : une passe, un attaquant éliminé', [g.pl.elim.size, g.pl.combo, g.p.players[hexToPlayer(0)].seg.on], [1, 1, false]);
  g.sensor('onetwo', { vy: 300 }, 0);
  eq('UNE-DEUX en descendant : rien', g.pl.elim.size, 1);
  g.sensor('inlane', b, 0);
  g.pass();
  eq('attaque lancée (inlane) : la passe suivante élimine deux joueurs, ligne d’attaque à terre → poche attaquant allumée', [g.pl.elim.size, g.pl.lit], [3, [true, false, false]]);
  const sc = g.pl.score;
  g.pass(); g.pass(); g.pass();
  eq('5 passes : COMBO (jauge vidée, 250 000 points)', [g.pl.combo, g.pl.combos, g.pl.score - sc >= PTS.combo], [0, 1, true]);
  g.pass(); tick(g, 9);
  eq('la jauge COMBO se vide sans passe', g.pl.combo, 0);
}
// Verrous et multiball.
{
  const { g, fx } = newGame();
  inPlay(g);
  g.pl.lit = [true, true, true];
  for (const li of [0, 1]) {
    into(g, 'lock', li);
    tick(g, 1.5);
    eq(`poche ${li} allumée : bille verrouillée, nouvelle bille sur le lanceur`, [g.pl.locked[li], g.waiting, g.p.balls.length], [true, true, 1]);
    inPlay(g);
  }
  into(g, 'lock', 2);
  tick(g, 3);
  eq('troisième bille verrouillée : multiball à 3 billes', [g.multiball, g.p.balls.filter(o => o.state === 'play' || o.state === 'ramp').length, fx.some(f => f[0] === 'multiball')], [true, 3, true]);
  const sc = g.pl.score;
  g.goal(live(g));
  eq('but pendant le multiball : jackpot', g.pl.score - sc >= PTS.jackpot + PTS.goal, true);
  const { g: g2 } = newGame(); inPlay(g2);
  const c = into(g2, 'lock', 1); tick(g2, 1.2);
  eq('poche éteinte : la bille ressort aussitôt', [c.state, g2.pl.locked[1]], ['play', false]);
}
// TRICK : les récompenses tournent ; bille en plus une seule fois par partie.
{
  const { g } = newGame();
  inPlay(g);
  const got = [];
  for (let i = 0; i < MODES.length + 1; i++) { got.push(MODES[g.pl.mode % MODES.length]); into(g, 'trick'); tick(g, 3); }
  eq('TRICK : jackpot, super jackpot, coup d’envoi, bille en plus, temps additionnel, puis on recommence', got, [...MODES, MODES[0]]);
  eq('TRICK : une seule bille en plus', [g.pl.extra, g.pl.extraGiven], [1, true]);
}
// Outlane : super jackpot allumé, le but suivant le rapporte.
{
  const { g } = newGame(); inPlay(g);
  g.sensor('outlane', live(g), 0);
  const sc = g.pl.score; g.goal(live(g));
  eq('outlane : super jackpot sur le but suivant', [g.pl.superLit, g.pl.score - sc >= PTS.superJackpot], [false, true]);
}
// Bille sauvée, bille perdue (but encaissé), fin de partie.
{
  const { g, over } = newGame();
  inPlay(g);
  g.drain(live(g));
  eq('bille perdue dans les 10 s : sauvée, pas de but encaissé', [g.pl.away, g.pl.ball], [0, 1]);
  tick(g, 2);
  for (let k = 0; k < 3; k++) {
    g.saveUntil = 0;
    g.drain(live(g));
    tick(g, 5);
    if (k < 2) { eq(`bille ${k + 1} perdue : but de l’adversaire, bille suivante`, [g.pl.away, g.pl.ball, g.waiting], [k + 1, k + 2, true]); inPlay(g); }
  }
  eq('3 billes perdues : fin de partie, avec le match en cours', [g.state, over.length, over[0] && over[0][0].current.away], ['over', 1, 3]);
}
// Bonus × multiplicateur en fin de bille ; le multiplicateur repart à 1 sans HOLD.
{
  const { g } = newGame(); inPlay(g);
  g.saveUntil = 0; g.pl.bonus = { ramps: 2, passes: 0, bumpers: 0, goals: 0 }; g.pl.mult = 2;
  const sc = g.pl.score; g.drain(live(g)); tick(g, 5);
  eq('bonus de fin de bille × multiplicateur (4X), puis multiplicateur remis à 1', [g.pl.score - sc, g.pl.mult], [2 * 20000 * 4, 0]);
}
// Tilt : batteurs coupés.
{
  const { g } = newGame(); inPlay(g);
  for (let i = 0; i < 4; i++) g.nudge(1);
  eq('trop de coups sur la caisse : TILT, batteurs coupés', [g.tilted, g.p.flippers.every(f => !f.pressed)], [true, true]);
  g.input('L', true);
  eq('TILT : les batteurs ne répondent plus', g.p.flippers.some(f => f.pressed), false);
}
// Plusieurs joueurs : à tour de rôle, chacun son match.
{
  const { g } = newGame(2);
  inPlay(g);
  g.pl.home = 1; g.saveUntil = 0;
  g.drain(live(g)); tick(g, 5);
  eq('2 joueurs : au tour du joueur 2, qui a son propre match', [g.cur, g.pl.home, g.pl.away], [1, 0, 0]);
}
eq('points de chaque élément : multiples de 10 (contrôle du tableau d’honneur)', Object.values(PTS).every(v => v % 10 === 0), true);

// ------------------------------------------------------------------ robot : des parties entières
// Le robot lance la bille et donne un coup de batteur quand elle arrive dessus (principal, TACKLE ou
// mini-flipper). Jamais de bille hors du plateau, jamais de bille immobile plus de 3 s hors du lanceur.
{
  let escaped = 0, stuck = 0, games = 0, ramps = 0, goals = 0;
  for (let n = 0; n < 6; n++) {
    const { g, over } = newGame();
    g.on.fx = k => { if (k === 'ramp') ramps++; if (k === 'goal') goals++; };
    const still = new Map(), hand = { L: 0, R: 0 };
    for (let s = 0; s < 900 && !over.length; s += 1 / 60) {
      if (g.waiting && g.state === 'play') g.launch(0.6 + Math.random() * 0.4);
      for (const side of ['L', 'R']) {
        // Coup de batteur : levé 0,15 s, puis baissé au moins 0,2 s.
        const near = g.p.balls.some(b => b.state === 'play' && g.p.flippers.some(f => f.side === side && (f.lv || 0) === b.lv && Math.hypot(b.x - f.px, b.y - f.py) < f.len + 14 && b.vy > -50));
        if (hand[side] <= 0 && near) hand[side] = 0.35;
        g.input(side, hand[side] > 0.2);
        hand[side] -= 1 / 60;
      }
      g.update(1 / 60);
      for (const b of g.p.balls) {
        if (b.state !== 'play') { still.delete(b); continue; }
        if (!(b.x > -5 && b.x < W + 5 && b.y > -5 && b.y < H) || Number.isNaN(b.x)) escaped++;
        const v = Math.hypot(b.vx, b.vy), t0 = v < 5 && !g.p.onPlunger(b) ? (still.get(b) || 0) + 1 / 60 : 0;
        still.set(b, t0);
        if (t0 > 3) { stuck++; still.set(b, 0); console.log('  bille immobile en', Math.round(b.x), Math.round(b.y), 'niveau', b.lv); }
      }
    }
    if (over.length) games++;
  }
  eq('robot : aucune bille hors du plateau', escaped, 0);
  eq('robot : aucune bille coincée', stuck, 0);
  eq('robot : des parties qui se terminent, des rampes franchies', [games > 0, ramps > 0], [true, true]);
  console.log(`robot : ${games}/6 parties finies, ${ramps} rampes, ${goals} buts`);
}

console.log(`${ok} OK, ${fail} échec(s)`);
process.exit(fail ? 1 : 0);
