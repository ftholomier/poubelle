// Fige les établissements réels d'un territoire de démonstration (API Recherche d'entreprises, données SIRENE de
// l'INSEE) dans scripts/seed/haut-doubs-etablissements.json, lu ensuite par le jeu de démonstration sans réseau.
// Usage : npm run demo:sirene   (quelques minutes : 7 requêtes par seconde au plus)
import { writeFileSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { fromRecherche, type RechercheResult } from '../src/lib/sirene';
import type { SireneRecord } from '../src/server/db/schema';
import communes from './seed/haut-doubs-communes.json';

const API = process.env.SIRENE_API_URL ?? 'https://recherche-entreprises.api.gouv.fr';
const out = path.join(path.dirname(fileURLToPath(import.meta.url)), 'seed/haut-doubs-etablissements.json');
const sleep = (ms: number) => new Promise((r) => setTimeout(r, ms));

async function page(insee: string, n: number): Promise<{ results: RechercheResult[]; total_pages: number }> {
  for (let attempt = 0; ; attempt++) {
    const res = await fetch(`${API}/search?code_commune=${insee}&etat_administratif=A&per_page=25&page=${n}`, {
      headers: { accept: 'application/json' },
      signal: AbortSignal.timeout(20_000),
    });
    if (res.ok) return (await res.json()) as { results: RechercheResult[]; total_pages: number };
    if ((res.status === 429 || res.status >= 500) && attempt < 6) {
      await sleep(1500 * (attempt + 1));
      continue;
    }
    throw new Error(`API Recherche d'entreprises : HTTP ${res.status} (commune ${insee}, page ${n})`);
  }
}

async function main() {
  const records: SireneRecord[] = [];
  const perCommune: Record<string, number> = {};
  for (const c of communes) {
    const seen = new Set<string>();
    for (let n = 1, pages = 1; n <= pages && n <= 400; n++) {
      const data = await page(c.insee, n);
      pages = data.total_pages ?? 1;
      for (const r of data.results ?? []) {
        for (const e of fromRecherche(r, c.insee)) {
          // Établissements actifs et diffusibles seulement (les personnes opposées à la diffusion restent masquées).
          if (!e.active || seen.has(e.siret) || /NON.DIFFUSIBLE/i.test(e.name)) continue;
          seen.add(e.siret);
          records.push(e);
        }
      }
      await sleep(160);
    }
    perCommune[c.name] = seen.size;
    console.log(`${c.name.padEnd(28)} ${String(seen.size).padStart(5)} établissements`);
  }

  records.sort((a, b) => a.inseeCode.localeCompare(b.inseeCode) || a.name.localeCompare(b.name, 'fr'));
  const body = records.map((r) => JSON.stringify(r)).join(',\n  ');
  writeFileSync(
    out,
    `{\n "source": "API Recherche d'entreprises (SIRENE, INSEE), établissements actifs et diffusibles",\n "fetchedAt": "${new Date().toISOString().slice(0, 10)}",\n "perCommune": ${JSON.stringify(perCommune)},\n "records": [\n  ${body}\n ]\n}\n`,
  );
  console.log(`\n${records.length} établissements → ${path.relative(process.cwd(), out)}`);
}

main().catch((err) => {
  console.error(err instanceof Error ? err.message : err);
  process.exit(1);
});
