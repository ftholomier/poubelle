/* Carnet du supporter : création, lien perdu, saisie par saison, retrait, page publique, suppression. */
(() => {
  'use strict';
  const P = document.documentElement.lang === 'en' ? '/en' : ''; // adresses du site anglais
  const api = body => fetch('/api/carnet', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ lang: document.documentElement.lang, ...body }) })
    .then(r => r.json().catch(() => ({ error: 'Erreur ' + r.status })));
  const sync = r => { try { if (r && Array.isArray(r.ids)) localStorage.setItem('sr-carnet', JSON.stringify(r.ids)); if (r && r.has === false) localStorage.removeItem('sr-carnet'); } catch (e) {} };
  let L = {};
  try { L = JSON.parse(document.getElementById('cn-i18n')?.textContent || '{}'); } catch (e) {}
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);

  // Formulaires e-mail : créer le carnet / recevoir un nouveau lien.
  document.querySelectorAll('[data-cn-mail]').forEach(f => f.addEventListener('submit', async e => {
    e.preventDefault();
    const msg = f.querySelector('[data-cn-msg]');
    const btn = f.querySelector('button');
    btn.disabled = true;
    const r = await api({ action: f.dataset.mode, email: f.email.value.trim(), website: f.website.value, id: +f.dataset.id || 0, count: true });
    btn.disabled = false;
    sync(r);
    if (r.error) { msg.textContent = r.error; return; }
    if (r.created) { msg.textContent = '✓'; location.href = P + '/carnet/?bienvenue=1'; return; }
    msg.textContent = L.sent || '';
  }));

  // Changement de saison.
  document.querySelector('[data-cn-season]')?.addEventListener('change', e => e.target.form.submit());

  // Saisie par saison.
  const lot = document.querySelector('[data-cn-lot]');
  if (lot) {
    const boxes = [...lot.querySelectorAll('input[type=checkbox][value]')];
    const start = new Set(boxes.filter(b => b.checked).map(b => +b.value));
    lot.querySelectorAll('[data-cn-all]').forEach(b => b.addEventListener('click', () => boxes.forEach(x => { if (!x.closest('li').hidden) x.checked = b.dataset.cnAll === '1'; })));
    lot.querySelector('[data-cn-home]')?.addEventListener('change', e => boxes.forEach(x => { x.closest('li').hidden = e.target.checked && x.closest('li').dataset.home !== '1'; }));
    lot.addEventListener('submit', async e => {
      e.preventDefault();
      const msg = lot.querySelector('[data-cn-msg]');
      const add = boxes.filter(b => b.checked && !start.has(+b.value)).map(b => +b.value);
      const remove = boxes.filter(b => !b.checked && start.has(+b.value)).map(b => +b.value);
      const r = await api({ action: 'lot', add, remove });
      sync(r);
      if (r.error || r.needEmail) { msg.textContent = r.error || L.first || ''; return; }
      start.clear(); boxes.filter(b => b.checked).forEach(b => start.add(+b.value));
      msg.innerHTML = '✓ ' + esc(L.saved || '') + ' <a href="' + P + '/carnet/">' + esc(L.see || '') + ' →</a>';
    });
  }

  // Retirer un match (page du carnet).
  document.querySelectorAll('[data-cn-remove]').forEach(b => b.addEventListener('click', async () => {
    const r = await api({ action: 'retirer', id: +b.dataset.cnRemove });
    sync(r);
    if (r.ok) b.closest('li').remove();
  }));

  // Page publique.
  const pub = document.querySelector('[data-cn-public]');
  pub?.addEventListener('submit', async e => {
    e.preventDefault();
    const msg = pub.querySelector('[data-cn-msg]');
    const r = await api({ action: 'public', pseudo: pub.pseudo.value, on: pub.on.checked });
    if (r.error) { msg.textContent = r.error; return; }
    msg.innerHTML = r.url ? '✓ <a href="' + esc(r.url) + '" target="_blank" rel="noopener">' + esc(L.seePublic || r.url) + ' ↗</a>' : '✓ ' + esc(L.closed || '');
  });

  // Anniversaires : e-mail, notifications sur cet appareil.
  const rem = document.querySelector('[data-cn-remind]');
  if (rem) {
    const msg = rem.querySelector('[data-cn-msg]');
    const shown = r => { if (r.error) { msg.textContent = r.error; return; } msg.textContent = L.remindOn || '✓'; rem.querySelector('[data-cn-remind-n]').textContent = r.push ? (L.devices || '').replace('{n}', r.push) : ''; };
    rem.querySelector('[data-cn-remind-email]').addEventListener('change', async e => shown(await api({ action: 'rappels', email: e.target.checked })));
    rem.querySelector('[data-cn-remind-off]')?.addEventListener('click', async e => { shown(await api({ action: 'rappels', pushOff: true })); e.target.remove(); });
    rem.querySelector('[data-cn-remind-push]').addEventListener('click', async () => {
      let sub = null;
      try { const reg = await navigator.serviceWorker?.getRegistration(); sub = reg ? await reg.pushManager.getSubscription() : null; } catch (e) {}
      if (!sub) { msg.innerHTML = esc(L.noPush || '') + ' <a href="' + P + '/appli/">' + esc(L.appli || '/appli/') + ' →</a>'; return; }
      shown(await api({ action: 'rappels', endpoint: sub.endpoint }));
    });
  }

  // Anniversaire du supporter (jour et mois).
  const bday = document.querySelector('[data-cn-bday]');
  bday?.addEventListener('submit', async e => {
    e.preventDefault();
    const msg = document.querySelector('[data-cn-remind] [data-cn-msg]');
    const r = await api({ action: 'anniversaire', day: +bday.day.value, month: +bday.month.value });
    if (msg) msg.textContent = r.ok ? '✓ ' + (L.saved || '') : (r.error || '');
  });

  // Se déconnecter des autres appareils (et des liens envoyés pas encore ouverts).
  document.querySelector('[data-cn-logout]')?.addEventListener('click', async e => {
    if (!confirm(L.logout || '?')) return;
    const b = e.currentTarget;
    b.disabled = true;
    const r = await api({ action: 'deconnecter' });
    sync(r);
    const p = document.querySelector('[data-cn-others]');
    if (r.ok && r.has) { b.remove(); if (p) p.textContent = L.loggedOut || ''; }
    else if (r.ok) location.href = P + '/carnet/';
    else { b.disabled = false; if (p) p.textContent = r.error || ''; }
  });

  // Suppression.
  document.querySelector('[data-cn-delete]')?.addEventListener('click', async () => {
    if (!confirm(L.delete || '?')) return;
    const r = await api({ action: 'supprimer', confirm: true });
    sync(r);
    location.href = P + '/carnet/';
  });

  // Ouvert : on garde la liste à jour pour les boutons « J'y étais ! » des fiches (pages en cache).
  if (document.querySelector('[data-cn-page]')) api({ action: 'etat' }).then(sync);
})();
