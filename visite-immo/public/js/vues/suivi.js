// Suivi du projet en temps réel : toutes les cases de la visite à la diffusion (fait, à faire, en attente,
// pas commencé, prévu dans le vrai logiciel), pourcentage « opérationnel », mise à jour automatique.
// Aussi : le coach de captation affiché pendant l'enregistrement.

import { api } from "../api.js";
import { ecransDossier, outils, esc, toast } from "../ui.js";

export const STATUTS_SUIVI = {
  fait: ["✓", "Fait"],
  a_faire: ["!", "À faire"],
  en_attente: ["◔", "En attente"],
  pas_commence: ["○", "Pas commencé"],
  prevu: ["◌", "Prévu (vrai logiciel)"],
};

function anneau(pct) {
  return `<div class="anneau" style="--p:${pct}"><span><strong>${pct} %</strong><small>opérationnel</small></span></div>`;
}

function compteurs(c) {
  return `<div class="suivi-compteurs">${Object.entries(STATUTS_SUIVI)
    .filter(([k]) => c[k])
    .map(([k, [ic, l]]) => `<span class="sc sc-${k}"><i>${ic}</i>${c[k]} ${l.toLowerCase()}</span>`)
    .join("")}</div>`;
}

/** Carte compacte du résumé : pourcentage, une pastille par case, groupe par groupe. */
outils.suiviCompact = (visit) => {
  const s = visit.suivi;
  return `<a class="card suivi-compact" href="#/visite/${visit.id}/suivi">
    <div class="dossier-top"><h2>Suivi du projet</h2><span class="small"><u>Tout voir</u></span></div>
    <div class="suivi-compact-corps">${anneau(s.pourcentage)}
      <div class="suivi-pastilles">${s.groupes
        .map((g) => `<div><small>${esc(g.titre)}</small><span>${g.items.map((i) => `<i class="pastille st-${i.statut}" title="${esc(i.label)} : ${STATUTS_SUIVI[i.statut][1]}"></i>`).join("")}</span></div>`)
        .join("")}</div>
    </div>
    ${s.prochaine ? `<p class="small">Prochaine case : <strong>${esc(s.prochaine.label)}</strong>${s.prochaine.detail ? ` · <span class="muted">${esc(s.prochaine.detail)}</span>` : ""}</p>` : ""}
  </a>`;
};

function tableau(visit, s) {
  return `
    <section class="card suivi-tete">
      ${anneau(s.pourcentage)}
      <div>
        <h2>${s.pourcentage >= 90 ? "Dossier opérationnel" : s.pourcentage >= 60 ? "Presque prêt" : "En cours de préparation"}</h2>
        ${compteurs(s.compte)}
        <p class="small muted" id="suivi-maj">Mis à jour en direct · ${new Date(s.maj_le).toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit", second: "2-digit" })}</p>
      </div>
    </section>
    ${s.groupes
      .map(
        (g) => `<section class="card suivi-groupe">
        <div class="suivi-groupe-tete"><h2>${esc(g.titre)}</h2><span class="mono small">${g.pourcentage} %</span></div>
        <div class="jauge fine"><span style="width:${g.pourcentage}%"></span></div>
        ${g.items
          .map((i) => {
            const [ic, l] = STATUTS_SUIVI[i.statut];
            const corps = `<i class="st st-${i.statut}" aria-hidden="true">${ic}</i><span class="suivi-txt"><strong>${esc(i.label)}</strong>${i.detail ? `<small>${esc(i.detail)}</small>` : ""}</span><span class="suivi-etat st-txt-${i.statut}">${l}</span>`;
            return i.lien ? `<a class="suivi-case" href="${i.lien}">${corps}</a>` : `<div class="suivi-case">${corps}</div>`;
          })
          .join("")}
      </section>`,
      )
      .join("")}
    <div class="card actions-card">
      <a class="btn" href="api/?r=crm_export&id=${encodeURIComponent(visit.id)}" download>⬇️ Fiche CRM (JSON)</a>
      <p class="small muted">Format d'échange prêt pour le CRM du réseau : mandant, bien, mandat, estimation, annonce, photos, documents. L'envoi automatique se branchera dans le vrai logiciel.</p>
    </div>`;
}

ecransDossier.onglet_suivi = ($c, visit) => {
  $c.innerHTML = tableau(visit, visit.suivi);
  // En direct : on interroge le dossier toutes les 8 s tant que l'écran est ouvert (pièces déposées par le vendeur,
  // signatures, transcription qui se termine…)
  let empreinte = JSON.stringify(visit.suivi.groupes);
  const timer = setInterval(async () => {
    if (!document.querySelector(".suivi-tete")) return clearInterval(timer);
    if (document.hidden) return;
    try {
      const s = await api("suivi", { query: { id: visit.id } });
      const nouvelle = JSON.stringify(s.groupes);
      if (nouvelle !== empreinte) {
        empreinte = nouvelle;
        $c.innerHTML = tableau(visit, s);
        $c.querySelector(".suivi-tete")?.classList.add("flash");
      } else if (document.getElementById("suivi-maj")) {
        document.getElementById("suivi-maj").textContent = `Mis à jour en direct · ${new Date(s.maj_le).toLocaleTimeString("fr-FR", { hour: "2-digit", minute: "2-digit", second: "2-digit" })}`;
      }
    } catch {}
  }, 8000);
};

// ---------- Pendant l'enregistrement : ce que l'IA a déjà capté ----------

/**
 * Panneau sous le bouton d'enregistrement : sujets abordés (d'après la transcription des morceaux envoyés),
 * questions à poser, et prise de photos / documents sans arrêter l'audio.
 */
export function coachCaptation($zone, visitId) {
  $zone.innerHTML = `
    <section class="card captation">
      <div class="dossier-top"><h2>Captation en direct</h2><span class="small muted" id="cap-etat">en attente du 1er morceau</span></div>
      <div class="cap-sujets" id="cap-sujets"></div>
      <p class="small" id="cap-conseil"></p>
      <div class="cap-boutons">
        <label class="btn">📷 Photo du bien<input type="file" accept="image/*" capture="environment" id="cap-photo" hidden multiple></label>
        <label class="btn">📄 Document du vendeur<input type="file" accept="image/*,application/pdf" capture="environment" id="cap-doc" hidden></label>
      </div>
      <p class="small muted" id="cap-compte"></p>
    </section>`;
  const $ = (id) => document.getElementById(id);
  const maj = async () => {
    if (!$("cap-sujets")) return false;
    try {
      const c = await api("captation", { query: { id: visitId } });
      if (!$("cap-sujets")) return false;
      $("cap-sujets").innerHTML = c.sujets.map((s) => `<span class="cap-sujet ${s.couvert ? "ok" : ""}">${s.couvert ? "✓" : "○"} ${esc(s.label)}</span>`).join("");
      const manque = c.sujets.filter((s) => !s.couvert);
      $("cap-etat").textContent = c.morceaux ? `${c.couverts}/${c.sujets.length} sujets · ${c.minutes} min transcrites` : "en attente du 1er morceau (≈ 3 min)";
      $("cap-conseil").innerHTML = manque.length ? `💡 Pensez à demander ${manque.slice(0, 2).map((s) => `<strong>${esc(s.question)}</strong>`).join(" et ")}.` : "🎉 Tous les sujets du mandat ont été abordés.";
      $("cap-compte").textContent = `${c.photos} photo(s) · ${c.documents} document(s) reçu(s)`;
    } catch {}
    return true;
  };
  const envoyer = async (input, route, champ, extra = {}) => {
    const fichiers = [...input.files];
    input.value = "";
    for (const f of fichiers) {
      const form = new FormData();
      form.append(champ, f);
      Object.entries(extra).forEach(([k, v]) => form.append(k, v));
      toast(route === "photos" ? "Photo envoyée, retouche en cours…" : "Document envoyé, l'IA le lit…");
      try {
        await api(route, { method: "POST", query: { id: visitId }, form });
      } catch (e) {
        toast(e.message, "erreur");
      }
    }
    maj();
  };
  $("cap-photo").onchange = (e) => envoyer(e.target, "photos", "photo");
  $("cap-doc").onchange = (e) => envoyer(e.target, "pieces", "fichier", { cle: "auto" });
  maj();
  const timer = setInterval(async () => {
    if (!(await maj())) clearInterval(timer);
  }, 20000);
  return () => clearInterval(timer);
}

