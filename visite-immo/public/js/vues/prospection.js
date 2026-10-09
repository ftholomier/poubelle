// Prospection : secteurs, logements F/G à cibler, rues actives (boîtage), courriers personnalisés, suivi.

import { api } from "../api.js";
import { vues, ecran, esc, toast, action, fmtEuros } from "../ui.js";

const COUL_DPE = { F: "#eb8235", G: "#d7221f" };

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

vues.prospection = async () => {
  const $m = ecran("Prospection", '<div class="loader"></div>', { back: "#/" });
  const d = await api("prospection");
  const actives = d.cibles.filter((c) => c.statut !== "ecarte");
  const st = d.stats;
  $m.innerHTML = `
    <section class="card">
      <span class="tag">Trouver les vendeurs avant les autres</span>
      <p class="small muted" style="margin-top:12px">Données publiques : logements classés F ou G (ADEME), ventes réelles (DVF). Le courrier « Au propriétaire » remplace le démarchage téléphonique, désormais soumis au consentement préalable.</p>
      <form class="test-row" id="f-commune"><input id="commune" aria-label="Commune à ajouter" placeholder="Ajouter une commune (ex. Lougres)" autocomplete="off"><button class="btn primary">Chercher</button></form>
      <div id="resultats"></div>
    </section>
    ${d.secteurs.length ? `<div class="entonnoir">${[["nouveau", "À contacter"], ["courrier", "Courriers"], ["contacte", "Réponses"], ["rdv", "Estimations"], ["mandat", "Mandats"]].map(([k, l]) => `<div><strong>${st[k] || 0}</strong><span>${l}</span></div>`).join("")}</div>` : ""}
    ${d.secteurs.map((s) => `<section class="card"><div class="dossier-top"><h2>${esc(s.nom)} ${esc(s.cp || "")}</h2><span class="muted small">${s.disponible ? "" : "DPE indisponible"}</span></div>
      <div class="chiffres">
        <div class="chiffre"><small>Ventes 3 ans</small><strong>${s.marche.ventes}</strong></div>
        ${s.marche.prix_m2_maison ? `<div class="chiffre"><small>Maisons</small><strong>${fmtEuros(s.marche.prix_m2_maison)}/m²</strong></div>` : ""}
        ${s.marche.prix_m2_appartement ? `<div class="chiffre"><small>Appartements</small><strong>${fmtEuros(s.marche.prix_m2_appartement)}/m²</strong></div>` : ""}
      </div>
      ${s.marche.rues.length ? `<details class="aide"><summary>Rues actives : boîtage « votre rue se vend »</summary>${s.marche.rues.map((r) => `<div class="ligne"><div><strong>${esc(r.rue)}</strong><span class="muted small">${r.ventes} ventes · ${fmtEuros(r.prix_m2)}/m²</span></div><a class="icon-btn" target="_blank" href="api/?${new URLSearchParams({ r: "boitage", commune: s.citycode, rue: r.rue })}">📄</a></div>`).join("")}</details>` : ""}
      <div class="btn-row"><button class="btn ghost" data-reanalyser='${esc(JSON.stringify(s))}'>↻ Actualiser</button><button class="btn danger-ghost small-btn" data-suppr-secteur="${esc(s.citycode)}" data-nom="${esc(s.nom)}">🗑 Retirer ce secteur</button></div>
    </section>`).join("")}
    <div id="carte" class="carte" hidden></div>
    ${actives.length ? `<section class="card"><div class="dossier-top"><h2>Logements F / G à cibler</h2><span class="muted small">${actives.length}</span></div>
      <label class="check"><input type="checkbox" id="tout"> Tout sélectionner (à contacter)</label>
      ${actives.map((c) => `<div class="ligne cible"><label class="check-ligne"><input type="checkbox" value="${c.id}" data-statut="${c.statut}"><div><strong>${esc(c.adresse)}</strong>
          <span class="muted small"><b class="dpe-pastille" style="background:${COUL_DPE[c.dpe] || "#999"}">${esc(c.dpe)}</b> ${esc(c.type || "")}${c.surface ? ` · ${Math.round(c.surface)} m²` : ""}${c.annee ? ` · ${esc(c.annee)}` : ""} · DPE du ${new Date(c.date_dpe).toLocaleDateString("fr-FR")}</span></div></label>
        <div class="cible-droite"><span class="score">${esc(c.score)}</span><select data-cible="${c.id}" aria-label="Statut de ${esc(c.adresse || "cette adresse")}">${Object.entries(d.statuts).map(([k, l]) => `<option value="${k}" ${k === c.statut ? "selected" : ""}>${l}</option>`).join("")}</select></div></div>`).join("")}
      <div class="sticky-actions"><button class="btn primary" id="courriers">📄 Courriers de la sélection</button></div>
    </section>` : d.secteurs.length ? '<div class="vide"><p>Aucune adresse à cibler.</p></div>' : ""}`;

  document.getElementById("f-commune").onsubmit = async (e) => {
    e.preventDefault();
    const q = document.getElementById("commune").value.trim();
    if (!q) return;
    const res = await api("communes", { query: { q } });
    document.getElementById("resultats").innerHTML = res.map((c, i) => `<button class="ligne choix-commune" data-i="${i}"><div><strong>${esc(c.nom)} ${esc(c.cp)}</strong><span class="muted small">${esc(c.contexte)}</span></div><span class="fleche">+</span></button>`).join("") || '<p class="muted small">Aucune commune trouvée (service indisponible ?).</p>';
    document.querySelectorAll(".choix-commune").forEach((b) => action(b, async () => {
      const r = await api("secteur", { method: "POST", body: res[b.dataset.i] });
      toast(r.service ? `${r.ajouts} adresse(s) à cibler ajoutée(s)` : "Données DPE indisponibles pour l'instant : statistiques de marché seulement", r.service ? "ok" : "");
      vues.prospection();
    }));
  };
  $m.querySelectorAll("[data-reanalyser]").forEach((b) => action(b, async () => {
    const r = await api("secteur", { method: "POST", body: JSON.parse(b.dataset.reanalyser) });
    toast(`${r.ajouts} nouvelle(s) adresse(s)`, "ok");
    vues.prospection();
  }));
  $m.querySelectorAll("[data-suppr-secteur]").forEach((b) => (b.onclick = async () => {
    if (!confirm(`Retirer le secteur ${b.dataset.nom} ? Les adresses pas encore contactées sont retirées aussi ; celles déjà suivies restent.`)) return;
    await api("secteur", { method: "DELETE", query: { citycode: b.dataset.supprSecteur } });
    toast("Secteur retiré");
    vues.prospection();
  }));
  $m.querySelectorAll("[data-cible]").forEach((s) => (s.onchange = async () => {
    await api("cible", { method: "POST", query: { id: s.dataset.cible }, body: { statut: s.value } });
    toast("Statut enregistré ✓", "ok");
  }));
  document.getElementById("tout")?.addEventListener("change", (e) => $m.querySelectorAll(".cible input[type=checkbox]").forEach((c) => (c.checked = e.target.checked && c.dataset.statut === "nouveau")));
  document.getElementById("courriers")?.addEventListener("click", () => {
    const ids = [...$m.querySelectorAll(".cible input:checked")].map((c) => c.value);
    if (!ids.length) return toast("Cochez au moins une adresse", "erreur");
    window.open(`api/?${new URLSearchParams({ r: "courriers", ids: ids.join(",") })}`, "_blank");
    setTimeout(() => vues.prospection(), 1500);
  });

  // Carte (si la bibliothèque cartographique est joignable)
  const points = actives.filter((c) => c.lat && c.lon);
  if (points.length) {
    chargerLeaflet().then((L) => {
      const el = document.getElementById("carte");
      if (!el) return;
      el.hidden = false;
      const carte = L.map(el).setView([points[0].lat, points[0].lon], 14);
      L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", { attribution: "© OpenStreetMap" }).addTo(carte);
      const groupe = L.featureGroup(points.map((c) => L.circleMarker([c.lat, c.lon], { radius: 8, color: "#111114", weight: 1.5, fillColor: COUL_DPE[c.dpe], fillOpacity: 0.9 }).bindPopup(`${esc(c.adresse)}<br>DPE ${esc(c.dpe)}`))).addTo(carte);
      carte.fitBounds(groupe.getBounds().pad(0.2));
    }).catch(() => {});
  }
};
