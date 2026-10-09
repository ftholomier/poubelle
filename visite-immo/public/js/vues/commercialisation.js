// Commercialisation : publication (page du bien, flux portails), statistiques, contacts reçus,
// visuels et vidéo pour les réseaux sociaux.

import { api } from "../api.js";
import { blocsVente } from "./vente.js";
import { fabriquerVideo } from "../video.js";
import { ecransDossier, actionsDossier, esc, toast, go, action, copier, fmtDate, fmtPrix, outils, theme, state } from "../ui.js";

actionsDossier.publier = async (visit) => {
  if (!confirm("Publier le bien ? La page du bien est mise en ligne, le flux des portails est mis à jour et les acquéreurs compatibles sont prévenus.")) return;
  try {
    await api("publier", { method: "POST", query: { id: visit.id }, body: { publier: true } });
    toast("Bien publié ✓", "ok");
    go(`/visite/${visit.id}/vente`);
  } catch (e) {
    toast(e.message, "erreur");
  }
};

async function partager(url, titre) {
  if (navigator.share) {
    try {
      await navigator.share({ url, title: titre });
      return;
    } catch {
      /* partage annulé */
    }
  }
  copier(url);
}

blocsVente.push({
  ordre: 10,
  async rendre($el, visit) {
    const d = await api("diffusion", { query: { id: visit.id } });
    if (!d.mandat_signe) {
      $el.innerHTML = `<section class="card"><h2>Diffusion</h2><p class="muted">La diffusion s'ouvre dès que le mandat est signé (obligation légale). Page du bien, annonce, visuels et vidéo sont déjà prêts.</p>
        <a class="btn" href="#/visite/${visit.id}/signature/mandat">✍️ Signature du mandat</a></section>`;
      return;
    }
    if (!d.publiee && visit.etape !== "en_vente") return; // offre acceptée ou vendu : plus de mise en vente
    if (!d.publiee) {
      $el.innerHTML = `<section class="card prochaine"><span class="tag">Prêt à diffuser</span><h2 class="prochaine-titre">Mettre le bien en vente</h2>
        <p class="muted">Page du bien avec assistant 24 h/24, flux pour les portails, alertes aux acquéreurs compatibles.</p>
        <button class="btn magic big" id="publier">🚀 Publier</button></section>`;
      $el.querySelector("#publier").onclick = () => actionsDossier.publier(visit);
      return;
    }
    $el.innerHTML = `<section class="card">
      <div class="dossier-top"><h2>En ligne</h2><span class="badge vert">publié</span></div>
      <div class="chiffres"><div class="chiffre noir"><small>Consultations</small><strong>${d.vues_total}</strong><span class="small">${d.vues_7j} sur 7 jours</span></div>
        <div class="chiffre"><small>Contacts</small><strong>${d.contacts.length}</strong></div></div>
      <div class="btn-row"><a class="btn primary" href="${esc(d.url)}" target="_blank">👁 Page du bien</a><button class="btn" id="partager">🔗 Partager</button></div>
      <p class="small muted" style="margin-top:12px">${esc(d.mention_prix)}</p>
      <details class="aide"><summary>Portails et flux</summary>
        <p class="small">Le flux ci-dessous liste vos biens publiés, au format d'échange décrit dans la documentation. Un multidiffuseur ou les portails (contrat pro) le lisent pour publier automatiquement.</p>
        <div class="test-row"><input readonly value="${esc(d.flux)}"><button class="btn" id="copier-flux">📋</button></div>
        <a class="btn ghost" href="#/visite/${visit.id}/apercu">👁 Rendu Leboncoin (simulation)</a>
      </details>
      <button class="btn ghost" id="suspendre">Suspendre la diffusion</button>
    </section>
    ${d.contacts.length ? `<section class="card"><h2>Contacts reçus</h2>${d.contacts.map((c) => `<a class="ligne" href="#/acquereur/${esc(c.acquereur)}"><div><strong>${esc(c.nom)}</strong><span class="muted small">${fmtDate(c.date)} · ${esc(c.canal)}${c.rdv ? " · visite réservée" : ""}${c.message ? ` · « ${esc(c.message.slice(0, 80))} »` : ""}</span></div><button type="button" class="icon-btn petit" data-suppr-contact="${esc(c.date)}" aria-label="Supprimer ce contact">🗑</button></a>`).join("")}</section>` : ""}`;
    $el.querySelectorAll("[data-suppr-contact]").forEach((b) => (b.onclick = async (e) => {
      e.preventDefault();
      e.stopPropagation();
      if (!confirm("Supprimer ce contact de la liste ? La fiche acquéreur est conservée.")) return;
      await api("contact_supprimer", { method: "POST", query: { id: visit.id }, body: { date: b.dataset.supprContact } });
      toast("Contact supprimé");
      outils.route();
    }));
    $el.querySelector("#partager").onclick = () => partager(d.url, visit.titre_annonce || visit.titre);
    $el.querySelector("#copier-flux").onclick = () => copier(d.flux);
    action($el.querySelector("#suspendre"), async () => {
      if (!confirm("Retirer la page du bien et le bien du flux des portails ?")) return;
      await api("publier", { method: "POST", query: { id: visit.id }, body: { publier: false } });
      outils.route();
    });
  },
});

// ---------- Visuels et vidéo ----------

ecransDossier.visuels = ($el, visit) => {
  if (!$el) return;
  const src = (f, dl = false) => `api/?r=visuel&id=${visit.id}&format=${f}&t=${Date.now()}${dl ? "&dl=1" : ""}`;
  $el.innerHTML = `<section class="card"><h2>Visuels prêts à publier</h2>
      <div class="visuels"><img src="${src("carre")}" alt="Visuel carré"><img src="${src("story")}" alt="Story"></div>
      <div class="btn-row"><a class="btn" href="${src("carre", true)}">⬇️ Carré</a><a class="btn" href="${src("story", true)}">⬇️ Story</a></div>
      ${(visit.photos || []).some((p) => p.staging) ? '<p class="small muted">Les visuels avec home staging portent la mention « aménagement virtuel ».</p>' : ""}
    </section>
    <section class="card"><h2>Vidéo courte (Reels, TikTok)</h2>
      <p class="small muted">${(visit.photos || []).length ? `Fabriquée sur votre téléphone à partir de ${Math.min(6, visit.photos.length)} photo(s), environ 20 secondes.` : "Ajoutez des photos (onglet Photos) pour une vidéo plus belle."}</p>
      <button class="btn magic" id="video">🎬 Fabriquer la vidéo</button><p class="small" id="video-etat"></p><div id="video-zone"></div></section>`;
  $el.querySelector("#video").onclick = async (e) => {
    const b = e.currentTarget;
    b.disabled = true;
    const etat = $el.querySelector("#video-etat");
    const v = (k) => visit.fiche?.champs?.[k]?.valeur || "";
    try {
      const blob = await fabriquerVideo({
        photos: (visit.photos || []).filter((p) => !p.floue).map((p) => `api/?r=photo&id=${visit.id}&f=${encodeURIComponent(p.staging?.fichier || p.fichier)}`),
        titre: visit.titre_annonce || visit.titre || "Nouveau bien",
        prix: fmtPrix(v("mandat_prix") || v("prix_souhaite")) || "",
        atouts: visit.points_forts || [],
        infos: [v("surface_habitable") && `${v("surface_habitable")} m²`, v("nb_chambres") && `${v("nb_chambres")} chambres`, v("dpe") && `DPE ${v("dpe")}`].filter(Boolean).join(" · "),
        agence: state.agence || theme.agence || "Synapse",
        logo: theme.logo ? `api/?r=logo` : "img/synapse-logo.svg",
      }, (p) => (etat.textContent = `Fabrication… ${Math.round(p * 100)} %`));
      const url = URL.createObjectURL(blob);
      const ext = blob.type.includes("mp4") ? "mp4" : "webm";
      $el.querySelector("#video-zone").innerHTML = `<video src="${url}" controls playsinline class="video-apercu"></video><a class="btn primary" download="video-bien.${ext}" href="${url}">⬇️ Télécharger la vidéo</a>`;
      etat.textContent = "";
    } catch (err) {
      etat.textContent = `Vidéo impossible sur ce navigateur : ${err.message}`;
    } finally {
      b.disabled = false;
    }
  };
};
