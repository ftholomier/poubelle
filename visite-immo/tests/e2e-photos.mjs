// Photos : ajout, retouche, pièce reconnue, détection du flou, home staging avec mention, réordonnancement.
import { navigateur, connexion, verifier, api } from "./outils.mjs";
import { execSync } from "node:child_process";

// Deux photos de test générées avec GD : une nette (motifs), une floue
execSync(`php -r '$i=imagecreatetruecolor(1600,1200);for($k=0;$k<400;$k++){imagefilledrectangle($i,rand(0,1600),rand(0,1200),rand(0,1600),rand(0,1200),imagecolorallocate($i,rand(40,200),rand(40,200),rand(40,200)));} imagejpeg($i,"/tmp/nette.jpg",90); for($k=0;$k<30;$k++) imagefilter($i,IMG_FILTER_GAUSSIAN_BLUR); imagejpeg($i,"/tmp/floue.jpg",90);'`);

const { browser, page, erreurs, capture } = await navigateur();
try {
  await connexion(page);
  const v0 = await api(page, "visits", { method: "POST", body: { titre: "Test photos", consentement: true } });
  await page.goto(`http://127.0.0.1:8099/#/visite/${v0.id}/photos`);
  await page.waitForSelector("#ajout", { state: "attached" });
  await page.setInputFiles("#ajout", ["/tmp/nette.jpg", "/tmp/floue.jpg"]);
  await page.waitForSelector(".photo-carte:nth-child(2)", { timeout: 30000 });
  let v = await api(page, "visit", { query: { id: v0.id } });
  verifier(v.photos.length === 2, "2 photos ajoutées et retouchées");
  verifier(v.photos.at(-1).floue && !v.photos[0].floue, `photo floue détectée (netteté ${v.photos.map((p) => p.nettete).join(" / ")}) et placée à la fin`);
  await page.click("[data-staging]");
  await page.click("[data-style=scandinave]");
  await page.waitForSelector(".badge.vert", { timeout: 30000 });
  v = await api(page, "visit", { query: { id: v0.id } });
  verifier(v.photos[0].staging?.fichier, "home staging créé (avec mention « aménagement virtuel »)");
  await capture("20-photos");
  await page.click("[data-zoom]");
  await page.waitForTimeout(500);
  await capture("21-photo-staging");
  verifier(!erreurs.length, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
} finally {
  await browser.close();
}
