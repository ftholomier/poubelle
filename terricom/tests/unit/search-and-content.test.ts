import { describe, expect, it } from 'vitest';
import { interpretQuery, type SearchVocabulary } from '@/server/ai/rules';
import { computeCompleteness, vitrineLevel, type CompletenessInput } from '@/server/completeness';
import { renderNewsletter } from '@/server/mail/newsletter';
import { invoiceTotals } from '@/server/services/billing';

const VOCAB: SearchVocabulary = {
  categories: [
    { slug: 'boulangerie', name: 'Boulangerie', family: 'COMMERCE', synonyms: ['pain', 'viennoiseries'] },
    { slug: 'chocolaterie', name: 'Chocolaterie', family: 'COMMERCE', synonyms: ['chocolat'] },
    { slug: 'plombier', name: 'Plombier', family: 'SERVICES', synonyms: ['plomberie', 'fuite'] },
  ],
  attributes: [
    { slug: 'idees-cadeaux', label: 'Idées cadeaux' },
    { slug: 'fabrication-locale', label: 'Fabrication locale' },
    { slug: 'terrasse', label: 'Terrasse' },
  ],
  communes: [{ slug: 'ornans', name: 'Ornans' }],
};

describe('recherche en langage naturel (repli sans IA)', () => {
  it('comprend une recherche de cadeau local', () => {
    const i = interpretQuery('où offrir local pour Noël ?', VOCAB);
    expect(i.natural).toBe(true);
    expect(i.kind).toBe('gift');
    expect(i.attributeSlugs).toContain('idees-cadeaux');
  });

  it('repère une urgence de dépannage et la commune', () => {
    const i = interpretQuery('fuite chaudière à Ornans', VOCAB);
    expect(i.kind).toBe('repair');
    expect(i.families).toContain('SERVICES');
    expect(i.communeSlugs).toEqual(['ornans']);
  });

  it('gère « ce soir » et une terrasse', () => {
    const i = interpretQuery('manger en terrasse ce soir', VOCAB);
    expect(i.openTonight).toBe(true);
    expect(i.families).toContain('RESTAURATION');
    expect(i.attributeSlugs).toContain('terrasse');
  });

  it('laisse une recherche simple en plein texte', () => {
    const i = interpretQuery('boulangerie', VOCAB);
    expect(i.natural).toBe(false);
    expect(i.categorySlugs).toEqual(['boulangerie']);
    expect(i.keywords).toEqual([]);
  });
});

const EMPTY: CompletenessInput = {
  name: 'Boulangerie Martin',
  street: null,
  phone: null,
  email: null,
  website: null,
  socials: {},
  description: null,
  logoUrl: null,
  coverUrl: null,
  photos: [],
  hoursCount: 0,
  exceptionalDates: [],
  hoursConfirmedAt: null,
  productsCount: 0,
  paymentCount: 0,
  serviceCount: 0,
  lastPostAt: null,
};

describe('complétude des fiches', () => {
  it('note une fiche précréée comme faible et propose des actions', () => {
    const r = computeCompleteness(EMPTY, new Date('2026-09-25T10:00:00Z'));
    expect(r.score).toBeLessThan(30);
    expect(r.items.filter((x) => !x.ok).every((x) => x.action)).toBe(true);
  });

  it('atteint 100 pour une fiche entièrement renseignée', () => {
    const full: CompletenessInput = {
      ...EMPTY,
      street: '12 rue de la Loue',
      phone: '0381000000',
      email: 'contact@boulangerie.fr',
      website: 'https://boulangerie.fr',
      socials: { instagram: 'https://instagram.com/boulangerie' },
      description: Array.from({ length: 80 }, () => 'pain').join(' '),
      logoUrl: 'https://x/logo.png',
      coverUrl: 'https://x/cover.jpg',
      photos: [{ tag: 'Principale' }, { tag: 'Intérieur' }, { tag: 'Intérieur' }, { tag: 'Produits' }, { tag: 'Équipe' }, { tag: null }],
      hoursCount: 12,
      exceptionalDates: ['2026-11-01', '2026-12-24', '2026-12-25'],
      hoursConfirmedAt: new Date('2026-09-20T10:00:00Z'),
      productsCount: 6,
      paymentCount: 3,
      serviceCount: 3,
      lastPostAt: new Date('2026-09-24T10:00:00Z'),
    };
    const r = computeCompleteness(full, new Date('2026-09-25T10:00:00Z'));
    expect(r.score).toBe(100);
    expect(vitrineLevel(r.score).name).toBe('★ Vitrine Or');
  });
});

describe('lettre d’information', () => {
  it('échappe les contenus et inclut désinscription et mesure', () => {
    const { html, text } = renderNewsletter({
      brandName: 'Val de Loue',
      number: 48,
      color: '#1F6B52',
      accent: '#F4B266',
      title: 'Noël <script>alert(1)</script>',
      intro: 'Les commerçants vous attendent.',
      heroImageUrl: null,
      blocks: [{ type: 'cta', label: 'Voir le calendrier', url: 'https://valdeloue.terricom.fr/campagnes/noel' }],
      newsletterName: 'La lettre du Val de Loue',
      unsubscribeUrl: 'https://valdeloue.terricom.fr/newsletter/desinscription?token=abc',
      preferencesUrl: 'https://valdeloue.terricom.fr/newsletter/desinscription?token=abc',
      link: (u) => `https://terricom.fr/api/n/c/tok?u=${encodeURIComponent(u)}`,
      openPixelUrl: 'https://terricom.fr/api/n/o/tok',
    });
    expect(html).not.toContain('<script>');
    expect(html).toContain('&lt;script&gt;');
    expect(html).toContain('Se désinscrire');
    expect(html).toContain('/api/n/o/tok');
    expect(html).toContain('/api/n/c/tok');
    expect(text).toContain('Se désinscrire : https://valdeloue.terricom.fr/newsletter/desinscription?token=abc');
  });
});

describe('facturation', () => {
  it('calcule HT, TVA et TTC ligne à ligne', () => {
    expect(
      invoiceTotals([
        { label: 'Licence annuelle', quantity: 1, unitCents: 900000, vatRate: 20 },
        { label: 'Formation', quantity: 2, unitCents: 45000, vatRate: 20 },
      ]),
    ).toEqual({ totalHtCents: 990000, vatCents: 198000, totalTtcCents: 1188000 });
  });
});

describe('jours fériés et complétude', () => {
  it('réclame les horaires du prochain jour férié (dans les 45 jours)', () => {
    const base = computeCompleteness({ ...EMPTY, hoursCount: 10, exceptionalDates: [] }, new Date('2026-09-25T10:00:00Z'));
    const holidays = base.items.find((i) => i.key === 'holidays');
    expect(holidays?.ok).toBe(false);
    expect(holidays?.action).toContain('Toussaint');
  });
});
