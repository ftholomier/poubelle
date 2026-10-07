/* Archives à ranger : les zip sont ouverts dans le navigateur, les images envoyées une par une. */
(() => {
  const box = document.querySelector('[data-arc]');
  if (!box) return;
  const input = box.querySelector('[data-arc-input]');
  const status = box.querySelector('[data-arc-status]');
  const bar = box.querySelector('[data-arc-bar]');
  const send = async (blob, name) => {
    const fd = new FormData();
    fd.append('action', 'deposer');
    fd.append('_csrf', box.dataset.csrf);
    fd.append('file', blob, name);
    for (let i = 0; i < 3; i++) {
      try {
        const res = await fetch('/admin/archives', { method: 'POST', body: fd, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        return await res.json();
      } catch (e) { await new Promise(r => setTimeout(r, 3000)); }
    }
    return { error: 'pas de réponse du serveur' };
  };
  input.addEventListener('change', async () => {
    const files = [...input.files];
    if (!files.length) return;
    input.disabled = true;
    // Liste des images : celles des zip, puis les images choisies directement.
    const jobs = [];
    for (const f of files) {
      if (/\.zip$/i.test(f.name)) {
        status.textContent = 'ouverture de ' + f.name + '…';
        const zip = await JSZip.loadAsync(f);
        zip.forEach((path, entry) => { if (!entry.dir && /\.(jpe?g|png)$/i.test(path) && !/(^|\/)(__MACOSX|\.)/.test(path)) jobs.push({ name: path.split('/').pop(), get: () => entry.async('blob') }); });
      } else if (/\.(jpe?g|png)$/i.test(f.name)) {
        jobs.push({ name: f.name, get: async () => f });
      }
    }
    const n = { nouvelle: 0, deja: 0, inconnue: 0, erreur: 0 };
    for (let i = 0; i < jobs.length; i++) {
      const r = await send(await jobs[i].get(), jobs[i].name);
      if (r.error || r.status === 'illisible') n.erreur++; else n[r.status] = (n[r.status] || 0) + 1;
      bar.style.width = Math.round((i + 1) / jobs.length * 100) + '%';
      status.textContent = (i + 1) + ' / ' + jobs.length + ' · ' + n.nouvelle + ' nouvelle(s), ' + n.deja + ' déjà reçue(s), ' + n.inconnue + ' hors catalogue' + (n.erreur ? ', ' + n.erreur + ' erreur(s)' : '');
    }
    status.textContent += ' — terminé.';
    input.disabled = false;
    if (n.nouvelle) setTimeout(() => location.reload(), 1200);
  });
})();
