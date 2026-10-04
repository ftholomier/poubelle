/* Recherche sur le web d'une fiche (aide à l'historien, sans dépendance).
   La carte [data-webcheck] de l'éditeur lance la recherche (Gemini + Google) ou rouvre la dernière :
   un panneau liste les propositions (divergences, compléments, pistes) avec leurs sources, les
   pages consultées et les suggestions de recherche de Google (affichées telles quelles, comme
   Google le demande). Rien n'est modifié dans la fiche : l'historien vérifie et reporte. */
(function () {
  'use strict';
  const BO = window.BO;
  const card = BO && BO.$('[data-webcheck]');
  if (!card) return;
  const { $, $$, esc } = BO;
  const GROUPS = [['divergence', 'Divergences avec la fiche'], ['complement', 'Compléments'], ['piste', 'Pistes à consulter']];
  const CONF = { haute: ['ok', 'confiance haute'], moyenne: ['warn', 'confiance moyenne'], faible: ['brouillon', 'confiance faible'] };
  let panel = null;
  let last = null;
  try { last = JSON.parse(card.dataset.last || 'null'); } catch (e) { last = null; }

  const safeUrl = u => /^https?:\/\//i.test(String(u || '')) ? String(u) : '';
  const plural = (n, one, many) => n + ' ' + (n > 1 ? many : one);
  const when = iso => { const d = new Date(iso); return isNaN(d) ? '' : d.toLocaleDateString('fr-FR') + ' à ' + d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }); };

  function make() {
    if (panel) return panel;
    panel = document.createElement('aside');
    panel.className = 'proof webpanel';
    panel.hidden = true;
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-labelledby', 'web-t');
    panel.innerHTML = '<div class="proof__head"><h2 class="proof__t" id="web-t" tabindex="-1">Recherche sur le web</h2><button type="button" class="iconbtn" data-web-close aria-label="Fermer la recherche" title="Fermer (Échap)">✕</button></div>'
      + '<div class="proof__status" data-web-status role="status"></div><div class="proof__list" data-web-list></div>'
      + '<p class="proof__foot">Chaque proposition vient de pages trouvées par Google : ouvrez la source et vérifiez avant de reporter une information dans la fiche. Rien n’est modifié automatiquement.</p>';
    document.body.appendChild(panel);
    panel.addEventListener('click', onClick);
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && panel && !panel.hidden && !$('.modal')) close(); });
    return panel;
  }
  function open() {
    make();
    // Un seul panneau à la fois : le correcteur se ferme.
    $('aside.proof:not(.webpanel):not([hidden]) [data-proof-close]')?.click();
    panel.hidden = false;
    $('#web-t', panel).focus({ preventScroll: true });
  }
  function close() {
    if (!panel || panel.hidden) return;
    panel.hidden = true;
    $('[data-web-run]', card)?.focus({ preventScroll: true });
  }
  function status(html, error = false) {
    const st = $('[data-web-status]', make());
    st.classList.toggle('is-error', error);
    st.innerHTML = html;
  }

  function render(r, cost) {
    make();
    const items = Array.isArray(r.items) ? r.items : [];
    const sources = Array.isArray(r.sources) ? r.sources : [];
    const srcLink = i => { const s = sources[i]; const u = s && safeUrl(s.url); return u ? '<a href="' + esc(u) + '" target="_blank" rel="noopener noreferrer">' + esc(s.title || u) + ' ↗</a>' : ''; };
    status('<span>' + (r.summary ? esc(r.summary) : '') + '</span>'
      + '<span class="proof__cost">' + esc(plural(items.length, 'proposition', 'propositions')) + ' · ' + esc(when(r.at)) + (r.by ? ' · ' + esc(r.by) : '') + (cost && cost.label ? ' · coût : ' + esc(cost.label) : '') + '</span>');
    let out = '';
    GROUPS.forEach(([type, title]) => {
      const list = items.filter(it => it.type === type);
      if (!list.length) return;
      out += '<h3 class="proof__field">' + esc(title) + '</h3>';
      list.forEach(it => {
        const conf = CONF[it.confidence] || CONF.moyenne;
        const links = (it.sources || []).map(srcLink).filter(Boolean);
        out += '<div class="webitem webitem--' + esc(type) + '">'
          + '<div class="webitem__head"><b>' + esc(it.field || 'Information') + '</b><span class="pill pill--' + conf[0] + '">' + esc(conf[1]) + '</span></div>'
          + (it.fiche ? '<p class="webitem__fiche"><span>Fiche :</span> ' + esc(it.fiche) + '</p>' : '')
          + '<p class="webitem__text">' + esc(it.text) + '</p>'
          + '<div class="webitem__foot"><span class="webitem__src">' + (links.length ? 'Source' + (links.length > 1 ? 's' : '') + ' : ' + links.join(' · ') : 'Voir les pages consultées plus bas') + '</span>'
          + '<button type="button" class="linkbtn xs" data-web-copy="' + esc(it.text) + '">Copier</button></div></div>';
      });
    });
    if (!items.length) out += '<p class="webempty">Rien de fiable à proposer pour cette fiche' + (sources.length ? ' : les pages consultées sont listées ci-dessous.' : '.') + '</p>';
    if (sources.length) {
      out += '<h3 class="proof__field">Pages consultées</h3><ol class="websources">' + sources.map((s, i) => { const l = srcLink(i); return l ? '<li>' + l + '</li>' : ''; }).join('') + '</ol>';
    }
    if (r.suggestions) out += '<h3 class="proof__field">Recherches Google</h3><iframe class="websugg" title="Suggestions de recherche Google" sandbox="allow-popups allow-popups-to-escape-sandbox" referrerpolicy="no-referrer"></iframe>';
    const list = $('[data-web-list]', panel);
    list.innerHTML = out;
    const frame = $('.websugg', list);
    if (frame) frame.srcdoc = '<!doctype html><meta charset="utf-8"><base target="_blank"><body style="margin:0">' + r.suggestions;
    list.scrollTop = 0;
  }

  function cardState(r) {
    const n = (r.items || []).length;
    const st = $('[data-web-state]', card);
    if (st) st.innerHTML = 'Dernière recherche le ' + esc(new Date(r.at).toLocaleDateString('fr-FR')) + (r.by ? ' par ' + esc(r.by) : '') + ' : <b>' + (n ? esc(plural(n, 'proposition', 'propositions')) : 'rien de nouveau') + '</b>.';
    const show = $('[data-web-show]', card);
    if (show) show.hidden = false;
    const run = $('[data-web-run]', card);
    if (run) run.textContent = 'Relancer';
  }

  async function run() {
    const btn = $('[data-web-run]', card);
    if (btn.disabled) return;
    btn.disabled = true;
    const label = btn.textContent;
    btn.textContent = 'Recherche…';
    open();
    $('[data-web-list]', panel).innerHTML = '';
    status('<span>Recherche en cours : l’IA interroge Google puis compare avec la fiche (10 à 60 secondes). Vous pouvez continuer à travailler.</span>');
    let r;
    try {
      r = await BO.post('/admin/api/recherche-web', { id: Number(card.dataset.id) });
    } catch (e) {
      r = { ok: false, error: 'Connexion interrompue : réessayez.' };
    }
    btn.disabled = false;
    btn.textContent = label;
    if (!r.ok || !r.result) {
      status('<span>' + esc(r.error || 'Recherche impossible.') + '</span>', true);
      return;
    }
    last = r.result;
    render(last, r.cost);
    cardState(last);
  }

  async function onClick(e) {
    if (e.target.closest('[data-web-close]')) { close(); return; }
    const copy = e.target.closest('[data-web-copy]');
    if (copy) {
      try { await navigator.clipboard.writeText(copy.dataset.webCopy); BO.toast('Copié : à coller dans la fiche après vérification'); } catch (err) { BO.toast('Copie impossible : sélectionnez le texte', true); }
    }
  }

  card.addEventListener('click', e => {
    if (e.target.closest('[data-web-run]')) { e.preventDefault(); run(); }
    else if (e.target.closest('[data-web-show]') && last) { e.preventDefault(); render(last); open(); }
  });
  // Le correcteur s'ouvre : ce panneau se ferme.
  document.addEventListener('click', e => { if (e.target.closest('[data-proofread]')) close(); });
})();
