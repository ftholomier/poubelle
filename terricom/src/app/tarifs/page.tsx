import type { Metadata } from 'next';
import Link from 'next/link';
import { MkCta, MkFaq, MkSection } from '@/components/site/Blocks';
import { SiteShell } from '@/components/site/SiteChrome';
import { MODULE_ORDER, MODULES } from '@/lib/constants';
import { fmtEuros } from '@/lib/format';
import { getPlans } from '@/server/services/billing';

export const metadata: Metadata = {
  title: 'Tarifs',
  description:
    'Licence annuelle pour les collectivités, gratuité pour les entreprises, options Premium sans engagement. Prix HT, facturation conforme au secteur public.',
  alternates: { canonical: '/tarifs' },
};

const ROWS: [string, string, string][] = [
  ['Commune indépendante', '1 200 – 2 400 € HT / an', 'Selon le nombre d’établissements'],
  ['Communauté de communes', '6 000 – 15 000 € HT / an', 'Selon le nombre de communes et d’établissements'],
  ['Agglomération, métropole, PETR', 'Sur devis', 'Déploiement par bassin de vie possible'],
  ['Mise en service', '2 000 – 8 000 € HT', 'Paramétrage, import SIRENE, formation, lancement'],
];

export default async function TarifsPage() {
  const plans = await getPlans();
  return (
    <SiteShell current="/tarifs">
      <section className="mk-wrap" style={{ paddingTop: 56 }}>
        <div className="mk-eyebrow" style={{ color: 'var(--green)', marginBottom: 8 }}>
          Tarifs
        </div>
        <h1 className="display" style={{ fontSize: 'clamp(40px,5vw,68px)', letterSpacing: '-0.04em', lineHeight: 0.95, margin: '0 0 12px', maxWidth: 980 }}>
          La collectivité finance, les professionnels en profitent gratuitement.
        </h1>
        <p style={{ margin: 0, fontSize: 18, color: '#4A514C', maxWidth: 720 }}>
          Une licence annuelle tout compris pour le territoire. Les entreprises disposent gratuitement de leur vitrine et peuvent souscrire des options, sans
          engagement.
        </p>
      </section>

      <MkSection eyebrow="Collectivités" title="Licence annuelle du territoire">
        <div className="mk-card" style={{ padding: '6px 24px' }}>
          {ROWS.map(([l, v, d]) => (
            <div
              key={l}
              style={{
                display: 'grid',
                gridTemplateColumns: 'minmax(0,1.2fr) minmax(0,1fr) minmax(0,1.4fr)',
                gap: 14,
                padding: '16px 0',
                borderBottom: '1px solid #EFEBE2',
                alignItems: 'baseline',
              }}
              className="tarif-row"
            >
              <b style={{ fontSize: 16 }}>{l}</b>
              <b className="display" style={{ fontSize: 22, color: 'var(--green)' }}>
                {v}
              </b>
              <span style={{ fontSize: 14, color: 'var(--muted)' }}>{d}</span>
            </div>
          ))}
          <div style={{ padding: '14px 0', fontSize: 14, color: '#4A514C', lineHeight: 1.6 }}>
            <b>Inclus :</b>{' '}
            {MODULE_ORDER.filter((m) => MODULES[m].tag === 'MVP')
              .map((m) => MODULES[m].label.toLowerCase())
              .join(', ')}
            , hébergement en France, sauvegardes, support et mises à jour. <b>En option :</b>{' '}
            {MODULE_ORDER.filter((m) => MODULES[m].tag === 'V2')
              .map((m) => MODULES[m].label.toLowerCase())
              .join(', ')}
            .
          </div>
        </div>
      </MkSection>

      <MkSection eyebrow="Entreprises" title="Pour les professionnels">
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(260px,1fr))', gap: 14 }}>
          {plans.map((p) => (
            <div key={p.key} className="mk-card" style={{ padding: 24, display: 'flex', flexDirection: 'column', gap: 8 }}>
              <b className="display" style={{ fontSize: 24 }}>
                {p.name}
              </b>
              <div className="display" style={{ fontSize: 36, letterSpacing: '-0.03em' }}>
                {p.priceMonthlyCents ? fmtEuros(p.priceMonthlyCents, { decimals: p.priceMonthlyCents % 100 !== 0 }) : '0 €'}
                <span style={{ fontSize: 14, fontWeight: 600, color: 'var(--muted)' }}> HT / mois</span>
              </div>
              <div style={{ fontSize: 14, color: 'var(--muted)' }}>{p.tagline}</div>
              <ul style={{ margin: '4px 0 0', padding: 0, listStyle: 'none', display: 'flex', flexDirection: 'column', gap: 5, fontSize: 14 }}>
                {p.features.map((f) => (
                  <li key={f}>
                    <span style={{ color: 'var(--green)', fontWeight: 800 }}>✓</span> {f}
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </div>
        <p style={{ fontSize: 13, color: 'var(--muted)', marginTop: 12 }}>
          Paiement par carte ou prélèvement, facture mensuelle, résiliation en un clic depuis l’espace entreprise.{' '}
          <Link href="/professionnels">En savoir plus</Link>
        </p>
      </MkSection>

      <MkSection title="Questions fréquentes">
        <MkFaq
          items={[
            [
              'Comment commander ?',
              'Sur devis, par bon de commande ou dans le cadre d’un marché. La licence est annuelle ; la mise en service est facturée une fois, au lancement.',
            ],
            [
              'Comment payer ?',
              'Par mandat administratif. Les factures sont déposées sur Chorus Pro et payables à 30 jours (délai global de paiement du secteur public).',
            ],
            [
              'Y a-t-il un engagement pluriannuel ?',
              'Non : la licence se renouvelle chaque année. En cas d’arrêt, vous récupérez l’export de vos données (fiches, abonnés consentants, statistiques).',
            ],
            [
              'Les quotas sont-ils limitants ?',
              'La licence inclut un nombre d’établissements, d’emails de newsletter et de crédits d’assistant IA adaptés à votre territoire ; ils sont ajustables en cours d’année.',
            ],
          ]}
        />
      </MkSection>
      <MkCta
        title="Un devis pour votre territoire ?"
        text="Indiquez-nous votre structure et vos communes : nous revenons vers vous sous 48 h avec une proposition."
      />
    </SiteShell>
  );
}
