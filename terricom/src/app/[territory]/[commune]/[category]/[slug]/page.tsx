import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound, permanentRedirect } from 'next/navigation';
import { cache, Fragment, type CSSProperties, type ReactNode } from 'react';
import { JsonLd } from '@/components/JsonLd';
import { MapView } from '@/components/maps/MapView';
import { Beacon } from '@/components/portal/Beacon';
import { AppointmentCard, ContactCard, FicheActions, FicheGallery, FicheTabs } from '@/components/portal/FicheClient';
import { EstSiteNav } from '@/components/portal/EstSiteNav';
import { CustomFormCard, FollowCard } from '@/components/portal/FicheExtras';
import { Photo } from '@/components/ui/Photo';
import { contrastRatio, isHexColor } from '@/lib/color';
import { CONTRACT_TYPES, POST_KINDS, type PostKind } from '@/lib/constants';
import { directionsHref, fmtPhone, tomorrowIso, truncate } from '@/lib/format';
import { activityL, attributeNameL, categoryNameL, contractL, longDateL, openLabels, postKindL, relativeL, weeklyRowsL } from '@/lib/i18n/format';
import { sized, variantUrl } from '@/lib/images';
import { themeStyle, visibleSections } from '@/lib/minisite';
import type { MiniSite, MiniSiteSection, Socials, TerritorySettings } from '@/server/db/schema';
import { breadcrumbJsonLd, localBusinessJsonLd } from '@/server/seo';
import { planLimits } from '@/server/services/billing';
import { getPublicEstablishment, relatedCards } from '@/server/services/establishments';
import { getPortal } from '@/server/services/portal';
import { withLang } from '@/lib/i18n';
import { portalT } from '@/server/i18n';
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
  const tr = await portalT(portal);
  if (!e) return { title: tr('common.notFound'), robots: { index: false } };
  const t = portal.territory;
  const L = tr.locale;
  const url = portalUrl(t, e.path);
  const activity = activityL(e, L);
  const tx = L === 'fr' ? null : e.translations?.[L];
  const title = tr('fiche.metaTitle', { name: e.name, activity, commune: e.commune.name });
  const description = truncate(
    [tx?.tagline ?? e.tagline, tx?.description ?? e.description].filter(Boolean).join('. ') || tr('fiche.metaDesc', { activity, commune: e.commune.name }),
    158,
  );
  const cover = photosOf(e)[0]?.large;
  return {
    title: { absolute: `${title} · ${t.name}` },
    description,
    // Flux de l'établissement (actualités RSS, événements iCal), repris sur son site ou dans un agrégateur.
    alternates: { canonical: withLang(url, tr.locale), types: { 'application/rss+xml': `${url}/actualites.xml`, 'text/calendar': `${url}/agenda.ics` } },
    openGraph: {
      type: 'website',
      title,
      description,
      url,
      siteName: t.name,
      locale: { fr: 'fr_FR', en: 'en_GB', de: 'de_DE' }[L],
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
  const tr = await portalT(portal);
  const L = tr.locale;
  const activity = activityL(e, L);
  // Description et accroche traduites (IA) ; à défaut, texte français signalé comme tel.
  const tx = L === 'fr' ? null : e.translations?.[L];
  const description = tx?.description ?? e.description;
  const tagline = tx?.tagline ?? e.tagline;
  const langNote = L === 'fr' || !(e.description || e.tagline) ? null : tx?.description || tx?.tagline ? tr('fiche.translated') : tr('common.originalFrench');
  const url = portalUrl(t, e.path);
  const photos = photosOf(e);
  const related = await relatedCards(t.id, e.communeId, e.id, 4);
  const hours = weeklyRowsL(e.hours, L, tr('fiche.closedDay'));
  const attr = (a: { slug: string; label: string }) => attributeNameL(a.slug, a.label, L);
  const tags = e.attributes.filter((a) => ['HIGHLIGHT', 'SERVICE', 'LABEL'].includes(a.group)).slice(0, 6);
  const payments = e.attributes.filter((a) => a.group === 'PAYMENT').map((a) => (L === 'fr' ? a.label : attr(a)));
  const accessibility = e.attributes.filter((a) => a.group === 'ACCESSIBILITY').map(attr);
  const services = e.attributes.filter((a) => a.group === 'SERVICE').map(attr);
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
        title: tr('fiche.offerTitle', { name: e.campaignOffer.campaign.name }),
        text: e.campaignOffer.offerDescription ?? e.campaignOffer.campaign.description ?? '',
        href: `${base}/campagnes/${e.campaignOffer.campaign.slug}`,
      }
    : promoPost
      ? { big: promoPost.promoLabel!, title: promoPost.title, text: promoPost.body, href: promoPost.ctaUrl ?? '#message' }
      : null;

  const hasNews = e.news.length > 0 || e.events.length > 0;
  const claimed = ['CLAIMED', 'VALIDATED'].includes(e.status);
  // Fonctions de l'offre de l'entreprise : pages, formulaires, abonnement, mini-site.
  const limits = await planLimits(e.plan);
  const pages = limits.extraPages ? e.pages : [];
  const forms = limits.customForms ? e.forms : [];
  const mini = (e.miniSite ?? {}) as MiniSite;
  const site = Boolean(limits.miniSite && mini.enabled);
  const theme = site && isHexColor(e.themeColor) ? e.themeColor : null;
  const themeVars = themeStyle(theme);
  const hasJobs = e.jobs.length > 0 && modules.has('JOBS');
  const bookable = e.appointmentsEnabled && modules.has('APPOINTMENTS') && limits.appointments;
  const directions = e.lat && e.lng ? directionsHref(e.lat, e.lng, settings.directionsProvider) : null;
  const ctaTarget =
    site && mini.cta?.label
      ? resolveCta(mini.cta.href, {
          base,
          path: e.path,
          phone: e.phone,
          directions,
          rdv: bookable,
          forms: forms.map((f) => f.id),
          pages: pages.map((p) => p.slug),
        })
      : null;
  const cta = ctaTarget && mini.cta ? { ...ctaTarget, label: mini.cta.label } : null;
  const order = visibleSections(mini, site);
  // Le formulaire de contact reste toujours proposé (en fin de page s'il n'a pas été placé).
  if (!order.includes('contact')) order.push('contact');

  const tabFor: Partial<Record<MiniSiteSection, { id: string; label: string }>> = {
    presentation: { id: 'presentation', label: tr('fiche.tab.presentation') },
    produits: e.products.length ? { id: 'produits', label: tr('fiche.tab.products') } : undefined,
    actualites: hasNews ? { id: 'actualites', label: tr('fiche.tab.news') } : undefined,
    formulaires: forms.length ? { id: 'demandes', label: forms.length === 1 ? truncate(forms[0].title, 26) : tr('fiche.tab.requests') } : undefined,
    contact: hasJobs ? { id: 'recrutement', label: tr('fiche.tab.jobs') } : { id: 'message', label: tr('fiche.tab.contact') },
  };
  const tabs = order.flatMap((k) => (tabFor[k] ? [tabFor[k]] : []));
  const contactTab = tabs.findIndex((tb) => tb.id === 'recrutement' || tb.id === 'message');
  tabs.splice(contactTab < 0 ? tabs.length : contactTab, 0, { id: 'acces', label: tr('fiche.tab.access') });

  const mapPoints = [
    ...(e.lat && e.lng ? [{ id: e.id, lat: e.lat, lng: e.lng, name: e.name, color: e.color }] : []),
    ...related
      .filter((r) => r.lat && r.lng)
      .map((r) => ({ id: r.id, lat: r.lat!, lng: r.lng!, name: r.name, color: r.color, subtitle: r.activity, href: `${base}${r.path}` })),
  ];
  const hostLabel = url.replace(/^https?:\/\//, '');

  const blocks: Record<MiniSiteSection, ReactNode> = {
    presentation: (
      <section id="presentation" style={{ scrollMarginTop: 130, display: 'flex', flexDirection: 'column', gap: 18 }}>
        <h2 className="sr-only">{tr('fiche.tab.presentation')}</h2>
        {tagline && description ? <p style={{ fontSize: 15, fontWeight: 700, color: 'var(--muted-2)', margin: 0 }}>{tagline}</p> : null}
        <p
          style={{ fontSize: 19, lineHeight: 1.6, margin: 0, maxWidth: 720, textWrap: 'pretty', whiteSpace: 'pre-line' }}
          lang={L !== 'fr' && !tx?.description && e.description ? 'fr' : undefined}
        >
          {description ?? tagline ?? tr('fiche.defaultDesc', { name: e.name, commune: e.commune.name, activity })}
        </p>
        {langNote ? <p style={{ margin: 0, fontSize: 12, color: 'var(--muted)' }}>{langNote}</p> : null}
        {labels.length ? (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <h3 className="h3" style={{ margin: '6px 0 0' }}>
              {tr('fiche.labels')}
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
                  {attr(l)}
                </li>
              ))}
            </ul>
          </div>
        ) : null}
        {!claimed ? (
          <div className="alert alert-info" style={{ fontSize: 14 }}>
            {tr('fiche.unclaimed.1')}{' '}
            <a href={appUrl(`/pro/revendiquer?fiche=${e.id}`)} style={{ fontWeight: 700 }}>
              {tr('fiche.unclaimed.2')}
            </a>{' '}
            {tr('fiche.unclaimed.3')}
          </div>
        ) : null}
      </section>
    ),
    pages: pages.length ? (
      <section aria-labelledby="pages-title">
        <h2 id="pages-title" className="h3" style={{ fontSize: 26, margin: '0 0 14px' }}>
          {tr('fiche.alsoDiscover')}
        </h2>
        <div className="auto-grid" style={{ ['--min' as string]: '220px', ['--gap' as string]: '12px' }}>
          {pages.map((pg) => (
            <Link
              key={pg.id}
              href={`${base}${e.path}/${pg.slug}`}
              className="card card-link"
              style={{ borderRadius: 14, overflow: 'hidden', color: 'inherit' }}
            >
              {pg.coverUrl ? (
                <div style={{ height: 120 }}>
                  <Photo src={sized(pg.coverUrl, 500, 260)} alt="" color={e.color} label={pg.title} />
                </div>
              ) : null}
              <div style={{ padding: '12px 14px', display: 'flex', flexDirection: 'column', gap: 4 }}>
                <b style={{ fontSize: 16 }}>{pg.title}</b>
                <span style={{ fontSize: 13, color: 'var(--muted)' }}>{truncate(pg.body.replace(/[#*\[\]()]/g, ''), 90)}</span>
                <span style={{ fontSize: 13, fontWeight: 700, color: 'var(--green)' }}>{tr('fiche.readPage')}</span>
              </div>
            </Link>
          ))}
        </div>
      </section>
    ) : null,
    offre: offer ? (
      <div className="promo-banner">
        <div className="display" style={{ fontSize: 40, lineHeight: 1, color: 'var(--ink)' }}>
          {offer.big}
        </div>
        <div>
          <div style={{ fontWeight: 700, fontSize: 16, color: 'var(--ink)' }}>{offer.title}</div>
          {offer.text ? <div style={{ fontSize: 14, color: 'var(--amber-fg-2)' }}>{truncate(offer.text, 160)}</div> : null}
        </div>
        <Link href={offer.href} className="btn btn-dark btn-sm" style={{ whiteSpace: 'nowrap' }}>
          {tr('fiche.offerCta')}
        </Link>
      </div>
    ) : null,
    produits: e.products.length ? (
      <section id="produits" style={{ scrollMarginTop: 130 }}>
        <h2 className="h3" style={{ fontSize: 26, margin: '0 0 14px' }}>
          {tr('fiche.products')}
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
    ) : null,
    actualites: hasNews ? (
      <section id="actualites" style={{ scrollMarginTop: 130 }}>
        <h2 className="h3" style={{ fontSize: 26, margin: '0 0 14px' }}>
          {tr('fiche.tab.news')}
        </h2>
        <div style={{ display: 'flex', flexDirection: 'column' }}>
          {e.events.map((ev) => (
            <Link key={ev.id} href={`${base}/agenda/${ev.slug}`} className="news-row" style={{ color: 'inherit' }}>
              <span className="tag" style={{ alignSelf: 'flex-start', justifySelf: 'start', background: POST_KINDS.EVENT.bg }}>
                {tr('fiche.event')}
              </span>
              <div>
                <div style={{ fontWeight: 700, fontSize: 16 }}>{ev.title}</div>
                <div style={{ fontSize: 14, color: 'var(--muted)' }}>
                  {longDateL(ev.startsAt, L)}
                  {ev.priceText ? ` · ${ev.priceText}` : ''}
                </div>
              </div>
              <span style={{ fontSize: 12, color: 'var(--green)', fontWeight: 700 }}>{tr('common.see')}</span>
            </Link>
          ))}
          {e.news.map((n) => (
            <article key={n.id} className="news-row">
              <span className="tag" style={{ alignSelf: 'flex-start', justifySelf: 'start', background: POST_KINDS[n.kind as PostKind].bg }}>
                {postKindL(n.kind as PostKind, POST_KINDS[n.kind as PostKind].short, L)}
              </span>
              <div>
                <h3 style={{ fontWeight: 700, fontSize: 16, margin: 0, fontFamily: 'var(--font-body)' }}>{n.title}</h3>
                {n.body ? <div style={{ fontSize: 14, color: 'var(--muted)' }}>{truncate(n.body, 180)}</div> : null}
              </div>
              <time dateTime={(n.publishedAt ?? n.createdAt).toISOString()} style={{ fontSize: 12, color: 'var(--muted)' }}>
                {relativeL(n.publishedAt ?? n.createdAt, L)}
              </time>
            </article>
          ))}
        </div>
      </section>
    ) : null,
    formulaires: forms.length ? (
      <section id="demandes" style={{ scrollMarginTop: 130, display: 'flex', flexDirection: 'column', gap: 14 }}>
        <h2 className="sr-only">{tr('fiche.tab.requests')}</h2>
        {forms.map((f) => (
          <div key={f.id} id={`form-${f.id}`} style={{ scrollMarginTop: 130 }}>
            <CustomFormCard form={{ id: f.id, title: f.title, intro: f.intro, fields: f.fields, submitLabel: f.submitLabel }} name={e.name} />
          </div>
        ))}
      </section>
    ) : null,
    contact: (
      <section
        id={hasJobs ? 'recrutement' : 'message'}
        style={{ scrollMarginTop: 130, display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(260px,1fr))', gap: 14 }}
      >
        {hasJobs
          ? e.jobs.map((j) => (
              <Link
                key={j.id}
                href={`${base}/emploi/${j.slug}`}
                style={{ background: 'var(--leaf)', borderRadius: 18, padding: 20, display: 'flex', flexDirection: 'column', gap: 6, color: 'inherit' }}
              >
                <div style={{ fontSize: 12, fontWeight: 800, letterSpacing: '0.07em', textTransform: 'uppercase', color: 'var(--leaf-fg)' }}>
                  {tr('fiche.hiring')}
                </div>
                <div className="display" style={{ fontSize: 22 }}>
                  {j.title}
                </div>
                <div style={{ fontSize: 14, color: 'var(--leaf-fg-2)' }}>
                  {[contractL(j.contractType, CONTRACT_TYPES[j.contractType].label, L), j.startText, e.commune.name].filter(Boolean).join(' · ')}
                </div>
              </Link>
            ))
          : null}
        <div id={hasJobs ? 'message' : undefined} style={{ scrollMarginTop: 130 }}>
          <ContactCard establishmentId={e.id} name={e.name} />
        </div>
      </section>
    ),
  };
  const colorHero = site && mini.hero === 'color';
  const heroBg = theme ?? e.color;
  const heroFg = contrastRatio(heroBg, '#ffffff') >= contrastRatio(heroBg, '#14201b') ? '#ffffff' : '#14201b';
  const identity = (
    <span style={{ fontSize: 12, fontWeight: 800, color: colorHero ? heroFg : e.color, textTransform: 'uppercase', letterSpacing: '0.07em' }}>
      {activity} · {e.commune.name}
    </span>
  );
  return (
    <div style={themeVars} className={site ? 'est-site' : undefined}>
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
          {tr('fiche.backMap')}
        </Link>
        <nav aria-label={tr('fiche.breadcrumb')}>
          <Link href={base || '/'} style={{ color: 'inherit' }}>
            {t.name}
          </Link>{' '}
          ›{' '}
          <Link href={`${base}/${e.commune.slug}`} style={{ color: 'inherit' }}>
            {e.commune.name}
          </Link>{' '}
          ›{' '}
          <Link href={`${base}/${e.commune.slug}/${e.category.slug}`} style={{ color: 'inherit' }}>
            {categoryNameL(e.category.slug, e.category.name, L)}
          </Link>
        </nav>
        <span className="mono hide-sm" style={{ marginLeft: 'auto', fontSize: 12, background: 'var(--sand)', padding: '4px 9px', borderRadius: 6 }}>
          {hostLabel}
        </span>
      </div>

      {site && pages.length ? (
        <EstSiteNav
          base={base}
          path={e.path}
          pages={pages}
          current={null}
          labels={{ aria: tr('fiche.sitePages', { name: e.name }), home: tr('fiche.siteHome'), contact: tr('fiche.tab.contact') }}
        />
      ) : null}

      {colorHero ? (
        <div className="container" style={{ marginTop: 14 }}>
          <div className="est-hero" style={{ background: heroBg, color: heroFg }}>
            {e.logoUrl ? (
              <img src={sized(e.logoUrl, 200, 200) ?? e.logoUrl} alt={tr('fiche.logo', { name: e.name })} width={88} height={88} className="est-hero-logo" />
            ) : null}
            <div style={{ minWidth: 0, display: 'flex', flexDirection: 'column', gap: 10 }}>
              {identity}
              <h1 className="display" style={{ fontSize: 'clamp(40px,5vw,72px)', letterSpacing: '-0.035em', lineHeight: 0.95, margin: 0 }}>
                {e.name}
              </h1>
              {mini.headline ? <p style={{ margin: 0, fontSize: 19, lineHeight: 1.45, maxWidth: 640 }}>{mini.headline}</p> : null}
              {cta ? (
                <a
                  href={cta.href}
                  className="btn"
                  style={{ alignSelf: 'flex-start', background: heroFg, color: heroBg, borderRadius: 999, fontWeight: 800, marginTop: 4 }}
                  {...(cta.external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                >
                  {cta.label}
                </a>
              ) : null}
            </div>
          </div>
        </div>
      ) : null}

      <div className="container" style={{ marginTop: 14 }}>
        <FicheGallery photos={photos} stamp={localMade ? tr('fiche.madeIn', { name: t.name }) : null} color={e.color} name={e.name} />
      </div>

      <div
        className="container split"
        style={{ ['--cols' as string]: 'minmax(0,1fr) 380px', ['--gap' as string]: '40px', ['--align' as string]: 'start', paddingTop: 30, paddingBottom: 60 }}
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: 34, minWidth: 0 }}>
          {colorHero ? (
            e.status === 'VALIDATED' || tags.length ? (
              <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', alignItems: 'center' }}>
                {e.status === 'VALIDATED' ? (
                  <span className="mint-tag" title={tr('fiche.verifiedTitle')}>
                    {tr('fiche.verified')}
                  </span>
                ) : null}
                {tags.map((tg) => (
                  <span
                    key={tg.slug}
                    style={{ fontSize: 13, padding: '6px 12px', borderRadius: 999, background: 'var(--mint)', color: 'var(--green)', fontWeight: 600 }}
                  >
                    {attr(tg)}
                  </span>
                ))}
              </div>
            ) : null
          ) : (
            <div style={{ display: 'flex', gap: 18, alignItems: 'flex-start' }}>
              {e.logoUrl ? (
                <img
                  src={sized(e.logoUrl, 160, 160) ?? e.logoUrl}
                  alt={tr('fiche.logo', { name: e.name })}
                  width={72}
                  height={72}
                  style={{ width: 72, height: 72, borderRadius: 18, objectFit: 'contain', background: '#fff', border: '1px solid var(--line)', flexShrink: 0 }}
                />
              ) : null}
              <div style={{ minWidth: 0 }}>
                <div style={{ display: 'flex', gap: 8, alignItems: 'center', marginBottom: 10, flexWrap: 'wrap' }}>
                  {identity}
                  {e.status === 'VALIDATED' ? (
                    <span className="mint-tag" title={tr('fiche.verifiedTitle')}>
                      {tr('fiche.verified')}
                    </span>
                  ) : null}
                </div>
                <h1 className="display" style={{ fontSize: 'clamp(40px,4.6vw,64px)', letterSpacing: '-0.035em', lineHeight: 0.95, margin: '0 0 16px' }}>
                  {e.name}
                </h1>
                {site && mini.headline ? (
                  <p style={{ margin: '0 0 14px', fontSize: 18, lineHeight: 1.45, color: 'var(--muted-3)', maxWidth: 640 }}>{mini.headline}</p>
                ) : null}
                {tags.length ? (
                  <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                    {tags.map((tg) => (
                      <span
                        key={tg.slug}
                        style={{ fontSize: 13, padding: '6px 12px', borderRadius: 999, background: 'var(--mint)', color: 'var(--green)', fontWeight: 600 }}
                      >
                        {attr(tg)}
                      </span>
                    ))}
                  </div>
                ) : null}
                {cta ? (
                  <a
                    href={cta.href}
                    className="btn btn-dark"
                    style={{ marginTop: 16, borderRadius: 999 }}
                    {...(cta.external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                  >
                    {cta.label}
                  </a>
                ) : null}
              </div>
            </div>
          )}

          <FicheTabs tabs={tabs} />

          {order.map((k) => (
            <Fragment key={k}>{blocks[k]}</Fragment>
          ))}

          {related.length ? (
            <section>
              <h2 className="h3" style={{ fontSize: 26, margin: '0 0 14px' }}>
                {tr('fiche.alsoIn', { commune: e.commune.name })}
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
              {openLabels(e.open, L).long}
            </div>
            <FicheActions
              establishmentId={e.id}
              territoryId={t.id}
              phone={e.phone}
              directionsUrl={directions}
              website={e.website}
              shareUrl={url}
              name={e.name}
            />
            <address style={{ fontSize: 14, lineHeight: 1.5, fontStyle: 'normal' }}>
              <div style={{ fontWeight: 600 }}>
                {[e.street, [e.postalCode ?? e.commune.postalCodes?.[0], e.commune.name].filter(Boolean).join(' ')].filter(Boolean).join(', ')}
              </div>
              {e.phone ? <div style={{ color: 'var(--muted)' }}>{fmtPhone(e.phone)}</div> : null}
              {e.serviceArea ? <div style={{ color: 'var(--muted)' }}>{tr('fiche.serviceArea', { area: e.serviceArea })}</div> : null}
              {e.email ? (
                <div>
                  <a href={`mailto:${e.email}`} style={{ fontWeight: 600 }}>
                    {e.email}
                  </a>
                </div>
              ) : null}
            </address>
            {socialLinks.length ? (
              <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }} aria-label={tr('fiche.socials')}>
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
                  ariaLabel={tr('fiche.mapAria')}
                  style={{ width: '100%', height: '100%' }}
                />
              </div>
            ) : null}
            <div>
              <h2 style={{ fontWeight: 700, fontSize: 14, margin: '0 0 6px', fontFamily: 'var(--font-body)' }}>{tr('fiche.hours')}</h2>
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
                <div style={{ fontSize: 13, color: 'var(--muted)' }}>{tr('fiche.callFirst')}</div>
              )}
              {nextException ? (
                <div style={{ fontSize: 12, color: 'var(--warn-fg)', marginTop: 8, fontWeight: 600 }}>
                  {nextException.label
                    ? tr('fiche.closedOn', { label: nextException.label, date: longDateL(nextException.date, L).toLowerCase() })
                    : tr('fiche.closedException', { date: longDateL(nextException.date, L).toLowerCase() })}
                </div>
              ) : null}
            </div>
            {payments.length || accessibility.length || services.length || e.priceInfo || e.accessibilityInfo ? (
              <div style={{ display: 'flex', flexDirection: 'column', gap: 6, fontSize: 13, borderTop: '1px solid var(--line)', paddingTop: 14 }}>
                {payments.length ? (
                  <div style={rowLine}>
                    <span style={{ color: 'var(--muted)' }}>{tr('fiche.payment')}</span>
                    <b style={{ textAlign: 'right' }}>{payments.map((p) => (p === 'Carte bancaire' ? 'CB' : p)).join(' · ')}</b>
                  </div>
                ) : null}
                {e.priceInfo ? (
                  <div style={rowLine}>
                    <span style={{ color: 'var(--muted)' }}>{tr('fiche.prices')}</span>
                    <b style={{ textAlign: 'right' }}>{e.priceInfo}</b>
                  </div>
                ) : null}
                {accessibility.length ? (
                  <div style={rowLine}>
                    <span style={{ color: 'var(--muted)' }}>{tr('fiche.accessibility')}</span>
                    <b style={{ textAlign: 'right' }}>{accessibility.join(' · ')}</b>
                  </div>
                ) : null}
                {e.accessibilityInfo ? <div style={{ color: 'var(--muted)', fontSize: 12 }}>{e.accessibilityInfo}</div> : null}
                {services.length ? (
                  <div style={rowLine}>
                    <span style={{ color: 'var(--muted)' }}>{tr('fiche.services')}</span>
                    <b style={{ textAlign: 'right' }}>{services.slice(0, 3).join(' · ')}</b>
                  </div>
                ) : null}
              </div>
            ) : null}
          </div>

          {bookable ? (
            <div id="rendez-vous" style={{ scrollMarginTop: 90 }}>
              <AppointmentCard
                establishmentId={e.id}
                info={e.appointmentInfo}
                services={e.products.filter((p) => p.kind === 'SERVICE').map((p) => p.name)}
                minDate={tomorrowIso()}
              />
            </div>
          ) : null}

          {limits.customerNewsletter && claimed ? <FollowCard establishmentId={e.id} name={e.name} /> : null}

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
            {tr('fiche.yours')}
            <b style={{ color: 'var(--green)' }}>{claimed ? tr('fiche.manage') : tr('fiche.claim')}</b>
          </a>
          {t.contactEmail ? (
            <a
              href={`mailto:${t.contactEmail}?subject=${encodeURIComponent(`Signalement : ${e.name}`)}&body=${encodeURIComponent(`Fiche : ${url}\n\nInformation à corriger :\n`)}`}
              style={{ fontSize: 12, color: 'var(--muted)', textAlign: 'center' }}
            >
              {tr('fiche.report')}
            </a>
          ) : null}
        </aside>
      </div>
    </div>
  );
}

/** Destination du bouton principal du mini-site. */
function resolveCta(
  href: string,
  o: { base: string; path: string; phone: string | null; directions: string | null; rdv: boolean; forms: string[]; pages: string[] },
): { href: string; external?: boolean } | null {
  if (href === 'tel') return o.phone ? { href: `tel:${o.phone.replace(/\s/g, '')}` } : null;
  if (href === 'itineraire') return o.directions ? { href: o.directions, external: true } : null;
  if (href === 'contact') return { href: '#message' };
  if (href === 'rdv') return o.rdv ? { href: '#rendez-vous' } : null;
  if (href.startsWith('form:')) return o.forms.includes(href.slice(5)) ? { href: `#form-${href.slice(5)}` } : null;
  if (href.startsWith('page:')) return o.pages.includes(href.slice(5)) ? { href: `${o.base}${o.path}/${href.slice(5)}` } : null;
  if (/^https:\/\/[^\s]+$/.test(href)) return { href, external: true };
  return null;
}
