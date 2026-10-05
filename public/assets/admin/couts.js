/* Système › Coûts IA : chiffres en direct (rafraîchis toutes les 10 secondes quand l'onglet est visible) et barème. */
(function () {
  'use strict';
  const BO = window.BO;
  if (!BO) return;
  const { $, $$, esc } = BO;
  const root = $('[data-costs]');
  if (!root) return;

  /* ------------------------------------------------------------ barème : ajouter une ligne */
  $('[data-price-add]')?.addEventListener('click', () => {
    const rep = $('[data-repeater="prices"]');
    const node = rep && BO.addItem(rep);
    node?.querySelector('input')?.focus();
  });

  /* ------------------------------------------------------------ en direct */
  const status = $('[data-live-status]');
  let count = null, timer = null, busy = false;
  const get = (o, path) => path.split('.').reduce((v, k) => (v == null ? v : v[k]), o);

  const recentRow = r => '<tr><td class="xs nowrap">' + esc(r.at) + '</td><td class="small nowrap" title="Modèle : ' + esc(r.model) + '">' + esc(r.use) + '</td><td class="small">' + esc(r.who) + '</td>'
    + '<td class="small ellipsis costs-ref">' + (r.refUrl ? '<a class="rowlink" href="' + esc(r.refUrl) + '">' + esc(r.ref) + '</a>' : esc(r.ref)) + '</td>'
    + '<td class="xs nowrap">' + esc(r.tokens) + '</td>'
    + '<td class="t-num" style="text-align:right">' + esc(r.eur) + (r.flag ? ' <span class="pill pill--warn">' + esc(r.flag) + '</span>' : '') + '</td></tr>';
  const useRow = u => '<tr><td class="t-strong">' + esc(u.label) + '</td><td class="t-num">' + esc(u.calls) + '</td><td class="t-num">' + esc(u.eur) + '</td><td class="small">' + esc(u.avg) + '</td></tr>';
  const empty = (cols, text) => '<tr><td colspan="' + cols + '" class="muted" style="padding:18px;text-align:center">' + text + '</td></tr>';

  function render(d) {
    $$('[data-live]').forEach(el => {
      const v = get(d, el.dataset.live);
      if (v != null && typeof v !== 'object') el.textContent = String(v);
    });
    $('[data-live-due]')?.classList.toggle('kpi--pink', !!d.due.has);
    const b = $('[data-live-budget]');
    if (b) {
      b.classList.toggle('kpi--pink', !!d.budget.over);
      $('[data-live="budget.pct"]', b).textContent = d.budget.set ? d.budget.pct + ' %' : '—';
      const bar = $('[data-live-bar]', b);
      if (bar) bar.style.width = Math.min(100, d.budget.pct) + '%';
    }
    const fresh = count === null ? 0 : Math.max(0, d.month.count - count);
    count = d.month.count;
    const recent = $('[data-live-recent]');
    if (recent) {
      recent.innerHTML = d.recent.length ? d.recent.map(recentRow).join('') : empty(6, 'Aucun appel à Gemini ce mois-ci.');
      [...recent.rows].slice(0, Math.min(fresh, d.recent.length)).forEach(tr => tr.classList.add('is-new'));
    }
    const uses = $('[data-live-uses]');
    if (uses) uses.innerHTML = d.uses.length ? d.uses.map(useRow).join('') : empty(4, 'Rien ce mois-ci.');
  }

  async function refresh() {
    if (busy || document.hidden) return;
    busy = true;
    try {
      const r = await fetch('/admin/api/couts', { credentials: 'same-origin', headers: { Accept: 'application/json', 'X-BO-Background': '1' } });
      if (!r.ok || !(r.headers.get('content-type') || '').includes('json')) throw new Error(String(r.status));
      render(await r.json());
      status?.classList.remove('is-off');
      if (status) status.title = '';
    } catch (e) {
      status?.classList.add('is-off');
      if (status) status.title = 'Mise à jour impossible pour l’instant (connexion ou session expirée)';
    }
    busy = false;
  }

  const start = () => { clearInterval(timer); timer = setInterval(refresh, 10000); };
  document.addEventListener('visibilitychange', () => { if (!document.hidden) { refresh(); start(); } });
  refresh();
  start();
})();
