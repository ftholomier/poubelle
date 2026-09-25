import { and, count, eq } from 'drizzle-orm';
import Link from 'next/link';
import { CampaignInvite } from '@/components/pro/CampaignInvite';
import { MiniBars, Ring } from '@/components/ui/Charts';
import { Photo } from '@/components/ui/Photo';
import { POST_KINDS, type PostKind } from '@/lib/constants';
import { fmtDayMonth, fmtHourOf, fmtInboxTime, fmtInt, parisParts, WEEKDAYS_SHORT } from '@/lib/format';
import { sized } from '@/lib/images';
import { db } from '@/server/db';
import { campaigns, subscribers } from '@/server/db/schema';
import { deltaLabel, establishmentKpis, loadProContext, recentMessages, scheduledPosts } from '@/server/services/pro';

type Props = { params: Promise<{ est: string }> };

const CHANNEL_LABELS: Record<string, string> = {
  FICHE: 'Fiche',
  COMMUNE: 'commune',
  TERRITOIRE: 'territoire',
  NEWSLETTER: 'newsletter',
  SOCIAL: 'réseaux sociaux',
};

const MSG_BG = ['var(--lilac)', 'var(--leaf)', 'var(--sky)', 'var(--rose)', 'var(--amber-soft)'];

export default async function ProDashboard({ params }: Props) {
  const { est: estId } = await params;
  const ctx = await loadProContext(estId);
  const { est, base, completeness, level, campaign, actor } = ctx;
  const [k30, k7, msgs, sched, subs, camp] = await Promise.all([
    establishmentKpis(est.id, 30),
    establishmentKpis(est.id, 7),
    recentMessages(est.id, 3),
    scheduledPosts(est.id, 3),
    db
      .select({ n: count() })
      .from(subscribers)
      .where(and(eq(subscribers.territoryId, est.territoryId), eq(subscribers.status, 'CONFIRMED'))),
    campaign ? db.select().from(campaigns).where(eq(campaigns.id, campaign.id)).limit(1) : Promise.resolve([]),
  ]);
  // Comparaison hebdomadaire seulement si le volume la rend significative.
  const weekDelta = k7.views.prev >= 20 ? Math.round(((k7.views.cur - k7.views.prev) / k7.views.prev) * 100) : null;
  const hello = `Bonjour ${actor.user.firstName || ''}`.trim();
  const greeting =
    weekDelta === null
      ? `${hello}, bienvenue dans votre espace${completeness.score < 85 ? ' : complétez votre fiche pour briller sur la carte' : ''}.`
      : weekDelta >= 5
        ? `${hello}, belle semaine : +${weekDelta}\u00a0% de vues.`
        : weekDelta > -10
          ? `${hello}, semaine stable : ${k7.views.cur} vues. Une actualité relancerait la curiosité !`
          : `${hello}, ${Math.abs(weekDelta)}\u00a0% de vues en moins cette semaine : publiez une actualité !`;
  const recs = completeness.items
    .filter((i) => i.action)
    .sort((a, b) => b.points - b.earned - (a.points - a.earned))
    .slice(0, 4);
  const kpis = [
    { label: 'Vues de la fiche', v: k30.views, d: deltaLabel(k30.views.cur, k30.views.prev, ' vs mois dernier') },
    { label: 'Appels', v: k30.calls, d: deltaLabel(k30.calls.cur, k30.calls.prev) },
    { label: 'Itinéraires', v: k30.directions, d: deltaLabel(k30.directions.cur, k30.directions.prev) },
    { label: 'Scans QR vitrine', v: k30.qr, d: deltaLabel(k30.qr.cur, k30.qr.prev) },
  ];

  return (
    <div className="app-content">
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.4fr) minmax(0,1fr)', ['--gap' as string]: '18px' }}>
        <div
          style={{
            background: 'var(--green)',
            color: '#fff',
            borderRadius: 22,
            padding: 26,
            display: 'grid',
            gridTemplateColumns: 'auto minmax(0,1fr)',
            gap: 24,
            alignItems: 'center',
            position: 'relative',
            overflow: 'hidden',
          }}
        >
          <div style={{ position: 'absolute', right: -30, top: -30, width: 180, height: 180, borderRadius: '50%', background: 'rgba(244,178,102,.18)' }} />
          <Ring value={completeness.score}>
            <div style={{ textAlign: 'center' }}>
              <div className="display" style={{ fontSize: 30, lineHeight: 1, whiteSpace: 'nowrap' }}>
                {completeness.score}%
              </div>
              <div style={{ fontSize: 11, color: 'var(--mint-3)' }}>complète</div>
            </div>
          </Ring>
          <div style={{ position: 'relative' }}>
            <div
              style={{
                display: 'inline-flex',
                gap: 8,
                alignItems: 'center',
                background: level.bg,
                color: 'var(--ink)',
                padding: '5px 12px',
                borderRadius: 999,
                fontWeight: 800,
                fontSize: 13,
                marginBottom: 10,
                transform: 'rotate(-2deg)',
              }}
            >
              {level.name}
            </div>
            <div className="display" style={{ fontSize: 28, letterSpacing: '-0.02em', lineHeight: 1.05 }}>
              {greeting}
            </div>
            <div style={{ fontSize: 14, color: 'var(--mint-3)', marginTop: 8 }}>{level.hint}</div>
          </div>
        </div>
        <div className="card" style={{ borderRadius: 22, padding: 20, display: 'flex', flexDirection: 'column', gap: 8 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
            <b>Vos prochaines actions</b>
            <span style={{ fontSize: 12, color: 'var(--muted)' }}>suggérées par l&apos;assistant</span>
          </div>
          {recs.length ? (
            recs.map((r) => (
              <Link
                key={r.key}
                href={r.section === 'publications' ? `${base}/publications` : `${base}/fiche#${r.section}`}
                style={{
                  display: 'grid',
                  gridTemplateColumns: '22px 1fr auto',
                  gap: 10,
                  alignItems: 'center',
                  padding: '9px 10px',
                  borderRadius: 10,
                  border: '1px solid var(--line-2)',
                  color: 'var(--text)',
                }}
              >
                <span style={{ width: 20, height: 20, borderRadius: 6, border: '1.5px solid var(--green)' }} aria-hidden="true" />
                <span style={{ fontSize: 14 }}>{r.action}</span>
                <span style={{ fontSize: 12, fontWeight: 800, color: 'var(--green)' }}>+{r.points - r.earned}%</span>
              </Link>
            ))
          ) : (
            <div style={{ fontSize: 14, color: 'var(--muted)' }}>
              Tout est parfait : votre fiche est complète. Pensez à publier une actualité chaque semaine.
            </div>
          )}
        </div>
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(190px,1fr))', gap: 14 }}>
        {kpis.map((k) => (
          <div key={k.label} className="card" style={{ borderRadius: 18, padding: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <div style={{ fontSize: 13, color: 'var(--muted)', fontWeight: 600 }}>{k.label}</div>
            <div style={{ display: 'flex', alignItems: 'flex-end', justifyContent: 'space-between', gap: 10 }}>
              <div className="display" style={{ fontSize: 34, letterSpacing: '-0.03em', lineHeight: 1 }}>
                {fmtInt(k.v.cur)}
              </div>
              <MiniBars values={k.v.bars} />
            </div>
            <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--green)' }}>{k.d}</div>
          </div>
        ))}
      </div>

      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(320px,1fr))', gap: 18 }}>
        {campaign && camp[0] && campaign.status !== 'DECLINED' ? (
          <CampaignInvite
            estId={est.id}
            campaign={{ id: campaign.id, name: campaign.name, status: campaign.status, offerLabel: campaign.offerLabel, advent: camp[0].mode === 'ADVENT' }}
            subscribers={Number(subs[0]?.n ?? 0)}
            image={sized(camp[0].cardImageUrl ?? camp[0].heroImageUrl, 900)}
          />
        ) : (
          <div className="card" style={{ borderRadius: 22, padding: 22, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <b>Kit vitrine</b>
            <p style={{ margin: 0, fontSize: 14, color: 'var(--muted)' }}>
              Imprimez votre affichette avec QR code : chaque scan est compté dans vos statistiques.
            </p>
            <Link href={`${base}/kit`} className="btn btn-dark btn-sm" style={{ alignSelf: 'flex-start' }}>
              Préparer mon kit
            </Link>
          </div>
        )}
        <div className="card" style={{ borderRadius: 22, padding: 20, display: 'flex', flexDirection: 'column', gap: 4 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
            <Link href={`${base}/messages`} style={{ fontWeight: 700, color: 'var(--text)' }}>
              Messages reçus
            </Link>
            {ctx.unreadMessages ? (
              <span style={{ fontSize: 12, fontWeight: 800, color: 'var(--danger)' }}>
                {ctx.unreadMessages} nouveau{ctx.unreadMessages > 1 ? 'x' : ''}
              </span>
            ) : null}
          </div>
          {msgs.length ? (
            msgs.map((m, i) => (
              <Link
                key={m.id}
                href={`${base}/messages?m=${m.id}`}
                style={{
                  display: 'grid',
                  gridTemplateColumns: '36px 1fr auto',
                  gap: 10,
                  padding: '10px 0',
                  borderTop: '1px solid var(--line-2)',
                  alignItems: 'center',
                  color: 'var(--text)',
                }}
              >
                <div
                  style={{
                    width: 36,
                    height: 36,
                    borderRadius: '50%',
                    background: MSG_BG[i % MSG_BG.length],
                    display: 'grid',
                    placeItems: 'center',
                    fontWeight: 800,
                    fontSize: 13,
                  }}
                >
                  {m.senderName.charAt(0).toUpperCase()}
                </div>
                <div style={{ minWidth: 0 }}>
                  <div style={{ fontWeight: m.readAt ? 600 : 800, fontSize: 14 }}>{m.senderName}</div>
                  <div style={{ fontSize: 13, color: 'var(--muted)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{m.body}</div>
                </div>
                <span style={{ fontSize: 12, color: 'var(--muted)' }}>{fmtInboxTime(m.createdAt)}</span>
              </Link>
            ))
          ) : (
            <div style={{ fontSize: 14, color: 'var(--muted)', borderTop: '1px solid var(--line-2)', paddingTop: 10 }}>
              Aucun message pour l&apos;instant. Les visiteurs peuvent vous écrire depuis votre fiche.
            </div>
          )}
        </div>
        <div className="card" style={{ borderRadius: 22, padding: 20, display: 'flex', flexDirection: 'column', gap: 4 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: 6 }}>
            <b>Publications programmées</b>
            <Link href={`${base}/publications`} style={{ fontSize: 13, fontWeight: 700 }}>
              + Nouvelle
            </Link>
          </div>
          {sched.length ? (
            sched.map((p) => {
              const at = p.publishAt ?? p.createdAt;
              const parts = parisParts(at);
              return (
                <div
                  key={p.id}
                  style={{
                    display: 'grid',
                    gridTemplateColumns: '56px 1fr',
                    gap: 12,
                    padding: '10px 0',
                    borderTop: '1px solid var(--line-2)',
                    alignItems: 'center',
                  }}
                >
                  <div style={{ width: 56, height: 56, borderRadius: 10, overflow: 'hidden' }}>
                    <Photo src={sized(p.imageUrl, 120, 120)} alt="" label={p.title} color={POST_KINDS[p.kind as PostKind].bg} />
                  </div>
                  <div>
                    <div style={{ fontWeight: 700, fontSize: 14 }}>{p.title}</div>
                    <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                      {WEEKDAYS_SHORT[parts.weekday]} {fmtDayMonth(at)} · {fmtHourOf(at)} · {p.channels.map((c) => CHANNEL_LABELS[c] ?? c).join(' + ')}
                    </div>
                  </div>
                </div>
              );
            })
          ) : (
            <div style={{ fontSize: 14, color: 'var(--muted)', borderTop: '1px solid var(--line-2)', paddingTop: 10 }}>
              Rien de programmé. L&apos;assistant peut rédiger votre prochaine actualité en quelques secondes.
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
