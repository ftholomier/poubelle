import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { createDealAction } from '../actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { DEAL_STAGE_LABELS, TERRITORY_KINDS, type TerritoryKind } from '@/lib/constants';
import { requirePlatformStaff } from '@/server/authz';

export const metadata: Metadata = { title: 'Nouvelle affaire' };

const STAGES = ['PROSPECT', 'FIRST_CONTACT', 'DEMO', 'PROPOSAL', 'NEGOTIATION'] as const;
const SOURCES = ['Salon des maires', 'Recommandation', 'Démo en ligne', 'Appel entrant', 'Formulaire terricom.fr', 'Congrès', 'Prospection'];

export default async function NewDealPage() {
  const actor = await requirePlatformStaff();
  if (!actor.isPlatformAdmin && !actor.roles.some((r) => r.role === 'PLATFORM_SALES')) notFound();
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 14, maxWidth: 900 }}>
      <Link href="/console?crm=pro" style={{ fontSize: 13, fontWeight: 600 }}>
        ← Suivi commercial
      </Link>
      <section className="console-card" style={{ padding: 24 }}>
        <h2 className="display" style={{ fontSize: 28, margin: '0 0 4px', letterSpacing: '-0.02em' }}>
          Nouvelle affaire
        </h2>
        <p style={{ margin: '0 0 18px', color: 'var(--muted)', fontSize: 14 }}>
          Une collectivité rencontrée, un appel entrant, une recommandation : les actions types de l’étape sont ajoutées automatiquement.
        </p>
        <ActionForm action={createDealAction} resetOnSuccess={false} style={{ display: 'flex', flexDirection: 'column', gap: 20 }}>
          <fieldset className="console-fieldset">
            <legend>Collectivité</legend>
            <div className="console-form-grid">
              <label className="field">
                <span>Nom *</span>
                <input name="name" className="input" required maxLength={255} placeholder="Pays de Morlaix" />
              </label>
              <label className="field">
                <span>Type</span>
                <select name="kind" className="input" defaultValue="CC">
                  {(Object.keys(TERRITORY_KINDS) as TerritoryKind[]).map((k) => (
                    <option key={k} value={k}>
                      {TERRITORY_KINDS[k].label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="field">
                <span>Communes</span>
                <input name="communesCount" type="number" min={1} className="input" defaultValue={20} />
              </label>
              <label className="field">
                <span>Habitants</span>
                <input name="population" type="number" min={0} className="input" />
              </label>
            </div>
          </fieldset>
          <fieldset className="console-fieldset">
            <legend>Affaire</legend>
            <div className="console-form-grid">
              <label className="field">
                <span>Étape</span>
                <select name="stage" className="input" defaultValue="PROSPECT">
                  {STAGES.map((s) => (
                    <option key={s} value={s}>
                      {DEAL_STAGE_LABELS[s]}
                    </option>
                  ))}
                </select>
              </label>
              <label className="field">
                <span>Licence envisagée (€ HT / an)</span>
                <input name="licence" type="number" min={0} step={100} className="input" defaultValue={8000} />
              </label>
              <label className="field">
                <span>Origine</span>
                <select name="source" className="input" defaultValue="">
                  <option value="">—</option>
                  {SOURCES.map((s) => (
                    <option key={s}>{s}</option>
                  ))}
                </select>
              </label>
            </div>
          </fieldset>
          <fieldset className="console-fieldset">
            <legend>Interlocuteur principal</legend>
            <div className="console-form-grid">
              <label className="field">
                <span>Nom</span>
                <input name="contactName" className="input" placeholder="Prénom Nom" />
              </label>
              <label className="field">
                <span>Fonction</span>
                <input name="contactRole" className="input" placeholder="Vice-président·e développement économique" />
              </label>
              <label className="field">
                <span>Email</span>
                <input name="contactEmail" type="email" className="input" />
              </label>
              <label className="field">
                <span>Téléphone</span>
                <input name="contactPhone" className="input" inputMode="tel" />
              </label>
            </div>
            <label className="field">
              <span>Notes</span>
              <textarea name="notes" className="textarea" rows={3} placeholder="Contexte, enjeux, calendrier budgétaire…" />
            </label>
          </fieldset>
          <SubmitButton className="btn btn-dark" style={{ alignSelf: 'flex-start' }} pendingLabel="Création…">
            Créer l’affaire
          </SubmitButton>
        </ActionForm>
      </section>
    </div>
  );
}
