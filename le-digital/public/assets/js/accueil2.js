/* LE-DIGITAL.com — Page d'accueil (/) : film du héros, orbite, parcours de formation, tâches IA, assistant. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  let CFG = {};
  try { CFG = JSON.parse(($('#ld-config') || {}).textContent || '{}'); } catch (e) { /* configuration absente */ }
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const anim = CFG.animations !== false && !reduce;
  // Déclenche une fonction quand l'élément entre à l'écran (et éventuellement quand il en sort).
  const seen = (el, on, off, threshold = 0.25) => {
    if (!('IntersectionObserver' in window)) { on(); return; }
    new IntersectionObserver(es => es.forEach(e => (e.isIntersecting ? on() : off && off())), { threshold }).observe(el);
  };

  /* ------------------------------------------------------------------ titre : 1re ligne sur une seule ligne, la plus grande possible */
  function fitTitle() {
    const h = $('[data-a2-fit]');
    if (!h) return;
    const l1 = $('.a2-h1__l1', h), l2 = $('.a2-h1__l2', h);
    const wide = matchMedia('(min-width: 641px)');
    let raf = 0;
    const fit = () => {
      raf = 0;
      [l1, l2].forEach(el => { el.style.fontSize = ''; });
      if (!wide.matches) return;
      const avail = h.clientWidth, used = l1.scrollWidth;
      if (!avail || !used) return;
      const size = parseFloat(getComputedStyle(l1).fontSize) * avail / used * 0.995;
      const px = Math.max(28, Math.min(132, size)) + 'px';
      [l1, l2].forEach(el => { el.style.fontSize = px; });
    };
    const later = () => { if (!raf) raf = requestAnimationFrame(fit); };
    fit();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(later);
    addEventListener('resize', later);
  }

  /* ------------------------------------------------------------------ apparition des maquettes */
  function inview() {
    $$('[data-a2-inview]').forEach(el => {
      if (!anim) { el.classList.add('is-in'); return; }
      let done = false;
      seen(el, () => { if (!done) { done = true; el.classList.add('is-in'); } });
    });
  }

  /* ------------------------------------------------------------------ film du héros : 4 scènes, sous-titres animés */
  function film() {
    const root = $('[data-a2-film]');
    if (!root) return;
    const scenes = $$('[data-scene]', root), caps = $$('[data-cap]', root), segs = $$('[data-go]', root), play = $('[data-a2-play]', root);
    const DUR = 5200;
    let cur = 0, t0 = 0, elapsed = 0, paused = !anim, visible = true, raf = 0;
    // Chaque mot du sous-titre apparaît à son tour (le dernier groupe en rouge).
    caps.forEach(c => {
      const words = c.textContent.trim().split(/\s+/);
      c.textContent = '';
      words.forEach((w, k) => {
        const s = document.createElement('span');
        s.className = 'w' + (k >= words.length - 2 && words.length > 4 ? ' w--hl' : '');
        s.style.setProperty('--k', k);
        s.textContent = w;
        c.append(s, ' ');
      });
    });
    const show = i => {
      cur = (i + scenes.length) % scenes.length;
      scenes.forEach((s, k) => {
        // Retirer puis remettre la classe relance les animations CSS de la scène.
        s.classList.remove('is-on');
        if (k === cur) { void s.offsetWidth; s.classList.add('is-on'); }
      });
      caps.forEach((c, k) => {
        c.hidden = k !== cur;
        if (k === cur) { c.style.animation = 'none'; void c.offsetWidth; c.style.animation = ''; $$('.w', c).forEach(w => { w.style.animation = 'none'; void w.offsetWidth; w.style.animation = ''; }); }
      });
      segs.forEach((b, k) => { b.classList.toggle('is-done', k < cur); $('i', b).style.setProperty('--p', 0); b.setAttribute('aria-current', k === cur ? 'step' : 'false'); });
      elapsed = 0; t0 = performance.now();
    };
    const tick = now => {
      raf = 0;
      if (paused || !visible) return;
      elapsed += now - t0; t0 = now;
      $('i', segs[cur]).style.setProperty('--p', Math.min(1, elapsed / DUR));
      if (elapsed >= DUR) show(cur + 1);
      raf = requestAnimationFrame(tick);
    };
    const run = () => { if (!raf && !paused && visible) { t0 = performance.now(); raf = requestAnimationFrame(tick); } };
    const setPaused = p => {
      paused = p;
      root.classList.toggle('is-paused', p);
      play.setAttribute('aria-label', p ? 'Lancer l’animation' : 'Mettre en pause l’animation');
      run();
    };
    play.addEventListener('click', () => setPaused(!paused));
    segs.forEach((b, k) => b.addEventListener('click', () => { show(k); run(); }));
    seen(root, () => { visible = true; run(); }, () => { visible = false; }, 0.15);
    document.addEventListener('visibilitychange', () => { visible = !document.hidden; run(); });
    show(0);
    setPaused(paused);
  }

  /* ------------------------------------------------------------------ orbite : une tâche du métier s'allume et s'automatise */
  function orbit() {
    const root = $('[data-a2-orbit]');
    if (!root || !anim) return;
    const items = $$('.a2-orbit__it', root), toast = $('[data-a2-toast]', root), txt = $('[data-a2-toast-t]', root);
    const base = root.dataset.toast || '';
    let i = 0, timer = 0;
    const step = () => {
      items.forEach((it, k) => it.classList.toggle('is-hot', k === i));
      txt.textContent = `${base} : ${items[i].textContent.trim()}`;
      toast.classList.remove('is-pop'); void toast.offsetWidth; toast.classList.add('is-pop');
      i = (i + 1) % items.length;
    };
    seen(root, () => { if (!timer) { step(); timer = setInterval(step, 2400); } }, () => { clearInterval(timer); timer = 0; });
  }

  /* ------------------------------------------------------------------ parcours de formation : l'info-bulle passe d'étape en étape */
  function chart() {
    const root = $('[data-a2-chart]');
    if (!root) return;
    const ms = $$('[data-ms]', root), tip = $('[data-a2-tip]', root), tt = $('[data-a2-tip-t]', root), td = $('[data-a2-tip-d]', root);
    let data = [];
    try { data = JSON.parse(($('[data-a2-ms]', root) || {}).textContent || '[]'); } catch (e) { /* étapes illisibles */ }
    if (!ms.length || !tip) return;
    let i = 0, timer = 0;
    const go = k => {
      i = k % ms.length;
      ms.forEach((m, j) => m.classList.toggle('is-on', j === i));
      const left = parseFloat(ms[i].style.left) || 0;
      tip.style.left = left + '%';
      tip.classList.toggle('is-left', left > 55);
      if (data[i]) { tt.textContent = data[i].t || ''; td.textContent = data[i].d || ''; }
    };
    go(0);
    ms.forEach((m, k) => m.addEventListener('mouseenter', () => { clearInterval(timer); timer = 0; go(k); }));
    if (!anim) return;
    seen(root, () => { if (!timer) timer = setInterval(() => go(i + 1), 2600); }, () => { clearInterval(timer); timer = 0; });
  }

  /* ------------------------------------------------------------------ tâches : l'IA les traite une à une */
  function tasks() {
    const root = $('[data-a2-tasks]');
    if (!root) return;
    const items = $$('[data-task]', root);
    if (!anim) { items.forEach(t => t.classList.add('is-done')); return; }
    let i = 0, timer = 0;
    const step = () => {
      if (i >= items.length) {
        timer = setTimeout(() => { items.forEach(t => t.classList.remove('is-done', 'is-work')); i = 0; step(); }, 3200);
        return;
      }
      const t = items[i];
      t.classList.add('is-work');
      timer = setTimeout(() => { t.classList.remove('is-work'); t.classList.add('is-done'); i++; step(); }, 850);
    };
    seen(root, () => { if (!timer) step(); }, () => { clearTimeout(timer); timer = 0; });
  }

  /* ------------------------------------------------------------------ assistant : questions qui défilent, envoi vers le chat du site */
  function ask() {
    const form = $('[data-a2-ask]');
    if (!form) return;
    const input = $('input', form);
    const open = q => {
      q = (q || '').trim();
      if (window.LDOpenBot) window.LDOpenBot(q || undefined);
      else location.href = '/on-prend-rdv#formulaire';
    };
    form.addEventListener('submit', e => { e.preventDefault(); open(input.value); input.value = ''; });
    $$('[data-q]').forEach(b => b.addEventListener('click', () => open(b.dataset.q)));
    // Exemples de questions tapés dans le champ (tant que le visiteur n'écrit pas).
    let list = [];
    try { list = JSON.parse(input.dataset.typer || '[]'); } catch (e) { /* liste illisible */ }
    const ph = input.getAttribute('placeholder') || '';
    if (anim && list.length) {
      let qi = 0, ci = 0, dir = 1, timer = 0;
      const typeTick = () => {
        if (document.activeElement === input || input.value) { input.setAttribute('placeholder', ph); timer = setTimeout(typeTick, 1200); return; }
        const q = list[qi];
        ci += dir;
        input.setAttribute('placeholder', q.slice(0, ci) + (dir > 0 ? '|' : ''));
        if (dir > 0 && ci >= q.length) { dir = -1; timer = setTimeout(typeTick, 1900); return; }
        if (dir < 0 && ci <= 0) { dir = 1; qi = (qi + 1) % list.length; }
        timer = setTimeout(typeTick, dir > 0 ? 45 : 18);
      };
      seen(form, () => { if (!timer) typeTick(); }, () => { clearTimeout(timer); timer = 0; input.setAttribute('placeholder', ph); });
    }
    // Réponse d'exemple écrite comme par l'assistant.
    const demo = $('[data-a2-type]');
    if (demo && anim) {
      const full = demo.dataset.a2Type || demo.textContent;
      let started = false;
      seen(demo, () => {
        if (started) return;
        started = true;
        demo.textContent = ''; demo.classList.add('is-typing');
        let k = 0;
        const step = () => {
          k = Math.min(full.length, k + 2);
          demo.textContent = full.slice(0, k);
          if (k < full.length) setTimeout(step, 22); else demo.classList.remove('is-typing');
        };
        setTimeout(step, 500);
      }, null, 0.6);
    }
  }

  const init = () => [fitTitle, inview, film, orbit, chart, tasks, ask].forEach(fn => { try { fn(); } catch (e) { if (window.console) console.error(e); } });
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
