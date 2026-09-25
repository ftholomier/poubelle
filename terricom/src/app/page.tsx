import type { Metadata } from 'next';
import Link from 'next/link';
import { SiteFooter, SiteHeader } from '@/components/site/SiteChrome';
import { DemoBar } from '@/components/DemoBar';
import { Photo } from '@/components/ui/Photo';
import { fmtEuros, fmtInt } from '@/lib/format';
import { env } from '@/server/env';
import { getPlans } from '@/server/services/billing';
import { pilotShowcase } from '@/server/services/marketing';

export const metadata: Metadata = {
  title: { absolute: 'terricom — Le territoire, en vitrine.' },
  description:
    "La plateforme d'animation et de valorisation économique du territoire : une fiche pour chaque commerce, artisan et producteur, une carte, des campagnes, une newsletter et des statistiques pour la collectivité.",
  alternates: { canonical: '/' },
};

const U = (id: string) => `https://images.unsplash.com/photo-${id}?w=800&h=500&fit=crop&q=70&auto=format`;

type Screen = { id: string; l: string; href: string | null };

export default async function PresentationPage() {
  const [pilot, plans] = await Promise.all([pilotShowcase(), getPlans()]);
  const demo = env.DEMO_MODE;
  const premium = plans.find((p) => p.key === 'PREMIUM');
  const enter = (space: string, path?: string) => (demo ? `/demo/entrer/${space}${path ? `?vers=${encodeURIComponent(path)}` : ''}` : null);
  const L = pilot?.links;
  const spaces: { n: string; who: string; tagBg: string; href: string; img: string; d: string; screens: Screen[] }[] = [
    {
      n: 'Portail public',
      who: 'Habitants & visiteurs',
      tagBg: '#F4B266',
      href: L?.home ?? '/territoires',
      img: U('1533900298318-6b8da08a523e'),
      d: 'Découvrir le territoire : recherche en langage naturel, carte, fiches optimisées pour Google, campagnes, circuits et emploi.',
      screens: [
        { id: 'P1', l: 'Accueil du territoire', href: L?.home ?? null },
        { id: 'P2', l: 'Carte & recherche IA', href: L?.explore ?? null },
        { id: 'P3', l: 'Fiche établissement', href: L?.fiche ?? null },
        { id: 'P4', l: 'Page commune', href: L?.commune ?? null },
        { id: 'P5', l: 'Campagne de Noël', href: L?.campaign ?? null },
        { id: 'P6', l: 'Circuits & passeport', href: L?.circuits ?? null },
        { id: 'P7', l: 'Agenda', href: L?.agenda ?? null },
        { id: 'P8', l: 'Travailler ici', href: L?.jobs ?? null },
      ],
    },
    {
      n: 'Espace entreprise',
      who: 'Commerçants & artisans',
      tagBg: '#D6E8B4',
      href: demo ? enter('pro')! : '/professionnels',
      img: U('1509440159596-0249088772ff'),
      d: 'Revendiquer sa fiche en 5 minutes, la tenir à jour, publier avec l’IA, suivre ses statistiques, imprimer son QR code.',
      screens: [
        { id: 'E1', l: 'Revendication de fiche', href: '/pro/revendiquer' },
        { id: 'E2', l: 'Tableau de bord', href: enter('pro') },
        { id: 'E3', l: 'Éditeur de fiche', href: enter('pro', '/pro/{est}/fiche') },
        { id: 'E4', l: 'Publications + IA', href: enter('pro', '/pro/{est}/publications') },
        { id: 'E5', l: 'Statistiques', href: enter('pro', '/pro/{est}/statistiques') },
        { id: 'E6', l: 'Kit vitrine & QR', href: enter('pro', '/pro/{est}/kit') },
        { id: 'E7', l: 'Offres', href: demo ? enter('pro', '/pro/{est}/offre') : '/tarifs' },
      ],
    },
    {
      n: 'Back-office collectivité',
      who: 'CC & communes',
      tagBg: '#CDE3F2',
      href: demo ? enter('collectivite')! : '/collectivites',
      img: U('1528605248644-14dd04022da1'),
      d: 'Piloter l’adoption, valider les revendications, lancer des campagnes avec l’assistant, envoyer la newsletter, mesurer.',
      screens: [
        { id: 'C1', l: 'Tableau de bord', href: enter('collectivite') },
        { id: 'C2', l: 'Entreprises & import', href: enter('collectivite', '/collectivite/entreprises') },
        { id: 'C3', l: 'Revendications', href: enter('collectivite', '/collectivite/moderation') },
        { id: 'C4', l: 'Campagnes + IA', href: enter('collectivite', '/collectivite/campagnes') },
        { id: 'C5', l: 'Newsletter', href: enter('collectivite', '/collectivite/newsletter') },
        { id: 'C6', l: 'Circuits', href: enter('collectivite', '/collectivite/circuits') },
        { id: 'C7', l: 'Statistiques', href: enter('collectivite', '/collectivite/statistiques') },
        { id: 'C8', l: 'Personnalisation', href: enter('collectivite', '/collectivite/personnalisation') },
      ],
    },
    {
      n: 'Console plateforme',
      who: 'Exploitant',
      tagBg: '#DCD3F3',
      href: demo ? enter('console')! : '/marque',
      img: U('1522071820081-009f0129c71c'),
      d: 'Gérer les territoires, abonnements, modules et quotas, la sécurité, l’audit et simuler le modèle économique.',
      screens: [
        { id: 'S1', l: 'Vue d’ensemble', href: enter('console') },
        { id: 'S2', l: 'Territoires & modules', href: enter('console', '/console/territoires') },
        { id: 'S3', l: 'Audit & sécurité', href: enter('console', '/console/audit') },
        { id: 'S4', l: 'Simulateur économique', href: enter('console', '/console/simulateur') },
      ],
    },
  ];
  const steps: [string, string, string | null][] = [
    ['Le territoire vu par un habitant', 'On cherche « où offrir local pour Noël » et l’assistant répond.', L?.home ?? null],
    ['La carte vivante', 'Filtrer, cliquer, arriver sur une fiche prête pour Google.', L?.explore ?? null],
    ['Le calendrier de l’Avent', 'L’animation commerciale rendue ludique.', L?.campaign ?? null],
    ['Une boulangère prend la main', 'Revendication vérifiée en 5 étapes.', '/pro/revendiquer'],
    ['L’IA rédige pour elle', 'Une phrase donne 6 publications prêtes.', enter('pro', '/pro/{est}/publications')],
    ['La collectivité pilote', 'Adoption, météo du commerce, campagne en un clic.', enter('collectivite')],
  ];
  const mvp = [
    'Multi-territoires',
    'Communes',
    'Fiches & revendication',
    'Recherche',
    'Carte',
    'Actualités',
    'Événements',
    'Admin communale & territoriale',
    'Statistiques',
    'Newsletter',
    'Personnalisation',
    'SEO',
    'Import',
  ];
  const v2 = [
    'Assistant IA',
    'Campagnes automatisées',
    'Circuits & QR codes',
    'Recrutement',
    'Multilingue',
    'Rendez-vous',
    'PWA & notifications',
    'Offres Premium',
  ];
  const heroImg = 'https://images.unsplash.com/photo-1470071459604-3b5ec3a7fe05?w=2200&q=75&auto=format&fit=crop';

  return (
    <>
      {demo ? <DemoBar active="presentation" /> : <SiteHeader current="/" />}
      <main id="contenu" className="site-main">
        <section style={{ position: 'relative', minHeight: 640, overflow: 'hidden', display: 'flex', alignItems: 'flex-end', background: 'var(--ink)' }}>
          {}
          <Photo src={heroImg} alt="" label=" " color="#1F6B52" eager style={{ position: 'absolute', inset: 0, width: '100%', height: '100%', opacity: 0.7 }} />
          <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,rgba(20,32,27,.1),rgba(20,32,27,.9))' }} />
          <div
            className="mk-wob"
            style={{
              position: 'absolute',
              right: '7%',
              top: 70,
              width: 170,
              height: 170,
              borderRadius: '50%',
              background: 'var(--amber)',
              color: 'var(--ink)',
              display: 'grid',
              placeItems: 'center',
              textAlign: 'center',
              fontFamily: 'var(--font-display)',
              fontWeight: 800,
              lineHeight: 1.05,
              fontSize: 18,
              boxShadow: '0 14px 40px rgba(0,0,0,.3)',
            }}
          >
            Gratuit
            <br />
            pour chaque
            <br />
            professionnel
          </div>
          <div style={{ position: 'relative', maxWidth: 1320, width: '100%', margin: '0 auto', padding: '0 32px 64px', color: '#fff' }}>
            <div style={{ fontSize: 14, fontWeight: 700, letterSpacing: '.1em', textTransform: 'uppercase', color: 'var(--amber)', marginBottom: 16 }}>
              {pilot ? `Territoire partenaire pilote · ${pilot.territory.name}` : 'Plateforme des territoires'}
            </div>
            <h1
              className="display"
              style={{
                fontSize: 'clamp(46px,6.6vw,104px)',
                lineHeight: 0.92,
                letterSpacing: '-0.04em',
                margin: '0 0 22px',
                maxWidth: 1100,
                textWrap: 'balance',
              }}
            >
              La plateforme d’animation et de valorisation économique du territoire.
            </h1>
            <p style={{ fontSize: 20, maxWidth: 640, color: '#E0E8E3', margin: 0, lineHeight: 1.45 }}>
              Donner à la collectivité une vision complète de son tissu économique et à chaque professionnel les moyens de gérer sa présence numérique, sans
              qu’il ait besoin de créer son propre site.
            </p>
            {!demo ? (
              <div style={{ display: 'flex', gap: 10, marginTop: 26, flexWrap: 'wrap' }}>
                <Link href="/demo" className="btn btn-amber">
                  Demander une démo
                </Link>
                {pilot ? (
                  <a href={pilot.links.home} className="btn" style={{ border: '1.5px solid #fff', color: '#fff' }}>
                    Voir un territoire
                  </a>
                ) : null}
              </div>
            ) : null}
          </div>
        </section>

        <section className="mk-wrap" style={{ padding: '70px 32px 30px', display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(300px,1fr))', gap: 22 }}>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div className="mk-eyebrow" style={{ color: 'var(--danger)' }}>
              Le constat
            </div>
            <div className="display" style={{ fontSize: 34, lineHeight: 1.05, letterSpacing: '-0.025em' }}>
              Nos professionnels sont sur Internet en pièces détachées.
            </div>
            <p style={{ margin: 0, fontSize: 16, color: 'var(--muted)', lineHeight: 1.55 }}>
              Informations incomplètes ou obsolètes, dispersées entre Google, Facebook et quelques sites. Les habitants ne trouvent pas, les visiteurs passent à
              côté.
            </p>
          </div>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <div className="mk-eyebrow" style={{ color: 'var(--green)' }}>
              La proposition
            </div>
            <div className="display" style={{ fontSize: 34, lineHeight: 1.05, letterSpacing: '-0.025em' }}>
              Une vitrine unique, animée par la collectivité.
            </div>
            <p style={{ margin: 0, fontSize: 16, color: 'var(--muted)', lineHeight: 1.55 }}>
              Une fiche référencée pour chaque établissement, une carte, des campagnes, des circuits, une newsletter et des statistiques pour piloter.
            </p>
          </div>
          <div style={{ background: 'var(--green)', color: '#fff', borderRadius: 24, padding: 26, display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18 }}>
            {[
              { v: fmtInt(pilot?.establishments ?? 0), l: 'fiches créées dès le lancement' },
              { v: fmtInt(pilot?.communes ?? 0), l: 'communes réunies' },
              { v: '0 €', l: 'pour les entreprises', amber: true },
              { v: '1', l: 'plateforme mutualisée' },
            ].map((x) => (
              <div key={x.l}>
                <div className="display" style={{ fontSize: 44, lineHeight: 1, color: x.amber ? 'var(--amber)' : undefined }}>
                  {x.v}
                </div>
                <div style={{ fontSize: 13, color: '#CFE3D6' }}>{x.l}</div>
              </div>
            ))}
          </div>
        </section>

        <section className="mk-wrap">
          <h2 className="mk-h2">Quatre espaces, une seule plateforme</h2>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(290px,1fr))', gap: 18 }}>
            {spaces.map((sp) => (
              <div key={sp.n} className="mk-card" style={{ overflow: 'hidden', display: 'flex', flexDirection: 'column' }}>
                <a href={sp.href} style={{ position: 'relative', height: 190, display: 'block', background: '#E4DFD3' }}>
                  {}
                  <Photo src={sp.img} alt="" label=" " color={sp.tagBg} style={{ width: '100%', height: '100%' }} />
                  <span
                    style={{
                      position: 'absolute',
                      left: 14,
                      top: 14,
                      background: sp.tagBg,
                      color: 'var(--ink)',
                      fontWeight: 800,
                      fontSize: 12,
                      padding: '5px 10px',
                      borderRadius: 8,
                      transform: 'rotate(-3deg)',
                    }}
                  >
                    {sp.who}
                  </span>
                </a>
                <div style={{ padding: '18px 20px 20px', display: 'flex', flexDirection: 'column', gap: 10, flex: 1 }}>
                  <a href={sp.href} className="display" style={{ fontSize: 26, letterSpacing: '-0.02em', color: 'var(--ink)' }}>
                    {sp.n} →
                  </a>
                  <div style={{ fontSize: 14, color: 'var(--muted)', lineHeight: 1.5 }}>{sp.d}</div>
                  <div style={{ display: 'flex', flexDirection: 'column', gap: 2, marginTop: 'auto', borderTop: '1px solid #EFEBE2', paddingTop: 10 }}>
                    {sp.screens.map((sc) =>
                      sc.href ? (
                        <a key={sc.id} href={sc.href} className="mk-space-link">
                          <span>
                            <b style={{ color: 'var(--brick)', fontFamily: 'ui-monospace,monospace', fontSize: 11, marginRight: 8 }}>{sc.id}</b>
                            {sc.l}
                          </span>
                          <span style={{ color: 'var(--green)' }}>↗</span>
                        </a>
                      ) : (
                        <span key={sc.id} className="mk-space-link">
                          <span>
                            <b style={{ color: 'var(--brick)', fontFamily: 'ui-monospace,monospace', fontSize: 11, marginRight: 8 }}>{sc.id}</b>
                            {sc.l}
                          </span>
                        </span>
                      ),
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </section>

        <section className="mk-wrap">
          <div style={{ background: 'var(--ink)', color: 'var(--cream)', borderRadius: 28, padding: 36 }}>
            {demo ? (
              <>
                <div className="mk-eyebrow" style={{ color: 'var(--amber)', marginBottom: 8 }}>
                  Pour la présentation aux élus · 12 minutes
                </div>
                <h2 className="mk-h2" style={{ fontSize: 40 }}>
                  Le parcours de démonstration
                </h2>
                <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))', gap: 12 }}>
                  {steps.map(([t, d, href], i) => (
                    <a key={t} href={href ?? '#'} className="mk-demo-step">
                      <div
                        className="display"
                        style={{
                          width: 36,
                          height: 36,
                          borderRadius: '50%',
                          background: 'var(--amber)',
                          color: 'var(--ink)',
                          display: 'grid',
                          placeItems: 'center',
                        }}
                      >
                        {i + 1}
                      </div>
                      <div style={{ fontWeight: 700, fontSize: 16 }}>{t}</div>
                      <div style={{ fontSize: 13, color: '#AEBDB5', lineHeight: 1.45 }}>{d}</div>
                    </a>
                  ))}
                </div>
              </>
            ) : (
              <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))', gap: 24, alignItems: 'center' }}>
                <div>
                  <div className="mk-eyebrow" style={{ color: 'var(--amber)', marginBottom: 8 }}>
                    Démonstration · 30 minutes en visio
                  </div>
                  <h2 className="mk-h2" style={{ fontSize: 40, marginBottom: 10 }}>
                    Voyez votre territoire en vitrine avant de décider.
                  </h2>
                  <p style={{ margin: 0, color: '#AEBDB5', fontSize: 16, lineHeight: 1.5 }}>
                    Nous préparons la démonstration avec les entreprises de vos communes, issues de la base SIRENE : vos élus découvrent leur propre territoire.
                  </p>
                </div>
                <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', justifyContent: 'flex-end' }}>
                  <Link href="/demo" className="btn btn-amber">
                    Demander une démo
                  </Link>
                  <Link href="/collectivites" className="btn console-btn-ghost-dark">
                    Ce que la collectivité y gagne
                  </Link>
                </div>
              </div>
            )}
          </div>
        </section>

        <section className="mk-wrap" style={{ padding: '40px 32px 80px', display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(300px,1fr))', gap: 18 }}>
          <div style={{ background: '#D6E8B4', borderRadius: 24, padding: 26, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <div style={{ fontSize: 12, fontWeight: 800, letterSpacing: '.08em', color: '#3B5A1F' }}>PILOTE · MVP</div>
            <div className="display" style={{ fontSize: 28, lineHeight: 1 }}>
              Être vu, partout.
            </div>
            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 6 }}>
              {mvp.map((m) => (
                <span key={m} style={{ background: '#fff', borderRadius: 999, padding: '5px 10px', fontSize: 12, fontWeight: 600 }}>
                  {m}
                </span>
              ))}
            </div>
          </div>
          <div style={{ background: '#DCD3F3', borderRadius: 24, padding: 26, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <div style={{ fontSize: 12, fontWeight: 800, letterSpacing: '.08em', color: '#4A3A7A' }}>SECONDE GÉNÉRATION</div>
            <div className="display" style={{ fontSize: 28, lineHeight: 1 }}>
              Faire venir, animer.
            </div>
            <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginTop: 6 }}>
              {v2.map((m) => (
                <span key={m} style={{ background: '#fff', borderRadius: 999, padding: '5px 10px', fontSize: 12, fontWeight: 600 }}>
                  {m}
                </span>
              ))}
            </div>
          </div>
          <div className="mk-card" style={{ padding: 26, display: 'flex', flexDirection: 'column', gap: 10 }}>
            <div style={{ fontSize: 12, fontWeight: 800, letterSpacing: '.08em', color: 'var(--muted)' }}>MODÈLE ÉCONOMIQUE</div>
            {[
              ['Commune indépendante', '1 200 – 2 400 € HT/an'],
              ['Communauté de communes', '6 000 – 15 000 € HT/an'],
              ['Mise en service', '2 000 – 8 000 € HT'],
            ].map(([l, v]) => (
              <div
                key={l}
                style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderTop: '1px solid #EFEBE2', fontSize: 14, gap: 10 }}
              >
                <span>{l}</span>
                <b>{v}</b>
              </div>
            ))}
            <div style={{ display: 'flex', justifyContent: 'space-between', padding: '8px 0', borderTop: '1px solid #EFEBE2', fontSize: 14, gap: 10 }}>
              <span>Entreprises</span>
              <b style={{ color: 'var(--green)' }}>Gratuit · Premium dès {premium ? fmtEuros(premium.priceMonthlyCents) : '24 €'}/mois</b>
            </div>
            <a href={demo ? enter('console', '/console/simulateur')! : '/tarifs'} style={{ fontWeight: 700, fontSize: 13, marginTop: 4 }}>
              {demo ? 'Ouvrir le simulateur →' : 'Voir les tarifs →'}
            </a>
          </div>
        </section>
      </main>
      <SiteFooter />
    </>
  );
}
