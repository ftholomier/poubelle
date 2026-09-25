import { describe, expect, it } from 'vitest';
import { calendarIcs, parseIcs } from '@/lib/ics';
import { rssFeed } from '@/lib/rss';
import { selectUpcoming } from '@/server/services/calendar-sync';
import { signConnectorPayload } from '@/server/services/connectors';
import { assertPublicUrl, isPrivateIp, OutboundError } from '@/server/net';

const SAMPLE = [
  'BEGIN:VCALENDAR',
  'VERSION:2.0',
  'PRODID:-//Office de tourisme//FR',
  'BEGIN:VEVENT',
  'UID:marche-noel@ot.example',
  'DTSTART;TZID=Europe/Paris:20261212T100000',
  'DTEND;TZID=Europe/Paris:20261212T180000',
  'SUMMARY:Marché de Noël\\, place Courbet',
  'DESCRIPTION:Artisans\\, producteurs et vin chaud.\\nEntrée libre',
  'LOCATION:Place Courbet\\, Ornans',
  'GEO:47.106;6.144',
  'BEGIN:VALARM',
  'ACTION:DISPLAY',
  'DESCRIPTION:Rappel',
  'END:VALARM',
  'END:VEVENT',
  'BEGIN:VEVENT',
  'UID:fete@ot.example',
  'DTSTART;VALUE=DATE:20260714',
  'DTEND;VALUE=DATE:20260715',
  'SUMMARY:Fête nationale avec un titre assez long pour être replié sur plusieurs lignes du fic',
  ' hier iCalendar',
  'URL:https://ot.example/fete',
  'END:VEVENT',
  'BEGIN:VEVENT',
  'UID:annule@ot.example',
  'DTSTART:20261001T160000Z',
  'SUMMARY:Atelier annulé',
  'STATUS:CANCELLED',
  'END:VEVENT',
  'BEGIN:VEVENT',
  'SUMMARY:Sans identifiant (ignoré)',
  'DTSTART:20261001T160000Z',
  'END:VEVENT',
  'END:VCALENDAR',
].join('\r\n');

describe('lecture d’un agenda iCal externe', () => {
  const events = parseIcs(SAMPLE);

  it('lit les événements valides, texte déséchappé, alarmes ignorées', () => {
    expect(events.map((e) => e.uid)).toEqual(['marche-noel@ot.example', 'fete@ot.example', 'annule@ot.example']);
    const m = events[0];
    expect(m.title).toBe('Marché de Noël, place Courbet');
    expect(m.description).toBe('Artisans, producteurs et vin chaud.\nEntrée libre');
    expect(m.location).toBe('Place Courbet, Ornans');
    expect(m.lat).toBeCloseTo(47.106);
  });

  it('convertit l’heure locale de Paris (heure d’hiver) en UTC', () => {
    expect(events[0].startsAt.toISOString()).toBe('2026-12-12T09:00:00.000Z');
    expect(events[0].endsAt?.toISOString()).toBe('2026-12-12T17:00:00.000Z');
  });

  it('gère les journées entières, les lignes repliées et les annulations', () => {
    const fete = events[1];
    expect(fete.allDay).toBe(true);
    expect(fete.title.endsWith('du fichier iCalendar')).toBe(true);
    expect(fete.url).toBe('https://ot.example/fete');
    expect(events[2].cancelled).toBe(true);
  });

  it('ne garde que les événements à venir, dans l’horizon d’un an', () => {
    const now = new Date('2026-09-25T12:00:00Z');
    const kept = selectUpcoming(events, now);
    expect(kept.map((e) => e.uid)).toEqual(['annule@ot.example', 'marche-noel@ot.example']);
  });
});

describe('agenda iCal publié', () => {
  it('produit un agenda relisible (aller-retour) avec caractères échappés', () => {
    const ics = calendarIcs('Agenda · Val de Loue', [
      {
        uid: 'a@terricom.fr',
        title: 'Dégustation ; vins, fromages',
        description: 'Ligne 1\nLigne 2',
        startsAt: new Date('2026-10-02T15:00:00Z'),
        endsAt: new Date('2026-10-02T17:00:00Z'),
      },
      { uid: 'b@terricom.fr', title: 'Foire', startsAt: new Date('2026-10-10T10:00:00Z'), allDay: true },
    ]);
    expect(ics).toContain('X-WR-CALNAME:Agenda · Val de Loue');
    expect(ics).toContain('SUMMARY:Dégustation \\; vins\\, fromages');
    expect(ics.split('\r\n').every((l) => Buffer.byteLength(l, 'utf8') <= 75)).toBe(true);
    const back = parseIcs(ics);
    expect(back).toHaveLength(2);
    expect(back[0].title).toBe('Dégustation ; vins, fromages');
    expect(back[0].description).toBe('Ligne 1\nLigne 2');
    expect(back[0].startsAt.toISOString()).toBe('2026-10-02T15:00:00.000Z');
    expect(back[1].allDay).toBe(true);
  });
});

describe('appels sortants (protection SSRF)', () => {
  it('reconnaît les adresses internes', () => {
    for (const ip of [
      '127.0.0.1',
      '10.2.3.4',
      '172.20.1.1',
      '192.168.1.10',
      '169.254.169.254',
      '100.64.0.1',
      '0.0.0.0',
      '::1',
      'fd00::1',
      'fe80::1',
      '::ffff:10.0.0.1',
    ])
      expect(isPrivateIp(ip), ip).toBe(true);
    for (const ip of ['8.8.8.8', '51.15.2.3', '172.32.0.1', '2a01:4f8::1']) expect(isPrivateIp(ip), ip).toBe(false);
  });

  it('refuse les protocoles non sûrs et les identifiants dans l’adresse', async () => {
    await expect(assertPublicUrl('http://exemple.fr/hook')).rejects.toBeInstanceOf(OutboundError);
    await expect(assertPublicUrl('https://user:pass@exemple.fr/hook')).rejects.toBeInstanceOf(OutboundError);
    await expect(assertPublicUrl('ftp://exemple.fr/agenda.ics', { allowHttp: true })).rejects.toBeInstanceOf(OutboundError);
    await expect(assertPublicUrl('https://127.0.0.1/hook')).rejects.toThrow('Adresse interne refusée.');
    await expect(assertPublicUrl('https://metadata.google.internal/')).rejects.toThrow('Adresse interne refusée.');
  });
});

describe('connecteur des entreprises', () => {
  it('signe « horodatage.corps » avec le secret (HMAC-SHA256)', () => {
    const sig = signConnectorPayload('whsec_test', 1790000000, '{"type":"test"}');
    expect(sig).toMatch(/^sha256=[0-9a-f]{64}$/);
    expect(signConnectorPayload('whsec_test', 1790000000, '{"type":"test"}')).toBe(sig);
    expect(signConnectorPayload('whsec_autre', 1790000000, '{"type":"test"}')).not.toBe(sig);
    expect(signConnectorPayload('whsec_test', 1790000001, '{"type":"test"}')).not.toBe(sig);
  });
});

describe('flux RSS', () => {
  it('échappe les contenus et annonce son adresse', () => {
    const xml = rssFeed({ title: 'Actualités · Val & Loue', link: 'https://x.fr/a', description: 'd', selfUrl: 'https://x.fr/a.xml' }, [
      { guid: '1', title: 'Promo <-15 %> & cadeaux', link: 'https://x.fr/f', publishedAt: new Date('2026-09-20T08:00:00Z'), description: '"Offre"' },
    ]);
    expect(xml).toContain('<title>Actualités · Val &amp; Loue</title>');
    expect(xml).toContain('<title>Promo &lt;-15 %&gt; &amp; cadeaux</title>');
    expect(xml).toContain('<atom:link href="https://x.fr/a.xml" rel="self" type="application/rss+xml"/>');
    expect(xml).toContain('<pubDate>Sun, 20 Sep 2026 08:00:00 GMT</pubDate>');
  });
});
