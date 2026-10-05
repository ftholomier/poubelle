/* Boîte à idées des 100 moments : demandes à l'IA (sommaire, piste, autre idée, premier jet),
   retenir / écarter, ajout et modification d'une idée (sources choisies parmi les fiches, photo). */
(function () {
  'use strict';
  const BO = window.BO;
  if (!BO || !document.querySelector('[data-ideas-tools]')) return;
  const { $, $$, esc } = BO;
  let conf = {};
  try { conf = JSON.parse($('#ideas-conf')?.textContent || '{}'); } catch (e) { conf = {}; }
  const URL = '/admin/moments/idees';

  /* ------------------------------------------------------------ envoi et attente */
  let busy = false;
  const wait = text => {
    const w = document.createElement('div');
    w.className = 'modal';
    w.innerHTML = '<div class="modal__box" role="status" aria-live="polite" style="text-align:center"><span class="spin" aria-hidden="true"></span><p style="margin:0;font-size:17px"></p></div>';
    $('p', w).textContent = text;
    document.body.appendChild(w);
    return () => w.remove();
  };
  const send = async (data, waitText) => {
    if (busy) return;
    busy = true;
    const done = waitText ? wait(waitText) : () => {};
    $$('[data-idea-act],[data-idea-piste] button').forEach(b => { b.dataset.wasDisabled = b.disabled ? '1' : ''; b.disabled = true; });
    const r = await BO.post(URL, data).catch(() => ({ error: 'Le serveur ne répond pas.' }));
    done();
    busy = false;
    $$('[data-idea-act],[data-idea-piste] button').forEach(b => { b.disabled = b.dataset.wasDisabled === '1'; });
    if (!r.ok) { BO.toast(r.error || 'Action impossible', true); return r; }
    if (r.redirect || r.reload) {
      try { sessionStorage.setItem('bo-toast', r.message || 'Enregistré'); } catch (e) { /* */ }
      if (r.redirect) location.href = r.redirect; else location.reload();
    } else BO.toast(r.message || 'Enregistré');
    return r;
  };

  /* ------------------------------------------------------------ petite fenêtre */
  const modal = (html, width) => {
    const m = document.createElement('div');
    m.className = 'modal';
    m.innerHTML = '<div class="modal__box" role="dialog" aria-modal="true" style="width:min(' + width + 'px,100%)">' + html + '</div>';
    const close = () => { m.remove(); document.removeEventListener('keydown', key); };
    const key = e => { if (e.key === 'Escape') close(); };
    m.addEventListener('click', e => { if (e.target === m || e.target.closest('[data-close]')) close(); });
    document.addEventListener('keydown', key);
    document.body.appendChild(m);
    BO.init(m);
    return { m, close };
  };

  const ask = (title, label, placeholder) => new Promise(resolve => {
    const { m, close } = modal('<h2 class="modal__t">' + esc(title) + '</h2><form class="stack" style="gap:12px"><label class="f"><span class="f__k">' + esc(label) + ' <i>facultatif</i></span><textarea class="in" rows="3" maxlength="300" placeholder="' + esc(placeholder) + '"></textarea></label><div class="row row--end"><button type="button" class="btn" data-close>Annuler</button><button type="submit" class="btn btn--navy">Écarter</button></div></form>', 560);
    let answered = false;
    $('form', m).addEventListener('submit', e => { e.preventDefault(); answered = true; resolve($('textarea', m).value.trim()); close(); });
    m.addEventListener('click', e => { if (!answered && (e.target === m || e.target.closest('[data-close]'))) resolve(null); });
    setTimeout(() => $('textarea', m).focus(), 30);
  });

  /* ------------------------------------------------------------ actions */
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-idea-act]');
    if (!b || b.disabled) return;
    const card = b.closest('[data-idea]');
    const idea = card ? JSON.parse(card.dataset.idea) : null;
    const act = b.dataset.ideaAct;
    if (act === 'sommaire') {
      if (!(await BO.confirm('Demander des idées à l’IA ?', 'Elle lit les fiches publiées du musée, époque par époque, et propose des idées appuyées sur ces fiches (une minute environ). Rien n’est publié : vous triez ensuite.', 'Demander'))) return;
      send({ action: 'sommaire' }, 'L’IA lit le musée et propose des idées… (une minute environ)');
    } else if (act === 'ecarter') {
      const reason = await ask('Écarter « ' + idea.title + ' » ?', 'Pourquoi ? (rappelé à l’IA, qui ne la proposera plus)', 'Déjà traité, trop anecdotique, fait douteux…');
      if (reason === null) return;
      send({ action: 'ecarter', id: idea.id, raison: reason });
    } else if (act === 'autre') {
      if (!(await BO.confirm('Une autre idée ?', 'L’idée « ' + idea.title + ' » est écartée et l’IA en propose une autre de la même époque.', 'Demander'))) return;
      send({ action: 'autre', id: idea.id }, 'L’IA cherche une autre idée…');
    } else if (act === 'rediger') {
      if (!(await BO.confirm('Demander le premier jet ?', 'L’IA écrit le récit d’après les seules fiches sources et crée la fiche « À relire », avec les points à vérifier. Rien n’est publié : vous relisez, corrigez et choisissez la date.', 'Rédiger'))) return;
      send({ action: 'rediger', id: idea.id }, 'L’IA rédige le premier jet… (une demi-minute environ)');
    } else {
      send({ action: act, id: idea && idea.id });
    }
  });
  $('[data-idea-piste]')?.addEventListener('submit', e => {
    e.preventDefault();
    const q = e.target.piste.value.trim();
    if (!q) { e.target.piste.focus(); return; }
    send({ action: 'piste', piste: q }, 'L’IA explore la piste « ' + q + ' »…');
  });

  /* ------------------------------------------------------------ ajouter ou modifier une idée */
  const form = idea => {
    const themes = conf.themes || {};
    const { m, close } = modal(
      '<h2 class="modal__t">' + (idea.id ? 'Modifier l’idée' : 'Nouvelle idée de moment') + '</h2>'
      + '<form class="stack" style="gap:12px" data-idea-form>'
      + '<div class="f"><span class="f__k">Titre <b>*</b></span><input class="in" name="title" maxlength="120" required value="' + esc(idea.title || '') + '" placeholder="La finale de 1988 au Parc des Princes"></div>'
      + '<div class="fgrid fgrid--2"><div class="f"><span class="f__k">Année</span><input class="in" name="year" inputmode="numeric" maxlength="4" value="' + esc(idea.year || '') + '"></div>'
      + '<div class="f"><span class="f__k">Date de l’événement <i>facultatif : date anniversaire</i></span><input class="in" type="date" name="date" value="' + esc(idea.date || '') + '"></div></div>'
      + '<div class="f"><span class="f__k">Thème</span><select class="in" name="theme">' + Object.entries(themes).map(([k, l]) => '<option value="' + esc(k) + '"' + (k === (idea.theme || 'match') ? ' selected' : '') + '>' + esc(l) + '</option>').join('') + '</select></div>'
      + '<div class="f"><span class="f__k">Pourquoi ce moment compte</span><textarea class="in" name="why" rows="3" maxlength="700" data-proof="text" spellcheck="true">' + esc(idea.why || '') + '</textarea></div>'
      + '<div class="f"><span class="f__k">Fiches sources <i>celles qui racontent ce moment : l’IA n’écrira qu’à partir d’elles</i></span><ul class="small" data-src-list style="margin:0;padding-left:18px"></ul><input class="in in--sm" data-ac="fiches" placeholder="Ajouter une fiche : tapez un match, un joueur…" autocomplete="off"></div>'
      + '<div class="f"><span class="f__k">Photo</span><div class="row" style="gap:10px;align-items:center"><img data-img alt="" style="width:120px;aspect-ratio:16/9;object-fit:cover;border:2px solid var(--navy);background:var(--sand)"><button type="button" class="btn btn--sm" data-img-pick>Choisir…</button><button type="button" class="btn btn--sm btn--ghost" data-img-none>Retirer</button></div><span class="f__help">Vide : la photo d’une fiche source, de préférence créditée.</span></div>'
      + '<div class="row row--end"><button type="button" class="btn" data-close>Annuler</button><button type="submit" class="btn btn--navy">Enregistrer</button></div></form>', 760);
    let sources = (idea.sources || []).slice();
    let image = idea.image || '';
    const list = $('[data-src-list]', m), img = $('[data-img]', m);
    const draw = () => {
      list.innerHTML = sources.map((s, i) => '<li>' + esc(s.title) + ' <button type="button" class="linkbtn xs" data-src-del="' + i + '">retirer</button></li>').join('') || '<li class="muted" style="list-style:none;margin-left:-18px">Aucune fiche.</li>';
      img.src = image ? BO.thumb(image, 320) : '';
      img.style.visibility = image ? 'visible' : 'hidden';
    };
    draw();
    list.addEventListener('click', e => { const d = e.target.closest('[data-src-del]'); if (d) { sources.splice(+d.dataset.srcDel, 1); draw(); } });
    m.addEventListener('ac:pick', e => {
      const it = e.detail || {};
      if (it.id && !sources.some(s => +s.id === +it.id)) sources.push({ id: +it.id, title: it.label || ('Fiche ' + it.id) });
      e.target.value = '';
      draw();
    });
    $('[data-img-pick]', m).addEventListener('click', async () => { const rel = await BO.pickMedia({}); if (rel && rel.length) { image = Array.isArray(rel) ? rel[0] : rel; draw(); } });
    $('[data-img-none]', m).addEventListener('click', () => { image = ''; draw(); });
    $('form', m).addEventListener('submit', async e => {
      e.preventDefault();
      const f = e.target;
      const data = { title: f.title.value, year: f.year.value, date: f.date.value, theme: f.theme.value, why: f.why.value, sources: sources.map(s => +s.id), image };
      const r = await send({ action: idea.id ? 'modifier' : 'ajouter', id: idea.id || '', idee: data });
      if (r && r.ok) close();
    });
    setTimeout(() => $('[name=title]', m).focus(), 30);
  };
  $('[data-idea-new]')?.addEventListener('click', () => form({}));
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-idea-edit]');
    if (b) form(JSON.parse(b.closest('[data-idea]').dataset.idea));
  });
})();
