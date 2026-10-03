/* Correcteur d'orthographe du back-office (sans dépendance).
   Un bouton [data-proofread] vérifie les textes rédigés de son formulaire (champs [data-proof]) :
   orthographe, accords, syntaxe (Gemini) et règles du musée. Chaque correction est proposée
   dans un panneau : « Corriger » la reporte dans le champ, « Ignorer » ne la propose plus pour
   cette fiche, « Ajouter au dictionnaire » protège un nom propre. Rien n'est enregistré tant
   que l'historien n'a pas cliqué sur « Enregistrer ». */
(function () {
  'use strict';
  const BO = window.BO;
  if (!BO) return;
  const { $, $$, esc } = BO;
  const BLOCK = /^(P|DIV|SECTION|ARTICLE|HEADER|FOOTER|ASIDE|H[1-6]|UL|OL|LI|BLOCKQUOTE|TABLE|THEAD|TBODY|TFOOT|TR|TD|TH|FIGURE|FIGCAPTION|HR|PRE|DL|DT|DD|CAPTION)$/;
  const WS = '[\\s\\u00a0\\u202f\\u2007\\u2009]';
  let uid = 0;
  let panel = null;
  let state = null;

  /* ---------------------------------------------------------- texte d'un champ
     Même calcul que le serveur : nœuds de texte bout à bout, un retour à la ligne avant et
     après chaque bloc. map : [position dans le texte, nœud] pour retrouver un passage. */
  function walk(root) {
    const map = [];
    let text = '';
    const go = node => {
      node.childNodes.forEach(c => {
        if (c.nodeType === 3) { map.push([text.length, c]); text += c.nodeValue; return; }
        if (c.nodeType !== 1) return;
        if (c.tagName === 'BR') { text += '\n'; return; }
        if (/^(SCRIPT|STYLE|TEMPLATE)$/.test(c.tagName)) return;
        const block = BLOCK.test(c.tagName);
        if (block) text += '\n';
        go(c);
        if (block) text += '\n';
      });
    };
    go(root);
    return { text, map };
  }
  const isHtml = el => el.matches('textarea[data-wysiwyg]');
  function model(el) {
    if (!isHtml(el)) return { text: el.value, html: false };
    const tpl = document.createElement('template');
    tpl.innerHTML = el.value;
    return Object.assign(walk(tpl.content), { html: true, tpl });
  }

  /* ---------------------------------------------------------- retrouver un passage */
  const reEsc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const flex = s => s.split(/[\s    ]+/).map(reEsc).join(WS + '+');
  function matches(text, src) {
    const out = [];
    let rx;
    try { rx = new RegExp(src, 'gdu'); } catch (e) { return out; }
    let m;
    while ((m = rx.exec(text))) {
      out.push(m.indices[1]);
      if (m[0] === '') rx.lastIndex++;
    }
    return out;
  }
  function locate(text, s) {
    const w = '(' + flex(s.wrong) + ')';
    const B = s.before ? flex(s.before) + WS + '*' : '';
    const A = s.after ? WS + '*' + flex(s.after) : '';
    for (const src of [B + w + A, w + A, B + w]) {
      if (src === w) continue;
      const r = matches(text, src);
      if (r.length === 1) return r[0];
    }
    const all = matches(text, w);
    if (all.length === 1) return all[0];
    return all.length > (s.nth || 0) ? all[s.nth || 0] : null;
  }
  function range(map, a, b) {
    const r = document.createRange();
    let started = false;
    for (const [start, node] of map) {
      const end = start + node.nodeValue.length;
      if (!started && a >= start && a <= end) { r.setStart(node, a - start); started = true; }
      if (started && b <= end) { r.setEnd(node, b - start); return r; }
    }
    return null;
  }

  /* ---------------------------------------------------------- appliquer une correction */
  function apply(el, s) {
    const md = model(el);
    const pos = locate(md.text, s);
    if (!pos) return null;
    const [a, b] = pos;
    let next;
    if (!md.html) {
      next = md.text.slice(0, a) + s.right + md.text.slice(b);
    } else {
      let done = false;
      for (const [start, node] of md.map) {
        const end = start + node.nodeValue.length;
        if (end <= a || start >= b) continue;
        const s0 = Math.max(a, start) - start, s1 = Math.min(b, end) - start;
        node.nodeValue = node.nodeValue.slice(0, s0) + (done ? '' : s.right) + node.nodeValue.slice(s1);
        done = true;
      }
      if (!done) return null;
      next = md.tpl.innerHTML;
    }
    const before = el.value;
    BO.setValue(el, next);
    return { before, after: el.value };
  }

  /* ---------------------------------------------------------- champs de l'écran */
  function fields(form) {
    return $$('[data-proof]', form).filter(el => !el.disabled && !el.readOnly && !el.closest('[data-nocollect]') && /\p{L}{2}/u.test(el.value || ''));
  }
  const keyOf = el => el.dataset.proofK || (el.dataset.proofK = 'c' + (++uid));
  const langOf = el => ((el.getAttribute('lang') || el.closest('[lang]')?.getAttribute('lang') || 'fr').slice(0, 2) === 'en' ? 'en' : 'fr');
  // Texte d'un intitulé sans ses compléments (« ? » d'aide, astérisque, indication, compteur).
  const bare = el => el ? [...el.childNodes].filter(n => !(n.nodeType === 1 && n.matches('i, b, em, button, .hint'))).map(n => n.textContent).join('').replace(/\s+/g, ' ').trim() : '';
  function labelOf(el) {
    const parts = [];
    const pnl = el.closest('[data-panel]');
    const tab = pnl && $('button[data-tab="' + pnl.dataset.panel + '"]');
    if (tab) parts.push(bare(tab));
    let lab = bare(el.closest('.f')?.querySelector('.f__k'));
    if (!lab) lab = el.getAttribute('aria-label') || el.placeholder || 'Texte';
    const ct = bare(el.closest('.card')?.querySelector('.card__t'));
    if (ct && ct !== lab && ct !== parts[0]) parts.push(ct);
    const item = el.closest('[data-item]');
    const rep = item?.parentElement?.closest('[data-repeater]');
    parts.push(lab + (item && rep ? ' n° ' + ($$(':scope > [data-item]', rep).indexOf(item) + 1) : ''));
    return parts.join(' › ');
  }
  function flash(el) {
    const box = el.closest('.f') || el.closest('.wys') || el;
    box.classList.remove('is-proofed');
    void box.offsetWidth;
    box.classList.add('is-proofed');
    setTimeout(() => box.classList.remove('is-proofed'), 1800);
  }
  /** Montre le champ (onglet, défilement) et sélectionne le passage fautif. */
  function show(s) {
    const el = s.el;
    const pnl = el.closest('[data-panel]');
    if (pnl && !pnl.classList.contains('is-on')) $('button[data-tab="' + pnl.dataset.panel + '"]')?.click();
    el.closest('details')?.setAttribute('open', '');
    const area = isHtml(el) ? el.closest('.wys')?.querySelector('.wys__area:not([hidden])') : null;
    const target = area || el;
    target.scrollIntoView({ block: 'center', behavior: 'smooth' });
    flash(el);
    setTimeout(() => {
      target.focus({ preventScroll: true });
      if (s.status !== 'todo') return;
      if (area) {
        const md = walk(area);
        const pos = locate(md.text, s);
        const r = pos && range(md.map, pos[0], pos[1]);
        if (r) { const sel = getSelection(); sel.removeAllRanges(); sel.addRange(r); }
      } else {
        const pos = locate(el.value, s);
        if (pos) el.setSelectionRange(pos[0], pos[1]);
      }
    }, 350);
  }

  /* ---------------------------------------------------------- panneau */
  function ensurePanel() {
    if (panel) return panel;
    panel = document.createElement('aside');
    panel.className = 'proof';
    panel.hidden = true;
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-labelledby', 'proof-t');
    panel.innerHTML = '<div class="proof__head"><h2 class="proof__t" id="proof-t" tabindex="-1">Correcteur d’orthographe</h2><button type="button" class="iconbtn" data-proof-close aria-label="Fermer le correcteur" title="Fermer (Échap)">✕</button></div>'
      + '<div class="proof__status" role="status" aria-live="polite"></div>'
      + '<div class="proof__tools" hidden><button type="button" class="btn btn--sm btn--navy" data-proof-all></button><button type="button" class="btn btn--sm" data-proof-rerun>Vérifier de nouveau</button></div>'
      + '<div class="proof__list"></div>'
      + '<p class="proof__foot">Rien n’est enregistré tant que vous n’avez pas cliqué sur « Enregistrer ». Les noms propres se protègent dans le <a href="/admin/collection/dictionnaire" target="_blank" rel="noopener">dictionnaire du musée ↗</a>.</p>';
    document.body.appendChild(panel);
    panel.addEventListener('click', onClick);
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && panel && !panel.hidden && !document.querySelector('.modal')) close(); });
    return panel;
  }
  function open() {
    ensurePanel().hidden = false;
    document.body.classList.add('has-proof');
    $('#proof-t', panel).focus({ preventScroll: true });
  }
  function close() {
    if (!panel || panel.hidden) return;
    panel.hidden = true;
    document.body.classList.remove('has-proof');
    state?.btn?.focus({ preventScroll: true });
  }
  function status(html, busy = false, error = false) {
    const st = $('.proof__status', ensurePanel());
    st.innerHTML = html;
    st.classList.toggle('is-busy', busy);
    st.classList.toggle('is-error', error);
  }
  const plural = (n, one, many) => n + ' ' + (n > 1 ? many : one);

  function render() {
    const todo = state.items.filter(s => s.status === 'todo');
    const done = state.items.filter(s => s.status === 'done').length;
    const by = {
      gemini: 'par Gemini (orthographe, accords, syntaxe) et les règles du musée',
      partiel: 'par les règles du musée et Gemini, qui n’a pas pu tout relire',
      regles: 'par les règles de base du musée',
    }[state.engine] || '';
    let html = '';
    if (!state.items.length) {
      html = '<span><b class="ok">✓ Aucune faute trouvée</b> dans ' + plural(state.count, 'texte vérifié', 'textes vérifiés') + ' ' + by + '.</span>';
    } else if (todo.length) {
      html = '<span><b>' + plural(todo.length, 'correction proposée', 'corrections proposées') + '</b> ' + by + '.' + (done ? ' ' + plural(done, 'déjà appliquée', 'déjà appliquées') + '.' : '') + '</span>';
    } else {
      html = '<span><b class="ok">✓ Relecture terminée.</b> ' + (done ? plural(done, 'correction appliquée', 'corrections appliquées') + ' : pensez à enregistrer.' : 'Aucune correction appliquée.') + '</span>'
        + (done && BO.saveForm && state.form.matches('[data-json-form]') ? ' <button type="button" class="btn btn--sm btn--navy" data-proof-save>Enregistrer maintenant</button>' : '');
    }
    if (state.notice) html += '<span class="proof__notice">' + esc(state.notice) + '</span>';
    if (state.engine === 'regles' && !state.gemini) html += '<span class="proof__notice">Vérification complète (accords, conjugaison, syntaxe) : un administrateur règle la clé Gemini dans Réglages › Assistant IA.</span>';
    // Coût de la vérification (frais de Gemini, voir Système › Coûts IA).
    if (state.cost && state.cost.calls) html += '<span class="proof__cost">Coût de cette vérification : ' + esc(state.cost.label) + ' (' + plural(state.cost.calls, 'appel', 'appels') + ' à Gemini).</span>';
    else if (state.cost && state.engine === 'gemini') html += '<span class="proof__cost">Sans frais : ces textes avaient déjà été relus par Gemini.</span>';
    status(html);
    const tools = $('.proof__tools', panel);
    tools.hidden = false;
    const all = $('[data-proof-all]', tools);
    all.hidden = !todo.length;
    all.textContent = 'Tout corriger (' + todo.length + ')';
    const list = $('.proof__list', panel);
    let out = '';
    let lastK = null;
    state.items.forEach(s => {
      if (s.status === 'ignored') return;
      if (s.k !== lastK) {
        out += '<button type="button" class="proof__field" data-goto="' + s.i + '">' + esc(s.fieldLabel) + '</button>';
        lastK = s.k;
      }
      const ctx = (s.before ? '…' + esc(s.before) : '') + '<del>' + esc(s.wrong) + '</del><ins>' + esc(s.right) + '</ins>' + (s.after ? esc(s.after) + '…' : '');
      out += '<div class="psug psug--' + s.status + '" data-i="' + s.i + '">'
        + '<p class="psug__ctx" data-goto="' + s.i + '" title="Voir le passage dans le champ">' + ctx + '</p>'
        + '<p class="psug__why"><span class="ptype ptype--' + esc(s.type) + '">' + esc(s.label) + '</span> ' + esc(s.why) + '</p>'
        + '<div class="psug__act">' + (s.status === 'done'
          ? '<span class="psug__ok">✓ Corrigé</span><button type="button" class="btn btn--sm btn--ghost" data-undo="' + s.i + '">Annuler</button>'
          : s.status === 'lost'
            ? '<span class="psug__lost">Passage introuvable : le texte a changé. Vérifiez de nouveau.</span>'
            : '<button type="button" class="btn btn--sm btn--navy" data-fix="' + s.i + '">Corriger</button><button type="button" class="btn btn--sm" data-ignore="' + s.i + '" title="Ne plus proposer cette correction ici">Ignorer</button>'
              + (s.word ? '<button type="button" class="linkbtn" data-word="' + s.i + '" title="Le mot ne sera plus jamais corrigé, sur aucune fiche">+ Dictionnaire</button>' : ''))
        + '</div></div>';
    });
    list.innerHTML = out;
    updateButtons();
  }
  function updateButtons() {
    if (!state) return;
    const n = state.items.filter(s => s.status === 'todo').length;
    $$('[data-proofread]', state.form).concat(state.btn ? [state.btn] : []).forEach(b => {
      let em = $('.proofcount', b);
      if (!n) { em?.remove(); return; }
      if (!em) { em = document.createElement('em'); em.className = 'proofcount'; b.appendChild(em); }
      em.textContent = n;
    });
    const st = state.form.querySelector('[data-proof-state]') || $('[data-proof-state]');
    if (st) st.innerHTML = n ? '<b>' + plural(n, 'correction proposée', 'corrections proposées') + '</b> : voir le panneau du correcteur.' : 'Relecture faite' + (state.items.some(s => s.status === 'done') ? ' : enregistrez pour garder les corrections.' : '.');
  }

  function fix(s) {
    if (s.status !== 'todo') return false;
    const r = apply(s.el, s);
    if (!r) { s.status = 'lost'; return false; }
    s.status = 'done';
    s.undo = r;
    flash(s.el);
    const note = $('[name="_message"]', state.form);
    if (note && !note.value.trim()) note.value = 'Corrections d’orthographe (correcteur)';
    return true;
  }

  async function onClick(e) {
    const t = e.target.closest('button, [data-goto], a');
    if (!t || !state && !t.matches('[data-proof-close]')) return;
    if (t.matches('[data-proof-close]')) { close(); return; }
    const item = i => state.items[+i];
    if (t.dataset.goto !== undefined) { show(item(t.dataset.goto)); return; }
    if (t.dataset.fix !== undefined) {
      const s = item(t.dataset.fix);
      if (!fix(s)) BO.toast('Passage introuvable : le texte a changé depuis la vérification.', true);
      render();
      return;
    }
    if (t.dataset.undo !== undefined) {
      const s = item(t.dataset.undo);
      if (s.undo && s.el.value === s.undo.after) { BO.setValue(s.el, s.undo.before); s.status = 'todo'; flash(s.el); }
      else BO.toast('Le champ a été modifié depuis : annulez à la main.', true);
      render();
      return;
    }
    if (t.dataset.ignore !== undefined) {
      const s = item(t.dataset.ignore);
      s.status = 'ignored';
      render();
      if (state.scope) {
        const r = await BO.post('/admin/api/correcteur/ignorer', { scope: state.scope, sig: s.sig });
        BO.toast(r.ok ? 'Correction ignorée : elle ne sera plus proposée ici.' : (r.error || 'Impossible de mémoriser ce choix.'), !r.ok);
      }
      return;
    }
    if (t.dataset.word !== undefined) {
      const s = item(t.dataset.word);
      const r = await BO.post('/admin/api/correcteur/dictionnaire', { mot: s.word });
      if (!r.ok) { BO.toast(r.error || 'Ajout impossible.', true); return; }
      state.items.forEach(x => { if (x.status === 'todo' && x.word && x.word.toLowerCase() === s.word.toLowerCase()) x.status = 'ignored'; });
      BO.toast(r.message || 'Mot ajouté au dictionnaire.');
      render();
      return;
    }
    if (t.matches('[data-proof-all]')) {
      let lost = 0;
      // De la fin vers le début : le contexte des corrections restantes reste intact.
      [...state.items].reverse().forEach(s => { if (s.status === 'todo' && !fix(s)) lost++; });
      render();
      BO.toast(lost ? lost + ' correction(s) introuvable(s) : vérifiez de nouveau.' : 'Corrections appliquées : pensez à enregistrer.', !!lost);
      return;
    }
    if (t.matches('[data-proof-rerun]')) { run(state.btn, state.form); return; }
    if (t.matches('[data-proof-save]')) { BO.saveForm(); }
  }

  /* ---------------------------------------------------------- vérification */
  async function run(btn, form) {
    form = form || btn.closest('form') || $('form[data-json-form]');
    if (!form) return;
    const els = fields(form);
    open();
    if (!els.length) { state = null; status('Aucun texte à vérifier sur cet écran.'); $('.proof__list', panel).innerHTML = ''; $('.proof__tools', panel).hidden = true; return; }
    const scope = btn.dataset.proofScope !== undefined ? btn.dataset.proofScope : 'ecran:' + location.pathname;
    status('<span class="spin" aria-hidden="true"></span> Vérification de ' + plural(els.length, 'texte', 'textes') + '… Les longues fiches peuvent demander jusqu’à une minute.', true);
    $('.proof__tools', panel).hidden = true;
    $('.proof__list', panel).innerHTML = '';
    btn.disabled = true;
    btn.setAttribute('aria-busy', 'true');
    const payload = els.map(el => ({ k: keyOf(el), value: el.value, html: isHtml(el), lang: langOf(el), kind: el.dataset.proof || 'text' }));
    const r = await BO.post('/admin/api/correcteur', { scope, fields: payload }).catch(() => ({ error: 'Connexion perdue : réessayez.' }));
    btn.disabled = false;
    btn.removeAttribute('aria-busy');
    if (!r.ok) { status(esc(r.error || 'Vérification impossible.'), false, true); return; }
    const byK = Object.fromEntries(els.map(el => [keyOf(el), el]));
    const labels = {};
    state = {
      form, btn, scope, engine: r.engine, gemini: r.gemini, notice: r.notice, count: els.length, cost: r.cost,
      items: (r.items || []).filter(s => byK[s.k]).map((s, i) => Object.assign(s, { i, el: byK[s.k], status: 'todo', fieldLabel: labels[s.k] || (labels[s.k] = labelOf(byK[s.k])) })),
    };
    render();
  }

  document.addEventListener('click', e => {
    const b = e.target.closest('[data-proofread]');
    if (!b) return;
    e.preventDefault();
    run(b);
  });
  // Arrivée depuis Qualité › Orthographe (…/fiche/123#correcteur) : vérification immédiate.
  const auto = () => { if (location.hash === '#correcteur') { const b = $('[data-proofread]'); if (b) run(b); } };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', auto); else auto();
})();
