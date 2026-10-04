/* Fiches et pages de synthèse : bouton « Écouter » (explication audio). Voix IA enregistrée si elle existe, sinon voix du navigateur. */
(() => {
  'use strict';
  const btn = document.querySelector('[data-audio]');
  if (!btn) return;
  const box = document.querySelector('[data-audio-text]');
  const say = box && box.querySelector('[data-audio-say]');
  const label = btn.querySelector('[data-audio-label]');
  const playLabel = label.textContent, stopLabel = btn.dataset.stop || 'Stop';
  const lang = btn.dataset.audioLang || 'fr-FR';
  const src = btn.dataset.audioSrc || '';
  const synth = window.speechSynthesis && window.SpeechSynthesisUtterance ? window.speechSynthesis : null;
  if (!say || (!src && !synth)) return;
  btn.hidden = false;
  let audio = null, playing = false, run = 0;

  const set = on => {
    playing = on;
    btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    btn.classList.toggle('is-playing', on);
    label.textContent = on ? stopLabel : playLabel;
  };
  // La meilleure voix de la langue disponible sur l'appareil (voix « naturelles » d'abord).
  const pickVoice = () => {
    const want = lang.toLowerCase(), base = want.slice(0, 2);
    const norm = v => (v.lang || '').toLowerCase().replace('_', '-');
    const score = v => (norm(v) === want ? 4 : 0) + (/natural|neural|online|google|premium|enhanced|amélie|thomas|audrey|daniel|serena/i.test(v.name) ? 2 : 0);
    return synth.getVoices().filter(v => norm(v).startsWith(base)).sort((a, b) => score(b) - score(a))[0] || null;
  };
  if (synth) { synth.getVoices(); if (synth.addEventListener) synth.addEventListener('voiceschanged', () => synth.getVoices()); }

  function speak() {
    synth.cancel();
    const id = ++run;
    // Phrase par phrase : certains navigateurs coupent les lectures longues.
    const parts = (say.textContent.match(/[^.!?…]+[.!?…]*/g) || [say.textContent]).map(s => s.trim()).filter(Boolean);
    const voice = pickVoice();
    let left = parts.length;
    parts.forEach(p => {
      const u = new SpeechSynthesisUtterance(p);
      u.lang = lang;
      if (voice) u.voice = voice;
      u.onend = u.onerror = () => { if (--left <= 0 && id === run) set(false); };
      synth.speak(u);
    });
    set(true);
  }
  function stop() {
    run++;
    if (audio) { audio.pause(); audio.currentTime = 0; }
    if (synth) synth.cancel();
    set(false);
  }
  btn.addEventListener('click', () => {
    if (playing) { stop(); return; }
    box.hidden = false;
    if (src) {
      audio = audio || new Audio(src);
      audio.onended = () => set(false);
      audio.play().then(() => set(true)).catch(() => { if (synth) speak(); });
    } else {
      speak();
    }
  });
  window.addEventListener('pagehide', stop);
})();
