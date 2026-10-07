// Commercialisation : publication, page du bien, assistant 24 h/24 qui réserve une visite, formulaire,
// flux portails, visuels réseaux sociaux, alertes automatiques aux acquéreurs compatibles.
import { navigateur, connexion, verifier, api, configurerEmail, mails, dossierEnVente, DONNEES } from "./outils.mjs";
import { execSync } from "node:child_process";

const { browser, ctx, page, erreurs, capture } = await navigateur();
try {
  await connexion(page);
  await configurerEmail(page);
  const id = await dossierEnVente(page);
  execSync(`php -r '$i=imagecreatetruecolor(1600,1200);for($k=0;$k<300;$k++){imagefilledellipse($i,rand(0,1600),rand(0,1200),rand(40,400),rand(40,400),imagecolorallocate($i,rand(80,230),rand(120,220),rand(60,200)));} imagejpeg($i,"/tmp/facade.jpg",90);'`);
  await page.goto(`http://127.0.0.1:8099/#/visite/${id}/photos`);
  await page.waitForSelector("#ajout", { state: "attached" });
  await page.setInputFiles("#ajout", ["/tmp/facade.jpg"]);
  await page.waitForSelector(".photo-carte");

  // 1. Résumé : prochaine étape « Mettre le bien en vente », puis publication
  await page.goto(`http://127.0.0.1:8099/#/visite/${id}/resume`);
  await page.waitForSelector("[data-action=publier]");
  await capture("40-resume-publier");
  await page.click("[data-action=publier]");
  await page.waitForSelector(".badge.vert", { timeout: 15000 });
  await capture("41-diffusion");
  const d = await api(page, "diffusion", { query: { id } });
  verifier(d.publiee && d.url, "bien publié : " + d.url);

  // 2. Page publique du bien, vue par un acquéreur
  const pub = await ctx.newPage();
  pub.on("pageerror", (e) => erreurs.push("vitrine : " + e.message));
  const url = d.url.replace(/^https?:\/\/[^/]+/, "http://127.0.0.1:8099");
  await pub.goto(url);
  await pub.waitForSelector(".vt-titre");
  await pub.screenshot({ path: new URL("./captures/42-page-bien.png", import.meta.url).pathname, fullPage: true });
  verifier(await pub.$(".vt-mention"), "mentions de prix obligatoires affichées");

  // 3. Assistant : question, demande de visite, réservation
  await pub.click("#vt-chat-ouvrir");
  for (const q of ["Quel est le DPE ?", "Je voudrais visiter", "Je m'appelle Paul Durand, 06 11 22 33 44"]) {
    const n = await pub.$$eval(".vt-bulle.ia", (l) => l.length);
    await pub.fill("#vt-message", q);
    await pub.click("#vt-saisie button");
    await pub.waitForFunction((n) => { const l = document.querySelectorAll(".vt-bulle.ia"); return l.length > n && !l[l.length - 1].textContent.startsWith("…"); }, n);
  }
  await pub.screenshot({ path: new URL("./captures/43-assistant.png", import.meta.url).pathname });
  const v = await api(page, "visit", { query: { id } });
  verifier(v.contacts?.some((c) => c.rdv), "visite réservée par l'assistant : " + v.contacts.map((c) => c.nom).join(", "));
  const ag = await api(page, "agenda");
  verifier(ag.rdv.some((r) => r.source === "assistant"), "rendez-vous ajouté à l'agenda de l'agent");
  verifier((await mails()).some((m) => m.a === "fred@agence.fr" && /Visite réservée/.test(m.sujet)), "agent prévenu par e-mail");

  // 4. Formulaire de visite
  await pub.reload();
  await pub.click(".vt-visite .vt-creneau >> nth=1");
  await pub.fill("#vt-form [name=nom]", "Sophie Lambert");
  await pub.fill("#vt-form [name=email]", "sophie@exemple.fr");
  await pub.click("#vt-form button");
  await pub.waitForSelector("#vt-form .ok");
  verifier((await mails()).some((m) => m.a === "sophie@exemple.fr"), "confirmation de visite envoyée à l'acquéreur");

  // 5. Flux portails, visuels
  const flux = await page.evaluate(async (u) => (await fetch(u)).text(), d.flux.replace(/^https?:\/\/[^/]+/, ""));
  verifier(flux.includes("<annonce") && flux.includes("mention_prix"), "flux XML pour les portails");
  for (const f of ["carre", "story"]) {
    const r = await page.evaluate(async (u) => { const x = await fetch(u); return [x.status, x.headers.get("content-type"), (await x.arrayBuffer()).byteLength]; }, `api/?r=visuel&id=${id}&format=${f}`);
    verifier(r[0] === 200 && r[1] === "image/jpeg" && r[2] > 20000, `visuel ${f} (${Math.round(r[2] / 1024)} Ko)`);
  }
  await page.goto(`http://127.0.0.1:8099/#/visite/${id}/social`);
  await page.waitForSelector(".visuels img");
  await page.waitForTimeout(1200);
  await capture("44-visuels");
  await page.goto(`http://127.0.0.1:8099/api/?r=visuel&id=${id}&format=carre`);
  await page.screenshot({ path: new URL("./captures/45-visuel-carre.png", import.meta.url).pathname });

  // 6. Nouvel acquéreur compatible : alerte automatique par le cron
  await page.goto("http://127.0.0.1:8099/#/");
  await api(page, "acquereur", { method: "POST", body: { prenom: "Nina", nom: "Roy", email: "nina@exemple.fr", criteres: { type: "Maison", budget_max: 450000, villes: "Lougres" } } });
  const out = execSync(`VI_DATA_DIR=${DONNEES}/data VI_SETTINGS=${DONNEES}/settings.json php ../app/cron.php --tache=alertes_acquereurs`).toString();
  verifier(/alertes_acquereurs/.test(out) && (await mails()).some((m) => m.a === "nina@exemple.fr"), "acquéreur compatible prévenu automatiquement");
  verifier(!erreurs.length, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
} finally {
  await browser.close();
}
