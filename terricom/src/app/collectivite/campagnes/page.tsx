import { and, asc, count, eq, sql } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { CampaignAssistant } from '@/components/bo/CampaignAssistant';
import { Photo } from '@/components/ui/Photo';
import { fmtInt } from '@/lib/format';
import { sized } from '@/lib/images';
import { db } from '@/server/db';
import { campaigns, establishments, posts } from '@/server/db/schema';
import { estScope, loadBoContext } from '@/server/services/backoffice';

export const metadata: Metadata = { title: 'Campagnes' };

const STATUS = {
  ACTIVE: { label: 'En cours', bg: 'var(--leaf)' },
  SCHEDULED: { label: 'Programmée', bg: 'var(--sky)' },
  DRAFT: { label: 'Brouillon', bg: 'var(--sand)' },
  ENDED: { label: 'Terminée', bg: '#E4E7E1' },
} as const;

const ORDER = { ACTIVE: 0, SCHEDULED: 1, DRAFT: 2, ENDED: 3 } as const;

const MONTHS = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

/** « 1er → 24 déc. », « Mars », « Permanent ». */
function period(start: string, end: string): string {
  const [ys, ms, ds] = start.split('-').map(Number);
  const [ye, me, de] = end.split('-').map(Number);
  if (ye - ys >= 1 && (ye - ys > 1 || me >= ms)) return 'Permanent';
  const d = (n: number) => (n === 1 ? '1er' : String(n));
  if (ms === me && ys === ye) return `${d(ds)} → ${de} ${MONTHS[me - 1]}`;
  if (ds === 1 && de >= 28 && me === ms) return MONTHS[ms - 1];
  return `${d(ds)} ${MONTHS[ms - 1]} → ${de} ${MONTHS[me - 1]}`;
}

export default async function CampaignsPage() {
  const ctx = await loadBoContext();
  const t = ctx.territory.id;
  const list = await db
    .select({
      id: campaigns.id,
      name: campaigns.name,
      tagline: campaigns.tagline,
      status: campaigns.status,
      mode: campaigns.mode,
      startsAt: campaigns.startsAt,
      endsAt: campaigns.endsAt,
      image: sql<string | null>`coalesce(${campaigns.cardImageUrl}, ${campaigns.heroImageUrl})`,
      joined: sql<number>`(select count(*)::int from campaign_participants p where p.campaign_id = "campaigns"."id" and p.status = 'JOINED')`,
      invited: sql<number>`(select count(*)::int from campaign_participants p where p.campaign_id = "campaigns"."id")`,
      posts: sql<number>`(select count(*)::int from posts p where p.campaign_id = "campaigns"."id" and p.status = 'PUBLISHED')`,
      views: sql<number>`(select count(*)::int from analytics_events a where a.ref_id = "campaigns"."id" and a.type = 'CAMPAIGN_VIEW') + (select coalesce(sum(p.view_count), 0)::int from posts p where p.campaign_id = "campaigns"."id")`,
    })
    .from(campaigns)
    .where(eq(campaigns.territoryId, t))
    .orderBy(asc(campaigns.startsAt));
  list.sort((a, b) => ORDER[a.status] - ORDER[b.status] || a.startsAt.localeCompare(b.startsAt));
  const [[{ n: ests }], [{ n: nPosts }]] = await Promise.all([
    db.select({ n: count() }).from(establishments).where(estScope(ctx)),
    db
      .select({ n: count() })
      .from(posts)
      .where(and(eq(posts.territoryId, t), eq(posts.status, 'PUBLISHED'))),
  ]);

  return (
    <div className="app-content">
      <div style={{ display: 'flex', justifyContent: 'flex-end', marginBottom: -6 }}>
        <Link href="/collectivite/campagnes/nouvelle" className="btn btn-brand btn-sm">
          + Nouvelle campagne
        </Link>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(260px,1fr))', gap: 14 }}>
        {list.map((c) => (
          <Link
            key={c.id}
            href={`/collectivite/campagnes/${c.id}`}
            className="card-link"
            style={{ background: 'var(--paper)', border: '1px solid var(--line)', borderRadius: 20, overflow: 'hidden', color: 'var(--text)' }}
          >
            <div style={{ position: 'relative', height: 120 }}>
              <Photo src={sized(c.image, 600, 300)} alt="" label={c.name} color="#7A2E26" />
              <span
                style={{
                  position: 'absolute',
                  left: 12,
                  top: 12,
                  background: STATUS[c.status].bg,
                  color: 'var(--ink)',
                  fontSize: 11,
                  fontWeight: 800,
                  padding: '4px 9px',
                  borderRadius: 999,
                }}
              >
                {STATUS[c.status].label}
              </span>
            </div>
            <div style={{ padding: '14px 16px', display: 'flex', flexDirection: 'column', gap: 6 }}>
              <div className="display" style={{ fontSize: 19 }}>
                {c.name}
              </div>
              <div style={{ fontSize: 13, color: 'var(--muted)' }}>
                {period(c.startsAt, c.endsAt)} · {c.mode === 'ADVENT' ? 'calendrier de l’Avent' : (c.tagline ?? 'campagne').toLowerCase()}
              </div>
              <div style={{ display: 'flex', gap: 14, fontSize: 12, marginTop: 4, flexWrap: 'wrap' }}>
                <span>
                  <b>{fmtInt(c.joined || c.invited)}</b> entreprises
                </span>
                <span>
                  <b>{fmtInt(c.posts)}</b> publications
                </span>
                <span>
                  <b>{c.views ? fmtInt(c.views) : '—'}</b> vues
                </span>
              </div>
            </div>
          </Link>
        ))}
      </div>
      <CampaignAssistant
        defaultPrompt="Prépare une campagne pour mettre en avant les producteurs locaux avant Noël."
        analysing={`Analyse de ${fmtInt(Number(ests))} fiches, ${fmtInt(Number(nPosts))} publications et de l’agenda…`}
      />
    </div>
  );
}
