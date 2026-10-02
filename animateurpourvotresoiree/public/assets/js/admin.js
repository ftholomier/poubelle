/* Back-office : menu mobile, recherche globale (Ctrl K), notifications, actions groupées, outils divers. */
(() => {
  'use strict';
  const APVS = window.APVS || {};
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const esc = APVS.esc || ((s) => String(s ?? ''));
  const ADMIN = (APVS.cfg && APVS.cfg.admin) || '/gestion/';
  const getJSON = async (url) => {
    const r = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' });
    if (r.status === 401) { location.href = ADMIN + 'login'; throw new Error('Session expirée'); }
    return r.json();
  };

  /* Menu latéral (mobile) */
  const burger = $('[data-adm-burger]');
  if (burger) {
    burger.addEventListener('click', () => document.body.classList.toggle('side-open'));
    document.addEventListener('click', (e) => { if (document.body.classList.contains('side-open') && !e.target.closest('.adm-side') && !e.target.closest('[data-adm-burger]')) document.body.classList.remove('side-open'); });
  }

  /* Menus déroulants (notifications, compte) */
  const closeDrops = (except) => $$('.adm-panel').forEach((p) => { if (p !== except) p.classList.add('hidden'); });
  const notifBtn = $('[data-notif-toggle]');
  const notifPanel = $('[data-notif-panel]');
  let notifLoaded = false;
  const loadNotifs = async () => {
    try {
      const d = await getJSON(ADMIN + 'notifications?format=json');
      $('[data-notif-list]').innerHTML = d.items.length ? d.items.map((n) => '<a class="notif lv-' + esc(n.level) + (n.read ? '' : ' unread') + '" href="' + esc(n.link || (ADMIN + 'notifications')) + '"><i></i><span><b>' + esc(n.title) + '</b>' + (n.body ? esc(n.body) + '<br>' : '') + '<small>' + esc(n.ago) + '</small></span></a>').join('') : '<p class="muted small" style="padding:14px">Aucune notification.</p>';
      notifLoaded = true;
    } catch (e) { /* hors ligne */ }
  };
  if (notifBtn) {
    notifBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      const open = notifPanel.classList.contains('hidden');
      closeDrops(notifPanel);
      notifPanel.classList.toggle('hidden', !open);
      notifBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open && !notifLoaded) loadNotifs();
    });
  }
  const menuBtn = $('[data-menu-toggle]');
  const menuPanel = $('[data-menu-panel]');
  if (menuBtn) menuBtn.addEventListener('click', (e) => { e.stopPropagation(); const open = menuPanel.classList.contains('hidden'); closeDrops(menuPanel); menuPanel.classList.toggle('hidden', !open); menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); });
  document.addEventListener('click', (e) => { if (!e.target.closest('.adm-drop')) closeDrops(null); });

  /* Rafraîchissement du compteur de notifications (toutes les 2 minutes) */
  setInterval(async () => {
    if (document.hidden) return;
    try {
      const d = await getJSON(ADMIN + 'notifications?format=json&count=1');
      let badge = $('[data-notif-count]');
      if (d.unread > 0) {
        if (!badge && notifBtn) { badge = document.createElement('span'); badge.className = 'dot-count'; badge.dataset.notifCount = ''; notifBtn.appendChild(badge); }
        if (badge) badge.textContent = d.unread > 99 ? '99+' : d.unread;
        if (d.unread > Number(sessionStorage.getItem('apvs:unread') || 0)) { notifLoaded = false; APVS.toast && APVS.toast('🔔 Nouvelle notification'); }
      } else if (badge) badge.remove();
      sessionStorage.setItem('apvs:unread', String(d.unread));
    } catch (e) { /* ignoré */ }
  }, 120000);

  /* Palette de recherche globale */
  const pal = $('#palette');
  if (pal) {
    const input = $('input', pal);
    const list = $('.palette-list', pal);
    let timer = null;
    let sel = 0;
    let req = 0;
    const open = () => { pal.showModal(); input.value = ''; list.innerHTML = '<li class="head">Tapez au moins 2 caractères</li>'; input.focus(); };
    $$('[data-palette-open]').forEach((b) => b.addEventListener('click', open));
    document.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); if (pal.open) pal.close(); else open(); }
      if (e.key === '/' && !pal.open && !/INPUT|TEXTAREA|SELECT/.test(document.activeElement.tagName) && !document.activeElement.isContentEditable) { e.preventDefault(); open(); }
    });
    pal.addEventListener('click', (e) => { if (e.target === pal) pal.close(); });
    const paint = () => $$('li[data-i]', list).forEach((li) => li.classList.toggle('sel', Number(li.dataset.i) === sel));
    input.addEventListener('keydown', (e) => {
      const items = $$('li[data-i]', list);
      if (e.key === 'ArrowDown') { e.preventDefault(); sel = Math.min(items.length - 1, sel + 1); paint(); items[sel]?.scrollIntoView({ block: 'nearest' }); }
      if (e.key === 'ArrowUp') { e.preventDefault(); sel = Math.max(0, sel - 1); paint(); items[sel]?.scrollIntoView({ block: 'nearest' }); }
      if (e.key === 'Enter') { e.preventDefault(); const a = items[sel] && $('a', items[sel]); if (a) location.href = a.href; }
    });
    input.addEventListener('input', () => {
      clearTimeout(timer);
      const q = input.value.trim();
      if (q.length < 2) { list.innerHTML = '<li class="head">Tapez au moins 2 caractères</li>'; return; }
      timer = setTimeout(async () => {
        const id = ++req;
        try {
          const d = await getJSON(ADMIN + 'recherche?format=json&q=' + encodeURIComponent(q));
          if (id !== req) return;
          let i = 0;
          let html = '';
          Object.entries(d.groups || {}).forEach(([group, items]) => {
            if (!items.length) return;
            html += '<li class="head">' + esc(group) + '</li>';
            items.forEach((it) => { html += '<li data-i="' + (i++) + '"><a href="' + esc(it.url) + '"><span>' + (it.icon || '•') + '</span><span>' + esc(it.title) + '<small>' + esc(it.sub || '') + '</small></span><span class="kind">' + esc(it.kind || '') + '</span></a></li>'; });
          });
          list.innerHTML = html || '<li class="head">Aucun résultat</li>';
          sel = 0;
          paint();
        } catch (e) { list.innerHTML = '<li class="head">Recherche indisponible</li>'; }
      }, 180);
    });
  }

  /* Cases « tout sélectionner » et barre d'actions groupées */
  $$('[data-check-all]').forEach((all) => {
    const scope = all.closest('form') || document;
    const boxes = () => $$('input[type=checkbox][name="ids[]"]', scope);
    const upd = () => {
      const n = boxes().filter((b) => b.checked).length;
      $$('[data-bulk-count]', scope).forEach((el) => { el.textContent = n; });
      $$('[data-bulk-needs]', scope).forEach((el) => { el.disabled = n === 0; });
    };
    all.addEventListener('change', () => { boxes().forEach((b) => { b.checked = all.checked; }); upd(); });
    scope.addEventListener('change', (e) => { if (e.target.name === 'ids[]') upd(); });
    upd();
  });

  /* Soumission automatique des filtres */
  $$('form[data-autosubmit]').forEach((f) => f.addEventListener('change', (e) => { if (e.target.matches('select, input[type=checkbox], input[type=date]')) f.requestSubmit(); }));

  /* Compteurs SEO (title 60, description 160) avec aperçu Google */
  $$('[data-seo-count]').forEach((el) => {
    const max = Number(el.dataset.seoCount);
    const out = document.createElement('span');
    out.className = 'hint';
    el.insertAdjacentElement('afterend', out);
    const upd = () => {
      const n = el.value.length;
      out.textContent = n + ' / ' + max + ' caractères' + (n > max ? ' — trop long, risque de coupure' : '');
      out.className = 'hint ' + (n === 0 ? '' : n > max ? 'count-bad' : n > max * 0.85 ? 'count-ok' : 'count-warn');
      const prev = el.dataset.serp && $(el.dataset.serp);
      if (prev) { const t = $('.' + (el.dataset.serpPart || 't'), prev); if (t) t.textContent = el.value || t.dataset.default || ''; }
    };
    el.addEventListener('input', upd);
    upd();
  });

  /* Génération automatique du slug à partir du titre */
  $$('[data-slug-from]').forEach((slug) => {
    const src = $(slug.dataset.slugFrom);
    if (!src) return;
    let touched = slug.value !== '';
    slug.addEventListener('input', () => { touched = true; });
    src.addEventListener('input', () => {
      if (touched) return;
      slug.value = src.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/['’]/g, '-').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
    });
  });

  /* Champs secrets : afficher / masquer */
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-reveal]');
    if (!b) return;
    const input = $(b.dataset.reveal);
    if (input) { input.type = input.type === 'password' ? 'text' : 'password'; b.textContent = input.type === 'password' ? 'Afficher' : 'Masquer'; }
  });

  /* Insertion de variables {{x}} dans le champ ciblé */
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-insert]');
    if (!b) return;
    const target = $(b.dataset.target);
    if (!target) return;
    const txt = b.dataset.insert;
    if (target.editor) { target.editor.area.focus(); document.execCommand('insertText', false, txt); target.editor.get(); return; }
    const s = target.selectionStart ?? target.value.length;
    target.value = target.value.slice(0, s) + txt + target.value.slice(target.selectionEnd ?? s);
    target.focus();
    target.selectionStart = target.selectionEnd = s + txt.length;
    target.dispatchEvent(new Event('input', { bubbles: true }));
  });

  /* Boutons d'action en arrière-plan : <button data-ajax-action="/url" data-payload='{}'> */
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('[data-ajax-action]');
    if (!b) return;
    e.preventDefault();
    if (b.dataset.confirmText && !confirm(b.dataset.confirmText)) return;
    b.classList.add('is-loading');
    try {
      let payload = {};
      try { payload = JSON.parse(b.dataset.payload || '{}'); } catch (er) { payload = {}; }
      if (b.dataset.fields) b.dataset.fields.split(',').forEach((sel) => { const el = $(sel.trim()); if (el) payload[el.name] = el.type === 'checkbox' ? (el.checked ? '1' : '') : el.value; });
      const d = await APVS.post(b.dataset.ajaxAction, payload);
      const out = b.dataset.output ? $(b.dataset.output) : null;
      if (out) out.innerHTML = d.html || ('<div class="alert alert-' + (d.ok === false ? 'error' : 'success') + '">' + esc(d.message || 'OK') + '</div>');
      else APVS.toast && APVS.toast(d.message || 'Fait ✔');
      if (d.reload) setTimeout(() => location.reload(), 600);
      if (d.redirect) location.href = d.redirect;
    } catch (err) {
      const out = b.dataset.output ? $(b.dataset.output) : null;
      if (out) out.innerHTML = '<div class="alert alert-error">' + esc(err.message) + '</div>';
      else APVS.toast && APVS.toast(err.message || 'Erreur');
    }
    b.classList.remove('is-loading');
  });

  /* Audience d'une campagne (recalcul à chaque changement de segment) */
  const seg = $('[data-segment]');
  if (seg) {
    const out = $('[data-audience]');
    let t = null;
    const upd = () => {
      clearTimeout(t);
      t = setTimeout(async () => {
        const fd = new FormData(seg.closest('form'));
        const data = {};
        fd.forEach((v, k) => { if (k.startsWith('segment')) { const key = k.replace(/^segment\[([a-z_]+)\](\[\])?$/, '$1'); if (k.endsWith('[]')) (data[key] = data[key] || []).push(v); else data[key] = v; } });
        try { const d = await APVS.post(ADMIN + 'emailing/audience', { segment: data }); out.textContent = d.count; } catch (e) { out.textContent = '?'; }
      }, 300);
    };
    seg.addEventListener('change', upd);
    upd();
  }

  /* QR code de la double authentification */
  const qr = $('[data-qr]');
  if (qr && window.qrcode) {
    const q = window.qrcode(0, 'M');
    q.addData(qr.dataset.qr);
    q.make();
    qr.innerHTML = q.createSvgTag({ cellSize: 4, margin: 2, scalable: true });
  }

  /* Exécution pas à pas (import de l'ancienne base, traitements par lots) */
  $$('[data-runner]').forEach((box) => {
    const btn = $('[data-runner-start]', box);
    if (!btn) return;
    btn.addEventListener('click', async () => {
      if (btn.dataset.confirmText && !confirm(btn.dataset.confirmText)) return;
      btn.disabled = true;
      const steps = $$('li[data-step]', box);
      const log = $('[data-runner-log]', box);
      const bar = $('.progress i', box);
      const say = (t) => { if (log) { log.textContent += t + '\n'; log.scrollTop = log.scrollHeight; } };
      for (let i = 0; i < steps.length; i++) {
        const li = steps[i];
        if (li.dataset.skip === '1') continue;
        li.className = 'run';
        let offset = 0;
        let done = false;
        while (!done) {
          try {
            const d = await APVS.post(box.dataset.runner, { step: li.dataset.step, offset });
            $('small', li).textContent = d.message || '';
            say('[' + li.dataset.step + '] ' + (d.message || ''));
            offset = d.offset || 0;
            done = !!d.done;
            if (bar && d.total) bar.style.width = Math.round(((i + (done ? 1 : (offset / d.total))) / steps.length) * 100) + '%';
          } catch (e) {
            li.className = 'err';
            $('small', li).textContent = e.message;
            say('ERREUR ' + li.dataset.step + ' : ' + e.message);
            btn.disabled = false;
            return;
          }
        }
        li.className = 'done';
      }
      if (bar) bar.style.width = '100%';
      say('Terminé ✔');
      APVS.toast && APVS.toast('Traitement terminé ✔');
      btn.disabled = false;
    });
  });

  /* Avertissement si on quitte une page avec des modifications non enregistrées */
  $$('form[data-dirty-check]').forEach((f) => {
    let dirty = false;
    f.addEventListener('input', () => { dirty = true; });
    f.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', (e) => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  });

  /* Aperçu d'email dans un cadre isolé */
  $$('iframe[data-srcdoc-from]').forEach((fr) => {
    const src = $(fr.dataset.srcdocFrom);
    if (src) fr.srcdoc = src.tagName === 'TEMPLATE' ? src.innerHTML : src.textContent;
  });
})();
