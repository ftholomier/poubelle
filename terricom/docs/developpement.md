# Guide de développement

Conventions, outillage et pièges connus : à lire avant de modifier le code. Ce document sert aussi de
mémoire du projet (décisions techniques et erreurs à ne pas reproduire). L’historique des lots est dans
[journal.md](journal.md), le reste à faire dans [`TODO.md`](../TODO.md).

## Pile et versions

Next.js 16.3 (App Router, Turbopack, `src/proxy.ts` remplace le middleware), React 19.3, TypeScript 5.9,
Drizzle ORM 0.45 sur PostgreSQL 16 (`citext`, `pg_trgm`, `unaccent`), Zod 4, Leaflet, pdf-lib, sharp,
web-push, SDK Anthropic. **Next 16 diffère de Next 13–15** : consulter `node_modules/next/dist/docs/`
avant d’utiliser une API (voir `AGENTS.md`).

## Organisation du code

```
src/app/                 routes : site (/), [territory] (portail), pro, collectivite, console, api
src/components/<espace>/ composants : portal, pro, bo (back-office), console, site, maps, ui, account
src/lib/                 fonctions pures partagées client/serveur (formats, horaires, couleurs, mini-site…)
src/server/              domaine : db/schema, services, authz, audit, auth, mail, jobs, print, ai, push
scripts/                 migrate, seed (+ seed/), worker, reset ; scripts/dev/ (ignoré par git) : outils de test
drizzle/                 migrations SQL (+ meta/ : journal et instantanés)
tests/unit, tests/e2e    Vitest, Playwright
```

## Commandes utiles

| Commande                                                              | Usage                                                                             |
| --------------------------------------------------------------------- | --------------------------------------------------------------------------------- |
| `npm run dev`                                                         | Serveur de développement (port 3000)                                              |
| `npm run worker`                                                      | File de tâches : emails, newsletters, lettres clients, notifications push, purges |
| `npm run db:reset`                                                    | Base neuve + migrations + jeu Val de Loue (≈ 15 s)                                |
| `npx tsc --noEmit -p .` · `npx eslint src` · `npx prettier --check .` | Contrôles avant chaque commit                                                     |
| `npx vitest run` · `npx playwright test`                              | Tests unitaires · parcours (serveur lancé)                                        |

## Base de données

1. Modifier le schéma dans `src/server/db/schema/*.ts` (tables en `camelCase`, colonnes converties en
   `snake_case` par Drizzle).
2. `npm run db:generate`, puis **renommer** le fichier généré (`drizzle/00NN_nom_parlant.sql`) et remplacer
   le tag correspondant dans `drizzle/meta/_journal.json`.
3. Ajouter à la main les mises à jour de données nécessaires en fin de fichier (précédées de
   `--> statement-breakpoint`), par ex. les nouveaux droits des offres (`plans.limits`).
4. `npm run db:migrate`, puis compléter `scripts/seed.ts` pour que la démonstration montre la fonction.

Les droits des offres sont dans `plans.limits` (`PlanLimits`, `src/server/db/schema/billing.ts`) ; toute
nouvelle option s’ajoute aussi à la console (`src/app/console/facturation/`) et au jeu de démonstration.

## Modèles de code

- **Actions serveur** : fichiers `'use server'` qui n’exportent **que des fonctions asynchrones** (pas de
  constantes ni de types valeur). Validation Zod, contrôle d’accès en premier (`proCtx`,
  `loadBoContext`, `requirePlatformStaff`), `audit()` pour toute modification, puis `revalidatePath`.
- **Formulaires** : `ActionForm` (client, toast de succès, erreur sous le formulaire) et `SubmitButton`
  (état d’envoi, prop `disabled`). Depuis un composant serveur, les enfants d’`ActionForm` doivent être des
  éléments, **jamais une fonction** (`{(pending) => …}` n’est possible que dans un composant client).
- **Plusieurs actions dans un même formulaire** : ne pas mettre `formAction` + `name` sur un bouton à
  l’intérieur d’un `ActionForm` (erreur d’hydratation) ; utiliser des formulaires séparés reliés par
  l’attribut `form=`.
- **Espace pro** : `loadProContext(estId)` donne l’établissement, le rôle (`OWNER`, `MEMBER`, `STAFF` pour
  un agent de la collectivité), l’offre et ses `limits`, les modules du territoire.
- **Back-office** : `BoContext` (`level` TERRITORY ou COMMUNE, `communeIds`, `access` ADMIN ou EDITOR) ;
  filtres `estScope`, `campaignScope`, `canEditCampaign`, `requireTerritoryLevel`.
- **Fonctions payantes** : vérifier `limits.*` côté serveur à la fois dans l’action, dans la page publique et
  dans les requêtes annexes (plan du site, API) ; présenter la fonction verrouillée avec `LockedFeature`.
- **Cache** : `memo(key, ttl, fn)` et `invalidate(prefix)` (`src/server/cache.ts`), clés `cards:<territoire>`,
  `portal:<slug>`, `territory:<id>` ; `cache()` de React pour dédupliquer dans une requête.
- **Pureté React** : pas de `Date.now()` ni `Math.random()` au rendu (utiliser `nowMs()`), la règle ESLint le
  signale.
- **Couleurs du mini-site** : `themeStyle()` (`src/lib/minisite.ts`) redéfinit `--green`, `--brand`,
  `--mint` avec un contraste vérifié (`readableOnLight`).
- **Classes CSS** : `src/app/globals.css` est partagé par tous les espaces ; préfixer les nouvelles classes
  (`.est-…` pour le mini-site) — `.site-nav` et `.site-header` appartiennent au site de la marque.
- **Emails** : gabarits dans `src/server/mail/templates.ts` (marque du territoire via `brandOf`, qui gère la
  marque blanche) ; lettres en lots via `src/server/services/newsletters.ts`.
- **Notifications push** : `notifyCompany`, `notifyTerritoryStaff`, `notifyUsers` (`src/server/push.ts`)
  mettent en file une tâche `push.send` ; sans clés VAPID, rien n’est envoyé.

## Pièges rencontrés (à ne pas reproduire)

| Symptôme                                                                  | Cause                                                                          | Correctif                                                                                           |
| ------------------------------------------------------------------------- | ------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------- |
| Boucle de réécriture en production                                        | `HOSTNAME` défini sur une adresse IP : Next considère la requête comme externe | Écouter sur `0.0.0.0` (manifestes, compose) ; avertissement au démarrage (`src/instrumentation.ts`) |
| Erreur d’hydratation sur la page des catégories                           | `formAction` + `name` sur des boutons dans `ActionForm`                        | Formulaires externes et attribut `form=`                                                            |
| Page en erreur 500 « Functions are not valid as a child »                 | Fonction passée comme enfant d’`ActionForm` depuis un composant serveur        | Enfants simples + `SubmitButton`                                                                    |
| Menu du site de la marque modifié                                         | Classe `.site-nav` réutilisée                                                  | Préfixe `.est-nav`                                                                                  |
| Nom accessible d’un champ trop long (le lecteur d’écran lit toute l’aide) | `<small>` d’aide placé dans le `<label>`                                       | `<small>` hors du label, relié par `aria-describedby`                                               |
| Tableau des sections partagé modifié                                      | `push()` sur une constante exportée                                            | `visibleSections()` renvoie une copie                                                               |

## Application installable (PWA) et notifications

- `src/app/sw.js/route.ts` génère le service worker (même script sur chaque hôte). En développement il ne met
  rien en cache ; pour tester le hors-ligne, utiliser `npm run build && npm start`.
- Les pages privées (`/pro`, `/collectivite`, `/console`, `/compte`, `/api`…) ne sont jamais mises en cache.
- Manifeste : `src/app/manifest.webmanifest/route.ts` (portail selon l’hôte ou `?territoire=`) ; icônes :
  `src/app/api/pwa/icon/route.tsx` (`next/og`).
- Push : `src/server/push.ts` ; interface `src/components/pwa/PushSettings.tsx`. Chromium sans interface
  (tests) refuse les notifications : lancer Playwright avec `channel: 'chromium'` pour obtenir l’autorisation ;
  l’abonnement reste impossible dans un contexte privé ou sans accès au service push (bac à sable).

## Tests

- Unitaires (`tests/unit`) : formats, horaires, recherche, sécurité, couleurs et sections du mini-site,
  rendu des lettres d’entreprise, texte enrichi (pas d’interprétation HTML ni de lien `javascript:`).
- Bout en bout (`tests/e2e`) : portail (recherche, filtres, mini-site, offre Essentiel), espaces de
  démonstration (pro Essentiel et Communication, collectivité, mairie, console), site, sécurité, mobile.
- Outils de vérification dans `scripts/dev/` (non versionnés) : `crawl.mjs` parcourt un espace et signale
  erreurs, textes suspects, débordements, défauts d’accessibilité (`SPACE=anon|pro|pro-communication|
collectivite|commune|console`, `MOBILE=1`, `EXTRA=chemins`).

## Environnement de développement (bac à sable)

Les API externes (tuiles OpenStreetMap, photos Unsplash, API Géo/SIRENE, Claude) sont bloquées dans le bac
à sable de développement : les cartes et photos y apparaissent vides, les assistants passent en mode règles.
Tout fonctionne normalement en production avec l’accès réseau.
