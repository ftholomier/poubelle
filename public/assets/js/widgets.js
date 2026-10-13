/* Page « Intégrez le musée sur votre site » : filtre par catégorie (#cat=…), réglage des options de
   chaque widget (aperçu rechargé en direct, codes script et iframe mis à jour), thème sombre,
   copier le code, hauteur automatique. */
(() => {
  'use strict';
  const EN = document.documentElement.lang === 'en';
  const esc = s => String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

  document.querySelectorAll('[data-copy]').forEach(b => b.addEventListener('click', async () => {
    const t = document.getElementById(b.dataset.copy);
    if (!t) return;
    t.select();
    try { await navigator.clipboard.writeText(t.value); } catch (e) { document.execCommand && document.execCommand('copy'); }
    const old = b.textContent;
    b.textContent = EN ? 'Copied ✓' : 'Copié ✓';
    setTimeout(() => { b.textContent = old; }, 1600);
  }));

  // Configurateur de chaque widget.
  document.querySelectorAll('[data-wcfg]').forEach(item => {
    const C = JSON.parse(item.dataset.wcfg);
    const frame = item.querySelector('iframe[data-musee]');
    const codes = item.querySelectorAll('[data-code]');
    const api = item.querySelector('[data-api]');
    const dyn = item.querySelector('[data-dyn]');
    let dark = false, timer = null, sugT = null;
    const value = i => {
      let v = i.value.trim();
      if (i.list && v !== '') {
        const n = s => s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/\s*\([^)]*\)\s*$/, '').trim().toLowerCase();
      const hit = [...i.list.options].find(x => x.value === v) || [...i.list.options].find(x => n(x.textContent) === n(v));
        v = hit ? hit.value : v;
      }
      if (i.hasAttribute('data-md') && /^\d{4}-\d\d-\d\d$/.test(v)) v = v.slice(5);
      return v;
    };
    const update = (reload) => {
      const q = new URLSearchParams();
      if (C.lang === 'en') q.set('lang', 'en');
      q.set('theme', dark ? 'sombre' : 'clair');
      const opts = [];
      item.querySelectorAll('[data-opt]').forEach(i => {
        const v = value(i), def = (C.options[i.dataset.opt] || {}).default || '';
        if (v !== '' && v !== def) { q.set(i.dataset.opt, v); opts.push([i.dataset.opt, v]); }
      });
      const src = C.base + '/w/' + C.key + '?' + q.toString();
      const title = C.name + ' · ' + C.site;
      const attrs = [...q.entries()].map(([k, v]) => ' data-' + k + '="' + esc(v) + '"').join('');
      codes[0].value = '<div data-musee-widget="' + C.key + '"' + attrs + '></div>\n' + C.loader;
      if (codes[1]) codes[1].value = '<iframe src="' + esc(src) + '" title="' + esc(title) + '" data-musee="' + C.key + '" width="100%" height="' + C.height + '" style="border:0;max-width:440px" loading="lazy"></iframe>';
      if (api) {
        const a = new URLSearchParams(q); a.delete('theme');
        api.href = C.base + '/api/v1/widgets/' + C.key + (a.toString() ? '?' + a.toString() : '');
        api.querySelector('code').textContent = api.href.replace(/^https?:\/\//, '');
      }
      if (dyn) {
        const [k, v] = opts[0] || [Object.keys(C.options)[0], '…'];
        dyn.value = "// " + (EN ? 'Change the option without reloading your page:' : 'Changer l’option sans recharger votre page :') + "\ndocument.querySelector('[data-musee-widget=\"" + C.key + "\"]').setAttribute('data-" + k + "', '" + String(v).replace(/'/g, '') + "');";
      }
      if (reload !== false && frame) {
        clearTimeout(timer);
        q.set('apercu', '1');
        timer = setTimeout(() => { frame.src = C.base + '/w/' + C.key + '?' + q.toString(); }, 250);
      }
    };
    item.addEventListener('input', e => {
      const i = e.target;
      if (!i.matches('[data-opt]')) return;
      if (i.dataset.suggest) {
        clearTimeout(sugT);
        sugT = setTimeout(() => fetch(C.base + i.dataset.suggest + '?q=' + encodeURIComponent(i.value) + (C.lang === 'en' ? '&lang=en' : ''))
          .then(r => r.ok ? r.json() : null).then(j => {
            if (j) i.list.innerHTML = j.items.filter(x => x.value !== 'auto').map(x => '<option value="' + esc(x.value) + '">' + esc(x.label) + '</option>').join('');
          }).catch(() => {}), 200);
      }
      update();
    });
    item.addEventListener('change', e => { if (e.target.matches('[data-opt]')) update(); });
    const tg = item.querySelector('[data-theme-toggle]');
    if (tg) tg.addEventListener('click', () => {
      dark = !dark;
      tg.textContent = dark ? (EN ? 'See in light' : 'Voir en clair') : (EN ? 'See in dark' : 'Voir en sombre');
      update();
    });
    update(false);
  });

  // Filtre par catégorie (puces), sans rechargement ; état dans l'adresse (#cat=matchs).
  const bar = document.querySelector('[data-cat-filter]');
  if (bar) {
    const items = document.querySelectorAll('.wdemo-item[data-cats]');
    const apply = (cat, push) => {
      if (!bar.querySelector('[data-cat="' + CSS.escape(cat) + '"]')) cat = '';
      bar.querySelectorAll('[data-cat]').forEach(b => { const on = b.dataset.cat === cat; b.classList.toggle('is-on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
      items.forEach(el => { el.hidden = cat !== '' && !el.dataset.cats.split(' ').includes(cat); });
      if (push) history.replaceState(null, '', cat ? '#cat=' + cat : location.pathname + location.search);
    };
    const fromHash = () => (location.hash.match(/^#cat=([a-z-]+)$/) || [])[1] || '';
    bar.addEventListener('click', e => { const b = e.target.closest('[data-cat]'); if (b) apply(b.dataset.cat, true); });
    addEventListener('hashchange', () => apply(fromHash(), false));
    if (fromHash()) apply(fromHash(), false);
  }

  addEventListener('message', e => {
    const d = e.data;
    if (!d || d.type !== 'musee-widget') return;
    document.querySelectorAll('iframe[data-musee]').forEach(f => { if (f.contentWindow === e.source) f.style.height = d.height + 'px'; });
  });
})();
