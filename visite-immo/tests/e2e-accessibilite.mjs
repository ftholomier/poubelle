// Accessibilité (WCAG 2.1 AA, moteur axe-core) sur les écrans principaux, en clair et en sombre, plus les réglages
// « plein soleil » (contraste renforcé, grandes cibles). Prérequis : axe-core dans tests/node_modules
// (npm i --prefix /tmp/axe axe-core && ln -s /tmp/axe/node_modules/axe-core tests/node_modules/axe-core).
import { createRequire } from "node:module";
import { readFileSync } from "node:fs";
import { navigateur, connexion, api, verifier, configurerEmail, dossierAvecMandat, mails, BASE } from "./outils.mjs";

let axe;
try {
  axe = readFileSync(createRequire(import.meta.url).resolve("axe-core/axe.min.js"), "utf8");
} catch {
  console.log("axe-core absent : voir l'en-tête du fichier. Test ignoré.");
  process.exit(0);
}

const SEUIL = ["critical", "serious"]; // ce qui bloque vraiment un utilisateur
const { browser, ctx, page, erreurs, capture } = await navigateur();
const rapport = [];

async function auditer(p, nom, { attendre } = {}) {
  if (attendre) await p.waitForSelector(attendre, { timeout: 30000 });
  await p.waitForTimeout(400);
  await p.evaluate(axe); // (évalué par l'outil de test : la politique de sécurité de la page bloque à juste titre tout script injecté)
  const r = await p.evaluate(async () => {
    const res = await window.axe.run(document, { runOnly: { type: "tag", values: ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa"] }, resultTypes: ["violations"] });
    return res.violations.map((v) => ({ id: v.id, impact: v.impact, aide: v.help, n: v.nodes.length, exemples: v.nodes.slice(0, 3).map((x) => x.target.join(" ") + " → " + (x.failureSummary || "").split("\n").slice(1, 2).join("")) }));
  });
  const graves = r.filter((v) => SEUIL.includes(v.impact));
  rapport.push({ nom, graves, autres: r.filter((v) => !SEUIL.includes(v.impact)) });
  if (process.env.DETAIL) for (const v of r) console.log(`   [${nom}] ${v.impact} ${v.id} ×${v.n} : ${v.aide}\n      ${v.exemples.join("\n      ")}`);
  return graves;
}

try {
  await page.goto(BASE);
  await auditer(page, "connexion (création du compte)", { attendre: "form" });
  await connexion(page);
  await configurerEmail(page);
  await api(page, "demo", { method: "POST" });
  const biens = await api(page, "visits");
  const enVente = biens.find((b) => b.etape === "en_vente") || biens[0];

  const ecrans = [
    ["aujourd'hui", "#/", ".home-hero, .ajd-item"],
    ["biens", "#/biens", ".visite"],
    ["dossier · résumé", `#/visite/${enVente.id}/resume`, ".prochaine"],
    ["dossier · suivi", `#/visite/${enVente.id}/suivi`, ".suivi-tete"],
    ["dossier · fiche", `#/visite/${enVente.id}/fiche`, ".field"],
    ["dossier · documents", `#/visite/${enVente.id}/documents`, ".doc-ligne"],
    ["dossier · vente", `#/visite/${enVente.id}/vente`, ".card"],
    ["rendu Leboncoin", `#/visite/${enVente.id}/apercu`, ".controle"],
    ["estimer un bien", "#/estimation", "#adr"],
    ["acquéreurs", "#/acquereurs", ".card"],
    ["agenda", "#/agenda", ".card"],
    ["prospection", "#/prospection", ".card"],
    ["tableau de bord", "#/tableau", ".card"],
    ["paramètres", "#/reglages", "form"],
    ["nouvelle visite", "#/nouvelle", "#rec"],
  ];
  for (const sombre of [false, true]) {
    await page.emulateMedia({ colorScheme: sombre ? "dark" : "light" });
    for (const [nom, hash, sel] of ecrans) {
      await page.goto(BASE + hash);
      await page.reload();
      await auditer(page, `${nom}${sombre ? " (sombre)" : ""}`, { attendre: sel });
    }
  }
  await page.emulateMedia({ colorScheme: "light" });

  // Confort terrain : « plein soleil » + grands boutons, réglés depuis Mon compte
  await page.goto(BASE + "#/compte");
  await page.waitForSelector("[data-confort=soleil]");
  await page.check("[data-confort=soleil]");
  await page.check("[data-confort=gants]");
  verifier(await page.evaluate(() => document.documentElement.classList.contains("confort-soleil") && document.documentElement.classList.contains("confort-gants")), "Mon compte : plein soleil et grands boutons activés");
  for (const [nom, hash, sel] of [ecrans[3], ecrans[14], ecrans[2]]) {
    await page.goto(BASE + hash);
    await page.reload();
    await auditer(page, `${nom} (plein soleil)`, { attendre: sel });
  }
  const tailles = await page.evaluate(() => [...document.querySelectorAll(".btn, .icon-btn")].filter((b) => b.offsetParent).map((b) => b.getBoundingClientRect().height));
  verifier(Math.min(...tailles) >= 52, `grands boutons : ${tailles.length} boutons, le plus petit fait ${Math.round(Math.min(...tailles))} px`);
  await capture("a11y-plein-soleil");
  await page.evaluate(() => localStorage.removeItem("vi-confort"));

  // Pages publiques : espace vendeur et page du bien
  const idv = await dossierAvecMandat(page, "4 rue des Lilas, Lougres");
  await api(page, "envoi_vendeur", { method: "POST", query: { id: idv }, body: await api(page, "envoi_vendeur", { query: { id: idv } }) });
  const espaceLien = (await mails()).map((m) => m.texte.match(/https?:\/\/\S+espace\/\?t=[\w-]+/)?.[0]).find(Boolean);
  if (espaceLien) {
    const p2 = await ctx.newPage();
    await p2.goto(espaceLien);
    await auditer(p2, "espace vendeur", { attendre: ".es-card" });
    await p2.close();
  }
  const d = await api(page, "dossier", { query: { id: enVente.id } });
  if (d.vitrine?.slug) {
    const p3 = await ctx.newPage();
    await p3.goto(`${BASE}v/?b=${d.vitrine.slug}`);
    await auditer(p3, "page publique du bien", { attendre: "body" });
    await p3.close();
  }

  for (const r of rapport) {
    const g = r.graves.map((v) => `${v.id} ×${v.n}`).join(", ");
    console.log(`${r.graves.length ? "✗" : "✓"} ${r.nom}${g ? " : " + g : ""}${r.autres.length ? ` (mineur : ${r.autres.map((v) => v.id).join(", ")})` : ""}`);
  }
  const total = rapport.reduce((n, r) => n + r.graves.length, 0);
  verifier(total === 0, `aucun problème d'accessibilité grave sur ${rapport.length} écrans (WCAG 2.1 AA)`);
  verifier(erreurs.length === 0, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
  console.log("\nAccessibilité : OK");
} catch (e) {
  console.error(e.message);
  process.exitCode = 1;
} finally {
  await browser.close();
}
