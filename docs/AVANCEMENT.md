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

## Aide et teaser (demande du 03/10, 06:31 UTC — en cours)
Demande : rubrique « Aide » du back-office = vraie formation en ligne (texte, PDF, captures
d'écran) pour rendre les historiens autonomes ; teaser vidéo promo calé sur la musique
fournie (curseur qui navigue dans le back-office et le site, montée en puissance).
- Réveils automatiques (send_later, à supprimer à la fin) : trig_01QFsVzpbiAUccqmZayCf5x1,
  trig_01MoLdoE3wKnW2jgSDFDr6nf, trig_01JMyfRohknevGgPxyst9BkF, trig_019LzQSPQoJemcYKhyRt7Wu9,
  trig_011AVAUWKkxLACHCUh68ECdv, trig_01CLYVCwRxMMuyYSaH4ECZj6, trig_01GrZeEvf6nTtYXGucYuzzjU.
- [ ] Aide : écrans /admin/aide (modules, leçons, quiz, progression), aide contextuelle
- [ ] Aide : captures d'écran annotées (app/Resources/aide/img), PDF guide + mémo
- [ ] Teaser : analyse musique, captures, animation, rendu MP4 (1920×1080)
- [ ] Tests, nettoyage (compte de capture, données), commit, envoi des fichiers

