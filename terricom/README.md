# terricom

**Le territoire, en vitrine.** Plateforme SaaS d’animation et de valorisation économique des territoires :
une fiche référencée pour chaque commerce, artisan et producteur, offerte par la collectivité (ou, sans
collectivité partenaire, par adhésion directe dans la vitrine nationale) ; un portail
public par territoire (carte, recherche en langage naturel, campagnes, circuits, agenda, emploi, en français,
anglais et allemand, installable sur mobile) ; un espace
entreprise pour tenir sa vitrine ; un back-office pour les intercommunalités et les communes ; une console
pour l’exploitant.

| Espace                  | Adresse                                                                      | Pour qui                                       |
| ----------------------- | ---------------------------------------------------------------------------- | ---------------------------------------------- |
| Site de la marque       | `/` · `/collectivites` · `/professionnels` · `/tarifs` · `/demo` · `/marque` | Prospects                                      |
| Portail d’un territoire | `https://<territoire>.terricom.fr`, domaine personnalisé ou `/<territoire>`  | Habitants, visiteurs                           |
| Espace entreprise       | `/pro` · adhésion directe `/pro/adhesion` · vitrine nationale `/france`      | Professionnels                                 |
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

La démonstration présente un territoire **réel** : la communauté de communes des **Lacs et Montagnes du
Haut-Doubs**, ses 32 communes et ses entreprises de la base SIRENE, importées en fiches précréées et contrôlées
une par une (`npm run demo:sirene` fige la liste, `npm run demo:controle` en donne le rapport). Rien n’y est
inventé, sauf quatre établissements **de démonstration** fictifs (commerce, boulangerie, atelier, adhérent direct) et quelques **exemples** (campagne, actualité, événement,
lettre), tous signalés comme tels.

Comptes (mot de passe `Terricom2026!`, code de double authentification : secret TOTP `JBSWY3DPEHPK3PXP`) :

| Rôle                                                    | Email                                    |
| ------------------------------------------------------- | ---------------------------------------- |
| Super administrateur                                    | camille@terricom.fr                      |
| Admin territoriale (CC Lacs et Montagnes)               | collectivite@haut-doubs.exemple.test     |
| Admin communale (Métabief)                              | mairie@metabief.exemple.test             |
| Commerce de démonstration (fictif, offre Communication) | commerce@demo-haut-doubs.exemple.test    |
| Boulangerie de démonstration (fictive, offre Essentiel) | boulangerie@demo-haut-doubs.exemple.test |
| Adhérent direct de démonstration (fictif, Pontarlier)   | adherent@demo-direct.exemple.test        |

**Tests automatiques** : ils reposent sur un jeu fictif (territoire « Val de Loue », autres clients,
prospects), chargé seulement avec `DEMO_DATASET=fictif npm run db:reset` et jamais montré en démonstration.

## Scripts

| Commande                          | Rôle                                                           |
| --------------------------------- | -------------------------------------------------------------- |
| `npm run dev` / `build` / `start` | Application Next.js (`start` : serveur autonome de production) |
| `npm run worker`                  | File de tâches et tâches planifiées                            |
| `npm run demo:sirene`             | Fige les entreprises réelles du Haut-Doubs (API SIRENE)        |
| `npm run demo:controle`           | Rapport de contrôle de l’import réel (CSV)                     |
| `npm run db:generate`             | Nouvelle migration depuis le schéma Drizzle                    |
| `npm run db:migrate`              | Applique les migrations (verrou consultatif, sûr en parallèle) |
| `npm run db:seed` / `db:reset`    | Jeu de démonstration / réinitialisation complète               |
| `npm run typecheck` · `lint`      | Contrôles statiques                                            |
| `npm run check`                   | Formate les fichiers modifiés, types, lint, tests unitaires    |
| `npm test`                        | Tests unitaires (Vitest)                                       |
| `npm run test:e2e`                | Parcours de bout en bout (Playwright, serveur lancé)           |
| `npm run dossier`                 | Dossier de réalisation en PDF (`docs/dossier`)                 |
| `npm run presentation`            | Présentation aux élus en PDF paysage (`docs/presentation`)     |
| `npm run strategie`               | Stratégie des fondateurs en PDF paysage (`docs/strategie`)     |

## Pile technique

Next.js 16 (App Router, composants serveur, actions serveur), React 19, TypeScript, PostgreSQL 16 avec
Drizzle ORM, recherche plein texte PostgreSQL, file de tâches PostgreSQL (`FOR UPDATE SKIP LOCKED`),
Leaflet et OpenStreetMap, génération PDF (pdf-lib, polices de la charte), Claude (Anthropic) pour les
assistants avec repli déterministe sans IA, stockage objet compatible S3, SMTP, Stripe pour les abonnements,
Web Push (VAPID) pour les notifications, API publique REST documentée en OpenAPI, portail multilingue
(dictionnaire typé, `Intl`, traductions de contenus par l’IA avec repli sur le français).

```
src/
  app/            pages et routes (site, [territory] portail, pro, collectivite, console, api)
  components/     interface (charte terricom) : portal, pro, bo, console, site, maps, ui
  lib/            fonctions pures partagées (formats, horaires, constantes, slugs, i18n du portail)
  server/         domaine : auth, authz (RBAC), audit, db (schéma), services, jobs, mail, print, ai
scripts/          migrations, graine, worker
drizzle/          migrations SQL versionnées
deploy/kubernetes Kustomize (base, overlays production / démo), PostgreSQL HA, cert-manager
docs/             fonctionnalités, développement, journal, API, architecture, déploiement, exploitation, sécurité, RGPD
tests/            unit (Vitest), e2e (Playwright)
```

## Documentation

- [Fonctionnalités par espace et par offre](docs/fonctionnalites.md)
- [Guide de développement : conventions, pièges connus](docs/developpement.md)
- [Journal des lots et décisions](docs/journal.md) · [TODO](TODO.md)
- [API publique v1](docs/api.md)
- [Architecture](docs/architecture.md)
- [Déploiement (Docker, Kubernetes haute disponibilité)](docs/deploiement.md)
- [Exploitation (supervision, sauvegardes, incidents)](docs/exploitation.md)
- [Sécurité](docs/securite.md)
- [RGPD et registre des traitements](docs/rgpd.md)
- [Présentation aux élus (PDF paysage)](docs/presentation/terricom-presentation-elus.pdf), aussi jouable avec
  animations dans un navigateur (`docs/presentation/presentation.html`, flèches du clavier)
- [Teaser vidéo (1 min 53)](docs/teaser/terricom-teaser.mp4) : la marque, puis l’application filmée sur le
  Haut-Doubs ; fabrication décrite dans [docs/teaser/README.md](docs/teaser/README.md)
- [Stratégie des fondateurs (PDF paysage, interne)](docs/strategie/terricom-strategie-fondateurs.pdf) : marché
  national, offre, grille tarifaire et pourquoi, vente, webmarketing, modèle économique et simulations sur cinq ans
- [Site commercial terricom.fr](../site-terricom/README.md) : site statique pour les élus, dossier `site-terricom/`
- [Fonctionnalités à développer (PDF, note de cadrage à la charte)](docs/cadrage/terricom-fonctionnalites-a-developper.pdf) :
  les 24 fonctionnalités cohérentes avec le positionnement, par thème, et la frontière à conserver (`npm run cadrage`)
- [Dossier de réalisation (PDF, 30 pages à la charte)](docs/dossier/terricom-dossier-de-realisation.pdf) : le projet
  expliqué à chaque niveau (design, ergonomie, fonctionnalités, IA, technique, hébergement, sécurité, RGPD, qualité)

## Configuration

Toutes les variables sont décrites dans [`.env.example`](.env.example) et validées au démarrage
(`src/server/env.ts`) : une configuration invalide empêche le serveur de démarrer avec un message explicite.
Sans `ANTHROPIC_API_KEY`, les assistants fonctionnent en mode règles ; sans SMTP, les emails sont conservés
dans la boîte d’envoi consultable depuis la console.
