import type { PlanKey } from './constants';

/**
 * Prix des abonnements des entreprises.
 *
 * - Entreprise d'un territoire partenaire : la fiche (Essentiel) est offerte par la collectivité ; les options
 *   Premium et Communication sont au prix de la table `plans` (modifiable dans la console).
 * - Adhésion directe (aucune collectivité partenaire) : l'entreprise paie aussi sa fiche. Adhésion à 29 € HT
 *   par mois (fiche + Premium) ou Adhésion Communication à 49 € HT par mois ; pas d'offre gratuite.
 * - Paiement annuel : dix mois facturés pour douze (deux mois offerts).
 *
 * Quand la commune ou la communauté de communes de l'entreprise adhère, l'abonnement bascule du prix direct
 * vers le prix de l'option équivalente (voir `services/direct.ts`).
 */

export type BillingInterval = 'MONTH' | 'YEAR';

/** Prix mensuels HT de l'adhésion directe, en centimes. */
export const DIRECT_MONTHLY_CENTS: Record<Exclude<PlanKey, 'ESSENTIEL'>, number> = {
  PREMIUM: 2900,
  COMMUNICATION: 4900,
};

export const DIRECT_PLAN_NAMES: Record<Exclude<PlanKey, 'ESSENTIEL'>, string> = {
  PREMIUM: 'Adhésion',
  COMMUNICATION: 'Adhésion Communication',
};

/** Mois facturés pour un abonnement annuel. */
export const MONTHS_BILLED_PER_YEAR = 10;

/**
 * Prix HT d'une période d'abonnement, en centimes.
 * `planMonthlyCents` : prix mensuel de l'option dans la table `plans` (utilisé hors adhésion directe).
 */
export function subscriptionPriceCents(p: { plan: PlanKey; direct: boolean; interval: BillingInterval; planMonthlyCents: number }): number {
  if (p.plan === 'ESSENTIEL') return 0;
  const monthly = p.direct ? DIRECT_MONTHLY_CENTS[p.plan] : p.planMonthlyCents;
  return p.interval === 'YEAR' ? monthly * MONTHS_BILLED_PER_YEAR : monthly;
}

/** Équivalent mensuel (revenu récurrent), en centimes. */
export function monthlyEquivalentCents(p: { plan: PlanKey; direct: boolean; interval: BillingInterval; planMonthlyCents: number }): number {
  const price = subscriptionPriceCents(p);
  return p.interval === 'YEAR' ? Math.round(price / 12) : price;
}

/** Nom affiché de l'abonnement (« Adhésion », « Premium »…). */
export function subscriptionName(plan: PlanKey, direct: boolean, planName: string): string {
  return direct && plan !== 'ESSENTIEL' ? DIRECT_PLAN_NAMES[plan] : planName;
}

/** Libellé de période pour les factures et l'interface. */
export function intervalLabel(interval: BillingInterval): string {
  return interval === 'YEAR' ? '1 an' : '1 mois';
}
