import type { Metadata } from 'next';
import Link from 'next/link';
import { openTicketAction, replyOwnTicketAction } from './actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { fmtStamp, relativeTime } from '@/lib/format';
import { loadBoContext } from '@/server/services/backoffice';
import { listTickets, TICKET_STATUS, ticketDetail, type TicketStatus } from '@/server/services/support';

export const metadata: Metadata = { title: 'Aide & support' };

type Props = { searchParams: Promise<{ ticket?: string; nouveau?: string }> };

export default async function BoSupportPage({ searchParams }: Props) {
  const sp = await searchParams;
  const ctx = await loadBoContext();
  const tickets = await listTickets({ territoryId: ctx.territory.id });
  const selectedId = sp.nouveau ? undefined : (tickets.find((t) => t.id === sp.ticket)?.id ?? tickets[0]?.id);
  const detail = selectedId ? await ticketDetail(selectedId) : null;
  const visible = detail?.messages.filter((m) => !m.internal) ?? [];

  return (
    <div className="app-content">
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) minmax(0,1.4fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8 }}>
            <h2 className="display" style={{ fontSize: 22, margin: 0 }}>
              Vos demandes
            </h2>
            <Link href="/collectivite/support?nouveau=1" className="btn btn-brand btn-sm">
              + Nouvelle demande
            </Link>
          </div>
          <p style={{ margin: 0, fontSize: 13, color: 'var(--muted)' }}>
            L’équipe terricom répond en moyenne sous 4 heures ouvrées. Pour une urgence (portail inaccessible), choisissez la priorité « Urgente ».
          </p>
          {tickets.length === 0 ? <p style={{ margin: 0, color: 'var(--muted)' }}>Aucune demande pour l’instant.</p> : null}
          {tickets.map((t) => (
            <Link
              key={t.id}
              href={`/collectivite/support?ticket=${t.id}`}
              scroll={false}
              style={{
                display: 'grid',
                gridTemplateColumns: 'minmax(0,1fr) auto',
                gap: 8,
                padding: 12,
                borderRadius: 12,
                border: `1.5px solid ${t.id === selectedId ? 'var(--ink)' : 'var(--line)'}`,
                color: 'var(--text)',
                background: t.id === selectedId ? '#fff' : 'transparent',
              }}
            >
              <div style={{ minWidth: 0 }}>
                <b style={{ display: 'block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{t.subject}</b>
                <span style={{ fontSize: 12, color: 'var(--muted)' }}>
                  n°{t.number} · {relativeTime(t.lastAt)}
                </span>
              </div>
              <span
                style={{
                  alignSelf: 'start',
                  fontSize: 11,
                  fontWeight: 800,
                  padding: '3px 8px',
                  borderRadius: 999,
                  background: TICKET_STATUS[t.status as TicketStatus].bg,
                }}
              >
                {TICKET_STATUS[t.status as TicketStatus].label}
              </span>
            </Link>
          ))}
        </section>

        {detail ? (
          <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            <div>
              <div style={{ fontSize: 12, color: 'var(--muted)' }}>Demande n°{detail.ticket.number}</div>
              <h2 className="display" style={{ fontSize: 24, margin: '2px 0 0' }}>
                {detail.ticket.subject}
              </h2>
            </div>
            {visible.map((m) => (
              <div
                key={m.id}
                className="ticket-msg"
                style={{ alignSelf: m.fromSupport ? 'flex-start' : 'flex-end', background: m.fromSupport ? '#F2F8F4' : '#F7F4EC' }}
              >
                <div style={{ fontSize: 12, color: 'var(--muted)', marginBottom: 4 }}>
                  <b style={{ color: 'var(--text)' }}>{m.authorLabel}</b> · {fmtStamp(m.createdAt)}
                </div>
                <div style={{ whiteSpace: 'pre-line', fontSize: 14, lineHeight: 1.5 }}>{m.body}</div>
              </div>
            ))}
            <ActionForm action={replyOwnTicketAction} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
              <input type="hidden" name="ticketId" value={detail.ticket.id} />
              <textarea
                name="body"
                className="textarea"
                rows={3}
                placeholder={detail.ticket.status === 'RESOLVED' ? 'Le problème persiste ? Écrivez ici pour rouvrir la demande.' : 'Compléter la demande…'}
                required
                aria-label="Message"
              />
              <SubmitButton className="btn btn-dark btn-sm" style={{ alignSelf: 'flex-end' }} pendingLabel="Envoi…">
                Envoyer
              </SubmitButton>
            </ActionForm>
          </section>
        ) : (
          <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <h2 className="display" style={{ fontSize: 24, margin: 0 }}>
              Nouvelle demande au support
            </h2>
            <ActionForm action={openTicketAction} resetOnSuccess={false} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
              <label className="field">
                <span>Objet</span>
                <input name="subject" className="input" required maxLength={200} placeholder="Ex. doublons après l’import SIRENE" />
              </label>
              <label className="field">
                <span>Priorité</span>
                <select name="priority" className="input" defaultValue="NORMAL">
                  <option value="LOW">Basse — question, suggestion</option>
                  <option value="NORMAL">Normale</option>
                  <option value="HIGH">Haute — bloque une campagne ou un envoi</option>
                  <option value="URGENT">Urgente — portail inaccessible</option>
                </select>
              </label>
              <label className="field">
                <span>Votre demande</span>
                <textarea name="body" className="textarea" rows={6} required placeholder="Décrivez le problème, la page concernée et ce que vous attendiez." />
              </label>
              <p style={{ margin: 0, fontSize: 12, color: 'var(--muted)' }}>
                Si besoin, le support peut ouvrir un accès temporaire de 30 minutes à votre back-office : il apparaît alors dans votre journal d’audit.
              </p>
              <SubmitButton className="btn btn-brand" style={{ alignSelf: 'flex-start' }} pendingLabel="Envoi…">
                Envoyer la demande
              </SubmitButton>
            </ActionForm>
          </section>
        )}
      </div>
    </div>
  );
}
