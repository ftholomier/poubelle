import { describe, expect, it } from 'vitest';
import { frenchHolidays, isOpenAt, openStatus, slotsForDate, type HoursSlot } from '@/lib/hours';

// Boulangerie : du mardi au samedi 6h30-19h, dimanche 7h-12h30 (0 = lundi)
const HOURS: HoursSlot[] = [
  ...[1, 2, 3, 4, 5].map((weekday) => ({ weekday, opensAt: '06:30', closesAt: '19:00' })),
  { weekday: 6, opensAt: '07:00', closesAt: '12:30' },
];

describe('horaires d’ouverture', () => {
  it('sait si un commerce est ouvert à un instant donné (heure de Paris)', () => {
    // Mardi 22 septembre 2026, 10h à Paris = 8h UTC
    expect(isOpenAt(HOURS, [], new Date('2026-09-22T08:00:00Z'))).toBe(true);
    // Lundi : fermé
    expect(isOpenAt(HOURS, [], new Date('2026-09-21T08:00:00Z'))).toBe(false);
    // Mardi 19h30 : fermé
    expect(isOpenAt(HOURS, [], new Date('2026-09-22T17:30:00Z'))).toBe(false);
  });

  it('applique les exceptions datées en priorité', () => {
    expect(slotsForDate(HOURS, [{ date: '2026-12-25', closed: true, label: 'Noël' }], '2026-12-25')).toEqual([]);
    const special = slotsForDate(HOURS, [{ date: '2026-12-24', closed: false, opensAt: '06:30', closesAt: '17:00' }], '2026-12-24');
    expect(special).toEqual([{ from: 390, to: 1020 }]);
  });

  it('annonce la prochaine ouverture quand c’est fermé', () => {
    const s = openStatus(HOURS, [], new Date('2026-09-21T08:00:00Z'));
    expect(s.open).toBe(false);
    expect(s.next?.when).toBe('demain');
    expect(s.shortLabel).toBe('Fermé');
  });

  it('connaît les jours fériés, y compris les fêtes mobiles', () => {
    const h = frenchHolidays(2026);
    expect(h.find((x) => x.label.startsWith('Lundi de Pâques'))?.date).toBe('2026-04-06');
    expect(h.find((x) => x.date === '2026-07-14')).toBeTruthy();
    expect(h).toHaveLength(11);
  });
});
