import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { JobCard } from '@/components/portal/Cards';
import { Photo } from '@/components/ui/Photo';
import { CONTRACT_TYPES, type ContractType } from '@/lib/constants';
import { pluralize } from '@/lib/format';
import { sized } from '@/lib/images';
import type { TerritorySettings } from '@/server/db/schema';
import { getPortal, listPublicJobs } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string }>; searchParams: Promise<Record<string, string | undefined>> };

const FILTERS: { key: ContractType | null; label: string }[] = [
  { key: null, label: 'Toutes' },
  { key: 'CDI', label: 'CDI' },
  { key: 'SAISONNIER', label: 'Saisonnier' },
  { key: 'ALTERNANCE', label: 'Alternance' },
  { key: 'STAGE', label: 'Stage' },
  { key: 'CDD', label: 'CDD' },
];

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory } = await params;
  const { territory: t } = await getPortal(territory);
  const settings = (t.settings ?? {}) as TerritorySettings;
  return {
    title: settings.jobsTitle ?? `Emploi à ${t.name}`,
    description: `Les offres d'emploi des entreprises de ${t.name} : CDI, saisonniers, alternances et stages, près de chez vous.`,
    alternates: { canonical: portalUrl(t, '/emploi') },
  };
}

export default async function JobsPage({ params, searchParams }: Props) {
  const { territory } = await params;
  const sp = await searchParams;
  const portal = await getPortal(territory);
  if (!portal.modules.has('JOBS')) notFound();
  const { base, territory: t } = portal;
  const settings = (t.settings ?? {}) as TerritorySettings;
  const all = await listPublicJobs(t.id);
  const active = FILTERS.find((f) => f.key && f.key.toLowerCase() === sp.contrat)?.key ?? null;
  const list = active ? all.filter((j) => j.job.contractType === active) : all;
  const withPhoto = all.filter((j) => j.company.coverUrl);
  const intro = settings.jobsIntro ?? 'Travailler près de chez soi.';
  const filters = FILTERS.filter((f) => !f.key || all.some((j) => j.job.contractType === f.key));

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
              {settings.jobsTitle ?? 'Travailler sur notre territoire'}
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
              {pluralize(all.length, 'offre publiée', 'offres publiées')} par les entreprises de {t.name}, mises à jour chaque jour.
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
        <nav aria-label="Filtrer par contrat" style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 18 }}>
          {filters.map((f) => {
            const on = f.key === active;
            return (
              <Link
                key={f.label}
                href={f.key ? `${base}/emploi?contrat=${f.key.toLowerCase()}` : `${base}/emploi`}
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
                {f.label}
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
              />
            ))}
          </div>
        ) : (
          <div className="card card-pad" style={{ color: 'var(--muted)' }}>
            Aucune offre {active ? `en ${CONTRACT_TYPES[active].label.toLowerCase()} ` : ''}pour le moment.
          </div>
        )}
      </section>
    </div>
  );
}
