# Animateur Pour Votre Soirée — nouvelle version

Annuaire des professionnels de l'animation et de l'événementiel (DJ, magiciens, groupes, animateurs
enfants, photobooths…) : recherche par métier, ville et carte, demandes de devis groupées, messages
directs, avis vérifiés, espace pro, back-office complet, assistant IA, application installable (PWA).

- **100 % PHP natif** (8.2+), sans framework, sans dépendance à installer, **sans base de données** :
  les données sont des fichiers JSON indexés, rangés hors du dossier web.
- Seul `public/` est exposé ; le code (`app/`), les données (`storage/`) et les secrets (`config/.env`)
  sont inaccessibles depuis le web.
- Gratuit pour les visiteurs et les pros : le modèle économique est la publicité **Google AdSense**,
  pilotée depuis le back-office.

## Sommaire

1. [Installation](#installation)
2. [Migration de l'ancien site](#migration-de-lancien-site)
3. [Tâches planifiées](#tâches-planifiées-cron)
4. [Back-office](#back-office)
5. [Référencement](#référencement)
6. [Sécurité et RGPD](#sécurité-et-rgpd)
7. [Structure du projet](#structure-du-projet)
8. [Commandes utiles](#commandes-utiles)
9. [Crédits et licences](#crédits-et-licences)

## Installation

**Prérequis** : PHP 8.2 ou plus avec les extensions `gd` (avec WebP), `mbstring`, `openssl`, `curl`,
`zip`, `intl`, `fileinfo`, `dom` (et de préférence `sodium`, `exif`, OPcache). Voir
`deploy/php.ini.example`.

**Espace disque** : comptez environ 400 Mo pour les données migrées (≈ 70 000 petits fichiers JSON,
vérifiez la limite d'« inodes » sur un hébergement mutualisé), 40 à 60 Mo pour les photos des pros,
et environ 45 Mo par sauvegarde (7 sauvegardes automatiques conservées par défaut).

1. Copiez tout le dossier sur le serveur (par exemple `/var/www/animateurpourvotresoiree`).
2. Faites pointer le domaine sur le dossier **`public/`** (Nginx : `deploy/nginx.conf` ; Apache :
   `public/.htaccess` est fourni). Si votre hébergement ne le permet pas, le `.htaccess` de la racine
   redirige tout vers `public/` et bloque le reste.
3. Donnez à PHP le droit d'écrire dans `storage/`, `config/` et `public/media/`.
4. Ouvrez le site une première fois : le fichier `config/.env` est créé automatiquement à partir de
   `config/.env.example`, avec des clés secrètes générées (`APP_KEY`, `CRON_TOKEN`, `SETUP_TOKEN`).
5. Renseignez au minimum dans `config/.env` : `APP_URL`, `APP_ENV=production`, `CONTACT_EMAIL`
   et les paramètres SMTP (tout se modifie ensuite depuis le back-office). Derrière Cloudflare ou un
   proxy qui gère le HTTPS, indiquez aussi `TRUSTED_PROXIES` (`cloudflare` ou les IP du proxy) : sinon
   la redirection vers HTTPS tourne en boucle et l'anti-spam ne voit que l'IP du proxy.
6. Allez sur `https://votre-site/gestion/setup` et saisissez le **jeton d'installation**
   (`SETUP_TOKEN` du fichier `config/.env`) pour créer le compte super-administrateur. Le jeton est
   effacé après usage. Alternative : `php bin/admin.php create`.
7. Activez la double authentification (Mon compte → 2FA), configurez le cron (ci-dessous), puis
   changez l'adresse du back-office (`ADMIN_PATH`) pour une adresse moins devinable.

## Migration de l'ancien site

Le dump SQL de l'ancienne base est **converti** (aucune base MySQL n'est nécessaire) :

- **Depuis le back-office** : Maintenance & import → déposez le fichier `.sql` (ou copiez-le par FTP
  dans `storage/import/`), cliquez sur « Lancer la migration », puis « Rapatrier les photos ».
- **En ligne de commande** :

  ```bash
  php bin/import.php chemin/vers/dump.sql --photos
  ```

Ce que fait la migration :

- 2 068 fiches pros importées : les **actifs** sont publiés, les anciens membres restent consultables
  dans le back-office (statut « Ancien membre ») ; textes réparés (accents perdus), noms et villes
  normalisés, **métiers déduits automatiquement** (14 catégories, sans striptease), géolocalisation
  à la commune (zones d'intervention reprises).
- Les **identifiants des pros sont conservés** ; les mots de passe, stockés en clair dans l'ancienne
  base, sont **chiffrés (Argon2id)** et un changement est demandé à la première connexion.
- Demandes de devis (≈ 7 600), messages aux pros (≈ 50 000), emails prospects (dédoublonnés),
  historique mensuel depuis 2002, mémo, articles « Actualités » (devenus le blog) : tout est repris.
- Les tables historiques (factures, parrainage, statistiques…) sont **archivées en lecture seule**
  (mots de passe masqués) : Back-office → Archives.
- Les **anciennes adresses** (`fiche.php?id=…`, `depresultat.php`, `moteurresultat.php`, articles…)
  sont redirigées en **301** vers les nouvelles pages.

La migration est rejouable : relancée, elle remplace les données importées (les photos déjà
rapatriées sont conservées).

## Tâches planifiées (cron)

Une seule ligne, toutes les minutes (`deploy/crontab.example`) :

```cron
* * * * * php /var/www/animateurpourvotresoiree/bin/cron.php > /dev/null 2>&1
```

Elle gère : envoi progressif des emails, campagnes programmées, agrégation des statistiques,
sitemaps, contrôles de santé, récapitulatif quotidien des administrateurs, badge « très demandé »,
nettoyage (sessions, caches, journaux, données expirées), sauvegarde nocturne, bilan hebdomadaire
des pros. Sans accès au cron, appelez l'adresse secrète `https://votre-site/cron/{CRON_TOKEN}`
toutes les minutes avec un service de cron en ligne (adresse visible dans Configuration).

## Back-office

Adresse : `https://votre-site/{ADMIN_PATH}/` (par défaut `/gestion/`). Rôles : super-administrateur,
administrateur, modérateur, rédacteur. Recherche globale avec <kbd>Ctrl</kbd>+<kbd>K</kbd>.

| Rubrique | Contenu |
| --- | --- |
| Tableau de bord | Chiffres clés, audience, demandes, inscriptions, état du site (cron, emails, IA, sauvegardes, disque), alertes |
| Statistiques | Audience sans cookie, fiches les plus vues, recherches des visiteurs, demandes par département / occasion / métier, historique depuis 2002 |
| Notifications | Centre d'alertes ; envoi par email (immédiat ou récapitulatif quotidien) et **notifications push** sur mobile/ordinateur |
| Pros | Liste filtrable, actions groupées, fiche complète, photos, validation / refus, mise en avant, aperçu de l'espace du pro, reclassement IA, export CSV, effacement RGPD |
| Demandes de devis | Modération hybride, choix des pros destinataires (carte des distances), suivi, spam et listes noires, export |
| Messages, contacts, avis | Modération, réponses aux messages du formulaire de contact, correction et publication des avis |
| Emailing | Campagnes vers les adhérents (segments par statut, métier, région, département, photos, connexion, complétude), test, programmation, suivi ouvertures/clics, désinscription en un clic ; modèles des emails automatiques ; file d'envoi ; prospects |
| Contenu | Blog (avec brouillons rédigés par l'IA), pages, page d'accueil, métiers, occasions, envoi d'images |
| Référencement | Gabarits de titres et descriptions (mots-clés + localisation), textes des pages locales (manuels ou IA), redirections et pages 404, robots.txt |
| Publicité | Identifiant AdSense, emplacements et formats, ads.txt, consentement (message Google ou bandeau du site), Analytics, codes de suivi |
| Réglages | Configuration `.env` complète (secrets masqués, mot de passe requis, sauvegarde de l'ancien fichier), fonctionnement, alertes, anti-spam, IA |
| Système | Sauvegardes (création, téléchargement, restauration), caches, index, import, journaux, archives, utilisateurs, mémo |

### Espace pro

Inscription gratuite (validation de l'email puis de l'équipe), tableau de bord, fiche (éditeur de
texte, aide à la rédaction par l'IA), photos (glisser-déposer, optimisation WebP automatique), demandes
de devis et messages reçus, suivi des demandes, avis (réponses publiques, invitations « client
vérifié »), statistiques, mode congés, notifications push, export de ses données, suppression du compte.

### Intelligence artificielle (Google Gemini)

Désactivable globalement ou rôle par rôle, avec un plafond d'appels quotidien : assistant des visiteurs
(il cherche dans l'annuaire réel et prépare la demande de devis), modération anti-spam, rédaction SEO,
classement des pros. Clé à renseigner dans Configuration (`GEMINI_API_KEY`).

## Référencement

- URL avec métier + lieu : `/dj/`, `/dj/bretagne/`, `/dj/rhone-69/`, `/dj/rhone-69/lyon/`,
  `/animateurs/…` (tous métiers), `/animation-mariage/rhone-69/`, fiche
  `/dj/rhone-69/lyon/nom-du-pro/`.
- Titres et descriptions générés à partir de gabarits modifiables, données structurées JSON-LD
  (LocalBusiness, avis, fil d'Ariane, articles, FAQ), sitemaps XML découpés, maillage interne
  (villes et départements voisins), pages vides en `noindex`, URL canoniques.
- Pages rapides : cache des pages pour les visiteurs, images WebP en plusieurs tailles, scripts
  différés, polices auto-hébergées, aucun cookie pour les visiteurs anonymes.

## Sécurité et RGPD

- Code et données hors du dossier web ; écritures atomiques avec verrous.
- En-têtes de sécurité stricts (CSP avec nonce, HSTS, X-Frame-Options…), jetons CSRF, sessions
  durcies, limitation des tentatives de connexion, **double authentification TOTP** pour l'équipe,
  restriction possible du back-office par adresse IP, chemin du back-office personnalisable.
- Mots de passe chiffrés en Argon2id ; secrets chiffrés au repos (2FA) ; journal d'audit des actions.
- Chaque rôle ne voit que ses rubriques, recherche globale comprise ; configuration `.env`, codes de
  suivi bruts, téléchargement et restauration des sauvegardes réservés au super-administrateur.
- IP des visiteurs lue derrière un proxy seulement s'il est déclaré de confiance ; blocage des
  connexions par IP et par couple compte + IP ; notifications push envoyées uniquement aux services
  des navigateurs ; emails de confirmation sans texte libre (pas de relais de spam).
- Anti-spam sans captcha visible : champ piège, délai minimal, preuve de travail calculée par le
  navigateur, limites de débit, listes noires, analyse du contenu, adresses jetables, MX, Turnstile
  et score IA facultatifs.
- Images ré-encodées (aucun fichier envoyé n'est servi tel quel), aucun script exécutable dans
  `public/media/`.
- Statistiques sans cookie ; Google Analytics, Meta Pixel et publicité personnalisée uniquement après
  consentement ; export et suppression des données par les pros ; conversations de l'assistant
  conservées 6 mois.
- ⚠️ Ne publiez jamais `config/.env`, `storage/` ni le dump SQL (le `.gitignore` les exclut).

## Structure du projet

```text
app/            code PHP (Core = socle, Services = métier, Controllers, Views), routes
app/data/       référentiels géographiques (communes, départements, régions) et articles d'origine
bin/            commandes : cron.php, import.php, admin.php, outils de génération
config/         .env (créé automatiquement, non versionné) et .env.example
deploy/         exemples Nginx, php.ini, crontab
public/         seul dossier exposé : index.php, assets (CSS, JS, polices, icônes), media
storage/        données JSON, caches, sessions, journaux, sauvegardes, imports (non versionné)
```

## Commandes utiles

```bash
php bin/cron.php                 # tâches planifiées (à lancer chaque minute)
php bin/cron.php --list          # dernier passage de chaque tâche
php bin/cron.php mail stats      # forcer certaines tâches
php bin/import.php dump.sql      # migrer l'ancienne base (ajoutez --photos pour les photos)
php bin/import.php --photos-only # rapatrier seulement les photos
php bin/admin.php create         # créer un super-administrateur
php bin/admin.php reset email    # réinitialiser un accès (mot de passe + 2FA)
php -S localhost:8000 -t public bin/dev-router.php   # serveur de développement
```

## Crédits et licences

- Cartes : [Leaflet](https://leafletjs.com) (BSD-2) et Leaflet.markercluster (MIT), fonds
  © OpenStreetMap / CARTO.
- QR codes : qrcode-generator de Kazuhiko Arase (MIT).
- Polices : Bricolage Grotesque, Instrument Serif, DM Mono (SIL Open Font License, fichiers dans
  `public/assets/fonts/`).
- Référentiels géographiques : découpage administratif (Etalab, Licence Ouverte 2.0), GeoNames
  (CC BY 4.0), contours france-geojson (Licence Ouverte).
