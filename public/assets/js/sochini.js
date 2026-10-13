/* Album Sochini / Parini.
   1. Kiosque : sélecteurs type → album, navigation sans recharger la page (adresse ?album=…&v=… mise à jour),
      suite d'un long album chargée à la demande. Sans JavaScript, tout marche par liens et formulaire.
   2. « Mon album » : collection personnelle. Tous les tirages sont faits par le serveur (/api/album-vignettes) ;
      ici on affiche, on anime l'ouverture des pochettes et on envoie les demandes d'échange. */
(function () {
  'use strict';
  var root = document.getElementById('sochini');
  if (!root) return;
  var lang = document.documentElement.lang === 'en' ? 'en' : 'fr';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // ------------------------------------------------------------ 1. kiosque
  function bindKiosk() {
    var form = root.querySelector('[data-snb-form]');
    if (!form) return;
    var lists = JSON.parse(form.querySelector('[data-snb-lists]').textContent || '{}');
    var tSel = form.querySelector('[data-snb-type]'), vSel = form.querySelector('[data-snb-v]'), vWrap = form.querySelector('[data-snb-vwrap]');
    tSel.addEventListener('change', function () {
      var l = lists[tSel.value] || [];
      var single = l.length === 1 && l[0][0] === '';
      vSel.innerHTML = '';
      l.forEach(function (x) { var o = document.createElement('option'); o.value = x[0]; o.textContent = x[1]; vSel.appendChild(o); });
      if (l.length) vSel.selectedIndex = l.length - 1;
      vWrap.hidden = single; vSel.disabled = single;
    });
    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      var q = 'album=' + encodeURIComponent(tSel.value) + (vSel.disabled ? '' : '&v=' + encodeURIComponent(vSel.value));
      go(form.action + '?' + q, true);
    });
  }
  function go(href, push) {
    root.classList.add('is-loading');
    fetch(href, { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } }).then(function (r) { return r.text(); }).then(function (html) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var src = doc.getElementById('sochini');
      if (!src) { location.href = href; return; }
      ['[data-snb-kiosk]', '[data-snb-album]', '.snb__missing'].forEach(function (s) {
        var a = root.querySelector(s), b = src.querySelector(s);
        if (a && b) a.replaceWith(b);
        else if (a) a.remove();
        else if (b) root.querySelector('#mon-album').before(b);
      });
      document.title = doc.title;
      if (push) history.pushState({ snb: 1 }, '', href);
      root.classList.remove('is-loading');
      bindKiosk();
      var t = root.querySelector('#alb-title');
      if (t) { t.setAttribute('tabindex', '-1'); t.focus({ preventScroll: true }); root.querySelector('[data-snb-album]').scrollIntoView({ behavior: reduce ? 'auto' : 'smooth' }); }
    }).catch(function () { location.href = href; });
  }
  root.addEventListener('click', function (ev) {
    var a = ev.target.closest('a[data-snb-link]');
    if (a && !ev.metaKey && !ev.ctrlKey && !ev.shiftKey) { ev.preventDefault(); go(a.href, true); return; }
    var m = ev.target.closest('a[data-snb-next]');
    if (m && !ev.metaKey && !ev.ctrlKey) {
      ev.preventDefault();
      var box = m.closest('[data-snb-items]'), p = m.closest('[data-snb-more]');
      m.setAttribute('aria-busy', 'true');
      fetch(m.href.replace('#vignettes', '') + '&part=suite', { credentials: 'same-origin' }).then(function (r) { return r.text(); }).then(function (html) {
        var tmp = document.createElement('div'); tmp.innerHTML = html;
        p.remove();
        var first = tmp.querySelector('.alb__item a, .alb__item div');
        // Groupe continué : les vignettes rejoignent la dernière grille.
        var lastGrid = box.querySelector('.alb__grid:last-of-type');
        var firstGrid = tmp.firstElementChild;
        if (firstGrid && firstGrid.matches('ul.alb__grid') && lastGrid && lastGrid.getAttribute('data-group') === firstGrid.getAttribute('data-group')) {
          while (firstGrid.firstChild) lastGrid.appendChild(firstGrid.firstChild);
          firstGrid.remove();
        }
        while (tmp.firstChild) box.appendChild(tmp.firstChild);
        if (first) { first.setAttribute('tabindex', first.tagName === 'A' ? '0' : '-1'); first.focus({ preventScroll: true }); }
      }).catch(function () { location.href = m.href; });
    }
  });
  window.addEventListener('popstate', function () { go(location.href, false); });
  bindKiosk();

  // ------------------------------------------------------------ 2. Mon album
  var box = root.querySelector('[data-mya]');
  var app = box && box.querySelector('[data-mya-app]');
  if (!app) return;
  var T = JSON.parse((document.getElementById('mya-i18n') || {}).textContent || '{}');
  var API = box.getAttribute('data-api'), SHARE = box.getAttribute('data-share');
  var pool = {}, order = [], st = null, chap = 'general', page = 0, tab = 'album';
  var PER = 60;
  function fmt(s, v) { return String(s || '').replace(/\{(\w+)\}/g, function (_, k) { return v && v[k] !== undefined ? v[k] : ''; }); }
  function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function api(body) {
    body.lang = lang;
    return fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'same-origin', body: JSON.stringify(body) })
      .then(function (r) { return r.json(); });
  }
  function msg(t) { var m = app.querySelector('[data-mya-msg]'); if (m) { m.textContent = t || ''; m.hidden = !t; } }
  function name(pid) { return pool[pid] ? pool[pid].name : '#' + pid; }

  function load() {
    Promise.all([
      fetch(box.getAttribute('data-pool') + '?lang=' + lang, { credentials: 'same-origin' }).then(function (r) { return r.json(); }),
      api({ action: 'etat' })
    ]).then(function (res) {
      res[0].v.forEach(function (x) { pool[x[0]] = { no: x[1], name: x[2], r: x[3], img: x[4], href: x[5] }; order.push(x[0]); });
      st = res[1].state;
      render();
      defiBonus();
    }).catch(function () { app.innerHTML = '<p>' + esc(T.err) + '</p>'; });
  }
  // Défi du jour réussi sur cet appareil : vignettes bonus (le serveur vérifie la partie).
  function defiBonus() {
    var tok = null, k = 'mya:defi:' + new Date().toISOString().slice(0, 10);
    try { tok = localStorage.getItem('df:tok'); if (localStorage.getItem(k)) return; } catch (e) { return; }
    if (!tok || !st || !st.packs) return;
    api({ action: 'bonus', kind: 'defi', tok: tok }).then(function (r) {
      if (r.ok && r.pack && r.pack.length) { try { localStorage.setItem(k, '1'); } catch (e) {} st = r.state; render(); reveal(r.pack, T.defi, r.pages); }
    });
  }

  function chapter() { for (var i = 0; i < st.chapters.length; i++) if (st.chapters[i].k === chap) return st.chapters[i]; return st.chapters[0]; }
  function render() {
    if (!st) return;
    if (!st.enabled) { app.innerHTML = '<p class="mya__msg">' + esc(T.closed) + '</p>'; return; }
    var total = order.length, h = '';
    h += '<div class="mya__bar"><span class="mya__k"><b>' + st.distinct + ' / ' + total + '</b><small>' + esc(T.stuck) + '</small></span>'
      + '<span class="mya__k"><b>' + st.doubles + '</b><small>' + esc(T.doubles) + '</small></span>'
      + '<span class="mya__k"><b>' + st.packsLeft + '</b><small>' + esc(T.packsLeft) + '</small></span>'
      + '<button type="button" class="btn btn--yellow" data-mya-open' + (st.packsLeft ? '' : ' disabled') + '>' + esc(st.packsLeft ? fmt(T.open, { n: st.size }) : T.tomorrow) + '</button></div>';
    h += '<p class="mya__msg" data-mya-msg hidden></p>';
    h += '<div class="mya__tabs" role="tablist">' + ['album', 'echanges', 'sauvegarde'].map(function (k) {
      return '<button type="button" class="btn btn--ghost" role="tab" aria-selected="' + (tab === k) + '" data-mya-tab="' + k + '">' + esc(T['tab_' + k]) + '</button>';
    }).join('') + '</div>';
    if (tab === 'album') h += albumHtml();
    else if (tab === 'echanges') h += tradeHtml();
    else h += saveHtml();
    app.innerHTML = h;
    try { if (st.packs) localStorage.setItem('sr-vig', '1'); } catch (e) {}
  }
  function albumHtml() {
    var c = chapter(), ids = c.ids, pages = Math.ceil(ids.length / PER);
    page = Math.min(page, pages - 1);
    var h = '<div class="mya__chap"><label class="snk__f"><span>' + esc(T.page) + '</span><select class="in" data-mya-chap>'
      + st.chapters.map(function (x) { return '<option value="' + esc(x.k) + '"' + (x.k === c.k ? ' selected' : '') + '>' + esc(x.title) + ' (' + x.have + '/' + x.total + ')' + (x.done ? ' ✓' : '') + '</option>'; }).join('')
      + '</select></label><div class="mya__prog"><span>' + esc(fmt(T.progress, { a: c.have, b: c.total })) + '</span><progress max="' + c.total + '" value="' + c.have + '">' + c.have + '</progress></div></div>';
    if (c.done) h += '<p class="mya__msg">' + esc(fmt(T.done, { d: c.done })) + ' <a href="' + esc(SHARE + c.k + '.png') + '" target="_blank" rel="noopener">' + esc(T.shareImg) + '</a></p>';
    h += '<ul class="mya__grid">';
    ids.slice(page * PER, page * PER + PER).forEach(function (pid) {
      var p = pool[pid], n = st.s[pid] || 0;
      if (!p) return;
      if (n > 0) {
        h += '<li class="mya__slot is-own is-' + p.r + '"><span class="alb__no">' + p.no + '</span>' + (n > 1 ? '<span class="mya__x" title="' + esc(T.doubles) + '">×' + n + '</span>' : '')
          + (p.img ? '<img src="' + esc(p.img) + '" alt="" loading="lazy" decoding="async">' : '') + '<b><a href="' + esc(p.href) + '" style="color:inherit;text-decoration:none">' + esc(p.name) + '</a></b>'
          + (p.r !== 'n' ? '<span class="sr-only"> · ' + esc(T['r_' + p.r]) + '</span>' : '') + '</li>';
      } else {
        h += '<li class="mya__slot"><span class="alb__no" aria-hidden="true">' + p.no + '</span><b>' + esc(p.name) + '</b><span class="sr-only">' + esc(T.missing) + '</span></li>';
      }
    });
    h += '</ul>';
    if (pages > 1) h += '<div class="mya__pager"><button type="button" class="btn btn--ghost" data-mya-pg="-1"' + (page ? '' : ' disabled') + '>←</button><span>' + esc(fmt(T.pg, { a: page + 1, b: pages })) + '</span><button type="button" class="btn btn--ghost" data-mya-pg="1"' + (page < pages - 1 ? '' : ' disabled') + '>→</button></div>';
    return h;
  }
  function options(list, empty) {
    return list.length ? list.map(function (pid) { return '<option value="' + pid + '">' + esc(pool[pid].no + ' · ' + pool[pid].name) + (st.s[pid] > 1 ? ' (×' + st.s[pid] + ')' : '') + '</option>'; }).join('') : '<option value="">' + esc(empty) + '</option>';
  }
  function tradeHtml() {
    var dbl = order.filter(function (pid) { return (st.s[pid] || 0) > 1; });
    var miss = order.filter(function (pid) { return !st.s[pid]; });
    var h = '<div class="mya__panel"><form class="mya__box" data-mya-offer><h3>' + esc(T.offerT) + '</h3><p>' + esc(T.offerD) + '</p>'
      + '<label>' + esc(T.give) + '<select class="in" name="give">' + options(dbl, T.noDouble) + '</select></label>'
      + '<label>' + esc(T.want) + '<select class="in" name="want">' + options(miss, T.noMissing) + '</select></label>'
      + '<button class="btn btn--navy"' + (dbl.length && miss.length ? '' : ' disabled') + '>' + esc(T.makeCode) + '</button><div data-mya-newcode></div></form>';
    h += '<form class="mya__box" data-mya-accept><h3>' + esc(T.acceptT) + '</h3><label>' + esc(T.code) + '<input class="in" name="code" autocomplete="off" placeholder="ABCD-EFGH" maxlength="12" required></label>'
      + '<button class="btn btn--navy" data-mya-see>' + esc(T.see) + '</button><div data-mya-trade></div></form>';
    h += '<div class="mya__box"><h3>' + esc(T.offers) + '</h3>' + (st.offers.length ? '<ul class="mya__list">' + st.offers.map(function (o) {
      return '<li><span class="mya__code" style="font-size:15px">' + esc(o.code) + '</span> ' + esc(fmt(T.offerLine, { g: name(o.give), w: name(o.want) })) + (o.used ? ' · ' + esc(T.used) : ' <button type="button" class="linkbtn" data-mya-cancel="' + esc(o.code) + '">' + esc(T.cancel) + '</button>') + '</li>';
    }).join('') + '</ul>' : '<p>' + esc(T.noOffer) + '</p>') + '</div></div>';
    return h;
  }
  function saveHtml() {
    var h = '<div class="mya__panel">';
    if (st.linked) h += '<div class="mya__box"><h3>' + esc(T.saveT) + '</h3><p>' + esc(T.linked) + '</p></div>';
    else h += '<div class="mya__box"><h3>' + esc(T.saveT) + '</h3><p>' + esc(T.saveD) + '</p><button type="button" class="btn btn--navy" data-mya-save>' + esc(T.showCode) + '</button><div data-mya-savecode></div></div>';
    h += '<form class="mya__box" data-mya-restore><h3>' + esc(T.restoreT) + '</h3><p>' + esc(T.restoreD) + '</p><label>' + esc(T.code) + '<input class="in" name="code" autocomplete="off" required></label><button class="btn btn--navy">' + esc(T.restore) + '</button></form>';
    h += '<div class="mya__box"><h3>' + esc(T.privacyT) + '</h3><p>' + esc(T.privacy) + '</p></div></div>';
    return h;
  }

  // Ouverture animée : cartes face cachée, retournées l'une après l'autre.
  var layer = null;
  function reveal(pack, title, pages) {
    if (!layer) {
      layer = document.createElement('div');
      layer.className = 'mya__pack'; layer.setAttribute('role', 'dialog'); layer.setAttribute('aria-modal', 'true'); layer.setAttribute('aria-labelledby', 'mya-pack-t');
      document.body.appendChild(layer);
      layer.addEventListener('click', function (ev) { if (ev.target.closest('[data-mya-close]') || ev.target === layer) close(); });
      document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && layer && !layer.hidden) close(); });
    }
    var h = '<h3 id="mya-pack-t">' + esc(title) + '</h3><ul class="mya__cards" style="list-style:none;margin:0;padding:0">';
    pack.forEach(function (d) {
      var p = pool[d.pid] || { name: '#' + d.pid, no: '', r: d.r };
      h += '<li class="mya__card is-' + d.r + '"><span class="mya__cin"><span class="mya__back" aria-hidden="true">' + esc(T.brand) + '</span><span class="mya__front">'
        + (d.new ? '<span class="mya__tag">' + esc(T.isNew) + '</span>' : '')
        + (p.img ? '<img src="' + esc(p.img) + '" alt="">' : '<span class="alb__ph"></span>') + '<b>' + esc(p.no + ' · ' + p.name) + '</b></span></span>'
        + '<span class="sr-only">' + esc(p.name + (d.r !== 'n' ? ' · ' + T['r_' + d.r] : '') + (d.new ? ' · ' + T.isNew : ' · ' + T.double)) + '</span></li>';
    });
    h += '</ul>';
    (pages || []).forEach(function (k) { h += '<p class="mya__msg">' + esc(fmt(T.pageDone, { t: chTitle(k) })) + '</p>'; });
    h += '<button type="button" class="btn btn--yellow" data-mya-close>' + esc(T.stick) + '</button>';
    layer.innerHTML = h; layer.hidden = false;
    var cards = layer.querySelectorAll('.mya__card');
    cards.forEach(function (c, i) { if (reduce) c.classList.add('is-flip'); else setTimeout(function () { c.classList.add('is-flip'); }, 450 + i * 380); });
    layer.querySelector('[data-mya-close]').focus();
  }
  function chTitle(k) { for (var i = 0; i < st.chapters.length; i++) if (st.chapters[i].k === k) return st.chapters[i].title; return k; }
  function close() { layer.hidden = true; var b = app.querySelector('[data-mya-open]'); if (b) b.focus(); }

  app.addEventListener('click', function (ev) {
    var b;
    if ((b = ev.target.closest('[data-mya-open]'))) {
      b.disabled = true;
      api({ action: 'ouvrir' }).then(function (r) {
        if (!r.ok) { msg(r.error); b.disabled = false; return; }
        st = r.state; render(); reveal(r.pack, T.packT, r.pages);
      });
    } else if ((b = ev.target.closest('[data-mya-tab]'))) { tab = b.getAttribute('data-mya-tab'); render(); app.querySelector('[data-mya-tab="' + tab + '"]').focus(); }
    else if ((b = ev.target.closest('[data-mya-pg]'))) { page += +b.getAttribute('data-mya-pg'); render(); }
    else if ((b = ev.target.closest('[data-mya-cancel]'))) {
      api({ action: 'annuler', code: b.getAttribute('data-mya-cancel') }).then(function (r) { if (r.ok) { st = r.state; render(); msg(T.cancelled); } else msg(r.error); });
    } else if ((b = ev.target.closest('[data-mya-save]'))) {
      api({ action: 'sauvegarde' }).then(function (r) { var o = app.querySelector('[data-mya-savecode]'); o.innerHTML = r.ok ? '<p class="mya__code">' + esc(r.code) + '</p><p>' + esc(T.keep) + '</p>' : '<p>' + esc(r.error) + '</p>'; });
    } else if ((b = ev.target.closest('[data-mya-do]'))) {
      ev.preventDefault();
      api({ action: 'accepter', code: b.getAttribute('data-mya-do') }).then(function (r) {
        if (!r.ok) { msg(r.error); return; }
        st = r.state; tab = 'album'; render(); reveal([{ pid: r.got, r: (pool[r.got] || {}).r || 'n', new: true }], T.traded, r.pages);
      });
    }
  });
  app.addEventListener('change', function (ev) {
    if (ev.target.matches('[data-mya-chap]')) { chap = ev.target.value; page = 0; render(); app.querySelector('[data-mya-chap]').focus(); }
  });
  app.addEventListener('submit', function (ev) {
    var f = ev.target;
    ev.preventDefault();
    if (f.matches('[data-mya-offer]')) {
      api({ action: 'offrir', give: +f.give.value, want: +f.want.value }).then(function (r) {
        if (!r.ok) { msg(r.error); return; }
        st = r.state; render();
        app.querySelector('[data-mya-newcode]').innerHTML = '<p>' + esc(T.newCode) + '</p><p class="mya__code">' + esc(r.code) + '</p>';
      });
    } else if (f.matches('[data-mya-accept]')) {
      var code = f.code.value;
      api({ action: 'voir', code: code }).then(function (r) {
        var o = f.querySelector('[data-mya-trade]');
        if (!r.ok) { o.innerHTML = '<p>' + esc(r.error) + '</p>'; return; }
        var t = r.trade, can = (st.s[t.want] || 0) > 1;
        o.innerHTML = '<p>' + esc(fmt(T.tradeIs, { g: name(t.give), w: name(t.want) })) + '</p>' + (can ? '<button type="button" class="btn btn--yellow" data-mya-do="' + esc(code) + '">' + esc(T.accept) + '</button>' : '<p>' + esc(T.needDouble) + '</p>');
      });
    } else if (f.matches('[data-mya-restore]')) {
      api({ action: 'restaurer', code: f.code.value }).then(function (r) { if (!r.ok) { msg(r.error); return; } st = r.state; tab = 'album'; render(); msg(T.restored); });
    }
  });
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (es) { if (es[0].isIntersecting) { io.disconnect(); load(); } }, { rootMargin: '400px' });
    io.observe(box);
  } else load();
})();
