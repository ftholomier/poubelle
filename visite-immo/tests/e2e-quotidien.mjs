// Au quotidien : bilan d'appel dicté (tâches, rappel), commande vocale, briefing, tâches cochées,
// tableau de bord, notifications push (chiffrement RFC 8291 vérifié en déchiffrant le message).
import { navigateur, connexion, verifier, api, configurerEmail, dossierEnVente, DONNEES } from "./outils.mjs";
import crypto from "node:crypto";
import { readFileSync, readdirSync } from "node:fs";
import { execSync } from "node:child_process";

const { browser, page, erreurs, capture } = await navigateur();
try {
  await connexion(page);
  await configurerEmail(page);
  const id = await dossierEnVente(page);

  // 1. Bilan d'appel dicté → noté dans le dossier, avenant de prix en tâche, rappel à l'agenda
  await page.goto("http://127.0.0.1:8099/#/");
  await page.waitForSelector("#ajd-appel");
  await capture("60-aujourdhui-actions");
  await page.click("#ajd-appel");
  await page.click("summary");
  await page.fill("#d-texte", "J'ai eu madame Martin pour le Chênois, elle accepte 399 000, je la rappelle jeudi 10 h.");
  await page.click("#d-envoyer-texte");
  await page.waitForSelector(".prets li");
  await capture("61-bilan-appel");
  let v = await api(page, "visit", { query: { id } });
  verifier(v.journal.some((j) => j.type === "appel") && v.prix_propose_vendeur?.prix === 399000, "appel noté dans le dossier avec le nouveau prix proposé");
  const ag = await api(page, "agenda", { query: { a: new Date(Date.now() + 9 * 86400e3).toISOString() } });
  verifier(ag.rdv.some((r) => r.type === "appel"), "rappel ajouté à l'agenda");
  await page.click("[data-close]");
  await page.waitForSelector("[data-tache]");
  const nbTaches = await page.$$eval("[data-tache]", (l) => l.length);
  verifier(nbTaches >= 1, `${nbTaches} tâche(s) du jour (avenant à faire signer ; les autres le jour du rappel)`);
  await page.click("[data-tache]");
  await page.waitForSelector(".ajd-item.fait");
  verifier(true, "tâche cochée");

  // 2. Commande vocale : « relance la vendeuse du Chênois »
  await page.click("#ajd-commande");
  await page.click("summary");
  await page.fill("#d-texte", "Relance la vendeuse du Chênois pour ses papiers");
  await page.click("#d-envoyer-texte");
  await page.waitForSelector("#c-ok");
  await capture("62-commande");
  await page.click("#c-ok");
  await page.waitForSelector(".toast.ok");
  v = await api(page, "visit", { query: { id } });
  verifier(v.journal.some((j) => /commande vocale/.test(j.texte)), "commande exécutée : vendeur relancé");

  // 3. Briefing
  const b = await api(page, "briefing");
  verifier(/Bonjour Frédéric/.test(b.texte), "briefing : « " + b.texte.split("\n").slice(0, 2).join(" ") + " … »");

  // 4. Tableau de bord
  await page.goto("http://127.0.0.1:8099/#/tableau");
  await page.waitForSelector(".paliers-barre");
  await capture("63-tableau");
  const t = await api(page, "tableau");
  verifier(t.temps_total.minutes > 100 && t.taux.taux === 80, `tableau de bord : palier ${t.taux.taux} %, ${Math.round(t.temps_total.minutes / 60)} h gagnées (estimation)`);

  // 5. Notifications : abonnement simulé, envoi, déchiffrement du message
  const ecdh = crypto.createECDH("prime256v1");
  ecdh.generateKeys();
  const auth = crypto.randomBytes(16);
  const b64u = (x) => x.toString("base64url");
  await api(page, "push_abonnement", { method: "POST", body: { abonnement: { endpoint: "http://127.0.0.1:8098/push/abc", keys: { p256dh: b64u(ecdh.getPublicKey()), auth: b64u(auth) } } } });
  const r = await api(page, "push_test", { method: "POST" });
  verifier(r.envoyes === 1, "notification envoyée au service push");
  const dir = DONNEES + "/push";
  const n = readdirSync(dir).filter((f) => f.endsWith(".bin")).length - 1;
  const corps = readFileSync(`${dir}/${n}.bin`);
  const meta = JSON.parse(readFileSync(`${dir}/${n}.json`, "utf8"));
  // Déchiffrement RFC 8291
  const sel = corps.subarray(0, 16), idlen = corps[20], asPub = corps.subarray(21, 21 + idlen), chiffre = corps.subarray(21 + idlen);
  const partage = ecdh.computeSecret(asPub);
  const hmac = (k, d) => crypto.createHmac("sha256", k).update(d).digest();
  const ikm = hmac(hmac(auth, partage), Buffer.concat([Buffer.from("WebPush: info\0"), ecdh.getPublicKey(), asPub, Buffer.from([1])]));
  const prk = hmac(sel, ikm);
  const cek = hmac(prk, Buffer.concat([Buffer.from("Content-Encoding: aes128gcm\0"), Buffer.from([1])])).subarray(0, 16);
  const nonce = hmac(prk, Buffer.concat([Buffer.from("Content-Encoding: nonce\0"), Buffer.from([1])])).subarray(0, 12);
  const d = crypto.createDecipheriv("aes-128-gcm", cek, nonce);
  d.setAuthTag(chiffre.subarray(-16));
  const clair = Buffer.concat([d.update(chiffre.subarray(0, -16)), d.final()]);
  const msg = JSON.parse(clair.subarray(0, clair.lastIndexOf(2)).toString("utf8"));
  verifier(meta.encoding === "aes128gcm" && msg.titre === "Notifications activées ✓", "message déchiffré : « " + msg.titre + " »");
  // Signature VAPID (JWT ES256) vérifiée avec la clé publique de l'appli
  const [, jwt, k] = meta.authorization.match(/^vapid t=([^,]+), k=(.+)$/);
  const [h, c, s] = jwt.split(".");
  const cle = crypto.createPublicKey({ key: { kty: "EC", crv: "P-256", x: b64u(Buffer.from(k, "base64url").subarray(1, 33)), y: b64u(Buffer.from(k, "base64url").subarray(33)) }, format: "jwk" });
  verifier(crypto.verify("sha256", Buffer.from(`${h}.${c}`), { key: cle, dsaEncoding: "ieee-p1363" }, Buffer.from(s, "base64url")), "signature VAPID valide (" + JSON.parse(Buffer.from(c, "base64url")).aud + ")");

  verifier(!erreurs.length, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
} finally {
  await browser.close();
}
