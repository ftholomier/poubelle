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

1. [Mise en ligne (FTP)](#mise-en-ligne-ftp)
2. [Données de l'ancien site](#données-de-lancien-site)
3. [Tâches automatiques](#tâches-automatiques)
4. [Back-office](#back-office)
5. [Référencement](#référencement)
6. [Sécurité et RGPD](#sécurité-et-rgpd)
7. [Structure du projet](#structure-du-projet)
8. [Commandes utiles](#commandes-utiles)
9. [Crédits et licences](#crédits-et-licences)

## Mise en ligne (FTP)

Le site est livré **complet** : code, données de l'ancien site déjà converties et photos. Il n'y a ni
base de données, ni fichier SQL, ni fichier de configuration à modifier.

1. Décompressez le paquet du site sur votre ordinateur, puis copiez les fichiers de données
   (`donnees-1-sur-N.zip`, `donnees-2-sur-N.zip`…) **tels quels, sans les décompresser**, dans son dossier
   `storage/install/`.
2. Envoyez **tout le contenu** du dossier par FTP dans le dossier web de l'hébergement (`www/`,
   `public_html/`…), fichiers cachés compris (`.htaccess`, `.ovhconfig`).
3. Ouvrez le site : à la première visite, les données s'installent toutes seules (quelques minutes au
   plus, la page se recharge d'elle-même ; s'il manque un fichier de données, elle l'indique et attend).
4. Connectez-vous au back-office (`/gestion/`) avec les identifiants fournis à la livraison, puis
   choisissez votre mot de passe.
5. Dans le back-office, renseignez vos clés : **Configuration** (clé Gemini pour l'IA) et
   **Publicité** (identifiant AdSense). C'est tout.

Ce qui se règle tout seul :

- `config/.env` est créé à la première visite avec des clés secrètes uniques ; le domaine, l'email de
  contact et l'envoi des emails (fonction d'envoi de l'hébergeur) sont préréglés. Un serveur SMTP peut
  être ajouté plus tard dans Configuration, mais ce n'est pas obligatoire.
- Cloudflare et les répartiteurs des hébergeurs sont reconnus automatiquement (vraie IP des visiteurs,
  HTTPS sans boucle de redirection).
- Le `.htaccess` de la racine envoie les visiteurs vers `public/` et interdit l'accès au code, aux
  données et à la configuration. Si l'hébergement permet de faire pointer le domaine directement sur
  `public/` (ou sous Nginx : `deploy/nginx.conf`), c'est encore mieux, mais pas nécessaire.
- Le fichier `.ovhconfig` choisit PHP 8.3 sur les hébergements OVH ; ailleurs, si la version de PHP
  est trop ancienne, le site affiche un message expliquant quoi changer.

**Prérequis** : PHP 8.2 ou plus avec les extensions courantes `gd` (avec WebP), `mbstring`, `openssl`,
`curl`, `zip`, `intl`, `fileinfo`, `dom` (présentes chez la plupart des hébergeurs).

**Espace disque** : environ 450 Mo une fois installé (≈ 75 000 petits fichiers, à comparer à la limite
d'« inodes » des offres mutualisées les plus modestes), plus environ 45 Mo par sauvegarde (7 sauvegardes
automatiques conservées par défaut).

Ensuite, conseillé : activez la double authentification (Mon compte → 2FA) et changez l'adresse du
back-office (`ADMIN_PATH` dans Configuration) pour une adresse moins devinable.

## Données de l'ancien site

L'ancienne base a été **convertie une fois pour toutes en fichiers** et les photos ont été rapatriées ;
le tout est livré dans `storage/install/` (une ou plusieurs archives `donnees….zip`), que le site
décompresse lui-même à la première visite avant de supprimer les archives. Ces données ne sont pas dans le dépôt Git, qui est public, car
elles contiennent les coordonnées des pros et de leurs clients.

Ce qui a été repris :

- 2 068 fiches pros : les **actifs** sont publiés, les anciens membres restent consultables dans le
  back-office (statut « Ancien membre ») ; textes réparés (accents perdus), noms et villes normalisés,
  **métiers déduits automatiquement** (14 catégories, sans striptease), géolocalisation à la commune
  (zones d'intervention reprises).
- **Toutes les photos** encore présentes sur l'ancien site, y compris celles des anciens membres.
- Les **identifiants des pros sont conservés** ; les mots de passe, stockés en clair dans l'ancienne
  base, sont **chiffrés (Argon2id)** et un changement est demandé à la première connexion.
- Demandes de devis (≈ 7 600), messages aux pros (≈ 50 000), emails prospects (dédoublonnés),
  historique mensuel depuis 2002, mémo, articles « Actualités » (devenus le blog).
- Les tables historiques (factures, parrainage, statistiques…) sont **archivées en lecture seule**
  (mots de passe masqués) : Back-office → Archives.
- Les **anciennes adresses** (`fiche.php?id=…`, `depresultat.php`, `moteurresultat.php`, articles…)
  sont redirigées en **301** vers les nouvelles pages.

Facultatif : l'outil de conversion reste disponible (Maintenance → « Reconvertir un export de
l'ancienne base », ou `php bin/import.php export.sql --photos`) si vous vouliez un jour repartir d'un
nouvel export de l'ancien site ; il remplace alors les données en place.

## Tâches automatiques

Rien à configurer : envoi progressif des emails, campagnes programmées, statistiques, sitemaps,
contrôles de santé, récapitulatif quotidien des administrateurs, badge « très demandé », nettoyage,
sauvegarde nocturne et bilan hebdomadaire des pros se déclenchent d'eux-mêmes au fil des visites.

Facultatif, pour une exécution à heure fixe même sans visite : une tâche cron toutes les minutes
(`deploy/crontab.example`) ou un service de cron en ligne appelant l'adresse secrète
`https://votre-site/cron/{CRON_TOKEN}` (visible dans Configuration).

```cron
* * * * * php /chemin/vers/le/site/bin/cron.php > /dev/null 2>&1
```

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
| Réglages | Configuration `.env` complète (secrets masqués, sauvegarde de l'ancien fichier, modèles Gemini proposés en direct), fonctionnement, alertes, anti-spam, IA |
| Système | Sauvegardes (création, téléchargement, restauration), caches, index, import, journaux, archives, utilisateurs, mémo |

### Espace pro

Inscription gratuite (validation de l'email puis de l'équipe), tableau de bord, fiche (éditeur de
texte, aide à la rédaction par l'IA), photos (glisser-déposer, optimisation WebP automatique), demandes
de devis et messages reçus, suivi des demandes, avis (réponses publiques, invitations « client
vérifié »), statistiques, mode congés, notifications push, export de ses données, suppression du compte.

### Intelligence artificielle (Google Gemini)

Désactivable globalement ou rôle par rôle, avec un plafond d'appels quotidien : assistant des visiteurs
(il cherche dans l'annuaire réel et prépare la demande de devis), modération anti-spam, rédaction SEO,
classement des pros. Clé à renseigner dans Configuration (`GEMINI_API_KEY`) : la liste des modèles
disponibles sur votre compte Google s'affiche alors, avec les modèles conseillés présélectionnés. Si Google
retire un jour le modèle choisi, le site bascule tout seul sur le modèle conseillé et vous prévient.

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
- IP des visiteurs lue derrière un proxy seulement s'il est de confiance (Cloudflare, réseau de
  l'hébergeur ou proxys déclarés dans `TRUSTED_PROXIES`) ; blocage des connexions par IP et par
  couple compte + IP ; notifications push envoyées uniquement aux services des navigateurs ; emails
  de confirmation sans texte libre (pas de relais de spam).
- Anti-spam sans captcha visible : champ piège, délai minimal, preuve de travail calculée par le
  navigateur, limites de débit, listes noires, analyse du contenu, adresses jetables, MX, Turnstile
  et score IA facultatifs.
- Images ré-encodées (aucun fichier envoyé n'est servi tel quel), aucun script exécutable dans
  `public/media/`.
- Statistiques sans cookie ; Google Analytics, Meta Pixel et publicité personnalisée uniquement après
  consentement ; export et suppression des données par les pros ; conversations de l'assistant
  conservées 6 mois.
- Cookies (règles CNIL) : rien n'est déposé avant le choix du visiteur. Publicité : message de Google
  (AdSense › Confidentialité et messages › Réglementations européennes, avec le bouton « Ne pas
  autoriser »), exigé par Google en Europe ; ou bandeau du site. Le bandeau du site (aussi utilisé pour
  Analytics et Meta Pixel) propose « Tout accepter », « Tout refuser » (même présentation) et
  « Personnaliser » ; le choix est gardé 6 mois, et le lien « Gérer les cookies » du pied de page
  permet d'en changer (cookies effacés en cas de retrait).
- ⚠️ Ne publiez jamais `config/.env`, `storage/`, les données livrées ni l'export SQL de l'ancien site
  (le `.gitignore` les exclut).

## Structure du projet

```text
app/            code PHP (Core = socle, Services = métier, Controllers, Views), routes
app/data/       référentiels géographiques (communes, départements, régions) et articles d'origine
bin/            commandes : cron.php, import.php, admin.php, outils de génération
config/         .env (créé automatiquement, non versionné) et .env.example
deploy/         exemples Nginx, php.ini, crontab
public/         seul dossier exposé : index.php, assets (CSS, JS, polices, icônes), media
storage/        données JSON, caches, sessions, journaux, sauvegardes (non versionné) ;
                install/ : archives des données livrées, décompressées à la première visite
```

## Commandes utiles

```bash
php bin/cron.php                 # tâches planifiées (facultatif : elles tournent aussi sans cron)
php bin/cron.php --list          # dernier passage de chaque tâche
php bin/cron.php mail stats      # forcer certaines tâches
php bin/import.php export.sql    # reconvertir un export de l'ancienne base (facultatif)
php bin/import.php --photos-only --all # rapatrier les photos manquantes
php bin/admin.php create         # créer un super-administrateur
php bin/admin.php reset email    # réinitialiser un accès (mot de passe + 2FA)
php -S localhost:8000 -t public bin/dev-router.php   # serveur de développement
```

## Crédits et licences

- Cartes : [Leaflet](https://leafletjs.com) (BSD-2) et Leaflet.markercluster (MIT), fond de carte
  © contributeurs [OpenStreetMap](https://www.openstreetmap.org/copyright) (gratuit, sans clé).
- QR codes : qrcode-generator de Kazuhiko Arase (MIT).
- Polices : Bricolage Grotesque, Instrument Serif, DM Mono (SIL Open Font License, fichiers dans
  `public/assets/fonts/`).
- Référentiels géographiques : découpage administratif (Etalab, Licence Ouverte 2.0), GeoNames
  (CC BY 4.0), contours france-geojson (Licence Ouverte).
