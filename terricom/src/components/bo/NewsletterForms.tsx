'use client';

import { useActionState, useRef } from 'react';
import { saveAudiencesAction, scheduleAction, sendTestAction, type NlState } from '@/app/collectivite/newsletter/actions';

const idle: NlState = { status: 'idle' };

function Msg({ state }: { state: NlState }) {
  if (state.status === 'idle') return null;
  return (
    <div
      role={state.status === 'error' ? 'alert' : 'status'}
      style={{ fontSize: 13, fontWeight: 600, color: state.status === 'error' ? 'var(--danger-fg)' : 'var(--green)' }}
    >
      {state.message}
    </div>
  );
}

/** Choix des audiences : chaque case enregistre immédiatement la sélection. */
export function AudiencePicker({
  newsletterId,
  items,
  selected,
  disabled,
}: {
  newsletterId: string;
  items: { id: string; name: string; description: string | null; n: number }[];
  selected: string[];
  disabled?: boolean;
}) {
  const ref = useRef<HTMLFormElement>(null);
  return (
    <form ref={ref} action={saveAudiencesAction} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
      <input type="hidden" name="newsletterId" value={newsletterId} />
      {items.map((a) => {
        const on = selected.includes(a.id);
        return (
          <label
            key={a.id}
            style={{
              cursor: disabled ? 'default' : 'pointer',
              display: 'grid',
              gridTemplateColumns: '20px 1fr auto',
              gap: 10,
              alignItems: 'center',
              padding: 10,
              borderRadius: 12,
              border: `1.5px solid ${on ? 'var(--green)' : 'var(--line)'}`,
              background: on ? 'var(--mint-2)' : '#fff',
            }}
          >
            <input
              type="checkbox"
              name="audience"
              value={a.id}
              defaultChecked={on}
              disabled={disabled}
              onChange={() => ref.current?.requestSubmit()}
              className="sr-only"
            />
            <span
              aria-hidden="true"
              style={{
                width: 18,
                height: 18,
                borderRadius: 5,
                border: '1.5px solid var(--green)',
                background: on ? 'var(--green)' : 'transparent',
                color: '#fff',
                fontSize: 11,
                display: 'grid',
                placeItems: 'center',
              }}
            >
              {on ? '✓' : ''}
            </span>
            <span>
              <span style={{ display: 'block', fontSize: 14, fontWeight: 700 }}>{a.name}</span>
              <span style={{ fontSize: 12, color: 'var(--muted)' }}>{a.description}</span>
            </span>
            <b style={{ fontSize: 13 }}>{a.n.toLocaleString('fr-FR')}</b>
          </label>
        );
      })}
    </form>
  );
}

export function ScheduleForm({ newsletterId, defaultDate, defaultTime }: { newsletterId: string; defaultDate: string; defaultTime: string }) {
  const [state, action, pending] = useActionState(scheduleAction, idle);
  const [test, testAction, testing] = useActionState(sendTestAction, idle);
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
      <form action={action} style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
        <input type="hidden" name="newsletterId" value={newsletterId} />
        <div style={{ display: 'flex', gap: 8 }}>
          <label className="sr-only" htmlFor="nl-date">
            Date d’envoi
          </label>
          <input id="nl-date" name="date" type="date" className="input" defaultValue={defaultDate} style={{ flex: 1 }} />
          <label className="sr-only" htmlFor="nl-time">
            Heure d’envoi
          </label>
          <input id="nl-time" name="time" type="time" className="input" defaultValue={defaultTime} style={{ flex: 1 }} />
        </div>
        <button
          type="submit"
          name="mode"
          value="schedule"
          disabled={pending}
          style={{ border: 0, background: 'var(--green)', color: '#fff', padding: 12, borderRadius: 10, fontWeight: 800, cursor: 'pointer' }}
        >
          {pending ? 'Programmation…' : 'Programmer l’envoi'}
        </button>
        <button type="submit" name="mode" value="now" disabled={pending} className="btn-link" style={{ fontSize: 13 }}>
          Envoyer maintenant
        </button>
        <Msg state={state} />
      </form>
      <form action={testAction}>
        <input type="hidden" name="newsletterId" value={newsletterId} />
        <button type="submit" disabled={testing} className="btn btn-outline btn-sm" style={{ width: '100%', justifyContent: 'center' }}>
          {testing ? 'Envoi du test…' : 'M’envoyer un test'}
        </button>
      </form>
      <Msg state={test} />
    </div>
  );
}
