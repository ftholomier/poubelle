// Réseau Synapse : coaching des 90 premiers jours, formation loi ALUR, question au juriste, contacts reçus,
// et pour l'administrateur la vue tête de réseau.

import { api } from "../api.js";
import { vues, ecran, esc, toast, feuille, action, fmtEuros, fmtCourt, state } from "../ui.js";

vues.reseau = async () => {
  const $m = ecran("Réseau Synapse", '<div class="loader"></div>', { back: "#/" });
  const d = await api("reseau");
  const f = d.formation;
  const faits = d.coaching.filter((e) => e.fait_le).length;
  $m.innerHTML = `
    <section class="card">
      <div class="dossier-top"><h2>Vos 90 premiers jours</h2><strong class="pct">${faits}/${d.coaching.length}</strong></div>
      <div class="jauge" style="margin:8px 0 12px"><span style="width:${(faits / d.coaching.length) * 100}%"></span></div>
      ${d.coaching.map((e) => `<div class="ech ech-${e.fait_le ? "fait" : e.en_retard ? "depasse" : "a_venir"}"><span>${e.fait_le ? "✓" : e.jour}</span><div><strong>${esc(e.libelle)}</strong><span class="muted small">${e.fait_le ? `fait le ${fmtCourt(e.fait_le)}` : `objectif : jour ${e.jour} (${fmtCourt(e.objectif)})`}</span></div></div>`).join("")}
      ${d.notes_coach.length ? `<h2 style="margin-top:14px">Messages de votre coach</h2>${d.notes_coach.slice().reverse().map((n) => `<div class="journal-ligne"><span class="muted small">${fmtCourt(n.date)} · ${esc(n.par)}</span><span>${esc(n.texte)}</span></div>`).join("")}` : ""}
    </section>
    ${d.recus.length ? `<section class="card"><h2>Contacts transmis par vos collègues</h2>${d.recus.map((a) => `<a class="ligne" href="#/acquereur/${esc(a.id)}"><div><strong>${esc([a.prenom, a.nom].filter(Boolean).join(" "))}</strong><span class="muted small">de ${esc(a.transmis_par)}</span></div><span class="fleche">→</span></a>`).join("")}</section>` : ""}
    <section class="card"><div class="dossier-top"><h2>Formation loi ALUR</h2><strong>${f.annee} h / ${f.objectif_annee} h</strong></div>
      <div class="jauge" style="margin:8px 0 6px"><span style="width:${Math.min(100, (f.annee / f.objectif_annee) * 100)}%"></span></div>
      <p class="small muted">${f.trois_ans} h sur 3 ans (42 h requises, dont 2 h d'éthique et 2 h de non-discrimination) pour le renouvellement de la carte.</p>
      ${f.liste.slice(0, 5).map((x) => `<div class="m-ligne"><span>${esc(x.intitule)} · ${new Date(x.date).toLocaleDateString("fr-FR")}</span><strong>${x.heures} h</strong></div>`).join("")}
      <button class="btn" id="formation">+ Ajouter une formation</button>
    </section>
    <section class="card"><h2>Une question juridique ?</h2>
      <p class="small muted">${d.juriste ? "Votre question part au juriste du réseau, avec le dossier en PDF si vous en choisissez un." : "Le juriste du réseau n'est pas encore configuré (Paramètres)."}</p>
      <button class="btn ${d.juriste ? "primary" : ""}" id="juriste" ${d.juriste ? "" : "disabled"}>⚖️ Poser une question</button>
      ${d.questions.slice(0, 3).map((q) => `<div class="journal-ligne"><span class="muted small">${fmtCourt(q.cree_le)}</span><span>${esc(q.sujet || q.question.slice(0, 80))}</span></div>`).join("")}
    </section>
    ${d.agents ? `<section class="card"><h2>Tête de réseau · ${d.agents.length} agent(s)</h2>
      <div class="table-scroll"><table class="tableau"><tr><th>Agent</th><th>Mandats</th><th>En vente</th><th>En cours</th><th>Ventes</th><th>CA 12 mois</th><th>Part</th><th>Coaching</th><th>ALUR</th><th></th></tr>
      ${d.agents.map((a) => `<tr><td><strong>${esc(a.nom)}</strong></td><td>${a.mandats}</td><td>${a.en_vente}</td><td>${a.en_cours}</td><td>${a.ventes}</td><td>${fmtEuros(a.ca)}</td><td>${a.taux} %</td><td>${a.coaching}</td><td>${a.formation} h</td><td><button class="btn small-btn" data-note="${a.id}" data-nom="${esc(a.nom)}">✉️</button></td></tr>`).join("")}
      </table></div><p class="small muted">✉️ : message de coaching (notification sur son téléphone).</p></section>` : ""}`;

  document.getElementById("formation").onclick = () => {
    const s = feuille(`<form id="ff"><h2>Formation suivie</h2><label>Intitulé<input name="intitule" required></label>
      <div class="row-2b"><label>Date<input type="date" name="date" required value="${new Date().toISOString().slice(0, 10)}"></label><label>Heures<input name="heures" inputmode="decimal" required></label></div>
      <label>Organisme<input name="organisme"></label><label>Thème<select name="theme"><option value="autre">Général</option><option value="ethique">Éthique et déontologie</option><option value="discrimination">Non-discrimination</option></select></label>
      <button class="btn primary big">Enregistrer</button><button type="button" class="btn ghost" data-close>Annuler</button></form>`);
    s.querySelector("#ff").onsubmit = async (e) => {
      e.preventDefault();
      try {
        await api("formation", { method: "POST", body: Object.fromEntries(new FormData(e.target)) });
        s.fermer();
        vues.reseau();
      } catch (err) {
        toast(err.message, "erreur");
      }
    };
  };
  document.getElementById("juriste").onclick = async () => {
    const biens = await api("visits");
    const s = feuille(`<form id="fj"><h2>⚖️ Question au juriste</h2><label>Sujet<input name="sujet" required placeholder="ex. Indivision, mandat, servitude…"></label>
      <label>Votre question<textarea name="question" rows="5" required></textarea></label>
      <label>Dossier concerné (PDF joint)<select name="dossier"><option value="">Aucun</option>${biens.map((b) => `<option value="${b.id}">${esc(b.titre)}</option>`).join("")}</select></label>
      <button class="btn primary big">Envoyer</button><button type="button" class="btn ghost" data-close>Annuler</button></form>`);
    s.querySelector("#fj").onsubmit = async (e) => {
      e.preventDefault();
      try {
        await api("question_juriste", { method: "POST", body: Object.fromEntries(new FormData(e.target)) });
        s.fermer();
        toast("Question envoyée au juriste ✓", "ok");
        vues.reseau();
      } catch (err) {
        toast(err.message, "erreur");
      }
    };
  };
  $m.querySelectorAll("[data-note]").forEach((b) => (b.onclick = () => {
    const s = feuille(`<form id="fn"><h2>Message à ${esc(b.dataset.nom)}</h2><label>Message<textarea name="texte" rows="4" required></textarea></label><button class="btn primary big">Envoyer</button><button type="button" class="btn ghost" data-close>Annuler</button></form>`);
    s.querySelector("#fn").onsubmit = async (e) => {
      e.preventDefault();
      await api("note_coach", { method: "POST", body: { agent: b.dataset.note, texte: new FormData(e.target).get("texte") } });
      s.fermer();
      toast("Message envoyé ✓", "ok");
    };
  }));
};

/** Bouton « Transmettre à un collègue » sur la fiche acquéreur. */
export async function transmettreAcquereur(acquereurId) {
  const d = await api("reseau");
  if (!d.collegues.length) return toast("Aucun collègue dans le réseau pour l'instant.", "erreur");
  const s = feuille(`<form id="ft"><h2>Transmettre à un collègue</h2><label>Collègue<select name="agent">${d.collegues.map((c) => `<option value="${c.id}">${esc(c.nom)}</option>`).join("")}</select></label>
    <label>Message<textarea name="message" rows="3" placeholder="Il cherche sur ton secteur…"></textarea></label>
    <button class="btn primary big">Transmettre</button><button type="button" class="btn ghost" data-close>Annuler</button></form>`);
  s.querySelector("#ft").onsubmit = async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    await api("partager_acquereur", { method: "POST", body: { acquereur: acquereurId, agent: fd.get("agent"), message: fd.get("message") } });
    s.fermer();
    toast("Contact transmis ✓", "ok");
  };
}

export { state, action };
