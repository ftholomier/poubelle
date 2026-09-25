'use client';

import { useActionState, useRef } from 'react';
import { photoAction, uploadPhotos, type ActionState } from '@/app/pro/[est]/actions';
import { Photo } from '@/components/ui/Photo';
import { Icon } from '@/components/ui/Icon';

type Item = { id: string; src: string; tag: string | null };

const TAGS = ['', 'Intérieur', 'Équipe', 'Produit', 'Façade', 'Atelier'];

/** Galerie de la fiche : ajout, ordre (la première est la photo principale), étiquettes, suppression. */
export function PhotoManager({ estId, photos, max }: { estId: string; photos: Item[]; max: number }) {
  const [state, upload, pending] = useActionState<ActionState, FormData>(uploadPhotos, { status: 'idle' });
  const formRef = useRef<HTMLFormElement>(null);
  return (
    <section id="photos" className="panel" style={{ scrollMarginTop: 90, gap: 12 }}>
      <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10 }}>
        <h2 className="panel-title">Photos</h2>
        <span style={{ fontSize: 13, color: 'var(--muted)' }}>
          {photos.length} / {max} · la première est la photo principale
        </span>
      </div>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill,minmax(120px,1fr))', gap: 10 }}>
        {photos.map((ph, i) => (
          <div key={ph.id} style={{ position: 'relative', aspectRatio: '1', borderRadius: 12, overflow: 'hidden', background: 'var(--sand)' }}>
            <Photo src={ph.src} alt="" label={String(i + 1)} />
            {i === 0 || ph.tag ? (
              <span
                style={{
                  position: 'absolute',
                  left: 6,
                  top: 6,
                  background: 'var(--amber)',
                  fontSize: 10,
                  fontWeight: 800,
                  padding: '2px 6px',
                  borderRadius: 4,
                  color: 'var(--ink)',
                }}
              >
                {i === 0 ? 'Principale' : ph.tag}
              </span>
            ) : null}
            <div style={{ position: 'absolute', right: 6, top: 6, display: 'flex', gap: 4 }}>
              {i > 0 ? (
                <form action={photoAction}>
                  <input type="hidden" name="estId" value={estId} />
                  <input type="hidden" name="photoId" value={ph.id} />
                  <button
                    name="op"
                    value="first"
                    className="btn btn-light btn-xs"
                    aria-label="Choisir comme photo principale"
                    title="Choisir comme photo principale"
                    style={{ padding: 4 }}
                  >
                    <Icon name="star" size={13} />
                  </button>
                </form>
              ) : null}
              <form action={photoAction} onSubmit={(e) => !confirm('Supprimer cette photo ?') && e.preventDefault()}>
                <input type="hidden" name="estId" value={estId} />
                <input type="hidden" name="photoId" value={ph.id} />
                <button
                  name="op"
                  value="delete"
                  className="btn btn-light btn-xs"
                  aria-label="Supprimer"
                  title="Supprimer"
                  style={{ padding: 4, color: 'var(--danger-fg)' }}
                >
                  <Icon name="trash" size={13} />
                </button>
              </form>
            </div>
            <div
              style={{
                position: 'absolute',
                left: 0,
                right: 0,
                bottom: 0,
                display: 'flex',
                gap: 4,
                alignItems: 'center',
                padding: 6,
                background: 'linear-gradient(180deg,transparent,rgba(20,32,27,.75))',
              }}
            >
              {i > 0 ? (
                <form action={photoAction}>
                  <input type="hidden" name="estId" value={estId} />
                  <input type="hidden" name="photoId" value={ph.id} />
                  <button name="op" value="up" className="btn btn-light btn-xs" aria-label="Déplacer avant" title="Déplacer avant" style={{ padding: 4 }}>
                    <Icon name="arrowLeft" size={13} />
                  </button>
                </form>
              ) : null}
              <form action={photoAction} style={{ flex: 1, minWidth: 0 }}>
                <input type="hidden" name="estId" value={estId} />
                <input type="hidden" name="photoId" value={ph.id} />
                <input type="hidden" name="op" value="tag" />
                <select
                  name="tag"
                  defaultValue={ph.tag ?? ''}
                  aria-label="Étiquette de la photo"
                  onChange={(e) => e.currentTarget.form?.requestSubmit()}
                  style={{ fontSize: 11, borderRadius: 6, border: 0, padding: '4px 2px', width: '100%' }}
                >
                  {TAGS.map((t) => (
                    <option key={t} value={t}>
                      {t || 'Étiquette'}
                    </option>
                  ))}
                </select>
              </form>
            </div>
          </div>
        ))}
        {photos.length < max ? (
          <form ref={formRef} action={upload} style={{ aspectRatio: '1' }}>
            <input type="hidden" name="estId" value={estId} />
            <label
              style={{
                height: '100%',
                borderRadius: 12,
                border: '2px dashed var(--sand-3)',
                display: 'grid',
                placeItems: 'center',
                textAlign: 'center',
                color: 'var(--muted)',
                fontSize: 13,
                fontWeight: 600,
                cursor: pending ? 'progress' : 'pointer',
                padding: 8,
              }}
            >
              {pending ? 'Envoi…' : '+ Ajouter'}
              <input
                type="file"
                name="photos"
                accept="image/jpeg,image/png,image/webp,image/avif,image/heic,image/heif"
                multiple
                className="sr-only"
                disabled={pending}
                onChange={() => formRef.current?.requestSubmit()}
              />
            </label>
          </form>
        ) : null}
      </div>
      {state.status !== 'idle' ? (
        <div className={`alert ${state.status === 'ok' ? 'alert-ok' : 'alert-error'}`} role="status">
          {state.message}
        </div>
      ) : null}
      <p style={{ margin: 0, fontSize: 12, color: 'var(--muted)' }}>
        JPEG, PNG, WebP ou HEIC, 10 Mo maximum. Les photos sont redimensionnées et les données de localisation retirées automatiquement.
      </p>
    </section>
  );
}
