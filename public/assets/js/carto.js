/* Carto du musée (maquette « Carto ») : les stades, les origines, les épopées, les lieux.
   Données réelles : /api/carte (fiches matchs, lieux de naissance géolocalisés, collections). */
(async function () {
  'use strict';
  const root = document.querySelector('[data-carto]');
  if (!root || !window.L) return;
  const EN = root.dataset.lang === 'en';
  const T = (fr, en) => (EN ? en : fr);
  const C = { navy: '#0E1F4D', blue: '#1F3FA8', yellow: '#F6C400', cream: '#F3EDDF', sand: '#E8DFC9', paper: '#FFFDF6' };
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const fmt = n => n.toLocaleString(EN ? 'en-GB' : 'fr-FR');

  // Petits équivalents des fonctions d3 utilisées par la maquette
  const d3 = {
    max: (a, f = x => x) => a.length ? Math.max(...a.map(f)) : undefined,
    mean: (a, f = x => x) => a.reduce((s, x) => s + f(x), 0) / (a.length || 1),
    sum: (a, f = x => x) => a.reduce((s, x) => s + f(x), 0),
    rollups: (a, red, key) => { const m = new Map(); a.forEach(x => { const k = key(x); if (!m.has(k)) m.set(k, []); m.get(k).push(x); }); return [...m].map(([k, v]) => [k, red(v)]); },
    rollup: (a, red, key) => new Map(d3.rollups(a, red, key)),
    scaleSqrt: () => { let d = [0, 1], r = [0, 1]; const s = x => r[0] + (r[1] - r[0]) * (Math.sqrt(Math.max(0, x)) - Math.sqrt(d[0])) / ((Math.sqrt(d[1]) - Math.sqrt(d[0])) || 1); s.domain = v => (d = v, s); s.range = v => (r = v, s); return s; },
    interpolateRgb: (a, b) => { const h = x => [1, 3, 5].map(i => parseInt(x.slice(i, i + 2), 16)); const A = h(a), B = h(b); return t => 'rgb(' + A.map((v, i) => Math.round(v + (B[i] - v) * t)).join(',') + ')'; },
  };

  let data;
  try { data = await (await fetch(root.dataset.api)).json(); } catch (e) { document.getElementById('cpanel').innerHTML = '<p class="cp-intro" style="padding:20px">' + T('La carte est momentanément indisponible.', 'The map is temporarily unavailable.') + '</p>'; return; }
  const HOME = data.home || [47.5122, 6.8111];
  const NOW = data.now || new Date().getFullYear();
  const DEC = []; for (let d = 1930; d <= Math.floor(NOW / 10) * 10; d += 10) DEC.push(d);
  const dlab = d => "'" + String(d).slice(2);
  const places = (data.places || []).map(p => ({ ...p, recs: (p.recs || []).map(r => ({ ...r, comp: r.comp === "Coupe d'Europe" ? 'Europe' : r.comp })) }));
  const people = data.people || [];
  const steps = data.steps || [];
  const lieux = data.lieux || [];
  const cname = {}; people.forEach(p => { if (p.iso) cname[p.iso] = p.country; });

  // ===== CARTE =====
  const map = L.map('cmap', { zoomControl: false, worldCopyJump: true, minZoom: 2 });
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>' }).addTo(map);
  let layer = L.layerGroup().addTo(map);
  let land = null;
  const loadLand = async () => { if (land) return land; try { const w = await (await fetch('/assets/vendor/countries-110m.topojson')).json(); land = topojson.feature(w, w.objects.countries); } catch (e) { land = { type: 'FeatureCollection', features: [] }; } return land; };
  const panel = document.getElementById('cpanel'), legend = document.getElementById('clegend'), drawer = document.getElementById('cdrawer'), story = document.getElementById('cstory');
  const S = { tab: 'matchs', comp: 'Tous', dec: 'Toutes', lieu: 'Tous', res: 'Tous', year: NOW, sel: null, rub: 'Tous', poste: 'Tous', ldec: 'Toutes', forme: false, intl: false, lview: 'Villes', etype: 'Toutes', eseason: 'Toutes', step: -1, ltype: 'Tous', timer: null };
  let home = () => {};
  document.getElementById('czoom').onclick = e => { const z = e.target.closest('[data-z]')?.dataset.z; if (z === 'in') map.zoomIn(); if (z === 'out') map.zoomOut(); if (z === 'reset') { closeDrawer(); home(); } };
  const chipRow = (id, label, opts, cur, f = x => x, cls = () => '') => `<div><span class="cp-flabel">${label}</span><div class="cp-chips" id="${id}">${opts.map(o => `<button type="button" class="cchip ${cls(o)}${String(o) === String(cur) ? ' on' : ''}" data-v="${esc(o)}">${esc(f(o))}</button>`).join('')}</div></div>`;
  const bindChips = (id, key, cb, parse = v => v) => panel.querySelector('#' + id).addEventListener('click', e => { const b = e.target.closest('.cchip'); if (!b) return; S[key] = parse(b.dataset.v); panel.querySelectorAll('#' + id + ' .cchip').forEach(c => c.classList.toggle('on', c === b)); cb(); });
  const decParse = v => v === 'Toutes' ? v : +v;
  const tally = recs => { const t = { n: recs.length, V: 0, N: 0, D: 0, '?': 0 }; recs.forEach(r => { t[r.res] = (t[r.res] || 0) + 1; }); return t; };
  const bar = t => t.n ? `<div class="c-bar"><span style="width:${t.V / t.n * 100}%;background:${C.yellow}"></span><span style="width:${t.N / t.n * 100}%;background:${C.paper}"></span><span style="width:${t.D / t.n * 100}%;background:${C.navy}"></span><span style="width:${(t['?'] || 0) / t.n * 100}%;background:${C.sand}"></span></div>` : '';
  const spark = rows => { const mx = d3.max(rows, r => r.n) || 1; return `<div class="cspark">${rows.map(r => `<div><i style="height:0" data-h="${r.n / mx * 70}"></i><small>${dlab(r.d)}</small></div>`).join('')}</div>`; };
  function openDrawer(html) { drawer.innerHTML = html; drawer.classList.add('open'); drawer.querySelector('.x').onclick = closeDrawer; requestAnimationFrame(() => drawer.querySelectorAll('.cspark i').forEach(i => { i.style.height = i.dataset.h + 'px'; })); }
  function closeDrawer() { drawer.classList.remove('open'); S.sel = null; panel.querySelectorAll('.crow.sel').forEach(r => r.classList.remove('sel')); }
  const head = (eb, title) => `<div class="cd-head"><button type="button" class="x" aria-label="${T('Fermer', 'Close')}">✕</button><span class="cp-eyebrow" style="color:${C.yellow}">${esc(eb)}</span><h2 class="cd-title">${esc(title)}</h2></div>`;
  const stopTimer = () => { clearInterval(S.timer); S.timer = null; panel.querySelectorAll('[data-play]').forEach(b => { b.textContent = b.dataset.play; }); };
  const resStyle = r => r === 'V' ? `background:${C.yellow}` : r === 'D' ? `background:${C.navy};color:${C.cream}` : `background:${C.paper}`;

  // ===== VUE · STADES =====
  const COMPS = [['Tous', T('Tous', 'All')], ['Championnat', T('Championnat', 'League')], ['Coupes nationales', T('Coupes nationales', 'Domestic cups')], ['Coupe de France', 'Coupe de France'], ['Coupe de la Ligue', 'Coupe de la Ligue'], ['Europe', T('Europe', 'Europe')], ['Amical', T('Amical', 'Friendly')]];
  const recsOf = p => p.recs.filter(r => r.y <= S.year && (S.dec === 'Toutes' || Math.floor(r.y / 10) * 10 === S.dec) && (S.res === 'Tous' || r.res === S.res)
    && (S.comp === 'Tous' || r.comp === S.comp || (S.comp === 'Coupes nationales' && ['Coupe de France', 'Coupe de la Ligue'].includes(r.comp)))
    && (S.lieu === 'Tous' || (S.lieu === 'Domicile' ? r.h === 1 : r.h === 0)));
  const markers = {};
  function tabMatchs() {
    panel.innerHTML = `<div class="cp-head"><span class="cp-eyebrow">${T('Carto · Les stades', 'Map · Stadiums')}</span><h1 class="cp-title">${T('Partout où<br>le Lion a joué', 'Everywhere<br>the Lion played')}</h1><p class="cp-intro">${T('Taille = nombre de matchs, couleur = bilan de Sochaux. Cliquez un stade pour la liste de ses matchs.', 'Size = number of matches, colour = Sochaux record. Click a stadium to list its matches.')}</p></div>
    <div class="cp-filters">
      <div><span class="cp-flabel">${T('Compétition', 'Competition')}</span><div class="cp-chips" id="fComp">${COMPS.map(([v, l]) => `<button type="button" class="cchip${v === S.comp ? ' on' : ''}" data-v="${esc(v)}">${esc(l)}</button>`).join('')}</div></div>
      ${chipRow('fDec', T('Décennie', 'Decade'), ['Toutes', ...DEC], S.dec, o => o === 'Toutes' ? T('Toutes', 'All') : dlab(o))}
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">${chipRow('fLieu', T('Lieu', 'Venue'), ['Tous', 'Domicile', 'Extérieur'], S.lieu, o => ({ Tous: T('Tous', 'All'), Domicile: T('Domicile', 'Home'), 'Extérieur': T('Extérieur', 'Away') }[o]))}${chipRow('fRes', T('Résultat', 'Result'), ['Tous', 'V', 'N', 'D'], S.res, o => o === 'Tous' ? T('Tous', 'All') : o, o => o === 'V' ? 'v' : o === 'D' ? 'dd' : '')}</div>
      <div><span class="cp-flabel">${T('Remonter le temps', 'Travel back in time')}</span><div class="cp-timeline"><button type="button" class="cplay" data-play="▶" id="cplay" aria-label="${T('Lecture', 'Play')}">▶</button><input type="range" id="cyr" min="1928" max="${NOW}" value="${S.year}" aria-label="${T('Année', 'Year')}"><span class="cp-year" id="cyrl">${S.year}</span></div></div>
    </div>
    <div class="cp-listhead"><span id="clcount"></span><span style="color:var(--muted)">${T('matchs', 'matches')}</span></div><div class="cp-list" id="clist"></div>`;
    bindChips('fComp', 'comp', updMatchs); bindChips('fDec', 'dec', updMatchs, decParse); bindChips('fLieu', 'lieu', updMatchs); bindChips('fRes', 'res', updMatchs);
    const yr = panel.querySelector('#cyr'); yr.oninput = () => { S.year = +yr.value; stopTimer(); updMatchs(); };
    panel.querySelector('#cplay').onclick = () => { if (S.timer) return stopTimer(); if (S.year >= NOW) S.year = 1928; panel.querySelector('#cplay').textContent = '❚❚'; S.timer = setInterval(() => { S.year++; if (S.year >= NOW) { S.year = NOW; stopTimer(); } updMatchs(); }, reduce ? 10 : 90); };
    legend.innerHTML = `<span class="d">${T('Bilan de Sochaux', 'Sochaux record')}</span><div class="clg"><span class="csw" style="background:${C.yellow}"></span>${T('Plus de victoires', 'More wins')}</div><div class="clg"><span class="csw" style="background:${C.paper}"></span>${T('Équilibré', 'Balanced')}</div><div class="clg"><span class="csw" style="background:${C.navy}"></span>${T('Plus de défaites', 'More defeats')}</div><div class="clg"><span class="csw" style="background:${C.blue}"></span>${T('Stade Bonal', 'Stade Bonal')}</div><span class="cnote">${T('Calculé depuis les fiches matchs géolocalisées', 'Computed from geolocated match records')}</span>`;
    places.forEach(p => {
      const m = L.circleMarker(p.ll, { radius: 0, weight: 1.5, color: C.navy, fillOpacity: .92 }).addTo(layer);
      m.on('mouseover', () => hlPlace(p.id, true)).on('mouseout', () => hlPlace(p.id, false)).on('click', () => openPlace(p.id));
      markers[p.id] = m;
    });
    const all = places.length ? L.latLngBounds(places.map(p => p.ll)) : L.latLngBounds([HOME, HOME]);
    home = () => map.flyToBounds(all, { padding: [50, 50], duration: reduce ? 0 : 1, maxZoom: 7 });
    map.fitBounds(all, { padding: [50, 50], maxZoom: 7 });
    updMatchs();
  }
  function updMatchs() {
    panel.querySelector('#cyrl').textContent = S.year; panel.querySelector('#cyr').value = S.year;
    const max = d3.max(places, p => p.recs.length) || 1, rs = d3.scaleSqrt().domain([0, max]).range([0, 34]);
    const rows = places.map(p => ({ p, t: tally(recsOf(p)) }));
    rows.forEach(({ p, t }) => {
      const m = markers[p.id]; const fill = p.home ? C.blue : t.V > t.D ? C.yellow : t.D > t.V ? C.navy : C.paper;
      m.setRadius(t.n ? Math.max(5, rs(t.n)) : 0).setStyle({ fillColor: fill, opacity: t.n ? 1 : 0, fillOpacity: t.n ? .92 : 0 });
      m.unbindTooltip().bindTooltip(`<b>${esc(p.name)}</b>${esc(p.city)} · ${t.n} ${T('match', 'match')}${t.n > 1 ? (EN ? 'es' : 's') : ''}<br>${t.V} V · ${t.N} N · ${t.D} D`, { className: 'tt', direction: 'top', offset: [0, -6] });
    });
    const vis = rows.filter(d => d.t.n).sort((a, b) => b.t.n - a.t.n);
    vis.slice().reverse().forEach(d => markers[d.p.id].bringToFront());
    panel.querySelector('#clcount').textContent = `${vis.length} ${T('stade', 'stadium')}${vis.length > 1 ? 's' : ''} · ${fmt(d3.sum(vis, d => d.t.n))}`;
    const list = panel.querySelector('#clist');
    list.innerHTML = vis.map((d, i) => `<button type="button" class="crow${S.sel === d.p.id ? ' sel' : ''}" data-id="${esc(d.p.id)}"><span class="c-rank">${String(i + 1).padStart(2, '0')}</span><span><span class="c-name">${esc(d.p.name)}</span><span class="c-meta">${d.p.city ? ' · ' + esc(d.p.city) : ''}</span>${bar(d.t)}</span><span class="c-num">${d.t.n}</span></button>`).join('') || `<p class="cp-intro" style="padding:16px">${T('Aucun match pour ces filtres.', 'No match for these filters.')}</p>`;
    list.querySelectorAll('.crow').forEach(r => { r.onmouseenter = () => hlPlace(r.dataset.id, true); r.onmouseleave = () => hlPlace(r.dataset.id, false); r.onclick = () => openPlace(r.dataset.id); });
  }
  function hlPlace(id, on) {
    const m = markers[id]; if (m) { m.setStyle({ color: on ? C.yellow : C.navy, weight: on ? 4 : 1.5 }); if (on) m.bringToFront(); }
    const row = panel.querySelector(`.crow[data-id="${CSS.escape(id)}"]`); if (row) row.classList.toggle('hl', on);
  }
  function openPlace(id) {
    S.sel = id; const p = places.find(x => x.id === id); const recs = recsOf(p); const t = tally(recs);
    const notable = recs.slice().sort((a, b) => b.y - a.y).slice(0, 30);
    openDrawer(`${head((p.home ? T('À domicile', 'Home') : T('Déplacement', 'Away')) + (p.city ? ' · ' + p.city : ''), p.name)}<div class="cd-body">
      <div class="cstats"><div><b>${t.n}</b><span>${T('matchs', 'matches')}</span></div><div><b>${t.V}</b><span>${T('victoires', 'wins')}</span></div><div><b>${t.N}</b><span>${T('nuls', 'draws')}</span></div><div><b>${t.D}</b><span>${T('défaites', 'defeats')}</span></div></div>${bar(t)}
      <div><span class="cp-flabel">${T('Matchs par décennie', 'Matches per decade')}</span>${spark(DEC.map(d => ({ d, n: recs.filter(r => Math.floor(r.y / 10) * 10 === d).length })))}</div>
      ${notable.length ? `<div><span class="cp-flabel">${T('Dans les archives', 'In the archives')}</span><div class="cmlist">${notable.map(r => `<a class="cmitem" href="${esc(r.href)}"><b>${r.y}</b><span>${esc(r.label)}<br><span class="c-meta">${esc(r.comp)}</span></span><span class="res" style="${resStyle(r.res)}">${esc(r.res)}</span></a>`).join('')}</div></div>` : ''}
      ${p.home ? `<a class="ccta" href="/bilans/stade-auguste-bonal/">${T('Le bilan complet à Bonal →', 'Full record at Bonal →')}</a>` : ''}</div>`);
    panel.querySelectorAll('.crow').forEach(r => r.classList.toggle('sel', r.dataset.id === id));
    map.flyTo(p.ll, Math.max(map.getZoom(), 9), { duration: reduce ? 0 : 1 });
  }

  // ===== VUE · ORIGINES =====
  const POSTES = ['Gardien', 'Défenseur', 'Milieu', 'Attaquant'];
  const posteLabel = p => EN ? ({ Gardien: 'Goalkeeper', 'Défenseur': 'Defender', Milieu: 'Midfielder', Attaquant: 'Forward' }[p] || p) : p;
  const rubLabel = r => EN ? ({ Joueur: 'Player', 'Entraîneur': 'Coach', Dirigeant: 'Executive', Personnage: 'Figure', Tous: 'All' }[r] || r) : r;
  let geo;
  const pFilter = p => (S.rub === 'Tous' || p.rub === S.rub) && (S.poste === 'Tous' || p.poste === S.poste) && (S.ldec === 'Toutes' || p.dec === S.ldec) && (!S.forme || p.forme) && (!S.intl || p.intl);
  function tabLions() {
    panel.innerHTML = `<div class="cp-head"><span class="cp-eyebrow">${T('Carto · Les origines', 'Map · Origins')}</span><h1 class="cp-title">${T("D'où viennent<br>nos Lions ?", 'Where do<br>our Lions come from?')}</h1><p class="cp-intro">${T('Lieu de naissance des joueurs, entraîneurs et dirigeants, tiré des fiches. Les points proches se regroupent : zoomez pour les séparer.', 'Birthplaces of players, coaches and executives, taken from their records. Nearby points are grouped: zoom in to split them.')}</p></div>
    <div class="cp-filters">
      ${chipRow('fView', T('Affichage', 'Display'), ['Villes', 'Pays'], S.lview, o => o === 'Villes' ? T('Villes', 'Cities') : T('Pays', 'Countries'))}
      ${chipRow('fRub', T('Rubrique', 'Section'), ['Tous', 'Joueur', 'Entraîneur', 'Dirigeant'], S.rub, o => o === 'Tous' ? rubLabel(o) : rubLabel(o) + 's')}
      ${chipRow('fPoste', T('Poste', 'Position'), ['Tous', ...POSTES], S.poste, o => o === 'Tous' ? T('Tous', 'All') : posteLabel(o))}
      ${chipRow('fLDec', T("Décennie d'arrivée", 'Decade of arrival'), ['Toutes', ...DEC], S.ldec, o => o === 'Toutes' ? T('Toutes', 'All') : dlab(o))}
      <div class="cp-toggles"><button type="button" class="tg${S.forme ? ' on' : ''}" data-k="forme"><i></i>${T('Formés au club', 'Academy graduates')}</button><button type="button" class="tg${S.intl ? ' on' : ''}" data-k="intl"><i></i>${T('Internationaux', 'Internationals')}</button></div>
    </div>
    <div class="cp-listhead"><span id="clcount"></span><span style="color:var(--muted)">${T('personnes', 'people')}</span></div><div class="cp-list" id="clist"></div>`;
    bindChips('fView', 'lview', updLions); bindChips('fRub', 'rub', updLions); bindChips('fPoste', 'poste', updLions); bindChips('fLDec', 'ldec', updLions, decParse);
    panel.querySelectorAll('.tg').forEach(b => { b.onclick = () => { S[b.dataset.k] = !S[b.dataset.k]; b.classList.toggle('on', S[b.dataset.k]); updLions(); }; });
    home = () => map.flyTo([30, 5], 3, { duration: reduce ? 0 : 1 });
    map.setView([30, 5], 3);
    map.on('zoomend', onZoomLions);
    updLions();
  }
  function onZoomLions() { if (S.tab === 'lions' && S.lview === 'Villes') drawClusters(); }
  let ppl = [];
  async function updLions() {
    ppl = people.filter(pFilter);
    const byCity = d3.rollups(ppl, v => v, p => p.city).map(([city, v]) => ({ city, v, ll: v[0].ll, iso: v[0].iso, country: v[0].country })).sort((a, b) => b.v.length - a.v.length);
    const byIso = d3.rollups(ppl.filter(p => p.iso), v => v.length, p => p.iso).sort((a, b) => b[1] - a[1]);
    panel.querySelector('#clcount').textContent = S.lview === 'Villes' ? `${byCity.length} ${T('villes', 'cities')} · ${ppl.length}` : `${byIso.length} ${T('pays', 'countries')} · ${ppl.length}`;
    const list = panel.querySelector('#clist');
    if (S.lview === 'Villes') list.innerHTML = byCity.map((c, i) => `<button type="button" class="crow" data-city="${esc(c.city)}"><span class="c-rank">${String(i + 1).padStart(2, '0')}</span><span><span class="c-name">${esc(c.city)}</span><span class="c-meta"> · ${esc(c.country)}</span></span><span class="c-num">${c.v.length}</span></button>`).join('');
    else { const mx = byIso[0]?.[1] || 1; list.innerHTML = byIso.map(([iso, n], i) => `<button type="button" class="crow" data-iso="${esc(iso)}"><span class="c-rank">${String(i + 1).padStart(2, '0')}</span><span><span class="c-name">${esc(cname[iso] || iso)}</span><div class="c-bar" style="border:0;background:rgba(14,31,77,.08)"><span style="width:${n / mx * 100}%;background:${C.blue}"></span></div></span><span class="c-num">${n}</span></button>`).join(''); }
    if (!ppl.length) list.innerHTML = `<p class="cp-intro" style="padding:16px">${T('Personne ne correspond à ces filtres.', 'Nobody matches these filters.')}</p>`;
    list.querySelectorAll('.crow').forEach(r => { r.onclick = () => r.dataset.city ? openCity(r.dataset.city, true) : openCountry(r.dataset.iso); });
    layer.clearLayers(); geo = null;
    if (S.lview === 'Villes') {
      drawClusters();
      legend.innerHTML = `<span class="d">${T('Origines', 'Origins')}</span><div class="clg"><span class="csw" style="background:${C.yellow}"></span>${T('Une personne', 'One person')}</div><div class="clg"><span class="csw" style="background:${C.navy};border-color:${C.yellow}"></span>${T('Groupe · cliquez pour zoomer', 'Group · click to zoom')}</div><span class="cnote">${T('Lieux de naissance géolocalisés depuis les fiches', 'Birthplaces geolocated from the records')}</span>`;
    } else {
      const lnd = await loadLand();
      const cnt = new Map(byIso), mx = d3.max(byIso, d => d[1]) || 1, col = d3.scaleSqrt().domain([0, mx]).range([.15, 1]), ip = d3.interpolateRgb(C.yellow, C.navy);
      geo = L.geoJSON(lnd, {
        style: f => { const n = cnt.get(f.id); return { fillColor: n ? ip(col(n)) : 'transparent', fillOpacity: n ? .85 : 0, color: n ? C.navy : 'transparent', weight: 1 }; },
        onEachFeature: (f, l) => { const n = cnt.get(f.id); if (!n) return; l.bindTooltip(`<b>${esc(cname[f.id] || f.properties?.name || '')}</b>${n} ${T('personne', 'person')}${n > 1 ? (EN ? 's' : 's') : ''}`, { className: 'tt', sticky: true }); l.on('mouseover', () => l.setStyle({ weight: 3 })).on('mouseout', () => l.setStyle({ weight: 1 })).on('click', () => openCountry(f.id)); },
      }).addTo(layer);
      legend.innerHTML = `<span class="d">${T('Personnes par pays', 'People per country')}</span><div style="width:180px;height:12px;border:2px solid var(--navy);background:linear-gradient(90deg,${ip(.15)},${C.navy})"></div><div style="display:flex;justify-content:space-between;font-size:13px"><span>${T('peu', 'few')}</span><span>${T('beaucoup', 'many')}</span></div>`;
    }
  }
  function drawClusters() {
    layer.clearLayers(); const z = map.getZoom(), cell = 64, groups = new Map();
    ppl.forEach(p => { const pt = map.project(p.ll, z); const key = Math.floor(pt.x / cell) + ':' + Math.floor(pt.y / cell); if (!groups.has(key)) groups.set(key, []); groups.get(key).push(p); });
    groups.forEach(g => {
      const lat = d3.mean(g, p => p.ll[0]), lon = d3.mean(g, p => p.ll[1]); const citiesIn = [...new Set(g.map(p => p.city))]; const one = citiesIn.length === 1;
      const size = Math.round(26 + Math.sqrt(g.length) * 7);
      const icon = L.divIcon({ className: '', html: `<div class="cl${g.length === 1 ? ' one' : ''}" style="width:${size}px;height:${size}px;font-size:${Math.min(22, 12 + Math.sqrt(g.length) * 2)}px">${g.length}</div>`, iconSize: [size, size], iconAnchor: [size / 2, size / 2] });
      const m = L.marker([lat, lon], { icon, keyboard: true, title: one ? citiesIn[0] : citiesIn.length + ' ' + T('villes', 'cities') }).addTo(layer);
      m.bindTooltip(`<b>${esc(one ? citiesIn[0] : citiesIn.length + ' ' + T('villes', 'cities'))}</b>${g.length} ${T('personne', 'person')}${g.length > 1 ? 's' : ''}${one ? '' : '<br>' + esc(citiesIn.slice(0, 4).join(', ')) + (citiesIn.length > 4 ? '…' : '')}`, { className: 'tt', direction: 'top', offset: [0, -size / 2] });
      m.on('click', () => one ? openCity(citiesIn[0]) : map.flyToBounds(L.latLngBounds(g.map(p => p.ll)), { padding: [80, 80], maxZoom: 12, duration: reduce ? 0 : .8 }));
    });
  }
  function personList(v) {
    const sorted = v.slice().sort((a, b) => (b.m || 0) - (a.m || 0));
    return `<div class="cmlist">${sorted.map(p => `<a class="cmitem" href="${esc(p.href)}"><b>→</b><span>${esc(p.name)}${p.intl ? `<span class="cbadge">${T('International', 'International')}</span>` : ''}${p.forme ? `<span class="cbadge">${T('Formé au club', 'Academy')}</span>` : ''}</span><span class="c-meta">${esc(p.poste ? posteLabel(p.poste) : rubLabel(p.rub))}</span></a>`).join('')}</div>`;
  }
  function originBody(v) {
    const po = d3.rollup(v, x => x.length, p => p.poste);
    return `<div class="cstats"><div><b>${v.length}</b><span>${T('au total', 'in total')}</span></div><div><b>${v.filter(p => p.rub === 'Joueur').length}</b><span>${T('joueurs', 'players')}</span></div><div><b>${v.filter(p => p.forme).length}</b><span>${T('formés', 'academy')}</span></div><div><b>${v.filter(p => p.intl).length}</b><span>${T('internat.', 'internat.')}</span></div></div>
      <div><span class="cp-flabel">${T('Par poste', 'By position')}</span><div class="cp-chips">${POSTES.map(p => `<span class="cchip">${esc(posteLabel(p))} · ${po.get(p) || 0}</span>`).join('')}</div></div>
      <div><span class="cp-flabel">${T("Décennie d'arrivée", 'Decade of arrival')}</span>${spark(DEC.map(d => ({ d, n: v.filter(p => p.dec === d).length })))}</div>
      <div><span class="cp-flabel">${T('Fiches', 'Records')}</span>${personList(v)}</div>`;
  }
  function openCity(city, fly) {
    const v = ppl.filter(p => p.city === city); if (!v.length) return;
    openDrawer(`${head(T('Né(e)s à', 'Born in') + ' · ' + (v[0].country || ''), city)}<div class="cd-body">${originBody(v)}<a class="ccta" href="/nos-lions/">${T('Voir Nos Lions →', 'See Nos Lions →')}</a></div>`);
    if (fly) map.flyTo(v[0].ll, Math.max(map.getZoom(), 8), { duration: reduce ? 0 : 1 });
  }
  async function openCountry(iso) {
    const v = ppl.filter(p => p.iso === iso); if (!v.length) return;
    openDrawer(`${head(T('Nos Lions · par pays', 'Our Lions · by country'), cname[iso] || iso)}<div class="cd-body">${originBody(v)}<a class="ccta" href="/nos-lions/">${T('Voir Nos Lions →', 'See Nos Lions →')}</a></div>`);
    const lnd = await loadLand(); const f = lnd.features.find(x => x.id === iso); if (f) map.flyToBounds(L.geoJSON(f).getBounds(), { paddingTopLeft: [40, 40], paddingBottomRight: [420, 40], maxZoom: 6, duration: reduce ? 0 : 1 });
  }

  // ===== VUE · ÉPOPÉES =====
  let routes = [];
  const curve = (a, b) => { const pts = []; const dx = b[1] - a[1], dy = b[0] - a[0]; const c = [(a[0] + b[0]) / 2 + dx * .22, (a[1] + b[1]) / 2 - dy * .22]; for (let t = 0; t <= 1.0001; t += .04) pts.push([(1 - t) * (1 - t) * a[0] + 2 * (1 - t) * t * c[0] + t * t * b[0], (1 - t) * (1 - t) * a[1] + 2 * (1 - t) * t * c[1] + t * t * b[1]]); return pts; };
  const visSteps = () => steps.map((s, i) => ({ ...s, i })).filter(s => (S.etype === 'Toutes' || s.type === S.etype) && (S.eseason === 'Toutes' || s.s === S.eseason));
  function tabEpopees() {
    const seasonsE = [...new Set(steps.map(s => s.s))];
    const types = [...new Set(steps.map(s => s.type))];
    panel.innerHTML = `<div class="cp-head"><span class="cp-eyebrow">${T('Carto · Les épopées', 'Map · Epic runs')}</span><h1 class="cp-title">${T('Les grandes<br>routes du Lion', 'The Lion’s<br>great journeys')}</h1><p class="cp-intro">${T('Finales et nuits européennes, reliées à Montbéliard. Choisissez une étape ou lancez le récit.', 'Finals and European nights, linked to Montbéliard. Pick a step or play the story.')}</p></div>
    <div class="cp-filters">${chipRow('fEType', T('Compétition', 'Competition'), ['Toutes', ...types], S.etype, o => o === 'Toutes' ? T('Toutes', 'All') : o)}${chipRow('fESeason', T('Saison', 'Season'), ['Toutes', ...seasonsE], S.eseason, o => o === 'Toutes' ? T('Toutes', 'All') : o)}<button type="button" class="ccta" id="cplayStory" data-play="${T('▶ Lancer le récit', '▶ Play the story')}">${T('▶ Lancer le récit', '▶ Play the story')}</button></div>
    <div class="cp-steps" id="csteps"></div>`;
    bindChips('fEType', 'etype', updEpopees); bindChips('fESeason', 'eseason', updEpopees);
    panel.querySelector('#cplayStory').onclick = () => { if (S.timer) return stopTimer(); const vs = visSteps(); if (!vs.length) return; let k = 0; goStep(vs[0].i); panel.querySelector('#cplayStory').textContent = T('❚❚ Pause', '❚❚ Pause'); S.timer = setInterval(() => { k++; if (k >= vs.length) return stopTimer(); goStep(vs[k].i); }, 4200); };
    legend.innerHTML = `<span class="d">${T('Légende', 'Legend')}</span><div class="clg"><span class="csw" style="background:${C.blue}"></span>Montbéliard</div><div class="clg"><span class="csw" style="background:${C.yellow}"></span>${T('Trophée remporté', 'Trophy won')}</div><div class="clg"><span class="csw" style="background:${C.paper}"></span>${T('Étape', 'Step')}</div>`;
    const b = L.latLngBounds([HOME, ...steps.map(s => s.ll)]);
    home = () => map.flyToBounds(b, { padding: [70, 70], duration: reduce ? 0 : 1 });
    map.fitBounds(b, { padding: [70, 70] });
    L.circleMarker(HOME, { radius: 11, color: C.navy, weight: 2, fillColor: C.blue, fillOpacity: 1 }).bindTooltip('Montbéliard', { permanent: true, className: 'lbl', direction: 'right', offset: [10, 0] }).addTo(layer);
    updEpopees();
  }
  function updEpopees() {
    const vs = visSteps(); routes.forEach(r => { layer.removeLayer(r.line); layer.removeLayer(r.dot); }); routes = [];
    vs.forEach(s => {
      const line = L.polyline(curve(HOME, s.ll), { color: C.blue, weight: 2, opacity: .5, dashArray: '4 7', className: 'route' }).addTo(layer);
      const dot = L.circleMarker(s.ll, { radius: 9, color: C.navy, weight: 2, fillColor: s.win ? C.yellow : C.paper, fillOpacity: 1 }).addTo(layer).bindTooltip(`<b>${s.y} · ${esc(s.t)}</b>${esc(s.h)}`, { className: 'tt', direction: 'top', offset: [0, -8] });
      dot.on('click', () => { stopTimer(); goStep(s.i); });
      routes.push({ i: s.i, line, dot });
    });
    panel.querySelector('#csteps').innerHTML = vs.map(s => `<button type="button" class="cstep${S.step === s.i ? ' on' : ''}" data-i="${s.i}"><span class="n">${s.y}</span><span><span class="c-name">${esc(s.t)}</span><br><span class="c-meta">${esc(s.h)} · ${esc(s.s)}${s.win ? ' · ' + T('trophée', 'trophy') + ' ★' : ''}</span></span></button>`).join('') || `<p class="cp-intro" style="padding:16px">${T('Aucune étape pour ces filtres.', 'No step for these filters.')}</p>`;
    panel.querySelectorAll('.cstep').forEach(b => { b.onclick = () => { stopTimer(); goStep(+b.dataset.i); }; });
  }
  function goStep(i) {
    S.step = i; const s = steps[i];
    panel.querySelectorAll('.cstep').forEach(b => b.classList.toggle('on', +b.dataset.i === i));
    routes.forEach(r => { const on = r.i === i; r.line.setStyle({ color: on ? C.yellow : C.blue, weight: on ? 6 : 2, opacity: on ? 1 : .35, dashArray: on ? null : '4 7' }); if (on) { r.line.bringToFront(); r.dot.bringToFront(); } });
    const r = routes.find(x => x.i === i); const el = r?.line.getElement();
    if (el && !reduce) { const Lg = el.getTotalLength(); el.style.transition = 'none'; el.style.strokeDasharray = Lg; el.style.strokeDashoffset = Lg; el.getBoundingClientRect(); el.style.transition = 'stroke-dashoffset 1.4s cubic-bezier(.6,0,.3,1)'; el.style.strokeDashoffset = 0; setTimeout(() => { el.style.strokeDasharray = ''; el.style.transition = ''; }, 1500); }
    map.flyToBounds(L.latLngBounds([HOME, s.ll]), { paddingTopLeft: [60, 60], paddingBottomRight: [60, 190], maxZoom: 9, duration: reduce ? 0 : 1.2 });
    story.innerHTML = `<span class="yr">${s.y}</span><div><h3>${esc(s.t)} · ${esc(s.h)}${s.win ? ' ★' : ''}</h3><p>${esc(s.p)}</p>${s.href ? `<a href="${esc(s.href)}" style="color:${C.yellow}">${T('Lire la fiche →', 'Read more →')}</a>` : ''}</div><div class="csnav"><button type="button" data-d="-1" aria-label="${T('Précédent', 'Previous')}">←</button><button type="button" data-d="1" aria-label="${T('Suivant', 'Next')}">→</button></div>`;
    story.classList.remove('show'); void story.offsetWidth; story.classList.add('show');
    story.querySelectorAll('button').forEach(bt => { bt.onclick = () => { stopTimer(); const vs = visSteps(); const k = vs.findIndex(x => x.i === S.step); const n = vs[(k + +bt.dataset.d + vs.length) % vs.length]; if (n) goStep(n.i); }; });
  }

  // ===== VUE · LIEUX =====
  const LT = { Stade: ['S', C.yellow], Formation: ['F', C.blue], Patrimoine: ['P', C.paper], Supporters: ['♥', C.navy] };
  const lmk = [];
  function tabLieux() {
    const types = Object.keys(LT).filter(k => lieux.some(l => l.t === k));
    panel.innerHTML = `<div class="cp-head"><span class="cp-eyebrow">${T('Carto · Les lieux du club', 'Map · Club places')}</span><h1 class="cp-title">${T('Le pays<br>du Lion', 'Lion<br>country')}</h1><p class="cp-intro">${T('Bonal, la formation, le patrimoine Peugeot et les lieux des supporters.', 'Bonal, the academy, Peugeot heritage and supporters’ places.')}</p></div>
    <div class="cp-filters">${chipRow('fLT', T('Type de lieu', 'Type of place'), ['Tous', ...types], S.ltype, o => o === 'Tous' ? T('Tous', 'All') : o)}</div>
    <div class="cp-listhead"><span id="clcount"></span><span style="color:var(--muted)">${T('lieux', 'places')}</span></div><div class="cp-list" id="clist"></div>`;
    bindChips('fLT', 'ltype', updLieux);
    legend.innerHTML = `<span class="d">${T('Types de lieux', 'Types of places')}</span>${Object.entries(LT).map(([k, [, c]]) => `<div class="clg"><span class="csw" style="background:${c}"></span>${k}</div>`).join('')}<div class="clg"><span class="csw" style="border-style:dashed"></span>${T('Position indicative', 'Approximate position')}</div>`;
    home = () => map.flyTo(HOME, 14, { duration: reduce ? 0 : 1 });
    map.setView(HOME, 14);
    lieux.forEach((l, i) => {
      const [g, c] = LT[l.t] || ['•', C.paper]; const fg = c === C.navy || c === C.blue ? C.cream : C.navy;
      const icon = L.divIcon({ className: '', html: `<div class="cpin${l.validated ? '' : ' ind'}" style="background:${c};color:${fg}"><span>${g}</span></div>`, iconSize: [34, 34], iconAnchor: [17, 34] });
      const m = L.marker(l.ll, { icon, title: l.n }).addTo(layer).bindTooltip(`<b>${esc(l.n)}</b>${esc(l.t)}`, { className: 'tt', direction: 'top', offset: [0, -34] });
      m.on('click', () => openLieu(i)); lmk[i] = m;
    });
    updLieux();
  }
  function updLieux() {
    const vis = lieux.map((l, i) => ({ l, i })).filter(({ l }) => S.ltype === 'Tous' || l.t === S.ltype);
    lieux.forEach((l, i) => { const show = vis.some(v => v.i === i); const el = lmk[i]?.getElement(); if (el) { el.style.opacity = show ? 1 : 0; el.style.pointerEvents = show ? 'auto' : 'none'; el.style.transition = 'opacity .3s'; } });
    panel.querySelector('#clcount').textContent = vis.length + ' ' + T('lieu', 'place') + (vis.length > 1 ? (EN ? 's' : 'x') : '');
    const list = panel.querySelector('#clist');
    list.innerHTML = vis.map(({ l, i }) => `<button type="button" class="crow" data-i="${i}"><span class="c-rank">${(LT[l.t] || ['•'])[0]}</span><span><span class="c-name">${esc(l.n)}</span><br><span class="c-meta">${esc(l.t)}${l.validated ? '' : ' · ' + T('position indicative', 'approximate position')}</span></span><span class="c-num">→</span></button>`).join('');
    list.querySelectorAll('.crow').forEach(r => { const i = +r.dataset.i; r.onclick = () => openLieu(i); });
  }
  function openLieu(i) {
    const l = lieux[i];
    openDrawer(`${head(l.t, l.n)}<div class="cd-body"><p class="cp-intro" style="font-size:18px;color:var(--navy)">${esc(l.d)}</p>${l.validated ? '' : `<span class="cnote">${T('Position indicative, à confirmer.', 'Approximate position, to be confirmed.')}</span>`}${l.href ? `<a class="ccta" href="${esc(l.href)}">${T('Voir dans le musée →', 'See in the museum →')}</a>` : ''}</div>`);
    map.flyTo(l.ll, 16, { duration: reduce ? 0 : 1 });
  }

  // ===== NAVIGATION ENTRE LES VUES =====
  function render() {
    stopTimer(); closeDrawer(); story.classList.remove('show'); S.step = -1; map.off('zoomend', onZoomLions); layer.clearLayers(); routes = [];
    ({ matchs: tabMatchs, lions: tabLions, epopees: tabEpopees, lieux: tabLieux })[S.tab]();
  }
  const tabs = root.querySelectorAll('.ctab');
  tabs.forEach(t => { t.onclick = () => { S.tab = t.dataset.tab; tabs.forEach(x => x.setAttribute('aria-selected', x === t ? 'true' : 'false')); try { history.replaceState(null, '', '?vue=' + S.tab); } catch (e) {} render(); }; });
  const v = new URLSearchParams(location.search).get('vue'); if (['matchs', 'lions', 'epopees', 'lieux'].includes(v)) { S.tab = v; tabs.forEach(x => x.setAttribute('aria-selected', x.dataset.tab === v ? 'true' : 'false')); }
  window.addEventListener('keydown', e => { if (e.key === 'Escape') closeDrawer(); });
  new ResizeObserver(() => map.invalidateSize()).observe(root.querySelector('.cmap'));
  render();
})();
