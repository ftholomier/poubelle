// Règles du flipper « au stade » : chaque partie est une épopée en Coupe, match après match, contre
// des adversaires de l'histoire du club. Un match se joue en deux mi-temps de 45:00 (chrono qui
// défile quand une bille est en jeu) ; on marque sur le mini-terrain, chaque bille perdue est un but
// pour l'adversaire. Les passes (rampes, couloirs UNE-DEUX) éliminent les joueurs adverses et
// remplissent la jauge COMBO ; une ligne éliminée allume son verrou (attaquant, milieu, défenseur) ;
// trois billes verrouillées lancent le multiball. Joueurs (1 à 4, à tour de rôle), billes, points,
// multiplicateurs de bonus, super jackpot, geste technique, bille sauvée, tilt, messages de l'écran à
// points. Aucun rendu ici.

/** Points de chaque élément. */
export const PTS = {
  bumper: 1000, sling: 110, pass: 25000, ramp: 50000, onetwo: 25000, onetwoBoth: 100000, lockLit: 100000, lock: 25000,
  goal: 250000, jackpot: 1000000, superJackpot: 5000000, rampJackpot: 250000, combo: 250000, hole: 25000, trick: 50000,
  trickJackpot: 500000, trap: 15000, tackle: 10000, player: 5000, save: 5000, skill: 500000, win: 1000000, draw: 250000, cup: 5000000, outlane: 10000,
};
/** Les tours de la Coupe. */
const ROUNDS = ['r8', 'r4', 'r2', 'final'];
/** Lignes adverses dans l'ordre des hexagones du plateau : 2 attaquants, 4 milieux, 4 défenseurs. */
export const LINES = ['att', 'mil', 'def'];
const HEX_LINE = ['att', 'att', 'mil', 'mil', 'mil', 'mil', 'def', 'def', 'def', 'def'];
/** Hexagone → joueur du mini-terrain (physique : défenseurs 0-3, milieux 4-7, attaquants 8-9). */
export const hexToPlayer = i => (i < 2 ? 8 + i : i < 6 ? 4 + (i - 2) : i - 6);
/** Multiplicateurs de bonus du couloir de lancement. */
export const MULTS = [1, 2, 4, 6, 8, 10];
/** Récompenses du geste technique (inserts de mode), dans l'ordre où elles tournent. */
export const MODES = ['jackpot', 'super', 'skill', 'extra', 'time'];
/** Tirs du coup d'envoi : rampe CENTRE, rampe BALLON LONG, accès mini-terrain. */
export const SHOTS = ['r2', 'r3', 'r4'];
const HALF = 2700; // une mi-temps : 45:00 de temps de match
const RATE = 30; // secondes de match par seconde de jeu (une mi-temps ≈ 1 min 30 de bille en jeu)
const COMBO_MAX = 5, COMBO_DECAY = 8; // jauge COMBO : 5 cases, une case perdue toutes les 8 s sans passe
const LION = 50, DOUBLE = 25; // coups de bumper pour réveiller le Lion, secondes de points doublés
const SAVE = 10; // secondes de bille sauvée après le lancer
const SKILL = 10; // secondes pour réussir le coup d'envoi après le lancer
const ATTACK = 6; // secondes d'attaque lancée (inlanes) : chaque passe élimine deux joueurs

export class Game {
  /**
   * cfg : { balls, text (libellés traduits), facts (« Le saviez-vous ? »), opps: [{ name, ini, kit }] } ;
   * on.fx(nom, données) : sons et lumières (fx 'opp' : nouvel adversaire) ; on.over(résultats) : fin de partie.
   */
  constructor(phys, table, cfg = {}, on = {}) {
    this.p = phys;
    this.t = table;
    this.cfg = { balls: 3, text: {}, facts: [], opps: [{ name: '', ini: '' }], ...cfg };
    if (!this.cfg.opps.length) this.cfg.opps = [{ name: '', ini: '' }];
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
      n: i + 1, score: 0, ball: 1, extra: 0, extraGiven: false, done: new Set(), bonus: { ramps: 0, passes: 0, bumpers: 0, goals: 0 },
      mult: 0, hold: false, combo: 0, comboAt: 0, combos: 0, attackUntil: 0, superLit: false, lion: 0,
      elim: new Set(), lit: [false, false, false], locked: [false, false, false], mode: 0,
      round: 0, opp: (Math.floor(Math.random() * this.cfg.opps.length) + i) % this.cfg.opps.length,
      home: 0, away: 0, half: 1, clock: HALF, goals: [], matches: [], cups: 0, goalsTotal: 0,
    }));
    this.lastOneTwo = null;
    this.cur = 0;
    this.state = 'play';
    this.msgs = [];
    this.timers = [];
    this.serve(true);
  }

  /** Nouvelle bille sur le lanceur (début de tour) ; auto : lancée toute seule (bille sauvée). */
  serve(newTurn, auto = false) {
    for (const b of [...this.p.balls]) if (b.state === 'gone') this.p.removeBall(b);
    const b = this.p.addBall(this.t.plunger.x, this.t.plunger.ball);
    if (newTurn) {
      this.tilt = 0;
      this.tilted = false;
      this.multiball = false;
      this.saveUntil = 0;
      this.saveArmed = true;
      this.lionUntil = 0;
      this.skill = Math.floor(Math.random() * SHOTS.length);
      this.skillArmed = true;
      this.skillUntil = Infinity;
      this.waiting = true;
      this.sync();
      this.on.fx('opp', this.opp());
      this.say(this.players.length > 1 ? this.tr('player', { n: this.pl.n }) : this.tr('ball', { n: this.pl.ball, t: this.cfg.balls }), this.tr('launchHint'), 2500);
    }
    if (auto) this.after(0.7, () => this.p.launch(0.86));
    return b;
  }

  /** Adversaire du match en cours (joueur en cours). */
  opp(pl = this.pl) { return this.cfg.opps[pl.opp % this.cfg.opps.length]; }

  /** Le plateau suit le joueur : joueurs adverses éliminés, adresse du gardien. */
  sync() {
    const pl = this.pl;
    this.p.resetPlayers();
    for (const i of pl.elim) this.p.players[hexToPlayer(i)].seg.on = false;
    this.p.keeperSkill = Math.min(0.6, pl.home * 0.12);
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
        // Bille sur le lanceur : les flippers choisissent le tir du coup d'envoi.
        if (this.waiting) this.skill = (this.skill + (what === 'L' ? SHOTS.length - 1 : 1)) % SHOTS.length;
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
    const pl = this.pl;
    // Chrono du match : il défile quand une bille est en jeu ; à 00:00, mi-temps ou fin du match.
    if (this.state === 'play' && pl && !this.waiting && !this.tilted && this.p.balls.some(b => b.state === 'play' || b.state === 'ramp')) {
      pl.clock -= dt * RATE;
      if (pl.clock <= 0) this.whistle();
    }
    // La jauge COMBO se vide peu à peu sans passe.
    if (pl && pl.combo > 0 && this.time - pl.comboAt > COMBO_DECAY) { pl.combo--; pl.comboAt = this.time; }
    if (this.skillArmed && this.time > this.skillUntil) this.skillArmed = false;
    const due = this.timers.filter(t => t.at <= this.time);
    this.timers = this.timers.filter(t => t.at > this.time);
    for (const t of due) t.fn();
    if (this.msgs.length) {
      this.msgs[0].ms -= dt * 1000;
      if (this.msgs[0].ms <= 0) this.msgs.shift();
    }
  }

  /** Chrono affiché (« 45:00 », « 03:27 »). */
  clock(pl = this.pl) {
    const s = Math.max(0, Math.ceil(pl ? pl.clock : HALF));
    return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
  }

  /** Minute du match écoulée (pour les buts : « 23' », « 67' »). */
  minute(pl = this.pl) {
    return Math.min(90, Math.floor((pl.half - 1) * 45 + (HALF - Math.max(0, pl.clock)) / 60) + 1) + "'";
  }

  // ------------------------------------------------------------------ le match

  /** Coup de sifflet : mi-temps, ou fin du match (résultat, tour suivant ou élimination). */
  whistle() {
    const pl = this.pl;
    if (pl.half === 1) {
      pl.half = 2;
      pl.clock = HALF;
      this.say(this.tr('half'), this.tr('scoreLine', { h: pl.home, a: pl.away, o: this.opp().ini || this.opp().name }), 2400, 'half');
      this.on.fx('half');
      return;
    }
    const diff = pl.home - pl.away, opp = this.opp(), round = ROUNDS[Math.min(pl.round, ROUNDS.length - 1)];
    let key = 'loss';
    if (diff > 0) key = 'win'; else if (diff === 0) key = 'draw';
    pl.matches.push({ opp: opp.name || opp.ini, ini: opp.ini, home: pl.home, away: pl.away, round, result: key, goals: pl.goals.slice() });
    let small = this.tr('scoreLine', { h: pl.home, a: pl.away, o: opp.ini || opp.name });
    if (key === 'win') {
      this.add(PTS.win * diff);
      pl.done.add('victoire');
      if (pl.round >= ROUNDS.length - 1) {
        pl.cups++;
        pl.done.add('coupe');
        this.add(PTS.cup);
        pl.round = 0;
        this.after(2.6, () => this.say(this.tr('cupWon'), this.tr('cupWonSub'), 3000, 'cupwon'));
        this.on.fx('cupwon');
      } else {
        pl.round++;
        small = this.tr('nextRound', { r: this.roundName(pl.round) });
      }
    } else if (key === 'draw') {
      this.add(PTS.draw);
      small = this.tr('replay');
    } else {
      pl.round = 0;
      small = this.tr('out');
    }
    this.say(this.tr(key), small, 2600, key);
    this.on.fx(key);
    // Nouveau match (même adversaire après un nul, sinon le suivant).
    if (key !== 'draw') pl.opp = (pl.opp + 1) % this.cfg.opps.length;
    pl.home = 0; pl.away = 0; pl.half = 1; pl.clock = HALF; pl.goals = [];
    pl.elim = new Set(); pl.lit = [false, false, false];
    this.sync();
    this.after(2.8, () => {
      this.say(this.tr('kickoff'), this.tr('vs', { r: this.roundName(pl.round), o: this.opp().name || this.opp().ini }), 2400, 'match');
      this.on.fx('opp', this.opp());
    });
  }

  roundName(i) {
    return this.tr(ROUNDS[Math.min(i, ROUNDS.length - 1)]);
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
        if (this.skillArmed && this.skillUntil === Infinity) this.skillUntil = this.time + SKILL;
        a.launchedAt = this.time;
        this.on.fx('launch', a);
        break;
      case 'sensor':
        this.sensor(a, b, c);
        break;
      case 'bumper':
        this.add(PTS.bumper);
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
        if (a === 'sling' || a === 'kicker') { this.add(PTS.sling); this.on.fx(a, d); } else this.on.fx('hit', c);
        break;
      case 'flip-hit':
        this.on.fx('flip-hit', c);
        if (d === 'tackle' && this.time - (this.tackleAt ?? -9) > 2) {
          this.tackleAt = this.time;
          this.add(PTS.tackle);
          pl.done.add('tacle');
          this.say(this.tr('tackle'), '', 900, 'warn');
          this.on.fx('tackle');
        }
        break;
      case 'player':
        this.add(PTS.player);
        this.on.fx('player', a);
        break;
      case 'save':
        this.add(PTS.save);
        this.on.fx('save');
        if (this.time - (this.saveMsgAt ?? -9) > 2.5) { this.saveMsgAt = this.time; this.say(this.tr('keeper'), '', 1100, 'warn'); }
        break;
      case 'goal':
        this.goal(a);
        break;
      case 'mini-out':
        this.say(this.tr('missed'), '', 1200, 'warn');
        this.on.fx('missed');
        break;
      case 'ramp-in':
        this.on.fx('ramp-in', c);
        break;
      case 'ramp-done':
        this.ramp(b);
        break;
      case 'ramp-fail':
        this.on.fx('ramp-fail');
        break;
      case 'drain':
        this.drain(a);
        break;
    }
  }

  sensor(tag, b, ref) {
    const pl = this.pl;
    if (tag === 'inlane') {
      pl.attackUntil = this.time + ATTACK;
      this.say(this.tr('attack'), this.tr('attackSub'), 1400, 'pass');
      this.on.fx('lane', ref);
    } else if (tag === 'outlane') {
      this.add(PTS.outlane);
      if (!pl.superLit) { pl.superLit = true; this.say(this.tr('superLit'), '', 1600, 'jackpot'); }
      this.on.fx('outlane', ref);
    } else if (tag === 'onetwo') {
      // Compté en montant seulement, et pas pour la bille qui sort du lanceur (elle traverse le couloir droit).
      if (b.vy > 0 || (ref === 1 && this.time - (b.launchedAt ?? -9) < 2)) return;
      this.add(PTS.onetwo);
      this.on.fx('onetwo', ref);
      const last = this.lastOneTwo;
      this.lastOneTwo = { ref, at: this.time };
      if (last && last.ref !== ref && this.time - last.at < 5) { this.add(PTS.onetwoBoth); this.say(this.tr('onetwo'), '+ ' + this.fmt(PTS.onetwoBoth), 1400, 'skill'); }
      this.pass();
    } else if (tag === 'lock') {
      this.lock(b, ref);
    } else if (tag === 'trick') {
      this.trick(b);
    } else if (tag === 'trap') {
      const s = this.t.sensors.find(o => o.tag === 'trap');
      this.p.hold(b, s.x, s.y);
      this.add(PTS.trap);
      this.on.fx('trap');
      this.after(0.6, () => { if (b.state === 'held') this.p.release(b, 260, -950); });
    } else if (tag === 'hole') {
      // Trou d'éjection du mini-terrain : la bille repart par le rail vers l'inlane droit.
      this.add(this.multiball ? PTS.rampJackpot : PTS.hole);
      this.on.fx('hole', ref);
      const h = this.t.mini.holes[ref];
      this.p.hold(b, h.x, h.y);
      this.after(0.7, () => { if (b.state === 'held') { b.state = 'play'; this.p.enterRamp(b, ref ? 6 : 5, 520); } });
    }
  }

  /** Une passe réussie : un joueur adverse éliminé (deux si l'attaque est lancée), jauge COMBO. */
  pass() {
    const pl = this.pl;
    pl.bonus.passes++;
    this.add(PTS.pass);
    const n = this.time < pl.attackUntil ? 2 : 1;
    for (let k = 0; k < n; k++) this.eliminate();
    pl.combo++;
    pl.comboAt = this.time;
    this.on.fx('pass', pl.combo);
    if (pl.combo >= COMBO_MAX) {
      pl.combo = 0;
      pl.combos++;
      pl.done.add('combo');
      this.add(PTS.combo * pl.combos);
      this.after(0.5, () => this.say('COMBO !', '+ ' + this.fmt(PTS.combo * pl.combos), 1800, 'skill'));
      this.on.fx('combo');
    }
  }

  /** Élimine le prochain joueur adverse (attaquants, puis milieux, puis défenseurs). */
  eliminate() {
    const pl = this.pl;
    const i = HEX_LINE.findIndex((l, k) => !pl.elim.has(k));
    if (i < 0) return;
    pl.elim.add(i);
    this.p.players[hexToPlayer(i)].seg.on = false;
    this.on.fx('eliminate', i);
    const line = HEX_LINE[i], li = LINES.indexOf(line);
    if (HEX_LINE.every((l, k) => l !== line || pl.elim.has(k)) && !pl.locked[li]) {
      pl.lit[li] = true;
      this.after(0.4, () => this.say(this.tr('lockLit', { l: this.tr('line_' + line) }), this.tr('lockLitSub'), 1800, 'lock'));
      this.on.fx('lock-lit', li);
    } else {
      this.after(0.4, () => this.say(this.tr('eliminated'), this.tr(pl.elim.size === 9 ? 'leftOne' : 'left', { n: 10 - pl.elim.size }), 1000, 'pass'));
    }
  }

  /** Poche LOCK : allumée, la bille y est verrouillée (trois billes : multiball) ; sinon elle ressort. */
  lock(b, li) {
    const pl = this.pl, s = this.t.sensors.find(o => o.tag === 'lock' && o.ref === li);
    this.p.hold(b, s.x, s.y);
    const out = [[0, 320], [-130, 300], [-90, 320]][li];
    if (pl.lit[li] && !this.multiball) {
      pl.lit[li] = false;
      pl.locked[li] = true;
      this.add(PTS.lockLit);
      pl.done.add('lock');
      this.on.fx('locked', li);
      if (pl.locked.every(x => x)) {
        this.say(this.tr('multiball'), this.tr('multiballSub'), 3000, 'multiball');
        this.after(1, () => this.startMultiball(b));
        return;
      }
      this.say(this.tr('locked', { l: this.tr('line_' + LINES[li]) }), this.tr('locks', { n: pl.locked.filter(x => x).length }), 2200, 'lock');
      // La bille reste dans la poche ; une nouvelle bille arrive sur le lanceur.
      this.after(1.2, () => { this.p.removeBall(b); this.serve(false); this.waiting = true; });
      return;
    }
    this.add(PTS.lock);
    this.on.fx('kickout', li);
    this.after(0.8, () => { if (b.state === 'held') this.p.release(b, out[0], out[1]); });
  }

  /** Multiball : les billes verrouillées ressortent de leurs poches. */
  startMultiball(b) {
    const pl = this.pl;
    this.multiball = true;
    pl.done.add('multiball');
    pl.jackpotsAtStart = pl.jackpots || 0;
    this.saveUntil = this.time + SAVE + 3;
    this.on.fx('multiball');
    const outs = [[0, 320], [-130, 300], [-90, 320]];
    LINES.forEach((l, li) => {
      const s = this.t.sensors.find(o => o.tag === 'lock' && o.ref === li);
      if (b.state === 'held' && b.x === s.x && b.y === s.y) { this.p.release(b, outs[li][0], outs[li][1]); return; }
      const nb = this.p.addBall(s.x, s.y);
      this.p.hold(nb, s.x, s.y);
      this.after(0.4 + li * 0.5, () => this.p.release(nb, outs[li][0], outs[li][1]));
    });
    pl.locked = [false, false, false];
  }

  /** Rampe ou rail franchi : passes, jackpots en multiball. */
  ramp(id) {
    const pl = this.pl, name = this.t.ramps[id].name;
    if (id >= 5) return; // rails des trous d'éjection : simple retour
    pl.bonus.ramps++;
    this.add(PTS.ramp);
    this.on.fx('ramp', id);
    this.shot('r' + id);
    if (name === 'acces') { this.say(this.tr('mini'), this.tr('miniSub'), 1600, 'pass'); return; }
    if (this.multiball && (name === 'centre' || name === 'ballon-long')) { this.add(PTS.rampJackpot); this.say('JACKPOT', this.fmt(PTS.rampJackpot), 1400, 'jackpot'); this.on.fx('jackpot'); }
    if (name === 'passe') this.say(this.tr('mini'), this.tr('miniSub'), 1600, 'pass');
    this.pass();
  }

  /** Tir réussi : coup d'envoi (le tir qui clignote, dans les 10 s qui suivent le lancer). */
  shot(name) {
    if (!this.skillArmed) return;
    this.skillArmed = false;
    if (SHOTS[this.skill] === name) {
      this.add(PTS.skill);
      this.pl.done.add('skill');
      this.say(this.tr('skill'), '+ 500 000', 2200, 'skill');
      this.on.fx('skill');
    }
  }

  /** But ! La bille reste au fond des filets pendant la fête, puis ressort par le rail du trou droit. */
  goal(b) {
    const pl = this.pl, m = this.t.mini;
    this.p.hold(b, Math.max(m.goal.x1 + 8, Math.min(m.goal.x2 - 8, b.x)), m.goal.net);
    pl.home++;
    pl.goalsTotal++;
    pl.bonus.goals++;
    pl.goals.push(this.minute());
    pl.done.add('but');
    let pts = PTS.goal * pl.goalsTotal, small = this.tr('scoreLine', { h: pl.home, a: pl.away, o: this.opp().ini || this.opp().name }) + ' · ' + this.minute();
    if (this.multiball) { pl.jackpots = (pl.jackpots || 0) + 1; pl.done.add('jackpot'); pts += PTS.jackpot * pl.jackpots; small = 'JACKPOT ' + this.fmt(PTS.jackpot * pl.jackpots); }
    if (pl.superLit) { pl.superLit = false; pl.done.add('super'); pts += PTS.superJackpot; small = 'SUPER JACKPOT ' + this.fmt(PTS.superJackpot); this.on.fx('super'); }
    this.add(pts);
    if (pl.mult < MULTS.length - 1) pl.mult++;
    if (pl.mult === MULTS.length - 1) pl.hold = true;
    this.say(this.tr('goal'), small, 3200, 'goal');
    this.on.fx('goal', pl.home);
    // Coup d'envoi après le but : l'équipe adverse revient au complet.
    pl.elim = new Set();
    this.after(2.6, () => {
      this.sync();
      if (b.state !== 'held') return;
      b.state = 'play';
      this.p.place(b, 1, m.holes[1].x, m.holes[1].y);
      this.p.enterRamp(b, 6, 520);
      this.on.fx('tunnel');
    });
  }

  /** Geste technique (TRICK) : la récompense allumée, un « Le saviez-vous ? », puis la bille part dans la rampe PASSE. */
  trick(b) {
    const pl = this.pl, s = this.t.sensors.find(o => o.tag === 'trick');
    this.p.hold(b, s.x, s.y);
    const mode = MODES[pl.mode % MODES.length];
    pl.mode++;
    this.add(PTS.trick);
    let small = '';
    if (mode === 'jackpot') { this.add(PTS.trickJackpot); small = 'JACKPOT ' + this.fmt(PTS.trickJackpot); } else if (mode === 'super') { pl.superLit = true; small = this.tr('superLit'); } else if (mode === 'skill') { this.skillArmed = true; this.skillUntil = this.time + SKILL; small = this.tr('skillLit'); } else if (mode === 'extra') {
      if (!pl.extraGiven) { pl.extraGiven = true; pl.extra++; pl.done.add('extra'); small = this.tr('extraBall'); this.on.fx('extra'); } else { this.add(PTS.trickJackpot); small = 'JACKPOT ' + this.fmt(PTS.trickJackpot); }
    } else { pl.clock += 300; small = this.tr('moreTime'); }
    pl.done.add('trick');
    this.say(this.tr('trick'), small, 2000, 'skill');
    const facts = this.cfg.facts;
    if (facts.length) this.after(2, () => this.say(this.tr('fact'), facts[Math.floor(Math.random() * facts.length)], 4200, 'fact'));
    this.on.fx('trick', mode);
    // Éjection : la bille remonte le long du flanc gauche, droit dans la rampe PASSE.
    this.after(1.4, () => { if (b.state === 'held') { const v = 1300, dx = 20, dy = -108, L = Math.hypot(dx, dy); this.p.release(b, dx / L * v, dy / L * v); } });
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
    this.multiball = false;
    this.say(this.tr('multiballEnd'), '', 1600, 'pass');
    this.pl.elim = new Set();
    this.sync();
  }

  /** Bille perdue : but de l'adversaire, bonus de fin de bille, puis bille suivante (ou fin de partie). */
  endBall() {
    const pl = this.pl;
    this.p.setFlipper('L', false);
    this.p.setFlipper('R', false);
    pl.away++;
    pl.goalsAgainst = (pl.goalsAgainst || 0) + 1;
    this.say(this.tr('awayGoal', { o: this.opp().name || this.opp().ini }), this.tr('scoreLine', { h: pl.home, a: pl.away, o: this.opp().ini || this.opp().name }), 2000, 'away');
    this.on.fx('awaygoal');
    const bo = pl.bonus, k = MULTS[pl.mult];
    const bonus = this.tilted ? 0 : (bo.ramps * 20000 + bo.passes * 10000 + bo.bumpers * 100 + bo.goals * 50000) * k;
    if (!this.tilted) pl.score += bonus;
    pl.bonus = { ramps: 0, passes: 0, bumpers: 0, goals: 0 };
    this.state = 'bonus';
    this.after(2, () => this.say(this.tr('bonus'), this.fmt(bonus) + (k > 1 ? ' (×' + k + ')' : ''), 2200, 'bonus'));
    this.on.fx('bonus');
    this.after(4.4, () => {
      this.state = 'play';
      if (!pl.hold) pl.mult = 0;
      pl.hold = false;
      if (pl.extra > 0 && !this.tilted) {
        pl.extra--;
        this.say(this.tr('again'), '', 1800, 'extra');
        this.serve(true);
        return;
      }
      pl.ball++;
      // Joueur suivant qui a encore des billes.
      for (let k2 = 1; k2 <= this.players.length; k2++) {
        const j = (this.cur + k2) % this.players.length;
        if (this.players[j].ball <= this.cfg.balls) {
          this.cur = j;
          this.serve(true);
          return;
        }
      }
      this.state = 'over';
      this.on.over(this.players.map(p => ({
        n: p.n, score: p.score, done: [...p.done], cups: p.cups, matches: p.matches.slice(),
        current: { opp: this.opp(p).name || this.opp(p).ini, home: p.home, away: p.away, round: ROUNDS[Math.min(p.round, ROUNDS.length - 1)] },
      })));
    });
  }

  fmt(n) {
    return String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
  }

  /** Le match du joueur en cours, pour le tableau de score : buts, chrono, mi-temps, adversaire, tour. */
  match() {
    const pl = this.pl;
    return pl ? { home: pl.home, away: pl.away, clock: this.clock(), half: pl.half, opp: this.opp(), round: this.roundName(pl.round) } : null;
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
    else if (pl.lit.some(x => x)) small = this.tr('lockHint');
    else small = this.tr('vs', { r: this.roundName(pl.round), o: this.opp().name || this.opp().ini });
    return { big: this.fmt(pl.score), small, kind: 'score' };
  }

  /** Lumières du plateau pour le rendu. */
  lamps() {
    const pl = this.pl;
    if (!pl) return null;
    return {
      hexes: HEX_LINE.map((l, i) => !pl.elim.has(i)), lit: pl.lit.slice(), locked: pl.locked.slice(), combo: pl.combo, comboMax: COMBO_MAX,
      mult: pl.mult, hold: pl.hold, superLit: pl.superLit, attack: this.time < pl.attackUntil, mode: MODES[pl.mode % MODES.length],
      skill: this.waiting || this.skillArmed ? SHOTS[this.skill] : '', multiball: this.multiball, save: this.time < this.saveUntil,
      lion: this.time < this.lionUntil, tilted: this.tilted, home: pl.home, away: pl.away, clock: this.clock(), half: pl.half,
      ball: Math.min(pl.ball, this.cfg.balls), balls: this.cfg.balls, round: pl.round,
    };
  }
}
