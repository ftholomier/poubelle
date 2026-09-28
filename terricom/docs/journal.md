# Journal de développement

Historique des lots livrés sur la branche `claude/determined-planck-fvpvuh`, avec les décisions prises.
À compléter à chaque lot (voir aussi [`TODO.md`](../TODO.md)).

## Contexte

Commande : développer en une fois la plateforme SaaS **terricom** d’après le cahier des charges (35 sections)
et reproduire exactement la maquette et la charte fournies (écrans P1–P8 portail, E1–E7 entreprise,
C1–C8 collectivité, S1–S5 console, site de présentation, carte). Territoire pilote de démonstration :
Communauté de communes du Val de Loue (Doubs), 24 communes, environ 800 fiches.

## Lots

| Commit               | Lot                                | Points clés                                                                                                                                                                                                                                                                                                                                                                          |
| -------------------- | ---------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `87372df`            | Socle                              | Next.js 16 + Drizzle, jetons de la charte, modèle multi-territorial (≈ 60 tables), jeu de démonstration                                                                                                                                                                                                                                                                              |
| `b57452d`            | Portail public                     | Explorateur carte + liste, fiches, communes, campagnes, circuits, agenda, emploi, SEO (JSON-LD, plan du site)                                                                                                                                                                                                                                                                        |
| `680d852`            | Espace entreprise                  | Tableau de bord, éditeur de fiche, publications assistées, statistiques, kit vitrine, offres, revendication, compte                                                                                                                                                                                                                                                                  |
| `2350708`            | Back-office                        | Tableau de bord, entreprises et import, revendications, campagnes IA, newsletter, circuits, statistiques, personnalisation                                                                                                                                                                                                                                                           |
| `52bf434`            | Console                            | Vue d’ensemble, territoires, audit, simulateur, suivi commercial, outils d’exploitation                                                                                                                                                                                                                                                                                              |
| `98d7b17`            | Site de la marque                  | Présentation, collectivités, professionnels, territoires, tarifs, démo, charte, pages légales                                                                                                                                                                                                                                                                                        |
| `4ac1c39`            | Exploitation                       | Worker, Kubernetes haute disponibilité, CI, tests, documentation                                                                                                                                                                                                                                                                                                                     |
| `519fdde`, `5d87809` | Vérification                       | Parcours complet de chaque espace (bureau et mobile), accessibilité des formulaires, plan du site, retouches mobiles                                                                                                                                                                                                                                                                 |
| `d8effd5`            | Routage multi-domaines             | Boucle de réécriture si `HOSTNAME` est une IP : écoute sur `0.0.0.0` et garde-fou au démarrage                                                                                                                                                                                                                                                                                       |
| `bedb4a9`            | Campagnes communales               | Une mairie crée ses opérations et consulte celles du territoire sans pouvoir les modifier                                                                                                                                                                                                                                                                                            |
| `80f45ba`            | Fiche publique                     | Logo, labels, email, réseaux, tarifs, accessibilité                                                                                                                                                                                                                                                                                                                                  |
| `bf4e7c7`            | Explorateur                        | Filtres avancés (services, accessibilité, labels, paiement)                                                                                                                                                                                                                                                                                                                          |
| `2b1518c`            | Carte                              | Calques événements, marchés, lieux économiques (nouvelle table `points_of_interest`)                                                                                                                                                                                                                                                                                                 |
| `2bcd6c8`            | Catégories et communes             | Catégories propres au territoire (`territory_categories`), rattachement et transfert de communes                                                                                                                                                                                                                                                                                     |
| `736994b`            | Newsletter et QR                   | Audiences par zone et par activité, affiches et QR codes des campagnes                                                                                                                                                                                                                                                                                                               |
| `697c71a`            | Rendez-vous et offres              | Contrôles serveur, annulation, mise en avant des offres payantes                                                                                                                                                                                                                                                                                                                     |
| `02d1a68`            | Multi-établissements               | Ajout d’un établissement de la même entreprise                                                                                                                                                                                                                                                                                                                                       |
| `8184f32`            | Offres Premium et Communication    | Clients abonnés (double opt-in, lettres, export), formulaires personnalisés, pages supplémentaires, mini-site                                                                                                                                                                                                                                                                        |
| `490a82a`, `0fd587b` | Plateforme                         | Marque blanche, indicateurs d’usage et de rentabilité, API publique v1, documentation (fonctionnalités, développement, journal, API, TODO), contrôles `npm run check`                                                                                                                                                                                                                |
| `8cbf74c`            | PWA et notifications               | Manifeste par portail, icônes générées, service worker (hors-ligne des pages publiques), notifications push (messages, formulaires, rendez-vous, candidatures, revendications)                                                                                                                                                                                                       |
| `0d5440b`            | Multilingue                        | Portail en anglais et en allemand (module `MULTILINGUAL`) : interface, formats, SEO par langue, emails visiteurs ; traductions IA des fiches et des textes, saisie manuelle                                                                                                                                                                                                          |
| `6dd80e9`            | Synchronisation                    | Flux iCal et RSS (territoire, fiches), connecteur signé des entreprises (Premium), agendas externes iCal importés chaque heure, protection SSRF, hors-ligne vérifié en production                                                                                                                                                                                                    |
| `13b481a`            | Contrôles finaux                   | Compilation de production, parcours automatique des 6 espaces (bureau et mobile, 0 signalement), 33 tests e2e et 57 tests unitaires, worker vérifié, base de démonstration réinitialisée                                                                                                                                                                                             |
| `f626383`            | Dossier de réalisation             | PDF de 30 pages à la charte, du design à l’hébergement, captures de la compilation de production ; `npm start` sur le serveur autonome ; coques pleine hauteur corrigées hors démonstration                                                                                                                                                                                          |
| `c98afab`            | Présentation aux élus              | Diaporama paysage : communauté de communes → commune → commerce → habitants, puis accompagnement ; captures annotées, étapes animées                                                                                                                                                                                                                                                 |
| `55009de`            | Synchronisation SIRENE             | Passage mensuel (INSEE ou Recherche d’entreprises), nouveautés et fermetures à valider par la collectivité, fichier stock pour les grands territoires, activités exclues par territoire                                                                                                                                                                                              |
| —                    | Stratégie des fondateurs           | PDF paysage interne (45 pages) : marché national, offre, grille tarifaire unique par population, vente, webmarketing, modèle sur cinq ans, trois scénarios                                                                                                                                                                                                                           |
| `b0fc15c`            | Teaser « The Mountain »            | Marque révélée à 36 s, plan topographique réel, captures animées image par image avec curseur, fond de carte Overture rendu pour l’occasion, photos Commons ; accord « 1 artisan » corrigé dans l’assistant de campagne                                                                                                                                                              |
| `8d6542c`            | Site commercial terricom.fr        | Site statique autonome (`site-terricom/`) destiné aux élus : 15 pages, captures réelles du Haut-Doubs, film, formulaires par courriel ; aucun tarif public (vente en direct)                                                                                                                                                                                                         |
| `cb19206`            | Site commercial, version nationale | Message national (le Haut-Doubs devient le territoire de démonstration signalé), photos de toute la France, l’application en action en boucles vidéo (scènes filmées pour le site), démonstration par onglets, film intégré                                                                                                                                                          |
| —                    | Formulaires du site commercial     | `POST /api/site/demonstration` et `/api/site/contact` : affaire dans le suivi commercial (sans doublon), courriels à l’équipe et à l’expéditeur, CORS limité à `SITE_ORIGINS`, messagerie en secours côté site                                                                                                                                                                       |
| —                    | Adhésion directe des entreprises   | Une entreprise dont la collectivité n’est pas cliente adhère seule (`/pro/adhesion`, 29 ou 49 € HT par mois, annuel à dix mois) ; fiche publiée dans la vitrine nationale `terricom.fr/france` ; affaire de sa CC alimentée dans le suivi commercial ; console « Adhésions directes » ; bascule automatique vers le portail et l’option équivalente quand la commune ou la CC adhère |
| —                    | Film de présentation aux élus      | 1 min 54 sur « The Mountain », sans logo au début ni tarif : l’enjeu pour la CC (contour officiel, 32 communes, 1 971 entreprises), la plateforme, habitants, collectivité, communes, entreprises, ce que chacun y gagne ; logo et terricom.fr à la fin (`docs/teaser/terricom-elus.mp4`)                                                                                            |
| —                    | Fiche « Appeler un élu »           | PDF A4 de 4 pages à la charte (`docs/prospection/terricom-appel-elu.pdf`, `npm run appel-elu`) : script d’appel pour obtenir un rendez-vous, dix phrases clés, réponses aux objections, courriel de confirmation et suivi ; aucun tarif                                                                                                                                              |

## Décisions

- **Adhésion directe** : hors territoire partenaire, pas de fiche gratuite (« une entreprise en direct doit
  forcément payer ») : Adhésion 29 € HT (fiche + Premium) ou Adhésion Communication 49 € HT par mois. Les fiches
  sont hébergées par un territoire national `france` (`settings.national`), ce qui garde `territory_id`
  obligatoire. Quand la collectivité adhère, la bascule est automatique : fiche déplacée (avec publications,
  événements, offres d’emploi), offerte, abonnement au prix de l’option équivalente sans prorata
  (Adhésion → Premium 24 €, Communication inchangée), courriel à l’entreprise, entrée d’audit.

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
- **Exclure seulement ce qui est certain** : le code NAF seul trompe (commerces déclarés « siège social »,
  artisans à enseigne en « location de logement », École du Ski Français en association, station de ski
  gérée par un syndicat mixte). La forme juridique tranche : SCI, sociétés civiles, indivisions, copropriétés,
  particuliers sans enseigne et services publics non touristiques sont exclus ; sociétés commerciales,
  entrepreneurs à enseigne et associations au nom d’activité ouverte au public vont « à vérifier » dans la file
  SIRENE, où un agent décide. Sans forme juridique (CSV), le code NAF suffit, comme avant.
- **Territoire réel de démonstration** : entreprises réelles uniquement en fiches précréées, avec les seules
  données publiques SIRENE (nom, activité, adresse, position) ; aucun contenu inventé (description, horaires,
  avis) n’est attribué à une vraie entreprise. La liste est figée dans le dépôt pour une démo sans réseau.
- **Grille nationale unique** (document stratégie) : licence annuelle tout compris par tranche de population de
  la CC (4 900 à 16 900 € HT), publique et non négociée ; mise en service 1 900 € + 50 € par commune, offerte en
  2027 ; trois ans toujours sous le seuil de 60 000 € HT (achat sur devis). Le Haut-Doubs est à 7 900 € HT par an.
  Hypothèses et simulations dans `docs/strategie/modele.py`, document produit par `docs/strategie/generer.py`.
- **Teaser** : aucune maquette, l’application réelle est filmée image par image (horloge Playwright et animations
  CSS pilotées) ; les tuiles de carte ne sont rendues que pour les vues réellement filmées (relevé des tuiles
  manquantes pendant une première passe) ; photos Commons substituées aux photos Unsplash uniquement pendant la capture.
- **Bac à sable** : les services externes y sont bloqués (cartes, photos, IA) ; ils fonctionnent en production.

- **Site commercial** : dossier `site-terricom/` hors de l’application, HTML statique sans dépendance, à
  déposer sur n’importe quel hébergement. Discours tourné vers les élus (fierté du territoire, place de chaque
  commune, animation, communauté) et en opposition assumée aux places de marché locales, avec le constat de la
  Cour des comptes (septembre 2023) cité fidèlement. Aucun prix sur le site public : la proposition se fait en
  rendez-vous. Captures produites par `scripts/teaser/captures-site.mjs` sur la démo Haut-Doubs.
- **Note de cadrage des fonctionnalités** : la note des fondateurs est mise à la charte sans changer son fond
  (`docs/cadrage/generer.py`, `scripts/cadrage.mjs`, A4 portrait). La mise en forme ajoute des regroupements
  par thème, des étiquettes de statut et les huit verbes du périmètre. Le script refuse de produire le PDF si
  une page déborde.
- **Deux modèles de prix** : le document des fondateurs présente désormais les deux options, à arbitrer.
  - Modèle A : la grille par tranches.
  - Modèle B : l’abonnement territorial issu de la note de cadrage des fondateurs, soit 10 000 € + 0,20 € par
    habitant, plafonné à 59 000 € HT, avec 5 000 € de mise en service.

  `modele.py` simule chaque scénario avec l’un ou l’autre (`tarif='A'|'B'`). Calculées sur les populations
  réelles des 989 CC, les moyennes sont de 9 190 € (A) contre 14 307 € (B). Avec B, 86 CC dépassent le seuil
  de 60 000 € HT sur trois ans. Le fichier `prospection/intercommunalites-france.xlsx` donne le chiffre
  d’affaires du modèle B pour chaque CC.

- **Formulaires du site commercial** : pas de service tiers ; la plateforme les reçoit comme le formulaire
  `/demo` de l’application, avec la même logique (service `site-requests.ts`). Une personne qui écrit plusieurs
  fois rejoint son affaire ouverte au lieu d’en créer une nouvelle. Un message d’entreprise n’est pas un prospect :
  il est seulement transmis à l’équipe. Si la plateforme est injoignable, le site ouvre la messagerie de
  l’internaute avec la demande préremplie.
- **Site commercial, version nationale** : le discours s’adresse à toute la France. Le Haut-Doubs n’illustre
  qu’en tant que territoire de démonstration, toujours signalé comme tel. Les photos d’ambiance viennent de
  plusieurs régions (Wikimedia Commons, crédits sous chaque photo). L’application est montrée en train d’être
  utilisée : `scripts/teaser/scenes-site.mjs` filme des scènes de 7 à 13 s qui reviennent à leur point de
  départ. `site-terricom/outils/animations.py` en fait des boucles MP4 muettes, avec un fondu enchaîné au raccord
  (0,5 Mo chacune environ). Elles sont lues seulement à l’écran, avec un bouton pause, et rien ne démarre seul
  si le visiteur a demandé à réduire les animations.

- **Connexion depuis le portail** : l’en-tête du portail d’une communauté de communes ou d’une commune
  propose « Se connecter » à côté de « Vous êtes pro ? », et le pied de page « Espace élus et agents ». Les deux
  mènent à la connexion unique de la plateforme ; chacun est ensuite dirigé vers son espace (console,
  back-office, espace entreprise ou compte), directement s’il est déjà connecté. Le portail ne lit pas la
  session : posée sur le domaine de la plateforme, elle n’est pas visible sur un sous-domaine ou un domaine de
  collectivité.

## Vérifications à chaque lot

`tsc`, ESLint, Prettier, Vitest, Playwright (portail et espaces), parcours automatique des espaces concernés
(`scripts/dev/crawl.mjs`, bureau et mobile), captures d’écran comparées à la maquette.
