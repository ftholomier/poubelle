// Acquéreur qui cherche dans une commune précise : seuls les biens de cette commune (ou à moins de 5 km) lui sont
// proposés ; accents, tirets et code postal sans importance.
import { execFileSync } from "node:child_process";
import { navigateur, connexion, api, verifier, configurerEmail, dossierAvecMandat, BASE, DONNEES } from "./outils.mjs";

const { browser, page, erreurs, capture } = await navigateur();
const php = (code) => execFileSync("php", ["-r", `require "app/bootstrap.php"; ${code}`], { cwd: new URL("..", import.meta.url).pathname, env: { ...process.env, VI_DATA_DIR: DONNEES + "/data", VI_SETTINGS: DONNEES + "/settings.json", VI_API_ADRESSE: "http://127.0.0.1:8098/adresse" } }).toString();

try {
  await connexion(page);
  await configurerEmail(page);
  const lougres = [];
  for (const rue of ["11 rue du Chênois", "4 rue des Lilas", "8 rue Pasteur"]) lougres.push(await dossierAvecMandat(page, `${rue}, Lougres`));
  const chatillon = await dossierAvecMandat(page, "3 rue des Vergers, Châtillon-le-Duc");
  await api(page, "visit", { method: "POST", query: { id: chatillon }, body: { champs: { ville: "25870 Châtillon-le-Duc", adresse: "3 rue des Vergers" }, source: "agent" } });

  // L'acquéreur, comme dicté : « chatillon le duc », sans accent ni tiret
  const a = await api(page, "acquereur", { method: "POST", body: { prenom: "Paul", nom: "Durand", email: "paul@exemple.fr", criteres: { type: "Maison", budget_max: 500000, villes: "chatillon le duc" } } });
  const fiche = await api(page, "acquereur", { query: { id: a.id } });
  const ids = fiche.biens.map((b) => b.id);
  verifier(ids.length === 1 && ids[0] === chatillon, `biens compatibles : seulement Châtillon-le-Duc (${fiche.biens.map((b) => b.titre).join(", ")})`);
  verifier(fiche.biens[0].raisons.includes("secteur recherché"), "raison affichée : secteur recherché");

  await page.goto(`${BASE}#/acquereur/${a.id}`);
  await page.waitForSelector("text=Biens compatibles");
  await page.waitForTimeout(500);
  await capture("secteur-01-acquereur");
  verifier(!(await page.locator("text=Lougres").count()), "aucun bien de Lougres à l'écran");

  // Dans l'autre sens : le bien de Lougres ne lui est pas proposé
  const r = await api(page, "rapprochements", { query: { id: lougres[0] } });
  verifier(!r.some((x) => x.id === a.id), "bien de Lougres : Paul n'est pas dans les acquéreurs compatibles");

  // Communes toutes proches (moins de 5 km) : acceptées avec la distance
  const res = JSON.parse(php(`echo json_encode([
    secteur_compatible("Châtillon-le-Duc", ["public" => ["geo" => ["city" => "École-Valentin", "citycode" => "25212", "lat" => 47.278, "lon" => 5.978]], "fiche" => ["champs" => ["ville" => ["valeur" => "25480 École-Valentin"]]]]),
    secteur_compatible("St-Vit", ["fiche" => ["champs" => ["ville" => ["valeur" => "25410 Saint-Vit"]]]]),
  ], JSON_UNESCAPED_UNICODE);`));
  verifier(res[0][0] === "proche" && /km de Châtillon-le-Duc/.test(res[0][1]), `commune voisine acceptée : « ${res[0][1]} »`);
  // Classement : la commune demandée à 100 %, la voisine juste en dessous, Lougres (≈ 35 km) écartée
  const score = JSON.parse(php(`
    $a = ["criteres" => ["villes" => "Châtillon-le-Duc"]];
    $bien = fn ($ville, $geo) => ["titre" => $ville, "public" => ["geo" => $geo], "fiche" => ["champs" => ["ville" => ["valeur" => $ville]]]];
    echo json_encode([
      rapprochement($a, $bien("25870 Châtillon-le-Duc", ["city" => "Châtillon-le-Duc", "citycode" => "25132", "lat" => 47.307, "lon" => 5.995]))["score"],
      rapprochement($a, $bien("25480 École-Valentin", ["city" => "École-Valentin", "citycode" => "25212", "lat" => 47.278, "lon" => 5.978]))["score"],
      rapprochement($a, $bien("25260 Lougres", ["city" => "Lougres", "citycode" => "25349", "lat" => 47.0942, "lon" => 6.3561])),
    ]);`));
  verifier(score[0] === 100 && score[1] < 100 && score[1] >= 75 && score[2].bloquant, `classement par score : Châtillon-le-Duc ${score[0]} %, École-Valentin ${score[1]} %, Lougres écarté (${score[2].raisons.join(", ")})`);
  verifier(res[1][0] === "oui", "« St-Vit » reconnu comme « Saint-Vit »");
  verifier(erreurs.length === 0, "aucune erreur JavaScript" + (erreurs.length ? " : " + erreurs.join(" | ") : ""));
  console.log("\nSecteur des acquéreurs : OK");
} catch (e) {
  console.error(e.message);
  await capture("secteur-erreur").catch(() => {});
  process.exitCode = 1;
} finally {
  await browser.close();
}
