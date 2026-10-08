/* Système › Traductions › Fiches : « Traduire 10 fiches maintenant », une fiche à la fois, avec la progression affichée. */
(function () {
  'use strict';
  const BO = window.BO;
  if (!BO) return;
  const { $, esc } = BO;
  const form = $('[data-tr-run]');
  const log = $('[data-tr-log]');
  if (!form || !log) return;
  const status = $('[data-tr-status]', log);
  const list = $('[data-tr-list]', log);

  // « Tout traduire d'un coup » : une fiche après l'autre jusqu'à ce qu'il n'en reste plus (bouton Stop).
  const all = $('[data-tr-all]', form);
  const stop = $('[data-tr-stop]', log);
  const bar = $('[data-tr-bar]', log);
  let halt = false;
  stop && stop.addEventListener('click', () => { halt = true; stop.disabled = true; status.textContent = 'Arrêt demandé : fin de la fiche en cours…'; });
  all && all.addEventListener('click', async () => {
    if (!(await BO.confirm('Tout traduire d’un coup ?', 'Gemini traduit toutes les fiches non traduites ou à retraduire, une par une (10 à 60 secondes chacune). Laissez cette page ouverte ; vous pouvez arrêter à tout moment. Les traductions relues ne sont jamais écrasées.', 'Tout traduire'))) return;
    halt = false;
    form.querySelectorAll('button').forEach(b => { b.disabled = true; });
    log.hidden = false; bar.hidden = false; stop.hidden = false; stop.disabled = false;
    list.innerHTML = '';
    let done = 0, failed = 0, total = null;
    for (;;) {
      if (halt) { status.textContent = 'Arrêté : ' + done + ' fiche(s) traduite(s). Relancez quand vous voulez, la suite reprend où elle s’est arrêtée.'; break; }
      status.textContent = 'Traduction en cours… ' + done + ' faite(s)' + (total !== null ? ', ' + Math.max(0, total - done - failed) + ' restante(s)' : '');
      const r = await BO.post('/admin/traductions', { action: 'gemini-une' });
      if (!r.ok) { status.textContent = 'Arrêt : ' + (r.error || 'erreur inconnue'); break; }
      if (total === null) total = (r.todo || 0) + (r.title ? 1 : 0);
      if (r.title) {
        const ok = r.result === 'ok';
        ok ? done++ : failed++;
        list.insertAdjacentHTML('afterbegin', '<li>' + (ok ? '✓ ' : '✗ ') + esc(r.title) + (ok ? '' : ' : ' + esc(r.result)) + '</li>');
      }
      $('i', bar).style.width = (total ? Math.min(100, Math.round((done + failed) / total * 100)) : 100) + '%';
      if (r.stop || !r.todo) {
        status.textContent = r.error ? 'Arrêt : ' + r.error : 'Terminé : ' + done + ' fiche(s) traduite(s)' + (failed ? ', ' + failed + ' en échec (réessayées plus tard)' : '') + '. Rechargez la page pour voir les compteurs.';
        break;
      }
    }
    stop.hidden = true;
    form.querySelectorAll('button').forEach(b => { b.disabled = false; });
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = form.querySelector('button');
    const n = parseInt(form.querySelector('[name=n]').value, 10) || 10;
    btn.disabled = true;
    log.hidden = false;
    list.innerHTML = '';
    let done = 0;
    for (let i = 1; i <= n; i++) {
      status.textContent = 'Fiche ' + i + ' sur ' + n + ' : traduction par Gemini en cours (10 à 60 secondes)…';
      const r = await BO.post('/admin/traductions', { action: 'gemini-une' });
      if (!r.ok) { status.textContent = 'Arrêt : ' + (r.error || 'erreur inconnue'); break; }
      if (r.title) {
        const ok = r.result === 'ok';
        done += ok ? 1 : 0;
        list.insertAdjacentHTML('beforeend', '<li>' + (ok ? '✓ ' : '✗ ') + esc(r.title) + (ok ? '' : ' : ' + esc(r.result)) + '</li>');
      }
      if (r.stop) {
        status.textContent = r.error ? 'Arrêt : ' + r.error : (r.title ? 'Terminé.' : 'Plus aucune fiche à traduire.');
        break;
      }
      if (i === n) status.textContent = 'Terminé : ' + done + ' fiche(s) traduite(s), ' + r.todo + ' restante(s). Rechargez la page pour voir les compteurs.';
    }
    btn.disabled = false;
  });
})();
