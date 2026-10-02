/*
 * Explorateur : liste à gauche, carte synchronisée à droite (principe de l'explorateur terricom).
 *  - la carte reçoit UNE fois tous les points ; les repères sont créés une seule fois (table id → repère)
 *  - la liste reçoit une page de résultats + la liste complète des identifiants filtrés
 *  - survol ou focus d'un résultat → la carte s'y déplace, ouvre le regroupement et la bulle
 *  - chaque filtre relance la recherche ; la carte ne garde que les repères visibles et se recadre
 *  - l'adresse suit les filtres (history.replaceState) ; les réponses arrivées dans le désordre sont ignorées
 */
(() => {
  'use strict';
  const root = document.querySelector('[data-explore]');
  if (!root) return;
  const APVS = window.APVS || {};
  const esc = APVS.esc || ((s) => String(s));
  const $ = (s, r = root) => r.querySelector(s);
  const $$ = (s, r = root) => Array.from(r.querySelectorAll(s));

  const CLUSTER_THRESHOLD = 80;
  const PER = 24;
  const state = Object.assign({ q: '', cat: '', occasion: '', dep: '', region: '', insee: '', ou: '', radius: 40, sort: '', photo: false, reviews: false, lat: null, lng: null }, JSON.parse(root.dataset.state || '{}'));
  const isSearchPage = root.dataset.searchPage === '1';
  const list = $('[data-results]');
  const countEl = $('[data-count]');
  const countLabel = $('[data-count-label]');
  const emptyEl = $('[data-empty]');
  const moreBtn = $('[data-more]');
  const moreCount = $('[data-more-count]');
  const mapEl = $('[data-map]');
  const pointsEl = document.getElementById('map-points');
  const points = pointsEl ? JSON.parse(pointsEl.textContent || '[]') : [];
  let total = Number(root.dataset.total || 0);
  let loaded = $$('.result-card', list).length;
  let visibleIds = root.dataset.filtered === '1' ? JSON.parse(root.dataset.ids || '[]') : null;
  let focusIds = root.dataset.filtered === '1' ? JSON.parse(root.dataset.focus || 'null') : null; // pros situés sur le lieu demandé
  let center = root.dataset.filtered === '1' ? JSON.parse(root.dataset.center || 'null') : null; // ville ou position demandée
  let reqId = 0;

  /* --------------------------------------------------------------- carte */
  let L = null;
  let map = null;
  let cluster = null;
  const markers = new Map();
  let focusedPin = null;

  const popupHtml = (p) => `<div class="map-pop" style="--c:${esc(p.color)}">${p.img ? `<img src="${esc(p.img)}" alt="" loading="lazy">` : '<div class="ph"></div>'}<div class="in"><b>${esc(p.name)}</b><small>${esc(p.sub)}</small><a class="go" href="${esc(p.url)}?src=carte">Voir la fiche</a></div></div>`;

  // France métropolitaine (Corse comprise) : vue par défaut de la carte
  const FRANCE = [[41.3, -5.3], [51.2, 9.7]];
  const inFrance = (ll) => ll.lat >= 41.3 && ll.lat <= 51.2 && ll.lng >= -5.3 && ll.lng <= 9.7;
  const fit = (animate) => {
    if (!map) return;
    if (!visibleIds) { map.fitBounds(FRANCE, { padding: [10, 10], animate: !!animate }); return; } // sans filtre : la France
    // lieu demandé (département, région, ville, autour de moi) : on cadre sur les pros situés sur place ;
    // s'il n'y en a aucun, on montre la ville ou la position demandée
    const local = focusIds && focusIds.length ? focusIds : null;
    if (!local && center) { map.setView([center.lat, center.lng], 10, { animate: !!animate }); return; }
    const set = new Set(local || visibleIds);
    let pts = [];
    markers.forEach((m, id) => { if (set.has(id)) pts.push(m.getLatLng()); });
    // les résultats d'outre-mer (ou des coordonnées aberrantes) ne doivent pas faire dézoomer sur le globe :
    // s'il y a des résultats en métropole, on cadre sur eux ; sinon (recherche à La Réunion…) sur les autres
    const metro = pts.filter(inFrance);
    if (metro.length) pts = metro;
    if (pts.length) map.fitBounds(L.latLngBounds(pts), { padding: [40, 40], maxZoom: 12, animate: !!animate });
    else map.fitBounds(FRANCE, { padding: [10, 10], animate: !!animate });
  };

  const applyVisible = () => {
    if (!map) return;
    const set = visibleIds ? new Set(visibleIds) : null;
    if (cluster) {
      const add = [];
      const del = [];
      markers.forEach((m, id) => {
        const on = !set || set.has(id);
        if (on && !cluster.hasLayer(m)) add.push(m);
        if (!on && cluster.hasLayer(m)) del.push(m);
      });
      if (del.length) cluster.removeLayers(del);
      if (add.length) cluster.addLayers(add);
    } else {
      markers.forEach((m, id) => {
        const on = !set || set.has(id);
        if (on && !map.hasLayer(m)) m.addTo(map);
        if (!on && map.hasLayer(m)) map.removeLayer(m);
      });
    }
    fit(true);
  };

  const highlightCard = (id, on) => {
    const card = list.querySelector(`.result-card[data-id="${id}"]`);
    if (card) card.classList.toggle('is-focus', on);
  };

  const setPinFocus = (m) => {
    if (focusedPin && focusedPin._icon) focusedPin._icon.querySelector('.apvs-pin')?.classList.remove('is-focus');
    focusedPin = m;
    if (m && m._icon) m._icon.querySelector('.apvs-pin')?.classList.add('is-focus');
  };

  // Partie de la carte cachée sous l'en-tête collant ou sous le bas de l'écran (page défilée) :
  // la bulle s'ouvre toujours dans la zone réellement visible.
  const keepPopupVisible = (m) => {
    const popup = m.getPopup();
    if (!popup) return;
    const header = document.querySelector('.site-header');
    const r = mapEl.getBoundingClientRect();
    const top = Math.max(0, Math.round((header ? header.getBoundingClientRect().bottom : 0) - r.top));
    const bottom = Math.max(0, Math.round(r.bottom - window.innerHeight));
    popup.options.autoPanPaddingTopLeft = L.point(30, 30 + top);
    popup.options.autoPanPaddingBottomRight = L.point(30, 30 + bottom);
  };
  const initMap = async () => {
    if (map || !mapEl) return;
    L = await APVS.loadLeaflet();
    const wantCluster = points.length > CLUSTER_THRESHOLD;
    map = L.map(mapEl, { scrollWheelZoom: true, zoomControl: true, attributionControl: true, preferCanvas: false });
    L.tileLayer(APVS.cfg.map.tiles, { attribution: APVS.cfg.map.attribution, maxZoom: 19, subdomains: 'abc' }).addTo(map);
    cluster = wantCluster ? L.markerClusterGroup({
      showCoverageOnHover: false,
      maxClusterRadius: 46,
      spiderfyOnMaxZoom: true,
      chunkedLoading: true,
      iconCreateFunction: (c) => L.divIcon({ className: '', iconSize: [44, 44], html: `<div class="apvs-cluster"><span>${c.getChildCount()}</span></div>` }),
    }) : null;
    const set = visibleIds ? new Set(visibleIds) : null;
    const initial = [];
    for (const p of points) {
      if (!Number.isFinite(p.lat) || !Number.isFinite(p.lng)) continue;
      const m = L.marker([p.lat, p.lng], { icon: APVS.pinIcon(L, p.color, (p.name || '?').charAt(0).toUpperCase(), false), title: p.name, riseOnHover: true })
        .bindPopup(popupHtml(p), { maxWidth: 260, autoPanPadding: [30, 30] });
      m.on('mouseover', () => { highlightCard(p.id, true); keepPopupVisible(m); });
      m.on('mousedown', () => keepPopupVisible(m));
      m.on('mouseout', () => highlightCard(p.id, false));
      m.on('popupopen', () => setPinFocus(m));
      markers.set(p.id, m);
      if (!set || set.has(p.id)) initial.push(m);
    }
    if (cluster) { cluster.addLayers(initial); map.addLayer(cluster); }
    else initial.forEach((m) => m.addTo(map));
    fit(false);
  };

  // La carte est créée à l'affichage (desktop immédiatement, mobile au premier clic sur « Carte »).
  const mapVisible = () => mapEl && mapEl.offsetParent !== null;
  if (mapVisible()) initMap();
  else if ('IntersectionObserver' in window && mapEl) {
    const io = new IntersectionObserver((en) => { if (en.some((x) => x.isIntersecting)) { io.disconnect(); initMap(); } });
    io.observe(mapEl);
  }

  /* -------------------------------------------------- survol de la liste → carte */
  let focusTimer = null;
  const focus = (id) => {
    if (!map) return;
    const m = markers.get(id);
    if (!m) return;
    keepPopupVisible(m);
    if (cluster) {
      if (!cluster.hasLayer(m)) return; // repère masqué par le filtre
      cluster.zoomToShowLayer(m, () => { m.openPopup(); setPinFocus(m); });
      return;
    }
    map.flyTo(m.getLatLng(), Math.max(map.getZoom(), 11), { duration: 0.6 });
    m.openPopup();
    setPinFocus(m);
  };
  const onEnter = (e) => {
    const card = e.target.closest('.result-card');
    if (!card || !list.contains(card)) return;
    const id = Number(card.dataset.id);
    clearTimeout(focusTimer);
    focusTimer = setTimeout(() => focus(id), 110);
  };
  list.addEventListener('mouseover', onEnter);
  list.addEventListener('focusin', onEnter);
  list.addEventListener('mouseleave', () => clearTimeout(focusTimer));

  /* ----------------------------------------------------------- recherche */
  const params = (extra = {}) => {
    const u = new URLSearchParams();
    if (state.q) u.set('q', state.q);
    if (state.cat) u.set('cat', state.cat);
    if (state.occasion) u.set('occasion', state.occasion);
    if (state.insee) { u.set('insee', state.insee); if (state.ou) u.set('ou', state.ou); }
    else if (state.dep) u.set('dep', state.dep);
    else if (state.region) u.set('region', state.region);
    if ((state.insee || state.lat) && state.radius) u.set('rayon', String(state.radius));
    if (state.lat && state.lng) { u.set('lat', Number(state.lat).toFixed(4)); u.set('lng', Number(state.lng).toFixed(4)); }
    if (state.photo) u.set('photo', '1');
    if (state.reviews) u.set('avis', '1');
    if (state.sort) u.set('tri', state.sort);
    Object.entries(extra).forEach(([k, v]) => u.set(k, String(v)));
    return u;
  };
  const filtered = () => !!(state.q || state.cat || state.occasion || state.insee || state.dep || state.region || state.photo || state.reviews || state.lat);

  const paintChips = () => {
    $$('[data-cat]').forEach((b) => { const on = b.dataset.cat === (state.cat || ''); b.classList.toggle('is-on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    $$('[data-occasion]').forEach((b) => { const on = b.dataset.occasion === state.occasion; b.classList.toggle('is-on', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    $$('[data-toggle]').forEach((b) => { const k = b.dataset.toggle === 'avis' ? 'reviews' : 'photo'; b.classList.toggle('is-on', !!state[k]); b.setAttribute('aria-pressed', state[k] ? 'true' : 'false'); });
    const radius = $('[data-radius]');
    if (radius) radius.classList.toggle('hidden', !(state.insee || state.lat));
  };

  const setCount = () => {
    if (countEl) countEl.textContent = total.toLocaleString('fr-FR');
    if (countLabel) countLabel.textContent = total > 1 ? 'pros' : 'pro';
    if (emptyEl) emptyEl.classList.toggle('hidden', total > 0);
    if (moreBtn) moreBtn.classList.toggle('hidden', loaded >= total);
    if (moreCount) moreCount.textContent = Math.max(0, total - loaded).toLocaleString('fr-FR');
  };

  const run = async () => {
    const id = ++reqId;
    const qs = params();
    // L'adresse suit les filtres (partage / rechargement) ; sur les pages SEO on bascule vers /recherche/.
    const url = (isSearchPage || filtered()) ? '/recherche/' + (qs.toString() ? '?' + qs : '') : location.pathname;
    try { history.replaceState(history.state, '', url); } catch (e) { /* ignore */ }
    list.classList.add('is-loading');
    try {
      const r = await fetch('/api/pros?' + params({ offset: 0, per: PER }), { headers: { Accept: 'application/json' } });
      const d = await r.json();
      if (id !== reqId) return; // réponse obsolète
      if (!r.ok) throw new Error(d.error || 'Recherche impossible');
      list.innerHTML = d.html;
      total = d.total;
      loaded = d.count;
      visibleIds = filtered() ? d.ids : null;
      focusIds = filtered() ? d.focus : null;
      center = filtered() ? d.center : null;
      setCount();
      APVS.paintFavs && APVS.paintFavs();
      APVS.pushAds && APVS.pushAds();
      applyVisible();
      list.scrollTop = 0;
      root.querySelector('.explore-list').scrollTop = 0;
    } catch (e) {
      if (id === reqId) APVS.toast && APVS.toast(e.message || 'Recherche impossible');
    } finally {
      if (id === reqId) list.classList.remove('is-loading');
    }
  };

  const loadMore = async (e) => {
    e.preventDefault();
    moreBtn.classList.add('is-loading');
    try {
      const r = await fetch('/api/pros?' + params({ offset: loaded, per: PER }), { headers: { Accept: 'application/json' } });
      const d = await r.json();
      list.insertAdjacentHTML('beforeend', d.html);
      loaded += d.count;
      setCount();
      APVS.paintFavs && APVS.paintFavs();
    } catch (er) { APVS.toast && APVS.toast('Chargement impossible'); }
    moreBtn.classList.remove('is-loading');
  };
  if (moreBtn) moreBtn.addEventListener('click', loadMore);

  /* ---------------------------------------------------------- filtres */
  const form = $('[data-explore-form]');
  const qInput = form ? form.querySelector('input[name="q"]') : null;
  const ouInput = form ? form.querySelector('input[name="ou"]') : null;
  let qTimer = null;
  if (qInput) {
    qInput.addEventListener('input', () => {
      clearTimeout(qTimer);
      const v = qInput.value.trim();
      qTimer = setTimeout(() => { if (v !== state.q) { state.q = v; run(); } }, v.length > 8 ? 550 : 300);
    });
  }
  if (ouInput) {
    ouInput.addEventListener('commune:chosen', (e) => {
      const it = e.detail;
      state.insee = it.insee;
      state.ou = it.label;
      state.dep = '';
      state.region = '';
      state.lat = null;
      state.lng = null;
      paintChips();
      run();
    });
    ouInput.addEventListener('input', () => {
      if (ouInput.value.trim() === '' && state.insee) { state.insee = ''; state.ou = ''; paintChips(); run(); }
    });
  }
  if (form) form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (qInput) state.q = qInput.value.trim();
    run();
  });
  root.addEventListener('click', (e) => {
    const cat = e.target.closest('[data-cat]');
    if (cat) { state.cat = cat.dataset.cat; paintChips(); run(); return; }
    const occ = e.target.closest('[data-occasion]');
    if (occ) { state.occasion = state.occasion === occ.dataset.occasion ? '' : occ.dataset.occasion; paintChips(); run(); return; }
    const tg = e.target.closest('[data-toggle]');
    if (tg) { const k = tg.dataset.toggle === 'avis' ? 'reviews' : 'photo'; state[k] = !state[k]; paintChips(); run(); return; }
    const near = e.target.closest('[data-near]');
    if (near) {
      if (!('geolocation' in navigator)) { APVS.toast && APVS.toast('Géolocalisation indisponible'); return; }
      near.classList.add('is-loading');
      navigator.geolocation.getCurrentPosition((pos) => {
        near.classList.remove('is-loading');
        state.lat = pos.coords.latitude;
        state.lng = pos.coords.longitude;
        state.insee = '';
        state.ou = '';
        state.dep = '';
        state.region = '';
        state.sort = 'distance';
        if (ouInput) ouInput.value = 'Autour de moi';
        const sortSel = $('[data-sort]');
        if (sortSel) sortSel.value = 'distance';
        paintChips();
        run();
      }, () => { near.classList.remove('is-loading'); APVS.toast && APVS.toast('Position indisponible'); }, { enableHighAccuracy: false, timeout: 8000, maximumAge: 300000 });
      return;
    }
    const tm = e.target.closest('[data-toggle-map]');
    if (tm) {
      const hidden = root.classList.toggle('map-hidden');
      tm.querySelector('span').textContent = hidden ? 'Carte' : 'Liste';
      if (!hidden) { initMap().then(() => setTimeout(() => { map && map.invalidateSize(); fit(false); }, 60)); }
    }
  });
  const sortSel = $('[data-sort]');
  if (sortSel) sortSel.addEventListener('change', () => { state.sort = sortSel.value; run(); });
  const radiusSel = $('[data-radius]');
  if (radiusSel) radiusSel.addEventListener('change', () => { state.radius = Number(radiusSel.value); run(); });

  setCount();
  paintChips();
  if (!root.classList.contains('map-hidden') || window.matchMedia('(min-width: 901px)').matches) {
    const t = root.querySelector('[data-toggle-map] span');
    if (t && !root.classList.contains('map-hidden')) t.textContent = 'Liste';
  }
})();
