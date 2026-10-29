// Règles d'un match de babyfoot : engagement par le trou de la bande, buts, chrono (deux mi-temps)
// ou premier à N buts, but en or (Coupe rejouée à égalité), balle morte, statistiques (tirs, arrêts,
// plus beau but). L'affichage et les commandes sont dans babyfoot.src.js.
import { LX, GOAL } from './table.js';
import { REACH, crossZ } from './ai.js';

/** Chrono « 2'14 ». */
export function clock(s) {
  s = Math.max(0, Math.floor(s));
  return Math.floor(s / 60) + '’' + String(s % 60).padStart(2, '0');
}

export class Match {
  /**
   * opt : { format: 'temps' | 'buts', half: durée d'une mi-temps (s), goals: buts pour gagner,
   *   golden: but en or si égalité au bout du temps, replay: attendre la fin du ralenti après un but,
   *   first: équipe qui engage, names: [[noms par barre et figurine], …] (Rejoue l'histoire) }
   * hooks : { fx(name, data), over(result) }
   */
  constructor(phys, opt = {}, hooks = {}) {
    this.p = phys;
    this.o = { format: 'temps', half: 180, goals: 5, golden: false, replay: false, first: 0, ...opt };
    this.h = hooks;
    this.score = [0, 0];
    this.stats = { shots: [0, 0], saves: [0, 0], best: [null, null], goals: [] };
    this.state = 'ready';
    this.half = 1;
    this.clockT = 0;       // temps de jeu de la mi-temps en cours
    this.total = 0;        // temps de jeu du match
    this.wall = 0;         // temps réel écoulé
    this.timer = 0;
    this.server = this.o.first;
    this.side = 1;
    this.slow = 0;
    this.shot = null;      // tir cadré en cours : { team, key }
    this.lastShot = '';
    this.goldenOn = false;
    this.winner = null;
  }

  fx(name, data) { if (this.h.fx) this.h.fx(name, data); }

  start() {
    this.state = 'kickoff';
    this.timer = 1.1;
    this.fx('whistle', { long: true });
  }

  /** Fin du ralenti (appelé par l'affichage, ou tout de suite sans ralenti). */
  replayDone() {
    if (this.state !== 'replay') return;
    this.afterGoal();
  }

  update(dt) {
    if (this.state === 'over' || this.state === 'ready') return;
    this.wall += dt;
    const p = this.p;
    if (this.state !== 'replay') p.update(dt);
    for (const e of p.events.splice(0)) this.event(e);
    switch (this.state) {
      case 'kickoff':
        this.timer -= dt;
        if (this.timer <= 0) {
          p.serve(this.server === 0 ? -3.5 : 3.5, this.side);
          this.side = -this.side;
          this.state = 'play';
          this.slow = 0;
        }
        break;
      case 'play': {
        this.clockT += dt;
        this.total += dt;
        // Balle morte : immobile hors de portée pendant 2,5 s. Balle gardée sous une figurine plus de
        // 10 s : engagement pour l'autre équipe (comme la règle des 10 secondes).
        const b = p.ball, v = Math.hypot(b.vx, b.vz);
        this.slow = v < 2.5 ? this.slow + dt : 0;
        const keeper = this.reachable();
        if (this.slow > (keeper ? 10 : 2.5)) this.deadBall(false, keeper);
        if (this.o.format === 'temps' && !this.goldenOn && this.clockT >= this.o.half) this.timeUp();
        // Prolongation du but en or : 2 minutes au plus, puis match nul.
        if (this.goldenOn && this.clockT >= 120) this.finish();
        break;
      }
      case 'goal':
        this.timer -= dt;
        if (this.timer <= 0) {
          if (this.o.replay) { this.state = 'replay'; this.fx('replay', this.lastGoal); } else this.afterGoal();
        }
        break;
      case 'half':
        this.timer -= dt;
        if (this.timer <= 0) { this.state = 'kickoff'; this.timer = 0.9; this.fx('whistle', { long: true }); }
        break;
    }
  }

  event(e) {
    const p = this.p;
    if (e.type === 'hit') {
      // Tir : frappe d'une figurine qui envoie la balle vers le but adverse ; cadré si elle y entrerait.
      const dir = p.rods[e.rod].dir;
      const key = e.rod + ':' + p.rods[e.rod].kickAt;
      if (this.shot && this.shot.team !== e.team && this.state === 'play') {
        this.stats.saves[e.team]++;
        this.fx('save', { team: e.team });
      }
      this.shot = null;
      if (e.kick && e.vx * dir > 60 && key !== this.lastShot) {
        // Tir : frappe vers le but adverse (cadrée ou presque) ; arrêt si l'adversaire la renvoie.
        const t = (dir * LX - e.x) / e.vx, z = crossZ(e.z, e.vz, t);
        if (Math.abs(z) < GOAL + 4) { this.lastShot = key; this.stats.shots[e.team]++; }
        if (Math.abs(z) < GOAL) this.shot = { team: e.team };
      }
      this.fx(e.kick ? 'kick' : 'touch', e);
    } else if (e.type === 'goal') {
      if (this.state !== 'play') return;
      this.shot = null;
      this.goal(e);
    } else if (e.type === 'out') {
      this.deadBall(true);
    } else {
      this.fx(e.type, e);
    }
  }

  goal(e) {
    const team = e.team, t = e.touch;
    this.score[team]++;
    const own = !!t && t.team !== team;
    const rod = t ? this.p.rods[t.rod] : null;
    const num = rod && !own ? rod.num[t.k] : null;
    const names = this.o.names && this.o.names[team];
    const name = !own && rod && names && names[t.rod] ? names[t.rod][t.k] || null : null;
    const g = { team, own, num, name, at: this.o.format === 'temps' ? clock(this.clockT) : clock(this.total), half: this.half, speed: Math.round(e.speed), score: this.score.slice() };
    this.stats.goals.push(g);
    if (!own && (!this.stats.best[team] || g.speed > this.stats.best[team].speed)) this.stats.best[team] = g;
    this.lastGoal = g;
    this.server = 1 - team;
    this.state = 'goal';
    this.timer = 1.6;
    this.fx('goal', g);
    if (this.goldenOn) this.endAfterGoal = true;
    if (this.o.format === 'buts' && this.score[team] >= this.o.goals) this.endAfterGoal = true;
  }

  afterGoal() {
    if (this.endAfterGoal) { this.finish(); return; }
    this.state = 'kickoff';
    this.timer = 0.8;
  }

  /** Équipe dont une figurine peut encore toucher la balle (null : personne). */
  reachable() {
    const b = this.p.ball;
    const r = this.p.rods.find(q => Math.abs(b.x - q.x) < REACH - 0.5 && q.offs.some(o => Math.abs(b.z - o) <= q.travel + 2.5));
    return r ? { team: r.team } : null;
  }

  /**
   * Balle morte : gardée trop longtemps (keeper), engagement pour l'adversaire ; hors de portée
   * dans une surface, rendue au gardien ; ailleurs, nouvel engagement.
   */
  deadBall(out = false, keeper = null) {
    const p = this.p, b = p.ball;
    this.slow = 0;
    this.fx('dead', {});
    if (keeper) {
      this.state = 'kickoff';
      this.timer = 0.8;
      this.server = 1 - keeper.team;
      b.live = false;
      return;
    }
    if (!out && Math.abs(b.x) > LX - 15) {
      const keeper = p.rods.find(r => r.role === 'G' && Math.sign(r.x) === Math.sign(b.x));
      p.place(keeper.x + keeper.dir * (REACH - 3.2), keeper.z);
      return;
    }
    this.state = 'kickoff';
    this.timer = 0.8;
    this.server = 1 - this.server;
    b.live = false;
  }

  timeUp() {
    this.fx('whistle', { long: true, end: this.half === 2 });
    if (this.half === 1) {
      this.half = 2;
      this.clockT = 0;
      this.state = 'half';
      this.timer = 2.4;
      this.server = 1 - this.o.first;
      this.p.ball.live = false;
      this.fx('half', {});
      return;
    }
    if (this.score[0] === this.score[1] && this.o.golden) {
      this.goldenOn = true;
      this.clockT = 0;
      this.state = 'half';
      this.timer = 2.4;
      this.server = this.o.first;
      this.p.ball.live = false;
      this.fx('golden', {});
      return;
    }
    this.finish();
  }

  finish() {
    if (this.state === 'over') return;
    this.state = 'over';
    this.p.ball.live = false;
    this.winner = this.score[0] > this.score[1] ? 0 : this.score[1] > this.score[0] ? 1 : null;
    this.fx('end', { winner: this.winner });
    if (this.h.over) this.h.over(this.result());
  }

  result() {
    return { score: this.score.slice(), winner: this.winner, stats: this.stats, time: Math.round(this.total), wall: Math.round(this.wall), golden: this.goldenOn };
  }

  /** Pour le tableau de score : chrono affiché. */
  clockText() {
    if (this.goldenOn) return clock(this.clockT);
    return this.o.format === 'temps' ? clock(Math.min(this.o.half, this.clockT)) : clock(this.total);
  }
}
