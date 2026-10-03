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
    let ok = 0;
    for (let i = 0; i < list.length; i++) {
      const f = list[i];
      if (progress) { progress.hidden = false; progress.textContent = 'Envoi ' + (i + 1) + ' / ' + list.length + ' : ' + f.name + '…'; }
      const fd = new FormData();
      fd.append('file', f);
      if (replace) fd.append('replace', replace);
      const r = await BO.post('/admin/medias/envoi', fd, false);
      if (r.ok) ok++; else BO.toast(r.error || ('Échec : ' + f.name), true);
    }
    if (progress) progress.textContent = ok + ' fichier(s) envoyé(s).';
    if (ok) {
      BO.toast(replace ? 'Fichier remplacé.' : ok + ' fichier(s) ajouté(s) à la médiathèque.');
      setTimeout(() => { location.href = replace ? location.href : '/admin/medias?tri=recent'; }, 600);
    }
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
      + '<div class="row"><button type="submit" class="btn btn--navy">Enregistrer</button>'
      + '<label class="btn">Remplacer le fichier…<input type="file" hidden data-replace accept="' + (it.pdf ? 'application/pdf' : 'image/jpeg,image/png,image/gif,image/webp') + '"></label>'
      + (conf.canDelete ? '<span class="grow"></span><button type="button" class="btn btn--danger" data-del>Supprimer</button>' : '')
      + '</div><span class="f__help">« Remplacer » garde la même adresse : toutes les fiches qui utilisent l’image afficheront le nouveau fichier.</span></form>'
      + '</div></div>', true);
    const form = $('form', m);
    form.addEventListener('submit', async ev => {
      ev.preventDefault();
      const data = { file: it.file };
      ['caption', 'caption_en', 'credit', 'rights', 'alt', 'alt_en', 'date_text'].forEach(k => { data[k] = form[k].value; });
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
})();
