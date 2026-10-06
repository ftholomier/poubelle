# Sochaux Rétro — mise en ligne sur o2switch

Guide pas à pas pour installer le musée par FTP sur **musee.fcsochauxretro.com**, l'ouvrir
aux historiens puis au public. La même installation sert aussi le **site de l'association**
sur www.fcsochauxretro.com (section 11), une fois la copie des photos terminée : d'ici là,
www reste l'ancien WordPress. Aucun accès SSH n'est nécessaire : tout se fait par FTP et
depuis cPanel.

## 1. Prérequis

- Hébergement o2switch (cPanel) et un logiciel FTP (FileZilla par exemple).
- **PHP 8.3** : cPanel › Sélectionner une version de PHP. Chez o2switch, **chaque version a
  sa propre liste d'extensions** : après avoir choisi 8.3, onglet Extensions, cocher `mbstring`,
  `intl`, `sodium`, `gd` (avec WebP), `curl`, `openssl`, `zip`, `fileinfo`, `dom`, `ctype`,
  `iconv`, `zlib`, `json`, `xml` (scripts de reprise WordPress) ; conseillées : `opcache`
  (vitesse : sans lui, chaque page relit tout le code et les données calculées, plusieurs Mo ;
  Tâches planifiées › Serveur le signale) et `exif` (sens des photos prises au téléphone). Avec une version trop ancienne ou
  une extension indispensable absente, le site affiche « Réglage du serveur en cours » avec la
  liste de ce qui manque ; une fois connecté, Back-office › Tâches planifiées › Serveur fait
  le point complet.
- Espace disque : environ 7 Go (photos originales 5 Go, vignettes générées, sauvegardes).
- Aucune base de données : tout est stocké en fichiers JSON.

## 2. Récupérer les fichiers

Le code et les données sont sur GitHub, dans le dépôt `ftholomier/poubelle`,
**branche `claude/sweet-einstein-hawxrw`** (ce n'est pas la branche principale).

1. Sur GitHub, ouvrir le dépôt, choisir cette branche dans la liste des branches, puis
   bouton « Code » › « Download ZIP ». Décompresser l'archive.
2. Le dossier obtenu contient : `app/`, `bin/`, `config/`, `data/`, `docs/`, `public/`,
   `scripts/`, `storage/`, `templates/`, `tests/`. Les deux fichiers `.svg` à la racine et
   les dossiers `docs/maquette/` et `tests/` ne sont pas nécessaires sur le serveur.

Les photos originales (≈ 12 700 fichiers, 5 Go) ne sont pas dans le dépôt : elles sont
reprises directement depuis le WordPress actuel, sur le même hébergement (§ 4).

## 3. Envoi par FTP

1. Créer sur le serveur un dossier **hors de `public_html`**, par exemple
   `/home/<compte>/sochauxretro/`, et y envoyer tout le contenu du dossier décompressé
   (en gardant l'arborescence). Pour cette première installation, on envoie **tout**, y
   compris `data/` (les 2 940 fiches) et `storage/` (vide) ; les mises à jour suivantes
   se font autrement (§ 10). `data/` compte environ 5 900 petits fichiers : l'envoi prend
   quelques minutes.
2. cPanel › Domaines › Créer un domaine : le **sous-domaine du musée**
   `musee.fcsochauxretro.com`, dont la **racine du document** est
   `/home/<compte>/sochauxretro/public` (décocher « Partager la racine du document » si
   cPanel le propose). Seul ce dossier est visible depuis Internet ; le code et les données
   restent hors d'atteinte. `<compte>` est l'identifiant cPanel (Répertoire personnel
   `/home/<compte>` dans la colonne d'informations de cPanel).
3. HTTPS : certificat AutoSSL de cPanel, puis, dans `public/.htaccess`, retirer le `#`
   devant les deux lignes sous « HTTPS et domaine principal ».
   L'application du musée (installation sur l'écran d'accueil, lecture hors connexion,
   notifications) ne fonctionne qu'en HTTPS : les navigateurs refusent le service worker sur
   une adresse en http://. Les notifications partent par la tâche planifiée (ci-dessous) ;
   PHP doit avoir les extensions « openssl » et « curl » (Système › Réglages › vérification du
   serveur le signale sinon).
4. Ouvrir le site une première fois : il est **fermé au public dès l'installation** (page
   d'attente, rien n'est indexé par les moteurs de recherche) ; aller directement à
   `/admin/premier-acces` (§ 6). La première page du site met quelques secondes à s'afficher
   (construction des index), les suivantes sont immédiates.

## 4. Photos originales (une seule fois)

La médiathèque elle-même (12 735 médias : légendes, crédits, dates, liens avec les fiches)
arrive avec `data/media.json`. Il ne manque que les **fichiers originaux** (4,9 Go), qui ne
sont pas dans le dépôt : le script `scripts/wp/media-sync.php` les reprend depuis le
WordPress actuel, seulement les originaux (pas les miniatures de WordPress), sous les mêmes
chemins (`2024/12/photo.jpg` → `storage/media/originals/2024/12/photo.jpg`), avec un contrôle
d'empreinte. Sans SSH, on le lance avec une tâche cron temporaire :

1. cPanel › Tâches Cron › ajouter une tâche toutes les 5 minutes avec la commande :

   ```
   /opt/alt/php83/usr/bin/php /home/<compte>/sochauxretro/scripts/wp/media-sync.php --depuis=/home/<compte>/public_html/wp-content/uploads >> /home/<compte>/sochauxretro/storage/media-sync.log 2>&1
   ```

   - WordPress sur le même hébergement : `--depuis` copie les fichiers de disque à disque
     (quelques minutes). Le dossier est celui de la racine du document de
     www.fcsochauxretro.com (cPanel › Domaines), suivi de `/wp-content/uploads` : en général
     `/home/<compte>/public_html/wp-content/uploads` (on y voit des dossiers 2023, 2024…).
   - WordPress ailleurs : retirer `--depuis=…` ; les fichiers sont téléchargés depuis
     www.fcsochauxretro.com (4,9 Go, quelques heures, en plusieurs passages).
   - `/opt/alt/php83/usr/bin/php` est le PHP 8.3 « ligne de commande » d'o2switch. La simple
     commande `php` des tâches cron y lance le PHP du site web : le journal n'affiche alors
     que « Status: 500 Internal Server Error » (ou « A lancer avec le PHP en ligne de
     commande »).
2. Suivre `storage/media-sync.log` avec le gestionnaire de fichiers de cPanel. Deux passages
   ne se chevauchent jamais ; chaque passage reprend là où le précédent s'est arrêté.
3. Quand le journal indique « 0 à télécharger », **supprimer cette tâche cron**.

Le WordPress de www.fcsochauxretro.com reste en place (site de l'association) : rien ne
presse, la copie peut être relancée à tout moment. Les photos absentes du dossier WordPress
sont téléchargées depuis l'ancien site ; en cas d'échec, le détail est dans
`storage/import/media-sync-erreurs.json` (relancer la tâche suffit souvent).

Tant que la copie n'est pas terminée, chaque image manquante est remplacée sur le site par
un cadre beige avec un pictogramme, et le back-office le rappelle : tâche « Copier les photos originales sur le serveur » dans le
tableau de bord (administrateurs) et alerte « Photos originales absentes du serveur » dans
Qualité › Adresses et médias. Les deux disparaissent d'elles-mêmes dans la demi-heure qui suit
la fin de la copie (tâche planifiée du § 5).

Ensuite, tout est automatique :
- les **vignettes** WebP (de 160 à 1 600 pixels de large) sont créées à la première
  visite de chaque image, puis servies directement par Apache (`public/media/`, environ
  2 Go à terme). Les préparer à l'avance n'est pas nécessaire ; pour le faire quand même :
  `/opt/alt/php83/usr/bin/php /home/<compte>/sochauxretro/bin/console.php images 800` (puis 480 et 1200), en tâche
  cron temporaire ;
- les **vignettes des vidéos** (YouTube, Dailymotion…) sont copiées par la tâche planifiée ;
- les **anciennes adresses d'images** de WordPress (`/wp-content/uploads/…`, y compris
  les miniatures) sont redirigées vers les nouvelles ;
- les **nouvelles photos** s'ajoutent dans Back-office › Médiathèque (JPG, PNG, GIF, WebP,
  PDF, 25 Mo au plus) ; les photos reçues par « Contribuer » s'y versent en un clic.

## 5. Tâche planifiée du site (indispensable, permanente)

cPanel › Tâches Cron, une ligne toutes les 5 minutes :

```
/opt/alt/php83/usr/bin/php /home/<compte>/sochauxretro/bin/console.php cron >/dev/null 2>&1
```

Elle publie les fiches programmées, recalcule les statistiques, traduit, envoie la
newsletter « Ce jour-là » et les notifications de l’appli du musée, géolocalise les stades et les lieux de naissance, complète la
médiathèque, copie les vignettes des vidéos, indexe l'assistant, synchronise les dons,
régénère le plan du site, sauvegarde et purge les données personnelles anciennes.

Pour vérifier qu'elle fonctionne : Back-office › Tâches planifiées affiche l'heure du dernier
passage de chaque tâche. Si rien n'apparaît après 10 minutes, vérifier le chemin du PHP :
`/opt/alt/php83/usr/bin/php` est le PHP 8.3 « ligne de commande » d'o2switch (la simple
commande `php` des tâches cron y lance le PHP du site web, qui ne convient pas ; le support
o2switch confirme le chemin au besoin). Le tableau de bord rappelle aussi la tâche si elle
ne passe plus.

Après l'installation, environ 900 stades et lieux de naissance restent à placer sur la
carte : la géolocalisation (OpenStreetMap, une requête par seconde) les traite par lots
de 40 toutes les 10 minutes, soit quelques heures. Les lieux non trouvés se corrigent à la
main dans Back-office › Saisons, adversaires, lieux. Les vignettes des 1 077 vidéos sont
copiées par lots de 100 chaque heure.

## 6. Premier accès au back-office

1. Ouvrir `https://musee.fcsochauxretro.com/admin/premier-acces`.
2. Un code est écrit dans `storage/premier-acces.txt` (lisible avec le gestionnaire de
   fichiers de cPanel) ; le saisir avec votre nom, votre e-mail et un mot de passe.
   Le fichier est supprimé dès que le compte administrateur est créé. Le tableau de bord
   signale en tête tout réglage du serveur qui manquerait (« Régler le serveur »).
3. Back-office › **Réglages** :
   - Général : **adresse du site** `https://musee.fcsochauxretro.com` (valeur par défaut,
     à vérifier) : les liens des e-mails (invitations, mot de passe oublié, newsletter)
     l'utilisent ; si elle ne correspond pas à l'adresse ouverte, le tableau de bord et
     l'écran Utilisateurs le signalent. « Adresse affichée sur les images de partage » :
     `musee.fcsochauxretro.com`. Puis l'e-mail de contact ;
   - E-mail (SMTP) : boîte créée dans cPanel (serveur `mail.<domaine>`, port 465 SSL,
     identifiant = adresse complète) — sinon la fonction mail() de PHP est utilisée ;
   - Assistant IA : clé Gemini, bouton « Recharger la liste des modèles », choix du modèle,
     puis Tâches planifiées › « Index sémantique de l'assistant » pour indexer le site ;
   - Traduction : traduction anglaise automatique des fiches (Gemini), relue dans
     Back-office › Traductions EN ;
   - Correcteur : vérification orthographique de fond (300 appels à Gemini par jour par
     défaut, soit environ deux semaines pour un premier passage complet du musée). Pour
     tout vérifier d'un coup, lancer une fois en SSH (ou en tâche cron temporaire)
     `/opt/alt/php83/usr/bin/php /home/<compte>/sochauxretro/bin/console.php correcteur` ;
   - Fiches audio : bouton « Écouter » (gratuit, actif par défaut) ; pour la voix IA, choisir
     la voix puis Système › Fiches audio › « Essayer d'abord sur 20 fiches », écouter, et
     lancer toutes les fiches. Le dossier `public/media/audio/` doit être inscriptible (comme
     `public/media/`) ; les voix sont enregistrées en MP3 (par ffmpeg si l'hébergement le
     fournit, sinon par l'encodeur MP3 du site, en PHP : rien à installer) ;
   - Coûts IA : nom de la personne qui paie la facture Google (imprimé sur le relevé
     mensuel que l'association rembourse), taux de change de sa banque, budget mensuel
     éventuel ; la dépense se suit dans Système › Coûts IA ;
   - Dons : clés Stripe et PayPal (mode test d'abord, puis production), voir § 8 ;
   - Mentions légales, cookies : à relire et compléter.
4. Back-office › **Utilisateurs** : inviter les historiens (administrateur ou utilisateur).
   Le lien d'invitation part par e-mail et peut aussi être copié.

## 7. Ouverture du musée

1. **Pendant la préparation**, le site est fermé au public dès l'installation : page d'attente
   (Éditorial › Page d'attente, active par défaut) ou mot de passe d'accès (Réglages ›
   Général). Les visiteurs ne voient qu'elle, la page ne propose aucun lien vers le
   back-office, et rien n'est indexé (robots.txt fermé, en-tête « noindex » sur toutes les
   réponses). Les membres de l'équipe **connectés** au back-office (`/admin`) voient le vrai
   site, avec un bandeau jaune « Site fermé au public » en haut de chaque page ; déconnectés,
   ils retrouvent la page d'attente.
2. **Vérifier** : Pilotage › Qualité › « Contrôler maintenant » (aucune nouvelle anomalie
   attendue, hormis les photos tant que leur copie n'est pas terminée), pages légales, un
   don en mode test, l'assistant IA, une invitation envoyée à soi-même et « Mot de passe
   oublié » (e-mails), le PDF d'un match et celui d'un joueur. Si possible, le test de fumée
   avec un compte (`SR_BASE=https://musee.fcsochauxretro.com SR_EMAIL=… SR_PASSWORD=… node
   tests/smoke.js`, voir `docs/CONTROLE-2026-10.md`, § 5) : il se connecte d'abord et parcourt
   tout le site et le back-office comme l'équipe, même fermé au public. Tous les autres essais
   automatiques ont tourné sur le serveur de développement.
3. **Le jour de l'ouverture** :
   - vérifier que la copie des photos est terminée (§ 4 : « 0 à télécharger ») ;
   - adresse du site `https://musee.fcsochauxretro.com` dans Réglages › Général (par
     défaut), et « Masquer le site aux moteurs de recherche » décoché ;
   - décocher la page d'attente (et vider le mot de passe d'accès s'il a servi) ;
   - « Contrôler maintenant », puis le test de fumée des pages publiques
     (`SR_BASE=https://musee.fcsochauxretro.com node tests/smoke.js`) ;
   - Google Search Console : ajouter la propriété `https://musee.fcsochauxretro.com` et
     soumettre `https://musee.fcsochauxretro.com/sitemap.xml`.
4. **Le teaser vidéo** (1 min 55) apparaît sur l'accueil à l'ouverture (Éditorial › Accueil
   pour le masquer) ; il peut aussi être montré plus tôt sur la page d'attente (case
   « Afficher le teaser vidéo », décochée par défaut ; « Aperçu avec le teaser » le montre à
   l'équipe sans l'activer). Tant qu'il n'est montré nulle part au public, la vidéo reste
   introuvable par une adresse directe.
5. **www.fcsochauxretro.com** devient le site de l'association, servi par la même
   installation (section 11) ; il met en avant le musée sur toutes ses pages. L'ancien
   WordPress n'ayant jamais été public ni indexé, aucune redirection n'est indispensable ;
   si d'anciennes adresses avaient circulé (`/2015/03/…`, `/matchs/…`), le site de
   l'association les envoie de lui-même au musée (301), qui les reconnaît grâce à ses 5 984
   redirections intégrées.

Garder le dossier WordPress tant que la copie des photos n'est pas terminée et vérifiée
(il en est la source).

## 8. Dons (Stripe et PayPal)

- Stripe : clés publique et secrète ; webhook vers
  `https://musee.fcsochauxretro.com/api/dons/stripe/webhook` avec les événements
  `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
  `checkout.session.expired`, `invoice.paid`, `customer.subscription.deleted`,
  `charge.refunded` ; copier le secret de signature dans les réglages.
- PayPal : application REST (identifiant et secret) ; webhook vers
  `https://musee.fcsochauxretro.com/api/dons/paypal/webhook` avec les événements
  `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.REFUNDED`, `PAYMENT.SALE.COMPLETED`,
  `PAYMENT.SALE.REFUNDED`, `BILLING.SUBSCRIPTION.ACTIVATED`, `BILLING.SUBSCRIPTION.CANCELLED`,
  `BILLING.SUBSCRIPTION.EXPIRED`, `BILLING.SUBSCRIPTION.SUSPENDED` ; copier l'identifiant
  du webhook dans les réglages.
- Reçus fiscaux : développés mais désactivés ; à activer seulement si l'association y a droit.

## 9. Sauvegardes et sécurité

- Sauvegarde automatique quotidienne dans `storage/backups/` (ZIP : données, réglages
  chiffrés et leur clé, comptes, versions, messages, dons, newsletter, votes, journal ;
  photos le dimanche si l'option est cochée). Téléchargeables dans Back-office ›
  Sauvegardes : en garder régulièrement une copie hors du serveur. o2switch conserve en
  plus ses propres sauvegardes quotidiennes (JetBackup).
- Restauration : décompresser l'archive à la racine du projet (par FTP).
- `storage/secret.key` chiffre les clés API et mots de passe des réglages : il est inclus
  dans les sauvegardes, ne jamais le diffuser.
- Les pages sont servies avec une politique de sécurité du contenu (CSP) ; un nouveau
  service externe (lecteur vidéo, carte…) doit y être ajouté dans `app/Kernel.php`.

## 10. Mettre à jour le site après la mise en ligne

Dès que les historiens travaillent dans le back-office, **les dossiers `data/` et
`storage/` du serveur font foi** : ils contiennent leurs saisies, les comptes, les
réglages et les dons.

- Pour une nouvelle version du code : **Système › Mises à jour** (administrateurs). L'écran
  compare la version installée à la dernière version de la branche GitHub réglée
  (Réglages › Mises à jour), liste les changements et les applique en un clic : il ne
  remplace que les fichiers du code qui ont changé (`app/`, `bin/`, `config/`, `scripts/`,
  `templates/`, `public/` sauf `public/media/`), ajoute les nouveaux libellés anglais sans
  toucher aux traductions du serveur, garde un `public/.htaccess` réglé à la main, vide les
  caches (sauf `storage/cache/correcteur/`) et sauvegarde d'abord les fichiers remplacés
  (« Revenir à cette version »). Vérification automatique toutes les 3 heures ; le tableau
  de bord signale une nouvelle version. La toute première fois, l'écran lui-même doit être
  envoyé par FTP (ou toute version qui le contient).
- **Synchronisation** : chaque vérification compare aussi le code du serveur, fichier par
  fichier, à la dernière version de GitHub (empreintes Git, rien n'est téléchargé ; les fins
  de ligne converties par un logiciel FTP en mode texte ne comptent pas). Après un envoi par
  FTP, « Vérifier maintenant » reconnaît la version si tout est identique, ou liste les
  fichiers oubliés ou différents : « Synchroniser avec GitHub » les remplace en un clic.
- À défaut, par FTP : n'envoyer que ces mêmes dossiers, puis vider `storage/cache/` **sauf le
  dossier `correcteur/`** (réponses du correcteur déjà payées) : le reste se reconstruit à la
  visite suivante.
- **Ne jamais renvoyer `data/` ni `storage/`** depuis le dépôt : cela effacerait le
  travail fait dans le back-office.
- Les scripts de `scripts/wp/` servent uniquement à la reprise de WordPress : relancer
  `scripts/wp/import.php` **écrase les fiches reprises**. Ne plus l'utiliser une fois les
  historiens au travail.

## 11. Site de l'association sur www.fcsochauxretro.com

Le site de l'association (présentation, actions, actualités, agenda, adhésion en ligne,
bénévolat, contact) fait partie du même code que le musée : **rien d'autre à installer**,
et chaque mise à jour depuis GitHub met à jour les deux sites. Il se pilote dans le
back-office, pavé **Site de l'association** (administrateurs seulement).

1. **Préparer sans attendre** : le site est fermé au départ, derrière **sa propre page
   d'attente** (pavé › Page d'attente ; celle du musée est indépendante). Depuis le
   back-office, « Aperçu complet » montre le site entier à l'adresse
   `https://musee.fcsochauxretro.com/apercu-association/`, et « Aperçu de la page d'attente »
   ce que verront les visiteurs, même tant que www mène encore au WordPress. Parcourir la
   liste « À vérifier » du tableau de bord (tarifs d'adhésion, e-mail de réception, mentions
   légales, contenus d'exemple).
2. **Quand la copie des photos est terminée et vérifiée** (section 4), faire mener
   `www.fcsochauxretro.com` **et** `fcsochauxretro.com` au même dossier que le musée,
   `/home/<compte>/sochauxretro/public` :
   - si le domaine est un domaine ajouté (cPanel › **Domaines**, bouton « Gérer ») : changer
     sa **racine du document** pour ce dossier ;
   - si c'est le **domaine principal** du compte (racine `public_html`, non modifiable dans
     cPanel) : renommer d'abord `public_html` en `public_html-wordpress` (Gestionnaire de
     fichiers ; c'est l'ancien WordPress, gardé tel quel), puis créer une tâche cron d'une
     minute avec la commande
     `ln -s /home/<compte>/sochauxretro/public /home/<compte>/public_html`,
     et la supprimer dès que le lien `public_html` apparaît (ou demander ce lien au support
     o2switch).

   Vérifier ensuite le certificat HTTPS des deux noms (cPanel › SSL/TLS Status, « Run
   AutoSSL »).
3. Ouvrir `https://www.fcsochauxretro.com/` : la page d'attente du site de l'association
   s'affiche (et `fcsochauxretro.com` redirige vers www). Les anciennes adresses du
   WordPress qui existent au musée y sont redirigées automatiquement.
4. Dans le tableau de bord du pavé : **Ouvrir le site au public**. Le site devient indexable ;
   soumettre `https://www.fcsochauxretro.com/sitemap.xml` dans Google Search Console
   (propriété `https://www.fcsochauxretro.com`).
5. Le dossier du WordPress (`public_html-wordpress` ou l'ancienne racine) peut alors être
   archivé (sauvegarde) puis supprimé.

Adresses : l'adresse du site et les noms redirigés vers lui se règlent dans le pavé
(Réglages du site) ; par défaut `https://www.fcsochauxretro.com` et `fcsochauxretro.com`.

**Adhésion en ligne** : elle utilise les clés Stripe et PayPal des dons (section 8), sans
nouveau webhook : les adresses `…/api/dons/stripe/webhook` et `…/api/dons/paypal/webhook` du
musée confirment aussi les cotisations. Sans paiement en ligne, le site propose le chèque et
un bulletin à imprimer ; un lien HelloAsso peut être ajouté dans les réglages du pavé.

**Données** : textes modifiés dans le pavé dans `data/vitrine/` ; adhésions et propositions de
bénévolat dans `storage/vitrine/` (comprises dans les sauvegardes, jamais dans le dépôt,
effacées automatiquement selon les durées annoncées sur la page Confidentialité).

## Avant l'ouverture : préparer toutes les vignettes
Les vignettes manquantes sont fabriquées à la première visite (deux à la fois au plus) ; au
lancement, mieux vaut qu'elles existent déjà. En SSH, depuis le dossier du site :
`php bin/console.php images all` (toutes les largeurs ; relançable, les fichiers prêts sont
sautés ; compter environ une heure et 3 à 4 Go). Une largeur seule : `images 800`, ou une liste :
`images 480,800,1200`. Détails et autres étapes : `docs/PLAN-VITESSE-LANCEMENT.md`.

Le **cache des pages** (Réglages › Général, activé par défaut) resert aux visiteurs anonymes les
pages déjà calculées ; il se met à jour seul après chaque enregistrement et chaque mise à jour.
En cas de doute sur une page restée ancienne : `php bin/console.php pages-vider`.

