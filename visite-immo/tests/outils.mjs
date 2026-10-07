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
        return { status: r.status, ...JSON.parse(t) };
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
