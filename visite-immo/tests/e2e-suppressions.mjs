// Suppressions : tâche, document du vendeur, secteur de prospection, contact reçu, visite d'acquéreur, bien entier.
import { navigateur, connexion, api, verifier, configurerEmail, BASE } from "./outils.mjs";

const { browser, page, erreurs, capture } = await navigateur();
try {
  await connexion(page);
  await configurerEmail(page);
  await api(page, "demo", { method: "POST" });
  const biens = await api(page, "visits");

  // Tâche, depuis l'écran Aujourd'hui
  await api(page, "tache", { method: "POST", body: { titre: "Tâche à supprimer" } });
  await page.goto(BASE + "#/");
  await page.reload();
  const ligne = page.locator(".ajd-item", { hasText: "Tâche à supprimer" });
  await ligne.locator("[data-suppr-tache]").click();
  await page.waitForFunction(() => ![...document.querySelectorAll(".ajd-item")].some((e) => e.textContent.includes("Tâche à supprimer")));
  verifier(true, "tâche supprimée depuis Aujourd'hui");

  // Document du vendeur
  const enVente = biens.find((b) => b.etape === "en_vente");
  const nomFichier = await page.evaluate(async (id) => {
    const c = Object.assign(document.createElement("canvas"), { width: 800, height: 1100 });
    const g = c.getContext("2d");
    g.fillStyle = "#fff"; g.fillRect(0, 0, 800, 1100); g.fillStyle = "#000"; g.font = "40px sans-serif"; g.fillText("AVIS DE TAXE FONCIÈRE 2025", 40, 100);
    const f = new FormData();
    f.append("fichier", await new Promise((ok) => c.toBlob(ok, "image/jpeg", 0.9)), "taxe.jpg");
    f.append("cle", "taxe_fonciere");
    const r = await (await fetch(`api/?r=pieces&id=${id}`, { method: "POST", headers: { "X-Requested-With": "visite-immo" }, body: f })).json();
    if (!r.pieces) throw new Error(JSON.stringify(r));
    return r.pieces.find((p) => p.cle === "taxe_fonciere").fichiers.at(-1).nom;
  }, enVente.id);
  await page.goto(`${BASE}#/visite/${enVente.id}/pieces`);
  await page.waitForSelector(`[data-suppr-piece="${nomFichier}"]`);
  await capture("suppr-01-pieces");
  await page.click(`[data-suppr-piece="${nomFichier}"]`);
  await page.waitForFunction((n) => !document.querySelector(`[data-suppr-piece="${n}"]`), nomFichier);
  const pieces = await api(page, "pieces", { query: { id: enVente.id } });
  const taxe = pieces.pieces.find((p) => p.cle === "taxe_fonciere");
  verifier(!taxe.fichiers?.some((f) => f.nom === nomFichier) && taxe.statut !== "recue", "document du vendeur supprimé, la pièce redevient à recevoir");
  verifier((await page.evaluate((u) => fetch(u).then((r) => r.status), `api/?r=piece&id=${enVente.id}&f=${nomFichier}`)) === 404, "le fichier est effacé du serveur");

  // Contact reçu et visite d'acquéreur
  let d = await api(page, "dossier", { query: { id: enVente.id } });
  const nbContacts = d.contacts.length;
  if (nbContacts) {
    d = await api(page, "contact_supprimer", { method: "POST", query: { id: enVente.id }, body: { date: d.contacts[0].date } });
    verifier(d.contacts.length === nbContacts - 1, "contact reçu supprimé");
  }
  const avecVisite = (await Promise.all(biens.map((b) => api(page, "dossier", { query: { id: b.id } })))).find((x) => x.visites_acq?.length);
  const va = avecVisite.visites_acq[0];
  await page.goto(`${BASE}#/visite/${avecVisite.id}/vente`);
  await page.waitForSelector(`[data-suppr-visite="${va.id}"]`);
  await page.click(`[data-suppr-visite="${va.id}"]`);
  await page.waitForFunction((i) => !document.querySelector(`[data-suppr-visite="${i}"]`), va.id);
  d = await api(page, "dossier", { query: { id: avecVisite.id } });
  verifier(!d.visites_acq.some((x) => x.id === va.id), `visite de ${va.nom} supprimée`);
  if (va.rdv) verifier(!(await api(page, "agenda")).rdv?.some?.((r) => r.id === va.rdv), "son rendez-vous est retiré de l'agenda");

  // Secteur de prospection
  await page.goto(BASE + "#/prospection");
  await page.waitForSelector("[data-suppr-secteur]");
  const avant = await page.$$eval("[data-suppr-secteur]", (l) => l.length);
  await page.click("[data-suppr-secteur]");
  await page.waitForFunction((n) => document.querySelectorAll("[data-suppr-secteur]").length === n - 1, avant);
  verifier(true, "secteur de prospection retiré");

  // Bien entier, depuis le résumé
  const victime = biens.find((b) => b.etape === "vendu") || biens.at(-1);
  await page.goto(`${BASE}#/visite/${victime.id}/resume`);
  await page.waitForSelector("#suppr-bien");
  await capture("suppr-02-resume");
  await page.click("#suppr-bien");
  await page.waitForFunction(() => location.hash.startsWith("#/biens"));
  verifier(!(await api(page, "visits")).some((b) => b.id === victime.id), `bien « ${victime.titre} » supprimé depuis son résumé`);
  verifier(erreurs.length === 0, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
  console.log("\nSuppressions : OK");
} catch (e) {
  console.error(e.message);
  await capture("suppr-erreur").catch(() => {});
  process.exitCode = 1;
} finally {
  await browser.close();
}
