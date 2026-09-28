import { and, count, eq, inArray, isNull, ne, or, type SQL } from 'drizzle-orm';
import { cookies } from 'next/headers';
import { notFound, redirect } from 'next/navigation';
import { cache } from 'react';
import { STAFF_ROLES, type StaffRole } from '@/lib/constants';
import { requireActor, staffTerritoryIds, type Actor } from '../authz';
import { db } from '../db';
import { campaigns, claims, communes, establishments, sireneChanges, type TerritorySettings } from '../db/schema';
import { getTerritoriesByIds, getTerritoryCommunes, type Commune, type Territory } from './territories';

/**
 * Contexte du back-office collectivité : territoire courant, périmètre (territoire entier
 * ou commune) et niveau d'accès de l'agent. Tout accès aux données passe par ce périmètre.
 */

export const BO_SCOPE_COOKIE = 'tc_bo_scope';

export type BoScopeOption = { key: string; label: string; sub: string; level: 'TERRITORY' | 'COMMUNE' };

export type BoContext = {
  actor: Actor;
  territory: Territory;
  settings: TerritorySettings;
  level: 'TERRITORY' | 'COMMUNE';
  /** null : toutes les communes du territoire. */
  communeIds: string[] | null;
  commune: Commune | null;
  communes: Commune[];
  access: 'ADMIN' | 'EDITOR';
  roleLabel: string;
  scopes: BoScopeOption[];
  scopeKey: string;
  scopeName: string;
};

/** « Communauté de communes du Val de Loue » → « CC du Val de Loue ». */
export function shortLegalName(legalName: string): string {
  return legalName
    .replace(/^Communauté de communes\s+/i, 'CC ')
    .replace(/^Communauté d['’]agglomération\s+/i, 'CA ')
    .replace(/^Communauté urbaine\s+/i, 'CU ');
}

/** Rôles pour lesquels la double authentification est exigée quoi qu'il arrive. */
const MFA_REQUIRED: StaffRole[] = ['TERRITORY_ADMIN', 'PLATFORM_ADMIN', 'PLATFORM_SUPPORT', 'PLATFORM_SALES'];

export const loadBoContext = cache(async (): Promise<BoContext> => {
  const actor = await requireActor('/collectivite');
  const territoryIds = staffTerritoryIds(actor);
  if (!territoryIds.length) {
    if (actor.isPlatformStaff) redirect('/console');
    notFound();
  }
  const territoriesList = await getTerritoriesByIds(territoryIds);
  const scopes: BoScopeOption[] = [];
  for (const t of territoriesList) {
    const territoryRole =
      actor.impersonation?.territoryId === t.id ||
      actor.roles.some((r) => r.territoryId === t.id && (r.role === 'TERRITORY_ADMIN' || r.role === 'TERRITORY_EDITOR'));
    if (territoryRole) scopes.push({ key: `t:${t.id}`, label: t.name, sub: 'Territoire', level: 'TERRITORY' });
  }
  const communeRoles = actor.roles.filter((r) => (r.role === 'COMMUNE_ADMIN' || r.role === 'COMMUNE_EDITOR') && r.communeId);
  const communeRows = communeRoles.length
    ? await db
        .select()
        .from(communes)
        .where(
          inArray(
            communes.id,
            communeRoles.map((r) => r.communeId!),
          ),
        )
    : [];
  for (const c of communeRows)
    scopes.push({ key: `c:${c.id}`, label: `Mairie ${/^[aeiouyhâéèêîôû]/i.test(c.name) ? 'd’' : 'de '}${c.name}`, sub: 'Commune', level: 'COMMUNE' });
  if (!scopes.length) notFound();

  const jar = await cookies();
  const wanted = jar.get(BO_SCOPE_COOKIE)?.value;
  const scope = scopes.find((s) => s.key === wanted) ?? scopes[0];
  let territory: Territory;
  let commune: Commune | null = null;
  if (scope.level === 'TERRITORY') {
    territory = territoriesList.find((t) => `t:${t.id}` === scope.key)!;
  } else {
    commune = communeRows.find((c) => `c:${c.id}` === scope.key)!;
    const role = communeRoles.find((r) => r.communeId === commune!.id)!;
    territory = territoriesList.find((t) => t.id === role.territoryId) ?? (await getTerritoriesByIds([role.territoryId!]))[0];
  }
  if (!territory) notFound();

  const territoryRoles = actor.roles.filter((r) => r.territoryId === territory.id);
  const roleForScope =
    scope.level === 'TERRITORY'
      ? (territoryRoles.find((r) => r.role === 'TERRITORY_ADMIN') ?? territoryRoles.find((r) => r.role === 'TERRITORY_EDITOR'))
      : territoryRoles.find((r) => r.communeId === commune?.id);
  const impersonating = actor.impersonation?.territoryId === territory.id && !roleForScope;
  const role: StaffRole = impersonating ? 'PLATFORM_SUPPORT' : (roleForScope?.role ?? 'COMMUNE_EDITOR');
  const access: 'ADMIN' | 'EDITOR' = impersonating || role === 'TERRITORY_ADMIN' || role === 'COMMUNE_ADMIN' ? 'ADMIN' : 'EDITOR';
  const settings = (territory.settings ?? {}) as TerritorySettings;

  // Double authentification : obligatoire pour les administrateurs du territoire, et pour toute
  // l'équipe si la collectivité l'a décidé.
  const mfaRequired = MFA_REQUIRED.includes(role) || actor.isPlatformStaff || settings.requireMfaForAll === true;
  if (mfaRequired && !actor.user.mfaEnabled) redirect('/compte/securite?mfa=obligatoire&next=/collectivite');

  const allCommunes = await getTerritoryCommunes(territory.id);
  return {
    actor,
    territory,
    settings,
    level: scope.level,
    communeIds: commune ? [commune.id] : null,
    commune,
    communes: commune ? allCommunes.filter((c) => c.id === commune!.id) : allCommunes,
    access,
    roleLabel: impersonating ? 'Support terricom (accès temporaire)' : STAFF_ROLES[role],
    scopes,
    scopeKey: scope.key,
    scopeName: scope.level === 'TERRITORY' ? shortLegalName(territory.legalName) : scope.label,
  };
});

/** Filtre SQL des établissements du périmètre courant. */
export function estScope(ctx: BoContext): SQL {
  return and(eq(establishments.territoryId, ctx.territory.id), ctx.communeIds ? inArray(establishments.communeId, ctx.communeIds) : undefined)!;
}

/**
 * Campagnes visibles : toutes au niveau territorial ; au niveau communal, celles de la commune
 * et celles du territoire (ces dernières en consultation seule).
 */
export function campaignScope(ctx: BoContext): SQL {
  return and(
    eq(campaigns.territoryId, ctx.territory.id),
    ctx.communeIds ? or(isNull(campaigns.communeId), inArray(campaigns.communeId, ctx.communeIds)) : undefined,
  )!;
}

/** Une campagne se modifie au niveau territorial, ou par la commune qui la porte. */
export function canEditCampaign(ctx: BoContext, c: { communeId: string | null }): boolean {
  return ctx.level === 'TERRITORY' || (c.communeId !== null && (ctx.communeIds ?? []).includes(c.communeId));
}

/** Réservé aux administrateurs (territoire ou commune). */
export function requireBoAdmin(ctx: BoContext) {
  if (ctx.access !== 'ADMIN') notFound();
}

/** Réservé au niveau territorial (personnalisation, équipe, abonnements). */
export function requireTerritoryLevel(ctx: BoContext) {
  if (ctx.level !== 'TERRITORY') notFound();
}

/** Compteurs de la navigation (établissements du périmètre, revendications en attente). */
export const boCounts = cache(async (ctx: BoContext) => {
  const [[ests], [pending], [sirene]] = await Promise.all([
    db
      .select({ n: count() })
      .from(establishments)
      .where(and(estScope(ctx), ne(establishments.status, 'ARCHIVED'))),
    db
      .select({ n: count() })
      .from(claims)
      .innerJoin(establishments, eq(establishments.id, claims.establishmentId))
      .where(and(estScope(ctx), inArray(claims.status, ['PENDING', 'NEEDS_INFO']))),
    db
      .select({ n: count() })
      .from(sireneChanges)
      .where(
        and(
          eq(sireneChanges.territoryId, ctx.territory.id),
          eq(sireneChanges.status, 'PENDING'),
          ctx.communeIds ? inArray(sireneChanges.communeId, ctx.communeIds) : undefined,
        ),
      ),
  ]);
  return { establishments: Number(ests?.n ?? 0), pendingClaims: Number(pending?.n ?? 0), pendingSirene: Number(sirene?.n ?? 0) };
});
