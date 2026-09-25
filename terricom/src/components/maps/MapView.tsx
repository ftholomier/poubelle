'use client';

import type * as Leaflet from 'leaflet';
import { useEffect, useRef, type CSSProperties } from 'react';

export type MapPoint = {
  id: string;
  lat: number;
  lng: number;
  name: string;
  color: string;
  subtitle?: string;
  image?: string | null;
  href?: string;
};

export type HeatPoint = { name: string; lat: number; lng: number; value: number };
export type LabelPoint = { name: string; lat: number; lng: number; color: string; tooltip?: string };

export type MapMode = 'explore' | 'mini' | 'fiche' | 'circuit' | 'heat' | 'france' | 'picker';

type Props = {
  mode: MapMode;
  tileUrl: string;
  attribution: string;
  points?: MapPoint[];
  heat?: HeatPoint[];
  labels?: LabelPoint[];
  /** explore : identifiants visibles (filtre) */
  visibleIds?: string[] | null;
  /** explore : point à mettre en avant (survol de la liste) ; fiche : établissement affiché */
  focusId?: string | null;
  wheel?: boolean;
  center?: [number, number];
  zoom?: number;
  className?: string;
  style?: CSSProperties;
  ariaLabel?: string;
  /** picker : position choisie par glisser-déposer */
  onPick?: (lat: number, lng: number) => void;
};

/** Au-delà de ce nombre de repères, ils sont regroupés (lisibilité et performances). */
const CLUSTER_THRESHOLD = 80;

const esc = (s: string) =>
  s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!);

function popupHtml(p: MapPoint): string {
  return `<div class="tc-popup">${p.image ? `<img src="${esc(p.image)}" alt="" loading="lazy">` : ''}<div class="tc-popup-body"><b>${esc(p.name)}</b>${
    p.subtitle ? `<small>${esc(p.subtitle)}</small>` : ''
  }${p.href ? `<a href="${esc(p.href)}">Voir la fiche</a>` : ''}</div></div>`;
}

/**
 * Carte interactive libre (OpenStreetMap ou fond configurable) aux repères de la marque.
 * Reprend les modes de la maquette : exploration, mini-carte, fiche, circuit, densité, France.
 */
export function MapView(props: Props) {
  const el = useRef<HTMLDivElement>(null);
  const mapRef = useRef<Leaflet.Map | null>(null);
  const LRef = useRef<typeof Leaflet | null>(null);
  const markers = useRef(new Map<string, Leaflet.Marker>());
  const clusterRef = useRef<Leaflet.MarkerClusterGroup | null>(null);
  const propsRef = useRef(props);
  // Les dernières props sont lues par les gestionnaires Leaflet (glisser-déposer du repère…).
  useEffect(() => {
    propsRef.current = props;
  });

  // Création de la carte (une seule fois)
  useEffect(() => {
    let cancelled = false;
    (async () => {
      const L = (await import('leaflet')).default;
      const p0 = propsRef.current;
      const wantCluster = (p0.mode === 'explore' || p0.mode === 'mini') && (p0.points?.length ?? 0) > CLUSTER_THRESHOLD;
      if (wantCluster) {
        // Le greffon de regroupement s'accroche à l'objet global L.
        (window as unknown as { L: typeof Leaflet }).L = L;
        await import('leaflet.markercluster');
      }
      if (cancelled || !el.current || mapRef.current) return;
      LRef.current = L;
      const p = propsRef.current;
      const map = L.map(el.current, {
        scrollWheelZoom: Boolean(p.wheel),
        zoomControl: p.mode !== 'mini',
        attributionControl: true,
      });
      mapRef.current = map;
      L.tileLayer(p.tileUrl, { attribution: p.attribution, maxZoom: 19 }).addTo(map);
      const icon = (pt: MapPoint, big = false, dim = false) =>
        L.divIcon({
          className: '',
          iconSize: big ? [44, 44] : [30, 30],
          iconAnchor: big ? [22, 44] : [15, 30],
          popupAnchor: [0, big ? -40 : -28],
          html: `<div class="tc-pin${big ? ' big' : ''}${dim ? ' dim' : ''}" style="--c:${esc(pt.color)}"><span>${esc(pt.name.charAt(0))}</span></div>`,
        });
      const pts = (p.points ?? []).filter((x) => Number.isFinite(x.lat) && Number.isFinite(x.lng));

      if (p.mode === 'explore' || p.mode === 'mini') {
        const visible = p.visibleIds ? new Set(p.visibleIds) : null;
        const cluster = wantCluster
          ? L.markerClusterGroup({
              showCoverageOnHover: false,
              maxClusterRadius: 46,
              spiderfyOnMaxZoom: true,
              chunkedLoading: true,
              iconCreateFunction: (c) =>
                L.divIcon({ className: '', iconSize: [42, 42], html: `<div class="tc-cluster"><span>${c.getChildCount()}</span></div>` }),
            })
          : null;
        const initial: Leaflet.Marker[] = [];
        for (const pt of pts) {
          const m = L.marker([pt.lat, pt.lng], { icon: icon(pt), title: pt.name }).bindPopup(popupHtml(pt));
          markers.current.set(pt.id, m);
          if (!visible || visible.has(pt.id)) initial.push(m);
        }
        if (cluster) {
          cluster.addLayers(initial);
          map.addLayer(cluster);
          clusterRef.current = cluster;
        } else initial.forEach((m) => m.addTo(map));
        if (pts.length) map.fitBounds(L.latLngBounds(pts.map((x) => [x.lat, x.lng])), { padding: [40, 40] });
        else map.setView(p.center ?? [46.6, 2.6], p.zoom ?? 11);
      } else if (p.mode === 'fiche') {
        const focus = pts.find((x) => x.id === p.focusId) ?? pts[0];
        for (const pt of pts) {
          if (pt === focus) continue;
          L.marker([pt.lat, pt.lng], { icon: icon(pt, false, true) }).bindPopup(popupHtml(pt)).addTo(map);
        }
        if (focus) {
          L.marker([focus.lat, focus.lng], { icon: icon(focus, true), zIndexOffset: 1000, title: focus.name }).addTo(map);
          map.setView([focus.lat, focus.lng], p.zoom ?? 15);
        } else map.setView(p.center ?? [46.6, 2.6], p.zoom ?? 13);
      } else if (p.mode === 'circuit') {
        if (pts.length > 1)
          L.polyline(
            pts.map((s) => [s.lat, s.lng] as [number, number]),
            { color: '#14201B', weight: 4, opacity: 0.8, dashArray: '2 9', lineCap: 'round' },
          ).addTo(map);
        pts.forEach((s, i) =>
          L.marker([s.lat, s.lng], {
            icon: L.divIcon({ className: '', iconSize: [34, 34], iconAnchor: [17, 17], html: `<div class="tc-num">${i + 1}</div>` }),
            title: s.name,
          })
            .bindPopup(popupHtml(s))
            .addTo(map),
        );
        if (pts.length) map.fitBounds(L.latLngBounds(pts.map((x) => [x.lat, x.lng])), { padding: [50, 50] });
        else map.setView(p.center ?? [46.6, 2.6], 11);
      } else if (p.mode === 'heat') {
        const heat = p.heat ?? [];
        for (const h of heat) {
          L.circle([h.lat, h.lng], {
            radius: Math.sqrt(h.value) * 230,
            color: '#1F6B52',
            weight: 1.5,
            fillColor: h.value > 80 ? '#1F6B52' : h.value > 35 ? '#5FA37E' : '#A9D1B7',
            fillOpacity: 0.45,
          }).addTo(map);
          L.marker([h.lat, h.lng], {
            icon: L.divIcon({
              className: '',
              iconSize: [120, 30],
              iconAnchor: [60, 15],
              html: `<div class="tc-cnt">${h.value}<br><small>${esc(h.name)}</small></div>`,
            }),
            interactive: false,
          }).addTo(map);
        }
        if (heat.length) map.fitBounds(L.latLngBounds(heat.map((c) => [c.lat, c.lng])), { padding: [30, 30] });
        else map.setView(p.center ?? [46.6, 2.6], 10);
      } else if (p.mode === 'france') {
        for (const t of p.labels ?? []) {
          const m = L.marker([t.lat, t.lng], {
            icon: L.divIcon({ className: '', iconSize: [0, 0], html: `<div class="tc-terr" style="--c:${esc(t.color)}">${esc(t.name)}</div>` }),
          }).addTo(map);
          if (t.tooltip) m.bindTooltip(esc(t.tooltip));
        }
        map.setView(p.center ?? [46.6, 2.6], p.zoom ?? 6);
      } else if (p.mode === 'picker') {
        const start = p.center ?? [46.6, 2.6];
        const pt: MapPoint = { id: 'pick', lat: start[0], lng: start[1], name: '•', color: '#1F6B52' };
        const m = L.marker(start, { icon: icon(pt, true), draggable: true }).addTo(map);
        m.on('dragend', () => {
          const ll = m.getLatLng();
          propsRef.current.onPick?.(ll.lat, ll.lng);
        });
        map.setView(start, p.zoom ?? 15);
      }
    })();
    const markerMap = markers.current;
    return () => {
      cancelled = true;
      mapRef.current?.remove();
      mapRef.current = null;
      clusterRef.current = null;
      markerMap.clear();
    };
  }, []);

  // Filtre des repères visibles (explore)
  const visibleKey = props.visibleIds ? props.visibleIds.join(',') : '*';
  useEffect(() => {
    const map = mapRef.current;
    if (!map || (props.mode !== 'explore' && props.mode !== 'mini')) return;
    const visible = props.visibleIds ? new Set(props.visibleIds) : null;
    const cluster = clusterRef.current;
    if (cluster) {
      const toAdd: Leaflet.Marker[] = [];
      const toRemove: Leaflet.Marker[] = [];
      for (const [id, m] of markers.current) {
        const on = !visible || visible.has(id);
        if (on && !cluster.hasLayer(m)) toAdd.push(m);
        if (!on && cluster.hasLayer(m)) toRemove.push(m);
      }
      if (toRemove.length) cluster.removeLayers(toRemove);
      if (toAdd.length) cluster.addLayers(toAdd);
      return;
    }
    for (const [id, m] of markers.current) {
      const on = !visible || visible.has(id);
      if (on && !map.hasLayer(m)) m.addTo(map);
      if (!on && map.hasLayer(m)) map.removeLayer(m);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visibleKey, props.mode]);

  // Mise en avant d'un repère (survol d'un résultat)
  useEffect(() => {
    const map = mapRef.current;
    if (!map || props.mode !== 'explore' || !props.focusId) return;
    const m = markers.current.get(props.focusId);
    if (!m) return;
    const cluster = clusterRef.current;
    if (cluster) {
      if (!cluster.hasLayer(m)) return;
      cluster.zoomToShowLayer(m, () => m.openPopup());
      return;
    }
    map.flyTo(m.getLatLng(), Math.max(map.getZoom(), 14), { duration: 0.6 });
    m.openPopup();
  }, [props.focusId, props.mode]);

  return (
    <div
      ref={el}
      className={`map-root ${props.className ?? ''}`}
      style={props.style}
      role="region"
      aria-label={props.ariaLabel ?? 'Carte interactive'}
    />
  );
}
