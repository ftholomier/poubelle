/* Fiches audio : carte « Écouter » de l'éditeur (texte lu, voix IA) et écoute depuis l'écran de suivi. */
(function () {
  'use strict';
  const BO = window.BO;
  if (!BO) return;
  const { $, $$ } = BO;

  /* ------------------------------------------------------------ lecture (fichier ou voix du navigateur) */
  const synth = window.speechSynthesis && window.SpeechSynthesisUtterance ? window.speechSynthesis : null;
  let player = null, playingBtn = null, run = 0;
  const reset = () => {
    if (playingBtn) { playingBtn.textContent = playingBtn.dataset.label || '▶ Écouter'; playingBtn.setAttribute('aria-pressed', 'false'); }
    playingBtn = null;
  };
  function stop() {
    run++;
    if (player) { player.pause(); player.currentTime = 0; }
    if (synth) synth.cancel();
    reset();
  }
  function pickVoice(lang) {
    const want = lang.toLowerCase(), base = want.slice(0, 2);
    const norm = v => (v.lang || '').toLowerCase().replace('_', '-');
    const score = v => (norm(v) === want ? 4 : 0) + (/natural|neural|online|google|premium|enhanced|amélie|thomas|audrey|daniel|serena/i.test(v.name) ? 2 : 0);
    return synth.getVoices().filter(v => norm(v).startsWith(base)).sort((a, b) => score(b) - score(a))[0] || null;
  }
  if (synth) synth.getVoices();
  /** Joue un fichier audio, sinon lit le texte avec la voix du navigateur ; un second clic arrête. */
  BO.audioPlay = (btn, { url = '', text = '', lang = 'fr-FR' } = {}) => {
    if (playingBtn === btn) { stop(); return; }
    stop();
    playingBtn = btn;
    btn.dataset.label = btn.dataset.label || btn.textContent;
    btn.textContent = '■ Arrêter';
    btn.setAttribute('aria-pressed', 'true');
    const id = run;
    const speak = () => {
      if (!synth || !text) { reset(); BO.toast('Lecture impossible dans ce navigateur.', true); return; }
      const parts = (text.match(/[^.!?…]+[.!?…]*/g) || [text]).map(s => s.trim()).filter(Boolean);
      const voice = pickVoice(lang);
      let left = parts.length;
      parts.forEach(p => {
        const u = new SpeechSynthesisUtterance(p);
        u.lang = lang;
        if (voice) u.voice = voice;
        u.onend = u.onerror = () => { if (--left <= 0 && id === run) reset(); };
        synth.speak(u);
      });
    };
    if (url) {
      player = new Audio(url);
      player.onended = () => { if (id === run) reset(); };
      player.play().catch(speak);
    } else {
      speak();
    }
  };
  $$('[data-audio-url]').forEach(b => b.addEventListener('click', () => BO.audioPlay(b, { url: b.dataset.audioUrl })));

  /* ------------------------------------------------------------ carte « Écouter » de l'éditeur */
  const card = $('[data-audio-card]');
  if (!card) return;
  let state = {};
  try { state = JSON.parse(card.dataset.state || '{}'); } catch (e) { state = {}; }
  const area = $('[data-audio-textarea]', card);
  const info = $('[data-audio-info]', card);
  const saveBtn = $('[data-audio-save]', card);
  const lang = () => ($('input[name="_audio_lang"]:checked', card) || {}).value || 'fr';
  const SRC = { auto: 'Résumé automatique', ai: 'Rédigé par l’IA', manual: 'Écrit à la main' };

  function words(t) { return (t.trim().match(/\S+/g) || []).length; }
  function render() {
    const s = state[lang()] || { text: '', src: 'auto', words: 0 };
    area.value = s.text;
    area.setAttribute('lang', lang());
    const voice = s.url ? 'voix IA ' + (s.voice || '') + (s.dur ? ' · ' + Math.round(s.dur) + ' s' : '') : (s.hasVoice ? 'voix IA à refaire (texte changé) · voix du navigateur en attendant' : 'voix du navigateur');
    info.textContent = SRC[s.src] + (s.outdated ? ' (le texte de l’IA ne correspond plus à la fiche)' : '') + ' · ' + s.words + ' mots · ' + voice;
    saveBtn.hidden = true;
    $('[data-audio-act="automatique"]', card).hidden = s.src === 'auto';
    $('[data-audio-act="supprimer-voix"]', card).hidden = !s.hasVoice;
    const dl = $('[data-audio-download]', card);
    if (dl) {
      dl.hidden = !s.url;
      if (s.url) { dl.href = s.url; dl.download = s.name || ''; } else { dl.removeAttribute('href'); }
    }
    $('[data-audio-act="voix"]', card).textContent = s.url ? 'Refaire la voix IA' : 'Voix IA';
    // Textes d'un autre match : l'IA ne raconte pas la fiche avant correction (alerte de Qualité).
    let warn = $('[data-audio-blocked]', card);
    if (s.blocked && !warn) {
      warn = document.createElement('p');
      warn.className = 'small';
      warn.setAttribute('data-audio-blocked', '');
      warn.style.cssText = 'margin:0;padding:8px 10px;border-left:4px solid var(--red);background:var(--cream)';
      info.after(warn);
    }
    if (warn) { warn.hidden = !s.blocked; warn.textContent = s.blocked ? '⚠ ' + s.blocked + ' Seul l’en-tête du match est lu.' : ''; }
    $('[data-audio-act="ia-texte"]', card).hidden = !!s.blocked;
    $('[data-audio-act="voix"]', card).hidden = !!s.blocked && s.src !== 'manual';
  }
  // Le texte lu n'appartient pas à la fiche : il ne la marque pas « modifiée ».
  ['input', 'change'].forEach(ev => card.addEventListener(ev, e => {
    e.stopPropagation();
    if (e.target === area) {
      const s = state[lang()] || {};
      saveBtn.hidden = area.value.trim() === (s.text || '').trim();
      info.textContent = info.textContent.replace(/\d+ mots/, words(area.value) + ' mots');
    }
    if (e.target.name === '_audio_lang') { stop(); render(); }
  }));

  async function act(action, btn) {
    const was = btn ? btn.textContent : '';
    if (btn) { btn.disabled = true; btn.textContent = '…'; }
    const r = await BO.post('/admin/api/audio', { id: +card.dataset.id, lang: lang(), action, text: area.value }).catch(() => ({ error: 'Connexion perdue : réessayez.' }));
    if (btn) { btn.disabled = false; btn.textContent = was; }
    if (!r.ok) { BO.toast(r.error || 'Action impossible.', true); return; }
    state = r.state || state;
    render();
    if (r.message) BO.toast(r.message);
  }
  saveBtn.addEventListener('click', () => act('enregistrer', saveBtn));
  card.addEventListener('click', async e => {
    const b = e.target.closest('[data-audio-act]');
    if (b) {
      const a = b.dataset.audioAct;
      if (a === 'automatique' && !(await BO.confirm('Revenir au résumé automatique ?', 'Le texte actuel sera remplacé par le résumé tiré des données de la fiche.', 'Revenir'))) return;
      if (a === 'supprimer-voix' && !(await BO.confirm('Supprimer la voix IA ?', 'La voix du navigateur reprendra pour les visiteurs.', 'Supprimer', true))) return;
      if (!saveBtn.hidden && (a === 'voix' || a === 'ia-texte') && !(await BO.confirm('Texte non gardé', 'Le texte modifié n’est pas encore gardé. Continuer sans le garder ?', 'Continuer'))) return;
      stop();
      act(a, b);
      return;
    }
    const p = e.target.closest('[data-audio-play]');
    if (p) {
      const s = state[lang()] || {};
      const edited = area.value.trim() !== (s.text || '').trim();
      BO.audioPlay(p, { url: edited ? '' : (s.url || ''), text: area.value, lang: s.lang || (lang() === 'en' ? 'en-GB' : 'fr-FR') });
    }
  });
  render();
})();
