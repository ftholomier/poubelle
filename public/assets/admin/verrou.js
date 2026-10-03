/*
 * Verrou de modification : <form data-lock="fiche:123"> signale toutes les 30 secondes que la fiche est
 * ouverte. Si quelqu'un d'autre l'a déjà ouverte, bandeau « Marie modifie cette fiche », lecture seule et
 * « Prendre la main ». La personne à qui l'on prend la main en est prévenue et ne peut plus enregistrer.
 * Après 30 minutes sans activité, la fiche est libérée ; elle se verrouille de nouveau dès que l'on reprend.
 */
(function () {
  'use strict';
  const BO = window.BO;
  if (!BO) return;
  const { $, $$, esc } = BO;
  const form = $('form[data-lock]');
  if (!form) return;
  const key = form.dataset.lock;
  const tab = form.dataset.lockTab || [...crypto.getRandomValues(new Uint8Array(6))].map(b => b.toString(16).padStart(2, '0')).join('');
  const what = form.dataset.lockWhat || 'cette fiche';
  const EVERY = window.BO_LOCK_EVERY || 30000;
  const IDLE_MAX = window.BO_LOCK_IDLE || 30 * 60000;
  const loaded = form.dataset.modified || '';
  let bar = $('[data-lockbar]', form);
  if (!bar) {
    bar = document.createElement('div');
    bar.className = 'lockbar';
    bar.setAttribute('data-lockbar', '');
    bar.setAttribute('role', 'status');
    bar.hidden = true;
    form.prepend(bar);
  }
  // mine : on modifie ; other : quelqu'un d'autre modifie (lecture seule) ; taken : on nous a pris la main ;
  // free : la personne est partie ; asleep : fiche libérée pour inactivité.
  let state = form.dataset.lockState === 'other' ? 'other' : 'mine';
  let lastAct = Date.now(), timer = null, busy = false, holderName = '';

  /* ------------------------------------------------------------ lecture seule */
  const CTRL = '[data-save], [data-proofread], [data-tr], [data-tr-all], button[form="trash-form"], button[form="untrash-form"], button[form="destroy-form"]';
  function readonly(on, why, strict = true) {
    form.classList.toggle('is-readonly', on);
    if (on) form.dataset.readonly = why; else delete form.dataset.readonly;
    // Contenu figé (sauf pour la personne évincée, qui doit pouvoir copier son texte).
    zones().forEach(el => { el.inert = on && strict; });
    $$(CTRL).forEach(b => { b.disabled = on; });
  }
  // Zones à figer : onglets de contenu d'une fiche (les boutons d'onglets restent utilisables),
  // sinon [data-lock-zone], sinon tout le formulaire sauf la barre d'outils et la colonne latérale.
  function zones() {
    if ($('.fpanel', form)) return $$('.fpanel, .pub .seg, .pub [data-show-if], .pub label.f, [data-lock-zone]', form);
    const marked = $$('[data-lock-zone]', form);
    return marked.length ? marked : [...form.children].filter(c => !c.matches('.lockbar, .toolbar, .editor__side, input, template'));
  }
  function show(html, tone = '') {
    bar.className = 'lockbar' + (tone ? ' lockbar--' + tone : '');
    bar.innerHTML = html;
    bar.hidden = !html;
  }
  const since = h => ' depuis ' + esc(h.since) + (h.idle >= 5 ? ' (sans activité depuis ' + h.idle + ' min)' : '');

  function showOther(h) {
    holderName = h.name;
    readonly(true, h.name + ' modifie ' + what + ' : prenez la main pour enregistrer.');
    show('<span class="lockbar__t">🔒 <b>' + esc(h.name) + '</b> modifie ' + what + since(h) + '. Vous êtes en lecture seule.</span>'
      + '<button type="button" class="btn btn--sm btn--navy" data-lock-take>Prendre la main</button>');
  }
  function showFree(saved) {
    const who = holderName ? '<b>' + esc(holderName) + '</b>' : 'La personne qui la modifiait';
    if (saved) {
      show('<span class="lockbar__t">✓ ' + who + ' a fermé ' + what + ' et enregistré une nouvelle version à ' + esc(saved.at) + '.</span>'
        + '<button type="button" class="btn btn--sm btn--navy" data-lock-reload>Recharger pour modifier</button>', 'ok');
    } else {
      show('<span class="lockbar__t">✓ ' + who + ' a fermé ' + what + '.</span>'
        + '<button type="button" class="btn btn--sm btn--navy" data-lock-edit>Modifier maintenant</button>', 'ok');
    }
  }
  function mine() {
    state = 'mine';
    readonly(false);
    show('');
  }

  /* ------------------------------------------------------------ échanges avec le serveur */
  async function ping(mode) {
    if (busy) return null;
    busy = true;
    const idle = Math.round((Date.now() - lastAct) / 1000);
    const r = await BO.post('/admin/api/verrou', { key, mode, tab, idle, modified: loaded }).catch(() => null);
    busy = false;
    if (!r || !r.ok) return null;
    if (r.taken && state !== 'taken') { taken(r.taken); return r; }
    if (mode === 'hold' || mode === 'take') {
      if (r.mine) {
        if (r.saved && mode === 'take') {
          // Version plus récente que celle affichée : il faut repartir de celle-ci.
          state = 'free';
          show('<span class="lockbar__t">Vous avez la main. ' + esc(r.saved.by) + ' a enregistré une nouvelle version à ' + esc(r.saved.at) + ' : rechargez pour partir de celle-ci.</span>'
            + '<button type="button" class="btn btn--sm btn--navy" data-lock-reload>Recharger</button>', 'ok');
          return r;
        }
        if (state !== 'mine') mine();
        return r;
      }
      state = 'other';
      if (r.holder) showOther(r.holder);
      return r;
    }
    // Observation (lecture seule ou évincé).
    if (r.holder) {
      if (state === 'other') showOther(r.holder);
    } else if (state === 'other' || state === 'taken') {
      const wasTaken = state === 'taken';
      state = 'free';
      readonly(true, 'Rechargez la fiche pour la modifier.', !wasTaken);
      showFree(r.saved);
    }
    return r;
  }

  async function taken(t) {
    state = 'taken';
    holderName = t.by;
    readonly(true, t.by + ' a pris la main sur ' + what + ' : vos modifications ne peuvent plus être enregistrées.', false);
    show('<span class="lockbar__t">⚠ <b>' + esc(t.by) + '</b> a pris la main sur ' + what + ' à ' + esc(t.at) + '. Vous ne pouvez plus enregistrer : copiez ce dont vous avez besoin, puis rechargez.</span>'
      + '<button type="button" class="btn btn--sm btn--navy" data-lock-reload>Recharger</button>', 'warn');
    if (await BO.confirm(t.by + ' a pris la main', t.by + ' modifie maintenant ' + what + ' (depuis ' + t.at + '). Vos modifications non enregistrées restent affichées tant que vous ne quittez pas la page : copiez-les si besoin.', 'Recharger la fiche')) {
      location.reload();
    }
  }

  // Appelé par l'enregistrement refusé (réponse 423) : prendre la main puis enregistrer.
  BO.takeLock = async () => {
    const r = await ping('take');
    return !!(r && r.mine && state === 'mine');
  };

  bar.addEventListener('click', async e => {
    if (e.target.closest('[data-lock-reload]')) { location.reload(); return; }
    if (e.target.closest('[data-lock-edit]')) { await ping('hold'); return; }
    if (e.target.closest('[data-lock-take]')) {
      const who = holderName || 'La personne qui modifie';
      if (await BO.confirm('Prendre la main ?', who + ' ne pourra plus enregistrer ses modifications en cours : prévenez-la si possible.', 'Prendre la main', true)) {
        const r = await ping('take');
        if (r && r.mine && state === 'mine') BO.toast('Vous avez la main sur ' + what + '.');
      }
    }
  });

  /* ------------------------------------------------------------ présence et inactivité */
  const wake = () => {
    lastAct = Date.now();
    if (state === 'asleep') { state = 'mine'; show(''); ping('hold'); }
  };
  ['keydown', 'pointerdown', 'input'].forEach(ev => form.addEventListener(ev, wake, { passive: true }));
  document.addEventListener('visibilitychange', () => { if (!document.hidden) tick(); });

  function release() {
    const fd = new FormData();
    fd.append('_csrf', BO.CSRF);
    fd.append('key', key);
    fd.append('mode', 'release');
    fd.append('tab', tab);
    navigator.sendBeacon('/admin/api/verrou', fd);
  }
  function tick() {
    if (state === 'mine') {
      if (Date.now() - lastAct > IDLE_MAX) {
        state = 'asleep';
        release();
        show('<span class="lockbar__t">Fiche libérée après ' + Math.round(IDLE_MAX / 60000) + ' minutes sans activité, pour que d’autres puissent la modifier. Elle se verrouille de nouveau dès que vous reprenez.</span>', 'info');
        return;
      }
      ping('hold');
    } else if (state === 'other' || state === 'taken') {
      ping('watch');
    }
  }
  window.addEventListener('pagehide', () => { if (state === 'mine') release(); });

  if (state === 'other') readonly(true, 'Quelqu’un d’autre modifie ' + what + '.');
  holderName = ($('.lockbar__t b', bar) || {}).textContent || '';
  // Ouverture sans verrou pris par le serveur (collections, accueil…) : on le prend tout de suite.
  if (!form.dataset.lockTab || state === 'other') tick();
  timer = setInterval(tick, EVERY);
})();
