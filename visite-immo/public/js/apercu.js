// Aperçu de l'annonce telle qu'elle apparaîtrait sur Leboncoin (mise en page reproduite pour la simulation, nom en
// texte simple, aucun logo). Simulation interne : tout vient du dossier (fiche, réponses dictées, texte de l'IA, photos
// retouchées), y compris les mentions légales d'une annonce immobilière (arrêté du 10 janvier 2017 modifié, loi Climat
// et résilience : classe énergie et climat, dépenses d'énergie estimées, mention « logement à consommation énergétique
// excessive » pour F et G). La publication réelle passe par un compte pro Leboncoin ou un multidiffuseur.

const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);
const nb = (v) => Number(String(v).replace(/[^\d.,-]/g, "").replace(",", "."));
const euros = (v) => `${Math.round(v).toLocaleString("fr-FR")} €`;

const DPE = {
  A: "#009c6d", B: "#52b153", C: "#78bd76", D: "#f4e70f", E: "#f0b50f", F: "#eb8235", G: "#d7221f",
};
const GES = {
  A: "#f6edfd", B: "#e4c7fb", C: "#d5aaf6", D: "#cb95f3", E: "#ba72ec", F: "#a94deb", G: "#8a19df",
};

/** Prix affiché et mentions d'honoraires, comme l'exige la réglementation. */
function prixEtHonoraires(v) {
  const fai = nb(v("mandat_prix")) || 0;
  const net = nb(v("prix_souhaite")) || 0;
  const charge = v("mandat_honoraires_charge");
  const hono = v("mandat_honoraires");
  const prix = fai || net;
  let pct = null;
  if (/%/.test(hono)) pct = nb(hono);
  else if (nb(hono) && prix) pct = Math.round((nb(hono) / (charge === "Le vendeur" ? prix : prix - nb(hono))) * 1000) / 10;
  let mention = "";
  if (charge === "L'acquéreur") {
    const horsHono = net || (pct ? prix / (1 + pct / 100) : 0);
    mention = `Prix honoraires inclus : ${euros(prix)}${pct ? `, dont ${String(pct).replace(".", ",")} % TTC d'honoraires à la charge de l'acquéreur` : ", honoraires à la charge de l'acquéreur"}${horsHono ? ` (prix hors honoraires : ${euros(horsHono)})` : ""}.`;
  } else if (charge === "Le vendeur") {
    mention = `Prix : ${euros(prix)}. Honoraires à la charge du vendeur.`;
  }
  return { prix, mention, prixM2: nb(v("surface_habitable")) ? Math.round(prix / nb(v("surface_habitable"))) : 0 };
}

function echelle(lettre, couleurs, titre, sousTitre) {
  if (!lettre || !couleurs[lettre]) return "";
  return `<div class="lbc-echelle">
    <div class="lbc-echelle-titre">${titre}<span>${sousTitre}</span></div>
    <div class="lbc-barres">${Object.keys(couleurs)
      .map((l, i) => {
        const clair = couleurs === GES ? i < 4 : ["D", "E"].includes(l); // lettre foncée sur les couleurs claires
        return `<div class="lbc-barre ${l === lettre ? "on" : ""}" style="background:${couleurs[l]};width:${34 + i * 9}%;color:${clair ? "#2b1745" : "#fff"};text-shadow:${clair ? "none" : ""}">${l}</div>`;
      })
      .join("")}</div>
  </div>`;
}

function depensesEnergie(v) {
  const min = nb(v("depenses_energie_min")), max = nb(v("depenses_energie_max"));
  if (!min || !max) return "";
  return `Montant estimé des dépenses annuelles d'énergie pour un usage standard : entre ${euros(min)} et ${euros(max)} par an (prix moyens des énergies indexés sur l'année de référence du DPE).`;
}

/**
 * Contrôle avant diffusion : ce qu'un portail ou la réglementation exige, et ce qui fait une bonne annonce.
 * niveau : ok | attention (recommandé) | bloquant (à corriger avant de publier).
 */
export function controlesAnnonce(visit) {
  const champs = Array.isArray(visit.fiche?.champs) ? {} : visit.fiche?.champs || {};
  const v = (k) => (champs[k]?.valeur || "").trim();
  const titre = (visit.titre_annonce || "").trim();
  const desc = (visit.annonce || "").trim();
  const photos = (visit.photos || []).filter((p) => !p.floue);
  const floues = (visit.photos || []).filter((p) => p.floue).length;
  const { prix, mention } = prixEtHonoraires(v);
  const c = [];
  const ajout = (niveau, label, detail = "") => c.push({ niveau, label, detail });
  ajout(titre ? (titre.length <= 70 ? "ok" : "attention") : "bloquant", "Titre de l'annonce", titre ? `${titre.length} caractères${titre.length > 70 ? " : trop long, il sera coupé dans les listes de résultats (70 conseillés)" : ""}` : "Manquant : générez l'annonce");
  ajout(!desc ? "bloquant" : desc.length > 4000 ? "bloquant" : desc.length < 400 ? "attention" : "ok", "Description", desc ? `${desc.length} caractères${desc.length > 4000 ? " : au-delà de 4 000, le texte est refusé ou coupé" : desc.length < 400 ? " : un peu courte (400 conseillés)" : ""}` : "Manquante");
  ajout(photos.length >= 8 ? "ok" : photos.length >= 3 ? "attention" : "bloquant", "Photos", `${photos.length} photo(s) nette(s)${floues ? `, ${floues} floue(s) écartée(s)` : ""}${photos.length < 8 ? " : 8 ou plus conseillées, les annonces avec photos sont bien plus consultées" : ""}`);
  if (photos.some((p) => p.staging)) ajout("ok", "Home staging signalé", "Mention « Aménagement virtuel » sur les photos modifiées");
  ajout(prix ? "ok" : "bloquant", "Prix", prix ? euros(prix) : "Renseignez le prix de présentation");
  ajout(mention ? "ok" : "bloquant", "Honoraires (arrêté du 10 janvier 2017)", mention || "Indiquez qui paie les honoraires et leur montant");
  ajout(v("surface_habitable") ? "ok" : "bloquant", "Surface habitable", v("surface_habitable") ? `${v("surface_habitable")} m²` : "Manquante");
  ajout(v("ville") && /\d{5}/.test(v("ville")) ? "ok" : "bloquant", "Commune et code postal", v("ville") || "Manquants");
  ajout(v("dpe") ? "ok" : "bloquant", "Classe énergie (DPE)", v("dpe") ? `Classe ${v("dpe")}${v("ges") ? `, climat ${v("ges")}` : " · classe climat (GES) manquante"}` : "Obligatoire dans toute annonce");
  if (v("dpe") && !v("ges")) c.at(-1).niveau = "attention";
  ajout(depensesEnergie(v) ? "ok" : "attention", "Dépenses d'énergie estimées", depensesEnergie(v) ? "Fourchette indiquée" : "Obligatoire pour les DPE récents : reportez la fourchette « entre … et … € par an » du DPE");
  if (["F", "G"].includes(v("dpe"))) ajout("ok", "Mention « logement à consommation énergétique excessive »", "Ajoutée automatiquement (classe F ou G)");
  if (v("copropriete") === "oui") ajout(v("lots_copropriete") && v("charges_mensuelles") ? "ok" : "bloquant", "Copropriété", v("lots_copropriete") && v("charges_mensuelles") ? "Nombre de lots et charges annuelles indiqués" : "Nombre de lots et charges courantes obligatoires");
  const dpeTexte = (desc.match(/\bDPE\s*(?:[:=]|classe|class[ée])?\s*([A-G])\b/i) || [])[1];
  if (dpeTexte && v("dpe") && dpeTexte.toUpperCase() !== v("dpe")) ajout("bloquant", "Cohérence du DPE", `La description indique ${dpeTexte.toUpperCase()}, la fiche ${v("dpe")} : corrigez le texte`);
  ajout("ok", "Géorisques", "Mention ajoutée automatiquement");
  ajout(/(\b0[1-9](?:[ .]?\d{2}){4}\b)|@|https?:\/\//.test(desc) ? "attention" : "ok", "Coordonnées dans le texte", /(\b0[1-9](?:[ .]?\d{2}){4}\b)|@|https?:\/\//.test(desc) ? "Téléphone, e-mail ou lien dans la description : souvent refusés par les portails" : "Aucune");
  return c;
}

/** Texte prêt à coller dans le formulaire du portail (description + mentions obligatoires). */
export function texteADiffuser(visit) {
  const champs = Array.isArray(visit.fiche?.champs) ? {} : visit.fiche?.champs || {};
  const v = (k) => (champs[k]?.valeur || "").trim();
  const { mention } = prixEtHonoraires(v);
  return [
    (visit.annonce || "").trim(),
    "",
    mention,
    v("dpe") ? `Classe énergie ${v("dpe")}${v("ges") ? `, classe climat ${v("ges")}` : ""}.${["F", "G"].includes(v("dpe")) ? " Logement à consommation énergétique excessive." : ""}` : "",
    depensesEnergie(v),
    v("copropriete") === "oui" ? `Copropriété${v("lots_copropriete") ? ` de ${v("lots_copropriete")} lots` : ""}${v("charges_mensuelles") ? `, charges courantes annuelles : ${euros(nb(v("charges_mensuelles")) * 12)}` : ""}.` : "",
    "Les informations sur les risques auxquels ce bien est exposé sont disponibles sur le site Géorisques : www.georisques.gouv.fr",
  ]
    .filter((x, i) => x || i === 1)
    .join("\n");
}

export function rendreApercu(visit, agent, agence) {
  const champs = Array.isArray(visit.fiche?.champs) ? {} : visit.fiche?.champs || {};
  const v = (k) => (champs[k]?.valeur || "").trim();
  const { prix, mention, prixM2 } = prixEtHonoraires(v);

  const villeBrute = v("ville");
  const cp = (villeBrute.match(/\b\d{5}\b/) || [""])[0];
  const ville = villeBrute.replace(/\b\d{5}\b/, "").trim() || villeBrute;
  const lieu = [ville, cp].filter(Boolean).join(" ");
  const type = v("type_bien") || "Bien";
  const titre = visit.titre_annonce || `${type} ${v("nb_pieces") ? v("nb_pieces") + " pièces " : ""}${v("surface_habitable") ? v("surface_habitable") + " m²" : ""}`.trim();
  const date = new Date().toLocaleDateString("fr-FR", { day: "numeric", month: "long", year: "numeric" });

  const criteres = [
    ["🏠", "Type de bien", v("type_bien")],
    ["📐", "Surface habitable", v("surface_habitable") && `${v("surface_habitable")} m²`],
    ["🚪", "Nombre de pièces", v("nb_pieces")],
    ["🛏️", "Chambres", v("nb_chambres")],
    ["🛁", "Salles de bain", v("nb_salles_de_bain")],
    ["🌳", "Surface du terrain", v("surface_terrain") && `${Number(v("surface_terrain")).toLocaleString("fr-FR")} m²`],
    ["🏢", "Étage", v("etage")],
    ["🛗", "Ascenseur", v("ascenseur") && (v("ascenseur") === "oui" ? "Oui" : "Non")],
    ["🔥", "Chauffage", v("chauffage")],
    ["☀️", "Exposition", v("exposition")],
    ["🌿", "Extérieur", v("exterieur")],
    ["🚗", "Stationnement", v("stationnement")],
    ["📅", "Année de construction", v("annee_construction")],
    ["🔧", "État", v("etat_general")],
    ["🔑", "Disponibilité", v("disponibilite")],
  ].filter(([, , val]) => val);

  const copro = v("copropriete") === "oui";
  const legales = [
    mention,
    copro ? `Bien soumis au statut de la copropriété${v("lots_copropriete") ? ` (lots ${v("lots_copropriete")})` : ""}${v("charges_mensuelles") ? `, charges courantes annuelles moyennes : ${euros(nb(v("charges_mensuelles")) * 12)}` : ""}. Aucune procédure en cours connue.` : "",
    v("taxe_fonciere") ? `Taxe foncière : ${euros(nb(v("taxe_fonciere")))} par an.` : "",
    v("dpe") ? `Classe énergie ${v("dpe")}${v("ges") ? `, classe climat ${v("ges")}` : ""}.` : "",
    depensesEnergie(v),
    "Les informations sur les risques auxquels ce bien est exposé sont disponibles sur le site Géorisques : www.georisques.gouv.fr",
  ].filter(Boolean);

  const reelles = (visit.photos || []).filter((p) => !p.floue);
  const photos = reelles.length
    ? reelles.map((p) => ({ src: `api/?r=photo&id=${visit.id}&f=${encodeURIComponent(p.staging?.fichier || p.fichier)}`, nom: p.piece || "", virtuel: !!p.staging }))
    : [
        ["Façade", "linear-gradient(135deg,#c9d7c0,#8fb08a)", "🏡"],
        ["Séjour", "linear-gradient(135deg,#efe3cf,#d8c3a0)", "🛋️"],
        ["Cuisine", "linear-gradient(135deg,#e7ece9,#b9c9c2)", "🍳"],
        ["Jardin", "linear-gradient(135deg,#d9edc7,#9ccf7d)", "🌳"],
        ["Chambre", "linear-gradient(135deg,#ece6f3,#c9bcdc)", "🛏️"],
      ].map(([nom, fond, ic]) => ({ nom, fond, ic }));
  const vignette = (p, i) =>
    `<div class="lbc-photo ${i === 0 ? "lbc-photo-1" : ""}" ${p.fond ? `style="background:${p.fond}"` : ""}>${p.src ? `<img src="${p.src}" alt="${esc(p.nom)}" loading="${i < 3 ? "eager" : "lazy"}">` : `<span>${p.ic}</span><em>${esc(p.nom)}</em>`}${p.virtuel ? '<span class="lbc-virtuel">Aménagement virtuel</span>' : ""}${i === 0 ? `<b>1/${photos.length}</b>` : ""}</div>`;
  const reference = (visit.id || "").replace(/-/g, "").slice(-8).toUpperCase();
  const desc = esc(visit.annonce || "").replace(/\n/g, "<br>");

  return `
  <div class="lbc">
    <div class="lbc-simulation">Simulation · aperçu de diffusion généré automatiquement, non publié</div>
    <header class="lbc-header">
      <span class="lbc-marque">leboncoin</span>
      <span class="lbc-recherche">🔍 Rechercher sur le site</span>
      <span class="lbc-deposer">⊕ Déposer une annonce</span>
    </header>
    <nav class="lbc-fil">Accueil › Ventes immobilières › ${esc(cp ? cp.slice(0, 2) : "")} › ${esc(ville)}</nav>

    <div class="lbc-galerie">
      ${photos.slice(0, 5).map(vignette).join("")}
    </div>

    <div class="lbc-corps">
      <main class="lbc-principal">
        <h1 class="lbc-titre">${esc(titre)}</h1>
        <p class="lbc-resume">${[v("nb_pieces") && `${v("nb_pieces")} pièces`, v("surface_habitable") && `${v("surface_habitable")} m²`, v("surface_terrain") && `terrain ${Number(v("surface_terrain")).toLocaleString("fr-FR")} m²`].filter(Boolean).join(" · ")}</p>
        <p class="lbc-prix">${prix ? euros(prix) : "Prix à définir"}</p>
        ${prixM2 ? `<p class="lbc-m2">${prixM2.toLocaleString("fr-FR")} €/m² · Honoraires inclus</p>` : ""}
        <p class="lbc-lieu">📍 ${esc(lieu)}</p>
        <p class="lbc-date">${date}</p>

        <section class="lbc-bloc">
          <h2>Critères</h2>
          <div class="lbc-criteres">${criteres.map(([ic, l, val]) => `<div class="lbc-critere"><span class="lbc-ic">${ic}</span><span><small>${esc(l)}</small><strong>${esc(val)}</strong></span></div>`).join("")}</div>
        </section>

        <section class="lbc-bloc">
          <h2>Description</h2>
          <p class="lbc-desc">${desc}</p>
        </section>

        <section class="lbc-bloc">
          <h2>Diagnostics énergétiques</h2>
          <div class="lbc-diags">
            ${echelle(v("dpe"), DPE, "Classe énergie", "Consommation énergétique")}
            ${echelle(v("ges"), GES, "GES", "Émissions de gaz à effet de serre")}
            ${!v("dpe") && !v("ges") ? '<p class="lbc-gris">Diagnostics en cours de réalisation.</p>' : ""}
          </div>
          ${["F", "G"].includes(v("dpe")) ? '<p class="lbc-alerte-energie">Logement à consommation énergétique excessive</p>' : ""}
          ${depensesEnergie(v) ? `<p class="lbc-energie">${esc(depensesEnergie(v))}</p>` : ""}
        </section>

        <section class="lbc-bloc">
          <h2>Informations légales</h2>
          ${legales.map((l) => `<p class="lbc-legal">${esc(l)}</p>`).join("")}
        </section>

        <section class="lbc-bloc">
          <h2>Localisation</h2>
          <div class="lbc-carte"><span>📍</span><strong>${esc(lieu)}</strong><small>Localisation approximative</small></div>
        </section>
      </main>

      <aside class="lbc-vendeur">
        <div class="lbc-pro">
          <img src="img/synapse-icone.svg" alt="">
          <div><strong>${esc(agence || "Agence")}</strong><span>Professionnel · ${esc(agent.nom)}</span></div>
        </div>
        <button class="lbc-btn lbc-btn-plein">Envoyer un message</button>
        <button class="lbc-btn">📞 Voir le numéro</button>
        <p class="lbc-ref">Référence annonce : ${esc(reference)}</p>
        <p class="lbc-ref">Mandat ${esc((v("mandat_type") || "").toLowerCase()) || "—"}</p>
      </aside>
    </div>
    <div class="lbc-barre-bas"><button class="lbc-btn">📞 Appeler</button><button class="lbc-btn lbc-btn-plein">Message</button></div>
  </div>`;
}
