import Link from 'next/link';
import { Beacon } from '@/components/portal/Beacon';
import { CircuitCard, FeedCard, OpenCard, SectionHead } from '@/components/portal/Cards';
import { HeroSearch } from '@/components/portal/HeroSearch';
import { NewsletterForm } from '@/components/portal/NewsletterForm';
import { MapView } from '@/components/maps/MapView';
import { Photo } from '@/components/ui/Photo';
import { sized } from '@/lib/images';
import { FAMILIES, FAMILY_ORDER } from '@/lib/constants';
import { fmtInt, fmtLongDate } from '@/lib/format';
import type { HomeBlock } from '@/server/db/schema';
import { allCards, countOpenJobs, getCircuits, getFeed, getPortal, toMapPoints } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';
import { getTerritoryCommunes } from '@/server/services/territories';

type Props = { params: Promise<{ territory: string }> };

function HeroTitle({ text }: { text: string }) {
  const lines = text.split('|');
  return (
    <>
      {lines.map((line, i) => (
        <span key={i}>
          {line.split('&').map((part, j, arr) => (
            <span key={j}>
              {part}
              {j < arr.length - 1 ? <span style={{ color: 'var(--brand-accent)' }}>&amp;</span> : null}
            </span>
          ))}
          {i < lines.length - 1 ? <br /> : null}
        </span>
      ))}
    </>
  );
}

export default async function TerritoryHome({ params }: Props) {
  const { territory: param } = await params;
  const portal = await getPortal(param);
  const { territory: t, base, modules, featuredCampaign: camp } = portal;
  const blocks = new Set(t.homeBlocks as HomeBlock[]);
  const [cards, feed, circuits, jobsCount] = await Promise.all([
    allCards(t.id),
    blocks.has('feed') ? getFeed(t.id, { limit: 6, channel: 'TERRITOIRE', establishmentsOnly: true }) : Promise.resolve([]),
    blocks.has('circuits') && modules.has('CIRCUITS') ? getCircuits(t.id) : Promise.resolve([]),
    blocks.has('jobs') && modules.has('JOBS') ? countOpenJobs(t.id) : Promise.resolve(0),
  ]);
  const openCount = cards.filter((c) => c.open.open).length;
  const withPhoto = cards.filter((c) => c.coverUrl);
  const openCards = [
    ...withPhoto.filter((c) => c.open.open && c.isFeatured),
    ...withPhoto.filter((c) => c.open.open && !c.isFeatured).sort((a, b) => b.completeness - a.completeness),
    ...withPhoto.filter((c) => !c.open.open && c.isFeatured),
    ...withPhoto.filter((c) => !c.open.open && !c.isFeatured && c.open.next),
  ].slice(0, 4);
  const settings = t.settings as { newsletterName?: string; jobsTitle?: string };
  const newsletterName = settings.newsletterName ?? `La lettre de ${t.name}`;
  const prompts = [
    { label: '✦ Où offrir local pour Noël ?', query: 'où offrir local pour noël' },
    { label: '✦ Restaurants ouverts ce soir', query: 'quels restaurants ouverts ce soir' },
    { label: `✦ Réparer ma chaudière`, query: 'un artisan pour réparer ma chaudière' },
    { label: 'Producteurs bio', query: 'bio' },
  ];
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'WebSite',
    name: `${t.name} — ${t.tagline}`,
    url: portalUrl(t, '/'),
    publisher: { '@type': 'GovernmentOrganization', name: t.legalName },
    potentialAction: {
      '@type': 'SearchAction',
      target: { '@type': 'EntryPoint', urlTemplate: portalUrl(t, '/explorer?q={search_term_string}') },
      'query-input': 'required name=search_term_string',
    },
  };

  return (
    <div data-screen-label="P1 Accueil territoire">
      <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }} />
      <section className="hero">
        <Photo src={sized(t.heroImageUrl, 2000)} alt="" eager style={{ position: 'absolute', inset: 0 }} />
        <div
          style={{
            position: 'absolute',
            inset: 0,
            background: 'linear-gradient(180deg,rgba(20,32,27,.15) 0%,rgba(20,32,27,.25) 40%,rgba(20,32,27,.82) 100%)',
          }}
        />
        <div className="hero-badge" aria-hidden="true">
          <div>
            <div className="hero-badge-n" style={{ fontSize: 40 }}>
              {fmtInt(portal.prosCount)}
            </div>
            <div style={{ fontSize: 13, fontWeight: 600, marginTop: 4 }}>
              pros près
              <br />
              de chez vous
            </div>
          </div>
        </div>
        <div className="container" style={{ position: 'relative', paddingBottom: 56, color: '#fff' }}>
          <div
            style={{
              display: 'inline-flex',
              gap: 8,
              alignItems: 'center',
              background: 'rgba(255,253,248,.16)',
              backdropFilter: 'blur(8px)',
              padding: '6px 12px',
              borderRadius: 999,
              fontSize: 13,
              fontWeight: 600,
              marginBottom: 18,
            }}
          >
            <span className="live-dot" style={{ background: 'var(--pulse)' }} />
            {fmtInt(openCount)} {openCount > 1 ? 'commerces ouverts' : 'commerce ouvert'} en ce moment
          </div>
          <h1
            className="text-balance"
            style={{
              fontFamily: 'var(--font-display)',
              fontWeight: 800,
              fontSize: 'clamp(44px,6.4vw,92px)',
              lineHeight: 0.94,
              letterSpacing: '-0.035em',
              margin: '0 0 18px',
              maxWidth: 960,
            }}
          >
            <HeroTitle text={t.heroTitle ?? `${t.name},|fait main & fait ici.`} />
          </h1>
          <p style={{ fontSize: 19, maxWidth: 560, margin: '0 0 28px', color: '#E5ECE8', lineHeight: 1.45 }}>{t.heroSubtitle}</p>
          {blocks.has('search') ? <HeroSearch base={base} prompts={prompts} /> : null}
        </div>
      </section>

      {blocks.has('openNow') && openCards.length ? (
        <section className="container" style={{ paddingTop: 56, paddingBottom: 20 }}>
          <SectionHead
            eyebrow="Maintenant"
            title={openCards.some((c) => c.open.open) ? 'Ouvert près de vous' : 'Bientôt ouvert près de vous'}
            action={
              <Link href={`${base}/explorer`} style={{ fontWeight: 700 }}>
                Tout voir sur la carte →
              </Link>
            }
          />
          <div className="auto-grid" style={{ ['--min' as string]: '260px' }}>
            {openCards.map((e) => (
              <OpenCard key={e.id} e={e} base={base} />
            ))}
          </div>
        </section>
      ) : null}

      {blocks.has('campaign') && camp && modules.has('CAMPAIGNS') ? (
        <section className="container" style={{ paddingTop: 40, paddingBottom: 40 }}>
          <Link href={`${base}/campagnes/${camp.slug}`} className="campaign-banner" style={{ background: camp.colorBg }}>
            <div style={{ padding: 44, color: camp.colorText, display: 'flex', flexDirection: 'column', gap: 14, justifyContent: 'center' }}>
              <span
                style={{
                  alignSelf: 'flex-start',
                  background: 'var(--brand-accent)',
                  color: 'var(--ink)',
                  fontWeight: 800,
                  fontSize: 12,
                  padding: '5px 10px',
                  borderRadius: 6,
                  transform: 'rotate(-3deg)',
                  letterSpacing: '.04em',
                }}
              >
                CAMPAGNE DU TERRITOIRE
              </span>
              <div style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 'clamp(34px,4vw,56px)', lineHeight: 0.95, letterSpacing: '-0.03em' }}>
                {camp.name}
              </div>
              <div style={{ fontSize: 16, color: camp.colorTextSoft, maxWidth: 420 }}>{camp.description}</div>
              <div style={{ fontSize: 13, fontWeight: 700, color: camp.colorTextSoft }}>
                Du {fmtLongDate(camp.startsAt).replace(/^\w+ /, '').toLowerCase()} au {fmtLongDate(camp.endsAt).replace(/^\w+ /, '').toLowerCase()}
              </div>
              <div style={{ display: 'flex', gap: 10, marginTop: 6 }}>
                <span style={{ background: camp.colorText, color: camp.colorBg, padding: '11px 18px', borderRadius: 12, fontWeight: 700 }}>
                  {camp.ctaLabel ?? 'Découvrir la campagne'}
                </span>
              </div>
            </div>
            <Photo src={sized(camp.cardImageUrl ?? camp.heroImageUrl, 1200)} alt="" />
          </Link>
        </section>
      ) : null}

      {blocks.has('map') && modules.has('MAP') ? (
        <section className="container split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1.4fr)', paddingTop: 30, paddingBottom: 30 }}>
          <div style={{ display: 'flex', flexDirection: 'column', justifyContent: 'center', gap: 16 }}>
            <div className="eyebrow">La carte vivante</div>
            <h2 className="h-section" style={{ lineHeight: 1 }}>
              Tout le territoire,
              <br />à portée de clic.
            </h2>
            <p style={{ fontSize: 16, color: 'var(--muted)', lineHeight: 1.5 }}>
              Commerces, artisans, producteurs, marchés et événements : une carte libre basée sur OpenStreetMap, filtrable en un geste.
            </p>
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
              {FAMILY_ORDER.map((f) => (
                <Link
                  key={f}
                  href={`${base}/explorer?famille=${FAMILIES[f].slug}`}
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    gap: 6,
                    fontSize: 13,
                    fontWeight: 600,
                    background: 'var(--paper)',
                    border: '1px solid var(--line)',
                    padding: '6px 10px',
                    borderRadius: 999,
                    color: 'var(--text)',
                  }}
                >
                  <span className="chip-dot" style={{ width: 10, height: 10, background: FAMILIES[f].color }} />
                  {FAMILIES[f].label}
                </Link>
              ))}
            </div>
            <div>
              <Link href={`${base}/explorer`} className="btn btn-dark">
                Explorer la carte
              </Link>
            </div>
          </div>
          <div style={{ height: 440, borderRadius: 24, overflow: 'hidden', border: '1px solid var(--line)' }}>
            <MapView
              mode="mini"
              tileUrl={portal.mapConfig.tileUrl}
              attribution={portal.mapConfig.attribution}
              points={toMapPoints(cards, base)}
              ariaLabel={`Carte des professionnels de ${t.name}`}
            />
          </div>
        </section>
      ) : null}

      {blocks.has('feed') && feed.length ? (
        <section className="container" style={{ paddingTop: 50, paddingBottom: 20 }}>
          <SectionHead
            eyebrow="Le fil du territoire"
            title="Quoi de neuf chez vos pros"
            action={
              <Link href={`${base}/actualites`} style={{ fontWeight: 700 }}>
                Toutes les actualités →
              </Link>
            }
          />
          <div className="auto-grid" style={{ ['--min' as string]: '300px' }}>
            {feed.map((f) => (
              <FeedCard key={f.id} f={f} base={base} />
            ))}
          </div>
          <Beacon type="POST_VIEW" territoryId={t.id} refIds={feed.map((f) => f.id)} />
        </section>
      ) : null}

      {circuits.length ? (
        <section className="container" style={{ paddingTop: 50, paddingBottom: 20 }}>
          <SectionHead
            eyebrow="Balades"
            title="Circuits à tamponner"
            action={
              <Link href={`${base}/circuits`} style={{ fontWeight: 700 }}>
                Tous les circuits →
              </Link>
            }
          />
          <div className="auto-grid" style={{ ['--min' as string]: '300px' }}>
            {circuits.map((c) => (
              <CircuitCard key={c.id} c={c} href={`${base}/circuits/${c.slug}`} />
            ))}
          </div>
        </section>
      ) : null}

      {blocks.has('jobs') || blocks.has('newsletter') ? (
        <section className="container" style={{ paddingTop: 50, paddingBottom: 50 }}>
          <div className="auto-fit" style={{ ['--min' as string]: '320px' }}>
            {blocks.has('jobs') && modules.has('JOBS') ? (
              <Link
                href={`${base}/emploi`}
                style={{
                  background: 'var(--leaf)',
                  borderRadius: 24,
                  padding: 34,
                  display: 'flex',
                  flexDirection: 'column',
                  gap: 12,
                  minHeight: 260,
                  color: 'var(--ink)',
                }}
              >
                <div className="eyebrow-800" style={{ color: 'var(--leaf-fg)' }}>
                  {settings.jobsTitle ?? `Travailler à ${t.name}`}
                </div>
                <div style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 64, lineHeight: 0.9, letterSpacing: '-0.04em' }}>
                  {jobsCount} offre{jobsCount > 1 ? 's' : ''}
                </div>
                <div style={{ fontSize: 16, color: 'var(--leaf-fg-2)', maxWidth: 360 }}>
                  CDI, saisonniers, alternances : les entreprises d&apos;ici recrutent, à 20 minutes de chez vous.
                </div>
                <div style={{ marginTop: 'auto', fontWeight: 700 }}>Voir les offres →</div>
              </Link>
            ) : null}
            {blocks.has('newsletter') && modules.has('NEWSLETTER') ? (
              <div
                style={{ background: 'var(--ink)', color: 'var(--cream)', borderRadius: 24, padding: 34, display: 'flex', flexDirection: 'column', gap: 14 }}
              >
                <div className="eyebrow-800" style={{ color: 'var(--amber)' }}>
                  {newsletterName}
                </div>
                <div style={{ fontFamily: 'var(--font-display)', fontWeight: 800, fontSize: 34, lineHeight: 1, letterSpacing: '-0.02em' }}>
                  Le week-end local,
                  <br />
                  dans votre boîte mail.
                </div>
                <NewsletterForm
                  territoryId={t.id}
                  communes={(await getTerritoryCommunes(t.id)).map((c) => ({ id: c.id, name: c.name }))}
                  consentText={`J'accepte de recevoir la lettre d'information de ${t.name}. Mes données ne sont jamais revendues (RGPD).`}
                />
              </div>
            ) : null}
          </div>
        </section>
      ) : null}
    </div>
  );
}
