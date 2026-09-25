import { and, asc, eq, gt, isNull } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { addDomainAction, inviteStaffAction, mfaReminderAction, revokeRoleAction, saveSettingsAction, verifyDomainAction } from './actions';
import { BrandingEditor } from '@/components/bo/BrandingEditor';
import { ActionForm } from '@/components/pro/ActionForm';
import { Photo } from '@/components/ui/Photo';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { MODULES, STAFF_ROLES } from '@/lib/constants';
import { fullName, relativeTime } from '@/lib/format';
import { sized } from '@/lib/images';
import { db } from '@/server/db';
import { communes, HOME_BLOCKS, roleAssignments, territoryDomains, territoryModules, tokens, users } from '@/server/db/schema';
import { env } from '@/server/env';
import { loadBoContext, requireTerritoryLevel } from '@/server/services/backoffice';

export const metadata: Metadata = { title: 'Personnalisation & rôles' };

export default async function PersonalizationPage() {
  const ctx = await loadBoContext();
  requireTerritoryLevel(ctx);
  const t = ctx.territory;
  const admin = ctx.access === 'ADMIN';
  const [team, pending, domains, modules] = await Promise.all([
    db
      .select({
        roleId: roleAssignments.id,
        role: roleAssignments.role,
        userId: users.id,
        firstName: users.firstName,
        lastName: users.lastName,
        email: users.email,
        avatarUrl: users.avatarUrl,
        mfa: users.mfaEnabled,
        lastLoginAt: users.lastLoginAt,
        communeName: communes.name,
      })
      .from(roleAssignments)
      .innerJoin(users, eq(users.id, roleAssignments.userId))
      .leftJoin(communes, eq(communes.id, roleAssignments.communeId))
      .where(eq(roleAssignments.territoryId, t.id))
      .orderBy(asc(roleAssignments.role), asc(users.lastName)),
    db
      .select({ email: tokens.email, payload: tokens.payload, expiresAt: tokens.expiresAt })
      .from(tokens)
      .where(and(eq(tokens.kind, 'INVITE_STAFF'), isNull(tokens.usedAt), gt(tokens.expiresAt, new Date()))),
    db.select().from(territoryDomains).where(eq(territoryDomains.territoryId, t.id)),
    db.select().from(territoryModules).where(eq(territoryModules.territoryId, t.id)),
  ]);
  const invites = pending.filter((p) => (p.payload as { territoryId?: string }).territoryId === t.id);
  const primary = domains.find((d) => d.isPrimary) ?? domains[0];
  const host = t.primaryHost ?? primary?.host ?? `${env.PLATFORM_DOMAIN}/${t.slug}`;
  const s = ctx.settings;

  return (
    <div className="app-content">
      {!admin ? <div className="alert alert-info">Lecture seule : seuls les administrateurs du territoire peuvent modifier ces réglages.</div> : null}
      <BrandingEditor
        initial={{
          colorPrimary: t.colorPrimary,
          colorAccent: t.colorAccent,
          initials: t.initials,
          name: t.name,
          tagline: t.tagline,
          heroTitle: t.heroTitle ?? '',
          heroSubtitle: t.heroSubtitle ?? '',
          heroImageUrl: sized(t.heroImageUrl, 1200),
          logoUrl: t.logoUrl,
          blocks: t.homeBlocks,
        }}
        allBlocks={HOME_BLOCKS}
        host={host}
      >
        <section className="bo-card" style={{ padding: 0, borderRadius: 20, overflow: 'hidden' }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', padding: '16px 18px', alignItems: 'center' }}>
            <b>Équipe &amp; rôles</b>
            {admin ? (
              <details style={{ position: 'relative' }}>
                <summary style={{ fontSize: 13, fontWeight: 700, color: 'var(--green)', cursor: 'pointer', listStyle: 'none' }}>+ Inviter</summary>
                <div className="menu-pop" style={{ padding: 14, width: 320 }}>
                  <ActionForm action={inviteStaffAction} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                    <input name="email" type="email" className="input" placeholder="prenom.nom@mairie.fr" required aria-label="Email" />
                    <select name="role" className="input" aria-label="Rôle" defaultValue="TERRITORY_EDITOR">
                      <option value="TERRITORY_ADMIN">{STAFF_ROLES.TERRITORY_ADMIN}</option>
                      <option value="TERRITORY_EDITOR">{STAFF_ROLES.TERRITORY_EDITOR}</option>
                      <option value="COMMUNE_ADMIN">{STAFF_ROLES.COMMUNE_ADMIN}</option>
                      <option value="COMMUNE_EDITOR">{STAFF_ROLES.COMMUNE_EDITOR}</option>
                    </select>
                    <select name="communeId" className="input" aria-label="Commune (rôles communaux)" defaultValue="">
                      <option value="">Commune (rôles communaux)…</option>
                      {ctx.communes.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.name}
                        </option>
                      ))}
                    </select>
                    <SubmitButton className="btn btn-brand btn-sm" pendingLabel="Envoi…">
                      Envoyer l’invitation
                    </SubmitButton>
                  </ActionForm>
                </div>
              </details>
            ) : null}
          </div>
          {team.map((u) => (
            <div
              key={u.roleId}
              style={{
                display: 'grid',
                gridTemplateColumns: '36px 1fr 1.2fr auto',
                gap: 12,
                alignItems: 'center',
                padding: '10px 18px',
                borderTop: '1px solid var(--line-2)',
                fontSize: 13,
              }}
            >
              <span style={{ width: 34, height: 34, borderRadius: '50%', overflow: 'hidden' }}>
                <Photo src={sized(u.avatarUrl, 80, 80)} alt="" label={fullName(u)} color="#7A5BB5" />
              </span>
              <div style={{ minWidth: 0 }}>
                <b>{fullName(u)}</b>
                <div style={{ color: 'var(--muted)', fontSize: 12, overflow: 'hidden', textOverflow: 'ellipsis' }}>{u.email}</div>
              </div>
              <span style={{ fontWeight: 600 }}>
                {STAFF_ROLES[u.role]}
                {u.communeName ? ` · ${u.communeName}` : ''}
                <span style={{ display: 'block', fontSize: 11, color: 'var(--muted)', fontWeight: 400 }}>
                  {u.lastLoginAt ? `connecté·e ${relativeTime(u.lastLoginAt)}` : 'jamais connecté·e'}
                </span>
              </span>
              <span style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                {u.mfa ? (
                  <span style={{ fontSize: 11, fontWeight: 800, padding: '3px 8px', borderRadius: 999, background: 'var(--leaf)' }}>MFA</span>
                ) : admin ? (
                  <form action={mfaReminderAction}>
                    <input type="hidden" name="userId" value={u.userId} />
                    <button
                      type="submit"
                      title="Envoyer un rappel"
                      style={{
                        fontSize: 11,
                        fontWeight: 800,
                        padding: '3px 8px',
                        borderRadius: 999,
                        background: 'var(--warn-bg)',
                        border: 0,
                        cursor: 'pointer',
                      }}
                    >
                      Sans MFA · relancer
                    </button>
                  </form>
                ) : (
                  <span style={{ fontSize: 11, fontWeight: 800, padding: '3px 8px', borderRadius: 999, background: 'var(--warn-bg)' }}>Sans MFA</span>
                )}
                {admin && u.userId !== ctx.actor.user.id ? (
                  <form action={revokeRoleAction}>
                    <input type="hidden" name="roleId" value={u.roleId} />
                    <button
                      type="submit"
                      aria-label={`Retirer l’accès de ${fullName(u)}`}
                      style={{
                        border: 0,
                        background: 'var(--danger-bg)',
                        color: 'var(--danger-fg)',
                        borderRadius: 6,
                        width: 22,
                        height: 22,
                        cursor: 'pointer',
                      }}
                    >
                      ×
                    </button>
                  </form>
                ) : null}
              </span>
            </div>
          ))}
          {invites.map((i) => (
            <div key={i.email} style={{ padding: '10px 18px', borderTop: '1px solid var(--line-2)', fontSize: 13, color: 'var(--muted)' }}>
              Invitation en attente · <b>{i.email}</b> · expire {relativeTime(i.expiresAt)}
            </div>
          ))}
        </section>
      </BrandingEditor>

      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1.2fr)', ['--gap' as string]: '20px', ['--align' as string]: 'start' }}>
        <section className="bo-card" style={{ padding: 20, borderRadius: 20, display: 'flex', flexDirection: 'column', gap: 12 }}>
          <b>Adresse du portail</b>
          {domains.map((d) => (
            <div
              key={d.id}
              style={{ display: 'flex', border: '1px solid var(--line)', borderRadius: 10, overflow: 'hidden', fontSize: 14, alignItems: 'center' }}
            >
              <span style={{ padding: 11, background: 'var(--sand)', color: 'var(--muted)' }}>https://</span>
              <span style={{ flex: 1, padding: 11 }}>{d.host}</span>
              {d.verifiedAt ? (
                <span style={{ padding: 11, color: 'var(--green)', fontWeight: 700 }}>✓ SSL</span>
              ) : admin ? (
                <ActionForm action={verifyDomainAction} style={{ padding: '0 8px' }}>
                  <input type="hidden" name="host" value={d.host} />
                  <SubmitButton className="btn btn-outline btn-sm" pendingLabel="Vérification…">
                    Vérifier le DNS
                  </SubmitButton>
                </ActionForm>
              ) : (
                <span style={{ padding: 11, color: 'var(--brick)', fontWeight: 700 }}>en attente</span>
              )}
            </div>
          ))}
          <div style={{ fontSize: 13, color: 'var(--muted)' }}>
            Adresse par défaut : {env.PLATFORM_DOMAIN}/{t.slug} · sous-domaine : {t.slug}.{env.PLATFORM_DOMAIN}
          </div>
          {admin ? (
            <ActionForm action={addDomainAction} style={{ display: 'flex', gap: 8 }}>
              <input name="host" className="input" placeholder="commerces.votre-collectivite.fr" aria-label="Nouvelle adresse" />
              <SubmitButton className="btn btn-dark btn-sm">Utiliser</SubmitButton>
            </ActionForm>
          ) : null}
          <b style={{ marginTop: 8 }}>Modules du contrat</b>
          <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
            {modules.map((m) => (
              <span
                key={m.module}
                style={{
                  fontSize: 12,
                  fontWeight: 700,
                  padding: '4px 10px',
                  borderRadius: 999,
                  background: m.enabled ? 'var(--mint)' : 'var(--sand)',
                  color: m.enabled ? 'var(--green)' : 'var(--muted)',
                }}
              >
                {m.enabled ? '✓' : '○'} {MODULES[m.module]?.label ?? m.module}
              </span>
            ))}
          </div>
          <span style={{ fontSize: 12, color: 'var(--muted)' }}>Pour activer un module, contactez votre interlocuteur terricom.</span>
          <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginTop: 6 }}>
            <Link href="/collectivite/personnalisation/categories" className="btn btn-outline btn-sm">
              Catégories du portail →
            </Link>
            <Link href="/collectivite/personnalisation/langues" className="btn btn-outline btn-sm">
              Langues du portail →
            </Link>
          </div>
        </section>
        <section className="bo-card" style={{ padding: 20, borderRadius: 20 }}>
          <b>Règles du territoire</b>
          <ActionForm
            action={saveSettingsAction}
            resetOnSuccess={false}
            style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))', gap: 12, marginTop: 12 }}
          >
            <label className="field">
              <span>Validation des revendications</span>
              <select name="claimValidation" className="input" defaultValue={s.claimValidation ?? 'MANUAL'} disabled={!admin}>
                <option value="MANUAL">Par un agent (recommandé)</option>
                <option value="AUTO">Automatique si les contrôles concordent</option>
              </select>
            </label>
            <label className="field">
              <span>Publications des professionnels</span>
              <select name="postModeration" className="input" defaultValue={s.postModeration ?? 'POST'} disabled={!admin}>
                <option value="POST">Publiées directement, modérables</option>
                <option value="PRE">Validées avant publication</option>
              </select>
            </label>
            <label className="field">
              <span>Email de contact du portail</span>
              <input name="contactEmail" type="email" className="input" defaultValue={t.contactEmail ?? ''} disabled={!admin} />
            </label>
            <label className="field">
              <span>Nom de la newsletter</span>
              <input
                name="newsletterName"
                className="input"
                defaultValue={s.newsletterName ?? ''}
                placeholder={`${t.name} · Le week-end local`}
                disabled={!admin}
              />
            </label>
            <label className="field">
              <span>Itinéraires proposés</span>
              <select name="directionsProvider" className="input" defaultValue={s.directionsProvider ?? 'google'} disabled={!admin}>
                <option value="google">Google Maps</option>
                <option value="osm">OpenStreetMap</option>
                <option value="apple">Plans (Apple)</option>
              </select>
            </label>
            <label className="field">
              <span>Objectif de fiches revendiquées (%)</span>
              <input name="adoptionGoalPct" type="number" min={5} max={100} className="input" defaultValue={s.adoptionGoalPct ?? 60} disabled={!admin} />
            </label>
            <label className="field">
              <span>Éditeur (mentions légales)</span>
              <input name="legalPublisher" className="input" defaultValue={s.legalPublisher ?? t.legalName} disabled={!admin} />
            </label>
            <label className="field">
              <span>Email du délégué à la protection des données</span>
              <input name="dpoEmail" type="email" className="input" defaultValue={s.dpoEmail ?? ''} disabled={!admin} />
            </label>
            <label className="field" style={{ gridColumn: '1 / -1' }}>
              <span>Texte du pied de page</span>
              <input name="footerText" className="input" defaultValue={s.footerText ?? ''} maxLength={400} disabled={!admin} />
            </label>
            <label style={{ display: 'flex', gap: 8, fontSize: 14, alignItems: 'center', gridColumn: '1 / -1' }}>
              <input
                type="checkbox"
                name="requireMfaForAll"
                defaultChecked={s.requireMfaForAll === true}
                disabled={!admin}
                style={{ accentColor: 'var(--green)' }}
              />
              Exiger la double authentification pour toute l’équipe (obligatoire pour les administrateurs)
            </label>
            {admin ? (
              <SubmitButton className="btn btn-brand btn-sm" pendingLabel="Enregistrement…" style={{ justifySelf: 'start' }}>
                Enregistrer les règles
              </SubmitButton>
            ) : null}
          </ActionForm>
        </section>
      </div>
    </div>
  );
}
