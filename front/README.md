# Site Renov'Est (renovest.fr) — PHP natif

Refonte complète de renovest.fr :

- **contenu** : celui du site renovest.fr actuel, repris sans réécriture (textes, menu,
  arborescence et adresses des pages, produits, actualités, réalisations, avis, photos) ;
- **design, ergonomie et fonctionnalités** : ceux de la maquette validée
  (`design_handoff_renovest_site`) — barre CTA fixe, bot IA, popup de sortie, devis en
  3 étapes, header qui passe en gris translucide au scroll, apparitions, compteurs,
  parallaxe, carrousel d'avis, curseur ;
- **technique** : PHP natif, JSON, API, HTML, CSS, JavaScript natif. Aucune base de
  données, aucun framework, aucune dépendance (pas de Composer, pas de npm).

## Arborescence

```
front/
├── public/            ← SEULE racine web (DocumentRoot)
│   ├── index.php      contrôleur frontal (site, API, back-office)
│   ├── .htaccess      réécriture d'adresses, HTTPS, blocage des scripts
│   ├── assets/        css (site, admin), js (site, admin), img (logo, formes du héros), favicons
│   ├── media/         photos et vidéo reprises de renovest.fr (versionnées)
│   └── uploads/       fichiers téléversés depuis le back-office (jamais versionnés)
├── content/           contenu d'origine extrait de renovest.fr (JSON versionné)
├── src/               code PHP (routage, stockage, API, back-office, bot, Facebook, mises à jour)
├── views/             gabarits PHP (pages publiques, back-office)
├── config/            config.php (+ config.local.php propre au serveur, non versionné)
└── storage/           données vivantes : contenus modifiés, demandes, comptes, secrets (non versionné)
```

Tout ce qui n'est pas dans `public/` est hors d'atteinte du navigateur ; chaque dossier
contient en plus un `.htaccess` « Require all denied ».

**Contenu** : au premier lancement, chaque fichier `content/*.json` est copié dans
`storage/data/`. Le back-office modifie ensuite `storage/data/` : une mise à jour du code
ne touche jamais aux contenus modifiés, aux demandes ni aux comptes.

## Installation

1. Envoyer le dossier `front/` sur l'hébergement et faire pointer le domaine sur
   `front/public`. Si l'hébergeur ne le permet pas, pointer sur `front/` : le `.htaccess`
   présent redirige en interne vers `public/` et bloque les autres dossiers.
2. PHP 8.1 ou plus avec les extensions `curl`, `zip`, `fileinfo`, `dom` (et `gd` pour
   réduire les grandes photos). Les dossiers `storage/` et `public/uploads/` doivent être
   inscriptibles par PHP (et `src/`, `views/`, `public/`, `content/` pour les mises à jour
   en un clic).
3. Facultatif : créer `config/config.local.php` :
   ```php
   <?php return [
       'site_url'  => 'https://renovest.fr',
       'setup_key' => 'une-longue-cle-aleatoire',
       'mail_from' => 'no-reply@renovest.fr',
   ];
   ```
4. Ouvrir `/admin` : page d'installation. Saisir la clé (`setup_key`, ou à défaut le contenu
   du fichier `storage/data/setup-key.txt` généré automatiquement, à lire par FTP), puis
   l'e-mail et le mot de passe du compte administrateur.
5. Programmer chez l'hébergeur, toutes les heures, l'appel de l'adresse indiquée dans
   **Back-office › Sauvegarde › Tâche planifiée** (`/api/cron?key=…`) : synchronisation
   Facebook et vérification des mises à jour.

Développement local :

```
php -S localhost:8000 -t front/public front/public/index.php
```

## Pages et adresses

Les adresses de renovest.fr sont conservées à l'identique (référencement) :

| Page | Adresse |
|---|---|
| Accueil | `/` |
| Présentation | `/entreprise-de-menuiserie-a-montbeliard-et-belfort-specialiste-fenetres-fermetures/` |
| Nos produits (+ 6 pages produit) | `/menuiseries-exterieures-montbeliard-fenetres-portes-entree-garage-volets-pergolas/` … |
| Nos réalisations | `/vente-menuiserie-fenetre-porte-entree-garage-cloture-montbeliard-belfort-besancon-mulhouse/` |
| Actu (+ 17 actualités) | `/menuiserie-fenetre-store-pergola-montbeliard-belfort-mulhouse-besancon/` |
| Avis | `/menuiserie-fenetre-porte-entree-garage-pergola-montbeliard-belfort/` |
| Contact | `/specialise-devis-menuiserie-fenetre-porte-garage-entree-franche-comte/` |
| Mentions légales | `/renovest-menuiserie-fenetre-porte-entree-garage-belfort-montbeliard-mulhouse/` |
| Devis (nouveau, maquette) | `/devis/` |

Redirections 301 : `/wp-content/uploads/…` → `/media/…`, `/category/actualites/`,
`/slider/…`, `/actu-site/`, etc. Plan du site : `/sitemap.xml`, `robots.txt`.

## API JSON

| Méthode | Adresse | Rôle |
|---|---|---|
| POST | `/api/devis` | demande de devis (3 étapes) |
| POST | `/api/contact` | formulaire de contact |
| POST | `/api/rappel` | demande de rappel |
| POST | `/api/bot` | assistant du site |
| POST | `/api/vocal`, `/api/vocal/resume` | message vocal, résumé IA avant envoi |
| GET | `/api/site`, `/api/produits`, `/api/actus`, `/api/actus/{id}`, `/api/realisations`, `/api/avis` | contenu en lecture |
| GET | `/api/cron?key=…` | tâches planifiées |

Anti-spam invisible sur tous les formulaires (`src/Antispam.php`, réglages dans Réglages › Anti-spam) :
- barrages (robot certain, faux « merci », rien d'enregistré) : pot de miel, envoi depuis un autre site
  (en-tête `Origin`), assistant et résumé du vocal appelés sans la preuve JavaScript de la page ;
- score de suspicion : jeton signé (HMAC) trop récent, aucun geste humain sur la page (preuve `pf`),
  logiciel automatique, liens, mots de spam (liste modifiable), anglais, alphabet étranger, e-mail jetable,
  numéro fantaisiste, doublon. Au-delà du seuil (souple 8 / normal 5 / strict 3), la demande va dans le
  statut « Spam » sans e-mail ; « Ce n'est pas un spam » la récupère et envoie l'e-mail. Spams effacés après 30 jours ;
- limitation par IP, plafond quotidien de questions à l'assistant (coût Gemini).

Chaque demande légitime est enregistrée puis envoyée par e-mail.

## Back-office (`/admin`)

- **Tableau de bord** : demandes non traitées, graphique 14 jours, liste de mise en service.
- **Demandes** : devis, contacts, rappels, messages vocaux (écoute) ; recherche, filtres, statut, notes, export CSV.
- **Conversations du bot** : journal des échanges avec l'assistant.
- **Page d'accueil** : héros (titre, vidéo), compteurs, sections, bloc final, SEO.
- **Pages** : Présentation, Nos produits, Réalisations, Actu, Avis, Contact, Devis, Mentions.
- **Produits** : ajout, modification (éditeur de texte), ordre, visibilité, SEO.
- **Photos produits**, **Réalisations**, **Avis clients** : fiche Google connectée (recherche par nom, adresse ou lien), avis saisis à la main, note affichée.
- **Actualités** : création, programmation, catégories ; import et publication Facebook.
- **Médiathèque** : téléversement (glisser-déposer), réduction automatique des photos.
- **Menu**, **Coordonnées** (agences, téléphones, zone d'intervention, réseaux).
- **Conversion** : bandeau supérieur, barre CTA, bot, bulle d'accroche, popup de sortie,
  formulaire de devis, logos partenaires, curseur.
- **Assistant IA** : clé API Gemini, liste dynamique des modèles Gemini, informations complémentaires, test.
- **Facebook** : page, jeton, catégorie par défaut, règles par hashtag, synchronisation.
- **E-mails** : destinataires, SMTP facultatif, test d'envoi.
- **Utilisateurs** (administrateur / éditeur), **Mon compte**, mot de passe oublié.
- **Mises à jour** : en un clic depuis GitHub (voir ci-dessous).
- **Sauvegarde** : export / import JSON du contenu, retour au contenu d'origine, tâche planifiée.

Sécurité : session limitée à `/admin` (`HttpOnly`, `SameSite=Strict`), jeton CSRF sur
chaque action, limitation des tentatives de connexion, mots de passe hachés, HTML saisi
nettoyé (liste blanche), en-têtes CSP (nonce), téléversements vérifiés (type réel), aucun
script exécutable dans `uploads/`, secrets stockés hors web et jamais renvoyés.

## Mises à jour en un clic depuis GitHub

Même mécanique qu'Intensiti (**Back-office › Mises à jour**, administrateurs) :

- le dépôt et la branche sont réglables (par défaut `ftholomier/poubelle`,
  branche `claude/laughing-lamport-g5ikf8`) ; un jeton GitHub en lecture seule est
  nécessaire si le dépôt est privé ;
- « Vérifier » compare la version installée à la dernière version de la branche et le code
  du serveur fichier par fichier (empreintes Git, sans rien télécharger) : un site envoyé
  par FTP identique est reconnu, un fichier oublié ou retouché est signalé ;
- « Mettre à jour maintenant » télécharge l'archive, ne remplace que les fichiers qui ont
  changé dans `front/` (`src/`, `views/`, `public/`, `config/`, `content/`), sauvegarde
  avant d'écrire, affiche « Mise à jour en cours » pendant la copie et remet l'ancienne
  version en cas d'échec ;
- jamais touchés : `storage/`, `public/uploads/`, `config/config.local.php` ; un
  `config.php` ou `.htaccess` modifié sur le serveur est gardé ;
- « Revenir à cette version » annule une mise à jour (5 sauvegardes gardées) ;
- vérification automatique toutes les 3 heures par la tâche planifiée : pastille dans le menu.

## Assistant IA (Gemini)

Sans clé, le bot répond avec les réponses rapides réglées dans **Conversion** (et une
recherche par mots-clés dans les produits). Avec une clé API Gemini de Google
(**Assistant IA**, clé créée sur aistudio.google.com), les questions libres sont envoyées au
modèle Gemini choisi (API Generative Language, appel cURL natif, clé dans l'en-tête
`x-goog-api-key`).

Dès que la clé est saisie, la **liste des modèles** est lue en direct chez Google
(`GET /v1beta/models`, modèles capables de discuter) : modèles stables d'abord, puis
préversions, du plus récent au plus ancien. « Automatique » prend le modèle « flash » stable
le plus récent. La liste est gardée 24 h et peut être actualisée d'un clic.

Les consignes envoyées sont construites à partir du contenu du site : l'assistant n'invente
ni prix ni délai et propose toujours le devis, l'appel ou le contact. Si Gemini ne répond pas
(quota, panne), le bot bascule sur les réponses automatiques.

## Message vocal (bouton rouge de la barre CTA)

Sur le modèle de le-digital.com, la barre CTA fixe propose, en plus de « Contactez-nous » et
« Demandez un devis », un bouton à pastille rouge **« Laissez un vocal »** :

1. clic : le navigateur demande l'accès au micro, la barre devient une barre
   d'enregistrement (stop, chrono, ondes sonores, questions-guides toutes les 15 s,
   « Terminer », annuler) ; arrêt automatique à la durée maximale réglée ;
2. réécoute dans un panneau : lecteur audio, transcription faite en direct par le navigateur
   (Chrome, Edge, Safari), résumé par Gemini si une clé est réglée, nom et téléphone
   (obligatoire), « Recommencer » ou « Envoyer » ;
3. le vocal (`POST /api/vocal`) est rangé hors du dossier public (`storage/voices/`), devient
   une demande « Vocal » du back-office (lecteur audio, téléchargement, transcription,
   résumé IA) et part par e-mail avec le fichier audio en pièce jointe (jusqu'à 6 Mo).
   Sans transcription du navigateur, Gemini transcrit le fichier si une clé est réglée.

Textes, questions, durée maximale et consigne de résumé : **Back-office › Conversion**.

## Avis Google

**Back-office › Avis clients** : saisir une clé API Google (Google Cloud, « Places API (New) »
activée), puis chercher la fiche par le **nom de l'entreprise**, son **adresse**, ou en collant
un **lien Google Maps** ou un **lien de recherche Google** ; « C'est ma fiche » la connecte.

- note et nombre d'avis affichés : ceux de la fiche Google (accueil, page Avis, données
  structurées pour les moteurs de recherche) ;
- avis : lus chez Google une fois par jour (tâche planifiée, ou juste après l'envoi d'une page
  si l'hébergement le permet) ; Google n'en donne que 5 par lecture, tous ceux déjà vus sont
  gardés (60 au plus) ; en cas de panne, la dernière version reste affichée ;
- modes : avis Google complétés par les avis saisis (sans doublon), avis Google seuls, ou avis
  saisis seuls ; note minimale des avis Google affichés (4 étoiles par défaut) ;
- page Avis : boutons « Laisser un avis Google » et « Voir tous les avis sur Google ».

## Actualités et Facebook

Les actus créées dans le back-office et les publications importées de la page Facebook
(API Graph, jeton de page) sont mélangées par date, sans aucune distinction visuelle.
Chaque publication importée reçoit une catégorie (par défaut ou par hashtag), ses images
(copiées dans `public/uploads/fb/`) et un extrait. Une actu du site peut être publiée sur la
page à l'enregistrement.

## Origine du contenu

Tout le contenu de `content/` et de `public/media/` vient de renovest.fr (pages, API
WordPress, médiathèque) : textes, titres et descriptions SEO d'origine, 6 produits, 17
actualités, 98 photos de réalisations, 60 photos produits, 10 avis Google. La vidéo du
héros (`renovest-new.mp4`) est réencodée sans piste son (75 Mo → 8 Mo, même cadrage).

Adaptations de la maquette au contenu réel :

- menu réel à 7 entrées (Accueil, Présentation, Nos produits + sous-menu, Nos réalisations,
  Actu, Avis, Contact) : le menu complet s'affiche à partir de 1360 px, burger en dessous ;
- 6 produits réels (la maquette en montrait 7) ; 4 engagements « Notre philosophie » ;
- compteurs issus du texte du site : plus de 30 ans, plus de 15 personnes, 8 poseurs,
  4 commerciaux ;
- les éléments de la maquette sans équivalent sur renovest.fr ne sont pas inventés :
  pas de bloc « Aides 30 % », pas de note fictive (la note est calculée sur les avis
  réels), bandeau des fabricants masqué tant qu'aucun logo n'est ajouté ;
- textes de conversion (bulle, popup, bot) reformulés avec les engagements réels du site
  (« devis détaillé et gratuit », « prise de mesures sur site »…), tous modifiables.
