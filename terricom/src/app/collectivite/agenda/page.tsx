import { and, asc, desc, eq, gte, inArray, isNull, ne } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import {
  addCalendarFeedAction,
  archiveEventAction,
  archiveNewsAction,
  removeCalendarFeedAction,
  syncCalendarFeedAction,
  saveEventAction,
  saveMarketAction,
  saveNewsAction,
  savePoiAction,
  toggleMarketAction,
  togglePoiAction,
} from './actions';
import { PositionPicker } from '@/components/bo/PositionPicker';
import { ActionForm } from '@/components/pro/ActionForm';
import { FileDrop } from '@/components/ui/FileDrop';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { EVENT_KINDS, POI_KINDS, type EventKind, type PoiKind } from '@/lib/constants';
import { daysAgoDate, fmtEventBadge, fmtShortDate, parisDate, parisParts, relativeTime, WEEKDAYS_LONG } from '@/lib/format';
import { db } from '@/server/db';
import { calendarFeeds, communes, establishments, events, markets, pointsOfInterest, posts } from '@/server/db/schema';
import { env } from '@/server/env';
import { loadBoContext } from '@/server/services/backoffice';
import { portalUrl } from '@/server/urls';

export const metadata: Metadata = { title: 'Agenda & actualités' };

type Props = { searchParams: Promise<Record<string, string | undefined>> };

const TABS = [
  { key: 'actualites', label: 'Actualités' },
  { key: 'evenements', label: 'Événements' },
  { key: 'marches', label: 'Marchés' },
  { key: 'lieux', label: 'Lieux sur la carte' },
  { key: 'synchronisation', label: 'Agendas externes' },
] as const;

export default async function AgendaPage({ searchParams }: Props) {
  const ctx = await loadBoContext();
  const sp = await searchParams;
  const tab = TABS.find((t) => t.key === sp.onglet)?.key ?? 'actualites';
  const communeSelect = (name: string, value: string | null, required = false) => (
    <select name={name} className="input" defaultValue={value ?? ctx.commune?.id ?? ''} required={required} aria-label="Commune">
      {ctx.level === 'TERRITORY' && !required ? <option value="">Tout le territoire</option> : <option value="">Commune…</option>}
      {ctx.communes.map((c) => (
        <option key={c.id} value={c.id}>
          {c.name}
        </option>
      ))}
    </select>
  );
  const tabs = (
    <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
      {TABS.map((t) => (
        <Link key={t.key} href={`/collectivite/agenda?onglet=${t.key}`} className="bo-chip" aria-current={t.key === tab ? 'true' : undefined}>
          {t.label}
        </Link>
      ))}
    </div>
  );

  if (tab === 'actualites') {
    const list = await db
      .select({
        id: posts.id,
        title: posts.title,
        body: posts.body,
        status: posts.status,
        publishedAt: posts.publishedAt,
        publishAt: posts.publishAt,
        communeId: posts.communeId,
        communeName: communes.name,
      })
      .from(posts)
      .leftJoin(communes, eq(communes.id, posts.communeId))
      .where(
        and(
          eq(posts.territoryId, ctx.territory.id),
          isNull(posts.establishmentId),
          ne(posts.status, 'ARCHIVED'),
          ctx.communeIds ? inArray(posts.communeId, ctx.communeIds) : undefined,
        ),
      )
      .orderBy(desc(posts.createdAt))
      .limit(30);
    const editing = list.find((p) => p.id === sp.id) ?? null;
    return (
      <div className="app-content">
        {tabs}
        <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
          <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            <b style={{ marginBottom: 8 }}>Actualités de la collectivité</b>
            {list.map((p) => (
              <div
                key={p.id}
                style={{
                  display: 'flex',
                  gap: 10,
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  padding: '10px 0',
                  borderTop: '1px solid var(--line-2)',
                  fontSize: 14,
                }}
              >
                <Link href={`/collectivite/agenda?onglet=actualites&id=${p.id}`} style={{ color: 'var(--text)', minWidth: 0 }}>
                  <b style={{ display: 'block' }}>{p.title}</b>
                  <span style={{ fontSize: 12, color: 'var(--muted)' }}>
                    {p.status === 'SCHEDULED' && p.publishAt
                      ? `programmée le ${fmtShortDate(p.publishAt)}`
                      : p.publishedAt
                        ? `publiée le ${fmtShortDate(p.publishedAt)}`
                        : p.status.toLowerCase()}
                    {p.communeName ? ` · ${p.communeName}` : ' · tout le territoire'}
                  </span>
                </Link>
                <form action={archiveNewsAction}>
                  <input type="hidden" name="postId" value={p.id} />
                  <button type="submit" className="btn-link" style={{ color: 'var(--danger-fg)', fontSize: 12 }}>
                    Retirer
                  </button>
                </form>
              </div>
            ))}
            {!list.length ? <span style={{ fontSize: 13, color: 'var(--muted)' }}>Aucune actualité publiée par la collectivité.</span> : null}
          </section>
          <section className="bo-card">
            <b>{editing ? 'Modifier l’actualité' : 'Nouvelle actualité'}</b>
            <ActionForm
              key={editing?.id ?? 'new'}
              action={saveNewsAction}
              resetOnSuccess={!editing}
              style={{ display: 'flex', flexDirection: 'column', gap: 10, marginTop: 12 }}
            >
              <input type="hidden" name="postId" value={editing?.id ?? ''} />
              <input
                name="title"
                className="input"
                placeholder="Titre (ex. Travaux rue Courbet : les commerces restent ouverts)"
                defaultValue={editing?.title ?? ''}
                required
                aria-label="Titre"
              />
              <textarea
                name="body"
                className="input"
                rows={6}
                placeholder="Texte de l’actualité"
                defaultValue={editing?.body ?? ''}
                required
                aria-label="Texte"
              />
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                {communeSelect('communeId', editing?.communeId ?? null)}
                <input
                  name="publishAt"
                  type="date"
                  className="input"
                  aria-label="Date de publication (facultatif)"
                  title="Laisser vide pour publier maintenant"
                />
              </div>
              <FileDrop name="image" accept="image/jpeg,image/png,image/webp" label="Ajouter une image (facultatif)" />
              <SubmitButton className="btn btn-brand" pendingLabel="Publication…" style={{ alignSelf: 'flex-start' }}>
                {editing ? 'Enregistrer' : 'Publier'}
              </SubmitButton>
            </ActionForm>
          </section>
        </div>
      </div>
    );
  }

  if (tab === 'evenements') {
    const list = await db
      .select({
        id: events.id,
        title: events.title,
        kind: events.kind,
        startsAt: events.startsAt,
        endsAt: events.endsAt,
        locationName: events.locationName,
        address: events.address,
        summary: events.summary,
        description: events.description,
        priceText: events.priceText,
        registrationUrl: events.registrationUrl,
        organizerName: events.organizerName,
        isFeatured: events.isFeatured,
        communeId: events.communeId,
        communeName: communes.name,
        estName: establishments.name,
        authorType: events.authorType,
        sourceFeedId: events.sourceFeedId,
      })
      .from(events)
      .leftJoin(communes, eq(communes.id, events.communeId))
      .leftJoin(establishments, eq(establishments.id, events.establishmentId))
      .where(
        and(
          eq(events.territoryId, ctx.territory.id),
          eq(events.status, 'PUBLISHED'),
          gte(events.startsAt, daysAgoDate(1)),
          ctx.communeIds ? inArray(events.communeId, ctx.communeIds) : undefined,
        ),
      )
      .orderBy(asc(events.startsAt))
      .limit(60);
    // Les événements d'une fiche ou d'un agenda externe se modifient à leur source.
    const editing = list.find((e) => e.id === sp.id && e.authorType !== 'ESTABLISHMENT' && !e.sourceFeedId) ?? null;
    const p = editing ? parisParts(editing.startsAt) : null;
    const e2 = editing?.endsAt ? parisParts(editing.endsAt) : null;
    const hhmm = (x: { hour: number; minute: number }) => `${String(x.hour).padStart(2, '0')}:${String(x.minute).padStart(2, '0')}`;
    return (
      <div className="app-content">
        {tabs}
        <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
          <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            <b style={{ marginBottom: 8 }}>Agenda à venir ({list.length})</b>
            {list.map((ev) => (
              <div
                key={ev.id}
                style={{
                  display: 'grid',
                  gridTemplateColumns: '1fr auto',
                  gap: 10,
                  padding: '10px 0',
                  borderTop: '1px solid var(--line-2)',
                  fontSize: 14,
                  alignItems: 'center',
                }}
              >
                <div style={{ minWidth: 0 }}>
                  <span
                    style={{
                      fontSize: 11,
                      fontWeight: 800,
                      padding: '2px 8px',
                      borderRadius: 999,
                      background: EVENT_KINDS[ev.kind as EventKind].bg,
                      marginRight: 6,
                    }}
                  >
                    {EVENT_KINDS[ev.kind as EventKind].label}
                  </span>
                  {ev.authorType === 'ESTABLISHMENT' || ev.sourceFeedId ? (
                    <b>{ev.title}</b>
                  ) : (
                    <Link href={`/collectivite/agenda?onglet=evenements&id=${ev.id}`} style={{ fontWeight: 700 }}>
                      {ev.title}
                    </Link>
                  )}
                  <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                    {fmtEventBadge(ev.startsAt, ev.endsAt)} · {ev.estName ?? ev.organizerName ?? ctx.scopeName}
                    {ev.communeName ? ` · ${ev.communeName}` : ''}
                    {ev.isFeatured ? ' · ★ à la une' : ''}
                    {ev.sourceFeedId ? ' · agenda externe (synchronisé)' : ''}
                  </div>
                </div>
                <form action={archiveEventAction}>
                  <input type="hidden" name="eventId" value={ev.id} />
                  <button type="submit" className="btn-link" style={{ color: 'var(--danger-fg)', fontSize: 12 }}>
                    Retirer
                  </button>
                </form>
              </div>
            ))}
          </section>
          <section className="bo-card">
            <b>{editing ? 'Modifier l’événement' : 'Nouvel événement de la collectivité'}</b>
            <ActionForm
              key={editing?.id ?? 'new'}
              action={saveEventAction}
              resetOnSuccess={!editing}
              style={{ display: 'flex', flexDirection: 'column', gap: 10, marginTop: 12 }}
            >
              <input type="hidden" name="eventId" value={editing?.id ?? ''} />
              <input
                name="title"
                className="input"
                placeholder="Titre (ex. Marché de Noël d’Ornans)"
                defaultValue={editing?.title ?? ''}
                required
                aria-label="Titre"
              />
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                <select name="kind" className="input" defaultValue={editing?.kind ?? 'ANIMATION'} aria-label="Type">
                  {Object.entries(EVENT_KINDS).map(([k, v]) => (
                    <option key={k} value={k}>
                      {v.label}
                    </option>
                  ))}
                </select>
                {communeSelect('communeId', editing?.communeId ?? null)}
                <input name="date" type="date" className="input" defaultValue={editing ? parisDate(editing.startsAt) : ''} required aria-label="Date" />
                <div style={{ display: 'flex', gap: 6 }}>
                  <input name="start" type="time" className="input" defaultValue={p ? hhmm(p) : '10:00'} required aria-label="Début" />
                  <input name="end" type="time" className="input" defaultValue={e2 ? hhmm(e2) : ''} aria-label="Fin" />
                </div>
                <input
                  name="locationName"
                  className="input"
                  placeholder="Lieu (ex. Place Courbet)"
                  defaultValue={editing?.locationName ?? ''}
                  aria-label="Lieu"
                />
                <input name="address" className="input" placeholder="Adresse" defaultValue={editing?.address ?? ''} aria-label="Adresse" />
                <input name="priceText" className="input" placeholder="Tarif (ex. Gratuit)" defaultValue={editing?.priceText ?? ''} aria-label="Tarif" />
                <input
                  name="organizerName"
                  className="input"
                  placeholder={`Organisateur (${ctx.scopeName})`}
                  defaultValue={editing?.organizerName ?? ''}
                  aria-label="Organisateur"
                />
              </div>
              <input
                name="summary"
                className="input"
                placeholder="Résumé en une phrase"
                defaultValue={editing?.summary ?? ''}
                maxLength={300}
                aria-label="Résumé"
              />
              <textarea
                name="description"
                className="input"
                rows={4}
                placeholder="Description"
                defaultValue={editing?.description ?? ''}
                required
                aria-label="Description"
              />
              <input
                name="registrationUrl"
                className="input"
                placeholder="Lien d’inscription (facultatif)"
                defaultValue={editing?.registrationUrl ?? ''}
                aria-label="Lien d’inscription"
              />
              <label style={{ display: 'flex', gap: 8, fontSize: 14, alignItems: 'center' }}>
                <input type="checkbox" name="isFeatured" defaultChecked={editing?.isFeatured ?? false} style={{ accentColor: 'var(--green)' }} />
                Mettre à la une de l’agenda
              </label>
              <FileDrop name="image" accept="image/jpeg,image/png,image/webp" label="Visuel (facultatif)" />
              <SubmitButton className="btn btn-brand" pendingLabel="Enregistrement…" style={{ alignSelf: 'flex-start' }}>
                {editing ? 'Enregistrer' : 'Ajouter à l’agenda'}
              </SubmitButton>
            </ActionForm>
          </section>
        </div>
      </div>
    );
  }

  if (tab === 'synchronisation') {
    const feeds = await db
      .select({ f: calendarFeeds, communeName: communes.name })
      .from(calendarFeeds)
      .leftJoin(communes, eq(communes.id, calendarFeeds.communeId))
      .where(and(eq(calendarFeeds.territoryId, ctx.territory.id), ctx.communeIds ? inArray(calendarFeeds.communeId, ctx.communeIds) : undefined))
      .orderBy(asc(calendarFeeds.name));
    const base = portalUrl(ctx.territory, '');
    return (
      <div className="app-content">
        {tabs}
        <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.2fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
          <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            <div>
              <b>Agendas synchronisés</b>
              <p style={{ margin: '4px 0 0', fontSize: 13, color: 'var(--muted)', lineHeight: 1.5 }}>
                Les événements d’un agenda existant (office de tourisme, mairie, association) sont repris automatiquement dans l’agenda du portail, mis à jour
                chaque heure et retirés s’ils sont annulés.
              </p>
            </div>
            {feeds.map(({ f, communeName }) => (
              <div key={f.id} style={{ borderTop: '1px solid var(--line-2)', paddingTop: 10, display: 'flex', flexDirection: 'column', gap: 6 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', gap: 10, alignItems: 'baseline', flexWrap: 'wrap' }}>
                  <b>{f.name}</b>
                  <span style={{ fontSize: 12, color: 'var(--muted)' }}>
                    {EVENT_KINDS[f.kind as EventKind].label} · {communeName ?? 'Tout le territoire'}
                  </span>
                </div>
                <div className="mono" style={{ fontSize: 12, color: 'var(--muted)', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>
                  {f.url}
                </div>
                <div style={{ fontSize: 13, color: f.lastStatus?.startsWith('Échec') ? 'var(--danger-fg)' : 'var(--muted-3)' }} role="status">
                  {f.lastSyncAt ? `${f.lastStatus ?? ''} · ${relativeTime(f.lastSyncAt)}` : 'Jamais synchronisé'}
                </div>
                <div style={{ display: 'flex', gap: 12, alignItems: 'center' }}>
                  <ActionForm action={syncCalendarFeedAction} resetOnSuccess={false}>
                    <input type="hidden" name="feedId" value={f.id} />
                    <SubmitButton className="btn btn-outline btn-sm" pendingLabel="Synchronisation…">
                      Synchroniser maintenant
                    </SubmitButton>
                  </ActionForm>
                  <form action={removeCalendarFeedAction}>
                    <input type="hidden" name="feedId" value={f.id} />
                    <button type="submit" className="btn-link" style={{ fontSize: 13, color: 'var(--danger-fg)' }}>
                      Retirer
                    </button>
                  </form>
                </div>
              </div>
            ))}
            {!feeds.length ? <span style={{ fontSize: 13, color: 'var(--muted)' }}>Aucun agenda externe pour l’instant.</span> : null}
          </section>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
            <section className="bo-card">
              <b>Ajouter un agenda</b>
              <ActionForm action={addCalendarFeedAction} style={{ display: 'flex', flexDirection: 'column', gap: 10, marginTop: 12 }}>
                <input name="name" className="input" placeholder="Nom (ex. Office de tourisme)" required maxLength={160} aria-label="Nom de l’agenda" />
                <input
                  name="url"
                  className="input"
                  placeholder="Adresse iCal (https://… .ics ou webcal://…)"
                  required
                  maxLength={1000}
                  aria-label="Adresse de l’agenda (iCal)"
                />
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                  <select name="kind" className="input" defaultValue="ANIMATION" aria-label="Type des événements">
                    {(Object.keys(EVENT_KINDS) as EventKind[]).map((k) => (
                      <option key={k} value={k}>
                        {EVENT_KINDS[k].label}
                      </option>
                    ))}
                  </select>
                  {communeSelect('communeId', null, ctx.level === 'COMMUNE')}
                </div>
                <SubmitButton className="btn btn-brand" pendingLabel="Synchronisation…" style={{ alignSelf: 'flex-start' }}>
                  Ajouter et synchroniser
                </SubmitButton>
              </ActionForm>
            </section>
            <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 13 }}>
              <b>Diffuser l’agenda du portail</b>
              <span style={{ color: 'var(--muted)', lineHeight: 1.5 }}>
                Dans l’autre sens, l’agenda et les actualités du portail se reprennent sur le site de la collectivité ou d’une mairie :
              </span>
              <code className="mono" style={{ fontSize: 12, wordBreak: 'break-all' }}>
                {base}/agenda.ics
              </code>
              <code className="mono" style={{ fontSize: 12, wordBreak: 'break-all' }}>
                {base}/actualites.xml
              </code>
            </section>
          </div>
        </div>
      </div>
    );
  }

  if (tab === 'lieux') {
    const pois = await db
      .select({ p: pointsOfInterest, communeName: communes.name })
      .from(pointsOfInterest)
      .leftJoin(communes, eq(communes.id, pointsOfInterest.communeId))
      .where(and(eq(pointsOfInterest.territoryId, ctx.territory.id), ctx.communeIds ? inArray(pointsOfInterest.communeId, ctx.communeIds) : undefined))
      .orderBy(asc(pointsOfInterest.name));
    const editing = pois.find((x) => x.p.id === sp.id)?.p ?? null;
    const center: [number, number] = editing
      ? [editing.lat, editing.lng]
      : [ctx.commune?.lat ?? ctx.territory.centerLat ?? 46.6, ctx.commune?.lng ?? ctx.territory.centerLng ?? 2.6];
    return (
      <div className="app-content">
        {tabs}
        <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
          <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            <b>Lieux économiques</b>
            <span style={{ fontSize: 13, color: 'var(--muted)', marginBottom: 8 }}>
              Zones d’activités, halles, office de tourisme, tiers-lieux… affichés sur la carte du portail avec les événements et les marchés.
            </span>
            {pois.map(({ p, communeName }) => (
              <div
                key={p.id}
                style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  gap: 10,
                  padding: '10px 0',
                  borderTop: '1px solid var(--line-2)',
                  fontSize: 14,
                  opacity: p.isActive ? 1 : 0.55,
                }}
              >
                <Link href={`/collectivite/agenda?onglet=lieux&id=${p.id}`} style={{ color: 'var(--text)', minWidth: 0 }}>
                  <b>{p.name}</b>
                  <span style={{ display: 'block', fontSize: 12, color: 'var(--muted)' }}>
                    {POI_KINDS[p.kind as PoiKind].label}
                    {communeName ? ` · ${communeName}` : ''}
                    {p.address ? ` · ${p.address}` : ''}
                  </span>
                </Link>
                <form action={togglePoiAction}>
                  <input type="hidden" name="poiId" value={p.id} />
                  <button type="submit" className="btn-link" style={{ fontSize: 12 }}>
                    {p.isActive ? 'Masquer' : 'Afficher'}
                  </button>
                </form>
              </div>
            ))}
            {!pois.length ? <span style={{ fontSize: 13, color: 'var(--muted)' }}>Aucun lieu pour l’instant.</span> : null}
          </section>
          <section className="bo-card">
            <b>{editing ? 'Modifier le lieu' : 'Nouveau lieu'}</b>
            <ActionForm
              key={editing?.id ?? 'new'}
              action={savePoiAction}
              resetOnSuccess={!editing}
              style={{ display: 'flex', flexDirection: 'column', gap: 10, marginTop: 12 }}
            >
              <input type="hidden" name="poiId" value={editing?.id ?? ''} />
              <input
                name="name"
                className="input"
                placeholder="Nom (ex. Zone d’activités des Prés)"
                defaultValue={editing?.name ?? ''}
                required
                aria-label="Nom"
              />
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
                <select name="kind" className="input" defaultValue={editing?.kind ?? 'ZONE_ACTIVITE'} aria-label="Type de lieu">
                  {(Object.keys(POI_KINDS) as PoiKind[]).map((k) => (
                    <option key={k} value={k}>
                      {POI_KINDS[k].label}
                    </option>
                  ))}
                </select>
                {communeSelect('communeId', editing?.communeId ?? null, ctx.level === 'COMMUNE')}
              </div>
              <input name="address" className="input" placeholder="Adresse" defaultValue={editing?.address ?? ''} aria-label="Adresse" />
              <input name="url" className="input" placeholder="Site internet (https://…)" defaultValue={editing?.url ?? ''} aria-label="Site internet" />
              <textarea
                name="description"
                className="input"
                rows={2}
                placeholder="En quelques mots (entreprises présentes, services…)"
                defaultValue={editing?.description ?? ''}
                aria-label="Description"
              />
              <PositionPicker lat={center[0]} lng={center[1]} tileUrl={env.MAP_TILE_URL} attribution={env.MAP_TILE_ATTRIBUTION} />
              <SubmitButton className="btn btn-brand" pendingLabel="Enregistrement…" style={{ alignSelf: 'flex-start' }}>
                {editing ? 'Enregistrer' : 'Ajouter à la carte'}
              </SubmitButton>
            </ActionForm>
          </section>
        </div>
      </div>
    );
  }

  const list = await db
    .select({ m: markets, communeName: communes.name })
    .from(markets)
    .innerJoin(communes, eq(communes.id, markets.communeId))
    .where(and(eq(markets.territoryId, ctx.territory.id), ctx.communeIds ? inArray(markets.communeId, ctx.communeIds) : undefined))
    .orderBy(asc(markets.weekday), asc(markets.startTime));
  const editing = list.find((x) => x.m.id === sp.id)?.m ?? null;
  return (
    <div className="app-content">
      {tabs}
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
          <b style={{ marginBottom: 8 }}>Marchés hebdomadaires</b>
          {list.map(({ m, communeName }) => (
            <div
              key={m.id}
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                gap: 10,
                padding: '10px 0',
                borderTop: '1px solid var(--line-2)',
                fontSize: 14,
                opacity: m.isActive ? 1 : 0.55,
              }}
            >
              <Link href={`/collectivite/agenda?onglet=marches&id=${m.id}`} style={{ color: 'var(--text)' }}>
                <b>{m.name}</b>
                <span style={{ display: 'block', fontSize: 12, color: 'var(--muted)' }}>
                  Chaque {WEEKDAYS_LONG[m.weekday]} · {m.startTime.slice(0, 5).replace(':', 'h')}–{m.endTime.slice(0, 5).replace(':', 'h')} · {communeName}
                  {m.place ? ` · ${m.place}` : ''}
                </span>
              </Link>
              <form action={toggleMarketAction}>
                <input type="hidden" name="marketId" value={m.id} />
                <button type="submit" className="btn-link" style={{ fontSize: 12 }}>
                  {m.isActive ? 'Suspendre' : 'Réactiver'}
                </button>
              </form>
            </div>
          ))}
          {!list.length ? <span style={{ fontSize: 13, color: 'var(--muted)' }}>Aucun marché renseigné.</span> : null}
        </section>
        <section className="bo-card">
          <b>{editing ? 'Modifier le marché' : 'Nouveau marché'}</b>
          <ActionForm
            key={editing?.id ?? 'new'}
            action={saveMarketAction}
            resetOnSuccess={!editing}
            style={{ display: 'flex', flexDirection: 'column', gap: 10, marginTop: 12 }}
          >
            <input type="hidden" name="marketId" value={editing?.id ?? ''} />
            <input name="name" className="input" placeholder="Nom (ex. Marché du samedi)" defaultValue={editing?.name ?? ''} required aria-label="Nom" />
            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 10 }}>
              {communeSelect('communeId', editing?.communeId ?? null, true)}
              <input name="place" className="input" placeholder="Lieu (ex. Place Courbet)" defaultValue={editing?.place ?? ''} aria-label="Lieu" />
              <select name="weekday" className="input" defaultValue={String(editing?.weekday ?? 5)} aria-label="Jour">
                {WEEKDAYS_LONG.map((d, i) => (
                  <option key={d} value={i}>
                    Chaque {d}
                  </option>
                ))}
              </select>
              <div style={{ display: 'flex', gap: 6 }}>
                <input name="startTime" type="time" className="input" defaultValue={editing?.startTime.slice(0, 5) ?? '08:00'} required aria-label="Début" />
                <input name="endTime" type="time" className="input" defaultValue={editing?.endTime.slice(0, 5) ?? '12:30'} required aria-label="Fin" />
              </div>
            </div>
            <textarea
              name="description"
              className="input"
              rows={3}
              placeholder="Producteurs présents, spécialités…"
              defaultValue={editing?.description ?? ''}
              aria-label="Description"
            />
            <SubmitButton className="btn btn-brand" pendingLabel="Enregistrement…" style={{ alignSelf: 'flex-start' }}>
              {editing ? 'Enregistrer' : 'Ajouter le marché'}
            </SubmitButton>
          </ActionForm>
        </section>
      </div>
    </div>
  );
}
