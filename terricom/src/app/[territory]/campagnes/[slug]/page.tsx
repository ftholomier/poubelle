import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { cache } from 'react';
import { AdventCalendar } from '@/components/portal/AdventCalendar';
import { Beacon } from '@/components/portal/Beacon';
import { Photo } from '@/components/ui/Photo';
import { fmtInt, fmtLongDate, parisParts } from '@/lib/format';
import { sized } from '@/lib/images';
import { getPortal, getPublicCampaign } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string; slug: string }> };

const load = cache(async (territoryParam: string, slug: string) => {
  const portal = await getPortal(territoryParam);
  if (!portal.modules.has('CAMPAIGNS')) return { portal, data: null };
  return { portal, data: await getPublicCampaign(portal.territory.id, slug) };
});

const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

function periodLabel(start: string, end: string): string {
  const [, sm, sd] = start.split('-').map(Number);
  const [, em, ed] = end.split('-').map(Number);
  const day = (d: number) => (d === 1 ? '1er' : String(d));
  return sm === em ? `${day(sd)} → ${day(ed)} ${MONTHS[em - 1]}` : `${day(sd)} ${MONTHS[sm - 1]} → ${day(ed)} ${MONTHS[em - 1]}`;
}

function daysBetween(from: string, to: string): number {
  return Math.round((Date.parse(`${to}T12:00:00Z`) - Date.parse(`${from}T12:00:00Z`)) / 86_400_000);
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory, slug } = await params;
  const { portal, data } = await load(territory, slug);
  if (!data) return { title: 'Campagne introuvable', robots: { index: false } };
  const c = data.campaign;
  return {
    title: c.name,
    description: c.description || c.tagline || `${c.name} : les offres des commerces de ${portal.territory.name}.`,
    alternates: { canonical: portalUrl(portal.territory, `/campagnes/${c.slug}`) },
    openGraph: { title: c.name, description: c.tagline ?? undefined, images: c.heroImageUrl ? [{ url: sized(c.heroImageUrl, 1200)! }] : undefined },
  };
}

export default async function CampaignPage({ params }: Props) {
  const { territory, slug } = await params;
  const { portal, data } = await load(territory, slug);
  if (!data) notFound();
  const { base, territory: t } = portal;
  const { campaign: c, participants, calendar, today } = data;
  const offers = participants.filter((p) => p.offer).length;
  const advent = c.mode === 'ADVENT';
  const started = c.startsAt <= today;
  const ended = c.endsAt < today;
  const christmas = `${c.endsAt.slice(0, 4)}-12-25`;
  const countdown = ended
    ? { big: 'Fin', small: 'campagne terminée' }
    : !started
      ? { big: `J-${daysBetween(today, c.startsAt)}`, small: 'avant le lancement' }
      : advent
        ? { big: `J-${Math.max(0, daysBetween(today, christmas))}`, small: 'avant Noël' }
        : { big: `J-${daysBetween(today, c.endsAt)}`, small: 'avant la fin' };
  const todayParts = parisParts(new Date());
  const soft = c.colorTextSoft;

  return (
    <div style={{ background: c.colorBgDark, color: c.colorText }}>
      <Beacon type="CAMPAIGN_VIEW" territoryId={t.id} refId={c.id} />
      <section style={{ position: 'relative', overflow: 'hidden' }}>
        {c.heroImageUrl ? <Photo src={sized(c.heroImageUrl, 2000)} alt="" eager color={c.colorBgDark} label=" " style={{ position: 'absolute', inset: 0, opacity: 0.35 }} /> : null}
        <div
          className="container split"
          style={{ position: 'relative', paddingTop: 70, paddingBottom: 50, ['--cols' as string]: 'minmax(0,1.2fr) minmax(0,1fr)', ['--gap' as string]: '40px', ['--align' as string]: 'end' }}
        >
          <div>
            <span
              style={{
                display: 'inline-block',
                background: 'var(--amber)',
                color: 'var(--ink)',
                fontWeight: 800,
                fontSize: 13,
                padding: '6px 12px',
                borderRadius: 8,
                transform: 'rotate(-3deg)',
                marginBottom: 18,
              }}
            >
              {periodLabel(c.startsAt, c.endsAt)}
            </span>
            <h1 className="display" style={{ fontSize: 'clamp(52px,6.5vw,100px)', letterSpacing: '-0.04em', lineHeight: 0.88, margin: '0 0 18px' }}>
              {c.name}
            </h1>
            <p style={{ fontSize: 19, color: soft, maxWidth: 560, margin: 0 }}>{c.description || c.tagline}</p>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 10 }}>
            <div style={{ background: 'rgba(255,243,230,.1)', borderRadius: 16, padding: 16 }}>
              <div className="display" style={{ fontSize: 40 }}>
                {fmtInt(participants.length)}
              </div>
              <div style={{ fontSize: 13, color: soft }}>commerces</div>
            </div>
            <div style={{ background: 'rgba(255,243,230,.1)', borderRadius: 16, padding: 16 }}>
              <div className="display" style={{ fontSize: 40 }}>
                {fmtInt(offers)}
              </div>
              <div style={{ fontSize: 13, color: soft }}>offres actives</div>
            </div>
            <div style={{ background: 'var(--amber)', color: 'var(--ink)', borderRadius: 16, padding: 16 }}>
              <div className="display" style={{ fontSize: 40 }}>
                {countdown.big}
              </div>
              <div style={{ fontSize: 13 }}>{countdown.small}</div>
            </div>
          </div>
        </div>
      </section>

      {advent && calendar.length ? (
        <section className="container" style={{ paddingTop: 30, paddingBottom: 50 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'end', marginBottom: 18, gap: 14, flexWrap: 'wrap' }}>
            <h2 className="display" style={{ fontSize: 36, letterSpacing: '-0.02em', margin: 0 }}>
              Le calendrier de l&apos;Avent du territoire
            </h2>
            <span style={{ fontSize: 14, color: soft }}>
              {started && !ended
                ? `Cliquez sur une case déjà ouverte · aujourd'hui : ${todayParts.day} ${MONTHS[todayParts.month - 1]}`
                : ended
                  ? 'Le calendrier est terminé : merci à tous !'
                  : `Première case le ${fmtLongDate(c.startsAt).toLowerCase()}`}
            </span>
          </div>
          <AdventCalendar doors={calendar} base={base} storageKey={`advent:${c.id}`} />
        </section>
      ) : null}

      <section style={{ background: c.colorText, color: 'var(--text)' }}>
        <div className="container" style={{ paddingTop: 50, paddingBottom: 60 }}>
          <h2 className="display" style={{ fontSize: 36, letterSpacing: '-0.02em', margin: '0 0 20px' }}>
            Les commerces participants
          </h2>
          {participants.length ? (
            <div className="auto-grid" style={{ ['--min' as string]: '240px', ['--gap' as string]: '16px' }}>
              {participants.map((e) => (
                <Link
                  key={e.id}
                  href={`${base}${e.path}?src=campagne`}
                  className="card-lift"
                  style={{ background: '#fff', borderRadius: 16, overflow: 'hidden', border: '1px solid #F0DCCB', color: 'inherit' }}
                >
                  <div style={{ position: 'relative', height: 160 }}>
                    <Photo src={sized(e.coverUrl, 500, 320)} alt="" color={e.color} label={e.name} />
                    {e.offer ? (
                      <span
                        style={{
                          position: 'absolute',
                          right: 10,
                          top: 10,
                          background: 'var(--danger)',
                          color: '#fff',
                          fontWeight: 800,
                          fontSize: 12,
                          padding: '5px 9px',
                          borderRadius: 999,
                          transform: 'rotate(5deg)',
                        }}
                      >
                        {e.offer}
                      </span>
                    ) : null}
                  </div>
                  <div style={{ padding: '12px 14px' }}>
                    <div style={{ fontWeight: 700, fontSize: 16 }}>{e.name}</div>
                    <div style={{ fontSize: 13, color: 'var(--muted)' }}>
                      {e.activity} · {e.communeName}
                    </div>
                  </div>
                </Link>
              ))}
            </div>
          ) : (
            <p style={{ color: 'var(--muted)' }}>Les inscriptions des commerces sont en cours : revenez bientôt !</p>
          )}
          <div style={{ marginTop: 26, display: 'flex', gap: 12, flexWrap: 'wrap', alignItems: 'center' }}>
            <a href={`/pro?territoire=${t.slug}`} className="btn btn-dark">
              Vous êtes commerçant ? Participez
            </a>
            <span style={{ fontSize: 13, color: 'var(--muted)' }}>Participation gratuite, depuis votre espace professionnel.</span>
          </div>
        </div>
      </section>
    </div>
  );
}
