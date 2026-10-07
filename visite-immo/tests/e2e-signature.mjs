// Signature électronique par un service externe : firma.dev (champs placés dans nos cadres, webhook signé HMAC,
// exemplaire signé récupéré) puis BoldSign (même intégration que le projet Qualiopi, synchronisation de l'état).
import { createHmac } from "node:crypto";
import { readFileSync, writeFileSync, rmSync } from "node:fs";
import { navigateur, connexion, api, verifier, configurerEmail, dossierAvecMandat, mails, DONNEES, BASE } from "./outils.mjs";

const { browser, page, erreurs, capture } = await navigateur();

async function webhook(entetes, corps) {
  const r = await fetch(BASE + "api/signature.php", { method: "POST", headers: { "Content-Type": "application/json", ...entetes }, body: corps });
  return r.status;
}
const signer = (secret, t, corps, cle = "v1") => `t=${t},${cle}=${createHmac("sha256", secret).update(`${t}.${corps}`).digest("hex")}`;

try {
  await connexion(page);
  await configurerEmail(page);

  // ---------- firma.dev ----------
  const id = await dossierAvecMandat(page);
  await api(page, "settings", { method: "POST", body: { signature_mode: "firma", signature_api_cle: "cle-firma-test" } });
  const cfg = await api(page, "settings");
  verifier(cfg.signature_mode === "firma" && cfg.signature_api_cle === "••••••", "mode firma.dev enregistré, clé masquée");

  await page.goto(`${BASE}#/visite/${id}/signature/mandat`);
  await page.click("#creer");
  await page.waitForSelector("#liens");
  verifier(!(await page.$("[data-signer]")), "pas de signature au doigt en mode firma.dev");
  await capture("sig-01-firma-a-envoyer");
  await Promise.all([page.waitForResponse((r) => r.url().includes("signature_demande")), page.click("#liens")]);
  await page.waitForSelector("#synchro");
  await capture("sig-02-firma-envoye");

  const envoi = JSON.parse(readFileSync(DONNEES + "/firma-derniere.json", "utf8"));
  verifier(Buffer.from(envoi.document, "base64").subarray(0, 4).toString() === "%PDF", "PDF du mandat envoyé en base64");
  verifier(envoi.recipients.length === 2 && envoi.recipients[0].email === "claire.martin@exemple.fr" && envoi.recipients[1].email === "fred@agence.fr", "2 signataires : vendeur puis agent");
  verifier(envoi.language === "fr" && envoi.recipients[0].designation === "Signer", "langue française, rôle Signer");
  verifier(
    envoi.fields.length === 2 && envoi.fields.every((f) => f.type === "signature" && f.page_number >= 1 && f.position.x > 0 && f.position.x + f.position.width < 100 && f.position.y + f.position.height < 100),
    `champs de signature placés dans les cadres du mandat (page ${envoi.fields[0].page_number}, x ${envoi.fields[0].position.x} %, y ${envoi.fields[0].position.y} %)`,
  );
  verifier(envoi.fields[0].recipient_id === "temp_1" && envoi.fields[1].recipient_id === "temp_2", "chaque champ rattaché à son signataire");

  let v = await api(page, "dossier", { query: { id } });
  const apiId = v.signatures.mandat.api_id;
  verifier(apiId?.startsWith("sr_") && v.signatures.mandat.signataires[0].api_id === "rcp_temp_1", `demande firma.dev ${apiId} rattachée au dossier`);

  // Adresse de retour déclarée chez firma.dev → secret mémorisé
  const wh = await api(page, "signature_webhook", { method: "POST" });
  verifier(wh.ok && wh.secret_enregistre, "webhook déclaré chez firma.dev, secret enregistré");

  // Webhook mal signé → refusé
  const t = Math.floor(Date.now() / 1000);
  const corpsSigne = JSON.stringify({ id: "evt_1", type: "signing_request.recipient.signed", created_at: new Date().toISOString(), data: { signing_request: { id: apiId }, recipients: [{ id: "rcp_temp_1", email: "claire.martin@exemple.fr", name: "Claire Martin", signed_at: new Date().toISOString() }] } });
  verifier((await webhook({ "X-Firma-Signature": signer("mauvais", t, corpsSigne) }, corpsSigne)) === 401, "webhook avec une mauvaise signature HMAC refusé");
  verifier((await webhook({ "X-Firma-Signature": signer("secret-webhook-test", t - 7200, corpsSigne) }, corpsSigne)) === 401, "webhook trop ancien refusé (rejeu)");
  verifier((await webhook({ "X-Firma-Signature": signer("secret-webhook-test", t, corpsSigne), "X-Firma-Event": "signing_request.recipient.signed" }, corpsSigne)) === 200, "webhook « signataire a signé » accepté");
  v = await api(page, "dossier", { query: { id } });
  verifier(v.signatures.mandat.signataires[0].signe_le && !v.signatures.mandat.signataires[1].signe_le && v.signatures.mandat.statut === "en_attente", "vendeur coché, agent en attente");

  // Fin de signature : PDF signé récupéré, mandat → en vente, exemplaires envoyés
  writeFileSync(DONNEES + "/firma-fini", "1");
  const corpsFin = JSON.stringify({ id: "evt_2", type: "signing_request.completed", created_at: new Date().toISOString(), data: { signing_request: { id: apiId, status: "completed" } } });
  verifier((await webhook({ "X-Firma-Signature": signer("secret-webhook-test", t, corpsFin) }, corpsFin)) === 200, "webhook « terminé » accepté");
  v = await api(page, "dossier", { query: { id } });
  verifier(v.signatures.mandat.statut === "signe" && v.etape === "en_vente", "mandat signé via firma.dev, dossier « en vente »");
  verifier(v.signatures.mandat.signataires.every((s) => s.signe_le && s.methode), "tous les signataires cochés");
  const pdf = await page.evaluate(async (u) => await (await fetch(u)).text(), `api/?r=pdf&id=${id}&doc=mandat`);
  verifier(pdf.includes("SIGNE-PAR-LE-SERVICE"), "l'exemplaire signé est celui renvoyé par firma.dev");
  const recus = (await mails()).filter((m) => m.sujet.startsWith("Signé : Mandat"));
  verifier(recus.length === 2, "exemplaire signé envoyé au vendeur et à l'agent");
  verifier((await webhook({ "X-Firma-Signature": signer("secret-webhook-test", t, corpsFin) }, corpsFin)) === 200 && (await mails()).filter((m) => m.sujet.startsWith("Signé : Mandat")).length === 2, "webhook rejoué : rien n'est envoyé deux fois");
  await page.goto(`${BASE}#/visite/${id}/signature/mandat`);
  await page.reload();
  await page.waitForSelector(".tag-citron");
  await capture("sig-03-firma-signe");

  // ---------- BoldSign ----------
  const id2 = await dossierAvecMandat(page, "4 rue des Lilas, Lougres");
  await api(page, "settings", { method: "POST", body: { signature_mode: "boldsign", signature_api_cle: "cle-boldsign-test", signature_webhook_secret: "secret-bs" } });
  await api(page, "signature_demande", { method: "POST", query: { id: id2 }, body: { doc: "mandat" } });
  v = await api(page, "signature_demande", { method: "POST", query: { id: id2 }, body: { doc: "mandat", envoyer: true } });
  const bs = JSON.parse(readFileSync(DONNEES + "/boldsign-derniere.json", "utf8"));
  verifier(bs.fichier && bs.champs["Signers[0].EmailAddress"] === "claire.martin@exemple.fr" && bs.champs["Signers[1].EmailAddress"] === "fred@agence.fr", "BoldSign : PDF en multipart, 2 signataires");
  verifier(+bs.champs["Signers[0].formFields[0].bounds.x"] > 0 && +bs.champs["Signers[0].formFields[0].bounds.y"] > 100 && +bs.champs["Signers[0].formFields[0].bounds.y"] < 1123 && bs.champs["Signers[0].formFields[0].fieldType"] === "Signature", "BoldSign : champ de signature en pixels (96 dpi) dans le cadre");
  const doc2 = v.signatures.mandat.api_id;
  verifier(doc2?.startsWith("bs_"), `document BoldSign ${doc2}`);
  verifier((await webhook({}, JSON.stringify({ event: { eventType: "Viewed" }, data: { documentId: doc2 } }))) === 200, "BoldSign : événement intermédiaire acquitté");
  const corpsBs = JSON.stringify({ event: { eventType: "Completed" }, data: { documentId: doc2 } });
  verifier((await webhook({ "X-BoldSign-Signature": `t=${t}, s0=00` }, corpsBs)) === 401, "BoldSign : « Completed » mal signé refusé");
  let r = await api(page, "signature_synchro", { method: "POST", query: { id: id2 }, body: { doc: "mandat" } });
  verifier(r.etat === "en_cours", "synchronisation : toujours en attente chez BoldSign");
  writeFileSync(DONNEES + "/boldsign-fini", "1");
  verifier((await webhook({ "X-BoldSign-Signature": signer("secret-bs", t, corpsBs, "s0") }, corpsBs)) === 200, "BoldSign : « Completed » signé accepté");
  v = await api(page, "dossier", { query: { id: id2 } });
  verifier(v.signatures.mandat.statut === "signe" && v.etape === "en_vente", "mandat signé via BoldSign, dossier « en vente »");

  // Retour au mode intégré : la signature au doigt fonctionne toujours
  await api(page, "settings", { method: "POST", body: { signature_mode: "interne" } });
  verifier((await api(page, "settings")).signature_mode === "interne", "retour au mode intégré");
  verifier(erreurs.length === 0, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
  console.log("\nSignature externe : OK");
} catch (e) {
  console.error(e.message);
  await capture("sig-erreur").catch(() => {});
  process.exitCode = 1;
} finally {
  for (const f of ["firma-fini", "boldsign-fini"]) rmSync(DONNEES + "/" + f, { force: true });
  await browser.close();
}
