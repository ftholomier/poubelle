/* Back-office Sochaux Rétro : interactions communes (sans dépendance). */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const CSRF = ($('meta[name=csrf]') || {}).content || '';
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const BO = window.BO = { $, $$, esc, CSRF };
  BO.thumb = (rel, w = 480) => /\.svg$/i.test(rel) ? '/media/full/' + encodeURI(rel) : (/\.pdf$/i.test(rel) ? '/assets/admin/pdf.svg' : '/media/' + w + '/' + encodeURI(rel) + '.webp');

  /* ---------------------------------------------------------- toasts, confirmations */
  BO.toast = (msg, error = false) => {
    const t = document.createElement('div');
    t.className = 'toast' + (error ? ' toast--error' : '');
    t.setAttribute('role', 'status');
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), error ? 6000 : 2600);
  };
  $$('[data-toast]').forEach(t => setTimeout(() => t.remove(), t.classList.contains('toast--error') ? 7000 : 3200));
  try { const m = sessionStorage.getItem('bo-toast'); if (m) { sessionStorage.removeItem('bo-toast'); setTimeout(() => BO.toast(m), 50); } } catch (e) { /* */ }

  BO.confirm = (title, text = '', okLabel = 'Confirmer', danger = false) => new Promise(resolve => {
    const m = document.createElement('div');
    m.className = 'modal';
    m.innerHTML = '<div class="modal__box" role="alertdialog" aria-modal="true"><h2 class="modal__t"></h2><p style="margin:0;font-size:16px;line-height:1.45"></p><div class="row row--end"><button type="button" class="btn" data-no>Annuler</button><button type="button" class="btn ' + (danger ? 'btn--danger' : 'btn--navy') + '" data-yes></button></div></div>';
    $('.modal__t', m).textContent = title;
    $('p', m).textContent = text;
    $('[data-yes]', m).textContent = okLabel;
    const done = v => { m.remove(); document.removeEventListener('keydown', key); resolve(v); };
    const key = e => { if (e.key === 'Escape') done(false); };
    m.addEventListener('click', e => { if (e.target === m) done(false); });
    $('[data-no]', m).addEventListener('click', () => done(false));
    $('[data-yes]', m).addEventListener('click', () => done(true));
    document.addEventListener('keydown', key);
    document.body.appendChild(m);
    $('[data-yes]', m).focus();
  });
  // <form data-confirm="Titre|Texte|Bouton|danger">
  document.addEventListener('submit', async e => {
    const f = e.target;
    if (!f.matches('form[data-confirm]') || f.dataset.confirmed) return;
    e.preventDefault();
    const [t, x, b, d] = f.dataset.confirm.split('|');
    if (await BO.confirm(e.submitter?.dataset.confirmTitle || t, x || '', b || 'Confirmer', d === 'danger')) {
      f.dataset.confirmed = '1';
      // Bouton avec sa propre adresse (formaction) : f.submit() ne la reprend pas d'elle-même.
      if (e.submitter && e.submitter.hasAttribute('formaction')) f.action = e.submitter.formAction;
      e.submitter && e.submitter.name ? (() => { const h = document.createElement('input'); h.type = 'hidden'; h.name = e.submitter.name; h.value = e.submitter.value; f.appendChild(h); })() : null;
      f.submit();
    }
  }, true);
  // <form data-busy="Contrôle en cours…"> : traitement de quelques secondes, bouton grisé pendant l'envoi.
  document.addEventListener('submit', e => {
    const f = e.target;
    if (e.defaultPrevented || !f.matches('form[data-busy]')) return;
    const b = e.submitter || $('button[type="submit"]', f);
    if (b) setTimeout(() => { b.disabled = true; b.textContent = f.dataset.busy; }, 0);
  });

  // background : appel automatique (verrou, rafraîchissement), qui ne prolonge pas la session.
  BO.post = async (url, data, isJson = true, background = false) => {
    const opt = { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF': CSRF, Accept: 'application/json' } };
    if (background) opt.headers['X-BO-Background'] = '1';
    if (isJson) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(data || {}); } else { opt.body = data; }
    const r = await fetch(url, opt);
    let j = {};
    try { j = await r.json(); } catch (e) { j = { error: 'Réponse inattendue du serveur (' + r.status + ').' }; }
    if (!r.ok && !j.error) j.error = 'Erreur ' + r.status;
    j._status = r.status;
    // Session fermée (inactivité, mot de passe changé…) : session.js ramène à la page de connexion.
    if (r.status === 401) document.dispatchEvent(new CustomEvent('bo:expired', { detail: j }));
    return j;
  };

  /** Change la valeur d'un champ (et de son éditeur visuel le cas échéant). */
  BO.setValue = (el, v) => {
    if (!el) return;
    el.value = v ?? '';
    el.dispatchEvent(new Event('bo:set'));
    el.dispatchEvent(new Event('input', { bubbles: true }));
  };

  /* ---------------------------------------------------------- favoris (bande du haut)
     « + Ajouter un favori » ouvre une fenêtre : mes favoris (renommer, monter, descendre, retirer),
     la page affichée, puis tout ce qu'on peut ajouter (écrans du menu, créations, réglages). */
  (() => {
    const bar = $('[data-favs]');
    let data = null;
    try { data = JSON.parse($('#bo-favs')?.textContent || 'null'); } catch (e) { /* */ }
    if (!bar || !data) return;
    let favs = data.favs || [];
    const isFav = url => favs.some(f => f.url === url);
    const drawBar = () => {
      $('[data-favs-list]', bar).innerHTML = favs.map(f => {
        const on = data.here && f.url === data.here.url;
        return '<a href="' + esc(f.url) + '" class="favs__a' + (on ? ' is-on" aria-current="page' : '') + '">' + esc(f.label) + '</a>';
      }).join('');
      const hint = $('[data-favs-hint]', bar);
      if (hint) hint.hidden = favs.length > 0;
    };
    const save = async payload => {
      const r = await BO.post('/admin/api/favoris', payload);
      if (!r.ok) { BO.toast(r.error || 'Favori non enregistré.', true); return false; }
      favs = r.favs;
      drawBar();
      return true;
    };
    const open = () => {
      const opener = document.activeElement;
      const m = document.createElement('div');
      m.className = 'modal';
      m.innerHTML = '<div class="modal__box" style="width:min(660px,100%)" role="dialog" aria-modal="true" aria-labelledby="fav-t">'
        + '<div class="row" style="justify-content:space-between;align-items:center;gap:10px"><h2 class="modal__t" id="fav-t">Mes favoris</h2><button type="button" class="btn btn--sm" data-close aria-label="Fermer">✕</button></div>'
        + '<p class="small muted" style="margin:0">Des liens directs dans la bande du haut, pour ne plus chercher dans le menu de gauche (' + data.max + ' au plus). Rien que pour vous : chacun a les siens.</p>'
        + '<div class="fav-pick" data-mine></div>'
        + '<input type="search" data-filter placeholder="Chercher un écran à ajouter…" aria-label="Chercher un écran à ajouter" style="font:inherit;padding:8px 10px;border:2px solid var(--navy)">'
        + '<div class="fav-pick" data-all></div></div>';
      const box = $('.modal__box', m);
      const mine = $('[data-mine]', m), all = $('[data-all]', m), filter = $('[data-filter]', m);
      const drawMine = () => {
        mine.innerHTML = '<h3>Dans la bande du haut (' + favs.length + ' / ' + data.max + ')</h3>' + (favs.length ? favs.map((f, i) =>
          '<div class="fav-row is-fav" data-url="' + esc(f.url) + '"><input type="text" value="' + esc(f.label) + '" maxlength="40" aria-label="Nom du favori" data-rename>'
          + '<button type="button" class="btn btn--sm" data-move="-1" aria-label="Monter"' + (i ? '' : ' disabled') + '>↑</button>'
          + '<button type="button" class="btn btn--sm" data-move="1" aria-label="Descendre"' + (i < favs.length - 1 ? '' : ' disabled') + '>↓</button>'
          + '<button type="button" class="btn btn--sm" data-del aria-label="Retirer des favoris">✕</button></div>').join('')
          : '<p class="small muted" style="margin:0">Aucun favori pour l’instant : choisissez ci-dessous.</p>');
      };
      const line = (it, note) => '<div class="fav-row' + (isFav(it.url) ? ' is-fav' : '') + '" data-url="' + esc(it.url) + '" data-label="' + esc(it.label) + '" data-text="' + esc((it.label + ' ' + note).toLowerCase()) + '">'
        + '<span class="fav-row__l"><b>' + esc(it.label) + '</b>' + (note ? '<small>' + esc(note) + '</small>' : '') + '</span>'
        + '<button type="button" class="btn btn--sm' + (isFav(it.url) ? ' btn--navy' : '') + '" data-toggle aria-pressed="' + isFav(it.url) + '">' + (isFav(it.url) ? '★ Ajouté' : '☆ Ajouter') + '</button></div>';
      const drawAll = () => {
        let h = '';
        if (data.here) h += '<section><h3>Cette page</h3>' + line(data.here, data.here.url) + '</section>';
        for (const [group, items] of Object.entries(data.choices || {})) {
          h += '<section><h3>' + esc(group) + '</h3>' + items.map(it => line(it, '')).join('') + '</section>';
        }
        all.innerHTML = h;
        applyFilter();
      };
      const applyFilter = () => {
        const q = filter.value.trim().toLowerCase();
        $$('section', all).forEach(sec => {
          let n = 0;
          $$('.fav-row', sec).forEach(r => { const ok = !q || r.dataset.text.includes(q); r.hidden = !ok; n += ok; });
          sec.hidden = !n;
        });
      };
      const redraw = () => { drawMine(); drawAll(); };
      const close = () => { m.remove(); document.removeEventListener('keydown', key); opener?.focus?.(); };
      const key = e => { if (e.key === 'Escape') close(); };
      m.addEventListener('click', async e => {
        if (e.target === m || e.target.closest('[data-close]')) { close(); return; }
        const row = e.target.closest('.fav-row');
        const url = row?.dataset.url;
        if (!url) return;
        if (e.target.closest('[data-toggle]')) {
          if (await save(isFav(url) ? { action: 'retirer', url } : { action: 'ajouter', url, label: row.dataset.label })) redraw();
        } else if (e.target.closest('[data-del]')) {
          if (await save({ action: 'retirer', url })) redraw();
        } else if (e.target.closest('[data-move]')) {
          const urls = favs.map(f => f.url), i = urls.indexOf(url), j = i + +e.target.closest('[data-move]').dataset.move;
          if (i < 0 || j < 0 || j >= urls.length) return;
          [urls[i], urls[j]] = [urls[j], urls[i]];
          if (await save({ action: 'ordre', urls })) { drawMine(); $('[data-url="' + CSS.escape(url) + '"] [data-move="' + (j > i ? 1 : -1) + '"]', mine)?.focus(); }
        }
      });
      m.addEventListener('change', async e => {
        const inp = e.target.closest('[data-rename]');
        if (inp && await save({ action: 'renommer', url: inp.closest('.fav-row').dataset.url, label: inp.value })) drawAll();
      });
      m.addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.matches('[data-rename]')) { e.preventDefault(); e.target.blur(); } });
      filter.addEventListener('input', applyFilter);
      document.addEventListener('keydown', key);
      redraw();
      document.body.appendChild(m);
      filter.focus();
      box.scrollTop = 0;
    };
    $('[data-fav-open]', bar).addEventListener('click', open);
  })();

  /* ---------------------------------------------------------- traduction ponctuelle (Gemini)
     <button data-tr="champ"> traduit le champ « champ » vers « champ_en » dans le même bloc ;
     <button data-tr-all> traduit tous les champs « *_en » vides du bloc. */
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-tr], [data-tr-all]');
    if (!b) return;
    e.preventDefault();
    const scope = b.closest('[data-item]') || b.closest('[data-tr-scope]') || b.closest('form') || document;
    const find = n => scope.querySelector('[data-field="' + n + '"], [name="' + n + '"]');
    let pairs = [];
    // « champ » → « champ_en » ; « a.0>a_en.0 » : paire explicite
    if (b.dataset.tr) {
      pairs = b.dataset.tr.split(',').map(n => n.includes('>') ? n.split('>').map(x => find(x.trim())) : [find(n.trim()), find(n.trim() + '_en')]);
    } else {
      $$('[data-field], [name]', scope).forEach(dst => {
        const name = dst.dataset.field || dst.name || '';
        if (!/_en(\.\d+)?$/.test(name) || dst.value.trim() || (dst.type === 'hidden' && dst.dataset.wysiwyg === undefined)) return;
        const n = name.replace(/_en(\.\d+)?$/, '$1');
        const own = dst.closest('[data-item]') || scope;
        pairs.push([own.querySelector('[data-field="' + n + '"], [name="' + n + '"]'), dst]);
      });
    }
    pairs = pairs.filter(([src, dst]) => src && dst && src.value.trim());
    if (!pairs.length) { BO.toast('Rien à traduire : le texte français est vide ou la version anglaise est déjà remplie.', true); return; }
    b.disabled = true;
    const label = b.textContent;
    b.textContent = 'Traduction…';
    const r = await BO.post('/admin/api/traduire', { texts: pairs.map(([src]) => src.value) });
    b.disabled = false;
    b.textContent = label;
    if (!r.ok) { BO.toast(r.error || 'Traduction impossible', true); return; }
    pairs.forEach(([src, dst]) => { const t = r.translations[src.value]; if (t) BO.setValue(dst, t); });
    BO.toast('Traduit avec Gemini' + (r.cost && r.cost.label ? ' (coût : ' + r.cost.label + ')' : '') + ' : relisez avant d’enregistrer.');
  });

  /* ---------------------------------------------------------- menus déroulants, menu mobile */
  document.addEventListener('click', e => {
    const tg = e.target.closest('[data-dropdown-toggle]');
    $$('[data-dropdown] .menu').forEach(m => {
      if (!tg || !tg.parentElement.contains(m)) { m.hidden = true; m.parentElement.querySelector('[data-dropdown-toggle]')?.setAttribute('aria-expanded', 'false'); }
    });
    if (tg) {
      const m = $('.menu', tg.parentElement);
      m.hidden = !m.hidden;
      tg.setAttribute('aria-expanded', String(!m.hidden));
    }
  });
  // Menu compact à deux niveaux : interrupteur en haut du menu, choix gardé un an (cookie lu par le serveur).
  const navMode = $('[data-nav-mode]');
  navMode && navMode.addEventListener('change', () => {
    $('[data-bo]').classList.toggle('nav-compact', navMode.checked);
    document.cookie = 'bo_nav=' + (navMode.checked ? 'compact' : 'normal') + ';path=/admin;max-age=31536000;samesite=lax';
  });
  const navBtn = $('[data-nav-toggle]');
  navBtn?.addEventListener('click', () => {
    const bo = $('[data-bo]');
    bo.classList.toggle('nav-open');
    navBtn.setAttribute('aria-expanded', String(bo.classList.contains('nav-open')));
  });

  /* ---------------------------------------------------------- recherche globale (Ctrl+K) */
  const qk = $('[data-qk]'), qkIn = $('[data-qk-input]'), qkRes = $('[data-qk-res]');
  let qkTimer, qkSel = 0, qkSeq = 0;
  const qkOpen = () => { if (!qk) return; qk.hidden = false; qkIn.value = ''; qkRes.innerHTML = ''; setTimeout(() => qkIn.focus(), 10); qkSearch(); };
  const qkClose = () => { if (qk) qk.hidden = true; };
  async function qkSearch() {
    const q = qkIn.value.trim(), seq = ++qkSeq;
    const r = await fetch('/admin/api/recherche?q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(x => x.json()).catch(() => ({ items: [] }));
    if (seq !== qkSeq) return; // une recherche plus récente est partie entre-temps
    qkSel = 0;
    qkRes.innerHTML = (r.items || []).map((it, i) => '<a href="' + esc(it.url) + '" class="' + (i === 0 ? 'is-on' : '') + '"><span class="pill">' + esc(it.kind) + '</span><span>' + esc(it.title) + (it.meta ? ' <small>' + esc(it.meta) + '</small>' : '') + '</span><small>' + esc(it.status || '') + '</small></a>').join('') || '<p class="muted" style="padding:14px 18px;margin:0">' + (q ? 'Aucun résultat.' : 'Tapez quelques lettres…') + '</p>';
  }
  $$('[data-qk-open]').forEach(b => b.addEventListener('click', qkOpen));
  qkIn?.addEventListener('input', () => { clearTimeout(qkTimer); qkTimer = setTimeout(qkSearch, 160); });
  qkIn?.addEventListener('keydown', e => {
    const items = $$('a', qkRes);
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      qkSel = Math.max(0, Math.min(items.length - 1, qkSel + (e.key === 'ArrowDown' ? 1 : -1)));
      items.forEach((a, i) => a.classList.toggle('is-on', i === qkSel));
      items[qkSel]?.scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'Enter' && items[qkSel]) {
      e.preventDefault();
      location.href = items[qkSel].href;
    }
  });
  qk?.addEventListener('click', e => { if (e.target === qk) qkClose(); });
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); qkOpen(); }
    if (e.key === 'Escape') qkClose();
  });

  /* ---------------------------------------------------------- listes : cases à cocher + actions groupées */
  $$('[data-bulk-form]').forEach(form => {
    const all = $('[data-check-all]', form), bar = $('[data-bulk-bar]', form), count = $('[data-bulk-count]', form);
    const boxes = () => $$('input[name="ids[]"]', form);
    const sync = () => {
      const n = boxes().filter(b => b.checked).length;
      if (bar) bar.hidden = n === 0;
      if (count) count.textContent = n + ' sélectionnée' + (n > 1 ? 's' : '');
      boxes().forEach(b => b.closest('tr')?.classList.toggle('is-sel', b.checked));
      if (all) all.checked = n > 0 && n === boxes().length;
    };
    all?.addEventListener('change', () => { boxes().forEach(b => (b.checked = all.checked)); sync(); });
    form.addEventListener('change', e => { if (e.target.matches('input[name="ids[]"]')) sync(); });
    sync();
  });
  // Ligne entière cliquable (data-href), sauf sur les contrôles
  document.addEventListener('click', e => {
    const tr = e.target.closest('tr[data-href]');
    if (!tr || e.target.closest('a,button,input,label,select,[data-handle]') || document.body.classList.contains('is-sorting')) return;
    if (e.ctrlKey || e.metaKey) window.open(tr.dataset.href); else location.href = tr.dataset.href;
  });

  /* ---------------------------------------------------------- répéteurs (ajout, suppression, tri) */
  // Renumérote une liste et grise ↑ sur le premier élément, ↓ sur le dernier.
  BO.renumber = rep => {
    const its = $$(':scope > [data-item], :scope > [data-sort-item]', rep);
    its.forEach((it, i) => {
      const n = it.querySelector(':scope > [data-pos] [data-item-n]');
      if (n) n.textContent = String(i + 1).padStart(+(rep.dataset.pad || 2), '0');
      const up = it.querySelector(':scope > .rep__tools > .mover > [data-rep-up], :scope > td .mover > [data-rep-up]');
      const dn = it.querySelector(':scope > .rep__tools > .mover > [data-rep-down], :scope > td .mover > [data-rep-down]');
      if (up) up.disabled = i === 0;
      if (dn) dn.disabled = i === its.length - 1;
    });
  };
  function addItem(rep, at) {
    const tpl = rep.querySelector(':scope > template');
    if (!tpl) return null;
    const node = tpl.content.firstElementChild.cloneNode(true);
    const anchor = at || $(':scope > [data-rep-add]', rep);
    rep.insertBefore(node, anchor && anchor.parentElement === rep ? anchor : null);
    BO.init(node);
    BO.renumber(rep);
    rep.dispatchEvent(new Event('input', { bubbles: true }));
    return node;
  }
  BO.addItem = addItem;
  document.addEventListener('click', async e => {
    const add = e.target.closest('[data-rep-add]');
    if (add) {
      const rep = add.closest('[data-repeater]');
      const node = addItem(rep);
      node?.querySelector('input,textarea,select,[contenteditable]')?.focus();
      return;
    }
    const item = e.target.closest('[data-item]');
    if (!item) return;
    const rep = item.parentElement;
    if (e.target.closest('[data-rep-del]')) {
      if (item.querySelector('input,textarea')?.value && !(await BO.confirm('Supprimer cet élément ?', '', 'Supprimer', true))) return;
      item.remove();
      BO.renumber(rep);
      rep.dispatchEvent(new Event('input', { bubbles: true }));
    } else if (e.target.closest('[data-rep-dup]')) {
      const copy = item.cloneNode(true);
      rep.insertBefore(copy, item.nextSibling);
      BO.init(copy);
      BO.renumber(rep);
      rep.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });
  /* ---------------------------------------------------------- tri par glisser-déposer (souris, doigt, clavier)
     Listes triables : [data-repeater] (éléments [data-item]) et [data-sortable] (éléments [data-sort-item]).
     Chaque élément a ↑ (monter d'un cran), l'icône quatre flèches [data-handle] (glisser-déposer plus loin :
     un trait jaune montre où l'élément sera lâché, la liste défile toute seule près des bords) et ↓.
     Clic sur le numéro [data-pos] : saisir directement la position. Au clavier, sur l'icône : flèches,
     Début/Fin, Page préc./suiv., Entrée pour saisir la position. */
  const sortItems = list => [...list.children].filter(c => c.matches('[data-item], [data-sort-item]'));
  const sortLive = document.createElement('div');
  sortLive.className = 'sr-only'; sortLive.setAttribute('aria-live', 'polite');
  document.body.appendChild(sortLive);
  const dropLine = document.createElement('div');
  dropLine.className = 'dropline'; dropLine.hidden = true; dropLine.innerHTML = '<span></span>';
  document.body.appendChild(dropLine);
  const sortMove = (list, item, before) => {
    const its = sortItems(list), from = its.indexOf(item);
    if (before === item) return;
    if (before) list.insertBefore(item, before);
    else { const last = its.filter(i => i !== item).pop(); if (last) last.after(item); }
    const all = sortItems(list), to = all.indexOf(item);
    if (to === from) return;
    BO.renumber(list);
    list.dispatchEvent(new CustomEvent('sorted', { bubbles: true, detail: { item, from, to } }));
    list.dispatchEvent(new Event('input', { bubbles: true }));
    sortLive.textContent = 'Déplacé en position ' + (to + 1) + ' sur ' + all.length;
    item.classList.remove('is-moved'); void item.offsetWidth; item.classList.add('is-moved');
  };
  BO.sortMove = sortMove;
  const sortTo = (list, item, pos) => {
    const others = sortItems(list).filter(i => i !== item);
    pos = Math.max(1, Math.min(others.length + 1, pos));
    sortMove(list, item, others[pos - 1] || null);
  };
  const scrollBox = el => {
    for (let p = el; p && p !== document.body; p = p.parentElement) {
      const st = getComputedStyle(p);
      if (/(auto|scroll)/.test(st.overflowY) && p.scrollHeight > p.clientHeight + 2) return p;
    }
    return null;
  };
  let srt = null;
  const sortTarget = (x, y) => {
    const its = sortItems(srt.list).filter(i => i !== srt.item);
    if (!its.length) return null;
    const rects = its.map(i => i.getBoundingClientRect());
    if (!srt.grid) {
      let k = rects.findIndex(r => y < r.top + r.height / 2);
      if (k < 0) k = its.length;
      return { before: its[k] || null, idx: k, rects };
    }
    let best = 0, bd = Infinity;
    rects.forEach((r, i) => {
      const dx = Math.max(r.left - x, 0, x - r.right), dy = Math.max(r.top - y, 0, y - r.bottom), d = dx * dx + dy * dy * 4;
      if (d < bd) { bd = d; best = i; }
    });
    const after = x > rects[best].left + rects[best].width / 2;
    return { before: its[best + (after ? 1 : 0)] || null, idx: best + (after ? 1 : 0), rects, ref: best, after };
  };
  const drawLine = t => {
    if (!t) { dropLine.hidden = true; return; }
    const { rects, idx } = t, lr = srt.list.getBoundingClientRect();
    dropLine.hidden = false;
    dropLine.querySelector('span').textContent = srt.slots ? 'N° ' + String(srt.slots[idx]?.n ?? '').padStart(3, '0') : 'Position ' + (idx + 1);
    if (!srt.grid) {
      const prev = rects[idx - 1], next = rects[idx];
      const y = prev && next ? (prev.bottom + next.top) / 2 : next ? next.top - 4 : prev.bottom + 4;
      const r = next || prev;
      dropLine.className = 'dropline';
      Object.assign(dropLine.style, { left: Math.max(lr.left, r.left) + 'px', top: (y - 2) + 'px', width: Math.min(lr.width, r.width) + 'px', height: '' });
    } else {
      const r = rects[t.ref], x = t.after ? r.right + 4 : r.left - 4;
      dropLine.className = 'dropline is-v';
      Object.assign(dropLine.style, { left: (x - 2) + 'px', top: r.top + 'px', height: r.height + 'px', width: '' });
    }
  };
  const sortFrame = () => {
    if (!srt?.active) return;
    const { x, y } = srt, edge = 60, speed = d => Math.min(28, Math.ceil(d / 2.5));
    const box = srt.box;
    if (box) {
      const r = box.getBoundingClientRect();
      if (y < r.top + edge && box.scrollTop > 0) box.scrollTop -= speed(r.top + edge - y);
      else if (y > r.bottom - edge && box.scrollTop + box.clientHeight < box.scrollHeight) box.scrollTop += speed(y - (r.bottom - edge));
    }
    if (y < edge) window.scrollBy(0, -speed(edge - y));
    else if (y > innerHeight - edge) window.scrollBy(0, speed(y - (innerHeight - edge)));
    srt.target = sortTarget(x, y);
    drawLine(srt.target);
    srt.raf = requestAnimationFrame(sortFrame);
  };
  const sortStart = () => {
    const { item, list } = srt, r = item.getBoundingClientRect();
    const its = sortItems(list);
    srt.active = true;
    srt.grid = its.length > 1 && Math.abs(its[0].getBoundingClientRect().top - its[1].getBoundingClientRect().top) < 4;
    srt.box = scrollBox(list);
    try { srt.slots = list.dataset.moments ? JSON.parse(list.dataset.moments) : null; } catch (e) { srt.slots = null; }
    srt.dx = srt.x0 - r.left; srt.dy = srt.y0 - r.top;
    let g = item.cloneNode(true);
    g.querySelectorAll('[id],[name],[data-field]').forEach(el => { el.removeAttribute('id'); el.removeAttribute('name'); el.removeAttribute('data-field'); });
    g.removeAttribute('data-item'); g.removeAttribute('data-sort-item');
    let host = list;
    if (item.tagName === 'TR') {
      // Ligne de tableau : on l'emballe dans un petit tableau aux mêmes largeurs de colonnes.
      [...item.children].forEach((td, i) => { g.children[i].style.width = td.getBoundingClientRect().width + 'px'; });
      const t = document.createElement('table'), tb = document.createElement('tbody');
      t.className = 'sort-ghost-table'; tb.appendChild(g); t.appendChild(tb); g = t;
      host = document.body;
    }
    g.classList.add('sort-ghost');
    g.setAttribute('aria-hidden', 'true');
    g.inert = true;
    // Le fantôme reste dans la liste (en position fixe) pour garder exactement la même mise en forme.
    Object.assign(g.style, { width: r.width + 'px', left: r.left + 'px', top: r.top + 'px' });
    host.appendChild(g);
    srt.ghost = g;
    item.classList.add('is-sorting-src');
    document.body.classList.add('is-sorting');
    srt.raf = requestAnimationFrame(sortFrame);
  };
  const sortEnd = drop => {
    if (!srt) return;
    const s = srt; srt = null;
    cancelAnimationFrame(s.raf);
    s.ghost?.remove();
    s.item.classList.remove('is-sorting-src');
    document.body.classList.remove('is-sorting');
    dropLine.hidden = true;
    if (drop && s.active && s.target) sortMove(s.list, s.item, s.target.before);
  };
  // Saisie directe de la position (clic sur le numéro de l'élément)
  const posEdit = (item, list) => {
    const btn = item.querySelector(':scope > [data-pos], :scope > td [data-pos]');
    if (!btn || btn.hidden) return;
    const total = sortItems(list).length, cur = sortItems(list).indexOf(item) + 1;
    const inp = document.createElement('input');
    inp.type = 'number'; inp.min = '1'; inp.max = String(total); inp.value = String(cur);
    inp.className = 'posedit'; inp.setAttribute('aria-label', 'Nouvelle position (1 à ' + total + ')');
    btn.hidden = true; btn.after(inp); inp.select(); inp.focus();
    let done = false;
    const finish = ok => {
      if (done) return; done = true;
      const v = parseInt(inp.value, 10);
      inp.remove(); btn.hidden = false;
      if (ok && v && v !== cur) { sortTo(list, item, v); item.scrollIntoView({ block: 'nearest' }); }
      btn.focus();
    };
    inp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); finish(true); } if (e.key === 'Escape') { e.preventDefault(); finish(false); } e.stopPropagation(); });
    inp.addEventListener('blur', () => finish(true));
    inp.addEventListener('pointerdown', e => e.stopPropagation());
  };
  document.addEventListener('pointerdown', e => {
    if (e.button !== 0 || srt || e.target.closest('input, select, textarea, button, a')) return;
    if (e.target.closest('[data-handle]')) e.target.closest('[data-handle]').focus({ preventScroll: true });
    const handle = e.target.closest('[data-handle]');
    const item = handle?.closest('[data-item], [data-sort-item]');
    const list = item?.parentElement;
    if (!item || !list || !list.matches('[data-repeater], [data-sortable]')) return;
    e.preventDefault();
    srt = { item, list, x0: e.clientX, y0: e.clientY, x: e.clientX, y: e.clientY, active: false, id: e.pointerId };
  });
  window.addEventListener('pointermove', e => {
    if (!srt || e.pointerId !== srt.id) return;
    srt.x = e.clientX; srt.y = e.clientY;
    if (!srt.active && Math.hypot(e.clientX - srt.x0, e.clientY - srt.y0) > 5) sortStart();
    if (srt.active) { e.preventDefault(); srt.ghost.style.left = (e.clientX - srt.dx) + 'px'; srt.ghost.style.top = (e.clientY - srt.dy) + 'px'; }
  }, { passive: false });
  window.addEventListener('pointerup', e => { if (srt && e.pointerId === srt.id) sortEnd(true); });
  window.addEventListener('pointercancel', () => sortEnd(false));
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && srt) sortEnd(false); }, true);
  document.addEventListener('dragstart', e => { if (e.target.closest?.('[data-handle]')) e.preventDefault(); });
  // ↑ / ↓ : un cran ; numéro : saisie de la position
  document.addEventListener('click', e => {
    const pos = e.target.closest('[data-pos]');
    if (pos) { const it = pos.closest('[data-item], [data-sort-item]'); if (it) posEdit(it, it.parentElement); return; }
    const b = e.target.closest('[data-rep-up], [data-rep-down]');
    const item = b?.closest('[data-item], [data-sort-item]');
    const list = item?.parentElement;
    if (!item || !list?.matches('[data-repeater], [data-sortable]')) return;
    const its = sortItems(list), i = its.indexOf(item);
    if (b.matches('[data-rep-up]') && i > 0) sortMove(list, item, its[i - 1]);
    if (b.matches('[data-rep-down]') && i < its.length - 1) sortMove(list, item, its[i + 2] || null);
    (b.disabled ? item.querySelector('[data-handle]') : b)?.focus();
  });
  // Clavier sur la poignée
  document.addEventListener('keydown', e => {
    const handle = e.target.closest?.('[data-handle]');
    const item = handle?.closest('[data-item], [data-sort-item]');
    const list = item?.parentElement;
    if (!item || !list?.matches('[data-repeater], [data-sortable]') || e.target !== handle) return;
    const its = sortItems(list), i = its.indexOf(item);
    const go = { ArrowUp: i, ArrowLeft: i, ArrowDown: i + 2, ArrowRight: i + 2, Home: 1, End: its.length, PageUp: i - 9, PageDown: i + 11 }[e.key];
    if (go !== undefined) { e.preventDefault(); sortTo(list, item, go); handle.focus(); item.scrollIntoView({ block: 'nearest' }); return; }
    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); posEdit(item, list); }
  });
  // Liste d'ordre d'une rubrique : retrouver une fiche, remettre en ordre chronologique ou alphabétique
  const norm = t => (t || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
  $$('[data-order-find]').forEach(inp => {
    const list = inp.closest('.card__body')?.querySelector('[data-repeater]');
    const out = inp.parentElement.querySelector('[data-order-found]');
    let hits = [], k = 0;
    const show = () => {
      if (!hits.length) { out.textContent = inp.value.trim() ? 'aucune fiche' : ''; return; }
      const it = hits[k % hits.length];
      out.textContent = (k % hits.length + 1) + ' / ' + hits.length;
      it.scrollIntoView({ block: 'center' });
      it.classList.remove('is-moved'); void it.offsetWidth; it.classList.add('is-moved');
    };
    inp.addEventListener('input', () => {
      const q = norm(inp.value.trim());
      hits = q.length < 2 ? [] : sortItems(list).filter(it => norm(it.textContent).includes(q));
      k = 0; show();
    });
    inp.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); k++; show(); } });
  });
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-order-sort]');
    if (!b) return;
    const list = b.closest('.card__body')?.querySelector('[data-repeater]');
    if (!list) return;
    const how = b.dataset.orderSort, its = sortItems(list);
    if (!(await BO.confirm('Remettre les ' + its.length + ' fiches en ordre ' + (how === 'az' ? 'alphabétique' : how === 'asc' ? 'chronologique (du plus ancien au plus récent)' : 'chronologique (du plus récent au plus ancien)') + ' ?', 'Vous pourrez ensuite ajuster à la main. Rien n’est enregistré avant « Enregistrer ».', 'Réordonner'))) return;
    const key = it => how === 'az' ? norm(it.dataset.title) : (it.dataset.date || '');
    its.sort((a, c) => { const x = key(a), y = key(c); return (x < y ? -1 : x > y ? 1 : 0) * (how === 'desc' ? -1 : 1); });
    // réinsertion dans le nouvel ordre, avant les éléments non triables (modèle, bouton d'ajout)
    const tail = [...list.children].find(c => !c.matches('[data-item], [data-sort-item]'));
    its.forEach(it => list.insertBefore(it, tail || null));
    BO.renumber(list);
    list.dispatchEvent(new Event('input', { bubbles: true }));
    sortLive.textContent = 'Liste réordonnée';
  });
  // Préparer la publication (moment du centenaire) : statut « Planifié » et date proposée.
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-plan-at]');
    const f = b && b.closest('form');
    const r = f && $('input[name="status"][value="planifie"]', f);
    const d = f && $('input[name="publish_at"]', f);
    if (!r || !d) return;
    e.preventDefault();
    r.checked = true;
    r.dispatchEvent(new Event('change', { bubbles: true }));
    if (b.dataset.planAt) { d.value = b.dataset.planAt; d.dispatchEvent(new Event('input', { bubbles: true })); }
    d.scrollIntoView({ block: 'center', behavior: 'smooth' });
    d.focus({ preventScroll: true });
  });
  // Poignées : atteignables au clavier et annoncées
  BO.initHandles = root => {
    $$('[data-repeater], [data-sortable]', root).forEach(l => { if (!l.closest('template')) BO.renumber(l); });
    if (root !== document && root.matches?.('[data-item]')) BO.renumber(root.parentElement);
    $$('[data-handle]', root).forEach(initHandle);
  };
  const initHandle = h => {
    if (h.closest('template') || h.dataset.handleReady) return;
    h.dataset.handleReady = '1';
    h.tabIndex = 0;
    h.setAttribute('role', 'button');
    h.setAttribute('aria-label', 'Déplacer plus loin : glisser-déposer, ou flèches du clavier' + (h.closest('[data-item], [data-sort-item]')?.querySelector('[data-pos]') ? ', Entrée pour saisir la position' : ''));
  };

  /* ---------------------------------------------------------- auto-complétion (personnes, clubs, stades, fiches) */
  function initAc(root) {
    $$('[data-ac]', root).forEach(inp => {
      if (inp.dataset.acReady) return;
      inp.dataset.acReady = '1';
      const wrap = document.createElement('div');
      wrap.className = 'ac';
      inp.parentNode.insertBefore(wrap, inp);
      wrap.appendChild(inp);
      const list = document.createElement('div');
      list.className = 'ac__list';
      list.hidden = true;
      wrap.appendChild(list);
      let timer, items = [], sel = -1, picking = false;
      const idField = inp.dataset.acId ? (inp.closest('[data-item]') || inp.closest('form') || document).querySelector('[data-field="' + inp.dataset.acId + '"],[name="' + inp.dataset.acId + '"]') : null;
      const render = () => {
        list.innerHTML = items.map((it, i) => '<button type="button" class="' + (i === sel ? 'is-on' : '') + '" data-i="' + i + '">' + esc(it.label) + (it.meta ? '<small>' + esc(it.meta) + '</small>' : '') + '</button>').join('');
        list.hidden = !items.length;
      };
      const pick = it => {
        inp.value = inp.dataset.acValue ? (it[inp.dataset.acValue] ?? it.value ?? it.label) : (it.value ?? it.label);
        if (idField) { idField.value = it.id ?? ''; idField.dispatchEvent(new Event('change', { bubbles: true })); }
        list.hidden = true;
        // Prévient les autres écouteurs, sans relancer la recherche (la liste resterait ouverte)
        picking = true;
        inp.dispatchEvent(new Event('input', { bubbles: true }));
        picking = false;
        inp.dispatchEvent(new CustomEvent('ac:pick', { bubbles: true, detail: it }));
      };
      inp.addEventListener('input', e => {
        if (e.isTrusted && idField) idField.value = '';
        clearTimeout(timer);
        if (picking) { items = []; return; }
        const q = inp.value.trim();
        if (q.length < 2) { items = []; render(); return; }
        timer = setTimeout(async () => {
          const r = await fetch('/admin/api/ac?type=' + encodeURIComponent(inp.dataset.ac) + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' }).then(x => x.json()).catch(() => ({ items: [] }));
          items = r.items || [];
          sel = -1;
          render();
        }, 150);
      });
      inp.addEventListener('keydown', e => {
        if (list.hidden) return;
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(0, Math.min(items.length - 1, sel + (e.key === 'ArrowDown' ? 1 : -1))); render(); }
        else if (e.key === 'Enter' && sel >= 0) { e.preventDefault(); pick(items[sel]); }
        else if (e.key === 'Escape') { list.hidden = true; }
      });
      inp.addEventListener('blur', () => setTimeout(() => (list.hidden = true), 180));
      list.addEventListener('mousedown', e => { const b = e.target.closest('button'); if (b) { e.preventDefault(); pick(items[+b.dataset.i]); } });
    });
  }

  /* ---------------------------------------------------------- sélecteur de médias */
  BO.pickMedia = (opts = {}) => new Promise(resolve => {
    const m = document.createElement('div');
    m.className = 'modal';
    m.innerHTML = '<div class="modal__box modal__box--wide" role="dialog" aria-modal="true" aria-label="Médiathèque">'
      + '<div class="modal__head"><h2 class="modal__t" style="font-size:22px">Choisir ' + (opts.multiple ? 'des images' : 'une image') + '</h2><span class="grow"></span>'
      + '<div class="toolbar"><div class="search"><input type="search" placeholder="Nom de fichier, légende, crédit…" data-q></div></div>'
      + '<label class="btn btn--yellow">Importer…<input type="file" accept="image/*,application/pdf" multiple hidden data-up></label>'
      + (opts.multiple ? '<button type="button" class="btn btn--navy" data-ok>Ajouter la sélection</button>' : '')
      + '<button type="button" class="btn" data-close>Fermer</button></div>'
      + '<div class="modal__body"><div class="drop" data-drop><b>Glissez des images ici</b><span class="small muted">JPG, PNG, WebP, GIF ou PDF · 25 Mo max</span></div><div class="mgrid" data-grid></div><div class="row" style="justify-content:center"><button type="button" class="btn" data-more hidden>Afficher plus</button></div></div></div>';
    document.body.appendChild(m);
    const grid = $('[data-grid]', m), more = $('[data-more]', m), q = $('[data-q]', m);
    let page = 1, timer, chosen = new Map();
    const close = v => { m.remove(); resolve(v); };
    const load = async (reset) => {
      if (reset) { page = 1; grid.innerHTML = ''; }
      const r = await fetch('/admin/api/medias?q=' + encodeURIComponent(q.value) + '&page=' + page, { credentials: 'same-origin' }).then(x => x.json()).catch(() => ({ items: [] }));
      grid.insertAdjacentHTML('beforeend', (r.items || []).map(it => '<button type="button" class="mtile' + (chosen.has(it.file) ? ' is-on' : '') + '" data-file="' + esc(it.file) + '" title="' + esc(it.file) + '"><span class="mtile__img"><img src="' + esc(it.thumb) + '" alt="" loading="lazy">' + (it.flag ? '<span class="mtile__flag">' + esc(it.flag) + '</span>' : '') + '</span><span class="mtile__name">' + esc(it.caption || it.name) + '</span></button>').join(''));
      more.hidden = !r.more;
    };
    grid.addEventListener('click', e => {
      const t = e.target.closest('[data-file]');
      if (!t) return;
      if (!opts.multiple) return close([t.dataset.file]);
      if (chosen.has(t.dataset.file)) { chosen.delete(t.dataset.file); t.classList.remove('is-on'); } else { chosen.set(t.dataset.file, 1); t.classList.add('is-on'); }
    });
    $('[data-ok]', m)?.addEventListener('click', () => close([...chosen.keys()]));
    $('[data-close]', m).addEventListener('click', () => close([]));
    m.addEventListener('click', e => { if (e.target === m) close([]); });
    more.addEventListener('click', () => { page++; load(false); });
    q.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => load(true), 200); });
    const upload = async files => {
      for (const f of files) {
        const fd = new FormData();
        fd.append('file', f);
        BO.toast('Envoi de ' + f.name + '…');
        const r = await BO.post('/admin/medias/envoi', fd, false);
        if (r.ok) { chosen.set(r.file, 1); if (!opts.multiple) return close([r.file]); } else BO.toast(r.error || 'Envoi impossible', true);
      }
      load(true);
    };
    $('[data-up]', m).addEventListener('change', e => upload([...e.target.files]));
    const drop = $('[data-drop]', m);
    drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('is-over'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('is-over'));
    drop.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('is-over'); upload([...e.dataTransfer.files]); });
    load(true);
    setTimeout(() => q.focus(), 30);
  });
  // Champ « image » : bouton Choisir / Retirer
  function initImagePickers(root) {
    $$('[data-image-field]', root).forEach(box => {
      if (box.dataset.ready) return;
      box.dataset.ready = '1';
      const input = $('input[type=hidden]', box), prev = $('.imgpick__prev', box), name = $('[data-image-name]', box);
      const show = () => {
        prev.innerHTML = input.value ? '<img src="' + BO.thumb(input.value, 480) + '" alt="">' : 'Aucune image';
        if (name) name.textContent = input.value || '';
      };
      $('[data-image-pick]', box)?.addEventListener('click', async () => {
        const r = await BO.pickMedia();
        if (r[0]) { input.value = r[0]; show(); input.dispatchEvent(new Event('input', { bubbles: true })); }
      });
      $('[data-image-clear]', box)?.addEventListener('click', () => { input.value = ''; show(); input.dispatchEvent(new Event('input', { bubbles: true })); });
      show();
    });
  }
  // Galerie : ajout de plusieurs images d'un coup
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-gallery-add]');
    if (!b) return;
    const rep = b.closest('[data-repeater]');
    const files = await BO.pickMedia({ multiple: true });
    files.forEach(f => {
      const node = addItem(rep);
      const img = node && $('[data-field="image"]', node);
      if (img) { img.value = f; img.dispatchEvent(new Event('input', { bubbles: true })); node.querySelector('[data-image-field]') && (node.querySelector('[data-image-field]').dataset.ready = '', initImagePickers(node)); }
    });
  });

  /* ---------------------------------------------------------- onglets internes */
  function initTabs(root) {
    $$('[data-ftabs]', root).forEach(bar => {
      if (bar.dataset.ready) return;
      bar.dataset.ready = '1';
      const scope = bar.closest('[data-tabs-scope]') || document;
      const show = key => {
        $$('button[data-tab]', bar).forEach(b => b.classList.toggle('is-on', b.dataset.tab === key));
        $$('[data-panel]', scope).forEach(p => p.classList.toggle('is-on', p.dataset.panel === key));
        try { sessionStorage.setItem('bo-tab-' + location.pathname, key); } catch (e) { /* */ }
      };
      bar.addEventListener('click', e => { const b = e.target.closest('button[data-tab]'); if (b) show(b.dataset.tab); });
      let start = (location.hash || '').slice(1);
      if (!$('button[data-tab="' + start + '"]', bar)) { try { start = sessionStorage.getItem('bo-tab-' + location.pathname) || ''; } catch (e) { start = ''; } }
      show($('button[data-tab="' + start + '"]', bar) ? start : ($('button[data-tab]', bar)?.dataset.tab || ''));
    });
  }

  /* ---------------------------------------------------------- formulaire JSON (fiches, collections) */
  // Lit les champs d'un conteneur : [name] (chemin pointé) et [data-repeater] (listes).
  BO.collect = function collect(root) {
    const out = {};
    const own = el => el.closest('[data-item], [data-collect]') === root;
    const set = (obj, path, val) => {
      const keys = path.split('.');
      let o = obj;
      keys.slice(0, -1).forEach(k => { if (typeof o[k] !== 'object' || o[k] === null || Array.isArray(o[k])) o[k] = {}; o = o[k]; });
      o[keys[keys.length - 1]] = val;
    };
    const valueOf = el => {
      if (el.type === 'checkbox') return el.dataset.value !== undefined ? (el.checked ? el.dataset.value : null) : el.checked;
      if (el.type === 'radio') return el.checked ? el.value : undefined;
      if (el.multiple) return [...el.selectedOptions].map(o => o.value);
      const v = el.value;
      if (el.dataset.type === 'number') return v === '' ? null : Number(String(v).replace(',', '.'));
      if (el.dataset.type === 'int') return v === '' ? null : parseInt(v, 10);
      if (el.dataset.type === 'json') { try { return JSON.parse(v || 'null'); } catch (e) { return null; } }
      if (el.dataset.type === 'lines') return v.split('\n').map(s => s.trim()).filter(Boolean);
      return v;
    };
    $$('[name], [data-field]', root).forEach(el => {
      if (!own(el) || el.closest('[data-nocollect]')) return;
      const path = el.dataset.field ?? el.name;
      if (!path || path === '_csrf' || path.endsWith('[]')) return;
      const v = valueOf(el);
      if (v === undefined) return;
      if (el.type === 'checkbox' && el.dataset.multi !== undefined) {
        // ensemble de cases → tableau
        const cur = path.split('.').reduce((o, k) => (o ? o[k] : undefined), out) || [];
        if (v) cur.push(v);
        set(out, path, cur);
        return;
      }
      set(out, path, v);
    });
    $$('[data-repeater]', root).forEach(rep => {
      if (rep.closest('[data-item], [data-collect]') !== root) return;
      const items = $$(':scope > [data-item]', rep).map(it => {
        const v = collect(it);
        return rep.dataset.scalar !== undefined ? (v._ ?? '') : v;
      });
      set(out, rep.dataset.repeater, rep.dataset.scalar !== undefined ? items.filter(x => x !== '') : items);
    });
    return out;
  };

  /** Formulaire enregistré en JSON : <form data-json-form data-url="…"> */
  function initJsonForms(root) {
    $$('form[data-json-form]', root).forEach(form => {
      if (form.dataset.ready) return;
      form.dataset.ready = '1';
      form.setAttribute('data-collect', '');
      const saved = $('[data-saved]', form) || $('[data-saved]');
      const draftKey = form.dataset.draft ? 'bo-draft-' + form.dataset.draft : null;
      let dirty = false, busy = false, timer;
      const markDirty = () => {
        if (!dirty) { dirty = true; if (saved) { saved.textContent = 'Modifications non enregistrées'; saved.classList.add('is-dirty'); } }
        clearTimeout(timer);
        if (draftKey) timer = setTimeout(() => { try { localStorage.setItem(draftKey, JSON.stringify({ at: Date.now(), base: form.dataset.modified || '', data: BO.collect(form) })); } catch (e) { /* stockage plein */ } }, 1500);
      };
      form.addEventListener('input', e => { e.target.closest?.('.f')?.classList.add('is-dirty'); markDirty(); });
      form.addEventListener('change', markDirty);
      window.addEventListener('beforeunload', e => { if (dirty && !BO.leaving) { e.preventDefault(); e.returnValue = ''; } });
      const save = async (extra = {}) => {
        if (busy) return;
        // Verrou de modification (verrou.js) : lecture seule tant que quelqu'un d'autre modifie.
        if (form.dataset.readonly) { BO.toast(form.dataset.readonly, true); return; }
        busy = true;
        $$('[data-save]').forEach(b => (b.disabled = true));
        const data = BO.collect(form);
        Object.assign(data, extra);
        data._modified = form.dataset.modified || '';
        const r = await BO.post(form.dataset.url, data);
        busy = false;
        $$('[data-save]').forEach(b => (b.disabled = false));
        if (r.locked) {
          const go = BO.takeLock && await BO.confirm('Enregistrement impossible', r.locked + ' Prendre la main et enregistrer vos modifications ?', 'Prendre la main', true);
          if (go && await BO.takeLock()) return save(extra);
          if (!BO.takeLock) BO.toast(r.locked, true);
          return;
        }
        if (r.conflict) {
          const go = await BO.confirm('Modifiée entre-temps', r.conflict + ' Enregistrer quand même vos modifications ?', 'Écraser', true);
          if (go) { form.dataset.modified = r.modified; return save(extra); }
          return;
        }
        // Le serveur fait confirmer (garde-fou : fiche d'un match réutilisée pour un autre) puis on renvoie.
        if (r.confirm) {
          const c = r.confirm;
          if (await BO.confirm(c.title || 'Confirmer', c.text || '', c.ok || 'Confirmer', true)) return save(Object.assign({}, extra, c.extra || {}));
          return;
        }
        if (!r.ok) { BO.toast(r.error || 'Enregistrement impossible', true); if (r.field) highlight(r.field); return; }
        dirty = false;
        if (draftKey) { try { localStorage.removeItem(draftKey); } catch (e) { /* */ } }
        form.dataset.modified = r.modified || form.dataset.modified;
        $$('.f.is-dirty', form).forEach(f => f.classList.remove('is-dirty'));
        if (saved) { saved.textContent = r.savedLabel || 'Enregistré'; saved.classList.remove('is-dirty'); }
        BO.toast(r.message || 'Enregistré');
        if (r.redirect || r.reload) {
          // Le message reste affiché après le rechargement de la page.
          try { sessionStorage.setItem('bo-toast', r.message || 'Enregistré'); } catch (e) { /* */ }
          if (r.redirect) { location.href = r.redirect; return; }
          location.reload();
          return;
        }
        form.dispatchEvent(new CustomEvent('bo:saved', { detail: r }));
      };
      const highlight = path => {
        const el = form.querySelector('[name="' + path + '"]');
        if (!el) return;
        const panel = el.closest('[data-panel]');
        if (panel) $('button[data-tab="' + panel.dataset.panel + '"]')?.click();
        el.closest('.f')?.classList.add('is-error');
        el.focus();
      };
      form.addEventListener('submit', e => { e.preventDefault(); save(); });
      $$('[data-save]').forEach(b => b.addEventListener('click', e => { e.preventDefault(); save(b.dataset.saveExtra ? JSON.parse(b.dataset.saveExtra) : {}); }));
      document.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(); } });
      // Brouillon local plus récent que la version enregistrée ?
      if (draftKey) {
        try {
          const d = JSON.parse(localStorage.getItem(draftKey) || 'null');
          if (d && d.base === (form.dataset.modified || '') && Date.now() - d.at < 14 * 864e5) {
            const bar = document.createElement('div');
            bar.className = 'alert';
            bar.style.gridColumn = '1 / -1'; // au-dessus des deux colonnes de l'éditeur
            bar.innerHTML = 'Un brouillon non enregistré du <b>' + new Date(d.at).toLocaleString('fr-FR') + '</b> a été retrouvé sur cet ordinateur. <button type="button" class="btn btn--sm btn--navy" data-draft-apply>Le récupérer</button> <button type="button" class="btn btn--sm" data-draft-drop>L’ignorer</button>';
            form.prepend(bar);
            $('[data-draft-drop]', bar).addEventListener('click', () => { localStorage.removeItem(draftKey); bar.remove(); });
            $('[data-draft-apply]', bar).addEventListener('click', async () => { bar.remove(); await save(d.data); location.reload(); });
          }
        } catch (e) { /* */ }
      }
      BO.saveForm = save;
    });
  }

  /* ---------------------------------------------------------- divers */
  function initMisc(root) {
    // Compteurs de caractères (SEO)
    $$('[data-count-max]', root).forEach(inp => {
      if (inp.dataset.ready) return;
      inp.dataset.ready = '1';
      const out = document.createElement('span');
      out.className = 'f__help';
      inp.insertAdjacentElement('afterend', out);
      const up = () => { const n = inp.value.length, m = +inp.dataset.countMax; out.textContent = n + ' / ' + m + ' caractères' + (n > m ? ' · trop long pour Google' : ''); out.style.color = n > m ? 'var(--red)' : ''; };
      inp.addEventListener('input', up);
      up();
    });
    // Champ dépendant d'une case : data-show-if="nom" (visible si la case est cochée)
    $$('[data-show-if]', root).forEach(el => {
      const scope = el.closest('form') || document;
      const srcs = $$('[name="' + el.dataset.showIf + '"]', scope);
      if (!srcs.length) return;
      const up = () => {
        const s = srcs[0];
        let on;
        if (s.type === 'radio') { const c = srcs.find(r => r.checked); on = !!c && (el.dataset.showValue === undefined || c.value === el.dataset.showValue); }
        else if (s.type === 'checkbox') on = s.checked;
        else on = s.value !== '' && (el.dataset.showValue === undefined || s.value === el.dataset.showValue);
        el.hidden = !on;
      };
      srcs.forEach(x => x.addEventListener('change', up));
      up();
    });
    // Aperçu « comme sur le site » avec les modifications en cours
    $$('[data-preview]', root).forEach(b => b.addEventListener('click', () => {
      const form = b.closest('form');
      const f = document.createElement('form');
      f.method = 'post';
      f.action = b.dataset.preview;
      f.target = '_blank';
      const add = (n, v) => { const i = document.createElement('input'); i.type = 'hidden'; i.name = n; i.value = v; f.appendChild(i); };
      add('_csrf', CSRF);
      add('data', JSON.stringify(form ? BO.collect(form) : {}));
      document.body.appendChild(f);
      f.submit();
      f.remove();
    }));
    // Copier dans le presse-papiers
    $$('[data-copy]', root).forEach(b => b.addEventListener('click', () => { navigator.clipboard?.writeText(b.dataset.copy); BO.toast('Copié'); }));
  }

  BO.init = function (root = document) {
    initAc(root);
    initImagePickers(root);
    initTabs(root);
    initJsonForms(root);
    initMisc(root);
    if (window.BOWys) window.BOWys.init(root);
  };
  document.addEventListener('DOMContentLoaded', () => BO.init(document));
  if (document.readyState !== 'loading') BO.init(document);
})();

/* Compléments : image miniature, éditeur de tableaux, import d'une composition. */
(function () {
  'use strict';
  const BO = window.BO;
  if (!BO) return;
  const { $, $$, esc } = BO;

  /* ---------------------------------------------------------- image miniature (logos…) */
  document.addEventListener('click', async e => {
    const box = e.target.closest('[data-image-mini]');
    if (!box) return;
    const input = $('input[type=hidden]', box);
    if (e.target.closest('[data-pick]')) {
      const r = await BO.pickMedia();
      if (!r[0]) return;
      input.value = r[0];
      $('[data-pick]', box).innerHTML = '<img src="' + esc(BO.thumb(r[0], 160)) + '" alt="">';
      input.dispatchEvent(new Event('input', { bubbles: true }));
    } else if (e.target.closest('[data-clear]')) {
      input.value = '';
      $('[data-pick]', box).textContent = '+';
      input.dispatchEvent(new Event('input', { bubbles: true }));
    }
  });

  /* ---------------------------------------------------------- lecture d'un tableau collé (Excel, page web, texte) */
  BO.parseTable = (html, text) => {
    if (html && /<table/i.test(html)) {
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const t = doc.querySelector('table');
      const rows = [...t.querySelectorAll('tr')].map(tr => [...tr.children].map(c => c.textContent.replace(/\s+/g, ' ').trim()));
      return rows.filter(r => r.some(c => c !== ''));
    }
    const lines = String(text || '').replace(/\r/g, '').split('\n').filter(l => l.trim() !== '');
    const sep = lines.some(l => l.includes('\t')) ? '\t' : (lines.some(l => l.includes(';')) ? ';' : ',');
    return lines.map(l => l.split(sep).map(c => c.trim()));
  };
  /** Fenêtre « Coller un tableau » : renvoie les lignes lues, ou null. */
  BO.pasteTable = (title, help) => new Promise(resolve => {
    const m = document.createElement('div');
    m.className = 'modal';
    m.innerHTML = '<div class="modal__box" style="width:min(720px,100%)" role="dialog" aria-modal="true"><h2 class="modal__t"></h2><p class="small muted" style="margin:0"></p>'
      + '<div class="pastezone" contenteditable="true" data-ph="Cliquez ici puis collez (Ctrl+V) le tableau copié dans Excel, LibreOffice ou une page web…" role="textbox" aria-multiline="true"></div>'
      + '<p class="small" data-info style="margin:0"></p><label class="toggle small"><input type="checkbox" data-first checked><span class="toggle__box"></span><span>La première ligne contient les intitulés des colonnes</span></label>'
      + '<div class="row row--end"><button type="button" class="btn" data-no>Annuler</button><button type="button" class="btn btn--navy" data-yes disabled>Importer</button></div></div>';
    $('.modal__t', m).textContent = title;
    $('p', m).textContent = help || '';
    document.body.appendChild(m);
    const zone = $('.pastezone', m), info = $('[data-info]', m), yes = $('[data-yes]', m);
    let rows = null;
    zone.addEventListener('paste', e => {
      e.preventDefault();
      rows = BO.parseTable(e.clipboardData.getData('text/html'), e.clipboardData.getData('text/plain'));
      zone.innerHTML = '<table style="border-collapse:collapse">' + rows.slice(0, 12).map(r => '<tr>' + r.map(c => '<td style="border:1px solid #ccc;padding:2px 6px">' + esc(c) + '</td>').join('') + '</tr>').join('') + '</table>' + (rows.length > 12 ? '<p>…</p>' : '');
      info.textContent = rows.length + ' ligne(s) lue(s), ' + Math.max(0, ...rows.map(r => r.length)) + ' colonne(s).';
      yes.disabled = !rows.length;
    });
    const done = v => { m.remove(); resolve(v); };
    $('[data-no]', m).addEventListener('click', () => done(null));
    m.addEventListener('click', e => { if (e.target === m) done(null); });
    yes.addEventListener('click', () => done({ rows, header: $('[data-first]', m).checked }));
    setTimeout(() => zone.focus(), 30);
  });

  /* ---------------------------------------------------------- éditeur de tableaux (champ JSON) */
  function initTableEditors(root) {
    $$('input[data-table-editor]', root).forEach(input => {
      if (input.dataset.ready) return;
      input.dataset.ready = '1';
      const single = input.dataset.single !== undefined;
      let data;
      try { data = JSON.parse(input.value || 'null'); } catch (e) { data = null; }
      let tables = single ? (data ? [data] : []) : (Array.isArray(data) ? data : []);
      const ui = document.createElement('div');
      ui.className = 'tbled';
      input.insertAdjacentElement('afterend', ui);
      const save = () => {
        const clean = tables.map(t => ({ title: t.title || '', headers: t.headers || [], rows: (t.rows || []).filter(r => r.some(c => String(c).trim() !== '')), source_table: t.source_table ?? null }));
        input.value = JSON.stringify(single ? (clean[0] || null) : clean);
        input.dispatchEvent(new Event('input', { bubbles: true }));
      };
      const width = t => Math.max(t.headers.length, ...t.rows.map(r => r.length), 1);
      const render = () => {
        ui.innerHTML = tables.map((t, ti) => {
          const w = width(t);
          const cell = (v, r, c) => '<input type="text" value="' + esc(v ?? '') + '" data-r="' + r + '" data-c="' + c + '" aria-label="Cellule">';
          return '<div class="tbled__t" data-t="' + ti + '">'
            + '<div class="tbled__head">' + (single ? '<b class="d" style="text-transform:uppercase">Tableau</b>' : '<input class="in in--sm" type="text" value="' + esc(t.title || '') + '" data-title placeholder="Titre du tableau (facultatif)" aria-label="Titre du tableau">')
            + '<button type="button" class="btn btn--sm" data-paste>Coller un tableau…</button>' + (single ? '' : '<button type="button" class="btn btn--sm btn--danger" data-deltable>Supprimer</button>') + '</div>'
            + '<div class="tbled__grid"><table><thead><tr><th class="tbled__x"></th>' + Array.from({ length: w }, (_, c) => '<th>' + cell(t.headers[c], -1, c) + '</th>').join('') + '</tr>'
            + '<tr><th class="tbled__x"></th>' + Array.from({ length: w }, (_, c) => '<th class="tbled__x" style="width:auto"><button type="button" data-delcol="' + c + '" title="Supprimer la colonne">✕ col.</button></th>').join('') + '</tr></thead><tbody>'
            + t.rows.map((row, r) => '<tr><td class="tbled__x"><button type="button" data-delrow="' + r + '" title="Supprimer la ligne">✕</button></td>' + Array.from({ length: w }, (_, c) => '<td>' + cell(row[c], r, c) + '</td>').join('') + '</tr>').join('')
            + '</tbody></table></div><div class="tbled__foot"><button type="button" class="btn btn--sm" data-addrow>+ Ligne</button><button type="button" class="btn btn--sm" data-addcol>+ Colonne</button><span class="xs muted">' + t.rows.length + ' ligne(s)</span></div></div>';
        }).join('') + (single && tables.length ? '' : '<button type="button" class="rep__add" data-addtable>+ ' + (single ? 'Créer le tableau' : 'Ajouter un tableau') + '</button>');
      };
      ui.addEventListener('input', e => {
        e.stopPropagation();
        const box = e.target.closest('[data-t]');
        if (!box) return;
        const t = tables[+box.dataset.t];
        if (e.target.matches('[data-title]')) t.title = e.target.value;
        else if (e.target.dataset.r !== undefined) {
          const r = +e.target.dataset.r, c = +e.target.dataset.c;
          if (r < 0) { while (t.headers.length <= c) t.headers.push(''); t.headers[c] = e.target.value; } else { while (t.rows[r].length <= c) t.rows[r].push(''); t.rows[r][c] = e.target.value; }
        }
        save();
      });
      ui.addEventListener('click', async e => {
        const b = e.target.closest('button');
        if (!b) return;
        const box = b.closest('[data-t]');
        const t = box ? tables[+box.dataset.t] : null;
        if (b.matches('[data-addtable]')) tables.push({ title: '', headers: ['', ''], rows: [['', '']] });
        else if (b.matches('[data-addrow]')) t.rows.push(Array(width(t)).fill(''));
        else if (b.matches('[data-addcol]')) { t.headers.push(''); t.rows.forEach(r => r.push('')); }
        else if (b.dataset.delrow !== undefined) t.rows.splice(+b.dataset.delrow, 1);
        else if (b.dataset.delcol !== undefined) { const c = +b.dataset.delcol; t.headers.splice(c, 1); t.rows.forEach(r => r.splice(c, 1)); }
        else if (b.matches('[data-deltable]')) { if (!(await BO.confirm('Supprimer ce tableau ?', '', 'Supprimer', true))) return; tables.splice(+box.dataset.t, 1); }
        else if (b.matches('[data-paste]')) {
          const r = await BO.pasteTable('Coller un tableau', 'Le tableau collé remplace le contenu de celui-ci.');
          if (!r) return;
          const rows = r.rows.slice();
          t.headers = r.header ? rows.shift() : (t.headers.length ? t.headers : []);
          t.rows = rows;
        } else return;
        render();
        save();
      });
      render();
    });
  }

  /* ---------------------------------------------------------- composition : import d'un tableau */
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-lineup-paste]');
    if (!b) return;
    const rep = document.querySelector('[data-repeater="match.lineup"]');
    if (!rep) return;
    const r = await BO.pasteTable('Importer une composition', 'Colonnes : Poste (G, D, M, A, R, E), Nom et prénom, Buts, Remplacement, Cartons — comme dans les fiches de l’ancien site. Avec une ligne d’en-tête, les colonnes sont reconnues dans n’importe quel ordre (dont « Numéro »).');
    if (!r || !r.rows.length) return;
    const rows = r.rows.slice(r.header ? 1 : 0);
    // Colonnes : d'après la ligne d'en-tête si elle est reconnue, sinon dans l'ordre habituel.
    const norm = v => String(v || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
    let cols = { pos: 0, name: 1, num: -1, goals: 2, sub: 3, cards: 4 };
    if (r.header) {
      const h = r.rows[0].map(norm);
      const find = re => h.findIndex(x => re.test(x));
      const c = { pos: find(/^post/), name: find(/nom|joueur/), num: find(/^(n°|no\b|num|maillot)/), goals: find(/^but/), sub: find(/chang|rempl/), cards: find(/carton/) };
      if (c.name >= 0) cols = c;
    }
    const at = (cells, i) => (i >= 0 ? String(cells[i] ?? '').trim() : '');
    const existing = $$(':scope > [data-item]', rep).filter(it => $('[data-field="name"]', it)?.value.trim());
    if (existing.length && await BO.confirm('Remplacer la composition actuelle ?', existing.length + ' joueur(s) déjà saisi(s). « Remplacer » efface la composition actuelle ; « Annuler » ajoute les joueurs à la suite.', 'Remplacer', true)) {
      $$(':scope > [data-item]', rep).forEach(it => it.remove());
    }
    const map = { gardien: 'G', defenseur: 'D', 'défenseur': 'D', milieu: 'M', attaquant: 'A', 'remplaçant': 'R', remplacant: 'R', 'entraîneur': 'E', entraineur: 'E' };
    rows.forEach(cells => {
      let [pos, name, num, goals, sub, cards] = [at(cells, cols.pos), at(cells, cols.name), at(cells, cols.num), at(cells, cols.goals), at(cells, cols.sub), at(cells, cols.cards)];
      if (!name && pos && !/^[GDMARE]$/i.test(pos)) { name = pos; pos = ''; }
      if (!String(name || '').trim()) return;
      const p = map[String(pos).toLowerCase()] || String(pos).trim().toUpperCase().slice(0, 1);
      const node = BO.addItem(rep);
      if (!node) return;
      const set = (f, v) => { const el = $('[data-field="' + f + '"]', node); if (el) el.value = v ?? ''; };
      set('position', /^[GDMARE]$/.test(p) ? p : '');
      set('name', String(name).trim());
      set('number', num);
      set('goals_text', goals);
      set('sub_text', sub);
      set('cards_text', cards);
    });
    rep.dispatchEvent(new Event('input', { bubbles: true }));
    BO.toast(rows.length + ' ligne(s) importée(s). Vérifiez les postes puis enregistrez : les noms seront reliés aux fiches joueurs.');
  });

  // Listes de filtres : envoi du formulaire dès qu'un choix change.
  document.addEventListener('change', e => {
    const el = e.target.closest('[data-autosubmit]');
    if (el && el.form) el.form.submit();
  });

  // Réglages › IA : recharger la liste des modèles Gemini depuis la clé enregistrée.
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-models-refresh]');
    if (!b) return;
    b.disabled = true;
    const r = await BO.post('/admin/api/modeles', {});
    b.disabled = false;
    if (!r.ok) { BO.toast(r.error || 'Chargement impossible', true); return; }
    document.querySelectorAll('[data-models]').forEach(box => {
      const sel = box.querySelector('select');
      const list = r[box.dataset.models] || {};
      const cur = sel.value;
      [...sel.options].slice(1).forEach(o => o.remove());
      Object.entries(list).forEach(([id, label]) => { const o = new Option(label, id); if (id === cur) o.selected = true; sel.add(o); });
    });
    BO.toast('Liste des modèles mise à jour (' + Object.keys(r.generate || {}).length + ' modèles).');
  });

  // Bulles d'aide « ? » : texte court au survol, au clavier (Tab) et au toucher.
  const hintConf = (() => { try { return JSON.parse(document.getElementById('bo-tips')?.textContent || '{}'); } catch (e) { return {}; } })();
  const hintKey = (el) => (el.querySelector('label')?.textContent || el.childNodes[0]?.textContent || el.textContent || '')
    .replace(/\s+/g, ' ').replace(/\s*\*\s*$/, '').trim().toLowerCase();
  let hintPop = null, hintFor = null, hintTimer = null, hintAt = 0;
  const hideHint = () => { clearTimeout(hintTimer); if (hintPop) hintPop.hidden = true; if (hintFor) hintFor.setAttribute('aria-expanded', 'false'); hintFor = null; };
  const showHint = (btn) => {
    clearTimeout(hintTimer);
    if (!hintPop) {
      hintPop = document.createElement('div');
      hintPop.className = 'hintpop'; hintPop.id = 'hintpop'; hintPop.setAttribute('role', 'tooltip'); hintPop.hidden = true;
      hintPop.addEventListener('mouseenter', () => clearTimeout(hintTimer));
      hintPop.addEventListener('mouseleave', () => { hintTimer = setTimeout(hideHint, 200); });
      document.body.appendChild(hintPop);
    }
    if (hintFor && hintFor !== btn) hintFor.setAttribute('aria-expanded', 'false');
    if (hintFor !== btn || hintPop.hidden) hintAt = Date.now();
    hintFor = btn;
    hintPop.innerHTML = btn.dataset.hint + (btn.dataset.guide ? '<br><a href="' + btn.dataset.guide + '">Voir le guide de cet écran →</a>' : '');
    hintPop.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
    btn.setAttribute('aria-describedby', 'hintpop');
    const r = btn.getBoundingClientRect(), w = hintPop.offsetWidth, h = hintPop.offsetHeight;
    const left = Math.max(8, Math.min(window.innerWidth - w - 8, r.left + r.width / 2 - w / 2));
    const top = r.bottom + 8 + h > window.innerHeight - 8 ? Math.max(8, r.top - h - 8) : r.bottom + 8;
    hintPop.style.left = left + 'px'; hintPop.style.top = top + 'px';
  };
  const addHints = (root) => {
    const tips = hintConf.tips || {};
    const add = (el, text, guide) => {
      if (!text || el.dataset.hinted) return;
      el.dataset.hinted = '1';
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'hint'; b.textContent = '?';
      b.dataset.hint = text;
      if (guide) b.dataset.guide = guide;
      const name = (el.textContent || '').replace(/\s+/g, ' ').trim();
      b.setAttribute('aria-label', 'Aide : ' + name);
      b.setAttribute('aria-expanded', 'false');
      // Le « ? » est dans le libellé du champ : on fixe le nom du champ pour qu'il ne l'englobe pas.
      const ctl = el.closest('label')?.control;
      if (ctl && !ctl.hasAttribute('aria-label') && !ctl.hasAttribute('aria-labelledby')) ctl.setAttribute('aria-label', name);
      const label = el.querySelector('label');
      if (label) label.after(b); else el.appendChild(b);
    };
    if (root === document) {
      const h = document.querySelector('.top__h');
      if (h && tips.screen) add(h, tips.screen, hintConf.guide);
    }
    root.querySelectorAll('.card__t, .f__k').forEach(el => {
      if (el.closest('template')) return;
      // Dans une liste d'éléments (galerie, temps forts…), la bulle n'est que sur le premier.
      const item = el.closest('[data-item]');
      if (item && item.previousElementSibling?.matches('[data-item]')) {
        el.querySelectorAll('.hint').forEach(b => b.remove());
        delete el.dataset.hinted;
        return;
      }
      const k = hintKey(el), rep = el.closest('[data-repeater]');
      const text = (rep && tips[rep.dataset.repeater + ':' + k]) || tips[k];
      if (k && k !== 'screen' && text) add(el, text, null);
    });
  };
  // Survol à la souris seulement : au toucher, les événements de souris simulés sont ignorés.
  let lastTouch = 0;
  document.addEventListener('pointerdown', e => { if (e.pointerType !== 'mouse') lastTouch = Date.now(); }, true);
  const byTouch = () => Date.now() - lastTouch < 1000;
  document.addEventListener('mouseover', e => { const b = e.target.closest('.hint'); if (b && !byTouch()) showHint(b); });
  document.addEventListener('mouseout', e => { const b = e.target.closest('.hint'); if (b && !byTouch() && !b.contains(e.relatedTarget)) hintTimer = setTimeout(hideHint, 250); });
  document.addEventListener('focusin', e => { const b = e.target.closest('.hint'); if (b) showHint(b); else if (hintPop && !hintPop.contains(e.target)) hideHint(); });
  document.addEventListener('click', e => {
    const b = e.target.closest('.hint');
    // Au toucher, le focus ouvre déjà la bulle : un 2e appui sur le même « ? » la referme.
    if (b) { e.preventDefault(); e.stopPropagation(); if (hintFor === b && !hintPop.hidden && e.pointerType !== 'mouse' && Date.now() - hintAt > 400) hideHint(); else showHint(b); return; }
    if (hintPop && !hintPop.hidden && !hintPop.contains(e.target)) hideHint();
  }, true);
  document.addEventListener('keydown', e => { if (e.key === 'Escape' && hintPop && !hintPop.hidden) hideHint(); });
  window.addEventListener('scroll', () => { if (hintPop && !hintPop.hidden) hideHint(); }, { passive: true });

  // Champs écrits à la main dans une grille (<label class="f"><span class="f__k">…</span><input>…) :
  // le champ et son aide passent dans un bloc .f__c, comme ceux de Form, pour s'aligner sur la ligne.
  const wrapFields = (root) => {
    root.querySelectorAll('.fgrid > .f, .fpair > .f').forEach(f => {
      if (f.querySelector(':scope > .f__c') || !f.querySelector(':scope > .f__k')) return;
      const c = document.createElement('div');
      c.className = 'f__c';
      [...f.childNodes].filter(n => !(n.nodeType === 1 && n.classList.contains('f__k'))).forEach(n => c.appendChild(n));
      f.appendChild(c);
    });
  };
  const prevInit = BO.init;
  BO.init = function (root = document) { prevInit(root); wrapFields(root); initTableEditors(root); addHints(root); BO.initHandles(root); };
  wrapFields(document);
  addHints(document);
  initTableEditors(document);
  BO.initHandles(document);
})();
