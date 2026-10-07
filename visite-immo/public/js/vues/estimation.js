// Estimer un bien en temps réel : adresse + caractéristiques → ventes réelles de biens similaires (DVF),
// prix, fourchette, confiance, tendance du marché. Le résultat se met à jour à chaque changement.

import { api } from "../api.js";
import { vues, ecran, esc, fmtEuros, go, copier } from "../ui.js";

const ETATS = ["À rénover", "Travaux à prévoir", "Bon état", "Très bon état", "Refait à neuf"];
const DPE = { A: "#009c6d", B: "#52b153", C: "#a5cc74", D: "#f4e70f", E: "#f2a541", F: "#eb8235", G: "#d7221f" };

function chargerLeaflet() {
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

/** Prix médian au m² par année : barres fines, valeur au survol et en texte sous chaque barre. */
export function graphiqueTendance(t) {
  const ans = Object.keys(t?.par_annee || {});
  if (ans.length < 2) return "";
  const vals = ans.map((a) => t.par_annee[a]);
  const max = Math.max(...vals), min = Math.min(...vals) * 0.8;
  const pct = (t.annuelle * 100).toFixed(1).replace(".", ",");
  return `<div class="tendance"><div class="tendance-tete"><span>Prix médian au m² par année</span><strong class="${t.annuelle >= 0 ? "vert-txt" : "orange-txt"}">${t.annuelle >= 0 ? "+" : ""}${pct} %/an</strong></div>
    <div class="tendance-barres" role="img" aria-label="Prix médian au m² par année">${ans
      .map((a, i) => `<div title="${a} : ${fmtEuros(vals[i])}/m²"><span style="height:${12 + ((vals[i] - min) / (max - min || 1)) * 88}%"></span><b>${Math.round(vals[i] / 10) * 10}</b><small>${a}</small></div>`)
      .join("")}</div></div>`;
}

/** Carte de résultat (partagée avec l'écran d'avis de valeur du dossier). */
export function blocEstimation(e, { simulation = false } = {}) {
  if (!e) return `<p class="muted">Pas assez de ventes comparables pour estimer : précisez la surface et le type de bien.</p>`;
  const pos = Math.max(4, Math.min(96, ((e.prix - e.bas) / (e.haut - e.bas || 1)) * 100));
  const conf = e.confiance;
  return `
    <div class="estim-prix"><small>Estimation</small><strong data-prix>${fmtEuros(e.prix)}</strong>
      <span class="muted">${fmtEuros(e.prix_m2)}/m² · ${e.surface} m²</span></div>
    <div class="fourchette"><div class="fourchette-barre"><span style="left:${pos}%"></span></div>
      <div class="fourchette-bornes"><span>${fmtEuros(e.bas)}</span><span>${fmtEuros(e.haut)}</span></div></div>
    <div class="estim-infos">
      <span class="confiance confiance-${conf.niveau === "élevée" ? "haute" : conf.niveau === "moyenne" ? "moyenne" : "basse"}">${conf.niveau === "élevée" ? "●●●" : conf.niveau === "moyenne" ? "●●○" : "●○○"} Confiance ${esc(conf.niveau)}</span>
      <span>${e.comparables.length} ventes similaires sur ${e.nb_ventes_secteur}</span>
      ${e.communes_voisines?.length ? `<span class="badge">+ communes voisines</span>` : ""}
      ${simulation ? '<span class="badge orange">ventes simulées (service indisponible)</span>' : ""}
    </div>
    ${e.communes_voisines?.length ? `<p class="small muted">Peu de ventes dans la commune : ventes de ${e.communes_voisines.map(esc).join(", ")} ajoutées (les plus proches pèsent le plus).</p>` : ""}
    ${e.ajustements.length ? `<p class="small muted">Ajustements : ${e.ajustements.map((x) => `${esc(x[0])} ${x[1] > 0 ? "+" : ""}${Math.round(x[1] * 100)} %`).join(" · ")}</p>` : ""}
    ${e.ecart_vendeur != null ? `<p class="small ${Math.abs(e.ecart_vendeur) >= 5 ? "orange-txt" : ""}">Prix souhaité par le vendeur : ${e.ecart_vendeur > 0 ? "+" : ""}${String(e.ecart_vendeur).replace(".", ",")} % par rapport à l'estimation.</p>` : ""}`;
}

export function listeComparables(comp) {
  return `<div class="comparables">${comp
    .map(
      (c) => `<div class="comparable">
        <div class="comp-sim" style="--sim:${c.similarite}%"><span>${c.similarite} %</span></div>
        <div class="comp-txt"><div><strong>${fmtEuros(c.prix)}</strong> · ${Math.round(c.surface)} m²${c.pieces ? ` · ${c.pieces} p.` : ""}${c.terrain ? ` · terrain ${Math.round(c.terrain)} m²` : ""}</div>
          <span class="muted small">${esc(c.adresse)}${c.commune ? `, ${esc(c.commune)}` : ""} · ${new Date(c.date).toLocaleDateString("fr-FR", { month: "short", year: "numeric" })}${c.distance != null ? ` · ${c.distance < 1000 ? c.distance + " m" : (c.distance / 1000).toFixed(1).replace(".", ",") + " km"}` : ""}</span></div>
        <div class="comp-m2"><span>${fmtEuros(c.prix_m2)}<small>/m²</small></span>${c.prix_m2_actualise !== c.prix_m2 ? `<span class="muted small">auj. ${fmtEuros(c.prix_m2_actualise)}</span>` : ""}</div>
      </div>`,
    )
    .join("")}</div>`;
}

vues.estimation = async () => {
  const $m = ecran("Estimer un bien", "", { back: "#/" });
  const bien = { type_bien: "Maison", surface_habitable: 100, nb_pieces: 4, surface_terrain: "", etat_general: "Bon état", dpe: "", exterieur: "", stationnement: "", exposition: "" };
  let lieu = null; // { label, lat, lon, citycode }
  let ctrl = null;
  let timer = null;
  let carte = null;

  $m.innerHTML = `
    <section class="card">
      <span class="tag">En temps réel</span>
      <p class="small muted" style="margin-top:10px">Ventes réelles de biens similaires (DVF, données publiques de la DGFiP), actualisées selon l'évolution du marché local. Le prix bouge à chaque modification.</p>
      <label>Adresse du bien
        <div class="suggest-wrap"><input id="adr" aria-label="Adresse du bien" placeholder="ex. 11 rue du Chênois, Lougres" autocomplete="off"><div class="suggestions" id="sugg"></div></div>
      </label>
      <div class="segment" id="type">${["Maison", "Appartement"].map((t) => `<button type="button" data-v="${t}" class="${t === bien.type_bien ? "on" : ""}">${t}</button>`).join("")}</div>
      <label>Surface habitable : <strong id="surf-v">${bien.surface_habitable} m²</strong><input type="range" id="surf" min="15" max="400" step="1" value="${bien.surface_habitable}"></label>
      <div class="row-2b">
        <label>Pièces<div class="stepper"><button type="button" data-d="-1">−</button><output id="pieces">${bien.nb_pieces}</output><button type="button" data-d="1">+</button></div></label>
        <label id="terrain-l">Terrain (m²)<input id="terrain" inputmode="numeric" placeholder="ex. 900"></label>
      </div>
      <div class="lbl-chips">État</div>
      <div class="chips-choix" id="etat">${ETATS.map((x) => `<button type="button" data-v="${x}" class="${x === bien.etat_general ? "on" : ""}">${x}</button>`).join("")}</div>
      <div class="lbl-chips">DPE</div>
      <div class="chips-dpe" id="dpe">${Object.entries(DPE).map(([l, c]) => `<button type="button" data-v="${l}" style="--c:${c}">${l}</button>`).join("")}</div>
      <div class="lbl-chips">Atouts</div>
      <div class="chips-choix" id="atouts">
        <button type="button" data-k="exterieur" data-v="balcon terrasse">Balcon / terrasse</button>
        <button type="button" data-k="exterieur" data-v="jardin">Jardin</button>
        <button type="button" data-k="stationnement" data-v="garage">Garage / parking</button>
        <button type="button" data-k="exposition" data-v="sud">Exposé sud</button>
      </div>
      <label>Prix souhaité par le vendeur <span class="muted">(facultatif)</span><input id="prix-v" inputmode="numeric" placeholder="ex. 420000"></label>
    </section>
    <section class="card estim-resultat" id="res"><p class="muted">Saisissez l'adresse pour lancer l'estimation.</p></section>
    <section class="card" id="tend" hidden></section>
    <section class="card" id="comp" hidden></section>`;

  const $ = (id) => document.getElementById(id);
  const $adr = $("adr"), $sugg = $("sugg");

  const lancer = () => {
    clearTimeout(timer);
    timer = setTimeout(estimer, 220);
  };

  async function estimer() {
    if (!lieu && $adr.value.trim().length < 5) return;
    ctrl?.abort();
    ctrl = new AbortController();
    $("res")?.classList.add("calcul");
    const q = { ...bien, ...(lieu ? { lat: lieu.lat, lon: lieu.lon, citycode: lieu.citycode, adresse: lieu.label } : { adresse: $adr.value.trim() }) };
    Object.keys(q).forEach((k) => (q[k] === "" || q[k] == null) && delete q[k]);
    try {
      const r = await api("estimation", { query: q, signal: ctrl.signal });
      if (!lieu) lieu = { label: r.adresse, lat: r.lat, lon: r.lon, citycode: r.citycode };
      afficher(r);
    } catch (e) {
      if (e.name === "AbortError" || !$("res")) return;
      $("res").innerHTML = `<p class="orange-txt">${esc(e.message)}</p>`;
    } finally {
      $("res")?.classList.remove("calcul");
    }
  }

  function afficher(r) {
    if (!$("res")) return; // écran quitté entre-temps
    const e = r.estimation;
    $("res").innerHTML = `<div class="small muted">${esc(r.adresse)}</div>${blocEstimation(e, { simulation: r.simulation })}
      ${e ? `<div class="btn-row"><button class="btn" id="copier">Copier</button><button class="btn primary" id="visite">🎙️ Démarrer la visite ici</button></div>` : ""}`;
    $("tend").hidden = !e?.tendance || Object.keys(e.tendance.par_annee).length < 2;
    if (e) $("tend").innerHTML = `<h2>Le marché ${r.communes_voisines?.length ? "du secteur" : `à ${esc(r.adresse.split(" ").slice(-1)[0])}`}</h2>${graphiqueTendance(e.tendance)}<p class="small muted">${r.nb_ventes_commune} ventes de maisons et d'appartements sur 5 ans dans la commune${r.communes_voisines?.length ? `, ${r.nb_ventes} avec ${r.communes_voisines.length} commune(s) voisine(s)` : ""}.</p>`;
    $("comp").hidden = !e;
    if (e) {
      $("comp").innerHTML = `<h2>Les ${e.comparables.length} ventes les plus ressemblantes</h2><div id="carte" class="carte-estim"></div>${listeComparables(e.comparables)}
        <p class="small muted">Le pourcentage indique la ressemblance avec votre bien (surface, pièces, terrain, distance, date de vente). « auj. » : prix au m² actualisé avec l'évolution du marché.</p>`;
      dessinerCarte(r, e);
      $("copier").onclick = () =>
        copier(`Estimation ${r.adresse} : ${fmtEuros(e.prix)} (fourchette ${fmtEuros(e.bas)} – ${fmtEuros(e.haut)}), ${fmtEuros(e.prix_m2)}/m², d'après ${e.comparables.length} ventes réelles similaires (DVF). Confiance ${e.confiance.niveau}.`);
      $("visite").onclick = () => {
        try {
          sessionStorage.setItem("vi-adresse", r.adresse);
        } catch {}
        go("/nouvelle");
      };
    }
  }

  async function dessinerCarte(r, e) {
    try {
      const L = await chargerLeaflet();
      const el = $("carte");
      if (!el) return;
      carte?.remove();
      carte = L.map(el, { zoomControl: false, attributionControl: true }).setView([r.lat, r.lon], 15);
      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { maxZoom: 19, attribution: "© OpenStreetMap" }).addTo(carte);
      const pts = [[r.lat, r.lon]];
      L.circleMarker([r.lat, r.lon], { radius: 9, color: "#111114", weight: 2, fillColor: "#d4f22e", fillOpacity: 1 }).addTo(carte).bindTooltip("Votre bien");
      e.comparables.forEach((c) => {
        if (!c.lat) return;
        pts.push([c.lat, c.lon]);
        L.circleMarker([c.lat, c.lon], { radius: 5 + c.similarite / 25, color: "#fffdf8", weight: 2, fillColor: "#2e7d3a", fillOpacity: 0.9 })
          .addTo(carte)
          .bindTooltip(`${fmtEuros(c.prix)} · ${Math.round(c.surface)} m² · ${c.similarite} %`);
      });
      if (pts.length > 1) carte.fitBounds(pts, { padding: [24, 24], maxZoom: 16 });
    } catch {
      $("carte")?.remove(); // hors ligne : la liste suffit
    }
  }

  // Adresse : suggestions pendant la saisie
  let tSugg = null;
  $adr.oninput = () => {
    lieu = null;
    clearTimeout(tSugg);
    tSugg = setTimeout(async () => {
      const q = $adr.value.trim();
      if (q.length < 4) return ($sugg.innerHTML = "");
      const s = await api("adresse_suggestions", { query: { q } }).catch(() => []);
      $sugg.innerHTML = s.map((x, i) => `<button type="button" data-i="${i}">${esc(x.label)}</button>`).join("");
      $sugg.querySelectorAll("button").forEach(
        (b) =>
          (b.onclick = () => {
            const x = s[b.dataset.i];
            lieu = { label: x.label, lat: x.lat, lon: x.lon, citycode: x.citycode };
            $adr.value = x.label;
            $sugg.innerHTML = "";
            estimer();
          }),
      );
    }, 200);
  };
  $adr.onkeydown = (ev) => {
    if (ev.key === "Enter") {
      ev.preventDefault();
      $sugg.innerHTML = "";
      estimer();
    }
  };

  const choix = (id, cle) =>
    $(id).querySelectorAll("button").forEach(
      (b) =>
        (b.onclick = () => {
          const on = !b.classList.contains("on");
          $(id).querySelectorAll("button").forEach((x) => x.classList.remove("on"));
          if (on || id === "type") b.classList.add("on");
          bien[cle] = on || id === "type" ? b.dataset.v : "";
          if (id === "type") $("terrain-l").hidden = bien.type_bien === "Appartement";
          lancer();
        }),
    );
  choix("type", "type_bien");
  choix("etat", "etat_general");
  choix("dpe", "dpe");
  $("atouts").querySelectorAll("button").forEach(
    (b) =>
      (b.onclick = () => {
        b.classList.toggle("on");
        const k = b.dataset.k;
        bien[k] = [...$("atouts").querySelectorAll(`button.on[data-k="${k}"]`)].map((x) => x.dataset.v).join(" ");
        lancer();
      }),
  );
  $("surf").oninput = (ev) => {
    bien.surface_habitable = ev.target.value;
    $("surf-v").textContent = `${ev.target.value} m²`;
    lancer();
  };
  document.querySelectorAll(".stepper button").forEach(
    (b) =>
      (b.onclick = () => {
        bien.nb_pieces = Math.max(1, Math.min(12, Number(bien.nb_pieces) + Number(b.dataset.d)));
        $("pieces").textContent = bien.nb_pieces;
        lancer();
      }),
  );
  $("terrain").oninput = (ev) => {
    bien.surface_terrain = ev.target.value.replace(/\D/g, "");
    lancer();
  };
  $("prix-v").oninput = (ev) => {
    bien.prix_souhaite = ev.target.value.replace(/\D/g, "");
    lancer();
  };
};
