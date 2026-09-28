import { WEEKDAYS, type EventKind, type Family, type PostKind } from '../constants';
import {
  fmtDayMonth,
  fmtEventBadge,
  fmtEventHours,
  fmtHourOf,
  fmtInt,
  fmtLongDate,
  fmtTime,
  parisDate,
  parisParts,
  relativeTime,
  TZ,
  WEEKDAYS_SHORT,
} from '../format';
import { weeklyRows, type HoursSlot, type OpenStatus } from '../hours';
import { INTL, type Locale } from './index';

/**
 * Formats et libellés traduits du portail. En français, les fonctions d'origine sont utilisées telles quelles
 * (le rendu français reste identique à la maquette).
 */

export const WEEKDAY_NAMES: Record<Locale, readonly string[]> = {
  fr: WEEKDAYS,
  en: ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
  de: ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'],
};

/** Jours abrégés : « Lun. » / « Mon » / « Mo. ». */
export const WEEKDAY_SHORT_NAMES: Record<Locale, readonly string[]> = {
  fr: WEEKDAYS_SHORT,
  en: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
  de: ['Mo.', 'Di.', 'Mi.', 'Do.', 'Fr.', 'Sa.', 'So.'],
};

/** Entier formaté : « 1 234 » / « 1,234 » / « 1.234 ». */
export function intL(n: number | null | undefined, locale: Locale): string {
  if (locale === 'fr' || n === null || n === undefined || Number.isNaN(n)) return fmtInt(n);
  return new Intl.NumberFormat(INTL[locale]).format(Math.round(n));
}

/** « 14/12 » (fr, de : « 14.12. ») pour une date ISO (AAAA-MM-JJ). */
export function shortDateL(iso: string, locale: Locale): string {
  const [, m, d] = iso.split('-');
  if (locale === 'de') return `${d}.${m}.`;
  return `${d}/${m}`;
}

const toDate = (d: Date | string) => (typeof d === 'string' ? new Date(d.length === 10 ? `${d}T12:00:00` : d) : d);
const capitalize = (s: string) => s.charAt(0).toUpperCase() + s.slice(1);

/** « 9h30 » (fr), « 9:30 » (en, de). */
export function timeL(t: string | null | undefined, locale: Locale): string {
  if (!t) return '';
  if (locale === 'fr') return fmtTime(t);
  const [h, m] = t.split(':');
  return `${Number(h)}:${(m ?? '00').padStart(2, '0')}`;
}

function minL(m: number, locale: Locale): string {
  return timeL(`${Math.floor((m % 1440) / 60)}:${String(m % 60).padStart(2, '0')}`, locale);
}

/** Heure d'un instant : « 10h » (fr), « 10:00 » (en, de). */
export function hourOfL(d: Date, locale: Locale): string {
  if (locale === 'fr') return fmtHourOf(d);
  const p = parisParts(d);
  return `${p.hour}:${String(p.minute).padStart(2, '0')}`;
}

/** « 14 déc. » / « 14 Dec » / « 14. Dez. » */
export function dayMonthL(d: Date | string, locale: Locale): string {
  if (locale === 'fr') return fmtDayMonth(d);
  return new Intl.DateTimeFormat(INTL[locale], { timeZone: TZ, day: 'numeric', month: 'short' }).format(toDate(d));
}

/** « Samedi 14 décembre » / « Saturday 14 December » / « Samstag, 14. Dezember » */
export function longDateL(d: Date | string, locale: Locale, withYear = false): string {
  if (locale === 'fr') return fmtLongDate(d, withYear);
  return capitalize(
    new Intl.DateTimeFormat(INTL[locale], { timeZone: TZ, weekday: 'long', day: 'numeric', month: 'long', ...(withYear ? { year: 'numeric' } : {}) }).format(
      toDate(d),
    ),
  );
}

/** Temps relatif : « il y a 2 h » / « 2 hours ago » / « vor 2 Stunden ». */
export function relativeL(d: Date | string | null | undefined, locale: Locale, now = new Date()): string {
  if (!d) return '';
  if (locale === 'fr') return relativeTime(d, now);
  const date = toDate(d);
  const rtf = new Intl.RelativeTimeFormat(INTL[locale], { numeric: 'auto' });
  const diffMin = Math.round((date.getTime() - now.getTime()) / 60_000);
  if (Math.abs(diffMin) < 60) return rtf.format(diffMin, 'minute');
  const diffH = Math.round(diffMin / 60);
  if (Math.abs(diffH) < 24 && parisDate(date) === parisDate(now)) return rtf.format(diffH, 'hour');
  const days = Math.round((Date.parse(parisDate(date)) - Date.parse(parisDate(now))) / 86_400_000);
  if (Math.abs(days) < 7) return rtf.format(days, 'day');
  if (Math.abs(days) < 31) return rtf.format(Math.round(days / 7), 'week');
  if (Math.abs(days) < 365) return rtf.format(Math.round(days / 30), 'month');
  return rtf.format(Math.round(days / 365), 'year');
}

/** Bandeau d'événement : « SAM. 14 DÉC. · 10H–20H » / « SAT 14 DEC · 10:00–20:00 ». */
export function eventBadgeL(start: Date, end: Date | null, locale: Locale): string {
  if (locale === 'fr') return fmtEventBadge(start, end);
  const wd = new Intl.DateTimeFormat(INTL[locale], { timeZone: TZ, weekday: 'short' }).format(start).replace('.', '');
  const hours = end && parisDate(start) === parisDate(end) ? `${hourOfL(start, locale)}–${hourOfL(end, locale)}` : hourOfL(start, locale);
  return `${wd} ${dayMonthL(start, locale)} · ${hours}`.toUpperCase();
}

/** « 10h – 20h » / « 10:00 – 20:00 » ; « à partir de 10h » / « from 10:00 ». */
export function eventHoursL(start: Date, end: Date | null, locale: Locale): string {
  if (locale === 'fr') return fmtEventHours(start, end);
  if (!end) return `${locale === 'de' ? 'ab' : 'from'} ${hourOfL(start, locale)}`;
  return parisDate(start) === parisDate(end)
    ? `${hourOfL(start, locale)} – ${hourOfL(end, locale)}`
    : `${hourOfL(start, locale)} → ${dayMonthL(end, locale)} ${hourOfL(end, locale)}`;
}

const OPEN_TEXT: Record<Exclude<Locale, 'fr'>, Record<string, string>> = {
  en: {
    unknownShort: 'Hours coming soon',
    unknownLong: 'Opening hours not provided',
    open: 'Open',
    openNow: 'Open now',
    closesAt: 'Open now · closes at {t}',
    closed: 'Closed',
    opensAt: 'Closed · opens at {t}',
    opensTomorrow: 'Closed · opens tomorrow at {t}',
    opensDay: 'Closed · opens {d} at {t}',
    temporarily: 'Temporarily closed',
  },
  de: {
    unknownShort: 'Öffnungszeiten folgen',
    unknownLong: 'Keine Öffnungszeiten angegeben',
    open: 'Geöffnet',
    openNow: 'Jetzt geöffnet',
    closesAt: 'Jetzt geöffnet · schließt um {t}',
    closed: 'Geschlossen',
    opensAt: 'Geschlossen · öffnet um {t}',
    opensTomorrow: 'Geschlossen · öffnet morgen um {t}',
    opensDay: 'Geschlossen · öffnet am {d} um {t}',
    temporarily: 'Vorübergehend geschlossen',
  },
};

/** Libellés courts et longs de l'état d'ouverture dans la langue du visiteur. */
export function openLabels(s: OpenStatus, locale: Locale): { short: string; long: string } {
  if (locale === 'fr') return { short: s.shortLabel, long: s.longLabel };
  const x = OPEN_TEXT[locale];
  const fill = (k: string, v: Record<string, string>) => x[k].replace(/\{(\w)\}/g, (_, n: string) => v[n] ?? '');
  if (s.unknown) return { short: x.unknownShort, long: x.unknownLong };
  if (s.open) {
    const until = s.raw?.untilMin;
    return { short: x.open, long: until !== null && until !== undefined ? fill('closesAt', { t: minL(until, locale) }) : x.openNow };
  }
  if (s.raw?.nextMin !== undefined) {
    const t = minL(s.raw.nextMin, locale);
    const offset = s.raw.nextOffset ?? 0;
    const long =
      offset === 0
        ? fill('opensAt', { t })
        : offset === 1
          ? fill('opensTomorrow', { t })
          : fill('opensDay', { t, d: WEEKDAY_NAMES[locale][s.raw.nextWeekday ?? 0] });
    return { short: x.closed, long };
  }
  return { short: x.closed, long: s.raw?.temporarilyClosed ? x.temporarily : x.closed };
}

/** Tableau des horaires de la semaine dans la langue du visiteur. */
export function weeklyRowsL(hours: HoursSlot[], locale: Locale, closedLabel: string, now: Date = new Date()) {
  if (locale === 'fr') return weeklyRows(hours, now);
  return weeklyRows(hours, now, { dayNames: WEEKDAY_NAMES[locale], closed: closedLabel, time: (t) => timeL(t, locale) });
}

// ─── Libellés de référence ──────────────────────────────────────────────────

export const FAMILY_NAMES: Record<Exclude<Locale, 'fr'>, Record<Family, { label: string; plural: string }>> = {
  en: {
    COMMERCE: { label: 'Shops', plural: 'shops' },
    ARTISAN: { label: 'Craftspeople', plural: 'craftspeople' },
    PRODUCTEUR: { label: 'Producers', plural: 'producers' },
    RESTAURATION: { label: 'Food & drink', plural: 'restaurants' },
    SERVICES: { label: 'Services', plural: 'services' },
  },
  de: {
    COMMERCE: { label: 'Geschäfte', plural: 'Geschäfte' },
    ARTISAN: { label: 'Handwerk', plural: 'Handwerker' },
    PRODUCTEUR: { label: 'Erzeuger', plural: 'Erzeuger' },
    RESTAURATION: { label: 'Gastronomie', plural: 'Restaurants' },
    SERVICES: { label: 'Dienstleistungen', plural: 'Dienstleister' },
  },
};

const CATEGORY_NAMES: Record<string, [en: string, de: string]> = {
  boulangerie: ['Bakery', 'Bäckerei'],
  fromagerie: ['Cheese shop', 'Käserei'],
  restaurant: ['Restaurant', 'Restaurant'],
  menuiserie: ['Joinery', 'Schreinerei'],
  'plombier-chauffagiste': ['Plumbing & heating', 'Sanitär & Heizung'],
  caviste: ['Wine shop', 'Weinhandlung'],
  maraicher: ['Market gardener', 'Gemüsebauer'],
  'metiers-d-art': ['Arts & crafts', 'Kunsthandwerk'],
  chocolatier: ['Chocolatier', 'Chocolatier'],
  auberge: ['Country inn', 'Landgasthof'],
  apiculteur: ['Beekeeper', 'Imker'],
  garage: ['Garage', 'Autowerkstatt'],
  epicerie: ['Grocery', 'Lebensmittelladen'],
  distillerie: ['Distillery', 'Brennerei'],
  bistrot: ['Bistro', 'Bistro'],
  ebeniste: ['Cabinetmaker', 'Kunsttischler'],
  boucherie: ['Butcher & deli', 'Metzgerei'],
  coiffure: ['Hairdresser', 'Friseur'],
  'institut-beaute': ['Beauty salon', 'Kosmetikstudio'],
  pharmacie: ['Pharmacy', 'Apotheke'],
  fleuriste: ['Florist', 'Blumenladen'],
  librairie: ['Bookshop & newsagent', 'Buchhandlung'],
  maconnerie: ['Masonry', 'Maurerbetrieb'],
  couvreur: ['Roofer', 'Dachdecker'],
  electricien: ['Electrician', 'Elektriker'],
  peintre: ['Painter & decorator', 'Malerbetrieb'],
  ferme: ['Farm', 'Bauernhof'],
  'brasserie-artisanale': ['Craft brewery', 'Handwerksbrauerei'],
  pizzeria: ['Pizzeria', 'Pizzeria'],
  traiteur: ['Caterer', 'Partyservice'],
  hebergement: ['Hotel & holiday home', 'Hotel & Ferienhaus'],
  superette: ['Convenience store', 'Minimarkt'],
  'pret-a-porter': ['Clothing', 'Bekleidung'],
  taxi: ['Taxi', 'Taxi'],
  cordonnerie: ['Shoe repair', 'Schuhmacher'],
  paysagiste: ['Landscaper', 'Gartenbau'],
  informatique: ['IT services', 'IT-Service'],
  conseil: ['Consulting', 'Beratung'],
  'auto-ecole': ['Driving school', 'Fahrschule'],
  opticien: ['Optician', 'Optiker'],
  'tabac-presse': ['Tobacconist & newsagent', 'Tabak & Presse'],
  bricolage: ['Hardware store', 'Baumarkt'],
};

/** Nom de catégorie traduit (catégories de référence) ; nom d'origine sinon (catégories propres au territoire). */
export function categoryNameL(slug: string, name: string, locale: Locale): string {
  if (locale === 'fr') return name;
  const x = CATEGORY_NAMES[slug];
  return x ? x[locale === 'en' ? 0 : 1] : name;
}

const ATTRIBUTE_NAMES: Record<string, [en: string, de: string]> = {
  'fabrication-locale': ['Made locally', 'Lokal hergestellt'],
  'idees-cadeaux': ['Gift ideas', 'Geschenkideen'],
  'click-collect': ['Click & collect', 'Click & Collect'],
  livraison: ['Delivery', 'Lieferung'],
  'rdv-en-ligne': ['Online booking', 'Online-Termin'],
  'vente-directe': ['Farm shop', 'Direktverkauf'],
  terrasse: ['Terrace', 'Terrasse'],
  'sur-devis': ['On quotation', 'Auf Anfrage'],
  visite: ['Visits', 'Besichtigung'],
  'depannage-chaudiere': ['Boiler repair', 'Heizungsnotdienst'],
  'produits-locaux': ['Local produce', 'Regionale Produkte'],
  'zero-dechet': ['Zero waste', 'Zero Waste'],
  'sans-gluten': ['Gluten-free', 'Glutenfrei'],
  bio: ['Organic', 'Bio'],
  'aop-comte': ['Comté PDO', 'Comté g.U.'],
  rge: ['RGE certified', 'RGE-zertifiziert'],
  'artisan-boulanger': ['Artisan baker', 'Handwerksbäcker'],
  'maitre-restaurateur': ['Maître restaurateur', 'Maître Restaurateur'],
  epv: ['Living heritage company', 'Lebendiges Kulturerbe'],
  'acces-pmr': ['Wheelchair access', 'Barrierefrei'],
  'boucle-magnetique': ['Hearing loop', 'Induktionsschleife'],
  'carte-bancaire': ['Card', 'Karte'],
  especes: ['Cash', 'Bargeld'],
  cheque: ['Cheque', 'Scheck'],
  'sans-contact': ['Contactless', 'Kontaktlos'],
  'tickets-resto': ['Meal vouchers', 'Essensgutscheine'],
  'cheques-vacances': ['Holiday vouchers', 'Urlaubsschecks'],
};

export function attributeNameL(slug: string, label: string, locale: Locale): string {
  if (locale === 'fr') return label;
  const x = ATTRIBUTE_NAMES[slug];
  return x ? x[locale === 'en' ? 0 : 1] : label;
}

export const POST_KIND_NAMES: Record<Exclude<Locale, 'fr'>, Record<PostKind, string>> = {
  en: { NEWS: 'News', PROMO: 'Offer', NOUVEAUTE: 'New', EVENT: 'Event', HOURS: 'Hours', JOB: 'Hiring' },
  de: { NEWS: 'Neuigkeit', PROMO: 'Angebot', NOUVEAUTE: 'Neu', EVENT: 'Veranstaltung', HOURS: 'Öffnungszeiten', JOB: 'Stellenangebot' },
};

export const EVENT_KIND_NAMES: Record<Exclude<Locale, 'fr'>, Record<EventKind, { label: string; plural: string }>> = {
  en: {
    MARCHE: { label: 'Market', plural: 'Markets' },
    DEGUSTATION: { label: 'Tasting', plural: 'Tastings' },
    PORTES_OUVERTES: { label: 'Open day', plural: 'Open days' },
    ATELIER: { label: 'Workshop', plural: 'Workshops' },
    CONCERT: { label: 'Concert', plural: 'Concerts' },
    ANIMATION: { label: 'Activity', plural: 'Activities' },
    SALON: { label: 'Fair', plural: 'Fairs' },
    AUTRE: { label: 'Event', plural: 'Events' },
  },
  de: {
    MARCHE: { label: 'Markt', plural: 'Märkte' },
    DEGUSTATION: { label: 'Verkostung', plural: 'Verkostungen' },
    PORTES_OUVERTES: { label: 'Tag der offenen Tür', plural: 'Tage der offenen Tür' },
    ATELIER: { label: 'Workshop', plural: 'Workshops' },
    CONCERT: { label: 'Konzert', plural: 'Konzerte' },
    ANIMATION: { label: 'Veranstaltung', plural: 'Veranstaltungen' },
    SALON: { label: 'Messe', plural: 'Messen' },
    AUTRE: { label: 'Veranstaltung', plural: 'Veranstaltungen' },
  },
};

/** Programme type d'un événement (affiché quand l'organisateur n'a pas détaillé le sien). */
export const EVENT_PROGRAM_NAMES: Record<Exclude<Locale, 'fr'>, Partial<Record<EventKind, { label: string; text: string }[]>>> = {
  en: {
    MARCHE: [
      { label: 'Opening', text: 'Stallholders set up, mulled wine' },
      { label: 'Afternoon', text: 'Children’s activities and music' },
      { label: 'Late afternoon', text: 'Traders’ raffle' },
    ],
    DEGUSTATION: [
      { label: 'Welcome', text: 'Meet the producer and their craft' },
      { label: 'Tasting', text: '3 to 5 products, with commentary' },
      { label: 'Shop', text: 'Buy on site, free gift wrapping' },
    ],
    PORTES_OUVERTES: [
      { label: 'Visit', text: 'Discover the workshop and its tools' },
      { label: 'Demonstration', text: 'The craftsperson at work, live' },
      { label: 'Q&A', text: 'Questions, orders and quotes' },
    ],
    ATELIER: [
      { label: 'Welcome', text: 'Apron provided, introduction to the materials' },
      { label: 'Workshop', text: 'Step-by-step guided session' },
      { label: 'Take home', text: 'Everyone leaves with their creation' },
    ],
  },
  de: {
    MARCHE: [
      { label: 'Eröffnung', text: 'Aufbau der Stände und Glühwein' },
      { label: 'Nachmittag', text: 'Kinderprogramm und Musik' },
      { label: 'Zum Abschluss', text: 'Tombola der Händler' },
    ],
    DEGUSTATION: [
      { label: 'Empfang', text: 'Vorstellung des Erzeugers und seines Handwerks' },
      { label: 'Verkostung', text: '3 bis 5 Produkte mit Erläuterungen' },
      { label: 'Laden', text: 'Verkauf vor Ort, Geschenkverpackung gratis' },
    ],
    PORTES_OUVERTES: [
      { label: 'Besichtigung', text: 'Werkstatt und Werkzeuge entdecken' },
      { label: 'Vorführung', text: 'Handwerk live erleben' },
      { label: 'Austausch', text: 'Fragen, Bestellungen und Angebote' },
    ],
    ATELIER: [
      { label: 'Empfang', text: 'Schürze wird gestellt, Vorstellung des Materials' },
      { label: 'Workshop', text: 'Schritt für Schritt angeleitet' },
      { label: 'Zum Mitnehmen', text: 'Alle nehmen ihr eigenes Werk mit nach Hause' },
    ],
  },
};

export const CONTRACT_NAMES: Record<Exclude<Locale, 'fr'>, Record<string, string>> = {
  en: { CDI: 'Permanent', CDD: 'Fixed-term', ALTERNANCE: 'Work-study', SAISONNIER: 'Seasonal', STAGE: 'Internship', INTERIM: 'Temp', INDEPENDANT: 'Freelance' },
  de: {
    CDI: 'Unbefristet',
    CDD: 'Befristet',
    ALTERNANCE: 'Duales Studium',
    SAISONNIER: 'Saisonstelle',
    STAGE: 'Praktikum',
    INTERIM: 'Zeitarbeit',
    INDEPENDANT: 'Freiberuflich',
  },
};

// ─── Aides d'affichage ──────────────────────────────────────────────────────

const OPEN_PILL: Record<Exclude<Locale, 'fr'>, Record<string, string>> = {
  en: { open: 'Open', opens: 'Opens at {t}', tomorrow: 'Opens tomorrow at {t}', day: 'Opens {d} at {t}', tonight: 'Open tonight' },
  de: { open: 'Geöffnet', opens: 'Öffnet um {t}', tomorrow: 'Öffnet morgen um {t}', day: 'Öffnet {d} um {t}', tonight: 'Heute Abend geöffnet' },
};

/** Pastille d'ouverture des cartes : « Ouvert · 19h00 », « Ouvre demain à 8h00 ». */
export function openPillL(s: OpenStatus, locale: Locale): string {
  if (locale === 'fr') {
    if (s.open) return `Ouvert${s.until ? ` · ${s.until}` : ''}`;
    return s.next ? `Ouvre ${s.next.when ? `${s.next.when} ` : ''}à ${s.next.time}` : s.shortLabel;
  }
  const x = OPEN_PILL[locale];
  if (s.open) return `${x.open}${s.raw?.untilMin != null ? ` · ${minL(s.raw.untilMin, locale)}` : ''}`;
  if (s.raw?.nextMin !== undefined) {
    const t = minL(s.raw.nextMin, locale);
    const offset = s.raw.nextOffset ?? 0;
    const tpl = offset === 0 ? x.opens : offset === 1 ? x.tomorrow : x.day;
    return tpl.replace('{t}', t).replace('{d}', WEEKDAY_NAMES[locale][s.raw.nextWeekday ?? 0]);
  }
  return openLabels(s, locale).short;
}

/** Activité affichée : catégorie traduite hors français (le libellé saisi par le pro est en français). */
export function activityL(e: { activity: string; categorySlug?: string; category?: { slug: string } }, locale: Locale): string {
  if (locale === 'fr') return e.activity;
  const slug = e.categorySlug ?? e.category?.slug;
  return slug ? categoryNameL(slug, e.activity, locale) : e.activity;
}

/** Étiquette d'une carte (service, label, « ouvert ce soir »). */
export function tagL(key: string | undefined, label: string, locale: Locale): string {
  if (locale === 'fr' || !key) return label;
  if (key === 'open-tonight') return OPEN_PILL[locale].tonight;
  return attributeNameL(key, label, locale);
}

/** Mois abrégé : « déc. » / « Dec » / « Dez. ». */
export function monthShortL(d: Date, locale: Locale): string {
  if (locale === 'fr') return `${['janv', 'févr', 'mars', 'avr', 'mai', 'juin', 'juil', 'août', 'sept', 'oct', 'nov', 'déc'][parisParts(d).month - 1]}.`;
  return new Intl.DateTimeFormat(INTL[locale], { timeZone: TZ, month: 'short' }).format(d);
}

export function eventKindL(kind: EventKind, locale: Locale, labels: Record<EventKind, { label: string; plural: string }>, plural = false): string {
  const x = locale === 'fr' ? labels[kind] : EVENT_KIND_NAMES[locale][kind];
  return plural ? x.plural : x.label;
}

export function postKindL(kind: PostKind, frLabel: string, locale: Locale): string {
  return locale === 'fr' ? frLabel : POST_KIND_NAMES[locale][kind];
}

export function contractL(kind: string, frLabel: string, locale: Locale): string {
  return locale === 'fr' ? frLabel : (CONTRACT_NAMES[locale][kind] ?? frLabel);
}

export function familyL(f: Family, frLabel: string, locale: Locale, plural = false): string {
  return locale === 'fr' ? frLabel : plural ? FAMILY_NAMES[locale][f].plural : FAMILY_NAMES[locale][f].label;
}
