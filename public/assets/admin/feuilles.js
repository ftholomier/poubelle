/* Feuilles de match : tant que la page est ouverte, l'import avance de lui-même, un lot à la fois. */
(() => {
  const box = document.querySelector('[data-fm-run]');
  if (!box) return;
  const $ = s => box.querySelector(s);
  let fails = 0;
  const step = async () => {
    if (document.hidden) { setTimeout(step, 5000); return; }
    let r;
    try {
      const body = new URLSearchParams({ action: 'lot', _csrf: box.dataset.csrf });
      const res = await fetch('/admin/import-feuilles', { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      r = await res.json();
      fails = 0;
    } catch (e) {
      fails++;
      $('[data-fm-last]').textContent = 'pas de réponse du serveur, nouvel essai dans 20 s…';
      if (fails < 6) setTimeout(step, 20000);
      return;
    }
    if (r.error) { $('[data-fm-last]').textContent = 'erreur : ' + r.error; return; }
    $('[data-fm-left]').textContent = r.left;
    if (r.done !== undefined) $('[data-fm-done]').textContent = r.done;
    if (r.props_total !== undefined) $('[data-fm-props]').textContent = r.props_total;
    if (r.busy) { $('[data-fm-last]').textContent = 'la tâche planifiée traite déjà un lot, on attend…'; setTimeout(step, 10000); return; }
    $('[data-fm-last]').textContent = (r.created || 0) + ' créée(s), ' + (r.compared || 0) + ' comparée(s) dans ce lot' + (r.errors ? ', ' + r.errors + ' erreur(s)' : '') + '.';
    if (r.running && r.left > 0) {
      setTimeout(step, 800);
    } else {
      $('[data-fm-state]').textContent = r.left > 0 ? 'Import en pause' : 'Import terminé';
      if (!r.left) setTimeout(() => location.reload(), 1500);
    }
  };
  setTimeout(step, 600);
})();
