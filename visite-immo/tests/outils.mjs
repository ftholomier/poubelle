// Outils communs aux tests de bout en bout (Playwright, Chromium avec micro simulé).
import { chromium } from "playwright";
import { mkdirSync } from "node:fs";

export const BASE = "http://127.0.0.1:8099/";
export const CAPTURES = new URL("./captures/", import.meta.url).pathname;
mkdirSync(CAPTURES, { recursive: true });

export async function navigateur({ audio } = {}) {
  const args = ["--use-fake-device-for-media-stream", "--use-fake-ui-for-media-stream"];
  if (audio) args.push(`--use-file-for-fake-audio-capture=${audio}`);
  const browser = await chromium.launch({ executablePath: process.env.CHROMIUM || "/opt/pw-browsers/chromium", args }).catch(() => chromium.launch({ args }));
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, permissions: ["microphone", "camera"], locale: "fr-FR" });
  const page = await ctx.newPage();
  const erreurs = [];
  page.on("pageerror", (e) => erreurs.push(e.message));
  page.on("console", (m) => m.type() === "error" && !/Failed to load resource/.test(m.text()) && erreurs.push(m.text()));
  page.on("dialog", (d) => d.accept());
  const capture = (nom) => page.screenshot({ path: `${CAPTURES}${nom}.png`, fullPage: false });
  return { browser, ctx, page, erreurs, capture };
}

/** Crée le compte administrateur (premier lancement) ou se connecte. */
export async function connexion(page, { nom = "Frédéric Dupont", login = "fred", password = "motdepasse" } = {}) {
  await page.goto(BASE);
  await page.waitForSelector("form");
  if (await page.$("[name=nom]")) await page.fill("[name=nom]", nom);
  await page.fill("[name=login]", login);
  await page.fill("[name=password]", password);
  await page.click("form button");
  await page.waitForSelector(".tabbar");
}

/** Appel direct à l'API depuis la page (même session). */
export async function api(page, route, { method = "GET", query = {}, body } = {}) {
  return page.evaluate(
    async ({ route, method, query, body }) => {
      const r = await fetch(`api/?${new URLSearchParams({ r: route, ...query })}`, {
        method,
        headers: { "X-Requested-With": "visite-immo", "Content-Type": "application/json" },
        body: body ? JSON.stringify(body) : undefined,
      });
      const t = await r.text();
      try {
        const d = JSON.parse(t);
        return Array.isArray(d) ? d : { status: r.status, ...d };
      } catch {
        return { status: r.status, texte: t.slice(0, 300) };
      }
    },
    { route, method, query, body },
  );
}

export function verifier(cond, message) {
  if (!cond) throw new Error("ÉCHEC : " + message);
  console.log("✓ " + message);
}

export const DONNEES = (process.env.TMPDIR || "/tmp") + "/visite-immo-test";

/** Configure l'envoi d'e-mails vers le serveur SMTP simulé et les coordonnées de l'agent. */
export async function configurerEmail(page) {
  await api(page, "settings", { method: "POST", body: { email_methode: "smtp", email_expediteur: "agence@test.fr", email_expediteur_nom: "Synapse Test", smtp_host: "127.0.0.1", smtp_port: 2525, smtp_securite: "aucune", smtp_user: "agence@test.fr", smtp_pass: "secret" } });
  await api(page, "profile", { method: "POST", body: { nom: "Frédéric Dupont", email: "fred@agence.fr", telephone: "06 11 22 33 44" } });
}

/** Contenu des e-mails reçus par le SMTP simulé (texte décodé). */
export async function mails() {
  const { readdirSync, readFileSync } = await import("node:fs");
  const dir = DONNEES + "/mails";
  return readdirSync(dir)
    .filter((f) => f.endsWith(".eml"))
    .sort()
    .map((f) => {
      const brut = readFileSync(`${dir}/${f}`, "utf8");
      const parties = [...brut.matchAll(/Content-Transfer-Encoding: base64\r?\n(?:[^\r\n]+\r?\n)*\r?\n([A-Za-z0-9+/=\r\n]+)/g)].map((m) => Buffer.from(m[1].replace(/\s/g, ""), "base64").toString("utf8"));
      const sujet = (brut.match(/^Subject: (.*)$/m) || [])[1] || "";
      const dec = sujet.replace(/=\?UTF-8\?B\?([^?]+)\?=/g, (_, b) => Buffer.from(b, "base64").toString("utf8"));
      return { fichier: f, brut, sujet: dec, a: (brut.match(/^To: (.*)$/m) || [])[1], texte: parties.join("\n") };
    });
}

/** Signature dessinée (PNG) pour les tests d'API. */
export async function signaturePng() {
  const { execSync } = await import("node:child_process");
  execSync(`php -r '$i=imagecreatetruecolor(400,120);imagesavealpha($i,true);imagefill($i,0,0,imagecolorallocatealpha($i,0,0,0,127));$n=imagecolorallocate($i,17,17,20);imagesetthickness($i,3);for($x=10;$x<390;$x+=4){imageline($i,$x,60+(int)(40*sin($x/20)),$x+4,60+(int)(40*sin(($x+4)/20)),$n);}imagepng($i,"/tmp/sig-test.png");'`);
  const { readFileSync } = await import("node:fs");
  return "data:image/png;base64," + readFileSync("/tmp/sig-test.png").toString("base64");
}

export const CHAMPS_MANDAT = { civilite_vendeur: "Madame", prenom_vendeur: "Claire", nom_vendeur: "Martin", naissance_date_vendeur: "12/04/1961", naissance_lieu_vendeur: "Besançon",
  telephone_vendeur: "06 12 34 56 78", email_vendeur: "claire.martin@exemple.fr", situation_vendeur: "Veuf / veuve", adresse: "11 rue du Chênois", ville: "25260 Lougres", nb_pieces: "6",
  origine_propriete: "Acquisition en 2005", copropriete: "non", occupation: "Occupé par le propriétaire", mandat_type: "Exclusif", mandat_lieu: "En agence",
  mandat_prix: "412000", mandat_honoraires: "12 000 €", mandat_honoraires_charge: "L'acquéreur", mandat_duree: "3", mandat_date: "07/10/2026" };

/** Crée par l'API un dossier complet (visite démo, documents, informations du mandat), mandat pas encore signé. */
export async function dossierAvecMandat(page, titre = "11 rue du Chênois, Lougres") {
  const v = await api(page, "visits", { method: "POST", body: { titre, consentement: true } });
  const id = v.id;
  await api(page, "visit", { method: "POST", query: { id }, body: { champs: { type_bien: "Maison", surface_habitable: "125" }, source: "agent" } });
  await api(page, "generate", { method: "POST", query: { id } });
  await api(page, "preparer", { method: "POST", query: { id } });
  await api(page, "visit", { method: "POST", query: { id }, body: { champs: CHAMPS_MANDAT, source: "dialogue" } });
  await api(page, "settings", { method: "POST", body: { raison_sociale: "Synapse SAS", siege: "1 rue de la Paix, 25000 Besançon", siret: "123 456 789 00012", carte_numero: "CPI 2501 2026 000 001", carte_delivree_par: "CCI du Doubs", garant: "Galian", rcp: "MMA IARD" } });
  return id;
}

/** Crée par l'API un dossier complet (visite démo, documents, mandat signé) : le bien est « en vente ». */
export async function dossierEnVente(page, titre = "11 rue du Chênois, Lougres") {
  const id = await dossierAvecMandat(page, titre);
  let d = await api(page, "signature_demande", { method: "POST", query: { id }, body: { doc: "mandat" } });
  const png = await signaturePng();
  for (const s of d.signatures.mandat.signataires) d = await api(page, "signer", { method: "POST", query: { id }, body: { doc: "mandat", signataire: s.id, image: png } });
  if (d.etape !== "en_vente") throw new Error("dossier pas en vente : " + JSON.stringify(d).slice(0, 300));
  return id;
}
