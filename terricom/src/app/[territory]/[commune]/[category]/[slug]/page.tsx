import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound, permanentRedirect } from 'next/navigation';
import { cache, type CSSProperties } from 'react';
import { JsonLd } from '@/components/JsonLd';
import { MapView } from '@/components/maps/MapView';
import { Beacon } from '@/components/portal/Beacon';
import { AppointmentCard, ContactCard, FicheActions, FicheGallery, FicheTabs } from '@/components/portal/FicheClient';
import { Photo } from '@/components/ui/Photo';
import { CONTRACT_TYPES, POST_KINDS, type PostKind } from '@/lib/constants';
import { directionsHref, fmtLongDate, fmtPhone, relativeTime, tomorrowIso, truncate } from '@/lib/format';
import { weeklyRows } from '@/lib/hours';
import { sized, variantUrl } from '@/lib/images';
import type { Socials, TerritorySettings } from '@/server/db/schema';
import { breadcrumbJsonLd, localBusinessJsonLd } from '@/server/seo';
import { getPublicEstablishment, relatedCards } from '@/server/services/establishments';
import { getPortal } from '@/server/services/portal';
import { appUrl, portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string; commune: string; category: string; slug: string }> };

const load = cache(async (territoryParam: string, commune: string, slug: string) => {
  const portal = await getPortal(territoryParam);
  const e = await getPublicEstablishment(portal.territory.id, commune, slug);
  return { portal, e };
});

function photosOf(e: NonNullable<Awaited<ReturnType<typeof getPublicEstablishment>>>) {
  const list = e.photos.map((m) => ({ src: variantUrl(m, 640), large: variantUrl(m, 1280), alt: m.alt ?? e.name }));
  if (!list.length && e.coverUrl) list.push({ src: sized(e.coverUrl, 640)!, large: sized(e.coverUrl, 1400)!, alt: e.name });
  return list;
}

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory, commune, slug } = await params;
  const { portal, e } = await load(territory, commune, slug);
  if (!e) return { title: 'Adresse introuvable', robots: { index: false } };
  const t = portal.territory;
  const url = portalUrl(t, e.path);
  const title = `${e.name} — ${e.activity} à ${e.commune.name}`;
  const description = truncate(
    [e.tagline, e.description].filter(Boolean).join('. ') || `${e.activity} à ${e.commune.name} : horaires, adresse, téléphone et actualités.`,
    158,
  );
  const cover = photosOf(e)[0]?.large;
  return {
    title: { absolute: `${title} · ${t.name}` },
    description,
    alternates: { canonical: url },
    openGraph: {
      type: 'website',
      title,
      description,
      url,
      siteName: t.name,
      locale: 'fr_FR',
      images: cover ? [{ url: cover, width: 1400, height: 933, alt: e.name }] : undefined,
    },
    twitter: { card: cover ? 'summary_large_image' : 'summary', title, description },
    // Une fiche précréée par import, jamais complétée, reste accessible mais n'est pas proposée aux moteurs.
    robots: e.status === 'PRECREATED' && !e.description ? { index: false, follow: true } : undefined,
  };
}

const rowLine: CSSProperties = { display: 'flex', justifyContent: 'space-between', gap: 12 };

export default async function FichePage({ params }: Props) {
  const { territory, commune, category, slug } = await params;
  const { portal, e } = await load(territory, commune, slug);
  if (!e) notFound();
  const { base, territory: t, modules } = portal;
  if (category !== e.category.slug) permanentRedirect(`${base}${e.path}`);

  const settings = (t.settings ?? {}) as TerritorySettings;
  const url = portalUrl(t, e.path);
  const photos = photosOf(e);
  const related = await relatedCards(t.id, e.communeId, e.id, 4);
  const hours = weeklyRows(e.hours);
  const tags = e.attributes.filter((a) => ['HIGHLIGHT', 'SERVICE', 'LABEL'].includes(a.group)).slice(0, 6);
  const payments = e.attributes.filter((a) => a.group === 'PAYMENT').map((a) => a.label);
  const accessibility = e.attributes.filter((a) => a.group === 'ACCESSIBILITY').map((a) => a.label);
  const services = e.attributes.filter((a) => a.group === 'SERVICE').map((a) => a.label);
  const labels = e.attributes.filter((a) => a.group === 'LABEL');
  const socials = e.socials as Socials;
  const socialLinks: [string, string][] = [];
  for (const [label, href] of [
    ['Facebook', socials.facebook],
    ['Instagram', socials.instagram],
    ['LinkedIn', socials.linkedin],
    ['TikTok', socials.tiktok],
    ['YouTube', socials.youtube],
  ] as [string, string | undefined][])
    if (href && /^https?:\/\//.test(href)) socialLinks.push([label, href]);
  const localMade = e.attributes.some((a) => a.slug === 'fabrication-locale');
  const nextException = e.exceptions.find((x) => x.closed);

  // Bandeau d'offre : participation à une campagne en cours, sinon promotion publiée.
  const promoPost = e.news.find((p) => p.kind === 'PROMO' && p.promoLabel && (!p.validTo || p.validTo >= new Date().toISOString().slice(0, 10)));
  const offer = e.campaignOffer?.offerLabel
    ? {
        big: e.campaignOffer.offerLabel,
        title: `Offre « ${e.campaignOffer.campaign.name} »`,
        text: e.campaignOffer.offerDescription ?? e.campaignOffer.campaign.description ?? '',
        href: `${base}/campagnes/${e.campaignOffer.campaign.slug}`,
      }
    : promoPost
      ? { big: promoPost.promoLabel!, title: promoPost.title, text: promoPost.body, href: promoPost.ctaUrl ?? '#message' }
      : null;

  const hasNews = e.news.length > 0 || e.events.length > 0;
  const tabs = [
    { id: 'presentation', label: 'Présentation' },
    ...(e.products.length ? [{ id: 'produits', label: 'Produits' }] : []),
    ...(hasNews ? [{ id: 'actualites', label: 'Actualités' }] : []),
    { id: 'acces', label: 'Accès' },
    ...(e.jobs.length && modules.has('JOBS') ? [{ id: 'recrutement', label: 'Recrutement' }] : [{ id: 'message', label: 'Contact' }]),
  ];

  const mapPoints = [
    ...(e.lat && e.lng ? [{ id: e.id, lat: e.lat, lng: e.lng, name: e.name, color: e.color }] : []),
    ...related
      .filter((r) => r.lat && r.lng)
      .map((r) => ({ id: r.id, lat: r.lat!, lng: r.lng!, name: r.name, color: r.color, subtitle: r.activity, href: `${base}${r.path}` })),
  ];
  const claimed = ['CLAIMED', 'VALIDATED'].includes(e.status);
  const hostLabel = url.replace(/^https?:\/\//, '');

  return (
    <div>
      <JsonLd
        data={localBusinessJsonLd(
          e,
          url,
          photos.slice(0, 6).map((p) => p.large),
        )}
      />
      <JsonLd
        data={breadcrumbJsonLd([
          { name: t.name, url: portalUrl(t, '/') },
          { name: e.commune.name, url: portalUrl(t, `/${e.commune.slug}`) },
          { name: e.category.name, url: portalUrl(t, `/${e.commune.slug}/${e.category.slug}`) },
          { name: e.name, url },
        ])}
      />
      <Beacon type="EST_VIEW" establishmentId={e.id} territoryId={t.id} communeId={e.communeId} />

      <div
        className="container"
        style={{ paddingTop: 18, display: 'flex', gap: 10, alignItems: 'center', flexWrap: 'wrap', fontSize: 13, color: 'var(--muted)' }}
      >
        <Link href={`${base}/explorer`} style={{ color: 'var(--green)', fontWeight: 700 }}>
          ← Carte
        </Link>
        <nav aria-label="Fil d'Ariane">
          <Link href={base || '/'} style={{ color: 'inherit' }}>
            {t.name}
          </Link>{' '}
          ›{' '}
          <Link href={`${base}/${e.commune.slug}`} style={{ color: 'inherit' }}>
            {e.commune.name}
          </Link>{' '}
          ›{' '}
          <Link href={`${base}/${e.commune.slug}/${e.category.slug}`} style={{ color: 'inherit' }}>
            {e.category.name}
          </Link>
        </nav>
        <span className="mono hide-sm" style={{ marginLeft: 'auto', fontSize: 12, background: 'var(--sand)', padding: '4px 9px', borderRadius: 6 }}>
          {hostLabel}
        </span>
      </div>

      <div className="container" style={{ marginTop: 14 }}>
        <FicheGallery photos={photos} stamp={localMade ? `Fait en ${t.name}` : null} color={e.color} name={e.name} />
      </div>

      <div
        className="container split"
        style={{ ['--cols' as string]: 'minmax(0,1fr) 380px', ['--gap' as string]: '40px', ['--align' as string]: 'start', paddingTop: 30, paddingBottom: 60 }}
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: 34, minWidth: 0 }}>
          <div style={{ display: 'flex', gap: 18, alignItems: 'flex-start' }}>
            {e.logoUrl ? (
              <img
                src={sized(e.logoUrl, 160, 160) ?? e.logoUrl}
                alt={`Logo ${e.name}`}
                width={72}
                height={72}
                style={{ width: 72, height: 72, borderRadius: 18, objectFit: 'contain', background: '#fff', border: '1px solid var(--line)', flexShrink: 0 }}
              />
            ) : null}
            <div style={{ minWidth: 0 }}>
              <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 10, flexWrap: 'wrap' }}>
                <span style={{ fontSize: 12, fontWeight: 800, color: e.color, textTransform: 'uppercase', letterSpacing: '0.07em' }}>
                  {e.activity} · {e.commune.name}
                </span>
                {e.status === 'VALIDATED' ? (
                  <span className="mint-tag" title="Informations vérifiées par la collectivité">
                    ✓ Fiche vérifiée
                  </span>
                ) : null}
              </div>
              <h1 className="display" style={{ fontSize: 'clamp(40px,4.6vw,64px)', letterSpacing: '-0.035em', lineHeight: 0.95, margin: '0 0 16px' }}>
                {e.name}
              </h1>
              {tags.length ? (
                <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                  {tags.map((tg) => (
                    <span
                      key={tg.slug}
                      style={{ fontSize: 13, padding: '6px 12px', borderRadius: 999, background: 'var(--mint)', color: 'var(--green)', fontWeight: 600 }}
                    >
                      {tg.label}
                    </span>
                  ))}
                </div>
              ) : null}
            </div>
          </div>

          <FicheTabs tabs={tabs} />

          <section id="presentation" style={{ scrollMarginTop: 130, display: 'flex', flexDirection: 'column', gap: 18 }}>
            <h2 className="sr-only">Présentation</h2>
            {e.tagline && e.description ? <p style={{ fontSize: 15, fontWeight: 700, color: 'var(--muted-2)', margin: 0 }}>{e.tagline}</p> : null}
            <p style={{ fontSize: 19, lineHeight: 1.6, margin: 0, maxWidth: 720, textWrap: 'pretty', whiteSpace: 'pre-line' }}>
              {e.description ?? e.tagline ?? `${e.name} vous accueille à ${e.commune.name}. ${e.activity} : poussez la porte !`}
            </p>
            {e.pages.map((pg) => (
              <div key={pg.id}>
                <h3 className="h3" style={{ margin: '6px 0 8px' }}>
                  {pg.title}
                </h3>
                <p style={{ fontSize: 16, lineHeight: 1.6, margin: 0, maxWidth: 720, whiteSpace: 'pre-line' }}>{pg.body}</p>
              </div>
            ))}
            {labels.length ? (
              <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                <h3 className="h3" style={{ margin: '6px 0 0' }}>
                  Labels &amp; certifications
                </h3>
                <ul style={{ listStyle: 'none', padding: 0, margin: 0, display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                  {labels.map((l) => (
                    <li
                      key={l.slug}
                      style={{
                        display: 'inline-flex',
                        gap: 6,
                        alignItems: 'center',
                        fontSize: 14,
                        fontWeight: 700,
                        padding: '7px 12px',
                        borderRadius: 10,
                        background: 'var(--paper)',
                        border: '1px solid var(--line)',
                      }}
                    >
                      <span aria-hidden="true" style={{ color: 'var(--green)' }}>
                        ✓
                      </span>
                      {l.label}
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
            {!claimed ? (
              <div className="alert alert-info" style={{ fontSize: 14 }}>
                Cette fiche a été créée à partir des données publiques des entreprises. Vous êtes le ou la gérante ?{' '}
                <a href={appUrl(`/pro/revendiquer?fiche=${e.id}`)} style={{ fontWeight: 700 }}>
                  Revendiquez-la gratuitement
                </a>{' '}
                pour ajouter photos, horaires et actualités.
              </div>
            ) : null}
          </section>

          {offer ? (
            <div className="promo-banner">
              <div className="display" style={{ fontSize: 40, lineHeight: 1, color: 'var(--ink)' }}>
                {offer.big}
              </div>
              <div>
                <div style={{ fontWeight: 700, fontSize: 16, color: 'var(--ink)' }}>{offer.title}</div>
                {offer.text ? <div style={{ fontSize: 14, color: 'var(--amber-fg-2)' }}>{truncate(offer.text, 160)}</div> : null}
              </div>
              <Link href={offer.href} className="btn btn-dark btn-sm" style={{ whiteSpace: 'nowrap' }}>
                J&apos;en profite
              </Link>
            </div>
          ) : null}

          {e.products.length ? (
            <section id="produits" style={{ scrollMarginTop: 130 }}>
              <h2 className="h3" style={{ fontSize: 26, margin: '0 0 14px' }}>
                Produits &amp; savoir-faire
              </h2>
              <div className="auto-grid" style={{ ['--min' as string]: '200px', ['--gap' as string]: '12px' }}>
                {e.products.map((p) => (
                  <div key={p.id} className="card" style={{ borderRadius: 14, overflow: 'hidden' }}>
                    <div style={{ height: 130 }}>
                      <Photo src={sized(p.imageUrl, 500, 300)} alt="" color={e.color} label={p.name} />
                    </div>
                    <div style={{ padding: '12px 14px' }}>
                      <div style={{ fontWeight: 700 }}>{p.name}</div>
                      {p.priceText ? <div style={{ fontSize: 13, color: 'var(--muted)' }}>{p.priceText}</div> : null}
                    </div>
                  </div>
                ))}
              </div>
            </section>
          ) : null}

          {hasNews ? (
            <section id="actualites" style={{ scrollMarginTop: 130 }}>
              <h2 className="h3" style={{ fontSize: 26, margin: '0 0 14px' }}>
                Actualités
              </h2>
              <div style={{ display: 'flex', flexDirection: 'column' }}>
                {e.events.map((ev) => (
                  <Link key={ev.id} href={`${base}/agenda/${ev.slug}`} className="news-row" style={{ color: 'inherit' }}>
                    <span className="tag" style={{ alignSelf: 'flex-start', justifySelf: 'start', background: POST_KINDS.EVENT.bg }}>
                      Événement
                    </span>
                    <div>
                      <div style={{ fontWeight: 700, fontSize: 16 }}>{ev.title}</div>
                      <div style={{ fontSize: 14, color: 'var(--muted)' }}>
                        {fmtLongDate(ev.startsAt)}
                        {ev.priceText ? ` · ${ev.priceText}` : ''}
                      </div>
                    </div>
                    <span style={{ fontSize: 12, color: 'var(--green)', fontWeight: 700 }}>Voir →</span>
                  </Link>
                ))}
                {e.news.map((n) => (
                  <article key={n.id} className="news-row">
                    <span className="tag" style={{ alignSelf: 'flex-start', justifySelf: 'start', background: POST_KINDS[n.kind as PostKind].bg }}>
                      {POST_KINDS[n.kind as PostKind].short}
                    </span>
                    <div>
                      <h3 style={{ fontWeight: 700, fontSize: 16, margin: 0, fontFamily: 'var(--font-body)' }}>{n.title}</h3>
                      {n.body ? <div style={{ fontSize: 14, color: 'var(--muted)' }}>{truncate(n.body, 180)}</div> : null}
                    </div>
                    <time dateTime={(n.publishedAt ?? n.createdAt).toISOString()} style={{ fontSize: 12, color: 'var(--muted)' }}>
                      {relativeTime(n.publishedAt ?? n.createdAt)}
                    </time>
                  </article>
                ))}
              </div>
            </section>
          ) : null}

          <section
            id={e.jobs.length && modules.has('JOBS') ? 'recrutement' : 'message'}
            style={{ scrollMarginTop: 130, display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(260px,1fr))', gap: 14 }}
          >
            {modules.has('JOBS')
              ? e.jobs.map((j) => (
                  <Link
                    key={j.id}
                    href={`${base}/emploi/${j.slug}`}
                    style={{ background: 'var(--leaf)', borderRadius: 18, padding: 20, display: 'flex', flexDirection: 'column', gap: 6, color: 'inherit' }}
                  >
                    <div style={{ fontSize: 12, fontWeight: 800, letterSpacing: '0.07em', textTransform: 'uppercase', color: 'var(--leaf-fg)' }}>
                      On recrute
                    </div>
                    <div className="display" style={{ fontSize: 22 }}>
                      {j.title}
                    </div>
                    <div style={{ fontSize: 14, color: 'var(--leaf-fg-2)' }}>
                      {[CONTRACT_TYPES[j.contractType].label, j.startText, e.commune.name].filter(Boolean).join(' · ')}
                    </div>
                  </Link>
                ))
              : null}
            <div id={e.jobs.length && modules.has('JOBS') ? 'message' : undefined} style={{ scrollMarginTop: 130 }}>
              <ContactCard establishmentId={e.id} name={e.name} />
            </div>
          </section>

          {related.length ? (
            <section>
              <h2 className="h3" style={{ fontSize: 26, margin: '0 0 14px' }}>
                Aussi à {e.commune.name}
              </h2>
              <div className="auto-grid" style={{ ['--min' as string]: '200px', ['--gap' as string]: '12px' }}>
                {related.map((r) => (
                  <Link key={r.id} href={`${base}${r.path}`} className="card card-link" style={{ borderRadius: 14, overflow: 'hidden' }}>
                    <div style={{ height: 110 }}>
                      <Photo src={sized(r.coverUrl, 400, 220)} alt="" color={r.color} label={r.name} />
                    </div>
                    <div style={{ padding: '10px 12px' }}>
                      <div style={{ fontWeight: 700, fontSize: 14 }}>{r.name}</div>
                      <div style={{ fontSize: 12, color: 'var(--muted)' }}>{r.activity}</div>
                    </div>
                  </Link>
                ))}
              </div>
            </section>
          ) : null}
        </div>

        <aside className="sticky-aside" style={{ position: 'sticky', top: 84, display: 'flex', flexDirection: 'column', gap: 14 }}>
          <div
            id="acces"
            className="card"
            style={{ borderRadius: 20, padding: 20, display: 'flex', flexDirection: 'column', gap: 16, boxShadow: 'var(--shadow-card)', scrollMarginTop: 90 }}
          >
            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 8,
                fontWeight: 700,
                color: e.open.unknown ? 'var(--muted)' : e.open.open ? 'var(--open)' : 'var(--closed)',
              }}
            >
              <span
                className={e.open.open ? 'live-dot' : undefined}
                style={{
                  width: 9,
                  height: 9,
                  borderRadius: '50%',
                  background: e.open.unknown ? 'var(--faint)' : e.open.open ? 'var(--open)' : 'var(--closed)',
                }}
              />
              {e.open.unknown ? 'Horaires non renseignés' : e.open.longLabel}
            </div>
            <FicheActions
              establishmentId={e.id}
              territoryId={t.id}
              phone={e.phone}
              directionsUrl={e.lat && e.lng ? directionsHref(e.lat, e.lng, settings.directionsProvider) : null}
              website={e.website}
              shareUrl={url}
              name={e.name}
            />
            <address style={{ fontSize: 14, lineHeight: 1.5, fontStyle: 'normal' }}>
              <div style={{ fontWeight: 600 }}>
                {[e.street, [e.postalCode ?? e.commune.postalCodes?.[0], e.commune.name].filter(Boolean).join(' ')].filter(Boolean).join(', ')}
              </div>
              {e.phone ? <div style={{ color: 'var(--muted)' }}>{fmtPhone(e.phone)}</div> : null}
              {e.serviceArea ? <div style={{ color: 'var(--muted)' }}>Intervient : {e.serviceArea}</div> : null}
              {e.email ? (
                <div>
                  <a href={`mailto:${e.email}`} style={{ fontWeight: 600 }}>
                    {e.email}
                  </a>
                </div>
              ) : null}
            </address>
            {socialLinks.length ? (
              <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }} aria-label="Réseaux sociaux">
                {socialLinks.map(([label, href]) => (
                  <a
                    key={label}
                    href={href}
                    target="_blank"
                    rel="noopener noreferrer me"
                    className="btn btn-light btn-xs"
                    style={{ borderRadius: 999, fontWeight: 700 }}
                  >
                    {label} ↗
                  </a>
                ))}
              </div>
            ) : null}
            {mapPoints.length ? (
              <div style={{ height: 180, borderRadius: 14, overflow: 'hidden' }}>
                <MapView
                  mode="fiche"
                  focusId={e.id}
                  points={mapPoints}
                  tileUrl={portal.mapConfig.tileUrl}
                  attribution={portal.mapConfig.attribution}
                  ariaLabel="Plan d'accès"
                  style={{ width: '100%', height: '100%' }}
                />
              </div>
            ) : null}
            <div>
              <h2 style={{ fontWeight: 700, fontSize: 14, margin: '0 0 6px', fontFamily: 'var(--font-body)' }}>Horaires</h2>
              {e.hours.length ? (
                hours.map((h) => (
                  <div
                    key={h.day}
                    style={{
                      ...rowLine,
                      fontSize: 13,
                      padding: '4px 8px',
                      borderRadius: 6,
                      background: h.isToday ? 'var(--mint)' : 'transparent',
                      fontWeight: h.isToday ? 700 : 400,
                    }}
                  >
                    <span>{h.day}</span>
                    <span style={{ textAlign: 'right' }}>{h.label}</span>
                  </div>
                ))
              ) : (
                <div style={{ fontSize: 13, color: 'var(--muted)' }}>Appelez avant de vous déplacer.</div>
              )}
              {nextException ? (
                <div style={{ fontSize: 12, color: 'var(--warn-fg)', marginTop: 8, fontWeight: 600 }}>
                  {nextException.label ? `${nextException.label} : fermé le ` : 'Fermeture exceptionnelle le '}
                  {fmtLongDate(nextException.date).toLowerCase()}
                </div>
              ) : null}
            </div>
            {payments.length || accessibility.length || services.length || e.priceInfo || e.accessibilityInfo ? (
              <div style={{ display: 'flex', flexDirection: 'column', gap: 6, fontSize: 13, borderTop: '1px solid var(--line)', paddingTop: 14 }}>
                {payments.length ? (
                  <div style={rowLine}>
                    <span style={{ color: 'var(--muted)' }}>Paiement</span>
                    <b style={{ textAlign: 'right' }}>{payments.map((p) => (p === 'Carte bancaire' ? 'CB' : p)).join(' · ')}</b>
                  </div>
                ) : null}
                {e.priceInfo ? (
                  <div style={rowLine}>
                    <span style={{ color: 'var(--muted)' }}>Tarifs</span>
                    <b style={{ textAlign: 'right' }}>{e.priceInfo}</b>
                  </div>
                ) : null}
                {accessibility.length ? (
                  <div style={rowLine}>
                    <span style={{ color: 'var(--muted)' }}>Accessibilité</span>
                    <b style={{ textAlign: 'right' }}>{accessibility.join(' · ')}</b>
                  </div>
                ) : null}
                {e.accessibilityInfo ? <div style={{ color: 'var(--muted)', fontSize: 12 }}>{e.accessibilityInfo}</div> : null}
                {services.length ? (
                  <div style={rowLine}>
                    <span style={{ color: 'var(--muted)' }}>Services</span>
                    <b style={{ textAlign: 'right' }}>{services.slice(0, 3).join(' · ')}</b>
                  </div>
                ) : null}
              </div>
            ) : null}
          </div>

          {e.appointmentsEnabled && modules.has('APPOINTMENTS') ? (
            <AppointmentCard
              establishmentId={e.id}
              info={e.appointmentInfo}
              services={e.products.filter((p) => p.kind === 'SERVICE').map((p) => p.name)}
              minDate={tomorrowIso()}
            />
          ) : null}

          <a
            href={claimed ? appUrl('/connexion?next=/pro') : appUrl(`/pro/revendiquer?fiche=${e.id}`)}
            style={{
              background: 'var(--sand)',
              borderRadius: 14,
              padding: '14px 16px',
              fontSize: 13,
              color: 'var(--text)',
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              gap: 10,
            }}
          >
            C&apos;est votre commerce ?<b style={{ color: 'var(--green)' }}>{claimed ? 'Gérer cette fiche →' : 'Revendiquer cette fiche →'}</b>
          </a>
          {t.contactEmail ? (
            <a
              href={`mailto:${t.contactEmail}?subject=${encodeURIComponent(`Signalement : ${e.name}`)}&body=${encodeURIComponent(`Fiche : ${url}\n\nInformation à corriger :\n`)}`}
              style={{ fontSize: 12, color: 'var(--muted)', textAlign: 'center' }}
            >
              Signaler une information erronée
            </a>
          ) : null}
        </aside>
      </div>
    </div>
  );
}
