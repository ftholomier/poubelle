import { and, desc, eq, ilike, sql, type SQL } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { resendEmailAction } from './actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { fmtDecimal, fmtInt, fmtStamp } from '@/lib/format';
import { requirePlatformStaff } from '@/server/authz';
import { db } from '@/server/db';
import { emails, newsletters, territories } from '@/server/db/schema';
import { env } from '@/server/env';

export const metadata: Metadata = { title: 'Emails' };

type Props = { searchParams: Promise<{ statut?: string; q?: string; email?: string }> };

const STATUS: Record<string, { label: string; bg: string }> = {
  SENT: { label: 'Envoyé', bg: '#D6E8B4' },
  OUTBOX: { label: 'Conservé', bg: '#E4E7E1' },
  QUEUED: { label: 'En file', bg: '#CDE3F2' },
  FAILED: { label: 'Échec', bg: '#F5DCD8' },
};

export default async function EmailsPage({ searchParams }: Props) {
  const sp = await searchParams;
  await requirePlatformStaff();
  const status = sp.statut && sp.statut in STATUS ? (sp.statut as keyof typeof STATUS) : undefined;
  const q = sp.q?.trim().slice(0, 100);
  const conds: SQL[] = [];
  if (status) conds.push(eq(emails.status, status as 'SENT' | 'OUTBOX' | 'QUEUED' | 'FAILED'));
  if (q) conds.push(ilike(emails.to, `%${q.replace(/[%_\\]/g, (m) => `\\${m}`)}%`));
  const [list, [k], nls] = await Promise.all([
    db
      .select({
        id: emails.id,
        to: emails.to,
        subject: emails.subject,
        template: emails.template,
        status: emails.status,
        error: emails.error,
        createdAt: emails.createdAt,
        sentAt: emails.sentAt,
        territory: territories.name,
      })
      .from(emails)
      .leftJoin(territories, eq(territories.id, emails.territoryId))
      .where(conds.length ? and(...conds) : undefined)
      .orderBy(desc(emails.createdAt))
      .limit(100),
    db
      .select({
        total: sql<number>`count(*)::int`,
        sent: sql<number>`count(*) filter (where ${emails.status} in ('SENT', 'OUTBOX'))::int`,
        failed: sql<number>`count(*) filter (where ${emails.status} = 'FAILED')::int`,
        queued: sql<number>`count(*) filter (where ${emails.status} = 'QUEUED')::int`,
      })
      .from(emails)
      .where(sql`${emails.createdAt} >= now() - interval '30 days'`),
    db
      .select({
        id: newsletters.id,
        subject: newsletters.subject,
        number: newsletters.number,
        sentAt: newsletters.sentAt,
        status: newsletters.status,
        recipients: newsletters.statsRecipients,
        sent: newsletters.statsSent,
        opens: newsletters.statsOpens,
        clicks: newsletters.statsClicks,
        unsub: newsletters.statsUnsubscribes,
        bounces: newsletters.statsBounces,
        territory: territories.name,
      })
      .from(newsletters)
      .innerJoin(territories, eq(territories.id, newsletters.territoryId))
      .where(sql`${newsletters.status} in ('SENT', 'SENDING', 'SCHEDULED')`)
      .orderBy(sql`${newsletters.sentAt} desc nulls first`)
      .limit(12),
  ]);
  const selected = sp.email && /^[0-9a-f-]{36}$/.test(sp.email) ? list.find((m) => m.id === sp.email) : undefined;
  const nlTotals = nls.reduce((a, n) => ({ sent: a.sent + n.sent, opens: a.opens + n.opens, clicks: a.clicks + n.clicks, bounces: a.bounces + n.bounces }), {
    sent: 0,
    opens: 0,
    clicks: 0,
    bounces: 0,
  });
  const tiles = [
    { l: 'Emails transactionnels (30 j)', v: fmtInt(k?.total ?? 0), d: env.MAIL_DRIVER === 'smtp' ? 'envoi SMTP' : 'mode boîte d’envoi (démo)' },
    { l: 'Échecs', v: fmtInt(k?.failed ?? 0), d: `${fmtInt(k?.queued ?? 0)} en file`, warn: (k?.failed ?? 0) > 0 },
    { l: 'Newsletters envoyées', v: fmtInt(nlTotals.sent), d: `${nls.filter((n) => n.status === 'SENT').length} campagnes récentes` },
    {
      l: 'Ouverture moyenne',
      v: nlTotals.sent ? `${fmtDecimal((nlTotals.opens / nlTotals.sent) * 100, 1)} %` : '—',
      d: nlTotals.sent ? `clics ${fmtDecimal((nlTotals.clicks / nlTotals.sent) * 100, 1)} %` : '',
    },
    { l: 'Rebonds', v: nlTotals.sent ? `${fmtDecimal((nlTotals.bounces / nlTotals.sent) * 100, 2)} %` : '—', d: 'adresses invalides désactivées' },
  ];
  const qs = (extra: Record<string, string | undefined>) => {
    const p = new URLSearchParams();
    for (const [key, v] of Object.entries({ statut: status, q, ...extra })) if (v) p.set(key, v);
    return `/console/emails${p.size ? `?${p}` : ''}`;
  };

  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 12 }}>
        {tiles.map((x) => (
          <div key={x.l} className="console-card" style={{ borderRadius: 18, padding: 18 }}>
            <div style={{ fontSize: 13, fontWeight: 600, color: 'var(--muted)' }}>{x.l}</div>
            <div className="display" style={{ fontSize: 30, letterSpacing: '-0.03em', color: x.warn ? 'var(--brick)' : undefined }}>
              {x.v}
            </div>
            <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--muted)' }}>{x.d}</div>
          </div>
        ))}
      </div>
      <div
        className="split"
        style={{
          ['--cols' as string]: selected ? 'minmax(0,1.1fr) minmax(0,1fr)' : 'minmax(0,1fr)',
          ['--gap' as string]: '18px',
          ['--align' as string]: 'start',
        }}
      >
        <section className="console-card" style={{ borderRadius: 20, overflow: 'hidden' }}>
          <div style={{ display: 'flex', gap: 10, padding: '14px 18px', alignItems: 'center', flexWrap: 'wrap' }}>
            <b>Emails émis</b>
            <form method="get" style={{ display: 'flex', gap: 6, marginLeft: 'auto', flexWrap: 'wrap' }}>
              <select
                name="statut"
                className="input"
                defaultValue={status ?? ''}
                aria-label="Statut"
                style={{ padding: '7px 10px', fontSize: 13, width: 'auto' }}
              >
                <option value="">Tous statuts</option>
                {Object.entries(STATUS).map(([key, s]) => (
                  <option key={key} value={key}>
                    {s.label}
                  </option>
                ))}
              </select>
              <input
                name="q"
                className="input"
                defaultValue={q ?? ''}
                placeholder="Destinataire…"
                aria-label="Destinataire"
                style={{ padding: '7px 10px', fontSize: 13, width: 180 }}
              />
              <button type="submit" className="btn btn-dark btn-sm">
                Filtrer
              </button>
            </form>
          </div>
          <div style={{ overflowX: 'auto' }}>
            <table className="console-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Destinataire</th>
                  <th>Objet</th>
                  <th>Modèle</th>
                  <th>Statut</th>
                </tr>
              </thead>
              <tbody>
                {list.map((m) => (
                  <tr key={m.id} style={{ background: m.id === selected?.id ? '#F2F8F4' : undefined }}>
                    <td style={{ whiteSpace: 'nowrap', fontSize: 12, color: 'var(--muted)' }}>{fmtStamp(m.createdAt)}</td>
                    <td style={{ maxWidth: 200, overflow: 'hidden', textOverflow: 'ellipsis' }}>{m.to}</td>
                    <td>
                      <Link href={qs({ email: m.id })} scroll={false} style={{ fontWeight: 600 }}>
                        {m.subject}
                      </Link>
                      {m.territory ? <div style={{ fontSize: 12, color: 'var(--muted)' }}>{m.territory}</div> : null}
                      {m.error ? <div style={{ fontSize: 12, color: 'var(--danger-fg)' }}>{m.error}</div> : null}
                    </td>
                    <td style={{ fontSize: 12, color: 'var(--muted)' }}>{m.template ?? '—'}</td>
                    <td>
                      <span
                        style={{ fontSize: 11, fontWeight: 800, padding: '3px 8px', borderRadius: 999, background: STATUS[m.status].bg, whiteSpace: 'nowrap' }}
                      >
                        {STATUS[m.status].label}
                      </span>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            {list.length === 0 ? <p style={{ padding: '6px 18px 16px', color: 'var(--muted)', margin: 0 }}>Aucun email pour ces critères.</p> : null}
          </div>
        </section>
        {selected ? (
          <section className="console-card sticky-aside" style={{ borderRadius: 20, overflow: 'hidden', position: 'sticky', top: 20 }}>
            <div style={{ display: 'flex', gap: 10, padding: '12px 16px', alignItems: 'center', borderBottom: '1px solid var(--console-line-2)' }}>
              <div style={{ minWidth: 0, flex: 1 }}>
                <b style={{ display: 'block', overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{selected.subject}</b>
                <span style={{ fontSize: 12, color: 'var(--muted)' }}>
                  à {selected.to} · {fmtStamp(selected.createdAt)}
                </span>
              </div>
              {selected.status === 'FAILED' ? (
                <ActionForm action={resendEmailAction}>
                  <input type="hidden" name="id" value={selected.id} />
                  <SubmitButton className="btn btn-dark btn-xs">Renvoyer</SubmitButton>
                </ActionForm>
              ) : null}
              <Link href={qs({})} scroll={false} aria-label="Fermer l’aperçu" style={{ fontSize: 18, color: 'var(--muted)' }}>
                ×
              </Link>
            </div>
            <iframe
              src={`/api/console/emails/${selected.id}`}
              title={`Aperçu : ${selected.subject}`}
              sandbox=""
              style={{ width: '100%', height: 640, border: 0, display: 'block', background: '#EDE8DC' }}
            />
          </section>
        ) : null}
      </div>
      <section className="console-card" style={{ borderRadius: 20, overflow: 'hidden' }}>
        <div style={{ padding: '14px 18px' }}>
          <b>Newsletters des territoires</b>
        </div>
        <div style={{ overflowX: 'auto' }}>
          <table className="console-table">
            <thead>
              <tr>
                <th>Territoire</th>
                <th>Lettre</th>
                <th>Envoi</th>
                <th style={{ textAlign: 'right' }}>Destinataires</th>
                <th style={{ textAlign: 'right' }}>Ouvertures</th>
                <th style={{ textAlign: 'right' }}>Clics</th>
                <th style={{ textAlign: 'right' }}>Désinscriptions</th>
              </tr>
            </thead>
            <tbody>
              {nls.map((n) => (
                <tr key={n.id}>
                  <td>{n.territory}</td>
                  <td>
                    <b>{n.number ? `n°${n.number} · ` : ''}</b>
                    {n.subject}
                  </td>
                  <td style={{ whiteSpace: 'nowrap', fontSize: 12, color: 'var(--muted)' }}>
                    {n.sentAt ? fmtStamp(n.sentAt) : n.status === 'SCHEDULED' ? 'programmée' : 'en cours'}
                  </td>
                  <td style={{ textAlign: 'right' }}>{fmtInt(n.recipients)}</td>
                  <td style={{ textAlign: 'right' }}>{n.sent ? `${fmtDecimal((n.opens / n.sent) * 100, 1)} %` : '—'}</td>
                  <td style={{ textAlign: 'right' }}>{n.sent ? `${fmtDecimal((n.clicks / n.sent) * 100, 1)} %` : '—'}</td>
                  <td style={{ textAlign: 'right' }}>{fmtInt(n.unsub)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}
