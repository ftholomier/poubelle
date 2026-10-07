// Onglet « Vente » d'un dossier : visites des acquéreurs (bon signé, retour dicté), acquéreurs compatibles,
// point hebdomadaire au vendeur. Les autres modules ajoutent leurs blocs (diffusion, offres, compromis…).

import { api } from "../api.js";
import { dicter } from "../dictee.js";
import { formulaireRdv } from "./agenda.js";
import { ecransDossier, esc, toast, go, action, feuille, fmtDate, pdfUrl, outils } from "../ui.js";

/** Blocs de l'onglet, dans l'ordre : { ordre, html(visit) | async rendre($el, visit) } */
export const blocsVente = [];

ecransDossier.onglet_vente = async ($c, visit) => {
  const rang = (b) => (b.prioritaire?.(visit) ? 0 : 1);
  const blocs = [...blocsVente].sort((a, b) => rang(a) - rang(b) || a.ordre - b.ordre);
  $c.innerHTML = blocs.map((b, i) => `<div id="bloc-${i}"></div>`).join("");
  for (const [i, b] of blocs.entries()) {
    try {
      await b.rendre(document.getElementById(`bloc-${i}`), visit);
    } catch (e) {
      document.getElementById(`bloc-${i}`).innerHTML = `<p class="erreur small">${esc(e.message)}</p>`;
    }
  }
};

const recharger = (visit) => (location.hash.endsWith("/vente") ? outils.route() : go(`/visite/${visit.id}/vente`));

// ---------- Visites des acquéreurs ----------

blocsVente.push({
  ordre: 20,
  async rendre($el, visit) {
    const visites = (visit.visites_acq || []).slice().sort((a, b) => b.date.localeCompare(a.date));
    $el.innerHTML = `<section class="card"><div class="dossier-top"><h2>Visites</h2><span class="muted small">${visites.length}</span></div>
      ${visites.map((va) => {
        const sig = visit.signatures?.[`bon:${va.id}`];
        const r = va.retour;
        return `<div class="visite-acq">
          <div class="dossier-top"><strong>${esc(va.nom)}</strong><span class="muted small">${fmtDate(va.date)}</span></div>
          ${r ? `<p class="small">Intérêt <strong>${r.interet}/5</strong> · ${esc(r.resume_vendeur)}</p>` : ""}
          <div class="btn-row">
            <button class="btn small-btn ${va.bon_signe ? "" : "primary"}" data-bon="${va.id}">${va.bon_signe ? "✓ Bon de visite" : sig?.statut === "en_attente" ? "✍️ Bon à signer" : "✍️ Bon de visite"}</button>
            <button class="btn small-btn ${r ? "" : "magic"}" data-retour="${va.id}">🎙️ ${r ? "Refaire le retour" : "Dicter le retour"}</button>
          </div></div>`;
      }).join("") || '<p class="muted">Aucune visite pour l\'instant.</p>'}
      <div class="btn-row" style="margin-top:10px"><button class="btn" id="planifier">📅 Planifier une visite</button><button class="btn" id="passage">🚶 Visiteur présent</button></div>
    </section>`;
    $el.querySelector("#planifier").onclick = () => formulaireRdv({ type: "visite", titre: "" }, { dossier: visit.id, apres: () => recharger(visit) });
    $el.querySelector("#passage").onclick = () => {
      const s = feuille(`<form id="fp"><h2>Visiteur présent</h2><label>Nom du visiteur<input name="nom" required></label><button class="btn primary big">Créer la visite et signer le bon</button><button type="button" class="btn ghost" data-close>Annuler</button></form>`);
      s.querySelector("#fp").onsubmit = async (e) => {
        e.preventDefault();
        const r = await api("visite_acq", { method: "POST", query: { id: visit.id }, body: { nom: new FormData(e.target).get("nom") } });
        s.fermer();
        const va = r.visites_acq.at(-1);
        go(`/visite/${visit.id}/signature/${encodeURIComponent("bon:" + va.id)}`);
      };
    };
    $el.querySelectorAll("[data-bon]").forEach((b) => (b.onclick = () => go(`/visite/${visit.id}/signature/${encodeURIComponent("bon:" + b.dataset.bon)}`)));
    $el.querySelectorAll("[data-retour]").forEach((b) => (b.onclick = async () => {
      const r = await dicter("retour_visite", { titre: "Retour de visite", aide: "Ce qui a plu, ce qui freine, l'avis sur le prix, la suite.", exemple: "Ils ont adoré le jardin, la cuisine les freine, ils trouvent le prix un peu haut, ils veulent revenir avec un artisan.", params: { dossier: visit.id, visite: b.dataset.retour } });
      if (r) {
        toast("Retour enregistré ✓ Le vendeur le verra dans le point de vendredi.", "ok");
        recharger(visit);
      }
    }));
  },
});

// ---------- Acquéreurs compatibles ----------

blocsVente.push({
  ordre: 30,
  async rendre($el, visit) {
    const liste = await api("rapprochements", { query: { id: visit.id } });
    $el.innerHTML = `<section class="card"><div class="dossier-top"><h2>Acquéreurs compatibles</h2><span class="muted small">${liste.length}</span></div>
      ${liste.map((a) => `<label class="ligne check-ligne"><input type="checkbox" value="${a.id}" ${a.propose || !a.email ? "" : "checked"} ${a.email ? "" : "disabled"}>
        <div><strong>${esc(a.nom)}</strong><span class="muted small">${esc(a.raisons.join(", "))} · qualifié ${a.qualification} %${a.propose ? " · déjà proposé" : ""}${a.email ? "" : " · pas d'e-mail"}</span></div><span class="score">${a.score}</span></label>`).join("") || '<p class="muted">Aucun acquéreur de votre fichier ne correspond pour l\'instant.</p>'}
      ${liste.length ? '<button class="btn primary" id="proposer">✉️ Proposer le bien aux acquéreurs cochés</button>' : ""}
    </section>`;
    action($el.querySelector("#proposer"), async () => {
      const ids = [...$el.querySelectorAll("input:checked")].map((i) => i.value);
      if (!ids.length) throw new Error("Cochez au moins un acquéreur.");
      const r = await api("proposer", { method: "POST", query: { id: visit.id }, body: { acquereurs: ids } });
      toast(`Bien proposé à ${r.envoyes} acquéreur(s) ✓`, "ok");
      this.rendre($el, visit);
    });
  },
});

// ---------- Point hebdomadaire au vendeur ----------

blocsVente.push({
  ordre: 60,
  async rendre($el, visit) {
    if (!["en_vente", "offre", "compromis"].includes(visit.etape) && !(visit.cr_hebdo || []).length) return;
    const crs = (visit.cr_hebdo || []).map((c, n) => ({ ...c, n })).reverse();
    $el.innerHTML = `<section class="card"><h2>Point de la semaine au vendeur</h2>
      <p class="small muted">Préparé automatiquement chaque vendredi à 17 h (consultations, contacts, visites, retours) et envoyé au vendeur.</p>
      ${crs.map((c) => `<div class="ligne"><div><strong>Semaine du ${new Date(c.du).toLocaleDateString("fr-FR")}</strong><span class="muted small">${c.visites} visite(s) · ${c.contacts} contact(s) · ${c.envoye_le ? `envoyé le ${fmtDate(c.envoye_le)}` : "à envoyer"}</span></div>
        <div class="doc-actions"><a class="icon-btn" target="_blank" href="${pdfUrl(visit.id, "cr_hebdo", false, { n: c.n })}">📄</a>${c.envoye_le ? "" : `<button class="icon-btn" data-envoyer="${c.n}">✉️</button>`}</div></div>`).join("")}
      <button class="btn" id="preparer">Préparer le point maintenant</button></section>`;
    action($el.querySelector("#preparer"), async () => {
      await api("cr_hebdo", { method: "POST", query: { id: visit.id }, body: {} });
      recharger(visit);
    });
    $el.querySelectorAll("[data-envoyer]").forEach((b) => action(b, async () => {
      await api("cr_hebdo", { method: "POST", query: { id: visit.id }, body: { envoyer: Number(b.dataset.envoyer) } });
      toast("Envoyé au vendeur ✓", "ok");
      recharger(visit);
    }));
  },
});
