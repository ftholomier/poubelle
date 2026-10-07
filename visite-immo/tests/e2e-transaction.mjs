// Du compromis à l'acte : offre dictée et signée, contre-proposition et acceptation du vendeur, LCB-FT,
// dossier notaire, échéancier et relances, acte → facture, commission, demande d'avis Google.
import { navigateur, connexion, verifier, api, configurerEmail, mails, dossierEnVente, signaturePng, DONNEES } from "./outils.mjs";
import { execSync } from "node:child_process";
import { readFileSync } from "node:fs";

const cron = (tache, quand) => execSync(`VI_DATA_DIR=${DONNEES}/data VI_SETTINGS=${DONNEES}/settings.json php ../app/cron.php --tache=${tache} --maintenant=${quand}`).toString().trim();
const { browser, ctx, page, erreurs, capture } = await navigateur();
try {
  await connexion(page);
  await configurerEmail(page);
  await api(page, "settings", { method: "POST", body: { lien_avis_google: "https://g.page/r/exemple/review" } });
  const id = await dossierEnVente(page);
  await api(page, "acquereur", { method: "POST", body: { prenom: "Julien", nom: "Moreau", email: "julien.moreau@exemple.fr", telephone: "06 22 33 44 55" } });
  const png = await signaturePng();

  // 1. Première offre dictée → signée → le vendeur contre-propose depuis son espace
  let r = await api(page, "dictee", { method: "POST", query: { type: "offre" }, body: { texte: "offre", dossier: id } });
  const o1 = r.resultat.offre;
  verifier(o1.montant === 398000 && o1.acquereur, "offre dictée et rattachée à l'acquéreur (398 000 €)");
  let v = await api(page, "signature_demande", { method: "POST", query: { id }, body: { doc: `offre:${o1.id}` } });
  v = await api(page, "signer", { method: "POST", query: { id }, body: { doc: `offre:${o1.id}`, signataire: "s1", image: png } });
  verifier(v.offres[0].statut === "transmise" && v.signatures[`acceptation:${o1.id}`], "offre signée par l'acquéreur et transmise au vendeur");
  verifier((await mails()).some((m) => m.a === "claire.martin@exemple.fr" && /Offre d'achat reçue/.test(m.sujet)), "vendeur prévenu par e-mail avec l'offre en PDF");
  const liens = JSON.parse(readFileSync(`${DONNEES}/data/liens.json`, "utf8"));
  const tv = Object.keys(liens).find((t) => liens[t].dossier === id && liens[t].role === "vendeur");
  const esp = await ctx.newPage();
  esp.on("pageerror", (e) => erreurs.push("espace : " + e.message));
  esp.on("dialog", (d) => d.accept(d.type() === "prompt" ? (d.message().includes("prix") ? "405000" : "Au plus près du prix affiché") : undefined));
  await esp.goto(`http://127.0.0.1:8099/espace/?t=${tv}`);
  await esp.waitForSelector("[data-offre-contre]");
  await esp.screenshot({ path: new URL("./captures/50-espace-offre.png", import.meta.url).pathname, fullPage: true });
  await esp.click("[data-offre-contre]");
  await esp.waitForLoadState("load");
  await esp.waitForTimeout(800);
  v = await api(page, "visit", { query: { id } });
  verifier(v.offres[0].statut === "contre_offre" && v.offres[0].reponse.prix === 405000, "contre-proposition du vendeur à 405 000 €");

  // 2. Deuxième offre à 405 000 → acceptée par le vendeur (signature)
  r = await api(page, "dictee", { method: "POST", query: { type: "offre" }, body: { texte: "offre", dossier: id } });
  const o2 = r.resultat.offre;
  await api(page, "signature_demande", { method: "POST", query: { id }, body: { doc: `offre:${o2.id}` } });
  await api(page, "signer", { method: "POST", query: { id }, body: { doc: `offre:${o2.id}`, signataire: "s1", image: png } });
  v = await api(page, "signer", { method: "POST", query: { id }, body: { doc: `acceptation:${o2.id}`, signataire: "s1", image: png } });
  verifier(v.etape === "offre" && v.vente?.debut, "offre acceptée par le vendeur : étape « offre », vente ouverte");
  verifier((await mails()).some((m) => m.a === "julien.moreau@exemple.fr" && /acceptée/.test(m.sujet)), "acquéreur prévenu de l'acceptation");

  // 3. Dates, intervenants, LCB-FT, notaires
  const today = new Date().toISOString().slice(0, 10);
  v = await api(page, "vente", { method: "POST", query: { id }, body: { compromis_le: today, notification_sru: today, notaire_vendeur: { nom: "Me Lefèvre", email: "lefevre@notaires.fr" }, notaire_acquereur: { nom: "Me Garnier", email: "garnier@notaires.fr" }, courtier: { nom: "Prêt+", email: "courtier@pretplus.fr" } } });
  verifier(v.etape === "compromis" && v.echeancier.find((e) => e.cle === "sru").date && v.vente.pret_limite, "compromis signé : délai SRU, prêt et acte calculés");
  await page.goto(`http://127.0.0.1:8099/#/visite/${id}/vente`);
  await page.waitForSelector("[data-lcbft]");
  execSync(`php -r '$i=imagecreatetruecolor(800,500);imagefill($i,0,0,imagecolorallocate($i,200,210,230));imagestring($i,5,40,40,"CARTE NATIONALE D IDENTITE",0);imagejpeg($i,"/tmp/cni.jpg");'`);
  for (const p of ["vendeur", "acquereur"]) {
    await page.click(`[data-lcbft=${p}]`);
    await page.setInputFiles("#fl-piece", "/tmp/cni.jpg");
    await Promise.all([page.waitForResponse((x) => x.url().includes("r=lcbft")), page.click("#fl .btn.primary")]);
    await page.waitForSelector(`[data-lcbft=${p}]`);
    await page.waitForTimeout(600);
  }
  v = await api(page, "visit", { query: { id } });
  verifier(v.lcbft.vendeur.gels.statut === "aucune" && v.lcbft.acquereur.niveau, `LCB-FT : vigilance ${v.lcbft.vendeur.niveau} / ${v.lcbft.acquereur.niveau}, registre des gels consulté`);
  await Promise.all([page.waitForResponse((x) => x.url().includes("envoyer_notaires")), page.click("#notaires")]);
  verifier((await mails()).filter((m) => /notaires\.fr/.test(m.a)).length >= 2, "dossier transmis aux deux notaires");
  await page.waitForTimeout(500);
  await capture("51-vente-compromis");
  const tn = Object.keys(JSON.parse(readFileSync(`${DONNEES}/data/liens.json`, "utf8"))).find((t) => JSON.parse(readFileSync(`${DONNEES}/data/liens.json`, "utf8"))[t].role === "notaire");
  await esp.goto(`http://127.0.0.1:8099/espace/?t=${tn}`);
  await esp.waitForSelector(".es-doc");
  await esp.screenshot({ path: new URL("./captures/52-espace-notaire.png", import.meta.url).pathname, fullPage: true });
  for (const doc of ["notaire", "vigilance&partie=vendeur"]) {
    const st = await esp.evaluate(async (u) => (await fetch(u)).headers.get("content-type"), `?t=${tn}&pdf=${doc}`);
    verifier(st === "application/pdf", `espace notaire : PDF ${doc}`);
  }

  // 4. Relance automatique du prêt à J-15
  const j15 = new Date(new Date(v.vente.pret_limite).getTime() - 14 * 86400e3).toISOString().slice(0, 10) + "T10:00";
  verifier(/relances_vente/.test(cron("relances_vente", j15)) && (await mails()).some((m) => m.a === "courtier@pretplus.fr"), "relance du prêt à J-15 (acquéreur et courtier)");

  // 5. Acte signé → facture, commission, avis Google
  v = await api(page, "acte", { method: "POST", query: { id }, body: { date: today } });
  verifier(v.etape === "vendu" && v.vente.facture.numero.startsWith("F-") && v.vente.commission.taux === 80, `acte : facture ${v.vente.facture.numero}, commission ${v.vente.commission.taux} % = ${v.vente.commission.montant} € HT`);
  for (const doc of ["facture", "commission"]) {
    const st = await page.evaluate(async (u) => (await fetch(u)).status, `api/?r=pdf&id=${id}&doc=${doc}`);
    verifier(st === 200, `PDF ${doc}`);
  }
  const j3 = new Date(Date.now() + 3 * 86400e3).toISOString().slice(0, 10) + "T11:00";
  verifier(/avis_google/.test(cron("avis_google", j3)), "demandes d'avis Google envoyées à J+3");
  const lienAvis = (await mails()).find((m) => /Votre avis compte/.test(m.sujet)).texte.match(/avis\.php\?t=[\w-]+/)[0];
  const redir = await page.evaluate(async (u) => (await fetch(u, { redirect: "manual" })).type, lienAvis);
  v = await api(page, "visit", { query: { id } });
  verifier(Object.values(v.avis).some((a) => a.clic), "clic sur le lien d'avis enregistré (pas de relance inutile)");
  await page.goto(`http://127.0.0.1:8099/#/visite/${id}/vente`);
  await page.reload();
  await page.waitForSelector(".prochaine.ok");
  await capture("53-vendu");
  verifier(!erreurs.length, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
} finally {
  await browser.close();
}
