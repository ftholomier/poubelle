import { and, eq, isNotNull, not, like } from 'drizzle-orm';
import { db } from '../db';
import { territories, territoryDomains } from '../db/schema';
import { env } from '../env';
import { logger } from '../logger';
import { k8sNamespace, k8sRequest } from '../services/infra';

/**
 * Domaines personnalisés des portails (commerces.valdeloue.fr…) : une fois le CNAME vérifié par la
 * collectivité, le worker ajoute l'adresse à un Ingress dédié, annoté pour cert-manager, qui émet et
 * renouvelle le certificat HTTPS (défi HTTP-01). Application côté serveur (server-side apply) :
 * l'Ingress reflète exactement la liste des domaines vérifiés des territoires actifs.
 *
 *   K8S_CUSTOM_DOMAINS_INGRESS  nom de l'Ingress géré (ex. terricom-custom-domains) ; vide = désactivé
 *   K8S_WEB_SERVICE             service web ciblé (défaut terricom-web, port 80)
 *   K8S_INGRESS_CLASS           classe d'Ingress (défaut nginx)
 *   K8S_CLUSTER_ISSUER          émetteur cert-manager (défaut letsencrypt-http01)
 */
export async function verifiedCustomHosts(): Promise<string[]> {
  const rows = await db
    .select({ host: territoryDomains.host })
    .from(territoryDomains)
    .innerJoin(territories, eq(territories.id, territoryDomains.territoryId))
    .where(and(isNotNull(territoryDomains.verifiedAt), not(like(territoryDomains.host, `%${env.PLATFORM_DOMAIN}`)), eq(territories.status, 'ACTIVE')));
  return [...new Set(rows.map((r) => r.host.toLowerCase()))].sort();
}

export async function syncCustomDomains(): Promise<{ hosts: number; applied: boolean; reason?: string }> {
  const name = process.env.K8S_CUSTOM_DOMAINS_INGRESS;
  const ns = k8sNamespace();
  const hosts = await verifiedCustomHosts();
  if (!name || !ns) return { hosts: hosts.length, applied: false, reason: 'hors Kubernetes ou synchronisation désactivée' };
  const path = `/apis/networking.k8s.io/v1/namespaces/${ns}/ingresses/${name}`;
  if (!hosts.length) {
    const del = await k8sRequest('DELETE', path);
    return { hosts: 0, applied: del.status === 200 || del.status === 404, reason: 'aucun domaine vérifié' };
  }
  const service = process.env.K8S_WEB_SERVICE ?? 'terricom-web';
  const secretOf = (h: string) => `tls-${h.replace(/[^a-z0-9]+/g, '-')}`.slice(0, 63).replace(/-+$/, '');
  const manifest = {
    apiVersion: 'networking.k8s.io/v1',
    kind: 'Ingress',
    metadata: {
      name,
      namespace: ns,
      labels: { 'app.kubernetes.io/part-of': 'terricom', 'app.kubernetes.io/managed-by': 'terricom-worker' },
      annotations: {
        'cert-manager.io/cluster-issuer': process.env.K8S_CLUSTER_ISSUER ?? 'letsencrypt-http01',
        'nginx.ingress.kubernetes.io/proxy-body-size': '16m',
      },
    },
    spec: {
      ingressClassName: process.env.K8S_INGRESS_CLASS ?? 'nginx',
      tls: hosts.map((h) => ({ hosts: [h], secretName: secretOf(h) })),
      rules: hosts.map((h) => ({
        host: h,
        http: { paths: [{ path: '/', pathType: 'Prefix', backend: { service: { name: service, port: { number: 80 } } } }] },
      })),
    },
  };
  const res = await k8sRequest('PATCH', `${path}?fieldManager=terricom-worker&force=true`, manifest, 'application/apply-patch+yaml');
  if (res.status !== 200 && res.status !== 201) {
    logger.error('domains.sync_failed', { status: res.status, hosts: hosts.length });
    throw new Error(`Synchronisation des domaines : API Kubernetes ${res.status}`);
  }
  return { hosts: hosts.length, applied: true };
}
