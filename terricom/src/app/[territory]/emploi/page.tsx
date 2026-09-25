import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { JobCard } from '@/components/portal/Cards';
import { Photo } from '@/components/ui/Photo';
import { CONTRACT_TYPES, type ContractType } from '@/lib/constants';
import { contractL, intL } from '@/lib/i18n/format';
import { territoryText } from '@/lib/i18n/territory';
import { withLang } from '@/lib/i18n';
import { portalT } from '@/server/i18n';
import { sized } from '@/lib/images';
import { getPortal, listPublicJobs } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string }>; searchParams: Promise<Record<string, string | undefined>> };

const FILTERS: (ContractType | null)[] = [null, 'CDI', 'SAISONNIER', 'ALTERNANCE', 'STAGE', 'CDD'];

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory } = await params;
  const portal = await getPortal(territory);
  const { territory: t } = portal;
  const tr = await portalT(portal);
  return {
    title: territoryText(t, 'jobsTitle', tr.locale) ?? tr('jobs.metaTitle', { name: t.name }),
    description: tr('jobs.metaDesc', { name: t.name }),
    alternates: { canonical: withLang(portalUrl(t, '/emploi'), tr.locale) },
  };
}

export default async function JobsPage({ params, searchParams }: Props) {
  const { territory } = await params;
  const sp = await searchParams;
  const portal = await getPortal(territory);
  if (!portal.modules.has('JOBS')) notFound();
  const { base, territory: t } = portal;
  const tr = await portalT(portal);
  const L = tr.locale;
  const all = await listPublicJobs(t.id);
  const active = FILTERS.find((f) => f && f.toLowerCase() === sp.contrat) ?? null;
  const list = active ? all.filter((j) => j.job.contractType === active) : all;
  const withPhoto = all.filter((j) => j.company.coverUrl);
  const intro = territoryText(t, 'jobsIntro', L) ?? tr('jobs.introDefault');
  const filters = FILTERS.filter((f) => !f || all.some((j) => j.job.contractType === f));

  return (
    <div>
      <section style={{ background: 'var(--leaf)' }}>
        <div
          className="container split"
          style={{
            paddingTop: 56,
            paddingBottom: 56,
            ['--cols' as string]: 'minmax(0,1.2fr) minmax(0,1fr)',
            ['--gap' as string]: '40px',
            ['--align' as string]: 'center',
          }}
        >
          <div>
            <div style={{ fontSize: 13, fontWeight: 800, letterSpacing: '0.08em', textTransform: 'uppercase', color: 'var(--leaf-fg)', marginBottom: 10 }}>
              {territoryText(t, 'jobsTitle', L) ?? tr('jobs.eyebrowDefault')}
            </div>
            <h1 className="display" style={{ fontSize: 'clamp(46px,5.6vw,84px)', letterSpacing: '-0.04em', lineHeight: 0.9, margin: '0 0 16px' }}>
              {intro.split(/,\s*/).map((part, i, arr) => (
                <span key={i} style={{ display: 'block' }}>
                  {part}
                  {i < arr.length - 1 ? ',' : ''}
                </span>
              ))}
            </h1>
            <p style={{ fontSize: 18, color: 'var(--leaf-fg-2)', maxWidth: 480, margin: 0 }}>
              {tr.n('jobs.count', all.length, { n: intL(all.length, L), name: t.name })}
            </p>
          </div>
          <div className="hide-md" style={{ position: 'relative', height: 380 }} aria-hidden="true">
            <div
              style={{ position: 'absolute', left: 0, top: 0, width: '68%', height: '78%', borderRadius: 22, overflow: 'hidden', transform: 'rotate(-3deg)' }}
            >
              <Photo src={sized(withPhoto[0]?.company.coverUrl ?? t.heroImageUrl, 900)} alt="" color="#3F8F4E" label=" " />
            </div>
            <div
              style={{
                position: 'absolute',
                right: 0,
                bottom: 0,
                width: '56%',
                height: '60%',
                borderRadius: 22,
                overflow: 'hidden',
                transform: 'rotate(4deg)',
                border: '6px solid var(--leaf)',
              }}
            >
              <Photo src={sized(withPhoto[1]?.company.coverUrl ?? t.heroImageUrl, 700)} alt="" color="#1F6B52" label=" " />
            </div>
          </div>
        </div>
      </section>
      <section className="container" style={{ paddingTop: 36, paddingBottom: 60 }}>
        <nav aria-label={tr('jobs.filterAria')} style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 18 }}>
          {filters.map((f) => {
            const on = f === active;
            return (
              <Link
                key={f ?? 'all'}
                href={f ? `${base}/emploi?contrat=${f.toLowerCase()}` : `${base}/emploi`}
                scroll={false}
                aria-current={on ? 'page' : undefined}
                style={{
                  border: '1.5px solid var(--ink)',
                  padding: '8px 14px',
                  borderRadius: 999,
                  fontWeight: 700,
                  fontSize: 13,
                  background: on ? 'var(--ink)' : 'transparent',
                  color: on ? '#fff' : 'var(--ink)',
                }}
              >
                {f ? contractL(f, CONTRACT_TYPES[f].label, L) : tr('jobs.all')}
              </Link>
            );
          })}
        </nav>
        {list.length ? (
          <div className="auto-grid" style={{ ['--min' as string]: '360px', ['--gap' as string]: '14px' }}>
            {list.map(({ job, company }) => (
              <JobCard
                key={job.id}
                href={`${base}/emploi/${job.slug}`}
                title={job.title}
                company={company.name}
                commune={company.communeName}
                contract={job.contractType as ContractType}
                image={company.coverUrl}
                L={L}
              />
            ))}
          </div>
        ) : (
          <div className="card card-pad" style={{ color: 'var(--muted)' }}>
            {active
              ? tr('jobs.noneKind', {
                  kind: L === 'de' ? contractL(active, CONTRACT_TYPES[active].label, L) : contractL(active, CONTRACT_TYPES[active].label, L).toLowerCase(),
                })
              : tr('jobs.none')}
          </div>
        )}
      </section>
    </div>
  );
}
