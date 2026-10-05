/* Médiathèque du back-office : envoi, fiche d'un média, sélection et crédit en lot. */
(function () {
  'use strict';
  const BO = window.BO;
  if (!BO) return;
  const { $, $$, esc } = BO;
  const grid = $('[data-media-grid]');
  if (!grid) return;
  let conf = {};
  try { conf = JSON.parse($('#media-conf')?.textContent || '{}'); } catch (e) { conf = {}; }

  /* ------------------------------------------------------------ envoi de fichiers */
  const progress = $('[data-media-progress]');
  async function upload(files, replace) {
    const list = [...files];
    if (!list.length) return;
    const done = [];
    for (let i = 0; i < list.length; i++) {
      const f = list[i];
      if (progress) { progress.hidden = false; progress.textContent = 'Envoi ' + (i + 1) + ' / ' + list.length + ' : ' + f.name + '…'; }
      const fd = new FormData();
      fd.append('file', f);
      if (replace) fd.append('replace', replace);
      const r = await BO.post('/admin/medias/envoi', fd, false);
      if (r.ok) done.push(r.file); else BO.toast(r.error || ('Échec : ' + f.name), true);
    }
    if (progress) progress.textContent = done.length + ' fichier(s) envoyé(s).';
    if (!done.length) return;
    if (replace) {
      try { sessionStorage.setItem('bo-toast', 'Fichier remplacé : les vignettes sont régénérées.'); } catch (e) { /* */ }
      location.reload();
      return;
    }
    describe(done);
  }

  /** Après un envoi : légende et crédit des nouveaux fichiers (le crédit est demandé d'emblée). */
  function describe(rels) {
    const { m } = modal(
      '<h2 class="modal__t">' + rels.length + ' fichier' + (rels.length > 1 ? 's' : '') + ' ajouté' + (rels.length > 1 ? 's' : '') + '</h2>'
      + '<div class="thumbs" style="grid-template-columns:repeat(auto-fill,minmax(90px,1fr))">' + rels.slice(0, 12).map(r => '<img src="' + esc(BO.thumb(r, 160)) + '" alt="" style="aspect-ratio:1;object-fit:cover;border:2px solid var(--navy)">').join('') + '</div>'
      + '<p class="small muted" style="margin:0">Indiquez d’où viennent ces documents : le crédit est affiché sous chaque image sur le site.</p>'
      + '<form class="stack" style="gap:12px">'
      + '<div class="f"><span class="f__k">Crédit <b>*</b></span><input type="text" name="credit" maxlength="250" required placeholder="Photographe, journal, collection…"></div>'
      + '<div class="f"><span class="f__k">Légende</span><input type="text" name="caption" maxlength="500" placeholder="Qui, quoi, où, quand…"></div>'
      + '<div class="f"><span class="f__k">Droits / licence</span><input type="text" name="rights" maxlength="250" placeholder="Tous droits réservés, cession du donateur…"></div>'
      + '<div class="row row--end"><button type="button" class="btn" data-later>Compléter plus tard</button><button type="submit" class="btn btn--navy">Enregistrer</button></div></form>');
    const go = () => { location.href = '/admin/medias?tri=recent'; };
    $('[data-later]', m).addEventListener('click', () => { try { sessionStorage.setItem('bo-toast', rels.length + ' fichier(s) ajouté(s), à compléter (filtre « Sans crédit »).'); } catch (e) { /* */ } go(); });
    $('form', m).addEventListener('submit', async e => {
      e.preventDefault();
      const f = e.target;
      if (!f.credit.value.trim()) { f.credit.focus(); return; }
      const r = await BO.post('/admin/medias/enregistrer', { files: rels, credit: f.credit.value, caption: f.caption.value, rights: f.rights.value, overwrite: true });
      if (!r.ok) { BO.toast(r.error || 'Enregistrement impossible', true); return; }
      try { sessionStorage.setItem('bo-toast', rels.length + ' fichier(s) ajouté(s) et décrit(s).'); } catch (er) { /* */ }
      go();
    });
    setTimeout(() => $('[name=credit]', m).focus(), 30);
  }
  const drop = $('[data-media-drop]');
  $('[data-media-up]')?.addEventListener('change', e => upload(e.target.files));
  if (drop) {
    drop.addEventListener('dragover', e => { e.preventDefault(); drop.classList.add('is-over'); });
    drop.addEventListener('dragleave', () => drop.classList.remove('is-over'));
    drop.addEventListener('drop', e => { e.preventDefault(); drop.classList.remove('is-over'); upload(e.dataTransfer.files); });
    drop.addEventListener('click', e => { if (!e.target.closest('label,input')) $('[data-media-up]')?.click(); });
    drop.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $('[data-media-up]')?.click(); } });
  }

  /* ------------------------------------------------------------ sélection */
  const bulk = $('[data-mbulk]'), count = $('[data-mbulk-count]');
  const boxes = () => $$('[data-msel]', grid);
  const selected = () => boxes().filter(b => b.checked).map(b => b.value);
  const sync = () => {
    const n = selected().length;
    bulk.hidden = n === 0;
    count.textContent = n + ' sélectionné' + (n > 1 ? 's' : '');
    boxes().forEach(b => b.closest('.mcardx').style.boxShadow = b.checked ? '0 0 0 3px var(--yellow)' : '');
  };
  grid.addEventListener('change', e => { if (e.target.matches('[data-msel]')) sync(); });
  $('[data-msel-page]')?.addEventListener('click', () => { const all = boxes().every(b => b.checked); boxes().forEach(b => (b.checked = !all)); sync(); });
  $('[data-mbulk-none]')?.addEventListener('click', () => { boxes().forEach(b => (b.checked = false)); sync(); });

  function modal(html, wide) {
    const m = document.createElement('div');
    m.className = 'modal';
    m.innerHTML = '<div class="modal__box' + (wide ? ' modal__box--wide' : '') + '" role="dialog" aria-modal="true" style="max-height:92vh;overflow:auto">' + html + '</div>';
    const close = () => { m.remove(); document.removeEventListener('keydown', key); };
    const key = e => { if (e.key === 'Escape') close(); };
    m.addEventListener('click', e => { if (e.target === m || e.target.closest('[data-close]')) close(); });
    document.addEventListener('keydown', key);
    document.body.appendChild(m);
    BO.init(m);
    return { m, close };
  }

  $('[data-mbulk-edit]')?.addEventListener('click', () => {
    const files = selected();
    const { m, close } = modal(
      '<h2 class="modal__t">' + files.length + ' média' + (files.length > 1 ? 's' : '') + '</h2>'
      + '<p class="small muted" style="margin:0">Les champs remplis ci-dessous sont appliqués à toute la sélection. Les champs laissés vides ne changent rien.</p>'
      + '<form class="stack" style="gap:12px" data-bulk-media>'
      + '<div class="f"><span class="f__k">Crédit</span><input type="text" name="credit" maxlength="250" placeholder="Collection Sochaux Rétro, L’Est Républicain…"></div>'
      + '<div class="f"><span class="f__k">Droits / licence</span><input type="text" name="rights" maxlength="250" placeholder="Tous droits réservés, CC BY-SA…"></div>'
      + '<div class="f"><span class="f__k">Légende</span><input type="text" name="caption" maxlength="500"></div>'
      + '<div class="f"><span class="f__k">Date ou époque</span><input type="text" name="date_text" maxlength="120" placeholder="Saison 1987-1988"></div>'
      + '<label class="toggle"><input type="checkbox" name="overwrite"><span class="toggle__box"></span><span>Remplacer aussi les valeurs déjà saisies</span></label>'
      + '<div class="row row--end"><button type="button" class="btn" data-close>Annuler</button><button type="submit" class="btn btn--navy">Appliquer</button></div></form>');
    $('form', m).addEventListener('submit', async e => {
      e.preventDefault();
      const f = e.target;
      const r = await BO.post('/admin/medias/enregistrer', { files, credit: f.credit.value, rights: f.rights.value, caption: f.caption.value, date_text: f.date_text.value, overwrite: f.overwrite.checked });
      if (!r.ok) { BO.toast(r.error || 'Enregistrement impossible', true); return; }
      close();
      BO.toast(r.message);
      setTimeout(() => location.reload(), 500);
    });
    setTimeout(() => $('[name=credit]', m).focus(), 30);
  });

  /* ------------------------------------------------------------ fiche d'un média */
  const field = (name, label, value, opts = {}) => '<div class="f' + (opts.full ? ' f--full' : '') + '"><span class="f__k">' + esc(label) + (opts.hint ? ' <i>' + esc(opts.hint) + '</i>' : '') + '</span><input type="text" name="' + name + '" value="' + esc(value) + '" maxlength="' + (opts.max || 300) + '"' + (opts.ph ? ' placeholder="' + esc(opts.ph) + '"' : '') + '></div>';
  grid.addEventListener('click', e => {
    const b = e.target.closest('[data-medit]');
    if (!b) return;
    const card = b.closest('[data-media]');
    const it = JSON.parse(card.dataset.media);
    const used = it.used.length
      ? '<ul style="margin:0;padding-left:18px;font-size:15px">' + it.used.map(u => '<li><a href="' + esc(u.url) + '" target="_blank" rel="noopener">' + esc(u.label) + '</a>' + (u.status && u.status !== 'publie' ? ' <span class="xs muted">(' + esc(u.status) + ')</span>' : '') + '</li>').join('') + (it.used_count > it.used.length ? '<li class="muted">… et ' + (it.used_count - it.used.length) + ' autre(s)</li>' : '') + '</ul>'
      : '<p class="small muted" style="margin:0">Ce média n’est utilisé dans aucune fiche ni aucun contenu éditorial.</p>';
    const { m, close } = modal(
      '<div class="modal__head"><h2 class="modal__t" style="font-size:20px;overflow-wrap:anywhere">' + esc(it.name) + '</h2><span class="grow"></span><button type="button" class="btn btn--sm" data-close>Fermer</button></div>'
      + '<div class="modal__body"><div class="cols" style="align-items:start">'
      + '<div class="stack" style="gap:10px">'
      + (it.pdf ? '<a class="btn" href="' + esc(it.full) + '" target="_blank" rel="noopener">Ouvrir le PDF ↗</a>' : '<a href="' + esc(it.full) + '" target="_blank" rel="noopener" title="Ouvrir l’original"><img src="' + esc(it.large) + '" alt="" style="width:100%;max-height:52vh;object-fit:contain;background:var(--sand);border:2px solid var(--navy)"></a>')
      + '<span class="xs muted">' + [it.width && it.height ? it.width + ' × ' + it.height + ' px' : '', it.size, it.added ? 'ajouté le ' + new Date(it.added).toLocaleDateString('fr-FR') : '', it.source].filter(Boolean).map(esc).join(' · ') + '</span>'
      + (it.raw && it.raw !== it.caption ? '<div class="alert alert--info small">Légende d’origine (ancien site) : ' + esc(it.raw) + '</div>' : '')
      + '<div class="f"><span class="f__k">Adresse de l’image</span><div class="row"><code class="small" style="overflow-wrap:anywhere;flex:1">' + esc(it.file) + '</code><button type="button" class="btn btn--sm" data-copy="' + esc(location.origin + it.full) + '">Copier le lien</button></div></div>'
      + '<div class="f"><span class="f__k">Utilisé dans</span>' + used + '</div>'
      + (it.pdf ? '' : '<div class="f"><span class="f__k">Murs de photos <a class="xs" href="/admin/murs-photos" target="_blank" rel="noopener">régler ↗</a></span>'
        + (it.wall ? '<span class="small muted">Pas sur les murs : ' + esc(it.wall) + '.</span>' : '<span class="small ok">✓ Peut être tirée au hasard sur les quatre murs de photos.</span>') + '</div>')
      + '</div>'
      + '<form class="stack" style="gap:12px" data-tr-scope>'
      + '<div class="fgrid fgrid--2">'
      + field('caption', 'Légende', it.caption, { full: true, max: 500, ph: 'Qui, quoi, où, quand…' })
      + '<div class="f f--full"><span class="f__k">Légende anglaise <button type="button" class="btn btn--sm btn--ghost" data-tr="caption,alt">Traduire avec Gemini</button></span><input type="text" name="caption_en" value="' + esc(it.caption_en) + '" maxlength="500"></div>'
      + field('credit', 'Crédit', it.credit, { max: 250, ph: 'Photographe, journal, collection…' })
      + field('rights', 'Droits / licence', it.rights, { max: 250, ph: 'Tous droits réservés' })
      + field('alt', 'Texte alternatif', it.alt, { full: true, hint: 'description pour les malvoyants et Google', ph: 'Ex. : L’équipe pose avant la finale au Parc des Princes' })
      + field('alt_en', 'Texte alternatif anglais', it.alt_en, { full: true })
      + field('date_text', 'Date ou époque', it.date_text, { max: 120, ph: 'Mai 1988' })
      + '</div>'
      + (it.pdf ? '' : '<label class="toggle"><input type="checkbox" name="nowall"' + (it.nowall ? ' checked' : '') + '><span class="toggle__box"></span><span>Jamais sur les murs de photos <span class="xs muted">(photo à ne pas montrer au hasard, même bien créditée)</span></span></label>')
      + '<div class="row"><button type="submit" class="btn btn--navy">Enregistrer</button>'
      + (it.pdf ? '' : '<button type="button" class="btn" data-retouch>Recadrer · pivoter' + (it.edit ? ' ✓' : '') + '</button>')
      + '<label class="btn">Remplacer le fichier…<input type="file" hidden data-replace accept="' + (it.pdf ? 'application/pdf' : 'image/jpeg,image/png,image/gif,image/webp') + '"></label>'
      + (conf.canDelete ? '<span class="grow"></span><button type="button" class="btn btn--danger" data-del>Supprimer</button>' : '')
      + '</div><span class="f__help">« Remplacer » garde la même adresse : toutes les fiches qui utilisent l’image afficheront le nouveau fichier.</span></form>'
      + '</div></div>', true);
    const form = $('form', m);
    form.addEventListener('submit', async ev => {
      ev.preventDefault();
      const data = { file: it.file };
      ['caption', 'caption_en', 'credit', 'rights', 'alt', 'alt_en', 'date_text'].forEach(k => { data[k] = form[k].value; });
      if (form.nowall) data.nowall = form.nowall.checked;
      const r = await BO.post('/admin/medias/enregistrer', data);
      if (!r.ok) { BO.toast(r.error || 'Enregistrement impossible', true); return; }
      BO.toast(r.message || 'Enregistré');
      const n = r.item;
      card.dataset.media = JSON.stringify(n);
      const body = $('.mcardx__body', card);
      body.children[1].textContent = n.caption || 'Sans légende';
      body.children[1].className = n.caption ? '' : 'muted';
      body.children[2].innerHTML = n.credit ? '© ' + esc(n.credit) : (n.pdf ? '<span class="muted">Document</span>' : '<span class="ko">⚠ Sans crédit</span>');
      card.classList.toggle('is-bad', !n.credit && !n.pdf);
      close();
    });
    $('[data-replace]', m)?.addEventListener('change', e => { if (e.target.files[0]) upload(e.target.files, it.file); });
    $('[data-retouch]', m)?.addEventListener('click', () => retouch(it, card, close));
    $('[data-del]', m)?.addEventListener('click', async () => {
      if (!(await BO.confirm('Supprimer ce média ?', it.used_count ? 'Il est utilisé dans ' + it.used_count + ' contenu(s) : ces pages afficheront une image manquante.' : 'Le fichier sera effacé définitivement.', 'Supprimer', true))) return;
      const r = await BO.post('/admin/medias/supprimer', { file: it.file, force: true });
      if (!r.ok) { BO.toast(r.error || 'Suppression impossible', true); return; }
      close();
      card.remove();
      BO.toast(r.message || 'Supprimé');
    });
    setTimeout(() => form.caption.focus(), 30);
  });

  /* ------------------------------------------------------------ recadrer / pivoter (non destructif) */
  function retouch(it, card, closeParent) {
    let rotate = (it.edit && it.edit.rotate) || 0;
    let crop = it.edit && it.edit.crop ? it.edit.crop.slice() : [0, 0, 1, 1];
    const { m, close } = modal(
      '<div class="modal__head"><h2 class="modal__t" style="font-size:20px">Recadrer · pivoter</h2><span class="grow"></span>'
      + '<button type="button" class="btn btn--sm" data-rl title="Pivoter à gauche">↺ 90°</button><button type="button" class="btn btn--sm" data-rr title="Pivoter à droite">↻ 90°</button>'
      + '<button type="button" class="btn btn--sm" data-full>Image entière</button><button type="button" class="btn btn--sm" data-reset>Annuler la retouche</button>'
      + '<button type="button" class="btn btn--sm" data-close>Fermer</button><button type="button" class="btn btn--sm btn--navy" data-apply>Appliquer</button></div>'
      + '<div class="modal__body" style="align-items:center"><div class="cropper"><img alt="" draggable="false"><div class="cropper__box"><i data-h="nw"></i><i data-h="ne"></i><i data-h="sw"></i><i data-h="se"></i></div></div>'
      + '<p class="xs muted" style="margin:0">Glissez le cadre ou ses coins. L’original n’est jamais modifié : seules les images affichées sur le site sont recadrées.</p></div>', true);
    const img = $('img', m), box = $('.cropper__box', m), wrap = $('.cropper', m);
    const load = () => { img.src = '/admin/medias/apercu?file=' + encodeURIComponent(it.file) + '&rotate=' + rotate; };
    const draw = () => { box.style.left = crop[0] * 100 + '%'; box.style.top = crop[1] * 100 + '%'; box.style.width = crop[2] * 100 + '%'; box.style.height = crop[3] * 100 + '%'; };
    const turn = dir => {
      const [x, y, w, h] = crop;
      crop = dir > 0 ? [1 - y - h, x, h, w] : [y, 1 - x - w, h, w];
      rotate = (rotate + (dir > 0 ? 90 : 270)) % 360;
      load();
      draw();
    };
    $('[data-rl]', m).addEventListener('click', () => turn(-1));
    $('[data-rr]', m).addEventListener('click', () => turn(1));
    $('[data-full]', m).addEventListener('click', () => { crop = [0, 0, 1, 1]; draw(); });
    let drag = null;
    wrap.addEventListener('pointerdown', e => {
      if (!e.target.closest('.cropper__box')) return;
      e.preventDefault();
      wrap.setPointerCapture(e.pointerId);
      drag = { h: e.target.dataset.h || 'move', x: e.clientX, y: e.clientY, c: crop.slice() };
    });
    wrap.addEventListener('pointermove', e => {
      if (!drag) return;
      const r = img.getBoundingClientRect();
      const dx = (e.clientX - drag.x) / r.width, dy = (e.clientY - drag.y) / r.height;
      let [x, y, w, h] = drag.c;
      const min = 0.05;
      if (drag.h === 'move') {
        x = Math.min(Math.max(0, x + dx), 1 - w);
        y = Math.min(Math.max(0, y + dy), 1 - h);
      } else {
        if (drag.h.includes('w')) { const nx = Math.min(Math.max(0, x + dx), x + w - min); w += x - nx; x = nx; }
        if (drag.h.includes('e')) { w = Math.min(Math.max(min, w + dx), 1 - x); }
        if (drag.h.includes('n')) { const ny = Math.min(Math.max(0, y + dy), y + h - min); h += y - ny; y = ny; }
        if (drag.h.includes('s')) { h = Math.min(Math.max(min, h + dy), 1 - y); }
      }
      crop = [x, y, w, h];
      draw();
    });
    const end = () => { drag = null; };
    wrap.addEventListener('pointerup', end);
    wrap.addEventListener('pointercancel', end);
    const send = async edit => {
      const r = await BO.post('/admin/medias/enregistrer', { file: it.file, edit });
      if (!r.ok) { BO.toast(r.error || 'Enregistrement impossible', true); return; }
      card.dataset.media = JSON.stringify(r.item);
      const thumb = $('.mcardx__img img', card) || $('[data-medit] img', card);
      if (thumb) thumb.src = r.item.thumb + (r.item.thumb.includes('?') ? '&' : '?') + 't=' + Date.now();
      BO.toast(r.message);
      close();
      closeParent();
    };
    $('[data-apply]', m).addEventListener('click', () => send({ rotate, crop }));
    $('[data-reset]', m).addEventListener('click', () => send(null));
    load();
    draw();
  }
})();
