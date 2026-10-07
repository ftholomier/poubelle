// Suivi du projet en temps réel : coach de captation pendant l'enregistrement (sujets, photos et documents pris
// sans arrêter l'audio), tableau de cases après la visite, mise à jour en direct, export CRM.
import { execSync } from "node:child_process";
import { navigateur, connexion, api, verifier, configurerEmail, CHAMPS_MANDAT, BASE } from "./outils.mjs";

execSync(`php -r '$i=imagecreatetruecolor(1600,1200);for($k=0;$k<400;$k++){imagefilledrectangle($i,rand(0,1600),rand(0,1200),rand(0,1600),rand(0,1200),imagecolorallocate($i,rand(40,200),rand(40,200),rand(40,200)));} imagejpeg($i,"/tmp/suivi-photo.jpg",90);'`);

const { browser, page, erreurs, capture } = await navigateur();
try {
  await connexion(page);
  await configurerEmail(page);

  // ---------- Pendant la visite ----------
  await page.click(".tb-rec");
  await page.fill("#titre", "11 rue du Chênois, Lougres");
  await page.check("#consent");
  await page.click("#rec");
  await page.waitForSelector(".rec-btn.recording");
  await page.waitForSelector(".captation .cap-sujet");
  verifier(true, "panneau « Captation en direct » affiché dès le début de l'enregistrement");
  await page.setInputFiles("#cap-photo", "/tmp/suivi-photo.jpg");
  await page.waitForFunction(() => /1 photo/.test(document.getElementById("cap-compte")?.textContent || ""), null, { timeout: 30000 });
  verifier(await page.isVisible(".rec-btn.recording"), "photo du bien prise sans arrêter l'enregistrement");
  await capture("suivi-01-captation");
  await page.waitForTimeout(1500);
  await page.click("#stop");
  await page.waitForSelector("#gen");
  await page.waitForFunction(() => document.querySelectorAll(".cap-sujet.ok").length >= 3, null, { timeout: 40000 });
  const couverts = await page.$$eval(".cap-sujet.ok", (l) => l.length);
  verifier(couverts >= 3, `${couverts} sujets repérés dans la transcription, conseils pour les autres : « ${(await page.textContent("#cap-conseil")).trim().slice(0, 90)}… »`);
  await capture("suivi-02-captation-transcrite");

  // ---------- Sortie de visite : le tableau ----------
  await page.click("#gen");
  await page.waitForSelector(".suivi-compact", { timeout: 60000 });
  const id = page.url().split("/visite/")[1].split("/")[0];
  verifier(await page.isVisible(".suivi-compact .anneau"), "résumé : carte « Suivi du projet » avec pourcentage et pastilles");
  await page.click(".suivi-compact");
  await page.waitForSelector(".suivi-tete");
  const groupes = await page.$$eval(".suivi-groupe h2", (l) => l.map((h) => h.textContent));
  verifier(groupes.length === 4, `4 groupes : ${groupes.join(", ")}`);
  const n = (sel) => page.$$eval(sel, (l) => l.length);
  verifier((await n(".suivi-case .st-fait")) >= 8, `${await n(".suivi-case .st-fait")} cases faites automatiquement à la sortie de visite`);
  verifier((await n(".suivi-case .st-prevu")) === 2, "diffusion réelle et envoi CRM : « prévu (vrai logiciel) »");
  const avant = await page.textContent(".suivi-tete .anneau strong");
  await capture("suivi-03-tableau");

  // ---------- En direct : l'agent complète à la voix, le tableau suit tout seul ----------
  await api(page, "visit", { method: "POST", query: { id }, body: { champs: CHAMPS_MANDAT, source: "dialogue" } });
  await page.waitForFunction((a) => document.querySelector(".suivi-tete .anneau strong")?.textContent !== a, avant, { timeout: 15000 });
  const apres = await page.textContent(".suivi-tete .anneau strong");
  verifier(parseInt(apres) > parseInt(avant), `mise à jour en direct sans recharger : ${avant} → ${apres}`);
  const dialogue = await page.locator(".suivi-case", { hasText: "Questions de l'assistant IA" }).locator(".st").getAttribute("class");
  verifier(dialogue.includes("st-fait"), "case « questions de l'assistant IA » cochée");
  await capture("suivi-04-tableau-complete");

  // ---------- Export CRM et carte Biens ----------
  const crm = await api(page, "crm_export", { query: { id } });
  verifier(crm.format === "synapse-visite-immo/1" && crm.mandant.vendeurs[0].nom === "Martin" && crm.bien.surface_habitable > 0 && crm.mandat.prix === 412000, "export CRM : mandant, bien, mandat");
  verifier(crm.annonce.photos.length === 1 && crm.suivi.pourcentage === parseInt(apres), "export CRM : photos et état du suivi");
  await page.goto(`${BASE}#/biens`);
  await page.waitForSelector(".suivi-mini");
  verifier((await page.textContent(".suivi-mini")).includes(`Opérationnel ${parseInt(apres)} %`), "liste des biens : jauge « opérationnel » sur chaque carte");
  verifier(erreurs.length === 0, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
  console.log("\nSuivi du projet : OK");
} catch (e) {
  console.error(e.message);
  await capture("suivi-erreur").catch(() => {});
  process.exitCode = 1;
} finally {
  await browser.close();
}
