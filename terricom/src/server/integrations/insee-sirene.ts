import { fromInsee, inseeQuery, type InseeEtablissement } from '@/lib/sirene';
import type { SireneRecord } from '../db/schema';
import { env } from '../env';
import { logger } from '../logger';

/**
 * API Sirene de l'INSEE (portail-api.insee.fr, clé gratuite) : établissements des communes du territoire
 * dont la fiche SIRENE a changé depuis une date (créations, fermetures, transferts). Pagination par curseur,
 * 30 requêtes par minute au plus.
 */

export function inseeConfigured(): boolean {
  return Boolean(env.INSEE_SIRENE_API_KEY);
}

type InseeResponse = { header?: { total?: number; curseur?: string; curseurSuivant?: string }; etablissements?: InseeEtablissement[] };

const pause = (ms: number) => new Promise((r) => setTimeout(r, ms));

export async function fetchInseeChanges(inseeCodes: string[], since: Date, opts: { max?: number } = {}): Promise<SireneRecord[]> {
  const key = env.INSEE_SIRENE_API_KEY;
  if (!key) throw new Error('Clé de l’API Sirene (INSEE_SIRENE_API_KEY) absente.');
  const wanted = new Set(inseeCodes);
  const out: SireneRecord[] = [];
  const max = opts.max ?? 20_000;
  // Plusieurs communes par requête (au plus 20) pour rester sous la limite de 30 requêtes par minute.
  for (let i = 0; i < inseeCodes.length && out.length < max; i += 20) {
    const q = inseeQuery(inseeCodes.slice(i, i + 20), since);
    let cursor = '*';
    for (let guard = 0; guard < 200 && out.length < max; guard++) {
      const url = `${env.INSEE_SIRENE_API_URL}/siret?${new URLSearchParams({ q, nombre: '1000', curseur: cursor })}`;
      let res: Response | null = null;
      for (let attempt = 0; attempt < 4; attempt++) {
        res = await fetch(url, { signal: AbortSignal.timeout(30_000), headers: { accept: 'application/json', 'X-INSEE-Api-Key-Integration': key } });
        if (res.status !== 429) break;
        await pause(61_000);
      }
      if (!res) break;
      if (res.status === 404) break; // aucun établissement modifié
      if (!res.ok) {
        logger.warn('insee.sirene_error', { status: res.status });
        throw new Error(`API Sirene de l’INSEE indisponible (HTTP ${res.status}).`);
      }
      const data = (await res.json()) as InseeResponse;
      for (const e of data.etablissements ?? []) {
        const r = fromInsee(e);
        if (r && wanted.has(r.inseeCode)) out.push(r);
      }
      const next = data.header?.curseurSuivant;
      await pause(2_100);
      if (!next || next === cursor) break;
      cursor = next;
    }
  }
  return out;
}
