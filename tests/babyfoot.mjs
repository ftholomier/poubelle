// Babyfoot : table, physique, ordinateur et règles, sans navigateur (app/Resources/babyfoot/).
// Barres et figurines, balle qui roule et s'arrête, bandes, poteaux, but, frappe plus forte quand
// on appuie longtemps, figurine levée qui laisse passer la balle, barre tenue par le joueur,
// engagement pour l'équipe qui encaisse, premier à 5, mi-temps, but en or, balle morte, règle des
// 10 secondes, statistiques, et des matchs entiers ordinateur contre ordinateur : jamais de balle
// hors du jeu, les niveaux se classent dans l'ordre.
// Usage : node tests/babyfoot.mjs (code de sortie 1 en cas d'échec).
import { LX, LZ, R, GOAL, buildRods } from '../app/Resources/babyfoot/table.js';
import { Physics, VMAX } from '../app/Resources/babyfoot/physics.js';
import { Brain, LEVELS, ASSIST, SIMPLE, activeRod, crossZ, REACH, kickable } from '../app/Resources/babyfoot/ai.js';
import { Match, clock } from '../app/Resources/babyfoot/rules.js';

let fail = 0, ok = 0;
const eq = (label, got, exp) => {
  const pass = JSON.stringify(got) === JSON.stringify(exp);
  pass ? ok++ : fail++;
  if (!pass) console.log('ÉCHEC ' + label + '\n  obtenu : ' + JSON.stringify(got) + '\n  attendu : ' + JSON.stringify(exp));
};
const run = (ph, sec) => { for (let i = 0; i < sec * 1000; i++) ph.step(0.001); };
// Hasard reproductible pour les matchs entiers.
let seed = 42;
const rand = () => (seed = (seed * 16807) % 2147483647) / 2147483647;

// ------------------------------------------------------------------ table
const rods = buildRods();
eq('8 barres : 1-2-5-3 par équipe, 11 figurines chacune', [rods.length, rods.filter(r => r.team === 0).map(r => r.n), rods.filter(r => r.team === 0).reduce((s, r) => s + r.n, 0), rods.filter(r => r.team === 1).reduce((s, r) => s + r.n, 0)], [8, [1, 2, 5, 3], 11, 11]);
eq('numéros des figurines : 1 à 11', rods.filter(r => r.team === 0).flatMap(r => r.num).sort((a, b) => a - b), [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]);
// Les figurines du bord touchent une balle collée à la bande ; les figurines d'une barre couvrent toute la largeur.
eq('chaque barre couvre la largeur (hors gardien)', rods.filter(r => r.role !== 'G').every(r => {
  for (let z = -LZ + R; z <= LZ - R; z += 0.5) if (!r.offs.some(o => Math.abs(z - o) <= r.travel + 2)) return false;
  return true;
}), true);
eq('deux barres voisines se partagent tout l’espace entre elles', rods.slice(1).every((r, i) => Math.abs(r.x - rods[i].x) <= 2 * REACH), true);

// ------------------------------------------------------------------ physique
{
  const ph = new Physics();
  ph.rods.forEach(r => ph.lift(r.i, true));
  run(ph, 0.3);
  ph.place(0, 20, 120, 0);
  run(ph, 30);
  const b = ph.ball;
  eq('balle lancée seule : elle finit par s’arrêter, dans le terrain', [Math.hypot(b.vx, b.vz) < 0.5, Math.abs(b.z) <= LZ - R + 0.01, Math.abs(b.x) <= LX - R + 0.01 || b.goal !== 0], [true, true, true]);
}
{
  const ph = new Physics();
  ph.rods.forEach(r => ph.lift(r.i, true));
  run(ph, 0.3);
  ph.place(0, 0, 0, 150);
  let bande = 0;
  for (let i = 0; i < 400; i++) { ph.step(0.001); for (const e of ph.events.splice(0)) if (e.type === 'bande') bande++; }
  eq('bande : la balle rebondit et repart en sens inverse', [bande >= 1, ph.ball.vz < 0, ph.ball.z < LZ - R + 0.01], [true, true, true]);
}
{
  const ph = new Physics();
  ph.rods.forEach(r => ph.lift(r.i, true));
  run(ph, 0.3);
  ph.place(45, 2, 150, 0);
  let goal = null;
  for (let i = 0; i < 2000 && !goal; i++) { ph.step(0.001); for (const e of ph.events.splice(0)) if (e.type === 'goal') goal = e; }
  eq('balle dans la bouche du but : but de l’équipe 0 (cage de droite)', [goal && goal.team, goal && goal.side], [0, 1]);
  run(ph, 2);
  eq('la balle reste au fond de la cage', [ph.ball.x > LX, ph.ball.x < LX + 7.1, Math.abs(ph.ball.z) < GOAL], [true, true, true]);
}
{
  const ph = new Physics();
  ph.rods.forEach(r => ph.lift(r.i, true));
  run(ph, 0.3);
  ph.place(45, GOAL + 0.3, 120, 0);
  let post = false, goal = false;
  for (let i = 0; i < 1500; i++) { ph.step(0.001); for (const e of ph.events.splice(0)) { if (e.type === 'poteau') post = true; if (e.type === 'goal') goal = true; } }
  eq('balle sur le poteau : elle rebondit, pas de but', [post, goal], [true, false]);
}
{
  // Une figurine verticale arrête une balle qui arrive de face.
  const ph = new Physics();
  const mid = ph.rods.find(r => r.team === 1 && r.role === 'M');
  ph.rods.forEach(r => { if (r !== mid) ph.lift(r.i, true); });
  run(ph, 0.3);
  ph.place(mid.x - 20, mid.offs[2], 100, 0);
  run(ph, 0.4);
  eq('figurine verticale : la balle rebondit dessus', ph.ball.vx < 0 && ph.ball.x < mid.x, true);
}
{
  // Frappe : plus on appuie longtemps (puissance), plus la balle part vite ; elle part vers l'avant.
  const speeds = [0, 1].map(pw => {
    const ph = new Physics();
    const att = ph.rods.find(r => r.team === 0 && r.role === 'A');
    ph.rods.forEach(r => { if (r !== att) ph.lift(r.i, true); });
    run(ph, 0.3);
    ph.place(att.x + 3.5, att.offs[1], 0, 0);
    ph.strike(att.i, pw);
    run(ph, 0.08);
    return [Math.round(ph.ball.vx), ph.ball.vx > 0];
  });
  eq('frappe : vers l’avant, plus forte en appui long', [speeds[0][1], speeds[1][1], speeds[1][0] > speeds[0][0] * 1.5, speeds[1][0] <= VMAX], [true, true, true, true]);
  eq('tir fort et tir doux : des vitesses jouables', [speeds[0][0] > 50, speeds[1][0] > 150], [true, true]);
}
{
  // Barre levée : la balle passe dessous.
  const ph = new Physics();
  const mid = ph.rods.find(r => r.team === 0 && r.role === 'M');
  ph.rods.forEach(r => ph.lift(r.i, true));
  run(ph, 0.3);
  ph.place(mid.x - 12, mid.offs[2], 80, 0);
  run(ph, 0.4);
  eq('barre levée : la balle passe dessous', ph.ball.x > mid.x + 3, true);
  ph.lift(mid.i, false);
  run(ph, 0.5);
  eq('barre reposée : la figurine revient à la verticale', Math.abs(mid.ang) < 0.01, true);
}
{
  // Glissement : la barre suit la consigne, sans dépasser ses butées.
  const ph = new Physics();
  const d = ph.rods.find(r => r.role === 'D');
  ph.slideTo(d.i, 99);
  run(ph, 1);
  eq('barre : butée respectée', Math.abs(d.z - d.travel) < 1e-6, true);
  d.maxV = 50;
  ph.slideTo(d.i, -d.travel);
  run(ph, 0.1);
  eq('barre : vitesse de glissement limitée', Math.abs(d.z - (d.travel - 5)) < 0.05, true);
}

// ------------------------------------------------------------------ ordinateur
eq('rebonds sur les bandes (z au bout de t)', [Math.round(crossZ(0, 10, 1) * 10) / 10, Math.round(crossZ(30, 10, 1) * 10) / 10, Math.round(crossZ(0, -100, 1) * 10) / 10], [10, 24.6, 29.2]);
{
  const ph = new Physics();
  ph.place(-20, 5, 0, 0);
  const act = activeRod(ph, 0);
  eq('barre tenue : celle qui a la balle devant elle', [ph.rods[act].role, ph.rods[act].team], ['D', 0]);
  ph.place(-5, 5, 0, 0);
  eq('balle devant le milieu : le milieu', ph.rods[activeRod(ph, 0, act)].role, 'M');
  ph.place(56, 0, 0, 0);
  eq('balle derrière le gardien adverse : notre attaque', ph.rods[activeRod(ph, 0)].role, 'A');
}
{
  // L'aide ne touche pas à la barre tenue par le joueur.
  const ph = new Physics();
  const held = ph.rods.find(r => r.team === 0 && r.role === 'M');
  const brain = new Brain(ph, 0, ASSIST, { human: () => held.i, rand });
  ph.place(-20, 25, 0, 0);
  ph.slideTo(held.i, -5);
  for (let i = 0; i < 60; i++) { brain.update(1 / 60); ph.update(1 / 60); }
  const def = ph.rods.find(r => r.team === 0 && r.role === 'D');
  eq('aide : la barre tenue garde la place voulue, les autres suivent la balle', [Math.abs(held.z + 5) < 0.01, def.offs.some(o => Math.abs(def.z + o - 25) < 4)], [true, true]);
}

{
  // Commandes simples : la barre qui peut frapper, et le tir guidé vers le point visé.
  const ph = new Physics();
  const att = ph.rods.find(r => r.team === 0 && r.role === 'A');
  ph.rods.forEach(r => { if (r !== att) ph.lift(r.i, true); });
  run(ph, 0.3);
  ph.place(att.x + 3.5, att.offs[1], 0, 0);
  eq('commandes simples : la barre qui a la balle devant elle', kickable(ph, 0), att.i);
  ph.place(att.x - 12, att.offs[1], 0, 0);
  eq('commandes simples : balle hors de portée → aucune barre', [0, 1].map(t => kickable(ph, t)).includes(att.i), false);
  const dirs = [7, -7].map(aim => {
    const q = new Physics();
    const a2 = q.rods.find(r => r.team === 0 && r.role === 'A');
    q.rods.forEach(r => { if (r !== a2) q.lift(r.i, true); });
    run(q, 0.3);
    q.place(a2.x + 3.5, a2.offs[1], 0, 0);
    q.strike(a2.i, 0.75, aim);
    run(q, 0.08);
    return [q.ball.vx > 0, Math.sign(q.ball.vz), Math.hypot(q.ball.vx, q.ball.vz) >= 100];
  });
  eq('tir guidé : vers l’avant, du côté visé, assez fort', dirs, [[true, 1, true], [true, -1, true]]);
  const brain = new Brain(ph, 0, SIMPLE, { human: () => -1, rand });
  eq('commandes simples : l’aide tient toutes les barres', brain.human(), -1);
}

// ------------------------------------------------------------------ règles
{
  const ph = new Physics();
  const fx = [];
  const m = new Match(ph, { format: 'buts', goals: 2, first: 1 }, { fx: (n, d) => fx.push([n, d]) });
  m.start();
  m.update(1.2);
  eq('engagement : la balle entre par la bande, côté de l’équipe qui engage', [m.state, ph.ball.live, ph.ball.x > 0, Math.abs(ph.ball.z) > LZ - 4], ['play', true, true, true]);
  ph.ball.x = LX + 1; ph.ball.z = 0; ph.ball.vx = 50; ph.ball.goal = 0;
  ph.touchBy[0] = ph.touch = { team: 0, rod: 5, k: 1, t: ph.time };
  m.update(0.02);
  eq('but : score, buteur (n°10), engagement pour l’équipe qui encaisse', [m.score, m.lastGoal.num, m.server, m.state], [[1, 0], 10, 1, 'goal']);
  m.update(2);
  eq('sans ralenti : engagement après la joie', m.state, 'kickoff');
  m.update(1);
  ph.ball.x = -LX - 1; ph.ball.z = 1; ph.ball.goal = 0;
  ph.touchBy[0] = ph.touch = { team: 0, rod: 1, k: 0, t: ph.time };
  m.update(0.02);
  eq('contre son camp : le but compte pour l’adversaire', [m.score, m.lastGoal.own], [[1, 1], true]);
  m.update(2); m.update(1);
  ph.ball.x = LX + 1; ph.ball.z = 0; ph.ball.goal = 0;
  ph.touchBy[0] = { team: 0, rod: 3, k: 2, t: ph.time };
  ph.touch = { team: 1, rod: 7, k: 0, t: ph.time };
  m.update(0.02);
  eq('déviation du gardien adverse : le but reste au tireur (n°6)', m.lastGoal.num, 6);
  let res = null;
  m.h.over = r => { res = r; };
  m.update(2);
  eq('premier à 2 : match fini, vainqueur', [m.state, res && res.winner, res && res.score], ['over', 0, [2, 1]]);
  eq('plus beau but et buts du match', [res.stats.goals.length, res.stats.best[0] !== null], [3, true]);
}
{
  const ph = new Physics();
  const fx = [];
  const m = new Match(ph, { format: 'temps', half: 10, golden: true, first: 0 }, { fx: n => fx.push(n) });
  m.start();
  m.update(1.2);
  for (let i = 0; i < 12 * 20; i++) m.update(0.05);
  eq('mi-temps au bout du temps, l’autre équipe engage', [fx.includes('half'), m.half, m.server], [true, 2, 1]);
  for (let i = 0; i < 16 * 20; i++) m.update(0.05);
  eq('égalité en Coupe : but en or', [fx.includes('golden'), m.goldenOn, m.state !== 'over'], [true, true, true]);
  m.update(1);
  ph.ball.x = -LX - 1; ph.ball.z = 0; ph.ball.goal = 0;
  ph.touchBy[1] = { team: 1, rod: 2, k: 1, t: ph.time };
  let res = null;
  m.h.over = r => { res = r; };
  m.update(0.02); m.update(2);
  eq('but en or : le match s’arrête aussitôt', [m.state, res && res.winner, res && res.golden], ['over', 1, true]);
  eq('chrono affiché', [clock(134), clock(5.9)], ['2’14', '0’05']);
}
{
  // Balle morte hors de portée dans une surface : rendue au gardien.
  const ph = new Physics();
  const m = new Match(ph, { format: 'buts' }, {});
  m.start(); m.update(1.2);
  ph.place(58, 25, 0, 0);
  for (let i = 0; i < 4 * 60; i++) m.update(1 / 60);
  const keeper = ph.rods.find(r => r.role === 'G' && r.team === 1);
  eq('balle morte dans un coin : la pente la ramène, ou elle est rendue au gardien', Math.abs(ph.ball.x - keeper.x) < REACH + 3 || ph.ball.x < keeper.x, true);
  // Balle gardée plus de 10 s sous une figurine : engagement pour l'adversaire.
  const m2 = new Match(ph, { format: 'buts' }, {});
  m2.start(); m2.update(1.2);
  const mid = ph.rods.find(r => r.team === 0 && r.role === 'M');
  ph.place(mid.x + 3, mid.z + mid.offs[2], 0, 0);
  for (let i = 0; i < 11 * 60; i++) { ph.ball.vx = ph.ball.vz = 0; ph.ball.x = mid.x + 3; ph.ball.z = mid.z + mid.offs[2]; m2.update(1 / 60); if (m2.state !== 'play') break; }
  eq('règle des 10 secondes : engagement pour l’adversaire', [m2.state, m2.server], ['kickoff', 1]);
}

// ------------------------------------------------------------------ matchs entiers
function match(la, lb, format = 'buts') {
  const ph = new Physics();
  let res = null, out = 0;
  const m = new Match(ph, { format, half: 180, goals: 5 }, { over: r => { res = r; }, fx: n => { if (n === 'out') out++; } });
  const A = new Brain(ph, 0, la, { rand }), B = new Brain(ph, 1, lb, { rand });
  m.start();
  let t = 0, escaped = 0;
  while (!res && t < 1800) {
    A.update(1 / 60); B.update(1 / 60); m.update(1 / 60); t += 1 / 60;
    const b = ph.ball;
    if (b.live && (Math.abs(b.z) > LZ || Math.abs(b.x) > LX + 8)) escaped++;
  }
  return { res, out, escaped };
}
{
  const runs = [match(LEVELS[1], LEVELS[1]), match(LEVELS[2], LEVELS[0]), match(LEVELS[0], LEVELS[2], 'temps')];
  eq('matchs entiers : tous finis, jamais de balle hors du jeu', runs.map(r => [!!r.res, r.out, r.escaped]), [[true, 0, 0], [true, 0, 0], [true, 0, 0]]);
  eq('premier à 5 : un vainqueur à 5 buts', Math.max(...runs[0].res.score), 5);
  eq('Ligue 1 bat l’Amateur', [runs[1].res.winner, runs[2].res.winner], [0, 1]);
  eq('statistiques : des tirs et des arrêts comptés', runs.every(r => r.res.stats.shots[0] + r.res.stats.shots[1] > 0), true);
}

console.log(fail ? `${fail} échec(s), ${ok} réussi(s)` : `Tout est bon (${ok} vérifications)`);
process.exit(fail ? 1 : 0);
