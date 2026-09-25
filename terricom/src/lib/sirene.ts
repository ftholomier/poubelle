import type { SireneRecord } from '@/server/db/schema';

/**
 * Fonctions pures de la synchronisation SIRENE : lecture des réponses (API Sirene de l'INSEE, API Recherche
 * d'entreprises, fichier stock géolocalisé), activités exclues par territoire, comparaison avec les fiches.
 */

// ─── Activités exclues des imports (réglage du territoire) ─────────────────

export const EXCLUSION_GROUPS: { key: string; label: string; hint: string; naf: string[] }[] = [
  {
    key: 'immobilier',
    label: 'Sociétés civiles et location immobilière',
    hint: 'SCI, location de biens, marchands de biens (68.10, 68.20)',
    naf: ['6810', '6820'],
  },
  {
    key: 'holdings',
    label: 'Holdings et sièges sociaux',
    hint: 'Sociétés de participation, fonds, sièges (64.20, 64.30, 70.10)',
    naf: ['6420', '6430', '7010'],
  },
  { key: 'administrations', label: 'Administrations et associations', hint: 'Collectivités, services publics, associations (84, 94)', naf: ['84', '94'] },
  { key: 'sante', label: 'Professions de santé', hint: 'Médecins, dentistes, infirmiers, kinés… (86.2, 86.9)', naf: ['862', '869'] },
  { key: 'juridique', label: 'Professions juridiques et comptables', hint: 'Avocats, notaires, experts-comptables (69)', naf: ['69'] },
  { key: 'finance', label: 'Banques, assurances, finance', hint: 'Agences bancaires, assurances, courtiers (64, 65, 66)', naf: ['64', '65', '66'] },
];

/** Réglage par défaut d'un territoire qui n'a encore rien choisi. */
export const DEFAULT_EXCLUDED_GROUPS = ['immobilier', 'holdings', 'administrations'];

export type SireneSettings = { autoSync?: boolean; excludedGroups?: string[]; excludedNaf?: string[] };

/** Code NAF sans point, en majuscules (« 56.10A » → « 5610A »). */
export function nafKey(naf: string | null | undefined): string {
  return (naf ?? '').replace(/[.\s]/g, '').toUpperCase();
}

/** Codes NAF saisis librement (« 68.20B, 7010 ; 94 ») → préfixes normalisés, dédoublonnés. */
export function parseNafList(text: string): string[] {
  return [
    ...new Set(
      text
        .split(/[\s,;]+/)
        .map(nafKey)
        .filter((c) => /^\d{2}(\d{1,2}[A-Z]?)?$/.test(c)),
    ),
  ].slice(0, 60);
}

export function excludedPrefixes(settings: SireneSettings | undefined): string[] {
  const groups = settings?.excludedGroups ?? DEFAULT_EXCLUDED_GROUPS;
  return [...EXCLUSION_GROUPS.filter((g) => groups.includes(g.key)).flatMap((g) => g.naf), ...(settings?.excludedNaf ?? [])];
}

/** Activité exclue par le territoire ? (préfixe de code NAF, ou société civile immobilière par sa forme juridique) */
export function isExcludedActivity(naf: string | null | undefined, settings: SireneSettings | undefined, legalCategory?: string | null): boolean {
  const code = nafKey(naf);
  const groups = settings?.excludedGroups ?? DEFAULT_EXCLUDED_GROUPS;
  if (legalCategory === '6540' && groups.includes('immobilier')) return true;
  return Boolean(code) && excludedPrefixes(settings).some((p) => code.startsWith(p));
}

// ─── Lecture des sources ───────────────────────────────────────────────────

const VOIE: Record<string, string> = {
  ALL: 'allée',
  AV: 'avenue',
  BD: 'boulevard',
  CHE: 'chemin',
  CHEM: 'chemin',
  CRS: 'cours',
  FG: 'faubourg',
  GR: 'grande rue',
  HAM: 'hameau',
  IMP: 'impasse',
  LD: 'lieu-dit',
  PL: 'place',
  QUA: 'quartier',
  QU: 'quai',
  R: 'rue',
  RTE: 'route',
  RUE: 'rue',
  SQ: 'square',
  VC: 'voie communale',
  ZA: 'zone artisanale',
  ZI: 'zone industrielle',
};

function titleCase(s: string): string {
  const t = s.trim().replace(/\s+/g, ' ');
  if (!t || t !== t.toUpperCase()) return t;
  return t.toLowerCase().replace(/(^|[\s'’-])(\p{L})/gu, (_m, sep: string, c: string) => sep + c.toUpperCase());
}

/** Adresse lisible depuis les champs SIRENE (numéro, répétition, type et libellé de voie). */
export function sireneStreet(num?: string | null, rep?: string | null, type?: string | null, voie?: string | null): string | null {
  const t = type ? (VOIE[type.toUpperCase()] ?? type.toLowerCase()) : '';
  const repet = rep ? ({ B: 'bis', T: 'ter', Q: 'quater' }[rep.toUpperCase()] ?? rep.toLowerCase()) : '';
  const s = [num, repet, t, voie ? titleCase(voie) : ''].filter(Boolean).join(' ').trim();
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : null;
}

function clean(v: unknown): string | null {
  if (typeof v !== 'string') return null;
  const t = v.trim();
  return t && t !== '[ND]' && t !== 'NULL' ? t : null;
}

/** Nom affiché : enseigne, sinon dénomination usuelle, sinon dénomination de l'entreprise (ou nom et prénom). */
function displayName(parts: (string | null | undefined)[]): string | null {
  const n = parts.map((p) => clean(p ?? null)).find(Boolean);
  return n ? titleCase(n).slice(0, 255) : null;
}

type InseePeriode = {
  dateFin: string | null;
  dateDebut: string | null;
  etatAdministratifEtablissement: string | null;
  enseigne1Etablissement?: string | null;
  denominationUsuelleEtablissement?: string | null;
  activitePrincipaleEtablissement?: string | null;
};
export type InseeEtablissement = {
  siret: string;
  statutDiffusionEtablissement?: string | null;
  dateCreationEtablissement?: string | null;
  uniteLegale?: {
    denominationUniteLegale?: string | null;
    denominationUsuelle1UniteLegale?: string | null;
    nomUniteLegale?: string | null;
    nomUsageUniteLegale?: string | null;
    prenom1UniteLegale?: string | null;
    categorieJuridiqueUniteLegale?: string | null;
  };
  adresseEtablissement?: {
    numeroVoieEtablissement?: string | null;
    indiceRepetitionEtablissement?: string | null;
    typeVoieEtablissement?: string | null;
    libelleVoieEtablissement?: string | null;
    codePostalEtablissement?: string | null;
    libelleCommuneEtablissement?: string | null;
    codeCommuneEtablissement?: string | null;
  };
  periodesEtablissement?: InseePeriode[];
};

/** Établissement de l'API Sirene (INSEE) → enregistrement ; null s'il n'est pas diffusable (personne physique opposée). */
export function fromInsee(e: InseeEtablissement): SireneRecord | null {
  if (e.statutDiffusionEtablissement && e.statutDiffusionEtablissement !== 'O') return null;
  const p = e.periodesEtablissement?.find((x) => !x.dateFin) ?? e.periodesEtablissement?.[0];
  const u = e.uniteLegale ?? {};
  const a = e.adresseEtablissement ?? {};
  const person = [clean(u.prenom1UniteLegale ?? null), clean(u.nomUsageUniteLegale ?? null) ?? clean(u.nomUniteLegale ?? null)].filter(Boolean).join(' ');
  const name = displayName([
    p?.enseigne1Etablissement,
    p?.denominationUsuelleEtablissement,
    u.denominationUsuelle1UniteLegale,
    u.denominationUniteLegale,
    person,
  ]);
  const insee = clean(a.codeCommuneEtablissement ?? null);
  if (!name || !insee || !/^\d{14}$/.test(e.siret)) return null;
  const active = p?.etatAdministratifEtablissement === 'A';
  return {
    siret: e.siret,
    name,
    naf: clean(p?.activitePrincipaleEtablissement ?? null),
    legalCategory: clean(u.categorieJuridiqueUniteLegale ?? null),
    street: sireneStreet(a.numeroVoieEtablissement, a.indiceRepetitionEtablissement, a.typeVoieEtablissement, a.libelleVoieEtablissement),
    postalCode: clean(a.codePostalEtablissement ?? null),
    inseeCode: insee,
    city: a.libelleCommuneEtablissement ? titleCase(a.libelleCommuneEtablissement) : null,
    lat: null,
    lng: null,
    active,
    createdOn: clean(e.dateCreationEtablissement ?? null),
    changedOn: active ? null : clean(p?.dateDebut ?? null),
  };
}

/** Requête de l'API Sirene : établissements des communes modifiés depuis une date. */
export function inseeQuery(inseeCodes: string[], since: Date): string {
  const communes = inseeCodes.map((c) => `codeCommuneEtablissement:${c}`).join(' OR ');
  const d = since.toISOString().slice(0, 19);
  return `(${communes}) AND dateDernierTraitementEtablissement:[${d} TO *]`;
}

type RechercheEtab = {
  siret: string;
  adresse?: string | null;
  code_postal?: string | null;
  commune?: string | null;
  libelle_commune?: string | null;
  latitude?: string | null;
  longitude?: string | null;
  activite_principale?: string | null;
  etat_administratif: string;
  nom_commercial?: string | null;
  liste_enseignes?: string[] | null;
  date_creation?: string | null;
  date_fermeture?: string | null;
};
export type RechercheResult = {
  nom_complet: string;
  activite_principale?: string | null;
  nature_juridique?: string | null;
  matching_etablissements?: RechercheEtab[];
};

/** Résultat de l'API Recherche d'entreprises → établissements de la commune demandée. */
export function fromRecherche(r: RechercheResult, inseeCode: string): SireneRecord[] {
  const out: SireneRecord[] = [];
  for (const e of r.matching_etablissements ?? []) {
    if (e.commune !== inseeCode) continue;
    const name = displayName([e.nom_commercial, e.liste_enseignes?.[0], r.nom_complet]);
    if (!name || !/^\d{14}$/.test(e.siret)) continue;
    const lat = Number(e.latitude);
    const lng = Number(e.longitude);
    out.push({
      siret: e.siret,
      name,
      naf: e.activite_principale ?? r.activite_principale ?? null,
      legalCategory: r.nature_juridique ?? null,
      street: e.adresse ? titleCase(e.adresse.replace(/\s\d{5}\s.*$/, '')) : null,
      postalCode: e.code_postal ?? null,
      inseeCode,
      city: e.libelle_commune ? titleCase(e.libelle_commune) : null,
      lat: Number.isFinite(lat) && e.latitude ? lat : null,
      lng: Number.isFinite(lng) && e.longitude ? lng : null,
      active: e.etat_administratif === 'A',
      createdOn: e.date_creation ?? null,
      changedOn: e.date_fermeture ?? null,
    });
  }
  return out;
}

/** Ligne du fichier stock géolocalisé (data.gouv, un fichier par département) → enregistrement. */
export function fromStockRow(r: Record<string, string>, inseeCodes: Set<string>): SireneRecord | null {
  const insee = r.codeCommuneEtablissement;
  if (!insee || !inseeCodes.has(insee)) return null;
  if (r.etatAdministratifEtablissement !== 'A') return null;
  if (r.statutDiffusionEtablissement && r.statutDiffusionEtablissement !== 'O') return null;
  const name = displayName([r.enseigne1Etablissement, r.denominationUsuelleEtablissement, r.denominationUniteLegale, r.nomUniteLegale]);
  if (!r.siret || !/^\d{14}$/.test(r.siret)) return null;
  const lat = Number(r.latitude);
  const lng = Number(r.longitude);
  return {
    siret: r.siret,
    name: name ?? '',
    naf: clean(r.activitePrincipaleEtablissement),
    street: sireneStreet(r.numeroVoieEtablissement, r.indiceRepetitionEtablissement, r.typeVoieEtablissement, r.libelleVoieEtablissement),
    postalCode: clean(r.codePostalEtablissement),
    inseeCode: insee,
    city: r.libelleCommuneEtablissement ? titleCase(r.libelleCommuneEtablissement) : null,
    lat: r.latitude && Number.isFinite(lat) ? lat : null,
    lng: r.longitude && Number.isFinite(lng) ? lng : null,
    active: true,
    createdOn: clean(r.dateCreationEtablissement),
  };
}

/** Adresses des fichiers stock des départements concernés (modèle avec « {dep} »). */
export function stockUrls(departments: (string | null)[], template: string): string[] {
  return [...new Set(departments.filter((d): d is string => Boolean(d)))].sort().map((d) => template.replace('{dep}', d));
}

// ─── Comparaison avec les fiches ───────────────────────────────────────────

export type KnownListing = { id: string; siret: string | null; status: string; communeInsee: string };

/**
 * Changements à proposer : établissements actifs inconnus (créations) et fiches actives dont l'établissement
 * est fermé (fermetures). Les changements déjà proposés (acceptés ou refusés) ne reviennent pas.
 * `scannedCommunes` : communes lues intégralement (liste complète des actifs) ; une fiche absente de cette
 * liste devient une fermeture « à confirmer ».
 */
export function diffSirene(input: { records: SireneRecord[]; known: KnownListing[]; decided: Set<string>; scannedCommunes?: Set<string> }): {
  creations: SireneRecord[];
  closures: { record: SireneRecord | null; listingId: string; siret: string; toConfirm: boolean }[];
} {
  const bySiret = new Map(input.known.filter((k) => k.siret).map((k) => [k.siret!, k]));
  const seen = new Set<string>();
  const creations: SireneRecord[] = [];
  const closures: { record: SireneRecord | null; listingId: string; siret: string; toConfirm: boolean }[] = [];
  for (const r of input.records) {
    if (seen.has(r.siret)) continue;
    seen.add(r.siret);
    const listing = bySiret.get(r.siret);
    if (r.active) {
      if (!listing && !input.decided.has(`CREATION|${r.siret}`)) creations.push(r);
    } else if (listing && listing.status !== 'ARCHIVED' && !input.decided.has(`CLOSURE|${r.siret}`)) {
      closures.push({ record: r, listingId: listing.id, siret: r.siret, toConfirm: false });
    }
  }
  if (input.scannedCommunes?.size) {
    for (const k of input.known) {
      if (!k.siret || k.status === 'ARCHIVED' || seen.has(k.siret) || !input.scannedCommunes.has(k.communeInsee)) continue;
      if (input.decided.has(`CLOSURE|${k.siret}`)) continue;
      closures.push({ record: null, listingId: k.id, siret: k.siret, toConfirm: true });
    }
  }
  return { creations, closures };
}

/** Synchronisation mensuelle due ? (28 jours au moins depuis le dernier passage réussi) */
export function syncDue(lastSuccess: Date | null, now = new Date()): boolean {
  return !lastSuccess || now.getTime() - lastSuccess.getTime() >= 28 * 86_400_000;
}
