// Sécurité : en-têtes (CSP, nosniff, cadre, referrer), essais de mot de passe limités, liens clients cloisonnés,
// suppression de photo stricte, références piégées refusées, données hostiles affichées sans être interprétées.
import { execFileSync } from "node:child_process";
import { navigateur, connexion, api, verifier, configurerEmail, dossierAvecMandat, mails, BASE, DONNEES } from "./outils.mjs";

const { browser, ctx, page, erreurs, capture } = await navigateur();
const php = (code) => execFileSync("php", ["-r", `require "app/bootstrap.php"; ${code}`], { cwd: new URL("..", import.meta.url).pathname, env: { ...process.env, VI_DATA_DIR: DONNEES + "/data", VI_SETTINGS: DONNEES + "/settings.json" } }).toString();

try {
  // ---------- En-têtes ----------
  const r = await fetch(BASE);
  const csp = r.headers.get("content-security-policy") || "";
  verifier(csp.includes("script-src 'self'") && !csp.includes("unsafe-inline' https://cdnjs.cloudflare.com 'sha") && !/script-src[^;]*unsafe-inline/.test(csp), "appli : politique de contenu stricte (aucun script en ligne non signé)");
  verifier(r.headers.get("x-content-type-options") === "nosniff" && r.headers.get("x-frame-options") === "SAMEORIGIN" && r.headers.get("referrer-policy") === "same-origin", "nosniff, pas d'affichage dans le cadre d'un autre site, liens personnels non transmis aux sites externes");
  const api401 = await fetch(BASE + "api/?r=visits");
  verifier(api401.status === 401 && api401.headers.get("x-content-type-options") === "nosniff", "API : non connecté → 401, avec les en-têtes de sécurité");

  // ---------- Essais de mots de passe ----------
  await connexion(page);
  await configurerEmail(page);
  const essai = (login, password) => fetch(BASE + "api/?r=login", { method: "POST", headers: { "Content-Type": "application/json", "X-Requested-With": "visite-immo" }, body: JSON.stringify({ login, password }) }).then((x) => x.status);
  const codes = [];
  for (let i = 0; i < 6; i++) codes.push(await essai("fred", "mauvais" + i));
  verifier(codes.slice(0, 5).every((c) => c === 401) && codes[5] === 429, `5 mauvais mots de passe puis blocage (${codes.join(", ")})`);
  verifier((await essai("fred", "motdepasse")) === 429, "même le bon mot de passe attend la fin du blocage (15 min)");
  const journal = await api(page, "acces", { query: { q: "Connexion refusée" } });
  verifier(journal.length >= 5, "les tentatives refusées sont au journal des accès");
  php(`write_json(DATA_DIR . "/connexions.json", []);`); // fin du blocage pour la suite du test

  // ---------- Liens clients cloisonnés ----------
  const id = await dossierAvecMandat(page);
  await api(page, "signature_demande", { method: "POST", query: { id }, body: { doc: "mandat" } });
  const agentId = (await api(page, "status")).user.id;
  const lienAcq = php(`echo lien_creer(user_by_id("${agentId}"), "${id}", "acquereur");`).trim();
  const lienVend = php(`echo lien_creer(user_by_id("${agentId}"), "${id}", "vendeur");`).trim();
  const pdf = (t, doc) => fetch(`${BASE}espace/?t=${t}&pdf=${doc}`).then((x) => x.status);
  verifier((await pdf(lienVend, "mandat")) === 200, "vendeur : il télécharge son mandat");
  verifier((await pdf(lienAcq, "mandat")) === 403, "acquéreur : le mandat du vendeur (données personnelles) lui est refusé");
  verifier((await pdf(lienAcq, "fiche")) === 200, "acquéreur : la fiche du bien reste accessible");
  verifier(php(`echo json_encode([lien_autorise_signature(["doc" => "bon:a1", "signataire" => "s1"], "bon:a2", "s1"), lien_autorise_signature(["doc" => "bon:a1", "signataire" => "s1"], "bon:a1", "s1")]);`) === "[false,true]", "un lien de signature ne vaut que pour son document et son signataire");
  const esp = await fetch(`${BASE}espace/?t=${lienVend}`);
  verifier((esp.headers.get("content-security-policy") || "").includes("script-src 'self' 'sha256-"), "espace client : politique de contenu stricte");

  // ---------- Suppression de photo stricte ----------
  const photo = await page.evaluate(async (id) => {
    const c = Object.assign(document.createElement("canvas"), { width: 1200, height: 900 });
    const g = c.getContext("2d");
    for (let i = 0; i < 300; i++) { g.fillStyle = `hsl(${i * 37},60%,50%)`; g.fillRect(Math.random() * 1200, Math.random() * 900, 140, 90); }
    const blob = await new Promise((ok) => c.toBlob(ok, "image/jpeg", 0.9));
    const f = new FormData();
    f.append("photo", blob, "test.jpg");
    const r = await fetch(`api/?r=photos&id=${id}`, { method: "POST", headers: { "X-Requested-With": "visite-immo" }, body: f });
    return (await r.json()).photos[0].fichier;
  }, id);
  await api(page, "photos_ordre", { method: "POST", query: { id }, body: { supprimer: photo.slice(0, 2) } });
  const encore = await page.evaluate((u) => fetch(u).then((r) => r.status), `api/?r=photo&id=${id}&f=${photo}`);
  verifier(encore === 200, `suppression avec un début de nom (« ${photo.slice(0, 2)} ») : aucune photo effacée`);
  await api(page, "photos_ordre", { method: "POST", query: { id }, body: { supprimer: photo } });
  verifier((await page.evaluate((u) => fetch(u).then((r) => r.status), `api/?r=photo&id=${id}&f=${photo}`)) === 404, "suppression avec le nom exact : photo et variantes effacées");

  // ---------- Références et données piégées ----------
  const piege = `"><img src=x onerror="window.__pirate=1">`;
  const t = await api(page, "tache", { method: "POST", body: { titre: "Rappeler " + piege, dossier: piege, acquereur: piege } });
  verifier(!t.dossier && !t.acquereur, "tâche : références de dossier ou d'acquéreur invalides ignorées");
  const a = await api(page, "acquereur", { method: "POST", body: { nom: piege, id: piege, email: "x@y.fr" } });
  verifier(a.id && a.id !== piege, "acquéreur : l'identifiant ne peut pas être imposé par la requête");
  const rdv = await api(page, "rdv", { method: "POST", body: { id: "x\r\nATTENDEE:pirate", debut: "2026-10-09T10:00", titre: piege } });
  verifier(rdv.id && !rdv.id.includes("\n"), "rendez-vous : identifiant invalide remplacé (pas d'injection dans le calendrier)");
  await api(page, "visit", { method: "POST", query: { id }, body: { champs: { surface_habitable: piege, nb_pieces: piege }, source: "agent" } });
  for (const h of ["#/", "#/acquereurs", "#/agenda", `#/visite/${id}/fiche`, `#/visite/${id}/apercu`]) {
    await page.goto(BASE + h);
    await page.reload();
    await page.waitForTimeout(900);
  }
  const pirate = await page.evaluate(() => window.__pirate || [...document.querySelectorAll("iframe")].some((f) => f.contentWindow?.__pirate));
  verifier(!pirate, "données piégées (tâche, acquéreur, rendez-vous, fiche, rendu Leboncoin) affichées sans être exécutées");
  verifier(!(await page.$("img[src=x]")), "aucune balise injectée dans la page");
  await capture("securite-fin");
  console.log("\nSécurité : OK");
} catch (e) {
  console.error(e.message);
  await capture("securite-erreur").catch(() => {});
  process.exitCode = 1;
} finally {
  await browser.close();
}
