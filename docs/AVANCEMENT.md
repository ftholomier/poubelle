# Sochaux Rétro — avancement du développement

Fichier de reprise : à relire en premier après une pause (quota, réveil automatique).
Règle : cocher au fur et à mesure, pousser après chaque étape.

## Réveils automatiques
- Supprimés à la fin du développement (03/10) : routine horaire et réveils ponctuels.

## Données locales (non versionnées, régénérables)
- `storage/import/` : aspiration brute (HTML, REST, ordres des mosaïques, rapports).
- `storage/media/originals/` : photos originales (≈ 5 Go, 12 724 fichiers).
- Aspiration : `WP_PASSWORD=… php scripts/wp/fetch.php all` (reprenable). Journal : `storage/logs/fetch.log`.
- Reprise : `php -d memory_limit=2G scripts/wp/import.php` (rejouable ; les fiches créées
  dans le back-office, n° ≥ 1 000 000, ne sont jamais touchées), puis
  `php bin/console.php index`, `php -d memory_limit=2G bin/console.php derived`,
  `php bin/console.php search`, `php bin/console.php medias`, `php bin/console.php geo`.
- Images hors médiathèque WordPress : `php scripts/wp/media-extra.php`.
- Contrôle d'exhaustivité : `php -d memory_limit=2G scripts/wp/completeness.php --detail`
  (rapport : `storage/import/completeness.json`).

## Phase 1 — Données
- [x] Aspiration HTML des 2 941 pages, API REST, ordre des mosaïques par catégorie
- [x] Téléchargement des originaux des médias (12 735 fichiers, tous présents ; sur le serveur :
      `php scripts/wp/media-sync.php`)
- [x] Extraction → `data/` (2 940 fiches : 1 664 matchs, 1 212 personnes, 60 articles, 4 pages ;
      123 rubriques ; 12 735 médias ; 5 984 redirections 301)
- [x] Référentiels : saisons, clubs (alias), stades, compétitions
- [x] Liens joueurs ↔ matchs, statistiques dérivées
- [x] Rapport de complétude (texte, images, tableaux, vidéos) — voir « Exhaustivité » ci-dessous

## Phase 2 — Noyau et front
- [x] Noyau (routeur, stockage JSON, index, réglages chiffrés, sessions, sécurité)
- [x] Charte de la maquette : polices auto-hébergées, CSS, JS natif
- [x] En-tête (bandeau, méga-menus, recherche plein écran, taille du texte, FR/EN, burger) + pied de page
- [x] Accueil (slider « À la une » aléatoire, compteurs, palmarès, époques, ce jour-là, compte à rebours)
- [x] Mosaïques (filtres, tri, vue liste, « Afficher plus »)
- [x] Fiche match (terrain + tableau avec n° de maillot, temps forts, vidéo, galerie, face-à-face)
- [x] Fiche personne (carte à collectionner, identité complète, récits, stats, tous ses matchs)
- [x] Fiches dirigeant / personnage / article thématique / pages
- [x] Images à la volée (WebP, redimensionnement + cache, retouches non destructives)
- [x] URL option B + redirections 301 + plan du site XML + SEO + images de partage

## Phase 3 — Back-office
- [x] Connexion, 2 niveaux, invitations, premier accès, journal d'activité
- [x] Tableau de bord, qualité, journal
- [x] Matchs, personnes, articles, objets, moments (onglets, versions, restauration, statuts, planification)
- [x] Référentiels, médiathèque (recadrage, doublons, droits), rubriques & menus & ordre des mosaïques
- [x] Éditorial : accueil, bandeau, slider, 100 moments, redirections, page d'attente
- [x] Réglages (secrets chiffrés), traductions, assistant IA, sauvegardes, tâches planifiées
- [x] Éditeur WYSIWYG natif pour tous les champs de texte long

## Phase 4 — Interactif
- [x] Saisons, face-à-face, bilans compétition / stade, records
- [x] Carto (stades, origines, épopées, lieux)
- [x] Frise, maillots, quiz, album, centenaire (moments, Onze), réserves (objets)

## Phase 5 — Communauté
- [x] Contribuer, contact, messages, newsletter, partenaires
- [x] Dons Stripe + PayPal (ponctuel / mensuel, webhooks, jauge, mur, reçus désactivés par défaut)

## Phase 6 — IA et traduction
- [x] Recherche plein texte
- [x] Assistant Gemini (RAG, bulle, limites, journal RGPD) — à activer avec la clé du client
- [x] Traduction EN (Gemini + correction BO) — à lancer avec la clé du client

## Phase 7 — Finitions
- [x] Sécurité (CSP, CSRF, antispam), cookies, accessibilité
- [x] Images de partage, newsletter « Ce jour-là »
- [x] Documentation de déploiement o2switch par FTP (`docs/DEPLOIEMENT.md`)
- [x] README, guide du back-office (`docs/GUIDE-BACK-OFFICE.md`), documentation technique
      (`docs/TECHNIQUE.md`), vérifications rapides (`tests/`)
- [x] Import final après le téléchargement complet des médias, puis versionnement de `data/`
- [x] Nettoyage : compte de test, journal d'activité de test, réveils automatiques

## Reste à faire par le client (voir `docs/DEPLOIEMENT.md`)
- Installation sur o2switch, tâche cron, premier compte administrateur, invitations.
- Clé Gemini (assistant IA, traduction anglaise des fiches), comptes Stripe et PayPal.
- Relecture des mentions légales, de la politique de confidentialité et des cookies.
- Tableau de bord Qualité : incohérences des fiches d'origine (scores, dates, tableaux de
  composition copiés d'un autre match, joueurs sans fiche, rapprochements à vérifier).

## Exhaustivité (contrôle du 03/10, 02:23 UTC)
- Texte : 1 817 125 mots retrouvés sur 1 817 997 (99,95 %).
- Images : 0 manquante sur 12 190 ; vidéos : 0 manquante sur 460 ; tableaux : 0 manquant sur 2 593.
- Écarts restants, tous expliqués :
  - Contact (n° 11) : textes d'interface de l'ancien formulaire, remplacé par le nouveau.
  - Amical Sochaux – Neuchâtel Xamax 1994 (n° 962) : texte d'exemple du thème (« lorem ipsum »,
    « This is the 1st tab ») laissé dans la page d'origine.
  - Accueil (n° 3) : compteurs « 11000+ » et « 1400+ » repris dans les réglages de l'accueil.
  - Amical Valence – Sochaux 1994 (n° 860) : en-têtes d'un tableau de composition vide.
- Corrigé grâce au contrôle : colonne « Numéro » des compositions (22 tableaux), textes à côté
  d'un tableau, titres de blocs, vidéos dans le texte (dont 13 Rutube), images retouchées par
  WordPress, 11 images hors médiathèque, page « Joueurs » (liste automatique → rubrique Nos Lions).
- Statistiques : buts contre son camp séparés, cartons « J 35' R 80' » lus correctement,
  temps de jeu des remplaçants entrés puis sortis ; même règle de lecture dans le back-office.
- Liens compositions ↔ fiches : 26 161 apparitions reliées (25 475 avant), joueurs cités
  sans fiche 82 → 40 ; rapprochements automatiques listés dans Qualité pour vérification.

## Journal
- 02/10 22:26 UTC — feu vert du client, démarrage du développement.
- 03/10 01:40 UTC — back-office complet et testé de bout en bout.
- 03/10 02:40 UTC — contrôle d'exhaustivité : pertes corrigées, compositions avec n° de maillot.
- 03/10 03:15 UTC — CSP, vidéos Rutube, vignettes vidéo, statistiques des compositions,
  liens joueurs, données versionnées, guide de mise en ligne : développement terminé.

## Aide, teaser, recette et compléments (03/10, 06:31 → 10:10 UTC) — terminé
- [x] Rubrique **Aide** du back-office : 17 chapitres (pas à pas, « Comment faire pour… »),
      73 captures annotées, recherche, aide contextuelle depuis chaque écran ; guide PDF (88 p.
      avec le correcteur, les coûts de l’IA, le verrou de modification, les fiches audio, le
      Rétro-Direct, le Fil jaune, le kit souvenirs et les chiffres du FCSM) et mémo (2 p.) ; **pas de quiz ni de suivi de progression**
      (précision du client).
- [x] **Bulles « ? »** sur chaque écran, carte et champ du back-office (souris, clavier, toucher).
- [x] **Teaser** 1 min 55 en 1920×1080 calé sur la musique fournie ; v2 : aucune mention de
      l'ancien site (jamais ouvert au public), fin « 100 ans · Un siècle de Lions, réuni dans un
      seul musée » au lieu d'un rendez-vous le 20 mai 2028 (le site ouvrira avant).
- [x] **Recette** : sécurité (audit, redirections ouvertes, en-têtes, droits des rôles, API),
      anti-spam (formulaires sans JavaScript, délais, limites), accessibilité (axe-core WCAG 2.1 AA :
      0 erreur sur une soixantaine de pages du site et du back-office), mobile (0 débordement),
      SEO (3 624 pages explorées, 0 lien cassé, anciennes adresses `/?p=` et `/?s=` en 301),
      RGPD (aucun service tiers avant accord, hors fonds de carte OpenStreetMap sans cookie,
      déclarés dans la politique de confidentialité), temps de réponse.
- [x] **Logo du header** agrandi, pointe qui déborde sous la bande ; première ligne du header
      sans débordement sur les écrans de portable.
- [x] **Glisser-déposer** partout : ↑ / icône quatre flèches / ↓ sur chaque élément, trait jaune
      d'insertion, défilement automatique, clavier, saisie directe de la position ; ordre
      d'affichage par rubrique (manuel, chronologique, A–Z) appliqué aux mosaïques (décennies et
      saisons dans l'ordre repris de WordPress) ; ordre des sous-rubriques ; calendrier des
      100 moments réorganisable.
- [x] **Export PDF** (vrai document, moteur `app/Pdf`) : fiches match / personne / article / objet /
      moment, saisons, face-à-face, bilans, records ; FR et EN ; cache, limite par IP, noindex.
- [x] Slider de l'accueil : silhouette « ? » écartée du tirage ; records : affluences impossibles
      écartées et signalées dans Qualité.
- [x] Affluences corrigées (fautes de frappe reprises de l'ancien site, chiffres vérifiés sur les
      feuilles de match Wikipédia) : fiche 2112, Nancy–Sochaux du 21/08/2011, 15 126 000 → 15 126 ;
      fiche 22054, Sochaux–Rouen du 21/03/2025, 100 001 → 10 001. Plus aucune alerte « affluence ».
- [x] Infographie A4 de toutes les fonctionnalités : `docs/sochaux-retro-fonctionnalites.pdf`.
- [x] Nettoyage : compte de test supprimé (le back-office repart sur « Premier accès »), données
      d'exemple des captures retirées, journaux et caches de test vidés.

- [x] **Correcteur d'orthographe et de syntaxe** dans le back-office : bouton « Vérifier
      l'orthographe » (fiches, accueil, rubriques, outils interactifs), panneau des
      propositions (faute barrée, correction, explication ; Corriger, Annuler, Tout corriger,
      Ignorer, + Dictionnaire), passage sélectionné dans son champ, balises conservées.
      Gemini (accords, conjugaison, homophones, syntaxe) et règles du musée sans service
      extérieur (mot répété, ponctuation, ordinaux « 2e »/« 1re », « À »), essayées sur les
      2 940 fiches. Propositions contrôlées (extrait présent, chiffres et noms propres
      intacts, pas de réécriture). Dictionnaire du musée, corrections ignorées par fiche,
      réponses en cache texte par texte. Tâche de fond + Qualité › Orthographe + tableau de
      bord ; réglages (plafond quotidien, typographie) ; commande
      `php bin/console.php correcteur`. Correcteur du navigateur activé dans les champs
      rédigés (anglais pour la version EN). Aide, mémo, guide PDF et captures à jour ;
      `tests/correcteur.php`. Essayé avec un faux Gemini local (pas de clé ici).
- [x] **Coût de l'IA en temps réel** (Système › Coûts IA) : chaque réponse de Gemini est
      comptée (jetons envoyés, en cache, produits, de réflexion × tarif du modèle), avec son
      usage, son demandeur et sa fiche ; écran rafraîchi toutes les 10 s (aujourd'hui, mois,
      à rembourser, budget, derniers appels, par usage) ; barème daté modifiable (tarifs
      Google d'octobre 2026, hausses programmées des modèles 3.6 à 3.8 au 1er janvier 2027) ;
      relevé mensuel PDF avec cases de signature et détail CSV pour l'association ;
      remboursements notés, rappel au tableau de bord ; budget mensuel avec mise en pause des
      tâches automatiques (et de l'assistant si souhaité) ; coût affiché après une
      vérification d'orthographe, une traduction ou une réindexation. `tests/couts.php` ;
      essai complet dans le navigateur avec un faux Gemini (pas de clé ici).
- [x] **Verrou de modification nominatif** : une fiche ouverte par quelqu'un est signalée aux
      autres (bandeau « Marie modifie cette fiche depuis 10 h 12 », lecture seule, mention
      « ✎ Prénom » dans les listes) ; « Prendre la main » avec alerte immédiate de la personne
      évincée et trace au journal ; libération à la fermeture, après 2 minutes sans nouvelles
      ou 30 minutes d'inactivité ; enregistrements refusés côté serveur tant que le verrou est
      tenu par un autre (fiches, contenus interactifs, accueil, rubriques, album ; actions
      groupées). `tests/verrou.php` ; essai à deux navigateurs.
- [x] **Fiches audio** : bouton « Écouter (30 s) » sur chaque fiche du site (match, personne,
      article), texte lu affiché ; gratuit par défaut (résumé automatique tiré des données, voix
      du navigateur) ; dans l'éditeur, texte modifiable, rédaction par l'IA, voix IA Gemini
      enregistrée (MP3) ; Système › Fiches audio : estimation, essai sur 20 fiches, tout le
      musée en traitement groupé (API Batch, moitié prix ≈ 5 €), suivi et annulation ; mise à
      jour de nuit des voix des fiches modifiées ; coûts comptés. `tests/audio.php` ; essai
      complet avec un faux Gemini (voix, envoi de fichier, traitement groupé).
- [x] **Rétro-Direct** (Interactif › Explorer l'histoire) : un grand match rejoué en direct
      le jour de son anniversaire, à l'heure du coup d'envoi (horloge du serveur, mi-temps de
      15 minutes, prolongation, tirs au but ; score qui change à la minute des buts, buteurs,
      remplacements, cartons, photos à la mi-temps, réactions d'après-match) ; compte à rebours
      sans le score, agenda .ics, bandeau du site ; spectateurs connectés, réactions ⚽ 👏 😱,
      « J'y étais ! » ; tous les matchs rejouables (1 500 environ) à revivre en accéléré (×1,
      ×10, ×60) depuis leur fiche. Back-office : anniversaires ronds proposés et classés,
      programmation en un clic, programme, public. Gratuit (aucune IA). `tests/retro.php` ;
      essai complet (programmation, compte à rebours, direct à la 38e minute, réactions,
      replay jusqu'aux tirs au but de la finale 1988, version anglaise, accessibilité).
- [x] **Le Fil jaune** (Interactif › Jouer) : deux joueurs reliés par la chaîne la plus courte
      de coéquipiers (matchs joués ensemble, premier match de chaque lien) ; constellation de
      chaque joueur (SVG) ; records (442 joueurs dans la même famille, 6 passes au plus, 2,8
      en moyenne ; les plus connectés, les inséparables, les plus éloignés ; la famille à part
      des années 1960-1970, faute de compositions) ; défi du jour à jouer et à partager ;
      encadré sur les fiches des joueurs. Rien à saisir : tout vient des compositions.
      `tests/filjaune.php` ; essai complet (recherche, chaîne de 6 passes, constellation,
      défi joué jusqu'au bout, version anglaise, accessibilité).
- [x] **Kit souvenirs « Les Après-midi Bonal »** (Interactif › Participer) : chaque mois, un PDF
      de 4 pages A4 en gros caractères pour les anciens supporters (le grand match d'il y a N
      ans, six visages à reconnaître, le quiz des anciens, « Racontez-nous » avec QR code vers
      le formulaire de témoignage) ; match choisi automatiquement ou par les historiens (mot
      d'introduction) ; QR code en PHP pur (vérifié par décodage). **« Ils y étaient »** sur
      chaque fiche de match : « J'y étais ! », souvenirs publiés depuis les contributions (texte
      et signature relus). `tests/souvenirs.php` ; essai complet (PDF et QR code lus,
      témoignage envoyé, publié, retiré ; choix du mois ; accessibilité).
- [x] **Les chiffres du FCSM** (`/chiffres/`, Matchs › Explorer et Interactif › Explorer
      l'histoire) : 100 statistiques en 11 chapitres, calculées depuis les tableaux de carrière
      (records depuis 1929 : Courtois 254 buts, Rust 456 matchs), les matchs officiels racontés
      (séries dans les seules saisons complètes : 32 matchs sans défaite en 1987-1988),
      les temps forts (remontadas, victoires arrachées, passes décisives) et les fiches des
      personnes (âges records, tailles, origines). Garde-fous contre les données douteuses
      (compositions recopiées, tableaux de carrière recopiés, dates de naissance invraisemblables) ;
      nouvelle alerte Qualité « tableau de statistiques identique » ; cache par langue calculé
      d'avance. `tests/chiffres.php` ; parcours et accessibilité (axe) sans erreur, FR et EN.

## Points de données à revoir par les historiens (relevés pendant la recette)
- **Tableaux de statistiques recopiés** : 594 fiches de joueurs partagent l'un de 34 tableaux
  identiques (modèle de l'ancien site, par exemple « 1990-1991, 22 matchs, 4 buts » sur 137
  fiches) : ces fiches affichent les chiffres d'un autre joueur. Alerte « Tableau de
  statistiques identique » dans Qualité ; ces tableaux sont ignorés par « Les chiffres ».
- Florent Ogier : né le 30/12/1976 d'après sa fiche, il aurait eu 39 ans à son arrivée en 2016
  (année de naissance à vérifier) ; date écartée des âges records.
- Sochaux–Lens du 24/07/1980 (fiche 23126) et Sochaux–Zalgiris Vilnius du 06/07/2002 (fiche
  18075) : compositions d'une autre époque (alertes « buts » et « tableau » dans Qualité).
- 94 fiches de personnes ont « xx » comme ville de naissance.
- Comparateur de maillots : les époques n'ont pas encore de photos (Interactif › Maillots).
- 100 moments : aucun moment écrit pour l'instant (les semaines passées affichent « Bientôt »).
- Saison 2026-2027 : le match du 02/10/2026 est en tête de l'ordre manuel (place par défaut du
  plugin WordPress) : le glisser à sa place dans Rubriques & menus.
