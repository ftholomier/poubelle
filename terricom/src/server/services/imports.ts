import { and, eq, inArray, isNull, or } from 'drizzle-orm';
import Papa from 'papaparse';
import { slugify } from '@/lib/slug';
import { audit, type AuditActor } from '../audit';
import { invalidate } from '../cache';
import { shortCode } from '../crypto';
import { db } from '../db';
import {
  categories,
  companies,
  establishments,
  importBatches,
  type ImportMapping,
  type ImportReport,
  type ImportRow,
} from '../db/schema';
import { env } from '../env';
import { isValidSiret } from '../integrations/public-data';
import { logger } from '../logger';
import { sendEmail } from '../mail/send';
import { claimInvitationTemplate } from '../mail/templates';
import { enqueue } from '../queue';
import { appUrl } from '../urls';
import { refreshCompleteness, refreshSearchKeywords } from './establishments';
import { getTerritoryById, getTerritoryCommunes } from './territories';

/**
 * Import d'établissements (fichier CSV ou base SIRENE) en trois temps :
 * analyse et correspondance des colonnes, contrôle (doublons, erreurs), création des fiches précréées.
 */

export const MAX_IMPORT_ROWS = 20_000;
export const MAX_IMPORT_BYTES = 8 * 1024 * 1024;

export type ImportField = keyof ImportMapping;

export const IMPORT_FIELDS: { key: ImportField; label: string; required?: boolean }[] = [
  { key: 'name', label: 'Nom', required: true },
  { key: 'siret', label: 'SIRET' },
  { key: 'naf', label: 'Code NAF' },
  { key: 'category', label: 'Catégorie' },
  { key: 'street', label: 'Adresse' },
  { key: 'postalCode', label: 'Code postal' },
  { key: 'inseeCode', label: 'Commune (code INSEE)' },
  { key: 'city', label: 'Commune (nom)' },
  { key: 'phone', label: 'Téléphone' },
  { key: 'email', label: 'Email' },
  { key: 'website', label: 'Site web' },
  { key: 'lat', label: 'Latitude' },
  { key: 'lng', label: 'Longitude' },
];

/** Colonne virtuelle : adresse reconstituée depuis les colonnes du fichier stock SIRENE. */
export const SIRENE_ADDRESS = '__adresse_sirene__';

const SYNONYMS: Record<ImportField, string[]> = {
  name: ['denominationusuelleetablissement', 'denominationusuelle', 'enseigne1etablissement', 'enseigne', 'nomcommercial', 'nom', 'name', 'raisonsociale', 'denominationunitelegale', 'nomcomplet', 'etablissement'],
  siret: ['siret', 'numerosiret'],
  naf: ['activiteprincipaleetablissement', 'activiteprincipale', 'naf', 'codenaf', 'ape', 'codeape'],
  category: ['categorie', 'category', 'activite', 'secteur', 'type'],
  street: ['adresse', 'adresseetablissement', 'address', 'rue', 'voie', 'adresseligne1'],
  postalCode: ['codepostaletablissement', 'codepostal', 'cp', 'postcode', 'zip'],
  inseeCode: ['codecommuneetablissement', 'codecommune', 'insee', 'codeinsee', 'citycode'],
  city: ['libellecommuneetablissement', 'libellecommune', 'commune', 'ville', 'city'],
  phone: ['telephone', 'tel', 'phone', 'numerotelephone'],
  email: ['email', 'courriel', 'mail', 'adresseemail'],
  website: ['siteweb', 'site', 'website', 'url', 'siteinternet'],
  lat: ['latitude', 'lat', 'y'],
  lng: ['longitude', 'lng', 'lon', 'long', 'x'],
};

function key(s: string): string {
  return s
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '');
}

function norm(s: string | null | undefined): string {
  return (s ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, ' ')
    .trim();
}

/** Décode un fichier CSV (UTF-8 ou Windows-1252) et le découpe en lignes. */
export function parseCsv(buf: Buffer): { headers: string[]; rows: Record<string, string>[] } {
  let text: string;
  try {
    text = new TextDecoder('utf-8', { fatal: true }).decode(buf);
  } catch {
    text = new TextDecoder('windows-1252').decode(buf);
  }
  text = text.replace(/^﻿/, '');
  const parsed = Papa.parse<Record<string, string>>(text, { header: true, skipEmptyLines: 'greedy', transformHeader: (h) => h.trim() });
  const headers = (parsed.meta.fields ?? []).filter(Boolean);
  const rows = parsed.data.slice(0, MAX_IMPORT_ROWS).map((r) => Object.fromEntries(Object.entries(r).map(([k, v]) => [k, typeof v === 'string' ? v.trim() : ''])));
  return { headers, rows };
}

/** Correspondance automatique des colonnes à partir des en-têtes. */
export function guessMapping(headers: string[]): ImportMapping {
  const mapping: ImportMapping = {};
  const byKey = new Map(headers.map((h) => [key(h), h]));
  for (const field of IMPORT_FIELDS) {
    for (const syn of SYNONYMS[field.key]) {
      const h = byKey.get(syn);
      if (h && !Object.values(mapping).includes(h)) {
        mapping[field.key] = h;
        break;
      }
    }
  }
  if (!mapping.street && byKey.has('libellevoieetablissement')) mapping.street = SIRENE_ADDRESS;
  // Fichier SIRENE sans enseigne : la dénomination de l'unité légale sert de nom.
  if (!mapping.name && byKey.has('denominationunitelegale')) mapping.name = byKey.get('denominationunitelegale');
  return mapping;
}

function pick(raw: Record<string, string>, column: string | undefined): string | null {
  if (!column) return null;
  if (column === SIRENE_ADDRESS) {
    const parts = ['numeroVoieEtablissement', 'indiceRepetitionEtablissement', 'typeVoieEtablissement', 'libelleVoieEtablissement'].map((k) => raw[k] ?? '');
    const s = parts.filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();
    return s ? s.charAt(0) + s.slice(1).toLowerCase() : null;
  }
  const v = raw[column];
  return v && v !== 'NULL' && v !== '[ND]' ? v : null;
}

function title(s: string): string {
  if (s !== s.toUpperCase()) return s;
  return s.toLowerCase().replace(/(^|[\s'’-])(\p{L})/gu, (m, sep: string, c: string) => sep + c.toUpperCase());
}

/** Contrôle des lignes : commune, catégorie, SIRET, doublons (fichier et base). */
export async function analyzeRows(
  territoryId: string,
  raw: Record<string, string>[],
  mapping: ImportMapping,
  defaultCategoryId: string | null,
): Promise<{ rows: ImportRow[]; report: ImportReport }> {
  const [communesList, cats, existing] = await Promise.all([
    getTerritoryCommunes(territoryId),
    db
      .select({ id: categories.id, name: categories.name, synonyms: categories.synonyms, nafCodes: categories.nafCodes })
      .from(categories)
      .where(and(eq(categories.isActive, true), or(isNull(categories.territoryId), eq(categories.territoryId, territoryId)))),
    db.select({ id: establishments.id, siret: establishments.siret, name: establishments.name, communeId: establishments.communeId }).from(establishments).where(eq(establishments.territoryId, territoryId)),
  ]);
  const byInsee = new Map(communesList.map((c) => [c.inseeCode, c]));
  const byName = new Map(communesList.map((c) => [norm(c.name), c]));
  const byNaf = new Map<string, (typeof cats)[number]>();
  for (const c of cats) for (const n of c.nafCodes) byNaf.set(n.replace(/\./g, '').toUpperCase(), c);
  const byCatName = new Map<string, (typeof cats)[number]>();
  for (const c of cats) {
    byCatName.set(norm(c.name), c);
    for (const s of c.synonyms ?? []) byCatName.set(norm(s), c);
  }
  const defaultCat = cats.find((c) => c.id === defaultCategoryId) ?? null;
  const existingBySiret = new Map(existing.filter((e) => e.siret).map((e) => [e.siret!, e.id]));
  const existingByName = new Map(existing.map((e) => [`${norm(e.name)}|${e.communeId}`, e.id]));
  const seen = new Map<string, number>();

  const rows: ImportRow[] = raw.map((r, i) => {
    const errors: string[] = [];
    const line = i + 2;
    const nameRaw = pick(r, mapping.name);
    const name = nameRaw ? title(nameRaw).slice(0, 255) : '';
    if (!name) errors.push('Nom manquant');
    const siretRaw = pick(r, mapping.siret)?.replace(/\s/g, '') ?? null;
    const siret = siretRaw && /^\d{14}$/.test(siretRaw) ? siretRaw : null;
    if (siretRaw && (!siret || !isValidSiret(siret))) errors.push('SIRET invalide');
    const insee = pick(r, mapping.inseeCode)?.padStart(5, '0') ?? null;
    const city = pick(r, mapping.city);
    const commune = (insee && byInsee.get(insee)) || (city ? byName.get(norm(city)) : undefined) || null;
    if (!commune) errors.push(insee || city ? `Commune hors du territoire (${city ?? insee})` : 'Commune manquante');
    const naf = pick(r, mapping.naf)?.replace(/\./g, '').toUpperCase() ?? null;
    const catText = pick(r, mapping.category);
    const cat = (naf && byNaf.get(naf)) || (catText ? byCatName.get(norm(catText)) : undefined) || defaultCat;
    if (!cat) errors.push(naf ? `Activité ${naf} sans catégorie correspondante` : 'Catégorie inconnue');
    const phone = pick(r, mapping.phone)?.replace(/[^\d+]/g, '').slice(0, 20) || null;
    const emailRaw = pick(r, mapping.email)?.toLowerCase() ?? null;
    const email = emailRaw && /^[^@\s]+@[^@\s]+\.[a-z]{2,}$/.test(emailRaw) ? emailRaw : null;
    let website = pick(r, mapping.website);
    if (website && !/^https?:\/\//.test(website)) website = `https://${website}`;
    const lat = Number(pick(r, mapping.lat)?.replace(',', '.'));
    const lng = Number(pick(r, mapping.lng)?.replace(',', '.'));
    const row: ImportRow = {
      line,
      name,
      siret: siret && isValidSiret(siret) ? siret : null,
      categoryId: cat?.id ?? null,
      categoryName: cat?.name ?? null,
      communeId: commune?.id ?? null,
      communeName: commune?.name ?? null,
      street: pick(r, mapping.street)?.slice(0, 255) ?? null,
      postalCode: pick(r, mapping.postalCode) ?? commune?.postalCodes[0] ?? null,
      phone,
      email,
      website,
      lat: Number.isFinite(lat) && lat > 40 && lat < 52 ? lat : null,
      lng: Number.isFinite(lng) && lng > -6 && lng < 11 ? lng : null,
      action: 'CREATE',
      existingId: null,
      errors,
    };
    if (errors.length) {
      row.action = 'SKIP';
      return row;
    }
    const dupKey = row.siret ?? `${norm(name)}|${row.communeId}`;
    const first = seen.get(dupKey);
    if (first) {
      row.action = 'SKIP';
      row.errors = [`Doublon de la ligne ${first}`];
      return row;
    }
    seen.set(dupKey, line);
    const existingId = (row.siret && existingBySiret.get(row.siret)) || existingByName.get(`${norm(name)}|${row.communeId}`) || null;
    if (existingId) {
      row.action = 'MERGE';
      row.existingId = existingId;
    }
    return row;
  });
  const report: ImportReport = {
    total: rows.length,
    valid: rows.filter((r) => r.action === 'CREATE').length,
    merged: rows.filter((r) => r.action === 'MERGE').length,
    duplicatesInFile: rows.filter((r) => r.action === 'SKIP' && r.errors[0]?.startsWith('Doublon')).length,
    errors: rows.filter((r) => r.action === 'SKIP' && !r.errors[0]?.startsWith('Doublon')).length,
  };
  return { rows, report };
}

/** Crée un lot à partir d'un fichier CSV déposé. */
export async function createCsvBatch(territoryId: string, userId: string, filename: string, buf: Buffer) {
  const { headers, rows: raw } = parseCsv(buf);
  if (!headers.length || !raw.length) throw new Error('Fichier vide ou illisible : vérifiez qu’il s’agit bien d’un CSV avec une ligne d’en-têtes.');
  const mapping = guessMapping(headers);
  const { rows, report } = await analyzeRows(territoryId, raw, mapping, null);
  const [batch] = await db
    .insert(importBatches)
    .values({ territoryId, createdById: userId, source: 'CSV', filename: filename.slice(0, 255), headers, mapping, rawRows: raw, rows, report })
    .returning({ id: importBatches.id });
  return batch.id;
}

/** Nouvelle analyse avec une correspondance corrigée par l'agent. */
export async function remapBatch(batchId: string, territoryId: string, mapping: ImportMapping, defaultCategoryId: string | null) {
  const [batch] = await db.select().from(importBatches).where(and(eq(importBatches.id, batchId), eq(importBatches.territoryId, territoryId))).limit(1);
  if (!batch || batch.status !== 'ANALYZED') throw new Error('Import introuvable ou déjà traité.');
  const { rows, report } = await analyzeRows(territoryId, batch.rawRows, mapping, defaultCategoryId);
  await db.update(importBatches).set({ mapping, rows, report, defaultCategoryId, updatedAt: new Date() }).where(eq(importBatches.id, batchId));
}

/** Création des fiches précréées et fusion des doublons ; invitations facultatives. */
export async function commitBatch(batchId: string, territoryId: string, actor: AuditActor & { user: { id: string } }, opts: { invite: boolean }): Promise<ImportReport> {
  const [batch] = await db.select().from(importBatches).where(and(eq(importBatches.id, batchId), eq(importBatches.territoryId, territoryId))).limit(1);
  if (!batch || batch.status !== 'ANALYZED') throw new Error('Import introuvable ou déjà traité.');
  await db.update(importBatches).set({ status: 'RUNNING', updatedAt: new Date() }).where(eq(importBatches.id, batchId));
  const territory = await getTerritoryById(territoryId);
  const communesList = await getTerritoryCommunes(territoryId);
  const communeById = new Map(communesList.map((c) => [c.id, c]));
  const catNaf = new Map((await db.select({ id: categories.id, nafCodes: categories.nafCodes }).from(categories)).map((c) => [c.id, c.nafCodes[0] ?? null]));
  const slugTaken = new Set(
    (await db.select({ c: establishments.communeId, s: establishments.slug }).from(establishments).where(eq(establishments.territoryId, territoryId))).map((r) => `${r.c}|${r.s}`),
  );
  const created: { id: string; name: string; email: string | null }[] = [];
  let updated = 0;
  const toGeocode: string[] = [];

  for (const row of batch.rows) {
    if (row.action === 'SKIP' || !row.communeId || !row.categoryId) continue;
    try {
      if (row.action === 'MERGE' && row.existingId) {
        const [e] = await db.select().from(establishments).where(eq(establishments.id, row.existingId)).limit(1);
        if (!e) continue;
        const patch: Partial<typeof establishments.$inferInsert> = {};
        if (!e.phone && row.phone) patch.phone = row.phone;
        if (!e.email && row.email) patch.email = row.email;
        if (!e.website && row.website) patch.website = row.website;
        if (!e.street && row.street) patch.street = row.street;
        if (!e.siret && row.siret) patch.siret = row.siret;
        if ((e.lat === null || e.lng === null) && row.lat !== null && row.lng !== null) Object.assign(patch, { lat: row.lat, lng: row.lng });
        if (Object.keys(patch).length) {
          await db.update(establishments).set(patch).where(eq(establishments.id, e.id));
          updated++;
        }
        continue;
      }
      const commune = communeById.get(row.communeId)!;
      const siren = row.siret?.slice(0, 9) ?? null;
      let companyId: string | null = null;
      if (siren) {
        const [found] = await db.select({ id: companies.id }).from(companies).where(eq(companies.siren, siren)).limit(1);
        companyId = found?.id ?? null;
      }
      if (!companyId) {
        const [co] = await db
          .insert(companies)
          .values({ siren, legalName: row.name.toUpperCase(), tradeName: row.name, nafCode: catNaf.get(row.categoryId) ?? null })
          .returning({ id: companies.id });
        companyId = co.id;
      }
      const base = slugify(row.name) || 'etablissement';
      let slug = base;
      for (let i = 2; slugTaken.has(`${commune.id}|${slug}`); i++) slug = `${base}-${i}`;
      slugTaken.add(`${commune.id}|${slug}`);
      const [est] = await db
        .insert(establishments)
        .values({
          companyId,
          communeId: commune.id,
          territoryId,
          categoryId: row.categoryId,
          slug,
          name: row.name,
          siret: row.siret,
          status: 'PRECREATED',
          origin: 'IMPORT',
          activityLabel: row.categoryName,
          street: row.street,
          postalCode: row.postalCode,
          lat: row.lat ?? commune.lat,
          lng: row.lng ?? commune.lng,
          phone: row.phone,
          email: row.email,
          website: row.website,
          qrCode: shortCode(8),
          createdById: actor.user.id,
        })
        .onConflictDoNothing()
        .returning({ id: establishments.id });
      if (!est) continue;
      created.push({ id: est.id, name: row.name, email: row.email });
      if (row.lat === null && row.street) toGeocode.push(est.id);
    } catch (err) {
      logger.warn('import.row_failed', { line: row.line, err: err instanceof Error ? err.message : String(err) });
    }
  }
  for (const c of created) {
    await refreshSearchKeywords(c.id);
    await refreshCompleteness(c.id);
  }
  for (const id of toGeocode) await enqueue('import.geocode', { establishmentId: id }, { dedupeKey: `geocode:${id}` });

  let invited = 0;
  if (opts.invite && territory) {
    for (const c of created.filter((x) => x.email)) {
      await sendEmail({
        ...claimInvitationTemplate({ to: c.email!, establishmentName: c.name, territory, url: appUrl(`/pro/revendiquer/${c.id}`) }),
        territoryId,
      });
      invited++;
    }
  }
  const report: ImportReport = { ...batch.report, created: created.length, updated, invited, letterIds: opts.invite ? created.filter((x) => !x.email).map((x) => x.id) : [] };
  await db.update(importBatches).set({ status: 'COMMITTED', report, committedAt: new Date(), rawRows: [], updatedAt: new Date() }).where(eq(importBatches.id, batchId));
  await audit({
    actor,
    category: 'IMPORT',
    action: batch.source === 'SIRENE' ? 'import.sirene' : 'import.csv',
    summary: `Import « ${batch.filename} » : ${created.length} fiches créées, ${updated} complétées${invited ? `, ${invited} invitations envoyées` : ''}`,
    territoryId,
    targetType: 'import',
    targetId: batchId,
  });
  invalidate(`cards:${territoryId}`);
  invalidate(`pros:${territoryId}`);
  invalidate(`communeCounts:${territoryId}`);
  return report;
}

// ─── Import depuis la base SIRENE (API Recherche d'entreprises) ────────────

type SearchResult = {
  results: {
    nom_complet: string;
    activite_principale: string | null;
    matching_etablissements?: {
      siret: string;
      adresse: string | null;
      code_postal: string | null;
      commune: string | null;
      libelle_commune: string | null;
      latitude: string | null;
      longitude: string | null;
      activite_principale?: string | null;
      etat_administratif: string;
      nom_commercial?: string | null;
      liste_enseignes?: string[] | null;
    }[];
  }[];
  total_pages?: number;
};

/** Crée un lot SIRENE à remplir par le worker. */
export async function createSireneBatch(territoryId: string, userId: string): Promise<string> {
  const [batch] = await db
    .insert(importBatches)
    .values({ territoryId, createdById: userId, source: 'SIRENE', filename: `Base SIRENE · ${new Date().toLocaleDateString('fr-FR')}`, status: 'PENDING' })
    .returning({ id: importBatches.id });
  await enqueue('import.sirene', { batchId: batch.id }, { dedupeKey: `sirene:${batch.id}` });
  return batch.id;
}

/** Tâche de fond : interroge l'API commune par commune (établissements actifs) puis analyse le lot. */
export async function runSireneImport(batchId: string): Promise<void> {
  const [batch] = await db.select().from(importBatches).where(eq(importBatches.id, batchId)).limit(1);
  if (!batch || batch.status !== 'PENDING') return;
  const communesList = await getTerritoryCommunes(batch.territoryId);
  const raw: Record<string, string>[] = [];
  try {
    for (const c of communesList) {
      for (let page = 1; page <= 40 && raw.length < MAX_IMPORT_ROWS; page++) {
        const url = `${env.SIRENE_API_URL}/search?code_commune=${c.inseeCode}&etat_administratif=A&per_page=25&page=${page}`;
        const res = await fetch(url, { signal: AbortSignal.timeout(10_000), headers: { accept: 'application/json' } });
        if (res.status === 429) {
          await new Promise((r) => setTimeout(r, 1500));
          page--;
          continue;
        }
        if (!res.ok) throw new Error(`API SIRENE indisponible (HTTP ${res.status})`);
        const data = (await res.json()) as SearchResult;
        for (const r of data.results ?? []) {
          for (const e of r.matching_etablissements ?? []) {
            if (e.etat_administratif !== 'A' || e.commune !== c.inseeCode) continue;
            raw.push({
              siret: e.siret,
              nom: e.nom_commercial || e.liste_enseignes?.[0] || r.nom_complet,
              activitePrincipale: e.activite_principale ?? r.activite_principale ?? '',
              adresse: (e.adresse ?? '').replace(/\s\d{5}\s.*$/, ''),
              codePostal: e.code_postal ?? '',
              codeCommune: e.commune ?? '',
              latitude: e.latitude ?? '',
              longitude: e.longitude ?? '',
            });
          }
        }
        if (!data.total_pages || page >= data.total_pages) break;
        await new Promise((r) => setTimeout(r, 180)); // ≤ 7 requêtes/s
      }
    }
  } catch (err) {
    await db
      .update(importBatches)
      .set({ status: 'FAILED', error: err instanceof Error ? err.message : String(err), updatedAt: new Date() })
      .where(eq(importBatches.id, batchId));
    return;
  }
  const headers = ['siret', 'nom', 'activitePrincipale', 'adresse', 'codePostal', 'codeCommune', 'latitude', 'longitude'];
  const mapping = guessMapping(headers);
  const { rows, report } = await analyzeRows(batch.territoryId, raw, mapping, null);
  await db.update(importBatches).set({ status: 'ANALYZED', headers, mapping, rawRows: raw, rows, report, updatedAt: new Date() }).where(eq(importBatches.id, batchId));
}

/** Établissements à inviter par courrier (sans email) parmi une sélection. */
export async function withoutEmail(ids: string[]) {
  if (!ids.length) return [];
  return db.select({ id: establishments.id }).from(establishments).where(and(inArray(establishments.id, ids), isNull(establishments.email)));
}
