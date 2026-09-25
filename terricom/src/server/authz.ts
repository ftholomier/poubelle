import { eq } from 'drizzle-orm';
import { notFound, redirect } from 'next/navigation';
import { cache } from 'react';
import { getSession, type SessionRow, type SessionUser } from './auth/session';
import { db } from './db';
import { companyMembers, roleAssignments } from './db/schema';
import type { StaffRole } from '@/lib/constants';

export type RoleRow = { role: StaffRole; territoryId: string | null; communeId: string | null };
export type Access = 'ADMIN' | 'EDITOR';

export type Actor = {
  user: SessionUser;
  session: SessionRow;
  roles: RoleRow[];
  memberships: { companyId: string; role: 'OWNER' | 'EDITOR' }[];
  isPlatformAdmin: boolean;
  isPlatformStaff: boolean;
  impersonation: { territoryId: string; expiresAt: Date; ticket: string | null } | null;
};

export class AccessDeniedError extends Error {
  constructor(message = 'Accès refusé') {
    super(message);
    this.name = 'AccessDeniedError';
  }
}

/** Acteur authentifié de la requête (utilisateur + rôles + entreprises), ou null. */
export const getActor = cache(async (): Promise<Actor | null> => {
  const s = await getSession();
  if (!s) return null;
  const [roles, memberships] = await Promise.all([
    db
      .select({ role: roleAssignments.role, territoryId: roleAssignments.territoryId, communeId: roleAssignments.communeId })
      .from(roleAssignments)
      .where(eq(roleAssignments.userId, s.user.id)),
    db
      .select({ companyId: companyMembers.companyId, role: companyMembers.role })
      .from(companyMembers)
      .where(eq(companyMembers.userId, s.user.id)),
  ]);
  const imp =
    s.session.impersonationTerritoryId && s.session.impersonationExpiresAt && s.session.impersonationExpiresAt > new Date()
      ? {
          territoryId: s.session.impersonationTerritoryId,
          expiresAt: s.session.impersonationExpiresAt,
          ticket: s.session.impersonationTicket,
        }
      : null;
  return {
    user: s.user,
    session: s.session,
    roles: roles as RoleRow[],
    memberships,
    isPlatformAdmin: roles.some((r) => r.role === 'PLATFORM_ADMIN'),
    isPlatformStaff: roles.some((r) => r.role.startsWith('PLATFORM_')),
    impersonation: imp,
  };
});

/** Exige une session valide et une MFA vérifiée si activée ; redirige vers la connexion sinon. */
export async function requireActor(next?: string): Promise<Actor> {
  const actor = await getActor();
  const suffix = next ? `?next=${encodeURIComponent(next)}` : '';
  if (!actor) redirect(`/connexion${suffix}`);
  if (actor.user.mfaEnabled && !actor.session.mfaVerified) redirect(`/connexion/mfa${suffix}`);
  return actor;
}

/** Les comptes de l'exploitant doivent obligatoirement activer la double authentification. */
export async function requirePlatformStaff(roles?: StaffRole[], next = '/console'): Promise<Actor> {
  const actor = await requireActor(next);
  if (!actor.isPlatformStaff) notFound();
  if (roles && !actor.isPlatformAdmin && !actor.roles.some((r) => roles.includes(r.role))) notFound();
  if (!actor.user.mfaEnabled) redirect('/compte/securite?mfa=obligatoire');
  return actor;
}

export function hasRole(actor: Actor, role: StaffRole): boolean {
  return actor.roles.some((r) => r.role === role);
}

/** Niveau d'accès d'un agent à un territoire entier. */
export function territoryAccess(actor: Actor | null, territoryId: string): Access | null {
  if (!actor) return null;
  if (actor.isPlatformAdmin) return 'ADMIN';
  if (actor.impersonation?.territoryId === territoryId) return 'ADMIN';
  if (actor.roles.some((r) => r.role === 'TERRITORY_ADMIN' && r.territoryId === territoryId)) return 'ADMIN';
  if (actor.roles.some((r) => r.role === 'TERRITORY_EDITOR' && r.territoryId === territoryId)) return 'EDITOR';
  return null;
}

/** Niveau d'accès d'un agent à une commune (les agents territoriaux couvrent toutes leurs communes). */
export function communeAccess(actor: Actor | null, territoryId: string, communeId: string | null): Access | null {
  const t = territoryAccess(actor, territoryId);
  if (t) return t;
  if (!actor || !communeId) return null;
  if (actor.roles.some((r) => r.role === 'COMMUNE_ADMIN' && r.communeId === communeId)) return 'ADMIN';
  if (actor.roles.some((r) => r.role === 'COMMUNE_EDITOR' && r.communeId === communeId)) return 'EDITOR';
  return null;
}

/** Communes accessibles au titre d'un rôle communal (hors accès territorial). */
export function communeScopedIds(actor: Actor, territoryId: string): string[] {
  return actor.roles
    .filter((r) => (r.role === 'COMMUNE_ADMIN' || r.role === 'COMMUNE_EDITOR') && r.territoryId === territoryId && r.communeId)
    .map((r) => r.communeId!) as string[];
}

/** Territoires où l'acteur a un rôle d'agent (territorial ou communal). */
export function staffTerritoryIds(actor: Actor): string[] {
  const ids = new Set<string>();
  for (const r of actor.roles) if (r.territoryId && !r.role.startsWith('PLATFORM_')) ids.add(r.territoryId);
  if (actor.impersonation) ids.add(actor.impersonation.territoryId);
  return [...ids];
}

export function isCompanyMember(actor: Actor | null, companyId: string, role?: 'OWNER'): boolean {
  if (!actor) return false;
  return actor.memberships.some((m) => m.companyId === companyId && (!role || m.role === role));
}

/** Un établissement est gérable par ses membres ou par les agents de sa commune / son territoire. */
export function canManageEstablishment(
  actor: Actor | null,
  est: { companyId: string; territoryId: string; communeId: string },
): 'OWNER' | 'MEMBER' | 'STAFF' | null {
  if (!actor) return null;
  if (isCompanyMember(actor, est.companyId, 'OWNER')) return 'OWNER';
  if (isCompanyMember(actor, est.companyId)) return 'MEMBER';
  if (communeAccess(actor, est.territoryId, est.communeId)) return 'STAFF';
  return null;
}

export function assert(condition: unknown, message = 'Accès refusé'): asserts condition {
  if (!condition) throw new AccessDeniedError(message);
}
