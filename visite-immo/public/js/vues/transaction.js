// Onglet Vente, suite : offres d'achat (dictée, signature, transmission au vendeur), puis du compromis à l'acte
// (intervenants, dates, échéancier, contrôle anti-blanchiment, dossier notaire, acte → facture et commission).

import { api } from "../api.js";
import { dicter } from "../dictee.js";
import { blocsVente } from "./vente.js";
import { actionsDossier, esc, toast, go, action, feuille, fmtDate, fmtPrix, fmtEuros, pdfUrl, outils } from "../ui.js";

const STATUT_OFFRE = { redigee: ["À signer par l'acquéreur", "orange"], transmise: ["Chez le vendeur", "bleu"], acceptee: ["Acceptée", "vert"], refusee: ["Refusée", "rouge"], contre_offre: ["Contre-proposition", "orange"], non_retenue: ["Non retenue", "gris"] };
const recharger = (visit) => (location.hash.endsWith("/vente") ? outils.route() : go(`/visite/${visit.id}/vente`));

actionsDossier.vente = (visit) => go(`/visite/${visit.id}/vente`);
actionsDossier.suivre = actionsDossier.vente;

// ---------- Offres ----------

blocsVente.push({
  ordre: 40,
  prioritaire: (v) => (v.offres || []).some((o) => ["redigee", "transmise"].includes(o.statut)),
  async rendre($el, visit) {
    if (!["en_vente", "offre", "compromis", "vendu"].includes(visit.etape) && !(visit.offres || []).length) return;
    const offres = (visit.offres || []).slice().reverse();
    $el.innerHTML = `<section class="card"><h2>Offres d'achat</h2>
      ${offres.map((o) => {
        const [lib, coul] = STATUT_OFFRE[o.statut] || [o.statut, "gris"];
        return `<div class="visite-acq"><div class="dossier-top"><strong>${esc(o.nom)} · ${fmtPrix(o.montant)}</strong><span class="badge ${coul}">${esc(lib)}</span></div>
          <p class="small muted">${fmtDate(o.date)} · ${o.financement === "comptant" ? "comptant" : `prêt ${fmtPrix(o.pret.montant)}`} · valable jusqu'au ${new Date(o.validite).toLocaleDateString("fr-FR")}${o.reponse?.prix ? ` · le vendeur propose ${fmtPrix(o.reponse.prix)}` : ""}${o.reponse?.message ? ` · « ${esc(o.reponse.message)} »` : ""}</p>
          <div class="btn-row">
            <a class="btn small-btn" target="_blank" href="${pdfUrl(visit.id, "offre", false, { cle: "offre:" + o.id })}">📄 Offre</a>
            ${o.statut === "redigee" ? `<button class="btn small-btn primary" data-signer-offre="${o.id}">✍️ Faire signer</button>` : ""}
            ${o.statut === "transmise" ? `<button class="btn small-btn" data-papier="${o.id}">Acceptée sur papier</button>` : ""}
          </div></div>`;
      }).join("") || '<p class="muted">Aucune offre pour l\'instant.</p>'}
      ${visit.etape === "en_vente" || visit.etape === "offre" ? '<button class="btn magic" id="dicter-offre" style="margin-top:10px">🎙️ Dicter une offre</button>' : ""}
    </section>`;
    $el.querySelector("#dicter-offre")?.addEventListener("click", async () => {
      const r = await dicter("offre", { titre: "Offre d'achat", aide: "Qui, combien, comment c'est financé, la durée de validité.", exemple: "Julien Moreau propose 398 000, prêt de 320 000 sur 25 ans à 3,9 % maximum, offre valable 10 jours.", params: { dossier: visit.id } });
      if (r) {
        toast("Offre rédigée ✓ Faites-la signer par l'acquéreur.", "ok");
        go(`/visite/${visit.id}/signature/${encodeURIComponent("offre:" + r.resultat.offre.id)}`);
      }
    });
    $el.querySelectorAll("[data-signer-offre]").forEach((b) => (b.onclick = () => go(`/visite/${visit.id}/signature/${encodeURIComponent("offre:" + b.dataset.signerOffre)}`)));
    $el.querySelectorAll("[data-papier]").forEach((b) => action(b, async () => {
      if (!confirm("Le vendeur a signé l'acceptation sur papier ? Elle sera enregistrée comme acceptée.")) return;
      await api("offre", { method: "POST", query: { id: visit.id }, body: { offre: b.dataset.papier, action: "acceptee_hors_ligne" } });
      recharger(visit);
    }));
  },
});

// ---------- Du compromis à l'acte ----------

const ETAT_ECH = { fait: "✓", a_venir: "◷", depasse: "⚠", a_saisir: "…" };

blocsVente.push({
  ordre: 50,
  prioritaire: (v) => Boolean(v.vente?.debut),
  async rendre($el, visit) {
    const s = visit.vente;
    if (!s?.debut) return;
    const l = visit.lcbft || {};
    const dt = (k) => (s[k] ? String(s[k]).slice(0, 10) : "");
    const intervenant = (k, titre) => `<fieldset class="pj"><legend>${titre}</legend><div class="row-2b"><input data-i="${k}.nom" placeholder="Nom" aria-label="${titre} : nom" value="${esc(s[k]?.nom || "")}"><input data-i="${k}.email" type="email" placeholder="E-mail" aria-label="${titre} : e-mail" value="${esc(s[k]?.email || "")}"></div></fieldset>`;
    $el.innerHTML = `
      <section class="card"><div class="dossier-top"><h2>La vente</h2><strong>${fmtPrix(s.prix)}</strong></div>
        <p class="small muted">Acquéreur : ${esc(s.acquereur_nom || "")}</p>
        <div class="echeancier">${(visit.echeancier || []).map((e) => `<div class="ech ech-${e.etat}"><span>${ETAT_ECH[e.etat]}</span><div><strong>${esc(e.label)}</strong><span class="muted small">${e.date ? new Date(e.date + "T12:00").toLocaleDateString("fr-FR", { weekday: "short", day: "numeric", month: "short" }) : "date à saisir"}${e.detail ? ` · ${esc(e.detail)}` : ""}</span></div></div>`).join("")}</div>
        <p class="small muted">Relances automatiques : prêt à J-15 et J-5 (acquéreur et courtier), notaires à J-10, rappel aux parties à J-2.</p>
      </section>
      <form class="card" id="f-vente"><h2>Dates</h2>
        <div class="row-2b"><label>Compromis prévu le<input type="date" name="compromis_prevu" value="${esc(dt("compromis_prevu"))}"></label><label>Compromis signé le<input type="date" name="compromis_le" value="${esc(dt("compromis_le"))}"></label></div>
        <div class="row-2b"><label>Notification SRU le<input type="date" name="notification_sru" value="${esc(dt("notification_sru"))}"></label><label>Financement<select name="financement">${[["pret", "Prêt"], ["comptant", "Comptant"], ["mixte", "Apport + prêt"]].map(([k, l]) => `<option value="${k}" ${k === (s.financement || "pret") ? "selected" : ""}>${l}</option>`).join("")}</select></label></div>
        <div class="row-2b"><label>Prêt : date limite<input type="date" name="pret_limite" value="${esc(dt("pret_limite"))}"></label><label>Offre de prêt reçue le<input type="date" name="pret_obtenu_le" value="${esc(dt("pret_obtenu_le"))}"></label></div>
        <div class="row-2b"><label>Acte prévu le<input type="date" name="acte_prevu" value="${esc(dt("acte_prevu"))}"></label><label>Lieu de l'acte<input name="acte_lieu" value="${esc(s.acte_lieu || "")}"></label></div>
        ${intervenant("notaire_vendeur", "Notaire du vendeur")}${intervenant("notaire_acquereur", "Notaire de l'acquéreur")}${intervenant("courtier", "Courtier")}
        <button class="btn primary">Enregistrer</button>
      </form>
      <section class="card"><h2>Contrôle anti-blanchiment</h2>
        ${["vendeur", "acquereur"].map((p) => `<div class="ligne"><div><strong>${p === "vendeur" ? "Vendeur" : "Acquéreur"}</strong><span class="muted small">${l[p] ? `Vigilance ${esc(l[p].niveau)} · ${esc(l[p].gels.detail)}` : "À faire : photo de la pièce d'identité"}</span></div>
          <div class="doc-actions">${l[p] ? `<a class="icon-btn" target="_blank" href="${pdfUrl(visit.id, "vigilance", false, { partie: p })}">📄</a>` : ""}<button class="btn small-btn ${l[p] ? "" : "primary"}" data-lcbft="${p}">${l[p] ? "Refaire" : "Contrôler"}</button></div></div>`).join("")}
      </section>
      <section class="card actions-card"><h2>Notaires et acte</h2>
        <a class="btn" target="_blank" href="${pdfUrl(visit.id, "notaire")}">📄 Fiche de renseignements</a>
        <button class="btn ${s.notaires_envoye_le ? "" : "primary"}" id="notaires">📨 ${s.notaires_envoye_le ? `Renvoyer aux notaires (envoyé le ${new Date(s.notaires_envoye_le).toLocaleDateString("fr-FR")})` : "Envoyer le dossier aux notaires"}</button>
        ${s.acte_le
          ? `<div class="prochaine ok card"><span class="tag tag-citron">Vendu</span><h2 class="prochaine-titre">Acte signé le ${new Date(s.acte_le + "T12:00").toLocaleDateString("fr-FR")}</h2>
              <p>Facture ${esc(s.facture.numero)} · ${fmtEuros(s.facture.ttc, 2)} TTC<br>Votre commission : <strong>${s.commission.taux} %</strong> soit ${fmtEuros(s.commission.montant, 2)} HT (palier ${s.commission.palier})</p>
              <div class="btn-row"><a class="btn" target="_blank" href="${pdfUrl(visit.id, "facture")}">📄 Facture</a><a class="btn" target="_blank" href="${pdfUrl(visit.id, "commission")}">📄 Commission</a></div></div>`
          : '<button class="btn magic big" id="acte">🔑 Acte signé</button>'}
      </section>`;

    $el.querySelector("#f-vente").onsubmit = async (e) => {
      e.preventDefault();
      const body = Object.fromEntries(new FormData(e.target));
      for (const i of e.target.querySelectorAll("[data-i]")) {
        const [k, c] = i.dataset.i.split(".");
        (body[k] ||= {})[c] = i.value;
      }
      try {
        await api("vente", { method: "POST", query: { id: visit.id }, body });
        toast("Enregistré ✓ Échéancier mis à jour.", "ok");
        recharger(visit);
      } catch (err) {
        toast(err.message, "erreur");
      }
    };
    action($el.querySelector("#notaires"), async () => {
      await api("envoyer_notaires", { method: "POST", query: { id: visit.id } });
      toast("Dossier envoyé aux notaires ✓", "ok");
      recharger(visit);
    });
    action($el.querySelector("#acte"), async () => {
      const date = prompt("Date de signature de l'acte (AAAA-MM-JJ) :", new Date().toISOString().slice(0, 10));
      if (!date) return;
      await api("acte", { method: "POST", query: { id: visit.id }, body: { date } });
      toast("Félicitations 🎉 Facture et commission prêtes.", "ok");
      recharger(visit);
    });
    $el.querySelectorAll("[data-lcbft]").forEach((b) => (b.onclick = () => controleLcbft(visit, b.dataset.lcbft)));
  },
});

function controleLcbft(visit, partie) {
  const sheet = feuille(`<form id="fl"><h2>🛡 Contrôle ${partie === "vendeur" ? "du vendeur" : "de l'acquéreur"}</h2>
    <p class="small muted">L'IA lit la pièce, la vérifie sur le registre national des gels des avoirs et rédige la fiche de vigilance.</p>
    <label class="btn">📷 Photo de la pièce d'identité<input type="file" name="piece" accept="image/*,application/pdf" hidden id="fl-piece"></label>
    <p class="small" id="fl-nom"></p>
    <label class="check"><input type="checkbox" name="ppe" value="1"> Personne politiquement exposée (ou proche)</label>
    <label>Pays de résidence<input name="pays" value="France"></label>
    <label>Origine des fonds<select name="origine"><option value="epargne">Épargne personnelle</option><option value="pret" ${partie === "acquereur" ? "selected" : ""}>Prêt bancaire</option><option value="vente">Vente d'un bien</option><option value="donation">Donation / succession</option><option value="autre">Autre</option></select></label>
    <label>Précisions<input name="origine_detail"></label>
    <button class="btn primary big">Lancer le contrôle</button><button type="button" class="btn ghost" data-close>Annuler</button></form>`);
  sheet.querySelector("#fl-piece").onchange = (e) => (sheet.querySelector("#fl-nom").textContent = e.target.files[0] ? `✓ ${e.target.files[0].name}` : "");
  sheet.querySelector("#fl").onsubmit = async (e) => {
    e.preventDefault();
    const fd = new FormData(e.target);
    fd.append("partie", partie);
    if (!fd.has("ppe")) fd.append("ppe", "0");
    const btn = e.target.querySelector(".btn.primary");
    btn.disabled = true;
    btn.textContent = "Contrôle en cours…";
    try {
      const v = await api("lcbft", { method: "POST", query: { id: visit.id }, form: fd });
      sheet.fermer();
      const c = v.lcbft[partie];
      toast(c.niveau === "interdit" ? "⛔ Personne inscrite au registre des gels : suspendez l'opération." : `Vigilance ${c.niveau} ✓`, c.niveau === "interdit" ? "erreur" : "ok");
      recharger(visit);
    } catch (err) {
      toast(err.message, "erreur");
      btn.disabled = false;
      btn.textContent = "Lancer le contrôle";
    }
  };
}
