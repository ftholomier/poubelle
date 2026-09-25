import { sql } from 'drizzle-orm';
import { db } from '../db';
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
