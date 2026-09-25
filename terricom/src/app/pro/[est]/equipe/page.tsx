import { and, eq, gt, isNull, sql } from 'drizzle-orm';
import { inviteMember, removeMember } from '../actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { fmtStamp, fullName } from '@/lib/format';
import { db } from '@/server/db';
import { companyMembers, tokens, users } from '@/server/db/schema';
import { loadProContext } from '@/server/services/pro';

type Props = { params: Promise<{ est: string }> };

export default async function TeamPage({ params }: Props) {
  const { est: estId } = await params;
  const ctx = await loadProContext(estId);
  const { est } = ctx;
  const [members, invites] = await Promise.all([
    db
      .select({ m: companyMembers, u: users })
      .from(companyMembers)
      .innerJoin(users, eq(users.id, companyMembers.userId))
      .where(eq(companyMembers.companyId, est.companyId)),
    db
      .select()
      .from(tokens)
      .where(
        and(eq(tokens.kind, 'INVITE_MEMBER'), isNull(tokens.usedAt), gt(tokens.expiresAt, new Date()), sql`${tokens.payload}->>'companyId' = ${est.companyId}`),
      ),
  ]);
  const canManage = ctx.role !== 'MEMBER';
  return (
    <div className="app-content">
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.2fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <div className="panel">
          <h2 className="panel-title">Personnes ayant accès à {est.name}</h2>
          {members.map(({ m, u }) => (
            <div
              key={u.id}
              style={{ display: 'flex', justifyContent: 'space-between', gap: 12, alignItems: 'center', borderTop: '1px solid var(--line-2)', paddingTop: 10 }}
            >
              <div>
                <div style={{ fontWeight: 700 }}>
                  {fullName(u)} {u.id === ctx.actor.user.id ? <span style={{ fontWeight: 500, color: 'var(--muted)' }}>(vous)</span> : null}
                </div>
                <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                  {u.email} · {m.role === 'OWNER' ? 'Titulaire' : 'Collaborateur·rice'} ·{' '}
                  {u.mfaEnabled ? 'double authentification ✓' : 'sans double authentification'}
                  {u.lastLoginAt ? ` · dernière connexion ${fmtStamp(u.lastLoginAt)}` : ''}
                </div>
              </div>
              {canManage && u.id !== ctx.actor.user.id ? (
                <form action={removeMember}>
                  <input type="hidden" name="estId" value={est.id} />
                  <input type="hidden" name="userId" value={u.id} />
                  <button type="submit" className="btn-link" style={{ color: 'var(--danger-fg)', fontSize: 13 }}>
                    Retirer l&apos;accès
                  </button>
                </form>
              ) : null}
            </div>
          ))}
          {invites.length ? (
            <div style={{ borderTop: '1px solid var(--line-2)', paddingTop: 10, fontSize: 13, color: 'var(--muted)' }}>
              Invitations en attente : {invites.map((i) => i.email).join(', ')}
            </div>
          ) : null}
        </div>
        {canManage ? (
          <ActionForm action={inviteMember} className="panel">
            <input type="hidden" name="estId" value={est.id} />
            <h2 className="panel-title">Inviter un collaborateur</h2>
            <p style={{ margin: 0, fontSize: 14, color: 'var(--muted)' }}>
              Un salarié, un associé, votre agence : chacun a son propre accès, retirable à tout moment. Aucun mot de passe partagé.
            </p>
            <input name="email" type="email" className="input" placeholder="email@exemple.fr" required />
            <select name="role" className="select" defaultValue="EDITOR">
              <option value="EDITOR">Collaborateur·rice : fiche, publications, messages</option>
              <option value="OWNER">Titulaire : tout, y compris l&apos;offre et l&apos;équipe</option>
            </select>
            <button type="submit" className="btn btn-brand" style={{ alignSelf: 'flex-start' }}>
              Envoyer l&apos;invitation
            </button>
          </ActionForm>
        ) : null}
      </div>
    </div>
  );
}
