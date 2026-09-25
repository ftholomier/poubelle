'use client';

import Link from 'next/link';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { MapView, OVERLAY_STYLE, type MapPoint, type OverlayKind, type OverlayPoint } from '@/components/maps/MapView';
import { Icon } from '@/components/ui/Icon';
import { Photo } from '@/components/ui/Photo';
import type { Family } from '@/lib/constants';
import {
  EXPLORER_FAMILIES,
  EXPLORER_TOGGLES,
  explorerQueryString,
  isFilteredState,
  type ExplorerFilterGroup,
  type ExplorerResponse,
  type ExplorerState,
  type ExplorerToggle,
} from '@/lib/explorer';
import { sized } from '@/lib/images';

type Props = {
  territorySlug: string;
  base: string;
  initialState: ExplorerState;
  initial: ExplorerResponse;
  filtered: boolean;
  points: MapPoint[];
  map: { tileUrl: string; attribution: string };
  aiEnabled: boolean;
  communes: { slug: string; name: string }[];
  filters: ExplorerFilterGroup[];
  overlays: OverlayPoint[];
};

/** P2 — Explorer : liste filtrable, carte synchronisée et réponse de l'assistant. */
export function ExplorerClient({ territorySlug, base, initialState, initial, filtered, points, map, aiEnabled, communes, filters, overlays }: Props) {
  const [state, setState] = useState<ExplorerState>(initialState);
  const [query, setQuery] = useState(initialState.q);
  const [data, setData] = useState<ExplorerResponse>(initial);
  const [visibleIds, setVisibleIds] = useState<string[] | null>(filtered ? initial.ids : null);
  const [loading, setLoading] = useState(false);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [focusId, setFocusId] = useState<string | null>(null);
  const [mapHidden, setMapHidden] = useState(true);
  const [locating, setLocating] = useState(false);
  const [moreOpen, setMoreOpen] = useState(false);
  const [layers, setLayers] = useState<OverlayKind[]>([]);
  const layerCounts = useMemo(() => {
    const c: Partial<Record<OverlayKind, number>> = {};
    for (const o of overlays) c[o.kind] = (c[o.kind] ?? 0) + 1;
    return c;
  }, [overlays]);
  const firstRun = useRef(true);
  const reqId = useRef(0);

  const fetchPage = useCallback(
    async (s: ExplorerState, offset: number) => {
      const res = await fetch(`/api/portal/search${explorerQueryString(s, { t: territorySlug, offset: String(offset) })}`, {
        headers: { accept: 'application/json' },
      });
      if (!res.ok) {
        const body = (await res.json().catch(() => null)) as { error?: string } | null;
        throw new Error(body?.error ?? 'La recherche a échoué, réessayez.');
      }
      return (await res.json()) as ExplorerResponse;
    },
    [territorySlug],
  );

  // Nouvelle recherche à chaque changement d'état (la saisie est temporisée).
  useEffect(() => {
    if (firstRun.current) {
      firstRun.current = false;
      return;
    }
    const id = ++reqId.current;
    const url = `${base}/explorer${explorerQueryString(state)}`;
    window.history.replaceState(window.history.state, '', url);
    setLoading(true);
    setError(null);
    fetchPage(state, 0)
      .then((d) => {
        if (id !== reqId.current) return;
        setData(d);
        setVisibleIds(isFilteredState(state) ? d.ids : null);
      })
      .catch((e: Error) => id === reqId.current && setError(e.message))
      .finally(() => id === reqId.current && setLoading(false));
  }, [state, base, fetchPage]);

  useEffect(() => {
    const trimmed = query.trim();
    if (trimmed === state.q) return;
    const t = setTimeout(() => setState((s) => ({ ...s, q: trimmed })), trimmed.length > 8 ? 550 : 300);
    return () => clearTimeout(t);
  }, [query, state.q]);

  const setFamily = (family: Family | null) => setState((s) => ({ ...s, family }));
  const toggle = (key: ExplorerToggle) =>
    setState((s) => ({ ...s, toggles: s.toggles.includes(key) ? s.toggles.filter((k) => k !== key) : [...s.toggles, key] }));

  const toggleAttr = (slug: string) =>
    setState((s) => ({ ...s, attrs: s.attrs.includes(slug) ? s.attrs.filter((a) => a !== slug) : [...s.attrs, slug].slice(0, 12) }));
  const attrLabel = useMemo(() => new Map(filters.flatMap((g) => g.options.map((o) => [o.slug, o.label] as const))), [filters]);

  const loadMore = async () => {
    setLoadingMore(true);
    try {
      const d = await fetchPage(state, data.items.length);
      setData((prev) => ({ ...prev, items: [...prev.items, ...d.items] }));
    } catch (e) {
      setError((e as Error).message);
    } finally {
      setLoadingMore(false);
    }
  };

  const locate = () => {
    if (!('geolocation' in navigator)) return;
    setLocating(true);
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setLocating(false);
        setState((s) => ({ ...s, near: { lat: pos.coords.latitude, lng: pos.coords.longitude } }));
      },
      () => {
        setLocating(false);
        setError('Position indisponible : les distances restent calculées depuis le centre du territoire.');
      },
      { enableHighAccuracy: false, timeout: 8000, maximumAge: 300_000 },
    );
  };

  const showAnswer = aiEnabled && data.answer && state.q.length > 0;
  const mapPoints = useMemo(() => points, [points]);

  return (
    <div className={`explore${mapHidden ? ' map-hidden' : ''}`}>
      <div className="explore-list">
        <form
          role="search"
          onSubmit={(e) => {
            e.preventDefault();
            setState((s) => ({ ...s, q: query.trim() }));
          }}
          style={{ display: 'flex', gap: 6, background: 'var(--paper)', border: '1.5px solid var(--ink)', borderRadius: 14, padding: 5 }}
        >
          <div aria-hidden="true" style={{ display: 'grid', placeItems: 'center', padding: '0 4px 0 8px', color: 'var(--brick)' }}>
            ✦
          </div>
          <label htmlFor="explore-q" className="sr-only">
            Rechercher par nom, produit ou besoin
          </label>
          <input
            id="explore-q"
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Nom, produit, besoin…"
            autoComplete="off"
            enterKeyHint="search"
            style={{ flex: 1, minWidth: 0, border: 0, outline: 'none', fontSize: 15, padding: '10px 4px', background: 'transparent' }}
          />
          {query ? (
            <button
              type="button"
              aria-label="Effacer la recherche"
              onClick={() => {
                setQuery('');
                setState((s) => ({ ...s, q: '' }));
              }}
              style={{ border: 0, background: 'var(--sand)', borderRadius: 8, padding: '0 10px', fontWeight: 700 }}
            >
              ×
            </button>
          ) : null}
        </form>

        {showAnswer ? (
          <div
            aria-live="polite"
            style={{ background: 'var(--ink)', color: 'var(--cream)', borderRadius: 16, padding: 16, display: 'flex', flexDirection: 'column', gap: 8 }}
          >
            <div
              style={{
                display: 'flex',
                gap: 8,
                alignItems: 'center',
                fontSize: 12,
                fontWeight: 700,
                color: 'var(--amber)',
                letterSpacing: '0.06em',
                textTransform: 'uppercase',
              }}
            >
              ✦ Réponse de l&apos;assistant
            </div>
            <div style={{ fontSize: 15, lineHeight: 1.5 }}>{data.answer!.text}</div>
            <div style={{ fontSize: 12, color: 'var(--sage-2)' }}>{data.answer!.meta}</div>
          </div>
        ) : null}

        {state.commune ? (
          <div style={{ display: 'flex', gap: 8, alignItems: 'center', fontSize: 13 }}>
            <span style={{ color: 'var(--muted)' }}>Commune :</span>
            <button
              type="button"
              className="pill"
              onClick={() => setState((s) => ({ ...s, commune: null }))}
              aria-label="Retirer le filtre de commune"
              style={{ background: 'var(--ink)', color: 'var(--cream)', border: 0, fontWeight: 700 }}
            >
              <Icon name="pin" size={13} />
              {communes.find((c) => c.slug === state.commune)?.name ?? state.commune}
              <Icon name="x" size={13} />
            </button>
          </div>
        ) : null}

        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }} role="group" aria-label="Familles d'activité">
          {EXPLORER_FAMILIES.map((f) => {
            const on = state.family === f.key;
            return (
              <button
                key={f.label}
                type="button"
                aria-pressed={on}
                onClick={() => setFamily(f.key)}
                style={{
                  display: 'flex',
                  alignItems: 'center',
                  gap: 6,
                  padding: '7px 12px',
                  borderRadius: 999,
                  fontSize: 13,
                  fontWeight: 600,
                  border: `1.5px solid ${on ? 'var(--ink)' : 'var(--line)'}`,
                  background: on ? 'var(--ink)' : 'var(--paper)',
                  color: on ? '#fff' : 'var(--text)',
                }}
              >
                <span style={{ width: 9, height: 9, borderRadius: '50%', background: f.color }} />
                {f.label}
              </button>
            );
          })}
        </div>

        <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }} role="group" aria-label="Filtres rapides">
          {EXPLORER_TOGGLES.map((t) => {
            const on = state.toggles.includes(t.key);
            return (
              <button
                key={t.key}
                type="button"
                aria-pressed={on}
                onClick={() => toggle(t.key)}
                style={{
                  padding: '6px 11px',
                  borderRadius: 8,
                  fontSize: 12,
                  fontWeight: 600,
                  border: `1px solid ${on ? 'var(--green)' : 'var(--line)'}`,
                  background: on ? 'var(--mint)' : 'transparent',
                  color: on ? 'var(--green)' : 'var(--muted)',
                }}
              >
                {on ? '✓' : '+'} {t.label}
              </button>
            );
          })}
        </div>

        {filters.length ? (
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', alignItems: 'center' }}>
              <button
                type="button"
                className="btn-link"
                aria-expanded={moreOpen}
                aria-controls="explore-more"
                onClick={() => setMoreOpen((o) => !o)}
                style={{ fontSize: 13, display: 'inline-flex', alignItems: 'center', gap: 5 }}
              >
                <Icon name="filter" size={14} />
                {moreOpen ? 'Moins de filtres' : 'Plus de filtres'}
                {state.attrs.length ? ` (${state.attrs.length})` : ''}
              </button>
              {!moreOpen
                ? state.attrs.map((slug) => (
                    <button
                      key={slug}
                      type="button"
                      onClick={() => toggleAttr(slug)}
                      aria-label={`Retirer le filtre ${attrLabel.get(slug) ?? slug}`}
                      className="explore-chip on"
                    >
                      ✓ {attrLabel.get(slug) ?? slug} <Icon name="x" size={12} />
                    </button>
                  ))
                : null}
            </div>
            {moreOpen ? (
              <div id="explore-more" className="explore-more">
                {filters.map((g) => (
                  <fieldset key={g.group}>
                    <legend>{g.label}</legend>
                    <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap' }}>
                      {g.options.map((o) => {
                        const on = state.attrs.includes(o.slug);
                        return (
                          <button key={o.slug} type="button" aria-pressed={on} onClick={() => toggleAttr(o.slug)} className={`explore-chip${on ? ' on' : ''}`}>
                            {on ? '✓' : '+'} {o.label}
                            <span className="explore-chip-count">{o.count.toLocaleString('fr-FR')}</span>
                          </button>
                        );
                      })}
                    </div>
                  </fieldset>
                ))}
              </div>
            ) : null}
          </div>
        ) : null}

        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8, fontSize: 13, color: 'var(--muted)', paddingTop: 4 }}>
          <span aria-live="polite">
            {loading ? <span className="spinner" style={{ width: 12, height: 12, verticalAlign: -1, marginRight: 6 }} aria-hidden="true" /> : null}
            <b style={{ color: 'var(--text)' }}>{data.total.toLocaleString('fr-FR')}</b> adresse{data.total > 1 ? 's' : ''}
          </span>
          <span style={{ display: 'flex', gap: 10, alignItems: 'center' }}>
            <span>Tri : {state.near ? 'les plus proches' : 'recommandés'}</span>
            {state.near ? (
              <button type="button" className="btn-link" onClick={() => setState((s) => ({ ...s, near: null }))} style={{ fontSize: 13 }}>
                Réinitialiser
              </button>
            ) : (
              <button
                type="button"
                className="btn-link"
                onClick={locate}
                disabled={locating}
                style={{ fontSize: 13, display: 'inline-flex', alignItems: 'center', gap: 4 }}
                title="Trier depuis ma position"
              >
                <Icon name="target" size={14} />
                {locating ? 'Localisation…' : 'Autour de moi'}
              </button>
            )}
            <button type="button" className="btn btn-dark btn-xs explore-toggle" onClick={() => setMapHidden((h) => !h)}>
              <Icon name={mapHidden ? 'map' : 'menu'} size={14} />
              {mapHidden ? 'Carte' : 'Liste'}
            </button>
          </span>
        </div>

        {error ? (
          <div className="alert alert-warn" role="status">
            {error}
          </div>
        ) : null}

        {data.items.length === 0 && !loading ? (
          <div className="card card-pad" style={{ textAlign: 'center', color: 'var(--muted)' }}>
            <div style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 18, color: 'var(--text)', marginBottom: 6 }}>
              Aucune adresse ne correspond
            </div>
            Essayez un autre mot, ou retirez un filtre.
          </div>
        ) : null}

        <div style={{ display: 'flex', flexDirection: 'column', gap: 14, opacity: loading ? 0.55 : 1, transition: 'opacity .15s' }}>
          {data.items.map((e) => (
            <Link
              key={e.id}
              href={`${base}${e.path}?src=recherche${state.q ? `&q=${encodeURIComponent(state.q)}` : ''}`}
              className="result-card"
              onMouseEnter={() => setFocusId(e.id)}
              onFocus={() => setFocusId(e.id)}
            >
              <div style={{ width: 96, height: 96, borderRadius: 10, overflow: 'hidden' }}>
                <Photo src={sized(e.coverUrl, 300, 300)} alt="" color={e.color} label={e.name} />
              </div>
              <div style={{ minWidth: 0, display: 'flex', flexDirection: 'column', gap: 3, paddingTop: 2 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
                  <span style={{ fontSize: 11, fontWeight: 800, color: e.color, textTransform: 'uppercase', letterSpacing: '0.06em' }}>{e.activity}</span>
                  <span style={{ fontSize: 12, fontWeight: 700, color: e.isOpen ? 'var(--open)' : 'var(--closed)', whiteSpace: 'nowrap' }}>{e.openLabel}</span>
                </div>
                <div style={{ fontFamily: 'var(--font-display)', fontWeight: 700, fontSize: 17 }}>{e.name}</div>
                <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                  {e.communeName}
                  {e.distance ? ` · ${e.distance}` : ''}
                </div>
                {e.tags.length ? (
                  <div style={{ display: 'flex', gap: 4, flexWrap: 'wrap', marginTop: 4 }}>
                    {e.tags.map((tg) => (
                      <span key={tg} className="soft-tag">
                        {tg}
                      </span>
                    ))}
                  </div>
                ) : null}
              </div>
            </Link>
          ))}
        </div>

        {data.items.length < data.total ? (
          <button type="button" className="btn btn-outline" onClick={loadMore} disabled={loadingMore} style={{ alignSelf: 'center' }}>
            {loadingMore ? 'Chargement…' : `Afficher plus d'adresses (${(data.total - data.items.length).toLocaleString('fr-FR')})`}
          </button>
        ) : null}
      </div>
      <div className="explore-map">
        {overlays.length ? (
          <div className="map-layers" role="group" aria-label="Afficher sur la carte">
            {(Object.keys(OVERLAY_STYLE) as OverlayKind[])
              .filter((k) => layerCounts[k])
              .map((k) => {
                const on = layers.includes(k);
                return (
                  <button key={k} type="button" aria-pressed={on} onClick={() => setLayers((ls) => (ls.includes(k) ? ls.filter((x) => x !== k) : [...ls, k]))}>
                    <span className="dot" style={{ ['--c' as string]: OVERLAY_STYLE[k].color }} aria-hidden="true" />
                    {OVERLAY_STYLE[k].label} · {layerCounts[k]}
                  </button>
                );
              })}
          </div>
        ) : null}
        <MapView
          overlays={overlays}
          overlayKinds={layers}
          mode="explore"
          wheel
          tileUrl={map.tileUrl}
          attribution={map.attribution}
          points={mapPoints}
          visibleIds={visibleIds}
          focusId={focusId}
          ariaLabel="Carte des adresses du territoire"
          style={{ width: '100%', height: '100%', minHeight: 320 }}
        />
      </div>
    </div>
  );
}
