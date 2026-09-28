import { redirect } from 'next/navigation';
import { updateProfileAction } from './actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { PushSettings } from '@/components/pwa/PushSettings';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { STAFF_ROLES } from '@/lib/constants';
import { fmtLongDate } from '@/lib/format';
import { getActor } from '@/server/authz';
import { userSubscriptionCount, vapidPublicKey } from '@/server/push';

export default async function ProfilePage() {
  const actor = await getActor();
  if (!actor) redirect('/connexion?next=/compte');
  const u = actor.user;
  const devices = await userSubscriptionCount(u.id);
  return (
    <div className="app-content" style={{ maxWidth: 820 }}>
      <section
        className="card"
        style={{
          borderRadius: 20,
          padding: 24,
          display: 'flex',
          flexDirection: 'column',
          gap: 16,
        }}
      >
        <div>
          <h2 className="display" style={{ fontSize: 22, margin: 0 }}>
            Informations personnelles
          </h2>
          <p style={{ margin: '4px 0 0', color: 'var(--muted)', fontSize: 14 }}>Visibles uniquement par votre équipe et votre collectivité.</p>
        </div>
        <ActionForm action={updateProfileAction} resetOnSuccess={false} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))',
              gap: 12,
            }}
          >
            <label className="field">
              <span>Prénom</span>
              <input name="firstName" className="input" defaultValue={u.firstName} required autoComplete="given-name" />
            </label>
            <label className="field">
              <span>Nom</span>
              <input name="lastName" className="input" defaultValue={u.lastName} required autoComplete="family-name" />
            </label>
            <label className="field">
              <span>Téléphone</span>
              <input name="phone" className="input" defaultValue={u.phone ?? ''} autoComplete="tel" inputMode="tel" />
            </label>
            <label className="field">
              <span>Fonction</span>
              <input name="jobTitle" className="input" defaultValue={u.jobTitle ?? ''} placeholder="Gérante, chargé de mission…" />
            </label>
          </div>
          <label className="field">
            <span>Email de connexion</span>
            <input className="input" value={u.email} readOnly aria-readonly="true" style={{ background: 'var(--cream)' }} />
            <small style={{ color: 'var(--muted)' }}>Pour changer d&apos;adresse, écrivez au support : nous vérifierons votre identité.</small>
          </label>
          <SubmitButton className="btn btn-brand" style={{ alignSelf: 'flex-start' }} pendingLabel="Enregistrement…">
            Enregistrer
          </SubmitButton>
        </ActionForm>
      </section>
      <section
        id="notifications"
        className="card"
        style={{ borderRadius: 20, padding: 24, marginTop: 16, display: 'flex', flexDirection: 'column', gap: 12, scrollMarginTop: 90 }}
        aria-labelledby="notif-title"
      >
        <div>
          <h2 id="notif-title" className="display" style={{ fontSize: 22, margin: 0 }}>
            Notifications et application
          </h2>
          <p style={{ margin: '4px 0 0', color: 'var(--muted)', fontSize: 14 }}>
            Soyez prévenu·e sur ce téléphone ou cet ordinateur : nouveaux messages, demandes de rendez-vous, candidatures, revendications à valider.
          </p>
        </div>
        <PushSettings vapidKey={vapidPublicKey()} devices={devices} />
      </section>
      <section
        className="card"
        style={{
          borderRadius: 20,
          padding: 24,
          marginTop: 16,
          display: 'flex',
          flexDirection: 'column',
          gap: 10,
          fontSize: 14,
        }}
      >
        <h2 className="display" style={{ fontSize: 22, margin: 0 }}>
          Vos accès
        </h2>
        <div>Compte créé le {fmtLongDate(u.createdAt, true)}.</div>
        {actor.roles.length ? (
          <ul style={{ margin: 0, paddingLeft: 18 }}>
            {actor.roles.map((r, i) => (
              <li key={i}>{STAFF_ROLES[r.role]}</li>
            ))}
          </ul>
        ) : null}
        {actor.memberships.length ? (
          <div>
            {actor.memberships.length} entreprise
            {actor.memberships.length > 1 ? 's' : ''} gérée
            {actor.memberships.length > 1 ? 's' : ''} ({actor.memberships.filter((m) => m.role === 'OWNER').length} en tant que titulaire).
          </div>
        ) : null}
      </section>
    </div>
  );
}
