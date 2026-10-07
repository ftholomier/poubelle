// Agenda : rendez-vous des deux prochaines semaines, ajout à la voix ou au clavier, abonnement calendrier,
// disponibilités utilisées par l'assistant acquéreurs pour proposer des créneaux.

import { api } from "../api.js";
import { dicter } from "../dictee.js";
import { vues, ecran, esc, toast, action, feuille, copier, fmtHeure } from "../ui.js";

const TYPES = { visite: "Visite", estimation: "Estimation", signature: "Signature", appel: "Appel", autre: "Rendez-vous" };
const JOURS = ["", "Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim"];

vues.agenda = async () => {
  const $m = ecran("Agenda", '<div class="loader"></div>', { actif: "agenda" });
  const d = await api("agenda");
  const parJour = {};
  for (const r of d.rdv) (parJour[r.debut.slice(0, 10)] ||= []).push(r);
  const jours = Object.keys(parJour).sort();
  $m.innerHTML = `
    <div class="btn-row"><button class="btn magic" id="dicter">🎙️ Dicter un rendez-vous</button><button class="btn" id="ajout">+ Ajouter</button></div>
    ${jours.map((j) => `<section class="card agenda-jour"><h2>${new Date(j + "T12:00").toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long" })}</h2>
      ${parJour[j].map((r) => `<div class="rdv rdv-${r.type}" data-rdv="${esc(r.id)}">
        <span class="rdv-heure">${fmtHeure(r.debut)}</span>
        <div><strong>${esc(TYPES[r.type])} · ${esc(r.titre)}</strong>
          <span class="muted small">${esc([r.bien, r.acquereur_nom, r.lieu].filter(Boolean).join(" · "))}${r.source === "assistant" ? " · réservé par l'assistant" : ""}</span></div>
        ${r.dossier ? `<a class="icon-btn" href="#/visite/${r.dossier}/vente">→</a>` : ""}</div>`).join("")}
    </section>`).join("") || '<div class="vide"><p>Aucun rendez-vous.</p><p class="muted">Les visites réservées par l\'assistant acquéreurs apparaissent ici automatiquement.</p></div>'}
    <section class="card"><h2>Prochains créneaux libres</h2>
      <p class="small muted">Proposés automatiquement aux acquéreurs par l'assistant en ligne.</p>
      <div class="creneaux">${d.creneaux.map((c) => `<span class="creneau">${new Date(c).toLocaleDateString("fr-FR", { weekday: "short", day: "numeric" })} ${fmtHeure(c)}</span>`).join("") || '<span class="muted">Aucun</span>'}</div>
      <button class="btn" id="dispo">⏱ Mes disponibilités</button>
    </section>
    <section class="card"><h2>Dans mon agenda habituel</h2>
      <p class="small muted">Abonnez votre agenda Google, Apple ou Outlook à ce lien : vos rendez-vous y apparaissent automatiquement.</p>
      <div class="test-row"><input readonly value="${esc(d.ics)}" id="ics" aria-label="Adresse d'abonnement au calendrier"><button class="btn" id="copier">📋</button></div>
      <a class="btn ghost" href="${esc(d.ics.replace(/^https?:/, "webcal:"))}">Ouvrir dans mon calendrier</a>
    </section>`;

  document.getElementById("dicter").onclick = async () => {
    const r = await dicter("rdv", { titre: "Nouveau rendez-vous", exemple: "Jeudi 14 h 30, estimation chez monsieur Bernard, 4 rue des Lilas à Lougres." });
    if (r) {
      toast(`Ajouté : ${r.resultat.rdv.titre}`, "ok");
      vues.agenda();
    }
  };
  document.getElementById("ajout").onclick = () => formulaireRdv({});
  document.getElementById("copier").onclick = () => copier(d.ics);
  document.getElementById("dispo").onclick = () => formulaireDispo(d.disponibilites);
  $m.querySelectorAll("[data-rdv]").forEach((el) => (el.onclick = (e) => {
    if (e.target.closest("a")) return;
    formulaireRdv(d.rdv.find((r) => r.id === el.dataset.rdv));
  }));
};

export function formulaireRdv(r, { dossier, acquereur, apres } = {}) {
  const local = (iso) => { const x = new Date(iso); x.setMinutes(x.getMinutes() - x.getTimezoneOffset()); return x.toISOString().slice(0, 16); };
  const debut = r.debut ? local(r.debut) : local(new Date(Date.now() + 86400e3).setHours(10, 0, 0, 0));
  const duree = r.debut ? Math.round((new Date(r.fin) - new Date(r.debut)) / 60000) : 45;
  const sheet = feuille(`<form id="fr"><h2>${r.id ? "Rendez-vous" : "Nouveau rendez-vous"}</h2>
    <label>Type<select name="type">${Object.entries(TYPES).map(([k, l]) => `<option value="${k}" ${k === (r.type || (dossier ? "visite" : "autre")) ? "selected" : ""}>${l}</option>`).join("")}</select></label>
    <label>Titre<input name="titre" value="${esc(r.titre || "")}" placeholder="ex. Visite avec M. Moreau"></label>
    <div class="row-2b"><label>Début<input name="debut" type="datetime-local" required value="${debut}"></label><label>Durée (min)<input name="duree" type="number" value="${duree}"></label></div>
    <label>Lieu<input name="lieu" value="${esc(r.lieu || "")}"></label>
    <label>Notes<textarea name="notes" rows="2">${esc(r.notes || "")}</textarea></label>
    <button class="btn primary big">Enregistrer</button>
    ${r.id ? '<button type="button" class="btn danger-ghost" id="suppr">Supprimer</button>' : ""}
    <button type="button" class="btn ghost" data-close>Annuler</button></form>`);
  sheet.querySelector("#fr").onsubmit = async (e) => {
    e.preventDefault();
    const body = Object.fromEntries(new FormData(e.target));
    body.debut = new Date(body.debut).toISOString();
    body.dossier = r.dossier || dossier || "";
    body.acquereur = r.acquereur || acquereur || "";
    try {
      await api("rdv", { method: "POST", query: r.id ? { id: r.id } : {}, body });
      sheet.fermer();
      toast("Rendez-vous enregistré ✓", "ok");
      apres ? apres() : vues.agenda();
    } catch (err) {
      toast(err.message, "erreur");
    }
  };
  action(sheet.querySelector("#suppr"), async () => {
    if (!confirm("Supprimer ce rendez-vous ?")) return;
    await api("rdv", { method: "DELETE", query: { id: r.id } });
    sheet.fermer();
    apres ? apres() : vues.agenda();
  });
}

function formulaireDispo(d) {
  const sheet = feuille(`<form id="fd"><h2>Mes disponibilités</h2>
    <p class="small muted">L'assistant acquéreurs ne propose des visites que sur ces créneaux, en évitant vos rendez-vous.</p>
    <div class="jours">${[1, 2, 3, 4, 5, 6, 7].map((j) => `<label class="check"><input type="checkbox" name="jours" value="${j}" ${d.jours.includes(j) ? "checked" : ""}> ${JOURS[j]}</label>`).join("")}</div>
    <div class="row-2b"><label>De<input type="time" name="debut" value="${d.debut}"></label><label>À<input type="time" name="fin" value="${d.fin}"></label></div>
    <div class="row-2b"><label>Pause de<input type="time" name="p0" value="${d.pause[0]}"></label><label>à<input type="time" name="p1" value="${d.pause[1]}"></label></div>
    <label>Durée d'une visite (min)<input type="number" name="duree_visite" value="${d.duree_visite}"></label>
    <button class="btn primary big">Enregistrer</button><button type="button" class="btn ghost" data-close>Annuler</button></form>`);
  sheet.querySelector("#fd").onsubmit = async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    await api("disponibilites", { method: "POST", body: { jours: fd.getAll("jours"), debut: fd.get("debut"), fin: fd.get("fin"), pause: [fd.get("p0"), fd.get("p1")], duree_visite: fd.get("duree_visite") } });
    sheet.fermer();
    toast("Disponibilités enregistrées ✓", "ok");
    vues.agenda();
  };
}
