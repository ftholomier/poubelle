import { describe, expect, it } from 'vitest';
import { fmtEuros, fmtInt, fromParisLocal, parisDate, parisParts, pluralize, relativeTime } from '@/lib/format';
import { normalizeText, slugify } from '@/lib/slug';

const clean = (s: string) => s.replace(/[  ]/g, ' ');

describe('formats français', () => {
  it('formate les montants en euros sans décimales par défaut', () => {
    expect(clean(fmtEuros(940000))).toBe('9 400 €');
    expect(clean(fmtEuros(2450, { decimals: true }))).toBe('24,50 €');
    expect(fmtEuros(null)).toBe('—');
  });

  it('sépare les milliers', () => {
    expect(clean(fmtInt(8542))).toBe('8 542');
    expect(clean(pluralize(2, 'fiche', 'fiches'))).toBe('2 fiches');
    expect(clean(pluralize(1, 'fiche', 'fiches'))).toBe('1 fiche');
  });

  it('convertit l’heure de Paris en tenant compte de l’heure d’été', () => {
    expect(fromParisLocal('2026-07-14', '10:00').toISOString()).toBe('2026-07-14T08:00:00.000Z');
    expect(fromParisLocal('2026-01-15', '10:00').toISOString()).toBe('2026-01-15T09:00:00.000Z');
    expect(parisDate(new Date('2026-03-28T23:30:00Z'))).toBe('2026-03-29');
    expect(parisParts(new Date('2026-10-25T00:30:00Z')).hour).toBe(2);
  });

  it('exprime les durées relatives', () => {
    const now = new Date('2026-09-25T12:00:00Z');
    expect(relativeTime(new Date('2026-09-25T11:55:00Z'), now)).toBe('il y a 5 min');
    expect(relativeTime(new Date('2026-09-22T10:00:00Z'), now)).toBe('il y a 3 j');
    expect(relativeTime(new Date('2026-09-25T15:00:00Z'), now)).toBe('dans 3 h');
  });
});

describe('identifiants d’URL', () => {
  it('produit des slugs lisibles', () => {
    expect(slugify('Céramiques Lison')).toBe('ceramiques-lison');
    expect(slugify('Cœur de Savoie & Belledonne')).toBe('coeur-de-savoie-et-belledonne');
    expect(slugify("L'Atelier d'Ornans")).toBe('l-atelier-d-ornans');
  });

  it('normalise le texte pour la recherche', () => {
    expect(normalizeText('  Où offrir LOCAL pour Noël ?  ')).toBe('ou offrir local pour noel');
  });
});
