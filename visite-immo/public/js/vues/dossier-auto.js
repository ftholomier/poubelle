// Écrans du dossier préparés automatiquement après la visite : envoi au vendeur en un geste, signature
// (sur place ou par lien), pièces du vendeur, avis de valeur, croquis de plan, dossier technique, photos,
// publications pour les réseaux sociaux.

import { graphiqueTendance, listeComparables } from "./estimation.js";
import { api } from "../api.js";
import { PadSignature } from "../pad.js";
import {
  state, esc, toast, go, fmtDate, fmtPrix, fmtEuros, copier, feuille, action, pdfUrl, champsOf,
  ecransDossier, actionsDossier, outils,
} from "../ui.js";

const recharger = (visit, onglet) => {
  const h = `#/visite/${visit.id}/${onglet}`;
  if (location.hash === h) outils.route();
  else location.hash = h;
};

// ---------- Envoyer au vendeur, en un geste ----------

actionsDossier.envoyer_vendeur = async (visit) => {
  if (!state.email) {
    feuille(`<h2>Envoyer au vendeur</h2><p>L'envoi d'e-mails n'est pas encore configuré.</p>
      ${state.user.role === "admin" ? '<a class="btn primary" href="#/reglages">⚙️ Configurer l\'envoi</a>' : '<p class="muted">Demandez à un administrateur de le configurer.</p>'}
      <button class="btn ghost" data-close>Fermer</button>`);
    return;
  }
  const b = await api("envoi_vendeur", { query: { id: visit.id } });
  const docs = (visit.documents || []).filter((d) => d.pdf && !d.interne && d.pret && d.cle !== "mandat");
  const sheet = feuille(`<form id="ev">
    <h2>✉️ Tout envoyer au vendeur</h2>
    <label>E-mail du vendeur<input name="to" type="email" required value="${esc(b.to)}" placeholder="vendeur@exemple.fr"></label>
    <label>Objet<input name="sujet" required value="${esc(b.sujet)}"></label>
    <label>Message<textarea name="message" rows="9">${esc(b.message)}</textarea></label>
    <fieldset class="pj"><legend>Pièces jointes</legend>
      ${docs.map((d) => `<label class="check"><input type="checkbox" name="docs" value="${d.cle}" ${b.docs.includes(d.cle) ? "checked" : ""}> ${esc(d.label)}</label>`).join("")}
    </fieldset>
    <label class="check"><input type="checkbox" name="mandat" ${b.mandat_pret ? "checked" : "disabled"}> Mandat à signer en ligne ${b.mandat_pret ? "" : '<span class="muted small">(informations du mandat incomplètes)</span>'}</label>
    ${b.pieces.length ? `<p class="small muted">📎 Le vendeur pourra déposer dans son espace : ${esc(b.pieces.join(", "))}. Relances automatiques à J+2, J+5, J+10.</p>` : ""}
    <p class="small muted">Un lien vers son espace personnel est ajouté au message.</p>
    <button class="btn magic big">Envoyer</button>
    <button type="button" class="btn ghost" data-close>Annuler</button>
  </form>`);
  sheet.querySelector("#ev").onsubmit = async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const btn = e.target.querySelector(".btn.magic");
    btn.disabled = true;
    btn.textContent = "Envoi…";
    try {
      await api("envoi_vendeur", { method: "POST", query: { id: visit.id }, body: { to: fd.get("to"), sujet: fd.get("sujet"), message: fd.get("message"), docs: fd.getAll("docs"), mandat: fd.has("mandat") } });
      sheet.fermer();
      toast("Envoyé au vendeur ✓ Les relances sont automatiques.", "ok");
      recharger(visit, "resume");
    } catch (err) {
      toast(err.message, "erreur");
      btn.disabled = false;
      btn.textContent = "Envoyer";
    }
  };
};

// ---------- Signature ----------

actionsDossier.signer_mandat = (visit) => go(`/visite/${visit.id}/signature/mandat`);

ecransDossier.signature = async ($c, visit) => {
  const cle = decodeURIComponent(location.hash.split("/")[4] || "mandat");
  let d = visit.signatures?.[cle];
  if (cle === "mandat" && (visit.mandat_manques || []).length && !d) {
    $c.innerHTML = `<section class="card"><h2>Mandat incomplet</h2><p>Avant la signature, il manque : ${esc(visit.mandat_manques.join(", "))}.</p>
      <a class="btn magic big" href="#/visite/${visit.id}/dialogue">🎙️ Compléter à la voix</a></section>`;
    return;
  }
  if ((!d || d.statut === "annule") && cle !== "mandat") {
    // Bon de visite, offre… : la demande se crée directement, on peut signer tout de suite
    await api("signature_demande", { method: "POST", query: { id: visit.id }, body: { doc: cle } });
    return recharger(visit, `signature/${encodeURIComponent(cle)}`);
  }
  if (!d || d.statut === "annule") {
    $c.innerHTML = `<section class="card"><span class="tag">Signature</span><h2 class="prochaine-titre">${cle === "mandat" ? "Mandat de vente" : esc(cle)}</h2>
      <p class="muted">${cle === "mandat" ? "Le mandat reçoit son numéro définitif au registre, puis chacun signe : au doigt sur ce téléphone, ou via un lien envoyé par e-mail (avec code de vérification)." : ""}</p>
      <button class="btn magic big" id="creer">✍️ Préparer la signature</button></section>`;
    action(document.getElementById("creer"), async () => {
      if (cle === "mandat" && !confirm("Inscrire le mandat au registre (numéro définitif) et préparer la signature ?")) return;
      await api("signature_demande", { method: "POST", query: { id: visit.id }, body: { doc: cle } });
      recharger(visit, `signature/${encodeURIComponent(cle)}`);
    });
    return;
  }
  const signe = d.statut === "signe";
  const pdfDoc = cle.split(":")[0] === "mandat" ? "mandat" : cle.split(":")[0];
  $c.innerHTML = `
    <section class="card ${signe ? "prochaine ok" : ""}">
      <span class="tag ${signe ? "tag-citron" : ""}">${signe ? "Signé" : "En attente de signature"}</span>
      <h2 class="prochaine-titre">${esc(d.label)}${visit.mandat?.numero && cle === "mandat" ? ` n° ${esc(visit.mandat.numero)}` : ""}</h2>
      <p class="small muted">Créé le ${fmtDate(d.cree_le)}${d.mode !== "interne" ? ` · ${{ firma: "firma.dev", boldsign: "BoldSign" }[d.mode] || "service de signature externe"}${d.api_id ? " : envoyé, chaque signataire a reçu son lien par e-mail" : " : pas encore envoyé"}` : ""}${signe ? ` · complet le ${fmtDate(d.signe_le)}` : ""}</p>
      <a class="btn" href="${pdfUrl(visit.id, pdfDoc, false, { cle })}" target="_blank">📄 ${signe ? "Exemplaire signé (PDF)" : "Voir le document"}</a>
    </section>
    <section class="card"><h2>Signataires</h2>
      ${d.signataires
        .map(
          (s) => `<div class="ligne"><div><strong>${s.signe_le ? "✓ " : ""}${esc(s.nom)}</strong><span class="muted small">${s.role === "agent" ? "Vous (mandataire)" : s.role === "vendeur" ? "Vendeur" : esc(s.role)}${s.signe_le ? ` · signé le ${fmtDate(s.signe_le)} · ${esc(s.methode || "")}` : s.email ? ` · ${esc(s.email)}` : ""}</span></div>
          ${!s.signe_le && d.mode === "interne" ? `<button class="btn small-btn ${s.role === "agent" ? "primary" : ""}" data-signer="${s.id}" data-nom="${esc(s.nom)}">${s.role === "agent" ? "Signer" : "Signer ici"}</button>` : ""}</div>`,
        )
        .join("")}
    </section>
    ${!signe && d.mode === "interne" ? `<div class="card actions-card">
      <button class="btn" id="liens">✉️ Envoyer les liens de signature par e-mail</button>
      <p class="small muted">${(d.envois || []).length ? `Liens envoyés ${d.envois.length} fois, dernier le ${fmtDate(d.envois.at(-1))}. Relances automatiques à J+2 et J+5.` : "Chaque signataire reçoit un lien personnel et un code par e-mail."}</p>
      <button class="btn ghost" id="annuler">Annuler la demande</button></div>` : ""}
    ${!signe && d.mode !== "interne" ? `<div class="card actions-card">
      ${d.api_id
        ? `<button class="btn" id="synchro">🔄 Vérifier l'état chez ${{ firma: "firma.dev", boldsign: "BoldSign" }[d.mode] || "le service"}</button>
           <p class="small muted">Envoyé le ${fmtDate(d.envois?.[0] || d.cree_le)}. Le service relance les signataires et nous prévient dès que tout le monde a signé ; l'exemplaire signé arrive alors dans le dossier.</p>`
        : `<button class="btn magic big" id="liens">✉️ Envoyer pour signature${{ firma: " via firma.dev", boldsign: " via BoldSign" }[d.mode] || ""}</button>
           <p class="small muted">Chaque signataire (vous compris) reçoit un e-mail du service avec le document ; les cases de signature sont déjà placées.</p>`}
      <button class="btn ghost" id="annuler">Annuler la demande</button></div>` : ""}`;

  $c.querySelectorAll("[data-signer]").forEach((b) => (b.onclick = () => padSignature(visit, cle, b.dataset.signer, b.dataset.nom, d.label)));
  action(document.getElementById("liens"), async () => {
    await api("signature_demande", { method: "POST", query: { id: visit.id }, body: { doc: cle, envoyer: true } });
    toast(d.mode === "interne" ? "Liens de signature envoyés ✓" : "Document envoyé pour signature ✓", "ok");
    recharger(visit, `signature/${encodeURIComponent(cle)}`);
  });
  action(document.getElementById("synchro"), async () => {
    const r = await api("signature_synchro", { method: "POST", query: { id: visit.id }, body: { doc: cle } });
    toast(r.etat === "signe" ? "Signé par tous ✓" : r.etat === "annule" ? "Demande refusée, expirée ou annulée" : r.etat ? "Toujours en attente de signature" : "Service injoignable pour l'instant", r.etat === "signe" ? "ok" : "");
    recharger(visit, `signature/${encodeURIComponent(cle)}`);
  });
  action(document.getElementById("annuler"), async () => {
    if (!confirm("Annuler cette demande de signature ?")) return;
    await api("signature_annuler", { method: "POST", query: { id: visit.id }, body: { doc: cle } });
    recharger(visit, `signature/${encodeURIComponent(cle)}`);
  });
};

/** Signature sur place : le signataire signe au doigt sur le téléphone de l'agent. */
export function padSignature(visit, cle, signataire, nom, label, apres) {
  const sheet = feuille(`<h2>✍️ ${esc(nom)}</h2>
    <p class="small muted">${esc(label)} · ${esc(visit.titre || "")}</p>
    <label class="check"><input type="checkbox" id="lu"> J'ai lu le document et j'accepte de le signer électroniquement.</label>
    <p class="small muted">Signez avec le doigt dans le cadre :</p>
    <canvas class="pad"></canvas>
    <div class="btn-row"><button class="btn ghost" id="effacer">Effacer</button><button class="btn magic" id="valider">Valider la signature</button></div>
    <button class="btn ghost" data-close>Annuler</button>`);
  const pad = new PadSignature(sheet.querySelector(".pad"));
  sheet.querySelector("#effacer").onclick = () => pad.effacer();
  action(sheet.querySelector("#valider"), async () => {
    if (!sheet.querySelector("#lu").checked) throw new Error("Cochez la case « J'ai lu le document ».");
    const image = pad.png();
    if (!image) throw new Error("Signez dans le cadre.");
    await api("signer", { method: "POST", query: { id: visit.id }, body: { doc: cle, signataire, image } });
    sheet.fermer();
    navigator.vibrate?.([60, 40, 60]);
    toast(`Signature de ${nom} enregistrée ✓`, "ok");
    apres ? apres() : recharger(visit, `signature/${encodeURIComponent(cle)}`);
  });
}

// ---------- Pièces du vendeur ----------

actionsDossier.pieces = (visit) => go(`/visite/${visit.id}/pieces`);

ecransDossier.pieces = async ($c, visit) => {
  const r = await api("pieces", { query: { id: visit.id } });
  const alertes = r.alertes.filter((a) => !a.vu);
  const manque = r.pieces.filter((p) => p.statut !== "recue" && !p.facultative).length;
  $c.innerHTML = `
    ${alertes.length ? `<section class="card alerte"><h2>⚠ Points de vigilance lus dans les documents</h2>${alertes.map((a) => `<p>${esc(a.texte)}</p>`).join("")}<button class="btn small-btn" id="vu">C'est noté</button></section>` : ""}
    <section class="card">
      <div class="dossier-top"><h2>Pièces du vendeur</h2><strong>${manque ? `${manque} à recevoir` : "complet ✓"}</strong></div>
      <p class="small muted">${r.relances.length ? `${r.relances.length} relance(s) automatique(s), dernière le ${fmtDate(r.relances.at(-1))}.` : "Le vendeur dépose ses papiers depuis son espace ; relances automatiques après l'envoi."}</p>
      ${r.pieces
        .map(
          (p) => `<div class="ligne piece ${p.statut === "recue" ? "ok" : ""}"><div>
            <strong>${p.statut === "recue" ? "✓ " : ""}${esc(p.label)}</strong>
            ${p.aide ? `<span class="muted small">${esc(p.aide)}</span>` : ""}${p.facultative ? '<span class="muted small"> · facultatif</span>' : ""}
            ${(p.fichiers || []).map((f) => `<a class="small piece-fichier" href="api/?r=piece&id=${visit.id}&f=${encodeURIComponent(f.nom)}" target="_blank">📎 ${esc(f.resume || f.nom)}${f.par === "vendeur" ? " · déposé par le vendeur" : ""}</a>`).join("")}
          </div>
          <label class="btn small-btn">📷<input type="file" accept="image/*,application/pdf" data-piece="${esc(p.cle)}" hidden></label></div>`,
        )
        .join("")}
      <label class="btn magic big" style="margin-top:14px">📷 Ajouter un document (l'IA reconnaît la pièce)<input type="file" accept="image/*,application/pdf" data-piece="auto" hidden></label>
    </section>`;
  $c.querySelectorAll("input[data-piece]").forEach((input) => {
    input.onchange = async () => {
      const f = input.files[0];
      if (!f) return;
      const fd = new FormData();
      fd.append("fichier", f);
      fd.append("cle", input.dataset.piece);
      toast("Lecture du document…");
      try {
        await api("pieces", { method: "POST", query: { id: visit.id }, form: fd });
        toast("Document lu et rangé ✓", "ok");
        ecransDossier.pieces($c, visit);
      } catch (e) {
        toast(e.message, "erreur");
      }
    };
  });
  action(document.getElementById("vu"), async () => {
    await api("alertes_vues", { method: "POST", query: { id: visit.id } });
    ecransDossier.pieces($c, visit);
  });
};

// ---------- Avis de valeur ----------

ecransDossier.avis = ($c, visit) => {
  const a = visit.avis_valeur;
  if (!a) {
    $c.innerHTML = `<section class="card"><h2>Avis de valeur</h2><p>Pas encore calculé : il faut l'adresse et la surface habitable.</p><button class="btn magic big" id="calc">↻ Calculer</button></section>`;
    action(document.getElementById("calc"), async () => {
      await api("preparer", { method: "POST", query: { id: visit.id } });
      recharger(visit, "avis");
    });
    return;
  }
  const ecart = a.ecart_vendeur;
  $c.innerHTML = `
    <section class="card">
      <h2>Avis de valeur ${a.simulation ? '<span class="badge orange">ventes simulées</span>' : ""}</h2>
      <div class="chiffres">
        <div class="chiffre noir"><small>Prix conseillé</small><strong>${fmtPrix(a.retenu)}</strong></div>
        <div class="chiffre"><small>Fourchette</small><strong>${fmtPrix(a.bas)} – ${fmtPrix(a.haut)}</strong></div>
        <div class="chiffre"><small>Médiane secteur</small><strong>${fmtEuros(a.prix_m2_median)}/m²</strong></div>
        ${a.prix_vendeur ? `<div class="chiffre ${Math.abs(ecart) >= 5 ? "citron" : ""}"><small>Prix du vendeur</small><strong>${fmtPrix(a.prix_vendeur)}</strong><span class="small">${ecart > 0 ? "+" : ""}${ecart} %</span></div>` : ""}
      </div>
      ${a.confiance ? `<div class="estim-infos"><span class="confiance confiance-${a.confiance.niveau === "élevée" ? "haute" : a.confiance.niveau === "moyenne" ? "moyenne" : "basse"}">Confiance ${esc(a.confiance.niveau)}</span>${a.direct ? "<span>Mis à jour en direct à chaque modification de la fiche</span>" : ""}</div>` : ""}
      ${a.tendance ? graphiqueTendance(a.tendance) : ""}
      <label>Prix conseillé retenu<input id="retenu" inputmode="numeric" value="${a.retenu}"></label>
      <div class="btn-row"><button class="btn primary" id="garder">Enregistrer ce prix</button><button class="btn" id="recalc">↻ Recalculer</button></div>
    </section>
    <section class="card"><h2>Argumentaire</h2>${a.argumentaire_perime ? `<p class="small orange-txt">Les chiffres ont changé depuis la rédaction : touchez « Recalculer » pour réécrire l'argumentaire.</p>` : ""}<div class="texte">${esc(a.argumentaire).replace(/\n/g, "<br>")}</div></section>
    <section class="card"><h2>${a.comparables.length} ventes comparables (DVF)</h2>
      ${a.comparables[0]?.similarite != null ? listeComparables(a.comparables) : `<div class="table-scroll"><table class="tableau"><tr><th>Date</th><th>Adresse</th><th>Surf.</th><th>Prix</th><th>€/m²</th><th>Dist.</th></tr>
      ${a.comparables.map((c) => `<tr><td>${new Date(c.date).toLocaleDateString("fr-FR", { month: "2-digit", year: "2-digit" })}</td><td>${esc(c.adresse)}</td><td>${Math.round(c.surface)} m²</td><td>${fmtPrix(c.prix)}</td><td>${Number(c.prix_m2).toLocaleString("fr-FR")}</td><td>${c.distance != null ? c.distance + " m" : "—"}</td></tr>`).join("")}
      </table></div>`}
      ${a.ajustements.length ? `<p class="small muted">Ajustements : ${a.ajustements.map((x) => `${esc(x[0])} ${x[1] > 0 ? "+" : ""}${Math.round(x[1] * 100)} %`).join(" · ")}</p>` : ""}
    </section>
    <div class="sticky-actions"><button class="btn primary" data-pdf="avis">📄 PDF</button><button class="btn" data-send="avis">✉️ Envoyer</button></div>`;
  outils.bindDocActions($c, visit, { flush: async () => {} });
  action(document.getElementById("garder"), async () => {
    await api("avis", { method: "POST", query: { id: visit.id }, body: { retenu: document.getElementById("retenu").value } });
    toast("Prix conseillé enregistré ✓", "ok");
    recharger(visit, "avis");
  });
  action(document.getElementById("recalc"), async () => {
    await api("avis", { method: "POST", query: { id: visit.id }, body: {} });
    recharger(visit, "avis");
  });
};

// ---------- Croquis de plan ----------

ecransDossier.plan = async ($c, visit) => {
  const { svg } = await api("plan", { query: { id: visit.id } });
  const pieces = visit.plan?.pieces || [];
  const ligne = (p = { nom: "", surface: "", niveau: "RDC" }) => `<div class="plan-ligne"><input data-k="nom" value="${esc(p.nom)}" placeholder="Pièce"><input data-k="surface" inputmode="decimal" value="${p.surface || ""}" placeholder="m²"><input data-k="niveau" value="${esc(p.niveau || "RDC")}" placeholder="Niveau"><button class="icon-btn" data-suppr>✕</button></div>`;
  $c.innerHTML = `
    <section class="card"><h2>Croquis de plan</h2>${svg || '<p class="muted">Aucune pièce citée pendant la visite : ajoutez-les ci-dessous.</p>'}
      <p class="small muted">Disposition indicative, non cotée. Les surfaces suivies de ≈ sont estimées.</p></section>
    <section class="card"><h2>Pièces</h2><div id="lignes">${pieces.map(ligne).join("")}</div>
      <button class="btn" id="ajout">+ Ajouter une pièce</button>
      <button class="btn primary big" id="maj" style="margin-top:10px">Mettre à jour le plan</button></section>
    <div class="sticky-actions"><button class="btn primary" data-pdf="plan">📄 PDF</button><button class="btn" data-send="plan">✉️ Envoyer</button></div>`;
  outils.bindDocActions($c, visit, { flush: async () => {} });
  const $l = document.getElementById("lignes");
  $l.addEventListener("click", (e) => e.target.closest("[data-suppr]")?.closest(".plan-ligne").remove());
  document.getElementById("ajout").onclick = () => $l.insertAdjacentHTML("beforeend", ligne());
  action(document.getElementById("maj"), async () => {
    const pieces = [...$l.querySelectorAll(".plan-ligne")].map((l) => Object.fromEntries([...l.querySelectorAll("[data-k]")].map((i) => [i.dataset.k, i.value])));
    await api("plan", { method: "POST", query: { id: visit.id }, body: { pieces } });
    toast("Plan mis à jour ✓", "ok");
    recharger(visit, "plan");
  });
};

// ---------- Dossier technique ----------

ecransDossier.technique = ($c, visit) => {
  const p = visit.public;
  if (!p) {
    $c.innerHTML = `<section class="card"><p>Pas encore récupéré.</p><button class="btn magic big" id="go">Récupérer cadastre, risques et DPE</button></section>`;
  } else {
    const r = p.risques || {}, c = p.cadastre || {}, d = p.dpe || {};
    $c.innerHTML = `
      ${p.simulation ? '<div class="banner">Service public injoignable : une partie des données est simulée pour le test.</div>' : ""}
      <section class="card"><h2>Localisation</h2><p><strong>${esc(p.geo?.label || "")}</strong></p>
        ${c.numero ? `<p>Parcelle section <strong>${esc(c.section)} n° ${esc(c.numero)}</strong>${c.contenance ? ` · ${c.contenance.toLocaleString("fr-FR")} m²` : ""}</p>` : ""}</section>
      <section class="card"><h2>Risques (Géorisques)</h2>${(r.liste || []).map((x) => `<p>• ${esc(x)}</p>`).join("") || "<p>Aucun risque recensé.</p>"}
        ${r.sismicite ? `<p class="small">Sismicité : ${esc(r.sismicite)}${r.radon ? ` · Radon : ${esc(r.radon)}` : ""}</p>` : ""}</section>
      <section class="card"><h2>DPE enregistré (ADEME)</h2>${d.trouve ? `<p>Classe <strong>${esc(d.dpe)}</strong> · GES <strong>${esc(d.ges)}</strong> · établi le ${new Date(d.date).toLocaleDateString("fr-FR")} (n° ${esc(d.numero)})</p>` : "<p>Aucun DPE enregistré : à commander avant la mise en vente.</p>"}</section>
      <p class="small muted">Sources : ${esc((p.sources || []).join(", ") || "—")} · ${fmtDate(p.maj_le)}</p>
      <div class="sticky-actions"><button class="btn primary" data-pdf="technique">📄 PDF</button><button class="btn" id="go">↻ Actualiser</button></div>`;
    outils.bindDocActions($c, visit, { flush: async () => {} });
  }
  action(document.getElementById("go"), async () => {
    await api("preparer", { method: "POST", query: { id: visit.id } });
    recharger(visit, "technique");
  });
};

// ---------- Publications réseaux sociaux ----------

ecransDossier.social = ($c, visit) => {
  const posts = visit.posts || {};
  $c.innerHTML = `${Object.entries({ instagram: "Instagram", facebook: "Facebook", linkedin: "LinkedIn" })
    .filter(([k]) => posts[k])
    .map(([k, l]) => `<section class="card"><div class="dossier-top"><h2>${l}</h2><button class="btn small-btn" data-copy="${k}">📋 Copier</button></div><div class="texte">${esc(posts[k]).replace(/\n/g, "<br>")}</div></section>`)
    .join("")}<div id="visuels"></div>`;
  $c.querySelectorAll("[data-copy]").forEach((b) => (b.onclick = () => copier(posts[b.dataset.copy])));
  ecransDossier.visuels?.(document.getElementById("visuels"), visit);
};

// ---------- Photos ----------

ecransDossier.onglet_photos = ($c, visit) => {
  const photos = visit.photos || [];
  const src = (f, mini = true) => `api/?r=photo&id=${visit.id}&f=${encodeURIComponent(f)}${mini ? "&mini=1" : ""}`;
  $c.innerHTML = `
    <section class="card">
      <div class="dossier-top"><h2>Photos du bien</h2><span class="muted small">${photos.length} photo${photos.length > 1 ? "s" : ""}</span></div>
      <label class="btn magic big">📷 Prendre ou ajouter des photos<input type="file" id="ajout" accept="image/*" multiple hidden></label>
      <p class="small muted">Retouche automatique (lumière, netteté), pièce reconnue par l'IA, photos floues signalées, ordre conseillé pour l'annonce.</p>
      <p class="small" id="progression"></p>
    </section>
    ${photos.length ? `<div class="photos-grille">${photos
      .map(
        (p, i) => `<div class="photo-carte ${p.floue ? "floue" : ""}">
          <img src="${src(p.staging?.fichier || p.fichier)}" alt="${esc(p.piece)}" loading="lazy" data-zoom="${esc(p.staging?.fichier || p.fichier)}">
          <div class="photo-info"><strong>${i + 1}. ${esc(p.piece)}</strong>${p.floue ? '<span class="badge rouge">floue</span>' : ""}${p.staging ? '<span class="badge vert">staging</span>' : ""}</div>
          <div class="photo-actions">
            <button class="icon-btn" data-haut="${esc(p.fichier)}" aria-label="Monter">↑</button>
            <button class="icon-btn" data-staging="${esc(p.fichier)}" aria-label="Home staging">🛋️</button>
            <button class="icon-btn" data-suppr="${esc(p.fichier)}" aria-label="Supprimer">🗑</button>
          </div></div>`,
      )
      .join("")}</div>
      <button class="btn" id="trier">✨ Remettre dans l'ordre conseillé</button>` : ""}`;

  const maj = (r) => {
    visit.photos = r.photos;
    ecransDossier.onglet_photos($c, visit);
  };
  document.getElementById("ajout").onchange = async (e) => {
    const files = [...e.target.files];
    let n = 0, r = null;
    for (const f of files) {
      document.getElementById("progression").textContent = `Retouche et analyse : ${++n}/${files.length}…`;
      const fd = new FormData();
      fd.append("photo", f);
      try {
        r = await api("photos", { method: "POST", query: { id: visit.id }, form: fd });
      } catch (err) {
        toast(`${f.name} : ${err.message}`, "erreur");
      }
    }
    if (r) maj(r);
  };
  const ordre = () => photos.map((p) => p.fichier);
  $c.querySelectorAll("[data-haut]").forEach((b) => (b.onclick = async () => {
    const o = ordre();
    const i = o.indexOf(b.dataset.haut);
    if (i > 0) [o[i - 1], o[i]] = [o[i], o[i - 1]];
    maj(await api("photos_ordre", { method: "POST", query: { id: visit.id }, body: { ordre: o } }));
  }));
  $c.querySelectorAll("[data-suppr]").forEach((b) => (b.onclick = async () => {
    if (!confirm("Supprimer cette photo ?")) return;
    maj(await api("photos_ordre", { method: "POST", query: { id: visit.id }, body: { supprimer: b.dataset.suppr } }));
  }));
  $c.querySelectorAll("[data-staging]").forEach((b) => (b.onclick = () => {
    const sheet = feuille(`<h2>🛋️ Home staging virtuel</h2><p class="small muted">L'IA meuble ou vide la pièce sans toucher à l'architecture. La mention « aménagement virtuel » est ajoutée sur l'image, comme il se doit.</p>
      ${["contemporain", "scandinave", "industriel", "vider"].map((s) => `<button class="btn" data-style="${s}">${s === "vider" ? "Vider la pièce" : "Style " + s}</button>`).join("")}
      <button class="btn ghost" data-close>Annuler</button>`);
    sheet.querySelectorAll("[data-style]").forEach((x) => action(x, async () => {
      const r = await api("staging", { method: "POST", query: { id: visit.id }, body: { fichier: b.dataset.staging, style: x.dataset.style } });
      sheet.fermer();
      toast("Home staging prêt ✓", "ok");
      maj(r);
    }));
  }));
  $c.querySelectorAll("[data-zoom]").forEach((img) => (img.onclick = () => feuille(`<img class="photo-zoom" src="${src(img.dataset.zoom, false)}" alt=""><button class="btn ghost" data-close>Fermer</button>`)));
  action(document.getElementById("trier"), async () => maj(await api("photos_ordre", { method: "POST", query: { id: visit.id }, body: { trier: true } })));
};

export { champsOf };
