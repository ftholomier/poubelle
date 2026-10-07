// Journal des accès (RGPD) : consultations d'un dossier par l'agent (regroupées), documents sensibles, espace du
// vendeur ouvert par son lien, écran administrateur, export CSV, inclusion dans l'export des données d'une personne.
import { navigateur, connexion, api, verifier, configurerEmail, dossierAvecMandat, mails, BASE } from "./outils.mjs";

const { browser, ctx, page, erreurs, capture } = await navigateur();
try {
  await connexion(page);
  await configurerEmail(page);
  const id = await dossierAvecMandat(page);

  // L'agent ouvre le dossier plusieurs fois et télécharge le mandat (document sensible)
  for (let i = 0; i < 3; i++) await api(page, "dossier", { query: { id } });
  await page.evaluate((u) => fetch(u).then((r) => r.arrayBuffer()), `api/?r=pdf&id=${id}&doc=mandat`);
  await page.evaluate((u) => fetch(u).then((r) => r.arrayBuffer()), `api/?r=pdf&id=${id}&doc=avis`);

  // Le vendeur reçoit son lien et ouvre son espace
  const brouillon = await api(page, "envoi_vendeur", { query: { id } });
  await api(page, "envoi_vendeur", { method: "POST", query: { id }, body: brouillon });
  const mail = (await mails()).find((m) => m.a === "claire.martin@exemple.fr");
  const lien = mail.texte.match(/https?:\/\/\S+espace\/\?t=[\w-]+/)?.[0];
  const vendeur = await ctx.newPage();
  await vendeur.goto(lien);
  await vendeur.waitForSelector(".es-card");
  await vendeur.close();

  let l = await api(page, "acces");
  const consult = l.filter((a) => a.action === "Consultation du dossier" && a.dossier === id);
  verifier(consult.length === 1, "3 consultations rapprochées du dossier = 1 ligne au journal");
  verifier(consult[0].qui === "Frédéric Dupont" && consult[0].bien.includes("Chênois") && consult[0].ip, "qui, quel bien, quand, depuis quelle adresse IP");
  verifier(l.some((a) => a.action === "Document PDF" && a.objet === "Mandat de vente" && a.sensible), "mandat téléchargé : noté comme sensible");
  verifier(l.some((a) => a.action === "Document PDF" && a.objet === "Avis de valeur" && !a.sensible), "avis de valeur téléchargé : noté");
  const esp = l.find((a) => a.action === "Ouverture de son espace");
  verifier(esp && esp.qui === "Claire Martin" && esp.role.startsWith("vendeur"), "le vendeur qui ouvre son espace par son lien est noté");

  l = await api(page, "acces", { query: { sensible: "1" } });
  verifier(l.length && l.every((a) => a.sensible), "filtre « données sensibles seulement »");

  // Écran administrateur
  await page.goto(`${BASE}#/reglages`);
  await page.waitForSelector("#acces-btn");
  await page.fill("#acces-q", "Chênois");
  await page.click("#acces-btn");
  await page.waitForSelector(".acces-ligne");
  await page.locator("#acces-liste").scrollIntoViewIfNeeded();
  await capture("acces-01-parametres");
  verifier((await page.$$(".acces-ligne")).length >= 3, "Paramètres : journal affiché et filtrable");
  const csv = await page.evaluate(() => fetch("api/?r=acces_csv").then((r) => r.text()));
  verifier(csv.includes("Date;Qui;Rôle;Action") && csv.includes("Ouverture de son espace"), "export CSV du journal");

  // Droit d'accès : l'export des données de la personne dit qui a consulté ses dossiers
  const exp = await page.evaluate(() => fetch("api/?r=rgpd_export&q=Claire%20Martin").then((r) => r.json()));
  verifier(exp.acces?.some((a) => a.action === "Ouverture de son espace") && exp.acces.every((a) => !("qui_id" in a)), "export RGPD de la personne : accès à ses dossiers inclus");
  l = await api(page, "acces");
  verifier(l.some((a) => a.action === "Export des données d'une personne" && a.objet === "Claire Martin"), "l'export lui-même est tracé");
  verifier(erreurs.length === 0, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
  console.log("\nJournal des accès : OK");
} catch (e) {
  console.error(e.message);
  await capture("acces-erreur").catch(() => {});
  process.exitCode = 1;
} finally {
  await browser.close();
}
