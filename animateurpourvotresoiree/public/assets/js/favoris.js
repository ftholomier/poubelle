/* Page « Mes favoris » : cartes chargées depuis l'API à partir de la liste locale. */
(() => {
  const page = document.querySelector('[data-favs-page]');
  if (!page) return;
  const grid = page.querySelector('[data-favs-grid]');
  const empty = page.querySelector('[data-favs-empty]');
  const devis = page.querySelector('[data-favs-devis]');
  const clear = page.querySelector('[data-favs-clear]');
  const APVS = window.APVS;
  const load = async () => {
    const ids = APVS.store.get('favs', []);
    if (!ids.length) { grid.innerHTML = ''; empty.classList.remove('hidden'); devis.classList.add('hidden'); clear.classList.add('hidden'); return; }
    const r = await fetch('/api/favoris?ids=' + ids.join(','), { headers: { Accept: 'application/json' } });
    const d = await r.json();
    grid.innerHTML = d.html;
    APVS.store.set('favs', ids.filter((id) => d.ids.includes(id)));
    empty.classList.toggle('hidden', d.ids.length > 0);
    devis.classList.toggle('hidden', d.ids.length === 0);
    clear.classList.toggle('hidden', d.ids.length === 0);
    devis.href = '/devis/?pros=' + d.ids.join(',');
    APVS.paintFavs();
  };
  document.addEventListener('apvs:favs', load);
  clear.addEventListener('click', () => { APVS.store.set('favs', []); load(); APVS.paintFavs(); });
  load();
})();
