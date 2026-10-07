// Parcours de base : compte, Aujourd'hui, visite enregistrée (mode démo), création du dossier, résumé, documents.
import { navigateur, connexion, verifier, api } from "./outils.mjs";

const { browser, page, erreurs, capture } = await navigateur();
try {
  await connexion(page);
  await page.waitForSelector(".home-hero");
  await capture("01-aujourdhui-vide");

  await page.click(".tb-rec");
  await page.waitForSelector("#rec");
  await page.fill("#titre", "11 rue du Chênois, Lougres");
  await page.check("#consent");
  await page.click("#rec");
  await page.waitForSelector(".rec-btn.recording");
  await page.waitForTimeout(2500);
  await page.click("#stop");
  await page.waitForSelector("#gen");
  await page.click("#gen");
  await page.waitForSelector(".stepper", { timeout: 60000 });
  await page.waitForTimeout(500);
  await capture("02-resume");
  verifier(await page.$(".prochaine"), "carte « Prochaine étape » affichée");

  await page.click(".tabs a[href$='/documents']");
  await page.waitForSelector(".doc-ligne");
  await capture("03-documents");
  const nbDocs = await page.$$eval(".doc-ligne", (l) => l.length);
  verifier(nbDocs >= 6, `${nbDocs} documents listés`);

  await page.click(".bar .icon-btn"); // retour
  await page.click(".tabbar a[href='#/biens']");
  await page.waitForSelector(".visite");
  await capture("04-biens");
  await page.click(".tabbar a[href='#/']");
  await page.waitForSelector(".ajd-item");
  await capture("05-aujourdhui");

  verifier(!erreurs.length, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
} finally {
  await browser.close();
}
