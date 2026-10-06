/*
 * Fiches de match : « J'y étais ! ». Le match rejoint le carnet du supporter (créé avec un e-mail
 * au premier clic) et compte une fois dans « Ils y étaient ». La page vient du cache : l'état du
 * carnet est lu dans le navigateur (liste gardée par /api/carnet).
 */
(() => {
  'use strict';
  const box = document.querySelector('[data-iye]');
  if (!box) return;
  const btn = box.querySelector('[data-iye-btn]'), out = box.querySelector('[data-iye-n]');
  const form = box.querySelector('[data-iye-mail]'), msg = box.querySelector('[data-iye-msg]');
  const id = +box.dataset.id, key = 'rd-etais-' + id;
  const label = n => (n > 1 ? box.dataset.ln : box.dataset.l1).replace('{n}', n);
  const store = (k, v) => { try { v === undefined ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch (e) {} };
  const read = k => { try { return localStorage.getItem(k); } catch (e) { return null; } };
  const counted = () => read(key) === '1';
  const inCarnet = () => { try { return (JSON.parse(read('sr-carnet') || '[]') || []).includes(id); } catch (e) { return false; } };
  const show = () => { if (inCarnet()) { btn.textContent = btn.dataset.in; btn.disabled = true; } };
  const api = body => fetch('/api/carnet', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ lang: document.documentElement.lang, ...body }) }).then(r => r.json()).catch(() => ({}));
  const done = r => {
    if (Array.isArray(r.ids)) store('sr-carnet', JSON.stringify(r.ids));
    if (r.etais) out.textContent = label(r.etais);
    store(key, '1');
    form.hidden = true;
    show();
  };
  show();

  btn.addEventListener('click', async () => {
    if (btn.disabled) return;
    btn.disabled = true;
    const r = await api({ action: 'ajouter', id, count: !counted() });
    btn.disabled = false;
    if (r.needEmail) { store('sr-carnet'); form.hidden = false; form.email.focus(); return; }
    if (r.ok) done(r);
  });

  form.addEventListener('submit', async e => {
    e.preventDefault();
    const r = await api({ action: 'creer', email: form.email.value.trim(), website: form.website.value, id, count: !counted() });
    if (r.error) { msg.textContent = r.error; return; }
    if (r.created) { done(r); return; }
    msg.textContent = msg.dataset.sent;
  });

  // Sans carnet : seulement le compteur public, comme avant.
  box.querySelector('[data-iye-count]').addEventListener('click', () => {
    form.hidden = true;
    if (counted()) return;
    store(key, '1');
    fetch('/api/retro-direct', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'etais', id }) })
      .then(r => r.json()).then(r => { if (r && r.etais) out.textContent = label(r.etais); }).catch(() => {});
  });
})();
