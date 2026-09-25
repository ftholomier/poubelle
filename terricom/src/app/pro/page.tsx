import type { Metadata } from 'next';
import Link from 'next/link';
import { redirect } from 'next/navigation';
import { Logo } from '@/components/ui/Brand';
import { Photo } from '@/components/ui/Photo';
import { getActor } from '@/server/authz';
import { managedEstablishments } from '@/server/services/pro';
import { sized } from '@/lib/images';

export const metadata: Metadata = {
  title: 'Espace professionnel',
  description: 'Commerçants, artisans, producteurs : revendiquez gratuitement votre fiche et faites-vous connaître sur le portail de votre territoire.',
  robots: { index: true },
};

type Props = { searchParams: Promise<Record<string, string | undefined>> };

export default async function ProHome({ searchParams }: Props) {
  const sp = await searchParams;
  const actor = await getActor();
  if (actor && (!actor.user.mfaEnabled || actor.session.mfaVerified)) {
    const list = await managedEstablishments(actor.user.id);
    // Raccourcis de l'application installée (?vers=messages…) : rubrique de l'établissement unique.
    const section = ['messages', 'publications', 'statistiques', 'fiche', 'rendez-vous', 'clients'].includes(sp.vers ?? '') ? `/${sp.vers}` : '';
    if (list.length === 1 && !sp.choisir) redirect(`/pro/${list[0].id}${section}`);
    if (list.length > 1) {
      return (
        <main className="container" style={{ paddingTop: 50, paddingBottom: 70, maxWidth: 820 }}>
          <h1 className="h-page" style={{ fontSize: 42, margin: '0 0 8px' }}>
            Vos établissements
          </h1>
          <p style={{ color: 'var(--muted)', margin: '0 0 24px' }}>Choisissez l&apos;établissement à gérer.</p>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 10 }}>
            {list.map((e) => (
              <Link
                key={e.id}
                href={`/pro/${e.id}`}
                className="card card-link"
                style={{ display: 'grid', gridTemplateColumns: '56px 1fr auto', gap: 14, alignItems: 'center', padding: 12, borderRadius: 16 }}
              >
                <div style={{ width: 56, height: 56, borderRadius: 12, overflow: 'hidden' }}>
                  <Photo src={sized(e.coverUrl, 120, 120)} alt="" label={e.name} />
                </div>
                <div>
                  <div style={{ fontWeight: 700 }}>{e.name}</div>
                  <div style={{ fontSize: 13, color: 'var(--muted)' }}>{e.communeName}</div>
                </div>
                <span style={{ color: 'var(--green)', fontWeight: 700 }}>Gérer →</span>
              </Link>
            ))}
            <Link
              href="/pro/inscription"
              className="card card-link"
              style={{ padding: '14px 16px', borderRadius: 16, borderStyle: 'dashed', color: 'var(--green)', fontWeight: 700 }}
            >
              + Ajouter un établissement (autre adresse de votre entreprise)
            </Link>
          </div>
        </main>
      );
    }
  }
  return (
    <main className="auth-shell">
      <div className="auth-visual">
        <Photo
          src={sized('https://images.unsplash.com/photo-1517433670267-08bbd4be890f?w=1400', 1400)}
          alt=""
          color="#1F6B52"
          label=" "
          style={{ position: 'absolute', inset: 0, opacity: 0.55 }}
        />
        <div style={{ position: 'absolute', inset: 0, background: 'linear-gradient(180deg,rgba(20,32,27,.2),rgba(20,32,27,.85))' }} />
        <div style={{ position: 'absolute', left: 48, right: 48, bottom: 48, color: '#fff' }}>
          <h1 className="display" style={{ fontSize: 'clamp(40px,4.4vw,64px)', letterSpacing: '-0.035em', lineHeight: 0.95, margin: '0 0 16px' }}>
            Votre vitrine numérique,
            <br />
            offerte par votre territoire.
          </h1>
          <div style={{ display: 'flex', flexDirection: 'column', gap: 8, fontSize: 15, color: '#E0E8E3' }}>
            <div>✓ Gratuit, sans carte bancaire</div>
            <div>✓ Référencé sur Google, sans créer de site</div>
            <div>✓ Visible sur la carte, les circuits et la newsletter du territoire</div>
          </div>
        </div>
      </div>
      <div className="auth-panel" style={{ gap: 18 }}>
        <Logo size={30} />
        <h2 className="h-page" style={{ fontSize: 40, margin: '10px 0 0' }}>
          Espace professionnel
        </h2>
        <p style={{ margin: 0, color: 'var(--muted)', fontSize: 16, maxWidth: 480 }}>
          Votre collectivité a déjà créé votre fiche à partir des données publiques des entreprises. Revendiquez-la en 3 minutes pour ajouter vos photos, vos
          horaires, vos actualités et vos offres.
        </p>
        <div style={{ display: 'flex', gap: 10, flexWrap: 'wrap' }}>
          <Link
            href={`/pro/revendiquer${sp.territoire ? `?territoire=${encodeURIComponent(sp.territoire)}` : ''}`}
            className="btn btn-brand"
            style={{ padding: '14px 20px' }}
          >
            Revendiquer ma fiche
          </Link>
          <Link href="/connexion?next=/pro" className="btn btn-outline" style={{ padding: '14px 20px' }}>
            Se connecter
          </Link>
        </div>
        <p style={{ fontSize: 14, color: 'var(--muted)', margin: 0 }}>
          Votre activité n&apos;apparaît pas ? <Link href="/pro/inscription">Créez votre fiche</Link>. Découvrez aussi les{' '}
          <Link href="/tarifs">offres Premium</Link>.
        </p>
      </div>
    </main>
  );
}
