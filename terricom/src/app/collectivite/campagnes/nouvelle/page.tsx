import type { Metadata } from 'next';
import Link from 'next/link';
import { createCampaignAction } from '../actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { tomorrowIso } from '@/lib/format';

export const metadata: Metadata = { title: 'Nouvelle campagne' };

export default function NewCampaignPage() {
  const start = tomorrowIso();
  return (
    <div className="app-content" style={{ maxWidth: 720 }}>
      <Link href="/collectivite/campagnes" style={{ fontSize: 13, fontWeight: 600 }}>
        ← Toutes les campagnes
      </Link>
      <section className="bo-card">
        <h2 className="display" style={{ fontSize: 24, margin: '0 0 4px' }}>
          Nouvelle campagne
        </h2>
        <p style={{ margin: '0 0 16px', color: 'var(--muted)', fontSize: 14 }}>
          Astuce : l&apos;assistant territorial peut préparer la sélection, la page et le plan de diffusion à votre place.
        </p>
        <ActionForm action={createCampaignAction} resetOnSuccess={false} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          <label className="field">
            <span>Nom de la campagne</span>
            <input name="name" className="input" required maxLength={200} placeholder="Ex. Semaine de l’artisanat" />
          </label>
          <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12 }}>
            <label className="field">
              <span>Début</span>
              <input name="startsAt" type="date" className="input" defaultValue={start} required />
            </label>
            <label className="field">
              <span>Fin</span>
              <input name="endsAt" type="date" className="input" required />
            </label>
          </div>
          <SubmitButton className="btn btn-brand" style={{ alignSelf: 'flex-start' }} pendingLabel="Création…">
            Créer et configurer
          </SubmitButton>
        </ActionForm>
      </section>
    </div>
  );
}
