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

  /* ------------------------------------------------------------ Modèles Gemini : liste dynamique */
  var modelSel = $('select[data-gemini-models]');
  if (modelSel) {
    var keyIn = $('input[data-gemini-key]');
    var status = document.createElement('div');
    status.className = 'f-help gm-status';
    var refresh = document.createElement('button');
    refresh.type = 'button'; refresh.className = 'b b--l'; refresh.textContent = '⟳ Actualiser la liste des modèles';
    refresh.style.marginTop = '8px';
    modelSel.parentNode.insertBefore(refresh, modelSel.nextSibling);
    modelSel.parentNode.insertBefore(status, refresh.nextSibling);
    var fmt = function (n) { return n >= 1000000 ? (n / 1000000).toLocaleString('fr-FR') + ' M' : n >= 1000 ? Math.round(n / 1000) + ' k' : String(n); };
    var fill = function (models, rec) {
      var cur = modelSel.value, found = false;
      modelSel.innerHTML = '';
      var auto = document.createElement('option');
      auto.value = ''; auto.textContent = 'Automatique (modèle recommandé' + (rec ? ' : ' + rec : '') + ')';
      modelSel.appendChild(auto);
      [['Modèles stables', true], ['Préversions et modèles expérimentaux', false]].forEach(function (g) {
        var list = models.filter(function (m) { return !!m.stable === g[1]; });
        if (!list.length) return;
        var og = document.createElement('optgroup'); og.label = g[0] + ' (' + list.length + ')';
        list.forEach(function (m) {
          var o = document.createElement('option');
          o.value = m.id; o.textContent = m.name + ' — ' + m.id;
          o.title = (m.description || '') + (m.input ? ' · entrée ' + fmt(m.input) + ' jetons' : '') + (m.output ? ' · sortie ' + fmt(m.output) + ' jetons' : '');
          if (m.id === cur) { o.selected = true; found = true; }
          og.appendChild(o);
        });
        modelSel.appendChild(og);
      });
      if (cur && !found) {
        var x = document.createElement('option');
        x.value = cur; x.textContent = cur + ' (introuvable pour cette clé)'; x.selected = true;
        modelSel.appendChild(x);
      }
    };
    var loading = 0;
    var load = function (key, force) {
      var n = ++loading;
      status.classList.remove('is-err');
      status.textContent = 'Chargement de la liste des modèles chez Google…';
      refresh.disabled = true;
      api('bot.models', { key: key || '', force: force ? '1' : '' }).then(function (res) {
        if (n !== loading) return;
        refresh.disabled = false;
        if (!res.ok) { status.textContent = res.error || 'Liste indisponible.'; status.classList.add('is-err'); return; }
        fill(res.models || [], res.recommended || '');
        status.textContent = res.message;
      }, function () { refresh.disabled = false; });
    };
    var timer = null;
    if (keyIn) keyIn.addEventListener('input', function () {
      clearTimeout(timer);
      var v = keyIn.value.trim();
      if (v.length >= 30) timer = setTimeout(function () { load(v, true); }, 600);
    });
    refresh.addEventListener('click', function () { load(keyIn ? keyIn.value.trim() : '', true); });
    // Clé déjà enregistrée mais liste pas encore chargée : chargement automatique
    if (modelSel.getAttribute('data-has-key') === '1' && modelSel.getAttribute('data-count') === '0') load('', false);
  }

  /* ------------------------------------------------------------ Avis Google : recherche de la fiche */
  var gs = $('form[data-google-search]');
  if (gs) {
    var gres = $('[data-google-results]', gs);
    gs.addEventListener('submit', function (e) {
      e.preventDefault();
      var keyIn = $('[data-google-key]', gs), key = keyIn ? keyIn.value.trim() : '';
      var btn = $('button[type="submit"]', gs);
      gres.innerHTML = '<p class="bo-muted">Recherche chez Google…</p>';
      btn.disabled = true;
      api('google.search', { q: gs.elements.q.value, key: key }).then(function (r) {
        btn.disabled = false;
        gres.innerHTML = '';
        if (!r.ok) { toast(r.error, true); return; }
        if (!r.results.length) {
          var p = document.createElement('p'); p.className = 'bo-muted';
          p.textContent = 'Aucune fiche trouvée pour « ' + r.query + ' ». Essayez avec l\'adresse complète ou collez le lien Google Maps de la fiche.';
          gres.appendChild(p); return;
        }
        r.results.forEach(function (pl) {
          var d = document.createElement('div'); d.className = 'gr__item';
          d.innerHTML = '<div><b></b><br><span class="bo-muted"></span></div><div class="gr__item-r"><span class="gr__stars"></span><button class="b b--y" type="button">C\'est ma fiche</button></div>';
          $('b', d).textContent = pl.name;
          $('.bo-muted', d).textContent = pl.address;
          $('.gr__stars', d).textContent = pl.rating ? '★ ' + String(pl.rating).replace('.', ',') + ' · ' + pl.count + ' avis' : 'Pas encore d\'avis';
          $('button', d).addEventListener('click', function () {
            this.disabled = true; this.textContent = 'Connexion…';
            api('google.select', { place_id: pl.place_id, key: key }).then(done, function () {});
          });
          gres.appendChild(d);
        });
      }, function () { btn.disabled = false; });
    });
  }

  function initAll(root) { initRep(root); initImages(root); initRte(root); }
  initAll(document);

  /* ------------------------------------------------------------ Anti-spam : tester un message */
  var ast = $('[data-antispam-test]');
  if (ast) ast.addEventListener('submit', function (e) {
    e.preventDefault();
    var out = $('[data-antispam-result]', ast), btn = $('button[type=submit]', ast);
    btn.classList.add('is-busy');
    api('antispam.test', new FormData(ast)).then(function (res) {
      btn.classList.remove('is-busy');
      if (!res.ok) { toast(res.error || 'Erreur', true); return; }
      var r = res.result;
      out.className = 'as-res ' + (r.spam ? 'is-spam' : 'is-ok');
      out.innerHTML = '';
      var h = document.createElement('b');
      h.textContent = (r.spam ? 'Serait mis en spam' : 'Passerait normalement') + ' — score ' + r.score + ' (seuil ' + r.limit + ')';
      out.appendChild(h);
      if (r.reasons.length) {
        var ul = document.createElement('ul');
        r.reasons.forEach(function (t) { var li = document.createElement('li'); li.textContent = t; ul.appendChild(li); });
        out.appendChild(ul);
      } else { var p = document.createElement('p'); p.textContent = 'Aucun indice de spam dans ce contenu.'; out.appendChild(p); }
      out.hidden = false;
    }, function () { btn.classList.remove('is-busy'); });
  });

  /* ------------------------------------------------------------ Mot de passe d'un compte : afficher, générer, copier */
  // Mot de passe facile à dicter : syllabes prononçables + chiffres, ex. « Bolatu-Rimeso-38 »
  function genPassword() {
    var c = 'bcdfgjklmnprstvz', v = 'aeiou', r = new Uint32Array(16), i = 0;
    (window.crypto || window.msCrypto).getRandomValues(r);
    var pick = function (s) { return s.charAt(r[i++] % s.length); };
    var word = function () { var w = ''; for (var k = 0; k < 3; k++) w += pick(c) + pick(v); return w.charAt(0).toUpperCase() + w.slice(1); };
    return word() + '-' + word() + '-' + (10 + r[i++] % 90);
  }
  $$('[data-pw]').forEach(function (w) {
    var inp = $('input', w), tg = $('[data-pw-toggle]', w);
    var show = function (on) { inp.type = on ? 'text' : 'password'; if (tg) tg.textContent = on ? 'Masquer' : 'Afficher'; };
    if (tg) tg.addEventListener('click', function () {
      if (!inp.value && !inp.readOnly) { toast('Ce champ sert à donner un nouveau mot de passe : il est vide. Tapez-en un ou cliquez sur « Générer ».', true); inp.focus(); return; }
      show(inp.type === 'password');
    });
    var gb = $('[data-pw-gen]', w);
    if (gb) gb.addEventListener('click', function () { inp.value = genPassword(); show(true); inp.focus(); inp.select(); });
    var cb = $('[data-pw-copy]', w);
    if (cb) cb.addEventListener('click', function () {
      if (!inp.value) { toast('Le champ est vide : tapez ou générez d\'abord un mot de passe.', true); return; }
      var ok = function () { toast('Mot de passe copié.'); };
      if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(inp.value).then(ok, function () { show(true); inp.select(); document.execCommand('copy'); ok(); });
      else { show(true); inp.select(); document.execCommand('copy'); ok(); }
    });
  });

  /* ------------------------------------------------------------ Guide et formation */
  // Progression gardée dans le navigateur, par compte : { done: [chapitres lus], total: n }
  function guideKey(user) { return 'rv-guide:' + (user || ''); }
  function guideLoad(user) {
    try { var v = JSON.parse(localStorage.getItem(guideKey(user)) || '{}'); return { done: Array.isArray(v.done) ? v.done : [], total: +v.total || 0 }; }
    catch (e) { return { done: [], total: 0 }; }
  }
  function guideSave(user, st) { try { localStorage.setItem(guideKey(user), JSON.stringify(st)); } catch (e) { /* navigation privée */ } }

  var card = $('[data-guide-card]');
  if (card) {
    var cs = guideLoad(card.getAttribute('data-user')), cp = $('[data-guide-progress]', card);
    if (cp) cp.textContent = cs.total ? (cs.done.length >= cs.total ? 'Formation terminée ✓' : 'Formation : ' + cs.done.length + ' / ' + cs.total + ' chapitres lus') : 'Commencer la formation →';
  }

  var guide = $('[data-guide]');
  if (guide) {
    var user = guide.getAttribute('data-user'), total = +guide.getAttribute('data-total') || 0;
    var st = guideLoad(user); st.total = total;
    st.done = st.done.filter(function (id) { return !!$('[data-gch="' + id + '"]', guide); });
    var paintGuide = function () {
      $$('[data-guide-done]', guide).forEach(function (b) {
        var on = st.done.indexOf(b.getAttribute('data-guide-done')) > -1;
        b.classList.toggle('is-on', on); b.textContent = on ? 'Compris ✓' : 'J\'ai compris ✓';
      });
      $$('[data-toc]', guide).forEach(function (a) { a.classList.toggle('is-done', st.done.indexOf(a.getAttribute('data-toc')) > -1); });
      $$('[data-toc] i', guide).forEach(function (i, k) { i.textContent = i.parentNode.classList.contains('is-done') ? '✓' : String(k + 1); });
      var pct = total ? Math.round(st.done.length / total * 100) : 0;
      $('[data-guide-bar]', guide).style.width = pct + '%';
      $('[data-guide-count]', guide).textContent = st.done.length + ' / ' + total + ' chapitres' + (st.done.length >= total ? ' — formation terminée, bravo !' : '');
      guideSave(user, st);
    };
    guide.addEventListener('click', function (e) {
      var b = e.target.closest('[data-guide-done]');
      if (b) {
        var id = b.getAttribute('data-guide-done'), i = st.done.indexOf(id);
        if (i > -1) st.done.splice(i, 1);
        else {
          st.done.push(id);
          // Enchaîne sur le chapitre suivant
          var next = b.closest('.gch').nextElementSibling;
          while (next && (!next.classList.contains('gch') || next.classList.contains('is-hidden'))) next = next.nextElementSibling;
          if (next) setTimeout(function () { next.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 250);
        }
        paintGuide();
        return;
      }
      var z = e.target.closest('[data-zoom]');
      if (z) {
        e.preventDefault();
        var o = document.createElement('div'); o.className = 'gzoom'; o.setAttribute('role', 'dialog'); o.setAttribute('aria-label', 'Capture agrandie (cliquez pour fermer)');
        var im = document.createElement('img'); im.src = z.getAttribute('href'); im.alt = ($('img', z) || {}).alt || '';
        o.appendChild(im); document.body.appendChild(o);
        var shut = function () { o.remove(); document.removeEventListener('keydown', esc); };
        var esc = function (ev) { if (ev.key === 'Escape') shut(); };
        o.addEventListener('click', shut); document.addEventListener('keydown', esc);
      }
    });
    var pb = $('[data-guide-print]', guide);
    if (pb) pb.addEventListener('click', function () { $$('img[loading="lazy"]', guide).forEach(function (i) { i.loading = 'eager'; }); setTimeout(function () { window.print(); }, 400); });

    // Recherche : n'affiche que les chapitres qui contiennent tous les mots tapés (accents ignorés)
    var norm = function (s) { return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); };
    var gq = $('[data-guide-search]', guide), empty = $('[data-guide-empty]', guide);
    if (gq) gq.addEventListener('input', function () {
      var words = norm(gq.value).split(/\s+/).filter(Boolean), shown = 0;
      $$('.gch', guide).forEach(function (sec) {
        var txt = sec._txt || (sec._txt = norm(sec.textContent));
        var ok = words.every(function (w) { return txt.indexOf(w) > -1; });
        sec.classList.toggle('is-hidden', !ok); if (ok) shown++;
        var t = $('[data-toc="' + sec.id + '"]', guide); if (t) t.hidden = !ok;
      });
      empty.hidden = shown > 0;
    });

    // Sommaire : chapitre en cours de lecture
    if ('IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (ents) {
        ents.forEach(function (en) {
          if (!en.isIntersecting) return;
          $$('[data-toc]', guide).forEach(function (a) { a.classList.toggle('is-on', a.getAttribute('data-toc') === en.target.id); });
        });
      }, { rootMargin: '-90px 0px -65% 0px' });
      $$('.gch', guide).forEach(function (s) { io.observe(s); });
    }
    paintGuide();
  }
})();
