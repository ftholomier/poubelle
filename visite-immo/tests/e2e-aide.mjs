// Questions difficiles : « ? » à côté des champs juridiques dans la fiche, et pendant la conversation vocale
// les boutons « Passer cette question » et « Je ne comprends pas » (la conversation Gemini Live est simulée).
import { execFileSync } from "node:child_process";
import { mkdirSync, writeFileSync } from "node:fs";
import { navigateur, connexion, api, verifier, dossierAvecMandat, configurerEmail, BASE, DONNEES } from "./outils.mjs";

// Micro simulé : 0,6 s de « parole » (son modulé) puis 2,5 s de silence, en boucle
const wav = DONNEES + "/parole-silence.wav";
{
  const taux = 16000, n = Math.round(taux * 3.1), donnees = Buffer.alloc(44 + n * 2);
  donnees.write("RIFF", 0); donnees.writeUInt32LE(36 + n * 2, 4); donnees.write("WAVEfmt ", 8); donnees.writeUInt32LE(16, 16);
  donnees.writeUInt16LE(1, 20); donnees.writeUInt16LE(1, 22); donnees.writeUInt32LE(taux, 24); donnees.writeUInt32LE(taux * 2, 28);
  donnees.writeUInt16LE(2, 32); donnees.writeUInt16LE(16, 34); donnees.write("data", 36); donnees.writeUInt32LE(n * 2, 40);
  for (let i = 0; i < n; i++) {
    const t = i / taux;
    const v = t < 0.6 ? 0.4 * Math.sin(2 * Math.PI * 180 * t) * (0.6 + 0.4 * Math.sin(2 * Math.PI * 4 * t)) : 0;
    donnees.writeInt16LE(Math.round(v * 32767), 44 + i * 2);
  }
  mkdirSync(DONNEES, { recursive: true });
  writeFileSync(wav, donnees);
}
const { browser, page, erreurs, capture } = await navigateur({ audio: wav });
const php = (code) => execFileSync("php", ["-r", `require "app/bootstrap.php"; ${code}`], { cwd: new URL("..", import.meta.url).pathname, env: { ...process.env, VI_DATA_DIR: DONNEES + "/data", VI_SETTINGS: DONNEES + "/settings.json" } }).toString();

// La conversation Gemini Live simulée doit être en place avant le chargement de l'appli.
// Comme Google : Gemini 3.1 coupe la conversation (code 1007) si le texte arrive en clientContent.
const recus = [];
let couperAvec = 0; // coupure du serveur au prochain message
let refuserClientContent = false; // pour simuler un futur modèle qui ferait de même sans être connu de l'appli
await page.routeWebSocket(/generativelanguage\.googleapis\.com/, (ws) => {
  let voix = false;
  let modele = "";
  const dire = (texte) => ws.send(JSON.stringify({ serverContent: voix ? { outputTranscription: { text: texte }, turnComplete: true } : { modelTurn: { parts: [{ text: texte }] }, turnComplete: true } }));
  ws.onMessage((m) => {
    const msg = JSON.parse(typeof m === "string" ? m : m.toString());
    recus.push(msg);
    if (msg.setup) {
      modele = msg.setup.model;
      voix = msg.setup.generationConfig.responseModalities[0] === "AUDIO";
      if (msg.setup.generationConfig.speechConfig?.languageCode && modele.includes("gemini-3")) return ws.close({ code: 1007, reason: "Precondition check failed." });
      return ws.send(JSON.stringify({ setupComplete: {} }));
    }
    if (couperAvec) return ws.close({ code: couperAvec, reason: "Internal error" });
    // Gemini 3.1 : détection de parole manuelle, langue imposée ou texte en clientContent → coupure
    const g31 = modele.includes("gemini-3.1");
    if (g31 && (msg.realtimeInput?.activityStart || msg.realtimeInput?.activityEnd)) return ws.close({ code: 1007, reason: "Precondition check failed." });
    if (msg.clientContent && (modele.includes("gemini-3.1") || refuserClientContent)) return ws.close({ code: 1007, reason: "Request contains an invalid argument." });
    const texte = msg.clientContent?.turns?.[0]?.parts?.[0]?.text || msg.realtimeInput?.text || "";
    if (texte.startsWith("Je suis prêt")) dire("Quelle est l'origine de propriété du bien ?");
    else if (texte.startsWith("[EXPLIQUER]")) dire("C'est la façon dont le vendeur est devenu propriétaire : achat, héritage ou donation. Par exemple, achat en 2005 chez un notaire. Quelle est l'origine de propriété ?");
    else if (texte.startsWith("[PASSER]")) dire("Très bien. Le bien est-il loué ?");
  });
});
const texteEnvoye = (m) => m.clientContent?.turns?.[0]?.parts?.[0]?.text || m.realtimeInput?.text || "";
const reglerModele = (modele) => php(`$s = read_json(SETTINGS_FILE, []); $s["gemini_api_key"] = "cle-test"; $s["api_gemini"] = "http://127.0.0.1:8098/gemini"; $s["api_gemini_live"] = "http://127.0.0.1:8098/gemini"; $s["modele_dialogue"] = "${modele}"; write_json(SETTINGS_FILE, $s);`);
const ouvrir = async (id) => {
  recus.length = 0;
  await page.goto(`${BASE}#/visite/${id}/fiche`); // repasse par la fiche : sinon même adresse, écran non rechargé
  await page.waitForSelector(".aide-champ");
  await page.goto(`${BASE}#/visite/${id}/dialogue`);
  await page.waitForSelector("#start");
  await page.click("#start");
  await page.waitForFunction(() => document.querySelector("#ia")?.textContent.includes("origine de propriété"));
};

try {
  await connexion(page);
  await configurerEmail(page);
  const id = await dossierAvecMandat(page);

  // 1. Les explications viennent du serveur avec la liste des champs
  const sections = await api(page, "fields");
  const origine = sections.flatMap((s) => s.champs).find((c) => c.cle === "origine_propriete");
  verifier(/héritage/.test(origine?.aide || "") && /notari/.test(origine.aide), "« Origine de propriété » a une explication simple");

  // 2. Fiche : le « ? » ouvre et referme l'explication
  await page.goto(`${BASE}#/visite/${id}/fiche`);
  await page.waitForSelector(".aide-champ[data-aide='origine_propriete']");
  const bouton = page.locator(".aide-champ[data-aide='origine_propriete']");
  verifier(await page.isHidden("#aide-origine_propriete"), "explication fermée par défaut");
  await bouton.scrollIntoViewIfNeeded();
  await bouton.click();
  verifier((await page.isVisible("#aide-origine_propriete")) && (await bouton.getAttribute("aria-expanded")) === "true", "fiche : le « ? » affiche l'explication");
  await capture("aide-00-fiche");
  await bouton.click();
  verifier(await page.isHidden("#aide-origine_propriete"), "fiche : un second appui la referme");

  // 3. Conversation vocale simulée
  reglerModele("gemini-live-2.6-flash");
  await ouvrir(id);
  const setup = recus.find((m) => m.setup)?.setup;
  const consignes = setup?.systemInstruction?.parts?.[0]?.text || "";
  verifier(consignes.includes("[PASSER]") && consignes.includes("[EXPLIQUER]") && consignes.includes("titre de propriété"), "consignes de l'IA : règles Passer / Expliquer et explications des champs");
  verifier((await page.isVisible("#passer")) && (await page.isVisible("#expliquer")), "conversation : boutons « Passer » et « Je ne comprends pas » affichés");

  await page.click("#expliquer");
  await page.waitForFunction(() => document.querySelector("#ia")?.textContent.includes("héritage"));
  verifier(recus.some((m) => texteEnvoye(m).startsWith("[EXPLIQUER]")), "« Je ne comprends pas » : l'IA explique la question avec un exemple");
  await capture("aide-01-expliquer");

  await page.click("#passer");
  await page.waitForFunction(() => document.querySelector("#ia")?.textContent.includes("loué"));
  verifier(recus.some((m) => texteEnvoye(m).startsWith("[PASSER]")), "« Passer » : l'IA passe à la question suivante");
  await capture("aide-02-passer");

  await page.click("#stop");

  // 4. Gemini 3.1 Flash Live (voix seulement, texte en realtimeInput) : plus de coupure à l'ouverture
  reglerModele("gemini-3.1-flash-live-preview");
  await ouvrir(id);
  const setup31 = recus.find((m) => m.setup)?.setup;
  verifier(setup31.generationConfig.responseModalities[0] === "AUDIO" && setup31.outputAudioTranscription, "Gemini 3.1 : réponse en voix, avec le texte affiché à l'écran");
  verifier(!recus.some((m) => m.clientContent) && recus.some((m) => m.realtimeInput?.text?.startsWith("Je suis prêt")), "Gemini 3.1 : le texte part en realtimeInput, la conversation ne coupe plus");
  verifier(!setup31.realtimeInputConfig && !setup31.generationConfig.speechConfig?.languageCode && !recus.some((m) => m.realtimeInput?.activityStart), "Gemini 3.1 : réglage standard (Google détecte la parole, langue non imposée)");
  await page.click("#expliquer");
  await page.waitForFunction(() => document.querySelector("#ia")?.textContent.includes("héritage"));
  await page.click("#passer");
  await page.waitForFunction(() => document.querySelector("#ia")?.textContent.includes("loué"));
  verifier(true, "Gemini 3.1 : « Je ne comprends pas » et « Passer » fonctionnent");
  // le micro simulé de Chromium émet un bip régulier : il doit partir en audio, puis « fin du flux » au silence
  for (let i = 0; i < 40 && !recus.some((m) => m.realtimeInput?.audioStreamEnd); i++) await page.waitForTimeout(500);
  verifier(recus.some((m) => m.realtimeInput?.audio) && recus.some((m) => m.realtimeInput?.audioStreamEnd), "Gemini 3.1 : la voix part en audio, puis « fin du flux » quand l'agent se tait");
  await page.click("#stop");

  // 5. Modèle inconnu qui coupe en 1007 : l'appli rouvre seule la conversation en realtimeInput
  reglerModele("gemini-live-2.6-flash");
  refuserClientContent = true;
  await ouvrir(id);
  verifier(recus.filter((m) => m.setup).length === 2 && recus.some((m) => m.realtimeInput?.text?.startsWith("Je suis prêt")), "coupure 1007 : reprise automatique, sans intervention");
  await page.click("#stop");
  refuserClientContent = false;

  // 6. Autre coupure côté Google : le code est affiché pour le diagnostic
  await ouvrir(id);
  couperAvec = 1011;
  await page.click("#passer");
  await page.waitForSelector(".erreur");
  verifier(/coupée par Google \(code 1011 · Internal error · après [\w.]+\)/.test(await page.textContent(".erreur")), `coupure : code affiché (« ${(await page.textContent(".erreur")).slice(0, 80)}… »)`);
  await capture("aide-03-coupure");
  couperAvec = 0;

  verifier(erreurs.length === 0, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
  console.log("\nAide aux questions : OK");
} catch (e) {
  console.error(e.message);
  await capture("aide-erreur").catch(() => {});
  process.exitCode = 1;
} finally {
  await browser.close();
}
