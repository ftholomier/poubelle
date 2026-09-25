import type { ReactNode } from 'react';
import { DemoBar } from '@/components/DemoBar';
import { Photo } from '@/components/ui/Photo';
import { Logo } from '@/components/ui/Brand';

/** Mise en page des écrans d'accès (connexion, double authentification, mot de passe). */
export function AuthShell({ children, title, subtitle }: { children: ReactNode; title?: ReactNode; subtitle?: ReactNode }) {
  return (
    <>
      <DemoBar active="presentation" />
      <main className="auth-shell">
        <div className="auth-visual" aria-hidden="true">
          <Photo src="https://images.unsplash.com/photo-1517433670267-08bbd4be890f?w=1400&q=70&auto=format&fit=crop" alt="" color="#1F6B52" label=" " style={{ position: 'absolute', inset: 0, opacity: 0.55 }} />
          <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,rgba(20,32,27,.2),rgba(20,32,27,.85))' }} />
          <div style={{ position: 'absolute', left: 48, right: 48, bottom: 48, color: '#fff' }}>
            <h1 className="display" style={{ fontSize: 'clamp(38px,4.2vw,60px)', letterSpacing: '-0.035em', lineHeight: 0.95, margin: '0 0 16px' }}>
              {title ?? (
                <>
                  Le territoire,
                  <br />
                  en vitrine.
                </>
              )}
            </h1>
            <div style={{ fontSize: 15, color: '#E0E8E3', maxWidth: 440 }}>
              {subtitle ?? 'Commerçants, artisans, producteurs, agents des collectivités : un seul accès, sécurisé, pour faire vivre l’économie locale.'}
            </div>
          </div>
        </div>
        <div className="auth-panel">
          <div style={{ marginBottom: 28 }}>
            <Logo size={30} />
          </div>
          {children}
        </div>
      </main>
    </>
  );
}
