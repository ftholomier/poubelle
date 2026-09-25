import { and, desc, eq, ne } from 'drizzle-orm';
import { deleteEvent, saveEvent } from '../actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { DateBox } from '@/components/portal/Cards';
import { FileDrop } from '@/components/ui/FileDrop';
import { EVENT_KINDS, type EventKind } from '@/lib/constants';
import { fmtEventHours, fmtLongDate, parisDate, fmtHourOf, tomorrowIso, nowMs } from '@/lib/format';
import { db } from '@/server/db';
import { events } from '@/server/db/schema';
import { loadProContext } from '@/server/services/pro';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ est: string }>; searchParams: Promise<Record<string, string | undefined>> };

const hhmm = (d: Date) => {
  const h = fmtHourOf(d).replace('h', ':');
  const [a, b] = h.split(':');
  return `${a.padStart(2, '0')}:${(b || '00').padStart(2, '0')}`;
};

export default async function EventsProPage({ params, searchParams }: Props) {
  const { est: estId } = await params;
  const sp = await searchParams;
  const ctx = await loadProContext(estId);
  const { est, base, territory } = ctx;
  const list = await db
    .select()
    .from(events)
    .where(and(eq(events.establishmentId, est.id), ne(events.status, 'ARCHIVED')))
    .orderBy(desc(events.startsAt))
    .limit(60);
  const editing = list.find((e) => e.id === sp.evenement) ?? null;
  const now = nowMs();
  return (
    <div className="app-content">
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <div className="panel">
          <h2 className="panel-title">Vos événements</h2>
          {list.length ? (
            list.map((ev) => {
              const past = (ev.endsAt ?? ev.startsAt).getTime() < now;
              return (
                <div
                  key={ev.id}
                  style={{
                    display: 'grid',
                    gridTemplateColumns: '62px minmax(0,1fr) auto',
                    gap: 12,
                    alignItems: 'center',
                    borderTop: '1px solid var(--line-2)',
                    paddingTop: 10,
                    opacity: past ? 0.6 : 1,
                  }}
                >
                  <DateBox date={ev.startsAt} kind={ev.kind as EventKind} />
                  <div style={{ minWidth: 0 }}>
                    <div style={{ fontWeight: 700 }}>{ev.title}</div>
                    <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                      {EVENT_KINDS[ev.kind as EventKind].label} · {fmtLongDate(ev.startsAt)} · {fmtEventHours(ev.startsAt, ev.endsAt)}
                    </div>
                  </div>
                  <div style={{ display: 'flex', gap: 8, fontSize: 12 }}>
                    {!past ? (
                      <a href={portalUrl(territory, `/agenda/${ev.slug}`)} target="_blank" rel="noopener" className="btn-link">
                        Voir
                      </a>
                    ) : null}
                    <a href={`${base}/evenements?evenement=${ev.id}`} className="btn-link">
                      Modifier
                    </a>
                    <form action={deleteEvent}>
                      <input type="hidden" name="estId" value={est.id} />
                      <input type="hidden" name="eventId" value={ev.id} />
                      <button type="submit" className="btn-link" style={{ color: 'var(--danger-fg)' }}>
                        Retirer
                      </button>
                    </form>
                  </div>
                </div>
              );
            })
          ) : (
            <p style={{ margin: 0, fontSize: 14, color: 'var(--muted)' }}>
              Atelier, dégustation, portes ouvertes : vos événements apparaissent dans l&apos;agenda du territoire.
            </p>
          )}
        </div>
        <ActionForm action={saveEvent} className="panel" resetOnSuccess={!editing} key={editing?.id ?? 'new'}>
          <input type="hidden" name="estId" value={est.id} />
          <input type="hidden" name="eventId" value={editing?.id ?? ''} />
          <h2 className="panel-title">{editing ? 'Modifier l’événement' : 'Nouvel événement'}</h2>
          <input
            name="title"
            className="input"
            placeholder="Titre (ex. Atelier pain au levain pour enfants)"
            defaultValue={editing?.title}
            required
            maxLength={255}
          />
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))', gap: 10 }}>
            <select name="kind" className="select" defaultValue={editing?.kind ?? 'ATELIER'}>
              {(Object.keys(EVENT_KINDS) as EventKind[]).map((k) => (
                <option key={k} value={k}>
                  {EVENT_KINDS[k].label}
                </option>
              ))}
            </select>
            <input
              name="date"
              type="date"
              className="input"
              min={editing ? undefined : tomorrowIso()}
              defaultValue={editing ? parisDate(editing.startsAt) : ''}
              required
              aria-label="Date"
            />
            <input name="start" type="time" className="input" defaultValue={editing ? hhmm(editing.startsAt) : '10:00'} required aria-label="Début" />
            <input name="end" type="time" className="input" defaultValue={editing?.endsAt ? hhmm(editing.endsAt) : ''} aria-label="Fin" />
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 10 }}>
            <input
              name="locationName"
              className="input"
              placeholder={`Lieu (par défaut : ${est.name})`}
              defaultValue={editing?.locationName ?? ''}
              maxLength={255}
            />
            <input name="address" className="input" placeholder="Adresse (par défaut : la vôtre)" defaultValue={editing?.address ?? ''} maxLength={255} />
            <input
              name="priceText"
              className="input"
              placeholder="Tarif (ex. Gratuit, 8 € sur inscription)"
              defaultValue={editing?.priceText ?? ''}
              maxLength={120}
            />
            <input name="capacity" type="number" min={1} className="input" placeholder="Places (facultatif)" defaultValue={editing?.capacity ?? ''} />
          </div>
          <input name="registrationUrl" className="input" placeholder="Lien d'inscription (facultatif)" defaultValue={editing?.registrationUrl ?? ''} />
          <textarea
            name="description"
            rows={4}
            className="textarea"
            placeholder="Décrivez le déroulé, le public, ce qu'il faut apporter…"
            defaultValue={editing?.description ?? ''}
            required
            maxLength={5000}
          />
          <FileDrop name="image" accept="image/*" label="+ Image (facultatif, sinon votre photo principale)" />
          <button type="submit" className="btn btn-brand" style={{ alignSelf: 'flex-start' }}>
            {editing ? 'Enregistrer' : 'Publier dans l’agenda'}
          </button>
        </ActionForm>
      </div>
    </div>
  );
}
