// Jeu de démonstration, réseau (coaching, formation, tête de réseau), paramètres, RGPD, contact transmis.
import { navigateur, connexion, verifier, api, configurerEmail } from "./outils.mjs";
const { browser, page, erreurs, capture } = await navigateur();
try {
  await connexion(page);
  await configurerEmail(page);
  const t0 = Date.now();
  const r = await api(page, "demo", { method: "POST" });
  verifier(r.biens === 6 && r.acquereurs === 4, `jeu de démonstration chargé en ${Math.round((Date.now() - t0) / 1000)} s : ${r.biens} biens, ${r.acquereurs} acquéreurs`);
  const biens = await api(page, "visits");
  const etapes = biens.map((b) => b.etape).sort().join(", ");
  verifier(["compromis", "en_vente", "offre", "preparation", "vendu", "visite"].every((e) => etapes.includes(e)), "toutes les étapes représentées : " + etapes);
  for (const [nom, url] of [["80-aujourdhui-demo", "#/"], ["81-biens-demo", "#/biens"], ["82-agenda-demo", "#/agenda"], ["83-acquereurs-demo", "#/acquereurs"], ["84-tableau-demo", "#/tableau"], ["85-reseau", "#/reseau"], ["86-reglages", "#/reglages"]]) {
    await page.goto("http://127.0.0.1:8099/" + url);
    await page.reload();
    await page.waitForSelector("main .card, main .vide, .home-hero", { timeout: 20000 });
    await page.waitForTimeout(700);
    await capture(nom);
  }
  const enVente = biens.find((b) => b.etape === "en_vente");
  for (const onglet of ["resume", "vente", "photos", "documents"]) {
    await page.goto(`http://127.0.0.1:8099/#/visite/${enVente.id}/${onglet}`);
    await page.reload();
    await page.waitForSelector("#content > *", { timeout: 20000 });
    await page.waitForTimeout(800);
    await capture(`87-bien-${onglet}`);
  }
  const res = await api(page, "reseau");
  verifier(res.coaching.filter((e) => e.fait_le).length >= 5 && res.agents.length === 1, `coaching : ${res.coaching.filter((e) => e.fait_le).length}/8 étapes faites ; vue tête de réseau`);
  const f = await api(page, "formation", { method: "POST", body: { intitule: "Loi ALUR : actualités", heures: 7, date: new Date().toISOString().slice(0, 10) } });
  verifier(f.annee >= 7, `formation ALUR : ${f.annee} h cette année`);
  await api(page, "settings", { method: "POST", body: { juriste_email: "juriste@synapse-demo.fr" } });
  const q = await api(page, "question_juriste", { method: "POST", body: { sujet: "Indivision", question: "Un des indivisaires refuse de signer le mandat, que faire ?", dossier: enVente.id } });
  verifier(q.ok, "question envoyée au juriste avec le dossier en PDF");
  await api(page, "users", { method: "POST", body: { nom: "Léa Collègue", login: "lea", password: "motdepasse", email: "lea@agence.fr" } });
  const acq = await api(page, "acquereurs");
  const lea = (await api(page, "reseau")).collegues[0];
  const t = await api(page, "partager_acquereur", { method: "POST", body: { acquereur: acq[0].id, agent: lea.id, message: "Il cherche sur ton secteur" } });
  verifier(t.ok, "contact transmis à une collègue");
  const rg = await api(page, "rgpd_recherche", { query: { q: "Moreau" } });
  verifier(rg.acquereurs >= 1, `RGPD : données de « Moreau » trouvées (${rg.acquereurs} fiche(s), ${rg.dossiers} dossier(s), ${rg.contacts} contact(s))`);
  verifier(!erreurs.length, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
} finally {
  await browser.close();
}
