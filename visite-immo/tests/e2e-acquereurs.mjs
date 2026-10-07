// Acquéreurs, agenda, visites (bon signé, retour dicté), rapprochement, point du vendredi au vendeur.
import { navigateur, connexion, verifier, api, configurerEmail, mails, dossierEnVente, signaturePng, DONNEES } from "./outils.mjs";
import { execSync } from "node:child_process";

const { browser, page, erreurs, capture } = await navigateur();
try {
  await connexion(page);
  await configurerEmail(page);
  const id = await dossierEnVente(page);
  verifier(true, "dossier en vente créé");

  // 1. Nouvel acquéreur dicté (mode démo : la fiche simulée est rangée automatiquement)
  await page.goto("http://127.0.0.1:8099/#/acquereurs");
  await page.waitForSelector("#dicter");
  await capture("30-acquereurs-vide");
  await page.click("#dicter");
  await page.waitForSelector("#d-rec");
  await capture("31-dictee");
  await page.click("#d-rec");
  await page.waitForTimeout(1500);
  await page.click("#d-rec");
  await page.waitForSelector(".score", { timeout: 20000 });
  await capture("32-fiche-acquereur");
  const acqId = page.url().split("/acquereur/")[1];
  const fa = await api(page, "acquereur", { query: { id: acqId } });
  verifier(fa.acquereur.criteres.budget_max === 430000 && fa.acquereur.qualification >= 80, `fiche dictée : ${fa.acquereur.prenom} ${fa.acquereur.nom}, qualifié ${fa.acquereur.qualification} %`);
  verifier(fa.biens.some((b) => b.id === id), `bien compatible trouvé (score ${fa.biens[0]?.score})`);

  // 2. Agenda : rendez-vous dicté, visite planifiée avec l'acquéreur
  await api(page, "dictee", { method: "POST", query: { type: "rdv" }, body: { texte: "jeudi estimation" } });
  const demain = new Date(Date.now() + 86400e3);
  demain.setHours(15, 0, 0, 0);
  await api(page, "rdv", { method: "POST", body: { type: "visite", titre: "Visite Moreau", debut: demain.toISOString(), dossier: id, acquereur: acqId } });
  await page.goto("http://127.0.0.1:8099/#/agenda");
  await page.waitForSelector(".rdv");
  await capture("33-agenda");
  const ag = await api(page, "agenda");
  verifier(ag.rdv.length === 2 && ag.creneaux.length > 0, `${ag.rdv.length} rendez-vous, ${ag.creneaux.length} créneaux libres proposés`);
  const ics = await page.evaluate(async (u) => (await fetch(u.replace(/^https?:\/\/[^/]+/, ""))).text(), ag.ics);
  verifier(ics.includes("BEGIN:VCALENDAR") && (ics.match(/BEGIN:VEVENT/g) || []).length === 2, "flux ICS d'abonnement calendrier");

  // 3. Onglet Vente : bon de visite signé sur place, retour dicté
  await page.goto(`http://127.0.0.1:8099/#/visite/${id}/vente`);
  await page.waitForSelector("[data-bon]");
  await capture("34-onglet-vente");
  await page.click("[data-bon]");
  await page.waitForSelector("[data-signer]");
  const png = await signaturePng();
  let v = await api(page, "visit", { query: { id } });
  const cleBon = Object.keys(v.signatures).find((k) => k.startsWith("bon:"));
  for (const s of v.signatures[cleBon].signataires) v = await api(page, "signer", { method: "POST", query: { id }, body: { doc: cleBon, signataire: s.id, image: png } });
  verifier(v.visites_acq[0].bon_signe, "bon de visite signé par l'acquéreur et l'agent");
  const bon = await page.evaluate(async (u) => (await fetch(u)).status, `api/?r=pdf&id=${id}&doc=bon&cle=${encodeURIComponent(cleBon)}`);
  verifier(bon === 200, "PDF du bon de visite");
  await page.goto(`http://127.0.0.1:8099/#/visite/${id}/vente`);
  await page.waitForSelector("[data-retour]");
  await page.click("[data-retour]");
  await page.click("summary");
  await page.fill("#d-texte", "Ils ont adoré le jardin, la cuisine les freine.");
  await page.click("#d-envoyer-texte");
  await page.waitForSelector(".visite-acq .small strong");
  await capture("35-retour-dicte");
  v = await api(page, "visit", { query: { id } });
  verifier(v.visites_acq[0].retour?.interet === 4, "retour de visite enregistré (intérêt 4/5)");

  // 4. Rapprochement : proposer le bien par e-mail
  const prop = await api(page, "proposer", { method: "POST", query: { id }, body: { acquereurs: [acqId] } });
  verifier(prop.envoyes === 1 && (await mails()).some((m) => m.a === "julien.moreau@exemple.fr"), "bien proposé à l'acquéreur par e-mail");

  // 5. Vendredi 18 h : le point de la semaine part tout seul chez le vendeur
  const vendredi = new Date();
  vendredi.setDate(vendredi.getDate() + ((5 - vendredi.getDay() + 7) % 7 || 7));
  vendredi.setHours(18, 0, 0, 0);
  const out = execSync(`VI_DATA_DIR=${DONNEES}/data VI_SETTINGS=${DONNEES}/settings.json php ../app/cron.php --tache=cr_hebdo --maintenant=${vendredi.toISOString().slice(0, 16)}`).toString();
  verifier(/cr_hebdo/.test(out), "cron du vendredi : " + out.trim());
  const m = await mails();
  verifier(m.some((x) => x.a === "claire.martin@exemple.fr" && /point de la semaine/i.test(x.sujet)), "point de la semaine envoyé au vendeur");

  // 6. Espace vendeur : les visites et le point de la semaine
  const liens = JSON.parse((await import("node:fs")).readFileSync(`${DONNEES}/data/liens.json`, "utf8"));
  const token = Object.keys(liens).find((t) => liens[t].dossier === id && liens[t].role === "vendeur");
  await page.goto(`http://127.0.0.1:8099/espace/?t=${token}`);
  await page.waitForSelector(".es-ligne");
  await page.screenshot({ path: new URL("./captures/36-espace-visites.png", import.meta.url).pathname, fullPage: true });
  verifier(true, "espace vendeur : visites et retours visibles");

  // 7. Aujourd'hui : le nouveau contact apparaît
  await page.goto("http://127.0.0.1:8099/#/");
  await page.waitForSelector(".ajd-item");
  await capture("37-aujourdhui");
  verifier(!erreurs.length, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
} finally {
  await browser.close();
}
