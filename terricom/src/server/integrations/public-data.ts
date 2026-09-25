import { env } from '../env';
import { logger } from '../logger';

/**
 * Connecteurs vers les API publiques de l'État (sans clé) :
 *  - API Recherche d'entreprises (base SIRENE) : vérification SIRET, import ;
 *  - API Géo : communes d'un EPCI (création d'un territoire) ;
 *  - Base Adresse Nationale : géocodage des adresses.
 * Toutes les fonctions échouent proprement (null) si le service est indisponible.
 */
async function getJson<T>(url: string, timeoutMs = 8000): Promise<T | null> {
  try {
    const res = await fetch(url, { signal: AbortSignal.timeout(timeoutMs), headers: { accept: 'application/json' } });
    if (!res.ok) {
      logger.warn('public_api.http_error', { url, status: res.status });
      return null;
    }
    return (await res.json()) as T;
  } catch (err) {
    logger.warn('public_api.unreachable', { url, err: err instanceof Error ? err.message : String(err) });
    return null;
  }
}

export type SireneEstablishment = {
  siren: string;
  siret: string;
  name: string;
  legalName: string;
  holderName: string | null;
  nafCode: string | null;
  address: string | null;
  postalCode: string | null;
  city: string | null;
  inseeCode: string | null;
  lat: number | null;
  lng: number | null;
  active: boolean;
};

type RechercheEntreprisesResponse = {
  results: {
    siren: string;
    nom_complet: string;
    nom_raison_sociale: string | null;
    activite_principale: string | null;
    dirigeants?: { nom?: string; prenoms?: string; denomination?: string }[];
    siege: {
      siret: string;
      adresse: string | null;
      code_postal: string | null;
      libelle_commune: string | null;
      commune: string | null;
      latitude: string | null;
      longitude: string | null;
      etat_administratif: string;
    };
    matching_etablissements?: {
      siret: string;
      adresse: string | null;
      code_postal: string | null;
      libelle_commune: string | null;
      commune: string | null;
      latitude: string | null;
      longitude: string | null;
      etat_administratif: string;
    }[];
  }[];
};

/** Recherche un établissement par SIRET dans la base SIRENE. */
export async function lookupSiret(siret: string): Promise<SireneEstablishment | null> {
  const clean = siret.replace(/\s/g, '');
  if (!/^\d{14}$/.test(clean)) return null;
  const data = await getJson<RechercheEntreprisesResponse>(`${env.SIRENE_API_URL}/search?q=${clean}&per_page=1`);
  const r = data?.results?.[0];
  if (!r) return null;
  const etab = r.matching_etablissements?.find((m) => m.siret === clean) ?? (r.siege.siret === clean ? r.siege : null);
  if (!etab) return null;
  const d = r.dirigeants?.[0];
  return {
    siren: r.siren,
    siret: etab.siret,
    name: r.nom_complet,
    legalName: r.nom_raison_sociale ?? r.nom_complet,
    holderName: d ? (d.denomination ?? `${d.nom ?? ''} ${d.prenoms ?? ''}`.trim()) || null : null,
    nafCode: r.activite_principale,
    address: etab.adresse,
    postalCode: etab.code_postal,
    city: etab.libelle_commune,
    inseeCode: etab.commune,
    lat: etab.latitude ? Number(etab.latitude) : null,
    lng: etab.longitude ? Number(etab.longitude) : null,
    active: etab.etat_administratif === 'A',
  };
}

/** Contrôle de Luhn d'un numéro SIRET (hors cas particulier de La Poste). */
export function isValidSiret(siret: string): boolean {
  const s = siret.replace(/\s/g, '');
  if (!/^\d{14}$/.test(s)) return false;
  if (s.startsWith('356000000')) return true;
  let sum = 0;
  for (let i = 0; i < 14; i++) {
    let n = Number(s[13 - i]);
    if (i % 2 === 1) {
      n *= 2;
      if (n > 9) n -= 9;
    }
    sum += n;
  }
  return sum % 10 === 0;
}

/** Complète 13 chiffres avec la clé de contrôle (jeux de données de démonstration, tests). */
export function completeSiret(first13: string): string {
  for (let d = 0; d <= 9; d++) if (isValidSiret(`${first13}${d}`)) return `${first13}${d}`;
  return `${first13}0`;
}

export type GeoCommune = {
  nom: string;
  code: string;
  codesPostaux: string[];
  codeDepartement: string;
  population?: number;
  centre?: { type: 'Point'; coordinates: [number, number] };
};

/** Communes membres d'un EPCI (par SIREN de l'EPCI). */
export async function communesOfEpci(epciSiren: string): Promise<GeoCommune[] | null> {
  return getJson<GeoCommune[]>(
    `${env.GEO_API_URL}/epcis/${epciSiren}/communes?fields=nom,code,codesPostaux,codeDepartement,population,centre&format=json`,
  );
}

export async function communeByInsee(code: string): Promise<GeoCommune | null> {
  return getJson<GeoCommune>(`${env.GEO_API_URL}/communes/${code}?fields=nom,code,codesPostaux,codeDepartement,population,centre&format=json`);
}

type BanResponse = { features: { geometry: { coordinates: [number, number] }; properties: { score: number; label: string; citycode: string } }[] };

/** Géocode une adresse (Base Adresse Nationale). */
export async function geocode(address: string, inseeCode?: string | null): Promise<{ lat: number; lng: number; score: number; label: string } | null> {
  const params = new URLSearchParams({ q: address, limit: '1' });
  if (inseeCode) params.set('citycode', inseeCode);
  const data = await getJson<BanResponse>(`${env.BAN_API_URL}/search/?${params}`);
  const f = data?.features?.[0];
  if (!f || f.properties.score < 0.4) return null;
  return { lng: f.geometry.coordinates[0], lat: f.geometry.coordinates[1], score: f.properties.score, label: f.properties.label };
}
