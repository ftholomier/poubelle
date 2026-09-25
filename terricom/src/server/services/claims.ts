import { and, asc, desc, eq, inArray, ne, sql } from 'drizzle-orm';
import type { EstablishmentStatus } from '@/lib/constants';
import { fmtPhone, fullName } from '@/lib/format';
import { audit, type AuditActor } from '../audit';
import { rateLimit } from '../auth/rate-limit';
import { invalidate } from '../cache';
import { decrypt, encrypt, numericCode, sha256 } from '../crypto';
import { db } from '../db';
import {
  categories,
  claims,
  communes,
  companies,
  companyMembers,
  establishmentRevisions,
  establishments,
  media,
  openingHours,
  roleAssignments,
  territories,
  users,
  type CheckResult,
  type TerritorySettings,
} from '../db/schema';
import { isValidSiret, lookupSiret, type SireneEstablishment } from '../integrations/public-data';
import { sendEmail } from '../mail/send';
import { claimApprovedTemplate, claimNeedsInfoTemplate, claimRejectedTemplate, claimSubmittedTemplate } from '../mail/templates';
import { isMobileNumber, maskPhone, sendSms, smsAvailable } from '../sms';
import { appUrl } from '../urls';
import { refreshCompleteness } from './establishments';

/**
 * Revendication d'une fiche par un professionnel : recherche, contrôles automatiques
 * (SIRET/SIRENE, domaine email, code, Kbis), niveau de risque et décision de la collectivité.
 */

export type ClaimMethod = 'SIRET' | 'CODE' | 'KBIS';
export type RiskLevel = 'LOW' | 'MEDIUM' | 'HIGH';
export const OPEN_CLAIM_STATUSES = ['PENDING', 'NEEDS_INFO'] as const;
/** Motif de masquage d'une fiche créée par un professionnel, en attente de validation. */
export const PENDING_CREATION = 'Création en attente de validation par la collectivité';

const SMS_CODE_TTL_MS = 30 * 60_000;
const LETTER_CODE_TTL_MS = 30 * 86_400_000;

const FREE_MAIL = new Set([
  'gmail.com',
  'googlemail.com',
  'yahoo.fr',
  'yahoo.com',
  'hotmail.fr',
  'hotmail.com',
  'outlook.fr',
  'outlook.com',
  'live.fr',
  'live.com',
  'msn.com',
  'orange.fr',
  'wanadoo.fr',
  'free.fr',
  'sfr.fr',
  'neuf.fr',
  'laposte.net',
  'icloud.com',
  'me.com',
  'aol.com',
  'bbox.fr',
  'gmx.fr',
  'gmx.com',
  'protonmail.com',
  'proton.me',
]);

const LEGAL_FORMS = /\b(SARL|SAS|SASU|EURL|SA|SCI|SNC|EARL|GAEC|SCEA|SCOP|SELARL|ASSOCIATION)\b/;

function norm(s: string | null | undefined): string {
  return (s ?? '')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toUpperCase()
    .replace(/[^A-Z0-9]+/g, ' ')
    .trim();
}

function hostOf(url: string | null | undefined): string | null {
  if (!url) return null;
  try {
    return new URL(url.startsWith('http') ? url : `https://${url}`).hostname.replace(/^www\./, '').toLowerCase();
  } catch {
    return null;
  }
}

/** « la mairie d'Ornans », « la mairie de Quingey ». */
export function mairieDe(commune: string): string {
  return /^[aeiouyhâéèêîôûAEIOUYHÂÉÈÊÎÔÛ]/.test(commune) ? `la mairie d’${commune}` : `la mairie de ${commune}`;
}

export function claimStatusLabel(status: EstablishmentStatus, managed: boolean): string {
  if (status === 'SUSPENDED') return 'fiche suspendue';
  if (status === 'ARCHIVED') return 'fiche archivée';
  if (managed) return 'fiche revendiquée';
  return status === 'TO_COMPLETE' ? 'fiche à compléter' : 'fiche précréée';
}

// ─── Recherche ─────────────────────────────────────────────────────────────

export type ClaimCandidate = {
  id: string;
  name: string;
  street: string | null;
  communeName: string;
  territoryName: string;
  status: EstablishmentStatus;
  coverUrl: string | null;
  managed: boolean;
  pending: boolean;
  claimable: boolean;
  statusLabel: string;
};

/** Recherche d'établissements à revendiquer (nom, commune, activité ou SIRET). */
export async function searchClaimCandidates(q: string, territoryId: string | null, limit = 6): Promise<ClaimCandidate[]> {
  const digits = q.replace(/\s/g, '');
  const tokens = q
    .split(/[\s,.;:'’()/-]+/)
    .map((t) => t.trim())
    .filter((t) => t.length >= 2)
    .slice(0, 6);
  const conds = [ne(establishments.status, 'ARCHIVED')];
  if (territoryId) conds.push(eq(establishments.territoryId, territoryId));
  if (/^\d{14}$/.test(digits)) conds.push(eq(establishments.siret, digits));
  else if (/^\d{9}$/.test(digits)) conds.push(sql`${establishments.siret} like ${`${digits}%`}`);
  else if (tokens.length) {
    for (const t of tokens) {
      const like = `%${t}%`;
      conds.push(
        sql`(f_unaccent(${establishments.name}) ilike f_unaccent(${like}) or f_unaccent(${communes.name}) ilike f_unaccent(${like}) or f_unaccent(coalesce(${establishments.activityLabel}, '')) ilike f_unaccent(${like}) or array_to_string(${communes.postalCodes}, ' ') like ${like})`,
      );
    }
  } else return [];
  const managed = sql<boolean>`exists (select 1 from company_members m where m.company_id = "establishments"."company_id")`;
  const rows = await db
    .select({
      id: establishments.id,
      name: establishments.name,
      street: establishments.street,
      status: establishments.status,
      coverUrl: establishments.coverUrl,
      communeName: communes.name,
      territoryName: territories.name,
      managed,
      pending: sql<boolean>`exists (select 1 from claims c where c.establishment_id = "establishments"."id" and c.status in ('PENDING', 'NEEDS_INFO'))`,
    })
    .from(establishments)
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .innerJoin(territories, eq(territories.id, establishments.territoryId))
    .where(and(...conds))
    // Les fiches encore libres d'abord : c'est ce que cherche la personne qui revendique.
    .orderBy(managed, sql`similarity(f_unaccent(${establishments.name}), f_unaccent(${q})) desc`, asc(establishments.name))
    .limit(limit);
  return rows.map((r) => {
    const claimable = !r.managed && r.status !== 'SUSPENDED' && r.status !== 'ARCHIVED';
    return { ...r, claimable, statusLabel: claimStatusLabel(r.status, r.managed) };
  });
}

// ─── Fiche à revendiquer ───────────────────────────────────────────────────

export async function loadClaimTarget(estId: string) {
  const [row] = await db
    .select({
      est: establishments,
      company: companies,
      commune: communes,
      territory: territories,
      categoryName: categories.name,
    })
    .from(establishments)
    .innerJoin(companies, eq(companies.id, establishments.companyId))
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .innerJoin(territories, eq(territories.id, establishments.territoryId))
    .innerJoin(categories, eq(categories.id, establishments.categoryId))
    .where(eq(establishments.id, estId))
    .limit(1);
  if (!row) return null;
  const [[members], [hours]] = await Promise.all([
    db
      .select({ n: sql<number>`count(*)::int` })
      .from(companyMembers)
      .where(eq(companyMembers.companyId, row.est.companyId)),
    db
      .select({ n: sql<number>`count(*)::int` })
      .from(openingHours)
      .where(eq(openingHours.establishmentId, estId)),
  ]);
  const managed = (members?.n ?? 0) > 0;
  return { ...row, managed, hasHours: (hours?.n ?? 0) > 0, claimable: !managed && row.est.status !== 'SUSPENDED' && row.est.status !== 'ARCHIVED' };
}

export type ClaimTarget = NonNullable<Awaited<ReturnType<typeof loadClaimTarget>>>;

/** Lignes « Ces infos sont-elles justes ? » avec leur source. */
export function claimInfoRows(t: ClaimTarget): { k: string; v: string; s: string; ok: boolean }[] {
  const e = t.est;
  const fromImport = e.origin === 'IMPORT' && Boolean(e.siret);
  return [
    { k: 'Nom', v: e.name, s: fromImport ? '✓ SIRENE' : '✓ Fiche', ok: true },
    {
      k: 'Adresse',
      v: [e.street, t.commune.name].filter(Boolean).join(', '),
      s: e.lat !== null && e.lng !== null ? '✓ OSM' : 'à confirmer',
      ok: e.lat !== null && e.lng !== null,
    },
    {
      k: 'Activité',
      v: `${e.activityLabel ?? t.categoryName}${t.company.nafCode ? ` (${t.company.nafCode})` : ''}`,
      s: t.company.nafCode ? '✓ NAF' : '✓ Fiche',
      ok: true,
    },
    { k: 'Téléphone', v: e.phone ? fmtPhone(e.phone) : '—', s: e.phone ? 'à confirmer' : 'à compléter', ok: false },
    { k: 'Horaires', v: t.hasHours ? 'Renseignés par la collectivité' : '—', s: t.hasHours ? 'à vérifier' : 'à compléter', ok: false },
  ];
}

/** Moyen d'envoi du code de vérification pour une fiche. */
export function codeChannel(t: ClaimTarget): { kind: 'SMS' | 'LETTER'; label: string } {
  if (smsAvailable() && isMobileNumber(t.est.phone)) return { kind: 'SMS', label: `SMS au ${maskPhone(t.est.phone!)}` };
  const addr = [t.est.street, `${t.est.postalCode ?? t.commune.postalCodes[0] ?? ''} ${t.commune.name}`.trim()].filter(Boolean).join(', ');
  return { kind: 'LETTER', label: `Courrier à l’adresse de l’établissement (${addr})` };
}

// ─── Contrôles automatiques ────────────────────────────────────────────────

type Evidence = {
  target: ClaimTarget;
  claimant: { firstName: string; lastName: string; email: string };
  method: ClaimMethod;
  siret: string | null;
  sirene: SireneEstablishment | null | undefined;
  kbis: boolean;
  codeVerified: boolean;
  codeSentTo: string | null;
  otherPending: number;
};

export type SiretVerdict = { ok: boolean | null; message: string; holder: string | null; strong: boolean };

/** Vérifie un SIRET saisi au regard de la fiche et de la base SIRENE. */
export function siretVerdict(
  t: ClaimTarget,
  siret: string,
  claimant: { firstName: string; lastName: string } | null,
  sirene: SireneEstablishment | null | undefined,
): SiretVerdict {
  const clean = siret.replace(/\s/g, '');
  if (!isValidSiret(clean)) return { ok: false, message: 'Ce numéro SIRET n’est pas valide (14 chiffres, clé de contrôle).', holder: null, strong: false };
  if (sirene && !sirene.active) return { ok: false, message: 'Cet établissement est fermé dans la base SIRENE.', holder: sirene.holderName, strong: false };
  const holder = sirene?.holderName ?? (t.company.siren === clean.slice(0, 9) ? t.company.legalName : null) ?? sirene?.legalName ?? null;
  if (t.est.siret && t.est.siret !== clean) {
    if (t.est.siret.slice(0, 9) === clean.slice(0, 9))
      return { ok: null, message: 'Autre établissement de la même entreprise : la collectivité confirmera.', holder, strong: false };
    return { ok: false, message: 'Ce SIRET ne correspond pas à celui de la fiche.', holder, strong: false };
  }
  if (!holder) return { ok: null, message: 'Base SIRENE momentanément injoignable : la collectivité vérifiera.', holder: null, strong: false };
  const h = norm(holder);
  const nameMatch = claimant
    ? norm(claimant.lastName)
        .split(' ')
        .some((part) => part.length >= 2 && h.split(' ').includes(part))
    : false;
  if (nameMatch) return { ok: true, message: `Correspond à « ${holder} » dans la base SIRENE`, holder, strong: true };
  if (LEGAL_FORMS.test(h) || !claimant) return { ok: true, message: `Correspond à « ${holder} » dans la base SIRENE`, holder, strong: false };
  return { ok: false, message: `SIRET de la fiche, mais titulaire différent (« ${holder} »).`, holder, strong: false };
}

function evaluate(ev: Evidence): { checks: CheckResult[]; risk: RiskLevel; holder: string | null; strong: boolean } {
  const checks: CheckResult[] = [];
  let strong = false;
  let high = false;
  let holder: string | null = null;

  if (ev.siret) {
    const v = siretVerdict(ev.target, ev.siret, ev.claimant, ev.sirene);
    holder = v.holder;
    strong ||= v.strong;
    if (v.ok === true) checks.push({ ok: true, label: v.strong ? 'SIRET vérifié' : 'SIRET', detail: v.holder ? `Titulaire : ${v.holder}` : v.message });
    else if (v.ok === false) {
      const mismatch = v.message.startsWith('Ce SIRET ne correspond') || v.message.includes('fermé');
      high ||= mismatch;
      checks.push({ ok: false, label: 'SIRET', detail: v.message.includes('titulaire différent') ? 'Nom du gérant différent' : v.message });
    } else checks.push({ ok: null, label: 'SIRET', detail: v.message });
  } else if (ev.method === 'SIRET') {
    checks.push({ ok: false, label: 'SIRET', detail: 'Non renseigné' });
  }

  // Domaine de l'adresse email
  const domain = ev.claimant.email.split('@')[1]?.toLowerCase() ?? '';
  const site = hostOf(ev.target.est.website);
  const estMailDomain = ev.target.est.email?.split('@')[1]?.toLowerCase() ?? null;
  if (ev.target.est.email && ev.target.est.email.toLowerCase() === ev.claimant.email.toLowerCase())
    checks.push({ ok: true, label: 'Email de la fiche', detail: 'Identique à l’email public' });
  else if (domain && (domain === site || domain === estMailDomain))
    checks.push({ ok: true, label: 'Domaine email', detail: `${domain} = ${domain === site ? 'site' : 'email'} de la fiche` });
  else if (FREE_MAIL.has(domain)) checks.push({ ok: false, label: 'Email personnel', detail: 'Domaine non professionnel' });
  else checks.push({ ok: null, label: 'Domaine email', detail: `${domain} : sans lien connu avec la fiche` });

  if (ev.method === 'CODE') {
    const letter = ev.codeSentTo?.startsWith('Courrier');
    if (ev.codeVerified) {
      strong = true;
      checks.push({ ok: true, label: letter ? 'Code courrier' : 'Code SMS', detail: 'Code saisi par le demandeur' });
    } else checks.push({ ok: null, label: letter ? 'Code courrier' : 'Code SMS', detail: 'Envoyé, en attente de saisie' });
  }
  if (ev.kbis) checks.push({ ok: null, label: 'Kbis déposé', detail: 'À contrôler : daté de moins de 3 mois' });
  if (ev.otherPending > 0) {
    high = true;
    checks.push({
      ok: false,
      label: 'Demande concurrente',
      detail: `${ev.otherPending} autre${ev.otherPending > 1 ? 's' : ''} demande${ev.otherPending > 1 ? 's' : ''} en cours`,
    });
  }
  if (ev.target.managed) {
    high = true;
    checks.push({ ok: false, label: 'Fiche déjà gérée', detail: 'Un compte gère déjà cette fiche' });
  }
  const risk: RiskLevel = high ? 'HIGH' : strong ? 'LOW' : 'MEDIUM';
  return { checks, risk, holder, strong };
}

/** Vérification en direct d'un SIRET (étape « Identité »). */
export async function checkSiretFor(estId: string, siret: string, claimant: { firstName: string; lastName: string } | null): Promise<SiretVerdict> {
  const t = await loadClaimTarget(estId);
  if (!t) return { ok: false, message: 'Fiche introuvable.', holder: null, strong: false };
  const clean = siret.replace(/\s/g, '');
  const sirene = isValidSiret(clean) ? await lookupSiret(clean) : null;
  return siretVerdict(t, clean, claimant, sirene);
}

// ─── Dépôt d'une demande ───────────────────────────────────────────────────

export class ClaimError extends Error {}

export async function openClaimOf(userId: string, estId: string) {
  const [row] = await db
    .select()
    .from(claims)
    .where(and(eq(claims.userId, userId), eq(claims.establishmentId, estId), inArray(claims.status, [...OPEN_CLAIM_STATUSES])))
    .orderBy(desc(claims.createdAt))
    .limit(1);
  return row ?? null;
}

export async function submitClaim(input: {
  estId: string;
  user: { id: string; firstName: string; lastName: string; email: string };
  method: ClaimMethod;
  siret: string | null;
  claimantRole: string | null;
  kbisMediaId: string | null;
  /** Fiche créée par le demandeur (inscription) : masquée jusqu'à validation. */
  creation?: boolean;
}): Promise<{ claimId: string; demoCode: string | null; approved: boolean }> {
  const t = await loadClaimTarget(input.estId);
  if (!t) throw new ClaimError('Fiche introuvable.');
  if (t.managed) throw new ClaimError('Cette fiche est déjà gérée par un compte. Contactez votre mairie si vous avez repris l’activité.');
  const pendingCreation = input.creation && t.est.suspendedReason === PENDING_CREATION;
  if (!t.claimable && !pendingCreation) throw new ClaimError('Cette fiche ne peut pas être revendiquée pour le moment. Contactez votre mairie.');
  const existing = await openClaimOf(input.user.id, t.est.id);
  if (existing) return { claimId: existing.id, demoCode: null, approved: false };

  const siret = input.siret ? input.siret.replace(/\s/g, '') : null;
  if (input.method === 'SIRET' && (!siret || !isValidSiret(siret))) throw new ClaimError('Saisissez un numéro SIRET valide (14 chiffres).');
  if (input.method === 'KBIS' && !input.kbisMediaId) throw new ClaimError('Déposez votre extrait Kbis (PDF).');
  const sirene = siret && isValidSiret(siret) ? await lookupSiret(siret) : undefined;
  const [{ n: otherPending }] = await db
    .select({ n: sql<number>`count(*)::int` })
    .from(claims)
    .where(and(eq(claims.establishmentId, t.est.id), inArray(claims.status, [...OPEN_CLAIM_STATUSES])));

  let code: string | null = null;
  let codeSentTo: string | null = null;
  const channel = input.method === 'CODE' ? codeChannel(t) : null;
  if (channel) {
    code = numericCode(6);
    codeSentTo = channel.label;
  }
  const result = evaluate({
    target: t,
    claimant: input.user,
    method: input.method,
    siret,
    sirene,
    kbis: Boolean(input.kbisMediaId),
    codeVerified: false,
    codeSentTo,
    otherPending,
  });
  const [claim] = await db
    .insert(claims)
    .values({
      establishmentId: t.est.id,
      territoryId: t.est.territoryId,
      userId: input.user.id,
      status: 'PENDING',
      claimantRole: input.claimantRole,
      method: input.method,
      siretProvided: siret,
      sireneHolder: result.holder,
      checks: result.checks,
      riskLevel: result.risk,
      kbisMediaId: input.kbisMediaId,
      codeSentTo,
      codeSentAt: code ? new Date() : null,
    })
    .returning();
  if (code) {
    await db
      .update(claims)
      .set({ codeHash: sha256(`${claim.id}:${code}`), codeEnc: channel?.kind === 'LETTER' ? encrypt(code) : null })
      .where(eq(claims.id, claim.id));
    if (channel?.kind === 'SMS')
      await sendSms(t.est.phone!, `terricom : votre code de vérification pour « ${t.est.name} » est ${code}. Il expire dans 30 minutes.`);
  }
  if (input.kbisMediaId) await db.update(media).set({ ownerId: claim.id }).where(eq(media.id, input.kbisMediaId));

  await audit({
    actor: { user: { ...input.user } },
    category: 'MODIFICATION',
    action: 'claim.submitted',
    summary: `Demande de revendication de « ${t.est.name} » (${input.method}, risque ${result.risk.toLowerCase()})`,
    territoryId: t.est.territoryId,
    targetType: 'establishment',
    targetId: t.est.id,
    metadata: { claimId: claim.id },
  });

  const settings = (t.territory.settings ?? {}) as TerritorySettings;
  if (settings.claimValidation === 'AUTO' && result.risk === 'LOW' && result.strong && !pendingCreation) {
    await approveClaim(claim.id, 'Système', 'Validation automatique (contrôles concordants)');
    return { claimId: claim.id, demoCode: code, approved: true };
  }
  await sendEmail({
    ...claimSubmittedTemplate({
      to: input.user.email,
      firstName: input.user.firstName,
      establishmentName: t.est.name,
      reviewer: await reviewerLabel(t.est.communeId, t.commune.name, t.territory.name),
    }),
    territoryId: t.est.territoryId,
  });
  return { claimId: claim.id, demoCode: code, approved: false };
}

/** Qui valide : la mairie si elle a des agents, sinon la collectivité du territoire. */
export async function reviewerLabel(communeId: string, communeName: string, territoryName: string): Promise<string> {
  const [agent] = await db.select({ id: roleAssignments.id }).from(roleAssignments).where(eq(roleAssignments.communeId, communeId)).limit(1);
  const label = agent ? mairieDe(communeName) : territoryName;
  return label.charAt(0).toUpperCase() + label.slice(1);
}

/** Saisie du code reçu par SMS ou par courrier. */
export async function verifyClaimCode(claimId: string, userId: string, code: string): Promise<{ ok: boolean; message: string; approved?: boolean }> {
  const [c] = await db
    .select()
    .from(claims)
    .where(and(eq(claims.id, claimId), eq(claims.userId, userId)))
    .limit(1);
  if (!c || !OPEN_CLAIM_STATUSES.includes(c.status as (typeof OPEN_CLAIM_STATUSES)[number])) return { ok: false, message: 'Demande introuvable.' };
  if (c.codeVerifiedAt) return { ok: true, message: 'Code déjà validé.' };
  if (!c.codeHash || !c.codeSentAt) return { ok: false, message: 'Aucun code n’a été envoyé pour cette demande.' };
  const limited = await rateLimit(`claimcode:${c.id}`, 6, 3600);
  if (!limited.ok) return { ok: false, message: 'Trop d’essais. Réessayez dans une heure.' };
  const ttl = c.codeSentTo?.startsWith('Courrier') ? LETTER_CODE_TTL_MS : SMS_CODE_TTL_MS;
  if (Date.now() - c.codeSentAt.getTime() > ttl) return { ok: false, message: 'Ce code a expiré. Demandez-en un nouveau.' };
  const clean = code.replace(/\D/g, '');
  if (sha256(`${c.id}:${clean}`) !== c.codeHash) return { ok: false, message: 'Code incorrect.' };
  const t = await loadClaimTarget(c.establishmentId);
  const [u] = await db.select().from(users).where(eq(users.id, userId)).limit(1);
  if (!t || !u) return { ok: false, message: 'Demande introuvable.' };
  const [{ n: otherPending }] = await db
    .select({ n: sql<number>`count(*)::int` })
    .from(claims)
    .where(and(eq(claims.establishmentId, c.establishmentId), inArray(claims.status, [...OPEN_CLAIM_STATUSES]), ne(claims.id, c.id)));
  const result = evaluate({
    target: t,
    claimant: u,
    method: 'CODE',
    siret: c.siretProvided,
    sirene: c.sireneHolder ? ({ holderName: c.sireneHolder, active: true } as SireneEstablishment) : undefined,
    kbis: Boolean(c.kbisMediaId),
    codeVerified: true,
    codeSentTo: c.codeSentTo,
    otherPending,
  });
  await db
    .update(claims)
    .set({ codeVerifiedAt: new Date(), codeEnc: null, checks: result.checks, riskLevel: result.risk, updatedAt: new Date() })
    .where(eq(claims.id, c.id));
  await audit({
    actor: { user: u },
    category: 'MODIFICATION',
    action: 'claim.code_verified',
    summary: `Code de vérification saisi pour « ${t.est.name} »`,
    territoryId: c.territoryId,
    targetType: 'establishment',
    targetId: c.establishmentId,
  });
  const settings = (t.territory.settings ?? {}) as TerritorySettings;
  if (settings.claimValidation === 'AUTO' && result.risk === 'LOW') {
    await approveClaim(c.id, 'Système', 'Validation automatique (code vérifié)');
    return { ok: true, message: 'Code validé : la fiche est à vous !', approved: true };
  }
  return { ok: true, message: 'Code validé. La collectivité finalise la vérification.' };
}

/** Code d'un courrier à imprimer (back-office), tant qu'il n'a pas été saisi. */
export function letterCodeOf(c: { codeEnc: string | null }): string | null {
  return c.codeEnc ? decrypt(c.codeEnc) : null;
}

/** Complément demandé : le demandeur dépose un Kbis. */
export async function addClaimDocument(claimId: string, userId: string, kbisMediaId: string): Promise<void> {
  const [c] = await db
    .select()
    .from(claims)
    .where(and(eq(claims.id, claimId), eq(claims.userId, userId)))
    .limit(1);
  if (!c || !OPEN_CLAIM_STATUSES.includes(c.status as (typeof OPEN_CLAIM_STATUSES)[number])) throw new ClaimError('Demande introuvable.');
  const checks = c.checks.filter((k) => k.label !== 'Kbis déposé');
  checks.push({ ok: null, label: 'Kbis déposé', detail: 'À contrôler : daté de moins de 3 mois' });
  await db.update(claims).set({ kbisMediaId, status: 'PENDING', checks, updatedAt: new Date() }).where(eq(claims.id, c.id));
  await db.update(media).set({ ownerId: c.id }).where(eq(media.id, kbisMediaId));
}

// ─── Décisions de la collectivité ──────────────────────────────────────────

type Reviewer = { user: { id: string; firstName: string; lastName: string; email: string } } | 'Système' | 'Démonstration';

function reviewerId(r: Reviewer): string | null {
  return typeof r === 'string' ? null : r.user.id;
}

async function claimWithContext(claimId: string) {
  const [row] = await db
    .select({ claim: claims, user: users, est: establishments, commune: communes, territory: territories })
    .from(claims)
    .innerJoin(users, eq(users.id, claims.userId))
    .innerJoin(establishments, eq(establishments.id, claims.establishmentId))
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .innerJoin(territories, eq(territories.id, establishments.territoryId))
    .where(eq(claims.id, claimId))
    .limit(1);
  return row ?? null;
}

function invalidatePortal(territoryId: string) {
  invalidate(`cards:${territoryId}`);
  invalidate(`pros:${territoryId}`);
  invalidate(`communeCounts:${territoryId}`);
}

/** Validation : l'entreprise prend la main sur sa fiche. */
export async function approveClaim(claimId: string, reviewer: Reviewer, note?: string | null): Promise<void> {
  const ctx = await claimWithContext(claimId);
  if (!ctx) throw new ClaimError('Demande introuvable.');
  if (!OPEN_CLAIM_STATUSES.includes(ctx.claim.status as (typeof OPEN_CLAIM_STATUSES)[number])) throw new ClaimError('Cette demande a déjà été traitée.');
  const now = new Date();
  const who = await reviewerLabel(ctx.est.communeId, ctx.commune.name, ctx.territory.name);
  await db.transaction(async (tx) => {
    await tx
      .insert(companyMembers)
      .values({ companyId: ctx.est.companyId, userId: ctx.user.id, role: 'OWNER' })
      .onConflictDoUpdate({ target: [companyMembers.companyId, companyMembers.userId], set: { role: 'OWNER' } });
    await tx
      .update(claims)
      .set({ status: 'APPROVED', reviewerId: reviewerId(reviewer), reviewedAt: now, decisionNote: note ?? null, codeEnc: null, updatedAt: now })
      .where(eq(claims.id, claimId));
    await tx
      .update(claims)
      .set({ status: 'CANCELLED', decisionNote: 'Une autre demande a été validée pour cette fiche.', reviewedAt: now, updatedAt: now })
      .where(and(eq(claims.establishmentId, ctx.est.id), ne(claims.id, claimId), inArray(claims.status, [...OPEN_CLAIM_STATUSES])));
    const creation = ctx.est.status === 'SUSPENDED' && ctx.est.suspendedReason === PENDING_CREATION;
    await tx
      .update(establishments)
      .set({
        status: ctx.est.status === 'PRECREATED' || ctx.est.status === 'TO_COMPLETE' || creation ? 'CLAIMED' : ctx.est.status,
        suspendedReason: creation ? null : ctx.est.suspendedReason,
        lastActivityAt: now,
        publishedAt: ctx.est.publishedAt ?? now,
      })
      .where(eq(establishments.id, ctx.est.id));
    await tx.insert(establishmentRevisions).values({
      establishmentId: ctx.est.id,
      userId: ctx.user.id,
      source: 'PRO',
      summary:
        typeof reviewer === 'string' && reviewer === 'Système'
          ? 'Revendication validée automatiquement'
          : `Revendication validée par ${who.charAt(0).toLowerCase()}${who.slice(1)}`,
    });
  });
  await refreshCompleteness(ctx.est.id);
  await audit({
    actor: reviewer,
    category: 'MODIFICATION',
    action: 'claim.approved',
    summary: `Revendication validée : « ${ctx.est.name} » gérée par ${fullName(ctx.user)}`,
    territoryId: ctx.est.territoryId,
    targetType: 'establishment',
    targetId: ctx.est.id,
    metadata: { claimId },
  });
  await sendEmail({
    ...claimApprovedTemplate({
      to: ctx.user.email,
      firstName: ctx.user.firstName || 'et bienvenue',
      establishmentName: ctx.est.name,
      url: appUrl(`/pro/${ctx.est.id}`),
    }),
    territoryId: ctx.est.territoryId,
  });
  invalidatePortal(ctx.est.territoryId);
}

export async function requestClaimInfo(claimId: string, reviewer: Reviewer, note: string): Promise<void> {
  const ctx = await claimWithContext(claimId);
  if (!ctx || ctx.claim.status !== 'PENDING') throw new ClaimError('Cette demande a déjà été traitée.');
  await db
    .update(claims)
    .set({ status: 'NEEDS_INFO', reviewerId: reviewerId(reviewer), decisionNote: note, updatedAt: new Date() })
    .where(eq(claims.id, claimId));
  await audit({
    actor: reviewer as AuditActor,
    category: 'MODIFICATION',
    action: 'claim.needs_info',
    summary: `Justificatif demandé pour « ${ctx.est.name} »`,
    territoryId: ctx.est.territoryId,
    targetType: 'establishment',
    targetId: ctx.est.id,
    metadata: { claimId },
  });
  await sendEmail({
    ...claimNeedsInfoTemplate({
      to: ctx.user.email,
      firstName: ctx.user.firstName,
      establishmentName: ctx.est.name,
      note,
      url: appUrl(`/pro/revendiquer/suivi/${claimId}`),
    }),
    territoryId: ctx.est.territoryId,
  });
}

export async function rejectClaim(claimId: string, reviewer: Reviewer, note: string): Promise<void> {
  const ctx = await claimWithContext(claimId);
  if (!ctx || !OPEN_CLAIM_STATUSES.includes(ctx.claim.status as (typeof OPEN_CLAIM_STATUSES)[number]))
    throw new ClaimError('Cette demande a déjà été traitée.');
  await db
    .update(claims)
    .set({ status: 'REJECTED', reviewerId: reviewerId(reviewer), reviewedAt: new Date(), decisionNote: note, codeEnc: null, updatedAt: new Date() })
    .where(eq(claims.id, claimId));
  // Une fiche créée par le demandeur et jamais publiée est archivée.
  if (ctx.est.status === 'SUSPENDED' && ctx.est.suspendedReason === PENDING_CREATION)
    await db.update(establishments).set({ status: 'ARCHIVED', archivedAt: new Date() }).where(eq(establishments.id, ctx.est.id));
  await audit({
    actor: reviewer as AuditActor,
    category: 'MODIFICATION',
    action: 'claim.rejected',
    summary: `Revendication refusée pour « ${ctx.est.name} »`,
    territoryId: ctx.est.territoryId,
    targetType: 'establishment',
    targetId: ctx.est.id,
    metadata: { claimId },
  });
  await sendEmail({
    ...claimRejectedTemplate({ to: ctx.user.email, firstName: ctx.user.firstName, establishmentName: ctx.est.name, note }),
    territoryId: ctx.est.territoryId,
  });
}

/** Suivi d'une demande par son auteur. */
export async function claimForUser(claimId: string, userId: string) {
  const ctx = await claimWithContext(claimId);
  if (!ctx || ctx.claim.userId !== userId) return null;
  return ctx;
}
