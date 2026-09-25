import { MONTHS_SHORT, WEEKDAYS } from './constants';

export const TZ = 'Europe/Paris';

const intFmt = new Intl.NumberFormat('fr-FR');

/** 1284 → « 1 284 » (espace simple, comme dans la charte). */
export function fmtInt(n: number | null | undefined): string {
  if (n === null || n === undefined || Number.isNaN(n)) return '—';
  return intFmt.format(Math.round(n)).replace(/ | /g, ' ');
}

export function fmtDecimal(n: number, digits = 1): string {
  return new Intl.NumberFormat('fr-FR', { minimumFractionDigits: digits, maximumFractionDigits: digits })
    .format(n)
    .replace(/ | /g, ' ');
}

/** Montant en centimes → « 9 000 € » (ou avec décimales si nécessaire). */
export function fmtEuros(cents: number | null | undefined, opts: { decimals?: boolean } = {}): string {
  if (cents === null || cents === undefined) return '—';
  const value = cents / 100;
  const decimals = opts.decimals ?? !Number.isInteger(value);
  return (
    new Intl.NumberFormat('fr-FR', {
      minimumFractionDigits: decimals ? 2 : 0,
      maximumFractionDigits: decimals ? 2 : 0,
    })
      .format(value)
      .replace(/ | /g, ' ') + ' €'
  );
}

export function fmtPercent(n: number, digits = 0): string {
  return `${fmtDecimal(n, digits)} %`;
}

/** « 06:30:00 » → « 6h30 » ; « 19:00 » → « 19h00 ». */
export function fmtTime(t: string | null | undefined): string {
  if (!t) return '';
  const [h, m] = t.split(':');
  return `${Number(h)}h${(m ?? '00').padStart(2, '0')}`;
}

/** « 8h », « 16h30 » (heures rondes sans minutes). */
export function fmtTimeShort(t: string | null | undefined): string {
  if (!t) return '';
  const [h, m] = t.split(':');
  return m && m !== '00' ? `${Number(h)}h${m}` : `${Number(h)}h`;
}

export const WEEKDAYS_SHORT = ['Lun.', 'Mar.', 'Mer.', 'Jeu.', 'Ven.', 'Sam.', 'Dim.'];
export const WEEKDAYS_LONG = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

/** Parties d'une date dans le fuseau de Paris. */
export function parisParts(d: Date): { year: number; month: number; day: number; hour: number; minute: number; weekday: number } {
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone: TZ,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    weekday: 'short',
  }).formatToParts(d);
  const get = (type: string) => parts.find((p) => p.type === type)?.value ?? '0';
  const wd = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].indexOf(get('weekday'));
  return {
    year: Number(get('year')),
    month: Number(get('month')),
    day: Number(get('day')),
    hour: Number(get('hour')),
    minute: Number(get('minute')),
    weekday: wd, // 0 = lundi
  };
}

/** Instant correspondant à une date et une heure « à l'heure de Paris » (AAAA-MM-JJ, HH:MM). */
export function fromParisLocal(date: string, time: string): Date {
  const [y, m, d] = date.split('-').map(Number);
  const [hh, mm] = time.split(':').map(Number);
  const guess = Date.UTC(y, m - 1, d, hh, mm);
  const p = parisParts(new Date(guess));
  const offset = Date.UTC(p.year, p.month - 1, p.day, p.hour, p.minute) - guess;
  return new Date(guess - offset);
}

/** Date locale (Paris) au format ISO AAAA-MM-JJ. */
export function parisDate(d: Date = new Date()): string {
  const p = parisParts(d);
  return `${p.year}-${String(p.month).padStart(2, '0')}-${String(p.day).padStart(2, '0')}`;
}

function toDate(d: Date | string): Date {
  return typeof d === 'string' ? new Date(d.length === 10 ? `${d}T12:00:00` : d) : d;
}

/** « 14 déc. » */
export function fmtDayMonth(d: Date | string): string {
  const p = parisParts(toDate(d));
  return `${p.day} ${MONTHS_SHORT[p.month - 1]}.`.replace('mai.', 'mai').replace('juin.', 'juin').replace('août.', 'août');
}

/** « Samedi 14 décembre » */
export function fmtLongDate(d: Date | string, withYear = false): string {
  const date = toDate(d);
  const s = new Intl.DateTimeFormat('fr-FR', {
    timeZone: TZ,
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    ...(withYear ? { year: 'numeric' } : {}),
  }).format(date);
  return s.charAt(0).toUpperCase() + s.slice(1);
}

/** « 14/12/2026 » */
export function fmtShortDate(d: Date | string): string {
  return new Intl.DateTimeFormat('fr-FR', { timeZone: TZ, day: '2-digit', month: '2-digit', year: 'numeric' }).format(
    toDate(d),
  );
}

/** « 12/12 09:42 » (journal d'audit) */
export function fmtStamp(d: Date | string): string {
  const p = parisParts(toDate(d));
  return `${String(p.day).padStart(2, '0')}/${String(p.month).padStart(2, '0')} ${String(p.hour).padStart(2, '0')}:${String(p.minute).padStart(2, '0')}`;
}

/** « 9:12 » si aujourd'hui, sinon « hier », « lun. », « 3 oct. » (boîte de réception). */
export function fmtInboxTime(d: Date | string, now = new Date()): string {
  const date = toDate(d);
  const today = parisDate(now);
  const day = parisDate(date);
  if (day === today) {
    const p = parisParts(date);
    return `${p.hour}:${String(p.minute).padStart(2, '0')}`;
  }
  const diffDays = Math.round((Date.parse(today) - Date.parse(day)) / 86_400_000);
  if (diffDays === 1) return 'hier';
  if (diffDays < 7) return `${WEEKDAYS[parisParts(date).weekday].slice(0, 3).toLowerCase()}.`;
  return fmtDayMonth(date);
}

/** Temps relatif à la française : « il y a 2 h », « hier », « il y a 3 j », « il y a 2 sem. ». */
export function relativeTime(d: Date | string | null | undefined, now = new Date()): string {
  if (!d) return '';
  const date = toDate(d);
  const diffMs = now.getTime() - date.getTime();
  if (diffMs < 0) {
    const ahead = -diffMs;
    if (ahead < 3_600_000) return `dans ${Math.max(1, Math.round(ahead / 60_000))} min`;
    if (ahead < 86_400_000) return `dans ${Math.round(ahead / 3_600_000)} h`;
    return `dans ${Math.round(ahead / 86_400_000)} j`;
  }
  const min = Math.floor(diffMs / 60_000);
  if (min < 1) return "à l'instant";
  if (min < 60) return `il y a ${min} min`;
  const h = Math.floor(min / 60);
  const sameDay = parisDate(date) === parisDate(now);
  if (h < 24 && sameDay) return `il y a ${h} h`;
  const days = Math.round((Date.parse(parisDate(now)) - Date.parse(parisDate(date))) / 86_400_000);
  if (days <= 1) return 'hier';
  if (days < 7) return `il y a ${days} j`;
  if (days < 31) return `il y a ${Math.round(days / 7)} sem.`;
  if (days < 365) return `il y a ${Math.round(days / 30)} mois`;
  const years = Math.round(days / 365);
  return `il y a ${years} an${years > 1 ? 's' : ''}`;
}

/** Durée écoulée compacte pour les tableaux : « auj. », « hier », « 5 j », « 3 sem. », « 2 mois ». */
export function ageShort(d: Date | string | null | undefined, now = new Date()): string {
  if (!d) return '—';
  const days = Math.round((Date.parse(parisDate(now)) - Date.parse(parisDate(toDate(d)))) / 86_400_000);
  if (days <= 0) return 'auj.';
  if (days === 1) return 'hier';
  if (days < 7) return `${days} j`;
  if (days < 31) return `${Math.round(days / 7)} sem.`;
  if (days < 365) return `${Math.round(days / 30)} mois`;
  return `${Math.round(days / 365)} an${days >= 730 ? 's' : ''}`;
}

export function pluralize(n: number, one: string, many: string): string {
  return `${fmtInt(n)} ${n > 1 ? many : one}`;
}

/** Distance à vol d'oiseau en mètres. */
export function haversine(a: { lat: number; lng: number }, b: { lat: number; lng: number }): number {
  const R = 6_371_000;
  const toRad = (x: number) => (x * Math.PI) / 180;
  const dLat = toRad(b.lat - a.lat);
  const dLng = toRad(b.lng - a.lng);
  const h = Math.sin(dLat / 2) ** 2 + Math.cos(toRad(a.lat)) * Math.cos(toRad(b.lat)) * Math.sin(dLng / 2) ** 2;
  return 2 * R * Math.asin(Math.sqrt(h));
}

/** 350 → « 350 m » ; 9400 → « 9,4 km » ; 19000 → « 19 km ». */
export function fmtDistance(meters: number | null | undefined): string {
  if (meters === null || meters === undefined || !Number.isFinite(meters)) return '';
  if (meters < 1000) return `${Math.max(50, Math.round(meters / 50) * 50)} m`;
  const km = meters / 1000;
  return km < 10 ? `${fmtDecimal(km, 1)} km` : `${Math.round(km)} km`;
}

export function initials(first?: string | null, last?: string | null): string {
  return `${(first ?? '').trim().charAt(0)}${(last ?? '').trim().charAt(0)}`.toUpperCase() || '?';
}

export function fullName(u: { firstName?: string | null; lastName?: string | null; email?: string | null }): string {
  const n = `${u.firstName ?? ''} ${u.lastName ?? ''}`.trim();
  return n || u.email || 'Utilisateur';
}

export function truncate(s: string | null | undefined, max: number): string {
  if (!s) return '';
  return s.length <= max ? s : `${s.slice(0, max - 1).trimEnd()}…`;
}

export function wordCount(s: string | null | undefined): number {
  return (s ?? '').trim().split(/\s+/).filter(Boolean).length;
}

/** Téléphone français lisible : 0381621407 → 03 81 62 14 07 */
export function fmtPhone(p: string | null | undefined): string {
  if (!p) return '';
  const digits = p.replace(/[^\d+]/g, '');
  if (/^0\d{9}$/.test(digits)) return digits.replace(/(\d{2})(?=\d)/g, '$1 ').trim();
  if (/^\+33\d{9}$/.test(digits)) return `0${digits.slice(3)}`.replace(/(\d{2})(?=\d)/g, '$1 ').trim();
  return p;
}

export function telHref(p: string): string {
  const digits = p.replace(/[^\d+]/g, '');
  return `tel:${digits.startsWith('0') ? `+33${digits.slice(1)}` : digits}`;
}

/** Lien d'itinéraire vers un point (fournisseur choisi par le territoire). */
export function directionsHref(lat: number, lng: number, provider: 'google' | 'osm' | 'apple' = 'google'): string {
  if (provider === 'osm') return `https://www.openstreetmap.org/directions?to=${lat}%2C${lng}`;
  if (provider === 'apple') return `https://maps.apple.com/?daddr=${lat},${lng}`;
  return `https://www.google.com/maps/dir/?api=1&destination=${lat}%2C${lng}`;
}

/** Heure Paris d'un instant, au format court (« 10h », « 17h30 »). */
export function fmtHourOf(d: Date): string {
  const p = parisParts(d);
  return p.minute ? `${p.hour}h${String(p.minute).padStart(2, '0')}` : `${p.hour}h`;
}

/** « 10h – 20h » (événements). */
export function fmtEventHours(start: Date, end: Date | null): string {
  if (!end) return `à partir de ${fmtHourOf(start)}`;
  const sameDay = parisDate(start) === parisDate(end);
  return sameDay ? `${fmtHourOf(start)} – ${fmtHourOf(end)}` : `${fmtHourOf(start)} → ${fmtDayMonth(end)} ${fmtHourOf(end)}`;
}

/** « SAM. 14 DÉC. · 10H–20H » (bandeau d'événement à la une). */
export function fmtEventBadge(start: Date, end: Date | null): string {
  const wd = WEEKDAYS_SHORT[parisParts(start).weekday];
  const hours = end && parisDate(start) === parisDate(end) ? `${fmtHourOf(start)}–${fmtHourOf(end)}` : fmtHourOf(start);
  return `${wd} ${fmtDayMonth(start)} · ${hours}`.toUpperCase();
}

/** Date de demain (Paris), AAAA-MM-JJ. */
export function tomorrowIso(): string {
  return parisDate(new Date(Date.now() + 86_400_000));
}
