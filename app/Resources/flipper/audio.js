// Sons du flipper, fabriqués à la volée (Web Audio) : aucun fichier son à télécharger.
// Coupés par défaut ; le joueur les allume (choix gardé sur l'appareil).

export class Sound {
  constructor() {
    this.on = false;
    this.ctx = null;
  }

  enable(on) {
    this.on = on;
    if (on && !this.ctx) {
      const C = window.AudioContext || window.webkitAudioContext;
      if (!C) { this.on = false; return; }
      this.ctx = new C();
      this.master = this.ctx.createGain();
      this.master.gain.value = 0.5;
      this.master.connect(this.ctx.destination);
      const len = this.ctx.sampleRate;
      this.noiseBuf = this.ctx.createBuffer(1, len, this.ctx.sampleRate);
      const d = this.noiseBuf.getChannelData(0);
      for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
    }
    if (on && this.ctx && this.ctx.state === 'suspended') this.ctx.resume();
  }

  tone(freq, dur, type = 'square', vol = 0.2, to = null, delay = 0) {
    const c = this.ctx, t = c.currentTime + delay, o = c.createOscillator(), g = c.createGain();
    o.type = type; o.frequency.setValueAtTime(freq, t);
    if (to) o.frequency.exponentialRampToValueAtTime(to, t + dur);
    g.gain.setValueAtTime(vol, t); g.gain.exponentialRampToValueAtTime(0.001, t + dur);
    o.connect(g); g.connect(this.master); o.start(t); o.stop(t + dur + 0.02);
  }

  noise(dur, freq = 1200, q = 1, vol = 0.2, delay = 0, type = 'bandpass', sweep = null) {
    const c = this.ctx, t = c.currentTime + delay, s = c.createBufferSource(), f = c.createBiquadFilter(), g = c.createGain();
    s.buffer = this.noiseBuf; f.type = type; f.frequency.setValueAtTime(freq, t); f.Q.value = q;
    if (sweep) f.frequency.exponentialRampToValueAtTime(sweep, t + dur);
    g.gain.setValueAtTime(vol, t); g.gain.exponentialRampToValueAtTime(0.001, t + dur);
    s.connect(f); f.connect(g); g.connect(this.master); s.start(t); s.stop(t + dur + 0.02);
  }

  arp(notes, step = 0.09, type = 'square', vol = 0.12) {
    notes.forEach((n, i) => this.tone(n, step * 1.8, type, vol, null, i * step));
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
  }

  play(name, data) {
    if (!this.on || !this.ctx) return;
    switch (name) {
      case 'flip': this.noise(0.05, 1800, 2, 0.12); this.tone(90, 0.06, 'triangle', 0.15); break;
      case 'hit': if ((data || 0) > 300) this.noise(0.03, 2600, 3, Math.min(0.12, (data || 0) / 6000)); break;
      case 'bumper': this.tone(330, 0.09, 'square', 0.14, 160); this.noise(0.04, 3000, 2, 0.1); break;
      case 'sling': this.tone(520, 0.06, 'triangle', 0.14, 300); break;
      case 'target': this.tone(1046, 0.05, 'square', 0.1); this.noise(0.03, 4000, 3, 0.08); break;
      case 'targets-up': this.tone(200, 0.12, 'square', 0.08, 400); break;
      case 'lane': this.tone(660, 0.08, 'square', 0.1, 990); break;
      case 'launch': this.noise(0.35, 400, 1, 0.18, 0, 'bandpass', 2400); break;
      case 'ramp-in': this.noise(0.4, 300, 2, 0.1, 0, 'bandpass', 1800); break;
      case 'ramp': this.arp([523, 659, 784, 1046], 0.07); break;
      case 'ramp-fail': this.tone(300, 0.25, 'triangle', 0.1, 120); break;
      case 'saucer': this.tone(110, 0.15, 'sine', 0.3); this.tone(880, 0.6, 'sine', 0.08, null, 0.05); break;
      case 'kickback': this.noise(0.2, 800, 1, 0.2); this.arp([392, 523, 659], 0.06); break;
      case 'cup': this.arp([392, 494, 587, 784], 0.08, 'sawtooth', 0.08); break;
      case 'roar': this.roar(); break;
      case 'skill': case 'lanes': case 'letters': case 'extra': this.arp([523, 659, 784, 1046, 1318], 0.07, 'square', 0.1); break;
      case 'jackpot': case 'multiball': case 'cupwon':
        this.arp([392, 523, 659, 784, 659, 784, 1046], 0.1, 'sawtooth', 0.1);
        this.noise(1.6, 900, 0.7, 0.12, 0.2, 'bandpass'); // la foule
        break;
      case 'drain': this.tone(440, 0.7, 'triangle', 0.14, 70); break;
      case 'tilt': this.tone(110, 0.8, 'square', 0.18); break;
      case 'nudge': this.tone(60, 0.08, 'sine', 0.3); break;
      case 'bonus': this.arp([262, 330, 392], 0.12, 'triangle', 0.1); break;
    }
  }
}
