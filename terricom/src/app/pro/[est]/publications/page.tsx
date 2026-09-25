import { and, count, eq, gte } from 'drizzle-orm';
import { deletePost } from '../actions';
import { PostStudio } from '@/components/pro/PostStudio';
import { daysAgoDate, fmtDayMonth, fmtHourOf, fmtInt, parisParts } from '@/lib/format';
import { sized } from '@/lib/images';
import { db } from '@/server/db';
import { analyticsEvents, subscribers } from '@/server/db/schema';
import { loadProContext, monthSchedule, pastPosts, postsThisMonth, scheduledPosts } from '@/server/services/pro';

type Props = { params: Promise<{ est: string }> };

const MONTHS = ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];

export default async function PublicationsPage({ params }: Props) {
  const { est: estId } = await params;
  const ctx = await loadProContext(estId);
  const { est, limits, territory } = ctx;
  const now = parisParts(new Date());
  const [used, sched, past, days, subs, communeViews] = await Promise.all([
    postsThisMonth(est.id),
    scheduledPosts(est.id, 10),
    pastPosts(est.id, 6),
    monthSchedule(est.id, now.year, now.month),
    db
      .select({ n: count() })
      .from(subscribers)
      .where(and(eq(subscribers.territoryId, est.territoryId), eq(subscribers.status, 'CONFIRMED'))),
    db
      .select({ n: count() })
      .from(analyticsEvents)
      .where(and(eq(analyticsEvents.communeId, est.communeId), gte(analyticsEvents.occurredAt, daysAgoDate(30)))),
  ]);
  const settings = (territory.settings ?? {}) as { newsletterName?: string };
  const channels = [
    { key: 'FICHE', label: 'Ma fiche', detail: 'Toujours', locked: false, always: true },
    { key: 'COMMUNE', label: 'Page commune', detail: `${est.commune.name} · ${fmtInt(Number(communeViews[0]?.n ?? 0))} visites/mois`, locked: false },
    { key: 'TERRITOIRE', label: 'Page territoire', detail: territory.name, locked: false },
    {
      key: 'NEWSLETTER',
      label: settings.newsletterName ?? 'Newsletter du territoire',
      detail: `${fmtInt(Number(subs[0]?.n ?? 0))} abonnés${limits.newsletterChannel ? '' : ' · Premium'}`,
      locked: !limits.newsletterChannel || !ctx.modules.has('NEWSLETTER'),
    },
    { key: 'SOCIAL', label: 'Réseaux sociaux', detail: `Facebook + Instagram${limits.socialChannel ? '' : ' · Communication'}`, locked: !limits.socialChannel },
  ];
  // Calendrier du mois (lundi en premier)
  const first = new Date(Date.UTC(now.year, now.month - 1, 1));
  const offset = (first.getUTCDay() + 6) % 7;
  const daysInMonth = new Date(Date.UTC(now.year, now.month, 0)).getUTCDate();
  const busy = new Set(days.map((d) => parisParts(d).day));
  const cells = Array.from({ length: Math.ceil((offset + daysInMonth) / 7) * 7 }, (_, i) => {
    const n = i - offset + 1;
    return n >= 1 && n <= daysInMonth ? n : null;
  });

  return (
    <div className="app-content">
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) 320px', ['--gap' as string]: '22px', ['--align' as string]: 'start' }}>
        <PostStudio
          estId={est.id}
          channels={channels}
          cover={sized(est.coverUrl, 600, 300)}
          canSchedule={limits.scheduling}
          quota={{ used, max: limits.postsPerMonth }}
        />
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <div className="card" style={{ borderRadius: 20, padding: 18 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 12 }}>
              <b>{MONTHS[now.month - 1]}</b>
              <span style={{ fontSize: 12, color: 'var(--muted)' }}>
                {sched.length} programmée{sched.length > 1 ? 's' : ''}
              </span>
            </div>
            <div
              style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(7,1fr)',
                gap: 4,
                fontSize: 11,
                textAlign: 'center',
                color: 'var(--muted)',
                marginBottom: 6,
              }}
            >
              {['L', 'M', 'M', 'J', 'V', 'S', 'D'].map((d, i) => (
                <span key={i}>{d}</span>
              ))}
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(7,1fr)', gap: 4 }}>
              {cells.map((n, i) => {
                const today = n === now.day;
                const has = n !== null && busy.has(n);
                return (
                  <div
                    key={i}
                    style={{
                      aspectRatio: '1',
                      borderRadius: 8,
                      display: 'grid',
                      placeItems: 'center',
                      fontSize: 12,
                      fontWeight: has || today ? 800 : 400,
                      background: today ? 'var(--ink)' : has ? 'var(--amber)' : n ? 'var(--cream)' : 'transparent',
                      color: today ? '#fff' : 'var(--text)',
                    }}
                  >
                    {n ?? ''}
                  </div>
                );
              })}
            </div>
          </div>
          {sched.length ? (
            <div className="card" style={{ borderRadius: 20, padding: 18, display: 'flex', flexDirection: 'column', gap: 2 }}>
              <b style={{ marginBottom: 8 }}>Programmées</b>
              {sched.map((p) => (
                <div
                  key={p.id}
                  style={{ display: 'flex', justifyContent: 'space-between', gap: 10, padding: '9px 0', borderTop: '1px solid var(--line-2)', fontSize: 13 }}
                >
                  <span>
                    <b>{p.title}</b>
                    <br />
                    <span style={{ color: 'var(--muted)' }}>{p.publishAt ? `${fmtDayMonth(p.publishAt)} · ${fmtHourOf(p.publishAt)}` : ''}</span>
                  </span>
                  <form action={deletePost}>
                    <input type="hidden" name="estId" value={est.id} />
                    <input type="hidden" name="postId" value={p.id} />
                    <button type="submit" className="btn-link" style={{ color: 'var(--danger-fg)', fontSize: 12 }}>
                      Annuler
                    </button>
                  </form>
                </div>
              ))}
            </div>
          ) : null}
          <div className="card" style={{ borderRadius: 20, padding: 18, display: 'flex', flexDirection: 'column', gap: 2 }}>
            <b style={{ marginBottom: 8 }}>Dernières publications</b>
            {past.length ? (
              past.map((p) => (
                <div
                  key={p.id}
                  style={{ display: 'flex', justifyContent: 'space-between', gap: 10, padding: '9px 0', borderTop: '1px solid var(--line-2)', fontSize: 13 }}
                >
                  <span style={{ fontWeight: 600 }}>{p.title}</span>
                  <span style={{ color: 'var(--green)', fontWeight: 700, whiteSpace: 'nowrap' }}>{fmtInt(p.viewCount)} vues</span>
                </div>
              ))
            ) : (
              <span style={{ fontSize: 13, color: 'var(--muted)' }}>Aucune publication pour l&apos;instant.</span>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
