// Acquéreurs : liste, fiche dictée en 30 secondes, biens compatibles, historique.

import { api } from "../api.js";
import { dicter } from "../dictee.js";
import { transmettreAcquereur } from "./reseau.js";
import { vues, ecran, esc, toast, go, action, feuille, fmtPrix, fmtCourt, fmtDate } from "../ui.js";

const STATUTS = { nouveau: ["Nouveau", "orange"], qualifie: ["Qualifié", "vert"], visite: ["A visité", "bleu"], offre: ["Offre", "vert"], achete: ["A acheté", "gris"], perdu: ["Perdu", "gris"] };

export async function nouvelAcquereurDicte() {
  const r = await dicter("acquereur", {
    titre: "Nouvel acquéreur",
    aide: "Dites qui il est, ce qu'il cherche, son budget et son financement.",
    exemple: "Julien Moreau, 06 22 33 44 55, cherche une maison 4 chambres avec jardin à Lougres, budget 430 000, 90 000 d'apport, accord de la banque, pas de bien à vendre.",
  });
  if (!r) return;
  toast(`Fiche créée : ${r.resultat.biens.length} bien(s) compatible(s)`, "ok");
  go(`/acquereur/${r.resultat.acquereur.id}`);
}

vues.acquereurs = async () => {
  const $m = ecran("Acquéreurs", '<div class="loader"></div>', { actif: "acquereurs" });
  const liste = await api("acquereurs");
  const filtre = sessionStorage.getItem("filtre-acq") || "actifs";
  const garde = (a) => (filtre === "actifs" ? !["achete", "perdu"].includes(a.statut) : filtre === "nouveaux" ? a.statut === "nouveau" : true);
  $m.innerHTML = `
    <button class="btn magic big" id="dicter">🎙️ Nouvel acquéreur (dicter)</button>
    <button class="btn ghost" id="saisir">Saisir une fiche</button>
    <div class="filtres" style="margin-top:14px">${[["actifs", "En recherche"], ["nouveaux", "Nouveaux"], ["tous", "Tous"]].map(([k, l]) => `<button class="filtre ${k === filtre ? "on" : ""}" data-f="${k}">${l}</button>`).join("")}</div>
    ${liste.filter(garde).map((a) => {
      const [lib, coul] = STATUTS[a.statut] || [a.statut, "gris"];
      const c = a.criteres || {};
      return `<a class="card visite" href="#/acquereur/${esc(a.id)}">
        <div class="visite-top"><strong>${esc([a.prenom, a.nom].filter(Boolean).join(" ") || "Sans nom")}</strong><span class="badge ${coul}">${esc(lib)}</span></div>
        <div class="muted">${esc([c.type, c.budget_max && `≤ ${fmtPrix(c.budget_max)}`, c.chambres_min && `${c.chambres_min} ch.`, c.villes].filter(Boolean).join(" · ") || "Critères à préciser")}</div>
        <div class="visite-meta">Qualifié à ${a.qualification || 0} % · ${a.nb_biens} bien(s) compatible(s)${a.source ? ` · ${esc(a.source)}` : ""}</div>
      </a>`;
    }).join("") || '<div class="vide"><p>Aucun acquéreur.</p><p class="muted">Dictez sa recherche en 30 secondes après un appel : la fiche se remplit et les biens compatibles s\'affichent.</p></div>'}`;
  $m.querySelectorAll("[data-f]").forEach((b) => (b.onclick = () => (sessionStorage.setItem("filtre-acq", b.dataset.f), vues.acquereurs())));
  document.getElementById("dicter").onclick = nouvelAcquereurDicte;
  document.getElementById("saisir").onclick = () => formulaire({});
};

vues.acquereur = async ([id]) => {
  const $m = ecran("Acquéreur", '<div class="loader"></div>', { actif: "acquereurs", back: "#/acquereurs" });
  const { acquereur: a, biens, visites } = await api("acquereur", { query: { id } });
  const c = a.criteres || {}, f = a.financement || {};
  const [lib, coul] = STATUTS[a.statut] || [a.statut, "gris"];
  document.querySelector(".bar h1").textContent = [a.prenom, a.nom].filter(Boolean).join(" ") || "Acquéreur";
  const ligne = (l, v) => (v ? `<div class="m-ligne"><span>${l}</span><strong>${esc(v)}</strong></div>` : "");
  $m.innerHTML = `
    <section class="card">
      <div class="dossier-top"><span class="badge ${coul}">${esc(lib)}</span><strong class="pct">${a.qualification || 0} %</strong></div>
      <div class="jauge" style="margin:10px 0 12px"><span style="width:${a.qualification || 0}%"></span></div>
      <div class="btn-row">
        ${a.telephone ? `<a class="btn" href="tel:${esc(a.telephone.replace(/\s/g, ""))}">📞 Appeler</a>` : ""}
        ${a.email ? `<a class="btn" href="mailto:${esc(a.email)}">✉️ E-mail</a>` : ""}
      </div>
    </section>
    <section class="card"><h2>Recherche</h2>
      ${ligne("Type", c.type)}${ligne("Budget max", c.budget_max && fmtPrix(c.budget_max))}${ligne("Surface min", c.surface_min && `${c.surface_min} m²`)}
      ${ligne("Pièces min", c.pieces_min)}${ligne("Chambres min", c.chambres_min)}${ligne("Secteur", c.villes)}${ligne("Extérieur", c.exterieur)}${ligne("Autres", c.autres)}
    </section>
    <section class="card"><h2>Financement et délai</h2>
      ${ligne("Apport", f.apport && fmtPrix(f.apport))}${ligne("Financement", f.mode)}${ligne("Banque", f.accord_banque)}${ligne("Courtier", f.courtier)}${ligne("Bien à vendre", f.bien_a_vendre)}${ligne("Délai", a.delai)}
      ${a.notes ? `<p class="small muted">${esc(a.notes)}</p>` : ""}
    </section>
    <section class="card"><h2>Biens compatibles</h2>
      ${biens.map((b) => `<a class="ligne" href="#/visite/${b.id}/vente"><div><strong>${esc(b.titre)}</strong><span class="muted small">${fmtPrix(b.prix)} · ${esc(b.raisons.join(", "))}</span></div><span class="score">${b.score}</span></a>`).join("") || '<p class="muted">Aucun bien compatible pour l\'instant. Il sera prévenu automatiquement dès qu\'un bien correspond.</p>'}
    </section>
    ${visites.length ? `<section class="card"><h2>Visites</h2>${visites.map((x) => `<a class="ligne" href="#/visite/${x.dossier}/vente"><div><strong>${esc(x.bien)}</strong><span class="muted small">${fmtDate(x.date)}${x.retour ? ` · intérêt ${x.retour.interet}/5` : ""}</span></div><span class="fleche">→</span></a>`).join("")}</section>` : ""}
    <section class="card"><h2>Historique</h2>${(a.historique || []).slice().reverse().map((h) => `<div class="journal-ligne"><span class="muted small">${fmtCourt(h.date)}</span><span>${esc(h.texte)}</span></div>`).join("")}</section>
    <div class="card actions-card">
      <button class="btn magic" id="completer">🎙️ Compléter à la voix</button>
      <button class="btn" id="modifier">✏️ Modifier la fiche</button>
      <label>Statut<select id="statut">${Object.entries(STATUTS).map(([k, [l]]) => `<option value="${k}" ${k === a.statut ? "selected" : ""}>${l}</option>`).join("")}</select></label>
      <label class="check"><input type="checkbox" id="alertes" ${a.alertes !== false ? "checked" : ""}> Lui envoyer automatiquement les nouveaux biens compatibles</label>
      <button class="btn" id="transmettre">🤝 Transmettre à un collègue</button>
      <button class="btn danger-ghost" id="suppr">Supprimer la fiche</button>
    </div>`;
  document.getElementById("completer").onclick = async () => {
    const r = await dicter("acquereur", { titre: "Compléter la fiche", aide: "Ajoutez ce que vous avez appris (budget, financement, critères…).", params: { id } });
    if (r) vues.acquereur([id]);
  };
  document.getElementById("modifier").onclick = () => formulaire(a);
  document.getElementById("transmettre").onclick = () => transmettreAcquereur(id);
  const sauver = async (patch) => {
    await api("acquereur", { method: "POST", query: { id }, body: { ...a, ...patch } });
    toast("Enregistré ✓", "ok");
  };
  document.getElementById("statut").onchange = (e) => sauver({ statut: e.target.value });
  document.getElementById("alertes").onchange = (e) => sauver({ alertes: e.target.checked });
  action(document.getElementById("suppr"), async () => {
    if (!confirm("Supprimer cette fiche acquéreur ?")) return;
    await api("acquereur", { method: "DELETE", query: { id } });
    go("/acquereurs");
  });
};

function formulaire(a) {
  const c = a.criteres || {}, f = a.financement || {};
  const champ = (n, l, v, t = "text") => `<label>${l}<input name="${n}" type="${t}" value="${esc(v ?? "")}"></label>`;
  const sheet = feuille(`<form id="fa"><h2>${a.id ? "Modifier" : "Nouvel"} acquéreur</h2>
    <div class="row-2b">${champ("prenom", "Prénom", a.prenom)}${champ("nom", "Nom", a.nom)}</div>
    ${champ("telephone", "Téléphone", a.telephone, "tel")}${champ("email", "E-mail", a.email, "email")}
    <label>Type<select name="c.type">${["", "Maison", "Appartement", "Terrain", "Maison ou appartement"].map((t) => `<option ${t === (c.type || "") ? "selected" : ""}>${t}</option>`).join("")}</select></label>
    <div class="row-2b">${champ("c.budget_max", "Budget max (€)", c.budget_max, "number")}${champ("c.surface_min", "Surface min (m²)", c.surface_min, "number")}</div>
    <div class="row-2b">${champ("c.pieces_min", "Pièces min", c.pieces_min, "number")}${champ("c.chambres_min", "Chambres min", c.chambres_min, "number")}</div>
    ${champ("c.villes", "Villes / secteurs", c.villes)}${champ("c.exterieur", "Extérieur", c.exterieur)}
    <div class="row-2b">${champ("f.apport", "Apport (€)", f.apport, "number")}${champ("f.accord_banque", "Accord banque", f.accord_banque)}</div>
    ${champ("delai", "Délai", a.delai)}${champ("source", "Source", a.source)}
    <label>Notes<textarea name="notes" rows="3">${esc(a.notes || "")}</textarea></label>
    <button class="btn primary big">Enregistrer</button><button type="button" class="btn ghost" data-close>Annuler</button></form>`);
  sheet.querySelector("#fa").onsubmit = async (e) => {
    e.preventDefault();
    const body = { criteres: {}, financement: {}, statut: a.statut, alertes: a.alertes };
    for (const [k, v] of new FormData(e.target)) {
      if (k.startsWith("c.")) body.criteres[k.slice(2)] = v;
      else if (k.startsWith("f.")) body.financement[k.slice(2)] = v;
      else body[k] = v;
    }
    try {
      const r = await api("acquereur", { method: "POST", query: a.id ? { id: a.id } : {}, body });
      sheet.fermer();
      go(`/acquereur/${r.id}`);
      if (a.id) vues.acquereur([a.id]);
    } catch (err) {
      toast(err.message, "erreur");
    }
  };
}
