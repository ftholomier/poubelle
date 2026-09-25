import { and, desc, eq, isNull, ne } from 'drizzle-orm';
import Link from 'next/link';
import { markMessage, replyMessage } from '../actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { fmtInboxTime, fmtPhone, fmtStamp, telHref } from '@/lib/format';
import { db } from '@/server/db';
import { messages } from '@/server/db/schema';
import { loadProContext } from '@/server/services/pro';

type Props = { params: Promise<{ est: string }>; searchParams: Promise<Record<string, string | undefined>> };

const BG = ['var(--lilac)', 'var(--leaf)', 'var(--sky)', 'var(--rose)', 'var(--amber-soft)'];

export default async function MessagesPage({ params, searchParams }: Props) {
  const { est: estId } = await params;
  const sp = await searchParams;
  const ctx = await loadProContext(estId);
  const { est, base } = ctx;
  const archived = sp.vue === 'archives';
  const list = await db
    .select()
    .from(messages)
    .where(and(eq(messages.establishmentId, est.id), archived ? eq(messages.status, 'ARCHIVED') : ne(messages.status, 'ARCHIVED'), ne(messages.status, 'SPAM')))
    .orderBy(desc(messages.createdAt))
    .limit(200);
  const selected = list.find((m) => m.id === sp.m) ?? list[0] ?? null;
  if (selected && !selected.readAt) {
    await db
      .update(messages)
      .set({ readAt: new Date(), status: selected.status === 'NEW' ? 'READ' : selected.status })
      .where(and(eq(messages.id, selected.id), isNull(messages.readAt)));
  }
  return (
    <div className="app-content">
      <div style={{ display: 'flex', gap: 6 }}>
        <Link href={`${base}/messages`} className={`chip${!archived ? ' is-active' : ''}`}>
          Boîte de réception
        </Link>
        <Link href={`${base}/messages?vue=archives`} className={`chip${archived ? ' is-active' : ''}`}>
          Archives
        </Link>
      </div>
      {list.length ? (
        <div
          className="split"
          style={{ ['--cols' as string]: 'minmax(260px,380px) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}
        >
          <div className="card" style={{ borderRadius: 18, overflow: 'hidden' }}>
            {list.map((m, i) => (
              <Link
                key={m.id}
                href={`${base}/messages?m=${m.id}${archived ? '&vue=archives' : ''}`}
                style={{
                  display: 'grid',
                  gridTemplateColumns: '36px 1fr auto',
                  gap: 10,
                  padding: '12px 14px',
                  borderTop: i ? '1px solid var(--line-2)' : 0,
                  alignItems: 'center',
                  color: 'var(--text)',
                  background: selected?.id === m.id ? 'var(--mint-2)' : 'transparent',
                }}
              >
                <div
                  style={{
                    width: 36,
                    height: 36,
                    borderRadius: '50%',
                    background: BG[i % BG.length],
                    display: 'grid',
                    placeItems: 'center',
                    fontWeight: 800,
                    fontSize: 13,
                  }}
                >
                  {m.senderName.charAt(0).toUpperCase()}
                </div>
                <div style={{ minWidth: 0 }}>
                  <div style={{ fontWeight: m.readAt ? 600 : 800, fontSize: 14 }}>{m.senderName}</div>
                  <div style={{ fontSize: 13, color: 'var(--muted)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
                    {m.formId && m.subject ? `${m.subject} · ` : ''}
                    {m.body}
                  </div>
                </div>
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: 4 }}>
                  <span style={{ fontSize: 12, color: 'var(--muted)' }}>{fmtInboxTime(m.createdAt)}</span>
                  {!m.readAt ? <span style={{ width: 8, height: 8, borderRadius: '50%', background: 'var(--danger)' }} aria-label="Non lu" /> : null}
                  {m.status === 'REPLIED' ? <span style={{ fontSize: 10, fontWeight: 800, color: 'var(--green)' }}>RÉPONDU</span> : null}
                </div>
              </Link>
            ))}
          </div>
          {selected ? (
            <div className="panel" style={{ borderRadius: 18 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', gap: 12, flexWrap: 'wrap' }}>
                <div>
                  <div className="display" style={{ fontSize: 22 }}>
                    {selected.senderName}
                  </div>
                  <div style={{ fontSize: 13, color: 'var(--muted)' }}>
                    {[selected.senderEmail, selected.senderPhone ? fmtPhone(selected.senderPhone) : null].filter(Boolean).join(' · ')} · reçu le{' '}
                    {fmtStamp(selected.createdAt)}
                  </div>
                </div>
                <div style={{ display: 'flex', gap: 8 }}>
                  {selected.senderPhone ? (
                    <a href={telHref(selected.senderPhone)} className="btn btn-outline btn-sm">
                      Appeler
                    </a>
                  ) : null}
                  <form action={markMessage}>
                    <input type="hidden" name="estId" value={est.id} />
                    <input type="hidden" name="messageId" value={selected.id} />
                    <input type="hidden" name="status" value={selected.status === 'ARCHIVED' ? 'READ' : 'ARCHIVED'} />
                    <button type="submit" className="btn btn-ghost btn-sm">
                      {selected.status === 'ARCHIVED' ? 'Désarchiver' : 'Archiver'}
                    </button>
                  </form>
                </div>
              </div>
              {selected.subject ? (
                <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                  {selected.formId ? (
                    <span className="tag" style={{ background: 'var(--lilac)' }}>
                      Formulaire
                    </span>
                  ) : null}
                  <b>{selected.subject}</b>
                </div>
              ) : null}
              {selected.answers?.length ? (
                <dl className="answers-list">
                  {selected.answers.map((a, i) => (
                    <div key={i}>
                      <dt>{a.label}</dt>
                      <dd>{a.value}</dd>
                    </div>
                  ))}
                </dl>
              ) : (
                <div style={{ background: 'var(--cream)', borderRadius: 12, padding: 16, fontSize: 15, lineHeight: 1.6, whiteSpace: 'pre-line' }}>
                  {selected.body}
                </div>
              )}
              {selected.senderEmail ? (
                <ActionForm action={replyMessage} style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
                  <input type="hidden" name="estId" value={est.id} />
                  <input type="hidden" name="messageId" value={selected.id} />
                  <label className="field">
                    <span>Votre réponse (envoyée par email à {selected.senderEmail})</span>
                    <textarea
                      name="reply"
                      rows={5}
                      className="textarea"
                      required
                      maxLength={5000}
                      placeholder={`Bonjour ${selected.senderName.split(' ')[0]},`}
                    />
                  </label>
                  <button type="submit" className="btn btn-brand" style={{ alignSelf: 'flex-start' }}>
                    Envoyer la réponse
                  </button>
                </ActionForm>
              ) : (
                <div className="alert alert-info">
                  Pas d&apos;email laissé : rappelez cette personne au {selected.senderPhone ? fmtPhone(selected.senderPhone) : 'numéro indiqué'}.
                </div>
              )}
              <p style={{ margin: 0, fontSize: 12, color: 'var(--muted)' }}>
                Les messages sont conservés 3 ans puis supprimés automatiquement (RGPD). Ne réutilisez pas ces coordonnées à des fins commerciales sans accord.
              </p>
            </div>
          ) : null}
        </div>
      ) : (
        <div className="card card-pad" style={{ color: 'var(--muted)' }}>
          {archived ? 'Aucun message archivé.' : 'Aucun message pour l’instant. Les visiteurs peuvent vous écrire depuis votre fiche.'}
        </div>
      )}
    </div>
  );
}
