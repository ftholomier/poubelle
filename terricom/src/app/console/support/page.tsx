import type { Metadata } from 'next';
import Link from 'next/link';
import { replyTicketAction, updateTicketAction } from './actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { fmtStamp, fullName, relativeTime } from '@/lib/format';
import { requirePlatformStaff } from '@/server/authz';
import { listTickets, TICKET_PRIORITY, TICKET_STATUS, ticketDetail, type TicketStatus } from '@/server/services/support';

export const metadata: Metadata = { title: 'Support' };

type Props = { searchParams: Promise<{ vue?: string; ticket?: string }> };

const VIEWS: { key: string; label: string; status?: TicketStatus[] }[] = [
  { key: 'ouverts', label: 'À traiter', status: ['OPEN'] },
  { key: 'attente', label: 'En attente client', status: ['PENDING'] },
  { key: 'resolus', label: 'Résolus', status: ['RESOLVED'] },
  { key: 'tous', label: 'Tous' },
];

export default async function SupportPage({ searchParams }: Props) {
  const sp = await searchParams;
  const actor = await requirePlatformStaff();
  const view = VIEWS.find((v) => v.key === sp.vue) ?? VIEWS[0];
  const [tickets, all] = await Promise.all([listTickets({ status: view.status }), listTickets({})]);
  const counts = {
    ouverts: all.filter((t) => t.status === 'OPEN').length,
    attente: all.filter((t) => t.status === 'PENDING').length,
    resolus: all.filter((t) => t.status === 'RESOLVED').length,
    tous: all.length,
  };
  const selectedId = tickets.find((t) => t.id === sp.ticket)?.id ?? tickets[0]?.id;
  const detail = selectedId ? await ticketDetail(selectedId) : null;
  const canImpersonate = actor.isPlatformAdmin || actor.roles.some((r) => r.role === 'PLATFORM_SUPPORT');

  return (
    <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1.3fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
      <section className="console-card" style={{ borderRadius: 20, overflow: 'hidden' }}>
        <nav style={{ display: 'flex', gap: 4, padding: 12, flexWrap: 'wrap', borderBottom: '1px solid var(--console-line-2)' }} aria-label="Files de tickets">
          {VIEWS.map((v) => (
            <Link key={v.key} href={`/console/support?vue=${v.key}`} className="pill-tab" aria-current={v.key === view.key ? 'page' : undefined}>
              {v.label} · {counts[v.key as keyof typeof counts]}
            </Link>
          ))}
        </nav>
        {tickets.length === 0 ? <p style={{ padding: 18, margin: 0, color: 'var(--muted)' }}>Aucun ticket dans cette file.</p> : null}
        {tickets.map((t) => (
          <Link
            key={t.id}
            href={`/console/support?vue=${view.key}&ticket=${t.id}`}
            scroll={false}
            className="console-row"
            aria-current={t.id === selectedId ? 'true' : undefined}
            style={{
              display: 'grid',
              gridTemplateColumns: 'minmax(0,1fr) auto',
              gap: 8,
              padding: '12px 18px',
              borderBottom: '1px solid var(--console-line-2)',
              color: 'var(--text)',
              background: t.id === selectedId ? '#F2F8F4' : undefined,
            }}
          >
            <div style={{ minWidth: 0 }}>
              <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                n°{t.number} · {t.territoryName ?? 'Sans territoire'} ·{' '}
                <span style={{ color: TICKET_PRIORITY[t.priority]?.color, fontWeight: 700 }}>{TICKET_PRIORITY[t.priority]?.label}</span>
              </div>
              <b style={{ display: 'block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{t.subject}</b>
              <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                {t.messages} message{t.messages > 1 ? 's' : ''} · {relativeTime(t.lastAt)}
              </div>
            </div>
            <span
              style={{
                alignSelf: 'start',
                fontSize: 11,
                fontWeight: 800,
                padding: '3px 8px',
                borderRadius: 999,
                background: TICKET_STATUS[t.status as TicketStatus].bg,
                whiteSpace: 'nowrap',
              }}
            >
              {TICKET_STATUS[t.status as TicketStatus].label}
            </span>
          </Link>
        ))}
      </section>

      {detail ? (
        <section className="console-card" style={{ borderRadius: 20, padding: 20, display: 'flex', flexDirection: 'column', gap: 14 }}>
          <div style={{ display: 'flex', gap: 12, alignItems: 'flex-start', flexWrap: 'wrap' }}>
            <div style={{ flex: 1, minWidth: 240 }}>
              <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                Ticket n°{detail.ticket.number} · ouvert {relativeTime(detail.ticket.createdAt)} par{' '}
                {detail.creator ? `${fullName(detail.creator)} (${detail.creator.email})` : 'un agent'}
              </div>
              <h2 className="display" style={{ fontSize: 26, margin: '2px 0 0', letterSpacing: '-0.02em' }}>
                {detail.ticket.subject}
              </h2>
              <div style={{ fontSize: 14, color: 'var(--muted)' }}>{detail.territoryName ?? 'Sans territoire'}</div>
            </div>
            {canImpersonate && detail.ticket.territoryId ? (
              <Link href={`/console/territoires?t=${detail.ticket.territoryId}`} className="btn btn-amber btn-sm" style={{ borderRadius: 9 }}>
                Accès support temporaire
              </Link>
            ) : null}
          </div>
          <ActionForm action={updateTicketAction} resetOnSuccess={false} style={{ display: 'flex', gap: 8, alignItems: 'flex-end', flexWrap: 'wrap' }}>
            <input type="hidden" name="ticketId" value={detail.ticket.id} />
            <label className="field">
              <span>Statut</span>
              <select name="status" className="input" defaultValue={detail.ticket.status} style={{ padding: '8px 10px', fontSize: 13 }}>
                {(Object.keys(TICKET_STATUS) as TicketStatus[]).map((s) => (
                  <option key={s} value={s}>
                    {TICKET_STATUS[s].label}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Priorité</span>
              <select name="priority" className="input" defaultValue={detail.ticket.priority} style={{ padding: '8px 10px', fontSize: 13 }}>
                {Object.entries(TICKET_PRIORITY).map(([k, p]) => (
                  <option key={k} value={k}>
                    {p.label}
                  </option>
                ))}
              </select>
            </label>
            <label className="field">
              <span>Suivi par</span>
              <select name="assign" className="input" defaultValue="keep" style={{ padding: '8px 10px', fontSize: 13 }}>
                <option value="keep">{detail.assignee ? fullName(detail.assignee) : 'Personne'}</option>
                <option value="me">Moi</option>
                <option value="none">Personne</option>
              </select>
            </label>
            <SubmitButton className="btn btn-outline btn-sm">Mettre à jour</SubmitButton>
          </ActionForm>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            {detail.messages.map((m) => (
              <div
                key={m.id}
                className="ticket-msg"
                style={{
                  alignSelf: m.fromSupport ? 'flex-end' : 'flex-start',
                  background: m.internal ? '#FFF4E0' : m.fromSupport ? '#F2F8F4' : '#F7F4EC',
                  borderColor: m.internal ? '#F4B266' : 'var(--console-line)',
                }}
              >
                <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 4 }}>
                  <b style={{ color: 'var(--text)' }}>{m.authorLabel}</b> · {fmtStamp(m.createdAt)}
                  {m.internal ? ' · note interne' : ''}
                </div>
                <div style={{ whiteSpace: 'pre-line', fontSize: 14, lineHeight: 1.5 }}>{m.body}</div>
              </div>
            ))}
            {detail.messages.length === 0 && detail.ticket.body ? <div className="ticket-msg">{detail.ticket.body}</div> : null}
          </div>
          <ActionForm action={replyTicketAction} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <input type="hidden" name="ticketId" value={detail.ticket.id} />
            <textarea name="body" className="textarea" rows={4} placeholder="Votre réponse à la collectivité…" required aria-label="Réponse" />
            <div style={{ display: 'flex', gap: 14, alignItems: 'center', flexWrap: 'wrap' }}>
              <label className="checkbox" style={{ alignItems: 'center' }}>
                <input type="checkbox" name="internal" /> Note interne (non visible par la collectivité)
              </label>
              <label className="checkbox" style={{ alignItems: 'center' }}>
                <input type="checkbox" name="resolve" /> Marquer comme résolu
              </label>
              <SubmitButton className="btn btn-dark btn-sm" style={{ marginLeft: 'auto' }} pendingLabel="Envoi…">
                Envoyer
              </SubmitButton>
            </div>
          </ActionForm>
        </section>
      ) : (
        <section className="console-card" style={{ borderRadius: 20, padding: 20, color: 'var(--muted)' }}>
          Sélectionnez un ticket.
        </section>
      )}
    </div>
  );
}
