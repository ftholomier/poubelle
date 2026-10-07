// Écran « Aujourd'hui » : la liste de ce qu'il y a à faire, préparée automatiquement à partir de tous les dossiers.

import { api } from "../api.js";
import { state, vues, ecran, esc, demoBanner, fmtHeure, ilYa } from "../ui.js";

/** Les modules ajoutent ici des blocs en haut de l'écran (briefing, commande vocale…) : fn(data) => html, puis bind($m). */
export const blocsAujourdhui = [];

const ICONES = { dossier: "▦", rdv: "▤", relance: "↻", piece: "📎", signature: "✍️", contact: "◉", echeance: "⏰", tache: "☐", info: "✓" };

vues.aujourdhui = async () => {
  const $m = ecran("", '<div class="loader"></div>', { actif: "aujourdhui" });
  const data = await api("aujourdhui");
  const prenom = (state.user.nom || "").split(" ")[0];
  const jour = new Date().toLocaleDateString("fr-FR", { weekday: "long", day: "numeric", month: "long" });
  const urgents = data.elements.filter((e) => e.priorite === 1);
  const autres = data.elements.filter((e) => e.priorite !== 1 && e.type !== "info");
  const infos = data.elements.filter((e) => e.type === "info");

  const ligne = (e) => `<a class="ajd-item ${e.priorite === 1 ? "urgent" : ""}" href="${esc(e.lien || "#/")}">
      ${e.tache ? `<span class="ajd-ic ajd-check" data-tache="${esc(e.tache)}" title="Marquer comme fait">✓</span>` : `<span class="ajd-ic">${ICONES[e.type] || "•"}</span>`}
      <span class="ajd-txt"><strong>${esc(e.titre)}</strong><span class="muted small">${esc(e.detail || "")}</span></span>
      ${e.heure ? `<span class="ajd-heure">${esc(e.heure)}</span>` : '<span class="fleche">→</span>'}
    </a>`;

  $m.innerHTML = `
    <div class="home-hero"><span class="tag">${esc(jour)}</span><h2 style="margin-top:14px">Bonjour ${esc(prenom)}.<br><mark>${data.elements.length ? `${urgents.length + autres.length} chose${urgents.length + autres.length > 1 ? "s" : ""} à faire.` : "Tout est à jour."}</mark></h2></div>
    ${demoBanner()}
    ${blocsAujourdhui.map((b) => b.html(data)).join("")}
    ${urgents.length ? `<section class="card"><h2>En priorité</h2>${urgents.map(ligne).join("")}</section>` : ""}
    ${autres.length ? `<section class="card"><h2>Ensuite</h2>${autres.map(ligne).join("")}</section>` : ""}
    ${infos.length ? `<section class="card"><h2>Fait automatiquement</h2>${infos.map(ligne).join("")}</section>` : ""}
    ${!data.elements.length ? `<div class="vide"><p>Rien d'urgent.</p><p class="muted">Enregistrez une visite avec le bouton rouge : fiche, annonce, mandat et relances se préparent tout seuls.</p></div>` : ""}
    <p class="small muted center">${data.cron ? `Tâches automatiques : dernier passage ${ilYa(data.cron)}` : "Tâches automatiques : pas encore lancées (voir Paramètres)"}</p>`;
  blocsAujourdhui.forEach((b) => b.bind?.($m, data));
};

export { fmtHeure };
