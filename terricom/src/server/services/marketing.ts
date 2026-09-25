import { and, desc, eq, sql } from 'drizzle-orm';
import { cache } from 'react';
import { db } from '../db';
import { campaigns, categories, communes, companyMembers, establishments, territories, users } from '../db/schema';
import { portalUrl } from '../urls';

/**
 * Données du site de la marque : territoire pilote mis en avant, liens vers ses écrans
 * et chiffres réels (fiches, communes, territoires), jamais de chiffres inventés.
 */

export type Showcase = {
  territory: { slug: string; name: string; primaryHost: string | null; colorPrimary: string; heroImageUrl: string | null };
  establishments: number;
  communes: number;
  links: {
    home: string;
    explore: string;
    fiche: string | null;
    commune: string | null;
    campaign: string | null;
    circuits: string;
    agenda: string;
    jobs: string;
  };
};

export const pilotShowcase = cache(async (): Promise<Showcase | null> => {
  const [t] = await db
    .select()
    .from(territories)
    .where(sql`${territories.status} in ('ACTIVE', 'ONBOARDING')`)
    .orderBy(desc(territories.isPilot), sql`${territories.status} = 'ACTIVE' desc`, territories.createdAt)
    .limit(1);
  if (!t) return null;
  const [[counts], [demoEst], [camp]] = await Promise.all([
    db
      .execute<{ establishments: number; communes: number }>(
        sql`
      select (select count(*)::int from establishments where territory_id = ${t.id} and status <> 'ARCHIVED') as establishments,
             (select count(*)::int from commune_memberships where territory_id = ${t.id} and valid_to is null) as communes`,
      )
      .then((r) => r.rows),
    // Fiche de référence : celle du compte professionnel de démonstration, sinon la fiche validée la plus consultée.
    db
      .select({ slug: establishments.slug, communeSlug: communes.slug, categorySlug: categories.slug })
      .from(establishments)
      .innerJoin(communes, eq(communes.id, establishments.communeId))
      .innerJoin(categories, eq(categories.id, establishments.categoryId))
      .leftJoin(companyMembers, eq(companyMembers.companyId, establishments.companyId))
      .leftJoin(users, eq(users.id, companyMembers.userId))
      .where(and(eq(establishments.territoryId, t.id), eq(establishments.status, 'VALIDATED')))
      .orderBy(sql`${users.email} = 'sophie@boulangerie-martin.fr' desc nulls last`, desc(establishments.completeness))
      .limit(1),
    db
      .select({ slug: campaigns.slug })
      .from(campaigns)
      .where(and(eq(campaigns.territoryId, t.id), sql`${campaigns.status} in ('ACTIVE', 'SCHEDULED')`))
      .orderBy(sql`${campaigns.slug} = ${(t.settings as { featuredCampaignSlug?: string }).featuredCampaignSlug ?? ''} desc`, desc(campaigns.startsAt))
      .limit(1),
  ]);
  const u = (p: string) => portalUrl(t, p);
  return {
    territory: { slug: t.slug, name: t.name, primaryHost: t.primaryHost, colorPrimary: t.colorPrimary, heroImageUrl: t.heroImageUrl },
    establishments: counts?.establishments ?? 0,
    communes: counts?.communes ?? 0,
    links: {
      home: u(''),
      explore: u('/explorer'),
      fiche: demoEst ? u(`/${demoEst.communeSlug}/${demoEst.categorySlug}/${demoEst.slug}`) : null,
      commune: demoEst ? u(`/${demoEst.communeSlug}`) : null,
      campaign: camp ? u(`/campagnes/${camp.slug}`) : null,
      circuits: u('/circuits'),
      agenda: u('/agenda'),
      jobs: u('/emploi'),
    },
  };
});

export const platformNumbers = cache(async () => {
  const [r] = (
    await db.execute<{ territories: number; establishments: number; communes: number; claimed: number }>(sql`
      select (select count(*)::int from territories where status in ('ACTIVE', 'ONBOARDING')) as territories,
             (select count(*)::int from establishments e join territories t on t.id = e.territory_id where t.status in ('ACTIVE', 'ONBOARDING') and e.status <> 'ARCHIVED') as establishments,
             (select count(*)::int from commune_memberships m join territories t on t.id = m.territory_id where m.valid_to is null and t.status in ('ACTIVE', 'ONBOARDING')) as communes,
             (select count(*)::int from establishments e where e.status in ('CLAIMED', 'VALIDATED')) as claimed`)
  ).rows;
  return { territories: r?.territories ?? 0, establishments: r?.establishments ?? 0, communes: r?.communes ?? 0, claimed: r?.claimed ?? 0 };
});

/** Territoires en ligne (page « Territoires ») avec leur portail et quelques chiffres. */
export async function liveTerritories() {
  const rows = await db.execute<{
    id: string;
    slug: string;
    name: string;
    kind: string;
    status: string;
    is_pilot: boolean;
    primary_host: string | null;
    color_primary: string;
    color_accent: string;
    initials: string;
    hero_image_url: string | null;
    tagline: string;
    center_lat: number | null;
    center_lng: number | null;
    communes: number;
    establishments: number;
  }>(sql`
    select t.id, t.slug, t.name, t.kind, t.status, t.is_pilot, t.primary_host, t.color_primary, t.color_accent, t.initials, t.hero_image_url, t.tagline,
      t.center_lat, t.center_lng,
      (select count(*)::int from commune_memberships m where m.territory_id = t.id and m.valid_to is null) as communes,
      (select count(*)::int from establishments e where e.territory_id = t.id and e.status <> 'ARCHIVED') as establishments
    from territories t where t.status = 'ACTIVE' order by t.is_pilot desc, t.name`);
  return rows.rows.map((t) => ({ ...t, url: portalUrl({ slug: t.slug, primaryHost: t.primary_host }) }));
}
