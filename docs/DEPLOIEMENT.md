# Sochaux Rétro — mise en ligne sur o2switch

Guide pas à pas pour installer le site par FTP, l'ouvrir aux historiens puis basculer
l'adresse www.fcsochauxretro.com de WordPress vers le nouveau site.
Aucun accès SSH n'est nécessaire : tout se fait par FTP et depuis cPanel.

## 1. Prérequis

- Hébergement o2switch (cPanel) et un logiciel FTP (FileZilla par exemple).
- **PHP 8.3** (réglage de la version PHP dans cPanel), extensions : `gd` (avec WebP),
  `curl`, `mbstring`, `intl`, `sodium`, `openssl`, `zip`, `fileinfo`, `json`
  (`dom` et `xml` seulement pour les scripts de reprise WordPress).
- Espace disque : environ 7 Go (photos originales 5 Go, vignettes générées, sauvegardes).
- Aucune base de données : tout est stocké en fichiers JSON.

## 2. Récupérer les fichiers

Le code et les données sont sur GitHub, dans le dépôt `ftholomier/poubelle`,
**branche `claude/sweet-einstein-hawxrw`** (ce n'est pas la branche principale).

1. Sur GitHub, ouvrir le dépôt, choisir cette branche dans la liste des branches, puis
   bouton « Code » › « Download ZIP ». Décompresser l'archive.
2. Le dossier obtenu contient : `app/`, `bin/`, `config/`, `data/`, `docs/`, `public/`,
   `scripts/`, `storage/`, `templates/`. Les deux fichiers `.svg` à la racine et le dossier
   `docs/maquette/` ne sont pas nécessaires sur le serveur.

Les photos originales (≈ 12 700 fichiers, 5 Go) ne sont pas dans le dépôt : elles sont
reprises directement depuis le WordPress actuel, sur le même hébergement (§ 4).

## 3. Envoi par FTP

1. Créer sur le serveur un dossier **hors de `public_html`**, par exemple
   `/home/<compte>/sochauxretro/`, et y envoyer tout le contenu du dossier décompressé
   (en gardant l'arborescence). `data/` compte environ 5 900 petits fichiers : l'envoi
   prend quelques minutes.
2. cPanel › Domaines : la **racine du document** du domaine (d'abord un sous-domaine de
   test, voir § 7) doit être `/home/<compte>/sochauxretro/public`. Seul ce dossier est
   visible depuis Internet ; le code et les données restent hors d'atteinte.
3. HTTPS : certificat AutoSSL de cPanel, puis, dans `public/.htaccess`, retirer le `#`
   devant les deux lignes sous « HTTPS et domaine principal ».
4. Ouvrir le site une première fois : la première page met quelques secondes à s'afficher
   (construction des index), les suivantes sont immédiates.

## 4. Photos originales (une seule fois)

Le script `scripts/wp/media-sync.php` copie les photos utiles depuis le dossier d'envoi de
WordPress (seulement les originaux, pas les miniatures). Sans SSH, on le lance avec une
tâche cron temporaire :

1. cPanel › Tâches Cron › ajouter une tâche toutes les 5 minutes avec la commande :

   ```
   php /home/<compte>/sochauxretro/scripts/wp/media-sync.php --depuis=/home/<compte>/public_html/wp-content/uploads >> /home/<compte>/sochauxretro/storage/media-sync.log 2>&1
   ```

2. Suivre `storage/media-sync.log` avec le gestionnaire de fichiers de cPanel. Deux passages
   ne se chevauchent jamais ; chaque passage reprend là où le précédent s'est arrêté.
3. Quand le journal indique « 0 à télécharger », **supprimer cette tâche cron**.

Les photos absentes du dossier WordPress sont téléchargées depuis l'ancien site, tant qu'il
est en ligne. En cas d'échec, le détail est dans `storage/import/media-sync-erreurs.json`.

## 5. Tâche planifiée du site (indispensable, permanente)

cPanel › Tâches Cron, une ligne toutes les 5 minutes :

```
php /home/<compte>/sochauxretro/bin/console.php cron >/dev/null 2>&1
```

Elle publie les fiches programmées, recalcule les statistiques, traduit, envoie la
newsletter « Ce jour-là », géolocalise les stades et les lieux de naissance, complète la
médiathèque, copie les vignettes des vidéos, indexe l'assistant, synchronise les dons,
régénère le plan du site, sauvegarde et purge les données personnelles anciennes.

Pour vérifier qu'elle fonctionne : Back-office › Tâches planifiées affiche l'heure du dernier
passage de chaque tâche. Si rien n'apparaît après 10 minutes, la commande `php` utilise
probablement une autre version que 8.3 : remplacer `php` par le chemin complet du PHP 8.3
(chez o2switch, en général `/opt/alt/php83/usr/bin/php` ; le support o2switch le confirme).

Après l'installation, environ 900 stades et lieux de naissance restent à placer sur la
carte : la géolocalisation (OpenStreetMap, une requête par seconde) les traite par lots
de 40 toutes les 10 minutes, soit quelques heures. Les lieux non trouvés se corrigent à la
main dans Back-office › Saisons, adversaires, lieux. Les vignettes des 1 077 vidéos sont
copiées par lots de 100 chaque heure.

## 6. Premier accès au back-office

1. Ouvrir `https://<domaine>/admin/premier-acces`.
2. Un code est écrit dans `storage/premier-acces.txt` (lisible avec le gestionnaire de
   fichiers de cPanel) ; le saisir avec votre nom, votre e-mail et un mot de passe.
   Le fichier est supprimé dès que le compte administrateur est créé.
3. Back-office › **Réglages** :
   - Général : adresse du site (`https://www.fcsochauxretro.com`), e-mail de contact ;
     un mot de passe d'accès au site peut être posé pendant les essais ;
   - E-mail (SMTP) : boîte créée dans cPanel (serveur `mail.<domaine>`, port 465 SSL,
     identifiant = adresse complète) — sinon la fonction mail() de PHP est utilisée ;
   - Assistant IA : clé Gemini, bouton « Recharger la liste des modèles », choix du modèle,
     puis Tâches planifiées › « Index sémantique de l'assistant » pour indexer le site ;
   - Traduction : traduction anglaise automatique des fiches (Gemini), relue dans
     Back-office › Traductions EN ;
   - Dons : clés Stripe et PayPal (mode test d'abord, puis production), voir § 8 ;
   - Mentions légales, cookies : à relire et compléter.
4. Back-office › **Utilisateurs** : inviter les historiens (administrateur ou utilisateur).
   Le lien d'invitation part par e-mail et peut aussi être copié.

## 7. Bascule de www.fcsochauxretro.com

1. Installer d'abord sur un sous-domaine de test (ex. `nouveau.fcsochauxretro.com`),
   protégé par la page d'attente (Éditorial › Page d'attente) ou par le mot de passe
   d'accès (Réglages › Général).
2. Vérifier : tableau de bord Qualité, pages légales, un don en mode test, l'assistant IA.
3. Le jour J :
   - sauvegarde complète de WordPress (cPanel › JetBackup) ;
   - racine du document de `www.fcsochauxretro.com` → `/home/<compte>/sochauxretro/public` ;
   - mettre l'adresse définitive dans Réglages › Général ;
   - désactiver la page d'attente et le mot de passe d'accès.
4. Les **5 984 anciennes adresses** WordPress sont redirigées (301) vers les nouvelles,
   ainsi que les anciennes adresses d'images (`/wp-content/uploads/…`).
   Les adresses demandées mais introuvables sont listées dans Back-office › Redirections,
   onglet « Adresses introuvables », avec la fiche la plus proche à rediriger.
5. Google Search Console : soumettre `https://www.fcsochauxretro.com/sitemap.xml`.

Garder le dossier WordPress quelque temps après la bascule (il n'est plus visible, mais
il reste la source des photos d'origine).

## 8. Dons (Stripe et PayPal)

- Stripe : clés publique et secrète ; webhook vers
  `https://www.fcsochauxretro.com/api/dons/stripe/webhook` avec les événements
  `checkout.session.completed`, `checkout.session.async_payment_succeeded`,
  `checkout.session.expired`, `invoice.paid`, `customer.subscription.deleted`,
  `charge.refunded` ; copier le secret de signature dans les réglages.
- PayPal : application REST (identifiant et secret) ; webhook vers
  `https://www.fcsochauxretro.com/api/dons/paypal/webhook` avec les événements
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

- Pour une nouvelle version du code, n'envoyer par FTP que `app/`, `bin/`, `config/`,
  `public/` (sauf `public/media/`), `scripts/` et `templates/`, puis vider le dossier
  `storage/cache/` : tout ce qu'il contient se reconstruit à la visite suivante.
- **Ne jamais renvoyer `data/` ni `storage/`** depuis le dépôt : cela effacerait le
  travail fait dans le back-office.
- Les scripts de `scripts/wp/` servent uniquement à la reprise de WordPress : relancer
  `scripts/wp/import.php` **écrase les fiches reprises**. Ne plus l'utiliser une fois les
  historiens au travail.
