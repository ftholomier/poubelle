// Règles du flipper : joueurs (1 à 4, à tour de rôle), billes, missions (épopée en Coupe sur la
// rampe 1, Promotion sur la rampe 2, lettres, couloirs, orbite, combos), points, multiball, bille
// sauvée, tilt, bonus de fin de bille, messages de l'écran à points. Aucun rendu ici.

/** Points de chaque élément. */
export const PTS = {
  bumper: 1000, sling: 110, lane: 5000, lanesAll: 25000, skill: 500000, target: 7500, letters: 100000,
  ramp: 50000, rampStep: 25000, saucer: 25000, finale: 250000, jackpot: 1000000, kickback: 10000,
  ramp2: 40000, promo: 150000, combo: 100000, orbit: 30000, spin: 500, champignon: 1500,
};
/** Les trois niveaux de la Promotion (rampe 2) : on monte des équipes de jeunes vers l'équipe première. */
const PROMO = ['U17', 'U19', 'PRO'];
const COMBO = 6; // secondes pour enchaîner deux tirs (rampes, orbite)
const LION = 60; // coups de bumper pour réveiller le Lion
const DOUBLE = 25; // secondes de points doublés
const SAVE = 10; // secondes de bille sauvée après le lancer

export class Game {
  /**
   * cfg : { balls, text (libellés traduits), facts (« Le saviez-vous ? »), letters } ;
   * on.fx(nom, données) : sons et lumières ; on.over(résultats) : fin de partie.
   */
  constructor(phys, table, cfg = {}, on = {}) {
    this.p = phys;
    this.t = table;
    this.cfg = { balls: 3, text: {}, facts: [], ...cfg };
    this.on = { fx: () => {}, over: () => {}, ...on };
    this.state = 'attract';
    this.players = [];
    this.cur = 0;
    this.msgs = [];
    this.timers = [];
    this.time = 0;
    this.tilt = 0;
    this.tilted = false;
    this.multiball = false;
    phys.on = (...a) => this.event(...a);
  }

  tr(key, vars = {}) {
    return String(this.cfg.text[key] ?? key).replace(/\{(\w+)\}/g, (_, k) => vars[k] ?? '');
  }

  get pl() { return this.players[this.cur]; }

  start(n = 1) {
    n = Math.max(1, Math.min(4, n | 0));
    this.players = Array.from({ length: n }, (_, i) => ({
      n: i + 1, score: 0, ball: 1, extra: 0, lanes: [0, 0, 0, 0], letters: this.t.targets.map(() => 0), lettersDone: 0,
      cup: 0, cupsWon: 0, kickback: true, lion: 0, mult: 1, bonus: { lanes: 0, targets: 0, ramps: 0, bumpers: 0 }, done: new Set(), jackpots: 0,
      promo: 0, promotions: 0, combos: 0,
    }));
    this.lastShot = null;
    this.cur = 0;
    this.state = 'play';
    this.msgs = [];
    this.timers = [];
    this.serve(true);
  }

  /** Nouvelle bille sur le lanceur (début de tour) ; auto : lancée toute seule (bille sauvée, multiball). */
  serve(newTurn, auto = false) {
    for (const b of [...this.p.balls]) if (b.state === 'gone') this.p.removeBall(b);
    const b = this.p.addBall(this.t.plunger.x, this.t.plunger.ball);
    if (newTurn) {
      this.tilt = 0;
      this.tilted = false;
      this.multiball = false;
      this.p.resetTargets();
      this.saveUntil = 0;
      this.saveArmed = true;
      this.lionUntil = 0;
      this.skill = Math.floor(Math.random() * 4);
      this.skillArmed = true;
      this.waiting = true;
      this.say(this.players.length > 1 ? this.tr('player', { n: this.pl.n }) : this.tr('ball', { n: this.pl.ball, t: this.cfg.balls }), this.tr('launchHint'), 2500);
    }
    if (auto) this.after(0.7, () => this.p.launch(0.86));
    return b;
  }

  after(sec, fn) { this.timers.push({ at: this.time + sec, fn }); }

  /** Message de l'écran à points : grand texte, petite ligne, durée (ms). */
  say(big, small = '', ms = 2000, kind = '') {
    this.msgs.push({ big, small, ms, kind });
    if (this.msgs.length > 4) this.msgs.splice(0, this.msgs.length - 4);
  }

  add(pts) {
    if (this.state !== 'play' || this.tilted) return;
    const k = this.time < this.lionUntil ? 2 : 1;
    this.pl.score += pts * k;
  }

  /** Commandes du joueur. */
  input(what, down) {
    if (this.state !== 'play') return;
    if (what === 'L' || what === 'R') {
      this.p.setFlipper(what, down && !this.tilted);
      if (down && !this.tilted) {
        this.on.fx('flip', what);
        // Changement de couloir : les lumières des couloirs du haut tournent avec les batteurs.
        const l = this.pl.lanes;
        if (l.some(x => x) && !l.every(x => x)) this.pl.lanes = what === 'L' ? [...l.slice(1), l[0]] : [l[3], ...l.slice(0, 3)];
        if (this.waiting) this.skill = (this.skill + (what === 'L' ? 3 : 1)) % 4;
      }
    }
  }

  /** Coup sur la caisse : aide la bille ; trop de coups : TILT. */
  nudge(dx = 0) {
    if (this.state !== 'play' || this.tilted) return;
    this.p.nudge(dx * 90, -70);
    this.on.fx('nudge');
    this.tilt += 1;
    if (this.tilt > 3.2) {
      this.tilted = true;
      this.p.setFlipper('L', false);
      this.p.setFlipper('R', false);
      this.say('TILT', this.tr('tiltLost'), 3000, 'tilt');
      this.on.fx('tilt');
    } else if (this.tilt > 2) {
      this.say(this.tr('warning'), '', 900, 'warn');
    }
  }

  launch(power) {
    if (this.state === 'play') this.p.launch(power);
  }

  update(dt) {
    this.time += dt;
    this.tilt = Math.max(0, this.tilt - dt * 0.6);
    if (this.state === 'play' || this.state === 'bonus') this.p.update(dt);
    const due = this.timers.filter(t => t.at <= this.time);
    this.timers = this.timers.filter(t => t.at > this.time);
    for (const t of due) t.fn();
    if (this.msgs.length) {
      this.msgs[0].ms -= dt * 1000;
      if (this.msgs[0].ms <= 0) this.msgs.shift();
    }
  }

  // ------------------------------------------------------------------ événements de la physique

  event(ev, a, b, c, d) {
    if (this.state !== 'play') return;
    const pl = this.pl;
    switch (ev) {
      case 'launch':
        this.waiting = false;
        // « Tirez le lanceur » s'efface dès que la bille part.
        this.msgs = this.msgs.filter(m => m.small !== this.tr('launchHint'));
        if (this.saveArmed) { this.saveUntil = this.time + SAVE; this.saveArmed = false; }
        if (this.skillArmed) this.after(4, () => { this.skillArmed = false; });
        this.on.fx('launch', b);
        break;
      case 'sensor':
        if (a === 'lane') this.lane(d);
        else if (a === 'saucer') this.saucer(b);
        else if (a === 'kickback') this.kickback(b);
        else if (a === 'spinner') this.spinner(b);
        else if (a === 'orbit' && b.vx > 0) this.orbit();
        break;
      case 'bumper':
        this.add(this.t.bumpers[a] && this.t.bumpers[a].kind === 'champignon' ? PTS.champignon : PTS.bumper);
        pl.bonus.bumpers++;
        this.on.fx('bumper', a);
        if (++pl.lion >= LION && this.time >= this.lionUntil) {
          pl.lion = 0;
          pl.done.add('lion');
          this.lionUntil = this.time + DOUBLE;
          this.say(this.tr('lion'), this.tr('lionSub'), 2600, 'lion');
          this.on.fx('roar');
        }
        break;
      case 'hit':
        if (a === 'sling') { this.add(PTS.sling); this.on.fx('sling', d); } else this.on.fx('hit', c);
        break;
      case 'flip-hit':
        this.on.fx('flip-hit', c);
        break;
      case 'target':
        this.target(a);
        break;
      case 'ramp-mouth':
        if (b > 150) { this.p.enterRamp(a, c || 0); this.on.fx('ramp-in', c || 0); }
        break;
      case 'ramp-done':
        this.ramp(b || 0);
        break;
      case 'ramp-fail':
        this.on.fx('ramp-fail');
        break;
      case 'drain':
        this.drain(a);
        break;
    }
  }

  lane(i) {
    const pl = this.pl;
    this.add(PTS.lane);
    pl.bonus.lanes++;
    this.on.fx('lane', i);
    if (this.skillArmed) {
      this.skillArmed = false;
      if (i === this.skill) {
        this.add(PTS.skill);
        pl.done.add('skill');
        this.say(this.tr('skill'), '+ 500 000', 2200, 'skill');
        this.on.fx('skill');
      }
    }
    pl.lanes[i] = 1;
    if (pl.lanes.every(x => x)) {
      pl.mult = Math.min(5, pl.mult + 1);
      pl.done.add('lanes');
      this.add(PTS.lanesAll);
      this.say(this.t.digits.split('').join('-'), this.tr('mult', { n: pl.mult }), 2200, 'lanes');
      this.on.fx('lanes');
      this.after(0.8, () => { pl.lanes = [0, 0, 0, 0]; });
    }
  }

  target(i) {
    const pl = this.pl;
    this.add(PTS.target);
    pl.bonus.targets++;
    pl.letters[i] = 1;
    this.on.fx('target', i);
    if (this.p.segs.filter(s => s.tag === 'target' && s.on).length === 0) this.after(1.2, () => { this.p.resetTargets(); this.on.fx('targets-up'); });
    if (pl.letters.every(x => x)) {
      pl.lettersDone++;
      pl.done.add('letters');
      this.add(PTS.letters * pl.lettersDone);
      pl.kickback = true;
      let small = this.tr('kickbackLit');
      if (pl.lettersDone === 2) { pl.extra++; pl.done.add('extra'); small = this.tr('extraBall'); this.on.fx('extra'); }
      this.say(this.t.letters.split('').join('-'), small, 2600, 'letters');
      this.on.fx('letters');
      this.after(0.8, () => { pl.letters = pl.letters.map(() => 0); });
    }
  }

  /** Rampe franchie : 0 = rampe 1 (épopée en Coupe), 1 = rampe 2 (Promotion). */
  ramp(id = 0) {
    const pl = this.pl;
    pl.bonus.ramps++;
    this.combo('r' + id);
    if (id === 1) return this.ramp2();
    this.add(PTS.ramp + PTS.rampStep * Math.min(pl.cup, 5));
    this.on.fx('ramp');
    if (this.multiball) return this.jackpot();
    if (pl.cup < 5) {
      pl.cup++;
      this.say(this.tr('qualified'), pl.cup === 5 ? this.tr('finaleLit') : this.tr('nextRound', { r: this.roundName(pl.cup) }), 2400, 'cup');
      this.on.fx('cup', pl.cup);
    }
  }

  /** Rampe 2 : U17, U19, PRO ; la troisième fois, Promotion (points, centre de formation rallumé). */
  ramp2() {
    const pl = this.pl;
    this.add(PTS.ramp2);
    this.on.fx('ramp', 1);
    if (this.multiball) return this.jackpot();
    pl.promo++;
    if (pl.promo < PROMO.length) {
      this.say(this.tr('ramp2'), PROMO[pl.promo - 1], 2000, 'cup');
      return;
    }
    pl.promo = 0;
    pl.promotions++;
    pl.done.add('promo');
    pl.kickback = true;
    this.add(PTS.promo * pl.promotions);
    this.say(this.tr('promo'), this.tr('kickbackLit'), 2600, 'letters');
    this.on.fx('promo');
  }

  jackpot() {
    const pl = this.pl;
    pl.jackpots++;
    pl.done.add('jackpot');
    this.add(PTS.jackpot * pl.jackpots);
    this.say('JACKPOT', this.fmt(PTS.jackpot * pl.jackpots), 2400, 'jackpot');
    this.on.fx('jackpot');
  }

  /** Tirs enchaînés (rampes, orbite) en moins de COMBO secondes : combo. */
  combo(shot) {
    const pl = this.pl, last = this.lastShot;
    this.lastShot = { shot, at: this.time };
    if (!last || last.shot === shot || this.time - last.at > COMBO) return;
    pl.combos++;
    pl.done.add('combo');
    this.add(PTS.combo * Math.min(pl.combos, 5));
    this.after(0.9, () => this.say(this.tr('combo'), '+ ' + this.fmt(PTS.combo * Math.min(pl.combos, 5)), 1800, 'skill'));
    this.on.fx('combo');
  }

  /** Orbite : la bille a fait le tour par le couloir de gauche et l'arche. */
  orbit() {
    const pl = this.pl;
    if (this.time - (this.orbitAt ?? -9) < 1.5) return; // un seul tour compté, même si la bille rebondit au capteur
    this.orbitAt = this.time;
    this.add(PTS.orbit);
    pl.done.add('orbit');
    this.combo('orbit');
    this.say(this.tr('orbit'), '+ ' + this.fmt(PTS.orbit), 1600, 'lanes');
    this.on.fx('orbit');
  }

  /** Spinner : des points à chaque tour de palette (selon la vitesse de la bille). */
  spinner(b) {
    const spins = Math.max(1, Math.min(24, Math.round(Math.abs(b.vy) / 90)));
    this.add(PTS.spin * spins);
    this.on.fx('spinner', spins);
  }

  roundName(i) {
    return this.tr(['r32', 'r16', 'r8', 'r4', 'r2', 'final'][Math.min(i, 5)]);
  }

  saucer(b) {
    if (Math.hypot(b.vx, b.vy) > 1400) return; // trop rapide : elle passe par-dessus
    const pl = this.pl, s = this.t.saucer;
    this.p.hold(b, s.x, s.y);
    this.on.fx('saucer');
    if (pl.cup >= 5 && !this.multiball) {
      // Finale : multiball à 3 billes ; un jackpot (rampe) et la Coupe est gagnée.
      this.add(PTS.finale);
      this.multiball = true;
      pl.jackpotsAtStart = pl.jackpots;
      this.say(this.tr('final'), this.tr('multiball'), 3000, 'multiball');
      this.on.fx('multiball');
      this.saveUntil = this.time + SAVE + 3;
      this.after(1.5, () => this.serve(false, true));
      this.after(2.6, () => this.serve(false, true));
      this.after(3, () => this.p.release(b, -160, 260));
      return;
    }
    this.add(PTS.saucer);
    const facts = this.cfg.facts;
    const fact = facts.length ? facts[Math.floor(Math.random() * facts.length)] : '';
    this.say(this.tr('cupHole'), fact, fact ? 4200 : 1600, 'fact');
    this.after(1.4, () => this.p.release(b, -160, 260));
  }

  kickback(b) {
    const pl = this.pl;
    if (!pl.kickback || this.tilted) return;
    pl.kickback = false;
    this.add(PTS.kickback);
    this.p.hold(b, this.t.kickback.x, this.t.kickback.y);
    this.say(this.tr('kickback'), this.tr('kickbackSub'), 2000, 'kickback');
    this.on.fx('kickback');
    // Renvoi assez fort pour suivre l'arche du haut, le long du mur (une bille qui dérive vers la
    // droite toucherait le poteau du couloir et retomberait) : elle revient dans le jeu.
    this.after(0.45, () => this.p.release(b, -6, -2000));
  }

  drain(b) {
    this.p.removeBall(b);
    this.on.fx('drain');
    const left = this.p.balls.filter(o => o.state !== 'gone');
    if (!this.tilted && this.time < this.saveUntil && !this.multiball) {
      this.say(this.tr('saved'), '', 1800, 'save');
      this.serve(false, true);
      return;
    }
    if (left.length > 0) {
      if (this.multiball && left.length === 1) this.endMultiball();
      return;
    }
    if (this.multiball) this.endMultiball();
    this.endBall();
  }

  endMultiball() {
    const pl = this.pl;
    this.multiball = false;
    if (pl.jackpots > (pl.jackpotsAtStart || 0)) {
      pl.cupsWon++;
      pl.done.add('coupe');
      this.say(this.tr('cupWon'), this.tr('cupWonSub'), 3000, 'cupwon');
      this.on.fx('cupwon');
    } else {
      this.say(this.tr('cupLost'), '', 2200, 'cuplost');
    }
    pl.cup = 0;
  }

  endBall() {
    const pl = this.pl;
    this.p.setFlipper('L', false);
    this.p.setFlipper('R', false);
    const bo = pl.bonus;
    const bonus = this.tilted ? 0 : (bo.lanes * 2000 + bo.targets * 1500 + bo.ramps * 10000 + bo.bumpers * 100) * pl.mult;
    if (!this.tilted) pl.score += bonus;
    pl.bonus = { lanes: 0, targets: 0, ramps: 0, bumpers: 0 };
    this.state = 'bonus';
    this.say(this.tr('bonus'), this.fmt(bonus) + (pl.mult > 1 ? ' (×' + pl.mult + ')' : ''), 2400, 'bonus');
    this.on.fx('bonus');
    this.after(2.6, () => {
      this.state = 'play';
      if (pl.extra > 0 && !this.tilted) {
        pl.extra--;
        this.say(this.tr('again'), '', 1800, 'extra');
        this.serve(true);
        return;
      }
      pl.mult = 1;
      pl.ball++;
      // Joueur suivant qui a encore des billes.
      for (let k = 1; k <= this.players.length; k++) {
        const j = (this.cur + k) % this.players.length;
        if (this.players[j].ball <= this.cfg.balls) {
          this.cur = j;
          this.serve(true);
          return;
        }
      }
      this.state = 'over';
      this.on.over(this.players.map(p => ({ n: p.n, score: p.score, done: [...p.done], cups: p.cupsWon })));
    });
  }

  fmt(n) {
    return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  }

  /** Ce que montre l'écran à points maintenant. */
  screen() {
    const m = this.msgs[0];
    if (m) return { big: m.big, small: m.small, kind: m.kind };
    const pl = this.pl;
    if (!pl) return { big: '', small: '' };
    let small;
    if (this.waiting) small = this.tr('launchHint');
    else if (this.multiball) small = this.tr('jackpotHint');
    else if (pl.cup >= 5) small = this.tr('finaleLit');
    else small = this.tr('cupRound', { r: this.roundName(pl.cup) });
    return { big: this.fmt(pl.score), small, kind: 'score' };
  }

  /** Lumières du plateau pour le rendu. */
  lamps() {
    const pl = this.pl;
    if (!pl) return null;
    return {
      lanes: pl.lanes, skill: this.waiting || this.skillArmed ? this.skill : -1, letters: pl.letters, cup: pl.cup,
      finale: pl.cup >= 5 && !this.multiball, kickback: pl.kickback, save: this.time < this.saveUntil, lion: this.time < this.lionUntil,
      mult: pl.mult, multiball: this.multiball, tilted: this.tilted, promo: pl.promo,
      // Combo possible : on montre les autres tirs à enchaîner (rampes, orbite).
      combo: this.lastShot && this.time - this.lastShot.at < COMBO ? this.lastShot.shot : '',
    };
  }
}
