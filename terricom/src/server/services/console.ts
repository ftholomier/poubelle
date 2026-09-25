import { sql } from 'drizzle-orm';
import { db } from '../db';
import { env } from '../env';
import { recurringRevenue } from './billing';
import { groupOf } from './crm';
import { PIPELINE_GROUPS, type DealStage } from '@/lib/constants';

/**
 * Indicateurs de la console plateforme (S1) : revenu récurrent, adoption, conversion,
 * pipeline commercial et santé du service.
 */

async function rows<T>(query: ReturnType<typeof sql>): Promise<T[]> {
  return (await db.execute(query)).rows as T[];
}

export async function platformKpis() {
  const rev = await recurringRevenue();
  const [r] = await rows<{
    active: number;
    onboarding: number;
    establishments: number;
    claimed: number;
    arr_year_ago: number;
    canceled_30: number;
    active_30_ago: number;
  }>(sql`
    select
      (select count(*)::int from territories where status = 'ACTIVE') as active,
      (select count(*)::int from territories where status = 'ONBOARDING') as onboarding,
      (select count(*)::int from establishments where status <> 'ARCHIVED') as establishments,
      (select count(*)::int from establishments e where e.status <> 'ARCHIVED' and exists (select 1 from company_members m where m.company_id = e.company_id)) as claimed,
      (
        coalesce((select sum(amount_cents) from territory_contracts where kind = 'LICENCE' and status in ('ACTIVE', 'EXPIRED')
          and starts_at <= (now() - interval '12 months')::date and (ends_at is null or ends_at >= (now() - interval '12 months')::date)), 0)
        + coalesce((select sum(p.price_monthly_cents) * 12 from company_subscriptions s join plans p on p.key = s.plan
          where s.started_at <= now() - interval '12 months' and (s.canceled_at is null or s.canceled_at > now() - interval '12 months')), 0)
      )::int as arr_year_ago,
      (select count(*)::int from company_subscriptions where canceled_at >= now() - interval '30 days') as canceled_30,
      (select count(*)::int from company_subscriptions where started_at <= now() - interval '30 days' and (canceled_at is null or canceled_at >= now() - interval '30 days')) as active_30_ago
  `);
  const growth = r && r.arr_year_ago > 0 ? Math.round(((rev.arrCents - r.arr_year_ago) / r.arr_year_ago) * 100) : null;
  return {
    arrCents: rev.arrCents,
    growth,
    active: r?.active ?? 0,
    onboarding: r?.onboarding ?? 0,
    establishments: r?.establishments ?? 0,
    claimed: r?.claimed ?? 0,
    premium: rev.premiumCount,
    conversion: r?.claimed ? rev.premiumCount / r.claimed : 0,
    churn: r?.active_30_ago ? (r.canceled_30 ?? 0) / r.active_30_ago : 0,
  };
}

/** Revenu récurrent mensuel des 12 derniers mois (licences et abonnements Premium). */
export async function mrrSeries() {
  const data = await rows<{ month: string; licences: number; premium: number }>(sql`
    with months as (
      select generate_series(date_trunc('month', now()) - interval '11 months', date_trunc('month', now()), interval '1 month') as m
    )
    select to_char(m, 'YYYY-MM') as month,
      coalesce((select sum(c.amount_cents) / 12 from territory_contracts c where c.kind = 'LICENCE' and c.status in ('ACTIVE', 'EXPIRED')
        and c.starts_at <= (m + interval '1 month - 1 day')::date and (c.ends_at is null or c.ends_at >= m::date)), 0)::int as licences,
      coalesce((select sum(p.price_monthly_cents) from company_subscriptions s join plans p on p.key = s.plan
        where s.started_at <= m + interval '1 month' and (s.canceled_at is null or s.canceled_at > m + interval '1 month')), 0)::int as premium
    from months order by m
  `);
  const labels = ['jan', 'fév', 'mar', 'avr', 'mai', 'juin', 'juil', 'août', 'sep', 'oct', 'nov', 'déc'];
  return data.map((d) => ({ ...d, label: labels[Number(d.month.slice(5)) - 1] }));
}

export async function pipeline() {
  const data = await rows<{ stage: string; n: number; licence: number }>(sql`
    select stage, count(*)::int as n, coalesce(sum(licence_cents), 0)::int as licence from deals where stage <> 'LOST' group by stage
  `);
  return PIPELINE_GROUPS.map((g) => {
    const inGroup = data.filter((d) => g.stages.includes(d.stage as DealStage));
    const qualified = data.filter((d) => d.stage === 'DEMO' || d.stage === 'FIRST_CONTACT').reduce((a, d) => a + d.n, 0);
    return {
      key: g.key,
      label: g.label,
      bg: g.bg,
      n: inGroup.reduce((a, d) => a + d.n, 0),
      licenceCents: inGroup.reduce((a, d) => a + d.licence, 0),
      qualified,
    };
  });
}

export async function mapLabels() {
  const [terrs, dealRows] = await Promise.all([
    rows<{ name: string; lat: number; lng: number; status: string; is_pilot: boolean }>(sql`
      select name, center_lat as lat, center_lng as lng, status, is_pilot from territories where center_lat is not null`),
    rows<{ name: string; lat: number; lng: number; stage: string }>(sql`
      select name, lat, lng, stage from deals where territory_id is null and lat is not null and stage <> 'LOST'`),
  ]);
  const colorOf = (s: string) => (s === 'ACTIVE' ? '#1F6B52' : s === 'ONBOARDING' ? '#3E6FB0' : '#9A9F95');
  return [
    ...terrs.map((t) => ({
      name: t.name,
      lat: t.lat,
      lng: t.lng,
      color: colorOf(t.status),
      tooltip: t.is_pilot ? 'Pilote' : t.status === 'ACTIVE' ? 'Actif' : t.status === 'ONBOARDING' ? 'Onboarding' : 'Suspendu',
    })),
    ...dealRows.map((d) => {
      const g = groupOf(d.stage as DealStage);
      return { name: d.name, lat: d.lat, lng: d.lng, color: g === 'neg' ? '#C8892A' : '#9A9F95', tooltip: g === 'neg' ? 'Négociation' : 'Prospect' };
    }),
  ];
}

/** Disponibilité et temps de réponse (sondes du worker), tickets ouverts. */
export async function serviceHealth() {
  const [r] = await rows<{ total: number; ok: number; latency: number | null; tickets: number }>(sql`
    select
      (select count(*)::int from health_probes where at >= now() - interval '30 days') as total,
      (select count(*)::int from health_probes where at >= now() - interval '30 days' and ok) as ok,
      (select round(avg(latency_ms))::int from health_probes where at >= now() - interval '30 days' and ok) as latency,
      (select count(*)::int from support_tickets where status in ('OPEN', 'PENDING')) as tickets
  `);
  return {
    uptime: r && r.total ? r.ok / r.total : null,
    latency: r?.latency ?? null,
    tickets: r?.tickets ?? 0,
  };
}

export type TerritoryEconomics = {
  id: string;
  name: string;
  status: string;
  establishments: number;
  activeCompanies: number;
  premiumCompanies: number;
  revenueMonthlyCents: number;
  premiumMonthlyCents: number;
  campaignViews: number;
  emails: number;
  costMonthlyCents: number;
  costDetail: { ai: number; emails: number; storage: number; infra: number };
};

/**
 * Usage et rentabilité par territoire sur 30 jours : entreprises actives, vues des campagnes,
 * revenu mensuel (licence + offres) et coût d'exploitation estimé (IA, emails, stockage, part d'infrastructure).
 */
export async function territoryEconomics(): Promise<TerritoryEconomics[]> {
  const data = await rows<{
    id: string;
    name: string;
    status: string;
    ests: number;
    active_companies: number;
    premium_companies: number;
    premium_mrr: number;
    licence_mrr: number;
    campaign_views: number;
    emails: number;
    tin: number;
    tout: number;
    bytes: number;
  }>(sql`
    select t.id, t.name, t.status,
      (select count(*)::int from establishments e where e.territory_id = t.id and e.status <> 'ARCHIVED') as ests,
      (select count(distinct e.company_id)::int from establishments e
        where e.territory_id = t.id and e.last_activity_at >= now() - interval '30 days'
          and exists (select 1 from company_members m where m.company_id = e.company_id)) as active_companies,
      (select count(distinct s.company_id)::int from company_subscriptions s
        where s.status = 'ACTIVE' and exists (select 1 from establishments e where e.company_id = s.company_id and e.territory_id = t.id)) as premium_companies,
      (select coalesce(sum(p.price_monthly_cents), 0)::int from company_subscriptions s join plans p on p.key = s.plan
        where s.status = 'ACTIVE' and exists (select 1 from establishments e where e.company_id = s.company_id and e.territory_id = t.id)) as premium_mrr,
      (select coalesce(sum(c.amount_cents), 0)::int / 12 from territory_contracts c where c.territory_id = t.id and c.kind = 'LICENCE' and c.status = 'ACTIVE') as licence_mrr,
      (select count(*)::int from analytics_events a where a.territory_id = t.id and a.type = 'CAMPAIGN_VIEW' and a.occurred_at >= now() - interval '30 days') as campaign_views,
      (select count(*)::int from emails m where m.territory_id = t.id and m.created_at >= now() - interval '30 days') as emails,
      (select coalesce(sum(u.input_tokens), 0)::bigint from ai_usage u where u.territory_id = t.id and u.created_at >= now() - interval '30 days') as tin,
      (select coalesce(sum(u.output_tokens), 0)::bigint from ai_usage u where u.territory_id = t.id and u.created_at >= now() - interval '30 days') as tout,
      (select coalesce(sum(md.size_bytes), 0)::bigint from media md where md.territory_id = t.id) as bytes
    from territories t
    where t.status in ('ACTIVE', 'ONBOARDING')
    order by t.name
  `);
  const totalEsts = Math.max(
    1,
    data.reduce((n, d) => n + Number(d.ests), 0),
  );
  return data.map((d) => {
    const ai = (Number(d.tin) / 1e6) * env.AI_COST_INPUT_PER_MTOK + (Number(d.tout) / 1e6) * env.AI_COST_OUTPUT_PER_MTOK;
    const emails = (Number(d.emails) / 1000) * env.COST_EMAIL_PER_THOUSAND;
    const storage = (Number(d.bytes) / 1024 ** 3) * env.COST_STORAGE_PER_GB_MONTH;
    const infra = env.COST_INFRA_MONTHLY * (Number(d.ests) / totalEsts);
    const cents = (v: number) => Math.round(v * 100);
    return {
      id: d.id,
      name: d.name,
      status: d.status,
      establishments: Number(d.ests),
      activeCompanies: Number(d.active_companies),
      premiumCompanies: Number(d.premium_companies),
      premiumMonthlyCents: Number(d.premium_mrr),
      revenueMonthlyCents: Number(d.premium_mrr) + Number(d.licence_mrr),
      campaignViews: Number(d.campaign_views),
      emails: Number(d.emails),
      costMonthlyCents: cents(ai + emails + storage + infra),
      costDetail: { ai: cents(ai), emails: cents(emails), storage: cents(storage), infra: cents(infra) },
    };
  });
}
