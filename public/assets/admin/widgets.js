/* Communauté › Widgets & partenaires : configurateur (aperçu en direct, code script, code iframe et adresse de l'API à copier). */
(() => {
  'use strict';
  const box = document.querySelector('[data-widget-config]');
  document.querySelectorAll('[data-copy-input]').forEach(b => b.addEventListener('click', () => copy(document.getElementById(b.dataset.copyInput), b)));
  if (!box) return;
  const C = JSON.parse(box.dataset.widgetConfig);
  const $ = s => box.querySelector(s);
  const val = k => $('[data-cfg="' + k + '"]').value;
  const optsBox = $('[data-cfg-options]');
  const esc = s => String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
  let timer = null;

  // Champ d'une option selon son type : liste (select), liste filtrable (search : <datalist> et
  // suggestions de l'API pour les longues listes), date, nombre, texte.
  let dl = 0;
  function field(name, o) {
    const id = 'cfg-dl-' + (++dl);
    const opts = ch => Object.entries(ch || {}).map(([v, t]) => '<option value="' + esc(v) + '"' + (v === o.default ? ' selected' : '') + '>' + esc(t) + '</option>').join('');
    if (o.type === 'select') return '<select class="in" data-opt="' + esc(name) + '">' + opts(o.choices) + '</select>';
    if (o.type === 'search') {
      const list = Object.entries(o.choices || {}).filter(([v]) => v !== 'auto').map(([v, t]) => '<option value="' + esc(v) + '">' + esc(t) + '</option>').join('');
      return '<input class="in" type="search" data-opt="' + esc(name) + '" list="' + id + '" placeholder="' + esc((o.choices || {}).auto || 'Tapez un nom') + '" autocomplete="off"'
        + (o.suggest && o.total > Object.keys(o.choices || {}).length ? ' data-suggest="' + esc(o.suggest) + '"' : '') + '><datalist id="' + id + '">' + list + '</datalist>';
    }
    if (o.type === 'date') return '<input class="in" type="date" data-opt="' + esc(name) + '"' + (o.min ? ' min="' + esc(o.min) + '"' : '') + (o.format === 'md' ? ' data-md' : '') + '>';
    if (o.type === 'number') return '<input class="in" type="number" data-opt="' + esc(name) + '" min="' + esc(o.min) + '" max="' + esc(o.max) + '" value="' + esc(o.default) + '">';
    return '<input class="in" data-opt="' + esc(name) + '" maxlength="80" placeholder="' + esc(o.placeholder || '') + '" value="' + esc(o.default || '') + '">';
  }
  // Valeur d'un champ : pour une liste filtrable, le libellé tapé en entier vaut sa valeur ;
  // une date « jour du mois » ne garde que MM-JJ.
  function optValue(i) {
    let v = i.value.trim();
    if (i.list && v !== '') {
      const n = s => s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s*\([^)]*\)\s*$/, '').trim().toLowerCase();
      const hit = [...i.list.options].find(x => x.value === v) || [...i.list.options].find(x => n(x.textContent) === n(v));
      v = hit ? hit.value : v;
    }
    if (i.hasAttribute('data-md') && /^\d{4}-\d\d-\d\d$/.test(v)) v = v.slice(5);
    return v;
  }
  let sugT = null;
  function suggest(i) {
    clearTimeout(sugT);
    sugT = setTimeout(() => fetch(i.dataset.suggest + '?q=' + encodeURIComponent(i.value), { credentials: 'same-origin' }).then(r => r.ok ? r.json() : null).then(j => {
      if (!j) return;
      i.list.innerHTML = j.items.filter(x => x.value !== 'auto').map(x => '<option value="' + esc(x.value) + '">' + esc(x.label) + '</option>').join('');
    }).catch(() => {}), 200);
  }

  function renderOptions() {
    const w = C.widgets[val('widget')];
    optsBox.innerHTML = '';
    Object.entries(w.options || {}).forEach(([name, o]) => {
      const l = document.createElement('label');
      l.className = 'f';
      l.style.flex = '1 1 220px';
      l.innerHTML = '<span class="f__k">' + esc(o.label) + '</span>' + field(name, o) + (o.help ? '<small class="muted">' + esc(o.help) + '</small>' : '');
      optsBox.appendChild(l);
    });
    if (!Object.keys(w.options || {}).length) optsBox.innerHTML = '<p class="small muted" style="margin:0">Ce widget n’a pas d’option : il choisit seul son contenu chaque jour.</p>';
  }

  function update() {
    const key = val('widget');
    const w = C.widgets[key];
    const q = new URLSearchParams();
    if (val('lang') !== 'fr') q.set('lang', val('lang'));
    q.set('theme', val('theme'));
    if (val('format')) q.set('format', val('format'));
    optsBox.querySelectorAll('[data-opt]').forEach(i => { const v = optValue(i); const def = (w.options[i.dataset.opt] || {}).default || ''; if (v !== '' && v !== def) q.set(i.dataset.opt, v); });
    const p = C.partners.find(x => x.id === val('partner'));
    if (p && p.key) q.set('key', p.key);
    const src = C.base + '/w/' + key + '?' + q.toString();
    const api = new URLSearchParams(q); api.delete('theme'); api.delete('format');
    const apiUrl = C.base + '/api/v1/widgets/' + key + (api.toString() ? '?' + api.toString() : '');
    const title = w.name + ' · musée';
    $('[data-cfg-out="iframe"]').value = '<iframe src="' + esc(src) + '" title="' + esc(title) + '" data-musee="' + key + '" width="100%" height="' + w.height + '" style="border:0;max-width:440px" loading="lazy"></iframe>';
    $('[data-cfg-out="api"]').value = apiUrl;
    // Code script : mêmes réglages en attributs data-* (theme, lang, format, options, key).
    const attrs = [...q.entries()].map(([k, v]) => ' data-' + k + '="' + esc(v) + '"').join('');
    $('[data-cfg-out="script"]').value = '<div data-musee-widget="' + key + '"' + attrs + '></div>\n' + C.loader;
    // Changer une option chez le partenaire, sans recharger sa page : modifier l'attribut puis relancer.
    const first = Object.keys(w.options || {})[0];
    const dyn = $('[data-cfg-out="dynamic"]');
    if (dyn) {
      dyn.closest('[data-cfg-dyn]').hidden = !first;
      dyn.value = first ? '<script>\n  var el = document.querySelector(\'[data-musee-widget="' + key + '"]\');\n  el.setAttribute(\'data-' + first + '\', \'' + (first === 'adversaire' ? 'nantes' : 'auto') + '\');\n  MuseeWidgets.reload(el);\n</script>' : '';
    }
    $('[data-cfg-open]').href = src;
    const warn = [];
    if ((w.access === 'reserve' || w.access === 'payant') && !p) warn.push('Ce widget est ' + (w.access === 'reserve' ? 'réservé' : 'payant') + ' : choisissez un partenaire, sinon le code affichera « clé requise ».');
    if (w.access === 'payant' && p && !p.paid) warn.push('« ' + p.name + ' » n’a pas d’offre payante en cours : le widget lui sera refusé.');
    if (w.access === 'desactive') warn.push('Ce widget est désactivé : il n’est servi à personne (l’aperçu reste visible pour vous).');
    if (p && p.domains.length) warn.push('Clé limitée à : ' + p.domains.join(', ') + ' (le widget ne s’affichera que sur ces sites).');
    $('[data-cfg-warn]').textContent = warn.join(' ');
    // Aperçu : sans la clé (vous êtes connecté), pour ne pas compter dans les statistiques.
    q.delete('key'); q.set('apercu', '1');
    clearTimeout(timer);
    timer = setTimeout(() => { $('[data-cfg-preview]').src = '/w/' + key + '?' + q.toString(); $('[data-cfg-preview]').style.height = w.height + 'px'; }, 250);
  }

  function copy(input, btn) {
    if (!input) return;
    input.select();
    (navigator.clipboard ? navigator.clipboard.writeText(input.value) : Promise.reject()).catch(() => document.execCommand('copy'));
    const t = btn.textContent; btn.textContent = 'Copié ✓'; setTimeout(() => { btn.textContent = t; }, 1500);
  }

  box.addEventListener('change', e => { if (e.target.matches('[data-cfg="widget"]')) renderOptions(); update(); });
  box.addEventListener('input', e => { if (e.target.matches('[data-opt]')) { if (e.target.dataset.suggest) suggest(e.target); update(); } });
  box.querySelectorAll('[data-cfg-copy]').forEach(b => b.addEventListener('click', () => copy($('[data-cfg-out="' + b.dataset.cfgCopy + '"]'), b)));
  document.querySelectorAll('[data-cfg-pick]').forEach(b => b.addEventListener('click', () => {
    $('[data-cfg="widget"]').value = b.dataset.cfgPick; renderOptions(); update(); box.scrollIntoView({ behavior: 'smooth' });
  }));
  addEventListener('message', e => {
    const d = e.data;
    if (d && d.type === 'musee-widget') document.querySelectorAll('iframe').forEach(f => { if (f.contentWindow === e.source) f.style.height = d.height + 'px'; });
  });
  renderOptions();
  update();
})();

/* Onglet Écrans : lien à donner à la régie (clé du partenaire, format, langue, adversaire,
   horloge) et, dans l'éditeur de playlist, options utiles seulement pour le type de slide choisi. */
(() => {
  'use strict';
  const box = document.querySelector('[data-screen-link]');
  if (box) {
    const C = JSON.parse(box.dataset.screenLink);
    const val = k => box.querySelector('[data-sl="' + k + '"]').value;
    const out = box.querySelector('[data-sl-out]');
    const update = () => {
      const q = new URLSearchParams();
      const p = C.partners.find(x => x.id === val('partner'));
      if (p && p.key) q.set('key', p.key);
      ['format', 'lang', 'adversaire', 'horloge'].forEach(k => { if (val(k) !== '') q.set(k, val(k)); });
      const path = '/ecran/' + val('id');
      out.value = C.base + path + (q.toString() ? '?' + q.toString() : '');
      q.delete('key');
      box.querySelector('[data-sl-open]').href = path + (q.toString() ? '?' + q.toString() : '');
      box.querySelector('[data-sl-warn]').textContent = p ? '' : 'Sans partenaire, l’adresse ne marche que si le mode écran est en accès libre.';
    };
    box.addEventListener('change', update);
    box.addEventListener('input', e => { if (e.target.matches('[data-sl]')) update(); });
    box.querySelector('[data-sl-copy]').addEventListener('click', e => {
      out.select();
      (navigator.clipboard ? navigator.clipboard.writeText(out.value) : Promise.reject()).catch(() => document.execCommand('copy'));
      const b = e.currentTarget, t = b.textContent; b.textContent = 'Copié ✓'; setTimeout(() => { b.textContent = t; }, 1500);
    });
    update();
  }
  /* Contrôle avant diffusion : POST /admin/widgets/ecrans/controle (jeton CSRF de BO.post). */
  const esc = t => String(t).replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
  const dur = s => (s >= 60 ? Math.floor(s / 60) + ' min ' : '') + (s % 60 ? (s % 60) + ' s' : (s ? '' : '0 s'));
  const runCheck = async (panel, payload) => {
    const out = panel.querySelector('[data-check-out]');
    out.innerHTML = '<p class="small muted" style="margin:0">Contrôle en cours…</p>';
    payload.date = panel.querySelector('[data-check-date]').value;
    const r = window.BO ? await window.BO.post('/admin/widgets/ecrans/controle', payload) : { error: 'Back-office non chargé.' };
    if (!r.ok) { out.innerHTML = '<p class="alert alert--error small" style="margin:0">' + esc(r.error || 'Contrôle impossible.') + '</p>'; return; }
    let h = '<p class="small" style="margin:0 0 8px"><b>Adversaire</b> : ' + esc(r.opponent.text) + '</p>';
    h += '<div class="table"><table><thead><tr><th class="t-num">#</th><th>Slide</th><th class="t-num">Durée</th><th>État</th></tr></thead><tbody>';
    r.rows.forEach(x => {
      h += '<tr' + (x.shown ? '' : ' class="muted"') + '><td class="t-num">' + x.n + '</td><td>' + esc(x.name) + '</td><td class="t-num">' + x.duration + ' s</td><td>'
        + (x.shown ? '<b style="color:var(--green,#2f8a4f)">✓ affichée</b>' : '<b style="color:var(--red)">⚠ sautée</b>') + (x.note ? ' <small>· ' + esc(x.note) + '</small>' : '') + '</td></tr>';
    });
    h += '</tbody></table></div><p class="small" style="margin:8px 0 0"><b>' + r.shown + ' slide' + (r.shown > 1 ? 's' : '') + ' affichée' + (r.shown > 1 ? 's' : '') + '</b>'
      + (r.skipped ? ', ' + r.skipped + ' sautée' + (r.skipped > 1 ? 's' : '') : '') + ' · une boucle dure réellement <b>' + dur(r.duration) + '</b>'
      + (r.duration !== r.planned ? ' (au lieu de ' + dur(r.planned) + ' prévues)' : '') + ' · jour contrôlé : ' + esc(r.date.split('-').reverse().join('/')) + ', ' + (r.lang === 'en' ? 'anglais' : 'français') + '.</p>';
    out.innerHTML = h;
  };
  const slPanel = document.querySelector('[data-check="sl"]');
  if (slPanel && box) {
    const v = k => box.querySelector('[data-sl="' + k + '"]').value;
    slPanel.querySelector('[data-check-run]').addEventListener('click', () => runCheck(slPanel, { id: v('id'), adversaire: v('adversaire'), lang: v('lang') }));
  }
  const form = document.getElementById('editeur');
  if (!form) return;
  const edPanel = form.querySelector('[data-check="ed"]');
  if (edPanel) edPanel.querySelector('[data-check-run]').addEventListener('click', () => {
    const d = window.BO && window.BO.collect ? window.BO.collect(form) : {};
    runCheck(edPanel, { id: d.id || '', name: d.name || '', slides: d.slides || [], playlist_adversaire: d.adversaire || '', playlist_lang: d.lang || 'fr', adversaire: '', lang: '' });
  });
  const refresh = () => form.querySelectorAll('[data-item]').forEach(it => {
    const sel = it.querySelector('[data-slide-type]');
    if (!sel) return;
    it.querySelectorAll('[data-opt-for]').forEach(o => { o.hidden = !o.dataset.optFor.split(' ').includes(sel.value); });
  });
  form.addEventListener('change', e => { if (e.target.matches('[data-slide-type]')) refresh(); });
  form.addEventListener('input', () => setTimeout(refresh, 0));
  if (form.querySelector('[data-save], button[type="submit"]') === null) form.querySelectorAll('input, select, textarea, button').forEach(el => { el.disabled = true; });
  refresh();
})();

/* Mosaïque : [data-masonry] (grille CSS à rangées de 8 px) ; chaque enfant prend autant de
   rangées que sa hauteur réelle, recalculé à chaque changement de taille (aperçus en iframe). */
(() => {
  'use strict';
  document.querySelectorAll('[data-masonry]').forEach(g => {
    g.classList.add('is-masonry');
    const row = 8;
    const fit = el => {
      const mb = parseFloat(getComputedStyle(el).marginBottom) || 0;
      el.style.gridRowEnd = 'span ' + Math.max(1, Math.ceil((el.getBoundingClientRect().height + mb) / row));
    };
    const ro = 'ResizeObserver' in window ? new ResizeObserver(es => es.forEach(e => fit(e.target))) : null;
    [...g.children].forEach(el => { fit(el); if (ro) ro.observe(el); });
    addEventListener('resize', () => [...g.children].forEach(fit));
    addEventListener('load', () => [...g.children].forEach(fit));
  });
})();

/* Catalogue : filtre par catégorie (puces, sans rechargement, état dans l'adresse : #cat=matchs),
   puis nouveau calcul de la mosaïque. */
(() => {
  'use strict';
  const bar = document.querySelector('[data-cat-filter]');
  const grid = document.querySelector('.wcat');
  if (!bar || !grid) return;
  const apply = (cat, push) => {
    if (!bar.querySelector('[data-cat="' + CSS.escape(cat) + '"]')) cat = '';
    bar.querySelectorAll('[data-cat]').forEach(b => { const on = b.dataset.cat === cat; b.classList.toggle('is-on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    [...grid.children].forEach(el => { el.hidden = cat !== '' && !(el.dataset.cats || '').split(' ').includes(cat); });
    if (push) history.replaceState(null, '', cat ? '#cat=' + cat : location.pathname + location.search);
    dispatchEvent(new Event('resize')); // la mosaïque se recalcule
  };
  const fromHash = () => (location.hash.match(/^#cat=([a-z-]+)$/) || [])[1] || '';
  bar.addEventListener('click', e => { const b = e.target.closest('[data-cat]'); if (b) apply(b.dataset.cat, true); });
  addEventListener('hashchange', () => apply(fromHash(), false));
  if (fromHash()) apply(fromHash(), false);
})();
