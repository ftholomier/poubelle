// Cartes : Leaflet (chargé à la demande) et fond de carte Plan IGN (Géoplateforme de l'État, gratuit, sans clé).
// Pas OpenStreetMap : ses serveurs refusent les demandes sans « Referer », et l'appli n'en envoie pas aux sites
// externes (Referrer-Policy: same-origin, pour que les liens personnels des clients ne fuient pas).

export function chargerLeaflet() {
  if (window.L) return Promise.resolve(window.L);
  return new Promise((ok, ko) => {
    const css = document.createElement("link");
    css.rel = "stylesheet";
    css.href = "https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css";
    document.head.append(css);
    const s = document.createElement("script");
    s.src = "https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js";
    s.onload = () => ok(window.L);
    s.onerror = ko;
    document.head.append(s);
    setTimeout(() => ko(new Error("délai")), 6000);
  });
}

const PLAN_IGN =
  "https://data.geopf.fr/wmts?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=GEOGRAPHICALGRIDSYSTEMS.PLANIGNV2" +
  "&STYLE=normal&TILEMATRIXSET=PM&FORMAT=image/png&TILEMATRIX={z}&TILECOL={x}&TILEROW={y}";

/** Ajoute le fond Plan IGN à une carte Leaflet. */
export function fondCarte(L, carte) {
  return L.tileLayer(PLAN_IGN, { maxZoom: 19, maxNativeZoom: 18, attribution: '© <a href="https://www.ign.fr/" target="_blank" rel="noopener">IGN</a> Plan IGN' }).addTo(carte);
}
