// Prospection : ajout d'une commune, logements F/G ciblés, rues actives, courriers PDF, suivi.
import { navigateur, connexion, verifier, api } from "./outils.mjs";
const { browser, page, erreurs, capture } = await navigateur();
try {
  await connexion(page);
  await api(page, "profile", { method: "POST", body: { nom: "Frédéric Dupont", email: "fred@agence.fr", telephone: "06 11 22 33 44" } });
  await page.goto("http://127.0.0.1:8099/#/prospection");
  await page.waitForSelector("#commune");
  await page.fill("#commune", "Lougres");
  await page.click("#f-commune button");
  await page.waitForSelector(".choix-commune");
  await page.click(".choix-commune");
  await page.waitForSelector(".cible", { timeout: 30000 });
  await capture("70-prospection");
  const p = await api(page, "prospection");
  verifier(p.cibles.length === 8 && p.secteurs[0].marche.ventes > 0, `${p.cibles.length} logements F/G ciblés, ${p.secteurs[0].marche.ventes} ventes analysées`);
  verifier(p.secteurs[0].marche.rues.length > 0, `${p.secteurs[0].marche.rues.length} rue(s) active(s) pour le boîtage`);
  const ids = p.cibles.slice(0, 3).map((c) => c.id).join(",");
  const r = await page.evaluate(async (u) => { const x = await fetch(u); return [x.headers.get("content-type"), (await x.arrayBuffer()).byteLength]; }, `api/?r=courriers&ids=${ids}`);
  verifier(r[0] === "application/pdf" && r[1] > 20000, `3 courriers personnalisés (${Math.round(r[1] / 1024)} Ko)`);
  const p2 = await api(page, "prospection");
  verifier(p2.stats.courrier === 3, "statut « courrier envoyé » mis à jour");
  const rue = p.secteurs[0].marche.rues[0].rue;
  const b = await page.evaluate(async (u) => (await fetch(u)).headers.get("content-type"), `api/?r=boitage&commune=25349&rue=${encodeURIComponent(rue)}`);
  verifier(b === "application/pdf", "flyer de boîtage pour la rue « " + rue + " »");
  await api(page, "cible", { method: "POST", query: { id: p.cibles[0].id }, body: { statut: "rdv" } });
  await page.reload();
  await page.waitForSelector(".entonnoir");
  await capture("71-prospection-suivi");
  verifier(!erreurs.length, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
} finally {
  await browser.close();
}
