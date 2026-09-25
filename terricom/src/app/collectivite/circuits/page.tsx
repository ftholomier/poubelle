import { and, asc, count, eq, inArray, isNotNull, notInArray, sql } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { addStopAction, createCircuitAction, moveStopAction, removeStopAction, renameCircuitAction, saveCircuitAction } from './actions';
import { StopSearch } from '@/components/bo/StopSearch';
import { MapView } from '@/components/maps/MapView';
import { ActionForm } from '@/components/pro/ActionForm';
import { FileDrop } from '@/components/ui/FileDrop';
import { Photo } from '@/components/ui/Photo';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { FAMILIES } from '@/lib/constants';
import { fmtInt } from '@/lib/format';
import { sized } from '@/lib/images';
import { db } from '@/server/db';
import { categories, circuitStops, circuits, communes, establishments, passports, passportStamps } from '@/server/db/schema';
import { env } from '@/server/env';
import { qrSvg } from '@/server/qr';
import { estScope, loadBoContext } from '@/server/services/backoffice';
import { portalUrl } from '@/server/urls';

export const metadata: Metadata = { title: 'Circuits & parcours' };

type Props = { searchParams: Promise<Record<string, string | undefined>> };

const STATUS = { DRAFT: 'Brouillon', PUBLISHED: 'Publié', ARCHIVED: 'Archivé' } as const;

export default async function CircuitsPage({ searchParams }: Props) {
  const ctx = await loadBoContext();
  const sp = await searchParams;
  const list = await db.select().from(circuits).where(eq(circuits.territoryId, ctx.territory.id)).orderBy(asc(circuits.sortOrder), asc(circuits.name));
  const cur = list.find((c) => c.id === sp.circuit) ?? list.find((c) => c.status === 'PUBLISHED') ?? list[0];

  const header = (
    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
      {list.map((c) => (
        <Link key={c.id} href={`/collectivite/circuits?circuit=${c.id}`} className="bo-chip" aria-current={c.id === cur?.id ? 'true' : undefined}>
          {c.name}
          <small>{STATUS[c.status]}</small>
        </Link>
      ))}
      <form action={createCircuitAction} style={{ marginLeft: 'auto' }}>
        <SubmitButton className="btn btn-brand btn-sm" pendingLabel="Création…">
          + Nouveau circuit
        </SubmitButton>
      </form>
    </div>
  );
  if (!cur)
    return (
      <div className="app-content">
        {header}
        <div className="bo-card">Aucun circuit pour l’instant : créez le premier parcours à tamponner de votre territoire.</div>
      </div>
    );

  const stops = await db
    .select({
      id: circuitStops.id,
      position: circuitStops.position,
      estId: establishments.id,
      name: establishments.name,
      coverUrl: establishments.coverUrl,
      lat: establishments.lat,
      lng: establishments.lng,
      commune: communes.name,
      family: categories.family,
      activity: establishments.activityLabel,
    })
    .from(circuitStops)
    .innerJoin(establishments, eq(establishments.id, circuitStops.establishmentId))
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .innerJoin(categories, eq(categories.id, establishments.categoryId))
    .where(eq(circuitStops.circuitId, cur.id))
    .orderBy(asc(circuitStops.position));
  const stopEstIds = stops.map((s) => s.estId);
  const [suggestions, [opened], [stamps], [rewards], qr] = await Promise.all([
    db
      .select({ id: establishments.id, name: establishments.name })
      .from(establishments)
      .where(
        and(
          estScope(ctx),
          inArray(establishments.status, ['CLAIMED', 'VALIDATED']),
          isNotNull(establishments.lat),
          stopEstIds.length ? notInArray(establishments.id, stopEstIds) : undefined,
        ),
      )
      .orderBy(sql`${establishments.completeness} desc`, sql`${establishments.lastActivityAt} desc nulls last`)
      .limit(6),
    db.select({ n: count() }).from(passports).where(eq(passports.circuitId, cur.id)),
    db.select({ n: count() }).from(passportStamps).innerJoin(passports, eq(passports.id, passportStamps.passportId)).where(eq(passports.circuitId, cur.id)),
    db
      .select({ n: count() })
      .from(passports)
      .where(and(eq(passports.circuitId, cur.id), isNotNull(passports.completedAt))),
    qrSvg(portalUrl(ctx.territory, `/circuits/${cur.slug}`)),
  ]);
  const points = stops
    .filter((s) => s.lat !== null && s.lng !== null)
    .map((s) => ({
      id: s.estId,
      lat: s.lat!,
      lng: s.lng!,
      name: s.name,
      color: FAMILIES[s.family].color,
      subtitle: `${s.activity ?? ''} · ${s.commune}`,
      image: sized(s.coverUrl, 460, 220),
    }));

  return (
    <div className="app-content">
      {header}
      <div className="split" style={{ ['--cols' as string]: '360px minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <section className="bo-card" style={{ padding: 18, display: 'flex', flexDirection: 'column', gap: 10, borderRadius: 20 }}>
            <form action={renameCircuitAction}>
              <input type="hidden" name="circuitId" value={cur.id} />
              <label htmlFor="circ-name" className="sr-only">
                Nom du circuit
              </label>
              <input
                id="circ-name"
                name="name"
                defaultValue={cur.name}
                className="display"
                style={{ border: 0, fontSize: 22, padding: 0, background: 'transparent', outline: 'none', width: '100%' }}
              />
            </form>
            <div style={{ fontSize: 13, color: 'var(--muted)' }}>Étapes · {stops.length}</div>
            {stops.map((s, i) => (
              <div
                key={s.id}
                style={{
                  display: 'grid',
                  gridTemplateColumns: '30px 48px 1fr auto',
                  gap: 10,
                  alignItems: 'center',
                  padding: 8,
                  border: '1px solid var(--line-2)',
                  borderRadius: 12,
                  background: '#fff',
                }}
              >
                <div
                  style={{
                    width: 28,
                    height: 28,
                    borderRadius: '50%',
                    background: 'var(--ink)',
                    color: 'var(--amber)',
                    display: 'grid',
                    placeItems: 'center',
                    fontWeight: 800,
                    fontSize: 12,
                  }}
                >
                  {i + 1}
                </div>
                <span style={{ width: 48, height: 40, borderRadius: 8, overflow: 'hidden' }}>
                  <Photo src={sized(s.coverUrl, 100, 80)} alt="" label={s.name} color={FAMILIES[s.family].color} />
                </span>
                <div style={{ minWidth: 0 }}>
                  <div style={{ fontWeight: 700, fontSize: 13, whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{s.name}</div>
                  <div style={{ fontSize: 11, color: 'var(--muted)' }}>{s.commune}</div>
                </div>
                <div style={{ display: 'flex', gap: 2 }}>
                  <form action={moveStopAction}>
                    <input type="hidden" name="circuitId" value={cur.id} />
                    <input type="hidden" name="stopId" value={s.id} />
                    <button
                      type="submit"
                      aria-label={`Monter ${s.name}`}
                      disabled={i === 0}
                      style={{
                        border: 0,
                        background: 'var(--sand)',
                        borderRadius: 6,
                        width: 24,
                        height: 24,
                        cursor: 'pointer',
                        fontSize: 11,
                        opacity: i === 0 ? 0.4 : 1,
                      }}
                    >
                      ↑
                    </button>
                  </form>
                  <form action={removeStopAction}>
                    <input type="hidden" name="circuitId" value={cur.id} />
                    <input type="hidden" name="stopId" value={s.id} />
                    <button
                      type="submit"
                      aria-label={`Retirer ${s.name}`}
                      style={{
                        border: 0,
                        background: 'var(--danger-bg)',
                        color: 'var(--danger-fg)',
                        borderRadius: 6,
                        width: 24,
                        height: 24,
                        cursor: 'pointer',
                        fontSize: 11,
                      }}
                    >
                      ×
                    </button>
                  </form>
                </div>
              </div>
            ))}
            <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--muted)', marginTop: 4 }}>Ajouter une étape</div>
            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
              {suggestions.map((a) => (
                <form key={a.id} action={addStopAction}>
                  <input type="hidden" name="circuitId" value={cur.id} />
                  <input type="hidden" name="estId" value={a.id} />
                  <button
                    type="submit"
                    style={{
                      cursor: 'pointer',
                      border: '1px dashed var(--green)',
                      background: 'var(--mint-2)',
                      color: 'var(--green)',
                      padding: '6px 10px',
                      borderRadius: 999,
                      fontSize: 12,
                      fontWeight: 700,
                    }}
                  >
                    + {a.name}
                  </button>
                </form>
              ))}
            </div>
            <StopSearch circuitId={cur.id} />
          </section>
          <section className="bo-card" style={{ padding: 18, borderRadius: 20 }}>
            <details>
              <summary style={{ cursor: 'pointer', fontWeight: 700 }}>Paramètres du circuit · {STATUS[cur.status]}</summary>
              <ActionForm action={saveCircuitAction} resetOnSuccess={false} style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 10 }}>
                <input type="hidden" name="circuitId" value={cur.id} />
                <label className="field">
                  <span>Sous-titre</span>
                  <input name="meta" className="input" defaultValue={cur.meta ?? ''} maxLength={255} placeholder="7 étapes gourmandes au fil de l’eau" />
                </label>
                <label className="field">
                  <span>Présentation</span>
                  <textarea name="description" className="input" rows={3} defaultValue={cur.description} maxLength={3000} />
                </label>
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                  <label className="field">
                    <span>Distance (km)</span>
                    <input name="distanceKm" className="input" inputMode="decimal" defaultValue={cur.distanceKm ?? ''} />
                  </label>
                  <label className="field">
                    <span>Durée</span>
                    <input name="durationText" className="input" defaultValue={cur.durationText ?? ''} placeholder="1 journée" />
                  </label>
                  <label className="field">
                    <span>Mode</span>
                    <input name="travelMode" className="input" defaultValue={cur.travelMode ?? ''} placeholder="Vélo ou voiture" />
                  </label>
                  <label className="field">
                    <span>Tampons pour la récompense</span>
                    <input name="rewardThreshold" className="input" inputMode="numeric" defaultValue={cur.rewardThreshold ?? ''} />
                  </label>
                </div>
                <label className="field">
                  <span>Récompense</span>
                  <input name="rewardText" className="input" defaultValue={cur.rewardText ?? ''} maxLength={255} />
                </label>
                <label className="field">
                  <span>Statut</span>
                  <select name="status" className="input" defaultValue={cur.status}>
                    <option value="DRAFT">Brouillon</option>
                    <option value="PUBLISHED">Publié sur le portail</option>
                    <option value="ARCHIVED">Archivé</option>
                  </select>
                </label>
                <FileDrop name="image" accept="image/jpeg,image/png,image/webp" label="Visuel du circuit" />
                <SubmitButton className="btn btn-dark btn-sm" pendingLabel="Enregistrement…" style={{ alignSelf: 'flex-start' }}>
                  Enregistrer
                </SubmitButton>
              </ActionForm>
            </details>
          </section>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0 }}>
          <div style={{ height: 480, borderRadius: 22, overflow: 'hidden', border: '1px solid var(--line)' }}>
            <MapView
              key={points.map((p) => p.id).join(',')}
              mode="circuit"
              tileUrl={env.MAP_TILE_URL}
              attribution={env.MAP_TILE_ATTRIBUTION}
              points={points}
              center={ctx.territory.centerLat && ctx.territory.centerLng ? [ctx.territory.centerLat, ctx.territory.centerLng] : undefined}
              style={{ height: '100%' }}
              ariaLabel={`Carte du circuit ${cur.name}`}
            />
          </div>
          <div
            style={{
              display: 'grid',
              gridTemplateColumns: 'auto repeat(3,1fr)',
              gap: 14,
              alignItems: 'center',
              background: 'var(--paper)',
              border: '1px solid var(--line)',
              borderRadius: 20,
              padding: 16,
            }}
          >
            <div role="img" aria-label="QR code du circuit" style={{ width: 84, height: 84 }} dangerouslySetInnerHTML={{ __html: qr }} />
            {[
              [opened?.n ?? 0, 'passeports ouverts'],
              [stamps?.n ?? 0, 'tampons scannés'],
              [rewards?.n ?? 0, cur.rewardText?.toLowerCase().includes('panier') ? 'paniers gagnés' : 'récompenses gagnées'],
            ].map(([v, l]) => (
              <div key={l as string}>
                <div className="display" style={{ fontSize: 28 }}>
                  {fmtInt(Number(v))}
                </div>
                <div style={{ fontSize: 12, color: 'var(--muted)' }}>{l}</div>
              </div>
            ))}
          </div>
          <div style={{ display: 'flex', gap: 12, flexWrap: 'wrap', fontSize: 13 }}>
            <a href={`/api/collectivite/circuits/${cur.id}/tampons.pdf`} style={{ fontWeight: 700 }}>
              Imprimer les tampons des étapes (PDF)
            </a>
            {cur.status === 'PUBLISHED' ? (
              <a href={portalUrl(ctx.territory, `/circuits/${cur.slug}`)} target="_blank" rel="noopener noreferrer">
                Voir le circuit sur le portail ↗
              </a>
            ) : (
              <span style={{ color: 'var(--muted)' }}>Brouillon : publiez-le depuis les paramètres.</span>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
