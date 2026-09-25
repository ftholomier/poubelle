/**
 * iCalendar (RFC 5545) : fichiers « Ajouter à mon agenda », agendas publics auxquels s'abonner, et lecture
 * des agendas externes synchronisés dans l'agenda d'un territoire.
 */

function esc(s: string): string {
  return s.replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\r?\n/g, '\\n');
}

function stamp(d: Date): string {
  return d
    .toISOString()
    .replace(/[-:]/g, '')
    .replace(/\.\d{3}/, '');
}

/** Date seule (journée entière) : AAAAMMJJ dans le fuseau de Paris. */
function dateOnly(d: Date): string {
  return new Intl.DateTimeFormat('fr-CA', { timeZone: 'Europe/Paris', year: 'numeric', month: '2-digit', day: '2-digit' }).format(d).replace(/-/g, '');
}

/** Replie les lignes à 75 octets comme l'exige la norme. */
function fold(line: string): string {
  const out: string[] = [];
  let rest = line;
  while (Buffer.byteLength(rest, 'utf8') > 75) {
    let cut = 75;
    while (Buffer.byteLength(rest.slice(0, cut), 'utf8') > 75) cut--;
    out.push(rest.slice(0, cut));
    rest = ` ${rest.slice(cut)}`;
  }
  out.push(rest);
  return out.join('\r\n');
}

export type IcsEvent = {
  uid: string;
  title: string;
  description?: string | null;
  location?: string | null;
  url?: string | null;
  startsAt: Date;
  endsAt?: Date | null;
  allDay?: boolean;
  lat?: number | null;
  lng?: number | null;
  updatedAt?: Date | null;
};

function vevent(ev: IcsEvent, now: Date): string[] {
  const end = ev.endsAt ?? new Date(ev.startsAt.getTime() + 2 * 3_600_000);
  const when = ev.allDay
    ? [`DTSTART;VALUE=DATE:${dateOnly(ev.startsAt)}`, `DTEND;VALUE=DATE:${dateOnly(new Date(Math.max(end.getTime(), ev.startsAt.getTime()) + 86_400_000))}`]
    : [`DTSTART:${stamp(ev.startsAt)}`, `DTEND:${stamp(end)}`];
  return [
    'BEGIN:VEVENT',
    `UID:${ev.uid}`,
    `DTSTAMP:${stamp(now)}`,
    ...when,
    ev.updatedAt ? `LAST-MODIFIED:${stamp(ev.updatedAt)}` : '',
    `SUMMARY:${esc(ev.title)}`,
    ev.description ? `DESCRIPTION:${esc(ev.description)}` : '',
    ev.location ? `LOCATION:${esc(ev.location)}` : '',
    ev.lat && ev.lng ? `GEO:${ev.lat};${ev.lng}` : '',
    ev.url ? `URL:${ev.url}` : '',
    'END:VEVENT',
  ].filter(Boolean);
}

/** Agenda complet (abonnement depuis Google Agenda, Outlook, Apple Calendrier…). */
export function calendarIcs(name: string, events: IcsEvent[], opts: { description?: string; now?: Date } = {}): string {
  const now = opts.now ?? new Date();
  const lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//terricom//Agenda du territoire//FR',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    `X-WR-CALNAME:${esc(name)}`,
    opts.description ? `X-WR-CALDESC:${esc(opts.description)}` : '',
    'X-WR-TIMEZONE:Europe/Paris',
    'REFRESH-INTERVAL;VALUE=DURATION:PT6H',
    'X-PUBLISHED-TTL:PT6H',
    ...events.flatMap((ev) => vevent(ev, now)),
    'END:VCALENDAR',
  ].filter(Boolean);
  return lines.map(fold).join('\r\n') + '\r\n';
}

/** Fichier d'un seul événement (« Ajouter à mon agenda »). */
export function eventIcs(ev: IcsEvent): string {
  const lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//terricom//Agenda du territoire//FR',
    'CALSCALE:GREGORIAN',
    'METHOD:PUBLISH',
    ...vevent(ev, new Date()),
    'END:VCALENDAR',
  ];
  return lines.map(fold).join('\r\n') + '\r\n';
}

// ─── Lecture d'un agenda externe ────────────────────────────────────────────

export type ParsedIcsEvent = {
  uid: string;
  title: string;
  description: string;
  location: string | null;
  url: string | null;
  startsAt: Date;
  endsAt: Date | null;
  allDay: boolean;
  cancelled: boolean;
  lat: number | null;
  lng: number | null;
};

function unescapeText(s: string): string {
  return s.replace(/\\([\\;,nN])/g, (_, c: string) => (c === 'n' || c === 'N' ? '\n' : c));
}

/** Décalage (minutes) d'un fuseau IANA à un instant donné. */
function tzOffsetMinutes(timeZone: string, at: Date): number {
  try {
    const parts = new Intl.DateTimeFormat('en-US', {
      timeZone,
      hourCycle: 'h23',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
    }).formatToParts(at);
    const get = (t: string) => Number(parts.find((p) => p.type === t)?.value);
    const asUtc = Date.UTC(get('year'), get('month') - 1, get('day'), get('hour'), get('minute'), get('second'));
    return Math.round((asUtc - at.getTime()) / 60_000);
  } catch {
    return 0;
  }
}

/** Date iCalendar : UTC (…Z), heure locale d'un fuseau (TZID), heure « flottante » (Paris) ou date seule. */
function parseIcsDate(value: string, params: Record<string, string>): { date: Date; allDay: boolean } | null {
  const v = value.trim();
  const d = /^(\d{4})(\d{2})(\d{2})$/.exec(v);
  if (d || params.VALUE === 'DATE') {
    if (!d) return null;
    // Journée entière : midi, heure de Paris (évite les décalages de date).
    const utcNoon = Date.UTC(Number(d[1]), Number(d[2]) - 1, Number(d[3]), 12, 0, 0);
    return { date: new Date(utcNoon - tzOffsetMinutes('Europe/Paris', new Date(utcNoon)) * 60_000), allDay: true };
  }
  const m = /^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})?(Z)?$/.exec(v);
  if (!m) return null;
  const naive = Date.UTC(Number(m[1]), Number(m[2]) - 1, Number(m[3]), Number(m[4]), Number(m[5]), Number(m[6] ?? 0));
  if (m[7]) return { date: new Date(naive), allDay: false };
  const tz = params.TZID?.replace(/^"|"$/g, '') || 'Europe/Paris';
  // Deux passes : le décalage dépend de l'instant (heure d'été).
  let utc = naive - tzOffsetMinutes(tz, new Date(naive)) * 60_000;
  utc = naive - tzOffsetMinutes(tz, new Date(utc)) * 60_000;
  return { date: new Date(utc), allDay: false };
}

/**
 * Événements d'un fichier iCalendar (VEVENT). Les récurrences (RRULE) ne sont pas développées : seule la
 * première occurrence est reprise. Les champs inconnus sont ignorés.
 */
export function parseIcs(text: string): ParsedIcsEvent[] {
  const lines = text
    .replace(/\r\n[ \t]/g, '')
    .replace(/\n[ \t]/g, '')
    .split(/\r?\n/);
  const out: ParsedIcsEvent[] = [];
  let cur: Record<string, { value: string; params: Record<string, string> }> | null = null;
  let depth = 0;
  for (const raw of lines) {
    const line = raw.trimEnd();
    if (line === 'BEGIN:VEVENT') {
      cur = {};
      depth = 0;
      continue;
    }
    if (!cur) continue;
    if (line.startsWith('BEGIN:')) {
      depth++; // VALARM… : propriétés ignorées
      continue;
    }
    if (line.startsWith('END:') && line !== 'END:VEVENT') {
      depth = Math.max(0, depth - 1);
      continue;
    }
    if (line === 'END:VEVENT') {
      const get = (k: string) => cur?.[k];
      const start = get('DTSTART') ? parseIcsDate(get('DTSTART')!.value, get('DTSTART')!.params) : null;
      const uid = get('UID')?.value.trim();
      const title = get('SUMMARY') ? unescapeText(get('SUMMARY')!.value).trim() : '';
      if (start && uid && title) {
        const end = get('DTEND') ? parseIcsDate(get('DTEND')!.value, get('DTEND')!.params) : null;
        const geo = get('GEO')?.value.split(/[;,]/).map(Number);
        out.push({
          uid: uid.slice(0, 255),
          title: title.slice(0, 255),
          description: get('DESCRIPTION') ? unescapeText(get('DESCRIPTION')!.value).trim() : '',
          location: get('LOCATION') ? unescapeText(get('LOCATION')!.value).trim() || null : null,
          url: get('URL') && /^https?:\/\//i.test(get('URL')!.value.trim()) ? get('URL')!.value.trim() : null,
          startsAt: start.date,
          // Fin exclusive d'une journée entière : on garde la veille à midi.
          endsAt: end ? (start.allDay ? new Date(end.date.getTime() - 86_400_000) : end.date) : null,
          allDay: start.allDay,
          cancelled: get('STATUS')?.value.trim().toUpperCase() === 'CANCELLED',
          lat: geo && geo.length === 2 && geo.every(Number.isFinite) ? geo[0] : null,
          lng: geo && geo.length === 2 && geo.every(Number.isFinite) ? geo[1] : null,
        });
      }
      cur = null;
      continue;
    }
    if (depth > 0) continue;
    const colon = line.indexOf(':');
    if (colon < 1) continue;
    const [name, ...paramParts] = line.slice(0, colon).split(';');
    const params: Record<string, string> = {};
    for (const p of paramParts) {
      const eq = p.indexOf('=');
      if (eq > 0) params[p.slice(0, eq).toUpperCase()] = p.slice(eq + 1);
    }
    const key = name.toUpperCase();
    if (!(key in cur)) cur[key] = { value: line.slice(colon + 1), params };
  }
  return out;
}
