import { and, asc, count, desc, eq, gte, inArray, isNull, lte, ne, or, sql, type SQL } from 'drizzle-orm';
import { PUBLIC_STATUSES } from '@/lib/constants';
import { fmtEventBadge } from '@/lib/format';
import { sized } from '@/lib/images';
import { hmacSha256, randomToken, safeEqual } from '../crypto';
import { db } from '../db';
import {
  audiences,
  categories,
  communes,
  establishments,
  events,
  newsletterDeliveries,
  newsletters,
  posts,
  subscriberAudiences,
  subscribers,
  type NewsletterBlock,
  type TerritorySettings,
} from '../db/schema';
import { env } from '../env';
import { logger } from '../logger';
import { renderNewsletter, type NewsletterView, type ResolvedBlock } from '../mail/newsletter';
import { sendEmail } from '../mail/send';
import { enqueue } from '../queue';
import { appUrl, portalUrl } from '../urls';
import type { Territory } from './territories';

/**
 * Lettres d'information territoriales : composition, destinataires (double opt-in),
 * envoi par lots via la file de tâches, mesure d'ouverture et de clics.
 */

type TerritoryLike = Pick<Territory, 'id' | 'slug' | 'name' | 'primaryHost' | 'colorPrimary' | 'colorAccent' | 'settings'>;

export function newsletterName(t: TerritoryLike): string {
  const s = (t.settings ?? {}) as TerritorySettings;
  return s.newsletterName ?? `${t.name} · Le week-end local`;
}

/** Transforme les blocs enregistrés en contenus affichables (fiches, publications, événements). */
export async function resolveBlocks(territory: TerritoryLike, blocks: NewsletterBlock[]): Promise<ResolvedBlock[]> {
  const estIds = blocks.flatMap((b) => (b.type === 'establishments' ? b.ids : []));
  const postIds = blocks.flatMap((b) => (b.type === 'posts' ? b.ids : []));
  const eventIds = blocks.flatMap((b) => (b.type === 'events' ? b.ids : []));
  const [ests, postRows, eventRows] = await Promise.all([
    estIds.length
      ? db
          .select({
            id: establishments.id,
            name: establishments.name,
            tagline: establishments.tagline,
            activity: establishments.activityLabel,
            coverUrl: establishments.coverUrl,
            slug: establishments.slug,
            communeSlug: communes.slug,
            communeName: communes.name,
            categorySlug: categories.slug,
            lastPost: sql<
              string | null
            >`(select coalesce(p.promo_label || ' · ', '') || p.title from posts p where p.establishment_id = "establishments"."id" and p.status = 'PUBLISHED' and p.published_at >= now() - interval '30 days' order by p.published_at desc limit 1)`,
          })
          .from(establishments)
          .innerJoin(communes, eq(communes.id, establishments.communeId))
          .innerJoin(categories, eq(categories.id, establishments.categoryId))
          .where(and(eq(establishments.territoryId, territory.id), inArray(establishments.id, estIds), inArray(establishments.status, PUBLIC_STATUSES)))
      : Promise.resolve([]),
    postIds.length
      ? db
          .select({
            id: posts.id,
            title: posts.title,
            imageUrl: posts.imageUrl,
            estName: establishments.name,
            estCover: establishments.coverUrl,
            estSlug: establishments.slug,
            communeSlug: communes.slug,
            categorySlug: categories.slug,
          })
          .from(posts)
          .leftJoin(establishments, eq(establishments.id, posts.establishmentId))
          .leftJoin(communes, eq(communes.id, establishments.communeId))
          .leftJoin(categories, eq(categories.id, establishments.categoryId))
          .where(and(eq(posts.territoryId, territory.id), inArray(posts.id, postIds), eq(posts.status, 'PUBLISHED')))
      : Promise.resolve([]),
    eventIds.length
      ? db
          .select({
            id: events.id,
            title: events.title,
            slug: events.slug,
            startsAt: events.startsAt,
            endsAt: events.endsAt,
            locationName: events.locationName,
            imageUrl: events.imageUrl,
          })
          .from(events)
          .where(and(eq(events.territoryId, territory.id), inArray(events.id, eventIds), eq(events.status, 'PUBLISHED')))
      : Promise.resolve([]),
  ]);
  const estById = new Map(ests.map((e) => [e.id, e]));
  const postById = new Map(postRows.map((p) => [p.id, p]));
  const eventById = new Map(eventRows.map((e) => [e.id, e]));
  const out: ResolvedBlock[] = [];
  for (const b of blocks) {
    if (b.type === 'text') out.push({ type: 'text', text: b.text });
    else if (b.type === 'cta')
      out.push({
        type: 'cta',
        label: b.label,
        url: /^https?:\/\//.test(b.url) ? b.url : portalUrl(territory, b.url.replace(new RegExp(`^/${territory.slug}`), '')),
      });
    else if (b.type === 'establishments') {
      const items = b.ids
        .map((id) => estById.get(id))
        .filter(Boolean)
        .map((e) => ({
          name: e!.name,
          text: e!.lastPost ?? e!.tagline ?? `${e!.activity ?? ''} · ${e!.communeName}`,
          image: sized(e!.coverUrl, 200, 160),
          url: portalUrl(territory, `/${e!.communeSlug}/${e!.categorySlug}/${e!.slug}`),
        }));
      if (items.length) out.push({ type: 'cards', title: b.title, items });
    } else if (b.type === 'posts') {
      const items = b.ids
        .map((id) => postById.get(id))
        .filter(Boolean)
        .map((p) => ({
          name: p!.estName ?? p!.title,
          text: p!.estName ? p!.title : '',
          image: sized(p!.imageUrl ?? p!.estCover, 200, 160),
          url: p!.estSlug ? portalUrl(territory, `/${p!.communeSlug}/${p!.categorySlug}/${p!.estSlug}`) : portalUrl(territory, '/actualites'),
        }));
      if (items.length) out.push({ type: 'cards', title: b.title, items });
    } else if (b.type === 'events') {
      const items = b.ids
        .map((id) => eventById.get(id))
        .filter(Boolean)
        .map((e) => ({
          name: e!.title,
          text: `${fmtEventBadge(e!.startsAt, e!.endsAt)}${e!.locationName ? ` · ${e!.locationName}` : ''}`,
          image: sized(e!.imageUrl, 200, 160),
          url: portalUrl(territory, `/agenda/${e!.slug}`),
        }));
      if (items.length) out.push({ type: 'cards', title: b.title, items });
    }
  }
  return out;
}

export type NewsletterRow = typeof newsletters.$inferSelect;

/** Aperçu (sans mesure) d'une lettre. */
export async function previewHtml(n: NewsletterRow, territory: TerritoryLike): Promise<string> {
  const view: NewsletterView = {
    brandName: territory.name,
    number: n.number,
    color: territory.colorPrimary,
    accent: territory.colorAccent,
    title: n.title,
    intro: n.intro,
    preheader: n.preheader,
    heroImageUrl: sized(n.heroImageUrl, 1100, 500),
    blocks: await resolveBlocks(territory, n.blocks),
    newsletterName: newsletterName(territory),
    unsubscribeUrl: portalUrl(territory, '/newsletter/desinscription'),
    preferencesUrl: portalUrl(territory, '/newsletter/desinscription'),
  };
  return renderNewsletter(view).html;
}

/** Audiences du territoire avec le nombre de destinataires (abonnés confirmés, zones, professionnels). */
export async function audienceStats(territoryId: string) {
  const list = await db
    .select({
      id: audiences.id,
      name: audiences.name,
      description: audiences.description,
      kind: audiences.kind,
      communeId: audiences.communeId,
      criteria: audiences.criteria,
      isDefault: audiences.isDefault,
    })
    .from(audiences)
    .where(eq(audiences.territoryId, territoryId))
    .orderBy(asc(audiences.sortOrder), asc(audiences.name));
  const sizes = await Promise.all(list.map((a) => recipientCount(territoryId, [a.id])));
  return list.map((a, i) => ({ ...a, n: sizes[i] }));
}

type AudienceRow = { id: string; kind: string; communeId: string | null; criteria: { communeIds?: string[]; families?: string[]; categoryIds?: string[] } };

/**
 * Adresses des professionnels du territoire (gérants et collaborateurs des fiches actives),
 * éventuellement limités à des familles d'activité ou à des catégories.
 */
function proEmails(territoryId: string, a: AudienceRow): SQL {
  const fam = a.criteria.families?.length
    ? sql`and k.family in (${sql.join(
        a.criteria.families.map((f) => sql`${f}`),
        sql`, `,
      )})`
    : sql``;
  const cat = a.criteria.categoryIds?.length
    ? sql`and e.category_id in (${sql.join(
        a.criteria.categoryIds.map((c) => sql`${c}::uuid`),
        sql`, `,
      )})`
    : sql``;
  return sql`select lower(u.email) from users u join company_members cm on cm.user_id = u.id join establishments e on e.company_id = cm.company_id
    join categories k on k.id = e.category_id
    where e.territory_id = ${territoryId} and e.status in ('CLAIMED', 'VALIDATED', 'TO_COMPLETE') and u.deleted_at is null ${fam} ${cat}`;
}

async function loadAudiences(territoryId: string, audienceIds: string[]): Promise<AudienceRow[]> {
  if (!audienceIds.length) return [];
  return db
    .select({ id: audiences.id, kind: audiences.kind, communeId: audiences.communeId, criteria: audiences.criteria })
    .from(audiences)
    .where(and(eq(audiences.territoryId, territoryId), inArray(audiences.id, audienceIds)));
}

/**
 * Condition SQL « abonné destinataire » : membre inscrit de l'audience, habitant d'une commune de
 * la zone, ou professionnel correspondant aux critères.
 */
function recipientWhere(territoryId: string, list: AudienceRow[]): SQL {
  const ids = list.map((a) => a.id);
  const zone = [...new Set(list.filter((a) => a.kind === 'COMMUNE').flatMap((a) => [...(a.criteria.communeIds ?? []), ...(a.communeId ? [a.communeId] : [])]))];
  const pros = list.filter((a) => a.kind === 'BUSINESSES');
  return and(
    eq(subscribers.territoryId, territoryId),
    eq(subscribers.status, 'CONFIRMED'),
    or(
      sql`exists (select 1 from subscriber_audiences sa where sa.subscriber_id = ${subscribers.id} and sa.audience_id in (${sql.join(
        ids.map((i) => sql`${i}::uuid`),
        sql`, `,
      )}))`,
      zone.length ? inArray(subscribers.communeId, zone) : undefined,
      ...pros.map((a) => sql`lower(${subscribers.email}) in (${proEmails(territoryId, a)})`),
    ),
  )!;
}

/**
 * Les professionnels visés deviennent des abonnés « PRO » (information de la collectivité aux
 * entreprises, intérêt légitime) : ils reçoivent le lien de désinscription, qui est ensuite respecté.
 */
async function syncProSubscribers(territoryId: string, list: AudienceRow[]): Promise<void> {
  for (const a of list.filter((x) => x.kind === 'BUSINESSES')) {
    const missing = await db.execute<{ email: string }>(sql`select distinct x.email from (${proEmails(territoryId, a)}) as x(email)
      where not exists (select 1 from subscribers s where s.territory_id = ${territoryId} and lower(s.email) = x.email)`);
    for (const r of missing.rows)
      await db
        .insert(subscribers)
        .values({
          territoryId,
          email: r.email,
          status: 'CONFIRMED',
          source: 'PRO',
          consentText:
            'Professionnel référencé sur le portail : informations de la collectivité aux entreprises (intérêt légitime, désinscription en un clic).',
          confirmedAt: new Date(),
          unsubscribeToken: randomToken(24),
        })
        .onConflictDoNothing();
  }
}

/** Nombre de destinataires distincts des audiences choisies (y compris les professionnels pas encore abonnés). */
export async function recipientCount(territoryId: string, audienceIds: string[]): Promise<number> {
  const list = await loadAudiences(territoryId, audienceIds);
  if (!list.length) return 0;
  const [r] = await db
    .select({ n: sql<number>`count(distinct lower(${subscribers.email}))::int` })
    .from(subscribers)
    .where(recipientWhere(territoryId, list));
  let n = r?.n ?? 0;
  for (const a of list.filter((x) => x.kind === 'BUSINESSES')) {
    const [m] = (
      await db.execute<{ n: number }>(sql`select count(distinct x.email)::int as n from (${proEmails(territoryId, a)}) as x(email)
        where not exists (select 1 from subscribers s where s.territory_id = ${territoryId} and lower(s.email) = x.email)`)
    ).rows;
    n += Number(m?.n ?? 0);
  }
  return n;
}

/** Proposition de contenu : événements du week-end et nouveautés des commerces. */
export async function autoCompose(territory: TerritoryLike, communeIds: string[] | null) {
  const now = new Date();
  const in10 = new Date(now.getTime() + 10 * 86_400_000);
  const [upcoming, recent] = await Promise.all([
    db
      .select({ id: events.id, title: events.title, startsAt: events.startsAt, locationName: events.locationName, imageUrl: events.imageUrl })
      .from(events)
      .where(
        and(
          eq(events.territoryId, territory.id),
          eq(events.status, 'PUBLISHED'),
          gte(events.startsAt, now),
          lte(events.startsAt, in10),
          communeIds ? inArray(events.communeId, communeIds) : undefined,
        ),
      )
      .orderBy(desc(events.isFeatured), asc(events.startsAt))
      .limit(3),
    db
      .select({ estId: posts.establishmentId, title: posts.title })
      .from(posts)
      .innerJoin(establishments, eq(establishments.id, posts.establishmentId))
      .where(
        and(
          eq(posts.territoryId, territory.id),
          eq(posts.status, 'PUBLISHED'),
          gte(posts.publishedAt, new Date(now.getTime() - 14 * 86_400_000)),
          inArray(posts.kind, ['PROMO', 'NOUVEAUTE', 'EVENT', 'NEWS']),
          inArray(establishments.status, PUBLIC_STATUSES),
          communeIds ? inArray(posts.communeId, communeIds) : undefined,
        ),
      )
      .orderBy(desc(posts.publishedAt))
      .limit(12),
  ]);
  const estIds = [...new Set(recent.map((r) => r.estId).filter(Boolean) as string[])].slice(0, 3);
  const lead = upcoming[0];
  const blocks: NewsletterBlock[] = [];
  if (upcoming.length > 1) blocks.push({ type: 'events', ids: upcoming.slice(1).map((e) => e.id), title: 'Aussi au programme' });
  if (estIds.length)
    blocks.push({
      type: 'establishments',
      ids: estIds,
      title: `${estIds.length === 1 ? 'Une adresse' : `${estIds.length === 2 ? 'Deux' : 'Trois'} adresses`} à découvrir`,
    });
  blocks.push({ type: 'cta', label: "Voir tout l'agenda", url: '/agenda' });
  const title = lead ? `Ce week-end : ${lead.title.charAt(0).toLowerCase()}${lead.title.slice(1)}` : `Les nouveautés de ${territory.name}`;
  const intro = lead
    ? `${fmtEventBadge(lead.startsAt, null)}${lead.locationName ? `, ${lead.locationName}` : ''}. Et pour prolonger, ${estIds.length} adresse${estIds.length > 1 ? 's' : ''} qui ont des nouveautés cette semaine :`
    : `Promotions, nouveautés, rendez-vous : voici ce qui bouge cette semaine chez vos commerçants, artisans et producteurs.`;
  return { title, intro, subject: title, heroImageUrl: lead?.imageUrl ?? null, blocks };
}

// ─── Envoi ─────────────────────────────────────────────────────────────────

function signedClick(token: string, url: string): string {
  const sig = hmacSha256(env.SESSION_SECRET, `${token}|${url}`).slice(0, 16);
  return appUrl(`/api/n/c/${token}?u=${encodeURIComponent(url)}&s=${sig}`);
}

export function verifyClick(token: string, url: string, sig: string): boolean {
  return safeEqual(hmacSha256(env.SESSION_SECRET, `${token}|${url}`).slice(0, 16), sig);
}

/** Prépare les envois (une ligne par destinataire) puis confie l'envoi au worker, par lots. */
export async function dispatchNewsletter(newsletterId: string): Promise<number> {
  const [n] = await db.select().from(newsletters).where(eq(newsletters.id, newsletterId)).limit(1);
  if (!n || (n.status !== 'SCHEDULED' && n.status !== 'SENDING')) return 0;
  const list = await loadAudiences(n.territoryId, n.audienceIds);
  await syncProSubscribers(n.territoryId, list);
  const rows = list.length
    ? await db.selectDistinct({ id: subscribers.id, email: subscribers.email }).from(subscribers).where(recipientWhere(n.territoryId, list))
    : [];
  for (let i = 0; i < rows.length; i += 500) {
    await db
      .insert(newsletterDeliveries)
      .values(rows.slice(i, i + 500).map((r) => ({ newsletterId: n.id, subscriberId: r.id, email: r.email, token: randomToken(24) })))
      .onConflictDoNothing();
  }
  const [{ total }] = await db.select({ total: count() }).from(newsletterDeliveries).where(eq(newsletterDeliveries.newsletterId, n.id));
  await db
    .update(newsletters)
    .set({ status: 'SENDING', statsRecipients: Number(total), updatedAt: new Date() })
    .where(eq(newsletters.id, n.id));
  await enqueue('newsletter.send-batch', { newsletterId: n.id }, { dedupeKey: `nl-batch:${n.id}:0` });
  return Number(total);
}

/** Envoie un lot ; se replanifie tant qu'il reste des destinataires. */
export async function sendNewsletterBatch(newsletterId: string, size = 200): Promise<{ sent: number; remaining: number }> {
  const [n] = await db.select().from(newsletters).where(eq(newsletters.id, newsletterId)).limit(1);
  if (!n || n.status !== 'SENDING') return { sent: 0, remaining: 0 };
  const { getTerritoryById } = await import('./territories');
  const territory = await getTerritoryById(n.territoryId);
  if (!territory) return { sent: 0, remaining: 0 };
  const batch = await db
    .select({ d: newsletterDeliveries, unsubscribeToken: subscribers.unsubscribeToken })
    .from(newsletterDeliveries)
    .leftJoin(subscribers, eq(subscribers.id, newsletterDeliveries.subscriberId))
    .where(and(eq(newsletterDeliveries.newsletterId, n.id), eq(newsletterDeliveries.status, 'QUEUED')))
    .limit(size);
  const blocks = await resolveBlocks(territory, n.blocks);
  const name = newsletterName(territory);
  let sent = 0;
  for (const { d, unsubscribeToken } of batch) {
    try {
      const unsubscribeUrl = portalUrl(territory, `/newsletter/desinscription?token=${unsubscribeToken ?? ''}`);
      const { html, text } = renderNewsletter({
        brandName: territory.name,
        number: n.number,
        color: territory.colorPrimary,
        accent: territory.colorAccent,
        title: n.title,
        intro: n.intro,
        preheader: n.preheader,
        heroImageUrl: sized(n.heroImageUrl, 1100, 500),
        blocks,
        newsletterName: name,
        unsubscribeUrl,
        preferencesUrl: unsubscribeUrl,
        link: (url) => signedClick(d.token, url),
        openPixelUrl: appUrl(`/api/n/o/${d.token}`),
      });
      await sendEmail({
        to: d.email,
        subject: n.subject,
        html,
        text,
        template: 'newsletter',
        territoryId: n.territoryId,
        headers: {
          'List-Unsubscribe': `<${appUrl(`/api/newsletter/unsubscribe?token=${unsubscribeToken ?? ''}`)}>`,
          'List-Unsubscribe-Post': 'List-Unsubscribe=One-Click',
        },
      });
      await db.update(newsletterDeliveries).set({ status: 'SENT', sentAt: new Date() }).where(eq(newsletterDeliveries.id, d.id));
      sent++;
    } catch (err) {
      logger.warn('newsletter.delivery_failed', { id: d.id, err: err instanceof Error ? err.message : String(err) });
      await db
        .update(newsletterDeliveries)
        .set({ status: 'FAILED', error: String(err).slice(0, 500) })
        .where(eq(newsletterDeliveries.id, d.id));
    }
  }
  const [{ remaining }] = await db
    .select({ remaining: count() })
    .from(newsletterDeliveries)
    .where(and(eq(newsletterDeliveries.newsletterId, n.id), eq(newsletterDeliveries.status, 'QUEUED')));
  await db
    .update(newsletters)
    .set({
      statsSent: sql`${newsletters.statsSent} + ${sent}`,
      ...(Number(remaining) === 0 ? { status: 'SENT' as const, sentAt: new Date() } : {}),
      updatedAt: new Date(),
    })
    .where(eq(newsletters.id, n.id));
  if (Number(remaining) > 0) await enqueue('newsletter.send-batch', { newsletterId: n.id }, { runAt: new Date(Date.now() + 2000) });
  return { sent, remaining: Number(remaining) };
}

/** Lettres programmées dont l'heure est venue (tâche périodique). */
export async function dispatchDueNewsletters(): Promise<number> {
  const due = await db
    .select({ id: newsletters.id })
    .from(newsletters)
    .where(and(eq(newsletters.status, 'SCHEDULED'), lte(newsletters.scheduledAt, new Date()), isNull(newsletters.companyId)));
  for (const n of due) await dispatchNewsletter(n.id);
  return due.length;
}

export async function trackOpen(token: string): Promise<void> {
  if (!/^[A-Za-z0-9_-]{16,64}$/.test(token)) return;
  const [d] = await db
    .update(newsletterDeliveries)
    .set({ openedAt: new Date() })
    .where(and(eq(newsletterDeliveries.token, token), isNull(newsletterDeliveries.openedAt)))
    .returning({ newsletterId: newsletterDeliveries.newsletterId });
  if (d)
    await db
      .update(newsletters)
      .set({ statsOpens: sql`${newsletters.statsOpens} + 1` })
      .where(eq(newsletters.id, d.newsletterId));
}

export async function trackClick(token: string): Promise<void> {
  if (!/^[A-Za-z0-9_-]{16,64}$/.test(token)) return;
  const [d] = await db
    .update(newsletterDeliveries)
    .set({ clickedAt: new Date(), openedAt: sql`coalesce(${newsletterDeliveries.openedAt}, now())` })
    .where(and(eq(newsletterDeliveries.token, token), isNull(newsletterDeliveries.clickedAt)))
    .returning({ newsletterId: newsletterDeliveries.newsletterId });
  if (d)
    await db
      .update(newsletters)
      .set({ statsClicks: sql`${newsletters.statsClicks} + 1` })
      .where(eq(newsletters.id, d.newsletterId));
}

/** Taux cumulés des dernières lettres envoyées (ouverture, clics, désinscriptions). */
export async function recentRates(territoryId: string) {
  const [r] = await db
    .select({
      recipients: sql<number>`coalesce(sum(${newsletters.statsRecipients}), 0)::int`,
      opens: sql<number>`coalesce(sum(${newsletters.statsOpens}), 0)::int`,
      clicks: sql<number>`coalesce(sum(${newsletters.statsClicks}), 0)::int`,
      unsubscribes: sql<number>`coalesce(sum(${newsletters.statsUnsubscribes}), 0)::int`,
    })
    .from(newsletters)
    .where(
      and(
        eq(newsletters.territoryId, territoryId),
        eq(newsletters.status, 'SENT'),
        isNull(newsletters.companyId),
        gte(newsletters.sentAt, new Date(Date.now() - 90 * 86_400_000)),
      ),
    );
  const base = Math.max(1, r?.recipients ?? 0);
  return { opens: (r?.opens ?? 0) / base, clicks: (r?.clicks ?? 0) / base, unsubscribes: (r?.unsubscribes ?? 0) / base, hasData: (r?.recipients ?? 0) > 0 };
}

export async function nextNumber(territoryId: string): Promise<number> {
  const [r] = await db
    .select({ n: sql<number>`coalesce(max(${newsletters.number}), 0)::int` })
    .from(newsletters)
    .where(and(eq(newsletters.territoryId, territoryId), isNull(newsletters.companyId), ne(newsletters.status, 'CANCELLED')));
  return (r?.n ?? 0) + 1;
}
