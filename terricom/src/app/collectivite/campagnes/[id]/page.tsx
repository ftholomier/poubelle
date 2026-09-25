import { and, asc, eq, inArray } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { addParticipantAction, inviteParticipantsAction, removeParticipantAction, saveDoorAction, updateCampaignAction } from '../actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { SubmitButton } from '@/components/ui/SubmitButton';
import { FileDrop } from '@/components/ui/FileDrop';
import { Photo } from '@/components/ui/Photo';
import { fmtInt, relativeTime } from '@/lib/format';
import { sized } from '@/lib/images';
import { db } from '@/server/db';
import { adventDoors, campaignParticipants, campaigns, communes, establishments } from '@/server/db/schema';
import { campaignScope, canEditCampaign, loadBoContext, shortLegalName } from '@/server/services/backoffice';
import { portalUrl } from '@/server/urls';

export const metadata: Metadata = { title: 'Campagne' };

type Props = {
  params: Promise<{ id: string }>;
  searchParams: Promise<Record<string, string | undefined>>;
};

const PSTATUS = {
  JOINED: { label: 'Participe', bg: 'var(--mint)', fg: 'var(--green)' },
  INVITED: { label: 'Invité', bg: 'var(--info-bg)', fg: 'var(--info-fg)' },
  DECLINED: { label: 'Décliné', bg: 'var(--sand)', fg: 'var(--muted)' },
} as const;

export default async function CampaignEditPage({ params, searchParams }: Props) {
  const { id } = await params;
  const sp = await searchParams;
  if (!/^[0-9a-f-]{36}$/.test(id)) notFound();
  const ctx = await loadBoContext();
  const [c] = await db
    .select()
    .from(campaigns)
    .where(and(eq(campaigns.id, id), campaignScope(ctx)))
    .limit(1);
  if (!c) notFound();
  const editable = canEditCampaign(ctx, c);
  const [owner] = c.communeId ? await db.select({ name: communes.name }).from(communes).where(eq(communes.id, c.communeId)).limit(1) : [];
  const [participants, doors] = await Promise.all([
    db
      .select({
        id: establishments.id,
        name: establishments.name,
        coverUrl: establishments.coverUrl,
        commune: communes.name,
        status: campaignParticipants.status,
        offer: campaignParticipants.offerLabel,
        invitedAt: campaignParticipants.invitedAt,
        joinedAt: campaignParticipants.joinedAt,
      })
      .from(campaignParticipants)
      .innerJoin(establishments, eq(establishments.id, campaignParticipants.establishmentId))
      .innerJoin(communes, eq(communes.id, establishments.communeId))
      // Une commune ne voit que ses propres établissements dans une campagne du territoire.
      .where(and(eq(campaignParticipants.campaignId, c.id), ctx.communeIds ? inArray(establishments.communeId, ctx.communeIds) : undefined))
      .orderBy(asc(campaignParticipants.status), asc(establishments.name)),
    c.mode === 'ADVENT' ? db.select().from(adventDoors).where(eq(adventDoors.campaignId, c.id)).orderBy(asc(adventDoors.day)) : Promise.resolve([]),
  ]);
  const count = (s: keyof typeof PSTATUS) => participants.filter((p) => p.status === s).length;
  const notYetInvited = participants.filter((p) => p.status === 'INVITED' && !p.invitedAt).length;
  const publicUrl = c.status !== 'DRAFT' ? portalUrl(ctx.territory, `/campagnes/${c.slug}`) : null;
  const plan = c.aiPlan ?? {};
  const joined = participants.filter((p) => p.status === 'JOINED');

  return (
    <div className="app-content">
      <Link href="/collectivite/campagnes" style={{ fontSize: 13, fontWeight: 600 }}>
        ← Toutes les campagnes
      </Link>
      {!editable ? (
        <div className="alert alert-info" role="note">
          Campagne pilotée par la {shortLegalName(ctx.territory.legalName)} : vous la consultez. Les établissements de votre commune qui y participent sont
          listés ci-dessous.
        </div>
      ) : null}
      {sp.ok === 'invites' ? (
        <div className="alert alert-ok" role="status">
          Campagne créée et invitations envoyées dans la messagerie des professionnels.
        </div>
      ) : null}
      <section
        className="bo-card"
        style={{
          display: 'flex',
          gap: 16,
          alignItems: 'center',
          flexWrap: 'wrap',
          background: c.colorBg,
          color: c.colorText,
          border: 0,
        }}
      >
        <div style={{ minWidth: 0 }}>
          <div style={{ fontSize: 12, opacity: 0.85 }}>
            {owner ? `Opération communale · ${owner.name}` : 'Campagne du territoire'} · {c.startsAt.split('-').reverse().join('/')} →{' '}
            {c.endsAt.split('-').reverse().join('/')}
          </div>
          <h2 className="display" style={{ fontSize: 30, margin: 0, letterSpacing: '-0.02em' }}>
            {c.name}
          </h2>
          {c.tagline ? <div style={{ fontSize: 14, opacity: 0.9 }}>{c.tagline}</div> : null}
        </div>
        <div
          style={{
            marginLeft: 'auto',
            display: 'flex',
            gap: 18,
            alignItems: 'center',
            flexWrap: 'wrap',
          }}
        >
          {[
            ['participent', count('JOINED')],
            ['invités', count('INVITED')],
            ['déclinés', count('DECLINED')],
          ].map(([l, v]) => (
            <div key={l as string} style={{ textAlign: 'center' }}>
              <div className="display" style={{ fontSize: 26 }}>
                {fmtInt(v as number)}
              </div>
              <div style={{ fontSize: 12, opacity: 0.85 }}>{l}</div>
            </div>
          ))}
          {publicUrl ? (
            <a href={publicUrl} target="_blank" rel="noopener noreferrer" className="btn btn-light btn-sm">
              Voir la page ↗
            </a>
          ) : null}
        </div>
      </section>

      <div
        className="split"
        style={{
          ['--cols' as string]: 'minmax(0,1.2fr) minmax(0,1fr)',
          ['--gap' as string]: '18px',
          ['--align' as string]: 'start',
        }}
      >
        {editable ? (
          <section className="bo-card">
            <b>Paramètres</b>
            <ActionForm
              action={updateCampaignAction}
              resetOnSuccess={false}
              style={{
                display: 'flex',
                flexDirection: 'column',
                gap: 12,
                marginTop: 12,
              }}
            >
              <input type="hidden" name="campaignId" value={c.id} />
              <label className="field">
                <span>Nom</span>
                <input name="name" className="input" defaultValue={c.name} required maxLength={200} />
              </label>
              <label className="field">
                <span>Accroche</span>
                <input name="tagline" className="input" defaultValue={c.tagline ?? ''} maxLength={250} />
              </label>
              <label className="field">
                <span>Présentation (page publique)</span>
                <textarea name="description" className="input" rows={4} defaultValue={c.description} maxLength={4000} />
              </label>
              <div
                style={{
                  display: 'grid',
                  gridTemplateColumns: 'repeat(auto-fit,minmax(150px,1fr))',
                  gap: 12,
                }}
              >
                <label className="field">
                  <span>Début</span>
                  <input name="startsAt" type="date" className="input" defaultValue={c.startsAt} required />
                </label>
                <label className="field">
                  <span>Fin</span>
                  <input name="endsAt" type="date" className="input" defaultValue={c.endsAt} required />
                </label>
                <label className="field">
                  <span>Statut</span>
                  <select name="status" className="input" defaultValue={c.status}>
                    <option value="DRAFT">Brouillon</option>
                    <option value="SCHEDULED">Programmée</option>
                    <option value="ACTIVE">En cours</option>
                    <option value="ENDED">Terminée</option>
                  </select>
                </label>
                <label className="field">
                  <span>Format</span>
                  <select name="mode" className="input" defaultValue={c.mode}>
                    <option value="STANDARD">Sélection d’offres</option>
                    <option value="ADVENT">Calendrier de l’Avent</option>
                  </select>
                </label>
                <label className="field">
                  <span>Couleur de fond</span>
                  <input name="colorBg" type="color" className="input" defaultValue={c.colorBg} style={{ padding: 4, height: 44 }} />
                </label>
                <label className="field">
                  <span>Couleur du texte</span>
                  <input name="colorText" type="color" className="input" defaultValue={c.colorText} style={{ padding: 4, height: 44 }} />
                </label>
              </div>
              <label className="field">
                <span>Bouton d’action (facultatif)</span>
                <input name="ctaLabel" className="input" defaultValue={c.ctaLabel ?? ''} maxLength={64} placeholder="Ex. Ouvrir la case du jour" />
              </label>
              <label className="field">
                <span>Message d’invitation aux professionnels</span>
                <textarea name="invitationMessage" className="input" rows={3} defaultValue={c.invitationMessage ?? ''} maxLength={2000} />
              </label>
              <div className="field">
                <span>Visuel</span>
                {c.heroImageUrl ? (
                  <div
                    style={{
                      height: 110,
                      borderRadius: 12,
                      overflow: 'hidden',
                      marginBottom: 6,
                    }}
                  >
                    <Photo src={sized(c.heroImageUrl, 800, 300)} alt="" label={c.name} />
                  </div>
                ) : null}
                <FileDrop name="hero" accept="image/jpeg,image/png,image/webp" label="Remplacer le visuel (JPEG, PNG ou WebP)" />
              </div>
              <SubmitButton className="btn btn-brand" style={{ alignSelf: 'flex-start' }} pendingLabel="Enregistrement…">
                Enregistrer
              </SubmitButton>
            </ActionForm>
          </section>
        ) : (
          <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            <b>Présentation</b>
            {c.tagline ? <b style={{ fontSize: 15 }}>{c.tagline}</b> : null}
            <p style={{ margin: 0, fontSize: 14, lineHeight: 1.55, whiteSpace: 'pre-line' }}>{c.description || 'Pas encore de présentation.'}</p>
            {c.invitationMessage ? <p style={{ margin: 0, fontSize: 13, color: 'var(--muted)' }}>Message aux professionnels : {c.invitationMessage}</p> : null}
          </section>
        )}

        <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
          {plan.pageTitle || plan.plan?.length ? (
            <section
              style={{
                background: 'var(--ink)',
                color: 'var(--cream)',
                borderRadius: 22,
                padding: 20,
                display: 'flex',
                flexDirection: 'column',
                gap: 8,
              }}
            >
              <span
                style={{
                  fontSize: 11,
                  fontWeight: 800,
                  letterSpacing: '0.08em',
                  color: 'var(--amber)',
                }}
              >
                ✦ PRÉPARÉ PAR L&apos;ASSISTANT
              </span>
              {plan.pageTitle ? (
                <b className="display" style={{ fontSize: 20 }}>
                  {plan.pageTitle}
                </b>
              ) : null}
              {plan.newsletterSubject ? <span style={{ fontSize: 13, color: 'var(--sage)' }}>Newsletter : « {plan.newsletterSubject} »</span> : null}
              {plan.plan?.map((p, i) => (
                <div
                  key={i}
                  style={{
                    display: 'grid',
                    gridTemplateColumns: '62px 1fr',
                    gap: 10,
                    fontSize: 13,
                    padding: '5px 0',
                    borderTop: '1px solid var(--dark-3)',
                  }}
                >
                  <b style={{ color: 'var(--amber)' }}>{p.date}</b>
                  <span>{p.text}</span>
                </div>
              ))}
              <Link href={`/collectivite/newsletter?campagne=${c.id}`} style={{ color: 'var(--amber)', fontWeight: 700, fontSize: 13 }}>
                Préparer la newsletter de la campagne →
              </Link>
            </section>
          ) : null}

          <section className="bo-card" id="participants" style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            <div
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                gap: 10,
                flexWrap: 'wrap',
              }}
            >
              <b>Établissements ({participants.length})</b>
              {editable && notYetInvited ? (
                <ActionForm action={inviteParticipantsAction}>
                  <input type="hidden" name="campaignId" value={c.id} />
                  <button type="submit" className="btn btn-dark btn-sm">
                    Inviter les {notYetInvited} en attente
                  </button>
                </ActionForm>
              ) : null}
            </div>
            {editable ? (
              <ActionForm action={addParticipantAction} style={{ display: 'flex', gap: 8 }}>
                <input type="hidden" name="campaignId" value={c.id} />
                <label htmlFor="add-p" className="sr-only">
                  Ajouter un établissement
                </label>
                <input id="add-p" name="q" className="input" placeholder="Ajouter un établissement (nom)…" />
                <button type="submit" className="btn btn-outline btn-sm">
                  Ajouter
                </button>
              </ActionForm>
            ) : null}
            <div
              style={{
                display: 'flex',
                flexDirection: 'column',
                maxHeight: 460,
                overflowY: 'auto',
              }}
            >
              {participants.map((p) => (
                <div
                  key={p.id}
                  style={{
                    display: 'grid',
                    gridTemplateColumns: '36px 1fr auto auto',
                    gap: 10,
                    alignItems: 'center',
                    padding: '8px 0',
                    borderTop: '1px solid var(--line-2)',
                    fontSize: 13,
                  }}
                >
                  <span
                    style={{
                      width: 36,
                      height: 36,
                      borderRadius: 8,
                      overflow: 'hidden',
                    }}
                  >
                    <Photo src={sized(p.coverUrl, 80, 80)} alt="" label={p.name} />
                  </span>
                  <span style={{ minWidth: 0 }}>
                    <b
                      style={{
                        display: 'block',
                        whiteSpace: 'nowrap',
                        overflow: 'hidden',
                        textOverflow: 'ellipsis',
                      }}
                    >
                      {p.name}
                    </b>
                    <span style={{ color: 'var(--muted)' }}>
                      {p.commune}
                      {p.offer ? ` · ${p.offer}` : ''}
                      {p.status === 'INVITED' ? (p.invitedAt ? ` · invité ${relativeTime(p.invitedAt)}` : ' · invitation à envoyer') : ''}
                    </span>
                  </span>
                  <span
                    style={{
                      fontSize: 11,
                      fontWeight: 800,
                      padding: '3px 8px',
                      borderRadius: 999,
                      background: PSTATUS[p.status].bg,
                      color: PSTATUS[p.status].fg,
                    }}
                  >
                    {PSTATUS[p.status].label}
                  </span>
                  {editable ? (
                    <form action={removeParticipantAction}>
                      <input type="hidden" name="campaignId" value={c.id} />
                      <input type="hidden" name="estId" value={p.id} />
                      <button
                        type="submit"
                        aria-label={`Retirer ${p.name}`}
                        style={{
                          border: 0,
                          background: 'var(--danger-bg)',
                          color: 'var(--danger-fg)',
                          borderRadius: 6,
                          width: 24,
                          height: 24,
                          cursor: 'pointer',
                        }}
                      >
                        ×
                      </button>
                    </form>
                  ) : (
                    <span />
                  )}
                </div>
              ))}
              {!participants.length ? <span style={{ fontSize: 13, color: 'var(--muted)' }}>Aucun établissement pour l’instant.</span> : null}
            </div>
          </section>

          {c.status !== 'DRAFT' ? (
            <section className="bo-card" style={{ display: 'flex', gap: 14, alignItems: 'center', flexWrap: 'wrap' }}>
              <img
                src={`/api/collectivite/campagnes/${c.id}/qr.svg`}
                alt={`QR code de la campagne ${c.name}`}
                width={96}
                height={96}
                style={{ borderRadius: 10, background: '#fff', border: '1px solid var(--line)', padding: 6 }}
              />
              <div style={{ display: 'flex', flexDirection: 'column', gap: 6, minWidth: 0, flex: 1 }}>
                <b>QR code de la campagne</b>
                <span style={{ fontSize: 13, color: 'var(--muted)' }}>
                  Vers la page publique de l’opération, pour les vitrines, flyers et panneaux ; les visites par QR code sont comptées.
                </span>
                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', fontSize: 13, fontWeight: 700 }}>
                  <a href={`/api/collectivite/campagnes/${c.id}/affiche.pdf`} download>
                    Affiche A5 (PDF)
                  </a>
                  <a href={`/api/collectivite/campagnes/${c.id}/qr.png`} download>
                    QR code (PNG)
                  </a>
                  <a href={`/api/collectivite/campagnes/${c.id}/qr.svg`} download>
                    QR code (SVG)
                  </a>
                </div>
              </div>
            </section>
          ) : null}

          {c.mode === 'ADVENT' ? (
            <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              <b>Calendrier de l’Avent ({doors.length}/24 cases)</b>
              <div
                style={{
                  display: 'grid',
                  gridTemplateColumns: 'repeat(6,1fr)',
                  gap: 6,
                }}
              >
                {Array.from({ length: 24 }, (_, i) => {
                  const d = doors.find((x) => x.day === i + 1);
                  return (
                    <div
                      key={i}
                      title={d?.title ?? 'Case vide'}
                      style={{
                        aspectRatio: '1',
                        borderRadius: 10,
                        display: 'grid',
                        placeItems: 'center',
                        fontWeight: 800,
                        background: d ? c.colorBg : 'var(--sand)',
                        color: d ? c.colorText : 'var(--muted)',
                      }}
                    >
                      {i + 1}
                    </div>
                  );
                })}
              </div>
              {editable ? (
                <ActionForm
                  action={saveDoorAction}
                  style={{
                    display: 'grid',
                    gridTemplateColumns: '80px 1fr',
                    gap: 8,
                  }}
                >
                  <input type="hidden" name="campaignId" value={c.id} />
                  <select name="day" className="input" aria-label="Jour">
                    {Array.from({ length: 24 }, (_, i) => (
                      <option key={i} value={i + 1}>
                        {i + 1}
                      </option>
                    ))}
                  </select>
                  <input name="title" aria-label="Surprise du jour" className="input" placeholder="Surprise du jour (ex. -20 % sur les coffrets)" required />
                  <select name="establishmentId" className="input" aria-label="Établissement" style={{ gridColumn: '1 / -1' }} defaultValue="">
                    <option value="">Établissement participant…</option>
                    {joined.map((p) => (
                      <option key={p.id} value={p.id}>
                        {p.name}
                      </option>
                    ))}
                  </select>
                  <button type="submit" className="btn btn-outline btn-sm" style={{ gridColumn: '1 / -1', justifySelf: 'start' }}>
                    Enregistrer la case
                  </button>
                </ActionForm>
              ) : null}
            </section>
          ) : null}
        </div>
      </div>
    </div>
  );
}
