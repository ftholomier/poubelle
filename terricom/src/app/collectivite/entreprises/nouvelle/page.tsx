import { and, asc, eq, isNull, or } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { createEstablishmentAction } from '../actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { FAMILIES, type Family } from '@/lib/constants';
import { db } from '@/server/db';
import { categories } from '@/server/db/schema';
import { loadBoContext } from '@/server/services/backoffice';

export const metadata: Metadata = { title: 'Ajouter une entreprise' };

export default async function NewEstablishmentPage() {
  const ctx = await loadBoContext();
  const cats = await db
    .select({
      id: categories.id,
      name: categories.name,
      family: categories.family,
    })
    .from(categories)
    .where(and(eq(categories.isActive, true), or(isNull(categories.territoryId), eq(categories.territoryId, ctx.territory.id))))
    .orderBy(asc(categories.name));
  const byFamily = new Map<Family, typeof cats>();
  for (const c of cats) byFamily.set(c.family, [...(byFamily.get(c.family) ?? []), c]);
  return (
    <div className="app-content" style={{ maxWidth: 820 }}>
      <Link href="/collectivite/entreprises" style={{ fontSize: 13, fontWeight: 600 }}>
        ← Toutes les entreprises
      </Link>
      <section className="bo-card">
        <h2 className="display" style={{ fontSize: 24, margin: '0 0 4px' }}>
          Ajouter un établissement
        </h2>
        <p style={{ margin: '0 0 16px', color: 'var(--muted)', fontSize: 14 }}>
          La fiche est créée « précréée » : l&apos;entreprise pourra ensuite la revendiquer gratuitement.
        </p>
        <ActionForm action={createEstablishmentAction} resetOnSuccess={false} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fit,minmax(240px,1fr))',
              gap: 12,
            }}
          >
            <label className="field">
              <span>Nom *</span>
              <input name="name" className="input" required maxLength={160} />
            </label>
            <label className="field">
              <span>SIRET</span>
              <input name="siret" className="input" inputMode="numeric" placeholder="14 chiffres" />
            </label>
            <label className="field">
              <span>Catégorie *</span>
              <select name="categoryId" className="input" required defaultValue="">
                <option value="" disabled>
                  Choisir…
                </option>
                {[...byFamily.entries()].map(([f, list]) => (
                  <optgroup key={f} label={FAMILIES[f].label}>
                    {list.map((c) => (
                      <option key={c.id} value={c.id}>
                        {c.name}
                      </option>
                    ))}
                  </optgroup>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Commune *</span>
              <select name="communeId" className="input" required defaultValue={ctx.commune?.id ?? ''}>
                <option value="" disabled>
                  Choisir…
                </option>
                {ctx.communes.map((c) => (
                  <option key={c.id} value={c.id}>
                    {c.name}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Adresse</span>
              <input name="street" className="input" maxLength={255} />
            </label>
            <label className="field">
              <span>Téléphone</span>
              <input name="phone" className="input" inputMode="tel" />
            </label>
            <label className="field">
              <span>Email</span>
              <input name="email" type="email" className="input" />
            </label>
            <label className="field">
              <span>Site web</span>
              <input name="website" type="url" className="input" placeholder="https://" />
            </label>
          </div>
          <SubmitButton className="btn btn-brand" style={{ alignSelf: 'flex-start' }} pendingLabel="Création…">
            Créer la fiche
          </SubmitButton>
        </ActionForm>
      </section>
    </div>
  );
}
