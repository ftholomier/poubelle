import { and, desc, eq, inArray } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { revokeOthersAction, revokeSessionAction } from '../actions';
import { changePasswordAction } from '@/app/connexion/actions';
import { DisableMfaForm, MfaSetup, RegenerateCodesForm } from '@/components/account/AccountForms';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { fmtStamp, relativeTime } from '@/lib/format';
import { startEnrollment } from '@/server/auth/mfa';
import { safeNext } from '@/server/auth/login';
import { listSessions } from '@/server/auth/session';
import { getActor } from '@/server/authz';
import { db } from '@/server/db';
import { auditLog } from '@/server/db/schema';

export const metadata: Metadata = { title: 'Sécurité' };

type Props = { searchParams: Promise<Record<string, string | undefined>> };

/** Description lisible d'un navigateur à partir de son User-Agent. */
function device(ua: string | null): string {
  if (!ua) return 'Appareil inconnu';
  const browser = /Edg\//.test(ua) ? 'Edge' : /Firefox\//.test(ua) ? 'Firefox' : /Chrome\//.test(ua) ? 'Chrome' : /Safari\//.test(ua) ? 'Safari' : 'Navigateur';
  const os = /iPhone|iPad/.test(ua)
    ? 'iOS'
    : /Android/.test(ua)
      ? 'Android'
      : /Mac OS X/.test(ua)
        ? 'macOS'
        : /Windows/.test(ua)
          ? 'Windows'
          : /Linux/.test(ua)
            ? 'Linux'
            : 'système inconnu';
  return `${browser} sur ${os}`;
}

function Card({ title, subtitle, children }: { title: string; subtitle?: string; children: React.ReactNode }) {
  return (
    <section
      className="card"
      style={{
        borderRadius: 20,
        padding: 24,
        display: 'flex',
        flexDirection: 'column',
        gap: 14,
      }}
    >
      <div>
        <h2 className="display" style={{ fontSize: 22, margin: 0 }}>
          {title}
        </h2>
        {subtitle ? <p style={{ margin: '4px 0 0', color: 'var(--muted)', fontSize: 14 }}>{subtitle}</p> : null}
      </div>
      {children}
    </section>
  );
}

export default async function SecurityPage({ searchParams }: Props) {
  const sp = await searchParams;
  const actor = await getActor();
  if (!actor) redirect('/connexion?next=/compte/securite');
  const u = actor.user;
  const staff = actor.roles.length > 0;
  const [enrollment, sessionsList, events] = await Promise.all([
    u.mfaEnabled ? Promise.resolve(null) : startEnrollment(u.id),
    listSessions(u.id),
    db
      .select({
        at: auditLog.occurredAt,
        summary: auditLog.summary,
        ip: auditLog.ipHash,
      })
      .from(auditLog)
      .where(and(eq(auditLog.actorUserId, u.id), inArray(auditLog.category, ['AUTH', 'SECURITE'])))
      .orderBy(desc(auditLog.occurredAt))
      .limit(8),
  ]);
  const next = safeNext(sp.next);

  return (
    <div
      className="app-content"
      style={{
        maxWidth: 860,
        display: 'flex',
        flexDirection: 'column',
        gap: 16,
      }}
    >
      {sp.mfa === 'obligatoire' && !u.mfaEnabled ? (
        <div className="alert alert-info" role="status">
          <b>Double authentification requise.</b> Votre rôle donne accès à des données d&apos;entreprises et d&apos;habitants : activez-la ci-dessous pour
          continuer.
        </div>
      ) : null}
      {u.mfaEnabled && next ? (
        <div className="alert alert-ok" role="status">
          Votre compte est protégé. <Link href={next}>Continuer vers votre espace →</Link>
        </div>
      ) : null}

      <Card
        title="Double authentification"
        subtitle={
          u.mfaEnabled
            ? `Activée · ${u.mfaRecoveryCodes.length} code${u.mfaRecoveryCodes.length > 1 ? 's' : ''} de secours restant${u.mfaRecoveryCodes.length > 1 ? 's' : ''}`
            : 'Un code à usage unique vous sera demandé à chaque connexion, en plus du mot de passe.'
        }
      >
        {u.mfaEnabled ? (
          <>
            <div style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
              <span
                style={{
                  background: 'var(--mint)',
                  color: 'var(--green)',
                  fontWeight: 800,
                  fontSize: 12,
                  padding: '5px 10px',
                  borderRadius: 999,
                }}
              >
                ✓ Protégé
              </span>
              <span style={{ fontSize: 14, color: 'var(--muted)' }}>Application d&apos;authentification (TOTP)</span>
            </div>
            <RegenerateCodesForm />
            {staff ? (
              <p style={{ margin: 0, fontSize: 13, color: 'var(--muted)' }}>Obligatoire pour les agents : elle ne peut pas être désactivée.</p>
            ) : (
              <details>
                <summary style={{ cursor: 'pointer', fontSize: 14, fontWeight: 600 }}>Désactiver la double authentification</summary>
                <div style={{ marginTop: 10 }}>
                  <DisableMfaForm />
                </div>
              </details>
            )}
          </>
        ) : enrollment ? (
          <MfaSetup secret={enrollment.secret} qrSvg={enrollment.qrSvg} />
        ) : null}
      </Card>

      <Card title="Mot de passe" subtitle={u.passwordChangedAt ? `Modifié ${relativeTime(u.passwordChangedAt)}.` : undefined}>
        <ActionForm
          action={changePasswordAction}
          style={{
            display: 'flex',
            flexDirection: 'column',
            gap: 12,
            maxWidth: 460,
          }}
        >
          <label className="field">
            <span>Mot de passe actuel</span>
            <input name="current" type="password" className="input" autoComplete="current-password" required />
          </label>
          <label className="field">
            <span>Nouveau mot de passe</span>
            <input name="password" type="password" className="input" autoComplete="new-password" minLength={10} required />
            <small style={{ color: 'var(--muted)' }}>10 caractères minimum, avec lettres et chiffres. Vos autres sessions seront fermées.</small>
          </label>
          <SubmitButton className="btn btn-brand" style={{ alignSelf: 'flex-start' }} pendingLabel="Enregistrement…">
            Changer le mot de passe
          </SubmitButton>
        </ActionForm>
      </Card>

      <Card title="Sessions ouvertes" subtitle="Fermez à distance une session que vous ne reconnaissez pas.">
        <div style={{ display: 'flex', flexDirection: 'column' }}>
          {sessionsList.map((s) => {
            const current = s.id === actor.session.id;
            return (
              <div
                key={s.id}
                style={{
                  display: 'flex',
                  gap: 12,
                  alignItems: 'center',
                  padding: '10px 0',
                  borderTop: '1px solid var(--line-2)',
                  fontSize: 14,
                  flexWrap: 'wrap',
                }}
              >
                <div style={{ flex: 1, minWidth: 220 }}>
                  <b>{device(s.userAgent)}</b>
                  {current ? (
                    <span
                      style={{
                        marginLeft: 8,
                        background: 'var(--mint)',
                        color: 'var(--green)',
                        fontSize: 11,
                        fontWeight: 800,
                        padding: '2px 8px',
                        borderRadius: 999,
                      }}
                    >
                      Cette session
                    </span>
                  ) : null}
                  <div style={{ color: 'var(--muted)', fontSize: 13 }}>
                    Ouverte le {fmtStamp(s.createdAt)} · active {relativeTime(s.lastSeenAt)}
                    {s.mfaVerified ? ' · double authentification validée' : ''}
                  </div>
                </div>
                <form action={revokeSessionAction}>
                  <input type="hidden" name="sessionId" value={s.id} />
                  <button type="submit" className="btn btn-ghost btn-sm" style={{ color: 'var(--danger-fg)' }}>
                    {current ? 'Se déconnecter' : 'Fermer'}
                  </button>
                </form>
              </div>
            );
          })}
        </div>
        {sessionsList.length > 1 ? (
          <form action={revokeOthersAction}>
            <button type="submit" className="btn btn-outline btn-sm">
              Fermer toutes les autres sessions
            </button>
          </form>
        ) : null}
      </Card>

      <Card title="Activité récente" subtitle="Connexions et changements de sécurité de votre compte.">
        {events.length ? (
          <div style={{ display: 'flex', flexDirection: 'column' }}>
            {events.map((e, i) => (
              <div
                key={i}
                style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  gap: 12,
                  padding: '8px 0',
                  borderTop: '1px solid var(--line-2)',
                  fontSize: 14,
                }}
              >
                <span>{e.summary}</span>
                <span style={{ color: 'var(--muted)', whiteSpace: 'nowrap' }}>{fmtStamp(e.at)}</span>
              </div>
            ))}
          </div>
        ) : (
          <span style={{ fontSize: 14, color: 'var(--muted)' }}>Aucun évènement pour l&apos;instant.</span>
        )}
      </Card>
    </div>
  );
}
