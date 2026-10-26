/* LE-DIGITAL.com — interactions du site (JavaScript natif, sans dépendance). */
(() => {
  'use strict';

  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const CFG = (() => { try { return JSON.parse($('#ld-config').textContent); } catch (e) { return {}; } })();
  const reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  const anim = CFG.animations !== false && !reduce;
  const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const num = n => String(Math.round(n * 10) / 10).replace('.', ',');
  const eur = n => (Math.round(n * 100) / 100).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' });
  const store = {
    get(k, s = localStorage) { try { return JSON.parse(s.getItem(k)); } catch (e) { return null; } },
    set(k, v, s = localStorage) { try { s.setItem(k, JSON.stringify(v)); } catch (e) { /* stockage indisponible */ } },
  };
  const replay = (el, cls = 'is-anim') => { if (!el) return; el.classList.remove(cls); void el.offsetWidth; el.classList.add(cls); };

  /** Appel à l'API du site (jeton anti-abus injecté dans la page). */
  async function api(path, data) {
    const isForm = data instanceof FormData;
    const headers = { 'X-LD-Token': CFG.token || '' };
    if (!isForm) headers['Content-Type'] = 'application/json';
    const res = await fetch(path, { method: 'POST', headers, body: isForm ? data : JSON.stringify(data || {}), credentials: 'same-origin' });
    let j = {};
    try { j = await res.json(); } catch (e) { /* réponse non JSON */ }
    if (!res.ok || j.ok === false) {
      const err = new Error(j.error || 'Petit souci de connexion. Réessaie dans un instant.');
      err.status = res.status; err.data = j;
      throw err;
    }
    return j;
  }

  const showErr = (el, msg) => { if (!el) return; el.textContent = msg || ''; el.hidden = !msg; };
  const markErr = (form, field) => { $$('.is-err', form).forEach(x => x.classList.remove('is-err')); const f = field && form.querySelector(`[name="${field}"]`); if (f) { f.classList.add('is-err'); f.focus(); } };
  const isEmail = v => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v);
  const isPhone = v => (v.replace(/\D/g, '').length >= 8);

  /* ------------------------------------------------------------------ apparitions & compteurs */
  function motion() {
    if (!anim || !('IntersectionObserver' in window)) return;
    const count = el => {
      const to = +el.dataset.countTo, t0 = performance.now();
      const tick = n => { const p = Math.min(1, (n - t0) / 1600); el.textContent = Math.round(to * (1 - Math.pow(1 - p, 3))); if (p < 1) requestAnimationFrame(tick); };
      requestAnimationFrame(tick);
    };
    const io = new IntersectionObserver(es => es.forEach(e => {
      if (!e.isIntersecting) return;
      const el = e.target; io.unobserve(el);
      if (el.dataset.countTo) { count(el); return; }
      el.style.opacity = '1'; el.style.transform = 'none';
      const d = +(el.dataset.delay || 0);
      setTimeout(() => { el.style.transition = ''; el.style.transform = ''; el.style.opacity = ''; }, d + 950);
    }), { threshold: 0.12 });
    $$('[data-reveal]').forEach(el => {
      if (el.getBoundingClientRect().top < innerHeight * 0.92) return;
      const d = +(el.dataset.delay || 0);
      el.style.opacity = '0';
      el.style.transform = el.dataset.reveal === 'scale' ? 'scale(.96)' : 'translateY(32px)';
      el.style.transition = `opacity .8s ${d}ms ease, transform .9s ${d}ms cubic-bezier(.2,.7,.2,1)`;
      io.observe(el);
    });
    $$('[data-count-to]').forEach(el => { el.textContent = '0'; io.observe(el); });
  }

  /* ------------------------------------------------------------------ en-tête mobile */
  function header() {
    const hdr = $('[data-hdr]');
    if (!hdr) return;
    // Au défilement : en-tête en « capsule » flottante + barre de progression de lecture.
    let ticking = false;
    const onScroll = () => {
      ticking = false;
      const y = scrollY, max = document.documentElement.scrollHeight - innerHeight;
      hdr.classList.toggle('is-scrolled', y > 24);
      hdr.style.setProperty('--p', max > 0 ? Math.min(1, y / max).toFixed(4) : 0);
    };
    addEventListener('scroll', () => { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
    onScroll();

    // Pastille qui glisse sous le lien survolé, puis revient sur la page en cours.
    const nav = $('[data-hdr-nav]'), ind = nav && $('.hdr__ind', nav);
    if (nav && ind) {
      const links = $$('a', nav), current = links.find(a => a.classList.contains('is-active'));
      const moveTo = (a, instant) => {
        links.forEach(l => l.classList.toggle('is-hover', l === a && a !== current));
        if (!a) { ind.style.opacity = '0'; return; }
        if (instant) ind.style.transition = 'none';
        ind.style.width = a.offsetWidth + 'px';
        ind.style.transform = `translateX(${a.offsetLeft}px)`;
        ind.style.opacity = '1';
        if (instant) { ind.getBoundingClientRect(); ind.style.transition = ''; }
      };
      nav.classList.add('has-ind');
      links.forEach(a => { a.addEventListener('mouseenter', () => moveTo(a)); a.addEventListener('focus', () => moveTo(a)); });
      nav.addEventListener('mouseleave', () => moveTo(current));
      nav.addEventListener('focusout', e => { if (!nav.contains(e.relatedTarget)) moveTo(current); });
      const place = () => moveTo(current, true);
      place();
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(place);
      addEventListener('resize', place);
    }

    // Menu mobile plein écran.
    const btn = $('[data-menu-toggle]'), menu = $('#hdr-menu');
    if (!btn || !menu) return;
    const setMenu = open => {
      menu.hidden = !open;
      btn.setAttribute('aria-expanded', String(open));
      btn.setAttribute('aria-label', open ? 'Fermer le menu' : 'Menu');
      document.documentElement.classList.toggle('menu-open', open);
    };
    btn.addEventListener('click', () => setMenu(menu.hidden));
    $$('a', menu).forEach(a => a.addEventListener('click', () => setMenu(false)));
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && !menu.hidden) { setMenu(false); btn.focus(); } });
    addEventListener('resize', () => { if (innerWidth >= 1080 && !menu.hidden) setMenu(false); });
  }

  /* ------------------------------------------------------------------ haut de page : conversation animée */
  function hero() {
    const vis = $('[data-hero-seq]');
    if (vis) {
      const steps = $$('[data-step]', vis);
      const show = step => steps.forEach(el => {
        const s = +el.dataset.step;
        el.classList.toggle('is-on', el.hasAttribute('data-only') ? s === step : step >= s);
      });
      if (!anim) show(6);
      else {
        const seq = [400, 1200, 1800, 2700, 3400, 4000];
        let timers = [];
        const run = () => { timers.forEach(clearTimeout); show(0); timers = seq.map((t, i) => setTimeout(() => show(i + 1), t)); };
        run();
        setInterval(() => { if (!document.hidden) run(); }, 11000);
      }
    }
    const w = $('[data-rotate]');
    if (w && anim) {
      let words = [];
      try { words = JSON.parse(w.dataset.rotate); } catch (e) { /* liste invalide */ }
      let i = 0;
      if (words.length > 1) setInterval(() => { if (document.hidden) return; i = (i + 1) % words.length; w.textContent = words[i]; replay(w); }, 2200);
    }
  }

  /* ------------------------------------------------------------------ avant / après */
  function beforeAfter() {
    $$('[data-ba-tabs]').forEach(tabs => {
      const rows = $$('.ba', tabs.closest('section'));
      tabs.addEventListener('click', e => {
        const b = e.target.closest('[data-ba]');
        if (!b) return;
        const after = b.dataset.ba === 'after';
        $$('[data-ba]', tabs).forEach(x => { const on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-selected', String(on)); });
        rows.forEach(r => {
          r.classList.toggle('is-after', after);
          $('.ba__ic', r).textContent = after ? '✓' : '✕';
          const t = $('.ba__t', r);
          t.textContent = after ? r.dataset.after : r.dataset.before;
          t.style.animation = 'none'; void t.offsetWidth; t.style.animation = '';
        });
      });
    });
  }

  /* ------------------------------------------------------------------ le bureau qui se range (au défilement) */
  function bureau() {
    const root = $('[data-bureau]');
    if (!root) return;
    const stage = $('.bureau__stage', root), frame = $('.bureau__frame', root);
    const t1 = $('.bureau__t--before', root), t2 = $('.bureau__t--after', root);
    const prog = $('[data-bureau-prog]', root), hint = $('[data-bureau-hint]', root);
    const items = $$('.bitem', root).map((el, i) => ({ el, x: +el.dataset.x, y: +el.dataset.y, rot: +el.dataset.rot, col: i % 3, row: Math.floor(i / 3) }));
    let last = -1, raf = 0;
    const render = () => {
      raf = 0;
      const r = root.getBoundingClientRect();
      const p = Math.min(1, Math.max(0, -r.top / (r.height - innerHeight)));
      if (Math.abs(p - last) < 0.002) return;
      last = p;
      const e0 = Math.min(1, Math.max(0, (p - 0.15) / 0.6));
      const e = e0 < 0.5 ? 4 * e0 ** 3 : 1 - (-2 * e0 + 2) ** 3 / 2;
      const tidy = e > 0.55, L = (a, b) => a + (b - a) * e;
      t1.style.opacity = 1 - e; t2.style.opacity = e;
      frame.style.opacity = e; frame.style.transform = `scale(${0.94 + 0.06 * e})`;
      stage.classList.toggle('is-tidy', tidy);
      prog.style.width = (p * 100) + '%';
      hint.textContent = p < 0.15 ? hint.dataset.start : (tidy ? hint.dataset.done : hint.dataset.progress);
      items.forEach(it => {
        const s = it.el.style;
        s.left = L(it.x, 6 + it.col * 30) + '%';
        s.top = L(it.y, 16 + it.row * 26) + '%';
        s.width = L(26, 27.5) + '%';
        s.transform = `rotate(${L(it.rot, 0)}deg)`;
      });
    };
    const on = () => { if (!raf) raf = requestAnimationFrame(render); };
    addEventListener('scroll', on, { passive: true });
    addEventListener('resize', () => { last = -1; on(); });
    render();
  }

  /* ------------------------------------------------------------------ démo : devis par l'IA */
  function devis() {
    $$('[data-devis]').forEach(root => {
      const q = s => $(`[data-devis-${s}]`, root);
      const ta = q('prompt'), btn = q('go'), ref = q('ref'), chip = q('chip'), body = q('body'), empty = q('empty');
      const client = q('client'), objet = q('objet'), lines = q('lines'), tot = q('tot'), ht = q('ht'), ttc = q('ttc'), cond = q('cond');
      let loading = false, li = 0;
      $$('[data-devis-ex]', root).forEach(b => b.addEventListener('click', () => { ta.value = b.dataset.devisEx; ta.focus(); }));
      btn.addEventListener('click', async () => {
        if (loading) return;
        const p = ta.value.trim() || root.dataset.default;
        ta.value = p; loading = true; clearInterval(li);
        const t0 = performance.now();
        btn.classList.add('is-loading'); chip.textContent = 'Rédaction…'; chip.classList.remove('is-done');
        body.hidden = true; empty.hidden = false; ref.textContent = 'En attente';
        const tick = () => { btn.textContent = `L'IA rédige… ${((performance.now() - t0) / 1000).toFixed(1)} s`; };
        tick();
        const ti = setInterval(tick, 100);
        let d;
        try { d = (await api('/api/ia/devis', { prompt: p })).devis; }
        catch (e) { d = { client: 'Client', objet: p.slice(0, 60), lignes: [{ d: 'Prestation principale', q: 1, u: 'forfait', pu: 800 }, { d: 'Fournitures', q: 1, u: 'forfait', pu: 350 }, { d: "Main-d'œuvre", q: 8, u: 'h', pu: 45 }], tva: 20, conditions: 'Devis valable 30 jours. Acompte de 30 % à la commande.' }; }
        clearInterval(ti);
        const t = (performance.now() - t0) / 1000;
        loading = false; btn.classList.remove('is-loading'); btn.textContent = root.dataset.btnAgain;
        ref.textContent = 'N° ' + new Date().getFullYear() + '-' + String(Math.floor(t * 137) % 1000).padStart(3, '0');
        client.textContent = d.client; objet.textContent = d.objet;
        lines.innerHTML = ''; tot.hidden = true; body.hidden = false; empty.hidden = true;
        const total = d.lignes.reduce((a, l) => a + (+l.q || 0) * (+l.pu || 0), 0);
        let i = 0;
        li = setInterval(() => {
          if (i >= d.lignes.length) {
            clearInterval(li);
            ht.textContent = eur(total); ttc.textContent = eur(total * (1 + (+d.tva || 20) / 100));
            cond.textContent = d.conditions || ''; tot.hidden = false;
            chip.textContent = `Prêt en ${t.toFixed(1)} s ✓`; chip.classList.add('is-done');
            return;
          }
          const l = d.lignes[i++], row = document.createElement('div');
          row.className = 'paper__line';
          row.innerHTML = `<span>${esc(l.d)} <small>× ${esc(String(l.q).replace('.', ','))} ${esc(l.u)}</small></span><b>${eur((+l.q || 0) * (+l.pu || 0))}</b>`;
          lines.appendChild(row);
        }, 260);
      });
    });
  }

  /* ------------------------------------------------------------------ simulateur gain de temps */
  function simulator() {
    $$('[data-sim]').forEach(root => {
      let cfg;
      try { cfg = JSON.parse(root.dataset.sim); } catch (e) { return; }
      const inputs = $$('[data-sim-in]', root);
      const saved0 = store.get('ld-sim');
      if (saved0 && Array.isArray(saved0.h) && saved0.h.length === inputs.length) inputs.forEach((inp, i) => { inp.value = saved0.h[i]; });
      const out = s => $(`[data-sim-${s}]`, root);
      const update = persist => {
        const h = inputs.map(i => +i.value);
        const total = h.reduce((a, b) => a + b, 0);
        const saved = cfg.tasks.reduce((a, t, i) => a + h[i] * t.rate, 0);
        inputs.forEach((inp, i) => { const o = $(`[data-sim-out="${i}"]`, root); if (o) o.textContent = num(h[i]); });
        out('saved').textContent = num(saved);
        out('year').textContent = Math.round(saved * cfg.wpy);
        out('days').textContent = Math.round(saved * cfg.wpy / cfg.hpd);
        out('total').textContent = String(Math.round(total * 10) / 10).replace('.', ',');
        out('after').textContent = String(Math.round((total - saved) * 10) / 10).replace('.', ',');
        out('pct').style.width = total ? ((total - saved) / total * 100) + '%' : '0%';
        if (persist) {
          const top = cfg.tasks.map((t, i) => [t.short, h[i]]).filter(x => x[1] > 0).sort((a, b) => b[1] - a[1]).slice(0, 3);
          store.set('ld-sim', { h, saved: Math.round(saved * 10) / 10, top });
        }
      };
      inputs.forEach(i => i.addEventListener('input', () => update(true)));
      update(false);
    });
  }

  /* ------------------------------------------------------------------ quiz « quelle formule ? » */
  function quiz() {
    $$('[data-quiz]').forEach(root => {
      let cfg;
      try { cfg = JSON.parse(root.dataset.quiz); } catch (e) { return; }
      const n = cfg.results.length;
      let i = 0, score = Array(n).fill(0);
      const dots = $$('[data-quiz-dots] span', root), ask = $('[data-quiz-ask]', root), q = $('[data-quiz-q]', root), opts = $('[data-quiz-opts]', root);
      const res = $('[data-quiz-res]', root), rt = $('[data-quiz-rt]', root), rd = $('[data-quiz-rd]', root), href = $('[data-quiz-href]', root);
      const render = () => {
        dots.forEach((d, k) => { d.className = k < i ? 'is-done' : (k === i ? 'is-cur' : ''); });
        if (i >= cfg.questions.length) {
          ask.hidden = true;
          const best = score.indexOf(Math.max(...score)), r = cfg.results[best];
          rt.textContent = r.t; rd.textContent = r.d; href.href = r.href;
          res.hidden = false; res.style.animation = 'none'; void res.offsetWidth; res.style.animation = '';
          return;
        }
        res.hidden = true; ask.hidden = false;
        const Q = cfg.questions[i];
        q.textContent = Q.t; opts.innerHTML = '';
        Q.options.forEach(o => {
          const b = document.createElement('button');
          b.type = 'button'; b.textContent = o.t;
          b.addEventListener('click', () => { score = score.map((v, k) => v + (+(o.score || [])[k] || 0)); i++; render(); });
          opts.appendChild(b);
        });
        ask.style.animation = 'none'; void ask.offsetWidth; ask.style.animation = '';
      };
      $('[data-quiz-restart]', root).addEventListener('click', () => { i = 0; score = Array(n).fill(0); render(); });
      render();
    });
  }

  /* ------------------------------------------------------------------ analyse express */
  function analyse() {
    $$('[data-analyse]').forEach(root => {
      const form = $('[data-analyse-form]', root), btn = $('[data-analyse-btn]', root);
      const empty = $('[data-analyse-empty]', root), res = $('[data-analyse-res]', root);
      const cons = $('[data-analyse-constats]', root), ideas = $('[data-analyse-idees]', root);
      let loading = false;
      form.addEventListener('submit', async e => {
        e.preventDefault();
        if (loading) return;
        loading = true; btn.textContent = "L'IA analyse…"; btn.classList.add('is-loading');
        const fd = new FormData(form);
        let r;
        try { r = await api('/api/ia/analyse', { url: fd.get('url'), job: fd.get('job'), pain: fd.get('pain') }); }
        catch (err) {
          r = { constats: ['Tes tâches répétitives méritent un vrai outil.', 'Tes infos sont sans doute éparpillées.', 'Ton site peut travailler davantage pour toi.'], idees: [{ t: 'Devis assistés', d: "L'IA prépare, tu relis et tu envoies." }, { t: 'Relances automatiques', d: 'Elles partent au bon moment, sans y penser.' }, { t: 'Assistant sur ton site', d: 'Il répond aux questions fréquentes 24 h/24.' }] };
        }
        cons.innerHTML = r.constats.map((t, i) => `<div class="an__c" style="animation-delay:${i * 80}ms"><b>0${i + 1}</b>${esc(t)}</div>`).join('');
        ideas.innerHTML = r.idees.map((x, i) => `<div class="an__i" style="animation-delay:${240 + i * 80}ms"><b>${esc(x.t)}</b><span>${esc(x.d)}</span></div>`).join('');
        empty.hidden = true; res.hidden = false;
        loading = false; btn.classList.remove('is-loading'); btn.textContent = root.dataset.btnAgain;
        if (innerWidth < 860) res.scrollIntoView({ behavior: anim ? 'smooth' : 'auto', block: 'start' });
      });
    });
  }

  /* ------------------------------------------------------------------ « Explique-moi comme à un débutant » */
  function explain() {
    $$('[data-explain]').forEach(btn => {
      const block = btn.closest('.pblock__txt');
      const box = $('[data-explain-box]', block), txt = $('[data-explain-text]', block);
      let state = 'idle';
      btn.addEventListener('click', async () => {
        if (state === 'loading') return;
        if (state === 'shown') { box.hidden = true; state = 'idle'; btn.textContent = btn.dataset.label; return; }
        state = 'loading'; btn.textContent = btn.dataset.loading; btn.classList.add('is-loading');
        let t;
        try { t = (await api('/api/ia/explique', { id: btn.dataset.explain })).text; } catch (e) { t = btn.dataset.fallback; }
        txt.textContent = t; box.hidden = false; state = 'shown';
        btn.textContent = btn.dataset.hide; btn.classList.remove('is-loading');
      });
    });
  }

  /* ------------------------------------------------------------------ formation : formats & programme */
  function formation() {
    const tabs = $('[data-formats]');
    if (tabs) {
      let items = [];
      try { items = JSON.parse(tabs.dataset.formats); } catch (e) { /* données invalides */ }
      const sec = tabs.closest('section'), q = s => $(`[data-fmt-${s}]`, sec);
      tabs.addEventListener('click', e => {
        const b = e.target.closest('[data-fmt]');
        if (!b) return;
        const f = items[+b.dataset.fmt];
        $$('[data-fmt]', tabs).forEach(x => { const on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-selected', String(on)); });
        q('k').textContent = f.k; q('t').textContent = f.t; q('d').textContent = f.d;
        q('detail').textContent = f.detail || ''; q('detail').hidden = !f.detail;
        q('todo').textContent = f.todo || ''; q('todo').hidden = !f.todo || !!f.detail;
        q('stage').style.background = f.stage;
        const big = q('big'); big.textContent = f.big; big.style.animation = 'none'; void big.offsetWidth; big.style.animation = '';
      });
    }
    $$('[data-accordion]').forEach(acc => {
      acc.addEventListener('click', e => {
        const btn = e.target.closest('.acc__btn');
        if (!btn) return;
        const it = btn.closest('.acc__it'), wasOpen = it.classList.contains('is-open');
        $$('.acc__it', acc).forEach(x => { x.classList.remove('is-open'); $('.acc__btn', x).setAttribute('aria-expanded', 'false'); $('.acc__d', x).hidden = true; });
        if (!wasOpen) { it.classList.add('is-open'); btn.setAttribute('aria-expanded', 'true'); $('.acc__d', it).hidden = false; }
      });
    });
  }

  /* ------------------------------------------------------------------ accompagnement : 4 formules */
  function formules() {
    const grid = $('[data-formules]');
    if (!grid) return;
    let items = [];
    try { items = JSON.parse(grid.dataset.formules); } catch (e) { return; }
    const sec = grid.closest('section'), q = s => $(`[data-fcur-${s}]`, sec);
    grid.addEventListener('click', e => {
      const b = e.target.closest('[data-formule]');
      if (!b) return;
      const f = items[+b.dataset.formule];
      $$('[data-formule]', grid).forEach(x => { const on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-selected', String(on)); });
      q('k').textContent = f.k; q('t').textContent = f.t; q('d').textContent = f.d; q('how').textContent = f.how;
      q('cta').textContent = f.cta; q('cta').href = f.href;
      replay(q('anim'));
    });
  }

  /* ------------------------------------------------------------------ filtres (avis, blog) */
  function filters() {
    $$('[data-filter]').forEach(bar => {
      const key = bar.dataset.filter, list = $(`[data-filter-list="${key}"]`);
      if (!list) return;
      bar.addEventListener('click', e => {
        const b = e.target.closest('button');
        if (!b) return;
        const f = b.dataset.f;
        $$('button', bar).forEach(x => { const on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-pressed', String(on)); });
        $$('.fitem', list).forEach(it => { const show = f === '*' || it.dataset.tag === f; it.hidden = !show; if (show) replay(it); });
        if (key === 'blog' && history.replaceState) {
          const u = new URL(location.href);
          if (f === '*') u.searchParams.delete('theme'); else u.searchParams.set('theme', f);
          history.replaceState(null, '', u);
        }
      });
    });
  }

  /* ------------------------------------------------------------------ la formule : secrets au survol ou au toucher */
  function formula() {
    $$('[data-formula]').forEach(f => {
      const hint = f.parentElement.querySelector('[data-formula-hint]');
      if (hint && hint.dataset.touch && matchMedia('(hover: none)').matches) hint.textContent = hint.dataset.touch;
      f.addEventListener('click', e => {
        const t = e.target.closest('.formula__t');
        if (!t || !hint) return;
        $$('.formula__t', f).forEach(x => x.classList.toggle('is-on', x === t));
        hint.textContent = t.dataset.tip;
        hint.classList.remove('is-tip'); void hint.offsetWidth; hint.classList.add('is-tip');
      });
    });
  }

  /* ------------------------------------------------------------------ RdV : diagnostic transmis */
  function diag() {
    const el = $('[data-diag]');
    if (!el) return;
    let c = {};
    try { c = JSON.parse(el.dataset.diag); } catch (e) { return; }
    const sim = store.get('ld-sim'), brief = store.get('ld-brief');
    if (!sim && !brief) return;
    $('[data-diag-title]', el).textContent = sim ? c.title_sim.replace('{h}', String(sim.saved).replace('.', ',')) : c.title_brief;
    const rows = [];
    if (sim && sim.top && sim.top.length) rows.push([c.row_tasks, sim.top.map(t => t[0]).join(', ')]);
    if (brief && brief.besoin) rows.push([c.row_need, brief.besoin]);
    if (brief && brief.formule) rows.push([c.row_formula, brief.formule]);
    rows.forEach(([k, v]) => { const d = document.createElement('div'); d.className = 'diag__row'; d.innerHTML = `<span>${esc(k)}</span><span>${esc(v)}</span>`; el.appendChild(d); });
    el.hidden = false;
  }

  /* ------------------------------------------------------------------ RdV : réservation d'un créneau */
  function booking() {
    const form = $('[data-booking]');
    if (!form) return;
    let cfg;
    try { cfg = JSON.parse(form.dataset.booking); } catch (e) { return; }
    const daysEl = $('.bk__days', form), slotsEl = $('[data-bk-slots]', form), who = $('[data-bk-who]', form);
    const go = $('[data-bk-go]', form), err = $('[data-bk-err]', form), smsBtn = $('[data-bk-sms]', form);
    // Case « rappel par SMS » absente quand les SMS sont désactivés dans le back-office.
    let days = cfg.days, mode = 0, day = 0, slot = -1, sms = !!smsBtn, lastIcs = '', sending = false;
    const done = $('[data-bk-done]'), label = $('[data-bk-label]'), smsok = $('[data-bk-smsok]');
    const renderDays = () => {
      daysEl.innerHTML = days.map((d, i) => `<button type="button" role="radio" aria-checked="${i === day}" aria-label="${esc(d.label)}" class="${i === day ? 'is-on' : ''}" data-day="${i}"><span>${esc(d.wd)}</span><b>${d.n}</b></button>`).join('');
    };
    const renderSlots = () => {
      const d = days[day];
      slotsEl.innerHTML = d ? d.slots.map((s, j) => `<button type="button" role="radio" aria-checked="${j === slot}" class="${j === slot ? 'is-on' : ''}" data-slot="${j}"${s.free ? '' : ' disabled'}>${esc(s.t)}</button>`).join('') : '';
    };
    const update = () => {
      const s = slot >= 0 && days[day] ? days[day].slots[slot] : null;
      go.disabled = !s;
      go.textContent = s ? cfg.book.replace('{t}', s.t) : cfg.choose;
      who.hidden = !s;
    };
    $('.seg', form).addEventListener('click', e => {
      const b = e.target.closest('[data-mode]');
      if (!b) return;
      mode = +b.dataset.mode;
      $$('[data-mode]', form).forEach(x => { const on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-checked', String(on)); });
    });
    daysEl.addEventListener('click', e => { const b = e.target.closest('[data-day]'); if (!b) return; day = +b.dataset.day; slot = -1; renderDays(); renderSlots(); update(); });
    slotsEl.addEventListener('click', e => {
      const b = e.target.closest('[data-slot]');
      if (!b || b.disabled) return;
      const first = slot < 0;
      slot = +b.dataset.slot; renderSlots(); update(); showErr(err, '');
      if (first) setTimeout(() => $('#bk-name').focus({ preventScroll: true }), 50);
    });
    if (smsBtn) smsBtn.addEventListener('click', () => { sms = !sms; smsBtn.setAttribute('aria-pressed', String(sms)); });
    form.addEventListener('submit', async e => {
      e.preventDefault();
      if (slot < 0 || sending) return;
      const fd = new FormData(form), name = String(fd.get('name') || '').trim(), email = String(fd.get('email') || '').trim(), phone = String(fd.get('phone') || '').trim();
      if (!name) { markErr(form, 'name'); return showErr(err, 'Indique ton prénom 🙂'); }
      if (!isEmail(email)) { markErr(form, 'email'); return showErr(err, 'Ton email semble incomplet : il me sert à t’envoyer la confirmation.'); }
      if (sms && !isPhone(phone)) { markErr(form, 'phone'); return showErr(err, 'Pour le rappel SMS, indique ton numéro (ou décoche le rappel).'); }
      markErr(form, null); showErr(err, '');
      sending = true; go.classList.add('is-loading'); go.textContent = 'Réservation…';
      try {
        const d = days[day], s = d.slots[slot];
        const r = await api('/api/rdv', { date: d.date, time: s.t, mode, name, email, phone, sms, website: fd.get('website'), sim: store.get('ld-sim'), brief: store.get('ld-brief') });
        lastIcs = r.ics || '';
        label.textContent = cfg.doneText.replace('{label}', r.label);
        if (smsok) smsok.hidden = !r.sms;
        form.hidden = true; done.hidden = false;
        done.scrollIntoView({ behavior: anim ? 'smooth' : 'auto', block: 'nearest' });
      } catch (ex) {
        if (ex.data && ex.data.days) { days = ex.data.days; day = 0; slot = -1; renderDays(); renderSlots(); }
        if (ex.data && ex.data.field) markErr(form, ex.data.field);
        showErr(err, ex.message);
      } finally {
        sending = false; go.classList.remove('is-loading'); update();
      }
    });
    $('[data-bk-ics]').addEventListener('click', () => {
      if (!lastIcs) return;
      const a = document.createElement('a');
      a.href = URL.createObjectURL(new Blob([lastIcs], { type: 'text/calendar' }));
      a.download = 'rdv-le-digital.ics';
      document.body.appendChild(a); a.click(); a.remove();
    });
    $('[data-bk-change]').addEventListener('click', async () => {
      done.hidden = true; form.hidden = false; slot = -1;
      try {
        const res = await fetch('/api/rdv/creneaux', { credentials: 'same-origin' });
        const j = await res.json();
        if (j.days && j.days.length) { days = j.days; day = 0; }
      } catch (e) { /* on garde les créneaux connus */ }
      renderDays(); renderSlots(); update();
    });
  }

  /* ------------------------------------------------------------------ RdV : formulaire */
  function contact() {
    const form = $('[data-contact]');
    if (!form) return;
    const done = $('[data-ct-done]'), err = $('[data-ct-err]'), btn = $('.ct__go', form);
    const picked = new Set();
    $$('[data-interest]', form).forEach(b => b.addEventListener('click', () => {
      const v = b.dataset.interest, on = !picked.has(v);
      if (on) picked.add(v); else picked.delete(v);
      b.setAttribute('aria-pressed', String(on));
    }));
    const sim = store.get('ld-sim'), brief = store.get('ld-brief'), time = $('[data-ct-time]', form);
    const parts = [];
    if (sim && sim.top && sim.top.length) parts.push(sim.top.map(t => `${t[0]} (${String(t[1]).replace('.', ',')} h/sem.)`).join(', '));
    if (brief && brief.taches && brief.taches !== '—') parts.push(brief.taches);
    if (time && !time.value && parts.length) time.value = parts.join(' · ');
    let sending = false;
    form.addEventListener('submit', async e => {
      e.preventDefault();
      if (sending) return;
      const fd = new FormData(form);
      const data = Object.fromEntries(['name', 'phone', 'email', 'time_text', 'why', 'website'].map(k => [k, String(fd.get(k) || '').trim()]));
      if (!data.name) { markErr(form, 'name'); return showErr(err, 'Dis-moi au moins ton prénom 🙂'); }
      if (data.email && !isEmail(data.email)) { markErr(form, 'email'); return showErr(err, 'Ton email semble incomplet.'); }
      if (!data.email && !isPhone(data.phone)) { markErr(form, 'email'); return showErr(err, 'Laisse-moi un email ou un téléphone pour que je puisse te répondre.'); }
      markErr(form, null); showErr(err, '');
      sending = true; btn.classList.add('is-loading');
      try {
        await api('/api/contact', Object.assign(data, { interests: Array.from(picked), sim, brief }));
        form.hidden = true; done.hidden = false;
        done.scrollIntoView({ behavior: anim ? 'smooth' : 'auto', block: 'nearest' });
      } catch (ex) {
        if (ex.data && ex.data.field) markErr(form, ex.data.field);
        showErr(err, ex.message);
      } finally { sending = false; btn.classList.remove('is-loading'); }
    });
  }

  /* ------------------------------------------------------------------ RdV : rappel téléphonique */
  function callback() {
    const form = $('[data-callback]');
    if (!form) return;
    let labels = [];
    try { labels = JSON.parse(form.dataset.callback); } catch (e) { /* libellés invalides */ }
    let moment = 0, sending = false;
    const err = $('[data-cb-err]', form), done = $('[data-cb-done]');
    $('.cb__moments', form).addEventListener('click', e => {
      const b = e.target.closest('[data-moment]');
      if (!b) return;
      moment = +b.dataset.moment;
      $$('[data-moment]', form).forEach(x => { const on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-checked', String(on)); });
    });
    form.addEventListener('submit', async e => {
      e.preventDefault();
      if (sending) return;
      const phone = form.phone.value.trim();
      if (!isPhone(phone)) { markErr(form, 'phone'); return showErr(err, 'Ton numéro semble incomplet.'); }
      markErr(form, null); showErr(err, '');
      sending = true;
      try {
        const r = await api('/api/rappel', { phone, moment, website: form.website.value });
        const txt = done.dataset.tpl.replace('{moment}', r.moment || labels[moment] || '');
        const cut = txt.indexOf('!') + 1;
        done.innerHTML = cut > 0 ? `<b>${esc(txt.slice(0, cut))}</b>${esc(txt.slice(cut))}` : esc(txt);
        form.hidden = true; done.hidden = false;
      } catch (ex) { showErr(err, ex.message); } finally { sending = false; }
    });
  }

  /* ------------------------------------------------------------------ dock : assistant IA + vocal */
  function dock() {
    const root = $('#dock');
    if (!root) {
      // Assistant désactivé : les boutons « Demande à l'IA » mènent à la prise de rendez-vous.
      $$('[data-open-bot]').forEach(b => b.addEventListener('click', () => { location.href = '/on-prend-rdv#formulaire'; }));
      return;
    }
    const D = CFG.dock || {};
    const panel = $('#dock-chat'), list = $('#dock-list'), sugg = $('#dock-sugg'), form = $('#dock-form'), input = $('#dock-input'), briefBtn = $('#dock-brief');
    const vpanel = $('#dock-voice'), recBar = $('#dock-rec'), idle = $('#dock-idle'), bar = $('#dock-bar');
    const toggles = $$('[data-dock-toggle]');
    let msgs = [], loading = false, briefed = false, typingEl = null;

    // Statut « dispo » selon l'heure du visiteur.
    const now = new Date(), h = now.getHours(), wd = now.getDay();
    const st = $('#dock-status');
    if (st) st.textContent = (wd > 0 && wd < 6 && h >= (D.openFrom || 9) && h < (D.openTo || 18)) ? D.statusOpen : D.statusClosed;

    const scroll = () => { list.scrollTop = list.scrollHeight; };
    const addMsg = (role, text) => {
      const w = document.createElement('div'), b = document.createElement('div');
      w.className = 'msg msg--' + (role === 'user' ? 'user' : 'bot');
      b.className = 'msg__b'; b.textContent = text;
      w.appendChild(b); list.appendChild(w); scroll();
    };
    const addBrief = br => {
      const w = document.createElement('div');
      w.className = 'msg msg--brief';
      const rows = [['Ton besoin', br.besoin], ['Tâches à automatiser', br.taches], ['Urgence', br.urgence], ['Formule conseillée', br.formule]];
      w.innerHTML = `<div class="brief"><span class="brief__t">${esc(D.briefTitle)}</span>${rows.map(([k, v]) => `<div class="brief__r"><span>${esc(k)}</span><span>${esc(v)}</span></div>`).join('')}<a href="/on-prend-rdv#reserver">${esc(D.briefCta)}</a></div>`;
      list.appendChild(w); scroll();
    };
    const typing = on => {
      if (on && !typingEl) { typingEl = document.createElement('div'); typingEl.className = 'msg__typing'; typingEl.innerHTML = '<span></span><span></span><span></span>'; list.appendChild(typingEl); scroll(); }
      if (!on && typingEl) { typingEl.remove(); typingEl = null; }
    };
    const save = () => store.set('ld-chat', { msgs, briefed }, sessionStorage);
    const updBrief = () => { briefBtn.hidden = !(msgs.filter(m => m.role === 'user').length >= 2 && !briefed && !loading); };

    // Conversation conservée pendant la visite (navigation entre les pages).
    const prev = store.get('ld-chat', sessionStorage);
    if (prev && Array.isArray(prev.msgs) && prev.msgs.length) {
      msgs = prev.msgs; briefed = !!prev.briefed;
      if (sugg) sugg.remove();
      msgs.forEach(m => { if (m.role === 'brief') addBrief(m.brief); else addMsg(m.role, m.content); });
      updBrief();
    }

    // Barre du bas : affichée à l'arrêt, masquée quand on descend, de retour dès qu'on remonte ou qu'on s'arrête.
    const wrap = bar.closest('.dbar-wrap');
    let lastY = scrollY, stillT = 0;
    const away = v => wrap.classList.toggle('is-away', v && !bar.classList.contains('is-rec'));
    addEventListener('scroll', () => {
      const y = Math.max(0, scrollY), dy = y - lastY;
      if (Math.abs(dy) < 6) return;
      away(dy > 0 && y > 80);
      lastY = y;
      clearTimeout(stillT);
      stillT = setTimeout(() => away(false), 900);
    }, { passive: true });

    const open = v => {
      panel.hidden = !v;
      toggles.forEach(b => b.setAttribute('aria-expanded', String(v)));
      if (v) { closeVoicePanel(); hideToast(); scroll(); if (matchMedia('(hover: hover)').matches) setTimeout(() => input.focus({ preventScroll: true }), 60); }
    };
    window.LDOpenBot = q => { open(true); if (q) ask(q); };
    toggles.forEach(b => b.addEventListener('click', () => open(panel.hidden)));
    $$('[data-dock-close]').forEach(b => b.addEventListener('click', () => open(false)));
    $$('[data-open-bot]').forEach(b => b.addEventListener('click', () => window.LDOpenBot()));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') { if (!panel.hidden) open(false); if (!vpanel.hidden) closeVoicePanel(); } });

    const chatHistory = () => msgs.filter(m => m.role === 'user' || m.role === 'assistant').map(m => ({ role: m.role, content: m.content }));
    async function ask(q) {
      q = (q || '').trim();
      if (!q || loading) return;
      if (sugg && sugg.isConnected) sugg.remove();
      addMsg('user', q); msgs.push({ role: 'user', content: q });
      input.value = ''; loading = true; typing(true); updBrief();
      let text;
      try { text = (await api('/api/ia/chat', { messages: chatHistory() })).text; }
      catch (e) { text = e.status === 429 ? e.message : D.error; }
      typing(false); loading = false;
      addMsg('assistant', text); msgs.push({ role: 'assistant', content: text });
      updBrief(); save();
    }
    $$('[data-ask]', root).forEach(b => b.addEventListener('click', () => ask(b.dataset.ask)));
    form.addEventListener('submit', e => { e.preventDefault(); ask(input.value); });
    briefBtn.addEventListener('click', async () => {
      if (loading) return;
      loading = true; briefed = true; updBrief(); typing(true);
      let br;
      try { br = (await api('/api/ia/brief', { messages: chatHistory() })).brief; }
      catch (e) { br = { besoin: 'À préciser au premier café', taches: '—', urgence: 'moyenne', formule: 'À définir ensemble' }; }
      typing(false); loading = false;
      store.set('ld-brief', br);
      addBrief(br); msgs.push({ role: 'brief', brief: br });
      addMsg('assistant', D.briefDone); msgs.push({ role: 'assistant', content: D.briefDone });
      save();
    });

    // --- Toast « preuve sociale » (désactivé par défaut)
    const toast = $('#dock-toast'), toastTxt = $('#dock-toast-text');
    function hideToast() { toast.hidden = true; }
    if (Array.isArray(D.socialProof) && D.socialProof.length) {
      let k = 0;
      const show = () => {
        if (!panel.hidden || !vpanel.hidden) return;
        toastTxt.textContent = D.socialProof[k++ % D.socialProof.length];
        toast.hidden = false; replay(toast, 'pop');
        setTimeout(hideToast, 6000);
      };
      setTimeout(show, 8000); setInterval(show, 26000);
    }

    // --- Vocal pour Fred
    const vTitle = $('#voice-title'), vSent = $('#voice-sent'), vReview = $('#voice-review'), vErrorP = $('#voice-error');
    const vAudio = $('#voice-audio'), vTr = $('#voice-transcript'), vSum = $('#voice-summary'), vSumTxt = $('#voice-summary-text');
    const vName = $('#voice-name'), vPhone = $('#voice-phone'), vFormErr = $('#voice-form-err'), vSend = $('#voice-send');
    const vTime = $('#voice-time'), vQ = $('#voice-question'), bars = $$('#voice-bars span');
    let phase = 'idle', mr = null, chunks = [], stream = null, actx = null, raf = 0, ti = 0, sr = null, secs = 0, vtext = '', vsum = '', blob = null, canceled = false;

    const fmtTime = s => `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
    const updTime = () => { vTime.textContent = fmtTime(secs); const qs = D.questions || []; if (qs.length) vQ.textContent = qs[Math.min(qs.length - 1, Math.floor(secs / 15))]; };
    const cleanup = () => {
      clearInterval(ti); cancelAnimationFrame(raf);
      try { if (sr) sr.stop(); } catch (e) { /* déjà arrêtée */ }
      sr = null;
      if (stream) stream.getTracks().forEach(t => t.stop());
      stream = null;
      try { if (actx) actx.close(); } catch (e) { /* déjà fermé */ }
      actx = null;
      recBar.hidden = true; idle.hidden = false; bar.classList.remove('is-rec');
      bars.forEach(b => { b.style.height = '4px'; });
    };
    function closeVoicePanel() { vpanel.hidden = true; if (phase !== 'rec') phase = 'idle'; try { vAudio.pause(); } catch (e) { /* rien */ } }
    const showPanel = which => {
      vpanel.hidden = false; panel.hidden = true; toggles.forEach(b => b.setAttribute('aria-expanded', 'false'));
      vReview.hidden = which !== 'review'; vSent.hidden = which !== 'sent'; vErrorP.hidden = which !== 'error';
    };
    const voiceError = () => { cleanup(); phase = 'error'; vTitle.textContent = D.errorTitle; showPanel('error'); };

    async function startVoice() {
      if (phase === 'rec') return;
      cleanup();
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) return voiceError();
      try { stream = await navigator.mediaDevices.getUserMedia({ audio: true }); } catch (e) { return voiceError(); }
      panel.hidden = true; vpanel.hidden = true; hideToast();
      chunks = []; canceled = false; vtext = ''; vsum = ''; blob = null;
      const types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
      const type = types.find(t => MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t));
      try { mr = new MediaRecorder(stream, type ? { mimeType: type } : undefined); } catch (e) { return voiceError(); }
      mr.ondataavailable = ev => { if (ev.data && ev.data.size) chunks.push(ev.data); };
      mr.onstop = () => { if (canceled) return; blob = new Blob(chunks, { type: (mr.mimeType || type || 'audio/webm').split(';')[0] }); review(); };
      mr.start(250);
      const AC = window.AudioContext || window.webkitAudioContext;
      if (AC) {
        try {
          actx = new AC();
          const an = actx.createAnalyser(); an.fftSize = 256;
          actx.createMediaStreamSource(stream).connect(an);
          const buf = new Uint8Array(an.frequencyBinCount), hist = Array(28).fill(0);
          let lastT = 0;
          const loop = t => {
            an.getByteFrequencyData(buf);
            if (t - lastT > 70) {
              lastT = t;
              let sum = 0; for (let i = 2; i < 60; i++) sum += buf[i];
              hist.shift(); hist.push(Math.min(1, sum / 58 / 140));
              bars.forEach((b, i) => { b.style.height = Math.max(4, hist[i] * 36) + 'px'; });
            }
            raf = requestAnimationFrame(loop);
          };
          raf = requestAnimationFrame(loop);
        } catch (e) { /* visualisation indisponible */ }
      }
      const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
      if (SR) {
        try {
          sr = new SR(); sr.lang = 'fr-FR'; sr.continuous = true; sr.interimResults = true;
          sr.onresult = ev => { let t = ''; for (let i = 0; i < ev.results.length; i++) t += ev.results[i][0].transcript + ' '; vtext = t.trim(); };
          sr.onend = () => { if (phase === 'rec' && sr) { try { sr.start(); } catch (e) { /* relance impossible */ } } };
          sr.start();
        } catch (e) { sr = null; }
      }
      phase = 'rec'; secs = 0; updTime();
      idle.hidden = true; recBar.hidden = false; bar.classList.add('is-rec'); wrap.classList.remove('is-away');
      ti = setInterval(() => { secs++; updTime(); if (secs >= (D.maxSeconds || 90)) stopVoice(); }, 1000);
    }
    function stopVoice() { if (phase !== 'rec') return; phase = 'stopping'; const r = mr; cleanup(); try { r.stop(); } catch (e) { voiceError(); } }
    function cancelVoice() { canceled = true; const r = mr; cleanup(); try { if (r && r.state !== 'inactive') r.stop(); } catch (e) { /* rien */ } phase = 'idle'; }
    async function review() {
      phase = 'review';
      vTitle.textContent = D.voiceTitle;
      vAudio.src = URL.createObjectURL(blob);
      vTr.textContent = vtext || D.noTranscript; vTr.classList.toggle('is-empty', !vtext);
      vSum.hidden = true; showErr(vFormErr, '');
      showPanel('review');
      if (vtext && vtext.split(/\s+/).length >= 6) {
        vSum.hidden = false; vSumTxt.textContent = "L'IA résume ton message…";
        try { vsum = (await api('/api/ia/resume-vocal', { transcript: vtext })).summary || ''; } catch (e) { vsum = ''; }
        if (vsum) vSumTxt.textContent = vsum; else vSum.hidden = true;
      }
    }
    vSend.addEventListener('click', async () => {
      if (!blob || vSend.classList.contains('is-loading')) return;
      const name = vName.value.trim(), phone = vPhone.value.trim();
      vPhone.classList.remove('is-err');
      if (!isPhone(phone)) { vPhone.classList.add('is-err'); vPhone.focus(); return showErr(vFormErr, 'Laisse ton téléphone pour que Fred puisse te rappeler.'); }
      showErr(vFormErr, '');
      const ext = /mp4|m4a/.test(blob.type) ? 'm4a' : (/ogg/.test(blob.type) ? 'ogg' : 'webm');
      const fd = new FormData();
      fd.append('audio', blob, 'vocal.' + ext);
      fd.append('name', name); fd.append('phone', phone);
      fd.append('transcript', vtext); fd.append('summary', vsum); fd.append('duration', fmtTime(secs));
      vSend.classList.add('is-loading');
      try {
        await api('/api/vocal', fd);
        phase = 'sent';
        vTitle.textContent = (D.sentTitle || 'Bien reçu{name} ! 🎙️').replace('{name}', name ? ', ' + name : '');
        showPanel('sent');
      } catch (e) { showErr(vFormErr, e.message); }
      finally { vSend.classList.remove('is-loading'); }
    });
    $$('[data-voice-start]').forEach(b => b.addEventListener('click', startVoice));
    $$('[data-voice-stop]').forEach(b => b.addEventListener('click', stopVoice));
    $$('[data-voice-cancel]').forEach(b => b.addEventListener('click', cancelVoice));
    $$('[data-voice-close]').forEach(b => b.addEventListener('click', closeVoicePanel));
    window.LDStartVoice = startVoice;
  }

  /* ------------------------------------------------------------------ Mes solutions : Intensiti (vidéo réelle, écran en direct) */
  function intensiti() {
    const root = $('#intensiti');
    if (!root) return;
    const seen = (el, on, off) => {
      if (!('IntersectionObserver' in window)) { on(); return; }
      new IntersectionObserver(es => es.forEach(e => (e.isIntersecting ? on : (off || (() => {})))()), { threshold: .2 }).observe(el);
    };
    // Vidéo du back-office : lecture muette en boucle seulement quand elle est visible.
    const v = $('[data-itn-video]', root);
    if (v) {
      if (anim) seen(v, () => { if (v.preload === 'none') { v.preload = 'auto'; v.load(); } v.play().catch(() => {}); }, () => v.pause());
      else v.setAttribute('controls', '');
    }
    // Écran d'exemple : horloge, diapositives qui tournent, bandeau défilant (CSS).
    const live = $('[data-itn-live]', root);
    if (live) {
      let slides = [];
      try { slides = JSON.parse(live.dataset.slides || '[]'); } catch (e) { /* rien */ }
      const clock = $('[data-itn-clock]', live), title = $('[data-itn-title]', live), sub = $('[data-itn-sub]', live), box = $('.itn-live__slide', live);
      const tick = () => { const d = new Date(); clock.textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0'); };
      let i = 0;
      const show = () => {
        if (!slides.length) return;
        const [t, s] = slides[i % slides.length];
        title.textContent = t; sub.textContent = s;
        box.classList.remove('is-swap'); void box.offsetWidth; box.classList.add('is-swap');
        i++;
      };
      tick(); show();
      setInterval(tick, 15000);
      if (anim && slides.length > 1) setInterval(() => { if (!document.hidden) show(); }, 4200);
    }
    // Photos : léger mouvement lent une fois à l'écran.
    if (anim) $$('.itn-place', root).forEach(p => seen(p, () => p.classList.add('is-in')));
  }

  /* ------------------------------------------------------------------ avis longs (Google) : « Lire la suite » */
  function clamp() {
    const boxes = $$('[data-clamp]');
    if (!boxes.length) return;
    const check = () => boxes.forEach(box => {
      if (box.classList.contains('is-open') || box.dataset.clampDone) return;
      if (box.scrollHeight - box.clientHeight < 4) return;
      box.dataset.clampDone = '1';
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'rv__more'; b.textContent = 'Lire la suite'; b.setAttribute('aria-expanded', 'false');
      b.addEventListener('click', () => {
        const open = box.classList.toggle('is-open');
        b.textContent = open ? 'Réduire' : 'Lire la suite'; b.setAttribute('aria-expanded', String(open));
      });
      box.after(b);
    });
    check();
    // Polices chargées, filtres ou redimensionnement : la hauteur des textes change.
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(check);
    window.addEventListener('resize', check, { passive: true });
    document.addEventListener('click', e => { if (e.target.closest('[data-filter] button')) requestAnimationFrame(check); });
  }

  /* ------------------------------------------------------------------ démarrage */
  const init = () => {
    [header, hero, beforeAfter, bureau, devis, simulator, quiz, analyse, explain, formation, formules, filters, formula, diag, booking, contact, callback, dock, motion, clamp, intensiti]
      .forEach(fn => { try { fn(); } catch (e) { if (window.console) console.error(e); } });
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
