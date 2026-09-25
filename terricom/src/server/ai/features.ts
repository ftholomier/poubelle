import * as z from 'zod/v4';
import { FAMILIES, type Family, type PostKind, POST_KINDS } from '@/lib/constants';
import { slugify } from '@/lib/slug';
import { aiEnabled, recordUsage, structuredCall, type AiContext } from './client';
import { composeAnswer, interpretQuery, type AnswerResult, type SearchIntent, type SearchVocabulary } from './rules';

// ─── 1. Assistant rédactionnel multicanal ───────────────────────────────────

export type Tone = 'Chaleureux' | 'Pro' | 'Fun';

export type WriterInput = {
  draft: string;
  kind: PostKind;
  tone: Tone;
  establishment: {
    name: string;
    activity: string;
    family: Family;
    commune: string;
    address: string | null;
    territoryName: string;
    departmentCode: string | null;
    openingSummary: string | null;
    signature: string | null;
  };
};

export type WriterOutput = {
  fiche: string;
  facebook: string;
  instagram: string;
  linkedin: string;
  emailSubject: string;
  emailBody: string;
  seoTitle: string;
  source: 'ai' | 'rules';
};

const writerSchema = z.object({
  fiche: z.string().describe('Texte pour la fiche et le site, 2 à 4 phrases, informatif'),
  facebook: z.string().describe('Publication Facebook, chaleureuse, avec 1 emoji maximum et un appel à venir'),
  instagram: z.string().describe('Légende Instagram courte suivie de 4 à 6 hashtags locaux'),
  linkedin: z.string().describe('Publication LinkedIn sobre, angle entreprise locale et territoire'),
  emailSubject: z.string().describe("Objet d'email clients, 60 caractères maximum"),
  emailBody: z.string().describe("Corps d'email clients, 4 à 6 lignes, signé"),
  seoTitle: z.string().describe('Titre SEO, 60 caractères maximum, avec la commune'),
});

const WRITER_SYSTEM = `Tu es l'assistant rédactionnel de terricom, la plateforme qui met en vitrine les commerces, artisans et producteurs d'un territoire français.
Tu rédiges en français, pour de très petites entreprises locales, des publications prêtes à diffuser sur plusieurs canaux.
Ton de la marque : sérieux sur le fond, souriant sur la forme. Concret : on parle de produits, de gestes, d'horaires, de lieux réels.
Règles impératives :
- N'invente aucun fait (prix, dates, labels, ancienneté, promotions) qui ne figure pas dans la demande.
- Pas de superlatifs creux (« une offre riche et diversifiée »), pas de jargon marketing.
- Respecte le ton demandé : Chaleureux (proche, bienveillant), Pro (sobre, factuel), Fun (enjoué, rythmé).
- Mentionne la commune ; l'adresse seulement si elle est fournie.
- Les hashtags Instagram sont sans accents et en CamelCase (#ValDeLoue, #FaitIci).`;

function emojiFor(family: Family, activity: string): string {
  const a = activity.toLowerCase();
  if (/boulang|pain/.test(a)) return '🥖';
  if (/fromag/.test(a)) return '🧀';
  if (/chocolat/.test(a)) return '🍫';
  if (/vin|cave/.test(a)) return '🍷';
  if (/fleur/.test(a)) return '💐';
  return { COMMERCE: '🛍️', ARTISAN: '🛠️', PRODUCTEUR: '🌿', RESTAURATION: '🍽️', SERVICES: '🔧' }[family];
}

export function writerFallback(input: WriterInput): WriterOutput {
  const e = input.establishment;
  const food = ['RESTAURATION', 'PRODUCTEUR'].includes(e.family) || /boulang|chocolat|fromag|pâtiss|traiteur|épicerie|vin/i.test(e.activity);
  const T: Record<Tone, [string, string, string]> = {
    Chaleureux: ['Bonne nouvelle !', 'On vous attend avec le sourire', emojiFor(e.family, e.activity)],
    Pro: ['À découvrir dès maintenant :', e.openingSummary ? `Retrouvez-nous ${e.openingSummary}` : 'Retrouvez-nous aux horaires habituels', ''],
    Fun: [food ? 'Alerte gourmandise !' : 'Alerte nouveauté !', food ? "Venez vite avant qu'on mange tout" : 'Venez vite, on a hâte de vous montrer ça', '✨'],
  };
  const [hook, cta, emoji] = T[input.tone];
  const d = (input.draft.trim() || `${POST_KINDS[input.kind].label} chez ${e.name}`).replace(/[.!]+$/, '');
  const lower = d.charAt(0).toLowerCase() + d.slice(1);
  const where = e.address ? `au ${e.address.replace(/,?\s*\d{5}.*$/, '')} à ${e.commune}` : `à ${e.commune}`;
  const tag = (s: string) => `#${slugify(s).split('-').map((w) => w.charAt(0).toUpperCase() + w.slice(1)).join('')}`;
  return {
    fiche: `${hook} ${d}. Passez nous voir ${where}. ${cta}.`,
    facebook: `${hook} ${d} ${emoji}\n${cta} à ${e.commune}. Partagez à vos amis${food ? ' gourmands' : ''} !`.replace(/ \n/, '\n'),
    instagram: `${d} ${emoji}\n.\n${tag(e.commune)} ${tag(e.territoryName)} ${tag(e.activity)} #FaitIci #CommerceLocal`.replace(/ \n/, '\n'),
    linkedin: `Chez ${e.name}, nous continuons d'avancer : ${lower}. Fiers de faire vivre l'économie locale ${e.territoryName.startsWith('Val') ? 'du' : 'de'} ${e.territoryName}.`,
    emailSubject: d.length > 60 ? `${d.slice(0, 57)}…` : d,
    emailBody: `Bonjour,\n\n${hook} ${lower}. ${cta}.\n\n${e.signature ?? `L'équipe ${e.name}`}`,
    seoTitle: `${d} – ${e.name}, ${e.commune}${e.departmentCode ? ` (${e.departmentCode})` : ''}`.slice(0, 70),
    source: 'rules',
  };
}

export async function writeMultichannel(input: WriterInput, ctx: AiContext): Promise<WriterOutput> {
  const e = input.establishment;
  const ai = await structuredCall({
    feature: 'WRITER',
    system: WRITER_SYSTEM,
    schema: writerSchema,
    effort: 'medium',
    maxTokens: 4000,
    ctx,
    user: `Type de publication : ${POST_KINDS[input.kind].label}
Ton : ${input.tone}
Message du professionnel : « ${input.draft} »

Établissement : ${e.name} — ${e.activity} (${FAMILIES[e.family].label})
Commune : ${e.commune}${e.address ? ` — adresse : ${e.address}` : ''}
Territoire : ${e.territoryName}
${e.openingSummary ? `Horaires : ${e.openingSummary}` : ''}
Signature : ${e.signature ?? `L'équipe ${e.name}`}`,
  });
  if (ai) return { ...ai, source: 'ai' };
  await recordUsage('WRITER', ctx, { model: 'regles', inputTokens: 0, outputTokens: 0, fallback: true });
  return writerFallback(input);
}

// ─── 2. Amélioration de la description d'une fiche ──────────────────────────

export type ImproveInput = {
  current: string;
  name: string;
  activity: string;
  commune: string;
  territoryName: string;
  products: string[];
  services: string[];
  payments: string[];
};

const improveSchema = z.object({
  description: z.string().describe('Description de 70 à 120 mots, 2 ou 3 paragraphes courts'),
  seoTitle: z.string().describe('Titre SEO de 60 caractères maximum'),
});

export function improveFallback(i: ImproveInput): { description: string; seoTitle: string; source: 'rules' } {
  const base = i.current.trim().replace(/\s+/g, ' ');
  const intro = base.length > 20 ? base.replace(/\.?$/, '.') : `${i.name} vous accueille à ${i.commune}.`;
  // Minuscule initiale seulement : les sigles (AOP, PMR…) et noms propres restent intacts.
  const lc = (s: string) => (/^[A-ZÀ-Ý][a-zà-ÿ]/.test(s) ? s.charAt(0).toLowerCase() + s.slice(1) : s);
  const products = i.products.length ? ` Parmi nos incontournables : ${i.products.slice(0, 4).map(lc).join(', ')}.` : '';
  const services = i.services.length ? ` Sur place : ${i.services.slice(0, 4).map(lc).join(', ')}.` : '';
  const payments = i.payments.length ? ` Paiement accepté : ${i.payments.map(lc).join(', ')}.` : '';
  return {
    description: `${intro}${products}${services}${payments} Une adresse de ${i.territoryName} où l'on prend le temps de conseiller : poussez la porte !`,
    seoTitle: `${i.name} à ${i.commune} – ${i.activity}`.slice(0, 70),
    source: 'rules',
  };
}

export async function improveDescription(i: ImproveInput, ctx: AiContext) {
  const ai = await structuredCall({
    feature: 'IMPROVE',
    system: `${WRITER_SYSTEM}
Tâche : réécrire la description publique d'une fiche d'établissement pour qu'elle soit claire, chaleureuse et bien référencée.
N'utilise que les informations fournies : si un détail n'est pas donné (date de création, labels, prix), ne l'invente pas.`,
    schema: improveSchema,
    effort: 'medium',
    maxTokens: 3000,
    ctx,
    user: `Établissement : ${i.name} — ${i.activity} à ${i.commune} (${i.territoryName})
Description actuelle : « ${i.current || '(vide)'} »
Produits : ${i.products.join(', ') || '—'}
Services : ${i.services.join(', ') || '—'}
Moyens de paiement : ${i.payments.join(', ') || '—'}`,
  });
  if (ai) return { ...ai, source: 'ai' as const };
  await recordUsage('IMPROVE', ctx, { model: 'regles', inputTokens: 0, outputTokens: 0, fallback: true });
  return improveFallback(i);
}

// ─── 3. Recherche en langage naturel ────────────────────────────────────────

const intentSchema = z.object({
  families: z.array(z.enum(['COMMERCE', 'ARTISAN', 'PRODUCTEUR', 'RESTAURATION', 'SERVICES'])),
  categorySlugs: z.array(z.string()),
  attributeSlugs: z.array(z.string()),
  communeSlugs: z.array(z.string()),
  openNow: z.boolean(),
  openTonight: z.boolean(),
  keywords: z.array(z.string()).describe('Mots-clés produits/services utiles non couverts par les filtres'),
  kind: z.enum(['tonight', 'gift', 'repair', 'local', 'generic']),
});

export async function interpretSearch(query: string, vocab: SearchVocabulary, ctx: AiContext): Promise<SearchIntent & { source: 'ai' | 'rules' }> {
  const rules = interpretQuery(query, vocab);
  if (!rules.natural || !aiEnabled()) return { ...rules, source: 'rules' };
  const ai = await structuredCall({
    feature: 'SEARCH',
    system: `Tu traduis une recherche d'habitant ou de visiteur en filtres pour l'annuaire économique d'un territoire.
N'utilise que les identifiants (slugs) listés. Laisse une liste vide si rien ne correspond. N'invente pas d'identifiant.
Catégories disponibles : ${vocab.categories.map((c) => `${c.slug} (${c.name})`).join(', ')}
Services et labels : ${vocab.attributes.map((a) => `${a.slug} (${a.label})`).join(', ')}
Communes : ${vocab.communes.map((c) => `${c.slug} (${c.name})`).join(', ')}`,
    schema: intentSchema,
    effort: 'low',
    maxTokens: 2000,
    ctx,
    user: query,
  });
  if (!ai) return { ...rules, source: 'rules' };
  const validCat = new Set(vocab.categories.map((c) => c.slug));
  const validAttr = new Set(vocab.attributes.map((a) => a.slug));
  const validCom = new Set(vocab.communes.map((c) => c.slug));
  return {
    families: ai.families as Family[],
    categorySlugs: ai.categorySlugs.filter((s) => validCat.has(s)),
    attributeSlugs: ai.attributeSlugs.filter((s) => validAttr.has(s)),
    communeSlugs: ai.communeSlugs.filter((s) => validCom.has(s)),
    openNow: ai.openNow,
    openTonight: ai.openTonight,
    keywords: ai.keywords.slice(0, 6),
    kind: ai.kind,
    natural: true,
    source: 'ai',
  };
}

const answerSchema = z.object({
  text: z.string().describe('Réponse de 1 à 3 phrases, concrète, qui cite les établissements par leur nom'),
  meta: z.string().describe('Résumé très court, par ex. « 3 restaurants · triés par distance »'),
});

export async function answerSearch(
  query: string,
  intent: SearchIntent,
  results: AnswerResult[],
  ctx: AiContext,
): Promise<{ text: string; meta: string; source: 'ai' | 'rules' } | null> {
  if (!intent.natural) return null;
  if (aiEnabled() && results.length) {
    const ai = await structuredCall({
      feature: 'SEARCH',
      system: `Tu es l'assistant du portail économique d'un territoire. Tu réponds à la question d'un habitant en t'appuyant UNIQUEMENT sur la liste de résultats fournie (noms, activités, communes, horaires, distances, offres).
Réponds en français, en 1 à 3 phrases concrètes, sans formule de politesse. N'invente aucune information absente des résultats.`,
      schema: answerSchema,
      effort: 'low',
      maxTokens: 1500,
      ctx,
      user: `Question : « ${query} »\nRésultats :\n${results
        .slice(0, 6)
        .map(
          (r, i) =>
            `${i + 1}. ${r.name} — ${r.activity}, ${r.communeName}${r.distance ? `, à ${r.distance}` : ''} — ${r.openLabel}${r.until ? ` jusqu'à ${r.until}` : ''}${r.offer ? ` — offre : ${r.offer}` : ''}${r.campaign ? ` — participe à « ${r.campaign} »` : ''}`,
        )
        .join('\n')}`,
    });
    if (ai) return { ...ai, source: 'ai' };
  }
  const fallback = composeAnswer(query, intent, results);
  return fallback ? { ...fallback, source: 'rules' } : null;
}

// ─── 4. Assistant territorial : préparation d'une campagne ──────────────────

export type CampaignCandidate = {
  id: string;
  name: string;
  activity: string;
  family: Family;
  commune: string;
  attributes: string[];
  completeness: number;
};

export type CampaignPlan = {
  name: string;
  tagline: string;
  families: Family[];
  attributeSlugs: string[];
  selectedIds: string[];
  criteriaText: string;
  pageTitle: string;
  pageText: string;
  newsletterSubject: string;
  newsletterIntro: string;
  startsAt: string;
  endsAt: string;
  plan: { date: string; text: string }[];
  source: 'ai' | 'rules';
};

const campaignSchema = z.object({
  name: z.string(),
  tagline: z.string(),
  families: z.array(z.enum(['COMMERCE', 'ARTISAN', 'PRODUCTEUR', 'RESTAURATION', 'SERVICES'])),
  attributeSlugs: z.array(z.string()),
  selectedIds: z.array(z.string()).describe('Identifiants des établissements retenus parmi les candidats'),
  criteriaText: z.string().describe('Critères de sélection en une phrase'),
  pageTitle: z.string(),
  pageText: z.string().describe('Texte de la page thématique, 2 à 3 phrases, qui cite quelques établissements'),
  newsletterSubject: z.string(),
  newsletterIntro: z.string(),
  startsAt: z.string().describe('Date de début AAAA-MM-JJ'),
  endsAt: z.string().describe('Date de fin AAAA-MM-JJ'),
  plan: z.array(z.object({ date: z.string().describe('Libellé court, ex. « 25 nov. »'), text: z.string() })),
});

function fmtShort(iso: string): string {
  const [, m, d] = iso.split('-').map(Number);
  const months = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];
  return `${d === 1 ? '1er' : d} ${months[m - 1]}`;
}

function addDays(iso: string, n: number): string {
  const d = new Date(`${iso}T12:00:00Z`);
  d.setUTCDate(d.getUTCDate() + n);
  return d.toISOString().slice(0, 10);
}

export function campaignFallback(
  prompt: string,
  territoryName: string,
  candidates: CampaignCandidate[],
  subscribers: number,
  now = new Date(),
): CampaignPlan {
  const q = prompt.toLowerCase();
  const families: Family[] = [];
  if (/producteur|terroir|ferme|local/.test(q)) families.push('PRODUCTEUR');
  if (/artisan|atelier|métier|metier/.test(q)) families.push('ARTISAN');
  if (/restaura|table|gastronom/.test(q)) families.push('RESTAURATION');
  if (/commer|boutique/.test(q)) families.push('COMMERCE');
  const year = now.getUTCFullYear();
  const isXmas = /no[eë]l|f[êe]tes|cadeau/.test(q);
  const today = now.toISOString().slice(0, 10);
  const startsAt = isXmas ? (today > `${year}-12-01` ? today : `${year}-12-01`) : addDays(today, 14);
  const endsAt = isXmas ? `${year}-12-24` : addDays(startsAt, 21);
  const pool = candidates.filter((c) => (families.length ? families.includes(c.family) : true));
  const selected = pool.filter((c) => c.completeness >= 60).concat(pool.filter((c) => c.completeness < 60)).slice(0, 40);
  const communesCount = new Set(selected.map((c) => c.commune)).size;
  const label = families.length === 1 ? FAMILIES[families[0]].plural : 'professionnels';
  const name = isXmas ? `Un Noël 100 % ${territoryName}` : `Le mois des ${label} de ${territoryName}`;
  const cited = selected.slice(0, 3).map((c) => c.name);
  const inv = addDays(startsAt, -6);
  return {
    name,
    tagline: isXmas ? `${selected.length} ${label} vous ouvrent leurs portes jusqu'au 24 décembre` : `${selected.length} ${label} à découvrir`,
    families,
    attributeSlugs: isXmas ? ['idees-cadeaux'] : [],
    selectedIds: selected.map((c) => c.id),
    criteriaText: `Critères : ${families.length ? families.map((f) => FAMILIES[f].plural).join(', ') : 'toutes activités'}, fiche complète en priorité, ${communesCount} commune${communesCount > 1 ? 's' : ''} représentée${communesCount > 1 ? 's' : ''}.`,
    pageTitle: name,
    pageText: isXmas
      ? `« Cette année, glissez sous le sapin ${cited.length ? `un cadeau de chez ${cited.join(', ')}` : 'des produits d’ici'}. ${selected.length} ${label} vous ouvrent leurs portes jusqu'au 24 décembre. »`
      : `« ${selected.length} ${label} de ${territoryName} vous attendent${cited.length ? `, de ${cited.join(' à ')}` : ''}. Poussez la porte ! »`,
    newsletterSubject: isXmas ? `Noël se prépare chez vos ${label}` : `Ce mois-ci, place aux ${label}`,
    newsletterIntro: `Découvrez la sélection ${isXmas ? 'de Noël ' : ''}de ${territoryName} : ${selected.length} adresses près de chez vous.`,
    startsAt,
    endsAt,
    plan: [
      { date: fmtShort(inv), text: `Invitation des ${selected.length} ${label} à publier une offre` },
      { date: fmtShort(startsAt), text: 'Mise en ligne de la page thématique' },
      { date: fmtShort(addDays(startsAt, 2)), text: `Newsletter habitants · ${subscribers.toLocaleString('fr-FR')} abonnés` },
      { date: `${fmtShort(addDays(startsAt, 5)).split(' ')[0]}–${fmtShort(addDays(endsAt, -4))}`, text: '6 publications réseaux programmées' },
      { date: fmtShort(addDays(endsAt, -10)), text: 'Mise en avant sur le marché et dans les vitrines' },
    ],
    source: 'rules',
  };
}

export async function planCampaign(
  prompt: string,
  territoryName: string,
  candidates: CampaignCandidate[],
  subscribers: number,
  ctx: AiContext,
): Promise<CampaignPlan> {
  const today = new Date().toISOString().slice(0, 10);
  const ai = await structuredCall({
    feature: 'TERRITORIAL',
    system: `Tu es l'assistant territorial de terricom. Tu aides les agents d'une collectivité à préparer des campagnes d'animation commerciale (Noël chez vos commerçants, semaine de l'artisanat, producteurs locaux…).
Tu sélectionnes des établissements UNIQUEMENT parmi les candidats fournis (par identifiant), privilégie les fiches complètes et la diversité des communes.
Textes en français, ton « sérieux sur le fond, souriant sur la forme », concrets, sans superlatifs creux. Nous sommes le ${today}.`,
    schema: campaignSchema,
    effort: 'high',
    maxTokens: 8000,
    ctx,
    user: `Demande de l'agent : « ${prompt} »
Territoire : ${territoryName} — ${subscribers} abonnés à la newsletter.
Candidats (id | nom | activité | famille | commune | complétude | services) :
${candidates
  .slice(0, 250)
  .map((c) => `${c.id} | ${c.name} | ${c.activity} | ${c.family} | ${c.commune} | ${c.completeness}% | ${c.attributes.join(', ')}`)
  .join('\n')}`,
  });
  if (ai) {
    const valid = new Set(candidates.map((c) => c.id));
    return { ...ai, families: ai.families as Family[], selectedIds: ai.selectedIds.filter((id) => valid.has(id)), source: 'ai' };
  }
  await recordUsage('TERRITORIAL', ctx, { model: 'regles', inputTokens: 0, outputTokens: 0, fallback: true });
  return campaignFallback(prompt, territoryName, candidates, subscribers);
}

// ─── 5. Traduction (module multilingue) ─────────────────────────────────────

const translateSchema = z.object({ text: z.string() });

export async function translateText(text: string, lang: 'en' | 'de' | 'es' | 'it' | 'nl', ctx: AiContext): Promise<string | null> {
  const names = { en: 'anglais', de: 'allemand', es: 'espagnol', it: 'italien', nl: 'néerlandais' };
  const ai = await structuredCall({
    feature: 'TRANSLATE',
    system: `Traduis fidèlement du français vers l'${names[lang]} un texte de présentation d'un commerce local. Conserve les noms propres. Ne rajoute rien.`,
    schema: translateSchema,
    effort: 'low',
    maxTokens: 3000,
    ctx,
    user: text,
  });
  return ai?.text ?? null;
}
