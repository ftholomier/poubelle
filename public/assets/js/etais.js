/* Fiches de match : « J'y étais ! » (supporters présents au stade ce jour-là). */
(() => {
  'use strict';
  const box = document.querySelector('[data-iye]');
  if (!box) return;
  const btn = box.querySelector('[data-iye-btn]'), out = box.querySelector('[data-iye-n]');
  const id = +box.dataset.id, key = 'rd-etais-' + id;
  const label = n => (n > 1 ? box.dataset.ln : box.dataset.l1).replace('{n}', n);
  const done = () => { btn.disabled = true; if (!btn.textContent.startsWith('✓')) btn.textContent = '✓ ' + btn.textContent; };
  try { if (localStorage.getItem(key) === '1') done(); } catch (e) {}
  btn.addEventListener('click', () => {
    if (btn.disabled) return;
    done();
    try { localStorage.setItem(key, '1'); } catch (e) {}
    fetch('/api/retro-direct', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'etais', id }) })
      .then(r => r.json()).then(r => { if (r && r.etais) out.textContent = label(r.etais); }).catch(() => {});
  });
})();
