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
- [x] **Fiches audio** : bouton « Écouter » sur chaque fiche du site (match, personne,
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
- [x] **Kit souvenirs « Raconte-moi Bonal »** (Interactif › Participer) : chaque mois, un PDF
      A4 en gros caractères pour les anciens supporters (le grand match d'il y a N
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
- [x] **Le chiffre du jour** sur l'accueil (sous « Ce jour-là ») : un des 100 chiffres, un
      nouveau chaque jour (ordre mélangé, sans répétition avant d'avoir tout montré), lien vers
      sa carte sur la page des chiffres ; l'accueil n'attend jamais le calcul (cache, recalcul
      après l'envoi de la page). Case « Afficher » et aperçu du jour dans Éditorial › Accueil &
      bandeau ; essai complet (masquer, réafficher) et accessibilité sans erreur.

## Contrôle complet (03/10, après les chiffres du FCSM) — terminé
Rapport détaillé : `docs/CONTROLE-2026-10.md`.
- [x] Code : syntaxe, appels, gabarits et tests automatiques sans défaut.
- [x] Site public : 16 314 adresses (FR et EN) et PDF ; corrigés : « Array » lu dans l'audio
      des tirs au but, liste des matchs triée A→Z (5,25 s → 0,06 s), contrôle des réponses de
      Stripe.
- [x] Back-office : **un enregistrement sans modification abîmait les fiches reprises**
      (tirs au but et prolongations effacés, lieu de décès tronqué, rareté de l'album forcée,
      sous-titres recollés, saison des amicaux de juin changée, enregistrement refusé pour une
      date non reconnue). Corrigé : une valeur non touchée est gardée telle quelle ;
      129 fiches de tous types rejouées, 0 modifiée ; `tests/fiche-form.php`.
- [x] Sécurité et robustesse : 3 relectures. Corrigés :
      - écritures simultanées (index, recalculs, référentiels, traductions) ;
      - limites de connexion et liens par e-mail ;
      - redirection piégée ;
      - API derrière la page d'attente ;
      - résultats du Onze cachés jusqu'au dévoilement ;
      - 100 moments planifiés à leur semaine ;
      - newsletter par bouton, exports CSV, fichiers reçus.
- [x] Données : alertes Qualité « joueur inscrit deux fois » et « dates à vérifier » ;
      ancienne page d'accueil WordPress retirée ; 25 textes anglais ajoutés.
- [x] Droits (décision du client) : Coûts IA, Assistant IA, Fiches audio, Sauvegardes et
      Tâches réservés à l'administrateur, comme les montants dépensés en IA ; Qualité ouverte
      à tous. Ensuite : dons hors ligne (enregistrement, remboursement noté) et reçus fiscaux
      (émission, PDF) réservés à l'administrateur ; liste des dons, export, mur et notes
      ouverts à tous.
- [x] Qualité :
      - nouvel onglet « À compléter » (« xx » de l'ancien site, fiches à venir, vidéos) ;
      - jour de la semaine incohérent, tirs au but non détaillés ;
      - tri par gravité et pages de 300, toutes les alertes accessibles ;
      - les alertes « à venir » et « statistiques incohérentes » ouvraient une mauvaise fiche :
        corrigé.

      Les « xx » sont cachés sur le site public (214 fiches).

## Bouton « Contrôler maintenant » (03/10, demande du client) — terminé
Détail : `docs/CONTROLE-2026-10.md`, § 6.
- [x] Pilotage › Qualité › « Contrôler maintenant » (tous les comptes) : toutes les
      vérifications refaites, comparaison avec le contrôle précédent (nouvelles, corrigées),
      pastille « Nouveau », liste des nouvelles tous onglets, historique, journal, rappel
      dans le tableau de bord ; index des fiches remis à jour après un envoi par FTP.
- [x] Premier contrôle comparé au contrôle complet du 3 octobre (référence livrée avec le code).
- [x] Nouvelles vérifications : matchs (date, saison, résultat, score, doublon,
      composition), personnes (date impossible, rubrique, même nom), fiches (titre, adresse,
      rubrique, image, fichier), site (redirections, référentiels, rubriques, textes
      anglais) ; nouvel onglet « Adresses et médias » ; « Traductions à revoir » rempli.
- [x] `tests/controle.php`, essai de bout en bout historien et administrateur, aide
      (chapitre 12, FAQ, mémo, guide PDF) et captures.
- [x] Installation neuve essayée (le site n'a jamais été mis en ligne) : copie vierge de la
      branche, premier accès, toutes les pages, tâches planifiées ; photos pas encore copiées
      signalées en une alerte, corrections du correcteur sur des textes anciens jamais
      « nouvelles ». `docs/DEPLOIEMENT.md` précisé (première installation : tout envoyer).

## Second contrôle complet (04/10, demande du client) — terminé
Détail : `docs/CONTROLE-2026-10.md`, § 7.
- [x] 3 nouvelles relectures du code modifié (contrôle et Qualité, sécurité et droits,
      masquage des « xx »), 16 312 adresses parcourues, contenu des 3 464 PDF relu, textes
      audio et images de partage vérifiés, 30 anomalies injectées, installation neuve,
      écran de téléphone, rôles, accessibilité ; 13 suites de tests.
- [x] Corrigés : symboles des compositions (↑ ↓ 🟨 🟥 ⚽) et emoji des tweets imprimés « ? »
      dans les PDF ; citations posées dans une liste absentes des PDF ; « xx » encore
      visibles (carte d'identité, PDF, audio, recherche, accueil) ; fausses « nouvelles »
      anomalies ; redirections suivies comme par un visiteur ; fichiers retouchés à la main
      qui pouvaient bloquer les statistiques ou la recherche ; coûts de l'IA encore visibles
      des historiens ; noms de fichiers de la copie des photos contrôlés ; ménages de cache
      sûrs quand plusieurs demandes arrivent en même temps.
- [x] Limites honnêtes (serveur o2switch, Stripe, e-mails, Gemini, navigateurs, charge) et
      vérifications à faire juste après la mise en ligne : § 7 du rapport.

## Mise en ligne sur musee.fcsochauxretro.com (04/10) — en cours
- [x] Le musée reste sur **musee.fcsochauxretro.com** ; www.fcsochauxretro.com reste le site
      de l'association (l'ancien WordPress n'a jamais été public ni indexé : aucune
      redirection à prévoir). `docs/DEPLOIEMENT.md` réécrit en conséquence (§ 1, 3, 6, 7).
- [x] **Site fermé par défaut** dès l'installation (page d'attente) : le public ne voit
      qu'elle, sans lien vers le back-office ; l'équipe connectée voit le vrai site, avec un
      bandeau jaune « Site fermé au public » ; rien n'est indexé (robots.txt fermé, en-tête
      noindex sur toutes les réponses, rien en cache pour l'équipe). Réglage « Masquer le
      site aux moteurs de recherche » pour garder le site hors de Google même ouvert.
- [x] **Teaser vidéo** (1 min 55) : sur l'accueil (le public le voit à l'ouverture),
      et en option sur la page d'attente (décochée : surprise gardée, vidéo introuvable par
      le public) ; aperçu pour l'équipe ; lecture par morceaux (avance rapide, iPhone).
- [x] **Vérification du serveur** : message clair « Réglage du serveur en cours » si PHP 8.3
      ou une extension indispensable manque (cas rencontré : PHP 8.1, puis extensions
      absentes en 8.3) ; carte Serveur dans Tâches planifiées, tâche en tête du tableau de
      bord, erreur détaillée pour un administrateur connecté.
- [x] `tests/attente.php` (56 vérifications) et parcours complet dans le navigateur
      (visiteur, connexion, déconnexion, téléphone).
- [ ] Copie des photos depuis WordPress, tâche planifiée, réglages (adresse, e-mail), essais
      sur le vrai serveur, puis ouverture (`docs/DEPLOIEMENT.md`, § 4 à 7).

## Teaser, aide et documents PDF à jour (04/10) — terminé
- [x] **Teaser** (1 min 55, mêmes temps forts de la musique) refait avec le site et le
      back-office d'aujourd'hui : accueil, Les chiffres du FCSM, « Écouter », Rétro-Direct,
      Fil jaune ; recherche sur le web, correcteur, fiches audio, favoris, mises à jour en un
      clic ; adresse musee.fcsochauxretro.com ; chiffres du 4 octobre 2026 (2 938 fiches,
      12 735 photos, 1 077 vidéos, 26 111 apparitions, 237 adversaires).
- [x] **Aide** : toutes les captures refaites (bande des favoris, nouvel accueil, menu fixe,
      éditeur), 6 nouvelles (favoris, recherche sur le web, garde-fou « Est-ce bien le même
      match ? », mises à jour, pages de synthèse racontées) ; « Écouter » décrit avec le lien
      « Voir le texte de l'audio » ; 4 questions ajoutées à « Comment faire pour… ».
- [x] **Guide PDF** (98 p.), **mémo** (2 p.) et **infographie A4** régénérés.

## Slider de l'accueil : photos nettes seulement (04/10, demande du client) — terminé
- [x] Le tirage au hasard ne prend plus que les fiches « À la une » dont la photo fait au
      moins **1 200 × 600 pixels** (nette en plein écran) : 613 fiches sur 2 300 (562 matchs,
      36 personnes, 15 articles) ; les 1 687 autres (dont 665 de moins de 400 pixels de
      large, jusqu'à 40 × 60) restent « À la une » mais ne sont plus tirées. S'il n'en
      restait aucune, le tirage reprendrait toutes les vraies photos.
- [x] Back-office : la carte du slider compte les fiches écartées (photo trop petite, sans
      vraie photo) ; en sélection manuelle, étiquette « photo trop petite » ; dans la fiche,
      la case « À la une » signale une image trop petite (ou absente).
- [x] `tests/accueil.php` ; aide (texte, capture, « Comment faire pour… »), bulles « ? »,
      guide PDF (99 p.), guide du back-office et doc technique à jour.

## Teaser grand public (04/10, demande du client) — terminé
- [x] **Même musique, mêmes temps forts** (1 min 55), mais uniquement le site public : aucune
      scène du back-office, aucune mention des historiens. C'est désormais la vidéo montrée au
      public (accueil, page d'attente) ; la version précédente, qui présentait aussi le
      back-office, reste dans l'historique du dépôt pour les présentations à l'équipe.
- [x] Après « Explorez » (accueil, matchs, fiches, joueurs, face-à-face, chiffres, « Écouter »,
      anglais, Rétro-Direct, quiz, Fil jaune, mobile) : **« À vous de jouer ! »** — album des
      Lions (une carte se retourne, gagnée), frise, comparateur de maillots, le guide du musée
      qui répond sur Roger Courtois (réponse tirée de sa fiche : 350 buts en 404 matchs,
      champion de France 1935 et 1938, Coupe de France 1937), une photo de famille proposée
      au musée, le vote du Onze de légende, le PDF d'une fiche ; montage : Ce jour-là,
      100 moments, réserves, records, Nos Lions, stade Bonal, kit souvenirs, dons.
- [x] Chiffres pour le public : 1 664 matchs racontés, 1 212 portraits de Lions, 1 063 vidéos
      dans les fiches, 12 720 photos, 237 adversaires ; fin « Des matchs à revivre, des Lions à retrouver, des
      souvenirs à partager », puis « Explorez · Jouez · Partagez ».

## Présentation du projet en PDF (04/10, demande du client) — terminé
- [x] `docs/sochaux-retro-presentation.pdf` : 27 pages 16:9 pour présenter le projet à quelqu'un
      qui le découvre, en **montée en puissance** (jauge de 1 à 5 sur chaque page, couleurs de
      plus en plus intenses) : point de départ (2 940 fiches, 1,8 million de mots), puis
      « Le classique » (accueil, contenu, mobile et anglais), « Le malin » (fiches match,
      joueurs reliés, saisons, face-à-face, records, 100 chiffres), « Le vivant » (jeux,
      centenaire, contributions, PDF), « Le bluffant » (Rétro-Direct, Fil jaune, Écouter) et
      « Le truc de dingue » (guide du musée, saisie dans le back-office, IA des historiens,
      chiffres techniques), fin « Un siècle de Lions. Un seul musée. »

## Site de l'association sur www (04/10, demande du client) — prêt, fermé au public
- [x] **Une seule installation, deux adresses** : musee.fcsochauxretro.com (le musée) et
      www.fcsochauxretro.com (l'association) ; fcsochauxretro.com redirige vers www ; les
      anciennes adresses WordPress de www que le musée connaît y sont redirigées. Mis à jour
      depuis GitHub comme le musée.
- [x] Pages : accueil (teaser, chiffres, actions, actualités, agenda, centenaire, soutien,
      newsletter), qui sommes-nous, équipe, statuts et documents, six actions (musée en ligne,
      vidéos et réseaux, collections et archives, expositions et rencontres, Raconte-moi Bonal,
      centenaire 2028), actualités, agenda (Rétro-Direct du musée inclus, abonnement iCal),
      nous soutenir, adhérer, bénévolat, partenaires, presse (kit média avec la présentation
      PDF), contact, mentions légales, confidentialité, cookies (aucun bandeau : aucun cookie
      tiers), plan du site.
- [x] **Formulaires** : adhésion (carte et PayPal avec les clés des dons, chèque, bulletin à
      imprimer, HelloAsso facultatif), bénévolat, contact (dans Communauté › Messages),
      newsletter « Ce jour-là ». Antispam comme sur le musée.
- [x] **Pavé « Site de l'association »** du back-office, réservé aux administrateurs : tableau
      de bord (ouverture, aperçu, points à vérifier), contenus, adhésions (export CSV),
      bénévoles, réglages ; chapitre d'aide (admins).
- [x] **Contenus de départ** rédigés à partir de ce que fait vraiment le musée ; rien
      d'inventé sur les personnes, partenaires ou articles de presse. Ce qui ne pouvait pas
      être connu est marqué « à vérifier » : récit des débuts de l'association, gouvernance,
      **tarifs d'exemple (15, 10, 25, 50 €)**, durée de l'adhésion, expositions et rencontres,
      une actualité et trois événements d'exemple (invisibles du public).
- [x] Test automatique `tests/vitrine.php` (49 vérifications, dont une adhésion payée par un
      webhook Stripe signé) ; parcours complet dans un navigateur (pavé en admin, refus pour
      un compte historien, formulaires publics, ordinateur et téléphone).
- [x] **Page d'attente propre au site de l'association** (04/10, demande du client), distincte
      de celle du musée : page claire en deux colonnes, photo de la tribune de Bonal « qui
      attend son public » et pastille « Bientôt », liste « Ce qui vous attend », inscription à
      la lettre « Ce jour-là » (fonctionne site fermé), encart du musée (teaser facultatif),
      e-mail, réseaux, compte à rebours facultatif. Écran dédié dans le pavé (Site de
      l'association › Page d'attente) avec aperçu ; celle du musée reste dans Éditorial ›
      Page d'attente. 19 vérifications de plus dans `tests/vitrine.php`.
- [ ] À faire par l'association : compléter les points « À vérifier » du tableau de bord
      (tarifs, équipe, statuts, mentions légales, e-mail de réception), relire la page
      d'attente, puis, la copie des photos terminée, faire mener www au même dossier que le
      musée (DEPLOIEMENT § 11) et ouvrir le site.

## Lisibilité des textes et en-tête du musée (04/10, demande du client) — terminé
- [x] **Espace entre les paragraphes** rétabli dans tous les textes (fiches, articles, pages
      légales, site de l'association, pages d'attente) : une règle CSS (`.prose p`) annulait
      l'écart prévu entre les blocs depuis le début du projet. Les intertitres sont aérés
      d'autant ; les adresses trop longues passent à la ligne au lieu de déborder.
- [x] **En-tête du musée** : la ligne du haut débordait de l'écran entre 1440 et 1455 px de
      large (depuis l'ajout du lien vers le site officiel) et entre 1100 et 1180 px. L'accroche
      s'efface désormais sous 1520 px et la recherche devient une loupe entre 1100 et 1199 px.
      Contrôle : 21 pages × 5 largeurs (360 à 1920 px), aucun débordement.

## Mentions légales, menu et vidéo de l'accueil de l'association (04/10, demande du client) — terminé
- [x] Mentions légales du musée (français, anglais) et de l'association : rubrique
      « Développement » — Frédéric Tholomier | LE-DIGITAL.com (lien vers le-digital.com).
      Les pages légales de l'association chargent désormais leur feuille de style (encadrés,
      tableaux).
- [x] Accueil de l'association : vidéo nettement plus grande (747 × 420 px sur grand écran au
      lieu de 518 × 291) ; bandeau aligné sur l'en-tête (1440 px), vidéo sur 7/12 de la largeur,
      sous le texte en pleine largeur sous 1024 px.
- [x] Menu de l'association : libellés toujours sur une ligne (ils se coupaient entre 1241 et
      1300 px).
- [x] Pied de page de l'association : « Site développé par Frédéric Tholomier |
      LE-DIGITAL.com » (aussi sur la page d'attente) ; bouton jaune « Entrer dans le musée »
      sous la signature, à la place du lien texte de la barre du bas.

## Vitesse du site, fiches audio, menu des langues (04/10, demande du client) — terminé
- [x] **Mesures en ligne** : quand ses caches sont en mémoire, le serveur répond en 10 à 20 ms ;
      o2switch compresse déjà les pages et les styles en Brotli (CSS 62 → 17 Ko, page 38 →
      11 Ko), rien à ajouter. Les lenteurs venaient de deux causes :
      1. **après chaque mise à jour**, tous les caches étaient effacés : la première page
         refaisait l'index des fiches, les données calculées et la recherche (3,5 s en local,
         davantage en ligne : pic de 6,7 s mesuré) ;
      2. **chaque page relisait 16 Mo de caches** (données calculées pour le bandeau de
         l'en-tête, médiathèque), gratuits tant qu'ils restent en mémoire, mais près d'une
         seconde à chaque recalcul après un enregistrement, mise à jour ou redémarrage de PHP.
- [x] Données calculées découpées en parties (une page ne lit que ce qu'elle affiche ; une
      partie inchangée garde son fichier, déjà en mémoire) ; médiathèque en 16 groupes. Mesures
      à froid, avant → après : mentions légales 356 → 87 ms, accueil 895 → 159 ms, fiche de
      match 238 → 98 ms, fiche d'un joueur de 500 matchs 429 → 187 ms, liste des matchs 730 →
      118 ms, chiffres du FCSM 1 748 → 44 ms ; site de l'association 147-171 → 70-80 ms.
- [x] **Mise à jour** : les gros caches de données sont gardés et refaits en arrière-plan avec
      le nouveau code (page suivante une fois envoyée, sinon tâche planifiée) ; un index
      manquant n'est refait qu'une fois, même demandé par plusieurs pages en même temps.
- [x] Système › Tâches planifiées, carte Serveur : mémoire d'OPcache, et alerte si elle est trop
      petite ou si PHP ne sait pas terminer une page avant son travail d'après-page.
- [x] **Fiches audio** : « Tout le musée en voix IA » ne faisait que les fiches ; la carte
      devient « Toutes les fiches en voix IA » et précise que les pages de synthèse se lancent à
      part (« Lancer pour toutes les pages »).
- [x] **En-tête du musée** : la liste FR/EN n'est plus cachée par un méga-menu resté ouvert.

## Murs de photos (05/10, demande du client) — terminé
- [x] Quatre pavés dans Interactif, un nouveau tirage au hasard à chaque visite (et « Nouveau
      tirage » sans recharger), filtres par décennie et par photographe ou source :
      **Planche-contact** (bandes de film, crédit imprimé dans la marge, loupe ; traits de
      crayon retirés à la demande du client), **Le Lion illustré** (journal : Une, articles,
      « En images », brèves ; titres des fiches et légendes de la médiathèque, rien d'inventé),
      **Le mur du vestiaire** (tirages punaisés ou scotchés, à déplacer à la souris), **La
      grande mosaïque** (des centaines de photos dessinent « 100 », « FCSM », « 1928 » ou
      « 2028 »). Agrandissement avec légende, crédit et lien vers la fiche ; anglais complet.
- [x] Photos sûres seulement : crédit renseigné, sans « DR », sans crédit à risque (agences,
      presse nationale, télévision, sites web : liste modifiable), sans crédit qui n'est qu'une
      date ou une légende ; photo d'une fiche publiée ; 300 px au moins. Au 05/10 :
      **4 975 photos** sur les murs, 33 photographes ou sources dans le filtre, 5 décennies.
- [x] Back-office : Interactif › Murs de photos (photos écartées par raison avec lien vers la
      médiathèque, crédits exclus, crédits montrés, vignettes) ; médiathèque : statut de la photo
      et case « Jamais sur les murs de photos » ; tâche planifiée qui prépare les vignettes.
- [x] Aide (Interactif › Les murs de photos, médiathèque), bulles d'aide, docs, tests.
- À faire par les historiens : corriger les **126 crédits « sans auteur »** (une date ou une
  légende tapée dans le champ Crédit) et compléter les 5 057 photos sans crédit, au fil de
  l'eau : chaque photo corrigée rejoint les murs.

## 100 moments : dates choisies et boîte à idées de l'IA (05/10, demande du client) — terminé
- [x] **Dates choisies par les historiens** : la date de parution se choisit moment par moment
      (statut « Planifié » et sa date) ; c'est la validation, une seule suffit (notée dans la
      fiche). Le **numéro suit l'ordre des dates** et ne bouge plus une fois le moment en ligne.
      Date anniversaire de l'événement proposée en un clic. Fin du calendrier « un moment par
      semaine » et du glisser-déposer.
- [x] **Site** : 100 cases, les moments parus s'ouvrent, les autres restent « À venir », sans
      date ; page d'un moment « Moment n° X · 100 ans, 100 moments ». Rien sur l'IA.
- [x] **Calendrier** (Éditorial › 100 moments) : moments datés (date modifiable avant
      parution), moments à dater, points à surveiller (même jour, long trou, sans image,
      numéro retiré), rythme jusqu'au centenaire.
- [x] **Boîte à idées** : l'IA propose des idées appuyées sur les fiches publiées (sommaire
      par époque, piste précise, « Autre idée ») ; les historiens retiennent, modifient,
      écartent (raison rappelée à l'IA) ou ajoutent leurs idées ; couverture par décennie.
      **Premier jet** : fiche « À relire » rédigée d'après les seules fiches sources, avec les
      points à vérifier ; « Écrire moi-même » pour une fiche préremplie. L'IA ne date ni ne
      publie rien. Coûts suivis (usage « 100 moments »).
- [x] Aide (Articles › 100 moments, boîte à idées), bulles d'aide, docs, `tests/moments.php`,
      parcours complet vérifié dans le navigateur avec un faux Gemini (aucun appel réel).
- À faire par les historiens : lancer le sommaire, trier, faire rédiger et dater les moments ;
  avant 1970 (peu de fiches de matchs), compléter avec leurs propres idées.

## Retouches du 05/10 (demandes du client) — terminé
- [x] **Page d'attente de l'association** : crédit de la photo affiché (« Photo : … » sous la
      légende) ; champ « Crédit de la photo » dans l'écran, sinon le crédit de la médiathèque ;
      photo de départ créditée « FC Sochaux-Montbéliard » (image du site officiel du club).
- [x] **Déconnexion automatique du back-office** après 30 minutes sans activité, dans tous les
      onglets ; « Toujours là ? » deux minutes avant (« Rester connecté ») ; une saisie sans
      enregistrement compte comme une activité ; retour à la même page après reconnexion,
      brouillon d'une fiche gardé ; le serveur ferme aussi la session si la page est fermée.
      Les appels automatiques (verrou, coûts) ne prolongent pas la session. `tests/session.php`.
- [x] **Méga-menu Interactif** deux fois moins haut : une colonne par groupe, lignes compactes.
- [x] **Planche-contact** : plus aucun trait de crayon autour des photos (ni ronds, ni mots
      griffonnés).
- [x] **Mur du vestiaire** : ambiance sombre ; au fond, flouté et très assombri, le vestiaire
      des pros du FCSM avec ses maillots jaunes suspendus (photo du club, créditée en bas du mur) ;
      15 photos par tirage (3 lignes de 5).
- [x] **Export PDF** de la planche-contact, du Lion illustré et de la grande mosaïque : bouton
      « Télécharger en PDF », PDF du tirage affiché (mêmes photos, même numéro, même motif),
      photos cliquables vers leur fiche ; mosaïque sur deux pages (motif, puis couleurs et
      crédits). `tests/photos.php`.
- [x] **Murs vides juste après une mise à jour** (il fallait cliquer sur « Nouveau mur ») :
      le cache « médias utilisés par les fiches » est désormais gardé pendant la mise à jour,
      et recalculé par les murs s'il manque.
- [x] **Raconte-moi Bonal** (nouveau nom du kit souvenirs) : page « On vous raconte le match »
      dans le PDF et sur la page du kit, avec le récit de la version audio de la fiche ; la fiche complète du match en annexe du
      kit ; le même récit dans le PDF de chaque fiche de match.

## Boutique, lot A : création des modèles (05/10, demande du client) — terminé

- Logo de l'association redessiné en vectoriel (aucune photo dans la boutique).
- Moteur de dessin vectoriel et PDF imprimeur HD (CMJN, fonds perdus 3 mm, traits de coupe, format exact).
- Supports : t-shirt, sweat, mug, mug émaillé, tote bag, **casquette**, **écharpe**, posters A3/A2, carte, sticker.
- Éditeur de modèles à remplir (logo, textes, formes, champs du client), aperçus sur le produit.
- Banque de textes : 46 slogans et 14 anecdotes du rapport (anecdotes à valider par un historien), propositions de l'IA ; un champ du client peut proposer une liste de phrases au choix.
## Boutique, lot B : la vente (05/10, demande du client) — terminé

- Prix et suppléments par taille ; choix du client calibrés (couleur du produit, couleur et taille du texte, position sans recouvrir le logo).
- Boutique sur le site du musée (bouton dans l'en-tête et le pied de page des deux sites ; www/boutique/ y renvoie) : catalogue, article avec aperçu en direct, panier, livraison, paiement Stripe (l'association encaisse), page de suivi avec messages.
- Espace imprimeur séparé (/imprimeur/, lien de connexion par e-mail) : PDF par article, étapes, numéro de suivi, réponses aux clients.
- BO : Boutique › Commandes (étapes, remboursement Stripe, paiement hors ligne), Boutique › Réglages (imprimeur, port, délai, alertes, conditions de vente).
- Pied de page du musée refait : appel réglable, plan (Explorer, Interactif, Participer), boutons Boutique et Don, icônes des réseaux, lien vers l'association.
## Boutique, lot C : la gestion et « Ton match » (05/10, demande du client) — terminé

- Tableau de bord : ventes jour, mois, année, panier moyen, marge, courbe sur 12 mois, meilleures ventes, commandes à fabriquer.
- Stripe : frais et net de chaque paiement, rapprochement (rattrape les paiements dont le message s'est perdu), litiges signalés.
- Alertes au tableau de bord et par e-mail quotidien (litige, retard de fabrication ou d'expédition, client sans réponse, modèle sans prix).
- Relevés mensuels de l'imprimeur (PDF, tableur) avec coût de fabrication par support et marge ; l'imprimeur voit son relevé sans la marge.
- « Ton match » : le client donne une date, le musée retrouve le match et l'imprime (poster A3, t-shirt au dos) ; boutons « Ton match » dans l'éditeur.
- Le texte du client n'est jamais de la couleur du produit.
- Codes promo (pourcentage, montant, livraison offerte ; dates, minimum, plafond, une fois par client, modèles) appliqués au paiement Stripe par un coupon.
- Panier redessiné (cartes, quantités −/+, récapitulatif, jauge de livraison offerte, code promo) ; pastille du nombre d'articles sur le bouton « Boutique » (cookie sr_cart, compatible avec le cache).
- Conditions de vente proposées par défaut.

## Points de données à revoir par les historiens (relevés pendant la recette)
- Liste complète et à jour : `docs/CONTROLE-2026-10.md`, § 3. Elle comprend :
  - 10 compositions avec un joueur inscrit deux fois ;
  - 9 personnes aux dates incohérentes ;
  - 65 matchs dont la date en toutes lettres diffère de la date ;
  - 37 matchs dont seul le jour de la semaine est faux ;
  - 2 séances de tirs au but non détaillées ;
  - 2 liens vidéo cassés ;
  - 4 compositions avec une minute d'entrée sur une ligne de titulaire.
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
- 100 moments : aucun moment écrit pour l'instant ; commencer par la boîte à idées
  (Éditorial › 100 moments).
- Saison 2026-2027 : le match du 02/10/2026 est en tête de l'ordre manuel (place par défaut du
  plugin WordPress) : le glisser à sa place dans Rubriques & menus.

## Octobre 2026 — boutique (suite), équipe
- [x] L'équipe de Sochaux Rétro en cartes à collectionner (mission, quelques mots, anecdote), sur l'association et le musée
- [x] Poster souvenir d'un match : générateur vectoriel (A3), choix du match par propositions, dédicace, numéro de pièce, IA vérifiée
- [x] Posters en A4, A3, A2 au choix du client, un prix par format ; zoom de l'aperçu ; pastille « Pièce unique »
- [x] Anecdote sur un sujet choisi par le client (match, joueur), faits classés du plus parlant au plus banal, alerte si sujet sans matière
- [x] Aide (boutique, association, site public), guide et mémo PDF, infographie (2 pages), fichiers MD
- [x] Poster souvenir d'un joueur (générateur, propositions, éditeur, aide, guide PDF)

## Octobre 2026 — application du musée
- [x] Musée installable (PWA) : manifeste, icônes au blason, raccourcis, service worker, lecture hors connexion, page « Hors connexion » avec les pages lisibles, page « L'appli du musée » ; interrupteur dans les réglages ; site de l'association non concerné
- [x] Notifications Web Push sans bibliothèque : clés VAPID, chiffrement RFC 8291, abonnements par sujet (Rétro-Direct, 100 moments, nouvelles, kit, Ce jour-là), envois automatiques, envoi de l'équipe (Communauté › Notifications), historique et ouvertures, confidentialité mise à jour
- [x] Correction : les pages jamais gardées (back-office, boutique…) ne sont demandées qu'une fois au serveur malgré le préchargement des pages
- [x] Bandeau « Installer l'appli » sur téléphone (2e page vue, Android : bouton ; iPhone : les deux gestes ; « Plus tard » = 30 jours ; jamais dans l'appli ni par-dessus les cookies)
- [x] Page d'attente : « Prévenez-moi de l'ouverture » (abonnement aux nouvelles, appli installable aussi sur iPhone) ; annonce de l'ouverture prête dans Communauté › Notifications, envoyée une seule fois
- [x] Mesure : le service worker ne ralentit pas le back-office (pages chargées en 97 ms sans, 90 ms avec, en local)
- [x] Écrans de connexion du back-office : un petit match en fond pour le plaisir (passes, tir, « BUT ! », confettis, filet qui tremble, tableau d'affichage avec score et minute) ; terrain debout sur téléphone ; image fixe si l'appareil demande moins d'animations
- [ ] À faire après la mise en ligne en HTTPS : installer l'appli sur un Android et un iPhone, s'abonner, envoyer un essai (le vrai service de Google ou d'Apple ne peut pas être joint depuis l'environnement de développement)

## Vitesse au lancement (octobre 2026)
- [x] Banc de test proche de la production (Apache + PHP-FPM + OPcache), mesures de référence, audit en six axes contre-vérifié : `docs/PLAN-VITESSE-LANCEMENT.md`
- [x] Teaser servi par Apache (plus par PHP), limiteur de débit en fichiers répartis, choix des cookies sans limitation, JavaScript compressé
- [x] Calculs en cache faits une seule fois (les autres visiteurs reçoivent la version précédente) ; vignettes : verrou par image, 2 fabrications au plus à la fois, `images all` pour tout préparer
- [x] Diaporama de l'accueil : une seule photo chargée à l'arrivée ; recherche bornée (pire cas 143 → 28 ms, mêmes résultats), limitée par adresse, suggestions mises en cache
- [x] Cache des pages pour les visiteurs anonymes (accueil : environ 230 → 1 830 pages par seconde sur le banc), réglable dans Réglages › Général
- [x] Boutique : catalogue 130 → 8 ms (aperçus en fichiers statiques), pas de session pour un simple visiteur, travail après paiement hors de la page, tirages IA bornés
- [x] Clics sur la notification d'ouverture (4 400/s), pages introuvables sans écriture verrouillée, mise à jour du site sans démarrage à froid
- [ ] Reste avant l'ouverture (vers le 25/12/2026) : préparer les vignettes sur le serveur, remplir la réserve d'anecdotes

## Carnet du supporter (octobre 2026)
- [x] Version 1 : « J'y étais ! » des fiches et saisie par saison, carnet ouvert par lien envoyé à l'e-mail (obligatoire), bilan, porte-bonheur, 14 badges, carte à partager, page publique sous pseudo, suppression, back-office, confidentialité, tests
- [x] Anniversaire des matchs vus : « Il y a N ans jour pour jour, vous étiez au stade », par e-mail et/ou notification, au choix dans le carnet
- [x] Poster « Ma vie en jaune et bleu » en boutique (modèle livré inactif : prix à fixer), liste des matchs figée à la commande
- [ ] Suite possible : lien avec le kit souvenirs (« vous étiez à ce match du mois »)

## Quiz du club-house (octobre 2026)
- [x] Partie en direct : grand écran (salle d'attente avec QR code, question et compte à rebours, réponse avec répartition et rappel du match, classement, podium), téléphones des joueurs (code + pseudo, 4 réponses en couleurs et formes, verdict, points, rang), points selon la rapidité
- [x] Questions sans IA : moitié quiz du site, moitié fabriquées à partir des grands matchs (score, année, adversaire, buteur), tirées au hasard à chaque partie
- [x] Back-office Interactif › Quiz du club-house (créer, ouvrir le grand écran, effacer), anglais, aide et captures, confidentialité, tests (`tests/quizlive.php`)
- [x] Rétro-Direct commenté façon radio : texte d'un reporter d'époque écrit par l'IA et calé sur le déroulé, voix IA, effet « poste radio » et rumeur de la foule, ambiance de stade, bouton d'écoute sur la page du direct, fabrication par étapes (page ou tâche planifiée), coût suivi dans Coûts IA (`tests/radio.php`)
- [x] Défi du jour en solo : 10 questions identiques pour tous (français et anglais), un essai par jour et par compte, temps mesuré par le serveur, classements du jour, du mois et de la saison, série de jours, résultat à partager, rappel par notification, invités sans classement (`tests/defi.php`)
- [x] Championnat du club-house par saison : compte par e-mail et lien sécurisé (le compte du carnet du supporter), points selon le rang de chaque partie, invités sans points, classement sur le grand écran (salle d'attente, après le podium), page publique, partie amicale, modération (pseudo déplacé, retrait du classement)

## Contrôle des nouveautés (quiz, championnat, défi, radio) — octobre 2026
- [x] Classements réservés aux comptes confirmés (lien de l'e-mail ouvert une fois) : plus de classement rempli avec des adresses inventées ; points gardés en attendant, « en attente du lien » dans le back-office
- [x] Défi du jour : invité devenu membre le même jour sur le même appareil → partie reprise hors classement ; partie commencée avant minuit terminée normalement ; réponse renvoyée si le réseau coupe
- [x] Grand écran : double appui pendant les 3 s de lecture ignoré (la question ne se ferme plus à vide)
- [x] Téléphone : code gardé dans l'adresse (rechargement = même place), état aussitôt au retour de veille, réponse renvoyée une fois si le réseau coupe, plus de bandeau « Installer l'appli » sur les pages de jeu
- [x] Back-office : modération des joueurs du défi qui n'ont pas joué en salle ; « Pseudo déplacé » jamais identique à un autre pseudo
- [x] Parcours vérifié sur le banc : anglais, mobile, rechargement, double appui, défi, championnat, back-office ; suites carnet, quiz, défi, radio, Rétro-Direct, notifications, mises à jour au vert
- [x] Correctif en ligne : PageSpeed d'o2switch neutralisé (front sans styles après la mise à jour du 06/10)

## Documents de présentation (octobre 2026, mise à jour)
- [x] Présentation `docs/sochaux-retro-presentation.pdf` : 53 pages (carnet du supporter, poster « Ma vie en jaune et bleu », « Prêt pour le lancement » avec les mesures de vitesse, page récapitulative à jour)
- [x] Infographie A4 `docs/sochaux-retro-fonctionnalites.pdf` : carnet du supporter, poster du carnet, cache des pages (toujours 2 pages)
- [x] Guide (130 pages) et mémo (2 pages) de l'aide régénérés : sections et captures du carnet, du poster, bouton « Rafraîchir toutes les pages »
- [x] Présentation passée à 58 pages : défi du jour, quiz du club-house sur grand écran, championnat du club-house, Rétro-Direct commenté façon radio, page « Quiz et radio côté équipe » ; programme et page « Toute la solution » à jour
- [x] Infographie A4 : quiz du club-house et championnat, défi du jour, commentaire radio, soirées quiz dans le back-office, guide de 136 pages (toujours 2 pages)

