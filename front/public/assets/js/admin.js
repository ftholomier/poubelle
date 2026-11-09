/* Back-office Renov'Est — JavaScript natif : API JSON, formulaires, répéteurs, médiathèque, éditeur. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var csrf = ($('meta[name="csrf"]') || {}).content || '';

  function toast(msg, err) {
    var box = $('#bo-toasts'); if (!box || !msg) return;
    var t = document.createElement('div');
    t.className = 'toast' + (err ? ' toast--err' : '');
    t.textContent = msg; box.appendChild(t);
    setTimeout(function () { t.remove(); }, err ? 9000 : 4500);
  }

  function api(action, body) {
    var fd = body instanceof FormData ? body : new FormData();
    if (!(body instanceof FormData) && body) Object.keys(body).forEach(function (k) { fd.append(k, body[k]); });
    return fetch('/admin/api/' + action, { method: 'POST', body: fd, headers: { 'X-CSRF': csrf, 'Accept': 'application/json' }, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'Réponse inattendue du serveur (code ' + r.status + ').' }; }); })
      .then(function (res) {
        if (res && res.redirect && !res.ok && res.error) { toast(res.error, true); setTimeout(function () { location.href = res.redirect; }, 900); throw res; }
        return res;
      });
  }
  function done(res) {
    if (!res) return;
    if (!res.ok) { toast(res.error || 'Erreur', true); return; }
    if (res.message) toast(res.message);
    dirty = false;
    if (res.redirect) setTimeout(function () { location.href = res.redirect; }, res.message ? 500 : 0);
    else if (res.reload) setTimeout(function () { location.reload(); }, 700);
  }

  /* ------------------------------------------------------------ Formulaires [data-api] */
  var dirty = false;
  document.addEventListener('submit', function (e) {
    var f = e.target.closest('form[data-api]'); if (!f) return;
    e.preventDefault();
    if (f.getAttribute('data-confirm') && !confirm(f.getAttribute('data-confirm'))) return;
    $$('[data-rte]', f).forEach(rteSync);
    var btn = $('button[type="submit"]', f);
    if (btn) { btn.classList.add('is-busy'); btn.disabled = true; }
    api(f.getAttribute('data-api'), new FormData(f)).then(function (res) {
      if (btn) { btn.classList.remove('is-busy'); btn.disabled = false; }
      $$('.f.is-err', f).forEach(function (x) { x.classList.remove('is-err'); });
      done(res);
    }, function () { if (btn) { btn.classList.remove('is-busy'); btn.disabled = false; } });
  });
  document.addEventListener('input', function (e) { if (e.target.closest('form[data-dirty]')) dirty = true; });
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

  /* ------------------------------------------------------------ Boutons [data-action] */
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-action]'); if (!b) return;
    e.preventDefault();
    if (b.getAttribute('data-confirm') && !confirm(b.getAttribute('data-confirm'))) return;
    var fd = new FormData();
    Object.keys(b.dataset).forEach(function (k) { if (['action', 'confirm', 'busy'].indexOf(k) < 0) fd.append(k, b.dataset[k]); });
    var label = b.textContent;
    b.classList.add('is-busy'); if (b.dataset.busy) b.textContent = b.dataset.busy;
    api(b.getAttribute('data-action'), fd).then(function (res) { b.classList.remove('is-busy'); b.textContent = label; done(res); }, function () { b.classList.remove('is-busy'); b.textContent = label; });
  });

  /* ------------------------------------------------------------ Lignes cliquables, tri */
  document.addEventListener('click', function (e) {
    var tr = e.target.closest('tr[data-href]');
    if (tr && !e.target.closest('a,button')) location.href = tr.getAttribute('data-href');
  });
  $$('[data-sortable]').forEach(function (t) {
    t.addEventListener('click', function (e) {
      var b = e.target.closest('[data-move]'); if (!b) return;
      var tr = b.closest('tr'), dir = +b.getAttribute('data-move');
      if (dir < 0 && tr.previousElementSibling) tr.parentNode.insertBefore(tr, tr.previousElementSibling);
      if (dir > 0 && tr.nextElementSibling) tr.parentNode.insertBefore(tr.nextElementSibling, tr);
      var fd = new FormData();
      $$('tbody tr[data-id]', t).forEach(function (r) { fd.append('ids[]', r.getAttribute('data-id')); });
      api(t.getAttribute('data-sortable'), fd).then(done);
    });
  });

  /* ------------------------------------------------------------ Menu mobile */
  var burger = $('#bo-burger');
  if (burger) burger.addEventListener('click', function () { document.body.classList.toggle('nav-open'); });
  document.addEventListener('click', function (e) { if (document.body.classList.contains('nav-open') && !e.target.closest('.bo-side') && !e.target.closest('#bo-burger')) document.body.classList.remove('nav-open'); });

  /* ------------------------------------------------------------ Répéteurs */
  var repSeq = 1000;
  function initRep(root) {
    $$('[data-repeater]', root).forEach(function (rep) {
      if (rep.__init) return; rep.__init = true;
      rep.addEventListener('click', function (e) {
        var t = e.target;
        if (t.closest('[data-rep-add]') && t.closest('[data-repeater]') === rep) {
          var tpl = $(':scope > template', rep);
          var html = tpl.innerHTML.split('__i__').join(String(repSeq++));
          var wrap = document.createElement('div'); wrap.innerHTML = html.trim();
          var row = wrap.firstElementChild;
          rep.insertBefore(row, t.closest('[data-rep-add]'));
          initAll(row); dirty = true;
          var first = $('input,textarea,select', row); if (first) first.focus();
          return;
        }
        var row = t.closest('.rep__row'); if (!row || row.parentNode !== rep) return;
        if (t.closest('[data-rep-del]')) { row.remove(); dirty = true; }
        else if (t.closest('[data-rep-up]') && row.previousElementSibling && row.previousElementSibling.classList.contains('rep__row')) { rep.insertBefore(row, row.previousElementSibling); dirty = true; }
        else if (t.closest('[data-rep-down]') && row.nextElementSibling && row.nextElementSibling.classList.contains('rep__row')) { rep.insertBefore(row.nextElementSibling, row); dirty = true; }
      });
    });
  }

  /* ------------------------------------------------------------ Champs image */
  function initImages(root) {
    $$('[data-image]', root).forEach(function (w) {
      if (w.__init) return; w.__init = true;
      var input = $('input', w), pv = $('.img-f__pv', w);
      var paint = function () { pv.innerHTML = input.value ? '<img src="' + input.value.replace(/"/g, '&quot;') + '" alt="">' : ''; };
      input.addEventListener('change', paint);
      $('[data-pick]', w).addEventListener('click', function () { picker(false, function (paths) { input.value = paths[0]; paint(); dirty = true; }); });
      $('[data-clear]', w).addEventListener('click', function () { input.value = ''; paint(); dirty = true; });
    });
    $$('[data-images]', root).forEach(function (w) {
      if (w.__init) return; w.__init = true;
      var name = w.getAttribute('data-name');
      w.addEventListener('click', function (e) {
        var it = e.target.closest('.imgs__i');
        if (e.target.closest('[data-pick-multi]')) {
          picker(true, function (paths) {
            paths.forEach(function (p) {
              var d = document.createElement('div'); d.className = 'imgs__i';
              d.innerHTML = '<img alt=""><input type="hidden"><span><button type="button" data-up title="Avant">←</button><button type="button" data-down title="Après">→</button><button type="button" data-del title="Retirer">×</button></span>';
              $('img', d).src = p; var h = $('input', d); h.name = name; h.value = p;
              w.insertBefore(d, $('[data-pick-multi]', w));
            });
            dirty = true;
          });
          return;
        }
        if (!it) return;
        if (e.target.closest('[data-del]')) it.remove();
        else if (e.target.closest('[data-up]') && it.previousElementSibling) w.insertBefore(it, it.previousElementSibling);
        else if (e.target.closest('[data-down]') && it.nextElementSibling && it.nextElementSibling.classList.contains('imgs__i')) w.insertBefore(it.nextElementSibling, it);
        dirty = true;
      });
    });
  }

  /* ------------------------------------------------------------ Médiathèque (fenêtre de choix + page) */
  var mediaCache = null;
  function loadMedia(force) {
    if (mediaCache && !force) return Promise.resolve(mediaCache);
    return api('media.list', { folder: 'tout' }).then(function (r) { mediaCache = (r && r.items) || []; return mediaCache; });
  }
  function upload(files) {
    var fd = new FormData();
    Array.prototype.forEach.call(files, function (f) { fd.append('files[]', f); });
    toast('Envoi de ' + files.length + ' fichier(s)…');
    return api('media.upload', fd).then(function (r) { if (!r.ok) toast(r.error, true); else toast(r.message); mediaCache = null; return r; });
  }
  function tile(it, opts) {
    var d = document.createElement('div');
    d.className = 'mtile'; d.setAttribute('data-path', it.path);
    d.innerHTML = '<div class="mtile__i"></div><div class="mtile__n"></div>' + (opts && opts.manage ? '<div class="mtile__a"><button class="b b--icon" type="button" data-copy>Copier le lien</button>' + (it.folder === 'uploads' ? '<button class="b b--icon" type="button" data-del>Supprimer</button>' : '') + '</div>' : '');
    var box = $('.mtile__i', d);
    if (it.video) { var v = document.createElement('video'); v.src = it.path; v.muted = true; v.preload = 'metadata'; box.appendChild(v); }
    else { var i = document.createElement('img'); i.loading = 'lazy'; i.src = it.path; i.alt = ''; box.appendChild(i); }
    $('.mtile__n', d).textContent = it.name; $('.mtile__n', d).title = it.path;
    return d;
  }
  function renderGrid(grid, items, folder, q, opts) {
    grid.innerHTML = '';
    items.filter(function (it) { return (folder === 'tout' || it.folder === folder) && (!q || it.path.toLowerCase().indexOf(q) > -1); })
      .slice(0, 600).forEach(function (it) { grid.appendChild(tile(it, opts)); });
    if (!grid.children.length) grid.innerHTML = '<p class="bo-muted">Aucun fichier.</p>';
  }
  function picker(multi, cb) {
    var m = document.createElement('div');
    m.className = 'modal';
    m.innerHTML = '<div class="modal__c" role="dialog" aria-modal="true"><div class="modal__h"><h2>Choisir ' + (multi ? 'des images' : 'une image') + '</h2>' +
      '<input type="search" placeholder="Filtrer…" class="b b--l" style="cursor:text">' +
      '<label class="b b--y">Téléverser<input type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4" hidden></label>' +
      '<button class="b b--l" type="button" data-close>Fermer</button></div><div class="modal__b"><div class="mgrid"></div></div>' +
      '<div class="modal__f"><span class="bo-muted" style="margin-right:auto" data-count></span><button class="b b--l" type="button" data-close>Annuler</button><button class="b b--y" type="button" data-ok>Utiliser</button></div></div>';
    document.body.appendChild(m);
    var grid = $('.mgrid', m), q = $('input[type=search]', m), sel = [];
    var paint = function () { loadMedia().then(function (items) { renderGrid(grid, items, 'tout', q.value.toLowerCase(), null); $$('.mtile', grid).forEach(function (t) { t.classList.toggle('is-sel', sel.indexOf(t.getAttribute('data-path')) > -1); }); }); };
    var close = function () { m.remove(); };
    q.addEventListener('input', paint);
    $('input[type=file]', m).addEventListener('change', function () {
      upload(this.files).then(function (r) { if (r.ok) { r.items.forEach(function (it) { if (sel.indexOf(it.path) < 0) sel.push(it.path); }); if (!multi) sel = [r.items[0].path]; paint(); $('[data-count]', m).textContent = sel.length + ' sélectionnée(s)'; } });
    });
    m.addEventListener('click', function (e) {
      if (e.target === m || e.target.closest('[data-close]')) return close();
      if (e.target.closest('[data-ok]')) { if (sel.length) cb(sel); return close(); }
      var t = e.target.closest('.mtile'); if (!t) return;
      var p = t.getAttribute('data-path');
      if (!multi) { sel = [p]; cb(sel); return close(); }
      var i = sel.indexOf(p); if (i > -1) sel.splice(i, 1); else sel.push(p);
      t.classList.toggle('is-sel', i < 0);
      $('[data-count]', m).textContent = sel.length + ' sélectionnée(s)';
    });
    document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); } });
    paint(); q.focus();
  }
  // Page Médiathèque
  var mgrid = $('[data-media-grid]');
  if (mgrid) {
    var folder = 'tout', search = $('[data-media-search]');
    var repaint = function (force) { loadMedia(force).then(function (items) { renderGrid(mgrid, items, folder, (search.value || '').toLowerCase(), { manage: true }); }); };
    $$('[data-media-folder]').forEach(function (b) { b.addEventListener('click', function () { folder = b.getAttribute('data-media-folder'); $$('[data-media-folder]').forEach(function (x) { x.classList.toggle('is-on', x === b); }); repaint(); }); });
    search.addEventListener('input', function () { repaint(); });
    mgrid.addEventListener('click', function (e) {
      var t = e.target.closest('.mtile'); if (!t) return;
      var p = t.getAttribute('data-path');
      if (e.target.closest('[data-copy]')) { if (navigator.clipboard) navigator.clipboard.writeText(p); toast('Lien copié : ' + p); return; }
      if (e.target.closest('[data-del]')) { if (!confirm('Supprimer ce fichier ?')) return; api('media.delete', { path: p }).then(function (r) { done(r); if (r.ok) { mediaCache = null; repaint(true); } }); return; }
      window.open(p, '_blank');
    });
    var drop = $('[data-drop]'), inp = $('[data-upload]');
    inp.addEventListener('change', function () { upload(this.files).then(function () { repaint(true); }); this.value = ''; });
    ['dragenter', 'dragover'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (ev) { drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('is-over'); }); });
    drop.addEventListener('drop', function (e) { if (e.dataTransfer.files.length) upload(e.dataTransfer.files).then(function () { repaint(true); }); });
    repaint();
  }

  /* ------------------------------------------------------------ Éditeur de texte riche */
  function rteSync(w) {
    var ed = $('.rte__ed', w), ta = $('.rte__ta', w);
    if (!w.classList.contains('is-src')) ta.value = ed.innerHTML.replace(/<p><br><\/p>/g, '').trim();
  }
  function initRte(root) {
    $$('[data-rte]', root).forEach(function (w) {
      if (w.__init) return; w.__init = true;
      var ed = $('.rte__ed', w), ta = $('.rte__ta', w);
      ed.innerHTML = ta.value;
      ed.addEventListener('input', function () { dirty = true; });
      // Collage : texte simple (évite les styles parasites de Word ou du web)
      ed.addEventListener('paste', function (e) {
        var t = (e.clipboardData || window.clipboardData).getData('text/plain');
        if (t) { e.preventDefault(); document.execCommand('insertText', false, t); }
      });
      $('.rte__bar', w).addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return;
        var c = b.getAttribute('data-cmd');
        if (c === 'source') {
          if (w.classList.contains('is-src')) { ed.innerHTML = ta.value; w.classList.remove('is-src'); b.classList.remove('is-on'); }
          else { rteSync(w); w.classList.add('is-src'); b.classList.add('is-on'); }
          return;
        }
        if (w.classList.contains('is-src')) return;
        ed.focus();
        if (c === 'bold' || c === 'italic') document.execCommand(c);
        else if (c === 'h2' || c === 'h3' || c === 'p') document.execCommand('formatBlock', false, c.toUpperCase());
        else if (c === 'ul') document.execCommand('insertUnorderedList');
        else if (c === 'unlink') document.execCommand('unlink');
        else if (c === 'clear') document.execCommand('removeFormat');
        else if (c === 'link') { var u = prompt('Adresse du lien (https://… ou /page/)'); if (u) document.execCommand('createLink', false, u); }
        else if (c === 'image') {
          var range = window.getSelection().rangeCount ? window.getSelection().getRangeAt(0) : null;
          picker(false, function (p) { ed.focus(); if (range) { var s = window.getSelection(); s.removeAllRanges(); s.addRange(range); } document.execCommand('insertImage', false, p[0]); });
        }
        dirty = true;
      });
    });
  }

  function initAll(root) { initRep(root); initImages(root); initRte(root); }
  initAll(document);
})();
