# Déploiement

## Images

Le `Dockerfile` produit deux images non-root (uid 10001) :

| Cible   | Contenu                                                            | Commande                                                                  |
| ------- | ------------------------------------------------------------------ | ------------------------------------------------------------------------- |
| `web`   | Next.js en sortie autonome (`server.js`), polices des PDF incluses | `node server.js` (port 3000)                                              |
| `tools` | dépendances d’exécution, `src`, `scripts`, migrations              | `node --import tsx scripts/worker.ts` ; migrations : `scripts/migrate.ts` |

```bash
docker build --target web   -t ghcr.io/<organisation>/terricom-web:1.0.0 .
docker build --target tools -t ghcr.io/<organisation>/terricom-tools:1.0.0 .
```

La compilation ne lit aucun secret ni aucune base : toute la configuration est injectée à l’exécution.

Le serveur web doit écouter sur `HOSTNAME=0.0.0.0` (valeur de l’image, imposée dans les manifestes et
`docker-compose.yml`) ou sur un nom d’hôte : avec une adresse IP explicite, Next.js traiterait les réécritures
des portails servis sur leur propre domaine comme des requêtes externes (un avertissement est journalisé au
démarrage).
La CI (`.github/workflows/terricom.yml`) construit et publie les deux images sur GHCR à chaque étiquette
`terricom-vX.Y.Z` et sur `main`, avec SBOM et attestation de provenance.

## Serveur unique (préproduction, démonstration)

`docker-compose.yml` : PostgreSQL 16, migrations, web, worker, Mailpit (et ClamAV avec le profil
`antivirus`). Placer un proxy HTTPS devant le port 3000 (Caddy, Traefik, nginx). Pour les domaines
personnalisés des collectivités hors Kubernetes, utiliser l’émission de certificats à la demande en la
conditionnant à `GET /api/domains/check?domain=<hôte>` (200 si l’hôte sert un portail actif), par exemple
avec Caddy :

```
{
  on_demand_tls {
    ask http://web:3000/api/domains/check
  }
}
https:// {
  tls { on_demand }
  reverse_proxy web:3000
}
```

## Kubernetes (production, haute disponibilité)

### Prérequis du cluster

- 3 zones de disponibilité, `metrics-server` (autoscaling) ;
- `ingress-nginx`, `cert-manager` (+ webhook DNS de votre fournisseur pour le certificat générique) ;
- CloudNativePG (PostgreSQL HA) — ou une base PostgreSQL 16 managée en France ;
- External Secrets Operator relié au coffre-fort (Scaleway Secret Manager, OVHcloud KMS, Vault…) ;
- un bucket S3 pour les médias et un bucket pour les sauvegardes.

### DNS

| Enregistrement                                       | Cible                                                 |
| ---------------------------------------------------- | ----------------------------------------------------- |
| `terricom.fr`, `www.terricom.fr`                     | adresse du répartiteur de l’Ingress                   |
| `*.terricom.fr`                                      | idem (un portail par territoire)                      |
| `portails.terricom.fr`                               | idem : cible des CNAME des domaines des collectivités |
| `commerces.<collectivité>.fr` (chez la collectivité) | `CNAME portails.terricom.fr`                          |

### Secrets (`terricom-secrets`)

`DATABASE_URL`, `SESSION_SECRET`, `DATA_ENCRYPTION_KEY` (32 octets base64), `ANALYTICS_SALT`,
`S3_ENDPOINT`, `S3_ACCESS_KEY_ID`, `S3_SECRET_ACCESS_KEY`, `S3_PUBLIC_URL`, `SMTP_URL`,
`ANTHROPIC_API_KEY`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `METRICS_TOKEN`,
`SMS_WEBHOOK_URL`, `SMS_WEBHOOK_TOKEN`, `VAPID_PUBLIC_KEY` et `VAPID_PRIVATE_KEY` (notifications push, générées
une fois avec `npx web-push generate-vapid-keys` : les changer invalide les abonnements existants), et en option
`SOCIAL_WEBHOOK_URL`, `SOCIAL_WEBHOOK_SECRET`.
Les valeurs d’exemple de `.env.example` sont signalées dans la console (« Secrets en coffre-fort »).

### Mise en place

```bash
kubectl apply -f deploy/kubernetes/cert-manager/cluster-issuers.yaml
kubectl apply -f deploy/kubernetes/postgres/cluster.yaml          # si CloudNativePG
kubectl apply -k deploy/kubernetes/overlays/production
kubectl -n terricom rollout status deploy/terricom-web
```

### Ce que garantissent les manifestes

| Composant          | Disponibilité                                                                                                                                                                                            |
| ------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| web                | 3 à 12 réplicas (HPA CPU/mémoire), répartis par zone et par nœud, `maxUnavailable: 0`, PDB `minAvailable: 2`, sondes de démarrage / disponibilité (`/api/ready`) / vie (`/api/health`), arrêt progressif |
| worker             | 2 réplicas actifs, PDB, sonde de vie (port 9090), arrêt propre sur SIGTERM (tâches en cours terminées)                                                                                                   |
| PostgreSQL         | 3 instances CloudNativePG sur 3 zones, réplication synchrone, bascule automatique, WAL archivés en continu, sauvegarde de base quotidienne (rétention 30 jours), pooler PgBouncer                        |
| Sauvegarde logique | CronJob `terricom-backup` : `pg_dump` quotidien chiffré sur le stockage objet                                                                                                                            |
| Antivirus          | ClamAV × 2                                                                                                                                                                                               |
| Sécurité           | espace de noms en profil « restricted », conteneurs non-root en lecture seule, capacités retirées, NetworkPolicy par défaut en refus, comptes de service à privilèges minimaux                           |

Les migrations s’exécutent dans un conteneur d’initialisation de chaque pod web : le verrou consultatif
PostgreSQL garantit qu’une seule instance les applique. Les migrations sont additives (compatibles avec la
version précédente pendant le déploiement progressif).

### Domaines personnalisés

Quand une collectivité vérifie son domaine (Personnalisation → Adresse du portail), le worker
(tâche `domains.sync`, toutes les 5 minutes) ajoute l’hôte à l’Ingress `terricom-custom-domains` par
application côté serveur ; cert-manager émet et renouvelle le certificat (défi HTTP-01).

### Instance de démonstration

`kubectl apply -k deploy/kubernetes/overlays/demo` : espace `terricom-demo`, `DEMO_MODE=true`, barre de
démonstration et remise à zéro nocturne des données (4 h 30). Initialiser les données une première fois :

```bash
kubectl -n terricom-demo apply -f deploy/kubernetes/overlays/demo/seed-job.yaml
kubectl -n terricom-demo wait --for=condition=complete job/terricom-demo-seed --timeout=5m
```
