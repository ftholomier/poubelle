import { toSchemaOrgHours } from '@/lib/hours';
import type { EstablishmentDetail } from './services/establishments';

/** Type schema.org le plus précis possible selon la catégorie (repli : LocalBusiness). */
const SCHEMA_TYPES: Record<string, string> = {
  boulangerie: 'Bakery',
  patisserie: 'Bakery',
  chocolatier: 'Store',
  fromagerie: 'Store',
  restaurant: 'Restaurant',
  auberge: 'Restaurant',
  pizzeria: 'Restaurant',
  bistrot: 'BarOrPub',
  'brasserie-artisanale': 'Brewery',
  distillerie: 'Winery',
  traiteur: 'FoodEstablishment',
  caviste: 'LiquorStore',
  epicerie: 'GroceryStore',
  superette: 'GroceryStore',
  boucherie: 'Store',
  fleuriste: 'Florist',
  librairie: 'BookStore',
  'pret-a-porter': 'ClothingStore',
  bricolage: 'HardwareStore',
  opticien: 'Optician',
  pharmacie: 'Pharmacy',
  'tabac-presse': 'Store',
  informatique: 'ComputerStore',
  'plombier-chauffagiste': 'Plumber',
  electricien: 'Electrician',
  maconnerie: 'GeneralContractor',
  couvreur: 'RoofingContractor',
  peintre: 'HousePainter',
  menuiserie: 'HomeAndConstructionBusiness',
  ebeniste: 'HomeAndConstructionBusiness',
  paysagiste: 'HomeAndConstructionBusiness',
  garage: 'AutoRepair',
  'auto-ecole': 'DrivingSchool',
  coiffure: 'HairSalon',
  'institut-beaute': 'BeautySalon',
  hebergement: 'LodgingBusiness',
  taxi: 'TaxiService',
  conseil: 'ProfessionalService',
  cordonnerie: 'LocalBusiness',
};

export function schemaTypeFor(categorySlug: string): string {
  return SCHEMA_TYPES[categorySlug] ?? 'LocalBusiness';
}

type Json = Record<string, unknown>;

/** Données structurées LocalBusiness d'une fiche (référencement local). */
export function localBusinessJsonLd(e: EstablishmentDetail, url: string, imageUrls: string[]): Json {
  const payments = e.attributes.filter((a) => a.group === 'PAYMENT').map((a) => a.label);
  const socials = Object.values((e.socials ?? {}) as Record<string, string | undefined>).filter(Boolean) as string[];
  const data: Json = {
    '@context': 'https://schema.org',
    '@type': schemaTypeFor(e.category.slug),
    '@id': `${url}#etablissement`,
    name: e.name,
    url,
    description: e.description ?? e.tagline ?? undefined,
    image: imageUrls.length ? imageUrls : undefined,
    logo: e.logoUrl ?? undefined,
    telephone: e.phone ?? undefined,
    email: e.email ?? undefined,
    address: {
      '@type': 'PostalAddress',
      streetAddress: e.street ?? undefined,
      postalCode: e.postalCode ?? e.commune.postalCodes?.[0] ?? undefined,
      addressLocality: e.commune.name,
      addressCountry: 'FR',
    },
    geo: e.lat && e.lng ? { '@type': 'GeoCoordinates', latitude: e.lat, longitude: e.lng } : undefined,
    hasMap: e.lat && e.lng ? `https://www.openstreetmap.org/?mlat=${e.lat}&mlon=${e.lng}#map=17/${e.lat}/${e.lng}` : undefined,
    openingHoursSpecification: e.hours.length ? toSchemaOrgHours(e.hours) : undefined,
    sameAs: [e.website, ...socials].filter(Boolean),
    paymentAccepted: payments.length ? payments.join(', ') : undefined,
    priceRange: e.priceInfo ?? undefined,
    areaServed: e.serviceArea ?? undefined,
    makesOffer: e.products.length
      ? e.products.slice(0, 12).map((p) => ({
          '@type': 'Offer',
          itemOffered: { '@type': p.kind === 'SERVICE' ? 'Service' : 'Product', name: p.name, description: p.description ?? undefined },
          description: p.priceText ?? undefined,
        }))
      : undefined,
  };
  return prune(data);
}

export function breadcrumbJsonLd(items: { name: string; url: string }[]): Json {
  return {
    '@context': 'https://schema.org',
    '@type': 'BreadcrumbList',
    itemListElement: items.map((it, i) => ({ '@type': 'ListItem', position: i + 1, name: it.name, item: it.url })),
  };
}

export function eventJsonLd(ev: {
  title: string;
  description: string | null;
  startsAt: Date;
  endsAt: Date | null;
  url: string;
  image: string | null;
  locationName: string | null;
  address: string | null;
  lat: number | null;
  lng: number | null;
  priceText: string | null;
  organizer: { name: string; url?: string } | null;
}): Json {
  const free = /gratuit|libre/i.test(ev.priceText ?? '');
  return prune({
    '@context': 'https://schema.org',
    '@type': 'Event',
    name: ev.title,
    description: ev.description ?? undefined,
    startDate: ev.startsAt.toISOString(),
    endDate: ev.endsAt?.toISOString(),
    eventStatus: 'https://schema.org/EventScheduled',
    eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
    url: ev.url,
    image: ev.image ? [ev.image] : undefined,
    location: {
      '@type': 'Place',
      name: ev.locationName ?? ev.address ?? undefined,
      address: ev.address ?? undefined,
      geo: ev.lat && ev.lng ? { '@type': 'GeoCoordinates', latitude: ev.lat, longitude: ev.lng } : undefined,
    },
    isAccessibleForFree: free || undefined,
    offers: free ? { '@type': 'Offer', price: 0, priceCurrency: 'EUR', url: ev.url } : undefined,
    organizer: ev.organizer ? { '@type': 'Organization', name: ev.organizer.name, url: ev.organizer.url } : undefined,
  });
}

const EMPLOYMENT: Record<string, string> = {
  CDI: 'FULL_TIME',
  CDD: 'TEMPORARY',
  ALTERNANCE: 'INTERN',
  SAISONNIER: 'TEMPORARY',
  STAGE: 'INTERN',
  INTERIM: 'TEMPORARY',
  INDEPENDANT: 'CONTRACTOR',
};

/** Offre d'emploi (Google for Jobs). */
export function jobPostingJsonLd(j: {
  title: string;
  description: string;
  missions: string[];
  profile: string[];
  contractType: string;
  publishedAt: Date | null;
  expiresAt: Date | null;
  url: string;
  company: { name: string; url: string; logo?: string | null };
  locality: string;
  postalCode?: string | null;
  street?: string | null;
  salaryText?: string | null;
}): Json {
  const html = [
    `<p>${escapeHtml(j.description)}</p>`,
    j.missions.length ? `<h3>Missions</h3><ul>${j.missions.map((m) => `<li>${escapeHtml(m)}</li>`).join('')}</ul>` : '',
    j.profile.length ? `<h3>Profil</h3><ul>${j.profile.map((m) => `<li>${escapeHtml(m)}</li>`).join('')}</ul>` : '',
  ].join('');
  const posted = j.publishedAt ?? new Date();
  const valid = j.expiresAt ?? new Date(posted.getTime() + 60 * 86_400_000);
  return prune({
    '@context': 'https://schema.org',
    '@type': 'JobPosting',
    title: j.title,
    description: html,
    datePosted: posted.toISOString().slice(0, 10),
    validThrough: valid.toISOString(),
    employmentType: EMPLOYMENT[j.contractType] ?? 'OTHER',
    url: j.url,
    hiringOrganization: { '@type': 'Organization', name: j.company.name, sameAs: j.company.url, logo: j.company.logo ?? undefined },
    jobLocation: {
      '@type': 'Place',
      address: {
        '@type': 'PostalAddress',
        streetAddress: j.street ?? undefined,
        addressLocality: j.locality,
        postalCode: j.postalCode ?? undefined,
        addressCountry: 'FR',
      },
    },
    directApply: true,
  });
}

function escapeHtml(s: string): string {
  return s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!);
}

/** Supprime récursivement les valeurs vides (schema.org tolère mal les champs nuls). */
function prune<T>(v: T): T {
  if (Array.isArray(v)) {
    const arr = v.map(prune).filter((x) => x !== undefined && x !== null && !(Array.isArray(x) && x.length === 0));
    return arr as T;
  }
  if (v && typeof v === 'object' && !(v instanceof Date)) {
    const out: Record<string, unknown> = {};
    for (const [k, val] of Object.entries(v as Record<string, unknown>)) {
      const p = prune(val);
      if (p === undefined || p === null || p === '') continue;
      if (Array.isArray(p) && p.length === 0) continue;
      if (typeof p === 'object' && !Array.isArray(p) && Object.keys(p as object).length === 0) continue;
      out[k] = p;
    }
    return out as T;
  }
  return v;
}

/** Sérialisation sûre pour une balise <script type="application/ld+json">. */
export function jsonLdString(data: unknown): string {
  return JSON.stringify(data).replace(/</g, '\\u003c').replaceAll(String.fromCharCode(0x2028), '\\u2028').replaceAll(String.fromCharCode(0x2029), '\\u2029');
}
