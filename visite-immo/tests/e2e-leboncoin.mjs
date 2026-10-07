// Rendu de l'annonce sur Leboncoin (simulation) : vraies photos du dossier, mentions légales, vue téléphone et
// ordinateur, contrôle de conformité avant diffusion.
import { execSync } from "node:child_process";
import { navigateur, connexion, api, verifier, configurerEmail, dossierEnVente, BASE } from "./outils.mjs";

for (const [n, c] of [["facade", "120,150,110"], ["sejour", "210,190,160"], ["cuisine", "180,195,190"]])
  execSync(`php -r '$i=imagecreatetruecolor(1600,1200);imagefill($i,0,0,imagecolorallocate($i,${c}));for($k=0;$k<300;$k++){imagefilledrectangle($i,rand(0,1600),rand(0,1200),rand(0,1600),rand(0,1200),imagecolorallocate($i,rand(60,230),rand(60,230),rand(60,230)));} imagejpeg($i,"/tmp/lbc-${n}.jpg",90);'`);

const { browser, page, erreurs, capture } = await navigateur();
const cadre = () => page.frameLocator("#apercu");
try {
  await connexion(page);
  await configurerEmail(page);
  const id = await dossierEnVente(page);
  await page.goto(`${BASE}#/visite/${id}/photos`);
  await page.waitForSelector("#ajout", { state: "attached" });
  await page.setInputFiles("#ajout", ["/tmp/lbc-facade.jpg", "/tmp/lbc-sejour.jpg", "/tmp/lbc-cuisine.jpg"]);
  await page.waitForSelector(".photo-carte:nth-child(3)", { timeout: 40000 });

  // Avant : DPE F sans dépenses d'énergie → contrôles en alerte
  await api(page, "visit", { method: "POST", query: { id }, body: { champs: { dpe: "F", ges: "D" }, source: "agent" } });
  await page.goto(`${BASE}#/visite/${id}/apercu`);
  await page.waitForSelector(".controle");
  await cadre().locator(".lbc-titre").waitFor();
  const imgs = await cadre().locator(".lbc-galerie img").evaluateAll((l) => l.map((i) => i.complete && i.naturalWidth));
  verifier(imgs.length === 3, `les 3 vraies photos du dossier dans la galerie (${imgs.length})`);
  await page.waitForFunction(() => [...document.getElementById("apercu").contentDocument.querySelectorAll(".lbc-galerie img")].every((i) => i.complete && i.naturalWidth > 0));
  verifier(true, "photos chargées dans la page simulée");
  verifier(await cadre().locator(".lbc-alerte-energie").isVisible(), "mention « logement à consommation énergétique excessive » (classe F)");
  verifier(await cadre().locator("text=Simulation").first().isVisible(), "bandeau « simulation, non publié »");
  verifier(await page.locator(".controle.attention", { hasText: "Dépenses d'énergie" }).count() === 1, "contrôle : dépenses d'énergie à compléter");
  verifier(await page.locator(".controle", { hasText: "Photos" }).locator("small").textContent().then((t) => t.includes("3 photo")), "contrôle : nombre de photos");
  await capture("lbc-01-telephone");
  await page.locator("#cadre").screenshot({ path: new URL("./captures/lbc-cadre-telephone.png", import.meta.url).pathname });

  // Après : dépenses d'énergie renseignées → mention légale ajoutée
  await api(page, "visit", { method: "POST", query: { id }, body: { champs: { depenses_energie_min: "1890", depenses_energie_max: "2600" }, source: "agent" } });
  await page.reload();
  await cadre().locator(".lbc-energie").waitFor();
  verifier((await cadre().locator(".lbc-energie").textContent()).replace(/[\s\u202f]/g, " ").includes("entre 1 890 € et 2 600 €"), "mention des dépenses annuelles d'énergie");
  verifier(await page.locator(".controle.ok", { hasText: "Dépenses d'énergie" }).count() === 1, "contrôle des dépenses d'énergie au vert");
  verifier(await page.locator(".controle.bloquant", { hasText: "Cohérence du DPE" }).count() === 1, "incohérence repérée : la description parle d'un autre DPE que la fiche");
  await api(page, "visit", { method: "POST", query: { id }, body: { champs: { dpe: "C", ges: "A" }, source: "agent" } });
  await page.reload();
  await page.waitForSelector(".controle");
  const bloquants = await page.locator(".controle.bloquant").count();
  verifier(bloquants === 0, `DPE corrigé : aucun point bloquant (${await page.locator(".controle").count()} contrôles)`);

  // Vue ordinateur : la page simulée se met en page sur 1280 px, colonne vendeur à droite
  await page.click('#mode button[data-v="ordinateur"]');
  await page.waitForFunction(() => document.getElementById("apercu").style.width === "1280px");
  await page.waitForTimeout(300);
  const hCadre = await page.evaluate(() => document.getElementById("cadre").getBoundingClientRect().height);
  const hPage = await page.evaluate(() => document.getElementById("apercu").contentDocument.querySelector(".lbc").offsetHeight * 0.28);
  verifier(Math.abs(hCadre - hPage) < hPage * 0.2, "le cadre épouse la hauteur de la page à la largeur ordinateur");
  const [main, aside] = await Promise.all([cadre().locator(".lbc-principal").boundingBox(), cadre().locator(".lbc-vendeur").boundingBox()]);
  verifier(aside.x > main.x + main.width - 5, "vue ordinateur : encart vendeur à droite de l'annonce");
  await page.locator("#cadre").screenshot({ path: new URL("./captures/lbc-cadre-ordinateur.png", import.meta.url).pathname });
  await capture("lbc-02-ordinateur");

  const texte = await page.evaluate(async () => (await import("./js/apercu.js")).texteADiffuser(await (await fetch(`api/?r=visit&id=${location.hash.split("/")[2]}`, { headers: { "X-Requested-With": "visite-immo" } })).json()));
  verifier(texte.includes("Géorisques") && texte.includes("honoraires") && texte.includes("Classe énergie C"), "texte prêt à coller avec toutes les mentions");
  verifier(erreurs.length === 0, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
  console.log("\nRendu Leboncoin : OK");
} catch (e) {
  console.error(e.message);
  await capture("lbc-erreur").catch(() => {});
  process.exitCode = 1;
} finally {
  await browser.close();
}
