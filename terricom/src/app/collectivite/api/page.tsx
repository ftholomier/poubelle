import type { Metadata } from 'next';
import { revokeApiKeyAction } from './actions';
import { ApiKeyCreator } from '@/components/bo/ApiKeyCreator';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { fmtStamp } from '@/lib/format';
import { loadBoContext, requireTerritoryLevel } from '@/server/services/backoffice';
import { API_MAX_PER_PAGE, API_RATE_LIMIT, listApiKeys } from '@/server/services/public-api';
import { appUrl } from '@/server/urls';

export const metadata: Metadata = { title: 'API & données' };

const ENDPOINTS: [string, string][] = [
  ['GET /territory', 'Le territoire : nom, adresse du portail, nombre de communes et de fiches'],
  ['GET /communes', 'Les communes, avec leur code INSEE et leur nombre de fiches'],
  ['GET /categories', 'Les catégories d’activité présentes (avec vos noms personnalisés)'],
  ['GET /establishments', 'Les fiches publiées : filtres commune, category, q, updated_since'],
  ['GET /establishments/{id}', 'Une fiche : coordonnées, horaires, ouvert maintenant, photos, labels'],
  ['GET /events', 'L’agenda : événements à venir, ou entre from et to'],
  ['GET /posts', 'Les actualités et offres publiées (kind=promo pour les offres)'],
];

/** Clés de l'API publique du territoire (open data, site en marque blanche, connecteurs). */
export default async function ApiPage() {
  const ctx = await loadBoContext();
  requireTerritoryLevel(ctx);
  const admin = ctx.access === 'ADMIN';
  const keys = await listApiKeys(ctx.territory.id);
  const base = appUrl('/api/v1');
  return (
    <div className="app-content">
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.3fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <section className="bo-card" style={{ padding: 20, borderRadius: 20, display: 'flex', flexDirection: 'column', gap: 14 }} aria-labelledby="keys-title">
          <div>
            <h2 id="keys-title" className="panel-title">
              Clés d’accès
            </h2>
            <p style={{ margin: '4px 0 0', fontSize: 13, color: 'var(--muted)', lineHeight: 1.5 }}>
              Donnez une clé à chaque usage (site de l’office de tourisme, application, borne…) : vous pourrez la révoquer sans gêner les autres. Les clés
              donnent accès en lecture aux seules données publiées sur le portail.
            </p>
          </div>
          {admin ? <ApiKeyCreator /> : <div className="alert alert-info">Seuls les administrateurs du territoire créent et révoquent les clés.</div>}
          {keys.length ? (
            <div className="table-wrap">
              <table className="data-table">
                <thead>
                  <tr>
                    <th scope="col">Nom</th>
                    <th scope="col">Clé</th>
                    <th scope="col">Créée le</th>
                    <th scope="col">Dernier usage</th>
                    <th scope="col">
                      <span className="sr-only">Actions</span>
                    </th>
                  </tr>
                </thead>
                <tbody>
                  {keys.map((k) => (
                    <tr key={k.id} style={{ opacity: k.revokedAt ? 0.55 : 1 }}>
                      <td style={{ fontWeight: 700 }}>{k.name}</td>
                      <td className="mono" style={{ fontSize: 12 }}>
                        tc_{k.prefix}_…
                      </td>
                      <td>{fmtStamp(k.createdAt)}</td>
                      <td>{k.lastUsedAt ? fmtStamp(k.lastUsedAt) : 'jamais'}</td>
                      <td>
                        {k.revokedAt ? (
                          <span className="tag" style={{ background: 'var(--sand)' }}>
                            Révoquée
                          </span>
                        ) : admin ? (
                          <form action={revokeApiKeyAction}>
                            <input type="hidden" name="keyId" value={k.id} />
                            <SubmitButton className="btn btn-ghost btn-xs" pendingLabel="…">
                              Révoquer
                            </SubmitButton>
                          </form>
                        ) : (
                          <span className="tag" style={{ background: 'var(--ok-bg)' }}>
                            Active
                          </span>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : (
            <p style={{ margin: 0, fontSize: 14, color: 'var(--muted)' }}>Aucune clé pour l’instant.</p>
          )}
        </section>

        <section className="bo-card" style={{ padding: 20, borderRadius: 20, display: 'flex', flexDirection: 'column', gap: 12 }} aria-labelledby="doc-title">
          <h2 id="doc-title" className="panel-title">
            Documentation
          </h2>
          <p style={{ margin: 0, fontSize: 13, color: 'var(--muted)', lineHeight: 1.5 }}>
            Adresse de base <code className="mono">{base}</code>, réponses JSON, {API_RATE_LIMIT} requêtes par minute et par clé, {API_MAX_PER_PAGE} résultats
            par page au plus.
          </p>
          <ul style={{ listStyle: 'none', margin: 0, padding: 0, display: 'flex', flexDirection: 'column', gap: 8 }}>
            {ENDPOINTS.map(([path, text]) => (
              <li key={path} style={{ fontSize: 13 }}>
                <code className="mono" style={{ fontWeight: 700 }}>
                  {path}
                </code>
                <div style={{ color: 'var(--muted)' }}>{text}</div>
              </li>
            ))}
          </ul>
          <pre
            className="mono"
            style={{ margin: 0, fontSize: 12, background: 'var(--ink)', color: 'var(--cream)', borderRadius: 12, padding: 14, overflowX: 'auto' }}
          >
            {`curl -H "Authorization: Bearer <votre clé>" \\\n  "${base}/establishments?commune=ornans&per_page=50"`}
          </pre>
          <a href={`${base}/openapi.json`} className="btn btn-outline btn-sm" style={{ alignSelf: 'flex-start' }} target="_blank" rel="noopener noreferrer">
            Description OpenAPI ↗
          </a>
        </section>
      </div>
    </div>
  );
}
