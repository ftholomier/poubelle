import { WEEKDAYS } from './constants';
import { fmtTime, parisDate, parisParts } from './format';

/** Créneau hebdomadaire. weekday : 0 = lundi … 6 = dimanche. Heures « HH:MM » ou « HH:MM:SS ». */
export type HoursSlot = { weekday: number; opensAt: string; closesAt: string };

/** Exception datée (jour férié, fermeture exceptionnelle, ouverture spéciale). */
export type HoursException = {
  date: string; // AAAA-MM-JJ
  closed: boolean;
  opensAt?: string | null;
  closesAt?: string | null;
  label?: string | null;
};

export type OpenStatus = {
  open: boolean;
  /** « 19h00 » si ouvert : heure de fermeture */
  until: string | null;
  /** Prochaine ouverture si fermé : { when: 'demain' | 'lundi' | '', time: '8h00' } */
  next: { when: string; time: string } | null;
  /** « Ouvert » / « Fermé » */
  shortLabel: string;
  /** « Ouvert maintenant · ferme à 19h00 » / « Fermé · ouvre demain à 8h00 » */
  longLabel: string;
  /** Ouvert en soirée aujourd'hui (≥ 20 h) */
  openTonight: boolean;
  /** Aucune donnée d'horaires */
  unknown: boolean;
};

const toMin = (t: string) => {
  const [h, m] = t.split(':');
  return Number(h) * 60 + Number(m ?? 0);
};

function addDays(date: string, n: number): string {
  const d = new Date(`${date}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + n);
  return d.toISOString().slice(0, 10);
}

function weekdayOf(date: string): number {
  const d = new Date(`${date}T12:00:00Z`);
  return (d.getUTCDay() + 6) % 7;
}

/** Créneaux effectifs d'une date donnée (exceptions prioritaires). Minutes depuis minuit. */
export function slotsForDate(
  hours: HoursSlot[],
  exceptions: HoursException[],
  date: string,
): { from: number; to: number }[] {
  const exc = exceptions.filter((e) => e.date === date);
  if (exc.length) {
    if (exc.some((e) => e.closed)) return [];
    return exc
      .filter((e) => e.opensAt && e.closesAt)
      .map((e) => ({ from: toMin(e.opensAt!), to: toMin(e.closesAt!) }))
      .sort((a, b) => a.from - b.from);
  }
  const wd = weekdayOf(date);
  return hours
    .filter((h) => h.weekday === wd)
    .map((h) => ({ from: toMin(h.opensAt), to: toMin(h.closesAt) }))
    .sort((a, b) => a.from - b.from);
}

/** L'établissement est-il ouvert à cette date (AAAA-MM-JJ) et cette minute ? Gère les créneaux après minuit. */
export function isOpenAtMinute(hours: HoursSlot[], exceptions: HoursException[], date: string, minute: number): boolean {
  for (const s of slotsForDate(hours, exceptions, date)) {
    if (s.to > s.from) {
      if (minute >= s.from && minute < s.to) return true;
    } else if (minute >= s.from) {
      return true; // créneau qui passe minuit
    }
  }
  // Créneau de la veille qui déborde après minuit
  for (const s of slotsForDate(hours, exceptions, addDays(date, -1))) {
    if (s.to <= s.from && minute < s.to) return true;
  }
  return false;
}

export function isOpenAt(hours: HoursSlot[], exceptions: HoursException[], at: Date): boolean {
  const p = parisParts(at);
  return isOpenAtMinute(hours, exceptions, parisDate(at), p.hour * 60 + p.minute);
}

const fmtMin = (m: number) => fmtTime(`${Math.floor((m % 1440) / 60)}:${String(m % 60).padStart(2, '0')}`);

export function openStatus(hours: HoursSlot[], exceptions: HoursException[], now: Date = new Date()): OpenStatus {
  const unknown = hours.length === 0 && exceptions.length === 0;
  const today = parisDate(now);
  const p = parisParts(now);
  const minute = p.hour * 60 + p.minute;

  const openTonight =
    isOpenAtMinute(hours, exceptions, today, 20 * 60) || isOpenAtMinute(hours, exceptions, today, 21 * 60 + 30);

  if (unknown) {
    return {
      open: false,
      until: null,
      next: null,
      shortLabel: 'Horaires à venir',
      longLabel: 'Horaires non renseignés',
      openTonight: false,
      unknown: true,
    };
  }

  if (isOpenAtMinute(hours, exceptions, today, minute)) {
    // Heure de fermeture du créneau en cours
    let until: number | null = null;
    for (const s of slotsForDate(hours, exceptions, today)) {
      const end = s.to > s.from ? s.to : s.to + 1440;
      if (minute >= s.from && minute < end) until = end;
    }
    if (until === null) {
      for (const s of slotsForDate(hours, exceptions, addDays(today, -1))) {
        if (s.to <= s.from && minute < s.to) until = s.to;
      }
    }
    const untilLabel = until !== null ? fmtMin(until) : null;
    return {
      open: true,
      until: untilLabel,
      next: null,
      shortLabel: 'Ouvert',
      longLabel: untilLabel ? `Ouvert maintenant · ferme à ${untilLabel}` : 'Ouvert maintenant',
      openTonight,
      unknown: false,
    };
  }

  // Prochaine ouverture sur 7 jours
  for (let offset = 0; offset < 8; offset++) {
    const date = addDays(today, offset);
    const slots = slotsForDate(hours, exceptions, date).filter((s) => offset > 0 || s.from > minute);
    if (slots.length) {
      const time = fmtMin(slots[0].from);
      const when = offset === 0 ? '' : offset === 1 ? 'demain' : WEEKDAYS[weekdayOf(date)].toLowerCase();
      return {
        open: false,
        until: null,
        next: { when, time },
        shortLabel: 'Fermé',
        longLabel: `Fermé · ouvre ${when ? `${when} ` : ''}à ${time}`,
        openTonight,
        unknown: false,
      };
    }
  }
  return {
    open: false,
    until: null,
    next: null,
    shortLabel: 'Fermé',
    longLabel: 'Fermé temporairement',
    openTonight: false,
    unknown: false,
  };
}

/** Lignes du tableau d'horaires hebdomadaire (Lundi … Dimanche). */
export function weeklyRows(
  hours: HoursSlot[],
  now: Date = new Date(),
): { day: string; label: string; isToday: boolean; closed: boolean }[] {
  const todayWd = parisParts(now).weekday;
  return WEEKDAYS.map((day, wd) => {
    const slots = hours
      .filter((h) => h.weekday === wd)
      .sort((a, b) => toMin(a.opensAt) - toMin(b.opensAt))
      .map((h) => `${fmtTime(h.opensAt)} – ${fmtTime(h.closesAt)}`);
    return { day, label: slots.length ? slots.join(', ') : 'Fermé', isToday: wd === todayWd, closed: !slots.length };
  });
}

/** Horaires au format schema.org (openingHoursSpecification). */
export function toSchemaOrgHours(hours: HoursSlot[]) {
  const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
  return hours.map((h) => ({
    '@type': 'OpeningHoursSpecification',
    dayOfWeek: `https://schema.org/${dayNames[h.weekday]}`,
    opens: h.opensAt.slice(0, 5),
    closes: h.closesAt.slice(0, 5),
  }));
}

/** Jours fériés français (métropole) d'une année — utilisés pour rappeler les horaires exceptionnels. */
export function frenchHolidays(year: number): { date: string; label: string }[] {
  // Calcul de Pâques (algorithme de Meeus/Jones/Butcher)
  const a = year % 19;
  const b = Math.floor(year / 100);
  const c = year % 100;
  const d = Math.floor(b / 4);
  const e = b % 4;
  const f = Math.floor((b + 8) / 25);
  const g = Math.floor((b - f + 1) / 3);
  const h = (19 * a + b - d - g + 15) % 30;
  const i = Math.floor(c / 4);
  const k = c % 4;
  const l = (32 + 2 * e + 2 * i - h - k) % 7;
  const m = Math.floor((a + 11 * h + 22 * l) / 451);
  const month = Math.floor((h + l - 7 * m + 114) / 31);
  const day = ((h + l - 7 * m + 114) % 31) + 1;
  const easter = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
  const y = (md: string) => `${year}-${md}`;
  return [
    { date: y('01-01'), label: "Jour de l'an" },
    { date: addDays(easter, 1), label: 'Lundi de Pâques' },
    { date: y('05-01'), label: 'Fête du travail' },
    { date: y('05-08'), label: 'Victoire 1945' },
    { date: addDays(easter, 39), label: 'Ascension' },
    { date: addDays(easter, 50), label: 'Lundi de Pentecôte' },
    { date: y('07-14'), label: 'Fête nationale' },
    { date: y('08-15'), label: 'Assomption' },
    { date: y('11-01'), label: 'Toussaint' },
    { date: y('11-11'), label: 'Armistice' },
    { date: y('12-25'), label: 'Noël' },
  ].sort((x, z) => x.date.localeCompare(z.date));
}

/** Complément du nom d'un jour férié : « de Noël », « de la Toussaint », « du 14 juillet »… */
export function holidayOf(label: string): string {
  const map: Record<string, string> = {
    "Jour de l'an": 'du jour de l’an',
    'Lundi de Pâques': 'du lundi de Pâques',
    'Fête du travail': 'du 1er mai',
    'Victoire 1945': 'du 8 mai',
    Ascension: 'de l’Ascension',
    'Lundi de Pentecôte': 'du lundi de Pentecôte',
    'Fête nationale': 'du 14 juillet',
    Assomption: 'du 15 août',
    Toussaint: 'de la Toussaint',
    Armistice: 'du 11 novembre',
    Noël: 'de Noël',
  };
  return map[label] ?? `du ${label.toLowerCase()}`;
}

/** Prochain jour férié dans les N jours, s'il y en a un. */
export function upcomingHoliday(now: Date = new Date(), withinDays = 45): { date: string; label: string } | null {
  const today = parisDate(now);
  const limit = addDays(today, withinDays);
  const year = Number(today.slice(0, 4));
  const all = [...frenchHolidays(year), ...frenchHolidays(year + 1)];
  return all.find((h) => h.date >= today && h.date <= limit) ?? null;
}

export { addDays as addDaysIso, weekdayOf as weekdayOfIso };
