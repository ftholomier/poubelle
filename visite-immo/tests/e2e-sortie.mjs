// Sortie de visite : tout est préparé automatiquement, puis envoyé au vendeur en un geste ;
// le vendeur dépose une pièce et signe le mandat depuis son espace ; l'agent signe sur place.
import { navigateur, connexion, verifier, api, configurerEmail, mails, DONNEES } from "./outils.mjs";
import { readFileSync, writeFileSync } from "node:fs";
import { execSync } from "node:child_process";

const { browser, ctx, page, erreurs, capture } = await navigateur();
try {
  await connexion(page);
  await configurerEmail(page);
  await page.reload();
  await page.waitForSelector(".tabbar");

  // 1. Visite enregistrée puis création automatique de tout le dossier
  await page.click(".tb-rec");
  await page.fill("#titre", "11 rue du Chênois, Lougres");
  await page.check("#consent");
  await page.click("#rec");
  await page.waitForSelector(".rec-btn.recording");
  await page.waitForTimeout(2000);
  await page.click("#stop");
  await page.click("#gen");
  await page.waitForSelector(".stepper", { timeout: 90000 });
  const id = page.url().split("/visite/")[1].split("/")[0];
  let v = await api(page, "visit", { query: { id } });
  verifier(v.public && v.public.sources.includes("Cadastre (IGN)"), "dossier technique récupéré : " + v.public.sources.join(", "));
  verifier(v.avis_valeur && v.avis_valeur.retenu > 100000, `avis de valeur : ${v.avis_valeur.retenu} € sur ${v.avis_valeur.comparables.length} ventes`);
  verifier(v.fiche.champs.cadastre?.source === "public", "référence cadastrale reportée dans la fiche (source publique)");
  verifier(v.plan.pieces.length > 5 && v.posts.instagram, "plan et publications générés");
  verifier(v.pieces.length >= 4, `${v.pieces.length} pièces à demander`);
  await capture("10-resume-complet");

  // 2. Écrans des nouveaux documents
  for (const [ecran, sel] of [["avis", ".tableau"], ["plan", ".plan-svg"], ["technique", ".card"], ["social", ".texte"], ["pieces", ".piece"]]) {
    await page.goto(`http://127.0.0.1:8099/#/visite/${id}/${ecran}`);
    await page.waitForSelector(sel);
    await page.waitForTimeout(300);
    await capture(`11-${ecran}`);
  }
  for (const doc of ["avis", "plan", "technique", "dossier"]) {
    const r = await page.evaluate(async (u) => { const x = await fetch(u); return [x.status, x.headers.get("content-type"), (await x.arrayBuffer()).byteLength]; }, `api/?r=pdf&id=${id}&doc=${doc}`);
    verifier(r[0] === 200 && r[1] === "application/pdf" && r[2] > 5000, `PDF ${doc} (${r[2]} octets)`);
  }

  // 3. On complète les informations du mandat (comme à la voix), puis « Tout envoyer au vendeur »
  const champs = { civilite_vendeur: "Madame", prenom_vendeur: "Claire", nom_vendeur: "Martin", naissance_date_vendeur: "12/04/1961", naissance_lieu_vendeur: "Besançon",
    telephone_vendeur: "06 12 34 56 78", email_vendeur: "claire.martin@exemple.fr", situation_vendeur: "Veuf / veuve", adresse: "11 rue du Chênois", ville: "25260 Lougres", nb_pieces: "6",
    origine_propriete: "Acquisition en 2005", copropriete: "non", occupation: "Occupé par le propriétaire", mandat_type: "Exclusif", mandat_lieu: "Au domicile du vendeur",
    mandat_prix: "412000", mandat_honoraires: "12 000 €", mandat_honoraires_charge: "L'acquéreur", mandat_duree: "3", mandat_date: "07/10/2026" };
  await api(page, "visit", { method: "POST", query: { id }, body: { champs, source: "dialogue" } });
  await api(page, "settings", { method: "POST", body: { raison_sociale: "Synapse SAS", siege: "1 rue de la Paix, 25000 Besançon", siret: "123 456 789 00012", carte_numero: "CPI 2501 2026 000 001", carte_delivree_par: "CCI du Doubs", garant: "Galian, 89 rue La Boétie, Paris", rcp: "MMA IARD" } });
  await page.goto(`http://127.0.0.1:8099/#/visite/${id}/resume`);
  await page.waitForSelector(".prochaine");
  await capture("12-resume-pret");
  await page.click("[data-action=envoyer_vendeur]");
  await page.waitForSelector("#ev");
  await capture("13-envoi-vendeur");
  await page.click("#ev .btn.magic");
  await page.waitForSelector(".toast.ok");
  await page.waitForTimeout(800);
  let m = await mails();
  const mailVendeur = m.find((x) => x.a === "claire.martin@exemple.fr");
  verifier(mailVendeur, "e-mail parti chez le vendeur : " + mailVendeur?.sujet);
  const lien = mailVendeur.texte.match(/https?:\/\/\S+espace\/\?t=[\w-]+/)?.[0];
  verifier(lien, "lien vers l'espace vendeur dans l'e-mail");
  verifier(/Avis-de-valeur/.test(mailVendeur.brut), "avis de valeur en pièce jointe");
  v = await api(page, "visit", { query: { id } });
  verifier(v.mandat?.numero && v.signatures?.mandat?.statut === "en_attente", `mandat n° ${v.mandat.numero} inscrit au registre et en attente de signature`);
  verifier(v.etape === "signature", "étape du dossier : signature");

  // 4. Espace vendeur : dépôt d'une pièce et signature avec code reçu par e-mail
  const vendeur = await ctx.newPage();
  vendeur.on("pageerror", (e) => erreurs.push("espace : " + e.message));
  await vendeur.goto(lien.replace(/^https?:\/\/[^/]+/, "http://127.0.0.1:8099"));
  await vendeur.waitForSelector(".es-signer");
  await vendeur.screenshot({ path: new URL("./captures/14-espace-vendeur.png", import.meta.url).pathname, fullPage: true });
  writeFileSync("/tmp/titre-propriete.pdf", "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
  const [chooser] = await Promise.all([vendeur.waitForEvent("filechooser"), vendeur.click("label.es-btn >> nth=1")]);
  await chooser.setFiles("/tmp/titre-propriete.pdf");
  await vendeur.waitForLoadState("load");
  await vendeur.waitForTimeout(1500);
  v = await api(page, "visit", { query: { id } });
  verifier(v.fiche.champs.origine_propriete?.source !== "document" || /Lefèvre/.test(v.fiche.champs.origine_propriete.valeur), "pièce lue (titre de propriété)");
  verifier(v.pieces.some((p) => p.statut === "recue"), "pièce marquée reçue");

  await vendeur.check(".es-lu");
  await vendeur.click(".es-go");
  await vendeur.waitForSelector(".es-pad-zone:not([hidden])");
  await vendeur.waitForTimeout(800);
  m = await mails();
  const code = m.reverse().find((x) => /code de signature/i.test(x.sujet))?.sujet.match(/\d{6}/)?.[0];
  verifier(code, "code de signature reçu par e-mail : " + code);
  await vendeur.fill(".es-code-in", code);
  const box = await vendeur.$eval(".es-pad", (c) => { const r = c.getBoundingClientRect(); return [r.x, r.y, r.width, r.height]; });
  await vendeur.mouse.move(box[0] + 30, box[1] + 120);
  await vendeur.mouse.down();
  for (let i = 0; i < 30; i++) await vendeur.mouse.move(box[0] + 30 + i * 8, box[1] + 100 + Math.sin(i / 3) * 40);
  await vendeur.mouse.up();
  await vendeur.screenshot({ path: new URL("./captures/15-espace-signature.png", import.meta.url).pathname });
  await vendeur.click(".es-go");
  await vendeur.waitForSelector("text=c'est signé");
  verifier(true, "le vendeur a signé depuis son espace");

  // 5. L'agent signe sur son téléphone : le mandat est complet, le bien passe en vente
  await page.goto(`http://127.0.0.1:8099/#/visite/${id}/signature/mandat`);
  await page.waitForSelector("[data-signer]");
  await page.click("[data-signer]");
  await page.check("#lu");
  const b2 = await page.$eval("canvas.pad", (c) => { const r = c.getBoundingClientRect(); return [r.x, r.y]; });
  await page.mouse.move(b2[0] + 20, b2[1] + 100);
  await page.mouse.down();
  for (let i = 0; i < 25; i++) await page.mouse.move(b2[0] + 20 + i * 9, b2[1] + 80 + Math.cos(i / 2) * 30);
  await page.mouse.up();
  await capture("16-signature-agent");
  await page.click("#valider");
  await page.waitForSelector(".tag-citron");
  await capture("17-mandat-signe");
  v = await api(page, "visit", { query: { id } });
  verifier(v.signatures.mandat.statut === "signe" && v.etape === "en_vente", "mandat signé par tous, dossier « en vente »");
  m = await mails();
  verifier(m.filter((x) => /^Signé/.test(x.sujet)).length >= 2, "exemplaire signé envoyé au vendeur et à l'agent");
  const pdf = await page.evaluate(async (u) => new Uint8Array(await (await fetch(u)).arrayBuffer()).length, `api/?r=pdf&id=${id}&doc=mandat`);
  verifier(pdf > 30000, `PDF du mandat signé (${pdf} octets)`);
  writeFileSync(new URL("./captures/mandat-signe.pdf", import.meta.url).pathname, Buffer.from(await page.evaluate(async (u) => [...new Uint8Array(await (await fetch(u)).arrayBuffer())], `api/?r=pdf&id=${id}&doc=mandat`)));

  // 6. Tâches automatiques : relance des pièces 3 jours plus tard
  const demain = new Date(Date.now() + 3 * 86400e3);
  demain.setHours(10);
  const sortie = execSync(`VI_DATA_DIR=${DONNEES}/data VI_SETTINGS=${DONNEES}/settings.json php ../app/cron.php --maintenant=${demain.toISOString().slice(0, 16)}`).toString();
  verifier(/relances_pieces/.test(sortie), "cron : " + sortie.trim());

  verifier(!erreurs.length, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
} finally {
  await browser.close();
}
