# LE-DIGITAL.com — site + back-office

Site vitrine de Frédéric Tholomier, développé en **PHP natif** (aucune dépendance, aucun framework),
**sans base de données** : tous les contenus et toutes les données sont stockés en **JSON**.
HTML, CSS et JavaScript sont écrits à la main, fidèles à la maquette « Refonte complète le-digital.com ».

## Ce qui est inclus

**Site public** (11 pages + article de blog, desktop et mobile)

- Accueil, Sur mesure + IA, Formation, L'accompagnement, Mes solutions, Qui suis-je, Avis, Blog (liste + article),
  On prend RdV ?, Zones (Besançon, Montbéliard, Belfort, Dijon), Mentions légales / Accessibilité.
- Toutes les interactions de la maquette fonctionnent :
  - conversation animée du haut de page, mots qui défilent, compteurs, apparitions au défilement ;
  - « le bureau qui se range » au défilement ;
  - avant / après, programme en accordéon, formats de formation, 4 formules, quiz « quelle formule ? » ;
  - **démo IA « Ton devis en 10 secondes »**, **analyse express de l'activité** (lit réellement la page d'accueil du site indiqué),
    **« Explique-moi comme à un débutant »** (réponses mises en cache) ;
  - simulateur de gain de temps (mémorisé et transmis au formulaire de contact) ;
  - **assistant IA** (barre du bas) avec brief transmis à Fred ;
  - **vocal pour Fred** : enregistrement, visualisation, transcription, résumé IA, envoi (fichier audio reçu par e-mail) ;
  - **prise de rendez-vous réelle** : créneaux en temps réel, confirmation e-mail + fichier agenda, rappel SMS/e-mail la veille,
    lien d'annulation ;
  - formulaire de contact, demande de rappel, filtres Avis / Blog, secrets de la formule (survol ou toucher) ;
  - **pop-up de sortie** : au moment de quitter le site (souris vers le haut, remontée rapide sur mobile), une proposition
    de café (RdV, rappel, vocal) ; une fois par visite, jamais après une prise de contact ni sur la page RdV.
    Textes et réglages dans *Contenus › Commun* ; aperçu avec `?apercu-popup` à la fin de l'adresse.
- Emplacements « à fournir » de la maquette affichés automatiquement tant que le contenu réel est vide.
- Mobile soigné : menu repliable, barre « On prend RdV ? » fixée en bas, zones tactiles, aucune barre de défilement horizontale.

**Back-office** (`/admin`)

- Tableau de bord + checklist de mise en ligne.
- Demandes (formulaire, RdV, rappels, vocaux avec lecteur audio, briefs de l'assistant), statuts, notes, export CSV.
- Rendez-vous : agenda, annulation, disponibilités (jours, créneaux, congés, durée, prévenance), **flux iCal privé**
  à ajouter dans Google Agenda / Apple Calendrier.
- **Contenus** : éditeur générique de toutes les pages (textes, listes, boutons, images…), réinitialisation possible.
- **Avis clients** : saisis à la main, **importés depuis Google** (synchronisation quotidienne) et/ou **widgets d'un service d'avis**
  (codes HTML/JS à coller : carrousel sur toutes les pages, mur sur la page Avis) — voir plus bas.
- **Solutions** : afficher / masquer chaque solution (Formaliance masquée par défaut), changer l'ordre, en créer de
  nouvelles (nom, accroche, présentation, points forts, capture, logo, thème clair ou sombre, liens). Côté site, la page
  « Mes solutions » est une mosaïque de cartes (image, nom, accroche, points forts) qui mènent chacune à sa page
  `/mes-solutions/<nom>` (bloc complet + « Découvrir aussi »), accessible par le menu « Mes solutions ». Formulaire de
  contact, plan du site, titre Google et assistant IA suivent automatiquement. Une solution masquée reste prévisualisable par l'administrateur
  connecté. Les textes du bloc Intensiti restent dans *Contenus › Mes solutions*.
- Articles de blog (éditeur visuel) et **posts LinkedIn** sur la page Blog (*Blog › Posts LinkedIn* : code d'intégration
  d'une application externe à coller, au-dessus, sous ou à la place des articles), médiathèque (conversion WebP + tailles
  responsive automatiques).
- **IA & clés API** : clés chiffrées pour Anthropic (Claude), OpenAI, Google Gemini, Mistral, Groq, OpenRouter, DeepSeek, xAI ;
  **liste des modèles récupérée en direct** auprès de chaque fournisseur ; modèle par défaut + modèle par fonctionnalité,
  consignes (prompts) modifiables, test en direct, plafond quotidien, suivi de consommation.
- Paramètres : identité, coordonnées, e-mails (mail() ou SMTP), SMS (Brevo ou Twilio), SEO, cache, redirections 301,
  tâches planifiées, **sauvegarde ZIP** complète.
- **Mises à jour en un clic** depuis GitHub (voir plus bas).

## Structure

```
le-digital/
├── public/            ← SEUL dossier exposé au web (racine du site)
│   ├── index.php      ← point d'entrée unique
│   ├── assets/        ← CSS, JS, polices auto-hébergées, images optimisées
│   └── uploads/       ← images envoyées depuis le back-office
├── app/               ← code PHP (hors web)
│   ├── bootstrap.php, helpers.php, cron.php
│   ├── lib/           ← moteur (stockage JSON, IA, e-mails, RdV…) et contrôleurs
│   ├── views/         ← gabarits du site et du back-office
│   └── defaults/      ← contenus d'origine (textes de la maquette, articles de démarrage)
└── storage/           ← données JSON (hors web, créé au premier lancement)
    ├── content/       ← textes des pages, paramètres, réglages IA
    ├── data/          ← demandes, rendez-vous, avis, articles, médias, vocaux
    ├── secrets/       ← clés API chiffrées + clé de chiffrement
    ├── cache/, logs/, sessions/
```

## Prérequis

- PHP **8.1+** avec les extensions `curl`, `json`, `mbstring`, `gd` (avec WebP), `fileinfo`, `sodium` (ou `openssl`) ;
  `zip` pour la sauvegarde.
- Droits d'écriture sur `storage/` et `public/uploads/`.

## Installation

### Apache (recommandé)

1. Copier le dossier `le-digital/` sur le serveur.
2. Faire pointer la **racine web du domaine sur `le-digital/public`** (le `.htaccess` fourni gère la réécriture d'URL, le cache et la compression).
   Si ton hébergement ne permet pas de changer la racine, place le contenu de `le-digital/` à la racine : le `.htaccess`
   de premier niveau redirige vers `public/` et bloque l'accès à `app/` et `storage/`.
3. Ouvrir `https://ton-domaine/admin` → création du compte administrateur (une seule fois).
4. Dans le back-office : renseigner les paramètres (URL du site, numéro, LinkedIn, e-mails), coller une clé API d'IA,
   puis suivre la checklist du tableau de bord.
5. Activer HTTPS (décommenter la redirection dans `public/.htaccess`).

### Nginx

```nginx
server {
    root /var/www/le-digital/public;
    index index.php;
    client_max_body_size 16m;
    location / { try_files $uri /index.php?$query_string; }
    location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $document_root/index.php; fastcgi_pass unix:/run/php/php8.3-fpm.sock; }
    location ~* \.(css|js|woff2|webp|png|jpe?g|svg|gif)$ { expires 1y; add_header Cache-Control "public, immutable"; }
    location ^~ /uploads/ { location ~ \.php$ { deny all; } }
}
```

### En local

```bash
php -S localhost:8000 -t public public/index.php
```

## Tâche planifiée (rappels, avis Google, mises à jour)

À lancer **toutes les heures** (rappels de RdV à partir de 17 h la veille, avis Google rafraîchis une fois par jour,
recherche de mise à jour toutes les 3 h) :

```
0 * * * * php /chemin/vers/le-digital/app/cron.php
```

Sans accès SSH : appeler toutes les heures l'URL secrète affichée dans *Paramètres › Tâches planifiées*.

## IA

- Sans clé, le site fonctionne avec des réponses de secours (celles de la maquette).
- Avec une clé : *IA & clés API* → coller la clé → elle est vérifiée et la liste des modèles est chargée en direct.
- Claude : appel à l'API Messages (`anthropic-version: 2026-10-21`), effort de réflexion réglable (faible par défaut,
  pour des réponses rapides sur le site) et bascule automatique sur un modèle de repli en cas de refus pour les modèles
  qui le permettent.
- Protection contre les abus : jeton signé par page, vérification d'origine, limitation par IP, plafond quotidien d'appels.

## Avis Google

*Avis clients › Avis Google*, deux modes au choix :

- **Simple — clé API Google Places** : note globale, nombre d'avis et les 5 avis que Google juge les plus pertinents
  (limite de Google). Clé créée dans Google Cloud (API « Places API (New) »), établissement retrouvé par son nom.
- **Complet — Google Business Profile** : tous les avis et les réponses, via une connexion Google (OAuth).
  Demande d'activer les API Business Profile et l'accès accordé par Google.

Les avis importés sont rafraîchis chaque jour (tâche planifiée) ou à la demande. Texte, note et auteur restent ceux de Google ;
thème, activité affichée, mise en avant et publication restent réglables et sont conservés d'une synchro à l'autre.
Sur le site : logo Google, photo et lien de l'auteur, réponse de Fred, « Lire la suite », badge « ★ 4,9 · 37 avis Google »
et lien « Laisser un avis Google ». (Ils ne sont pas repris dans les données structurées, conformément aux règles de Google.)

**Widgets d'un service d'avis** (*Avis clients › Widgets à coller*) : deux codes fournis par le service (Trustindex, Elfsight,
Avis Vérifiés…), insérés tels quels :
- **carrousel** : sur toutes les pages, juste au-dessus du pied de page (fond blanc) ou dans le pied de page au-dessus
  de la citation (fond sombre). Sans carrousel, le pied de page affiche la note Google et des extraits d'avis ;
- **mur d'avis** : sur la page Avis (avant, après ou à la place des avis du site) et/ou sur l'accueil. Sur la page Avis,
  le mur remplace le carrousel.

## Mises à jour en un clic (GitHub)

*Back-office › Mises à jour* compare le code du serveur à la dernière version du dossier du site sur GitHub
(dépôt, branche et dossier réglables ; jeton « fine-grained » en lecture seule conseillé, obligatoire si le dépôt est privé).

- Liste des changements publiés, puis **« Mettre à jour maintenant »** : seuls les fichiers modifiés sont téléchargés,
  vérifiés (empreinte Git) puis mis en place ; les fichiers supprimés sur GitHub le sont aussi.
- Remplacés : `app/`, `public/` (sauf `public/uploads/`), `.htaccess`, `README.md`. **Jamais touchés** : `storage/`
  (contenus, demandes, avis, clés…) et `public/uploads/`. Un `.htaccess` modifié sur le serveur est conservé.
- Sauvegarde automatique avant chaque mise à jour (5 dernières gardées) et **« Revenir à cette version »** en un clic ;
  le site affiche « mise à jour en cours » pendant les quelques secondes de copie.
- Un site envoyé par FTP identique à la version GitHub est reconnu automatiquement.
- Les textes jamais retouchés dans le back-office suivent les mises à jour (seules les modifications faites dans le
  back-office sont stockées dans `storage/content`).

## Sécurité

- Code et données hors de la racine web ; sessions admin isolées ; mots de passe hachés ; jeton CSRF sur toutes les actions ;
  limitation des tentatives de connexion.
- Clés API et mots de passe SMTP chiffrés au repos (libsodium).
- Médias ré-encodés à l'envoi (aucun script exécutable dans `uploads/`), articles nettoyés (liste blanche HTML),
  analyse de site protégée contre les requêtes vers des adresses internes.
- Aucun cookie côté visiteurs (seul le back-office utilise une session), polices auto-hébergées : pas d'appel à des services tiers
  (sauf, si tu les actives, les photos des auteurs d'avis Google et les widgets d'avis collés).

## Sauvegarde

*Paramètres › Sauvegarde* télécharge un ZIP contenant `storage/content`, `storage/data`, `storage/secrets` et `public/uploads`.
Pour restaurer : replacer ces dossiers.
