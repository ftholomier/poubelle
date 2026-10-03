# Sochaux Rétro — avancement du développement

Fichier de reprise : à relire en premier après une pause (quota, réveil automatique).
Règle : cocher au fur et à mesure, pousser après chaque étape.

## Réveils automatiques (à supprimer quand tout est terminé)
- Routine horaire de secours : `trig_01As8VxpxYtxnuZiiJrm2bC7`.
- Réveils ponctuels (send_later) jusqu'à 04:30 UTC le 03/10 :
  trig_01WPhM91D2bCLq2JMeB2koiu (02:50), trig_01C7RBzsVRBS2zj8PixyEwUF (03:15),
  trig_01QLHt3gVKj4ucoRi4QUhx4J (03:40), trig_01E4qWa7tXoCL47JrbxAGxKy (04:05),
  trig_01JSKt5KTgc4wsx5APcm75WT (04:30). Réarmer une série si le travail continue au-delà.

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
- [ ] Téléchargement des originaux des médias (en cours, ≈ 97 %)
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
- [x] Documentation de déploiement o2switch (`docs/DEPLOIEMENT.md`)
- [ ] Import final après le téléchargement complet des médias, puis versionnement de `data/`
- [ ] Nettoyage : compte de test, journal d'activité de test, réveils automatiques

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
  d'un tableau, titres de blocs, vidéos dans le texte, images retouchées par WordPress,
  11 images hors médiathèque, page « Joueurs » (liste automatique → rubrique Nos Lions).

## Journal
- 02/10 22:26 UTC — feu vert du client, démarrage du développement.
- 03/10 01:40 UTC — back-office complet et testé de bout en bout.
- 03/10 02:40 UTC — contrôle d'exhaustivité : pertes corrigées, compositions avec n° de maillot.
