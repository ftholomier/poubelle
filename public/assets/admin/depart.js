/* Contenus de départ : tant que la page est ouverte, la création avance d'elle-même, un lot à la fois. */
(() => {
  const box = document.querySelector('[data-dp-run]');
  if (!box) return;
  const $ = s => box.querySelector(s);
  let fails = 0;
  let done = parseInt($('[data-dp-done]').textContent, 10) || 0;
  const step = async () => {
    let r;
    try {
      const body = new URLSearchParams({ action: 'lot', _csrf: box.dataset.csrf });
      const res = await fetch('/admin/contenus-de-depart', { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      r = await res.json();
      fails = 0;
    } catch (e) {
      fails++;
      $('[data-dp-last]').textContent = 'pas de réponse du serveur, nouvel essai dans 20 s…';
      if (fails < 6) setTimeout(step, 20000);
      return;
    }
    if (r.error) { $('[data-dp-last]').textContent = 'erreur : ' + r.error; return; }
    if (r.busy) { $('[data-dp-last]').textContent = 'un lot est déjà en cours, on attend…'; setTimeout(step, 8000); return; }
    done += r.created || 0;
    $('[data-dp-done]').textContent = done;
    $('[data-dp-last]').textContent = (r.created || 0) + ' fiche(s) créée(s) dans ce lot' + (r.errors && r.errors.length ? ', ' + r.errors.length + ' erreur(s) : ' + r.errors.slice(0, 2).join(' · ') : '') + '.';
    if (r.running) {
      setTimeout(step, 500);
    } else {
      $('[data-dp-state]').textContent = 'Création terminée';
      setTimeout(() => location.reload(), 1500);
    }
  };
  setTimeout(step, 500);
})();
