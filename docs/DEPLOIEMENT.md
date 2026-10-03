# Sochaux Rétro — mise en ligne sur o2switch

Guide pas à pas pour installer le site, l'ouvrir aux historiens puis basculer
l'adresse www.fcsochauxretro.com de WordPress vers le nouveau site.

## 1. Prérequis

- Hébergement o2switch (cPanel) avec accès SSH (recommandé) ou gestionnaire de fichiers.
- **PHP 8.3** (cPanel › « Sélectionner une version de PHP »), extensions : `gd` (avec WebP),
  `curl`, `mbstring`, `intl`, `sodium`, `openssl`, `zip`, `fileinfo`, `json`
  (`dom` et `xml` seulement pour les scripts de reprise WordPress).
- Espace disque : environ 7 Go (photos originales 5 Go, vignettes générées, sauvegardes).
- Aucune base de données : tout est stocké en fichiers JSON.

## 2. Installation des fichiers

1. Placer le projet **hors de `public_html`**, par exemple `/home/<compte>/sochauxretro/` :
   - par SSH : `git clone <adresse du dépôt> sochauxretro` puis `git checkout <branche>` ;
   - ou envoyer le dossier par SFTP.
   Le dépôt contient le code et les données éditoriales (`data/` : fiches, médias,
   rubriques, redirections).
2. Récupérer les **photos originales** (≈ 12 700 fichiers, 5 Go, hors dépôt) dans
   `storage/media/originals/`. Le plus simple, tant que l'ancien site est en ligne :
   `php scripts/wp/media-sync.php` sur le serveur (environ une heure ; relancer la commande
   reprend là où elle s'est arrêtée ; `--verifier` contrôle les empreintes des fichiers).
3. Droits d'écriture pour le compte (dossiers 755, fichiers 644) sur `data/`, `storage/`
   et `public/media/`.
4. cPanel › Domaines : la **racine du document** du domaine (ou du sous-domaine de test)
   doit être `/home/<compte>/sochauxretro/public`. Seul ce dossier est exposé.
5. HTTPS : certificat AutoSSL de cPanel, puis décommenter dans `public/.htaccess`
   les deux lignes « HTTPS et domaine principal ».

## 3. Premier accès au back-office

1. Ouvrir `https://<domaine>/admin/premier-acces`.
2. Un code est écrit dans `storage/premier-acces.txt` (lisible avec le gestionnaire de
   fichiers de cPanel) ; le saisir avec votre nom, votre e-mail et un mot de passe.
   Le fichier est supprimé dès que le compte administrateur est créé.
   Variante en SSH : `php bin/console.php admin <e-mail> "<Nom>"`.
3. Back-office › **Réglages** :
   - Général : adresse du site (`https://www.fcsochauxretro.com`), e-mail de contact ;
     un mot de passe d'accès au site peut être posé pendant les essais ;
   - E-mail (SMTP) : boîte créée dans cPanel (serveur `mail.<domaine>`, port 465 SSL,
     identifiant = adresse complète) — sinon la fonction mail() de PHP est utilisée ;
   - Assistant IA : clé Gemini, bouton « Recharger la liste des modèles », choix du modèle,
     puis Tâches planifiées › « Index sémantique de l'assistant » pour indexer le site ;
   - Traduction : traduction anglaise automatique des fiches (Gemini), relue dans
     Back-office › Traductions ;
   - Dons : clés Stripe et PayPal (mode test d'abord, puis production), voir § 6 ;
   - Mentions légales, cookies : à relire et compléter.
4. Back-office › **Utilisateurs** : inviter les historiens (administrateur ou utilisateur).
   Le lien d'invitation part par e-mail et peut aussi être copié.

## 4. Tâche planifiée (indispensable)

cPanel › Tâches Cron, une seule ligne, toutes les 5 minutes :

```
*/5 * * * * php /home/<compte>/sochauxretro/bin/console.php cron >/dev/null 2>&1
```

Vérifier en SSH que `php -v` donne bien la version 8.3 ; sinon utiliser le chemin complet
du PHP 8.3 indiqué par o2switch. La tâche publie les fiches programmées, recalcule les
statistiques, traduit, envoie la newsletter « Ce jour-là », géolocalise les stades et les
lieux de naissance, complète la médiathèque, copie les vignettes des vidéos, indexe l'assistant, synchronise les dons,
régénère le plan du site, sauvegarde et purge les données personnelles anciennes.
L'état de chaque tâche est visible dans Back-office › Tâches planifiées.

Après l'installation, environ 900 stades et lieux de naissance restent à placer sur la
carte : la géolocalisation (OpenStreetMap, une requête par seconde) les traite par lots
de 40 toutes les 10 minutes, soit quelques heures. Les lieux non trouvés se corrigent à la
main dans Back-office › Saisons, adversaires, lieux.

Les vignettes des 1 077 vidéos (YouTube, Dailymotion, Rutube) sont copiées sur le serveur
par lots de 100 chaque heure, ou en une fois avec `php bin/console.php videos` : avant
l'accord du visiteur, le lecteur montre ainsi l'image de la vidéo sans contacter l'hébergeur.

Facultatif, pour accélérer les premières visites : `php bin/console.php images 600`
pré-génère les vignettes des photos (sinon elles sont créées à la première demande).

## 5. Bascule de www.fcsochauxretro.com

1. Installer d'abord sur un sous-domaine de test (ex. `nouveau.fcsochauxretro.com`),
   protégé par la page d'attente ou le mot de passe d'accès (Réglages › Général).
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

## 6. Dons (Stripe et PayPal)

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

## 7. Sauvegardes et sécurité

- Sauvegarde automatique quotidienne dans `storage/backups/` (ZIP : données, réglages
  chiffrés et leur clé, comptes, versions, messages, dons, newsletter, votes, journal ;
  photos le dimanche si l'option est cochée). Téléchargeables dans Back-office ›
  Sauvegardes : en garder régulièrement une copie hors du serveur. o2switch conserve en
  plus ses propres sauvegardes quotidiennes (JetBackup).
- Restauration : décompresser l'archive à la racine du projet.
- `storage/secret.key` chiffre les clés API et mots de passe des réglages : il est inclus
  dans les sauvegardes, ne jamais le diffuser.
- Les pages sont servies avec une politique de sécurité du contenu (CSP) ; un nouveau
  service externe (lecteur vidéo, carte…) doit y être ajouté dans `app/Kernel.php`.

## 8. Mettre à jour le site

- Code : `git pull` (rien d'autre à faire : les caches se reconstruisent seuls).
- Les scripts de `scripts/wp/` servent uniquement à la reprise de WordPress : relancer
  `scripts/wp/import.php` **écrase les fiches reprises** (seules les fiches créées dans le
  back-office sont préservées). Ne plus l'utiliser une fois les historiens au travail.
