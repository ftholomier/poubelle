import { and, desc, eq, inArray, isNull, ne } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import {
  addEstablishmentAction,
  autoFillAction,
  createAudienceAction,
  createNewsletterAction,
  deleteAudienceAction,
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
import { pickableCategories } from '@/server/services/categories';
import { FAMILIES, FAMILY_ORDER } from '@/lib/constants';
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
  // Une mairie n'utilise que les audiences sans zone ou dont la zone comprend sa commune.
  const visibleAudiences = ctx.commune
    ? audienceList.filter((a) => {
        const zone = [...(a.criteria.communeIds ?? []), ...(a.communeId ? [a.communeId] : [])];
        return !zone.length || zone.includes(ctx.commune!.id);
      })
    : audienceList;
  const withPros = visibleAudiences.some((a) => a.kind === 'BUSINESSES' && n.audienceIds.includes(a.id));
  const cats = ctx.level === 'TERRITORY' ? await pickableCategories(ctx.territory.id) : [];
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
              {withPros
                ? '✓ Habitants : consentement explicite (double opt-in) · professionnels : information de la collectivité · désinscription en 1 clic'
                : '✓ 100 % avec consentement explicite (double opt-in) · désinscription en 1 clic'}
            </div>
            {ctx.level === 'TERRITORY' ? (
              <details className="bo-details">
                <summary style={{ fontSize: 13, fontWeight: 700, cursor: 'pointer' }}>Gérer les audiences</summary>
                <div style={{ display: 'flex', flexDirection: 'column', gap: 6, marginTop: 10 }}>
                  {visibleAudiences
                    .filter((a) => !a.isDefault)
                    .map((a) => (
                      <form key={a.id} action={deleteAudienceAction} style={{ display: 'flex', justifyContent: 'space-between', gap: 8, fontSize: 13 }}>
                        <input type="hidden" name="audienceId" value={a.id} />
                        <span>
                          {a.name} · {fmtInt(a.n)}
                        </span>
                        {ctx.access === 'ADMIN' ? (
                          <button type="submit" className="btn-link" style={{ color: 'var(--danger-fg)', fontSize: 12 }}>
                            Supprimer
                          </button>
                        ) : null}
                      </form>
                    ))}
                </div>
                <ActionForm action={createAudienceAction} style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 12 }}>
                  <input name="name" className="input" placeholder="Nom (ex. Plateau d’Amancey)" required aria-label="Nom de l’audience" />
                  <input name="description" className="input" placeholder="Description (facultatif)" aria-label="Description" />
                  <select name="kind" className="input" defaultValue="COMMUNE" aria-label="Type d’audience">
                    <option value="COMMUNE">Zone géographique : les habitants abonnés de communes choisies</option>
                    <option value="BUSINESSES">Professionnels : familles ou catégories d’activité</option>
                    <option value="MANUAL">Liste (inscriptions, import)</option>
                  </select>
                  <fieldset style={{ border: '1px solid var(--line)', borderRadius: 10, padding: '8px 10px' }}>
                    <legend style={{ fontSize: 12, fontWeight: 700, padding: '0 4px' }}>Communes de la zone</legend>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '4px 12px', maxHeight: 120, overflowY: 'auto' }}>
                      {ctx.communes.map((c) => (
                        <label key={c.id} style={{ fontSize: 12, display: 'flex', gap: 4, alignItems: 'center' }}>
                          <input type="checkbox" name="communeIds" value={c.id} />
                          {c.name}
                        </label>
                      ))}
                    </div>
                  </fieldset>
                  <fieldset style={{ border: '1px solid var(--line)', borderRadius: 10, padding: '8px 10px' }}>
                    <legend style={{ fontSize: 12, fontWeight: 700, padding: '0 4px' }}>Professionnels visés</legend>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '4px 12px' }}>
                      {FAMILY_ORDER.map((f) => (
                        <label key={f} style={{ fontSize: 12, display: 'flex', gap: 4, alignItems: 'center' }}>
                          <input type="checkbox" name="families" value={f} />
                          {FAMILIES[f].label}
                        </label>
                      ))}
                    </div>
                    <select
                      name="categoryIds"
                      multiple
                      className="input"
                      aria-label="Catégories (facultatif)"
                      style={{ marginTop: 6, height: 90, fontSize: 12 }}
                    >
                      {cats.map((c) => (
                        <option key={c.id} value={c.id}>
                          {c.name}
                        </option>
                      ))}
                    </select>
                    <span style={{ fontSize: 11, color: 'var(--muted)' }}>Sans choix : tous les professionnels du territoire.</span>
                  </fieldset>
                  <SubmitButton className="btn btn-outline btn-sm" pendingLabel="Création…" style={{ alignSelf: 'flex-start' }}>
                    Créer l’audience
                  </SubmitButton>
                </ActionForm>
              </details>
            ) : null}
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
