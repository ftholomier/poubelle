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
