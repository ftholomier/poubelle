import type { ReactNode } from 'react';
import { Logo, TerritoryBadge } from '@/components/ui/Brand';
import { Photo } from '@/components/ui/Photo';

export const CLAIM_STEPS = ['Recherche', 'Vérification des infos', 'Compte', 'Identité', 'Validation', 'C’est parti'] as const;
export const SIGNUP_STEPS = ['Votre activité', 'Validation', 'C’est parti'] as const;
export const DIRECT_STEPS = ['Votre entreprise', 'Votre formule', 'C’est parti'] as const;

const DEFAULT_PITCH = {
  title: ['Votre vitrine numérique,', 'offerte par votre territoire.'] as [string, string],
  points: ['Gratuit, sans carte bancaire', 'Référencé sur Google, sans créer de site', 'Visible sur la carte, les circuits et la newsletter du territoire'],
};

export const DIRECT_PITCH = {
  title: ['Votre vitrine numérique,', 'sans attendre votre commune.'] as [string, string],
  points: ['Fiche publiée dans la vitrine nationale terricom', 'Sans engagement, résiliable à tout moment', 'Offerte dès que votre collectivité adhère'],
};

type TerritoryBrand = { name: string; initials: string; logoUrl: string | null; colorPrimary: string; colorAccent: string } | null;

/**
 * Mise en page du parcours de revendication (E1) : visuel aux couleurs du territoire
 * à gauche, étapes et formulaire à droite.
 */
export function ClaimShell({
  territory,
  step,
  children,
  steps = CLAIM_STEPS,
  pitch = DEFAULT_PITCH,
}: {
  territory: TerritoryBrand;
  step: number;
  children: ReactNode;
  steps?: readonly string[];
  pitch?: { title: [string, string]; points: string[] };
}) {
  return (
    <main className="auth-shell">
      <div className="auth-visual" style={{ background: territory?.colorPrimary ?? 'var(--green)' }}>
        <Photo
          src="https://images.unsplash.com/photo-1517433670267-08bbd4be890f?w=1400&q=70&auto=format&fit=crop"
          alt=""
          color={territory?.colorPrimary ?? '#1F6B52'}
          label=" "
          eager
          style={{ position: 'absolute', inset: 0, opacity: 0.55 }}
        />
        <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,rgba(20,32,27,.2),rgba(20,32,27,.85))' }} />
        <div style={{ position: 'absolute', left: 48, right: 48, bottom: 48, color: '#fff' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 24 }}>
            {territory ? (
              <>
                <TerritoryBadge initials={territory.initials} logoUrl={territory.logoUrl} bg={territory.colorAccent} fg="#14201B" />
                <b>{territory.name} · Espace pro</b>
              </>
            ) : (
              <Logo size={26} onDark />
            )}
          </div>
          <h1 className="display" style={{ fontSize: 'clamp(40px,4.4vw,64px)', letterSpacing: '-0.035em', lineHeight: 0.95, margin: '0 0 16px' }}>
            {pitch.title[0]}
            <br />
            {pitch.title[1]}
          </h1>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 15, color: '#E0E8E3' }}>
            {pitch.points.map((p) => (
              <div key={p}>✓ {p}</div>
            ))}
          </div>
        </div>
      </div>
      <div className="auth-panel" style={{ justifyContent: 'flex-start', gap: 26 }}>
        <ol aria-label="Étapes" style={{ display: 'flex', gap: 6, listStyle: 'none', margin: 0, padding: 0 }}>
          {steps.map((label, i) => (
            <li key={label} aria-current={i === step ? 'step' : undefined} style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: 6 }}>
              <div style={{ height: 5, borderRadius: 3, background: i <= step ? 'var(--green)' : 'var(--line)' }} />
              <span className="claim-step-label" style={{ fontSize: 11, fontWeight: 700, color: i <= step ? 'var(--green)' : 'var(--faint)' }}>
                {label}
              </span>
            </li>
          ))}
        </ol>
        {children}
      </div>
    </main>
  );
}

export function ClaimTitle({ children, size = 36 }: { children: ReactNode; size?: number }) {
  return (
    <h2 className="display" style={{ fontSize: size, letterSpacing: '-0.025em', margin: 0, lineHeight: 1.05 }}>
      {children}
    </h2>
  );
}
