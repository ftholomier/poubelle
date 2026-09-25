# terricom

**Le territoire, en vitrine.** Plateforme SaaS d’animation et de valorisation économique des territoires :
une fiche référencée pour chaque commerce, artisan et producteur, offerte par la collectivité ; un portail
public par territoire (carte, recherche en langage naturel, campagnes, circuits, agenda, emploi) ; un espace
entreprise pour tenir sa vitrine ; un back-office pour les intercommunalités et les communes ; une console
pour l’exploitant.

| Espace                  | Adresse                                                                      | Pour qui                                       |
| ----------------------- | ---------------------------------------------------------------------------- | ---------------------------------------------- |
| Site de la marque       | `/` · `/collectivites` · `/professionnels` · `/tarifs` · `/demo` · `/marque` | Prospects                                      |
| Portail d’un territoire | `https://<territoire>.terricom.fr`, domaine personnalisé ou `/<territoire>`  | Habitants, visiteurs                           |
| Espace entreprise       | `/pro`                                                                       | Professionnels                                 |
| Back-office             | `/collectivite`                                                              | Admins territoriaux et communaux               |
| Console                 | `/console`                                                                   | Exploitant (super admin, support, commerciaux) |

## Démarrage rapide

### Avec Docker (tout compris)

```bash
cp .env.example .env            # ajuster SESSION_SECRET, DATA_ENCRYPTION_KEY, ANALYTICS_SALT
docker compose up -d --build     # PostgreSQL, migrations, web, worker, Mailpit
docker compose --profile demo run --rm seed   # jeu de démonstration « Val de Loue »
```

Application : http://localhost:3000 · emails envoyés : http://localhost:8025

### En développement

Prérequis : Node.js 22, PostgreSQL 16 (extensions `citext`, `pg_trgm`, `unaccent`).

```bash
npm ci
cp .env.example .env
npm run db:reset                 # base vide → migrations → jeu de démonstration (≈ 15 s)
npm run dev                      # http://localhost:3000
npm run worker                   # tâches de fond (autre terminal)
```

Avec `DEMO_MODE=true`, la barre de démonstration permet d’entrer dans chaque espace sans mot de passe.
Comptes (mot de passe `Terricom2026!`, code de double authentification : secret TOTP `JBSWY3DPEHPK3PXP`) :

| Rôle                                                           | Email                        |
| -------------------------------------------------------------- | ---------------------------- |
| Super administrateur                                           | camille@terricom.fr          |
| Admin territoriale (CC du Val de Loue)                         | c.duval@cc-valdeloue.fr      |
| Chargé de communication                                        | t.girod@cc-valdeloue.fr      |
| Admin communale (Ornans)                                       | commerce@ornans.fr           |
| Professionnelle (boulangerie)                                  | sophie@boulangerie-martin.fr |
| Caviste, offre Communication (mini-site, formulaires, clients) | julie@cave-comtoise.fr       |

## Scripts

| Commande                          | Rôle                                                           |
| --------------------------------- | -------------------------------------------------------------- |
| `npm run dev` / `build` / `start` | Application Next.js                                            |
| `npm run worker`                  | File de tâches et tâches planifiées                            |
| `npm run db:generate`             | Nouvelle migration depuis le schéma Drizzle                    |
| `npm run db:migrate`              | Applique les migrations (verrou consultatif, sûr en parallèle) |
| `npm run db:seed` / `db:reset`    | Jeu de démonstration / réinitialisation complète               |
| `npm run typecheck` · `lint`      | Contrôles statiques                                            |
| `npm test`                        | Tests unitaires (Vitest)                                       |
| `npm run test:e2e`                | Parcours de bout en bout (Playwright, serveur lancé)           |

## Pile technique

Next.js 16 (App Router, composants serveur, actions serveur), React 19, TypeScript, PostgreSQL 16 avec
Drizzle ORM, recherche plein texte PostgreSQL, file de tâches PostgreSQL (`FOR UPDATE SKIP LOCKED`),
Leaflet et OpenStreetMap, génération PDF (pdf-lib, polices de la charte), Claude (Anthropic) pour les
assistants avec repli déterministe sans IA, stockage objet compatible S3, SMTP, Stripe pour les abonnements.

```
src/
  app/            pages et routes (site, [territory] portail, pro, collectivite, console, api)
  components/     interface (charte terricom) : portal, pro, bo, console, site, maps, ui
  lib/            fonctions pures partagées (formats, horaires, constantes, slugs)
  server/         domaine : auth, authz (RBAC), audit, db (schéma), services, jobs, mail, print, ai
scripts/          migrations, graine, worker
drizzle/          migrations SQL versionnées
deploy/kubernetes Kustomize (base, overlays production / démo), PostgreSQL HA, cert-manager
docs/             architecture, déploiement, exploitation, sécurité, RGPD
tests/            unit (Vitest), e2e (Playwright)
```

## Documentation

- [Architecture](docs/architecture.md)
- [Déploiement (Docker, Kubernetes haute disponibilité)](docs/deploiement.md)
- [Exploitation (supervision, sauvegardes, incidents)](docs/exploitation.md)
- [Sécurité](docs/securite.md)
- [RGPD et registre des traitements](docs/rgpd.md)

## Configuration

Toutes les variables sont décrites dans [`.env.example`](.env.example) et validées au démarrage
(`src/server/env.ts`) : une configuration invalide empêche le serveur de démarrer avec un message explicite.
Sans `ANTHROPIC_API_KEY`, les assistants fonctionnent en mode règles ; sans SMTP, les emails sont conservés
dans la boîte d’envoi consultable depuis la console.
