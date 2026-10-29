// Sons du babyfoot, fabriqués à la volée (Web Audio) : aucun fichier à télécharger. Allumés
// d'office (le joueur peut les couper, choix gardé sur l'appareil) : claquement des frappes, bois
// des bandes, poteaux chromés, balle qui roule sur le tapis, sifflet de l'arbitre, rumeur de la
// salle, clameur au but (déception quand l'adversaire marque).

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
    this.master.gain.setTargetAtTime(on ? 0.75 : 0, t, 0.03);
  }

  init() {
    const C = window.AudioContext || window.webkitAudioContext;
    if (!C) return;
    const c = this.ctx = new C({ latencyHint: 'interactive' });
    this.master = c.createGain();
    this.master.gain.value = 0;
    const comp = c.createDynamicsCompressor();
    comp.threshold.value = -14; comp.ratio.value = 4;
    this.master.connect(comp); comp.connect(c.destination);
    const len = c.sampleRate * 2;
    this.noiseBuf = c.createBuffer(1, len, c.sampleRate);
    const d = this.noiseBuf.getChannelData(0);
    for (let i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
    // Balle qui roule sur le tapis : bruit grave, plus fort et plus clair quand elle va vite.
    this.roll = this.loop(c.createBiquadFilter(), 0);
    this.roll.f.type = 'lowpass'; this.roll.f.frequency.value = 260; this.roll.f.Q.value = 0.7;
    // Rumeur de la salle : bruit large qui ondule lentement.
    this.crowd = this.loop(c.createBiquadFilter(), 0);
    this.crowd.f.type = 'bandpass'; this.crowd.f.frequency.value = 480; this.crowd.f.Q.value = 0.5;
    const lfo = c.createOscillator();
    this.crowdDepth = c.createGain();
    lfo.frequency.value = 0.15; this.crowdDepth.gain.value = 0;
    lfo.connect(this.crowdDepth); this.crowdDepth.connect(this.crowd.g.gain); lfo.start();
  }

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

  /** Sifflet de l'arbitre : son aigu qui roule (trille). */
  whistle(dur = 0.35, delay = 0) {
    const c = this.ctx, t = c.currentTime + delay, o = c.createOscillator(), lfo = c.createOscillator(), lg = c.createGain(), g = c.createGain();
    o.type = 'sine'; o.frequency.value = 2900;
    lfo.frequency.value = 38; lg.gain.value = 140;
    lfo.connect(lg); lg.connect(o.frequency);
    g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.16, t + 0.02);
    g.gain.setValueAtTime(0.16, t + dur - 0.05); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    o.connect(g); g.connect(this.master);
    o.start(t); lfo.start(t); o.stop(t + dur + 0.02); lfo.stop(t + dur + 0.02);
    this.noise(dur, 3000, 2, 0.04, delay);
  }

  /** Clameur (montée puis descente) ; déception : souffle qui retombe. */
  cheer(dur = 2.2, vol = 0.32, sad = false) {
    const c = this.ctx, t = c.currentTime, s = c.createBufferSource(), f = c.createBiquadFilter(), g = c.createGain();
    s.buffer = this.noiseBuf; s.loop = true;
    f.type = 'bandpass'; f.Q.value = 0.6;
    if (sad) { f.frequency.setValueAtTime(900, t); f.frequency.exponentialRampToValueAtTime(260, t + dur); }
    else { f.frequency.setValueAtTime(600, t); f.frequency.linearRampToValueAtTime(1150, t + 0.4); }
    g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(vol, t + 0.2); g.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    s.connect(f); f.connect(g); g.connect(this.master); s.start(t); s.stop(t + dur + 0.05);
  }

  ambience(on) {
    this.crowdOn = on;
    if (!this.ctx) return;
    this.crowd.g.gain.setTargetAtTime(on ? 0.03 : 0, this.ctx.currentTime, 0.6);
    this.crowdDepth.gain.setTargetAtTime(on ? 0.012 : 0, this.ctx.currentTime, 0.6);
    if (!on) this.roll.g.gain.setTargetAtTime(0, this.ctx.currentTime, 0.05);
  }

  /** À chaque image : roulement selon la vitesse de la balle. */
  update(v) {
    if (!this.on || !this.ctx || !this.crowdOn) return;
    const t = this.ctx.currentTime, k = Math.min(1, v / 220);
    this.roll.g.gain.setTargetAtTime(v > 6 ? 0.04 + 0.2 * k : 0, t, 0.05);
    this.roll.f.frequency.setTargetAtTime(160 + 600 * k, t, 0.05);
  }

  play(name, data = {}) {
    if (!this.on || !this.ctx) return;
    switch (name) {
      // Frappe : claquement sec du plastique sur le liège, plus fort selon l'élan donné.
      case 'kick': {
        const k = Math.min(1, (data.dv || 60) / 220);
        this.noise(0.03, 3200, 1.2, 0.12 + 0.2 * k, 0, 'highpass');
        this.tone(170 + 120 * k, 0.06, 'triangle', 0.2 + 0.25 * k, 90);
        break;
      }
      case 'touch': this.tone(230, 0.04, 'triangle', Math.min(0.25, (data.dv || 20) / 300)); this.noise(0.02, 2400, 2, 0.06); break;
      // Bande en bois : coup sourd.
      case 'bande': {
        const k = Math.min(1, (data.speed || 40) / 200);
        this.tone(120 + 40 * k, 0.07, 'sine', 0.15 + 0.3 * k, 80);
        this.noise(0.05, 700, 1.5, 0.08 + 0.12 * k);
        break;
      }
      case 'poteau': this.tone(1760, 0.5, 'sine', 0.12, null); this.tone(2640, 0.35, 'sine', 0.06, null, 0.005); this.noise(0.03, 5000, 3, 0.1); break;
      case 'cage': this.noise(0.06, 400, 1, 0.15, 0, 'lowpass'); break;
      case 'filet': this.noise(0.18, 300, 0.8, 0.22, 0, 'lowpass'); this.tone(70, 0.12, 'sine', 0.2); break;
      // Engagement : la balle tombe par le trou de la bande et rebondit.
      case 'serve': for (let i = 0; i < 4; i++) this.tone(260 - i * 20, 0.05, 'triangle', 0.22 / (i + 1), 140, 0.05 + i * (0.14 - i * 0.025)); break;
      case 'whistle':
        if (data.end) { this.whistle(0.28); this.whistle(0.28, 0.38); this.whistle(0.9, 0.76); }
        else if (data.long) this.whistle(0.55);
        else this.whistle(0.22);
        break;
      case 'dead': this.whistle(0.18); this.whistle(0.18, 0.24); break;
      case 'goal':
        // La balle claque au fond de la cage, puis la salle réagit.
        for (let i = 0; i < 5; i++) this.tone(180 - i * 15, 0.05, 'triangle', 0.25 / (i + 1), 100, i * 0.09);
        if (data.team === 0 || data.duo) this.cheer(2.8, 0.42); else this.cheer(1.8, 0.24, true);
        break;
      case 'save': this.cheer(0.9, 0.12, true); break;
      case 'replay': this.noise(0.5, 300, 0.8, 0.12, 0, 'bandpass', 2400); break;
      case 'end': if (data.win) this.cheer(3.2, 0.4); break;
      case 'start': this.cheer(1.6, 0.2); break;
    }
  }
}
