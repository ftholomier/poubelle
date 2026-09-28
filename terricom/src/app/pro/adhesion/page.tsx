import type { Metadata } from 'next';
import Link from 'next/link';
import { ClaimShell, ClaimTitle, DIRECT_PITCH, DIRECT_STEPS } from '@/components/pro/ClaimShell';
import { DirectMemberForm, type DirectPrefill } from '@/components/pro/DirectMemberForm';
import { fmtEuros } from '@/lib/format';
import { DIRECT_MONTHLY_CENTS, MONTHS_BILLED_PER_YEAR } from '@/lib/pricing';
import { getSession } from '@/server/auth/session';
import { numericCode, randomToken } from '@/server/crypto';
import { env } from '@/server/env';
import { completeSiret } from '@/server/integrations/public-data';
import { signupCategories } from '@/server/services/signup';

export const metadata: Metadata = {
  title: 'Adhérer à terricom',
  description:
    'Votre commune n’a pas encore rejoint terricom ? Adhérez directement : votre fiche dans la vitrine nationale, les outils Premium, et une fiche offerte le jour où votre collectivité adhère.',
};

export default async function DirectMembershipPage() {
  const [session, categories] = await Promise.all([getSession(), signupCategories([])]);
  let prefill: DirectPrefill | null = null;
  if (env.DEMO_MODE) {
    const cat = categories.find((c) => /m[ée]tiers d.art|c[ée]ramique|artisan/i.test(c.name)) ?? categories[0];
    const suffix = randomToken(3)
      .replace(/[^a-z0-9]/gi, '')
      .toLowerCase();
    prefill = {
      siret: completeSiret(`8${numericCode(12)}`),
      name: 'Atelier d’exemple',
      categoryId: cat?.id ?? '',
      activityLabel: 'Céramiste (exemple)',
      street: 'Adresse d’exemple',
      postalCode: '25300',
      phone: '06 12 34 56 78',
      firstName: 'Prénom',
      lastName: 'Exemple',
      email: `adhesion.${suffix}@exemple.fr`,
      password: 'Terricom2026!',
    };
  }
  const month = (k: 'PREMIUM' | 'COMMUNICATION') => fmtEuros(DIRECT_MONTHLY_CENTS[k]);
  const year = (k: 'PREMIUM' | 'COMMUNICATION') => fmtEuros(DIRECT_MONTHLY_CENTS[k] * MONTHS_BILLED_PER_YEAR);
  return (
    <ClaimShell territory={null} step={0} steps={DIRECT_STEPS} pitch={DIRECT_PITCH}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 16, maxWidth: 620 }}>
        <ClaimTitle>Adhérez à terricom</ClaimTitle>
        <p style={{ margin: 0, color: 'var(--muted)' }}>
          Votre commune ou votre intercommunalité n’a pas encore rejoint terricom ? Adhérez directement : votre fiche complète dans la vitrine nationale
          terricom, tous les outils pour faire venir vos clients, et le jour où votre collectivité adhère, votre fiche lui est offerte et votre abonnement passe
          au tarif de l’option équivalente.
        </p>
        <p style={{ margin: 0, fontSize: 14, color: 'var(--muted)' }}>
          Votre collectivité est déjà partenaire ? Votre fiche y est gratuite : <Link href="/pro/revendiquer">retrouvez-la</Link> ou{' '}
          <Link href="/pro/inscription">référencez votre activité</Link>.
        </p>
        <DirectMemberForm
          categories={categories}
          loggedIn={Boolean(session)}
          prefill={prefill}
          prices={{
            PREMIUM: { month: month('PREMIUM'), year: year('PREMIUM') },
            COMMUNICATION: { month: month('COMMUNICATION'), year: year('COMMUNICATION') },
          }}
          billingEmail={session?.user.email ?? prefill?.email ?? ''}
        />
      </div>
    </ClaimShell>
  );
}
