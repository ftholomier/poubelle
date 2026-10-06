/*
 * Test de charge du quiz du club-house : une soirée simulée. N joueurs (200 au plus par partie)
 * et le grand écran interrogent /api/quiz-live (rythme des téléphones : 1 s pendant une question,
 * 2 à 2,5 s sinon ; ou un rythme fixe en ms), répondent en rafale à chaque question, pendant
 * qu'un visiteur charge des pages du musée. Affiche les temps de réponse et les erreurs.
 *
 * Usage (banc de test, voir docs/PLAN-VITESSE-LANCEMENT.md) :
 *   node tests/charge-quiz.js [joueurs=100] [questions=5] [rythme ms, 0 = comme les téléphones]
 *   BASE=http://127.0.0.1:8081 par défaut. Crée une partie dans storage/quizlive/ (effacée par le ménage).
 */
const { execFileSync } = require('child_process');
const fs = require('fs');
const N = +process.argv[2] || 100, QUESTIONS = +process.argv[3] || 5, POLL = process.argv[4] !== undefined ? +process.argv[4] : 1000;
const ROOT = require('path').resolve(__dirname, '..');
const BASE = process.env.BASE || 'http://127.0.0.1:8081';
const FICHE = execFileSync('php', ['-r', 'require "' + ROOT + '/app/bootstrap.php"; echo array_values(App\\Data\\Index::published("match"))[100]["path"];']).toString().trim();
const sleep = ms => new Promise(r => setTimeout(r, ms));
const stats = {};
const T0 = Date.now(), slow = [];
const rec = (k, ms, ok) => { (stats[k] ??= { t: [], err: 0 }); stats[k].t.push(ms); if (!ok) stats[k].err++; if (ms > 150) slow.push(((Date.now() - T0) / 1000).toFixed(1) + 's:' + k.split(' ')[0] + ':' + ms.toFixed(0)); };
async function api(body, kind) {
  const t = performance.now();
  try {
    const r = await fetch(BASE + '/api/quiz-live', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
    const j = await r.json();
    rec(kind, performance.now() - t, r.ok);
    return j;
  } catch (e) { rec(kind, performance.now() - t, false); return {}; }
}
async function page(path, kind) {
  const t = performance.now();
  try { const r = await fetch(BASE + path); await r.arrayBuffer(); rec(kind, performance.now() - t, r.ok); } catch (e) { rec(kind, performance.now() - t, false); }
}
(async () => {
  const [code, key] = execFileSync('php', ['-r', 'require "' + ROOT + '/app/bootstrap.php"; $r=App\\Services\\QuizLive::create(' + QUESTIONS + ',15,"fr","site"); echo $r["code"]," ",$r["key"];']).toString().trim().split(' ');
  const players = [];
  for (let i = 0; i < N; i++) { const j = await api({ action: 'rejoindre', code, name: 'Joueur ' + (i + 1) }, 'rejoindre'); players.push(j); }
  console.log('partie', code, '·', players.filter(p => p.pid).length, 'joueurs inscrits');
  let running = true;
  // Interrogation : chaque joueur toutes les POLL ms, décalés ; l'écran aussi.
  const loops = players.map((p, i) => (async () => { await sleep(Math.random() * (POLL || 2000)); while (running) { const t = performance.now(); p.last = await api({ action: 'etat', code, pid: p.pid, tok: p.tok }, 'etat (joueur)'); const ph = p.last && p.last.phase; const d = POLL === 0 ? (ph === 'question' ? 1000 : ph === 'lobby' ? 2500 : 2000) + Math.random() * 600 - 150 : POLL; await sleep(Math.max(0, d - (performance.now() - t))); } })());
  loops.push((async () => { while (running) { const t = performance.now(); await api({ action: 'ecran', code, key }, 'ecran'); await sleep(Math.max(0, 1000 - (performance.now() - t))); } })());
  // Un visiteur du musée pendant ce temps.
  loops.push((async () => { while (running) { await page('/', 'musée : accueil'); await page(FICHE, 'musée : fiche'); await page('/api/recherche?q=paille&suggest=1', 'musée : recherche'); await sleep(300); } })());
  const t0 = Date.now();
  await api({ action: 'suivant', code, key }, 'suivant');                   // première question (3 s de lecture)
  for (let q = 0; q < QUESTIONS; q++) {
    await sleep(3200);
    // Rafale : tout le monde répond entre 0,5 et 6 s.
    await Promise.all(players.map(async p => { await sleep(500 + Math.random() * 5500); await api({ action: 'repondre', code, pid: p.pid, tok: p.tok, choice: Math.floor(Math.random() * 4) }, 'repondre'); }));
    await sleep(1500);
    await api({ action: 'suivant', code, key }, 'suivant');                 // réponse → classement (ou podium)
    await sleep(3000);
    if (q < QUESTIONS - 1) { await api({ action: 'suivant', code, key }, 'suivant'); await sleep(200); }
  }
  running = false;
  await Promise.all(loops);
  const dur = (Date.now() - t0) / 1000;
  const g = JSON.parse(fs.readFileSync(ROOT + '/storage/quizlive/' + code + '.json', 'utf8'));
  const answered = Object.values(g.answers).reduce((a, x) => a + Object.keys(x).length, 0);
  console.log('durée', dur.toFixed(0), 's · phase finale', g.phase, '· réponses enregistrées', answered, '/', N * QUESTIONS);
  const pct = (a, p) => a[Math.min(a.length - 1, Math.floor(a.length * p))];
  let total = 0;
  for (const [k, s] of Object.entries(stats)) {
    const a = s.t.sort((x, y) => x - y); total += a.length;
    console.log(k.padEnd(20), String(a.length).padStart(6), 'req', ' p50', pct(a, .5).toFixed(0).padStart(5), 'ms  p95', pct(a, .95).toFixed(0).padStart(5), 'ms  p99', pct(a, .99).toFixed(0).padStart(5), 'ms  max', a[a.length - 1].toFixed(0).padStart(5), 'ms  erreurs', s.err);
  }
  console.log('débit total', (total / dur).toFixed(0), 'req/s');
  const by = {}; slow.forEach(x => { const sec = Math.floor(parseFloat(x)); by[sec] = (by[sec] || 0) + 1; });
  console.log('lents (>150 ms) par seconde :', JSON.stringify(by));
})();
