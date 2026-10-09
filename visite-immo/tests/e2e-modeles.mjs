// Modèles Gemini : c'est l'administrateur qui choisit (liste avec prix indicatif). Si le modèle de conversation choisi
// n'existe plus chez Google, l'appli le dit clairement, propose les disponibles, et ne change rien d'elle-même.
import { execFileSync } from "node:child_process";
import { navigateur, connexion, api, verifier, dossierAvecMandat, configurerEmail, BASE, DONNEES } from "./outils.mjs";

const { browser, page, erreurs, capture } = await navigateur();
const php = (code) => execFileSync("php", ["-r", `require "app/bootstrap.php"; ${code}`], { cwd: new URL("..", import.meta.url).pathname, env: { ...process.env, VI_DATA_DIR: DONNEES + "/data", VI_SETTINGS: DONNEES + "/settings.json" } }).toString();

try {
  await connexion(page);
  await configurerEmail(page);
  const id = await dossierAvecMandat(page);
  // Clé Gemini (simulée) et ancien modèle de conversation, retiré depuis par Google
  php(`$s = read_json(SETTINGS_FILE, []); $s["gemini_api_key"] = "cle-test"; $s["api_gemini"] = "http://127.0.0.1:8098/gemini"; $s["modele_dialogue"] = "gemini-live-2.5-flash-preview"; write_json(SETTINGS_FILE, $s);`);

  const r = await api(page, "live", { method: "POST", query: { id } });
  verifier(r.status === 502 && /n'est plus proposé par Google/.test(r.error) && r.error.includes("gemini-live-2.6-flash"), `message clair : « ${r.error.slice(0, 140)}… »`);
  verifier((await api(page, "settings")).modele_dialogue === "gemini-live-2.5-flash-preview", "le réglage n'a pas été changé à votre place");

  await page.goto(`${BASE}#/visite/${id}/dialogue`);
  await page.waitForSelector("#start");
  await page.click("#start");
  await page.waitForSelector("#dlg-erreur");
  verifier((await page.textContent("#dlg-erreur")).includes("plus proposé par Google") && (await page.isVisible("#dlg-erreur a[href='#/reglages']")), "écran de conversation : message affiché et bouton vers les Paramètres");
  await capture("modeles-00-conversation");

  // Paramètres : liste des modèles avec prix indicatif, moins chers d'abord, ancien modèle signalé
  await page.goto(`${BASE}#/reglages`);
  await page.waitForSelector("#load");
  await page.click("#load");
  await page.waitForFunction(() => document.querySelectorAll("#m-dialogue option").length >= 3);
  const options = await page.$$eval("#m-dialogue option", (l) => l.map((o) => o.textContent));
  verifier(options[0].startsWith("⚠") && options[0].includes("plus proposé"), "Paramètres : ancien modèle signalé ⚠");
  verifier(options.slice(1).every((o) => /^€+ · /.test(o)) && options.slice(1).findIndex((o) => o.includes("native-audio")) === options.length - 2, `modèles disponibles avec prix indicatif, le plus cher en dernier (${options.slice(1).map((o) => o.split(" · ")[0] + " " + o.split(" · ")[1]).join(" | ")})`);
  await page.selectOption("#m-dialogue", "gemini-live-2.6-flash");
  await Promise.all([page.waitForResponse((x) => x.url().includes("r=settings") && x.request().method() === "POST"), page.click("form .btn.primary.big")]);
  verifier((await api(page, "settings")).modele_dialogue === "gemini-live-2.6-flash", "nouveau modèle choisi et enregistré par l'administrateur");
  await page.locator("#m-dialogue").scrollIntoViewIfNeeded();
  await capture("modeles-01-parametres");
  verifier(erreurs.length === 0, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
  console.log("\nModèles : OK");
} catch (e) {
  console.error(e.message);
  await capture("modeles-erreur").catch(() => {});
  process.exitCode = 1;
} finally {
  await browser.close();
}
