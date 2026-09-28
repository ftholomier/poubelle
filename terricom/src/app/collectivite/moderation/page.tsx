import { and, asc, desc, eq, inArray, sql } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { ClaimDecision, PostDecision } from '@/components/bo/Decisions';
import { Photo } from '@/components/ui/Photo';
import { FAMILIES, POST_KINDS } from '@/lib/constants';
import { fmtStamp, fullName, relativeTime } from '@/lib/format';
import { sized } from '@/lib/images';
import { db } from '@/server/db';
import { categories, claims, communes, establishmentRevisions, establishments, posts, users } from '@/server/db/schema';
import { estScope, loadBoContext } from '@/server/services/backoffice';

export const metadata: Metadata = { title: 'Revendications & modération' };

type Props = { searchParams: Promise<Record<string, string | undefined>> };

const RISK = {
  LOW: { label: 'Risque faible', color: 'var(--green)' },
  MEDIUM: { label: 'À vérifier', color: 'var(--brick)' },
  HIGH: { label: 'Risque élevé', color: 'var(--danger)' },
} as const;

const METHOD = { SIRET: 'Numéro SIRET', CODE: 'Code par courrier ou SMS', KBIS: 'Extrait Kbis' } as Record<string, string>;

const OK_MSG: Record<string, string> = {
  valide: 'Revendication validée : l’entreprise a été prévenue par email.',
  refuse: 'Revendication refusée : le demandeur a été informé.',
  publiee: 'Publication validée et mise en ligne.',
  refusee: 'Publication refusée : le professionnel a reçu le motif.',
};

function Tabs({ tab, claimsCount, postsCount }: { tab: string; claimsCount: number; postsCount: number }) {
  const pill = (on: boolean) =>
    on
      ? { background: 'var(--ink)', color: '#fff', padding: '7px 12px', borderRadius: 999, fontWeight: 700, fontSize: 13 }
      : { border: '1px solid var(--line)', padding: '7px 12px', borderRadius: 999, fontWeight: 600, fontSize: 13, color: 'var(--text)' };
  return (
    <div style={{ display: 'flex', gap: 6, marginBottom: 4 }}>
      <Link href="/collectivite/moderation" style={pill(tab === 'claims')} aria-current={tab === 'claims' ? 'page' : undefined}>
        Revendications {claimsCount}
      </Link>
      <Link href="/collectivite/moderation?onglet=publications" style={pill(tab === 'posts')} aria-current={tab === 'posts' ? 'page' : undefined}>
        Publications {postsCount}
      </Link>
    </div>
  );
}

export default async function ModerationPage({ searchParams }: Props) {
  const ctx = await loadBoContext();
  const sp = await searchParams;
  const tab = sp.onglet === 'publications' ? 'posts' : 'claims';
  const [claimList, postList] = await Promise.all([
    db
      .select({
        id: claims.id,
        status: claims.status,
        method: claims.method,
        risk: claims.riskLevel,
        checks: claims.checks,
        createdAt: claims.createdAt,
        claimantRole: claims.claimantRole,
        kbisMediaId: claims.kbisMediaId,
        codeSentTo: claims.codeSentTo,
        codeVerifiedAt: claims.codeVerifiedAt,
        hasLetter: sql<boolean>`${claims.codeEnc} is not null`,
        decisionNote: claims.decisionNote,
        estId: establishments.id,
        estName: establishments.name,
        estStreet: establishments.street,
        estCover: establishments.coverUrl,
        estOrigin: establishments.origin,
        estSuspendedReason: establishments.suspendedReason,
        communeName: communes.name,
        family: categories.family,
        firstName: users.firstName,
        lastName: users.lastName,
        email: users.email,
        avatarUrl: users.avatarUrl,
      })
      .from(claims)
      .innerJoin(establishments, eq(establishments.id, claims.establishmentId))
      .innerJoin(communes, eq(communes.id, establishments.communeId))
      .innerJoin(categories, eq(categories.id, establishments.categoryId))
      .innerJoin(users, eq(users.id, claims.userId))
      .where(and(estScope(ctx), inArray(claims.status, ['PENDING', 'NEEDS_INFO'])))
      .orderBy(desc(claims.createdAt)),
    db
      .select({
        id: posts.id,
        title: posts.title,
        body: posts.body,
        kind: posts.kind,
        imageUrl: posts.imageUrl,
        promoLabel: posts.promoLabel,
        validTo: posts.validTo,
        createdAt: posts.createdAt,
        publishAt: posts.publishAt,
        channels: posts.channels,
        estId: establishments.id,
        estName: establishments.name,
        estCover: establishments.coverUrl,
        communeName: communes.name,
      })
      .from(posts)
      .leftJoin(establishments, eq(establishments.id, posts.establishmentId))
      .leftJoin(communes, eq(communes.id, posts.communeId))
      .where(and(eq(posts.territoryId, ctx.territory.id), eq(posts.status, 'PENDING'), ctx.communeIds ? inArray(posts.communeId, ctx.communeIds) : undefined))
      .orderBy(asc(posts.createdAt)),
  ]);

  const notice = sp.ok ? OK_MSG[sp.ok] : null;

  if (tab === 'posts') {
    const cur = postList.find((p) => p.id === sp.publication) ?? postList[0];
    return (
      <div className="app-content">
        {notice ? (
          <div className="alert alert-ok" role="status">
            {notice}
          </div>
        ) : null}
        <div className="split" style={{ ['--cols' as string]: '340px minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
            <Tabs tab={tab} claimsCount={claimList.length} postsCount={postList.length} />
            {postList.map((p) => {
              const on = cur?.id === p.id;
              return (
                <Link
                  key={p.id}
                  href={`/collectivite/moderation?onglet=publications&publication=${p.id}`}
                  style={{
                    display: 'grid',
                    gridTemplateColumns: '44px 1fr',
                    gap: 12,
                    alignItems: 'center',
                    padding: 12,
                    borderRadius: 14,
                    background: on ? 'var(--paper)' : 'transparent',
                    border: `1.5px solid ${on ? 'var(--ink)' : 'var(--line)'}`,
                    color: 'var(--text)',
                  }}
                >
                  <span style={{ width: 44, height: 44, borderRadius: 10, overflow: 'hidden' }}>
                    <Photo src={sized(p.imageUrl ?? p.estCover, 100, 100)} alt="" label={p.estName ?? p.title} />
                  </span>
                  <span style={{ minWidth: 0 }}>
                    <b style={{ fontSize: 14, display: 'block', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{p.title}</b>
                    <span style={{ fontSize: 12, color: 'var(--muted)' }}>
                      {p.estName ?? ctx.scopeName} · {relativeTime(p.createdAt)}
                    </span>
                    <span style={{ display: 'block', fontSize: 11, fontWeight: 800, color: '#7A5BB5', marginTop: 3 }}>{POST_KINDS[p.kind].label}</span>
                  </span>
                </Link>
              );
            })}
            {!postList.length ? (
              <div
                style={{
                  padding: 30,
                  textAlign: 'center',
                  color: 'var(--muted)',
                  background: 'var(--paper)',
                  borderRadius: 14,
                  border: '1px dashed var(--sand-3)',
                }}
              >
                Rien à modérer. Café mérité.
              </div>
            ) : null}
          </div>
          {cur ? (
            <section className="bo-card" style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 16 }}>
              <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                Publication à modérer · {cur.estName ?? ctx.scopeName}
                {cur.communeName ? ` · ${cur.communeName}` : ''} · reçue {relativeTime(cur.createdAt)}
              </div>
              {cur.imageUrl ? (
                <div style={{ height: 220, borderRadius: 16, overflow: 'hidden' }}>
                  <Photo src={sized(cur.imageUrl, 900, 440)} alt="" label={cur.title} />
                </div>
              ) : null}
              <div style={{ display: 'flex', gap: 8, alignItems: 'center', flexWrap: 'wrap' }}>
                <span
                  style={{ fontSize: 11, fontWeight: 800, padding: '3px 9px', borderRadius: 999, background: POST_KINDS[cur.kind].bg, color: 'var(--ink)' }}
                >
                  {POST_KINDS[cur.kind].label}
                </span>
                {cur.promoLabel ? (
                  <span style={{ fontSize: 11, fontWeight: 800, padding: '3px 9px', borderRadius: 999, background: 'var(--amber)' }}>{cur.promoLabel}</span>
                ) : null}
                {cur.validTo ? <span style={{ fontSize: 12, color: 'var(--muted)' }}>jusqu&apos;au {cur.validTo.split('-').reverse().join('/')}</span> : null}
              </div>
              <h2 className="display" style={{ fontSize: 28, margin: 0, letterSpacing: '-0.02em' }}>
                {cur.title}
              </h2>
              <p style={{ margin: 0, fontSize: 15, lineHeight: 1.6, whiteSpace: 'pre-wrap' }}>{cur.body}</p>
              <div style={{ fontSize: 13, color: 'var(--muted)' }}>
                Canaux demandés : {cur.channels.join(', ').toLowerCase()}
                {cur.publishAt ? ` · programmée le ${fmtStamp(cur.publishAt)}` : ''}
              </div>
              <PostDecision postId={cur.id} />
            </section>
          ) : null}
        </div>
      </div>
    );
  }

  const cur = claimList.find((c) => c.id === sp.demande) ?? claimList[0];
  const history = cur
    ? await db
        .select()
        .from(establishmentRevisions)
        .where(eq(establishmentRevisions.establishmentId, cur.estId))
        .orderBy(desc(establishmentRevisions.createdAt))
        .limit(6)
    : [];
  const checkStyle = (ok: boolean | null) =>
    ok === true
      ? { bg: 'var(--mint)', c: 'var(--green)', m: '✓' }
      : ok === false
        ? { bg: 'var(--warn-bg)', c: 'var(--warn-fg)', m: '!' }
        : { bg: 'var(--sand)', c: 'var(--muted)', m: '●' };

  return (
    <div className="app-content">
      {notice ? (
        <div className="alert alert-ok" role="status">
          {notice}
        </div>
      ) : null}
      <div className="split" style={{ ['--cols' as string]: '340px minmax(0,1fr)', ['--gap' as string]: '18px', ['--align' as string]: 'start' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          <Tabs tab={tab} claimsCount={claimList.length} postsCount={postList.length} />
          {claimList.map((c) => {
            const on = cur?.id === c.id;
            const creation = c.estOrigin === 'PRO' && c.estSuspendedReason?.startsWith('Création');
            return (
              <Link
                key={c.id}
                href={`/collectivite/moderation?demande=${c.id}`}
                style={{
                  display: 'grid',
                  gridTemplateColumns: '44px 1fr',
                  gap: 12,
                  alignItems: 'center',
                  padding: 12,
                  borderRadius: 14,
                  background: on ? 'var(--paper)' : 'transparent',
                  border: `1.5px solid ${on ? 'var(--ink)' : 'var(--line)'}`,
                  color: 'var(--text)',
                }}
              >
                <span style={{ width: 44, height: 44, borderRadius: '50%', overflow: 'hidden' }}>
                  <Photo src={sized(c.avatarUrl, 100, 100)} alt="" label={fullName(c)} color="#3E6FB0" />
                </span>
                <span style={{ minWidth: 0 }}>
                  <b style={{ fontSize: 14, display: 'block', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{c.estName}</b>
                  <span style={{ fontSize: 12, color: 'var(--muted)' }}>
                    {fullName(c)} · {relativeTime(c.createdAt)}
                  </span>
                  <span style={{ display: 'block', fontSize: 11, fontWeight: 800, color: RISK[c.risk].color, marginTop: 3 }}>
                    {RISK[c.risk].label}
                    {creation ? ' · nouvelle fiche' : ''}
                    {c.status === 'NEEDS_INFO' ? ' · justificatif demandé' : ''}
                  </span>
                </span>
              </Link>
            );
          })}
          {!claimList.length ? (
            <div
              style={{
                padding: 30,
                textAlign: 'center',
                color: 'var(--muted)',
                background: 'var(--paper)',
                borderRadius: 14,
                border: '1px dashed var(--sand-3)',
              }}
            >
              Tout est validé. Café mérité.
            </div>
          ) : null}
        </div>
        {cur ? (
          <section className="bo-card" style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 18 }}>
            <div style={{ display: 'flex', gap: 16, alignItems: 'center', flexWrap: 'wrap' }}>
              <span style={{ width: 72, height: 72, borderRadius: 16, overflow: 'hidden', flexShrink: 0 }}>
                <Photo src={sized(cur.estCover, 200, 200)} alt="" label={cur.estName} color={FAMILIES[cur.family].color} />
              </span>
              <div style={{ minWidth: 0 }}>
                <div style={{ fontSize: 12, color: 'var(--muted)' }}>
                  {cur.estOrigin === 'PRO' && cur.estSuspendedReason?.startsWith('Création') ? 'Demande de création de fiche' : 'Demande de revendication'}
                </div>
                <Link
                  href={`/collectivite/entreprises/${cur.estId}`}
                  className="display"
                  style={{ fontSize: 28, letterSpacing: '-0.02em', color: 'var(--text)' }}
                >
                  {cur.estName}
                </Link>
                <div style={{ fontSize: 13, color: 'var(--muted)' }}>{[cur.estStreet, cur.communeName].filter(Boolean).join(', ')}</div>
              </div>
              <div style={{ marginLeft: 'auto', display: 'flex', gap: 10, alignItems: 'center' }}>
                <span style={{ width: 44, height: 44, borderRadius: '50%', overflow: 'hidden' }}>
                  <Photo src={sized(cur.avatarUrl, 100, 100)} alt="" label={fullName(cur)} color="#3E6FB0" />
                </span>
                <div style={{ fontSize: 13 }}>
                  <b>{fullName(cur)}</b>
                  <div style={{ color: 'var(--muted)' }}>{cur.email}</div>
                </div>
              </div>
            </div>
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(180px,1fr))', gap: 10 }}>
              {cur.checks.map((k, i) => {
                const s = checkStyle(k.ok);
                return (
                  <div key={i} style={{ borderRadius: 14, padding: 14, background: s.bg }}>
                    <div style={{ fontWeight: 800, fontSize: 13, color: s.c }}>
                      {s.m} {k.label}
                    </div>
                    <div style={{ fontSize: 12, color: 'var(--muted-3)', marginTop: 4 }}>{k.detail}</div>
                  </div>
                );
              })}
            </div>
            <div style={{ display: 'flex', gap: 14, flexWrap: 'wrap', fontSize: 13, color: 'var(--muted)' }}>
              <span>Méthode : {METHOD[cur.method] ?? cur.method}</span>
              {cur.claimantRole ? <span>Qualité : {cur.claimantRole}</span> : null}
              {cur.kbisMediaId ? (
                <a href={`/api/documents/${cur.kbisMediaId}`} target="_blank" rel="noopener noreferrer" style={{ fontWeight: 700 }}>
                  Ouvrir le Kbis (PDF) ↗
                </a>
              ) : null}
              {cur.hasLetter ? (
                <a href={`/api/collectivite/claims/${cur.id}/courrier.pdf`} target="_blank" rel="noopener noreferrer" style={{ fontWeight: 700 }}>
                  Imprimer le courrier avec le code ↗
                </a>
              ) : null}
              {cur.status === 'NEEDS_INFO' && cur.decisionNote ? <span>Complément demandé : « {cur.decisionNote} »</span> : null}
            </div>
            <div>
              <b style={{ fontSize: 14 }}>Historique de la fiche</b>
              <div style={{ display: 'flex', flexDirection: 'column', marginTop: 8 }}>
                {[
                  { at: cur.createdAt, text: 'Demande de revendication', color: '#3E6FB0' },
                  ...history.map((h) => ({ at: h.createdAt, text: h.summary, color: h.source === 'IMPORT' ? 'var(--faint)' : 'var(--green)' })),
                ].map((h, i) => (
                  <div key={i} style={{ display: 'grid', gridTemplateColumns: '90px 14px 1fr', gap: 12, alignItems: 'center', fontSize: 13, padding: '6px 0' }}>
                    <span style={{ color: 'var(--muted)' }}>
                      {new Intl.DateTimeFormat('fr-FR', { day: 'numeric', month: 'short', timeZone: 'Europe/Paris' }).format(h.at)}
                    </span>
                    <span style={{ width: 10, height: 10, borderRadius: '50%', background: h.color }} />
                    <span>{h.text}</span>
                  </div>
                ))}
              </div>
            </div>
            <ClaimDecision claimId={cur.id} canDecide={ctx.access === 'ADMIN'} />
          </section>
        ) : null}
      </div>
    </div>
  );
}
