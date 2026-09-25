import { and, count, eq, inArray, or, sql } from 'drizzle-orm';
import { PUBLIC_STATUSES } from '@/lib/constants';
import { aiEnabled } from '../ai/client';
import { translateFields } from '../ai/features';
import { sha256 } from '../crypto';
import { db } from '../db';
import { establishments, territories, type EstablishmentTranslations, type TerritorySettings, type TerritoryTexts } from '../db/schema';
import { logger } from '../logger';
import { enqueue } from '../queue';
import { getEnabledModules } from './territories';

/**
 * Traduction des contenus du portail multilingue (module MULTILINGUAL).
 * Les textes saisis en français sont traduits par l'IA en anglais et en allemand ; sans IA (clé absente,
 * quota atteint, refus), le texte français reste affiché avec la mention « Texte original en français ».
 */

export const TRANSLATED_LOCALES = ['en', 'de'] as const;
export type TranslatedLocale = (typeof TRANSLATED_LOCALES)[number];

export const LOCALE_LABELS: Record<TranslatedLocale, string> = { en: 'Anglais', de: 'Allemand' };

/** Textes du portail traduisibles, dans l'ordre d'affichage du back-office. */
export const TERRITORY_TEXT_FIELDS: { key: keyof TerritoryTexts; label: string; multiline?: boolean; hint?: string }[] = [
  { key: 'tagline', label: 'Accroche du portail' },
  { key: 'heroTitle', label: 'Titre de l’accueil', hint: 'Le caractère | force un retour à la ligne, & est mis en couleur.' },
  { key: 'heroSubtitle', label: 'Sous-titre de l’accueil', multiline: true },
  { key: 'newsletterName', label: 'Nom de la newsletter' },
  { key: 'jobsTitle', label: 'Titre de la rubrique emploi' },
  { key: 'jobsIntro', label: 'Accroche de la rubrique emploi' },
  { key: 'livingTitle', label: 'Encart « Vivre ici » : titre' },
  { key: 'livingText', label: 'Encart « Vivre ici » : texte', multiline: true },
  { key: 'circuitsTitle', label: 'Titre de la rubrique circuits' },
  { key: 'footerText', label: 'Texte du pied de page', multiline: true },
];

type TerritoryTextSource = { tagline: string | null; heroTitle: string | null; heroSubtitle: string | null; settings: unknown };

/** Textes français du portail (colonnes du territoire et réglages), non vides. */
export function territoryFrenchTexts(t: TerritoryTextSource): Partial<Record<keyof TerritoryTexts, string>> {
  const s = (t.settings ?? {}) as TerritorySettings & Record<string, unknown>;
  const out: Partial<Record<keyof TerritoryTexts, string>> = {};
  for (const { key } of TERRITORY_TEXT_FIELDS) {
    const v = key === 'tagline' ? t.tagline : key === 'heroTitle' ? t.heroTitle : key === 'heroSubtitle' ? t.heroSubtitle : s[key];
    if (typeof v === 'string' && v.trim()) out[key] = v.trim();
  }
  return out;
}

/** Empreinte du texte français : une traduction n'est refaite que si le texte d'origine a changé. */
export function sourceHash(fields: Record<string, string | null | undefined>): string {
  return sha256(JSON.stringify(Object.entries(fields).map(([k, v]) => [k, v ?? '']))).slice(0, 16);
}

type EstablishmentSource = {
  id: string;
  territoryId: string;
  tagline: string | null;
  description: string | null;
  translations: EstablishmentTranslations | null;
};

const listingSource = (e: Pick<EstablishmentSource, 'tagline' | 'description'>) => ({ tagline: e.tagline ?? '', description: e.description ?? '' });

/** Traductions à jour dans toutes les langues (ou saisies à la main) ? */
export function listingUpToDate(e: Pick<EstablishmentSource, 'tagline' | 'description' | 'translations'>): boolean {
  const hash = sourceHash(listingSource(e));
  const tr = e.translations ?? {};
  return TRANSLATED_LOCALES.every((l) => tr[l]?.source === 'manual' || tr[l]?.hash === hash);
}

/** Programme la traduction d'une fiche si le territoire est multilingue, l'IA disponible et le texte nouveau. */
export async function queueEstablishmentTranslation(e: EstablishmentSource): Promise<boolean> {
  if (!aiEnabled()) return false;
  const src = listingSource(e);
  if (!src.tagline && !src.description) return false;
  if (listingUpToDate(e)) return false;
  const modules = await getEnabledModules(e.territoryId);
  if (!modules.has('MULTILINGUAL')) return false;
  await enqueue('i18n.translate', { establishmentId: e.id }, { dedupeKey: `i18n:est:${e.id}:${sourceHash(src)}`, maxAttempts: 2 });
  return true;
}

/** Traite une fiche (tâche de fond « i18n.translate ») : accroche et description, en anglais et en allemand. */
export async function translateEstablishment(id: string): Promise<{ translated: TranslatedLocale[] }> {
  const [e] = await db
    .select({
      id: establishments.id,
      territoryId: establishments.territoryId,
      companyId: establishments.companyId,
      tagline: establishments.tagline,
      description: establishments.description,
      translations: establishments.translations,
    })
    .from(establishments)
    .where(eq(establishments.id, id))
    .limit(1);
  if (!e) return { translated: [] };
  const src = listingSource(e);
  if (!src.tagline && !src.description) return { translated: [] };
  const hash = sourceHash(src);
  const current = e.translations ?? {};
  const next: EstablishmentTranslations = { ...current };
  const done: TranslatedLocale[] = [];
  for (const l of TRANSLATED_LOCALES) {
    if (current[l]?.source === 'manual' || current[l]?.hash === hash) continue;
    const out = await translateFields(src, l, { territoryId: e.territoryId, companyId: e.companyId }, "fiche d'un commerce : accroche et description");
    if (!out) continue; // Repli : le texte français reste affiché.
    next[l] = { tagline: out.tagline, description: out.description, hash, source: 'ai', translatedAt: new Date().toISOString() };
    done.push(l);
  }
  if (done.length) await db.update(establishments).set({ translations: next }).where(eq(establishments.id, id));
  else logger.info('i18n.listing_kept_french', { establishmentId: id });
  return { translated: done };
}

/** Traduction manuelle d'une langue d'une fiche (prioritaire sur la traduction automatique). */
export function manualListingTranslation(
  e: Pick<EstablishmentSource, 'tagline' | 'description' | 'translations'>,
  locale: TranslatedLocale,
  value: { tagline: string; description: string } | null,
): EstablishmentTranslations {
  const next: EstablishmentTranslations = { ...(e.translations ?? {}) };
  if (!value || (!value.tagline && !value.description)) delete next[locale];
  else
    next[locale] = {
      tagline: value.tagline || undefined,
      description: value.description || undefined,
      hash: sourceHash(listingSource(e)),
      source: 'manual',
      translatedAt: new Date().toISOString(),
    };
  return next;
}

/** Programme la traduction des fiches publiques du territoire qui n'ont pas de traduction à jour. */
export async function queueTerritoryListingTranslations(territoryId: string, limit = 400): Promise<number> {
  if (!aiEnabled()) return 0;
  const rows = await db
    .select({
      id: establishments.id,
      territoryId: establishments.territoryId,
      tagline: establishments.tagline,
      description: establishments.description,
      translations: establishments.translations,
    })
    .from(establishments)
    .where(
      and(
        eq(establishments.territoryId, territoryId),
        inArray(establishments.status, PUBLIC_STATUSES),
        or(sql`coalesce(${establishments.description}, '') <> ''`, sql`coalesce(${establishments.tagline}, '') <> ''`),
      ),
    );
  let queued = 0;
  for (const e of rows) {
    if (queued >= limit) break;
    if (await queueEstablishmentTranslation(e)) queued++;
  }
  return queued;
}

/** Avancement des traductions de fiches : fiches rédigées et fiches traduites dans toutes les langues. */
export async function listingTranslationStats(territoryId: string): Promise<{ withText: number; translated: number }> {
  const base = and(
    eq(establishments.territoryId, territoryId),
    inArray(establishments.status, PUBLIC_STATUSES),
    or(sql`coalesce(${establishments.description}, '') <> ''`, sql`coalesce(${establishments.tagline}, '') <> ''`),
  );
  const [[a], [b]] = await Promise.all([
    db.select({ n: count() }).from(establishments).where(base),
    db
      .select({ n: count() })
      .from(establishments)
      .where(and(base, sql`${establishments.translations} ? 'en'`, sql`${establishments.translations} ? 'de'`)),
  ]);
  return { withText: Number(a?.n ?? 0), translated: Number(b?.n ?? 0) };
}

/**
 * Propose par l'IA les traductions manquantes des textes du portail (les traductions déjà saisies sont conservées).
 * Renvoie le nombre de champs remplis, ou null si l'IA est indisponible.
 */
export async function translateTerritoryTexts(territoryId: string): Promise<number | null> {
  if (!aiEnabled()) return null;
  const [t] = await db.select().from(territories).where(eq(territories.id, territoryId)).limit(1);
  if (!t) return 0;
  const fr = territoryFrenchTexts(t);
  const settings = (t.settings ?? {}) as TerritorySettings;
  const translations = { ...(settings.translations ?? {}) };
  let filled = 0;
  let failed = false;
  for (const l of TRANSLATED_LOCALES) {
    const existing = translations[l] ?? {};
    const missing = Object.fromEntries(Object.entries(fr).filter(([k]) => !existing[k as keyof TerritoryTexts]?.trim())) as Record<string, string>;
    if (!Object.keys(missing).length) continue;
    const out = await translateFields(missing, l, { territoryId }, 'textes de présentation du portail du territoire');
    if (!out) {
      failed = true;
      continue;
    }
    translations[l] = { ...existing, ...out };
    filled += Object.keys(out).length;
  }
  if (filled) {
    await db
      .update(territories)
      .set({ settings: { ...settings, translations }, updatedAt: new Date() })
      .where(eq(territories.id, territoryId));
  }
  return failed && !filled ? null : filled;
}
