/* Trouvailles : tant que la page est ouverte, la recherche avance d'elle-même, un match à la fois. */
(() => {
  const box = document.querySelector('[data-trv-run]');
  if (!box || box.dataset.running !== '1') return;
  const $ = s => box.querySelector(s);
  const last = $('[data-trv-last]');
  let found = 0;
  let fails = 0;
  const step = async () => {
    if (document.hidden) { setTimeout(step, 5000); return; }
    last.textContent = 'fouille du match suivant…';
    let r;
    try {
      const body = new URLSearchParams({ action: 'avancer', _csrf: box.dataset.csrf });
      const res = await fetch('/admin/trouvailles', { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      r = await res.json();
      fails = 0;
    } catch (e) {
      // Délai dépassé ou coupure : on réessaie un peu plus tard (la recherche a pu aboutir côté serveur).
      fails++;
      last.textContent = 'pas de réponse du serveur, nouvel essai dans 20 s…';
      if (fails < 6) setTimeout(step, 20000);
      return;
    }
    $('[data-trv-left]').textContent = r.left;
    $('[data-trv-searched]').textContent = r.searched;
    if (r.found) {
      found += r.found;
      $('[data-trv-found]').textContent = found;
      $('[data-trv-new]').hidden = false;
    }
    if (r.busy) {
      last.textContent = 'la tâche planifiée fouille déjà un match, on attend…';
      setTimeout(step, 15000);
      return;
    }
    last.textContent = r.log ? 'dernier : ' + r.log : 'en cours…';
    if (r.left > 0 && r.running) {
      setTimeout(step, 1500);
    } else {
      $('[data-trv-state]').textContent = r.left > 0 ? 'Recherche en pause' : 'Recherche terminée';
      last.textContent = r.left > 0 ? 'en pause.' : 'tous les matchs de la file ont été fouillés.';
    }
  };
  setTimeout(step, 800);
})();
