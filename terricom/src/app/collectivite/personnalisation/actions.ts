'use server';

import { resolveCname, resolve4 } from 'node:dns/promises';
import { and, count, eq, ne } from 'drizzle-orm';
import { revalidatePath } from 'next/cache';
import { z } from 'zod';
import { STAFF_ROLES, type StaffRole } from '@/lib/constants';
import { audit } from '@/server/audit';
import { rateLimit } from '@/server/auth/rate-limit';
import { invalidate } from '@/server/cache';
import { randomToken, sha256 } from '@/server/crypto';
import { db } from '@/server/db';
import { HOME_BLOCKS, roleAssignments, territories, territoryDomains, tokens, users, type HomeBlock, type TerritorySettings } from '@/server/db/schema';
import { env } from '@/server/env';
import { sendEmail } from '@/server/mail/send';
import { securityAlertTemplate, staffInvitationTemplate } from '@/server/mail/templates';
import { MediaError, saveImageUpload } from '@/server/media';
import { loadBoContext, requireTerritoryLevel, type BoContext } from '@/server/services/backoffice';
import { appUrl } from '@/server/urls';
import { fullName } from '@/lib/format';

export type PersoState = { status: 'idle' | 'ok' | 'error'; message?: string };

const hex = z.string().regex(/^#[0-9a-fA-F]{6}$/);

async function adminCtx(): Promise<BoContext> {
  const ctx = await loadBoContext();
  requireTerritoryLevel(ctx);
  if (ctx.access !== 'ADMIN') throw new Error('Réservé aux administrateurs du territoire');
  return ctx;
}

function refreshAll(ctx: BoContext) {
  invalidate(`portal:${ctx.territory.slug}`);
  invalidate(`territory:${ctx.territory.id}`);
  revalidatePath('/', 'layout');
}

export async function saveBrandingAction(_prev: PersoState, form: FormData): Promise<PersoState> {
  const ctx = await adminCtx();
  const parsed = z
    .object({
      colorPrimary: hex,
      colorAccent: hex,
      initials: z.string().trim().min(1).max(4),
      tagline: z.string().trim().max(160),
      heroTitle: z.string().trim().max(200),
      heroSubtitle: z.string().trim().max(400),
      blocks: z.string(),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: `Champ invalide : ${parsed.error.issues[0]?.path.join('.')}` };
  const d = parsed.data;
  let blocks: HomeBlock[];
  try {
    const keys = new Set(HOME_BLOCKS.map((b) => b.key));
    blocks = (JSON.parse(d.blocks) as string[]).filter((b): b is HomeBlock => keys.has(b as HomeBlock));
  } catch {
    return { status: 'error', message: 'Blocs invalides.' };
  }
  const patch: Partial<typeof territories.$inferInsert> = {
    colorPrimary: d.colorPrimary,
    colorAccent: d.colorAccent,
    initials: d.initials.toUpperCase(),
    tagline: d.tagline || ctx.territory.tagline,
    heroTitle: d.heroTitle || null,
    heroSubtitle: d.heroSubtitle || null,
    homeBlocks: blocks,
    updatedAt: new Date(),
  };
  for (const [field, key] of [
    ['logo', 'logoUrl'],
    ['hero', 'heroImageUrl'],
  ] as const) {
    const file = form.get(field);
    if (file instanceof File && file.size > 0) {
      try {
        patch[key] = (await saveImageUpload(file, { ownerType: 'TERRITORY', ownerId: ctx.territory.id, territoryId: ctx.territory.id, uploadedById: ctx.actor.user.id })).url;
      } catch (err) {
        return { status: 'error', message: err instanceof MediaError ? err.message : 'Image refusée.' };
      }
    }
  }
  await db.update(territories).set(patch).where(eq(territories.id, ctx.territory.id));
  await audit({ actor: { user: ctx.actor.user }, category: 'CONFIGURATION', action: 'territory.branding', summary: 'Identité visuelle du portail mise à jour', territoryId: ctx.territory.id, targetType: 'territory', targetId: ctx.territory.id });
  refreshAll(ctx);
  return { status: 'ok', message: 'Portail mis à jour : les changements sont en ligne.' };
}

export async function saveSettingsAction(_prev: PersoState, form: FormData): Promise<PersoState> {
  const ctx = await adminCtx();
  const parsed = z
    .object({
      contactEmail: z.union([z.literal(''), z.string().trim().email('Email de contact invalide')]),
      newsletterName: z.string().trim().max(120),
      claimValidation: z.enum(['MANUAL', 'AUTO']),
      postModeration: z.enum(['PRE', 'POST']),
      directionsProvider: z.enum(['google', 'osm', 'apple']),
      adoptionGoalPct: z.coerce.number().int().min(5).max(100),
      legalPublisher: z.string().trim().max(255),
      dpoEmail: z.union([z.literal(''), z.string().trim().email('Email du DPO invalide')]),
      footerText: z.string().trim().max(400),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  const settings: TerritorySettings = {
    ...((ctx.territory.settings ?? {}) as TerritorySettings),
    newsletterName: d.newsletterName || undefined,
    claimValidation: d.claimValidation,
    postModeration: d.postModeration,
    directionsProvider: d.directionsProvider,
    adoptionGoalPct: d.adoptionGoalPct,
    adoptionGoalLabel: undefined,
    legalPublisher: d.legalPublisher || undefined,
    dpoEmail: d.dpoEmail || undefined,
    footerText: d.footerText || undefined,
    requireMfaForAll: form.get('requireMfaForAll') === 'on',
  };
  await db.update(territories).set({ settings, contactEmail: d.contactEmail || null, updatedAt: new Date() }).where(eq(territories.id, ctx.territory.id));
  await audit({ actor: { user: ctx.actor.user }, category: 'CONFIGURATION', action: 'territory.settings', summary: 'Paramètres du territoire mis à jour', territoryId: ctx.territory.id, targetType: 'territory', targetId: ctx.territory.id });
  refreshAll(ctx);
  return { status: 'ok', message: 'Paramètres enregistrés.' };
}

// ─── Domaine personnalisé ──────────────────────────────────────────────────

const HOST = /^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/;

export async function addDomainAction(_prev: PersoState, form: FormData): Promise<PersoState> {
  const ctx = await adminCtx();
  const host = String(form.get('host') ?? '')
    .trim()
    .toLowerCase()
    .replace(/^https?:\/\//, '')
    .replace(/\/.*$/, '');
  if (!HOST.test(host)) return { status: 'error', message: 'Adresse invalide (ex. commerces.valdeloue.fr).' };
  if (host.endsWith(env.PLATFORM_DOMAIN)) return { status: 'error', message: `Les adresses en ${env.PLATFORM_DOMAIN} sont réservées.` };
  const [taken] = await db.select({ t: territoryDomains.territoryId }).from(territoryDomains).where(eq(territoryDomains.host, host)).limit(1);
  if (taken && taken.t !== ctx.territory.id) return { status: 'error', message: 'Cette adresse est déjà utilisée par un autre portail.' };
  if (!taken) await db.insert(territoryDomains).values({ territoryId: ctx.territory.id, host, isPrimary: true });
  await db.update(territoryDomains).set({ isPrimary: false }).where(and(eq(territoryDomains.territoryId, ctx.territory.id), ne(territoryDomains.host, host)));
  await db.update(territoryDomains).set({ isPrimary: true }).where(eq(territoryDomains.host, host));
  await audit({ actor: { user: ctx.actor.user }, category: 'CONFIGURATION', action: 'domain.add', summary: `Adresse du portail : ${host}`, territoryId: ctx.territory.id });
  revalidatePath('/collectivite/personnalisation');
  return { status: 'ok', message: `Ajoutez chez votre hébergeur DNS un enregistrement CNAME « ${host} » vers « portails.${env.PLATFORM_DOMAIN} », puis cliquez sur Vérifier.` };
}

export async function verifyDomainAction(_prev: PersoState, form: FormData): Promise<PersoState> {
  const ctx = await adminCtx();
  const host = String(form.get('host') ?? '');
  const [d] = await db.select().from(territoryDomains).where(and(eq(territoryDomains.territoryId, ctx.territory.id), eq(territoryDomains.host, host))).limit(1);
  if (!d) return { status: 'error', message: 'Adresse introuvable.' };
  if (!(await rateLimit(`dns:${ctx.territory.id}`, 20, 3600)).ok) return { status: 'error', message: 'Trop de vérifications : réessayez plus tard.' };
  const target = `portails.${env.PLATFORM_DOMAIN}`;
  let ok = false;
  try {
    const cnames = await resolveCname(host);
    ok = cnames.some((c) => c.replace(/\.$/, '').toLowerCase() === target);
  } catch {
    try {
      const [mine, theirs] = await Promise.all([resolve4(host), resolve4(target)]);
      ok = mine.some((ip) => theirs.includes(ip));
    } catch {
      ok = false;
    }
  }
  if (!ok) return { status: 'error', message: `Le DNS de ${host} ne pointe pas encore vers ${target}. La propagation peut prendre quelques heures.` };
  await db.update(territoryDomains).set({ verifiedAt: new Date().toISOString().slice(0, 10) }).where(eq(territoryDomains.id, d.id));
  if (d.isPrimary) await db.update(territories).set({ primaryHost: host }).where(eq(territories.id, ctx.territory.id));
  await audit({ actor: { user: ctx.actor.user }, category: 'CONFIGURATION', action: 'domain.verified', summary: `Adresse ${host} vérifiée : le certificat HTTPS est émis automatiquement`, territoryId: ctx.territory.id });
  refreshAll(ctx);
  return { status: 'ok', message: `${host} est vérifiée. Le certificat HTTPS est émis automatiquement sous quelques minutes.` };
}

// ─── Équipe & rôles ────────────────────────────────────────────────────────

export async function inviteStaffAction(_prev: PersoState, form: FormData): Promise<PersoState> {
  const ctx = await adminCtx();
  const parsed = z
    .object({
      email: z.string().trim().toLowerCase().email('Email invalide'),
      role: z.enum(['TERRITORY_ADMIN', 'TERRITORY_EDITOR', 'COMMUNE_ADMIN', 'COMMUNE_EDITOR']),
      communeId: z.string().optional(),
    })
    .safeParse(Object.fromEntries(form));
  if (!parsed.success) return { status: 'error', message: parsed.error.issues[0]?.message };
  const d = parsed.data;
  const communeRole = d.role.startsWith('COMMUNE_');
  const commune = communeRole ? ctx.communes.find((c) => c.id === d.communeId) : null;
  if (communeRole && !commune) return { status: 'error', message: 'Choisissez la commune de cet agent.' };
  if (!(await rateLimit(`staff-invite:${ctx.actor.user.id}`, 30, 86_400)).ok) return { status: 'error', message: 'Trop d’invitations aujourd’hui.' };
  const token = randomToken(32);
  await db.insert(tokens).values({
    kind: 'INVITE_STAFF',
    tokenHash: sha256(token),
    email: d.email,
    payload: { staffRole: d.role, territoryId: ctx.territory.id, communeId: commune?.id ?? null },
    expiresAt: new Date(Date.now() + 7 * 86_400_000),
    createdById: ctx.actor.user.id,
  });
  await sendEmail({
    ...staffInvitationTemplate({
      to: d.email,
      inviter: fullName(ctx.actor.user),
      scopeName: commune ? `Commune de ${commune.name}` : ctx.territory.name,
      roleLabel: STAFF_ROLES[d.role as StaffRole],
      url: appUrl(`/invitation/${token}`),
    }),
    territoryId: ctx.territory.id,
  });
  await audit({ actor: { user: ctx.actor.user }, category: 'SECURITE', action: 'staff.invited', summary: `Invitation de ${d.email} (${STAFF_ROLES[d.role as StaffRole]}${commune ? ` · ${commune.name}` : ''})`, territoryId: ctx.territory.id });
  revalidatePath('/collectivite/personnalisation');
  return { status: 'ok', message: `Invitation envoyée à ${d.email} (valable 7 jours).` };
}

export async function revokeRoleAction(form: FormData): Promise<void> {
  const ctx = await adminCtx();
  const id = z.string().uuid().parse(form.get('roleId'));
  const [r] = await db.select().from(roleAssignments).where(and(eq(roleAssignments.id, id), eq(roleAssignments.territoryId, ctx.territory.id))).limit(1);
  if (!r) return;
  if (r.role === 'TERRITORY_ADMIN') {
    const [{ n }] = await db.select({ n: count() }).from(roleAssignments).where(and(eq(roleAssignments.territoryId, ctx.territory.id), eq(roleAssignments.role, 'TERRITORY_ADMIN')));
    if (Number(n) <= 1) return; // au moins un administrateur
  }
  await db.delete(roleAssignments).where(eq(roleAssignments.id, r.id));
  const [u] = await db.select().from(users).where(eq(users.id, r.userId)).limit(1);
  await audit({ actor: { user: ctx.actor.user }, category: 'SECURITE', action: 'staff.revoked', summary: `Accès retiré : ${u ? fullName(u) : r.userId} (${STAFF_ROLES[r.role]})`, territoryId: ctx.territory.id, targetType: 'user', targetId: r.userId });
  revalidatePath('/collectivite/personnalisation');
}

export async function mfaReminderAction(form: FormData): Promise<void> {
  const ctx = await adminCtx();
  const userId = z.string().uuid().parse(form.get('userId'));
  const [r] = await db.select({ id: roleAssignments.id }).from(roleAssignments).where(and(eq(roleAssignments.userId, userId), eq(roleAssignments.territoryId, ctx.territory.id))).limit(1);
  const [u] = r ? await db.select().from(users).where(eq(users.id, userId)).limit(1) : [];
  if (!u || u.mfaEnabled) return;
  await sendEmail({
    ...securityAlertTemplate({
      to: u.email,
      title: 'Activez la double authentification',
      detail: `${fullName(ctx.actor.user)} vous demande d'activer la double authentification sur votre compte ${ctx.territory.name}. Deux minutes suffisent : ${appUrl('/compte/securite')}`,
    }),
    territoryId: ctx.territory.id,
  });
  revalidatePath('/collectivite/personnalisation');
}
