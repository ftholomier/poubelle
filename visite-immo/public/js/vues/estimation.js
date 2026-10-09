// Estimer un bien en temps réel : adresse + caractéristiques → ventes réelles de biens similaires (DVF),
// prix, fourchette, confiance, tendance du marché. Le résultat se met à jour à chaque changement.

import { api } from "../api.js";
import { chargerLeaflet, fondCarte } from "../carte.js";
import { vues, ecran, esc, fmtEuros, go, copier } from "../ui.js";

const ETATS = ["À rénover", "Travaux à prévoir", "Bon état", "Très bon état", "Refait à neuf"];
const DPE = { A: "#009c6d", B: "#52b153", C: "#a5cc74", D: "#f4e70f", E: "#f2a541", F: "#eb8235", G: "#d7221f" };


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
        <label>Pièces<div class="compteur"><button type="button" data-d="-1">−</button><output id="pieces">${bien.nb_pieces}</output><button type="button" data-d="1">+</button></div></label>
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
      carte = L.map(el, { zoomControl: false, attributionControl: true, ...SANS_ANIMATION }).setView([r.lat, r.lon], 15);
      fondCarte(L, carte);
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
  document.querySelectorAll(".compteur button").forEach(
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

/* ---------- Dans la fiche : carte des ventes autour du bien et justification du prix ---------- */

// Sans animations : la carte est souvent redessinée (estimation en direct) ou quittée en cours d'animation,
// ce qui fait planter Leaflet (« _leaflet_pos »)
const SANS_ANIMATION = { zoomAnimation: false, fadeAnimation: false, markerZoomAnimation: false };
const kEuros = (p) => (p >= 1e6 ? `${(p / 1e6).toFixed(2).replace(".", ",")} M€` : `${Math.round(p / 1000)} k€`);
const moisAn = (d) => new Date(d).toLocaleDateString("fr-FR", { month: "short", year: "numeric" });
const distance = (m) => (m == null ? "" : m < 1000 ? `${m} m` : `${(m / 1000).toFixed(1).replace(".", ",")} km`);
// couleur selon le rang (ventes déjà triées par ressemblance) : les 4 premières, les 4 suivantes, le reste
const classeRang = (i) => (i < 4 ? "sim-haute" : i < 8 ? "sim-moyenne" : "sim-basse");

/** Le calcul en clair : prix au m² des ventes ressemblantes × surface, ajustements, fourchette. */
function justification(e) {
  const brut = e.prix_m2_marche * e.surface;
  const ajust = e.ajustements.map((x) => `${esc(x[0])} ${x[1] > 0 ? "+" : ""}${Math.round(x[1] * 100)} %`).join(", ");
  return `<ol class="justif">
    <li><span>Prix au m² des ventes qui ressemblent le plus à votre bien <small>(médiane pondérée par la ressemblance, prix actualisés au marché d'aujourd'hui)</small></span><strong>${fmtEuros(e.prix_m2_marche)}/m²</strong></li>
    <li><span>× surface habitable du bien</span><strong>${e.surface} m² = ${fmtEuros(Math.round(brut / 1000) * 1000)}</strong></li>
    ${e.ajustements.length ? `<li><span>Ajustements propres au bien : ${ajust}</span><strong>${e.coefficient >= 1 ? "+" : ""}${Math.round((e.coefficient - 1) * 100)} %</strong></li>` : ""}
    <li class="justif-total"><span>Estimation</span><strong>${fmtEuros(e.prix)}</strong></li>
  </ol>
  <p class="small muted">Fourchette ${fmtEuros(e.bas)} – ${fmtEuros(e.haut)} : de ${fmtEuros(e.prix_m2_bas)} à ${fmtEuros(e.prix_m2_haut)}/m² selon les ventes retenues (20 % les moins chères et les plus chères écartées).</p>`;
}

function popupVente(c, rang) {
  return `<div class="pop-vente"><strong>${fmtEuros(c.prix)}</strong>${rang != null ? ` <span class="pop-sim ${classeRang(rang)}">n° ${rang + 1} · ressemblance ${c.similarite} %</span>` : ""}
    <div>${esc(c.type || "")} · ${Math.round(c.surface)} m²${c.pieces ? ` · ${c.pieces} p.` : ""}${c.terrain ? ` · terrain ${Math.round(c.terrain)} m²` : ""}</div>
    <div>${fmtEuros(c.prix_m2)}/m²${c.prix_m2_actualise && c.prix_m2_actualise !== c.prix_m2 ? ` · aujourd'hui ≈ ${fmtEuros(c.prix_m2_actualise)}/m²` : ""}</div>
    <div class="muted">${esc(c.adresse || "")}${c.commune ? `, ${esc(c.commune)}` : ""} · vendu en ${moisAn(c.date)}${c.distance != null ? ` · à ${distance(c.distance)}` : ""}</div></div>`;
}

/**
 * Bloc « Estimation » de la fiche : prix, carte (le bien au centre, le prix de chaque vente autour), le calcul qui
 * justifie le prix et la liste des ventes. Se recalcule quand la surface, l'état, le DPE… changent.
 */
export function carteVentesFiche($el, visit, { lier = () => {} } = {}) {
  let carte = null;
  let timer = null;
  let dernier = "";
  let toutes = false;

  async function charger() {
    let r;
    try {
      r = await api("estimation_dossier", { query: { id: visit.id } });
    } catch (err) {
      $el.innerHTML = `<h2>Estimation</h2><p class="orange-txt small">${esc(err.message)}</p>`;
      return;
    }
    if (!$el.isConnected) return;
    const e = r.estimation;
    const empreinte = JSON.stringify([e?.prix, e?.comparables?.length, r.autres.length]);
    if (empreinte === dernier) return;
    const avant = dernier;
    dernier = empreinte;
    if (!e) {
      $el.innerHTML = `<h2>Estimation</h2><p class="muted small">${r.bien ? "Pas encore assez de ventes comparables : renseignez au moins le type de bien et la surface habitable." : "Renseignez l'adresse et la ville : les ventes alentour (DVF) apparaîtront ici, sur une carte, avec le prix estimé."}</p>`;
      return;
    }
    $el.innerHTML = `
      <div class="estim-tete"><h2>Estimation</h2><a class="small" href="#/visite/${visit.id}/avis">Avis de valeur ›</a></div>
      <div class="estim-fiche-prix"><strong data-prix>${fmtEuros(e.prix)}</strong><span class="muted small">${fmtEuros(e.bas)} – ${fmtEuros(e.haut)} · ${fmtEuros(e.prix_m2)}/m²</span>
        <span class="confiance confiance-${e.confiance.niveau === "élevée" ? "haute" : e.confiance.niveau === "moyenne" ? "moyenne" : "basse"}">${e.confiance.niveau === "élevée" ? "●●●" : e.confiance.niveau === "moyenne" ? "●●○" : "●○○"} Confiance ${esc(e.confiance.niveau)}</span></div>
      ${r.retenu && r.retenu !== e.prix ? `<p class="small">Prix retenu par vous dans l'avis de valeur : <strong>${fmtEuros(r.retenu)}</strong></p>` : ""}
      ${e.ecart_vendeur != null ? `<p class="small ${Math.abs(e.ecart_vendeur) >= 5 ? "orange-txt" : ""}">Prix souhaité par le vendeur : ${e.ecart_vendeur > 0 ? "+" : ""}${String(e.ecart_vendeur).replace(".", ",")} % par rapport à l'estimation.</p>` : ""}
      ${e.simulation ? '<p class="small"><span class="badge orange">ventes simulées (service DVF indisponible)</span></p>' : ""}
      <div class="carte-ventes" id="carte-ventes" role="region" aria-label="Carte des ventes autour du bien"></div>
      <div class="legende-ventes small">
        <span><i class="pt-bien"></i>Votre bien</span><span><i class="pt-comp sim-haute"></i>Les 4 plus ressemblantes</span><span><i class="pt-comp sim-moyenne"></i>Les suivantes</span><span><i class="pt-comp sim-basse"></i>Les moins ressemblantes</span>
        ${r.autres.length ? `<label class="toutes"><input type="checkbox" id="toutes-ventes" ${toutes ? "checked" : ""}> Autres ventes (${r.autres.length})</label>` : ""}
      </div>
      <h3 class="justif-titre">Pourquoi ce prix</h3>
      ${justification(e)}
      ${e.communes_voisines?.length ? `<p class="small muted">Peu de ventes dans la commune : ventes de ${e.communes_voisines.map(esc).join(", ")} ajoutées (les plus proches pèsent le plus).</p>` : ""}
      <details class="aide"><summary>Les ${e.comparables.length} ventes retenues</summary>${listeComparables(e.comparables)}</details>
      <div class="btn-row"><button class="btn primary" data-pdf="avis">📄 Rapport d'estimation</button><button class="btn" data-send="avis">✉️ Envoyer au vendeur</button></div>
      <p class="small muted">Le rapport reprend tout pour le vendeur : la carte des ventes autour de son bien, le calcul de ce prix, les ventes retenues et l'évolution du marché.</p>
      <p class="small muted">Ventes réelles publiées par la DGFiP (DVF), sur 5 ans. Touchez un prix sur la carte pour le détail.</p>`;
    if (avant) {
      $el.classList.remove("flash");
      void $el.offsetWidth;
      $el.classList.add("flash");
    }
    lier($el); // boutons PDF et envoi (liés par la fiche)
    dessiner(r, e);
    $el.querySelector("#toutes-ventes")?.addEventListener("change", (ev) => {
      toutes = ev.target.checked;
      dessiner(r, e);
    });
  }

  async function dessiner(r, e) {
    let L;
    try {
      L = await chargerLeaflet();
    } catch {
      $el.querySelector("#carte-ventes")?.remove(); // hors ligne : le calcul et la liste suffisent
      return;
    }
    const el = $el.querySelector("#carte-ventes");
    if (!el) return;
    carte?.remove();
    const centre = r.bien || e.comparables.find((c) => c.lat) || null;
    if (!centre) return el.remove();
    // sur téléphone, un doigt fait défiler la page ; la carte se déplace avec deux doigts ou les boutons
    carte = L.map(el, { scrollWheelZoom: false, dragging: !L.Browser.mobile, tap: false, ...SANS_ANIMATION }).setView([centre.lat, centre.lon], 15);
    fondCarte(L, carte);
    const pts = [];
    if (toutes) {
      r.autres.forEach((c) => {
        L.circleMarker([c.lat, c.lon], { radius: 4, color: "#fffdf8", weight: 1, fillColor: "#8a877f", fillOpacity: 0.85 }).addTo(carte).bindPopup(popupVente(c, null));
        pts.push([c.lat, c.lon]);
      });
    }
    // les ventes les plus ressemblantes passent au-dessus des autres quand les étiquettes se chevauchent
    e.comparables.forEach((c, i) => {
      if (!c.lat) return;
      pts.push([c.lat, c.lon]);
      const icone = L.divIcon({ className: "pin-vente", html: `<span class="${classeRang(i)}">${kEuros(c.prix)}</span>`, iconSize: null, iconAnchor: [0, 0] });
      L.marker([c.lat, c.lon], { icon: icone, title: `${fmtEuros(c.prix)}, ${Math.round(c.surface)} m²`, riseOnHover: true, zIndexOffset: (20 - i) * 10 }).addTo(carte).bindPopup(popupVente(c, i));
    });
    if (r.bien) {
      const icone = L.divIcon({ className: "pin-bien", html: `<span>🏠 ${kEuros(e.prix)}</span>`, iconSize: null, iconAnchor: [0, 0] });
      L.marker([r.bien.lat, r.bien.lon], { icon: icone, zIndexOffset: 1000, title: "Votre bien" }).addTo(carte).bindPopup(`<div class="pop-vente"><strong>Votre bien · ${fmtEuros(e.prix)}</strong><div>${esc(r.bien.adresse)}</div><div>${e.surface} m² · ${fmtEuros(e.prix_m2)}/m²</div></div>`);
      pts.push([r.bien.lat, r.bien.lon]);
    }
    if (pts.length > 1) carte.fitBounds(pts, { padding: [30, 30], maxZoom: 16 });
  }

  // Une caractéristique change (surface, état, DPE, prix souhaité…) : nouvelle estimation, sans recharger la carte pour rien
  const maj = () => {
    if (!$el.isConnected) return window.removeEventListener("dossier-maj", maj);
    clearTimeout(timer);
    timer = setTimeout(charger, 400);
  };
  window.addEventListener("dossier-maj", maj);
  $el.innerHTML = `<h2>Estimation</h2><p class="muted small">Recherche des ventes autour du bien…</p>`;
  charger();
}
