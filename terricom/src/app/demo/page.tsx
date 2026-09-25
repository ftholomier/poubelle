import type { Metadata } from 'next';
import { DemoForm } from '@/components/site/DemoForm';
import { SiteShell } from '@/components/site/SiteChrome';
import { fmtInt } from '@/lib/format';
import { pilotShowcase } from '@/server/services/marketing';

export const metadata: Metadata = {
  title: 'Demander une démo',
  description: 'Une démonstration de 30 minutes en visio, préparée avec les entreprises de votre territoire.',
  alternates: { canonical: '/demo' },
};

export default async function DemoPage() {
  const pilot = await pilotShowcase();
  return (
    <SiteShell current="/demo">
      <section className="mk-wrap mk-hero" style={{ alignItems: 'start' }}>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 16, position: 'sticky', top: 90 }}>
          <div className="mk-eyebrow" style={{ color: 'var(--green)' }}>
            Démonstration
          </div>
          <h1 className="display" style={{ fontSize: 'clamp(40px,5vw,68px)', letterSpacing: '-0.04em', lineHeight: 0.95, margin: 0 }}>
            Voyez votre territoire en vitrine avant de décider.
          </h1>
          <p style={{ margin: 0, fontSize: 18, color: '#4A514C', lineHeight: 1.5 }}>
            30 minutes en visio avec vos élus et vos services : nous importons au préalable les entreprises de vos communes depuis la base SIRENE.
          </p>
          <ul style={{ margin: 0, padding: 0, listStyle: 'none', display: 'flex', flexDirection: 'column', gap: 10, fontSize: 16 }}>
            {[
              'Le portail aux couleurs de votre territoire',
              'La revendication d’une fiche par un commerçant, en direct',
              'Le tableau de bord de la collectivité et la météo du commerce',
              'Une campagne créée en quelques minutes avec l’assistant',
            ].map((x) => (
              <li key={x} style={{ display: 'flex', gap: 10 }}>
                <span style={{ color: 'var(--green)', fontWeight: 800 }}>✓</span>
                {x}
              </li>
            ))}
          </ul>
          {pilot ? (
            <div className="mk-card" style={{ padding: 18, fontSize: 14, color: '#4A514C' }}>
              Territoire pilote : <b>{pilot.territory.name}</b> · {fmtInt(pilot.communes)} communes · {fmtInt(pilot.establishments)} fiches en ligne.{' '}
              <a href={pilot.links.home}>Voir le portail →</a>
            </div>
          ) : null}
        </div>
        <DemoForm />
      </section>
    </SiteShell>
  );
}
