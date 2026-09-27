import { describe, expect, it } from 'vitest';
import { campaignFallback, type CampaignCandidate } from '@/server/ai/features';

const cand = (id: string, family: CampaignCandidate['family'], commune: string): CampaignCandidate => ({
  id,
  name: `Adresse ${id}`,
  activity: 'Activité',
  family,
  commune,
  attributes: [],
  completeness: 80,
});
const NOW = new Date('2026-09-27T10:00:00Z');

describe('assistant de campagne sans IA', () => {
  it('accorde au singulier quand un seul établissement est retenu', () => {
    const plan = campaignFallback('Noël chez nos artisans', 'Haut-Doubs', [cand('a', 'ARTISAN', 'Jougne')], 0, NOW);
    expect(plan.tagline).toBe("1 artisan vous ouvre ses portes jusqu'au 24 décembre");
    expect(plan.plan[0].text).toBe('Invitation d’un artisan à publier une offre');
    expect(plan.newsletterIntro).toContain('une adresse');
  });
  it('garde le pluriel au-delà d’un établissement', () => {
    const plan = campaignFallback('Noël chez nos artisans', 'Haut-Doubs', [cand('a', 'ARTISAN', 'Jougne'), cand('b', 'ARTISAN', 'Métabief')], 0, NOW);
    expect(plan.tagline).toBe("2 artisans vous ouvrent leurs portes jusqu'au 24 décembre");
    expect(plan.criteriaText).toContain('2 communes représentées');
  });
});
