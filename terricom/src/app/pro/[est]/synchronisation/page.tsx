import { eq } from 'drizzle-orm';
import type { Metadata } from 'next';
import { rotateConnectorSecret, saveConnector, testConnector } from '../actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { LockedFeature } from '@/components/pro/LockedFeature';
import { CopyField } from '@/components/ui/CopyField';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { relativeTime } from '@/lib/format';
import { db } from '@/server/db';
import { companies } from '@/server/db/schema';
import { CONNECTOR_EVENTS, readConnectorSecret } from '@/server/services/connectors';
import { loadProContext } from '@/server/services/pro';
import { portalUrl } from '@/server/urls';

export const metadata: Metadata = { title: 'Synchronisation' };

type Props = { params: Promise<{ est: string }> };

const SAMPLE = `// Vérifier la signature (Node.js)
const crypto = require('node:crypto');
const ts = req.headers['x-terricom-timestamp'];
const expected = 'sha256=' + crypto.createHmac('sha256', SECRET)
  .update(ts + '.' + rawBody).digest('hex');
const valide = crypto.timingSafeEqual(Buffer.from(expected),
  Buffer.from(req.headers['x-terricom-signature']));`;

/** Synchronisation des contenus : flux publics (toutes les offres) et connecteur (Premium, Communication). */
export default async function SyncPage({ params }: Props) {
  const { est: estId } = await params;
  const ctx = await loadProContext(estId);
  const { est, base, limits, territory } = ctx;
  const ficheUrl = portalUrl(territory, est.path);
  const feeds = [
    { label: 'Vos actualités (RSS)', value: `${ficheUrl}/actualites.xml` },
    { label: 'Vos événements (agenda iCal)', value: `${ficheUrl}/agenda.ics` },
    { label: `Agenda de ${territory.name} (iCal)`, value: portalUrl(territory, '/agenda.ics') },
  ];
  const [company] = await db
    .select({ url: companies.socialWebhookUrl, secret: companies.webhookSecret, lastAt: companies.webhookLastAt, lastStatus: companies.webhookLastStatus })
    .from(companies)
    .where(eq(companies.id, est.companyId))
    .limit(1);
  const secret = ctx.role === 'STAFF' ? null : readConnectorSecret(company?.secret ?? null);

  return (
    <div className="app-content">
      <section className="card" style={{ borderRadius: 18, padding: 20, display: 'flex', flexDirection: 'column', gap: 12 }}>
        <div>
          <h2 className="h3" style={{ margin: 0 }}>
            Vos contenus, partout, sans ressaisie
          </h2>
          <p style={{ margin: '6px 0 0', color: 'var(--muted)', fontSize: 14, lineHeight: 1.5, maxWidth: 760 }}>
            Ce que vous publiez sur votre fiche peut être repris automatiquement sur votre propre site, dans un agenda ou par vos outils. Copiez ces adresses
            dans votre site (widget RSS, agenda) : elles se mettent à jour toutes seules.
          </p>
        </div>
        <div className="auto-grid" style={{ ['--min' as string]: '300px', ['--gap' as string]: '12px' }}>
          {feeds.map((f) => (
            <CopyField key={f.label} label={f.label} value={f.value} />
          ))}
        </div>
      </section>

      {!limits.contentSync ? (
        <LockedFeature
          base={base}
          plan="Premium"
          title="Synchronisez vos contenus avec vos outils"
          text="À chaque publication, événement, offre d’emploi ou modification de votre fiche, terricom prévient automatiquement l’outil de votre choix (Make, Zapier, n8n, votre site) pour republier sans ressaisie."
          points={[
            'Vos publications sur Facebook, Instagram ou LinkedIn via votre scénario',
            'Vos horaires et coordonnées à jour sur vos autres fiches',
            'Messages signés et renvoyés automatiquement en cas d’indisponibilité',
          ]}
        />
      ) : ctx.role === 'STAFF' ? (
        <div className="alert alert-info">Le connecteur de l’entreprise n’est accessible qu’à l’entreprise elle-même.</div>
      ) : (
        <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.3fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
          <section className="card" style={{ borderRadius: 18, padding: 20, display: 'flex', flexDirection: 'column', gap: 14 }}>
            <div>
              <h2 className="h3" style={{ margin: 0 }}>
                Connecteur
              </h2>
              <p style={{ margin: '6px 0 0', color: 'var(--muted)', fontSize: 14, lineHeight: 1.5 }}>
                Adresse (https) appelée à chaque nouveau contenu : un scénario Make, Zapier ou n8n, ou votre site. Laissez vide pour désactiver.
              </p>
            </div>
            <ActionForm action={saveConnector} resetOnSuccess={false} style={{ display: 'flex', gap: 8, alignItems: 'flex-end', flexWrap: 'wrap' }}>
              <input type="hidden" name="estId" value={est.id} />
              <label className="field" style={{ flex: 1, minWidth: 260 }}>
                <span>Adresse du connecteur</span>
                <input name="url" type="url" className="input" defaultValue={company?.url ?? ''} placeholder="https://hook.eu1.make.com/…" maxLength={1000} />
              </label>
              <SubmitButton className="btn btn-brand" pendingLabel="Enregistrement…">
                Enregistrer
              </SubmitButton>
            </ActionForm>
            {company?.url && secret ? (
              <>
                <CopyField label="Secret de signature" value={secret} secret />
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                  <ActionForm action={testConnector} resetOnSuccess={false}>
                    <input type="hidden" name="estId" value={est.id} />
                    <SubmitButton className="btn btn-dark btn-sm" pendingLabel="Envoi…">
                      Envoyer un essai
                    </SubmitButton>
                  </ActionForm>
                  <ActionForm action={rotateConnectorSecret} resetOnSuccess={false}>
                    <input type="hidden" name="estId" value={est.id} />
                    <SubmitButton className="btn btn-outline btn-sm" pendingLabel="…">
                      Renouveler le secret
                    </SubmitButton>
                  </ActionForm>
                </div>
                <div style={{ fontSize: 13, color: 'var(--muted)' }} role="status">
                  {company.lastAt ? (
                    <>
                      Dernier envoi {relativeTime(company.lastAt)} : <b style={{ color: 'var(--text)' }}>{company.lastStatus}</b>
                    </>
                  ) : (
                    'Aucun envoi pour le moment.'
                  )}
                </div>
              </>
            ) : null}
          </section>
          <section className="card" style={{ borderRadius: 18, padding: 20, display: 'flex', flexDirection: 'column', gap: 10, fontSize: 14 }}>
            <b>Ce qui est envoyé</b>
            <ul style={{ margin: 0, paddingLeft: 18, display: 'flex', flexDirection: 'column', gap: 4, color: 'var(--muted-3)' }}>
              {(Object.keys(CONNECTOR_EVENTS) as (keyof typeof CONNECTOR_EVENTS)[])
                .filter((k) => k !== 'test')
                .map((k) => (
                  <li key={k}>
                    <b style={{ color: 'var(--text)' }}>{CONNECTOR_EVENTS[k]}</b> <code className="mono">{k}</code>
                  </li>
                ))}
            </ul>
            <p style={{ margin: 0, color: 'var(--muted)', fontSize: 13, lineHeight: 1.5 }}>
              Message JSON (POST) signé : en-têtes <code className="mono">X-Terricom-Event</code>, <code className="mono">X-Terricom-Timestamp</code> et{' '}
              <code className="mono">X-Terricom-Signature</code>. Sans réponse positive, l’envoi est retenté jusqu’à cinq fois.
            </p>
            <pre
              className="mono"
              style={{ margin: 0, fontSize: 11.5, background: 'var(--sand)', borderRadius: 10, padding: 12, overflowX: 'auto', whiteSpace: 'pre' }}
            >
              {SAMPLE}
            </pre>
          </section>
        </div>
      )}
    </div>
  );
}
