// Estimation en temps réel : outil « Estimer un bien » (adresse, caractéristiques → prix qui bouge à chaque
// changement) et estimation en direct dans la fiche d'un dossier.
import { navigateur, connexion, api, verifier, configurerEmail, dossierAvecMandat, BASE } from "./outils.mjs";

const { browser, page, erreurs, capture } = await navigateur();
const prix = async () => Number((await page.textContent("[data-prix]")).replace(/\D/g, ""));
async function attendreChangement(avant) {
  await page.waitForFunction((p) => {
    const el = document.querySelector("[data-prix]");
    return el && Number(el.textContent.replace(/\D/g, "")) !== p && !document.querySelector(".estim-resultat.calcul");
  }, avant, { timeout: 8000 });
  return prix();
}

try {
  await connexion(page);
  await configurerEmail(page);

  // ---------- Outil « Estimer un bien » ----------
  await page.goto(`${BASE}#/estimation`);
  await page.fill("#adr", "11 rue du Chênois Lougres");
  await page.waitForSelector("#sugg button");
  await page.click("#sugg button");
  await page.waitForSelector("[data-prix]");
  const p1 = await prix();
  verifier(p1 > 100000, `estimation affichée dès le choix de l'adresse : ${p1.toLocaleString("fr-FR")} €`);
  verifier((await page.$$(".comparable")).length >= 3, `${(await page.$$(".comparable")).length} ventes similaires listées avec leur ressemblance`);
  verifier(await page.isVisible(".tendance-barres"), "tendance du marché par année affichée");
  verifier(await page.isVisible(".confiance"), "indice de confiance affiché");
  await capture("estim-01-outil");

  await page.$eval("#surf", (el) => { el.value = 140; el.dispatchEvent(new Event("input", { bubbles: true })); });
  const p2 = await attendreChangement(p1);
  verifier(p2 > p1, `surface 100 → 140 m² : ${p2.toLocaleString("fr-FR")} € (mise à jour immédiate)`);
  await page.click('#dpe button[data-v="G"]');
  const p3 = await attendreChangement(p2);
  verifier(p3 < p2, `DPE G : ${p3.toLocaleString("fr-FR")} € (décote appliquée)`);
  await page.click('#etat button[data-v="Refait à neuf"]');
  const p4 = await attendreChangement(p3);
  verifier(p4 > p3, `refait à neuf : ${p4.toLocaleString("fr-FR")} €`);
  await page.fill("#prix-v", "600000");
  await page.waitForSelector("text=Prix souhaité par le vendeur");
  verifier(true, "écart avec le prix souhaité par le vendeur affiché");
  await capture("estim-02-modifie");

  // Réponse rapide grâce au cache des ventes de la commune
  const t0 = Date.now();
  const r = await api(page, "estimation", { query: { lat: 47.0942, lon: 6.3561, citycode: "25349", type_bien: "Appartement", surface_habitable: 70, nb_pieces: 3 } });
  const duree = Date.now() - t0;
  verifier(r.estimation && r.estimation.comparables.every((c) => c.type === "Appartement"), "appartement : uniquement des ventes d'appartements");
  verifier(duree < 400, `réponse en ${duree} ms (ventes en cache)`);

  // Petite commune : trop peu de ventes, on ajoute les communes voisines
  const village = await api(page, "estimation", { query: { lat: 47.0442, lon: 6.3561, citycode: "25350", adresse: "Bretigney", type_bien: "Maison", surface_habitable: 115, nb_pieces: 5 } });
  verifier(village.nb_ventes_commune < 12, `village : seulement ${village.nb_ventes_commune} ventes en 5 ans`);
  verifier(village.communes_voisines.length >= 1 && village.estimation, `élargi aux communes voisines (${village.communes_voisines.join(", ")}) : ${village.estimation?.prix.toLocaleString("fr-FR")} €, confiance ${village.estimation?.confiance.niveau}`);
  verifier(village.estimation.comparables.some((c) => c.commune) && village.estimation.communes_voisines.length, "les ventes des communes voisines sont repérées dans la liste");
  const lougres = await api(page, "estimation", { query: { lat: 47.0942, lon: 6.3561, citycode: "25349", type_bien: "Maison", surface_habitable: 115, nb_pieces: 5 } });
  verifier(lougres.communes_voisines.length === 0, "commune bien fournie : pas d'élargissement");

  await page.click("#visite");
  await page.waitForSelector("#titre");
  verifier((await page.inputValue("#titre")).includes("Chênois"), "« Démarrer la visite ici » préremplit l'adresse");

  // ---------- Estimation en direct dans le dossier ----------
  const id = await dossierAvecMandat(page);
  let v = await api(page, "dossier", { query: { id } });
  const avant = v.avis_valeur.retenu;
  verifier(avant > 100000 && v.avis_valeur.confiance && v.avis_valeur.comparables[0].similarite > 0, `avis de valeur du dossier : ${avant.toLocaleString("fr-FR")} €, confiance ${v.avis_valeur.confiance.niveau}`);
  await page.goto(`${BASE}#/visite/${id}/fiche`);
  await page.waitForSelector("#estim-direct:not([hidden])");
  const affiche = await page.textContent("#estim-direct strong");
  await page.fill("#c-surface_habitable", "160");
  await page.waitForFunction((a) => document.querySelector("#estim-direct strong")?.textContent !== a, affiche, { timeout: 8000 });
  verifier(true, `fiche : surface 125 → 160 m², estimation en direct ${affiche} → ${await page.textContent("#estim-direct strong")}`);
  await capture("estim-03-fiche");
  v = await api(page, "dossier", { query: { id } });
  verifier(v.avis_valeur.retenu > avant && v.avis_valeur.direct, "avis de valeur enregistré mis à jour sans relancer l'IA");
  verifier(v.avis_valeur.argumentaire_perime === true, "argumentaire signalé « à réécrire »");
  await page.click("#estim-direct");
  await page.waitForSelector(".comparable");
  await capture("estim-04-avis");
  verifier(await page.isVisible("text=Les chiffres ont changé"), "écran avis : invitation à réécrire l'argumentaire");
  verifier(erreurs.length === 0, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
  console.log("\nEstimation en temps réel : OK");
} catch (e) {
  console.error(e.message);
  await capture("estim-erreur").catch(() => {});
  process.exitCode = 1;
} finally {
  await browser.close();
}
