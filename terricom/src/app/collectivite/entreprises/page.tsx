import { and, asc, count, desc, eq, inArray, isNull, ne, or, sql, type SQL } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { startSireneImportAction } from './actions';
import { EstablishmentTable, type EstRow } from '@/components/bo/EstablishmentTable';
import { CommitForm, ImportPoller, MappingForm, UploadForm } from '@/components/bo/ImportForms';
import { ESTABLISHMENT_STATUS, FAMILIES, PLAN_LABELS, type EstablishmentStatus } from '@/lib/constants';
import { ageShort, fmtInt, fmtStamp } from '@/lib/format';
import { sized } from '@/lib/images';
import { db } from '@/server/db';
import { campaigns, categories, communes, companies, establishments, importBatches } from '@/server/db/schema';
import { estScope, loadBoContext, type BoContext } from '@/server/services/backoffice';
import { IMPORT_FIELDS } from '@/server/services/imports';

export const metadata: Metadata = { title: 'Entreprises' };

type Props = { searchParams: Promise<Record<string, string | undefined>> };

const PAGE_SIZE = 20;
const STATUS_KEYS: Record<string, EstablishmentStatus> = {
  precreee: 'PRECREATED',
  'a-completer': 'TO_COMPLETE',
  revendiquee: 'CLAIMED',
  validee: 'VALIDATED',
  suspendue: 'SUSPENDED',
  archivee: 'ARCHIVED',
};

function qs(sp: Record<string, string | undefined>, patch: Record<string, string | null>) {
  const p = new URLSearchParams();
  for (const [k, v] of Object.entries({ ...sp, ...patch })) if (v) p.set(k, v);
  const s = p.toString();
  return `/collectivite/entreprises${s ? `?${s}` : ''}`;
}

async function ImportPanel({ ctx, lot }: { ctx: BoContext; lot: string | undefined }) {
  const batch =
    lot && /^[0-9a-f-]{36}$/.test(lot)
      ? (
          await db
            .select()
            .from(importBatches)
            .where(and(eq(importBatches.id, lot), eq(importBatches.territoryId, ctx.territory.id)))
            .limit(1)
        )[0]
      : undefined;
  const history = await db
    .select({
      id: importBatches.id,
      filename: importBatches.filename,
      status: importBatches.status,
      report: importBatches.report,
      createdAt: importBatches.createdAt,
    })
    .from(importBatches)
    .where(eq(importBatches.territoryId, ctx.territory.id))
    .orderBy(desc(importBatches.createdAt))
    .limit(4);
  const step = (n: number, label: string) => (
    <span style={{ fontSize: 11, fontWeight: 800, letterSpacing: '0.08em', color: 'var(--amber)' }}>
      {n} · {label}
    </span>
  );
  const panel = {
    background: 'var(--ink)',
    color: 'var(--cream)',
    borderRadius: 20,
    padding: 22,
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))',
    gap: 18,
  } as const;
  if (!batch) {
    return (
      <section style={panel} aria-label="Importer des établissements">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
          {step(1, 'FICHIER')}
          <UploadForm />
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 13 }}>
          {step(2, 'OU BASE SIRENE')}
          <p style={{ margin: 0, color: 'var(--sage)' }}>
            Récupère les établissements actifs de vos {ctx.communes.length} communes depuis l&apos;API publique Recherche d&apos;entreprises (données INSEE).
          </p>
          <form action={startSireneImportAction}>
            <button
              type="submit"
              style={{
                border: '1.5px solid var(--dark-4)',
                background: 'transparent',
                color: 'var(--cream)',
                padding: '10px 14px',
                borderRadius: 10,
                fontWeight: 700,
                cursor: 'pointer',
              }}
            >
              Interroger la base SIRENE
            </button>
          </form>
        </div>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 6, fontSize: 13 }}>
          {step(3, 'DERNIERS IMPORTS')}
          {history.length ? (
            history.map((h) => (
              <Link
                key={h.id}
                href={`/collectivite/entreprises?import=1&lot=${h.id}`}
                style={{ color: 'var(--cream)', display: 'flex', justifyContent: 'space-between', gap: 8 }}
              >
                <span style={{ whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>{h.filename}</span>
                <span style={{ color: 'var(--sage-2)', whiteSpace: 'nowrap' }}>
                  {h.status === 'COMMITTED' ? `${h.report.created ?? 0} créées` : h.status === 'FAILED' ? 'échec' : 'à valider'}
                </span>
              </Link>
            ))
          ) : (
            <span style={{ color: 'var(--sage-2)' }}>Aucun import pour l&apos;instant.</span>
          )}
        </div>
      </section>
    );
  }
  if (batch.status === 'PENDING' || batch.status === 'RUNNING') {
    return (
      <section style={{ ...panel, display: 'flex', alignItems: 'center', gap: 12 }}>
        <span
          aria-hidden="true"
          style={{
            width: 18,
            height: 18,
            borderRadius: '50%',
            border: '3px solid var(--dark-4)',
            borderTopColor: 'var(--amber)',
            animation: 'spin 1s linear infinite',
          }}
        />
        <span>{batch.source === 'SIRENE' ? 'Interrogation de la base SIRENE, commune par commune…' : 'Création des fiches en cours…'}</span>
        <ImportPoller />
      </section>
    );
  }
  if (batch.status === 'FAILED') {
    return (
      <section style={{ ...panel, display: 'flex', flexDirection: 'column', gap: 8 }}>
        <b>L&apos;import « {batch.filename} » a échoué</b>
        <span style={{ color: 'var(--sage)' }}>{batch.error}</span>
        <Link href="/collectivite/entreprises?import=1" style={{ color: 'var(--amber)', fontWeight: 700 }}>
          Recommencer
        </Link>
      </section>
    );
  }
  const r = batch.report;
  if (batch.status === 'COMMITTED') {
    return (
      <section style={{ ...panel, display: 'flex', flexDirection: 'column', gap: 8 }}>
        <span style={{ fontSize: 11, fontWeight: 800, letterSpacing: '0.08em', color: 'var(--amber)' }}>
          IMPORT TERMINÉ · {fmtStamp(batch.committedAt ?? batch.updatedAt)}
        </span>
        <b style={{ fontSize: 18 }}>
          {fmtInt(r.created ?? 0)} fiches précréées · {fmtInt(r.updated ?? 0)} fiches complétées
          {r.invited ? ` · ${fmtInt(r.invited)} invitations envoyées` : ''}
        </b>
        <div style={{ display: 'flex', gap: 14, flexWrap: 'wrap', fontSize: 13 }}>
          {r.letterIds?.length ? (
            <a href={`/api/collectivite/imports/${batch.id}/courriers.pdf`} style={{ color: 'var(--amber)', fontWeight: 700 }}>
              Imprimer les {fmtInt(r.letterIds.length)} courriers d&apos;invitation (PDF)
            </a>
          ) : null}
          <a href={`/api/collectivite/imports/${batch.id}/rapport.csv`} style={{ color: 'var(--cream)' }}>
            Rapport ligne à ligne (CSV)
          </a>
          <Link href="/collectivite/entreprises?import=1" style={{ color: 'var(--cream)' }}>
            Nouvel import
          </Link>
        </div>
      </section>
    );
  }
  const cats = await db
    .select({ id: categories.id, name: categories.name })
    .from(categories)
    .where(or(isNull(categories.territoryId), eq(categories.territoryId, ctx.territory.id)))
    .orderBy(asc(categories.name));
  const mapped = IMPORT_FIELDS.filter((f) => batch.mapping[f.key]);
  return (
    <section style={panel} aria-label="Importer des établissements">
      <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
        {step(1, 'FICHIER')}
        <div style={{ border: '2px dashed var(--dark-4)', borderRadius: 14, padding: 16 }}>
          <b style={{ overflowWrap: 'anywhere' }}>{batch.filename}</b>
          <div style={{ fontSize: 12, color: 'var(--sage-2)' }}>
            {fmtInt(r.total)} lignes · {batch.headers.length} colonnes · {batch.source === 'SIRENE' ? 'API SIRENE' : 'UTF-8'}
          </div>
        </div>
        <Link href="/collectivite/entreprises?import=1" style={{ fontSize: 12, color: 'var(--sage)' }}>
          Changer de fichier
        </Link>
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 6, fontSize: 13 }}>
        {step(2, 'CORRESPONDANCE')}
        {mapped.slice(0, 5).map((f) => (
          <div key={f.key} style={{ display: 'flex', justifyContent: 'space-between', gap: 8 }}>
            <span style={{ color: 'var(--sage-2)', whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis' }}>
              {batch.mapping[f.key] === '__adresse_sirene__' ? 'adresse SIRENE' : batch.mapping[f.key]}
            </span>
            <b style={{ whiteSpace: 'nowrap' }}>→ {f.label}</b>
          </div>
        ))}
        <details>
          <summary style={{ cursor: 'pointer', color: 'var(--amber)', fontWeight: 700 }}>Ajuster ({mapped.length} colonnes reconnues)</summary>
          <div style={{ marginTop: 8 }}>
            <MappingForm
              batchId={batch.id}
              headers={batch.headers}
              fields={IMPORT_FIELDS}
              mapping={batch.mapping}
              categories={cats}
              defaultCategoryId={batch.defaultCategoryId}
            />
          </div>
        </details>
      </div>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 6, fontSize: 13 }}>
        {step(3, 'CONTRÔLE')}
        <div>✓ {fmtInt(r.valid)} fiches valides</div>
        <div style={{ color: 'var(--amber)' }}>● {fmtInt(r.merged + r.duplicatesInFile)} doublons fusionnés</div>
        <div style={{ color: r.errors ? 'var(--rose)' : 'var(--sage-2)' }}>
          ● {fmtInt(r.errors)} erreur{r.errors > 1 ? 's' : ''} bloquante{r.errors > 1 ? 's' : ''}
        </div>
        {r.errors || r.merged ? (
          <a href={`/api/collectivite/imports/${batch.id}/rapport.csv`} style={{ color: 'var(--sage)', fontSize: 12 }}>
            Voir le détail ligne à ligne (CSV)
          </a>
        ) : null}
      </div>
      <CommitForm batchId={batch.id} count={r.valid} />
    </section>
  );
}

export default async function EstablishmentsPage({ searchParams }: Props) {
  const ctx = await loadBoContext();
  const sp = await searchParams;
  const status = sp.statut ? STATUS_KEYS[sp.statut] : undefined;
  const q = (sp.q ?? '').trim().slice(0, 80);
  const page = Math.max(1, Number(sp.page) || 1);
  const conds: SQL[] = [estScope(ctx)];
  conds.push(status ? eq(establishments.status, status) : ne(establishments.status, 'ARCHIVED'));
  if (q) conds.push(sql`(f_unaccent(${establishments.name}) ilike f_unaccent(${`%${q}%`}) or ${establishments.siret} like ${`${q.replace(/\s/g, '')}%`})`);
  if (sp.commune && /^[0-9a-f-]{36}$/.test(sp.commune)) conds.push(eq(establishments.communeId, sp.commune));
  if (sp.filtre === 'horaires')
    conds.push(
      sql`${establishments.status} in ('CLAIMED','VALIDATED','TO_COMPLETE') and exists (select 1 from opening_hours h where h.establishment_id = "establishments"."id") and (${establishments.hoursConfirmedAt} is null or ${establishments.hoursConfirmedAt} < now() - interval '6 months')`,
    );
  const where = and(...conds);
  const [statusCounts, [{ n: total }], list, camps] = await Promise.all([
    db.select({ status: establishments.status, n: count() }).from(establishments).where(estScope(ctx)).groupBy(establishments.status),
    db.select({ n: count() }).from(establishments).innerJoin(communes, eq(communes.id, establishments.communeId)).where(where),
    db
      .select({
        id: establishments.id,
        name: establishments.name,
        coverUrl: establishments.coverUrl,
        status: establishments.status,
        origin: establishments.origin,
        completeness: establishments.completeness,
        updatedAt: establishments.updatedAt,
        lastActivityAt: establishments.lastActivityAt,
        communeName: communes.name,
        categoryName: categories.name,
        family: categories.family,
        plan: companies.plan,
        managed: sql<boolean>`exists (select 1 from company_members m where m.company_id = "establishments"."company_id")`,
      })
      .from(establishments)
      .innerJoin(communes, eq(communes.id, establishments.communeId))
      .innerJoin(categories, eq(categories.id, establishments.categoryId))
      .innerJoin(companies, eq(companies.id, establishments.companyId))
      .where(where)
      .orderBy(sql`${establishments.lastActivityAt} desc nulls last`, asc(establishments.name))
      .limit(PAGE_SIZE)
      .offset((page - 1) * PAGE_SIZE),
    db
      .select({ id: campaigns.id, name: campaigns.name })
      .from(campaigns)
      .where(and(eq(campaigns.territoryId, ctx.territory.id), inArray(campaigns.status, ['DRAFT', 'SCHEDULED', 'ACTIVE'])))
      .orderBy(asc(campaigns.startsAt)),
  ]);
  const byStatus = new Map(statusCounts.map((s) => [s.status, Number(s.n)]));
  const allCount = [...byStatus.entries()].filter(([s]) => s !== 'ARCHIVED').reduce((a, [, n]) => a + n, 0);
  const chips: { key: string | null; label: string; n: number }[] = [
    { key: null, label: 'Tous', n: allCount },
    ...Object.entries(STATUS_KEYS)
      .filter(([, st]) => st !== 'ARCHIVED' || byStatus.get('ARCHIVED'))
      .map(([key, st]) => ({ key, label: ESTABLISHMENT_STATUS[st].label, n: byStatus.get(st) ?? 0 })),
  ];
  const rows: EstRow[] = list.map((e) => ({
    id: e.id,
    name: e.name,
    image: sized(e.coverUrl, 80, 80),
    color: FAMILIES[e.family]?.color ?? '#1F6B52',
    commune: e.communeName,
    category: e.categoryName,
    status: ESTABLISHMENT_STATUS[e.status],
    completeness: e.completeness,
    plan: e.managed ? (e.plan === 'ESSENTIEL' ? 'Gratuit' : PLAN_LABELS[e.plan]) : '—',
    planPaid: e.managed && e.plan !== 'ESSENTIEL',
    updated: e.status === 'PRECREATED' && e.origin === 'IMPORT' && !e.lastActivityAt ? 'import' : ageShort(e.lastActivityAt ?? e.updatedAt),
  }));
  const totalN = Number(total);
  const pages = Math.max(1, Math.ceil(totalN / PAGE_SIZE));
  const pageLinks = [...new Set([1, page - 1, page, page + 1, pages].filter((p) => p >= 1 && p <= pages))].sort((a, b) => a - b);
  const exportHref = qs({ statut: sp.statut, q: q || undefined, commune: sp.commune, filtre: sp.filtre }, {}).replace(
    '/collectivite/entreprises',
    '/api/collectivite/entreprises.csv',
  );

  return (
    <div className="app-content" style={{ gap: 16 }}>
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', alignItems: 'center' }}>
        {chips.map((c) => (
          <Link
            key={c.label}
            href={qs(sp, { statut: c.key, page: null })}
            className="bo-chip"
            aria-current={(sp.statut ?? null) === c.key ? 'true' : undefined}
          >
            {c.label}
            <small>{fmtInt(c.n)}</small>
          </Link>
        ))}
        <div style={{ marginLeft: 'auto', display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          {ctx.access === 'ADMIN' ? (
            <Link
              href={sp.import ? qs(sp, { import: null, lot: null }) : qs(sp, { import: '1' })}
              className="btn btn-outline btn-sm"
              style={{ border: '1.5px solid var(--ink)', color: 'var(--ink)' }}
            >
              ↥ Importer (CSV / SIRENE)
            </Link>
          ) : null}
          <Link href="/collectivite/entreprises/nouvelle" className="btn btn-brand btn-sm">
            + Ajouter
          </Link>
        </div>
      </div>
      {sp.filtre === 'horaires' || q || sp.commune ? (
        <div style={{ display: 'flex', gap: 10, alignItems: 'center', fontSize: 13, color: 'var(--muted)', flexWrap: 'wrap' }}>
          {sp.filtre === 'horaires' ? <span className="bo-chip">Horaires non confirmés depuis 6 mois</span> : null}
          {q ? <span className="bo-chip">Recherche : « {q} »</span> : null}
          {sp.commune ? <span className="bo-chip">{ctx.communes.find((c) => c.id === sp.commune)?.name ?? 'Commune'}</span> : null}
          <Link href={qs({}, { statut: sp.statut ?? null })}>Effacer les filtres</Link>
        </div>
      ) : null}
      {sp.import && ctx.access === 'ADMIN' ? <ImportPanel ctx={ctx} lot={sp.lot} /> : null}
      <EstablishmentTable rows={rows} campaigns={camps} exportHref={exportHref} />
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: 13, color: 'var(--muted)', flexWrap: 'wrap', gap: 10 }}>
        <span>
          {fmtInt(rows.length)} affichées sur {fmtInt(totalN)}
          {' · '}
          <a href={exportHref}>Exporter la liste (CSV)</a>
        </span>
        <nav aria-label="Pagination" style={{ display: 'flex', gap: 4, alignItems: 'center' }}>
          {page > 1 ? <Link href={qs(sp, { page: String(page - 1) })}>‹</Link> : <span style={{ opacity: 0.4 }}>‹</span>}
          {pageLinks.map((p, i) => (
            <span key={p} style={{ display: 'inline-flex', gap: 4 }}>
              {i > 0 && p - pageLinks[i - 1] > 1 ? <span>…</span> : null}
              {p === page ? (
                <b aria-current="page" style={{ color: 'var(--text)' }}>
                  {p}
                </b>
              ) : (
                <Link href={qs(sp, { page: String(p) })}>{p}</Link>
              )}
            </span>
          ))}
          {page < pages ? <Link href={qs(sp, { page: String(page + 1) })}>›</Link> : <span style={{ opacity: 0.4 }}>›</span>}
        </nav>
      </div>
    </div>
  );
}
