import type { Metadata } from 'next';
import Link from 'next/link';
import { addCategoryAction, deleteCategoryAction, saveCategoriesAction } from '../actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { FAMILIES, FAMILY_ORDER } from '@/lib/constants';
import { fmtInt } from '@/lib/format';
import { loadBoContext, requireTerritoryLevel } from '@/server/services/backoffice';
import { territoryCategoryList } from '@/server/services/categories';

export const metadata: Metadata = { title: 'Catégories du portail' };

/** Catégories du territoire : nom affiché sur le portail, catégories masquées, catégories propres. */
export default async function CategoriesPage() {
  const ctx = await loadBoContext();
  requireTerritoryLevel(ctx);
  const admin = ctx.access === 'ADMIN';
  const list = await territoryCategoryList(ctx.territory.id);
  return (
    <div className="app-content">
      <Link href="/collectivite/personnalisation" style={{ fontSize: 13, fontWeight: 600 }}>
        ← Personnalisation
      </Link>
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.6fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
          <div>
            <b>Catégories du portail</b>
            <p style={{ margin: '4px 0 0', fontSize: 13, color: 'var(--muted)' }}>
              Renommez une catégorie pour votre territoire (le nom apparaît sur le portail et les fiches), ou masquez-la : elle n’est plus proposée pour les
              nouvelles fiches, les fiches existantes la conservent.
            </p>
          </div>
          <ActionForm action={saveCategoriesAction} resetOnSuccess={false} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            {FAMILY_ORDER.map((f) => {
              const rows = list.filter((c) => c.family === f);
              if (!rows.length) return null;
              return (
                <fieldset key={f} style={{ border: 0, padding: 0, margin: 0, minWidth: 0 }}>
                  <legend
                    style={{ fontSize: 12, fontWeight: 800, letterSpacing: '0.06em', textTransform: 'uppercase', color: FAMILIES[f].color, marginBottom: 6 }}
                  >
                    {FAMILIES[f].label}
                  </legend>
                  {rows.map((c) => (
                    <div
                      key={c.id}
                      style={{
                        display: 'grid',
                        gridTemplateColumns: 'minmax(0,1fr) auto auto',
                        gap: 12,
                        alignItems: 'center',
                        padding: '6px 0',
                        borderTop: '1px solid var(--line-2)',
                        opacity: c.hidden ? 0.6 : 1,
                      }}
                    >
                      <label style={{ display: 'flex', flexDirection: 'column', gap: 2, minWidth: 0 }}>
                        <span className="sr-only">Nom affiché pour {c.baseName}</span>
                        <input
                          name={`label:${c.id}`}
                          className="input"
                          defaultValue={c.name}
                          placeholder={c.baseName}
                          maxLength={160}
                          disabled={!admin}
                          style={{ padding: '7px 10px' }}
                        />
                        <span style={{ fontSize: 11, color: 'var(--muted)' }}>
                          {c.own ? 'Propre au territoire' : c.name !== c.baseName ? `Nom commun : ${c.baseName}` : 'Catégorie commune'} · {fmtInt(c.count)}{' '}
                          fiche
                          {c.count > 1 ? 's' : ''}
                        </span>
                      </label>
                      <label style={{ display: 'flex', gap: 6, alignItems: 'center', fontSize: 13 }}>
                        <input type="checkbox" name={`hidden:${c.id}`} defaultChecked={c.hidden} disabled={!admin} style={{ accentColor: 'var(--green)' }} />
                        Masquée
                      </label>
                      <span style={{ width: 70, textAlign: 'right' }}>
                        {admin && c.own && c.count === 0 ? (
                          <button type="submit" form={`del-cat-${c.id}`} className="btn-link" style={{ color: 'var(--danger-fg)', fontSize: 12 }}>
                            Supprimer
                          </button>
                        ) : null}
                      </span>
                    </div>
                  ))}
                </fieldset>
              );
            })}
            {admin ? (
              <SubmitButton className="btn btn-brand" pendingLabel="Enregistrement…" style={{ alignSelf: 'flex-start' }}>
                Enregistrer les catégories
              </SubmitButton>
            ) : null}
          </ActionForm>
          {/* Formulaires de suppression hors du formulaire principal (pas de formulaires imbriqués). */}
          {list
            .filter((c) => admin && c.own && c.count === 0)
            .map((c) => (
              <form key={c.id} id={`del-cat-${c.id}`} action={deleteCategoryAction} hidden>
                <input type="hidden" name="categoryId" value={c.id} />
              </form>
            ))}
        </section>
        {admin ? (
          <section className="bo-card">
            <b>Ajouter une catégorie</b>
            <p style={{ margin: '4px 0 12px', fontSize: 13, color: 'var(--muted)' }}>
              Une activité propre à votre territoire (ex. « Fruitière à comté », « Tavaillonneur »). Elle devient une page du portail pour chaque commune.
            </p>
            <ActionForm action={addCategoryAction} style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              <label className="field">
                <span>Famille</span>
                <select name="family" className="input" defaultValue="ARTISAN">
                  {FAMILY_ORDER.map((f) => (
                    <option key={f} value={f}>
                      {FAMILIES[f].label}
                    </option>
                  ))}
                </select>
              </label>
              <label className="field">
                <span>Nom</span>
                <input name="name" className="input" required minLength={3} maxLength={160} />
              </label>
              <label className="field">
                <span>Autres mots recherchés (séparés par des virgules)</span>
                <input name="synonyms" className="input" maxLength={400} placeholder="ex. fromagerie, comté, affineur" />
              </label>
              <SubmitButton className="btn btn-dark btn-sm" pendingLabel="Ajout…" style={{ alignSelf: 'flex-start' }}>
                Ajouter la catégorie
              </SubmitButton>
            </ActionForm>
          </section>
        ) : null}
      </div>
    </div>
  );
}
