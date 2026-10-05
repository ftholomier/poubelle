/*
 * Boutique › éditeur de modèles. Le dessin (calques) est gardé ici ; chaque changement demande au
 * serveur l'aperçu sur le produit et le fichier d'impression (mêmes tracés que le PDF de
 * l'imprimeur). Dans le fichier d'impression : clic = choisir un calque, glisser = le déplacer,
 * flèches = 1 mm (Maj : 10 mm). « Enregistrer » envoie le dessin complet.
 */
(() => {
  'use strict';
  const root = document.querySelector('[data-shop-editor]');
  if (!root || !window.BO) return;
  const BO = window.BO;
  const $ = (s, r = root) => r.querySelector(s);
  const $$ = (s, r = root) => [...r.querySelectorAll(s)];
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const D = JSON.parse(document.getElementById('shop-data').textContent);
  const model = D.model;
  const sup = D.support;
  const faceKeys = Object.keys(sup.faces);
  let face = faceKeys.find(k => (model.faces[k].layers || []).length) || faceKeys[0];
  let sel = null;
  let dirty = false;
  const values = {};
  const uid = () => Math.random().toString(36).slice(2, 8);
  const F = () => model.faces[face];
  const L = () => F().layers;
  const cur = () => L().find(l => l.id === sel) || null;
  const r1 = v => Math.round(v * 10) / 10;
  // Fond foncé (fond de la face, sinon couleur du produit) : textes en jaune, sinon en bleu nuit.
  const dark = () => { const h = (F().bg || model.color || '#FFFFFF').replace('#', ''); const n = parseInt(h, 16); return 0.299 * (n >> 16) + 0.587 * ((n >> 8) & 255) + 0.114 * (n & 255) < 140; };

  /* ------------------------------------------------------------ rendu (serveur) */
  let busy = false, again = false, last = null, pendingAfterDrag = false;
  const render = async () => {
    if (busy) { again = true; return; }
    busy = true;
    const f = F();
    // positions envoyées : les boîtes renvoyées valent pour elles (cf. boxOf)
    const sent = Object.fromEntries(f.layers.map(l => [l.id, [+l.x || 0, +l.y || 0]]));
    const r = await BO.post('/admin/boutique/apercu', { support: sup.key, face, color: model.color, bg: f.bg, layers: f.layers, values }, true, true).catch(() => null);
    busy = false;
    if (again) { again = false; render(); return; }
    if (!r || !r.ok) return;
    // Pendant un glisser, ne pas remplacer le dessin sous la souris : on refera le rendu au lâcher.
    if (drag) { again = false; pendingAfterDrag = true; return; }
    r.pos = sent;
    last = r;
    $('[data-mock]').innerHTML = r.mockup;
    $('[data-print]').innerHTML = r.print;
    decorate();
    const w = $('[data-warn]');
    w.hidden = !r.warn.length;
    w.innerHTML = r.warn.map(esc).join('<br>');
    if (!document.activeElement || !document.activeElement.closest('[data-fields]')) fields();
  };
  let t = null;
  const soon = (ms = 120) => { clearTimeout(t); t = setTimeout(render, ms); };
  const changed = (ms) => { dirty = true; soon(ms); };

  // Repères dans le fichier d'impression : fonds perdus grisés, bord fini en pointillés, sélection.
  const NS = 'http://www.w3.org/2000/svg';
  const el = (name, attrs) => { const n = document.createElementNS(NS, name); for (const k in attrs) n.setAttribute(k, attrs[k]); return n; };
  function decorate() {
    const svg = $('[data-print] svg');
    if (!svg) return;
    svg.removeAttribute('width');
    svg.removeAttribute('height');
    const f = F(), b = +f.bleed || 0;
    if (b) {
      const p = `M${-b} ${-b}H${f.w + b}V${f.h + b}H${-b}Z M0 0V${f.h}H${f.w}V0Z`;
      svg.appendChild(el('path', { d: p, fill: 'rgba(14,31,77,.18)', 'fill-rule': 'evenodd', 'pointer-events': 'none' }));
    }
    svg.appendChild(el('rect', { x: 0, y: 0, width: f.w, height: f.h, fill: 'none', stroke: '#1e3fa8', 'stroke-width': Math.max(f.w, f.h) / 400, 'stroke-dasharray': Math.max(f.w, f.h) / 80, 'pointer-events': 'none' }));
    const g = +gridSel.value;
    if (g > 0) {
      let d = '';
      for (let x = g; x < f.w; x += g) d += `M${x} 0V${f.h}`;
      for (let y = g; y < f.h; y += g) d += `M0 ${y}H${f.w}`;
      svg.appendChild(el('path', { d, stroke: 'rgba(30,63,168,.22)', 'stroke-width': Math.max(f.w, f.h) / 900, fill: 'none', 'pointer-events': 'none' }));
      svg.appendChild(el('path', { d: `M${f.w / 2} 0V${f.h}M0 ${f.h / 2}H${f.w}`, stroke: 'rgba(30,63,168,.45)', 'stroke-width': Math.max(f.w, f.h) / 700, fill: 'none', 'pointer-events': 'none' }));
    }
    $('[data-align-btns]').classList.toggle('is-off', !cur());
    const cl = cur();
    if (cl && cl.type === 'text' && cl.fit && +cl.h > 0) svg.appendChild(el('rect', { x: cl.x, y: cl.y, width: cl.w, height: cl.h, fill: 'rgba(246,196,0,.12)', stroke: '#c99a00', 'stroke-width': Math.max(f.w, f.h) / 500, 'stroke-dasharray': Math.max(f.w, f.h) / 120, 'pointer-events': 'none' }));
    const box = last && sel && last.boxes[sel];
    if (box) {
      const pad = Math.max(f.w, f.h) / 200;
      svg.appendChild(el('rect', { x: box[0] - pad, y: box[1] - pad, width: box[2] - box[0] + 2 * pad, height: box[3] - box[1] + 2 * pad, fill: 'none', stroke: '#e2402c', 'stroke-width': Math.max(f.w, f.h) / 300, 'pointer-events': 'none', 'data-selbox': '1' }));
    }
    $('[data-dims]').textContent = `${f.w} × ${f.h} mm` + (b ? ` + ${b} mm de fonds perdus` : '');
  }

  /* ------------------------------------------------------------ barre : faces, couleurs, fond */
  function bar() {
    $('[data-faces]').innerHTML = faceKeys.map(k => `<button type="button" class="seg__b${k === face ? ' is-on' : ''}" data-face="${esc(k)}">${esc(sup.faces[k].label)}</button>`).join('');
    const cols = Object.entries(sup.colors || {});
    $('[data-colors-wrap]').hidden = !cols.length;
    $('[data-colors]').innerHTML = cols.map(([n, h]) => `<button type="button" class="sw${h === model.color ? ' is-on' : ''}" style="--c:${h}" title="${esc(n)}" data-color="${h}"></button>`).join('');
    $('[data-bg]').innerHTML = colorPick('bg', F().bg || '', true);
  }
  root.addEventListener('click', e => {
    const fb = e.target.closest('[data-face]');
    if (fb) { face = fb.dataset.face; sel = null; bar(); list(); props(); render(); return; }
    const cb = e.target.closest('[data-color]');
    if (cb) { model.color = cb.dataset.color; bar(); changed(0); }
  });

  // Choix de couleur : la charte, ou une couleur libre ; « aucune » si permis.
  function colorPick(name, value, allowNone) {
    const pal = Object.entries(D.palette);
    return `<div class="swatches" data-pick="${name}">` + (allowNone ? `<button type="button" class="sw sw--none${!value ? ' is-on' : ''}" title="Aucune" data-v=""></button>` : '')
      + pal.map(([n, h]) => `<button type="button" class="sw${value === h ? ' is-on' : ''}" style="--c:${h}" title="${esc(n)}" data-v="${h}"></button>`).join('')
      + `<input type="color" value="${value || '#0e1f4d'}" title="Autre couleur" data-v-free></div>`;
  }
  root.addEventListener('click', e => {
    const b = e.target.closest('[data-pick] [data-v]');
    if (!b) return;
    setColor(b.closest('[data-pick]').dataset.pick, b.dataset.v);
  });
  root.addEventListener('input', e => {
    if (e.target.matches('[data-pick] [data-v-free]')) setColor(e.target.closest('[data-pick]').dataset.pick, e.target.value.toUpperCase());
  });
  function setColor(name, v) {
    if (name === 'bg') { F().bg = v; bar(); changed(0); return; }
    const l = cur();
    if (!l) return;
    l[name] = v;
    props();
    changed(0);
  }

  /* ------------------------------------------------------------ calques */
  const label = l => l.type === 'logo' ? 'Logo de l’association' + (l.style === 'mono' ? ' (une couleur)' : '')
    : l.type === 'text' && l.mode === 'client' && String(l.field || '').startsWith('match_') ? '⚽ Ton match · ' + (((D.tonmatch || []).find(x => x.field === l.field) || {}).label || l.field)
    : l.type === 'text' && l.mode === 'client' && l.list ? '☰ Phrase au choix (' + (((D.lists || {})[l.list] || {}).name || 'liste') + ')'
    : l.type === 'text' ? (l.mode === 'client' ? '✎ ' : '') + '« ' + (String(l.text || '').slice(0, 28) || '…') + ' »'
    : l.type === 'rect' ? 'Rectangle' : 'Rond';
  function list() {
    const ls = L();
    $('[data-layers]').innerHTML = ls.map((l, i) => `<li class="${l.id === sel ? 'is-on' : ''}" data-layer-id="${esc(l.id)}"><button type="button" class="layers__n" data-select>${esc(label(l))}</button>`
      + `<span class="layers__acts"><button type="button" class="iconbtn" data-move="-1" title="Vers le fond"${i ? '' : ' disabled'}>↑</button><button type="button" class="iconbtn" data-move="1" title="Vers le dessus"${i < ls.length - 1 ? '' : ' disabled'}>↓</button><button type="button" class="iconbtn" data-dup title="Copier">⧉</button><button type="button" class="iconbtn" data-del title="Supprimer">×</button></span></li>`).join('')
      || '<li class="xs muted">Aucun calque : ajoutez le logo ou un texte.</li>';
  }
  $('[data-layers]').addEventListener('click', e => {
    const li = e.target.closest('[data-layer-id]');
    if (!li) return;
    const id = li.dataset.layerId, ls = L(), i = ls.findIndex(l => l.id === id);
    if (e.target.closest('[data-del]')) { ls.splice(i, 1); if (sel === id) sel = null; }
    else if (e.target.closest('[data-dup]')) { const c = JSON.parse(JSON.stringify(ls[i])); c.id = uid(); c.x += 5; c.y += 5; ls.splice(i + 1, 0, c); sel = c.id; }
    else if (e.target.closest('[data-move]')) { const j = i + +e.target.closest('[data-move]').dataset.move; [ls[i], ls[j]] = [ls[j], ls[i]]; }
    else { sel = id; list(); props(); decorate(); return; }
    list(); props(); changed(0);
  });
  root.addEventListener('click', e => {
    const b = e.target.closest('[data-add]');
    if (!b) return;
    const f = F(), w = f.w, h = f.h, k = b.dataset.add;
    let l;
    if (k === 'logo') { const lw = Math.min(w * 0.35, h * 0.5 / 1.133); l = { type: 'logo', x: r1((w - lw) / 2), y: r1(h * 0.1), w: r1(lw), style: 'couleurs', color: '#FDC729' }; }
    else if (k === 'rect' || k === 'ellipse') l = { type: k, x: r1(w * 0.3), y: r1(h * 0.3), w: r1(w * 0.4), h: r1(Math.min(h * 0.2, w * 0.4)), fill: k === 'ellipse' ? '#F6C400' : '', stroke: k === 'rect' ? '#F6C400' : '', sw: k === 'rect' ? r1(Math.max(0.5, w / 200)) : 0, r: 0 };
    else {
      const size = Math.max(8, Math.round(Math.min(w, h * 2) / 8));
      l = { type: 'text', x: r1(w * 0.05), y: r1(h * 0.6), w: r1(w * 0.9), text: k === 'client' ? 'Votre texte' : 'Jaune et bleu depuis 1928', font: 'display', size, color: dark() ? '#F6C400' : '#0E1F4D', align: 'center', upper: true, spacing: 0, lh: 1.1, fit: k !== 'text', mode: k === 'text' ? 'fixed' : 'client', field: k === 'client' ? 'texte' : '', label: k === 'client' ? 'Votre texte' : '', max: 20, h: 0, min: 10 };
    }
    if (k === 'match') {
      const tm = (D.tonmatch || []).find(x => x.field === b.dataset.field) || {};
      Object.assign(l, { field: tm.field, label: tm.label, text: tm.text, fit: true, mode: 'client', upper: tm.field !== 'match_phrase', h: 0, min: 8 });
    }
    if (k === 'phrase') {
      // Phrase au choix : le client choisit dans une liste de la banque de textes (réduite pour tenir dans un cadre).
      const lk = b.dataset.list || Object.keys(D.lists || {})[0] || '';
      const c = ((D.lists || {})[lk] || {}).choices || [];
      Object.assign(l, { list: lk, field: 'phrase_' + lk, label: 'Votre phrase', text: c[0] || 'Votre phrase', fit: true, h: r1(Math.min(h * 0.3, w * 0.35)), y: r1(h * 0.62), min: sup.mockup === 'cap' ? 20 : Math.max(10, Math.round(l.size * 0.4)) });
      if (sup.mockup === 'cap') l.size = Math.max(l.size, 28);
    }
    l.id = uid();
    L().push(l);
    sel = l.id;
    list(); props(); changed(0);
  });

  /* ------------------------------------------------------------ réglages du calque */
  const num = (k, lab, step = 0.5, min = '') => `<label class="f f--inline"><span class="f__k">${lab}</span><input class="in in--sm" type="number" step="${step}" ${min !== '' ? `min="${min}"` : ''} data-k="${k}" value="${esc(cur()[k] ?? '')}"></label>`;
  const chk = (k, lab) => `<label class="toggle"><input type="checkbox" data-k="${k}"${cur()[k] ? ' checked' : ''}><span class="toggle__box"></span><span>${lab}</span></label>`;
  function props() {
    const box = $('[data-props]');
    const l = cur();
    box.hidden = !l;
    if (!l) { fields(); return; }
    let h = `<div class="card__head"><h2 class="card__t card__t--sm">${esc(label(l))}</h2><button type="button" class="btn btn--sm btn--ghost" data-center title="Centrer sur la largeur">Centrer</button></div>`;
    h += `<div class="pgrid">${num('x', 'X (mm)')}${num('y', 'Y (mm)')}${num('w', 'Largeur', 0.5, 1)}${l.type === 'rect' || l.type === 'ellipse' ? num('h', 'Hauteur', 0.5, 0.2) : ''}</div>`;
    if (l.type === 'logo') {
      h += `<label class="f"><span class="f__k">Version</span><select class="in" data-k="style"><option value="couleurs"${l.style !== 'mono' ? ' selected' : ''}>En couleurs (blason bleu et jaune)</option><option value="mono"${l.style === 'mono' ? ' selected' : ''}>Une seule couleur (sur textile foncé)</option></select></label>`;
      if (l.style === 'mono') h += `<div class="f"><span class="f__k">Couleur</span>${colorPick('color', l.color, false)}</div>`;
    } else if (l.type === 'text') {
      h += `<label class="f"><span class="f__k">${l.mode === 'client' ? 'Texte d’exemple (remplacé par celui du client)' : 'Texte'}</span><textarea class="in" rows="2" data-k="text">${esc(l.text)}</textarea></label>`;
      h += `<div class="pgrid"><label class="f f--inline"><span class="f__k">Police</span><select class="in in--sm" data-k="font">${Object.entries(D.fonts).map(([k, n]) => `<option value="${k}"${l.font === k ? ' selected' : ''}>${esc(n)}</option>`).join('')}</select></label>${num('size', 'Corps (pt)', 1, 2)}${num('spacing', 'Espacement', 10)}${num('lh', 'Interligne', 0.05, 0.6)}</div>`;
      h += `<div class="seg" style="margin:6px 0">${['left', 'center', 'right'].map(a => `<button type="button" class="seg__b${l.align === a ? ' is-on' : ''}" data-align="${a}">${{ left: 'À gauche', center: 'Centré', right: 'À droite' }[a]}</button>`).join('')}</div>`;
      h += `<div class="row" style="gap:12px;flex-wrap:wrap">${chk('upper', 'Capitales')}${chk('fit', 'Réduire pour tenir dans le cadre')}</div>`;
      if (l.fit) h += `<div class="pgrid">${num('h', 'Hauteur du cadre (mm, 0 : une ligne)', 1, 0)}${num('min', 'Corps minimum (pt)', 1, 2)}</div><p class="xs muted" style="margin:2px 0 6px">Le texte est réduit, puis passe sur plusieurs lignes si le cadre a une hauteur, sans descendre sous le corps minimum (lisibilité, broderie). Un texte qui ne tient pas, même au minimum, n’est pas proposé au client.</p>`;
      h += `<div class="f"><span class="f__k">Couleur</span>${colorPick('color', l.color, false)}</div>`;
      h += `<label class="toggle"><input type="checkbox" data-client${l.mode === 'client' ? ' checked' : ''}><span class="toggle__box"></span><span>Rempli par le client</span></label>`;
      if (l.mode === 'client') h += `<label class="f"><span class="f__k">Réponse du client</span><select class="in in--sm" data-k="list"><option value="">Texte libre (il l’écrit)</option>${Object.entries(D.lists || {}).map(([k, v]) => `<option value="${esc(k)}"${l.list === k ? ' selected' : ''}>Choix dans la liste « ${esc(v.name)} » (${v.choices.length} phrases)</option>`).join('')}</select></label>`
        + (l.list ? `<p class="xs muted" style="margin:4px 0 0">Le client choisit une phrase validée de la liste. Gérez les phrases dans <a href="/admin/boutique/textes" target="_blank">Boutique › Banque de textes</a>. Les phrases trop longues pour le cadre sont écartées automatiquement (liste ci-dessous, dans l’essai des champs).</p>` : '')
        + `<div class="pgrid"><label class="f f--inline"><span class="f__k">Question posée</span><input class="in in--sm" data-k="label" value="${esc(l.label)}" placeholder="${l.list ? 'Votre phrase' : 'Votre texte'}"></label><label class="f f--inline"><span class="f__k">Nom du champ</span><input class="in in--sm" data-k="field" value="${esc(l.field)}" placeholder="texte"></label>${l.list ? '' : num('max', 'Caractères max', 1, 1)}</div><p class="xs muted" style="margin:4px 0 0">Deux calques avec le même nom de champ reçoivent le même texte (par exemple le prénom devant et au dos).</p>`;
    } else {
      h += `<div class="f"><span class="f__k">Remplissage</span>${colorPick('fill', l.fill || '', true)}</div><div class="f"><span class="f__k">Contour</span>${colorPick('stroke', l.stroke || '', true)}</div><div class="pgrid">${num('sw', 'Épaisseur du contour (mm)', 0.1, 0)}${l.type === 'rect' ? num('r', 'Arrondi (mm)', 0.5, 0) : ''}</div>`;
    }
    box.innerHTML = h;
    fields();
  }
  const box = $('[data-props]');
  box.addEventListener('input', e => {
    const l = cur(), k = e.target.dataset.k;
    if (!l || !k || e.target.type === 'checkbox') return;
    l[k] = e.target.type === 'number' ? (e.target.value === '' ? 0 : +e.target.value) : e.target.value;
    if (k === 'text' || k === 'label') { list(); fields(); }
    changed(k === 'text' ? 200 : 80);
  });
  box.addEventListener('change', e => {
    const l = cur();
    if (!l) return;
    if (e.target.matches('[data-client]')) { l.mode = e.target.checked ? 'client' : 'fixed'; if (l.mode === 'client' && !l.field) { l.field = 'texte'; l.label = 'Votre texte'; l.fit = true; } props(); list(); changed(0); return; }
    const k = e.target.dataset.k;
    if (!k) return;
    if (e.target.type === 'checkbox') l[k] = e.target.checked;
    else if (e.target.tagName === 'SELECT') l[k] = e.target.value;
    if (k === 'list' && l.list) { l.fit = true; if (l.field === 'prenom') { l.field = 'phrase'; l.label = 'Votre phrase'; } const c = (D.lists[l.list] || {}).choices || []; if (c.length) l.text = c[0]; }
    if (k === 'style' || e.target.tagName === 'SELECT') props();
    list();
    changed(0);
  });
  box.addEventListener('click', e => {
    const l = cur();
    if (!l) return;
    const a = e.target.closest('[data-align]');
    if (a) { l.align = a.dataset.align; props(); changed(0); }
    if (e.target.closest('[data-center]')) {
      const b = last && last.boxes[l.id];
      if (l.type === 'text') { l.x = r1((F().w - l.w) / 2); l.align = 'center'; }
      else l.x = r1((F().w - l.w) / 2);
      if (b && l.type !== 'text') l.x = r1(l.x);
      props(); changed(0);
    }
  });

  /* ------------------------------------------------------------ champs du client (essai) */
  function fields() {
    const fs = {};
    Object.values(model.faces).forEach(f => (f.layers || []).forEach(l => { if (l.type === 'text' && l.mode === 'client' && l.field) fs[l.field] = fs[l.field] || l; }));
    const keys = Object.keys(fs);
    $('[data-fields-card]').hidden = !keys.length;
    $('[data-fields]').innerHTML = keys.map(k => {
      const lst = fs[k].list && (D.lists || {})[fs[k].list];
      if (lst) {
        const srv = last && last.fields && last.fields[k];
        const ok = srv ? srv.choices : lst.choices, out = srv ? srv.rejected : [];
        return `<label class="f"><span class="f__k">${esc(fs[k].label || k)} <span class="xs muted">(liste « ${esc(lst.name)} » : ${ok.length} phrase${ok.length > 1 ? 's' : ''} proposée${ok.length > 1 ? 's' : ''})</span></span><select class="in" data-field="${esc(k)}"><option value="">${esc(fs[k].text)} (exemple)</option>${ok.map(c => `<option${values[k] === c ? ' selected' : ''}>${esc(c)}</option>`).join('')}</select></label>`
          + (out.length ? `<details class="xs" style="margin:-4px 0 8px"><summary class="muted">${out.length} phrase${out.length > 1 ? 's' : ''} écartée${out.length > 1 ? 's' : ''} : trop longue${out.length > 1 ? 's' : ''} pour ce cadre</summary><ul style="margin:4px 0 0 16px">${out.map(c => `<li>${esc(c)}</li>`).join('')}</ul></details>` : '');
      }
      return `<label class="f"><span class="f__k">${esc(fs[k].label || k)} <span class="xs muted">(${fs[k].max || 30} caractères au plus)</span></span><input class="in" data-field="${esc(k)}" maxlength="${fs[k].max || 30}" value="${esc(values[k] || '')}" placeholder="${esc(fs[k].text)}"></label>`;
    }).join('');
    const pdf = $('[data-pdf]');
    const q = keys.filter(k => values[k]).map(k => 'v_' + encodeURIComponent(k) + '=' + encodeURIComponent(values[k])).join('&');
    pdf.href = pdf.href.split('?')[0] + (q ? '?' + q : '');
  }
  $('[data-fields]').addEventListener('input', e => {
    const k = e.target.dataset.field;
    if (!k) return;
    values[k] = e.target.value;
    fields();
    soon(200);
  });

  /* ------------------------------------------------------------ grille, aimant, alignement */
  const gridSel = $('[data-grid]'), snapChk = $('[data-snap]');
  try { gridSel.value = localStorage.getItem('shop.grid') || '0'; snapChk.checked = localStorage.getItem('shop.snap') !== '0'; } catch (e) { /* stockage indisponible */ }
  gridSel.addEventListener('change', () => { try { localStorage.setItem('shop.grid', gridSel.value); } catch (e) { /* */ } decorate(); });
  snapChk.addEventListener('change', () => { try { localStorage.setItem('shop.snap', snapChk.checked ? '1' : '0'); } catch (e) { /* */ } });
  // Boîte d'un calque (mm) : tracés réels si connus, sinon son cadre.
  // Boîte d'un calque : celle du dernier rendu, décalée si le calque a bougé depuis.
  const boxOf = l => {
    const b = last && last.boxes[l.id], p = last && last.pos && last.pos[l.id];
    if (!b) return [l.x, l.y, l.x + (+l.w || 0), l.y + (+l.h || 10)];
    const dx = p ? l.x - p[0] : 0, dy = p ? l.y - p[1] : 0;
    return [b[0] + dx, b[1] + dy, b[2] + dx, b[3] + dy];
  };
  $('[data-tools]').addEventListener('click', e => {
    const b = e.target.closest('[data-al]'), l = cur();
    if (!b || !l) return;
    const f = F(), bx = boxOf(l), bw = bx[2] - bx[0], bh = bx[3] - bx[1], ox = bx[0] - l.x, oy = bx[1] - l.y;
    const a = b.dataset.al;
    if (a === 'left') l.x = r1(-ox); if (a === 'hcenter') l.x = r1((f.w - bw) / 2 - ox); if (a === 'right') l.x = r1(f.w - bw - ox);
    if (a === 'top') l.y = r1(-oy); if (a === 'vcenter') l.y = r1((f.h - bh) / 2 - oy); if (a === 'bottom') l.y = r1(f.h - bh - oy);
    props(); changed(0);
  });
  /**
   * Aimant : la boîte déplacée (bords et centre) colle au repère le plus proche (bords et centre de
   * la face, bords et centres des autres calques), sinon à la grille. Renvoie le décalage et les repères.
   */
  function snap(box, id, tol) {
    const f = F(), g = +gridSel.value;
    const xs = [0, f.w / 2, f.w], ys = [0, f.h / 2, f.h];
    for (const o of L()) {
      if (o.id === id) continue;
      const b = boxOf(o);
      xs.push(b[0], (b[0] + b[2]) / 2, b[2]); ys.push(b[1], (b[1] + b[3]) / 2, b[3]);
    }
    const axis = (lo, hi, cands) => {
      let best = null;
      for (const v of [lo, (lo + hi) / 2, hi]) for (const c of cands) { const d = c - v; if (Math.abs(d) <= tol && (!best || Math.abs(d) < Math.abs(best.d))) best = { d, at: c }; }
      if (!best && g > 0) { const d = Math.round(lo / g) * g - lo; if (Math.abs(d) <= tol) best = { d, at: null }; }
      return best;
    };
    return { x: axis(box[0], box[2], xs), y: axis(box[1], box[3], ys) };
  }
  function guides(svg, s) {
    svg.querySelectorAll('[data-guide]').forEach(n => n.remove());
    const f = F(), sw = Math.max(f.w, f.h) / 350, ext = Math.max(f.w, f.h) * 0.05;
    if (s.x && s.x.at !== null) svg.appendChild(el('line', { x1: s.x.at, x2: s.x.at, y1: -ext, y2: f.h + ext, stroke: '#e0218a', 'stroke-width': sw, 'pointer-events': 'none', 'data-guide': '1' }));
    if (s.y && s.y.at !== null) svg.appendChild(el('line', { y1: s.y.at, y2: s.y.at, x1: -ext, x2: f.w + ext, stroke: '#e0218a', 'stroke-width': sw, 'pointer-events': 'none', 'data-guide': '1' }));
  }

  /* ------------------------------------------------------------ glisser dans le fichier d'impression */
  const print = $('[data-print]');
  let drag = null;
  const pt = (svg, e) => { const p = svg.createSVGPoint(); p.x = e.clientX; p.y = e.clientY; return p.matrixTransform(svg.getScreenCTM().inverse()); };
  print.addEventListener('pointerdown', e => {
    const svg = print.querySelector('svg');
    if (!svg) return;
    const p = pt(svg, e);
    const hit = e.target.closest('[data-layer]');
    let id = hit ? hit.getAttribute('data-layer') : null;
    if (!id && last) {
      // Sans toucher un tracé (trou d'une lettre) : le calque dont la boîte contient le point, le plus haut.
      const ls = L();
      for (let i = ls.length - 1; i >= 0; i--) { const b = last.boxes[ls[i].id]; if (b && p.x >= b[0] && p.x <= b[2] && p.y >= b[1] && p.y <= b[3]) { id = ls[i].id; break; } }
    }
    sel = id;
    list(); props(); decorate();
    const l = cur();
    if (!l) return;
    drag = { id, sx: p.x, sy: p.y, x: l.x, y: l.y, svg, moved: false, box: boxOf(l).slice() };
    print.setPointerCapture(e.pointerId);
    e.preventDefault();
  });
  print.addEventListener('pointermove', e => {
    if (!drag) return;
    const p = pt(drag.svg, e), dx = p.x - drag.sx, dy = p.y - drag.sy;
    if (!drag.moved && Math.hypot(dx, dy) < 0.5) return;
    drag.moved = true;
    const l = cur();
    let mx = dx, my = dy;
    if (snapChk.checked && !e.altKey) {
      // Tolérance : 8 pixels à l'écran, convertis en mm.
      const tol = 8 / (drag.svg.getScreenCTM().a || 1);
      const s = snap([drag.box[0] + dx, drag.box[1] + dy, drag.box[2] + dx, drag.box[3] + dy], drag.id, tol);
      if (s.x && isFinite(s.x.d)) mx += s.x.d; if (s.y && isFinite(s.y.d)) my += s.y.d;
      guides(drag.svg, s);
      // Silhouette de dépôt : où l'élément va se poser.
      let ghost = drag.svg.querySelector('[data-ghost]');
      if (!ghost) { ghost = el('rect', { fill: 'rgba(224,33,138,.06)', stroke: '#e0218a', 'stroke-dasharray': Math.max(F().w, F().h) / 150, 'stroke-width': Math.max(F().w, F().h) / 600, 'pointer-events': 'none', 'data-ghost': '1', 'data-guide': '1' }); }
      ghost.setAttribute('x', drag.box[0] + mx); ghost.setAttribute('y', drag.box[1] + my); ghost.setAttribute('width', drag.box[2] - drag.box[0]); ghost.setAttribute('height', drag.box[3] - drag.box[1]);
      drag.svg.appendChild(ghost);
    } else guides(drag.svg, {});
    if (!isFinite(mx) || !isFinite(my)) return;
    l.x = r1(drag.x + mx);
    l.y = r1(drag.y + my);
    $$(`[data-print] [data-layer="${CSS.escape(drag.id)}"]`).forEach(n => n.setAttribute('transform', `translate(${l.x - drag.x} ${l.y - drag.y})`));
    const sb = print.querySelector('[data-selbox]');
    if (sb) sb.setAttribute('transform', `translate(${l.x - drag.x} ${l.y - drag.y})`);
  });
  const end = () => {
    if (drag) drag.svg.querySelectorAll('[data-guide]').forEach(n => n.remove());
    const moved = drag && drag.moved;
    drag = null;
    if (moved) { props(); changed(0); } else if (pendingAfterDrag) { pendingAfterDrag = false; render(); }
    pendingAfterDrag = false;
  };
  print.addEventListener('pointerup', end);
  print.addEventListener('pointercancel', end);
  document.addEventListener('keydown', e => {
    const l = cur();
    if (!l || !/^Arrow/.test(e.key) || e.target.closest('input, textarea, select')) return;
    const s = e.shiftKey ? 10 : 1;
    if (e.key === 'ArrowLeft') l.x = r1(l.x - s); if (e.key === 'ArrowRight') l.x = r1(l.x + s);
    if (e.key === 'ArrowUp') l.y = r1(l.y - s); if (e.key === 'ArrowDown') l.y = r1(l.y + s);
    e.preventDefault();
    props(); changed(60);
  });

  /* ------------------------------------------------------------ enregistrer */
  $('[data-name]').addEventListener('input', () => { dirty = true; });
  $('[data-active]').addEventListener('change', () => { dirty = true; });
  $('[data-sale]').addEventListener('input', () => { dirty = true; });
  $('[data-save]').addEventListener('click', async () => {
    const b = $('[data-save]');
    b.disabled = true;
    const faces = {};
    for (const k of faceKeys) faces[k] = { bg: model.faces[k].bg || '', layers: model.faces[k].layers || [] };
    const eur = v => Math.round((parseFloat(String(v).replace(',', '.')) || 0) * 100);
    const extra = {};
    $$('[data-sale-extra]').forEach(i => { if (eur(i.value) > 0) extra[i.dataset.saleExtra] = eur(i.value); });
    const sale = {
      price: eur($('[data-sale-price]').value), extra, desc: $('[data-sale-desc]').value,
      colors: $$('[data-sale-color]:checked').map(i => i.value), text_colors: $$('[data-sale-tcolor]:checked').map(i => i.value),
      text_sizes: $('[data-sale-tsizes]').checked, positions: $('[data-sale-pos]').checked,
      ...(() => { const v = $('[data-sale-comm]').value.trim().replace(',', '.'); if (v === '') return { rate: null, fee: null }; return $('[data-sale-comm-unit]').value === 'eur' ? { rate: null, fee: eur(v) } : { rate: parseFloat(v), fee: null }; })(),
    };
    const r = await BO.post(location.pathname, { name: $('[data-name]').value, color: model.color, active: $('[data-active]').checked, faces, sale });
    b.disabled = false;
    if (!r.ok) { BO.toast(r.error || 'Modèle non enregistré.', true); return; }
    dirty = false;
    if (sale.price <= 0 && $('[data-active]').checked) BO.toast('Modèle enregistré, mais pas encore en vente : indiquez son prix dans la carte « Vente ».', true);
    else BO.toast($('[data-active]').checked ? 'Modèle enregistré : il est en vente dans la boutique.' : 'Modèle enregistré (brouillon).');
  });
  $('[data-pdf]').addEventListener('click', e => { if (dirty) { e.preventDefault(); BO.toast('Enregistrez d’abord le modèle : le PDF part de la version enregistrée.', true); } });
  window.addEventListener('beforeunload', e => { if (dirty && !BO.leaving) { e.preventDefault(); e.returnValue = ''; } });

  bar(); list(); props(); render();
})();
