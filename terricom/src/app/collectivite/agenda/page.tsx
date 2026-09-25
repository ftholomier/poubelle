import { and, asc, desc, eq, gte, inArray, isNull, ne } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { archiveEventAction, archiveNewsAction, saveEventAction, saveMarketAction, saveNewsAction, toggleMarketAction } from './actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { FileDrop } from '@/components/ui/FileDrop';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { EVENT_KINDS, type EventKind } from '@/lib/constants';
import { daysAgoDate, fmtEventBadge, fmtShortDate, parisDate, parisParts, WEEKDAYS_LONG } from '@/lib/format';
import { db } from '@/server/db';
import { communes, establishments, events, markets, posts } from '@/server/db/schema';
import { loadBoContext } from '@/server/services/backoffice';

export const metadata: Metadata = { title: 'Agenda & actualités' };

type Props = { searchParams: Promise<Record<string, string | undefined>> };

const TABS = [
  { key: 'actualites', label: 'Actualités' },
  { key: 'evenements', label: 'Événements' },
  { key: 'marches', label: 'Marchés' },
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
    const editing = list.find((e) => e.id === sp.id && e.authorType !== 'ESTABLISHMENT') ?? null;
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
                  {ev.authorType === 'ESTABLISHMENT' ? (
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
