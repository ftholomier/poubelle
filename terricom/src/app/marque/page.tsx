import type { Metadata } from 'next';
import Link from 'next/link';
import type { CSSProperties, ReactNode } from 'react';
import { DemoBar } from '@/components/DemoBar';
import { SiteFooter, SiteHeader } from '@/components/site/SiteChrome';
import { Photo } from '@/components/ui/Photo';
import { fmtInt } from '@/lib/format';
import { env } from '@/server/env';
import { qrDataUrl } from '@/server/qr';
import { pilotShowcase, platformNumbers } from '@/server/services/marketing';

// Rendu à la demande : chiffres réels et configuration lue à l'exécution (jamais figés à la compilation).
export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'La marque',
  description: 'Charte graphique de terricom : plateforme de marque, logo, couleurs, typographie, ton, photographie et applications.',
  alternates: { canonical: '/marque' },
};

const D = 'var(--font-display)';
const IMG = (id: string, w = 600) => `https://images.unsplash.com/photo-${id}?w=${w}&q=70&auto=format&fit=crop`;

/** Repère de carte (symbole) avec ou sans le « t ». */
function Mark({ size, bg, fg, letter = true, style }: { size: number; bg: string; fg?: string; letter?: boolean; style?: CSSProperties }) {
  return (
    <div
      style={{
        width: size,
        height: size,
        borderRadius: '50% 50% 50% 14%',
        transform: 'rotate(-45deg)',
        background: bg,
        display: 'grid',
        placeItems: 'center',
        flexShrink: 0,
        ...style,
      }}
    >
      {letter ? (
        <span
          style={{
            transform: 'rotate(45deg)',
            fontFamily: D,
            fontWeight: 800,
            fontSize: Math.round(size * 0.64),
            lineHeight: 1,
            color: fg,
            marginTop: -Math.round(size * 0.077),
          }}
        >
          t
        </span>
      ) : null}
    </div>
  );
}

function Word({
  size,
  color = 'var(--ink)',
  dot = 'var(--amber)',
  ls = '-0.05em',
  lh,
}: {
  size: number;
  color?: string;
  dot?: string | null;
  ls?: string;
  lh?: number;
}) {
  return (
    <div style={{ fontFamily: D, fontWeight: 800, fontSize: size, letterSpacing: ls, lineHeight: lh, color }}>
      terricom{dot ? <span style={{ color: dot }}>.</span> : '.'}
    </div>
  );
}

function Tag({ children, color = '#5E655F', left = 22, top = 18 }: { children: ReactNode; color?: string; left?: number; top?: number }) {
  return <span style={{ position: 'absolute', left, top, fontSize: 12, fontWeight: 700, color }}>{children}</span>;
}

function Section({ id, n, title, children, style }: { id?: string; n: string; title: string; children: ReactNode; style?: CSSProperties }) {
  return (
    <section
      id={id}
      style={{ maxWidth: 1280, margin: '0 auto', padding: '70px 36px 40px', display: 'flex', flexDirection: 'column', gap: 22, scrollMarginTop: 70, ...style }}
    >
      <div className="mk-eyebrow" style={{ color: 'var(--brick)', letterSpacing: '.1em' }}>
        {n} · {title}
      </div>
      {children}
    </section>
  );
}

const card: CSSProperties = { background: '#FFFDF8', border: '1px solid #E4DFD3', borderRadius: 28, position: 'relative' };

export default async function BrandPage() {
  const [pilot, nums, qr] = await Promise.all([pilotShowcase(), platformNumbers(), qrDataUrl('https://terricom.fr', { dark: '#14201B' })]);
  const toc = [
    ['plateforme', 'Plateforme'],
    ['logo', 'Logo'],
    ['couleurs', 'Couleurs'],
    ['typo', 'Typographie'],
    ['ton', 'Ton'],
    ['images', 'Photographie'],
    ['applications', 'Applications'],
  ];
  const pillars = [
    ['Local', 'Chaque écran parle d’un lieu réel, de gens réels.'],
    ['Utile', 'Horaires justes, adresse, bouton appeler. Le reste vient après.'],
    ['Partagé', 'Une plateforme commune financée par la collectivité, gratuite pour les pros.'],
    ['Vivant', 'Campagnes, circuits, tampons : on donne envie de sortir.'],
  ];
  const domains = [
    ['terricom.fr', 'Site de la marque'],
    [`${pilot?.territory.slug ?? 'valdeloue'}.terricom.fr`, 'Un portail par territoire'],
    ['pro.terricom.fr', 'Espace entreprises'],
    ['@terricom.fr', 'Adresses email'],
  ];
  const donts: { l: string; bg: string; tf: string; fl: string; mark: string; fg: string; font: string }[] = [
    { l: 'Ne pas déformer', bg: '#FFFDF8', tf: 'scaleX(1.3)', fl: 'none', mark: '#1F6B52', fg: '#14201B', font: D },
    { l: 'Ne pas changer les couleurs', bg: '#FFFDF8', tf: 'none', fl: 'none', mark: '#7A5BB5', fg: '#D95C4E', font: D },
    { l: 'Ne pas changer la police', bg: '#FFFDF8', tf: 'none', fl: 'none', mark: '#1F6B52', fg: '#14201B', font: 'Georgia, serif' },
    {
      l: 'Pas d’effets ni d’ombres',
      bg: '#FFFDF8',
      tf: 'rotate(-12deg)',
      fl: 'drop-shadow(4px 4px 0 #F4B266) blur(.6px)',
      mark: '#1F6B52',
      fg: '#14201B',
      font: D,
    },
    { l: 'Contraste insuffisant', bg: '#5FA37E', tf: 'none', fl: 'none', mark: '#1F6B52', fg: '#3F8F4E', font: D },
  ];
  const core = [
    ['Encre', 'Texte, fonds sombres', '#14201B', '20 · 32 · 27', 'Pantone 5463 C', '#F7F4EC', '#14201B'],
    ['Vert Loue', 'Couleur principale', '#1F6B52', '31 · 107 · 82', 'Pantone 7727 C', '#FFFFFF', '#1F6B52'],
    ['Ambre', 'Accent, pastilles, appels à l’action', '#F4B266', '244 · 178 · 102', 'Pantone 1355 C', '#14201B', '#F4B266'],
    ['Crème', 'Fond de page', '#F7F4EC', '247 · 244 · 236', 'Pantone 9224 C', '#14201B', '#E4DFD3'],
  ];
  const cats = [
    ['Commerce', '#C8892A'],
    ['Artisan', '#3E6FB0'],
    ['Producteur', '#3F8F4E'],
    ['Restauration', '#D95C4E'],
    ['Services', '#7A5BB5'],
    ['Brique', '#C8702A'],
  ];
  const B = "'Bricolage Grotesque Variable', 'Bricolage Grotesque', sans-serif";
  const S = "'Instrument Sans Variable', 'Instrument Sans', sans-serif";
  const scale: [string, string, string, string, number, number, string][] = [
    ['Display', 'Bricolage 800 · 96', 'Le territoire, en vitrine.', B, 800, 64, '-0.045em'],
    ['Titre 1', 'Bricolage 800 · 56', 'Noël chez vos commerçants', B, 800, 48, '-0.035em'],
    ['Titre 2', 'Bricolage 800 · 32', 'Ouvert près de vous', B, 800, 32, '-0.025em'],
    ['Titre 3', 'Bricolage 700 · 20', 'Boulangerie Martin', B, 700, 20, '-0.01em'],
    ['Texte', 'Instrument 400 · 16', 'Pains au levain naturel, viennoiseries au beurre AOP et galettes comtoises.', S, 400, 16, '0'],
    ['Libellé', 'Instrument 800 · 12 · capitales', 'CAMPAGNE DU TERRITOIRE', S, 800, 12, '0.08em'],
  ];
  const tone = [
    ['On tutoie la clarté', 'Votre fiche est complète à 72 %.', 'Le taux de complétion de votre profil établissement s’élève à 72 %.'],
    ['On célèbre', 'Bienvenue Sophie, la vitrine est à vous !', 'Votre demande de revendication a été traitée avec succès.'],
    ['On reste concret', '23 producteurs vous ouvrent leurs portes jusqu’au 24.', 'Une offre riche et diversifiée pour tous les publics.'],
  ];
  const words = ['proche', 'fait ici', 'ouvert', 'pousser la porte', 'vitrine', 'ensemble', 'découvrir', 'simple'];
  const month = new Date().toLocaleDateString('fr-FR', { month: 'long', year: 'numeric', timeZone: 'Europe/Paris' });

  return (
    <>
      {env.DEMO_MODE ? <DemoBar active="marque" /> : <SiteHeader />}
      <main id="contenu" className="site-main" style={{ color: 'var(--ink)' }}>
        <section style={{ background: 'var(--ink)', color: 'var(--cream)', position: 'relative', overflow: 'hidden' }}>
          <div
            style={{
              position: 'absolute',
              right: -120,
              top: -120,
              width: 520,
              height: 520,
              borderRadius: '50% 50% 50% 12%',
              transform: 'rotate(-45deg)',
              background: 'var(--green)',
              opacity: 0.55,
            }}
          />
          <div
            className="mk-wob"
            style={{
              position: 'absolute',
              right: '7%',
              top: 110,
              width: 150,
              height: 150,
              borderRadius: '50%',
              background: 'var(--amber)',
              color: 'var(--ink)',
              display: 'grid',
              placeItems: 'center',
              textAlign: 'center',
              fontFamily: D,
              fontWeight: 800,
              fontSize: 17,
              lineHeight: 1.05,
            }}
          >
            Charte
            <br />
            graphique
            <br />
            v1.0
          </div>
          <div
            style={{ position: 'relative', maxWidth: 1280, margin: '0 auto', padding: '40px 36px 90px', display: 'flex', flexDirection: 'column', gap: 120 }}
          >
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', fontSize: 13, color: '#AEBDB5', flexWrap: 'wrap', gap: 10 }}>
              <span>terricom.fr</span>
              <span>Guide de marque · {month}</span>
            </div>
            <div style={{ display: 'flex', flexDirection: 'column', gap: 28 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: 26, flexWrap: 'wrap' }}>
                <Mark size={118} bg="var(--amber)" fg="var(--ink)" />
                <h1 style={{ fontFamily: D, fontWeight: 800, fontSize: 'clamp(72px,11vw,168px)', letterSpacing: '-0.055em', lineHeight: 0.85, margin: 0 }}>
                  terricom<span style={{ color: 'var(--amber)' }}>.</span>
                </h1>
              </div>
              <p style={{ fontSize: 'clamp(20px,2.2vw,28px)', maxWidth: 760, margin: 0, lineHeight: 1.35, color: '#D6E0DA', textWrap: 'pretty' }}>
                La plateforme qui met en vitrine l’économie locale. Pour les collectivités qui animent, les professionnels qui font, et les habitants qui
                poussent la porte.
              </p>
              <div style={{ display: 'flex', alignItems: 'center', gap: 18, flexWrap: 'wrap', marginTop: 8 }}>
                <div
                  style={{
                    display: 'inline-flex',
                    alignItems: 'center',
                    gap: 16,
                    background: 'var(--cream)',
                    color: 'var(--ink)',
                    borderRadius: 22,
                    padding: '14px 28px 14px 16px',
                    boxShadow: '0 18px 40px rgba(0,0,0,.35)',
                  }}
                >
                  <span
                    style={{
                      display: 'grid',
                      placeItems: 'center',
                      width: 52,
                      height: 52,
                      borderRadius: 14,
                      background: 'var(--green)',
                      color: 'var(--amber)',
                      fontSize: 22,
                      fontWeight: 800,
                    }}
                    aria-hidden="true"
                  >
                    🔒︎
                  </span>
                  <span style={{ fontFamily: D, fontWeight: 800, fontSize: 'clamp(40px,5.4vw,76px)', letterSpacing: '-0.045em', lineHeight: 1 }}>
                    terricom<span style={{ color: 'var(--green)' }}>.fr</span>
                  </span>
                </div>
                <span
                  style={{
                    background: 'var(--amber)',
                    color: 'var(--ink)',
                    fontFamily: D,
                    fontWeight: 800,
                    fontSize: 16,
                    padding: '9px 14px',
                    borderRadius: 12,
                    transform: 'rotate(-4deg)',
                  }}
                >
                  Notre domaine, 100 % français
                </span>
              </div>
            </div>
          </div>
        </section>

        <nav
          style={{ position: 'sticky', top: 0, zIndex: 20, background: 'var(--cream)', borderBottom: '1px solid #E4DFD3' }}
          aria-label="Sommaire de la charte"
        >
          <div style={{ maxWidth: 1280, margin: '0 auto', padding: '12px 36px', display: 'flex', gap: 6, flexWrap: 'wrap', fontSize: 13, fontWeight: 600 }}>
            {toc.map(([id, l], i) => (
              <a
                key={id}
                href={`#${id}`}
                style={{ color: 'var(--ink)', padding: '6px 12px', borderRadius: 999, border: '1px solid #E4DFD3', background: '#FFFDF8' }}
              >
                0{i + 1} {l}
              </a>
            ))}
          </div>
        </nav>

        <section
          id="plateforme"
          className="brand-split"
          style={{
            maxWidth: 1280,
            margin: '0 auto',
            padding: '90px 36px 40px',
            display: 'grid',
            gridTemplateColumns: 'minmax(0,1fr) minmax(0,1.3fr)',
            gap: 56,
            scrollMarginTop: 70,
          }}
        >
          <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
            <div className="mk-eyebrow" style={{ color: 'var(--brick)', letterSpacing: '.1em' }}>
              01 · Plateforme de marque
            </div>
            <h2 style={{ fontFamily: D, fontWeight: 800, fontSize: 'clamp(40px,4.4vw,64px)', letterSpacing: '-0.035em', lineHeight: 1.05, margin: 0 }}>
              <b>Terri</b>
              <span style={{ color: 'var(--green)', fontWeight: 400 }}>toire</span> + <b>com</b>
              <span style={{ color: 'var(--green)', fontWeight: 400 }}>merce</span>,<br />
              <b>com</b>
              <span style={{ color: 'var(--green)', fontWeight: 400 }}>munication</span>, <b>com</b>
              <span style={{ color: 'var(--green)', fontWeight: 400 }}>munauté</span>.
            </h2>
            <p style={{ fontSize: 17, color: '#4A514C', lineHeight: 1.6, margin: 0 }}>
              Le nom réunit le lieu et ceux qui le font vivre. Il se prononce en trois syllabes, s’écrit sans hésiter et tient dans une adresse courte :{' '}
              <b>terricom.fr</b>.
            </p>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(240px,1fr))', gap: 14 }}>
            <div
              style={{
                background: 'var(--green)',
                color: '#fff',
                borderRadius: 22,
                padding: 24,
                display: 'flex',
                flexDirection: 'column',
                gap: 8,
                gridColumn: '1/-1',
              }}
            >
              <div style={{ fontSize: 12, fontWeight: 800, letterSpacing: '.08em', color: 'var(--amber)' }}>SIGNATURE</div>
              <div style={{ fontFamily: D, fontWeight: 800, fontSize: 'clamp(30px,3.4vw,46px)', letterSpacing: '-0.03em', lineHeight: 1 }}>
                Le territoire, en vitrine.
              </div>
            </div>
            {pillars.map(([t, d]) => (
              <div key={t} style={{ ...card, borderRadius: 22, padding: 22, display: 'flex', flexDirection: 'column', gap: 6 }}>
                <div style={{ fontFamily: D, fontWeight: 800, fontSize: 22 }}>{t}</div>
                <div style={{ fontSize: 14, color: '#4A514C', lineHeight: 1.5 }}>{d}</div>
              </div>
            ))}
          </div>
        </section>

        <section style={{ maxWidth: 1280, margin: '0 auto', padding: '40px 36px 10px' }}>
          <div
            className="brand-split"
            style={{
              background: 'var(--green)',
              color: '#fff',
              borderRadius: 32,
              padding: 'clamp(28px,4vw,52px)',
              display: 'grid',
              gridTemplateColumns: 'minmax(0,1.5fr) minmax(0,1fr)',
              gap: 32,
              alignItems: 'center',
              position: 'relative',
              overflow: 'hidden',
            }}
          >
            <div
              style={{
                position: 'absolute',
                right: -80,
                bottom: -80,
                width: 280,
                height: 280,
                borderRadius: '50% 50% 50% 14%',
                transform: 'rotate(-45deg)',
                background: 'var(--ink)',
                opacity: 0.35,
              }}
            />
            <div style={{ position: 'relative', display: 'flex', flexDirection: 'column', gap: 14, minWidth: 0 }}>
              <div className="mk-eyebrow" style={{ color: 'var(--amber)', letterSpacing: '.1em' }}>
                Le nom de domaine de la marque
              </div>
              <div style={{ fontFamily: D, fontWeight: 800, fontSize: 'clamp(56px,9vw,136px)', letterSpacing: '-0.055em', lineHeight: 0.85 }}>
                terricom<span style={{ color: 'var(--amber)' }}>.fr</span>
              </div>
              <div style={{ fontSize: 18, color: '#CFE3D6', maxWidth: 560, lineHeight: 1.45 }}>
                Le nom de marque et le nom de domaine sont identiques, en <b style={{ color: '#fff' }}>.fr</b> : une plateforme française, hébergée en France,
                pensée pour les collectivités françaises.
              </div>
            </div>
            <div style={{ position: 'relative', display: 'flex', flexDirection: 'column', gap: 10 }}>
              {domains.map(([u, r]) => (
                <div
                  key={u}
                  style={{
                    display: 'flex',
                    justifyContent: 'space-between',
                    gap: 12,
                    alignItems: 'center',
                    background: 'rgba(20,32,27,.35)',
                    borderRadius: 14,
                    padding: '12px 16px',
                    fontSize: 15,
                  }}
                >
                  <span style={{ fontFamily: 'ui-monospace,monospace', fontWeight: 600 }}>{u}</span>
                  <span style={{ fontSize: 12, color: '#CFE3D6', textAlign: 'right' }}>{r}</span>
                </div>
              ))}
            </div>
          </div>
        </section>

        <Section id="logo" n="02" title="Logo">
          <div className="brand-split" style={{ display: 'grid', gridTemplateColumns: 'minmax(0,1.4fr) minmax(0,1fr)', gap: 18 }}>
            <div style={{ ...card, minHeight: 380, display: 'grid', placeItems: 'center' }}>
              <Tag>Logo principal · horizontal</Tag>
              <div style={{ display: 'flex', alignItems: 'center', gap: 18, flexWrap: 'wrap', justifyContent: 'center' }}>
                <Mark size={78} bg="var(--green)" fg="var(--amber)" />
                <Word size={92} ls="-0.055em" lh={0.9} />
              </div>
            </div>
            <div style={{ display: 'grid', gridTemplateRows: '1fr 1fr', gap: 18 }}>
              <div style={{ ...card, display: 'grid', placeItems: 'center', minHeight: 180 }}>
                <Tag>Logo empilé</Tag>
                <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 10 }}>
                  <Mark size={58} bg="var(--green)" fg="var(--amber)" />
                  <Word size={40} lh={0.9} />
                </div>
              </div>
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 18 }}>
                <div style={{ ...card, display: 'grid', placeItems: 'center', minHeight: 160 }}>
                  <Tag left={18} top={14}>
                    Symbole
                  </Tag>
                  <Mark size={72} bg="var(--green)" fg="var(--amber)" />
                </div>
                <div style={{ background: 'var(--green)', borderRadius: 28, display: 'grid', placeItems: 'center', position: 'relative', minHeight: 160 }}>
                  <Tag left={18} top={14} color="#CFE3D6">
                    Icône d’app
                  </Tag>
                  <div style={{ width: 88, height: 88, borderRadius: 22, background: 'var(--ink)', display: 'grid', placeItems: 'center' }}>
                    <Mark size={50} bg="var(--amber)" fg="var(--ink)" />
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(260px,1fr))', gap: 18 }}>
            <div style={{ background: 'var(--ink)', borderRadius: 24, minHeight: 170, display: 'grid', placeItems: 'center', position: 'relative' }}>
              <Tag left={18} top={14} color="#8FA197">
                Sur fond encre
              </Tag>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <Mark size={40} bg="var(--amber)" fg="var(--ink)" />
                <Word size={44} color="var(--cream)" />
              </div>
            </div>
            <div style={{ background: 'var(--amber)', borderRadius: 24, minHeight: 170, display: 'grid', placeItems: 'center', position: 'relative' }}>
              <Tag left={18} top={14} color="#6B4212">
                Sur fond ambre
              </Tag>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <Mark size={40} bg="var(--ink)" fg="var(--amber)" />
                <Word size={44} dot={null} />
              </div>
            </div>
            <div style={{ ...card, borderRadius: 24, minHeight: 170, display: 'grid', placeItems: 'center' }}>
              <Tag left={18} top={14}>
                Monochrome
              </Tag>
              <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                <Mark size={40} bg="var(--ink)" fg="#FFFDF8" />
                <Word size={44} dot={null} />
              </div>
            </div>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(300px,1fr))', gap: 18 }}>
            <div style={{ ...card, borderRadius: 24, padding: 24, display: 'flex', flexDirection: 'column', gap: 16 }}>
              <b>Construction du symbole</b>
              <div style={{ display: 'flex', gap: 26, alignItems: 'center', flexWrap: 'wrap' }}>
                <div
                  style={{
                    position: 'relative',
                    width: 150,
                    height: 150,
                    flexShrink: 0,
                    backgroundImage: 'linear-gradient(#E4DFD3 1px,transparent 1px),linear-gradient(90deg,#E4DFD3 1px,transparent 1px)',
                    backgroundSize: '15px 15px',
                    border: '1px solid #E4DFD3',
                    display: 'grid',
                    placeItems: 'center',
                  }}
                >
                  <div
                    style={{
                      width: 96,
                      height: 96,
                      borderRadius: '50% 50% 50% 14%',
                      transform: 'rotate(-45deg)',
                      border: '2px dashed var(--green)',
                      display: 'grid',
                      placeItems: 'center',
                    }}
                  >
                    <span
                      style={{
                        transform: 'rotate(45deg)',
                        fontFamily: D,
                        fontWeight: 800,
                        fontSize: 60,
                        lineHeight: 1,
                        color: 'var(--green)',
                        marginTop: -6,
                        opacity: 0.5,
                      }}
                    >
                      t
                    </span>
                  </div>
                </div>
                <div style={{ fontSize: 14, color: '#4A514C', lineHeight: 1.6, flex: 1, minWidth: 200 }}>
                  Un carré aux trois coins arrondis, pivoté à 45°, qui forme <b>un repère de carte</b>. Au centre, le <b>t</b> de terricom, qui évoque aussi une
                  enseigne ou une croisée de chemins. Le symbole reprend exactement les repères de la carte interactive de la plateforme.
                </div>
              </div>
            </div>
            <div style={{ ...card, borderRadius: 24, padding: 24, display: 'flex', flexDirection: 'column', gap: 16 }}>
              <b>Zone de protection &amp; tailles minimales</b>
              <div style={{ display: 'flex', gap: 22, alignItems: 'center', flexWrap: 'wrap' }}>
                <div style={{ border: '1.5px dashed var(--danger)', padding: 22, borderRadius: 6, position: 'relative' }}>
                  <span style={{ position: 'absolute', left: 4, top: 2, fontSize: 10, fontWeight: 800, color: 'var(--danger)' }}>x</span>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                    <Mark size={28} bg="var(--green)" fg="var(--amber)" />
                    <Word size={30} />
                  </div>
                </div>
                <div style={{ fontSize: 14, color: '#4A514C', lineHeight: 1.7 }}>
                  Marge minimale = <b>x</b>, soit la hauteur du « t ».
                  <br />
                  Écran : 96 px (horizontal), 16 px (symbole).
                  <br />
                  Imprimé : 25 mm (horizontal), 6 mm (symbole).
                </div>
              </div>
            </div>
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(200px,1fr))', gap: 12 }}>
            {donts.map((d) => (
              <div key={d.l} style={{ ...card, borderRadius: 20, overflow: 'hidden' }}>
                <div style={{ height: 120, display: 'grid', placeItems: 'center', background: d.bg, position: 'relative' }}>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 6, transform: d.tf, filter: d.fl }}>
                    <div style={{ width: 24, height: 24, borderRadius: '50% 50% 50% 14%', transform: 'rotate(-45deg)', background: d.mark }} />
                    <div style={{ fontFamily: d.font, fontWeight: 800, fontSize: 22, letterSpacing: '-0.04em', color: d.fg }}>terricom</div>
                  </div>
                  <span
                    aria-hidden="true"
                    style={{
                      position: 'absolute',
                      right: 10,
                      top: 10,
                      width: 24,
                      height: 24,
                      borderRadius: '50%',
                      background: 'var(--danger)',
                      color: '#fff',
                      display: 'grid',
                      placeItems: 'center',
                      fontWeight: 800,
                      fontSize: 14,
                    }}
                  >
                    ×
                  </span>
                </div>
                <div style={{ padding: '12px 14px', fontSize: 13, fontWeight: 600 }}>{d.l}</div>
              </div>
            ))}
          </div>
        </Section>

        <Section id="couleurs" n="03" title="Couleurs">
          <div className="brand-colors" style={{ display: 'grid', gridTemplateColumns: '2fr 1.3fr 1.3fr 1fr', gap: 12, minHeight: 320 }}>
            {core.map(([n, role, hex, rgb, pms, fg, bd]) => (
              <div
                key={n}
                style={{
                  background: hex,
                  color: fg,
                  borderRadius: 24,
                  padding: 22,
                  display: 'flex',
                  flexDirection: 'column',
                  justifyContent: 'flex-end',
                  gap: 4,
                  border: `1px solid ${bd}`,
                }}
              >
                <div style={{ fontFamily: D, fontWeight: 800, fontSize: 28, letterSpacing: '-0.02em' }}>{n}</div>
                <div style={{ fontSize: 13, opacity: 0.85 }}>{role}</div>
                <div style={{ fontFamily: 'ui-monospace,monospace', fontSize: 12, marginTop: 8, lineHeight: 1.6, whiteSpace: 'nowrap' }}>
                  {hex}
                  <br />
                  RVB {rgb}
                  <br />
                  {pms}
                </div>
              </div>
            ))}
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(140px,1fr))', gap: 10 }}>
            {cats.map(([n, hex]) => (
              <div key={n} style={{ ...card, borderRadius: 18, overflow: 'hidden' }}>
                <div style={{ height: 70, background: hex }} />
                <div style={{ padding: '10px 12px' }}>
                  <div style={{ fontWeight: 700, fontSize: 14 }}>{n}</div>
                  <div style={{ fontFamily: 'ui-monospace,monospace', fontSize: 11, color: 'var(--muted)' }}>{hex}</div>
                </div>
              </div>
            ))}
          </div>
          <div style={{ fontSize: 14, color: '#4A514C', maxWidth: 760, lineHeight: 1.6 }}>
            Les couleurs secondaires ne servent qu’à distinguer les <b>catégories d’activité</b> sur la carte et dans les listes, jamais comme couleur de fond
            d’une page. Proportions conseillées : 60 % crème, 25 % encre, 10 % vert Loue, 5 % ambre.
          </div>
        </Section>

        <Section id="typo" n="04" title="Typographie">
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(340px,1fr))', gap: 18 }}>
            <div style={{ ...card, padding: 30, display: 'flex', flexDirection: 'column', gap: 14 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, color: 'var(--muted)', fontWeight: 600 }}>
                <span>Titres</span>
                <span>Google Fonts · libre</span>
              </div>
              <div style={{ fontFamily: B, fontWeight: 800, fontSize: 110, letterSpacing: '-0.05em', lineHeight: 0.85 }}>Aa</div>
              <div style={{ fontFamily: B, fontWeight: 800, fontSize: 30, letterSpacing: '-0.02em' }}>Bricolage Grotesque</div>
              <div style={{ fontFamily: B, fontSize: 18, color: '#4A514C' }}>Semibold 600 · ExtraBold 800 · interlettrage −3 % à −5 %</div>
            </div>
            <div style={{ background: 'var(--ink)', color: 'var(--cream)', borderRadius: 28, padding: 30, display: 'flex', flexDirection: 'column', gap: 14 }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: 13, color: '#8FA197', fontWeight: 600 }}>
                <span>Texte &amp; interface</span>
                <span>Google Fonts · libre</span>
              </div>
              <div style={{ fontFamily: S, fontWeight: 600, fontSize: 110, letterSpacing: '-0.03em', lineHeight: 0.85 }}>Aa</div>
              <div style={{ fontFamily: S, fontWeight: 700, fontSize: 30 }}>Instrument Sans</div>
              <div style={{ fontSize: 18, color: '#AEBDB5' }}>Regular 400 · Medium 500 · Semibold 600 · Bold 700</div>
            </div>
          </div>
          <div style={{ ...card, padding: '10px 30px' }}>
            {scale.map(([k, spec, t, f, w, px, ls]) => (
              <div
                key={k}
                className="brand-scale-row"
                style={{
                  display: 'grid',
                  gridTemplateColumns: '150px minmax(0,1fr)',
                  gap: 20,
                  alignItems: 'baseline',
                  padding: '16px 0',
                  borderBottom: '1px solid #EFEBE2',
                }}
              >
                <div style={{ fontSize: 12, color: 'var(--muted)', fontFamily: 'ui-monospace,monospace' }}>
                  {k}
                  <br />
                  {spec}
                </div>
                <div style={{ fontFamily: f, fontWeight: w, fontSize: px, letterSpacing: ls, lineHeight: 1.1, overflow: 'hidden', textOverflow: 'ellipsis' }}>
                  {t}
                </div>
              </div>
            ))}
          </div>
        </Section>

        <Section id="ton" n="05" title="Ton & écriture">
          <h2 style={{ fontFamily: D, fontWeight: 800, fontSize: 'clamp(36px,4vw,56px)', letterSpacing: '-0.035em', lineHeight: 1, margin: 0, maxWidth: 900 }}>
            Sérieux sur le fond, souriant sur la forme.
          </h2>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))', gap: 14 }}>
            {tone.map(([t, yes, no]) => (
              <div key={t} style={{ ...card, borderRadius: 22, padding: 22, display: 'flex', flexDirection: 'column', gap: 12 }}>
                <div style={{ fontFamily: D, fontWeight: 800, fontSize: 22 }}>{t}</div>
                <div style={{ display: 'flex', gap: 10, fontSize: 14, lineHeight: 1.45 }}>
                  <span style={{ color: 'var(--green)', fontWeight: 800 }}>✓</span>
                  <span>{yes}</span>
                </div>
                <div style={{ display: 'flex', gap: 10, fontSize: 14, lineHeight: 1.45, color: '#8A8F86' }}>
                  <span style={{ color: 'var(--danger)', fontWeight: 800 }}>×</span>
                  <span style={{ textDecoration: 'line-through' }}>{no}</span>
                </div>
              </div>
            ))}
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(280px,1fr))', gap: 14 }}>
            <div style={{ background: 'var(--amber)', borderRadius: 22, padding: 24, display: 'flex', flexDirection: 'column', gap: 12 }}>
              <b style={{ color: 'var(--ink)' }}>La pointe de fun : pastilles &amp; tampons</b>
              <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap', alignItems: 'center' }}>
                <span
                  style={{
                    background: 'var(--ink)',
                    color: 'var(--amber)',
                    fontFamily: D,
                    fontWeight: 800,
                    fontSize: 14,
                    padding: '7px 12px',
                    borderRadius: 10,
                    transform: 'rotate(-4deg)',
                  }}
                >
                  Fait ici
                </span>
                <span
                  style={{
                    background: '#FFFDF8',
                    color: 'var(--ink)',
                    fontFamily: D,
                    fontWeight: 800,
                    fontSize: 14,
                    padding: '7px 12px',
                    borderRadius: 999,
                    transform: 'rotate(3deg)',
                  }}
                >
                  Ouvert !
                </span>
                <span
                  style={{
                    width: 72,
                    height: 72,
                    borderRadius: '50%',
                    border: '2.5px solid var(--ink)',
                    display: 'grid',
                    placeItems: 'center',
                    textAlign: 'center',
                    fontFamily: D,
                    fontWeight: 800,
                    fontSize: 11,
                    lineHeight: 1.05,
                    color: 'var(--ink)',
                    transform: 'rotate(-10deg)',
                  }}
                >
                  VISITÉ
                  <br />✓<br />
                  ORNANS
                </span>
                <span
                  style={{
                    background: 'var(--green)',
                    color: '#fff',
                    fontFamily: D,
                    fontWeight: 800,
                    fontSize: 14,
                    padding: '7px 12px',
                    borderRadius: 10,
                    transform: 'rotate(-2deg)',
                  }}
                >
                  ★ Vitrine Or
                </span>
              </div>
              <div style={{ fontSize: 13, color: '#4A3515' }}>
                Rotation de −6° à +6°, jamais plus de deux pastilles par écran. Réservées aux moments de réussite ou de découverte.
              </div>
            </div>
            <div style={{ background: 'var(--ink)', color: 'var(--cream)', borderRadius: 22, padding: 24, display: 'flex', flexDirection: 'column', gap: 10 }}>
              <b style={{ color: 'var(--amber)' }}>Mots de la marque</b>
              <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                {words.map((w) => (
                  <span key={w} style={{ border: '1px solid #3A4C43', padding: '6px 12px', borderRadius: 999, fontSize: 14 }}>
                    {w}
                  </span>
                ))}
              </div>
              <div style={{ fontSize: 13, color: '#8FA197', marginTop: 4 }}>
                On écrit « terricom » en minuscules, y compris en début de phrase. Jamais « TerriCom » ni « Terri-com ».
              </div>
            </div>
          </div>
        </Section>

        <Section id="images" n="06" title="Photographie">
          <div className="brand-photos" style={{ display: 'grid', gridTemplateColumns: '2fr 1fr 1fr', gridTemplateRows: '220px 220px', gap: 10 }}>
            <div style={{ gridRow: 'span 2', borderRadius: 24, overflow: 'hidden', position: 'relative' }}>
              <Photo src={IMG('1509440159596-0249088772ff', 1100)} alt="" label=" " color="#C8892A" style={{ width: '100%', height: '100%' }} />
              <span
                style={{
                  position: 'absolute',
                  left: 18,
                  bottom: 18,
                  background: 'var(--amber)',
                  fontFamily: D,
                  fontWeight: 800,
                  fontSize: 14,
                  padding: '7px 12px',
                  borderRadius: 10,
                  transform: 'rotate(-4deg)',
                }}
              >
                Des mains, des gestes
              </span>
            </div>
            {['1486297678162-eb2a19b0a32d', '1493106641515-6b5631de4bb9', '1470071459604-3b5ec3a7fe05', '1533900298318-6b8da08a523e'].map((id, i) => (
              <div key={id} style={{ borderRadius: 24, overflow: 'hidden' }}>
                <Photo src={IMG(id)} alt="" label=" " color={['#3E6FB0', '#D95C4E', '#3F8F4E', '#7A5BB5'][i]} style={{ width: '100%', height: '100%' }} />
              </div>
            ))}
          </div>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(220px,1fr))', gap: 12, fontSize: 14, lineHeight: 1.5 }}>
            <div>
              <b>Vrai et local.</b> Les commerçants et artisans du territoire, jamais de photos de banque d’images génériques en production.
            </div>
            <div>
              <b>Lumière naturelle.</b> Tons chauds, légèrement désaturés, en accord avec la palette crème et ambre.
            </div>
            <div>
              <b>Des gens en action.</b> On montre le geste (pétrir, tourner, servir) plutôt que des vitrines vides.
            </div>
            <div>
              <b>Coins arrondis.</b> 16 à 24 px sur écran, jamais de cadre ni d’ombre portée lourde.
            </div>
          </div>
        </Section>

        <Section id="applications" n="07" title="Applications" style={{ paddingBottom: 90 }}>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(340px,1fr))', gap: 18, gridAutoFlow: 'dense' }}>
            <div
              style={{
                background: '#E9E4D8',
                borderRadius: 28,
                padding: 36,
                display: 'flex',
                flexDirection: 'column',
                gap: 18,
                alignItems: 'center',
                justifyContent: 'center',
                minHeight: 380,
                overflow: 'hidden',
              }}
            >
              <div
                style={{
                  width: 340,
                  maxWidth: '100%',
                  height: 200,
                  background: 'var(--ink)',
                  borderRadius: 12,
                  padding: 22,
                  display: 'flex',
                  flexDirection: 'column',
                  justifyContent: 'space-between',
                  color: 'var(--cream)',
                  boxShadow: '0 18px 36px rgba(20,32,27,.25)',
                  transform: 'rotate(-4deg)',
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <Mark size={24} bg="var(--amber)" fg="var(--ink)" />
                  <Word size={24} color="var(--cream)" />
                </div>
                <div style={{ fontFamily: D, fontWeight: 800, fontSize: 18, color: 'var(--amber)', lineHeight: 1, alignSelf: 'flex-end', textAlign: 'right' }}>
                  Le territoire,
                  <br />
                  en vitrine.
                </div>
              </div>
              <div
                style={{
                  width: 340,
                  maxWidth: '100%',
                  height: 200,
                  background: '#FFFDF8',
                  borderRadius: 12,
                  padding: 22,
                  display: 'flex',
                  flexDirection: 'column',
                  justifyContent: 'space-between',
                  boxShadow: '0 18px 36px rgba(20,32,27,.18)',
                  transform: 'rotate(3deg)',
                  marginTop: -40,
                  marginLeft: 80,
                }}
              >
                <div>
                  <div style={{ fontFamily: D, fontWeight: 800, fontSize: 20 }}>Camille Moreau</div>
                  <div style={{ fontSize: 12, color: 'var(--muted)' }}>Responsable des partenariats territoriaux</div>
                </div>
                <div style={{ fontSize: 12, lineHeight: 1.6 }}>
                  camille@terricom.fr
                  <br />
                  06 12 34 56 78
                  <br />
                  <b style={{ color: 'var(--green)' }}>terricom.fr</b>
                </div>
              </div>
              <span style={{ fontSize: 12, fontWeight: 700, color: 'var(--muted)' }}>Cartes de visite · 85 × 55 mm</span>
            </div>

            <div
              style={{
                background: 'var(--green)',
                borderRadius: 28,
                padding: 36,
                display: 'flex',
                flexDirection: 'column',
                gap: 14,
                alignItems: 'center',
                justifyContent: 'center',
                minHeight: 380,
              }}
            >
              <div
                style={{
                  width: 220,
                  height: 220,
                  borderRadius: '50%',
                  background: 'var(--amber)',
                  display: 'flex',
                  flexDirection: 'column',
                  alignItems: 'center',
                  justifyContent: 'center',
                  gap: 6,
                  boxShadow: '0 18px 36px rgba(0,0,0,.25)',
                  transform: 'rotate(-6deg)',
                  border: '6px solid #FFFDF8',
                }}
              >
                <div style={{ fontFamily: D, fontWeight: 800, fontSize: 14, color: 'var(--ink)', letterSpacing: '.06em' }}>RETROUVEZ-NOUS SUR</div>
                <div style={{ background: '#fff', borderRadius: 10, padding: 6 }}>
                  <img src={qr} alt="QR code vers terricom.fr" style={{ width: 80, height: 80, display: 'block' }} />
                </div>
                <div style={{ fontFamily: D, fontWeight: 800, fontSize: 22, color: 'var(--ink)', letterSpacing: '-0.04em' }}>terricom.</div>
              </div>
              <span style={{ fontSize: 12, fontWeight: 700, color: '#CFE3D6' }}>Autocollant vitrine · Ø 10 cm</span>
            </div>

            <div
              style={{
                ...card,
                padding: 28,
                display: 'flex',
                flexDirection: 'column',
                gap: 12,
                alignItems: 'center',
                justifyContent: 'center',
                minHeight: 380,
              }}
            >
              <div
                style={{
                  width: 300,
                  maxWidth: '100%',
                  aspectRatio: '1',
                  borderRadius: 18,
                  overflow: 'hidden',
                  position: 'relative',
                  boxShadow: '0 18px 36px rgba(20,32,27,.18)',
                }}
              >
                <Photo
                  src={IMG('1452860606245-08befc0ff44b')}
                  alt=""
                  label=" "
                  color="#1F6B52"
                  style={{ position: 'absolute', inset: 0, width: '100%', height: '100%' }}
                />
                <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,rgba(20,32,27,0) 30%,rgba(20,32,27,.9))' }} />
                <span
                  style={{
                    position: 'absolute',
                    right: 14,
                    top: 14,
                    background: 'var(--amber)',
                    fontFamily: D,
                    fontWeight: 800,
                    fontSize: 12,
                    padding: '5px 9px',
                    borderRadius: 8,
                    transform: 'rotate(5deg)',
                  }}
                >
                  Nouveau territoire !
                </span>
                <div style={{ position: 'absolute', left: 18, right: 18, bottom: 16, color: '#fff', display: 'flex', flexDirection: 'column', gap: 8 }}>
                  <div style={{ fontFamily: D, fontWeight: 800, fontSize: 28, lineHeight: 0.95, letterSpacing: '-0.03em' }}>
                    {fmtInt(pilot?.establishments ?? 0)} pros du {pilot?.territory.name ?? 'territoire'} passent en vitrine.
                  </div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
                    <Mark size={16} bg="var(--amber)" letter={false} />
                    <b style={{ fontFamily: D, fontSize: 15 }}>terricom.fr</b>
                  </div>
                </div>
              </div>
              <span style={{ fontSize: 12, fontWeight: 700, color: 'var(--muted)' }}>Post réseaux sociaux · 1080 × 1080</span>
            </div>

            <div style={{ ...card, overflow: 'hidden', display: 'flex', flexDirection: 'column', gridColumn: '1/-1', minWidth: 0 }}>
              <div style={{ display: 'flex', gap: 6, padding: '10px 14px', background: '#EDE8DC', alignItems: 'center' }}>
                <span style={{ width: 10, height: 10, borderRadius: '50%', background: 'var(--danger)' }} />
                <span style={{ width: 10, height: 10, borderRadius: '50%', background: 'var(--amber)' }} />
                <span style={{ width: 10, height: 10, borderRadius: '50%', background: '#7CD18E' }} />
                <span
                  style={{
                    marginLeft: 10,
                    fontSize: 12,
                    background: '#fff',
                    padding: '4px 10px',
                    borderRadius: 6,
                    display: 'flex',
                    gap: 6,
                    alignItems: 'center',
                  }}
                >
                  <Mark size={10} bg="var(--green)" letter={false} />
                  terricom.fr
                </span>
              </div>
              <div
                style={{
                  background: 'var(--cream)',
                  padding: '18px 28px',
                  display: 'flex',
                  alignItems: 'center',
                  gap: 24,
                  borderBottom: '1px solid #E4DFD3',
                  flexWrap: 'wrap',
                }}
              >
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <Mark size={26} bg="var(--green)" fg="var(--amber)" />
                  <Word size={24} />
                </div>
                <div style={{ display: 'flex', gap: 18, fontSize: 14, fontWeight: 600, flexWrap: 'wrap' }}>
                  <Link href="/collectivites" style={{ color: 'var(--ink)' }}>
                    Collectivités
                  </Link>
                  <Link href="/professionnels" style={{ color: 'var(--ink)' }}>
                    Professionnels
                  </Link>
                  <Link href="/territoires" style={{ color: 'var(--ink)' }}>
                    Territoires
                  </Link>
                  <Link href="/tarifs" style={{ color: 'var(--ink)' }}>
                    Tarifs
                  </Link>
                </div>
                <Link
                  href="/demo"
                  style={{ marginLeft: 'auto', background: 'var(--ink)', color: '#fff', padding: '9px 14px', borderRadius: 10, fontWeight: 700, fontSize: 13 }}
                >
                  Demander une démo
                </Link>
              </div>
              <div
                className="brand-split"
                style={{
                  padding: '44px 28px',
                  display: 'grid',
                  gridTemplateColumns: 'minmax(0,1.3fr) minmax(0,1fr)',
                  gap: 28,
                  alignItems: 'center',
                  background: 'var(--cream)',
                }}
              >
                <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                  <div style={{ fontFamily: D, fontWeight: 800, fontSize: 'clamp(34px,3.6vw,52px)', letterSpacing: '-0.04em', lineHeight: 0.92 }}>
                    Le territoire,
                    <br />
                    en <span style={{ color: 'var(--green)' }}>vitrine</span>.
                  </div>
                  <div style={{ fontSize: 16, color: '#4A514C', maxWidth: 420 }}>
                    La plateforme clé en main pour valoriser commerces, artisans et producteurs de votre commune ou intercommunalité.
                  </div>
                  <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
                    <Link
                      href="/collectivites"
                      style={{ background: 'var(--green)', color: '#fff', padding: '11px 16px', borderRadius: 10, fontWeight: 700, fontSize: 14 }}
                    >
                      Découvrir
                    </Link>
                    <a
                      href={pilot?.links.home ?? '/territoires'}
                      style={{ border: '1.5px solid var(--ink)', padding: '10px 16px', borderRadius: 10, fontWeight: 700, fontSize: 14, color: 'var(--ink)' }}
                    >
                      Voir un territoire
                    </a>
                  </div>
                </div>
                <div style={{ position: 'relative', height: 240 }}>
                  <div style={{ width: '100%', height: '100%', borderRadius: 22, overflow: 'hidden' }}>
                    <Photo src={IMG('1533900298318-6b8da08a523e', 700)} alt="" label=" " color="#1F6B52" style={{ width: '100%', height: '100%' }} />
                  </div>
                  <span
                    style={{
                      position: 'absolute',
                      left: -12,
                      top: 18,
                      background: 'var(--amber)',
                      fontFamily: D,
                      fontWeight: 800,
                      fontSize: 14,
                      padding: '7px 12px',
                      borderRadius: 10,
                      transform: 'rotate(-5deg)',
                    }}
                  >
                    {fmtInt(nums.establishments)} pros en ligne
                  </span>
                </div>
              </div>
            </div>

            <div style={{ ...card, padding: 28, display: 'flex', flexDirection: 'column', gap: 16 }}>
              <b>Signature email</b>
              <div
                style={{
                  display: 'grid',
                  gridTemplateColumns: 'auto 1fr',
                  gap: 14,
                  alignItems: 'center',
                  padding: 16,
                  background: '#fff',
                  borderRadius: 12,
                  border: '1px solid #EFEBE2',
                }}
              >
                <Mark size={44} bg="var(--green)" fg="var(--amber)" />
                <div style={{ fontSize: 13, lineHeight: 1.5 }}>
                  <b style={{ fontSize: 15 }}>Camille Moreau</b>
                  <br />
                  <span style={{ color: 'var(--muted)' }}>Partenariats territoriaux · terricom</span>
                  <br />
                  <a href="https://terricom.fr">terricom.fr</a> · 06 12 34 56 78
                </div>
              </div>
              <b>Favicon &amp; icônes</b>
              <div style={{ display: 'flex', gap: 14, alignItems: 'flex-end' }}>
                {[
                  [64, 16, 36, 'var(--ink)'],
                  [40, 10, 22, 'var(--green)'],
                  [24, 6, 13, 'var(--green)'],
                  [16, 4, 9, 'var(--green)'],
                ].map(([box, r, m, bg]) => (
                  <div
                    key={String(box)}
                    style={{
                      width: box as number,
                      height: box as number,
                      borderRadius: r as number,
                      background: bg as string,
                      display: 'grid',
                      placeItems: 'center',
                    }}
                  >
                    <Mark size={m as number} bg="var(--amber)" letter={false} />
                  </div>
                ))}
              </div>
              <div style={{ fontSize: 12, color: 'var(--muted)' }}>Sous 24 px, le « t » disparaît : on garde seulement le repère.</div>
            </div>
          </div>
        </Section>
      </main>
      <footer style={{ background: 'var(--ink)', color: '#AEBDB5' }}>
        <div style={{ maxWidth: 1280, margin: '0 auto', padding: 36, display: 'flex', gap: 20, alignItems: 'center', flexWrap: 'wrap', fontSize: 13 }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
            <Mark size={24} bg="var(--amber)" letter={false} />
            <b style={{ fontFamily: D, fontSize: 22, color: 'var(--cream)', letterSpacing: '-0.05em' }}>terricom.</b>
          </div>
          <span style={{ fontFamily: D, fontWeight: 800, fontSize: 26, color: 'var(--amber)', letterSpacing: '-0.03em' }}>terricom.fr</span>
          <span>Charte graphique v1.0</span>
          <span style={{ marginLeft: 'auto' }}>
            Contact marque :{' '}
            <a href="mailto:marque@terricom.fr" style={{ color: '#AEBDB5' }}>
              marque@terricom.fr
            </a>
          </span>
        </div>
      </footer>
      <SiteFooter />
    </>
  );
}
