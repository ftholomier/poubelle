# Suisse Immo — site de recrutement (refonte)

Refonte complète du site `recrutement.suisse-immo.fr` en **page de capture / tunnel de vente**,
avec back-office intégré. Aucune base de données : tout est stocké en JSON.

> Le contenu éditorial reprend celui du site existant (métier, missions, compétences, réseau,
> avis Google, mentions légales, actualités), réécrit et réorganisé pour la conversion.

---

## 1. Stack

| Élément | Choix |
|---|---|
| Serveur | PHP 8.1+ natif, sans framework ni Composer |
| Données | Fichiers JSON dans `/data` (écriture atomique + verrou) |
| Front | HTML5 / CSS3 / JavaScript vanilla — zéro dépendance runtime |
| API | JSON interne (`/api/*`) pour le tunnel, le simulateur et la mesure |
| Polices | Bricolage Grotesque + Inter, **auto-hébergées** en woff2 variable (licence OFL) |
| E-mails | Client SMTP natif (STARTTLS / TLS implicite / AUTH), repli sur `mail()` |

**Aucun appel réseau tiers, sans exception** : pas de tracker, pas de CDN, pas de Google Fonts,
pas de cookie publicitaire. Une page du site ne contacte que son propre domaine — ce qui règle
aussi le transfert de l'adresse IP des visiteurs vers un tiers (CNIL / arrêt de Munich, 2022).

## 2. Arborescence

```
public/                   ← racine web (à pointer par le vhost)
  index.php               contrôleur frontal + table de routage
  .htaccess               réécriture, en-têtes de sécurité, cache, gzip
  assets/css/app.css      design system du site public
  assets/css/admin.css    design system du back-office
  assets/js/app.js        animations, simulateur, pop-in, formulaires AJAX
  assets/js/funnel.js     tunnel de candidature en 4 étapes
  assets/js/admin.js      champs répétables, garde-fou, slug auto, console du bot
  assets/js/bot.js        widget de conversation
  assets/fonts/           polices variables woff2 + licences (LISEZ-MOI.txt)
  assets/img/             logo et favicon (SVG), visuel de partage et icônes (PNG)

app/                      ← code applicatif (hors racine web)
  bootstrap.php           chargement + installation au premier lancement
  config.php              constantes, fuseau, limites d'upload
  Router.php              routeur à motifs `{param}`
  Store.php               persistance JSON atomique
  ErrorHandler.php        journalisation et présentation des erreurs
  PasswordReset.php       réinitialisation de mot de passe par lien e-mail
  Housekeeping.php        purge quotidienne des données échues
  Smtp.php                client SMTP sans dépendance
  Security.php            session, CSRF, limitation de débit, authentification
  Icons.php               icônes SVG en ligne
  Mailer.php              envoi + journalisation des e-mails
  Analytics.php           mesure maison du tunnel
  Bot.php                 assistant Gemini : base de connaissances, sélection, appel API
  DocText.php             extraction de texte (TXT, MD, CSV, HTML, JSON, DOCX, PDF)
  ContentSchema.php       schéma du contenu éditable (pilote le back-office)
  install.php             données de démarrage, compte admin, mise à niveau des réglages
  seed/                   contenu, réglages et articles par défaut
  Controllers/            SiteController · ApiController · AdminController
  Views/                  layout, partials, pages, admin

data/                     ← données d'exécution, jamais versionnées
  content.json  settings.json  posts.json
  applications.json  leads.json  maillog.json  users.json
  password-resets.json    demandes de réinitialisation (jetons hachés)
  bot.json  bot-docs.json  bot-chats.json
  events/                 audience, un fichier JSONL par mois (ajout en fin de fichier)
  ratelimit/              compteurs de limitation de débit
  logs/                   journal des erreurs, un fichier par mois
  uploads/                CV déposés par les candidats
  uploads/bot/            documents de la base de connaissances + texte extrait
```

`/data` et `/app` sont **hors de la racine web**. Chacun porte tout de même un `.htaccess` de
refus et un `index.html` muet, au cas où l'hébergeur exposerait les dossiers par erreur.

## 3. Installation

### En local

```bash
php -S localhost:8000 -t public public/index.php
```

Puis ouvrir <http://localhost:8000>. Le premier chargement crée `/data` et le compte admin.

### En production (Apache mutualisé)

1. Envoyer l'ensemble du dépôt hors de la racine publique, puis pointer le `DocumentRoot`
   (ou le dossier du domaine) sur `public/`.
2. Si l'hébergeur impose une racine fixe : placer le contenu de `public/` à la racine et
   `app/` + `data/` dans le dossier parent, en ajustant le `require` de `public/index.php`.
3. Droits d'écriture sur `data/` et `data/uploads/` (`chmod 775`).
4. Le compte administrateur est créé au premier chargement, avec un **mot de passe tiré au
   sort** (16 caractères) écrit dans `data/PREMIERE-CONNEXION.txt`. Le back-office impose son
   changement à la première connexion et supprime le fichier à ce moment-là. Pour choisir les
   identifiants à l'avance :
   ```bash
   ADMIN_EMAIL="vous@suisse-immo.fr" ADMIN_PASSWORD="…" php -r 'require "app/bootstrap.php";'
   ```
5. Décommenter la redirection HTTPS dans `public/.htaccess`.
6. Renseigner l'URL réelle dans **Back-office → Réglages → URL publique**.
7. Vérifier le bandeau de diagnostic du tableau de bord : il signale une extension PHP
   manquante, un dossier `data/` non inscriptible ou des mentions légales incomplètes.
8. Renseigner un serveur SMTP dans **Réglages → Envoi des e-mails** : `mail()` n'est pas
   disponible partout et ses messages finissent souvent en indésirables.

Installation dans un sous-dossier : renseigner le champ *Sous-dossier d'installation*
(ex. `/recrutement`) dans les réglages ; toutes les URL s'ajustent.

## 4. Le tunnel de conversion

Le parcours est pensé comme un entonnoir, avec plusieurs points de capture :

1. **Héros** — promesse, preuves, double CTA (candidater / simuler).
2. **Frustrations → réponses** — miroir des motivations, relance CTA.
3. **Simulateur de revenus** — engagement interactif ; le résultat est joint à la candidature.
4. **Avantages** (8 raisons), **valeurs**, **comparatif** face à la concurrence.
5. **Parcours en 4 étapes** — lève l'incertitude sur « ce qui se passe après ».
6. **Compétences**, **avis Google**, **FAQ** — traitement des objections.
7. **CTA final** pleine largeur.

Trois filets complémentaires :

- **Barre CTA flottante** affichée automatiquement 2 secondes après le chargement (ou dès le
  premier défilement), avec deux actions : accès direct au simulateur et candidature. Elle
  s'efface en bas de page pour ne pas masquer le CTA final, et reste refermable (choix mémorisé
  pour la session).
- **Pop-in de sortie** au `mouseout` haut d'écran (desktop) ou remontée rapide (mobile) —
  capture nom + e-mail uniquement.
- **Capture progressive** : chaque étape validée du tunnel est enregistrée côté serveur.
  Un abandon à l'étape 3 laisse donc un contact complet, listé dans
  *Candidatures → Abandonnées*.

## 5. Back-office (`/admin`)

| Écran | Contenu |
|---|---|
| **Tableau de bord** | Candidatures, visiteurs uniques, taux de conversion, tunnels abandonnés, entonnoir étape par étape, courbe de trafic, répartition du pipeline |
| **Candidatures** | Liste filtrable (étape, recherche), fiche détaillée, pipeline en 5 étapes, notes internes horodatées, téléchargement du CV, export CSV (séparateur `;`, BOM Excel), **composeur d'e-mail intégré** |
| **Messages** | Formulaire de contact et captures de la pop-in de sortie, avec réponse directe via le composeur |
| **Contenu du site** | Édition de **toutes** les sections : textes, listes répétables (réordonnables), curseurs et paliers du simulateur, avis, FAQ… |
| **Actualités** | CRUD complet, brouillon/publié, slug automatique, HTML nettoyé à l'enregistrement |
| **Bot IA** | Clé API Gemini, liste des modèles chargée en direct depuis Google, personnalité et consignes, choix des sources de connaissance, dépôt de documents, console de test, historique des conversations |
| **Réglages** | Identité, mentions légales, e-mail de notification, délai de réponse annoncé, activation de la barre CTA / pop-in / dépôt de CV, **animation des halos (marche/arrêt + vitesse)** |
| **Utilisateurs** | Création de comptes, changement de mot de passe (10 caractères minimum) |
| **E-mails envoyés** | Journal paginé des envois : transport utilisé, erreur exacte, contenu des 60 derniers |

L'éditeur de contenu est **piloté par un schéma** (`app/ContentSchema.php`) : ajouter un champ
dans ce fichier suffit à le rendre éditable, sans toucher aux vues du back-office.

## 6. Sécurité

**Authentification**
- Mot de passe d'installation tiré au sort, changement imposé à la première connexion.
- Hachage `password_hash()`, session régénérée à la connexion, expiration à 8 h,
  déconnexion en POST avec jeton (un lien `GET` ne peut plus déconnecter à distance).
- Limitation de débit à double clé : par adresse IP **et** par compte visé, pour qu'une
  attaque répartie sur plusieurs adresses reste bloquée.
- **Mot de passe oublié** : lien à usage unique envoyé par e-mail, valable une heure (voir §7).

**Entrées**
- Jeton CSRF sur tous les formulaires et appels API mutants.
- Anti-robot : champ leurre + délai minimal, sur l'envoi final **comme sur les brouillons**.
- Champs à liste fermée (situation, disponibilité, expérience, origine) validés contre les
  listes du back-office : une valeur forgée n'entre pas dans les données.
- Uploads : taille, extension **et type réel** contrôlés (`fileinfo`, ou signature du fichier),
  nom régénéré, stockage hors racine web, téléchargement via une route authentifiée.
- Le point de mesure `/api/track` n'accepte que les évènements réellement produits par
  l'interface : les conversions du tableau de bord ne peuvent pas être fabriquées.

**Sorties**
- Échappement systématique (`e()`), HTML des articles filtré par `DOMDocument` sur liste
  blanche de balises, d'attributs et de schémas d'URL.
- Exports CSV neutralisés contre l'injection de formules (`=`, `+`, `-`, `@`).
- Aucun détail technique renvoyé au visiteur : les motifs d'échec restent au journal.

**En-têtes**
- `Content-Security-Policy` complète, avec un **nonce** sur les rares scripts en ligne :
  aucun script injecté ne s'exécute, même en cas de faille d'échappement.
- `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, `Permissions-Policy`,
  et `Strict-Transport-Security` dès que la requête est chiffrée.
- Cache : rien de personnel n'est conservé par un intermédiaire ; le back-office passe en
  `no-store`, les ressources statiques versionnées en `immutable`.

**Données**
- Écritures JSON atomiques (fichier temporaire + `rename`) sous verrou exclusif ; un échec
  d'écriture lève une exception plutôt que de disparaître en silence.
- Purge quotidienne automatique selon les durées annoncées dans la politique de
  confidentialité (voir §14), CV orphelins compris.
- Toute erreur non rattrapée est journalisée dans `data/logs/` et présentée par une page 500
  propre — jamais une page à moitié rendue ni une trace d'exécution.

## 7. Mot de passe oublié

Le lien *Mot de passe oublié ?* de l'écran de connexion mène à `/admin/mot-de-passe-oublie`.
La personne saisit son adresse et reçoit un lien de réinitialisation.

**Ce qui est stocké** : jamais le jeton lui-même, seulement son empreinte SHA-256, comme un
mot de passe. Une copie de `data/password-resets.json` ne permet donc pas de prendre la main
sur un compte. Chaque demande retient l'identifiant du compte, l'empreinte, la date
d'expiration et une empreinte de l'adresse IP demandeuse.

**Ce qui protège le mécanisme**

| Risque | Réponse |
|---|---|
| Découvrir quels comptes existent | Le message affiché est identique que l'adresse corresponde à un compte ou non ; aucune demande n'est créée pour une adresse inconnue |
| Rejouer un lien | Usage unique : les demandes du compte sont supprimées dès qu'un mot de passe est enregistré |
| Lien intercepté tardivement | Validité d'une heure ; le lien expiré affiche un écran explicite proposant d'en demander un autre |
| Deviner un jeton | 256 bits d'aléa (`random_bytes`), comparaison en temps constant (`hash_equals`), format contrôlé avant toute lecture |
| Inonder une boîte mail | 4 demandes par heure et par compte, 10 par heure et par adresse IP |
| Une session ouverte par un tiers | Toute session ouverte avant le changement est fermée : `password_changed_at` est comparé à l'heure de connexion à chaque requête |
| Jeton lu dans un cookie tiers | Le jeton n'est lu que dans `$_POST` puis `$_GET`, jamais via `$_REQUEST` |

Un mot de passe choisi par ce chemin lève aussi l'obligation de changement de la première
connexion : c'est bien un mot de passe choisi par la personne.

L'envoi passe par le serveur SMTP des réglages ; à défaut, par la fonction `mail()` de
l'hébergeur, qui suffit sur la plupart des mutualisés. Ce message part même si les
notifications sont désactivées : c'est un message de service, pas une notification.

**Si aucun e-mail ne peut partir** — pas de serveur SMTP renseigné et pas d'agent local — le
lien est écrit dans `data/REINITIALISATION.txt`, dossier refusé au web et hors racine publique.
Il ne se lit qu'avec un accès au serveur, ce qui reste bien moins risqué que de modifier
`users.json` à la main, et le fichier est écrasé à chaque demande puis supprimé dès qu'un lien
est utilisé. C'est un filet de sécurité, pas un mode de fonctionnement : renseignez un serveur
SMTP dans *Réglages → Envoi des e-mails*.

Les demandes expirées depuis plus d'un jour sont supprimées par la purge quotidienne.

## 8. L'assistant IA (Gemini)

Le bot ne répond jamais « de mémoire » : à chaque question, le serveur assemble une base de
connaissances, en extrait les passages pertinents et les transmet au modèle comme **seule matière
autorisée**. Les consignes rédigées à la main sont toujours incluses, quelle que soit la question.

**Sources, activables une à une** dans *Back-office → Bot IA* :

| Source | Contenu | Poids |
|---|---|---|
| Consignes internes | Le texte libre saisi dans le back-office (barème réel, secteurs pourvus, ce qu'il ne faut pas dire) | épinglé — toujours transmis |
| Coordonnées | Adresse, téléphone, SIRET, délai de réponse annoncé | ×2 |
| Documents | Fichiers déposés : TXT, MD, CSV, HTML, JSON, DOCX, PDF (8 Mo max) | ×1,8 |
| Contenu du site | Toutes les sections éditables | ×1,6 |
| Actualités | Les analyses de marché publiées | ×0,6 |

Le texte des documents est extrait **à l'ajout** et stocké à côté du fichier : aucune relecture au
moment des questions. L'extraction utilise les extensions PHP standard — `zip` pour les `.docx`,
`zlib` pour les flux PDF compressés. Un PDF scanné (image, sans couche texte) est refusé avec un
message explicite invitant à coller le contenu dans le champ libre.

**Sélection des passages** : score par recouvrement de termes, normalisé par la longueur du
fragment (un texte long ne gagne pas par simple répétition) puis pondéré par la source, dans un
budget de 14 000 caractères. Aucune base vectorielle, aucun service tiers.

**Clé API** : saisie dans le back-office, stockée dans `data/bot.json` — hors racine web — et jamais
transmise au navigateur. Le champ affiche uniquement les quatre derniers caractères ; le laisser
vide conserve la clé en place. La liste des modèles est récupérée en direct auprès de Google
(`GET /v1beta/models`), filtrée sur ceux qui supportent `generateContent`, et mise en cache.

**Garde-fous** : règles non négociables placées en tête du prompt système — la base de
connaissances y est déclarée comme *documentation de référence*, jamais comme des consignes, et
les marqueurs de structure sont neutralisés dans les messages du visiteur, de sorte qu'une
question du type « oublie tes instructions » ne referme pas la section documentaire ; consignes
explicites interdisant d'inventer un chiffre ou une condition contractuelle ; réponse en texte brut, aucune balise du modèle n'est interprétée côté navigateur ;
limitation à 25 questions par quart d'heure et par visiteur ; conservation des 200 derniers
échanges pour relecture ; désactivation en un clic, qui retire le widget du site public.

Le widget n'apparaît que si le bot est activé **et** qu'une clé est enregistrée. Sans cela, l'API
publique répond un message d'indisponibilité renvoyant vers le formulaire de contact.

## 9. Écrire aux candidats

Le bouton « Écrire » d'une fiche candidat, comme le bouton « Répondre » d'un message, ouvre un
composeur intégré au back-office : **aucun lien `mailto:`**. L'envoi part du serveur, ce qui évite
de dépendre du client mail du poste et laisse une trace exploitable.

- **Modèles pré-remplis** : invitation au rendez-vous stratégique, relance d'une candidature
  abandonnée, demande de précisions, réponse négative, réponse à un message, message libre.
  `{prenom}` et `{secteur}` sont remplacés à l'ouverture, la signature de l'agence est ajoutée.
- **Destinataire non modifiable** : l'adresse affichée vient de la fiche, et elle est **relue
  côté serveur** au moment de l'envoi. Ce que poste le navigateur ne désigne que l'enregistrement,
  jamais le destinataire — un champ trafiqué ne peut pas détourner l'envoi.
- **Traçabilité** : chaque message part au journal *E-mails envoyés* et, pour une candidature,
  s'inscrit dans le suivi interne avec son objet et son corps (case décochable).
- **Deux transports** : le serveur SMTP renseigné dans les réglages (STARTTLS, TLS implicite,
  `AUTH LOGIN`/`PLAIN`), ou à défaut la fonction `mail()` de l'hébergeur. Le journal indique
  pour chaque envoi le transport utilisé et l'erreur exacte — rien n'est perdu silencieusement.
- **Repli `mail()`** : utilisé dès qu'aucun serveur SMTP n'est renseigné, avec une enveloppe
  d'expédition explicite (`-f`). Sans elle, l'agent local signe le message avec l'utilisateur
  système (`www-data@serveur`), une adresse inexistante que les filtres rejettent.
- **Diagnostic plutôt qu'un constat** : *Réglages → Envoi des e-mails* annonce le transport
  réellement actif et, s'il n'y en a aucun, la raison précise — fonction désactivée par
  l'hébergeur, `sendmail_path` vide, ou programme d'envoi absent de la machine.
- **Bouton d'essai** : un envoi réel depuis le back-office, dont le résultat s'affiche
  immédiatement avec le transport employé et le motif exact d'un éventuel échec.
- **Messages de service** (réinitialisation de mot de passe, essai d'envoi) : ils partent même
  si les notifications sont désactivées dans les réglages. Couper les notifications ne doit pas
  enfermer l'exploitant hors de son back-office.
- Les adresses et l'objet sont débarrassés de tout caractère de contrôle : un retour à la ligne
  dans un champ ne peut pas ajouter d'en-tête `Bcc` vers un tiers.

**Aucun lien `mailto:` sur le site public non plus.** L'adresse de l'agence reste affichée en
clair — dans le pied de page, sur la page contact, dans les mentions légales et la politique de
confidentialité — mais comme texte sélectionnable, jamais comme lien. Chaque endroit propose à
côté un chemin qui fonctionne pour tout le monde :

| Emplacement | Remplacement |
|---|---|
| Pied de page | Adresse en texte + lien « Écrire via le formulaire » vers `/contact` |
| Page contact, carte « Par e-mail » | Adresse en texte + lien « Utiliser le formulaire » vers l'ancre `#formulaire` de la même page |
| Politique de confidentialité | Adresse en gras + lien vers le formulaire, le courrier postal restant mentionné |

Les liens `tel:` sont conservés : sur mobile, toucher un numéro pour appeler est le comportement
attendu, et il ne dépend d'aucun logiciel à configurer.

## 10. Animation des halos

Chaque bloc du site porte un halo coloré diffus en arrière-plan. Ils peuvent dériver lentement —
translation, variation d'échelle — pour que le fond ne soit jamais complètement figé.

Le réglage se fait dans *Back-office → Réglages → Animations du fond* :

- **Un interrupteur** active ou coupe le mouvement sur l'ensemble du site.
- **Un curseur** fixe la durée d'un cycle complet, de 8 s (rapide) à 180 s (quasi immobile).
  Repères utiles : 15 s pour un mouvement bien visible, 34 s (valeur par défaut) pour une
  respiration discrète, 90 s pour un fond presque immobile.

Le layout traduit ces réglages en une classe `has-glow-motion` et une variable `--glow-cycle`
posées sur `<body>`. Les trois teintes suivent des trajectoires différentes, avec des durées
dérivées du cycle (×1 ; ×1,31 ; ×1,67) et des décalages de phase négatifs : deux halos ne
repassent jamais au même point en même temps, et la boucle ne se laisse pas repérer.

Seul `transform` est animé — jamais `opacity`, pour ne pas écraser l'intensité propre à chaque
halo définie dans les vues — donc aucun recalcul de mise en page. Sur mobile le cycle est
automatiquement rallongé de 60 %. `prefers-reduced-motion` fige tout, quel que soit le réglage.

## 11. Identité visuelle

Le logo officiel du réseau (monogramme, croix suisse et mot-symbole « SuisseImmo ») a été
vectorisé depuis le fichier d'origine et décliné en trois usages :

| Fichier | Usage |
|---|---|
| `app/Views/partials/logo.php` | SVG en ligne, utilisé par le site et le back-office. L'encre suit `currentColor`, le rouge la variable CSS `--logo-red` : la même source sert donc les fonds sombres et clairs. |
| `public/assets/img/logo-suisse-immo.svg` | Version figée aux couleurs d'origine (`#CC0017` / `#373737`), pour les fonds clairs et l'impression. |
| `public/assets/img/logo-suisse-immo-blanc.svg` | Version pour fonds sombres, utilisée dans le visuel de partage. |

Sur l'interface sombre, `--logo-red` vaut `#E62F43` — l'accent du site — afin que le rouge du
logo et celui des boutons ne se télescopent pas. Le rouge d'origine reste celui des fichiers
autonomes ; il suffit de changer la variable pour revenir à la teinte exacte de la charte.

Le favicon (`public/assets/img/favicon.svg`) reprend le monogramme sur une pastille anthracite,
lisible aussi bien dans un onglet clair que sombre. Il est décliné en PNG (32, 180, 192 et
512 px) pour les contextes qui ignorent le SVG — écran d'accueil iOS, manifeste d'application —
et le visuel de partage `og-cover.png` (1200 × 630) pour les réseaux sociaux. Ces PNG sont
produits par rastérisation des SVG : aucun outil externe n'est nécessaire pour les régénérer.

Les tracés du logo pèsent une vingtaine de kilo-octets : ils ne sont écrits qu'une fois par
page, dans un `<symbol>` masqué, et chaque occurrence n'est plus qu'une référence `<use>`. Le
même mécanisme sert la bibliothèque d'icônes (`icons_sprite()`).

## 12. Polices

Bricolage Grotesque et Inter sont **auto-hébergées** dans `public/assets/fonts/`, en woff2
variable — un seul fichier couvre toutes les graisses. `public/assets/fonts/LISEZ-MOI.txt`
rappelle la licence (SIL Open Font License 1.1, redistribution autorisée) et la marche à suivre
pour mettre à jour un fichier. Les charger depuis Google Fonts transmettrait l'adresse IP de
chaque visiteur à un tiers, ce que la CNIL et la jurisprudence allemande considèrent comme un
transfert nécessitant un consentement.

## 13. Accessibilité, référencement & performance

**Accessibilité (RGAA / WCAG 2.1 AA)**
- Navigation clavier complète, `aria-*` sur onglets, menu, tunnel et pop-in, lien d'évitement.
- Piège de focus opérant dans la fenêtre de sortie : le focus y entre à l'ouverture, ne
  s'échappe pas à la tabulation, et revient à son point de départ à la fermeture.
- Le mot tournant du titre est masqué aux lecteurs d'écran au profit d'un texte stable ; la
  série d'avis dupliquée pour le défilement est `aria-hidden`.
- Hiérarchie des titres sans saut de niveau, vérifiée sur les dix pages.
- Cibles tactiles d'au moins 44 × 44 px. Font exception les liens en pleine phrase, que le
  critère WCAG 2.5.8 exempte explicitement.
- Contrastes ≥ 4,5:1 mesurés sur les dix pages. Le rouge des fonds portant du texte blanc est
  très légèrement assombri (`--red-ink`) : le rouge de marque n'atteignait que 4,33:1.
- `prefers-reduced-motion` neutralise toutes les animations, y compris la dérive des halos.

**Référencement**
- Canonique sans chaîne de requête : `?utm_source=…` ne crée pas d'URL concurrente.
- Titres sous 60 caractères, descriptions entre 120 et 160, sur toutes les pages.
- Données structurées, chacune sur la seule page qu'elle décrit : `RealEstateAgent` partout,
  `FAQPage` sur l'accueil, `JobPosting` (avec `datePosted` et `validThrough` glissants) sur la
  page de candidature, `BlogPosting` sur les articles, `BreadcrumbList` sur les pages internes.
- `sitemap.xml` avec `lastmod`, `robots.txt` servi par le routeur avec un interrupteur
  d'indexation, `site.webmanifest`, anciennes URL WordPress redirigées en 301.
- Vignette de partage et icône iOS en PNG : les réseaux sociaux et l'écran d'accueil
  n'affichent pas les SVG.

**Performance**
- Logo et icônes écrits une seule fois par page dans un sprite `<symbol>` puis référencés par
  `<use>` : la page d'accueil est passée de 125 à 87 Ko.
- Polices auto-hébergées en woff2 variable, préchargées ; aucune requête vers un tiers.
- `backdrop-filter` réservé aux quelques éléments uniques (en-tête, menu mobile, barre
  flottante, fenêtre modale) et retiré des éléments répétés des dizaines de fois par page.
- Ressources statiques suffixées de leur date de modification et servies en `immutable`.
- Aucun script bloquant ; seules `transform` et `opacity` sont animées.

## 14. Conformité RGPD / LCEN

- **Mentions légales** : le tableau de bord signale l'absence du directeur de la publication ou
  des coordonnées de l'hébergeur, obligatoires au titre de l'article 6-III de la LCEN.
- **Durées de conservation**, appliquées par une purge quotidienne (`app/Housekeeping.php`)
  déclenchée par le trafic, sans tâche planifiée à configurer :

  | Donnée | Durée |
  |---|---|
  | Candidatures envoyées | 24 mois |
  | Tunnels abandonnés (brouillons) | 3 mois |
  | Messages de contact | 12 mois |
  | Conversations de l'assistant | 12 mois |
  | Journal des e-mails | 12 mois |
  | Mesure d'audience | 13 mois |
  | Demandes de réinitialisation | 1 jour après expiration |

  Les CV devenus orphelins sont supprimés avec la candidature correspondante.
- **Mesure d'audience** : maison, sans cookie ni identifiant persistant — l'empreinte visiteur
  est un condensat non réversible, remis à zéro chaque jour. Aucun bandeau n'est donc requis.
- **Aucun transfert vers un tiers** : polices auto-hébergées, aucun script externe. La seule
  sortie possible est l'API Gemini, et uniquement si l'assistant est activé — ce cas est alors
  déclaré dans la politique de confidentialité, qui l'affiche de façon conditionnelle.

## 15. Points à valider avant mise en ligne

Le simulateur est livré avec un barème **paramétrable et indicatif** (honoraires d'agence
à 4,5 % du prix de vente, paliers 70 / 80 / 90 %). Ces valeurs ne figurent pas sur le site
d'origine : **remplacez-les par votre grille réelle** dans *Back-office → Contenu → Simulateur*
avant publication. La mention légale sous le simulateur est également éditable.

De la même façon, le compteur « 7 années d'existence » reprend la valeur du site actuel :
le réseau ayant été fondé en juin 2017, elle est à réactualiser dans *Contenu → Chiffres clés*.
