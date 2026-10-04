# Sochaux Rétro — le musée en ligne du FC Sochaux-Montbéliard

Refonte complète de [fcsochauxretro.com](https://www.fcsochauxretro.com) (WordPress) en
PHP natif, sans base de données. Tout le contenu de l'ancien site est repris et contrôlé
automatiquement : 2 940 fiches (1 664 matchs, 1 212 personnes, articles et pages),
12 735 photos, 884 vidéos, 2 593 tableaux, 123 rubriques et 5 984 anciennes adresses
redirigées.

## Ce que fait le site

**Site public** (français et anglais)
- Accueil : slider « À la une », bandeau « En direct du musée », compte à rebours du
  centenaire (20 mai 2028), « Ce jour-là », le chiffre du jour (un des 100 chiffres du
  FCSM), palmarès, grandes époques.
- Rubriques d'origine avec méga-menus et mosaïques (filtres, tri, vue liste).
- Fiches match : score, terrain et tableau de composition (numéros, buts, remplacements,
  cartons), temps forts, réactions, vidéos, galerie, face-à-face avec l'adversaire.
- Fiches joueurs, entraîneurs, dirigeants : carte à collectionner, identité, récit,
  statistiques, tous leurs matchs reliés automatiquement.
- Explorer : saisons, face-à-face, bilans par compétition et par stade, records, et
  **Les chiffres du FCSM** : 100 statistiques depuis 1929 en 11 chapitres (meilleur buteur
  de l'histoire, recordman des matchs, plus longue invincibilité, but le plus rapide,
  remontadas, affluences, âges records…), calculées automatiquement depuis les fiches.
- INTERACTIF : **Rétro-Direct** (un grand match rejoué en direct, minute par minute, le
  jour de son anniversaire, et tous les matchs à revivre en accéléré), **Fil jaune** (deux
  joueurs reliés par les matchs joués ensemble, constellation des coéquipiers, défi du
  jour), **kit souvenirs** (« Les Après-midi Bonal » : chaque mois, 4 pages en gros
  caractères à imprimer pour les anciens supporters, avec QR code pour raconter ses
  souvenirs), quiz, album de cartes, maillots, frise, carte OpenStreetMap, centenaire
  (100 moments, Onze de légende), réserves du musée.
- **« Ils y étaient »** sur chaque fiche de match : « J'y étais ! » et souvenirs des
  supporters publiés par l'équipe.
- Recherche, assistant IA (Gemini) en bas à droite, dons (Stripe, PayPal), contact,
  contributions, newsletter « Ce jour-là ».
- **Télécharger en PDF** sur chaque fiche et chaque page de synthèse (saison,
  face-à-face, bilans, records) : un vrai document A4 mis en page aux couleurs du musée
  (polices du site embarquées, blason, pages numérotées, signets, liens), fabriqué par le
  serveur en PHP pur et mis en cache.

**Back-office** (`/admin`) pour les historiens : fiches, compositions en grille,
médiathèque, rubriques et menus (ordre des fiches de chaque décennie, saison ou rubrique
par glisser-déposer avec trait d'insertion), accueil, outils interactifs, communauté, dons,
traductions, qualité des données (avec un bouton **Contrôler maintenant** : contrôle
complet en quelques secondes, anomalies nouvelles et corrigées depuis le contrôle
précédent), **correcteur d'orthographe et de syntaxe** (Gemini et
règles du musée : chaque correction est proposée, l'historien accepte ou ignore ; relecture
de fond de tout le musée dans Qualité › Orthographe), **coût de l'IA en temps réel**
(dépense du jour et du mois, derniers appels, budget, relevé mensuel PDF et CSV à faire
rembourser par l'association), **verrou nominatif** (une fiche ouverte par quelqu'un est
signalée aux autres, en lecture seule, avec « Prendre la main »), **fiches audio** (chaque
fiche est expliquée à voix haute (3 minutes au plus, réglable) : voix du navigateur gratuite ou voix IA enregistrée,
fiche par fiche ou pour tout le musée en traitement groupé ; les pages de synthèse, face-à-face,
saisons, bilans, records et chiffres, sont racontées par l'IA et lues par sa voix enregistrée,
refaites chaque nuit quand leurs chiffres changent), **Rétro-Direct** (programme
des directs, anniversaires ronds proposés, public de chaque direct), **kit souvenirs** (match
de chaque mois, mot d'introduction ; témoignages publiés sur les fiches), sauvegardes, aide en
ligne (guide,
mémo PDF, bulles « ? »). Deux niveaux d'accès : administrateur
et utilisateur. Voir le [guide du back-office](docs/GUIDE-BACK-OFFICE.md).

## Technique en bref

- PHP 8.3 sans framework, données en fichiers JSON (aucune base de données).
- HTML, CSS et JavaScript natifs ; carte Leaflet + OpenStreetMap ; polices hébergées sur
  le site.
- Seul `public/` est exposé sur Internet ; code, données et réglages restent en dehors.
- Aucune dépendance à installer (ni Composer, ni npm).
- Services externes, tous facultatifs et réglés dans le back-office : Gemini
  (assistant, traduction, correcteur d'orthographe), Stripe et PayPal (dons), SMTP (e-mails).

## Arborescence

```
app/            code PHP
  Core/         noyau : requête, routeur, sessions, comptes, réglages, stockage JSON
  Data/         fiches, index, rubriques, médiathèque, données calculées, compositions
  Services/     images, recherche, assistant IA, traduction, paiements, e-mails, tâches…
  Front/        pages du site public (dont PdfExport : exports PDF)
  Pdf/          moteur PDF maison : polices TrueType, écriture du fichier, mise en page
  Admin/        écrans du back-office
  Kernel.php    point d'entrée HTTP (langue, page d'attente, routes, redirections)
bin/            console.php (commandes), dev-router.php (serveur local)
config/         settings.php : liste des réglages et valeurs par défaut
data/           contenus éditoriaux (fiches, médiathèque, rubriques, redirections…)
docs/           documentation et maquette graphique de référence
public/         seul dossier exposé : index.php, .htaccess, assets/, media/ (vignettes)
scripts/wp/     reprise de l'ancien WordPress (aspiration, import, contrôle)
storage/        fichiers de travail non versionnés (photos originales, caches, comptes,
                réglages, sessions, sauvegardes, journaux)
templates/      gabarits HTML (site public et back-office)
```

## Lancer le site en local

```
php -S 127.0.0.1:8080 -t public bin/dev-router.php
```

- Site : http://127.0.0.1:8080/
- Back-office : http://127.0.0.1:8080/admin/premier-acces (le code est écrit dans
  `storage/premier-acces.txt`), ou `php bin/console.php admin <e-mail> "<Nom>"`.
- Photos : `php scripts/wp/media-sync.php` les télécharge depuis l'ancien site ; sans
  elles, des emplacements vides s'affichent.

La première page met quelques secondes à s'afficher (construction des index dans
`storage/cache/`).

## Commandes

```
php bin/console.php index            reconstruit l'index des fiches
php bin/console.php derived          recalcule statistiques, liens, bilans, qualité
php bin/console.php search           reconstruit l'index de recherche
php bin/console.php cron             tâches planifiées (à lancer toutes les 5 min)
php bin/console.php admin <email> <nom>   crée un compte administrateur
php bin/console.php backup           sauvegarde immédiate
php bin/console.php rag              (ré)indexe les données pour l'assistant IA
php bin/console.php images [largeur] pré-génère les vignettes des photos
php bin/console.php medias           complète dimensions, poids et empreintes des médias
php bin/console.php videos           copie les vignettes des vidéos
php bin/console.php geo [--hors-ligne]  géolocalise stades et lieux de naissance
php bin/console.php correcteur [secondes]  vérifie l'orthographe de toutes les fiches (sans plafond quotidien)
php bin/console.php audio [secondes]       fiches audio : envoie et range les traitements groupés
```

## Mise en ligne

Sur o2switch, par FTP, sans SSH : voir [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md)
(récupération des fichiers, réglage de PHP, photos, tâche cron, premier accès, ouverture du
musée sur musee.fcsochauxretro.com, dons, mises à jour).

Important : une fois le site en service, les dossiers `data/` et `storage/` du serveur
contiennent le travail des historiens. Ne jamais les remplacer par ceux du dépôt.

## Documentation

| Document | Pour qui | Contenu |
|---|---|---|
| [docs/GUIDE-BACK-OFFICE.md](docs/GUIDE-BACK-OFFICE.md) | historiens | utiliser le back-office au quotidien |
| [docs/DEPLOIEMENT.md](docs/DEPLOIEMENT.md) | administrateur | installer, mettre en ligne, mettre à jour |
| [docs/TECHNIQUE.md](docs/TECHNIQUE.md) | développeur | architecture, données, caches, sécurité |
| [docs/DECISIONS.md](docs/DECISIONS.md) | tous | choix validés avec le club |
| [docs/AVANCEMENT.md](docs/AVANCEMENT.md) | tous | état du projet, contrôle d'exhaustivité |
| [docs/CONTROLE-2026-10.md](docs/CONTROLE-2026-10.md) | tous | contrôle complet du code et des données, points à vérifier par les historiens |
| [docs/IDEES.md](docs/IDEES.md) | association | idées en réserve (Allô Bonal, Rétro-Direct, Fil jaune…) |
| `docs/maquette/` | tous | maquette graphique de référence |

## Reprise du WordPress

Les scripts de `scripts/wp/` ont servi à reprendre l'ancien site, gelé pendant la refonte :
aspiration (`fetch.php`, mot de passe du site dans la variable `WP_PASSWORD`), import
(`import.php`), contrôle d'exhaustivité (`completeness.php`), photos (`media-sync.php`,
`media-extra.php`). L'import réécrit les fiches reprises : il ne sert plus une fois les
historiens au travail.
