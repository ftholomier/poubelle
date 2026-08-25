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
| Polices | Google Fonts (Bricolage Grotesque + Inter), avec repli système |

Aucun appel réseau tiers hors polices : pas de tracker, pas de CDN, pas de cookie publicitaire.

## 2. Arborescence

```
public/                   ← racine web (à pointer par le vhost)
  index.php               contrôleur frontal + table de routage
  .htaccess               réécriture, en-têtes de sécurité, cache, gzip
  robots.txt
  assets/css/app.css      design system du site public
  assets/css/admin.css    design system du back-office
  assets/js/app.js        animations, simulateur, pop-in, formulaires AJAX
  assets/js/funnel.js     tunnel de candidature en 4 étapes
  assets/js/admin.js      champs répétables, garde-fou, slug auto, console du bot
  assets/js/bot.js        widget de conversation
  assets/img/             logo, favicon et visuel de partage (SVG)

app/                      ← code applicatif (hors racine web)
  bootstrap.php           chargement + installation au premier lancement
  config.php              constantes, fuseau, limites d'upload
  Router.php              routeur à motifs `{param}`
  Store.php               persistance JSON atomique
  Security.php            session, CSRF, limitation de débit, authentification
  Icons.php               icônes SVG en ligne
  Mailer.php              envoi + journalisation des e-mails
  Analytics.php           mesure maison du tunnel
  Bot.php                 assistant Gemini : base de connaissances, sélection, appel API
  DocText.php             extraction de texte (TXT, MD, CSV, HTML, JSON, DOCX, PDF)
  ContentSchema.php       schéma du contenu éditable (pilote le back-office)
  install.php             données de démarrage + compte admin
  seed/                   contenu, réglages et articles par défaut
  Controllers/            SiteController · ApiController · AdminController
  Views/                  layout, partials, pages, admin

data/                     ← données d'exécution, jamais versionnées
  content.json  settings.json  posts.json
  applications.json  leads.json  events.json  maillog.json  users.json
  bot.json  bot-docs.json  bot-chats.json
  uploads/                CV déposés par les candidats
  uploads/bot/            documents de la base de connaissances + texte extrait
```

`/data` est **hors de la racine web**. Un `.htaccess` de refus y est écrit à l'installation,
au cas où l'hébergeur exposerait le dossier par erreur.

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
4. Définir le mot de passe admin **avant** le premier accès :
   ```bash
   ADMIN_EMAIL="vous@suisse-immo.fr" ADMIN_PASSWORD="…" php -r 'require "app/bootstrap.php";'
   ```
   Sinon un compte `admin@suisse-immo.fr` / `SuisseImmo2026!` est créé et les identifiants
   sont écrits dans `data/PREMIERE-CONNEXION.txt` — **à changer puis supprimer immédiatement.**
5. Décommenter la redirection HTTPS dans `public/.htaccess`.
6. Renseigner l'URL réelle dans **Back-office → Réglages → URL publique**.

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
| **Candidatures** | Liste filtrable (étape, recherche), fiche détaillée, pipeline en 5 étapes, notes internes horodatées, téléchargement du CV, export CSV (séparateur `;`, BOM Excel) |
| **Messages** | Formulaire de contact et captures de la pop-in de sortie |
| **Contenu du site** | Édition de **toutes** les sections : textes, listes répétables (réordonnables), curseurs et paliers du simulateur, avis, FAQ… |
| **Actualités** | CRUD complet, brouillon/publié, slug automatique, HTML nettoyé à l'enregistrement |
| **Bot IA** | Clé API Gemini, liste des modèles chargée en direct depuis Google, personnalité et consignes, choix des sources de connaissance, dépôt de documents, console de test, historique des conversations |
| **Réglages** | Identité, mentions légales, e-mail de notification, délai de réponse annoncé, activation de la barre CTA / pop-in / dépôt de CV |
| **Utilisateurs** | Création de comptes, changement de mot de passe (10 caractères minimum) |
| **E-mails envoyés** | Journal des 100 derniers envois, avec leur contenu — utile si `mail()` n'est pas configuré |

L'éditeur de contenu est **piloté par un schéma** (`app/ContentSchema.php`) : ajouter un champ
dans ce fichier suffit à le rendre éditable, sans toucher aux vues du back-office.

## 6. Sécurité

- Jeton CSRF sur tous les formulaires et appels API mutants.
- Mots de passe hachés via `password_hash()` ; session régénérée à la connexion ; expiration à 8 h.
- Limitation de débit par empreinte visiteur : candidatures, messages, tentatives de connexion.
- Anti-robot : champ leurre (honeypot) + délai minimal de remplissage.
- Échappement systématique en sortie (`e()`), HTML des articles filtré sur liste blanche,
  attributs `on*` et `javascript:` supprimés.
- Uploads : extension et taille contrôlées (PDF/DOC/DOCX, 5 Mo), nom de fichier régénéré,
  stockage hors racine web, téléchargement via une route authentifiée.
- Écritures JSON atomiques (fichier temporaire + `rename`) sous verrou exclusif.

## 7. L'assistant IA (Gemini)

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

**Garde-fous** : consignes explicites interdisant d'inventer un chiffre ou une condition
contractuelle ; réponse en texte brut, aucune balise du modèle n'est interprétée côté navigateur ;
limitation à 25 questions par quart d'heure et par visiteur ; conservation des 200 derniers
échanges pour relecture ; désactivation en un clic, qui retire le widget du site public.

Le widget n'apparaît que si le bot est activé **et** qu'une clé est enregistrée. Sans cela, l'API
publique répond un message d'indisponibilité renvoyant vers le formulaire de contact.

## 8. Identité visuelle

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
lisible aussi bien dans un onglet clair que sombre.

## 9. Accessibilité & performance

- Navigation clavier complète, `aria-*` sur onglets, menu, tunnel et pop-in, lien d'évitement.
- `prefers-reduced-motion` neutralise toutes les animations.
- Contrastes conformes AA sur le fond sombre.
- Aucun script bloquant, animations en `transform`/`opacity`, images vectorielles.
- Données structurées JSON-LD : `RealEstateAgent`, `JobPosting`, `FAQPage`.
- `sitemap.xml` généré dynamiquement, anciennes URL WordPress redirigées en 301.

## 10. Points à valider avant mise en ligne

Le simulateur est livré avec un barème **paramétrable et indicatif** (honoraires d'agence
à 4,5 % du prix de vente, paliers 70 / 80 / 90 %). Ces valeurs ne figurent pas sur le site
d'origine : **remplacez-les par votre grille réelle** dans *Back-office → Contenu → Simulateur*
avant publication. La mention légale sous le simulateur est également éditable.

De la même façon, le compteur « 7 années d'existence » reprend la valeur du site actuel :
le réseau ayant été fondé en juin 2017, elle est à réactualiser dans *Contenu → Chiffres clés*.
