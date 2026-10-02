/* INTERACTIF : quiz, album, comparateur de maillots, frise, vote du Onze de légende. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const EN = document.documentElement.lang === 'en';
  const T = (fr, en) => (EN ? en : fr);
  const motion = !matchMedia('(prefers-reduced-motion: reduce)').matches;
  const store = {
    get(k, d) { try { const v = JSON.parse(localStorage.getItem(k) || 'null'); return v ?? d; } catch (e) { return d; } },
    set(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} },
  };
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const toast = m => window.SR?.toast ? SR.toast(m) : null;
  const share = async (text) => {
    try {
      if (navigator.share) { await navigator.share({ text, url: location.href }); return; }
      await navigator.clipboard.writeText(text + ' ' + location.href);
      toast(T('Copié !', 'Copied!'));
    } catch (e) {}
  };
  const shuffle = a => { for (let i = a.length - 1; i > 0; i--) { const j = Math.floor(Math.random() * (i + 1)); [a[i], a[j]] = [a[j], a[i]]; } return a; };

  /* ------------------------------------------------------------------ QUIZ */
  const quiz = $('[data-quiz]');
  if (quiz) {
    const pool = JSON.parse($('[data-quiz-data]', quiz).textContent || '[]');
    const N = Math.min(+quiz.dataset.count || 6, pool.length);
    let qs = [], i = 0, score = 0, answered = false;
    const step = name => $$('[data-step]', quiz).forEach(s => { s.hidden = s.dataset.step !== name; });
    const start = () => { qs = shuffle(pool.slice()).slice(0, N); i = 0; score = 0; step('q'); show(); };
    const show = () => {
      const q = qs[i]; answered = false;
      $('[data-quiz-num]', quiz).textContent = T('Question', 'Question') + ' ' + (i + 1) + ' / ' + N;
      $('[data-quiz-score]', quiz).textContent = T('Score : ', 'Score: ') + score;
      $('[data-quiz-progress]', quiz).innerHTML = qs.map((_, k) => `<span class="${k < i ? 'is-done' : k === i ? 'is-cur' : ''}"></span>`).join('');
      const h = $('[data-quiz-question]', quiz); h.textContent = q.q; h.classList.remove('is-in'); void h.offsetWidth; if (motion) h.classList.add('is-in');
      // Les réponses sont mélangées à chaque partie.
      const order = shuffle(q.a.map((label, k) => ({ label, k })));
      $('[data-quiz-answers]', quiz).innerHTML = order.map((o, n) => `<button type="button" class="qans" data-k="${o.k}"><span class="qans__l">${'ABCD'[n]}</span><span class="qans__t">${esc(o.label)}</span></button>`).join('');
      $('[data-quiz-verdict]', quiz).hidden = true;
      $('[data-quiz-answers] .qans', quiz)?.focus({ preventScroll: true });
    };
    quiz.addEventListener('click', e => {
      const b = e.target.closest('.qans');
      if (!b || answered) return;
      answered = true;
      const q = qs[i], k = +b.dataset.k, ok = k === q.c;
      if (ok) score++;
      $$('.qans', quiz).forEach(x => { x.setAttribute('aria-disabled', 'true'); if (+x.dataset.k === q.c) x.classList.add('is-good'); else if (x === b) x.classList.add('is-bad'); });
      $('[data-quiz-verdict-t]', quiz).textContent = ok ? T('Bien vu !', 'Spot on!') : T('Raté…', 'Missed…');
      $('[data-quiz-fact]', quiz).textContent = q.fact || '';
      const next = $('[data-quiz-next]', quiz);
      next.textContent = i + 1 < N ? T('Question suivante →', 'Next question →') : T('Voir mon score', 'See my score');
      $('[data-quiz-verdict]', quiz).hidden = false;
      $('[data-quiz-score]', quiz).textContent = T('Score : ', 'Score: ') + score;
      next.focus({ preventScroll: true });
    });
    $('[data-quiz-next]', quiz).addEventListener('click', () => { if (i + 1 < N) { i++; show(); } else finish(); });
    $$('[data-quiz-start]', quiz).forEach(b => b.addEventListener('click', start));
    const ranks = s => s >= N ? [T("Lion d'or", 'Golden Lion'), T('Sans faute. Vous connaissez Bonal par cœur.', 'Flawless. You know Bonal by heart.')]
      : s >= Math.ceil(N * 2 / 3) ? [T('Pilier de tribune', 'Terrace stalwart'), T("Très solide ! Encore un ou deux matchs d'archives et c'est parfait.", 'Very solid! A couple more archive matches and you’re there.')]
      : s >= Math.ceil(N / 3) ? [T('Lionceau en formation', 'Lion cub in training'), T('Le centre de formation vous attend : la frise est faite pour vous.', 'The academy awaits: the timeline is made for you.')]
      : [T("Recrue de l'intersaison", 'Pre-season signing'), T('Pas grave : tout est dans le musée, il suffit de le visiter.', 'No worries: it’s all in the museum, just look around.')];
    function finish() {
      step('result');
      $('[data-quiz-final]', quiz).textContent = score + '/' + N;
      const [r, txt] = ranks(score);
      $('[data-quiz-rank]', quiz).textContent = r;
      $('[data-quiz-ranktext]', quiz).textContent = txt;
      const lion = $('[data-quiz-lion]', quiz); lion.classList.remove('is-in'); void lion.offsetWidth; if (motion) lion.classList.add('is-in');
      const extra = $('[data-quiz-extra]', quiz); extra.textContent = '';
      // Sans-faute : une carte rare de l'album est offerte.
      if (score === N) {
        const rare = store.get('fcsm-album-rare', 0) + 1; store.set('fcsm-album-rare', rare);
        extra.textContent = T('Une carte rare vous attend dans l’album !', 'A rare card awaits you in the album!');
      }
      fetch('/api/quiz', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ score, total: N }) })
        .then(r => r.json()).then(d => { if (d.ok && d.games > 5) extra.textContent = (extra.textContent ? extra.textContent + ' ' : '') + T(`Vous faites mieux que ${d.better} % des joueurs.`, `You beat ${d.better}% of players.`); }).catch(() => {});
    }
    $('[data-quiz-share]', quiz).addEventListener('click', () => share(T(`J'ai fait ${score}/${N} au quiz Sochaux Rétro ! Et vous ?`, `I scored ${score}/${N} in the Sochaux Rétro quiz! Can you beat it?`)));
  }

  /* ------------------------------------------------------------------ ALBUM */
  const album = $('[data-album]');
  if (album) {
    const cards = $$('.acard[data-card]', album);
    let got = new Set(store.get('fcsm-album', []).map(String));
    // Cartes rares gagnées au quiz : une légende au hasard parmi celles qui manquent.
    let rare = store.get('fcsm-album-rare', 0);
    if (rare > 0) {
      const legends = cards.filter(c => c.dataset.tier === 'legende' && !got.has(c.dataset.card));
      while (rare > 0 && legends.length) { const c = legends.splice(Math.floor(Math.random() * legends.length), 1)[0]; got.add(c.dataset.card); rare--; toast(T('Carte rare débloquée !', 'Rare card unlocked!')); }
      store.set('fcsm-album-rare', rare); store.set('fcsm-album', [...got]);
    }
    const total = +album.dataset.total || cards.length;
    const render = () => {
      cards.forEach(c => c.classList.toggle('is-own', got.has(c.dataset.card)));
      const owned = cards.filter(c => got.has(c.dataset.card));
      $('[data-album-owned]').textContent = owned.length;
      $('[data-album-bar]').style.width = (owned.length / total * 100).toFixed(1) + '%';
      $$('[data-album-tier]').forEach(el => { el.textContent = owned.filter(c => c.dataset.tier === el.dataset.albumTier).length; });
    };
    render();
    $('[data-album-filter]')?.addEventListener('click', e => {
      const b = e.target.closest('[data-f]'); if (!b) return;
      $$('[data-f]').forEach(x => x.classList.toggle('is-on', x === b));
      const f = b.dataset.f;
      $$('.acard', album).forEach(c => { const own = c.classList.contains('is-own'); c.hidden = f === 'own' ? !own : f === 'todo' ? own : false; });
    });
    $('[data-album-share]')?.addEventListener('click', () => share(T(`J'ai ${got.size}/${total} cartes dans l'album Sochaux Rétro !`, `I have ${got.size}/${total} cards in the Sochaux Rétro album!`)));
  }

  /* ------------------------------------------------------------------ MAILLOTS */
  const jers = $('[data-jerseys]');
  if (jers) {
    const eras = JSON.parse($('[data-jerseys-data]', jers).textContent || '[]');
    const box = $('[data-jbox]', jers);
    let l = +box.dataset.l, r = +box.dataset.r, p = 50, drag = false;
    const paint = (side, idx) => {
      const e = eras[idx] || {}; const el = $(`[data-jimg="${side}"]`, box);
      el.style.backgroundImage = e.image ? `url("${e.image}")` : '';
      el.innerHTML = e.image ? '' : `<em>${esc(T('Maillot · ', 'Shirt · ') + (e.label || ''))}</em>`;
      $(`[data-jtag="${side}"]`, box).textContent = e.label || '';
      $(`[data-jtext="${side}"]`, jers).textContent = e.text || '';
    };
    const chips = () => ['l', 'r'].forEach(side => $$(`[data-side="${side}"] .jchip`, jers).forEach(c => {
      const i = +c.dataset.i; c.classList.toggle('is-on', i === (side === 'l' ? l : r)); c.classList.toggle('is-off', i === (side === 'l' ? r : l));
    }));
    const setP = v => {
      p = Math.min(100, Math.max(0, v));
      $('[data-jimg="l"]', box).style.clipPath = `inset(0 ${100 - p}% 0 0)`;
      $('[data-jline]', box).style.left = p + '%';
      const h = $('[data-jhandle]', box); h.style.left = p + '%'; h.setAttribute('aria-valuenow', Math.round(p));
    };
    paint('l', l); paint('r', r); chips(); setP(50);
    jers.addEventListener('click', e => {
      const c = e.target.closest('.jchip'); if (!c) return;
      const side = c.closest('[data-side]').dataset.side, i = +c.dataset.i;
      if (i === (side === 'l' ? r : l)) return;
      if (side === 'l') l = i; else r = i;
      paint(side, i); chips();
    });
    const move = e => { if (!drag) return; const b = box.getBoundingClientRect(); setP((e.clientX - b.left) / b.width * 100); };
    $('[data-jhandle]', box).addEventListener('pointerdown', e => { drag = true; e.preventDefault(); });
    box.addEventListener('pointerdown', e => { if (e.target.closest('[data-jhandle]')) return; drag = true; move(e); });
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', () => { drag = false; });
    $('[data-jhandle]', box).addEventListener('keydown', e => { if (e.key === 'ArrowLeft') setP(p - 5); if (e.key === 'ArrowRight') setP(p + 5); });
    // Petite démonstration au chargement (comme la maquette)
    if (motion) { let t0 = null; const tick = now => { t0 ??= now; const k = (now - t0) / 1400; if (k > 1 || drag) return; setP(50 + Math.sin(k * Math.PI * 2) * 18 * (1 - k)); requestAnimationFrame(tick); }; setTimeout(() => requestAnimationFrame(tick), 500); }
  }

  /* ------------------------------------------------------------------ FRISE */
  const track = $('[data-frtrack]');
  if (track) {
    const evs = $$('.frev', track);
    const decBtns = $$('[data-frdec]');
    const sync = () => {
      const max = track.scrollWidth - track.clientWidth;
      const pr = max > 0 ? track.scrollLeft / max : 0;
      const ev = evs[Math.min(evs.length - 1, Math.round(pr * (evs.length - 1)))];
      const y = ev ? +ev.dataset.year : 1928;
      decBtns.forEach(b => b.classList.toggle('is-on', y >= +b.dataset.frdec));
    };
    track.addEventListener('scroll', sync, { passive: true });
    $$('[data-fr]').forEach(b => b.addEventListener('click', () => track.scrollBy({ left: +b.dataset.fr * 680, behavior: motion ? 'smooth' : 'auto' })));
    decBtns.forEach(b => b.addEventListener('click', () => {
      const d = +b.dataset.frdec; const ev = evs.find(x => +x.dataset.year >= d) || evs[evs.length - 1];
      if (ev) track.scrollTo({ left: ev.offsetLeft - 40, behavior: motion ? 'smooth' : 'auto' });
    }));
    let drag = null, moved = false;
    track.addEventListener('pointerdown', e => { if (e.pointerType !== 'mouse') return; drag = { x: e.clientX, s: track.scrollLeft }; moved = false; track.classList.add('is-drag'); });
    window.addEventListener('pointermove', e => { if (!drag) return; const dx = e.clientX - drag.x; if (Math.abs(dx) > 4) moved = true; track.scrollLeft = drag.s - dx; });
    window.addEventListener('pointerup', () => { drag = null; track.classList.remove('is-drag'); });
    track.addEventListener('click', e => { if (moved) { e.preventDefault(); moved = false; } }, true);
    track.addEventListener('keydown', e => { if (e.key === 'ArrowRight') track.scrollBy({ left: 340, behavior: 'smooth' }); if (e.key === 'ArrowLeft') track.scrollBy({ left: -340, behavior: 'smooth' }); });
    // Ancre d'année (#1981)
    const y = location.hash.slice(1);
    if (/^\d{4}$/.test(y)) { const ev = evs.find(x => +x.dataset.year >= +y); if (ev) track.scrollLeft = ev.offsetLeft - 40; }
    sync();
  }

  /* ------------------------------------------------------------------ ONZE DE LÉGENDE */
  const onze = $('[data-onze]');
  if (onze) {
    const D = JSON.parse($('[data-onze-data]', onze).textContent || '{}');
    const cands = D.cands || [], slotsDef = D.slots || [];
    const posName = { G: T('Gardien', 'Goalkeeper'), D: T('Défenseur', 'Defender'), M: T('Milieu', 'Midfielder'), A: T('Attaquant', 'Forward') };
    const saved = store.get('fcsm-onze', null);
    let picks = saved?.picks?.length === 11 ? saved.picks : Array(11).fill(null);
    let voted = !!saved?.voted, slot = Math.max(0, picks.findIndex(x => !x)), q = '';
    const norm = s => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    const save = () => store.set('fcsm-onze', { picks, voted });
    const slotEls = $$('[data-slot]', onze);
    function render() {
      const line = slotsDef[slot][0];
      $('[data-onze-title]', onze).textContent = T('Poste', 'Position') + ' ' + (slot + 1) + ' · ' + posName[line];
      slotEls.forEach((el, i) => {
        el.classList.toggle('is-cur', i === slot); el.classList.toggle('is-set', !!picks[i]);
        $('.oslot__c', el).textContent = picks[i] ? '✓' : slotsDef[i][0];
        $('.oslot__l', el).textContent = picks[i] ? picks[i].name.split(' ').slice(-1)[0] : posName[slotsDef[i][0]];
      });
      const taken = new Set(picks.filter(Boolean).map(p => p.id));
      const nq = norm(q.trim());
      const list = cands.filter(c => c[2] === line && (!nq || norm(c[1]).includes(nq))).slice(0, nq ? 40 : 16);
      $('[data-onze-cands]', onze).innerHTML = list.map(c => `<button type="button" class="ocand${picks[slot]?.id === c[0] ? ' is-on' : ''}${taken.has(c[0]) && picks[slot]?.id !== c[0] ? ' is-taken' : ''}" data-id="${c[0]}">${esc(c[1])}</button>`).join('') || `<span>${T('Aucun joueur trouvé.', 'No player found.')}</span>`;
      const full = picks.every(Boolean);
      const vb = $('[data-onze-vote]', onze);
      vb.disabled = !full || voted;
      vb.textContent = voted ? T('Vote enregistré ✓', 'Vote recorded ✓') : full ? T('Valider mon Onze', 'Submit my XI') : picks.filter(Boolean).length + ' / 11 ' + T('postes', 'positions');
    }
    slotEls.forEach((el, i) => el.addEventListener('click', () => { slot = i; q = ''; $('[data-onze-q]', onze).value = ''; render(); }));
    $('[data-onze-q]', onze).addEventListener('input', e => { q = e.target.value; render(); });
    $('[data-onze-cands]', onze).addEventListener('click', e => {
      const b = e.target.closest('.ocand'); if (!b || b.classList.contains('is-taken')) return;
      const c = cands.find(x => x[0] === +b.dataset.id); if (!c) return;
      picks[slot] = { id: c[0], name: c[1], line: c[2] }; voted = false; save();
      const nxt = picks.findIndex(x => !x); if (nxt >= 0) slot = nxt;
      q = ''; $('[data-onze-q]', onze).value = ''; render();
    });
    $('[data-onze-reset]', onze).addEventListener('click', () => { picks = Array(11).fill(null); voted = false; slot = 0; save(); render(); });
    $('[data-onze-vote]', onze).addEventListener('click', async () => {
      if (!picks.every(Boolean)) return;
      try {
        const r = await fetch('/api/onze', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ picks: picks.map(p => ({ id: p.id, line: p.line })) }) });
        const d = await r.json();
        if (!d.ok) { toast(d.error || T('Vote impossible.', 'Vote failed.')); if (r.status === 429) { voted = true; save(); render(); } return; }
        voted = true; save(); render();
        const box = $('[data-onze-results]', onze); box.hidden = false;
        $('[data-onze-voters]', onze).textContent = d.voters;
        $('[data-onze-rows]', onze).innerHTML = (d.results || []).map(x => `<div class="orow"><span>${esc(x.name)}</span><span class="orow__bar"><span style="width:${+x.pct}%"></span></span><b>${+x.pct}%</b></div>`).join('');
        toast(T('Merci pour votre vote !', 'Thanks for voting!'));
      } catch (e) { toast(T('Vote impossible pour le moment.', 'Vote unavailable right now.')); }
    });
    render();
  }
})();
