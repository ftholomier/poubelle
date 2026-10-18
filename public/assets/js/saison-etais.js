/*
 * Page d'une saison : « J'y étais, toute la saison ! ». Ajoute d'un coup les matchs de la saison
 * (tous, ou ceux à domicile) au carnet du supporter ; un e-mail crée le carnet si besoin.
 */
(() => {
  'use strict';
  const box = document.querySelector('[data-siye]');
  if (!box) return;
  const all = JSON.parse(box.dataset.all || '[]'), home = JSON.parse(box.dataset.home || '[]');
  const state = box.querySelector('[data-siye-state]'), rm = box.querySelector('[data-siye-remove]');
  const form = box.querySelector('[data-siye-mail]'), msg = box.querySelector('[data-siye-msg]');
  const read = () => { try { return JSON.parse(localStorage.getItem('sr-carnet') || '[]') || []; } catch (e) { return []; } };
  const store = ids => { try { localStorage.setItem('sr-carnet', JSON.stringify(ids)); } catch (e) {} };
  // Réponse de l'API ; en cas d'échec (429 trop de demandes, 503 carnet occupé, réseau coupé),
  // { error } : le message du serveur s'il en donne un, sinon le message générique de la page.
  const fail = box.dataset.error || 'Erreur, réessayez dans un instant.';
  const api = body => fetch('/api/carnet', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ lang: document.documentElement.lang, ...body }) })
    .then(async r => { const j = await r.json().catch(() => ({})); return r.ok ? (j || {}) : { ...j, error: (j && j.error) || fail }; })
    .catch(() => ({ error: fail }));
  const oops = r => { state.textContent = r.error || fail; state.classList.add('is-error'); };
  let pending = null;
  const show = () => {
    const mine = read();
    const n = all.filter(id => mine.includes(id)).length;
    if (n) { state.textContent = box.dataset.done.replace('{n}', n); state.classList.remove('is-error'); }
    rm.hidden = !n;
    document.querySelectorAll('.mrow[data-mid]').forEach(a => a.classList.toggle('is-seen', mine.includes(+a.dataset.mid)));
  };
  const add = async ids => {
    const r = await api({ action: 'lot', add: ids });
    if (r.needEmail) { pending = ids; form.hidden = false; form.email.focus(); return; }
    if (Array.isArray(r.ids)) { store(r.ids); form.hidden = true; show(); return; }
    oops(r);
  };
  box.querySelectorAll('[data-siye-add]').forEach(b => b.addEventListener('click', () => add(b.dataset.siyeAdd === 'home' ? home : all)));
  rm.addEventListener('click', async () => {
    const r = await api({ action: 'lot', remove: all });
    if (Array.isArray(r.ids)) { store(r.ids); state.textContent = box.dataset.none; state.classList.remove('is-error'); show(); return; }
    oops(r);
  });
  form.addEventListener('submit', async e => {
    e.preventDefault();
    const r = await api({ action: 'creer', email: form.email.value.trim(), website: form.website.value });
    if (r.error) { msg.textContent = r.error; return; }
    if (r.created) { if (pending) await add(pending); return; }
    msg.textContent = msg.dataset.sent;
  });
  // État réel du carnet (la page peut venir du cache).
  api({ action: 'etat' }).then(r => { if (Array.isArray(r.ids)) { store(r.ids); show(); } });
  show();
})();
