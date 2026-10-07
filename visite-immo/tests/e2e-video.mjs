// Vidéo courte fabriquée dans le navigateur à partir des photos (canvas + MediaRecorder).
import { navigateur, connexion, verifier } from "./outils.mjs";
const { browser, page, erreurs, capture } = await navigateur();
try {
  await connexion(page);
  const ids = await page.evaluate(async () => (await (await fetch("api/?r=visits")).json()).map((v) => v.id));
  await page.goto(`http://127.0.0.1:8099/#/visite/${ids[0]}/social`);
  await page.waitForSelector("#video");
  await page.click("#video");
  await page.waitForSelector("video.video-apercu", { timeout: 60000 });
  const taille = await page.evaluate(async () => (await (await fetch(document.querySelector("video").src)).blob()).size);
  verifier(taille > 50000, `vidéo fabriquée (${Math.round(taille / 1024)} Ko)`);
  await capture("46-video");
  verifier(!erreurs.length, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
} finally {
  await browser.close();
}
