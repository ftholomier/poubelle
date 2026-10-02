/* Explorer : filtres instantanés des listes de matchs, tri des adversaires. */
(() => {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];

  // Filtres combinables : chaque barre filtre sur un attribut (data-key par défaut).
  const groups = {};
  $$('[data-filterbar]').forEach(bar => {
    const g = bar.dataset.filterbar, attr = bar.dataset.filterAttr || 'key';
    (groups[g] ??= {})[attr] = '*';
    $$('[data-filter]', bar).forEach(btn => btn.addEventListener('click', () => {
      $$('[data-filter]', bar).forEach(b => b.classList.toggle('is-on', b === btn));
      groups[g][attr] = btn.dataset.filter;
      apply(g);
    }));
  });
  function apply(g) {
    const list = $(`[data-filtered="${g}"]`);
    if (!list) return;
    const f = groups[g];
    const filtering = Object.values(f).some(v => v !== '*');
    $$(':scope > *', list).forEach(el => {
      const ok = Object.entries(f).every(([attr, v]) => v === '*' || el.dataset[attr] === v);
      el.hidden = !ok || (!filtering && el.hasAttribute('data-more'));
    });
    const more = $(`[data-showall="${g}"]`);
    if (more) more.hidden = filtering;
  }
  $$('[data-showall]').forEach(btn => btn.addEventListener('click', () => {
    $$(`[data-filtered="${btn.dataset.showall}"] [data-more]`).forEach(el => { el.removeAttribute('data-more'); el.hidden = false; });
    btn.remove();
  }));

  // Adversaires : recherche et tri.
  const opp = $('[data-opplist]');
  if (opp) {
    const items = $$('.opp', opp);
    const norm = s => s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
    $('[data-oppfilter]')?.addEventListener('input', e => {
      const q = norm(e.target.value.trim());
      items.forEach(it => { it.hidden = q !== '' && !it.dataset.name.includes(q); });
    });
    $$('[data-oppsortby]').forEach(btn => btn.addEventListener('click', () => {
      $$('[data-oppsortby]').forEach(b => b.classList.toggle('is-on', b === btn));
      const k = btn.dataset.oppsortby;
      const sorted = items.slice().sort((a, b) => k === 'name' ? a.dataset.name.localeCompare(b.dataset.name, 'fr')
        : k === 'ratio' ? (+b.dataset.ratio - +a.dataset.ratio) || (+b.dataset.count - +a.dataset.count)
        : (+b.dataset.count - +a.dataset.count));
      sorted.forEach(it => opp.appendChild(it));
    }));
  }
})();
