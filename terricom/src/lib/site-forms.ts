import { z } from 'zod';

/**
 * Formulaires du site commercial statique (site-terricom/, terricom.fr) : validation et conversion vers les
 * demandes enregistrées par la plateforme. Les noms de champs sont ceux des pages HTML du site.
 */

const consent = z.union([z.literal('on'), z.literal(true), z.literal('true')], { error: 'Merci d’accepter d’être recontacté·e.' });
const honeypot = z.string().max(0).optional();
const optionalText = (max: number) =>
  z
    .string()
    .trim()
    .max(max)
    .optional()
    .transform((v) => v || undefined);

export const SITE_KINDS = { CC: 'Communauté de communes', CA: 'Communauté d’agglomération', COMMUNE: 'Commune', AUTRE: 'Autre' } as const;

export const siteDemoSchema = z.object({
  collectivite: z.string({ error: 'Indiquez votre collectivité.' }).trim().min(2, 'Indiquez votre collectivité.').max(200),
  type: z.enum(['CC', 'CA', 'COMMUNE', 'AUTRE']).default('CC'),
  communes: z.coerce
    .number()
    .int()
    .min(1)
    .max(500)
    .optional()
    .or(z.literal('').transform(() => undefined)),
  nom: z.string({ error: 'Indiquez votre nom.' }).trim().min(2, 'Indiquez votre nom.').max(160),
  fonction: optionalText(160),
  email: z.string({ error: 'Indiquez votre adresse électronique.' }).trim().toLowerCase().email('Adresse électronique invalide.'),
  tel: optionalText(32),
  format: optionalText(120),
  message: optionalText(3000),
  consentement: consent,
  website: honeypot,
});

export const SITE_CONTACT_SUBJECTS = [
  'Une question sur terricom',
  'Une démonstration pour notre territoire',
  'Des documents pour notre conseil',
  'Un devis',
  'Je suis une entreprise',
  'Autre',
] as const;

export const siteContactSchema = z.object({
  nom: z.string({ error: 'Indiquez votre nom.' }).trim().min(2, 'Indiquez votre nom.').max(160),
  email: z.string({ error: 'Indiquez votre adresse électronique.' }).trim().toLowerCase().email('Adresse électronique invalide.'),
  collectivite: optionalText(200),
  objet: z.enum(SITE_CONTACT_SUBJECTS).catch('Autre'),
  message: z.string({ error: 'Écrivez votre message.' }).trim().min(5, 'Écrivez votre message.').max(5000),
  consentement: consent,
  website: honeypot,
});

export type SiteDemo = z.infer<typeof siteDemoSchema>;
export type SiteContact = z.infer<typeof siteContactSchema>;

/** « Marie-Hélène Dupont » → prénom « Marie-Hélène », nom « Dupont » ; un seul mot : c'est le nom. */
export function splitName(full: string): { firstName: string; lastName: string } {
  const parts = full.trim().split(/\s+/).filter(Boolean);
  if (parts.length < 2) return { firstName: '', lastName: parts[0] ?? '' };
  return { firstName: parts[0], lastName: parts.slice(1).join(' ') };
}

/** Demande de démonstration du site → demande enregistrée (affaire dans le suivi commercial). */
export function demoFromSite(d: SiteDemo) {
  const { firstName, lastName } = splitName(d.nom);
  const notes = [d.message, d.format ? `Format souhaité : ${d.format}.` : ''].filter(Boolean).join('\n');
  return {
    firstName,
    lastName,
    role: d.fonction,
    organization: d.collectivite,
    kind: d.type,
    communes: d.communes,
    email: d.email,
    phone: d.tel,
    message: notes || undefined,
  };
}

/** Un message de contact devient une affaire lorsqu'il vient d'une collectivité (et non d'une entreprise). */
export function contactIsLead(c: SiteContact): boolean {
  return Boolean(c.collectivite) && c.objet !== 'Je suis une entreprise';
}

/** Premier message d'erreur de validation, lisible par l'internaute. */
export function firstIssue(error: z.ZodError): string {
  return error.issues[0]?.message ?? 'Formulaire incomplet.';
}
