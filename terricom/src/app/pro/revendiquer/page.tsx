import { and, count, eq, ne } from 'drizzle-orm';
import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { ClaimShell, ClaimTitle } from '@/components/pro/ClaimShell';
import { Photo } from '@/components/ui/Photo';
import { fmtInt } from '@/lib/format';
import { sized } from '@/lib/images';
import { db } from '@/server/db';
import { establishments } from '@/server/db/schema';
import { searchClaimCandidates } from '@/server/services/claims';
import { resolveTerritoryParam } from '@/server/services/territories';

export const metadata: Metadata = {
  title: 'Revendiquer ma fiche',
  description: 'Retrouvez la fiche de votre établissement créée par votre collectivité et prenez-en le contrôle gratuitement.',
};

type Props = { searchParams: Promise<Record<string, string | undefined>> };

export default async function ClaimSearchPage({ searchParams }: Props) {
  const sp = await searchParams;
  if (sp.fiche && /^[0-9a-f-]{36}$/.test(sp.fiche)) redirect(`/pro/revendiquer/${sp.fiche}`);
  const q = (sp.q ?? '').trim().slice(0, 120);
  const territory = sp.territoire ? await resolveTerritoryParam(sp.territoire) : null;
  const [results, [total]] = await Promise.all([
    q ? searchClaimCandidates(q, territory?.id ?? null, 6) : Promise.resolve([]),
    db
      .select({ n: count() })
      .from(establishments)
      .where(territory ? and(eq(establishments.territoryId, territory.id), ne(establishments.status, 'ARCHIVED')) : ne(establishments.status, 'ARCHIVED')),
  ]);
  const n = Number(total?.n ?? 0);
  const keep = territory ? `territoire=${encodeURIComponent(territory.slug)}&` : '';

  return (
    <ClaimShell territory={territory} step={0}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
        <ClaimTitle>Retrouvez votre établissement</ClaimTitle>
        <p style={{ margin: 0, color: 'var(--muted)' }}>
          {territory ? 'Votre collectivité a' : 'Les collectivités partenaires ont'} déjà créé {fmtInt(n)} fiches à partir des données publiques. La vôtre est sûrement là.
        </p>
        <form method="get" role="search" style={{ display: 'flex' }}>
          {territory ? <input type="hidden" name="territoire" value={territory.slug} /> : null}
          <label htmlFor="claim-q" className="sr-only">
            Nom de votre établissement, commune ou SIRET
          </label>
          <input
            id="claim-q"
            name="q"
            defaultValue={q}
            placeholder="Nom de l’établissement, commune ou SIRET"
            autoFocus={!q}
            autoComplete="off"
            style={{ flex: 1, border: '1.5px solid var(--ink)', borderRadius: 12, padding: 14, fontSize: 16, background: '#fff', minWidth: 0 }}
          />
          <button type="submit" className="sr-only">
            Rechercher
          </button>
        </form>
        {q ? (
          results.length ? (
            <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
              {results.map((r) => (
                <div
                  key={r.id}
                  style={{
                    display: 'grid',
                    gridTemplateColumns: r.claimable ? '64px 1fr auto' : '64px 1fr',
                    gap: 14,
                    alignItems: 'center',
                    border: r.claimable ? '2px solid var(--green)' : '1px solid var(--line)',
                    borderRadius: 14,
                    padding: r.claimable ? 12 : 13,
                    background: r.claimable ? 'var(--mint-2)' : 'transparent',
                    opacity: r.claimable ? 1 : 0.7,
                  }}
                >
                  <div style={{ width: 64, height: 64, borderRadius: 10, overflow: 'hidden', background: 'var(--sand)' }}>
                    {r.coverUrl ? <Photo src={sized(r.coverUrl, 150, 150)} alt="" label={r.name} /> : null}
                  </div>
                  <div style={{ minWidth: 0 }}>
                    <div style={{ fontWeight: 700 }}>{r.name}</div>
                    <div style={{ fontSize: 13, color: 'var(--muted)' }}>
                      {[r.street, r.communeName].filter(Boolean).join(', ')} · {r.statusLabel}
                      {r.claimable && r.pending ? ' · une demande est déjà en cours' : ''}
                    </div>
                  </div>
                  {r.claimable ? (
                    <Link
                      href={`/pro/revendiquer/${r.id}`}
                      style={{ border: 0, background: 'var(--green)', color: '#fff', padding: '10px 14px', borderRadius: 10, fontWeight: 700, whiteSpace: 'nowrap', textDecoration: 'none' }}
                    >
                      C&apos;est moi
                    </Link>
                  ) : null}
                </div>
              ))}
            </div>
          ) : (
            <div style={{ border: '1px dashed var(--sand-3)', borderRadius: 14, padding: 18, color: 'var(--muted)', fontSize: 14 }}>
              Aucune fiche ne correspond à « {q} ». Essayez avec le nom de votre commune, votre numéro SIRET, ou créez votre fiche.
            </div>
          )
        ) : null}
        <span style={{ fontSize: 13, color: 'var(--muted)' }}>
          Introuvable ? <Link href={`/pro/inscription?${keep}`.replace(/[?&]$/, '')}>Créer une nouvelle fiche</Link>
          {' · '}Déjà un compte ? <Link href="/connexion?next=/pro">Se connecter</Link>
        </span>
      </div>
    </ClaimShell>
  );
}
