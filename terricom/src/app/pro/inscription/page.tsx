import type { Metadata } from 'next';
import Link from 'next/link';
import { ClaimShell, ClaimTitle, SIGNUP_STEPS } from '@/components/pro/ClaimShell';
import { SignupForm, type SignupPrefill } from '@/components/pro/SignupForm';
import { fullName } from '@/lib/format';
import { getSession } from '@/server/auth/session';
import { numericCode, randomToken } from '@/server/crypto';
import { env } from '@/server/env';
import { completeSiret } from '@/server/integrations/public-data';
import { signupCategories, signupTerritories } from '@/server/services/signup';
import { resolveTerritoryParam } from '@/server/services/territories';

export const metadata: Metadata = {
  title: 'Référencer mon activité',
  description: 'Votre activité n’apparaît pas sur le portail de votre territoire ? Créez gratuitement sa fiche : votre collectivité la valide avant publication.',
};

type Props = { searchParams: Promise<Record<string, string | undefined>> };

export default async function SignupPage({ searchParams }: Props) {
  const sp = await searchParams;
  const territory = sp.territoire ? await resolveTerritoryParam(sp.territoire) : null;
  const [session, territories] = await Promise.all([getSession(), signupTerritories(territory?.id ?? null)]);
  const categories = await signupCategories(territories.map((t) => t.id));
  let prefill: SignupPrefill | null = null;
  if (env.DEMO_MODE) {
    const ornans = territories.flatMap((t) => t.communes).find((c) => c.name === 'Ornans') ?? territories[0]?.communes[0];
    const cat = categories.find((c) => /c[ée]ramique|poterie|artisan/i.test(c.name)) ?? categories[0];
    const suffix = randomToken(3).replace(/[^a-z0-9]/gi, '').toLowerCase();
    prefill = {
      siret: completeSiret(`9${numericCode(12)}`),
      name: 'Atelier Terre & Loue',
      categoryId: cat?.id ?? '',
      activityLabel: 'Céramiste',
      street: '3 rue de la Froidière',
      communeId: ornans?.id ?? '',
      phone: '06 12 34 56 78',
      firstName: 'Léna',
      lastName: 'Roussel',
      email: `lena.roussel.${suffix}@exemple.fr`,
      password: 'Terricom2026!',
    };
  }
  return (
    <ClaimShell territory={territory} step={0} steps={SIGNUP_STEPS}>
      <div style={{ display: 'flex', flexDirection: 'column', gap: 16, maxWidth: 560 }}>
        <ClaimTitle>Référencez votre activité</ClaimTitle>
        <p style={{ margin: 0, color: 'var(--muted)' }}>
          Gratuit et sans engagement. {territory ? territory.name : 'Votre collectivité'} vérifie chaque nouvelle fiche avant sa publication, sous 48 h en moyenne.
        </p>
        {session ? (
          <div className="alert alert-info" role="status">
            Connecté·e en tant que {fullName(session.user)} : la fiche sera rattachée à ce compte.
          </div>
        ) : null}
        {territories.length ? (
          <SignupForm territories={territories} categories={categories} loggedIn={Boolean(session)} prefill={prefill} />
        ) : (
          <div className="alert alert-info">Aucun territoire partenaire n&apos;accepte encore les inscriptions ici.</div>
        )}
        <span style={{ fontSize: 13, color: 'var(--muted)' }}>
          Votre fiche existe peut-être déjà : <Link href={`/pro/revendiquer${territory ? `?territoire=${territory.slug}` : ''}`}>rechercher et revendiquer</Link>.
        </span>
      </div>
    </ClaimShell>
  );
}
