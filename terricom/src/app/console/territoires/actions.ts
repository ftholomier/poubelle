'use server';

import { eq } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { redirect } from 'next/navigation';
import { z } from 'zod';
import type { ActionState } from '@/app/pro/[est]/actions';
import { MODULE_ORDER, MODULES, TERRITORY_STATUS, type ModuleKey, type StaffRole, type TerritoryStatus } from '@/lib/constants';
import { fullName } from '@/lib/format';
import { audit } from '@/server/audit';
import { startImpersonation } from '@/server/auth/session';
import { requirePlatformStaff, type Actor } from '@/server/authz';
import { invalidate } from '@/server/cache';
import { db } from '@/server/db';
import { supportTickets, territories, type TerritorySettings } from '@/server/db/schema';
import { sendEmail } from '@/server/mail/send';
import { staffInvitationTemplate } from '@/server/mail/templates';
import { attachCommunes, createTerritory, detachCommune, inviteTerritoryAdmin, setTerritoryModule } from '@/server/services/console-territories';
import { appUrl } from '@/server/urls';

async function consoleActor(roles?: StaffRole[]): Promise<Actor | null> {
  const actor = await requirePlatformStaff();
  if (roles && !actor.isPlatformAdmin && !actor.roles.some((r) => roles.includes(r.role))) return null;
  return actor;
}

async function territoryOf(id: string) {
  const [t] = await db.select().from(territories).where(eq(territories.id, id)).limit(1);
  return t ?? null;
}

function refresh(t: { id: string; slug: string }) {
  invalidate(`territory:${t.id}`);
  invalidate(`portal:${t.slug}`);
  revalidatePath('/console/territoires');
}

export async function toggleModuleAction(form: FormData): Promise<void> {
  const actor = await consoleActor(['PLATFORM_ADMIN']);
  if (!actor) return;
  const d = z
    .object({ territoryId: z.string().uuid(), module: z.enum(MODULE_ORDER as [ModuleKey, ...ModuleKey[]]), enabled: z.enum(['1', '0']) })
    .parse(Object.fromEntries(form));
  const t = await territoryOf(d.territoryId);
  if (!t) return;
  const enabled = d.enabled === '1';
  await setTerritoryModule(t.id, d.module, enabled);
  await audit({
    actor: { user: actor.user },
    category: 'CONFIGURATION',
    action: enabled ? 'module.enabled' : 'module.disabled',
    summary: `Module « ${MODULES[d.module].label} » ${enabled ? 'activé' : 'désactivé'} pour ${t.name}`,
    territoryId: t.id,
    targetType: 'territory',
    targetId: t.id,
  });
  refresh(t);
}

export async function updateQuotasAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await consoleActor(['PLATFORM_ADMIN']);
  if (!actor) return { status: 'error', message: 'Réservé aux super administrateurs.' };
  const parsed = z
    .object({
      territoryId: z.string().uuid(),
      quotaEstablishments: z.coerce.number().int().min(10).max(100_000),
      quotaEmailsMonthly: z.coerce.number().int().min(0).max(5_000_000),
      quotaAiCreditsMonthly: z.coerce.number().int().min(0).max(1_000_000),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: 'Valeurs de quotas invalides.' };
  const d = parsed.data;
  const t = await territoryOf(d.territoryId);
  if (!t) return { status: 'error', message: 'Territoire introuvable.' };
  await db
    .update(territories)
    .set({
      quotaEstablishments: d.quotaEstablishments,
      quotaEmailsMonthly: d.quotaEmailsMonthly,
      quotaAiCreditsMonthly: d.quotaAiCreditsMonthly,
      updatedAt: new Date(),
    })
    .where(eq(territories.id, t.id));
  await audit({
    actor: { user: actor.user },
    category: 'CONFIGURATION',
    action: 'territory.quotas',
    summary: `Quotas de ${t.name} : ${d.quotaEstablishments} fiches, ${d.quotaEmailsMonthly} emails/mois, ${d.quotaAiCreditsMonthly} crédits IA/mois`,
    territoryId: t.id,
    targetType: 'territory',
    targetId: t.id,
  });
  refresh(t);
  return { status: 'ok', message: 'Quotas mis à jour.' };
}

export async function setTerritoryStatusAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await consoleActor(['PLATFORM_ADMIN']);
  if (!actor) return { status: 'error', message: 'Réservé aux super administrateurs.' };
  const parsed = z
    .object({
      territoryId: z.string().uuid(),
      status: z.enum(['ONBOARDING', 'ACTIVE', 'SUSPENDED', 'CHURNED']),
      isPilot: z.string().optional(),
      whiteLabel: z.string().optional(),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: 'Statut invalide.' };
  const d = parsed.data;
  const t = await territoryOf(d.territoryId);
  if (!t) return { status: 'error', message: 'Territoire introuvable.' };
  const isPilot = d.isPilot === 'on';
  const settings = (t.settings ?? {}) as TerritorySettings;
  const whiteLabel = d.whiteLabel === 'on';
  if (t.status === d.status && t.isPilot === isPilot && Boolean(settings.whiteLabel) === whiteLabel) return { status: 'ok', message: 'Aucun changement.' };
  await db
    .update(territories)
    .set({ status: d.status, isPilot, settings: { ...settings, whiteLabel }, updatedAt: new Date() })
    .where(eq(territories.id, t.id));
  await audit({
    actor: { user: actor.user },
    category: 'CONFIGURATION',
    action: 'territory.status',
    summary: `${t.name} : statut « ${TERRITORY_STATUS[d.status as TerritoryStatus].label} »${isPilot ? ' (pilote)' : ''}${whiteLabel ? ', marque blanche' : ''}`,
    territoryId: t.id,
    targetType: 'territory',
    targetId: t.id,
  });
  refresh(t);
  return { status: 'ok', message: d.status === 'SUSPENDED' ? 'Territoire suspendu : le portail affiche une page de maintenance.' : 'Statut mis à jour.' };
}

/** Ouvre un accès support temporaire (30 min) au back-office d'un territoire, justifié et journalisé. */
export async function startSupportAccessAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await consoleActor(['PLATFORM_ADMIN', 'PLATFORM_SUPPORT']);
  if (!actor) return { status: 'error', message: 'Réservé au support et aux super administrateurs.' };
  const parsed = z
    .object({ territoryId: z.string().uuid(), ticketId: z.string().optional(), reason: z.string().trim().max(300).optional() })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: 'Demande invalide.' };
  const d = parsed.data;
  const t = await territoryOf(d.territoryId);
  if (!t) return { status: 'error', message: 'Territoire introuvable.' };
  let ticketLabel: string | null = null;
  if (d.ticketId) {
    const [tk] = await db.select().from(supportTickets).where(eq(supportTickets.id, d.ticketId)).limit(1);
    if (!tk || tk.territoryId !== t.id) return { status: 'error', message: 'Ticket introuvable pour ce territoire.' };
    ticketLabel = `#${tk.number}`;
  }
  if (!ticketLabel && !d.reason) return { status: 'error', message: 'Indiquez le ticket concerné ou le motif de l’accès.' };
  await startImpersonation(t.id, ticketLabel ?? d.reason!.slice(0, 64), 30);
  await audit({
    actor: { user: actor.user },
    category: 'SUPPORT',
    action: 'support.impersonation_start',
    summary: `Accès support ouvert sur ${t.name} (${ticketLabel ? `ticket ${ticketLabel}` : `motif : ${d.reason}`}, 30 min)`,
    territoryId: t.id,
    targetType: 'territory',
    targetId: t.id,
    metadata: { ticket: ticketLabel, reason: d.reason ?? null },
  });
  redirect(`/console/territoires?t=${t.id}`);
}

const newTerritorySchema = z.object({
  name: z.string().trim().min(2, 'Nom requis').max(160),
  legalName: z.string().trim().min(2, 'Raison sociale requise').max(255),
  kind: z.enum(['CC', 'CA', 'CU', 'METROPOLE', 'COMMUNE', 'PETR', 'OFFICE', 'AUTRE']),
  slug: z
    .string()
    .trim()
    .toLowerCase()
    .regex(/^[a-z0-9-]{0,64}$/, 'Identifiant : lettres minuscules, chiffres et tirets')
    .optional(),
  siren: z
    .string()
    .trim()
    .regex(/^(\d{9})?$/, 'SIREN : 9 chiffres')
    .optional(),
  inseeCodes: z.string().optional(),
  departmentCode: z
    .string()
    .trim()
    .regex(/^(\d{2,3}|2A|2B)?$/i, 'Département invalide')
    .optional(),
  population: z.coerce
    .number()
    .int()
    .min(0)
    .max(10_000_000)
    .optional()
    .or(z.literal('').transform(() => undefined)),
  contactEmail: z.string().trim().toLowerCase().email('Email de contact invalide').optional().or(z.literal('')),
  adminEmail: z.string().trim().toLowerCase().email('Email de l’administrateur invalide').optional().or(z.literal('')),
  status: z.enum(['ONBOARDING', 'ACTIVE']),
  isPilot: z.string().optional(),
  licence: z.coerce.number().min(0).max(1_000_000),
  setup: z.coerce.number().min(0).max(1_000_000),
  startsAt: z.string().regex(/^\d{4}-\d{2}-\d{2}$/, 'Date de début invalide'),
  colorPrimary: z.string().regex(/^#[0-9a-fA-F]{6}$/),
  colorAccent: z.string().regex(/^#[0-9a-fA-F]{6}$/),
  quotaEstablishments: z.coerce.number().int().min(10).max(100_000),
  quotaEmailsMonthly: z.coerce.number().int().min(0).max(5_000_000),
  quotaAiCreditsMonthly: z.coerce.number().int().min(0).max(1_000_000),
  dealId: z.string().uuid().optional().or(z.literal('')),
});

export async function createTerritoryAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await consoleActor(['PLATFORM_ADMIN']);
  if (!actor) return { status: 'error', message: 'Réservé aux super administrateurs.' };
  const raw = Object.fromEntries([...form.entries()].filter(([k]) => k !== 'modules'));
  const parsed = newTerritorySchema.safeParse(raw);
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message ?? 'Formulaire incomplet.' };
  const d = parsed.data;
  const inseeCodes = [...new Set((d.inseeCodes ?? '').split(/[\s,;]+/).filter((c) => /^(\d{5}|2[AB]\d{3})$/i.test(c)))];
  if (!d.siren && inseeCodes.length === 0)
    return { status: 'error', message: 'Indiquez le SIREN de l’intercommunalité ou la liste des codes INSEE des communes.' };
  const modules = form.getAll('modules').filter((m): m is ModuleKey => MODULE_ORDER.includes(m as ModuleKey));
  let result;
  try {
    result = await createTerritory({
      name: d.name,
      legalName: d.legalName,
      kind: d.kind,
      slug: d.slug || undefined,
      siren: d.siren || null,
      departmentCode: d.departmentCode || null,
      population: d.population ?? null,
      contactEmail: d.contactEmail || null,
      status: d.status,
      isPilot: d.isPilot === 'on',
      inseeCodes,
      licenceCents: Math.round(d.licence * 100),
      setupCents: Math.round(d.setup * 100),
      startsAt: d.startsAt,
      modules: modules.length ? modules : ['PORTAL', 'MAP'],
      colorPrimary: d.colorPrimary,
      colorAccent: d.colorAccent,
      quotaEstablishments: d.quotaEstablishments,
      quotaEmailsMonthly: d.quotaEmailsMonthly,
      quotaAiCreditsMonthly: d.quotaAiCreditsMonthly,
      dealId: d.dealId || null,
    });
  } catch (e) {
    return { status: 'error', message: `Création impossible : ${e instanceof Error ? e.message : 'erreur inconnue'}` };
  }
  if (d.adminEmail) {
    const token = await inviteTerritoryAdmin(result.id, d.adminEmail, actor.user.id);
    await sendEmail({
      ...staffInvitationTemplate({
        to: d.adminEmail,
        inviter: fullName(actor.user),
        scopeName: d.name,
        roleLabel: 'Admin territoriale',
        url: appUrl(`/invitation/${token}`),
      }),
      territoryId: result.id,
    });
  }
  await audit({
    actor: { user: actor.user },
    category: 'CONFIGURATION',
    action: 'territory.created',
    summary: `Territoire « ${d.name} » créé : ${result.attached} communes rattachées${d.adminEmail ? `, administrateur invité (${d.adminEmail})` : ''}`,
    territoryId: result.id,
    targetType: 'territory',
    targetId: result.id,
    metadata: { skipped: result.skipped },
  });
  revalidatePath('/console', 'layout');
  const qs = new URLSearchParams({ t: result.id, cree: String(result.attached) });
  if (result.skipped.length)
    qs.set(
      'ignorees',
      result.skipped
        .map((s) => s.reason)
        .join(' · ')
        .slice(0, 600),
    );
  redirect(`/console/territoires?${qs}`);
}

/** Rattache des communes (codes INSEE séparés par des virgules ou des espaces), avec transfert explicite. */
export async function attachCommunesAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await consoleActor(['PLATFORM_ADMIN']);
  if (!actor) return { status: 'error', message: 'Réservé aux administrateurs de la plateforme.' };
  const parsed = z.object({ territoryId: z.string().uuid(), codes: z.string().trim().min(5).max(2000) }).safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: 'Indiquez au moins un code INSEE (5 caractères).' };
  const t = await territoryOf(parsed.data.territoryId);
  if (!t) return { status: 'error', message: 'Territoire introuvable.' };
  const codes = [...new Set(parsed.data.codes.split(/[\s,;]+/).map((c) => c.trim().toUpperCase()))]
    .filter((c) => /^[0-9][0-9AB][0-9]{3}$/.test(c))
    .slice(0, 200);
  if (!codes.length) return { status: 'error', message: 'Codes INSEE invalides (ex. 25424).' };
  const r = await attachCommunes(t.id, codes, form.get('transfer') === 'on');
  if (r.attached.length || r.transferred.length)
    await audit({
      actor: { user: actor.user },
      category: 'CONFIGURATION',
      action: 'territory.communes.attach',
      summary: `Communes rattachées à ${t.name} : ${[...r.attached, ...r.transferred.map((x) => `${x}, transférée`)].join(', ')}`,
      territoryId: t.id,
      targetType: 'territory',
      targetId: t.id,
    });
  refresh(t);
  const parts = [
    r.attached.length ? `${r.attached.length} rattachée(s)` : '',
    r.transferred.length ? `${r.transferred.length} transférée(s)` : '',
    r.skipped.length ? `ignorées : ${r.skipped.map((x) => x.reason).join(' ; ')}` : '',
  ].filter(Boolean);
  return { status: r.attached.length || r.transferred.length ? 'ok' : 'error', message: parts.join(' · ') || 'Aucune commune rattachée.' };
}

/** Détache une commune (sans fiche) ou la transfère avec ses contenus vers un autre territoire. */
export async function detachCommuneAction(_prev: ActionState, form: FormData): Promise<ActionState> {
  const actor = await consoleActor(['PLATFORM_ADMIN']);
  if (!actor) return { status: 'error', message: 'Réservé aux administrateurs de la plateforme.' };
  const parsed = z
    .object({ territoryId: z.string().uuid(), communeId: z.string().uuid(), to: z.union([z.literal(''), z.string().uuid()]) })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: 'Requête invalide.' };
  const t = await territoryOf(parsed.data.territoryId);
  if (!t) return { status: 'error', message: 'Territoire introuvable.' };
  const r = await detachCommune(t.id, parsed.data.communeId, parsed.data.to || null);
  if (r.ok) {
    await audit({
      actor: { user: actor.user },
      category: 'CONFIGURATION',
      action: parsed.data.to ? 'territory.communes.transfer' : 'territory.communes.detach',
      summary: `${t.name} : ${r.message}`,
      territoryId: t.id,
      targetType: 'territory',
      targetId: t.id,
    });
    refresh(t);
    if (parsed.data.to) {
      const dest = await territoryOf(parsed.data.to);
      if (dest) refresh(dest);
    }
  }
  return { status: r.ok ? 'ok' : 'error', message: r.message };
}
