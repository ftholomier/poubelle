import { and, desc, eq, inArray, isNull, ne } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import {
  addEstablishmentAction,
  autoFillAction,
  createNewsletterAction,
  deleteDraftAction,
  removeItemAction,
  saveContentAction,
  unscheduleAction,
} from './actions';
import { AudiencePicker, ScheduleForm } from '@/components/bo/NewsletterForms';
import { ActionForm } from '@/components/pro/ActionForm';
import { FileDrop } from '@/components/ui/FileDrop';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { fmtDecimal, fmtInt, fmtStamp, parisDate, parisParts } from '@/lib/format';
import { db } from '@/server/db';
import { establishments, newsletters, type NewsletterBlock } from '@/server/db/schema';
import { loadBoContext } from '@/server/services/backoffice';
import { audienceStats, previewHtml, recentRates, recipientCount } from '@/server/services/newsletters';

export const metadata: Metadata = { title: 'Newsletter territoriale' };

type Props = { searchParams: Promise<Record<string, string | undefined>> };

const STATUS = { DRAFT: 'brouillon', SCHEDULED: 'programmée', SENDING: 'envoi en cours', SENT: 'envoyée', CANCELLED: 'annulée' } as const;

/** Prochain vendredi 8 h (créneau recommandé pour une lettre « week-end »). */
function nextFriday(): { date: string; time: string } {
  const now = new Date();
  const p = parisParts(now);
  const add = (4 - p.weekday + 7) % 7 || 7;
  return { date: parisDate(new Date(now.getTime() + add * 86_400_000)), time: '08:00' };
}

function pct(v: number): string {
  return `${fmtDecimal(v * 100, v * 100 < 1 ? 1 : 0)}%`;
}

export default async function NewsletterPage({ searchParams }: Props) {
  const ctx = await loadBoContext();
  const sp = await searchParams;
  const list = await db
    .select({
      id: newsletters.id,
      number: newsletters.number,
      subject: newsletters.subject,
      status: newsletters.status,
      sentAt: newsletters.sentAt,
      scheduledAt: newsletters.scheduledAt,
    })
    .from(newsletters)
    .where(
      and(
        eq(newsletters.territoryId, ctx.territory.id),
        isNull(newsletters.companyId),
        ne(newsletters.status, 'CANCELLED'),
        ctx.commune ? eq(newsletters.communeId, ctx.commune.id) : undefined,
      ),
    )
    .orderBy(desc(newsletters.createdAt))
    .limit(8);
  const currentId = sp.lettre ?? list.find((n) => n.status === 'DRAFT' || n.status === 'SCHEDULED')?.id ?? list[0]?.id;
  const [n] =
    currentId && /^[0-9a-f-]{36}$/.test(currentId)
      ? await db
          .select()
          .from(newsletters)
          .where(and(eq(newsletters.id, currentId), eq(newsletters.territoryId, ctx.territory.id)))
          .limit(1)
      : [];

  const newButton = (
    <form action={createNewsletterAction}>
      {sp.campagne ? <input type="hidden" name="campaignId" value={sp.campagne} /> : null}
      <SubmitButton className="btn btn-brand btn-sm" pendingLabel="Préparation…">
        {sp.campagne ? '+ Lettre de la campagne' : '+ Nouvelle lettre'}
      </SubmitButton>
    </form>
  );

  if (!n) {
    return (
      <div className="app-content">
        <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 12, alignItems: 'flex-start' }}>
          <b style={{ fontSize: 18 }}>Aucune lettre pour l’instant</b>
          <p style={{ margin: 0, color: 'var(--muted)' }}>La première lettre est préremplie avec les événements du week-end et les nouveautés des commerces.</p>
          {newButton}
        </section>
      </div>
    );
  }

  const [html, audienceList, total, rates] = await Promise.all([
    previewHtml(n, ctx.territory),
    audienceStats(ctx.territory.id),
    recipientCount(ctx.territory.id, n.audienceIds),
    recentRates(ctx.territory.id),
  ]);
  const body = html.slice(html.indexOf('<body'), html.lastIndexOf('</body>')).replace(/^<body[^>]*>/, '');
  const editable = n.status === 'DRAFT' || n.status === 'SCHEDULED';
  const estBlock = n.blocks.find((b): b is Extract<NewsletterBlock, { type: 'establishments' }> => b.type === 'establishments');
  const featured = estBlock?.ids.length
    ? await db
        .select({ id: establishments.id, name: establishments.name })
        .from(establishments)
        .where(and(eq(establishments.territoryId, ctx.territory.id), inArray(establishments.id, estBlock.ids)))
    : [];
  const cta = n.blocks.find((b): b is Extract<NewsletterBlock, { type: 'cta' }> => b.type === 'cta');
  const visibleAudiences = ctx.commune ? audienceList.filter((a) => !a.communeId || a.communeId === ctx.commune!.id) : audienceList;
  const slot = n.scheduledAt
    ? {
        date: parisDate(n.scheduledAt),
        time: `${String(parisParts(n.scheduledAt).hour).padStart(2, '0')}:${String(parisParts(n.scheduledAt).minute).padStart(2, '0')}`,
      }
    : nextFriday();

  return (
    <div className="app-content">
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
        {list.map((l) => (
          <Link key={l.id} href={`/collectivite/newsletter?lettre=${l.id}`} className="bo-chip" aria-current={l.id === n.id ? 'true' : undefined}>
            n°{l.number ?? '—'}
            <small>{STATUS[l.status]}</small>
          </Link>
        ))}
        <div style={{ marginLeft: 'auto' }}>{newButton}</div>
      </div>
      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1fr) 380px', ['--gap' as string]: '20px', ['--align' as string]: 'start' }}>
        <div
          className="nl-preview"
          style={{ background: 'var(--sand)', borderRadius: 22, overflow: 'hidden' }}
          aria-label="Aperçu de la lettre"
          dangerouslySetInnerHTML={{ __html: body }}
        />
        <div className="sticky-aside" style={{ display: 'flex', flexDirection: 'column', gap: 14, position: 'sticky', top: 90 }}>
          <section className="bo-card" style={{ padding: 18, display: 'flex', flexDirection: 'column', gap: 8 }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: 8 }}>
              <b>Contenu</b>
              <span style={{ fontSize: 12, color: 'var(--muted)' }}>
                n°{n.number} · {STATUS[n.status]}
                {n.sentAt ? ` le ${fmtStamp(n.sentAt)}` : ''}
              </span>
            </div>
            {editable ? (
              <details>
                <summary style={{ cursor: 'pointer', fontSize: 14, fontWeight: 600, color: 'var(--green)' }}>Modifier le texte, le visuel et le bouton</summary>
                <ActionForm action={saveContentAction} resetOnSuccess={false} style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 10 }}>
                  <input type="hidden" name="newsletterId" value={n.id} />
                  <label className="field">
                    <span>Objet de l’email</span>
                    <input name="subject" className="input" defaultValue={n.subject} required maxLength={255} />
                  </label>
                  <label className="field">
                    <span>Texte d’aperçu</span>
                    <input name="preheader" className="input" defaultValue={n.preheader ?? ''} maxLength={255} />
                  </label>
                  <label className="field">
                    <span>Titre</span>
                    <input name="title" className="input" defaultValue={n.title} required maxLength={255} />
                  </label>
                  <label className="field">
                    <span>Introduction</span>
                    <textarea name="intro" className="input" rows={4} defaultValue={n.intro} maxLength={3000} />
                  </label>
                  <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                    <label className="field">
                      <span>Bouton</span>
                      <input name="ctaLabel" className="input" defaultValue={cta?.label ?? ''} maxLength={64} />
                    </label>
                    <label className="field">
                      <span>Lien</span>
                      <input name="ctaUrl" className="input" defaultValue={cta?.url ?? ''} placeholder="/agenda" />
                    </label>
                  </div>
                  <FileDrop name="hero" accept="image/jpeg,image/png,image/webp" label="Changer le visuel" />
                  <SubmitButton className="btn btn-dark btn-sm" pendingLabel="Enregistrement…" style={{ alignSelf: 'flex-start' }}>
                    Enregistrer
                  </SubmitButton>
                </ActionForm>
              </details>
            ) : null}
            {editable ? (
              <>
                <div style={{ fontSize: 13, fontWeight: 700, marginTop: 4 }}>Adresses mises en avant</div>
                {featured.map((f) => (
                  <form
                    key={f.id}
                    action={removeItemAction}
                    style={{ display: 'flex', justifyContent: 'space-between', gap: 8, fontSize: 13, borderTop: '1px solid var(--line-2)', paddingTop: 6 }}
                  >
                    <input type="hidden" name="newsletterId" value={n.id} />
                    <input type="hidden" name="itemId" value={f.id} />
                    <span>{f.name}</span>
                    <button type="submit" className="btn-link" style={{ color: 'var(--danger-fg)', fontSize: 12 }}>
                      Retirer
                    </button>
                  </form>
                ))}
                <ActionForm action={addEstablishmentAction} style={{ display: 'flex', gap: 6 }}>
                  <input type="hidden" name="newsletterId" value={n.id} />
                  <input name="q" className="input" placeholder="Ajouter un établissement…" aria-label="Ajouter un établissement" style={{ fontSize: 13 }} />
                  <SubmitButton className="btn btn-outline btn-sm">Ajouter</SubmitButton>
                </ActionForm>
                <form action={autoFillAction}>
                  <input type="hidden" name="newsletterId" value={n.id} />
                  <SubmitButton className="btn-link" pendingLabel="Composition…" style={{ fontSize: 13 }}>
                    ✦ Recomposer avec l’actualité de la semaine
                  </SubmitButton>
                </form>
              </>
            ) : null}
          </section>
          <section className="bo-card" style={{ padding: 18, display: 'flex', flexDirection: 'column', gap: 8 }}>
            <b>Audiences</b>
            <AudiencePicker newsletterId={n.id} items={visibleAudiences} selected={n.audienceIds} disabled={!editable} />
            <div style={{ display: 'flex', justifyContent: 'space-between', paddingTop: 8, borderTop: '1px solid var(--line-2)', fontSize: 14 }}>
              <span>Destinataires</span>
              <b>{fmtInt(n.status === 'SENT' || n.status === 'SENDING' ? n.statsRecipients : total)}</b>
            </div>
            <div style={{ fontSize: 12, color: 'var(--green)', fontWeight: 600 }}>
              ✓ 100 % avec consentement explicite (double opt-in) · désinscription en 1 clic
            </div>
          </section>
          <section className="bo-card" style={{ padding: 18, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <b>Envoi</b>
            {n.status === 'SCHEDULED' && n.scheduledAt ? (
              <div className="alert alert-info" style={{ margin: 0 }}>
                Programmée le {fmtStamp(n.scheduledAt)}.
                <form action={unscheduleAction} style={{ display: 'inline' }}>
                  <input type="hidden" name="newsletterId" value={n.id} />{' '}
                  <button type="submit" className="btn-link">
                    Annuler la programmation
                  </button>
                </form>
              </div>
            ) : null}
            {editable ? (
              <ScheduleForm newsletterId={n.id} defaultDate={slot.date} defaultTime={slot.time} />
            ) : (
              <div style={{ fontSize: 14 }}>
                {n.status === 'SENDING'
                  ? `Envoi en cours : ${fmtInt(n.statsSent)} / ${fmtInt(n.statsRecipients)}`
                  : `Envoyée à ${fmtInt(n.statsSent)} abonnés${n.sentAt ? ` le ${fmtStamp(n.sentAt)}` : ''}.`}
              </div>
            )}
            {n.status === 'DRAFT' ? (
              <form action={deleteDraftAction}>
                <input type="hidden" name="newsletterId" value={n.id} />
                <button type="submit" className="btn-link" style={{ color: 'var(--danger-fg)', fontSize: 12 }}>
                  Supprimer ce brouillon
                </button>
              </form>
            ) : null}
          </section>
          <section
            className="bo-card"
            style={{ padding: 18, display: 'grid', gridTemplateColumns: 'repeat(3,1fr)', gap: 8, textAlign: 'center' }}
            aria-label="Statistiques des dernières lettres"
          >
            {n.status === 'SENT'
              ? [
                  [n.statsSent ? n.statsOpens / n.statsSent : 0, 'ouverture'],
                  [n.statsSent ? n.statsClicks / n.statsSent : 0, 'clics'],
                  [n.statsSent ? n.statsUnsubscribes / n.statsSent : 0, 'désinscrits'],
                ].map(([v, l]) => (
                  <div key={l as string}>
                    <div className="display" style={{ fontSize: 26 }}>
                      {pct(v as number)}
                    </div>
                    <div style={{ fontSize: 11, color: 'var(--muted)' }}>{l}</div>
                  </div>
                ))
              : [
                  [rates.opens, 'ouverture'],
                  [rates.clicks, 'clics'],
                  [rates.unsubscribes, 'désinscrits'],
                ].map(([v, l]) => (
                  <div key={l as string}>
                    <div className="display" style={{ fontSize: 26 }}>
                      {rates.hasData ? pct(v as number) : '—'}
                    </div>
                    <div style={{ fontSize: 11, color: 'var(--muted)' }}>{l}</div>
                  </div>
                ))}
          </section>
        </div>
      </div>
    </div>
  );
}
