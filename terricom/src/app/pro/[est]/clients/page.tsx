import { addContactAction, removeContactAction, sendLetterAction } from './actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { LockedFeature } from '@/components/pro/LockedFeature';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { fmtInt, fmtStamp } from '@/lib/format';
import { contactStats, customerLetters, CUSTOMER_LETTERS_PER_30_DAYS, lettersLast30Days, listContacts } from '@/server/services/customers';
import { loadProContext } from '@/server/services/pro';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ est: string }> };

const SOURCES: Record<string, string> = { FICHE: 'Fiche', MANUAL: 'Ajout manuel', IMPORT: 'Import' };
const pct = (n: number, d: number) => (d ? `${Math.round((n / d) * 100)} %` : '—');

export default async function ClientsPage({ params }: Props) {
  const { est: estId } = await params;
  const ctx = await loadProContext(estId);
  const { est, base, limits } = ctx;

  if (ctx.role === 'STAFF') {
    return (
      <div className="app-content">
        <div className="alert alert-info">Les clients d’une entreprise ne sont accessibles qu’à l’entreprise elle-même.</div>
      </div>
    );
  }
  if (!limits.customerNewsletter) {
    return (
      <div className="app-content">
        <LockedFeature
          base={base}
          plan="Communication"
          title="Écrivez directement à vos clients"
          text="Vos clients s’abonnent depuis votre fiche ou votre QR code, puis reçoivent vos nouveautés et vos offres par email, à vos couleurs. Consentement et désinscription sont gérés pour vous."
          points={[
            'Bouton « Suivre » sur votre fiche, confirmation par email (RGPD)',
            'Lettres à vos clients en quelques minutes, jusqu’à 4 par mois',
            'Ouvertures, clics et désinscriptions mesurés',
            'Export de vos contacts consentis',
          ]}
        />
      </div>
    );
  }

  const [stats, used, letters, contacts] = await Promise.all([
    contactStats(est.companyId),
    lettersLast30Days(est.companyId),
    customerLetters(est.companyId),
    listContacts(est.companyId, 200),
  ]);
  const left = Math.max(0, CUSTOMER_LETTERS_PER_30_DAYS - used);
  const ficheUrl = portalUrl(ctx.territory, est.path);
  const kpis = [
    { label: 'Abonnés actifs', value: fmtInt(stats.active), note: 'confirmés par email', color: 'var(--green)' },
    { label: 'En attente', value: fmtInt(stats.pending), note: 'confirmation non faite', color: 'var(--f-commerce)' },
    { label: 'Désinscrits', value: fmtInt(stats.unsubscribed), note: 'ne reçoivent plus rien', color: 'var(--sand-3)' },
    {
      label: 'Lettres sur 30 jours',
      value: `${used} / ${CUSTOMER_LETTERS_PER_30_DAYS}`,
      note: left ? `${left} envoi${left > 1 ? 's' : ''} possible${left > 1 ? 's' : ''}` : 'quota atteint',
      color: 'var(--f-services)',
    },
  ];

  return (
    <div className="app-content">
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(170px,1fr))', gap: 12 }}>
        {kpis.map((k) => (
          <div key={k.label} className="card kpi-tile" style={{ borderTopColor: k.color }}>
            <div className="kpi-label">{k.label}</div>
            <div className="kpi-value">{k.value}</div>
            <div className="kpi-note">{k.note}</div>
          </div>
        ))}
      </div>

      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.5fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <section className="panel" aria-labelledby="letter-title">
          <h2 id="letter-title" className="panel-title">
            Écrire à vos clients
          </h2>
          {stats.active === 0 ? (
            <div className="alert alert-info">Aucun abonné confirmé pour l’instant. Partagez votre fiche : le bouton « Suivre » y est affiché.</div>
          ) : null}
          <ActionForm action={sendLetterAction} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <input type="hidden" name="estId" value={est.id} />
            <label className="field">
              <span>Objet de l’email</span>
              <input name="subject" className="input" required maxLength={150} placeholder="Nouveautés de la semaine chez nous" />
            </label>
            <label className="field">
              <span>Titre</span>
              <input name="title" className="input" required maxLength={150} placeholder="La galette des rois est de retour !" />
            </label>
            <label className="field">
              <span>Message</span>
              <textarea name="message" className="textarea" rows={7} required maxLength={5000} placeholder="Bonjour à toutes et à tous, cette semaine…" />
            </label>
            <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1.4fr)', ['--gap' as string]: '10px' }}>
              <label className="field">
                <span>Bouton (facultatif)</span>
                <input name="ctaLabel" className="input" maxLength={40} placeholder="Réserver" />
              </label>
              <label className="field">
                <span>Lien du bouton</span>
                <input name="ctaUrl" type="url" className="input" maxLength={500} placeholder="https://" />
              </label>
            </div>
            <label className="checkbox">
              <input type="checkbox" name="withPosts" defaultChecked />
              <span>Ajouter mes 3 dernières publications</span>
            </label>
            <div style={{ display: 'flex', gap: 12, alignItems: 'center', flexWrap: 'wrap' }}>
              <SubmitButton className="btn btn-brand" pendingLabel="Envoi…" disabled={!left || !stats.active}>
                {`Envoyer à ${fmtInt(stats.active)} client${stats.active > 1 ? 's' : ''}`}
              </SubmitButton>
              <span style={{ fontSize: 12, color: 'var(--muted)' }}>
                Envoyé au nom de {est.name}, avec un lien de désinscription.{' '}
                {left ? `${left} envoi${left > 1 ? 's' : ''} restant${left > 1 ? 's' : ''} sur 30 jours.` : 'Quota atteint.'}
              </span>
            </div>
          </ActionForm>
        </section>

        <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
          <section className="panel" aria-labelledby="add-title">
            <h2 id="add-title" className="panel-title">
              Ajouter un client
            </h2>
            <ActionForm action={addContactAction} style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              <input type="hidden" name="estId" value={est.id} />
              <label className="field">
                <span>Email</span>
                <input name="email" type="email" className="input" required maxLength={254} autoComplete="off" />
              </label>
              <label className="field">
                <span>Nom (facultatif)</span>
                <input name="fullName" className="input" maxLength={120} autoComplete="off" />
              </label>
              <label className="checkbox" style={{ fontSize: 13 }}>
                <input type="checkbox" name="attest" required />
                <span>J’atteste que ce client m’a donné son accord pour recevoir mes emails.</span>
              </label>
              <SubmitButton className="btn btn-outline btn-sm" pendingLabel="Ajout…">
                Ajouter
              </SubmitButton>
            </ActionForm>
          </section>
          <section className="panel" aria-labelledby="grow-title">
            <h2 id="grow-title" className="panel-title">
              Gagner des abonnés
            </h2>
            <ul style={{ margin: 0, paddingLeft: 18, fontSize: 14, lineHeight: 1.6 }}>
              <li>Le bouton « Suivre » est affiché sur votre fiche publique.</li>
              <li>Imprimez votre QR code (Kit vitrine) et posez-le près de la caisse.</li>
              <li>Partagez le lien de votre fiche sur vos réseaux.</li>
            </ul>
            <div className="mono" style={{ fontSize: 12, background: 'var(--sand)', borderRadius: 8, padding: '8px 10px', overflowWrap: 'anywhere' }}>
              {ficheUrl}
            </div>
            {limits.contactsExport ? (
              <a href={`/api/pro/${est.id}/contacts.csv`} className="btn btn-outline btn-sm" style={{ alignSelf: 'flex-start' }}>
                Exporter mes contacts (CSV)
              </a>
            ) : null}
          </section>
        </div>
      </div>

      <section className="panel" aria-labelledby="history-title">
        <h2 id="history-title" className="panel-title">
          Lettres envoyées
        </h2>
        {letters.length ? (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th scope="col">Objet</th>
                  <th scope="col">Envoi</th>
                  <th scope="col">Destinataires</th>
                  <th scope="col">Ouvertures</th>
                  <th scope="col">Clics</th>
                  <th scope="col">Désinscriptions</th>
                </tr>
              </thead>
              <tbody>
                {letters.map((l) => (
                  <tr key={l.id}>
                    <td style={{ fontWeight: 700 }}>{l.subject}</td>
                    <td>{l.sentAt ? fmtStamp(l.sentAt) : l.status === 'SENDING' ? 'En cours…' : fmtStamp(l.createdAt)}</td>
                    <td>{fmtInt(l.recipients)}</td>
                    <td>{pct(l.opens, l.recipients)}</td>
                    <td>{pct(l.clicks, l.recipients)}</td>
                    <td>{fmtInt(l.unsubscribes)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p style={{ margin: 0, color: 'var(--muted)', fontSize: 14 }}>Aucune lettre envoyée pour l’instant.</p>
        )}
      </section>

      <section className="panel" aria-labelledby="contacts-title">
        <h2 id="contacts-title" className="panel-title">
          Vos clients abonnés ({fmtInt(contacts.length)})
        </h2>
        {contacts.length ? (
          <div className="table-wrap">
            <table className="data-table">
              <thead>
                <tr>
                  <th scope="col">Email</th>
                  <th scope="col">Nom</th>
                  <th scope="col">Origine</th>
                  <th scope="col">Statut</th>
                  <th scope="col">Depuis le</th>
                  <th scope="col">
                    <span className="sr-only">Actions</span>
                  </th>
                </tr>
              </thead>
              <tbody>
                {contacts.map((c) => (
                  <tr key={c.id}>
                    <td style={{ overflowWrap: 'anywhere' }}>{c.email}</td>
                    <td>{c.fullName ?? '—'}</td>
                    <td>{SOURCES[c.source] ?? c.source}</td>
                    <td>
                      <span className="tag" style={{ background: !c.subscribed ? 'var(--sand)' : c.confirmedAt ? 'var(--ok-bg)' : 'var(--warn-bg)' }}>
                        {!c.subscribed ? 'Désinscrit' : c.confirmedAt ? 'Actif' : 'En attente'}
                      </span>
                    </td>
                    <td>{fmtStamp(c.consentAt ?? c.createdAt)}</td>
                    <td>
                      <form action={removeContactAction}>
                        <input type="hidden" name="estId" value={est.id} />
                        <input type="hidden" name="contactId" value={c.id} />
                        <button type="submit" className="btn btn-ghost btn-xs" aria-label={`Supprimer ${c.email}`}>
                          Supprimer
                        </button>
                      </form>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        ) : (
          <p style={{ margin: 0, color: 'var(--muted)', fontSize: 14 }}>Pas encore d’abonné. Ils apparaîtront ici dès leur inscription depuis votre fiche.</p>
        )}
        <p style={{ margin: 0, fontSize: 12, color: 'var(--muted)' }}>
          Ces adresses vous sont confiées pour vos seuls envois : elles ne sont jamais partagées avec la collectivité ni avec d’autres entreprises.
        </p>
      </section>
    </div>
  );
}
