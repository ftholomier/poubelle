import { eq } from 'drizzle-orm';
import { NextResponse, type NextRequest } from 'next/server';
import { audit } from '@/server/audit';
import { createSession } from '@/server/auth/session';
import { db } from '@/server/db';
import { companyMembers, establishments, users } from '@/server/db/schema';
import { env } from '@/server/env';
import { publicOrigin } from '@/server/request';

const ACCOUNTS: Record<string, { email: string; path: string }> = {
  pro: { email: 'sophie@boulangerie-martin.fr', path: '/pro' },
  // Commerce abonné à l'offre Communication : mini-site, formulaires, clients abonnés.
  'pro-communication': { email: 'julie@cave-comtoise.fr', path: '/pro' },
  collectivite: { email: 'c.duval@cc-valdeloue.fr', path: '/collectivite' },
  commune: { email: 'commerce@ornans.fr', path: '/collectivite' },
  console: { email: 'camille@terricom.fr', path: '/console' },
  // Territoire aux données réelles : 32 communes et entreprises de la base SIRENE.
  'haut-doubs': { email: 'collectivite@haut-doubs.exemple.test', path: '/collectivite' },
  'haut-doubs-commune': { email: 'mairie@metabief.exemple.test', path: '/collectivite' },
};

/**
 * Mode démonstration uniquement : ouvre directement l'espace demandé avec le compte
 * de démonstration correspondant (la double authentification est considérée comme faite).
 */
export async function GET(req: NextRequest, { params }: { params: Promise<{ space: string }> }) {
  const { space } = await params;
  const origin = publicOrigin(req.headers);
  if (!env.DEMO_MODE) return new NextResponse('Introuvable', { status: 404 });
  const account = ACCOUNTS[space];
  if (!account) return NextResponse.redirect(new URL('/', origin), 302);
  const [user] = await db.select().from(users).where(eq(users.email, account.email)).limit(1);
  if (!user) return NextResponse.redirect(new URL('/connexion', origin), 302);
  await createSession(user.id, { mfaVerified: true });
  await audit({ actor: { user }, category: 'AUTH', action: 'auth.demo_login', summary: 'Connexion de démonstration', targetType: 'user', targetId: user.id });
  let path = account.path;
  let estId: string | null = null;
  if (space.startsWith('pro')) {
    const [est] = await db
      .select({ id: establishments.id })
      .from(companyMembers)
      .innerJoin(establishments, eq(establishments.companyId, companyMembers.companyId))
      .where(eq(companyMembers.userId, user.id))
      .limit(1);
    if (est) {
      estId = est.id;
      path = `/pro/${est.id}`;
    }
  }
  const target = new URL(path, origin);
  // ?vers=/chemin : écran précis ; {est} désigne la fiche du compte professionnel de démonstration.
  const next = req.nextUrl.searchParams.get('vers');
  if (next && next.startsWith('/') && !next.startsWith('//') && (estId || !next.includes('{est}'))) {
    const dest = new URL(next.replace('{est}', estId ?? ''), origin);
    target.pathname = dest.pathname;
    target.search = dest.search;
  }
  return NextResponse.redirect(target, 302);
}
