import type { Metadata } from 'next';
import Link from 'next/link';
import { MkCta, MkFaq, MkFeature, MkGrid, MkHero, MkSection, MkSteps } from '@/components/site/Blocks';
import { SiteShell } from '@/components/site/SiteChrome';
import { fmtEuros, fmtInt } from '@/lib/format';
import { getPlans } from '@/server/services/billing';
import { platformNumbers } from '@/server/services/marketing';

export const metadata: Metadata = {
  title: 'Pour les professionnels',
  description:
    'Commerçants, artisans, producteurs : votre fiche existe peut-être déjà sur le portail de votre territoire. Revendiquez-la gratuitement en 5 minutes et gérez votre vitrine.',
  alternates: { canonical: '/professionnels' },
};

export default async function ProfessionnelsPage() {
  const [plans, nums] = await Promise.all([getPlans(), platformNumbers()]);
  return (
    <SiteShell current="/professionnels">
      <MkHero
        eyebrow="Pour les professionnels"
        title={
          <>
            Votre vitrine sur le portail de votre territoire, <span style={{ color: 'var(--green)' }}>offerte</span>.
          </>
        }
        text="Votre collectivité a déjà créé votre fiche. Revendiquez-la en 5 minutes pour la compléter : horaires, photos, produits, actualités. Sans créer de site, sans abonnement."
        actions={
          <>
            <Link href="/pro/revendiquer" className="btn btn-brand">
              Trouver ma fiche
            </Link>
            <Link href="/connexion" className="btn btn-outline" style={{ border: '1.5px solid var(--ink)', color: 'var(--ink)' }}>
              J’ai déjà un compte
            </Link>
          </>
        }
        image="https://images.unsplash.com/photo-1509440159596-0249088772ff?w=1200&q=70&auto=format&fit=crop"
        imageColor="#C8892A"
        sticker={`${fmtInt(nums.establishments)} pros en vitrine`}
      />

      <MkSection eyebrow="Revendication" title="Cinq minutes pour prendre la main">
        <MkSteps
          steps={[
            ['Retrouvez votre fiche', 'Par nom, adresse ou SIRET : elle a été créée à partir des données publiques de l’INSEE.'],
            ['Créez votre compte', 'Email et mot de passe, double authentification proposée pour protéger votre vitrine.'],
            ['Prouvez que c’est vous', 'SIRET et nom du dirigeant, code envoyé à l’établissement ou Kbis : la collectivité vérifie.'],
            ['Complétez et publiez', 'Photos, horaires, spécialités : chaque ajout améliore votre visibilité sur Google et sur la carte.'],
          ]}
        />
      </MkSection>

      <MkSection eyebrow="Offres" title="Gratuit pour tous, plus loin si vous le souhaitez">
        <MkGrid min={260}>
          {plans.map((p) => (
            <div
              key={p.key}
              className="mk-card"
              style={{
                padding: 24,
                display: 'flex',
                flexDirection: 'column',
                gap: 10,
                background: p.key === 'PREMIUM' ? 'var(--ink)' : undefined,
                color: p.key === 'PREMIUM' ? 'var(--cream)' : undefined,
                borderColor: p.key === 'PREMIUM' ? 'var(--ink)' : undefined,
              }}
            >
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'baseline', gap: 8 }}>
                <b className="display" style={{ fontSize: 26 }}>
                  {p.name}
                </b>
                {p.key === 'ESSENTIEL' ? <span style={{ fontSize: 12, fontWeight: 800, color: 'var(--green)' }}>Offert</span> : null}
              </div>
              <div className="display" style={{ fontSize: 40, letterSpacing: '-0.03em', color: p.key === 'PREMIUM' ? 'var(--amber)' : undefined }}>
                {p.priceMonthlyCents ? fmtEuros(p.priceMonthlyCents, { decimals: p.priceMonthlyCents % 100 !== 0 }) : '0 €'}
                <span style={{ fontSize: 15, fontWeight: 600, opacity: 0.7 }}> HT / mois</span>
              </div>
              <div style={{ fontSize: 14, opacity: 0.8 }}>{p.tagline}</div>
              <ul style={{ margin: '6px 0 0', padding: 0, listStyle: 'none', display: 'flex', flexDirection: 'column', gap: 6, fontSize: 14 }}>
                {p.features.map((f) => (
                  <li key={f} style={{ display: 'flex', gap: 8 }}>
                    <span style={{ color: p.key === 'PREMIUM' ? 'var(--amber)' : 'var(--green)', fontWeight: 800 }}>✓</span>
                    {f}
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </MkGrid>
        <p style={{ fontSize: 13, color: 'var(--muted)', marginTop: 12 }}>
          Sans engagement : les options se souscrivent et se résilient depuis votre espace, au mois.
        </p>
      </MkSection>

      <MkSection eyebrow="Au quotidien" title="Ce que vous gérez depuis votre espace">
        <MkGrid min={240}>
          <MkFeature title="Votre fiche">Horaires (y compris exceptionnels), photos, produits et tarifs, moyens de paiement, accessibilité, liens.</MkFeature>
          <MkFeature title="Vos actualités">Nouveautés, promotions et événements, diffusés sur le portail, la carte et la newsletter du territoire.</MkFeature>
          <MkFeature title="Vos statistiques">Vues de la fiche, appels, itinéraires, recherches qui vous trouvent, heures d’affluence.</MkFeature>
          <MkFeature title="Votre kit vitrine">QR code, affichette et autocollant à imprimer pour relier votre boutique à votre fiche.</MkFeature>
        </MkGrid>
      </MkSection>

      <MkSection title="Questions fréquentes">
        <MkFaq
          items={[
            [
              'Pourquoi ma fiche existe-t-elle déjà ?',
              'Votre collectivité a importé les données publiques de la base SIRENE (INSEE) pour que chaque entreprise du territoire soit visible. Vous pouvez la compléter, la corriger ou demander son retrait.',
            ],
            [
              'Est-ce vraiment gratuit ?',
              'Oui : la fiche, sa mise à jour et les publications de base sont financées par votre collectivité. Seules les options Premium et Communication sont payantes, sans engagement.',
            ],
            [
              'Je ne veux pas apparaître sur le portail.',
              'Vous pouvez vous opposer à la publication de votre fiche depuis votre espace ou en écrivant à la collectivité : elle est alors retirée du portail.',
            ],
            [
              'Mon territoire n’utilise pas encore terricom.',
              'Parlez-en à votre mairie ou à votre communauté de communes : nous pouvons leur présenter la plateforme.',
            ],
            [
              'Qui voit mes données ?',
              'Les informations publiques de votre fiche sont visibles de tous. Vos coordonnées de compte, messages et statistiques restent privés, hébergés en France.',
            ],
          ]}
        />
      </MkSection>
      <MkCta
        title="Votre collectivité n’y est pas encore ?"
        text="Faites-lui découvrir terricom : nous organisons une démonstration avec les entreprises de votre commune."
      />
    </SiteShell>
  );
}
