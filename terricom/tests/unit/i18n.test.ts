import { describe, expect, it } from 'vitest';
import { MESSAGES } from '@/lib/i18n/messages';
import { isLocale, pluralForm, translator, withLang } from '@/lib/i18n';
import { dayMonthL, eventBadgeL, intL, shortDateL, timeL, WEEKDAY_SHORT_NAMES } from '@/lib/i18n/format';
import { territoryText } from '@/lib/i18n/territory';
import { listingUpToDate, manualListingTranslation, sourceHash, territoryFrenchTexts } from '@/server/services/translations';

describe('dictionnaire du portail', () => {
  it('a les mêmes clés dans les trois langues, sans valeur vide', () => {
    const fr = Object.keys(MESSAGES.fr).sort();
    expect(Object.keys(MESSAGES.en).sort()).toEqual(fr);
    expect(Object.keys(MESSAGES.de).sort()).toEqual(fr);
    for (const dict of Object.values(MESSAGES)) for (const v of Object.values(dict)) expect(String(v).trim()).not.toBe('');
  });

  it('garde les mêmes variables dans chaque traduction', () => {
    const vars = (s: string) => [...s.matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort();
    for (const [key, fr] of Object.entries(MESSAGES.fr)) {
      expect(vars(MESSAGES.en[key as keyof typeof MESSAGES.en]), `en:${key}`).toEqual(vars(fr));
      expect(vars(MESSAGES.de[key as keyof typeof MESSAGES.de]), `de:${key}`).toEqual(vars(fr));
    }
  });

  it('interpole et accorde en nombre selon la langue', () => {
    const fr = translator('fr');
    const en = translator('en');
    expect(fr('nav.explorer')).toBe('Explorer');
    expect(en('nav.explorer')).toBe('Explore');
    expect(en('explorer.more', { n: '3' })).toMatch(/\(3\)$/);
    expect(en('explorer.more', { n: '3' })).not.toBe(fr('explorer.more', { n: '3' }));
    // Le français met le singulier à 0 et 1, l'anglais seulement à 1.
    expect(pluralForm('fr', 0)).toBe('one');
    expect(pluralForm('en', 0)).toBe('other');
    expect(fr.n('circuit.stops', 1)).toBe('1 étape');
    expect(en.n('circuit.stops', 6)).toBe('6 stops');
    expect(isLocale('de')).toBe(true);
    expect(isLocale('es')).toBe(false);
  });

  it('construit les adresses par langue', () => {
    expect(withLang('https://x.fr/a', 'fr')).toBe('https://x.fr/a');
    expect(withLang('https://x.fr/a', 'en')).toBe('https://x.fr/a?lang=en');
    expect(withLang('https://x.fr/a?q=1', 'de')).toBe('https://x.fr/a?q=1&lang=de');
  });
});

describe('formats traduits', () => {
  it('garde le rendu français et adapte les autres langues', () => {
    expect(timeL('09:30', 'en')).toBe('9:30');
    expect(timeL('09:30', 'fr')).toBe('9h30');
    expect(intL(1234, 'en')).toBe('1,234');
    expect(intL(1234, 'de')).toBe('1.234');
    expect(shortDateL('2026-12-24', 'fr')).toBe('24/12');
    expect(shortDateL('2026-12-24', 'de')).toBe('24.12.');
    expect(WEEKDAY_SHORT_NAMES.en[0]).toBe('Mon');
    expect(dayMonthL('2026-12-14', 'en')).toBe('14 Dec');
    expect(eventBadgeL(new Date('2026-12-12T09:00:00Z'), new Date('2026-12-12T17:00:00Z'), 'en')).toMatch(/^SAT 12 DEC · 10:00–18:00$/);
  });
});

describe('contenus traduits', () => {
  const territory = {
    tagline: 'Commerces & savoir-faire',
    heroTitle: null,
    heroSubtitle: 'Sous-titre',
    settings: { newsletterName: 'La lettre', translations: { en: { tagline: 'Shops & know-how' } } },
  };

  it('lit la traduction d’un texte du territoire, sinon le français', () => {
    expect(territoryText(territory, 'tagline', 'en')).toBe('Shops & know-how');
    expect(territoryText(territory, 'tagline', 'de')).toBe('Commerces & savoir-faire');
    expect(territoryText(territory, 'newsletterName', 'en')).toBe('La lettre');
    expect(territoryFrenchTexts(territory)).toEqual({ tagline: 'Commerces & savoir-faire', heroSubtitle: 'Sous-titre', newsletterName: 'La lettre' });
  });

  it('refait la traduction d’une fiche seulement quand le français change', () => {
    const e = { tagline: 'Pain au levain', description: 'Depuis 1987.' };
    const hash = sourceHash(e);
    const translations = { en: { tagline: 'Sourdough', hash, source: 'ai' as const }, de: { tagline: 'Sauerteig', hash, source: 'ai' as const } };
    expect(listingUpToDate({ ...e, translations })).toBe(true);
    expect(listingUpToDate({ ...e, description: 'Depuis 1988.', translations })).toBe(false);
  });

  it('donne la priorité à la version du professionnel et permet d’y renoncer', () => {
    const e = { tagline: 'Pain au levain', description: 'Depuis 1987.', translations: {} };
    const manual = manualListingTranslation(e, 'en', { tagline: 'Our sourdough', description: '' });
    expect(manual.en).toMatchObject({ tagline: 'Our sourdough', source: 'manual' });
    expect(manual.en?.description).toBeUndefined();
    // Une traduction manuelle reste « à jour » même si le français change (le professionnel est prévenu).
    expect(listingUpToDate({ ...e, description: 'Autre texte', translations: { ...manual, de: manual.en } })).toBe(true);
    expect(manualListingTranslation({ ...e, translations: manual }, 'en', null).en).toBeUndefined();
  });
});
