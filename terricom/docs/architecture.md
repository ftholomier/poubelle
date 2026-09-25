# Architecture

## Vue d’ensemble

```
                 ┌──────────────── Ingress HTTPS (cert-manager) ────────────────┐
  habitants ───▶ │ terricom.fr · *.terricom.fr · domaines des collectivités      │
  pros, agents   └──────────────┬───────────────────────────────────────────────┘
                                ▼
                  ┌──────────────────────────┐        ┌────────────────────────┐
                  │ web (Next.js) × 3 à 12    │◀──────▶│ PostgreSQL 16 HA        │
                  │ SSR, actions serveur, API │        │ (CloudNativePG, 3 zones)│
                  └────────────┬─────────────┘        └───────────▲────────────┘
                               │ file de tâches (queue_jobs)        │
                               ▼                                    │
                  ┌──────────────────────────┐                     │
                  │ worker × 2                │─────────────────────┘
                  │ emails, newsletters, purge│──▶ SMTP · stockage objet S3 · ClamAV
                  │ publications, relances…   │──▶ API Géo / SIRENE / BAN · Claude (IA)
                  └──────────────────────────┘
```

- **Un seul code, plusieurs territoires** : chaque requête est aiguillée par `src/proxy.ts` selon l’hôte
  (`valdeloue.terricom.fr`, domaine personnalisé vérifié, ou chemin `/<territoire>` sur la plateforme).
  Les données sont cloisonnées par `territory_id` et chaque accès est contrôlé côté serveur (`src/server/authz.ts`).
- **Hiérarchie** : plateforme → territoire (EPCI, commune indépendante…) → communes (rattachement historisé)
  → entreprises (SIREN) → établissements (SIRET, fiche publique) → contenus (publications, événements,
  offres d’emploi, produits, photos).
- **Rendu** : composants serveur React ; les écrans interactifs (carte, éditeurs, assistants) sont des îlots
  client. Les pages publiques portent les métadonnées SEO (Schema.org, plan du site, URL canoniques).

## Données

Schéma Drizzle dans `src/server/db/schema/` (≈ 60 tables), migrations SQL versionnées dans `drizzle/`.

| Domaine       | Tables principales                                                                                                                                                                  |
| ------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Tenancy       | `territories`, `territory_domains`, `territory_modules`, `territory_categories`, `communes`, `commune_memberships`                                                                  |
| Utilisateurs  | `users`, `sessions`, `role_assignments`, `tokens`, `rate_limits`, `push_subscriptions`                                                                                              |
| Entreprises   | `companies`, `company_members`, `establishments` (dont `mini_site`, `theme_color`), horaires, attributs, `media`, `products`, `establishment_pages`, `establishment_forms`          |
| Revendication | `claims`, `establishment_revisions`                                                                                                                                                 |
| Contenus      | `posts`, `events`, `markets`, `points_of_interest`, `jobs`, `job_applications`, `messages` (réponses de formulaires), `appointments`                                                |
| Animation     | `campaigns`, `advent_doors`, `circuits`, `passports`, `passport_stamps`                                                                                                             |
| Newsletter    | `subscribers`, `audiences` (critères dynamiques), `newsletters` (territoire, commune ou entreprise), `newsletter_deliveries`, `company_contacts` (clients abonnés d’une entreprise) |
| Audience      | `analytics_events` (13 mois), `analytics_daily` (agrégats)                                                                                                                          |
| Facturation   | `plans`, `company_subscriptions`, `territory_contracts`, `invoices`                                                                                                                 |
| Plateforme    | `audit_log` (chaîné), `queue_jobs`, `job_schedules`, `emails`, `ai_usage`, `api_keys`, `support_tickets`, `ticket_messages`, `privacy_requests`, `health_probes`, `deals` (CRM)     |

## Traitements asynchrones

`scripts/worker.ts` exécute la file PostgreSQL (`src/server/queue.ts`) : plusieurs workers se partagent les
tâches sans doublon (`FOR UPDATE SKIP LOCKED`), avec reprise exponentielle et libération des tâches d’un
worker arrêté brutalement. Les tâches planifiées (`src/server/jobs/schedules.ts`) sont déclenchées une seule
fois par créneau grâce à une mise à jour conditionnelle de `job_schedules` ; elles se pilotent depuis la
console (« Tâches de fond ») : suspension, exécution manuelle, relance des échecs.

Tâches à la demande : envoi d’emails, préparation et envoi par lots des lettres (territoire et
entreprises), notifications push (`push.send`), géocodage et import SIRENE, diffusion sur les réseaux.

| Tâche                                                                     | Fréquence     |
| ------------------------------------------------------------------------- | ------------- |
| Sonde de disponibilité, publications programmées, newsletters programmées | chaque minute |
| Domaines personnalisés (Ingress cert-manager)                             | 5 min         |
| Statut des campagnes                                                      | 15 min        |
| Agrégats d’audience, index de recherche                                   | horaire       |
| Relances des fiches et récapitulatif des revendications                   | 9 h 30        |
| Factures échues                                                           | 7 h           |
| Purge de rétention (RGPD, sessions, journaux)                             | 3 h 15        |

## Recherche

Recherche plein texte PostgreSQL (`tsvector` pondéré, insensible aux accents, tolérance aux fautes par
trigrammes) sur les fiches, enrichie de mots-clés dérivés (catégories, synonymes, services, produits).
Une recherche en langage naturel (« où offrir local pour Noël ? ») est interprétée par un analyseur déterministe
(`src/server/ai/rules.ts`), affiné par Claude lorsque la clé API est configurée.

## Intelligence artificielle

`src/server/ai/` : rédaction des publications (variantes par canal), amélioration de texte, audit de fiche,
assistant de campagnes, recherche. Chaque appel est journalisé (`ai_usage`), décompté des quotas du
territoire, et dispose d’un **repli sans IA** : en l’absence de clé ou en cas d’erreur, un résultat
déterministe est produit et l’utilisateur n’est jamais bloqué. Aucune donnée personnelle n’est transmise.

## Fichiers et impressions

Médias : contrôle du format réel, réencodage (suppression des métadonnées EXIF, dont la géolocalisation),
déclinaisons WebP, analyse antivirus (ClamAV) des documents, stockage local ou S3. Documents privés (Kbis,
CV) servis uniquement aux personnes habilitées, consultation journalisée. PDF générés côté serveur avec les
polices de la charte : kit vitrine (affichette, autocollant, carte), courriers d’invitation, rapport de
territoire, factures, tampons des circuits.

## Emails

Transactionnels via `sendEmail` (file `email.send`, SMTP) ou boîte d’envoi consultable dans la console en
mode démonstration. Newsletters en lots avec pixel d’ouverture, liens de clic signés (HMAC),
en-têtes `List-Unsubscribe` en un clic, double consentement des abonnés.

## API publique

`/api/v1` (lecture seule, clé par territoire, 120 requêtes/min, CORS) : territoire, communes, catégories,
fiches, agenda, actualités ; description OpenAPI sur `/api/v1/openapi.json`. Détails : [api.md](api.md).

## Notifications et application installable

Notifications Web Push (clés VAPID) vers les appareils des professionnels et des agents : nouveaux messages,
réponses aux formulaires, demandes de rendez-vous, candidatures, revendications à valider. Les abonnements
sont enregistrés par appareil (`push_subscriptions`) et purgés s’ils expirent.
