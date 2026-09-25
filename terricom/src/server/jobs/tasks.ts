import { spawn } from 'node:child_process';
import { createHmac } from 'node:crypto';
import { and, eq, inArray, isNotNull, isNull, lt, lte, sql } from 'drizzle-orm';
import { parisDate } from '@/lib/format';
import { RETENTION } from '@/lib/constants';
import { purgeOldAuditEntries } from '../audit';
import { purgeExpiredRateLimits } from '../auth/rate-limit';
import { purgeExpiredSessions } from '../auth/session';
import { purgeOldEvents, rollupDay } from '../analytics';
import { invalidate } from '../cache';
import { db } from '../db';
import { claims, communes, establishments, healthProbes, jobApplications, media, posts, roleAssignments, territories, users } from '../db/schema';
import { env } from '../env';
import { geocode } from '../integrations/public-data';
import { logger } from '../logger';
import { deleteMediaFiles } from '../media';
import { sendEmail } from '../mail/send';
import { claimReminderTemplate, pendingClaimsDigestTemplate } from '../mail/templates';
import { enqueue, purgeFinishedJobs } from '../queue';
import { refreshCompleteness, refreshSearchKeywords } from '../services/establishments';
import { flagOverdueInvoices } from '../services/console-billing';
import { appUrl, portalUrl } from '../urls';

/**
 * Traitements exécutés par le worker. Chacun est idempotent : une tâche rejouée
 * (nouvelle tentative, deux workers) ne produit pas d'effet en double.
 */

/** Publications programmées arrivées à échéance (et modération préalable si le territoire l'exige). */
export async function publishDuePosts(): Promise<{ published: number; pending: number; archived: number }> {
  const due = await db
    .select({ p: posts, settings: territories.settings })
    .from(posts)
    .innerJoin(territories, eq(territories.id, posts.territoryId))
    .where(and(eq(posts.status, 'SCHEDULED'), lte(posts.publishAt, new Date())))
    .limit(500);
  let published = 0;
  let pending = 0;
  const touched = new Set<string>();
  for (const { p, settings } of due) {
    const needsModeration = (settings as { postModeration?: string }).postModeration === 'PRE' && p.authorType === 'ESTABLISHMENT' && !p.moderatedAt;
    const now = new Date();
    const res = await db
      .update(posts)
      .set(needsModeration ? { status: 'PENDING', updatedAt: now } : { status: 'PUBLISHED', publishedAt: now, updatedAt: now })
      .where(and(eq(posts.id, p.id), eq(posts.status, 'SCHEDULED')));
    if (!res.rowCount) continue;
    touched.add(p.territoryId);
    if (needsModeration) {
      pending++;
      continue;
    }
    published++;
    if (p.establishmentId) await db.update(establishments).set({ lastActivityAt: now }).where(eq(establishments.id, p.establishmentId));
    if (p.channels.includes('SOCIAL')) await enqueue('posts.social-sync', { postId: p.id }, { dedupeKey: `social:${p.id}` });
  }
  // Promotions et annonces expirées depuis plus de 30 jours : archivées (elles restent consultables dans l'espace pro).
  const archived = await db.execute(sql`
    update posts set status = 'ARCHIVED', updated_at = now()
    where status = 'PUBLISHED' and expires_at is not null and expires_at < now() - interval '30 days'`);
  for (const t of touched) invalidate(`feed:${t}`);
  return { published, pending, archived: archived.rowCount ?? 0 };
}

/**
 * Diffusion sur les réseaux sociaux (offre Communication) via un connecteur externe
 * (SOCIAL_WEBHOOK_URL : Make, n8n, Zapier ou service interne relié aux pages Facebook / Instagram).
 * Le message est signé (HMAC SHA-256) pour que le connecteur puisse en vérifier l'origine.
 */
export async function socialSync(postId: string): Promise<'sent' | 'skipped' | 'not-configured'> {
  const [row] = await db
    .select({ p: posts, est: establishments, territory: territories, communeSlug: communes.slug })
    .from(posts)
    .innerJoin(territories, eq(territories.id, posts.territoryId))
    .leftJoin(establishments, eq(establishments.id, posts.establishmentId))
    .leftJoin(communes, eq(communes.id, establishments.communeId))
    .where(eq(posts.id, postId))
    .limit(1);
  if (!row || row.p.status !== 'PUBLISHED' || row.p.socialSentAt || !row.p.channels.includes('SOCIAL')) return 'skipped';
  const url = process.env.SOCIAL_WEBHOOK_URL;
  if (!url) {
    logger.info('social.not_configured', { postId });
    return 'not-configured';
  }
  const payload = JSON.stringify({
    id: row.p.id,
    territory: { slug: row.territory.slug, name: row.territory.name },
    establishment: row.est ? { id: row.est.id, name: row.est.name } : null,
    title: row.p.title,
    texts: {
      facebook: row.p.variants.facebook ?? row.p.body,
      instagram: row.p.variants.instagram ?? row.p.body,
      linkedin: row.p.variants.linkedin ?? row.p.body,
    },
    imageUrl: row.p.imageUrl,
    link: portalUrl(row.territory, row.est && row.communeSlug ? `/${row.communeSlug}` : ''),
  });
  const signature = createHmac('sha256', process.env.SOCIAL_WEBHOOK_SECRET ?? env.SESSION_SECRET)
    .update(payload)
    .digest('hex');
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'content-type': 'application/json', 'x-terricom-signature': signature },
    body: payload,
    signal: AbortSignal.timeout(15_000),
  });
  if (!res.ok) throw new Error(`Connecteur réseaux sociaux : HTTP ${res.status}`);
  await db.update(posts).set({ socialSentAt: new Date() }).where(eq(posts.id, postId));
  return 'sent';
}

/** Ouverture et clôture des campagnes selon leurs dates (jour calendaire de Paris). */
export async function updateCampaignStatuses(): Promise<{ opened: number; closed: number }> {
  const today = parisDate();
  const opened = await db.execute(
    sql`update campaigns set status = 'ACTIVE', updated_at = now() where status = 'SCHEDULED' and starts_at <= ${today}::date and ends_at >= ${today}::date`,
  );
  const closed = await db.execute(
    sql`update campaigns set status = 'ENDED', updated_at = now() where status in ('ACTIVE', 'SCHEDULED') and ends_at < ${today}::date`,
  );
  if ((opened.rowCount ?? 0) + (closed.rowCount ?? 0) > 0) invalidate('portal:');
  return { opened: opened.rowCount ?? 0, closed: closed.rowCount ?? 0 };
}

/** Agrégats d'audience de la veille et du jour (idempotents). */
export async function rollupAnalytics(): Promise<void> {
  const today = parisDate();
  const d = new Date(`${today}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() - 1);
  await rollupDay(d.toISOString().slice(0, 10));
  await rollupDay(today);
}

/** Géocodage d'une fiche importée sans coordonnées (Base Adresse Nationale). */
export async function geocodeEstablishment(id: string): Promise<boolean> {
  const [e] = await db
    .select({
      id: establishments.id,
      street: establishments.street,
      postalCode: establishments.postalCode,
      lat: establishments.lat,
      commune: communes.name,
      insee: communes.inseeCode,
    })
    .from(establishments)
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .where(eq(establishments.id, id))
    .limit(1);
  if (!e || e.lat !== null || !e.street) return false;
  const point = await geocode(`${e.street} ${e.postalCode ?? ''} ${e.commune}`, e.insee);
  if (!point) return false;
  await db
    .update(establishments)
    .set({ lat: point.lat, lng: point.lng })
    .where(and(eq(establishments.id, id), isNull(establishments.lat)));
  return true;
}

/** Mots-clés de recherche et complétude des fiches modifiées récemment (ou de toutes). */
export async function refreshSearch(full = false): Promise<number> {
  const rows = await db
    .select({ id: establishments.id })
    .from(establishments)
    .where(full ? sql`true` : sql`${establishments.updatedAt} > now() - interval '2 hours'`)
    .limit(full ? 100_000 : 5000);
  for (const r of rows) {
    await refreshSearchKeywords(r.id);
    await refreshCompleteness(r.id);
  }
  return rows.length;
}

/**
 * Relances : (1) professionnels invités qui n'ont pas revendiqué leur fiche (au plus 3 envois,
 * espacés de 14 jours) ; (2) récapitulatif aux administrateurs des revendications en attente depuis plus de 24 h.
 */
export async function claimReminders(): Promise<{ reminders: number; digests: number }> {
  const candidates = await db
    .select({ e: establishments, territory: territories })
    .from(establishments)
    .innerJoin(territories, eq(territories.id, establishments.territoryId))
    .where(
      and(
        inArray(establishments.status, ['PRECREATED', 'TO_COMPLETE']),
        isNotNull(establishments.email),
        isNotNull(establishments.invitedAt),
        lt(establishments.invitedAt, new Date(Date.now() - 14 * 86_400_000)),
        lt(establishments.invitationCount, 3),
        eq(territories.status, 'ACTIVE'),
        sql`not exists (select 1 from company_members m where m.company_id = ${establishments.companyId})`,
        sql`not exists (select 1 from claims c where c.establishment_id = ${establishments.id} and c.status in ('PENDING', 'NEEDS_INFO'))`,
      ),
    )
    .limit(300);
  let reminders = 0;
  for (const { e, territory } of candidates) {
    const [{ views }] = (
      await db.execute<{ views: number }>(sql`
        select coalesce(sum(count), 0)::int as views from analytics_daily
        where establishment_id = ${e.id} and type = 'EST_VIEW' and day >= current_date - 30`)
    ).rows;
    await sendEmail({
      ...claimReminderTemplate({ to: e.email!, establishmentName: e.name, territory, url: appUrl(`/pro/revendiquer/${e.id}`), views: Number(views ?? 0) }),
      territoryId: territory.id,
    });
    await db
      .update(establishments)
      .set({ invitedAt: new Date(), invitationCount: sql`${establishments.invitationCount} + 1` })
      .where(eq(establishments.id, e.id));
    reminders++;
  }

  const pendingByTerritory = await db
    .select({ territoryId: claims.territoryId, n: sql<number>`count(*)::int`, oldest: sql<Date>`min(${claims.createdAt})` })
    .from(claims)
    .where(and(eq(claims.status, 'PENDING'), lt(claims.createdAt, new Date(Date.now() - 24 * 3_600_000))))
    .groupBy(claims.territoryId);
  let digests = 0;
  for (const row of pendingByTerritory) {
    const [territory] = await db.select().from(territories).where(eq(territories.id, row.territoryId)).limit(1);
    if (!territory) continue;
    const admins = await db
      .select({ email: users.email })
      .from(roleAssignments)
      .innerJoin(users, eq(users.id, roleAssignments.userId))
      .where(and(eq(roleAssignments.territoryId, territory.id), eq(roleAssignments.role, 'TERRITORY_ADMIN'), eq(users.status, 'ACTIVE')));
    const oldestHours = Math.round((Date.now() - new Date(row.oldest).getTime()) / 3_600_000);
    for (const a of admins) {
      await sendEmail({
        ...pendingClaimsDigestTemplate({ to: a.email, territory, count: Number(row.n), oldestHours, url: appUrl('/collectivite/moderation') }),
        territoryId: territory.id,
      });
      digests++;
    }
  }
  return { reminders, digests };
}

/** Purge de rétention (registre des traitements) : données personnelles et journaux techniques. */
export async function purgeRetention(): Promise<Record<string, number>> {
  const out: Record<string, number> = {};
  const run = async (key: string, fn: () => Promise<number>) => {
    try {
      out[key] = await fn();
    } catch (err) {
      logger.error('purge.failed', { key, err });
      out[key] = -1;
    }
  };
  const del = async (q: ReturnType<typeof sql>) => (await db.execute(q)).rowCount ?? 0;

  await run('sessions', purgeExpiredSessions);
  await run('rateLimits', purgeExpiredRateLimits);
  await run('tokens', () =>
    del(sql`delete from tokens where expires_at < now() - interval '30 days' or (used_at is not null and used_at < now() - interval '30 days')`),
  );
  await run('audit', purgeOldAuditEntries);
  await run('analyticsEvents', purgeOldEvents);
  await run('jobs', purgeFinishedJobs);
  await run('healthProbes', () => del(sql`delete from health_probes where at < now() - interval '90 days'`));
  await run('emails', () => del(sql`delete from emails where created_at < now() - interval '12 months'`));
  await run('messages', () => del(sql`delete from messages where purge_after < current_date`));
  await run('appointments', () => del(sql`delete from appointments where preferred_at < now() - make_interval(months => ${RETENTION.appointmentsMonths})`));
  await run('passports', () =>
    del(sql`delete from passports where coalesce(last_seen_at, created_at) < now() - make_interval(months => ${RETENTION.passportsMonths})`),
  );
  await run('subscribersPending', () => del(sql`delete from subscribers where status = 'PENDING' and created_at < now() - interval '30 days'`));
  await run('subscribersInactive', () =>
    del(sql`
      delete from subscribers s where s.status in ('CONFIRMED', 'UNSUBSCRIBED', 'BOUNCED')
        and coalesce(s.confirmed_at, s.created_at) < now() - make_interval(months => ${RETENTION.subscribersInactiveMonths})
        and not exists (select 1 from newsletter_deliveries d where d.subscriber_id = s.id and d.opened_at > now() - make_interval(months => ${RETENTION.subscribersInactiveMonths}))`),
  );
  // Clients d'entreprise : abonnements jamais confirmés effacés au bout de 30 jours (annoncé dans l'email de confirmation).
  await run('companyContactsPending', () =>
    del(sql`delete from company_contacts where subscribed and confirmed_at is null and coalesce(consent_at, created_at) < now() - interval '30 days'`),
  );
  await run('importBatches', () => del(sql`delete from import_batches where created_at < now() - interval '12 months'`));

  // Candidatures (avec leur CV) et justificatifs de revendication : suppression des fichiers puis des lignes.
  await run('applications', async () => {
    const old = await db
      .select({ id: jobApplications.id, cv: media })
      .from(jobApplications)
      .leftJoin(media, eq(media.id, jobApplications.cvMediaId))
      .where(lt(jobApplications.purgeAfter, parisDate()));
    for (const o of old) if (o.cv) await deleteMediaFiles(o.cv).catch(() => undefined);
    if (old.length) {
      await db.delete(jobApplications).where(
        inArray(
          jobApplications.id,
          old.map((o) => o.id),
        ),
      );
      const cvIds = old.map((o) => o.cv?.id).filter((x): x is string => Boolean(x));
      if (cvIds.length) await db.delete(media).where(inArray(media.id, cvIds));
    }
    return old.length;
  });
  await run('kbis', async () => {
    const old = await db
      .select({ claimId: claims.id, doc: media })
      .from(claims)
      .innerJoin(media, eq(media.id, claims.kbisMediaId))
      .where(and(isNotNull(claims.reviewedAt), lt(claims.reviewedAt, new Date(Date.now() - 365 * 86_400_000))));
    for (const o of old) {
      await deleteMediaFiles(o.doc).catch(() => undefined);
      await db.update(claims).set({ kbisMediaId: null }).where(eq(claims.id, o.claimId));
      await db.delete(media).where(eq(media.id, o.doc.id));
    }
    return old.length;
  });
  return out;
}

export async function billingDaily(): Promise<number> {
  return flagOverdueInvoices();
}

/** Sonde de disponibilité de bout en bout (par l'adresse publique) : alimente le taux de disponibilité de la console. */
export async function healthProbe(): Promise<{ ok: boolean; ms: number }> {
  const target = `${(process.env.HEALTH_PROBE_URL ?? env.APP_URL).replace(/\/$/, '')}/api/ready`;
  const t0 = performance.now();
  let ok = false;
  let detail: string | null = null;
  try {
    const res = await fetch(target, { signal: AbortSignal.timeout(10_000), headers: { 'user-agent': 'terricom-monitor/1.0' } });
    ok = res.ok;
    if (!ok) detail = `HTTP ${res.status}`;
  } catch (err) {
    detail = err instanceof Error ? err.message.slice(0, 250) : 'erreur';
  }
  const ms = Math.round(performance.now() - t0);
  await db.insert(healthProbes).values({ ok, latencyMs: ms, detail });
  if (!ok) logger.warn('health.probe_failed', { target, detail, ms });
  return { ok, ms };
}

/** Démonstration uniquement : remise à zéro nocturne du jeu de données (base, migrations, graine). */
export async function demoReset(): Promise<void> {
  if (!env.DEMO_MODE) return;
  await new Promise<void>((resolve, reject) => {
    const child = spawn('npm', ['run', 'db:reset'], { stdio: 'inherit', env: process.env });
    child.on('exit', (code) => (code === 0 ? resolve() : reject(new Error(`db:reset a échoué (code ${code})`))));
    child.on('error', reject);
  });
}
