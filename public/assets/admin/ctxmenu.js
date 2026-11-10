/* Menu contextuel du back-office (clic droit, touche « Menu » ou Maj+F10 ; appui long sur tablette).
   Le menu s'adapte à ce qui est sous la souris :
   - une fiche (ligne d'une liste, lien vers une fiche, ou la fiche ouverte) : ouvrir, aperçu, voir sur
     le site, copier les liens ou le numéro, valider, mettre à la corbeille ;
   - une photo (vignette de la médiathèque, image d'une page) : l'ouvrir dans la médiathèque, voir
     l'original, copier son lien, où elle est utilisée, la supprimer (si elle n'est utilisée nulle part) ;
   - un lien : nouvel onglet, copier le lien ;
   - un texte sélectionné : copier, le chercher dans le back-office ou sur le site ;
   - partout : nouvelle fiche, recherche, médiathèque, qualité, haut de page.
   Les droits et l'état (validée, à la corbeille, utilisée…) viennent du serveur (/admin/api/menu).
   Ctrl + clic droit (ou dans une zone de saisie) : le menu habituel du navigateur. */
(function () {
  'use strict';
  const BO = window.BO;
  if (!BO) return;
  const { esc } = BO;
  let menu = null;
  let lastFocus = null;

  const isEditable = el => !!el.closest('input, textarea, select, [contenteditable=""], [contenteditable="true"], .wys');

  /* ------------------------------------------------------------------ ce qu'il y a sous la souris */
  function ficheId(el) {
    const host = el.closest('[data-ctx-fiche]');
    if (host) return parseInt(host.dataset.ctxFiche, 10) || null;
    const a = el.closest('a[href]');
    const m = a && a.getAttribute('href').match(/^\/admin\/fiche\/(\d+)(?:[/?#]|$)/);
    if (m) return parseInt(m[1], 10);
    const tr = el.closest('tr[data-href]');
    const t = tr && tr.dataset.href.match(/^\/admin\/fiche\/(\d+)/);
    return t ? parseInt(t[1], 10) : null;
  }
  function pageFicheId() {
    const m = location.pathname.match(/^\/admin\/fiche\/(\d+)\/?$/);
    return m ? parseInt(m[1], 10) : null;
  }
  function mediaRel(el) {
    const card = el.closest('[data-media]');
    if (card) {
      try { return JSON.parse(card.dataset.media).file || null; } catch (e) { /* vignette illisible */ }
    }
    const img = el.closest('img');
    const src = img ? (img.getAttribute('src') || '') : '';
    const m = src.match(/\/m(?:edia|\/[a-z0-9-]+)\/(?:\d+|full)\/(.+?)(?:\.webp)?(?:\?.*)?$/);
    return m ? decodeURIComponent(m[1]) : null;
  }

  /* ------------------------------------------------------------------ actions */
  const copy = async (text, what) => {
    try { await navigator.clipboard.writeText(text); BO.toast(what + ' copié'); } catch (e) { BO.toast('Copie impossible : ' + text, true); }
  };
  const abs = u => new URL(u, location.origin).href;
  // Action qui modifie (valider, corbeille) : formulaire envoyé, comme les boutons de la fiche.
  const postForm = (url, fields = {}) => {
    const f = document.createElement('form');
    f.method = 'post';
    f.action = url;
    f.hidden = true;
    Object.entries(Object.assign({ _csrf: BO.CSRF }, fields)).forEach(([k, v]) => {
      const i = document.createElement('input');
      i.type = 'hidden'; i.name = k; i.value = v;
      f.appendChild(i);
    });
    document.body.appendChild(f);
    f.submit();
  };
  const info = async q => {
    try {
      const r = await fetch('/admin/api/menu?' + q, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
      return r.ok ? r.json() : null;
    } catch (e) { return null; }
  };

  function ficheItems(f, here) {
    const id = f.id;
    const items = [{ head: (f.type === 'match' ? 'Match' : f.type === 'personne' ? 'Personne' : 'Fiche') + ' n° ' + id + (f.title ? ' · ' + f.title : '') }];
    if (!here) {
      items.push({ label: 'Ouvrir la fiche', icon: '✎', href: '/admin/fiche/' + id });
      items.push({ label: 'Ouvrir dans un nouvel onglet', icon: '↗', href: '/admin/fiche/' + id, blank: true });
    }
    items.push({ label: 'Aperçu de la fiche', icon: '👁', href: '/admin/fiche/' + id + '/apercu', blank: true });
    if (f.public) items.push({ label: 'Voir sur le site', icon: '🌐', href: f.public, blank: true });
    items.push({ sep: true });
    if (f.public) items.push({ label: 'Copier le lien du site', icon: '⧉', run: () => copy(abs(f.public), 'Lien du site') });
    items.push({ label: 'Copier le lien du back-office', icon: '⧉', run: () => copy(abs('/admin/fiche/' + id), 'Lien du back-office') });
    items.push({ label: 'Copier le n° de fiche (' + id + ')', icon: '#', run: () => copy(String(id), 'Numéro') });
    if (!f.trashed) {
      items.push({ sep: true });
      if (!f.validated) {
        items.push({ label: 'Valider cette fiche', icon: '✓', run: async () => {
          if (await BO.confirm('Valider cette fiche ?', '« ' + (f.title || 'Fiche ' + id) + ' » : vous confirmez l’avoir relue et contrôlée.', 'Valider')) postForm('/admin/fiche/' + id + '/valider', { valider: '1' });
        } });
      } else {
        items.push({ label: 'Validée' + (f.validated_by ? ' par ' + f.validated_by : ''), icon: '✓', disabled: true });
      }
      items.push({ label: 'Mettre à la corbeille…', icon: '🗑', danger: true, run: async () => {
        if (await BO.confirm('Mettre cette fiche à la corbeille ?', '« ' + (f.title || 'Fiche ' + id) + ' » ne sera plus visible sur le site. On peut l’en sortir depuis la corbeille.', 'Mettre à la corbeille', true)) postForm('/admin/fiche/' + id + '/corbeille');
      } });
    } else {
      items.push({ label: 'Fiche à la corbeille', icon: '🗑', href: '/admin/corbeille' });
    }
    return items;
  }

  function mediaItems(m) {
    const items = [{ head: 'Photo · ' + m.name }];
    items.push({ label: 'Ouvrir dans la médiathèque', icon: '✎', href: '/admin/medias?q=' + encodeURIComponent(m.name) + '&ouvrir=' + encodeURIComponent(m.file) });
    items.push({ label: 'Voir l’original', icon: '↗', href: m.full, blank: true });
    items.push({ label: 'Copier le lien de l’image', icon: '⧉', run: () => copy(abs(m.full), 'Lien de l’image') });
    items.push({ label: 'Copier le nom du fichier', icon: '⧉', run: () => copy(m.file, 'Nom du fichier') });
    items.push({ sep: true });
    if (m.used_count) {
      items.push({ head: 'Utilisée dans ' + m.used_count + ' contenu' + (m.used_count > 1 ? 's' : '') });
      m.used.forEach(u => items.push({ label: u.label, icon: '→', href: u.url }));
      items.push({ label: 'Supprimer : impossible tant qu’elle est utilisée', icon: '🗑', disabled: true });
    } else {
      items.push({ label: 'Utilisée nulle part', icon: '·', disabled: true });
      if (m.can_delete) {
        items.push({ label: 'Supprimer la photo…', icon: '🗑', danger: true, run: async () => {
          if (!(await BO.confirm('Supprimer cette photo ?', '« ' + m.name + ' » n’est utilisée nulle part. Le fichier et ses vignettes seront effacés définitivement (noté dans le Journal).', 'Supprimer', true))) return;
          const r = await BO.post('/admin/medias/supprimer', { file: m.file });
          if (!r.ok) { BO.toast(r.error || 'Suppression impossible', true); return; }
          BO.toast(r.message || 'Photo supprimée');
          setTimeout(() => location.reload(), 600);
        } });
      }
    }
    return items;
  }

  function linkItems(a) {
    const href = a.getAttribute('href');
    if (!href || href.startsWith('#') || href.startsWith('javascript:')) return [];
    return [
      { head: 'Lien' },
      { label: 'Ouvrir dans un nouvel onglet', icon: '↗', href, blank: true },
      { label: 'Copier le lien', icon: '⧉', run: () => copy(abs(href), 'Lien') },
    ];
  }

  function textItems(text) {
    const short = text.length > 40 ? text.slice(0, 40) + '…' : text;
    return [
      { head: 'Texte sélectionné' },
      { label: 'Copier', icon: '⧉', run: () => copy(text, 'Texte') },
      { label: 'Chercher « ' + short + ' » dans le back-office', icon: '🔍', run: () => BO.quickSearch && BO.quickSearch(text) },
      { label: 'Chercher sur le site', icon: '🌐', href: '/recherche/?q=' + encodeURIComponent(text), blank: true },
    ];
  }

  function pageItems() {
    return [
      { head: 'Raccourcis' },
      { label: 'Rechercher… (Ctrl+K)', icon: '🔍', run: () => BO.quickSearch && BO.quickSearch('') },
      { label: 'Nouvelle fiche match', icon: '+', href: '/admin/fiche/nouvelle/match' },
      { label: 'Nouvelle fiche personne', icon: '+', href: '/admin/fiche/nouvelle/personne' },
      { label: 'Nouvel article', icon: '+', href: '/admin/fiche/nouvelle/article' },
      { label: 'Médiathèque', icon: '▣', href: '/admin/medias' },
      { label: 'Qualité', icon: '⚑', href: '/admin/qualite' },
      { label: 'Copier l’adresse de cette page', icon: '⧉', run: () => copy(location.href, 'Adresse') },
      { label: 'Haut de la page', icon: '↑', run: () => window.scrollTo({ top: 0, behavior: 'smooth' }) },
    ];
  }

  /* ------------------------------------------------------------------ affichage */
  function close() {
    if (!menu) return;
    menu.remove();
    menu = null;
    if (lastFocus && document.contains(lastFocus)) lastFocus.focus({ preventScroll: true });
  }

  function render(groups, x, y) {
    close();
    lastFocus = document.activeElement;
    menu = document.createElement('div');
    menu.className = 'ctxm';
    menu.setAttribute('role', 'menu');
    const html = [];
    groups.filter(g => g.length).forEach((g, gi) => {
      if (gi) html.push('<div class="ctxm__sep" role="separator"></div>');
      g.forEach((it, i) => {
        if (it.sep) { html.push('<div class="ctxm__sep" role="separator"></div>'); return; }
        if (it.head) { html.push('<div class="ctxm__head">' + esc(it.head) + '</div>'); return; }
        if (it.loading) { html.push('<div class="ctxm__head ctxm__loading">' + esc(it.loading) + '</div>'); return; }
        const cls = 'ctxm__it' + (it.danger ? ' is-danger' : '') + (it.disabled ? ' is-off' : '');
        const icon = '<span class="ctxm__ic" aria-hidden="true">' + esc(it.icon || '') + '</span>';
        const id = gi + '-' + i;
        html.push(it.href && !it.disabled
          ? '<a class="' + cls + '" role="menuitem" tabindex="-1" href="' + esc(it.href) + '"' + (it.blank ? ' target="_blank" rel="noopener"' : '') + ' data-i="' + id + '">' + icon + '<span>' + esc(it.label) + '</span></a>'
          : '<button type="button" class="' + cls + '" role="menuitem" tabindex="-1"' + (it.disabled ? ' aria-disabled="true"' : '') + ' data-i="' + id + '">' + icon + '<span>' + esc(it.label) + '</span></button>');
      });
    });
    menu.innerHTML = html.join('');
    document.body.appendChild(menu);
    menu.querySelectorAll('[data-i]').forEach(el => {
      const [gi, i] = el.dataset.i.split('-').map(Number);
      const it = groups.filter(g => g.length)[gi][i];
      el.addEventListener('click', e => {
        if (it.disabled) { e.preventDefault(); return; }
        if (it.run) { e.preventDefault(); close(); it.run(); return; }
        close();
      });
    });
    // Dans l'écran, jamais coupé par un bord.
    const r = menu.getBoundingClientRect();
    const left = Math.max(6, Math.min(x, window.innerWidth - r.width - 6));
    const top = Math.max(6, Math.min(y, window.innerHeight - r.height - 6));
    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
    const first = menu.querySelector('.ctxm__it:not(.is-off)');
    if (first) first.focus({ preventScroll: true });
  }

  async function open(e, target) {
    const sel = String(window.getSelection ? window.getSelection() : '').trim();
    const groups = [];
    const fid = ficheId(target);
    const rel = fid ? null : mediaRel(target);
    const link = !fid && !rel ? target.closest('a[href]') : null;
    const here = pageFicheId();
    const x = e.clientX || (target.getBoundingClientRect().left + 10);
    const y = e.clientY || (target.getBoundingClientRect().top + 10);
    if (sel) groups.push(textItems(sel));
    if (fid || rel) groups.push([{ loading: 'Chargement…' }]);
    if (link) groups.push(linkItems(link));
    if (here && !fid) groups.push([{ loading: 'Cette fiche…' }]);
    groups.push(pageItems());
    render(groups, x, y);
    // Informations du serveur (droits, état), puis menu complet au même endroit.
    const [main, page] = await Promise.all([
      fid ? info('fiche=' + fid) : rel ? info('media=' + encodeURIComponent(rel)) : null,
      here && !fid ? info('fiche=' + here) : null,
    ]);
    if (!menu) return;
    const full = [];
    if (sel) full.push(textItems(sel));
    if (main) full.push(main.kind === 'media' ? mediaItems(main) : ficheItems(main, main.id === here));
    else if (fid || rel) full.push([{ head: fid ? 'Fiche n° ' + fid + ' introuvable' : 'Photo hors médiathèque' }]);
    if (link) full.push(linkItems(link));
    if (page) full.push([{ head: 'Cette fiche' }].concat(ficheItems(page, true).slice(1)));
    full.push(pageItems());
    render(full, x, y);
  }

  document.addEventListener('contextmenu', e => {
    // Échappatoires : Ctrl (ou ⌘) + clic droit, zones de saisie (correcteur du navigateur, coller…).
    if (e.ctrlKey || e.metaKey || isEditable(e.target) || !e.target.closest('.bo, body')) return;
    if (e.target.closest('.ctxm')) { e.preventDefault(); return; }
    e.preventDefault();
    open(e, e.target);
  });
  document.addEventListener('click', e => { if (menu && !e.target.closest('.ctxm')) close(); }, true);
  window.addEventListener('scroll', close, { passive: true });
  window.addEventListener('resize', close);
  window.addEventListener('blur', close);
  document.addEventListener('keydown', e => {
    if (!menu) return;
    const items = Array.from(menu.querySelectorAll('.ctxm__it:not(.is-off)'));
    const i = items.indexOf(document.activeElement);
    if (e.key === 'Escape') { e.preventDefault(); close(); }
    else if (e.key === 'ArrowDown') { e.preventDefault(); items[(i + 1) % items.length]?.focus(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); items[(i - 1 + items.length) % items.length]?.focus(); }
    else if (e.key === 'Home') { e.preventDefault(); items[0]?.focus(); }
    else if (e.key === 'End') { e.preventDefault(); items[items.length - 1]?.focus(); }
    else if (e.key === 'Tab') { e.preventDefault(); close(); }
  });
})();
