'use client';

import { useRouter } from 'next/navigation';
import { useActionState, useEffect } from 'react';
import { commitImportAction, remapImportAction, uploadImportAction, type BoState } from '@/app/collectivite/entreprises/actions';
import { FileDrop } from '@/components/ui/FileDrop';

const idle: BoState = { status: 'idle' };

function Err({ state }: { state: BoState }) {
  if (state.status !== 'error') return null;
  return (
    <div role="alert" style={{ color: 'var(--rose)', fontSize: 13, fontWeight: 600 }}>
      {state.message}
    </div>
  );
}

export function UploadForm() {
  const [state, action, pending] = useActionState(uploadImportAction, idle);
  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
      <FileDrop name="file" accept=".csv,text/csv,text/plain" label="Déposer un fichier CSV (export SIRENE, tableur…)" required dark />
      <button type="submit" className="btn btn-amber" disabled={pending} style={{ justifyContent: 'center' }}>
        {pending ? 'Analyse du fichier…' : 'Analyser le fichier'}
      </button>
      <Err state={state} />
    </form>
  );
}

export function MappingForm({
  batchId,
  headers,
  fields,
  mapping,
  categories,
  defaultCategoryId,
}: {
  batchId: string;
  headers: string[];
  fields: { key: string; label: string; required?: boolean }[];
  mapping: Record<string, string | undefined>;
  categories: { id: string; name: string }[];
  defaultCategoryId: string | null;
}) {
  const [state, action, pending] = useActionState(remapImportAction, idle);
  const options = [...headers, ...(mapping.street === '__adresse_sirene__' ? ['__adresse_sirene__'] : [])];
  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 6, fontSize: 13 }}>
      <input type="hidden" name="batchId" value={batchId} />
      {fields.map((f) => (
        <label key={f.key} style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8, alignItems: 'center' }}>
          <span style={{ color: 'var(--sage-2)' }}>
            {f.label}
            {f.required ? ' *' : ''}
          </span>
          <select name={`map_${f.key}`} defaultValue={mapping[f.key] ?? ''} style={{ background: 'var(--dark-5)', color: 'var(--cream)', border: '1px solid var(--dark-4)', borderRadius: 8, padding: '5px 6px', fontSize: 12 }}>
            <option value="">— ignorer —</option>
            {options.map((h) => (
              <option key={h} value={h}>
                {h === '__adresse_sirene__' ? 'Adresse (colonnes SIRENE)' : h}
              </option>
            ))}
          </select>
        </label>
      ))}
      <label style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8, alignItems: 'center', marginTop: 4 }}>
        <span style={{ color: 'var(--sage-2)' }}>Catégorie par défaut</span>
        <select name="defaultCategoryId" defaultValue={defaultCategoryId ?? ''} style={{ background: 'var(--dark-5)', color: 'var(--cream)', border: '1px solid var(--dark-4)', borderRadius: 8, padding: '5px 6px', fontSize: 12 }}>
          <option value="">Aucune (ligne en erreur)</option>
          {categories.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
      </label>
      <button type="submit" disabled={pending} style={{ alignSelf: 'flex-start', marginTop: 6, background: 'transparent', color: 'var(--amber)', border: '1px solid var(--dark-4)', borderRadius: 8, padding: '6px 10px', fontWeight: 700, cursor: 'pointer' }}>
        {pending ? 'Analyse…' : 'Relancer le contrôle'}
      </button>
      <Err state={state} />
    </form>
  );
}

export function CommitForm({ batchId, count }: { batchId: string; count: number }) {
  const [state, action, pending] = useActionState(commitImportAction, idle);
  return (
    <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 8, justifyContent: 'flex-end' }}>
      <input type="hidden" name="batchId" value={batchId} />
      <button type="submit" disabled={pending || count === 0} style={{ border: 0, background: 'var(--amber)', color: 'var(--ink)', padding: 12, borderRadius: 10, fontWeight: 800, cursor: 'pointer', opacity: pending || !count ? 0.7 : 1 }}>
        {pending ? 'Création en cours…' : `Créer ${count.toLocaleString('fr-FR')} fiche${count > 1 ? 's' : ''} précréée${count > 1 ? 's' : ''}`}
      </button>
      <label style={{ display: 'flex', gap: 8, fontSize: 12, color: 'var(--sage)' }}>
        <input type="checkbox" name="invite" defaultChecked style={{ accentColor: 'var(--amber)' }} />
        Inviter chaque entreprise à revendiquer sa fiche (email + courrier)
      </label>
      <Err state={state} />
    </form>
  );
}

/** Rafraîchit la page tant que l'import SIRENE tourne en arrière-plan. */
export function ImportPoller() {
  const router = useRouter();
  useEffect(() => {
    const id = window.setInterval(() => router.refresh(), 4000);
    return () => window.clearInterval(id);
  }, [router]);
  return null;
}
