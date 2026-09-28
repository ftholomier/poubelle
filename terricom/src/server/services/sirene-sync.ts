import { and, desc, eq, inArray, ne, sql } from 'drizzle-orm';
import { diffSirene, syncDue } from '@/lib/sirene';
import { audit } from '../audit';
import { invalidate } from '../cache';
import { db } from '../db';
import { categories, communes, establishments, importBatches, sireneChanges, sireneSyncRuns, territories, type SireneRecord } from '../db/schema';
import { fetchInseeChanges, inseeConfigured } from '../integrations/insee-sirene';
import { lookupSiret } from '../integrations/public-data';
import { logger } from '../logger';
import { notifyTerritoryStaff } from '../push';
import { enqueue } from '../queue';
import type { BoContext } from './backoffice';
import { recordRevision } from './establishments';
import { analyzeRows, createEstablishments, guessMapping, recordsToRaw, scanCommune } from './imports';
import { getTerritoryCommunes } from './territories';

/**
 * Synchronisation SIRENE : chaque mois (ou à la demande), les créations et fermetures d'établissements des
 * communes du territoire sont relevées puis proposées à la collectivité. Rien n'est publié ni archivé sans
 * décision d'un agent : une nouveauté acceptée devient une fiche précréée (invitation par courrier), une
 * fermeture acceptée archive la fiche.
 */

const RAW_HEADERS = ['siret', 'nom', 'activitePrincipale', 'adresse', 'codePostal', 'codeCommune', 'latitude', 'longitude'];
const MAX_CONFIRM = 60;

type Trigger = 'SCHEDULE' | 'MANUAL';

async function lastSuccess(territoryId: string): Promise<Date | null> {
  const [run] = await db
    .select({ at: sireneSyncRuns.startedAt })
    .from(sireneSyncRuns)
    .where(and(eq(sireneSyncRuns.territoryId, territoryId), eq(sireneSyncRuns.status, 'DONE')))
    .orderBy(desc(sireneSyncRuns.startedAt))
    .limit(1);
  return run?.at ?? null;
}

/** Point de départ : dernier passage réussi, sinon dernier import, sinon il y a 31 jours. */
async function sinceDate(territoryId: string): Promise<Date> {
  const last = await lastSuccess(territoryId);
  if (last) return last;
  const [batch] = await db
    .select({ at: importBatches.committedAt })
    .from(importBatches)
    .where(and(eq(importBatches.territoryId, territoryId), eq(importBatches.status, 'COMMITTED')))
    .orderBy(desc(importBatches.committedAt))
    .limit(1);
  return batch?.at ?? new Date(Date.now() - 31 * 86_400_000);
}

/** Lance un passage (tâche de fond « sirene.sync »). */
export async function runSireneSync(
  territoryId: string,
  trigger: Trigger = 'SCHEDULE',
): Promise<{ creations: number; closures: number; ignored: number } | null> {
  const [t] = await db.select().from(territories).where(eq(territories.id, territoryId)).limit(1);
  if (!t) return null;
  const communesList = await getTerritoryCommunes(territoryId);
  if (!communesList.length) return null;
  const source = inseeConfigured() ? 'INSEE' : 'RECHERCHE';
  const since = await sinceDate(territoryId);
  const [run] = await db.insert(sireneSyncRuns).values({ territoryId, source, trigger, since }).returning({ id: sireneSyncRuns.id });
  try {
    const inseeToCommune = new Map(communesList.map((c) => [c.inseeCode, c]));
    const known = await db
      .select({ id: establishments.id, siret: establishments.siret, status: establishments.status, communeInsee: communes.inseeCode })
      .from(establishments)
      .innerJoin(communes, eq(communes.id, establishments.communeId))
      .where(eq(establishments.territoryId, territoryId));
    const decided = new Set(
      (await db.select({ kind: sireneChanges.kind, siret: sireneChanges.siret }).from(sireneChanges).where(eq(sireneChanges.territoryId, territoryId))).map(
        (d) => `${d.kind}|${d.siret}`,
      ),
    );

    let records: SireneRecord[] = [];
    const scanned = new Set<string>();
    if (source === 'INSEE') {
      records = await fetchInseeChanges([...inseeToCommune.keys()], since);
    } else {
      for (const c of communesList) {
        const r = await scanCommune(c.inseeCode);
        records.push(...r.records);
        if (r.complete) scanned.add(c.inseeCode);
      }
    }
    const diff = diffSirene({ records, known, decided, scannedCommunes: scanned });
    // Seules les créations récentes sont des nouveautés (les déclarations tardives restent couvertes par
    // une marge de 90 jours) : un établissement ancien absent des fiches relève de l'import initial.
    const floor = new Date(since.getTime() - 90 * 86_400_000).toISOString().slice(0, 10);
    const creations = diff.creations.filter((r) => !r.createdOn || r.createdOn >= floor);
    const closures = diff.closures;

    // Fermetures déduites d'une absence : confirmées une par une (état de l'établissement dans SIRENE).
    const confirmed: { record: SireneRecord; listingId: string }[] = [];
    for (const c of closures) {
      if (c.record) confirmed.push({ record: c.record, listingId: c.listingId });
      else if (confirmed.length < MAX_CONFIRM) {
        const e = await lookupSiret(c.siret);
        if (e && !e.active && e.inseeCode) {
          confirmed.push({
            listingId: c.listingId,
            record: {
              siret: e.siret,
              name: e.name,
              naf: e.nafCode,
              street: e.address,
              postalCode: e.postalCode,
              inseeCode: e.inseeCode,
              city: e.city,
              lat: e.lat,
              lng: e.lng,
              active: false,
            },
          });
        }
      }
    }

    // Nouveautés : mêmes contrôles qu'un import (activités exclues, catégorie, commune, doublons).
    const raw = recordsToRaw(creations);
    const { rows } = await analyzeRows(territoryId, raw, guessMapping(RAW_HEADERS), null);
    const toPropose = rows
      .map((row, i) => ({ row, record: creations[i] }))
      .filter(({ row }) => (row.action === 'CREATE' || row.review) && row.communeId && row.categoryId);
    const ignored = creations.length - toPropose.length;

    const values = [
      ...toPropose.map(({ row, record }) => ({
        territoryId,
        communeId: row.communeId!,
        runId: run.id,
        kind: 'CREATION',
        siret: record.siret,
        record: row.review ? { ...record, reviewReason: row.review } : record,
        categoryId: row.categoryId,
      })),
      ...confirmed
        .filter((c) => inseeToCommune.has(c.record.inseeCode) || known.some((k) => k.id === c.listingId))
        .map((c) => {
          const listing = known.find((k) => k.id === c.listingId)!;
          return {
            territoryId,
            communeId: (inseeToCommune.get(listing.communeInsee) ?? inseeToCommune.get(c.record.inseeCode))!.id,
            runId: run.id,
            kind: 'CLOSURE',
            siret: c.record.siret,
            record: c.record,
            establishmentId: c.listingId,
          };
        })
        .filter((v) => v.communeId),
    ];
    let inserted: { kind: string; communeId: string }[] = [];
    for (let i = 0; i < values.length; i += 500) {
      inserted = inserted.concat(
        await db
          .insert(sireneChanges)
          .values(values.slice(i, i + 500))
          .onConflictDoNothing()
          .returning({ kind: sireneChanges.kind, communeId: sireneChanges.communeId }),
      );
    }
    const nCreations = inserted.filter((v) => v.kind === 'CREATION').length;
    const nClosures = inserted.filter((v) => v.kind === 'CLOSURE').length;
    await db
      .update(sireneSyncRuns)
      .set({ status: 'DONE', creations: nCreations, closures: nClosures, ignored, finishedAt: new Date() })
      .where(eq(sireneSyncRuns.id, run.id));
    if (nCreations + nClosures) {
      await notifyTerritoryStaff(territoryId, {
        title: 'SIRENE : mises à jour à valider',
        body: `${nCreations} nouvelle${nCreations > 1 ? 's' : ''} entreprise${nCreations > 1 ? 's' : ''}, ${nClosures} fermeture${nClosures > 1 ? 's' : ''} signalée${nClosures > 1 ? 's' : ''}.`,
        url: '/collectivite/entreprises/sirene',
        tag: 'sirene',
      });
    }
    return { creations: nCreations, closures: nClosures, ignored };
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    logger.warn('sirene.sync_failed', { territoryId, err: message });
    await db
      .update(sireneSyncRuns)
      .set({ status: 'FAILED', error: message.slice(0, 500), finishedAt: new Date() })
      .where(eq(sireneSyncRuns.id, run.id));
    throw err;
  }
}

/** Tâche quotidienne : met en file les territoires dont la synchronisation mensuelle est due. */
export async function enqueueDueSireneSyncs(now = new Date()): Promise<number> {
  const list = await db
    .select({ id: territories.id, settings: territories.settings })
    .from(territories)
    .where(inArray(territories.status, ['ACTIVE', 'ONBOARDING']));
  let n = 0;
  for (const t of list) {
    if (t.settings.sirene?.autoSync === false) continue;
    if (!syncDue(await lastSuccess(t.id), now)) continue;
    await enqueue('sirene.sync', { territoryId: t.id }, { dedupeKey: `sirene-sync:${t.id}:${now.toISOString().slice(0, 10)}`, maxAttempts: 2 });
    n++;
  }
  return n;
}

// ─── Écrans du back-office ─────────────────────────────────────────────────

function changeScope(ctx: BoContext) {
  return and(eq(sireneChanges.territoryId, ctx.territory.id), ctx.communeIds ? inArray(sireneChanges.communeId, ctx.communeIds) : undefined);
}

export async function pendingSireneCount(ctx: BoContext): Promise<number> {
  const [r] = await db
    .select({ n: sql<number>`count(*)::int` })
    .from(sireneChanges)
    .where(and(changeScope(ctx), eq(sireneChanges.status, 'PENDING')));
  return Number(r?.n ?? 0);
}

export async function pendingSireneChanges(ctx: BoContext) {
  return db
    .select({
      id: sireneChanges.id,
      kind: sireneChanges.kind,
      siret: sireneChanges.siret,
      record: sireneChanges.record,
      createdAt: sireneChanges.createdAt,
      communeName: communes.name,
      categoryId: sireneChanges.categoryId,
      categoryName: categories.name,
      establishmentId: sireneChanges.establishmentId,
      listingName: establishments.name,
      listingStatus: establishments.status,
    })
    .from(sireneChanges)
    .innerJoin(communes, eq(communes.id, sireneChanges.communeId))
    .leftJoin(establishments, eq(establishments.id, sireneChanges.establishmentId))
    .leftJoin(categories, eq(categories.id, sireneChanges.categoryId))
    .where(and(changeScope(ctx), eq(sireneChanges.status, 'PENDING')))
    .orderBy(communes.name, sireneChanges.createdAt);
}

export async function sireneRuns(territoryId: string, limit = 6) {
  return db.select().from(sireneSyncRuns).where(eq(sireneSyncRuns.territoryId, territoryId)).orderBy(desc(sireneSyncRuns.startedAt)).limit(limit);
}

export async function nextSireneSync(territoryId: string, autoSync: boolean): Promise<Date | null> {
  if (!autoSync) return null;
  const last = await lastSuccess(territoryId);
  return last ? new Date(last.getTime() + 28 * 86_400_000) : new Date();
}

async function scopedPending(ctx: BoContext, ids: string[], kind: 'CREATION' | 'CLOSURE') {
  if (!ids.length) return [];
  return db
    .select()
    .from(sireneChanges)
    .where(and(changeScope(ctx), inArray(sireneChanges.id, ids), eq(sireneChanges.kind, kind), eq(sireneChanges.status, 'PENDING')));
}

function refresh(territoryId: string) {
  invalidate(`cards:${territoryId}`);
  invalidate(`pros:${territoryId}`);
  invalidate(`communeCounts:${territoryId}`);
}

/** Accepte des nouveautés : fiches précréées, à inviter par courrier (SIRENE ne fournit pas d'email). */
export async function acceptCreations(ctx: BoContext, ids: string[]): Promise<{ created: string[]; skipped: number }> {
  const changes = await scopedPending(ctx, ids, 'CREATION');
  if (!changes.length) return { created: [], skipped: 0 };
  const { rows } = await analyzeRows(ctx.territory.id, recordsToRaw(changes.map((c) => c.record)), guessMapping(RAW_HEADERS), null);
  // La catégorie retenue lors de la détection reste valable si le rapprochement ne la retrouve plus.
  rows.forEach((r, i) => {
    // Cas « à vérifier » : l'agent a tranché, la fiche est créée.
    if (r.review && r.categoryId && r.communeId) {
      r.action = 'CREATE';
      r.review = null;
    }
    if (!r.categoryId && changes[i].categoryId && r.communeId && r.name && r.errors.every((e) => e.startsWith('Activité'))) {
      r.categoryId = changes[i].categoryId;
      r.errors = [];
      r.action = 'CREATE';
    }
  });
  const { created } = await createEstablishments(
    ctx.territory.id,
    rows.filter((r) => r.action === 'CREATE'),
    ctx.actor.user.id,
  );
  const bySiret = new Map<string, string>();
  if (created.length) {
    for (const e of await db
      .select({ id: establishments.id, siret: establishments.siret })
      .from(establishments)
      .where(
        inArray(
          establishments.id,
          created.map((c) => c.id),
        ),
      ))
      if (e.siret) bySiret.set(e.siret, e.id);
  }
  const done = changes.filter((c) => bySiret.has(c.siret));
  for (const c of done) {
    await db
      .update(sireneChanges)
      .set({ status: 'ACCEPTED', establishmentId: bySiret.get(c.siret)!, decidedById: ctx.actor.user.id, decidedAt: new Date() })
      .where(eq(sireneChanges.id, c.id));
  }
  // Déjà présentes entre-temps (import, inscription) : la proposition est close.
  const already = changes.filter((c) => !bySiret.has(c.siret) && rows[changes.indexOf(c)]?.action === 'MERGE');
  if (already.length) {
    await db
      .update(sireneChanges)
      .set({ status: 'ACCEPTED', decidedById: ctx.actor.user.id, decidedAt: new Date() })
      .where(
        inArray(
          sireneChanges.id,
          already.map((c) => c.id),
        ),
      );
  }
  if (done.length) {
    await audit({
      actor: { user: ctx.actor.user },
      category: 'IMPORT',
      action: 'sirene.accept',
      summary: `SIRENE : ${done.length} nouvelle${done.length > 1 ? 's' : ''} fiche${done.length > 1 ? 's' : ''} créée${done.length > 1 ? 's' : ''}`,
      territoryId: ctx.territory.id,
      targetType: 'sirene',
    });
    refresh(ctx.territory.id);
  }
  return { created: done.map((c) => bySiret.get(c.siret)!), skipped: changes.length - done.length - already.length };
}

/** Refuse des propositions (nouveautés écartées, fermetures non confirmées) : elles ne reviendront pas. */
export async function rejectChanges(ctx: BoContext, ids: string[]): Promise<number> {
  if (!ids.length) return 0;
  const res = await db
    .update(sireneChanges)
    .set({ status: 'REJECTED', decidedById: ctx.actor.user.id, decidedAt: new Date() })
    .where(and(changeScope(ctx), inArray(sireneChanges.id, ids), eq(sireneChanges.status, 'PENDING')))
    .returning({ id: sireneChanges.id, kind: sireneChanges.kind });
  if (res.length) {
    await audit({
      actor: { user: ctx.actor.user },
      category: 'IMPORT',
      action: 'sirene.reject',
      summary: `SIRENE : ${res.length} proposition${res.length > 1 ? 's' : ''} écartée${res.length > 1 ? 's' : ''}`,
      territoryId: ctx.territory.id,
      targetType: 'sirene',
    });
  }
  return res.length;
}

/** Confirme des fermetures : la fiche est archivée (masquée du portail), avec historique. */
export async function archiveClosures(ctx: BoContext, ids: string[]): Promise<number> {
  const changes = await scopedPending(ctx, ids, 'CLOSURE');
  let n = 0;
  for (const c of changes) {
    if (!c.establishmentId) continue;
    const [prev] = await db.select({ status: establishments.status }).from(establishments).where(eq(establishments.id, c.establishmentId));
    const [e] = await db
      .update(establishments)
      .set({ status: 'ARCHIVED', updatedAt: new Date() })
      .where(and(eq(establishments.id, c.establishmentId), ne(establishments.status, 'ARCHIVED')))
      .returning({ id: establishments.id, name: establishments.name });
    if (e) {
      await recordRevision(
        e.id,
        { status: prev?.status },
        { status: 'ARCHIVED' },
        { userId: ctx.actor.user.id, source: 'COLLECTIVITE', summary: 'Archivée : établissement fermé selon SIRENE' },
      ).catch(() => undefined);
      n++;
    }
    await db.update(sireneChanges).set({ status: 'ACCEPTED', decidedById: ctx.actor.user.id, decidedAt: new Date() }).where(eq(sireneChanges.id, c.id));
  }
  if (n) {
    await audit({
      actor: { user: ctx.actor.user },
      category: 'MODIFICATION',
      action: 'sirene.archive',
      summary: `SIRENE : ${n} fiche${n > 1 ? 's' : ''} archivée${n > 1 ? 's' : ''} (établissements fermés)`,
      territoryId: ctx.territory.id,
      targetType: 'sirene',
    });
    refresh(ctx.territory.id);
  }
  return n;
}
