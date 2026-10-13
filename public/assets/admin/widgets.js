/* Communauté › Widgets & partenaires : configurateur (aperçu en direct, code script, code iframe et adresse de l'API à copier). */
(() => {
  'use strict';
  const box = document.querySelector('[data-widget-config]');
  document.querySelectorAll('[data-copy-input]').forEach(b => b.addEventListener('click', () => copy(document.getElementById(b.dataset.copyInput), b)));
  if (!box) return;
  const C = JSON.parse(box.dataset.widgetConfig);
  const $ = s => box.querySelector(s);
  const val = k => $('[data-cfg="' + k + '"]').value;
  const optsBox = $('[data-cfg-options]');
  const esc = s => String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
  let timer = null;

  function renderOptions() {
    const w = C.widgets[val('widget')];
    optsBox.innerHTML = '';
    Object.entries(w.options || {}).forEach(([name, o]) => {
      const l = document.createElement('label');
      l.className = 'f';
      l.style.flex = '1 1 220px';
      let field;
      if (o.type === 'select') {
        field = '<select class="in" data-opt="' + esc(name) + '">' + Object.entries(o.choices).map(([v, t]) => '<option value="' + esc(v) + '"' + (v === o.default ? ' selected' : '') + '>' + esc(t) + '</option>').join('') + '</select>';
      } else {
        field = '<input class="in" data-opt="' + esc(name) + '" value="' + esc(o.default || '') + '">';
      }
      l.innerHTML = '<span class="f__k">' + esc(o.label) + '</span>' + field;
      optsBox.appendChild(l);
    });
  }

  function update() {
    const key = val('widget');
    const w = C.widgets[key];
    const q = new URLSearchParams();
    if (val('lang') !== 'fr') q.set('lang', val('lang'));
    q.set('theme', val('theme'));
    if (val('format')) q.set('format', val('format'));
    optsBox.querySelectorAll('[data-opt]').forEach(i => { const v = i.value.trim(); const def = (w.options[i.dataset.opt] || {}).default || ''; if (v !== '' && v !== def) q.set(i.dataset.opt, v); });
    const p = C.partners.find(x => x.id === val('partner'));
    if (p && p.key) q.set('key', p.key);
    const src = C.base + '/w/' + key + '?' + q.toString();
    const api = new URLSearchParams(q); api.delete('theme'); api.delete('format');
    const apiUrl = C.base + '/api/v1/widgets/' + key + (api.toString() ? '?' + api.toString() : '');
    const title = w.name + ' · musée';
    $('[data-cfg-out="iframe"]').value = '<iframe src="' + esc(src) + '" title="' + esc(title) + '" data-musee="' + key + '" width="100%" height="' + w.height + '" style="border:0;max-width:440px" loading="lazy"></iframe>';
    $('[data-cfg-out="api"]').value = apiUrl;
    // Code script : mêmes réglages en attributs data-* (theme, lang, format, options, key).
    const attrs = [...q.entries()].map(([k, v]) => ' data-' + k + '="' + esc(v) + '"').join('');
    $('[data-cfg-out="script"]').value = '<div data-musee-widget="' + key + '"' + attrs + '></div>\n' + C.loader;
    $('[data-cfg-open]').href = src;
    const warn = [];
    if ((w.access === 'reserve' || w.access === 'payant') && !p) warn.push('Ce widget est ' + (w.access === 'reserve' ? 'réservé' : 'payant') + ' : choisissez un partenaire, sinon le code affichera « clé requise ».');
    if (w.access === 'payant' && p && !p.paid) warn.push('« ' + p.name + ' » n’a pas d’offre payante en cours : le widget lui sera refusé.');
    if (w.access === 'desactive') warn.push('Ce widget est désactivé : il n’est servi à personne (l’aperçu reste visible pour vous).');
    if (p && p.domains.length) warn.push('Clé limitée à : ' + p.domains.join(', ') + ' (le widget ne s’affichera que sur ces sites).');
    $('[data-cfg-warn]').textContent = warn.join(' ');
    // Aperçu : sans la clé (vous êtes connecté), pour ne pas compter dans les statistiques.
    q.delete('key'); q.set('apercu', '1');
    clearTimeout(timer);
    timer = setTimeout(() => { $('[data-cfg-preview]').src = '/w/' + key + '?' + q.toString(); $('[data-cfg-preview]').style.height = w.height + 'px'; }, 250);
  }

  function copy(input, btn) {
    if (!input) return;
    input.select();
    (navigator.clipboard ? navigator.clipboard.writeText(input.value) : Promise.reject()).catch(() => document.execCommand('copy'));
    const t = btn.textContent; btn.textContent = 'Copié ✓'; setTimeout(() => { btn.textContent = t; }, 1500);
  }

  box.addEventListener('change', e => { if (e.target.matches('[data-cfg="widget"]')) renderOptions(); update(); });
  box.addEventListener('input', e => { if (e.target.matches('[data-opt]')) update(); });
  box.querySelectorAll('[data-cfg-copy]').forEach(b => b.addEventListener('click', () => copy($('[data-cfg-out="' + b.dataset.cfgCopy + '"]'), b)));
  document.querySelectorAll('[data-cfg-pick]').forEach(b => b.addEventListener('click', () => {
    $('[data-cfg="widget"]').value = b.dataset.cfgPick; renderOptions(); update(); box.scrollIntoView({ behavior: 'smooth' });
  }));
  addEventListener('message', e => {
    const d = e.data;
    if (d && d.type === 'musee-widget') document.querySelectorAll('iframe').forEach(f => { if (f.contentWindow === e.source) f.style.height = d.height + 'px'; });
  });
  renderOptions();
  update();
})();
