import { and, count, desc, eq, gte } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { bulkInviteAction, messageProAction, setStatusAction } from '../actions';
import { ActionForm } from '@/components/pro/ActionForm';
import { Photo } from '@/components/ui/Photo';
import { ESTABLISHMENT_STATUS, FAMILIES, PLAN_LABELS } from '@/lib/constants';
import { daysAgoDate, fmtInt, fmtPhone, fmtShortDate, fmtStamp, fullName } from '@/lib/format';
import { sized } from '@/lib/images';
import { db } from '@/server/db';
import { analyticsEvents, categories, claims, communes, companies, companyMembers, establishmentRevisions, establishments, users } from '@/server/db/schema';
import { estScope, loadBoContext } from '@/server/services/backoffice';
import { portalUrl } from '@/server/urls';

export const metadata: Metadata = { title: 'Fiche entreprise' };

type Props = { params: Promise<{ id: string }> };

const ORIGIN = {
  IMPORT: 'Import (données publiques)',
  COLLECTIVITE: 'Créée par la collectivité',
  PRO: 'Créée par le professionnel',
  SYSTEM: 'Système',
} as const;

export default async function EstablishmentDetailPage({ params }: Props) {
  const { id } = await params;
  if (!/^[0-9a-f-]{36}$/.test(id)) notFound();
  const ctx = await loadBoContext();
  const [row] = await db
    .select({ e: establishments, commune: communes, cat: categories, company: companies })
    .from(establishments)
    .innerJoin(communes, eq(communes.id, establishments.communeId))
    .innerJoin(categories, eq(categories.id, establishments.categoryId))
    .innerJoin(companies, eq(companies.id, establishments.companyId))
    .where(and(estScope(ctx), eq(establishments.id, id)))
    .limit(1);
  if (!row) notFound();
  const { e, commune, cat, company } = row;
  const since = daysAgoDate(30);
  const [members, revisions, claimList, stats] = await Promise.all([
    db
      .select({
        id: users.id,
        firstName: users.firstName,
        lastName: users.lastName,
        email: users.email,
        role: companyMembers.role,
        mfa: users.mfaEnabled,
        lastLoginAt: users.lastLoginAt,
      })
      .from(companyMembers)
      .innerJoin(users, eq(users.id, companyMembers.userId))
      .where(eq(companyMembers.companyId, e.companyId)),
    db.select().from(establishmentRevisions).where(eq(establishmentRevisions.establishmentId, e.id)).orderBy(desc(establishmentRevisions.createdAt)).limit(12),
    db
      .select({ id: claims.id, status: claims.status, createdAt: claims.createdAt, firstName: users.firstName, lastName: users.lastName })
      .from(claims)
      .innerJoin(users, eq(users.id, claims.userId))
      .where(eq(claims.establishmentId, e.id))
      .orderBy(desc(claims.createdAt))
      .limit(5),
    db
      .select({ type: analyticsEvents.type, n: count() })
      .from(analyticsEvents)
      .where(and(eq(analyticsEvents.establishmentId, e.id), gte(analyticsEvents.occurredAt, since)))
      .groupBy(analyticsEvents.type),
  ]);
  const st = new Map(stats.map((s) => [s.type, Number(s.n)]));
  const status = ESTABLISHMENT_STATUS[e.status];
  const url = portalUrl(ctx.territory, `/${commune.slug}/${cat.slug}/${e.slug}`);
  const managed = members.length > 0;
  const info: [string, React.ReactNode][] = [
    ['Adresse', [e.street, `${e.postalCode ?? ''} ${commune.name}`.trim()].filter(Boolean).join(', ')],
    ['Téléphone', e.phone ? fmtPhone(e.phone) : '—'],
    ['Email', e.email ?? '—'],
    [
      'Site web',
      e.website ? (
        <a href={e.website} target="_blank" rel="noopener noreferrer">
          {e.website.replace(/^https?:\/\//, '')}
        </a>
      ) : (
        '—'
      ),
    ],
    ['SIRET', e.siret ? `${e.siret.slice(0, 3)} ${e.siret.slice(3, 6)} ${e.siret.slice(6, 9)} ${e.siret.slice(9)}` : '—'],
    ['Catégorie', `${cat.name} · ${FAMILIES[cat.family].label}`],
    ['Origine', ORIGIN[e.origin]],
    ['Offre', managed ? PLAN_LABELS[company.plan] : '—'],
    ['Créée le', fmtShortDate(e.createdAt)],
  ];
  const statusOps = [
    e.status !== 'VALIDATED' && e.status !== 'SUSPENDED' && e.status !== 'ARCHIVED'
      ? { op: 'validate', label: 'Valider la fiche', cls: 'btn btn-brand btn-sm' }
      : null,
    e.status === 'SUSPENDED' || e.status === 'ARCHIVED' ? { op: 'restore', label: 'Réactiver', cls: 'btn btn-brand btn-sm' } : null,
    e.status !== 'ARCHIVED' && ctx.access === 'ADMIN' ? { op: 'archive', label: 'Archiver (activité cessée)', cls: 'btn btn-ghost btn-sm' } : null,
  ].filter(Boolean) as { op: string; label: string; cls: string }[];

  return (
    <div className="app-content">
      <Link href="/collectivite/entreprises" style={{ fontSize: 13, fontWeight: 600 }}>
        ← Toutes les entreprises
      </Link>
      <section className="bo-card" style={{ display: 'flex', gap: 16, alignItems: 'center', flexWrap: 'wrap' }}>
        <div style={{ width: 72, height: 72, borderRadius: 16, overflow: 'hidden', flexShrink: 0 }}>
          <Photo src={sized(e.coverUrl, 200, 200)} alt="" label={e.name} color={FAMILIES[cat.family].color} />
        </div>
        <div style={{ minWidth: 0 }}>
          <div style={{ fontSize: 12, color: 'var(--muted)' }}>
            {e.activityLabel ?? cat.name} · {commune.name}
          </div>
          <h2 className="display" style={{ fontSize: 28, letterSpacing: '-0.02em', margin: 0 }}>
            {e.name}
          </h2>
          <div style={{ display: 'flex', gap: 10, alignItems: 'center', marginTop: 6, flexWrap: 'wrap' }}>
            <span style={{ fontSize: 12, fontWeight: 700, padding: '4px 10px', borderRadius: 999, background: status.bg, color: status.fg }}>
              {status.label}
            </span>
            <span style={{ fontSize: 13, color: 'var(--muted)' }}>Complétude {e.completeness} %</span>
            {e.suspendedReason ? <span style={{ fontSize: 13, color: 'var(--danger-fg)' }}>Motif : {e.suspendedReason}</span> : null}
          </div>
        </div>
        <div style={{ marginLeft: 'auto', display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          <Link href={`/pro/${e.id}/fiche`} className="btn btn-dark btn-sm">
            Modifier la fiche
          </Link>
          <a href={url} target="_blank" rel="noopener noreferrer" className="btn btn-outline btn-sm">
            Voir sur le portail ↗
          </a>
        </div>
      </section>

      <div className="split" style={{ ['--cols' as string]: 'minmax(0,1.3fr) minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
          <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
            <b style={{ marginBottom: 8 }}>Informations</b>
            {info.map(([k, v]) => (
              <div
                key={k}
                style={{ display: 'grid', gridTemplateColumns: '130px 1fr', gap: 12, padding: '7px 0', borderTop: '1px solid var(--line-2)', fontSize: 14 }}
              >
                <span style={{ color: 'var(--muted)' }}>{k}</span>
                <span style={{ overflowWrap: 'anywhere' }}>{v}</span>
              </div>
            ))}
          </section>
          <section className="bo-card" style={{ display: 'grid', gridTemplateColumns: 'repeat(4,1fr)', gap: 10, textAlign: 'center' }}>
            {[
              ['Vues', st.get('EST_VIEW') ?? 0],
              ['Appels', st.get('PHONE_CLICK') ?? 0],
              ['Itinéraires', st.get('DIRECTIONS_CLICK') ?? 0],
              ['Scans QR', st.get('QR_SCAN') ?? 0],
            ].map(([l, v]) => (
              <div key={l as string}>
                <div className="display" style={{ fontSize: 26 }}>
                  {fmtInt(v as number)}
                </div>
                <div style={{ fontSize: 12, color: 'var(--muted)' }}>{l} · 30 j</div>
              </div>
            ))}
          </section>
          <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 6 }}>
            <b style={{ marginBottom: 6 }}>Historique de la fiche</b>
            {[
              ...revisions.map((r) => ({
                at: r.createdAt,
                text: r.summary,
                color: r.source === 'IMPORT' ? 'var(--faint)' : r.source === 'COLLECTIVITE' ? 'var(--green)' : '#3E6FB0',
              })),
              ...claimList.map((c) => ({
                at: c.createdAt,
                text: `Demande de revendication · ${fullName(c)} (${c.status === 'APPROVED' ? 'validée' : c.status === 'REJECTED' ? 'refusée' : c.status === 'CANCELLED' ? 'annulée' : 'en attente'})`,
                color: '#3E6FB0',
              })),
            ]
              .sort((a, b) => b.at.getTime() - a.at.getTime())
              .slice(0, 14)
              .map((h, i) => (
                <div key={i} style={{ display: 'grid', gridTemplateColumns: '120px 14px 1fr', gap: 12, alignItems: 'center', fontSize: 13, padding: '5px 0' }}>
                  <span style={{ color: 'var(--muted)' }}>{fmtStamp(h.at)}</span>
                  <span style={{ width: 10, height: 10, borderRadius: '50%', background: h.color }} />
                  <span>{h.text}</span>
                </div>
              ))}
          </section>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 18 }}>
          <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            <b>Gestionnaires</b>
            {claimList
              .filter((c) => c.status === 'PENDING' || c.status === 'NEEDS_INFO')
              .map((c) => (
                <Link key={c.id} href={`/collectivite/moderation?demande=${c.id}`} className="alert alert-info" style={{ display: 'block', margin: 0 }}>
                  Demande de revendication de {fullName(c)} en attente · <b>Examiner →</b>
                </Link>
              ))}
            {managed ? (
              members.map((m) => (
                <div
                  key={m.id}
                  style={{ display: 'flex', justifyContent: 'space-between', gap: 10, fontSize: 14, borderTop: '1px solid var(--line-2)', paddingTop: 8 }}
                >
                  <span>
                    <b>{fullName(m)}</b> · {m.role === 'OWNER' ? 'titulaire' : 'collaborateur'}
                    <br />
                    <span style={{ color: 'var(--muted)', fontSize: 13 }}>{m.email}</span>
                  </span>
                  <span
                    style={{
                      fontSize: 11,
                      fontWeight: 800,
                      padding: '3px 8px',
                      borderRadius: 999,
                      background: m.mfa ? 'var(--leaf)' : 'var(--warn-bg)',
                      alignSelf: 'flex-start',
                    }}
                  >
                    {m.mfa ? 'MFA' : 'Sans MFA'}
                  </span>
                </div>
              ))
            ) : (
              <>
                <p style={{ margin: 0, fontSize: 14, color: 'var(--muted)' }}>
                  Aucun compte ne gère cette fiche. Invitez l&apos;entreprise à la revendiquer gratuitement.
                </p>
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
                  {e.email ? (
                    <ActionForm action={bulkInviteAction}>
                      <input type="hidden" name="ids" value={JSON.stringify([e.id])} />
                      <button type="submit" className="btn btn-brand btn-sm">
                        Inviter par email
                      </button>
                    </ActionForm>
                  ) : null}
                  <a href={`/api/collectivite/courriers.pdf?ids=${e.id}`} className="btn btn-outline btn-sm">
                    Courrier d&apos;invitation (PDF)
                  </a>
                </div>
              </>
            )}
          </section>
          <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            <b>Statut & modération</b>
            <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
              {statusOps.map((s) => (
                <ActionForm key={s.op} action={setStatusAction}>
                  <input type="hidden" name="estId" value={e.id} />
                  <input type="hidden" name="op" value={s.op} />
                  <button type="submit" className={s.cls}>
                    {s.label}
                  </button>
                </ActionForm>
              ))}
            </div>
            {e.status !== 'SUSPENDED' && e.status !== 'ARCHIVED' && ctx.access === 'ADMIN' ? (
              <details>
                <summary style={{ cursor: 'pointer', fontSize: 14, fontWeight: 600, color: 'var(--danger-fg)' }}>
                  Suspendre la fiche (masquée du public)
                </summary>
                <ActionForm action={setStatusAction} style={{ display: 'flex', flexDirection: 'column', gap: 8, marginTop: 8 }}>
                  <input type="hidden" name="estId" value={e.id} />
                  <input type="hidden" name="op" value="suspend" />
                  <textarea name="reason" className="input" rows={2} placeholder="Motif (communiqué au professionnel)" required maxLength={300} />
                  <button type="submit" className="btn btn-danger btn-sm" style={{ alignSelf: 'flex-start' }}>
                    Suspendre
                  </button>
                </ActionForm>
              </details>
            ) : null}
          </section>
          {managed ? (
            <section className="bo-card" style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              <b>Écrire au professionnel</b>
              <ActionForm action={messageProAction} style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                <input type="hidden" name="estId" value={e.id} />
                <textarea
                  name="body"
                  className="input"
                  rows={3}
                  required
                  maxLength={2000}
                  defaultValue={`Bonjour, pouvez-vous confirmer vos horaires${e.hoursConfirmedAt ? '' : ' (jamais confirmés)'} sur votre fiche ? Merci !`}
                />
                <button type="submit" className="btn btn-outline btn-sm" style={{ alignSelf: 'flex-start' }}>
                  Envoyer dans sa messagerie
                </button>
              </ActionForm>
            </section>
          ) : null}
        </div>
      </div>
    </div>
  );
}
