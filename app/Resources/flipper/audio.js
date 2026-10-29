// Sons du flipper, fabriqués à la volée (Web Audio) : aucun fichier son à télécharger.
// Allumés d'office (le joueur peut les couper, choix gardé sur l'appareil) : claquements des
// batteurs, bumpers, cibles, spinner, roulement de la bille selon sa vitesse (plus clair dans les rampes),
// rumeur du stade pendant la partie, clameur de la foule sur les grands moments.

export class Sound {
  constructor() {
    this.on = false;
    this.ctx = null;
    this.crowdOn = false;
  }

  /** Allume ou coupe le son (à appeler depuis un geste du joueur : clic, touche). */
  enable(on) {
    this.on = on;
    if (on && !this.ctx) this.init();
    if (!this.ctx) { this.on = false; return; }
    if (on && this.ctx.state === 'suspended') this.ctx.resume();
    const t = this.ctx.currentTime;
    this.master.gain.cancelScheduledValues(t);
    this.master.gain.setTargetAtTime(on ? 0.7 : 0, t, 0.03);
  }

  init() {
    const C = window.AudioContext || window.webkitAudioContext;
    if (!C) return;
    const c = this.ctx = new C({ latencyHint: 'interactive' });
    this.master = c.createGain();
    this.master.gain.value = 0;
    // Petit compresseur : les sons qui s'empilent (multiball) ne saturent pas.
    const comp = c.createDynamicsCompressor();
    comp.threshold.value = -14; comp.ratio.value = 4;
    this.master.connect(comp); comp.connect(c.destination);
    const len = c.sampleRate * 2;
    this.noiseBuf = c.createBuffer(1, len, c.sampleRate);
    const d = this.noiseBuf.getChannelData(0);
    for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
    // Roulement de la bille : bruit filtré en continu, volume et grave réglés à chaque image.
    this.roll = this.loop(c.createBiquadFilter(), 0);
    this.roll.f.type = 'lowpass'; this.roll.f.frequency.value = 300; this.roll.f.Q.value = 0.7;
    this.rampRoll = this.loop(c.createBiquadFilter(), 0);
    this.rampRoll.f.type = 'bandpass'; this.rampRoll.f.frequency.value = 1400; this.rampRoll.f.Q.value = 1.2;
    // Rumeur du stade : bruit grave qui ondule lentement (chants lointains).
    this.crowd = this.loop(c.createBiquadFilter(), 0);
    this.crowd.f.type = 'bandpass'; this.crowd.f.frequency.value = 520; this.crowd.f.Q.value = 0.5;
    const lfo = c.createOscillator();
    this.crowdDepth = c.createGain();
    lfo.frequency.value = 0.18; this.crowdDepth.gain.value = 0;
    lfo.connect(this.crowdDepth); this.crowdDepth.connect(this.crowd.g.gain); lfo.start();
  }

  /** Bruit en boucle → filtre → volume (réglable). */
  loop(f, vol) {
    const c = this.ctx, s = c.createBufferSource(), g = c.createGain();
    s.buffer = this.noiseBuf; s.loop = true; s.loopStart = Math.random();
    g.gain.value = vol;
    s.connect(f); f.connect(g); g.connect(this.master); s.start();
    return { s, f, g };
  }

  tone(freq, dur, type = 'square', vol = 0.2, to = null, delay = 0) {
    const c = this.ctx, t = c.currentTime + delay, o = c.createOscillator(), g = c.createGain();
    o.type = type; o.frequency.setValueAtTime(freq, t);
    if (to) o.frequency.exponentialRampToValueAtTime(to, t + dur);
    g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(vol, t + 0.004); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    o.connect(g); g.connect(this.master); o.start(t); o.stop(t + dur + 0.02);
  }

  noise(dur, freq = 1200, q = 1, vol = 0.2, delay = 0, type = 'bandpass', sweep = null) {
    const c = this.ctx, t = c.currentTime + delay, s = c.createBufferSource(), f = c.createBiquadFilter(), g = c.createGain();
    s.buffer = this.noiseBuf; f.type = type; f.frequency.setValueAtTime(freq, t); f.Q.value = q;
    if (sweep) f.frequency.exponentialRampToValueAtTime(sweep, t + dur);
    g.gain.setValueAtTime(vol, t); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    s.connect(f); f.connect(g); g.connect(this.master); s.start(t, Math.random()); s.stop(t + dur + 0.02);
  }

  arp(notes, step = 0.09, type = 'square', vol = 0.12) {
    notes.forEach((n, i) => this.tone(n, step * 1.8, type, vol, null, i * step));
  }

  /** Clameur de la foule : souffle large qui monte puis retombe. */
  cheer(dur = 2.2, vol = 0.32) {
    const c = this.ctx, t = c.currentTime, s = c.createBufferSource(), f = c.createBiquadFilter(), g = c.createGain();
    s.buffer = this.noiseBuf; s.loop = true;
    f.type = 'bandpass'; f.Q.value = 0.6; f.frequency.setValueAtTime(600, t); f.frequency.linearRampToValueAtTime(1100, t + 0.4);
    g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(vol, t + 0.25); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    s.connect(f); f.connect(g); g.connect(this.master); s.start(t); s.stop(t + dur + 0.05);
  }

  /** Rugissement du Lion : souffle grave filtré qui monte puis retombe. */
  roar() {
    const c = this.ctx, t = c.currentTime;
    const s = c.createBufferSource(), f = c.createBiquadFilter(), g = c.createGain(), o = c.createOscillator(), og = c.createGain();
    s.buffer = this.noiseBuf; s.loop = true;
    f.type = 'lowpass'; f.Q.value = 6; f.frequency.setValueAtTime(220, t); f.frequency.linearRampToValueAtTime(900, t + 0.35); f.frequency.exponentialRampToValueAtTime(160, t + 1.3);
    g.gain.setValueAtTime(0.001, t); g.gain.exponentialRampToValueAtTime(0.5, t + 0.25); g.gain.exponentialRampToValueAtTime(0.001, t + 1.4);
    o.type = 'sawtooth'; o.frequency.setValueAtTime(70, t); o.frequency.linearRampToValueAtTime(110, t + 0.35); o.frequency.linearRampToValueAtTime(55, t + 1.3);
    og.gain.setValueAtTime(0.001, t); og.gain.exponentialRampToValueAtTime(0.18, t + 0.25); og.gain.exponentialRampToValueAtTime(0.001, t + 1.4);
    s.connect(f); f.connect(g); g.connect(this.master); o.connect(og); og.connect(f);
    s.start(t); s.stop(t + 1.5); o.start(t); o.stop(t + 1.5);
    this.cheer(1.8, 0.2);
  }

  /** Rumeur du stade pendant la partie (coupée en pause et à la fin). */
  ambience(on) {
    this.crowdOn = on;
    if (!this.ctx) return;
    this.crowd.g.gain.setTargetAtTime(on ? 0.035 : 0, this.ctx.currentTime, 0.6);
    this.crowdDepth.gain.setTargetAtTime(on ? 0.012 : 0, this.ctx.currentTime, 0.6);
    if (!on) { this.roll.g.gain.setTargetAtTime(0, this.ctx.currentTime, 0.05); this.rampRoll.g.gain.setTargetAtTime(0, this.ctx.currentTime, 0.05); }
  }

  /** À chaque image : roulement selon la vitesse des billes sur le plateau et dans la rampe. */
  update(balls) {
    if (!this.on || !this.ctx || !this.crowdOn) return;
    let v = 0, ramp = 0;
    for (const b of balls) {
      if (b.state === 'play' && b.y < 830) v = Math.max(v, Math.hypot(b.vx, b.vy));
      else if (b.state === 'ramp') ramp = Math.max(ramp, Math.abs(b.rv));
    }
    const t = this.ctx.currentTime, k = Math.min(1, v / 1800);
    this.roll.g.gain.setTargetAtTime(v > 40 ? 0.05 + 0.22 * k : 0, t, 0.05);
    this.roll.f.frequency.setTargetAtTime(180 + 700 * k, t, 0.05);
    this.rampRoll.g.gain.setTargetAtTime(ramp > 60 ? 0.05 + 0.12 * Math.min(1, ramp / 1400) : 0, t, 0.05);
  }

  play(name, data) {
    if (!this.on || !this.ctx) return;
    switch (name) {
      // Batteur : claquement de la bobine (souffle sec + coup sourd).
      case 'flip': this.noise(0.045, 2400, 0.9, 0.22, 0, 'highpass'); this.tone(95, 0.09, 'sine', 0.45, 45); this.noise(0.03, 500, 1.5, 0.15); break;
      case 'flip-hit': this.tone(140, 0.05, 'triangle', Math.min(0.3, (data || 0) / 4000)); break;
      case 'hit': if ((data || 0) > 200) this.noise(0.035, 2600, 3, Math.min(0.16, (data || 0) / 5000)); break;
      case 'bumper': this.tone(240, 0.11, 'square', 0.16, 110); this.noise(0.05, 3200, 2, 0.16); this.tone(1320, 0.18, 'sine', 0.07, null, 0.01); break;
      case 'sling': this.noise(0.04, 1800, 2, 0.2); this.tone(520, 0.07, 'triangle', 0.16, 260); break;
      case 'target': this.tone(1046, 0.06, 'square', 0.1); this.noise(0.04, 4000, 3, 0.12); this.tone(110, 0.06, 'sine', 0.25); break;
      case 'targets-up': this.noise(0.12, 900, 1, 0.18); this.tone(200, 0.14, 'square', 0.08, 400); break;
      case 'lane': this.tone(660, 0.08, 'square', 0.1, 990); break;
      case 'launch': this.noise(0.08, 300, 1, 0.35, 0, 'lowpass'); this.noise(0.3, 400, 1, 0.16, 0.02, 'bandpass', 2400); break;
      case 'ramp-in': this.noise(0.4, 300, 2, 0.12, 0, 'bandpass', 1800); break;
      case 'ramp': this.arp(data === 1 ? [587, 740, 880, 1175] : [523, 659, 784, 1046], 0.07); break;
      case 'ramp-fail': this.tone(300, 0.25, 'triangle', 0.1, 120); break;
      case 'saucer': this.tone(110, 0.15, 'sine', 0.35); this.tone(880, 0.6, 'sine', 0.08, null, 0.05); break;
      case 'kickback': this.noise(0.2, 800, 1, 0.25); this.arp([392, 523, 659], 0.06); break;
      case 'cup': this.arp([392, 494, 587, 784], 0.08, 'sawtooth', 0.08); this.cheer(1.4, 0.16); break;
      case 'roar': this.roar(); break;
      case 'skill': case 'lanes': case 'letters': case 'extra': this.arp([523, 659, 784, 1046, 1318], 0.07, 'square', 0.1); break;
      case 'jackpot': case 'multiball': case 'cupwon':
        this.arp([392, 523, 659, 784, 659, 784, 1046], 0.1, 'sawtooth', 0.1);
        this.cheer(2.6, 0.36);
        break;
      case 'drain': this.tone(440, 0.7, 'triangle', 0.14, 70); this.noise(0.6, 400, 0.8, 0.06, 0.1); break;
      case 'tilt': this.tone(110, 0.8, 'square', 0.18); break;
      case 'nudge': this.tone(60, 0.08, 'sine', 0.35); this.noise(0.05, 200, 1, 0.2, 0, 'lowpass'); break;
      case 'bonus': this.arp([262, 330, 392], 0.12, 'triangle', 0.1); break;
      case 'start': this.arp([392, 523, 659, 784], 0.08, 'square', 0.1); this.cheer(1.6, 0.18); break;
      // Spinner : une série de « tic » qui ralentit, autant que de tours de palette.
      case 'spinner': for (let i = 0, n = Math.min(14, data || 1); i < n; i++) this.tone(2200, 0.018, 'square', 0.05, null, i * (0.03 + i * 0.006)); break;
      case 'orbit': this.noise(0.5, 500, 1.2, 0.14, 0, 'bandpass', 2600); this.arp([659, 784, 988], 0.06, 'triangle', 0.1); break;
      case 'promo': this.arp([523, 659, 784, 1046, 1318, 1568], 0.08, 'sawtooth', 0.09); this.cheer(2, 0.28); break;
      case 'combo': this.arp([784, 988, 1175, 1568], 0.06, 'square', 0.09); break;
    }
  }
}
