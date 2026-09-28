import { and, eq, inArray, isNotNull } from 'drizzle-orm';
import { type NextRequest } from 'next/server';
import { db } from '@/server/db';
import { territories, territoryDomains } from '@/server/db/schema';
import { env, platformHosts } from '@/server/env';

/**
 * Autorisation d'émission de certificat « à la demande » (Caddy on_demand_tls, Traefik…) pour les
 * déploiements hors Kubernetes : 200 si l'hôte sert la plateforme ou un portail actif, 404 sinon.
 * Évite qu'un tiers fasse émettre des certificats pour des domaines qui ne sont pas les nôtres.
 */
export async function GET(req: NextRequest) {
  const host = (req.nextUrl.searchParams.get('domain') ?? '').trim().toLowerCase();
  if (!host || host.length > 253) return new Response('invalid', { status: 400 });
  if (platformHosts.has(host) || host === `portails.${env.PLATFORM_DOMAIN}`) return new Response('ok');
  const active = ['ACTIVE', 'ONBOARDING'] as const;
  if (host.endsWith(`.${env.PLATFORM_DOMAIN}`)) {
    const slug = host.slice(0, -env.PLATFORM_DOMAIN.length - 1);
    const [t] = await db
      .select({ id: territories.id })
      .from(territories)
      .where(and(eq(territories.slug, slug), inArray(territories.status, [...active])))
      .limit(1);
    return new Response(t ? 'ok' : 'unknown', { status: t ? 200 : 404 });
  }
  const [d] = await db
    .select({ id: territoryDomains.id })
    .from(territoryDomains)
    .innerJoin(territories, eq(territories.id, territoryDomains.territoryId))
    .where(and(eq(territoryDomains.host, host), isNotNull(territoryDomains.verifiedAt), inArray(territories.status, [...active])))
    .limit(1);
  return new Response(d ? 'ok' : 'unknown', { status: d ? 200 : 404, headers: { 'cache-control': 'no-store' } });
}
