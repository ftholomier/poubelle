import { upcomingHoliday } from '@/lib/hours';
import { wordCount } from '@/lib/format';

/**
 * Score de complétude d'une fiche (0-100) et recommandations concrètes.
 * Le calcul est déterministe : il sert au badge « Vitrine Argent / Or », à l'audit
 * de la fiche et au tableau de bord de la collectivité.
 */
export type CompletenessInput = {
  name: string;
  street: string | null;
  phone: string | null;
  email: string | null;
  website: string | null;
  socials: Record<string, string | undefined>;
  description: string | null;
  logoUrl: string | null;
  coverUrl: string | null;
  photos: { tag: string | null }[];
  hoursCount: number;
  exceptionalDates: string[];
  hoursConfirmedAt: Date | null;
  productsCount: number;
  paymentCount: number;
  serviceCount: number;
  lastPostAt: Date | null;
};

export type CompletenessItem = {
  key: string;
  ok: boolean;
  points: number;
  earned: number;
  /** Libellé d'audit (« Coordonnées et catégorie vérifiées ») */
  audit: string;
  /** Recommandation actionnable si incomplet (« Ajoutez 3 photos de l'intérieur ») */
  action: string | null;
  /** Rubrique de l'éditeur où agir */
  section: 'identite' | 'description' | 'photos' | 'horaires' | 'services' | 'produits' | 'contact' | 'publications';
};

export function computeCompleteness(e: CompletenessInput, now: Date = new Date()): { score: number; items: CompletenessItem[] } {
  const items: CompletenessItem[] = [];
  const push = (i: Omit<CompletenessItem, 'earned'> & { earned?: number }) =>
    items.push({ ...i, earned: i.earned ?? (i.ok ? i.points : 0) });

  const identityOk = Boolean(e.name && e.street && e.phone);
  push({
    key: 'identity',
    points: 14,
    ok: identityOk,
    audit: identityOk ? 'Coordonnées et catégorie vérifiées' : 'Adresse ou téléphone manquant',
    action: identityOk ? null : 'Complétez votre adresse et votre téléphone',
    section: 'identite',
  });

  const words = wordCount(e.description);
  push({
    key: 'description',
    points: 14,
    ok: words >= 60,
    earned: words >= 60 ? 14 : words >= 10 ? 7 : 0,
    audit:
      words >= 60
        ? 'Description riche et optimisée'
        : words === 0
          ? 'Aucune description : c’est le premier texte lu par Google'
          : `Description trop courte (${words} mots) : visez 60+`,
    action: words >= 60 ? null : 'Enrichissez votre description (60 mots et plus)',
    section: 'description',
  });

  const photos = e.photos.length;
  push({
    key: 'photos',
    points: 12,
    ok: photos >= 5,
    earned: photos >= 5 ? 12 : photos >= 3 ? 8 : photos >= 1 ? 4 : 0,
    audit: photos >= 5 ? `${photos} photos publiées` : `Seulement ${photos} photo${photos > 1 ? 's' : ''} : visez 5 minimum`,
    action: photos >= 5 ? null : `Ajoutez ${5 - photos} photo${5 - photos > 1 ? 's' : ''} de votre activité`,
    section: 'photos',
  });

  const interior = e.photos.some((p) => /int[ée]rieur|boutique|atelier|salle/i.test(p.tag ?? ''));
  push({
    key: 'interior',
    points: 8,
    ok: interior,
    audit: interior ? "Photos de l'intérieur présentes" : "Aucune photo de l'intérieur de la boutique",
    action: interior ? null : "Ajoutez 3 photos de l'intérieur",
    section: 'photos',
  });

  push({
    key: 'hours',
    points: 12,
    ok: e.hoursCount > 0,
    audit: e.hoursCount > 0 ? 'Horaires hebdomadaires renseignés' : 'Horaires non renseignés',
    action: e.hoursCount > 0 ? null : 'Renseignez vos horaires d’ouverture',
    section: 'horaires',
  });

  const holiday = upcomingHoliday(now, 45);
  const holidayOk = !holiday || e.exceptionalDates.includes(holiday.date);
  const holidayName = holiday?.label === 'Noël' ? 'de Noël' : holiday ? `du ${holiday.label.toLowerCase()}` : '';
  push({
    key: 'holidays',
    points: 6,
    ok: holidayOk,
    audit: holidayOk ? 'Horaires des jours fériés à jour' : `Horaires ${holidayName} non renseignés`,
    action: holidayOk ? null : `Confirmez vos horaires ${holidayName}`,
    section: 'horaires',
  });

  push({
    key: 'products',
    points: 10,
    ok: e.productsCount >= 3,
    earned: e.productsCount >= 3 ? 10 : e.productsCount > 0 ? 5 : 0,
    audit: e.productsCount >= 3 ? 'Produits et services présentés' : 'Produits phares non décrits',
    action: e.productsCount >= 3 ? null : 'Décrivez vos produits phares',
    section: 'produits',
  });

  push({
    key: 'payment',
    points: 4,
    ok: e.paymentCount > 0,
    audit: e.paymentCount > 0 ? 'Moyens de paiement indiqués' : 'Moyens de paiement non indiqués',
    action: e.paymentCount > 0 ? null : 'Ajoutez vos moyens de paiement',
    section: 'services',
  });

  push({
    key: 'services',
    points: 6,
    ok: e.serviceCount >= 2,
    earned: e.serviceCount >= 2 ? 6 : e.serviceCount === 1 ? 3 : 0,
    audit: e.serviceCount >= 2 ? 'Services et labels renseignés' : 'Services (click & collect, accessibilité…) à préciser',
    action: e.serviceCount >= 2 ? null : 'Précisez vos services et labels',
    section: 'services',
  });

  const hasWeb = Boolean(e.website || e.email || Object.values(e.socials ?? {}).some(Boolean));
  push({
    key: 'web',
    points: 4,
    ok: hasWeb,
    audit: hasWeb ? 'Site ou réseaux sociaux liés' : 'Aucun lien vers vos réseaux sociaux',
    action: hasWeb ? null : 'Ajoutez votre site ou vos réseaux sociaux',
    section: 'contact',
  });

  const days = e.lastPostAt ? Math.floor((now.getTime() - e.lastPostAt.getTime()) / 86_400_000) : null;
  const recent = days !== null && days <= 30;
  push({
    key: 'activity',
    points: 6,
    ok: recent,
    audit: recent
      ? `Dernière actualité il y a ${days === 0 ? "moins d'un jour" : `${days} jour${days && days > 1 ? 's' : ''}`}`
      : days === null
        ? 'Aucune actualité publiée'
        : `Dernière actualité il y a ${days} jours`,
    action: recent ? null : 'Publiez une actualité ce mois-ci',
    section: 'publications',
  });

  const visual = Boolean(e.logoUrl || e.coverUrl || e.photos.length);
  push({
    key: 'visual',
    points: 4,
    ok: visual,
    audit: visual ? 'Visuel principal défini' : 'Aucun visuel principal',
    action: visual ? null : 'Choisissez une photo principale',
    section: 'photos',
  });

  const score = Math.min(100, items.reduce((s, i) => s + i.earned, 0));
  return { score, items };
}

export function vitrineLevel(score: number): { name: string; bg: string; hint: string } {
  if (score >= 100)
    return { name: '★ Vitrine Or', bg: '#F4B266', hint: 'Fiche parfaite : vous êtes mis en avant sur la page de votre commune.' };
  if (score >= 85)
    return { name: '★ Vitrine Argent+', bg: '#E5E1D6', hint: 'Encore une action pour décrocher la Vitrine Or.' };
  return {
    name: '★ Vitrine Argent',
    bg: '#E5E1D6',
    hint: 'Complétez les actions suggérées pour passer Vitrine Or et être mis en avant sur la page de votre commune.',
  };
}
