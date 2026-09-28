import { and, count, desc, eq, gte, ilike, lt, or, type SQL } from 'drizzle-orm';
import { sql } from 'drizzle-orm';
import { db } from '../db';
import { auditLog, privacyRequests, territories } from '../db/schema';
import { env } from '../env';
import { RETENTION, type AuditCategory } from '@/lib/constants';
import { fromParisLocal } from '@/lib/format';

/** Console plateforme — journal d'audit, indicateurs de sécurité et de conformité RGPD (S3). */

export type AuditFilters = { category?: AuditCategory; territoryId?: string; q?: string; from?: string; to?: string; page?: number };

export const AUDIT_PAGE_SIZE = 50;

export function auditWhere(f: AuditFilters): SQL | undefined {
  const conds: SQL[] = [];
  if (f.category) conds.push(eq(auditLog.category, f.category));
  if (f.territoryId) conds.push(eq(auditLog.territoryId, f.territoryId));
  if (f.q) {
    const like = `%${f.q.replace(/[%_\\]/g, (m) => `\\${m}`)}%`;
    conds.push(or(ilike(auditLog.summary, like), ilike(auditLog.actorLabel, like), ilike(auditLog.action, like))!);
  }
  if (f.from && /^\d{4}-\d{2}-\d{2}$/.test(f.from)) conds.push(gte(auditLog.occurredAt, fromParisLocal(f.from, '00:00')));
  if (f.to && /^\d{4}-\d{2}-\d{2}$/.test(f.to)) conds.push(lt(auditLog.occurredAt, new Date(fromParisLocal(f.to, '23:59').getTime() + 60_000)));
  return conds.length ? and(...conds) : undefined;
}

export async function auditEntries(f: AuditFilters) {
  const where = auditWhere(f);
  const page = Math.max(1, f.page ?? 1);
  const [rows, [{ n }]] = await Promise.all([
    db
      .select({
        id: auditLog.id,
        occurredAt: auditLog.occurredAt,
        actorLabel: auditLog.actorLabel,
        category: auditLog.category,
        action: auditLog.action,
        summary: auditLog.summary,
        territoryName: territories.name,
      })
      .from(auditLog)
      .leftJoin(territories, eq(territories.id, auditLog.territoryId))
      .where(where)
      .orderBy(desc(auditLog.id))
      .limit(AUDIT_PAGE_SIZE)
      .offset((page - 1) * AUDIT_PAGE_SIZE),
    db.select({ n: count() }).from(auditLog).where(where),
  ]);
  return { rows, total: Number(n), page, pages: Math.max(1, Math.ceil(Number(n) / AUDIT_PAGE_SIZE)) };
}

const EXAMPLE_SECRETS = ['change-me-please-change-me-please-32b', 'MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=', 'change-me-analytics-salt'];

export function secretsConfigured(): boolean {
  return ![env.SESSION_SECRET, env.DATA_ENCRYPTION_KEY, env.ANALYTICS_SALT].some((s) => EXAMPLE_SECRETS.includes(s));
}

export async function securityStats() {
  const [r] = (
    await db.execute<{ admins: number; admins_mfa: number; sessions: number; blocked: number; locked_now: number }>(sql`
      with staff as (
        select distinct u.id, u.mfa_enabled from users u join role_assignments r on r.user_id = u.id
        where r.role in ('PLATFORM_ADMIN', 'PLATFORM_SUPPORT', 'PLATFORM_SALES', 'TERRITORY_ADMIN', 'COMMUNE_ADMIN') and u.status = 'ACTIVE'
      )
      select
        (select count(*)::int from staff) as admins,
        (select count(*)::int from staff where mfa_enabled) as admins_mfa,
        (select count(*)::int from sessions where expires_at > now() and last_seen_at > now() - interval '24 hours') as sessions,
        (select count(*)::int from audit_log where occurred_at > now() - interval '24 hours'
          and action in ('auth.locked', 'auth.mfa_failed', 'auth.rate_limited', 'security.honeypot')) as blocked,
        (select count(*)::int from users where locked_until > now()) as locked_now
    `)
  ).rows;
  return {
    mfaPct: r && r.admins ? r.admins_mfa / r.admins : 1,
    sessions: r?.sessions ?? 0,
    blocked: r?.blocked ?? 0,
    lockedNow: r?.locked_now ?? 0,
    antivirus: Boolean(process.env.CLAMAV_HOST),
    secrets: secretsConfigured(),
  };
}

export async function rgpdStats() {
  const [r] = (
    await db.execute<{ consents: number; exports: number; deletions: number; open: number }>(sql`
      select
        ((select count(*)::int from subscribers where status = 'CONFIRMED') + (select count(*)::int from company_contacts where subscribed and consent_at is not null)) as consents,
        (select count(*)::int from privacy_requests where kind = 'EXPORT' and status = 'DONE') as exports,
        (select count(*)::int from privacy_requests where kind = 'DELETE' and status = 'DONE') as deletions,
        (select count(*)::int from privacy_requests where status = 'OPEN') as open
    `)
  ).rows;
  return {
    consents: r?.consents ?? 0,
    exports: r?.exports ?? 0,
    deletions: r?.deletions ?? 0,
    open: r?.open ?? 0,
    retentionMonths: RETENTION.subscribersInactiveMonths,
  };
}

export async function openPrivacyRequests() {
  return db
    .select({
      id: privacyRequests.id,
      number: privacyRequests.number,
      email: privacyRequests.email,
      kind: privacyRequests.kind,
      createdAt: privacyRequests.createdAt,
      territoryName: territories.name,
    })
    .from(privacyRequests)
    .leftJoin(territories, eq(territories.id, privacyRequests.territoryId))
    .where(eq(privacyRequests.status, 'OPEN'))
    .orderBy(privacyRequests.createdAt)
    .limit(20);
}
