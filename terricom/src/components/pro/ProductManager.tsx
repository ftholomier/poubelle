'use client';

import { useActionState, useState } from 'react';
import { deleteProduct, saveProduct, type ActionState } from '@/app/pro/[est]/actions';
import { FileDrop } from '@/components/ui/FileDrop';
import { Photo } from '@/components/ui/Photo';

type Product = { id: string; name: string; priceText: string | null; description: string | null; imageUrl: string | null; kind: string };

/** Produits phares et prestations affichés sur la fiche. */
export function ProductManager({ estId, products }: { estId: string; products: Product[] }) {
  const [editing, setEditing] = useState<Product | 'new' | null>(null);
  const [state, action, pending] = useActionState<ActionState, FormData>(
    async (prev, form) => {
      const res = await saveProduct(prev, form);
      if (res.status === 'ok') setEditing(null);
      return res;
    },
    { status: 'idle' },
  );
  const current = editing && editing !== 'new' ? editing : null;
  return (
    <section id="produits" className="panel" style={{ scrollMarginTop: 90 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10 }}>
        <h2 className="panel-title">Produits &amp; savoir-faire</h2>
        {!editing ? (
          <button type="button" className="btn-link" style={{ fontSize: 13 }} onClick={() => setEditing('new')}>
            + Ajouter
          </button>
        ) : null}
      </div>
      {products.length ? (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(180px,1fr))', gap: 10 }}>
          {products.map((pr) => (
            <div key={pr.id} className="card" style={{ borderRadius: 14, overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
              <div style={{ height: 96 }}>
                <Photo src={pr.imageUrl} alt="" label={pr.name} color="#C8892A" />
              </div>
              <div style={{ padding: '10px 12px', display: 'flex', flexDirection: 'column', gap: 2, flex: 1 }}>
                <b style={{ fontSize: 14 }}>{pr.name}</b>
                {pr.priceText ? <span style={{ fontSize: 12, color: 'var(--muted)' }}>{pr.priceText}</span> : null}
                <div style={{ display: 'flex', gap: 10, marginTop: 'auto', paddingTop: 6, fontSize: 12 }}>
                  <button type="button" className="btn-link" onClick={() => setEditing(pr)}>
                    Modifier
                  </button>
                  <form action={deleteProduct} onSubmit={(e) => !confirm('Supprimer ce produit ?') && e.preventDefault()}>
                    <input type="hidden" name="estId" value={estId} />
                    <input type="hidden" name="productId" value={pr.id} />
                    <button type="submit" className="btn-link" style={{ color: 'var(--danger-fg)' }}>
                      Supprimer
                    </button>
                  </form>
                </div>
              </div>
            </div>
          ))}
        </div>
      ) : !editing ? (
        <p style={{ margin: 0, fontSize: 14, color: 'var(--muted)' }}>
          Présentez 3 produits ou prestations phares : ils apparaissent sur votre fiche et aident les habitants à vous trouver.
        </p>
      ) : null}
      {editing ? (
        <form
          key={current?.id ?? 'new'}
          action={action}
          style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 10, borderTop: '1px solid var(--line-2)', paddingTop: 12 }}
        >
          <input type="hidden" name="estId" value={estId} />
          <input type="hidden" name="productId" value={current?.id ?? ''} />
          <input name="name" className="input" placeholder="Nom (ex. Pain au levain)" defaultValue={current?.name ?? ''} required maxLength={255} />
          <input name="priceText" className="input" placeholder="Prix (ex. 4,20 € / kg)" defaultValue={current?.priceText ?? ''} maxLength={120} />
          <select name="kind" className="select" defaultValue={current?.kind ?? 'PRODUCT'}>
            <option value="PRODUCT">Produit</option>
            <option value="SERVICE">Prestation</option>
          </select>
          <input
            name="description"
            className="input"
            placeholder="Courte description (facultatif)"
            defaultValue={current?.description ?? ''}
            maxLength={1000}
            style={{ gridColumn: '1 / -1' }}
          />
          <FileDrop name="image" accept="image/*" label="+ Photo (facultatif)" />
          <div style={{ display: 'flex', gap: 8, alignItems: 'end' }}>
            <button type="submit" className="btn btn-brand btn-sm" disabled={pending}>
              {pending ? '…' : 'Enregistrer'}
            </button>
            <button type="button" className="btn btn-ghost btn-sm" onClick={() => setEditing(null)}>
              Annuler
            </button>
          </div>
          {state.status === 'error' ? (
            <div className="alert alert-error" style={{ gridColumn: '1 / -1' }}>
              {state.message}
            </div>
          ) : null}
        </form>
      ) : null}
    </section>
  );
}
