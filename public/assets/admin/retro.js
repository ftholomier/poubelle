/* Interactif › Rétro-Direct : le match choisi propose la date de son prochain anniversaire. */
(() => {
  'use strict';
  const form = document.querySelector('[data-retro-form]');
  if (!form) return;
  const date = form.querySelector('[name="date"]'), hint = form.querySelector('[data-rd-hint]');
  const base = hint ? hint.textContent : '';
  form.addEventListener('ac:pick', e => {
    const d = (e.detail && e.detail.date) || '';
    if (!/^\d{4}-\d{2}-\d{2}$/.test(d)) { if (hint) hint.textContent = base; return; }
    const now = new Date(), today = now.toISOString().slice(0, 10);
    let y = now.getFullYear(), md = d.slice(5);
    let ann = y + '-' + md;
    if (ann < today) ann = (++y) + '-' + md;
    if (md === '02-29' && !(y % 4 === 0 && (y % 100 !== 0 || y % 400 === 0))) ann = y + '-02-28';
    if (!date.value) date.value = ann;
    if (hint) hint.textContent = 'Match du ' + d.split('-').reverse().join('/') + ' : prochain anniversaire le ' + ann.split('-').reverse().join('/') + ' (' + (y - +d.slice(0, 4)) + ' ans). ' + base;
  });
})();

/* Commentaire radio : « Préparer » demande le commentaire puis le fait avancer ici, une étape à la
   fois (le texte, puis une réplique), avec la progression ; la page fermée, la tâche planifiée continue. */
(() => {
  'use strict';
  const BO = window.BO;
  const box = document.querySelector('#programme[data-radio-estimate]');
  if (!BO || !box) return;
  const est = box.dataset.radioEstimate, jours = box.dataset.jours;
  box.addEventListener('click', async e => {
    const b = e.target.closest('[data-radio-run]');
    if (!b) return;
    const cell = b.closest('[data-radio-cell]'), msg = cell.querySelector('[data-radio-msg]');
    const id = cell.dataset.id;
    if (b.dataset.radioRun === 'start' && !confirm('Préparer le commentaire radio de ' + cell.dataset.label + ' ?\n\nL’IA écrit le commentaire d’un reporter d’époque, calé sur les temps forts, puis la voix IA le lit, réplique par réplique (quelques minutes).\nCoût estimé : environ ' + est + ', une seule fois.')) return;
    b.disabled = true;
    let r = await BO.post('/admin/retro-direct', { action: b.dataset.radioRun === 'start' ? 'radio' : 'radio-etape', id, ajax: '1', jours });
    for (let i = 0; i < 80 && r && r.ok && ['waiting', 'script'].includes(r.state); i++) {
      msg.textContent = r.total ? ' ' + r.done + '/' + r.total + ' répliques…' : ' le texte s’écrit…';
      if (r.error) msg.textContent += ' (' + r.error + ')';
      r = await BO.post('/admin/retro-direct', { action: 'radio-etape', id, ajax: '1', jours });
    }
    if (!r || !r.ok) { msg.textContent = ' ' + ((r && r.error) || 'Erreur inconnue.'); b.disabled = false; return; }
    location.reload();
  });
})();
