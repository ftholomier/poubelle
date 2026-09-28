import { asc, desc, gt, sql } from 'drizzle-orm';
import type { AuditCategory } from '@/lib/constants';
import { fullName } from '@/lib/format';
import { sha256, ipHash } from './crypto';
import { db, type DbOrTx } from './db';
import { auditLog } from './db/schema';
import { logger } from './logger';

const GENESIS = '0'.repeat(64);
const AUDIT_LOCK = 72_727_001;

export type AuditActor = { id: string; label: string } | { user: { id: string; firstName: string; lastName: string; email: string } } | 'Système' | string;

export type AuditEntry = {
  actor: AuditActor | null;
  category: AuditCategory;
  action: string;
  summary: string;
  territoryId?: string | null;
  targetType?: string;
  targetId?: string | null;
  metadata?: Record<string, unknown>;
  ip?: string | null;
  /** Date de l'événement : uniquement pour la reprise d'historique (import, jeu de démonstration). */
  at?: Date;
};

function resolveActor(actor: AuditActor | null): { id: string | null; label: string } {
  if (!actor) return { id: null, label: 'Anonyme' };
  if (typeof actor === 'string') return { id: null, label: actor };
  if ('user' in actor) return { id: actor.user.id, label: fullName(actor.user) };
  return { id: actor.id, label: actor.label };
}

function canonical(e: {
  occurredAt: string;
  actorUserId: string | null;
  actorLabel: string;
  territoryId: string | null;
  category: string;
  action: string;
  targetType: string | null;
  targetId: string | null;
  summary: string;
  metadata: Record<string, unknown>;
}): string {
  const sortKeys = (v: unknown): unknown =>
    v && typeof v === 'object' && !Array.isArray(v)
      ? Object.fromEntries(
          Object.keys(v as object)
            .sort()
            .map((k) => [k, sortKeys((v as Record<string, unknown>)[k])]),
        )
      : Array.isArray(v)
        ? v.map(sortKeys)
        : v;
  return JSON.stringify(sortKeys(e));
}

/**
 * Écrit une entrée dans le journal d'audit. Chaque entrée contient l'empreinte
 * de la précédente : toute altération ultérieure casse la chaîne et devient détectable.
 */
export async function audit(entry: AuditEntry, tx?: DbOrTx): Promise<void> {
  const run = async (t: DbOrTx) => {
    await t.execute(sql`SELECT pg_advisory_xact_lock(${AUDIT_LOCK})`);
    const last = await t.select({ hash: auditLog.hash }).from(auditLog).orderBy(desc(auditLog.id)).limit(1);
    const prevHash = last[0]?.hash ?? GENESIS;
    const actor = resolveActor(entry.actor);
    const occurredAt = entry.at ?? new Date();
    const record = {
      occurredAt: occurredAt.toISOString(),
      actorUserId: actor.id,
      actorLabel: actor.label,
      territoryId: entry.territoryId ?? null,
      category: entry.category,
      action: entry.action,
      targetType: entry.targetType ?? null,
      targetId: entry.targetId ?? null,
      summary: entry.summary,
      metadata: entry.metadata ?? {},
    };
    const hash = sha256(prevHash + canonical(record));
    await t.insert(auditLog).values({
      ...record,
      occurredAt,
      ipHash: ipHash(entry.ip ?? null),
      prevHash,
      hash,
    });
  };
  try {
    if (tx) await run(tx);
    else await db.transaction(run);
  } catch (err) {
    // L'audit ne doit jamais faire échouer silencieusement : on journalise et on propage.
    logger.error('audit.write_failed', { err, action: entry.action });
    throw err;
  }
}

/** Vérifie l'intégrité de la chaîne. Renvoie l'identifiant de la première entrée altérée, ou null. */
export async function verifyAuditChain(): Promise<{ ok: boolean; checked: number; brokenAt: number | null }> {
  let prev: string | null = null;
  let lastId = 0;
  let checked = 0;
  for (;;) {
    const batch = await db.select().from(auditLog).where(gt(auditLog.id, lastId)).orderBy(asc(auditLog.id)).limit(1000);
    if (!batch.length) break;
    for (const row of batch) {
      if (prev !== null && row.prevHash !== prev) return { ok: false, checked, brokenAt: row.id };
      const expected = sha256(
        row.prevHash +
          canonical({
            occurredAt: row.occurredAt.toISOString(),
            actorUserId: row.actorUserId,
            actorLabel: row.actorLabel,
            territoryId: row.territoryId,
            category: row.category,
            action: row.action,
            targetType: row.targetType,
            targetId: row.targetId,
            summary: row.summary,
            metadata: row.metadata,
          }),
      );
      if (expected !== row.hash) return { ok: false, checked, brokenAt: row.id };
      prev = row.hash;
      lastId = row.id;
      checked++;
    }
  }
  return { ok: true, checked, brokenAt: null };
}

/** Purge de rétention (entrées de plus de 12 mois), autorisée explicitement auprès du déclencheur. */
export async function purgeOldAuditEntries(): Promise<number> {
  return db.transaction(async (tx) => {
    await tx.execute(sql`SET LOCAL terricom.audit_purge = 'on'`);
    const res = await tx.execute(sql`DELETE FROM audit_log WHERE occurred_at < now() - interval '12 months'`);
    return res.rowCount ?? 0;
  });
}
