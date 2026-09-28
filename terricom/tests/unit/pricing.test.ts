import { describe, expect, it } from 'vitest';
import { DIRECT_MONTHLY_CENTS, monthlyEquivalentCents, subscriptionName, subscriptionPriceCents } from '@/lib/pricing';

describe('prix des abonnements des entreprises', () => {
  it('adhésion directe : 29 € et 49 € HT par mois, quel que soit le prix des options', () => {
    expect(subscriptionPriceCents({ plan: 'PREMIUM', direct: true, interval: 'MONTH', planMonthlyCents: 2400 })).toBe(2900);
    expect(subscriptionPriceCents({ plan: 'COMMUNICATION', direct: true, interval: 'MONTH', planMonthlyCents: 4900 })).toBe(4900);
    expect(DIRECT_MONTHLY_CENTS.PREMIUM).toBeGreaterThan(2400);
  });

  it('territoire partenaire : prix de l’option (table des offres)', () => {
    expect(subscriptionPriceCents({ plan: 'PREMIUM', direct: false, interval: 'MONTH', planMonthlyCents: 2400 })).toBe(2400);
    expect(subscriptionPriceCents({ plan: 'ESSENTIEL', direct: true, interval: 'MONTH', planMonthlyCents: 0 })).toBe(0);
  });

  it('annuel : dix mois facturés pour douze', () => {
    expect(subscriptionPriceCents({ plan: 'PREMIUM', direct: true, interval: 'YEAR', planMonthlyCents: 2400 })).toBe(29000);
    expect(subscriptionPriceCents({ plan: 'PREMIUM', direct: false, interval: 'YEAR', planMonthlyCents: 2400 })).toBe(24000);
    expect(monthlyEquivalentCents({ plan: 'COMMUNICATION', direct: true, interval: 'YEAR', planMonthlyCents: 4900 })).toBe(4083);
  });

  it('bascule vers l’option équivalente quand la collectivité adhère', () => {
    const avant = subscriptionPriceCents({ plan: 'PREMIUM', direct: true, interval: 'MONTH', planMonthlyCents: 2400 });
    const apres = subscriptionPriceCents({ plan: 'PREMIUM', direct: false, interval: 'MONTH', planMonthlyCents: 2400 });
    expect(avant - apres).toBe(500);
  });

  it('noms affichés', () => {
    expect(subscriptionName('PREMIUM', true, 'Premium')).toBe('Adhésion');
    expect(subscriptionName('COMMUNICATION', true, 'Communication')).toBe('Adhésion Communication');
    expect(subscriptionName('PREMIUM', false, 'Premium')).toBe('Premium');
  });
});
