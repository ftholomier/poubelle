// Rapport de contrôle de l'import réel du Haut-Doubs : pour chaque établissement SIRENE, décision (gardé, à vérifier,
// exclu) et motif, selon la forme juridique, le code NAF et le nom. Usage : npm run demo:controle
// → docs/demo/haut-doubs-controle.csv (séparateur point-virgule, lisible dans un tableur).
import { readFileSync, writeFileSync } from 'node:fs';
import { sireneExclusion } from '../src/lib/sirene';
import type { SireneRecord } from '../src/server/db/schema';

const data = JSON.parse(readFileSync('scripts/seed/haut-doubs-etablissements.json', 'utf8')) as { fetchedAt: string; records: SireneRecord[] };
const settings = { excludedGroups: ['immobilier', 'holdings', 'administrations', 'energie'] };
const cell = (v: string | null | undefined) => `"${(v ?? '').replace(/"/g, '""')}"`;
const counts: Record<string, number> = {};
const lines = ['décision;motif;siret;nom;code NAF;catégorie juridique;commune'];
for (const r of data.records) {
  const v = sireneExclusion(r.naf, settings, r.legalCategory, r.name);
  const decision = !v ? 'gardée' : v.verdict === 'EXCLUDE' ? 'exclue' : 'à vérifier';
  const reason = v?.reason ?? '';
  const k = `${decision}${reason ? ` · ${reason.replace(/ \(.*\)$/, '')}` : ''}`;
  counts[k] = (counts[k] ?? 0) + 1;
  lines.push([decision, cell(reason), r.siret, cell(r.name), r.naf ?? '', r.legalCategory ?? '', cell(r.city ?? r.inseeCode)].join(';'));
}
writeFileSync('docs/demo/haut-doubs-controle.csv', `﻿${lines.join('\n')}\n`);
console.log(`Données SIRENE du ${data.fetchedAt} : ${data.records.length} établissements actifs\n`);
for (const [k, n] of Object.entries(counts).sort((a, b) => b[1] - a[1])) console.log(`${String(n).padStart(5)}  ${k}`);
console.log('\n→ docs/demo/haut-doubs-controle.csv');
