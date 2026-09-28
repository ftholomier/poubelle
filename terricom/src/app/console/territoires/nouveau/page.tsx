import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { createTerritoryAction } from '../actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { MODULE_ORDER, MODULES, TERRITORY_KINDS, type TerritoryKind } from '@/lib/constants';
import { parisDate } from '@/lib/format';
import { requirePlatformStaff } from '@/server/authz';
import { dealPrefill, suggestedSlug } from '@/server/services/console-territories';

export const metadata: Metadata = { title: 'Nouveau territoire' };

type Props = { searchParams: Promise<{ deal?: string }> };

export default async function NewTerritoryPage({ searchParams }: Props) {
  const actor = await requirePlatformStaff();
  if (!actor.isPlatformAdmin) notFound();
  const { deal: dealId } = await searchParams;
  const deal = dealId && /^[0-9a-f-]{36}$/.test(dealId) ? await dealPrefill(dealId) : null;
  const kind: TerritoryKind = deal?.kind ?? 'CC';
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 14, maxWidth: 980 }}>
      <Link href="/console/territoires" style={{ fontSize: 13, fontWeight: 600 }}>
        ← Territoires & abonnements
      </Link>
      <section className="console-card" style={{ padding: 24 }}>
        <h2 className="display" style={{ fontSize: 28, margin: '0 0 4px', letterSpacing: '-0.02em' }}>
          Nouveau territoire client
        </h2>
        <p style={{ margin: '0 0 18px', color: 'var(--muted)', fontSize: 14, maxWidth: 720 }}>
          {deal
            ? `Création à partir de l’affaire « ${deal.name} » du suivi commercial : elle passera à l’étape « Signé ».`
            : 'Le portail est créé aussitôt à l’adresse de la plateforme ; le domaine personnalisé se branche ensuite depuis la personnalisation du territoire.'}
        </p>
        <ActionForm action={createTerritoryAction} resetOnSuccess={false} style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
          {deal ? <input type="hidden" name="dealId" value={deal.id} /> : null}
          <fieldset className="console-fieldset">
            <legend>Identité</legend>
            <div className="console-form-grid">
              <label className="field">
                <span>Nom d’usage *</span>
                <input name="name" className="input" required maxLength={160} defaultValue={deal?.name ?? ''} placeholder="Val de Loue" />
              </label>
              <label className="field">
                <span>Raison sociale *</span>
                <input
                  name="legalName"
                  className="input"
                  required
                  maxLength={255}
                  defaultValue={deal ? `${TERRITORY_KINDS[kind].label} ${deal.name}` : ''}
                  placeholder="Communauté de communes du Val de Loue"
                />
              </label>
              <label className="field">
                <span>Type de structure</span>
                <select name="kind" className="input" defaultValue={kind}>
                  {(Object.keys(TERRITORY_KINDS) as TerritoryKind[]).map((k) => (
                    <option key={k} value={k}>
                      {TERRITORY_KINDS[k].label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="field">
                <span>Identifiant d’URL</span>
                <input
                  name="slug"
                  className="input"
                  maxLength={64}
                  pattern="[a-z0-9\-]*"
                  defaultValue={deal ? suggestedSlug(deal.name) : ''}
                  placeholder="valdeloue"
                />
                <span className="field-hint">Portail : terricom.fr/identifiant et identifiant.terricom.fr</span>
              </label>
              <label className="field">
                <span>Email de contact</span>
                <input name="contactEmail" type="email" className="input" defaultValue={deal?.email ?? ''} placeholder="economie@cc-exemple.fr" />
              </label>
              <label className="field">
                <span>Population</span>
                <input
                  name="population"
                  type="number"
                  min={0}
                  className="input"
                  defaultValue={deal?.population ?? ''}
                  placeholder="calculée depuis les communes"
                />
              </label>
            </div>
          </fieldset>

          <fieldset className="console-fieldset">
            <legend>Communes</legend>
            <div className="console-form-grid">
              <label className="field">
                <span>SIREN de l’intercommunalité</span>
                <input name="siren" className="input" inputMode="numeric" maxLength={9} placeholder="200041952" />
                <span className="field-hint">Les communes membres sont chargées depuis l’API Géo (geo.api.gouv.fr).</span>
              </label>
              <label className="field">
                <span>Département</span>
                <input name="departmentCode" className="input" maxLength={3} placeholder="25" />
              </label>
              <label className="field" style={{ gridColumn: '1 / -1' }}>
                <span>Ou codes INSEE des communes</span>
                <textarea name="inseeCodes" className="textarea" rows={3} placeholder="25424, 25475, 25011… (commune indépendante : un seul code)" />
                <span className="field-hint">
                  Une commune déjà rattachée à un autre territoire n’est pas déplacée : le changement d’intercommunalité se fait avec historique.
                </span>
              </label>
            </div>
          </fieldset>

          <fieldset className="console-fieldset">
            <legend>Contrat</legend>
            <div className="console-form-grid">
              <label className="field">
                <span>Licence annuelle (€ HT)</span>
                <input name="licence" type="number" min={0} step={100} className="input" defaultValue={deal ? deal.licenceCents / 100 : 9000} />
              </label>
              <label className="field">
                <span>Mise en service (€ HT)</span>
                <input name="setup" type="number" min={0} step={100} className="input" defaultValue={deal ? deal.setupCents / 100 : 5000} />
              </label>
              <label className="field">
                <span>Début du contrat</span>
                <input name="startsAt" type="date" className="input" defaultValue={parisDate()} required />
              </label>
              <label className="field">
                <span>Statut</span>
                <select name="status" className="input" defaultValue="ONBOARDING">
                  <option value="ONBOARDING">Onboarding</option>
                  <option value="ACTIVE">Actif</option>
                </select>
              </label>
              <label className="checkbox" style={{ alignSelf: 'end', paddingBottom: 12 }}>
                <input type="checkbox" name="isPilot" /> Territoire partenaire pilote
              </label>
            </div>
          </fieldset>

          <fieldset className="console-fieldset">
            <legend>Modules</legend>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(220px,1fr))', gap: 8 }}>
              {MODULE_ORDER.map((m) => (
                <label key={m} className="checkbox" style={{ alignItems: 'center', color: 'var(--text)', fontWeight: 600 }}>
                  <input type="checkbox" name="modules" value={m} defaultChecked={MODULES[m].tag === 'MVP'} />
                  {MODULES[m].label}
                  <span
                    style={{
                      fontSize: 10,
                      fontWeight: 800,
                      padding: '2px 6px',
                      borderRadius: 4,
                      background: MODULES[m].tag === 'MVP' ? '#D6E8B4' : '#DCD3F3',
                      color: 'var(--text)',
                    }}
                  >
                    {MODULES[m].tag}
                  </span>
                </label>
              ))}
            </div>
          </fieldset>

          <fieldset className="console-fieldset">
            <legend>Quotas & apparence</legend>
            <div className="console-form-grid">
              <label className="field">
                <span>Établissements</span>
                <input name="quotaEstablishments" type="number" min={10} className="input" defaultValue={1500} />
              </label>
              <label className="field">
                <span>Emails newsletter / mois</span>
                <input name="quotaEmailsMonthly" type="number" min={0} step={1000} className="input" defaultValue={40000} />
              </label>
              <label className="field">
                <span>Crédits IA / mois</span>
                <input name="quotaAiCreditsMonthly" type="number" min={0} step={100} className="input" defaultValue={5000} />
              </label>
              <label className="field">
                <span>Couleur principale</span>
                <input name="colorPrimary" type="color" className="input" defaultValue="#1F6B52" style={{ height: 46, padding: 4 }} />
              </label>
              <label className="field">
                <span>Couleur d’accent</span>
                <input name="colorAccent" type="color" className="input" defaultValue="#F4B266" style={{ height: 46, padding: 4 }} />
              </label>
            </div>
          </fieldset>

          <fieldset className="console-fieldset">
            <legend>Premier administrateur</legend>
            <label className="field" style={{ maxWidth: 420 }}>
              <span>Email de l’administrateur territorial</span>
              <input name="adminEmail" type="email" className="input" placeholder="prenom.nom@cc-exemple.fr" />
              <span className="field-hint">Il reçoit une invitation valable 7 jours ; la double authentification lui sera demandée.</span>
            </label>
          </fieldset>

          <SubmitButton className="btn btn-dark" style={{ alignSelf: 'flex-start' }} pendingLabel="Création du territoire…">
            Créer le territoire
          </SubmitButton>
        </ActionForm>
      </section>
    </div>
  );
}
