# Journal de développement

Historique des lots livrés sur la branche `claude/determined-planck-fvpvuh`, avec les décisions prises.
À compléter à chaque lot (voir aussi [`TODO.md`](../TODO.md)).

## Contexte

Commande : développer en une fois la plateforme SaaS **terricom** d’après le cahier des charges (35 sections)
et reproduire exactement la maquette et la charte fournies (écrans P1–P8 portail, E1–E7 entreprise,
C1–C8 collectivité, S1–S5 console, site de présentation, carte). Territoire pilote de démonstration :
Communauté de communes du Val de Loue (Doubs), 24 communes, environ 800 fiches.

## Lots

| Commit               | Lot                             | Points clés                                                                                                                                                                                 |
| -------------------- | ------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `87372df`            | Socle                           | Next.js 16 + Drizzle, jetons de la charte, modèle multi-territorial (≈ 60 tables), jeu de démonstration                                                                                     |
| `b57452d`            | Portail public                  | Explorateur carte + liste, fiches, communes, campagnes, circuits, agenda, emploi, SEO (JSON-LD, plan du site)                                                                               |
| `680d852`            | Espace entreprise               | Tableau de bord, éditeur de fiche, publications assistées, statistiques, kit vitrine, offres, revendication, compte                                                                         |
| `2350708`            | Back-office                     | Tableau de bord, entreprises et import, revendications, campagnes IA, newsletter, circuits, statistiques, personnalisation                                                                  |
| `52bf434`            | Console                         | Vue d’ensemble, territoires, audit, simulateur, suivi commercial, outils d’exploitation                                                                                                     |
| `98d7b17`            | Site de la marque               | Présentation, collectivités, professionnels, territoires, tarifs, démo, charte, pages légales                                                                                               |
| `4ac1c39`            | Exploitation                    | Worker, Kubernetes haute disponibilité, CI, tests, documentation                                                                                                                            |
| `519fdde`, `5d87809` | Vérification                    | Parcours complet de chaque espace (bureau et mobile), accessibilité des formulaires, plan du site, retouches mobiles                                                                        |
| `d8effd5`            | Routage multi-domaines          | Boucle de réécriture si `HOSTNAME` est une IP : écoute sur `0.0.0.0` et garde-fou au démarrage                                                                                              |
| `bedb4a9`            | Campagnes communales            | Une mairie crée ses opérations et consulte celles du territoire sans pouvoir les modifier                                                                                                   |
| `80f45ba`            | Fiche publique                  | Logo, labels, email, réseaux, tarifs, accessibilité                                                                                                                                         |
| `bf4e7c7`            | Explorateur                     | Filtres avancés (services, accessibilité, labels, paiement)                                                                                                                                 |
| `2b1518c`            | Carte                           | Calques événements, marchés, lieux économiques (nouvelle table `points_of_interest`)                                                                                                        |
| `2bcd6c8`            | Catégories et communes          | Catégories propres au territoire (`territory_categories`), rattachement et transfert de communes                                                                                            |
| `736994b`            | Newsletter et QR                | Audiences par zone et par activité, affiches et QR codes des campagnes                                                                                                                      |
| `697c71a`            | Rendez-vous et offres           | Contrôles serveur, annulation, mise en avant des offres payantes                                                                                                                            |
| `02d1a68`            | Multi-établissements            | Ajout d’un établissement de la même entreprise                                                                                                                                              |
| `8184f32`            | Offres Premium et Communication | Clients abonnés (double opt-in, lettres, export), formulaires personnalisés, pages supplémentaires, mini-site                                                                               |
| `490a82a`, `0fd587b` | Plateforme                      | Marque blanche, indicateurs d’usage et de rentabilité, API publique v1, documentation (fonctionnalités, développement, journal, API, TODO), contrôles `npm run check`                       |
| `8cbf74c`            | PWA et notifications            | Manifeste par portail, icônes générées, service worker (hors-ligne des pages publiques), notifications push (messages, formulaires, rendez-vous, candidatures, revendications)              |
| `0d5440b`            | Multilingue                     | Portail en anglais et en allemand (module `MULTILINGUAL`) : interface, formats, SEO par langue, emails visiteurs ; traductions IA des fiches et des textes, saisie manuelle                 |
| `6dd80e9`            | Synchronisation                 | Flux iCal et RSS (territoire, fiches), connecteur signé des entreprises (Premium), agendas externes iCal importés chaque heure, protection SSRF, hors-ligne vérifié en production           |
| `13b481a`            | Contrôles finaux                | Compilation de production, parcours automatique des 6 espaces (bureau et mobile, 0 signalement), 33 tests e2e et 57 tests unitaires, worker vérifié, base de démonstration réinitialisée    |
| `f626383`            | Dossier de réalisation          | PDF de 30 pages à la charte, du design à l’hébergement, captures de la compilation de production ; `npm start` sur le serveur autonome ; coques pleine hauteur corrigées hors démonstration |
| `c98afab`            | Présentation aux élus           | Diaporama paysage : communauté de communes → commune → commerce → habitants, puis accompagnement ; captures annotées, étapes animées                                                        |
| (ce lot)             | Synchronisation SIRENE          | Passage mensuel (INSEE ou Recherche d’entreprises), nouveautés et fermetures à valider par la collectivité, fichier stock pour les grands territoires, activités exclues par territoire     |

## Décisions

- **Un seul code, cloisonnement par `territory_id`** ; aiguillage par hôte dans `src/proxy.ts` (sous-domaine,
  domaine personnalisé ou chemin `/<territoire>`).
- **IA avec repli** : chaque assistant produit un résultat déterministe sans clé Claude ou en cas de refus ;
  l’utilisateur n’est jamais bloqué.
- **Droits des offres en base** (`plans.limits`), modifiables par l’exploitant, toujours vérifiés côté serveur.
- **Clients d’une entreprise** : l’entreprise est responsable du traitement ; la collectivité n’y a pas accès.
- **Mini-site** plutôt que site séparé : la fiche garde son adresse sur le portail (référencement mutualisé),
  seule sa présentation change.
- **Pages supplémentaires** en texte simplement mis en forme (pas d’HTML) : aucun risque d’injection.
- **API publique** : clé par usage et par territoire, empreinte seule en base, données publiées uniquement.
- **Notifications push** : clés VAPID en variables d’environnement ; envoi différé par la file de tâches ;
  abonnements expirés supprimés automatiquement.
- **Multilingue** : libellés dans un dictionnaire typé (pas de bibliothèque externe), même adresse de page avec
  `?lang=` plutôt qu’un préfixe `/en/` (aucune route dupliquée, compatible avec les domaines personnalisés) ;
  contenus traduits par l’IA en tâche de fond, version du professionnel prioritaire, français en repli ; pages
  légales en français uniquement (texte qui fait foi).
- **Synchronisation** : webhooks génériques signés (HMAC, comme les grands services de paiement) plutôt que
  des intégrations propriétaires une à une (Google, Meta) : l’entreprise branche l’outil de son choix ; flux
  iCal/RSS standard pour les sites ; agendas externes en iCal (format universel des offices de tourisme).
- **SIRENE** : jamais de publication ni d’archivage automatique. La synchronisation propose, un agent décide ;
  une décision (acceptée ou écartée) n’est plus reproposée. Les nouveautés sont des établissements créés depuis
  le dernier passage (marge de 90 jours pour les déclarations tardives), pour ne pas rejouer l’import initial.
  SIRENE ne donnant pas d’email, l’invitation se fait par courrier. Par défaut, SCI, holdings et administrations
  sont exclues (non pertinentes pour un annuaire de commerces).
- **Bac à sable** : les services externes y sont bloqués (cartes, photos, IA) ; ils fonctionnent en production.

## Vérifications à chaque lot

`tsc`, ESLint, Prettier, Vitest, Playwright (portail et espaces), parcours automatique des espaces concernés
(`scripts/dev/crawl.mjs`, bureau et mobile), captures d’écran comparées à la maquette.
