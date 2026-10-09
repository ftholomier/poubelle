// Compteur du coût de l'IA, toujours visible en haut à droite : mis à jour à chaque réponse du serveur,
// et en direct pendant une conversation vocale (jetons comptés par le téléphone, Gemini Live simulé).
import { execFileSync } from "node:child_process";
import { navigateur, connexion, verifier, dossierAvecMandat, configurerEmail, BASE, DONNEES } from "./outils.mjs";

const { browser, page, erreurs, capture } = await navigateur();
const php = (code) => execFileSync("php", ["-r", `require "app/bootstrap.php"; ${code}`], { cwd: new URL("..", import.meta.url).pathname, env: { ...process.env, VI_DATA_DIR: DONNEES + "/data", VI_SETTINGS: DONNEES + "/settings.json" } }).toString();

// Gemini Live simulé : chaque réponse annonce 20 000 jetons lus et 500 écrits (texte)
await page.routeWebSocket(/generativelanguage\.googleapis\.com/, (ws) => {
  ws.onMessage((m) => {
    const msg = JSON.parse(typeof m === "string" ? m : m.toString());
    if (msg.setup) return ws.send(JSON.stringify({ setupComplete: {} }));
    const texte = msg.clientContent?.turns?.[0]?.parts?.[0]?.text || msg.realtimeInput?.text;
    if (!texte) return;
    ws.send(JSON.stringify({ serverContent: { modelTurn: { parts: [{ text: "Quelle est l'origine de propriété du bien ?" }] }, turnComplete: true } }));
    ws.send(JSON.stringify({ usageMetadata: { promptTokenCount: 20000, promptTokensDetails: [{ modality: "TEXT", tokenCount: 20000 }], responseTokenCount: 500, responseTokensDetails: [{ modality: "TEXT", tokenCount: 500 }] } }));
  });
});
const montant = async () => Number((await page.textContent("#cout-ia")).replace(/[^\d,]/g, "").replace(",", "."));

try {
  verifier((await page.goto(BASE), await page.$("#cout-ia")) === null, "pas de compteur avant la connexion");
  await connexion(page);
  await configurerEmail(page);
  await page.waitForSelector("#cout-ia");
  const boite = await page.locator("#cout-ia").boundingBox();
  const menu = await page.locator("#menu-btn").boundingBox();
  verifier(boite.x + boite.width > 370 && boite.y < 30, `compteur en haut à droite (${Math.round(boite.x)}, ${Math.round(boite.y)})`);
  verifier(!menu || menu.x + menu.width <= boite.x, "il ne recouvre pas le menu");
  verifier((await page.textContent("#cout-ia")).includes("0,000 €"), "0,000 € au départ");
  await capture("cout-00-accueil");

  // Dépense enregistrée côté serveur (analyse d'une visite) : le compteur suit dès la réponse suivante
  const id = await dossierAvecMandat(page);
  php(`log_cout(users()[0], null, "analyse", 0.0123);`);
  await page.goto(`${BASE}#/biens`);
  await page.waitForFunction(() => document.querySelector("#cout-ia")?.textContent.includes("0,012"));
  verifier(true, "dépense du serveur affichée (0,012 €)");

  // Conversation vocale : le coût monte en direct
  php(`$s = read_json(SETTINGS_FILE, []); $s["gemini_api_key"] = "cle-test"; $s["api_gemini"] = "http://127.0.0.1:8098/gemini"; $s["api_gemini_live"] = "http://127.0.0.1:8098/gemini"; $s["modele_dialogue"] = "gemini-live-2.6-flash"; write_json(SETTINGS_FILE, $s);`);
  await page.goto(`${BASE}#/visite/${id}/dialogue`);
  await page.waitForSelector("#start");
  await page.click("#start");
  await page.waitForFunction(() => document.querySelector("#cout-ia.en-direct"));
  const pendant1 = await montant();
  verifier(Math.abs(pendant1 - 0.022) < 0.0006, `conversation : coût ajouté en direct (${pendant1} €)`);
  await capture("cout-01-conversation");
  await page.click("#passer");
  await page.waitForFunction((avant) => Number(document.querySelector("#cout-ia").textContent.replace(/[^\d,]/g, "").replace(",", ".")) > avant, pendant1);
  verifier((await montant()) > pendant1, `chaque échange l'augmente (${await montant()} €)`);

  // Fin : le coût de la conversation est enregistré par le serveur, le compteur reste juste
  await page.click("#stop");
  await page.waitForSelector("#regen");
  await page.waitForFunction(() => !document.querySelector("#cout-ia.en-direct"));
  const total = JSON.parse(php(`echo json_encode(read_json(DATA_DIR . "/couts/" . date("Y-m") . ".json", []));`)).total;
  verifier(Math.abs(total - 0.0321) < 0.0002, `conversation enregistrée côté serveur (${total} €)`);
  await page.goto(`${BASE}#/biens`);
  await page.waitForTimeout(400);
  verifier(Math.abs((await montant()) - total) < 0.0006, `compteur = total du serveur (${await montant()} €)`);

  // Le détail au toucher
  await page.click("#cout-ia");
  await page.waitForSelector(".toast");
  verifier((await page.textContent(".toast")).includes("de l'agence ce mois-ci"), "détail : coût de l'agence ce mois-ci");

  // Déconnexion : le compteur disparaît
  await page.evaluate(() => fetch("api/?r=logout", { method: "POST", headers: { "X-Requested-With": "visite-immo" }, credentials: "same-origin" }));
  await page.goto(`${BASE}#/connexion`);
  await page.reload();
  await page.waitForSelector("#f");
  verifier((await page.$("#cout-ia")) === null, "après déconnexion : plus de compteur");
  verifier(erreurs.length === 0, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
  console.log("\nCompteur de coût : OK");
} catch (e) {
  console.error(e.message);
  await capture("cout-erreur").catch(() => {});
  process.exitCode = 1;
} finally {
  await browser.close();
}
