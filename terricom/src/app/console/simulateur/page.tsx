import { sql } from 'drizzle-orm';
import type { Metadata } from 'next';
import { Simulator } from '@/components/console/Simulator';
import { db } from '@/server/db';

export const metadata: Metadata = { title: 'Simulateur économique' };

/** Moyennes observées sur les territoires actifs, proposées comme hypothèse « réelle ». */
async function realAverages() {
  const [r] = (
    await db.execute<{ territories: number; ent: number | null; lic: number | null; claimed: number; premium: number; price: number | null }>(sql`
      with act as (select id from territories where status = 'ACTIVE')
      select
        (select count(*)::int from act) as territories,
        (select round(avg(n))::int from (select count(*) as n from establishments e join act on act.id = e.territory_id where e.status <> 'ARCHIVED' group by e.territory_id) x) as ent,
        (select round(avg(s) / 100)::int from (select sum(c.amount_cents) as s from territory_contracts c join act on act.id = c.territory_id where c.kind = 'LICENCE' and c.status = 'ACTIVE' group by c.territory_id) y) as lic,
        (select count(*)::int from establishments e join act on act.id = e.territory_id where exists (select 1 from company_members m where m.company_id = e.company_id)) as claimed,
        (select count(distinct s.company_id)::int from company_subscriptions s join establishments e on e.company_id = s.company_id join act on act.id = e.territory_id where s.status = 'ACTIVE' and s.plan <> 'ESSENTIEL') as premium,
        (select round(avg(p.price_monthly_cents) * 12 / 100)::int from company_subscriptions s join plans p on p.key = s.plan where s.status = 'ACTIVE') as price
    `)
  ).rows;
  if (!r || !r.territories || !r.ent || !r.lic || !r.price) return null;
  return {
    territories: r.territories,
    ent: Math.round(r.ent / 50) * 50 || 100,
    lic: Math.round(r.lic / 100) * 100,
    conv: Math.max(1, Math.min(30, Math.round((r.premium / Math.max(1, r.claimed)) * 100))),
    price: Math.max(120, Math.min(720, Math.round(r.price / 12) * 12)),
  };
}

export default async function SimulatorPage() {
  return <Simulator real={await realAverages()} />;
}
