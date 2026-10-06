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
