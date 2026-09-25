import type { Metadata } from 'next';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { cache } from 'react';
import { JsonLd } from '@/components/JsonLd';
import { MapView } from '@/components/maps/MapView';
import { Beacon } from '@/components/portal/Beacon';
import { ApplyForm } from '@/components/portal/ApplyForm';
import { Photo } from '@/components/ui/Photo';
import { CONTRACT_TYPES, type ContractType } from '@/lib/constants';
import { relativeTime, truncate } from '@/lib/format';
import { sized } from '@/lib/images';
import type { TerritorySettings } from '@/server/db/schema';
import { jobPostingJsonLd } from '@/server/seo';
import { getPortal, getPublicJob } from '@/server/services/portal';
import { portalUrl } from '@/server/urls';

type Props = { params: Promise<{ territory: string; slug: string }> };

const load = cache(async (territoryParam: string, slug: string) => {
  const portal = await getPortal(territoryParam);
  if (!portal.modules.has('JOBS')) return { portal, data: null };
  return { portal, data: await getPublicJob(portal.territory.id, slug) };
});

export async function generateMetadata({ params }: Props): Promise<Metadata> {
  const { territory, slug } = await params;
  const { portal, data } = await load(territory, slug);
  if (!data) return { title: 'Offre introuvable', robots: { index: false } };
  const { job, company } = data;
  const title = `${job.title} (${CONTRACT_TYPES[job.contractType as ContractType].label}) — ${company.name}, ${company.communeName}`;
  return {
    title,
    description: truncate(job.description, 158),
    alternates: { canonical: portalUrl(portal.territory, `/emploi/${job.slug}`) },
    openGraph: { title, description: truncate(job.description, 200), images: company.coverUrl ? [{ url: sized(company.coverUrl, 1200)! }] : undefined },
  };
}

const h3 = { fontSize: 26, margin: '0 0 10px' } as const;

export default async function JobPage({ params }: Props) {
  const { territory, slug } = await params;
  const { portal, data } = await load(territory, slug);
  if (!data) notFound();
  const { base, territory: t } = portal;
  const { job, company, address } = data;
  const ct = CONTRACT_TYPES[job.contractType as ContractType];
  const settings = (t.settings ?? {}) as TerritorySettings;
  const url = portalUrl(t, `/emploi/${job.slug}`);
  const tiles = [
    { label: 'Début', value: job.startText },
    { label: 'Rémunération', value: job.salaryText },
    { label: 'Temps de travail', value: job.workTimeText },
    { label: 'Publiée', value: job.publishedAt ? relativeTime(job.publishedAt) : null },
  ].filter((x) => x.value);

  return (
    <div>
      <Beacon type="JOB_VIEW" territoryId={t.id} establishmentId={company.id} refId={job.id} />
      <JsonLd
        data={jobPostingJsonLd({
          title: job.title,
          description: job.description,
          missions: job.missions,
          profile: job.profile,
          contractType: job.contractType,
          publishedAt: job.publishedAt,
          expiresAt: job.expiresAt,
          url,
          company: { name: company.name, url: portalUrl(t, company.path), logo: company.logoUrl },
          locality: company.communeName,
          postalCode: address?.postalCode,
          street: address?.street,
          salaryText: job.salaryText,
        })}
      />
      <section style={{ background: 'var(--leaf)' }}>
        <div className="container" style={{ paddingTop: 18, paddingBottom: 40, display: 'flex', flexDirection: 'column', gap: 22 }}>
          <div style={{ display: 'flex', gap: 10, fontSize: 13, color: 'var(--leaf-fg)', flexWrap: 'wrap' }}>
            <Link href={`${base}/emploi`} style={{ fontWeight: 700, color: 'var(--ink)' }}>
              ← Offres d&apos;emploi
            </Link>
            <span>
              {settings.jobsTitle ?? 'Emploi'} › {ct.label}
            </span>
          </div>
          <div style={{ display: 'flex', gap: 22, alignItems: 'center', flexWrap: 'wrap' }}>
            <div style={{ width: 96, height: 96, borderRadius: 22, overflow: 'hidden', border: '4px solid #fff', transform: 'rotate(-3deg)', flexShrink: 0 }}>
              <Photo src={sized(company.coverUrl, 200, 200)} alt="" color={company.color} label={company.name} />
            </div>
            <div style={{ flex: 1, minWidth: 280 }}>
              <span
                style={{
                  display: 'inline-block',
                  background: ct.bg,
                  border: '1.5px solid var(--ink)',
                  fontWeight: 800,
                  fontSize: 12,
                  padding: '4px 10px',
                  borderRadius: 999,
                  marginBottom: 8,
                }}
              >
                {ct.label}
              </span>
              <h1 className="display" style={{ fontSize: 'clamp(36px,4.6vw,62px)', letterSpacing: '-0.035em', lineHeight: 0.95, margin: '0 0 8px' }}>
                {job.title}
              </h1>
              <div style={{ fontSize: 16, color: 'var(--leaf-fg-2)' }}>
                <b>{company.name}</b> · {company.communeName}
              </div>
            </div>
          </div>
          {tiles.length ? (
            <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 10 }}>
              {tiles.map((x) => (
                <div key={x.label} style={{ background: 'var(--paper)', borderRadius: 14, padding: '12px 16px' }}>
                  <div style={{ fontSize: 12, color: 'var(--muted)', fontWeight: 700 }}>{x.label}</div>
                  <b>{x.value}</b>
                </div>
              ))}
            </div>
          ) : null}
        </div>
      </section>

      <div
        className="container split"
        style={{
          paddingTop: 34,
          paddingBottom: 60,
          ['--cols' as string]: 'minmax(0,1.6fr) minmax(320px,1fr)',
          ['--gap' as string]: '32px',
          ['--align' as string]: 'start',
        }}
      >
        <div style={{ display: 'flex', flexDirection: 'column', gap: 28, minWidth: 0 }}>
          <div>
            <h2 className="h3" style={h3}>
              Le poste
            </h2>
            <p style={{ fontSize: 17, lineHeight: 1.6, margin: 0, textWrap: 'pretty', whiteSpace: 'pre-line' }}>{job.description}</p>
          </div>
          {job.missions.length ? (
            <div>
              <h2 className="h3" style={h3}>
                Vos missions
              </h2>
              {job.missions.map((m, i) => (
                <div key={i} style={{ display: 'flex', gap: 12, padding: '12px 0', borderTop: '1px solid var(--line)', fontSize: 16 }}>
                  <span style={{ color: 'var(--green)', fontWeight: 800 }}>→</span>
                  {m}
                </div>
              ))}
            </div>
          ) : null}
          {job.profile.length ? (
            <div>
              <h2 className="h3" style={h3}>
                Profil recherché
              </h2>
              {job.profile.map((m, i) => (
                <div key={i} style={{ display: 'flex', gap: 12, padding: '12px 0', borderTop: '1px solid var(--line)', fontSize: 16 }}>
                  <span style={{ color: 'var(--brick)', fontWeight: 800 }}>✓</span>
                  {m}
                </div>
              ))}
            </div>
          ) : null}
          <div style={{ position: 'relative', borderRadius: 22, overflow: 'hidden', minHeight: 220, color: '#fff', background: 'var(--ink)' }}>
            <Photo src={sized(settings.livingImageUrl ?? t.heroImageUrl, 1200)} alt="" color="#1F6B52" label=" " style={{ position: 'absolute', inset: 0 }} />
            <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(90deg,rgba(20,32,27,.85),rgba(20,32,27,.2))' }} />
            <div style={{ position: 'relative', padding: 26, display: 'flex', flexDirection: 'column', gap: 8, maxWidth: 460 }}>
              <span
                style={{
                  alignSelf: 'flex-start',
                  background: 'var(--amber)',
                  color: 'var(--ink)',
                  fontWeight: 800,
                  fontSize: 12,
                  padding: '4px 9px',
                  borderRadius: 6,
                  transform: 'rotate(-3deg)',
                }}
              >
                Vivre ici
              </span>
              <div className="display" style={{ fontSize: 28, lineHeight: 1 }}>
                {settings.livingTitle ?? `Vivre et travailler à ${t.name}.`}
              </div>
              <div style={{ fontSize: 14, color: 'var(--sage-4)' }}>
                {settings.livingText ?? 'La collectivité vous accompagne pour vous installer : logement, écoles, transports.'}
              </div>
            </div>
          </div>
        </div>
        <aside className="sticky-aside" style={{ display: 'flex', flexDirection: 'column', gap: 14, position: 'sticky', top: 84 }}>
          <div className="card" style={{ borderRadius: 20, padding: 20, boxShadow: 'var(--shadow-card)' }}>
            <ApplyForm jobId={job.id} companyName={company.name} />
          </div>
          <Link
            href={`${base}${company.path}`}
            className="card card-link"
            style={{ borderRadius: 20, padding: 14, display: 'grid', gridTemplateColumns: '56px 1fr', gap: 12, alignItems: 'center' }}
          >
            <div style={{ width: 56, height: 56, borderRadius: 12, overflow: 'hidden' }}>
              <Photo src={sized(company.coverUrl, 120, 120)} alt="" color={company.color} label={company.name} />
            </div>
            <div>
              <div style={{ fontWeight: 700 }}>{company.name}</div>
              <div style={{ fontSize: 13, color: 'var(--muted)' }}>
                {company.activity} · {company.communeName}
              </div>
              <div style={{ fontSize: 13, fontWeight: 700, color: 'var(--green)', marginTop: 2 }}>Voir l&apos;entreprise →</div>
            </div>
          </Link>
          {company.lat && company.lng ? (
            <div style={{ height: 180, borderRadius: 20, overflow: 'hidden', border: '1px solid var(--line)' }}>
              <MapView
                mode="fiche"
                focusId={company.id}
                points={[{ id: company.id, lat: company.lat, lng: company.lng, name: company.name, color: company.color }]}
                tileUrl={portal.mapConfig.tileUrl}
                attribution={portal.mapConfig.attribution}
                ariaLabel="Lieu de travail"
                style={{ width: '100%', height: '100%' }}
              />
            </div>
          ) : null}
        </aside>
      </div>
    </div>
  );
}
