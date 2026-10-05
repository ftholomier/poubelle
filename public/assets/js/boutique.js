/**
 * Boutique (site de l'association) : aperçu et prix en direct sur la page d'un article (rendu par
 * le serveur, mêmes tracés que le fichier d'impression), faces, panier (quantité).
 */
(() => {
  document.querySelectorAll('[data-autosubmit]').forEach(i => i.addEventListener('change', () => i.form.submit()));
  const root = document.querySelector('[data-shop-product]');
  if (!root) return;
  const form = root.querySelector('form');
  const svgBox = root.querySelector('[data-shop-svg]');
  const priceBox = root.querySelector('[data-shop-price]');
  let face = '', timer = null, seq = 0, v3d = null;
  // Aperçu 3D : le module (Three.js) n'est chargé qu'au premier clic.
  const box3d = root.querySelector('[data-shop-3d]'), btn3d = root.querySelector('[data-3d-toggle]');
  const credit3d = root.querySelector('[data-3d-credit]'), hint = root.querySelector('[data-3d-hint]'), faceBar = root.querySelector('[data-faces]');
  const webgl = (() => { try { const c = document.createElement('canvas'); return !!(c.getContext('webgl2') || c.getContext('webgl')); } catch (e) { return false; } })();
  if (btn3d && !webgl) btn3d.hidden = true;
  // Anecdote : tirée par le serveur (fait vérifié + IA), signée ; « Une autre » en retire une.
  root.querySelectorAll('[data-anec]').forEach(box => {
    const txt = box.querySelector('[data-anec-text]'), val = box.querySelector('[data-anec-val]'), sig = box.querySelector('[data-anec-sig]'), btn = box.querySelector('[data-anec-btn]');
    const seen = [];
    btn.addEventListener('click', async () => {
      btn.disabled = true; txt.classList.add('is-busy');
      try {
        const r = await fetch(root.dataset.anecdote, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF': (form.querySelector('[name=_csrf]') || {}).value || '' }, credentials: 'same-origin', body: JSON.stringify({ model: root.dataset.model, opts: state().opts, avoid: seen }) });
        const d = await r.json();
        if (d.ok) {
          seen.push(d.text); val.value = d.text; sig.value = d.sig; txt.textContent = d.text; txt.classList.add('is-on');
          btn.querySelector('b').textContent = 'Une autre';
          soon(0);
        } else { txt.textContent = d.error || 'Pas d’anecdote cette fois : réessayez.'; }
      } catch (e) { txt.textContent = 'Pas d’anecdote cette fois : réessayez.'; }
      btn.disabled = false; txt.classList.remove('is-busy');
    });
  });
  btn3d?.addEventListener('click', async () => {
    const on = btn3d.getAttribute('aria-pressed') !== 'true';
    btn3d.setAttribute('aria-pressed', on ? 'true' : 'false');
    btn3d.querySelector('b').textContent = on ? 'Retour à l’aperçu' : 'Voir en 3D';
    svgBox.hidden = on; box3d.hidden = !on; if (credit3d) credit3d.hidden = !on;
    if (faceBar) faceBar.hidden = on;
    if (!on) { v3d?.destroy(); v3d = null; return; }
    hint.hidden = false; hint.textContent = 'Chargement de la 3D…';
    try {
      const mod = await import(root.dataset['3d']);
      v3d = mod.viewer(box3d);
      await render();
      hint.hidden = false; hint.textContent = 'Faites tourner l’objet à la souris ou au doigt · molette ou pincement pour zoomer';
      setTimeout(() => { hint.hidden = true; }, 4000);
    } catch (e) { hint.textContent = 'La 3D n’est pas disponible sur cet appareil.'; }
  });
  const state = () => {
    const fd = new FormData(form), values = {}, opts = {};
    for (const [k, v] of fd.entries()) {
      let m = k.match(/^values\[(.+)\]$/); if (m) values[m[1]] = String(v);
      m = k.match(/^opts\[(.+)\]$/); if (m) opts[m[1]] = String(v);
    }
    return { model: root.dataset.model, values, opts, size: fd.get('size') || '', qty: +(fd.get('qty') || 1), face, flat: !!v3d };
  };
  const render = async () => {
    const n = ++seq;
    svgBox.classList.add('is-busy');
    try {
      const r = await fetch(root.dataset.preview, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify(state()), credentials: 'same-origin' });
      const d = await r.json();
      if (n !== seq || !d.ok) return;
      svgBox.innerHTML = d.svg;
      if (v3d && d.flat) { await v3d.set(d.flat); hint.hidden = true; }
      priceBox.textContent = d.price;
      const note = root.querySelector('[data-shop-note]');
      if (note) note.textContent = d.note || '';
      root.querySelectorAll('[data-shop-err]').forEach(el => { const e = (d.errors || {})[el.dataset.shopErr]; el.hidden = !e; el.textContent = e || ''; });
    } catch (e) { /* aperçu indisponible : le formulaire reste utilisable */ } finally { if (n === seq) svgBox.classList.remove('is-busy'); }
  };
  const soon = (ms = 250) => { clearTimeout(timer); timer = setTimeout(render, ms); };
  form.addEventListener('input', e => { if (e.target.closest('[data-shop-in]')) soon(e.target.type === 'text' ? 350 : 60); });
  // Couleurs du texte : seules celles lisibles sur la couleur du produit (contraste WCAG ≥ 3) sont proposées.
  const lum = h => { const c = [1, 3, 5].map(i => parseInt(h.replace('#', '').padEnd(7, '0').slice(i - 1, i + 1), 16) / 255).map(v => v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; };
  const contrast = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
  const guard = () => {
    const pc = (form.querySelector('[name="opts[color]"]:checked') || {}).value || root.dataset.color || '';
    const tcs = [...form.querySelectorAll('[name="opts[tcolor]"]')];
    tcs.forEach(r => { const off = !pc || contrast(r.value, pc) < 3; r.disabled = off; r.closest('label').hidden = off; });
    const cur = tcs.find(r => r.checked);
    if (cur && cur.disabled) { const ok = tcs.find(r => !r.disabled); if (ok) ok.checked = true; }
  };
  guard();
  form.addEventListener('change', e => { if (e.target.closest('[data-shop-in]')) { guard(); soon(30); } });
  root.querySelectorAll('[data-face]').forEach(b => b.addEventListener('click', () => {
    face = b.dataset.face;
    root.querySelectorAll('[data-face]').forEach(x => x.classList.toggle('is-on', x === b));
    soon(0);
  }));
})();
