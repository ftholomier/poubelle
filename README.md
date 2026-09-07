# Le comptable à lunettes — refonte du site

Refonte du site [lecomptablealunettes.fr](https://www.lecomptablealunettes.fr) de
**Romain Lemaire**, en **PHP natif** (aucune dépendance, aucun framework),
**sans base de données**, avec back-office complet, assistant IA et multilingue.

Le contenu, la charte graphique, le logo, les photos et les témoignages sont
repris du site existant. L'architecture éditoriale d'origine est conservée
(Accueil · Qui suis-je ? · Accompagnement · Actu · Contactez-moi), et les
anciennes adresses WordPress sont redirigées en 301 vers les nouvelles.

---

## Sommaire

1. [Installation](#installation)
2. [Arborescence](#arborescence)
3. [Charte graphique](#charte-graphique)
4. [Le back-office](#le-back-office)
5. [Multilingue](#multilingue)
6. [Assistant IA](#assistant-ia)
7. [Avis Google](#avis-google)
8. [Stockage sans base de données](#stockage-sans-base-de-données)
9. [Sécurité](#sécurité)
10. [Mise en production](#mise-en-production)

---

## Installation

**Prérequis :** PHP 8.1 ou supérieur. Extensions recommandées : `mbstring`,
`json`, `fileinfo`, `curl`, `zip` (export de sauvegarde), `dom` (nettoyage HTML).

```bash
git clone <dépôt> && cd <dossier>
cp .env.example .env          # puis compléter APP_URL et APP_KEY
php -r 'echo bin2hex(random_bytes(32));'   # valeur pour APP_KEY

# Droits d'écriture sur les données uniquement
chmod -R 770 data storage
```

La **racine web du serveur doit pointer sur `/public`**. Tout le reste
(code, données, sauvegardes, téléversements) est hors de portée du navigateur.

Développement local :

```bash
php -S localhost:8000 -t public public/index.php
```

Première visite sur `/admin` : un écran d'installation crée le compte
administrateur et installe le contenu de démarrage.

---

## Arborescence

```
app/                      code applicatif (hors racine web)
├── bootstrap.php         amorçage : autoloader, config, garde-fous
├── config.php            configuration (lit .env)
├── routes.php            routes front, API, back-office, redirections 301
├── helpers.php           raccourcis de vue : e(), tr(), u(), icon()…
├── src/
│   ├── Ai/               Assistant (Gemini) + base de connaissance
│   ├── Content/          Réglages, Pages, Blocs, Avis, Médias, Demandes
│   ├── Controllers/      Site, Api, Admin, Auth, Media
│   ├── Core/             JsonStore, Schema, View, Icons, Http, Logger…
│   ├── Http/             Request, Response, Router
│   ├── I18n/             Translator
│   ├── Mail/             Mailer (mail() ou SMTP natif)
│   └── Security/         Auth, Session, Csrf, RateLimiter, Sanitizer
└── views/
    ├── front/            gabarits publics (+ un fichier par type de bloc)
    └── admin/            écrans du back-office

data/                     toutes les données (hors racine web)
├── settings.json         réglages du site
├── pages/*.json          une page = un fichier
├── pages-index.json      index léger du menu et du plan de site
├── reviews.json          avis saisis manuellement
├── users.json            comptes (mots de passe hachés Argon2id)
├── leads-AAAA-MM.json    demandes reçues, un fichier par mois
├── i18n/*.json           libellés d'interface par langue
├── backups/              30 versions par fichier, restaurables
├── runtime/              caches, verrous, compteurs anti-abus
└── uploads/              images et documents téléversés

public/                   RACINE WEB
├── index.php             contrôleur frontal unique
├── .htaccess             réécriture, en-têtes, cache
└── assets/               css, js, images, logo

storage/logs/             journaux applicatifs et journal d'audit
```

---

## Charte graphique

Reprise du site existant, pilotée par des **jetons CSS** injectés depuis le
back-office (*Charte graphique*). Aucune couleur n'est écrite en dur dans les
gabarits : la modifier au back-office la modifie partout.

| Rôle | Valeur | Origine |
|---|---|---|
| Bordeaux de marque | `#921732` | couleur du logo « ROMAIN LEMAIRE » |
| Noir | `#161922` | fond des visuels et sections sombres |
| Bleu d'action | `#0089F7` | couleur des boutons et liens du site |
| Bleu foncé | `#006EDF` | survol des boutons |
| Gris de fond | `#F7F7F7` | sections claires |
| Texte secondaire | `#626262` | |

**Typographies** : `Space Grotesk` (titres) et `Poppins` (texte), comme sur le
site d'origine.

**Design plat** : aplats de couleur, aucun dégradé décoratif, aucune ombre
portée simulant du relief. Le jeu d'icônes (`app/src/Core/Icons.php`) est un
ensemble de SVG monochromes en `currentColor`, sans dépendance externe.

### Animations

| Élément | Comportement |
|---|---|
| Apparition au défilement | fondu + translation, via `IntersectionObserver`, avec décalage progressif |
| Boutons | fondu et **glissement du fond de gauche à droite** au survol |
| Cartes | même voile de couleur glissant, icône et lien qui suivent |
| Bouton « Demandez un accompagnement » | **halo** pulsé d'épaisseur constante |
| Menu du pied de page | **trait fin sous le libellé, animé de gauche à droite** |
| Menu principal | même trait, en couleur d'accent |
| Chiffres clés | comptage animé à l'entrée dans l'écran |
| En-tête | masquage au défilement vers le bas, jauge de progression |

Tout est désactivé pour les visiteurs ayant demandé moins d'animations
(`prefers-reduced-motion`), et l'interrupteur *Animations* du back-office
permet de tout couper.

---

## Le back-office

Accès : `/admin` — connexion par **e-mail + mot de passe**, avec
**récupération de mot de passe** par lien à usage unique valable une heure.

| Écran | Rôle |
|---|---|
| Tableau de bord | état du site, dernières demandes, contrôles de sécurité |
| Pages & contenus | création, duplication, suppression, ordre du menu |
| Éditeur de page | blocs déplaçables, éditeur WYSIWYG, SEO, versions restaurables |
| Demandes | messages reçus, statut, export CSV |
| Avis Google | mode automatique ou manuel, saisie des avis de repli |
| Assistant IA | discours, suggestions, réindexation, test de question |
| Médias | images du site |
| Documents IA | PDF, DOCX, TXT… dont le texte alimente l'assistant |
| Langues | langues publiées, libellés d'interface, traduction automatique |
| Charte graphique | couleurs, polices, arrondis, logo, favicon |
| Réglages | coordonnées, réseaux, barre d'action, pop-up, SEO, mentions légales |
| Comptes | utilisateurs, rôles, mot de passe |
| Sauvegardes | versions restaurables, export ZIP, outils de réparation |

### Blocs disponibles

`hero` · `stats` · `services` · `steps` · `media_text` · `richtext` (WYSIWYG)
`reviews` · `faq` · `cta_band` · `contact` · `logos` · `posts`

Chaque bloc déclare son schéma dans `app/src/Content/Blocks.php`. Le formulaire
d'édition est **généré à partir de ce schéma** : ajouter un champ au catalogue
suffit, sans toucher au back-office.

---

## Multilingue

- Langues gérées : français, anglais, espagnol, allemand (activables à la carte).
- La langue par défaut est servie **sans préfixe** (`/contact`), les autres avec
  (`/en/contact`).
- Détection : URL → cookie → en-tête `Accept-Language` du navigateur.
- Balises `<html lang>`, `hreflang` de chaque version et `x-default` générées
  automatiquement ; le plan de site liste toutes les langues.
- Chaque champ éditorial est traduisible ; **une traduction absente reprend
  automatiquement la langue par défaut** — jamais de page vide.
- Le **widget Google Traduction** prend le relais pour les langues sans
  traduction saisie (activable dans *Langues*).

---

## Assistant IA

Bulle en bas à droite, adossée à **Google Gemini**.

1. Le contenu des pages publiées (WYSIWYG compris) et des documents téléversés
   marqués « base de connaissance » est découpé en extraits.
2. À chaque question, les cinq extraits les plus pertinents sont sélectionnés
   par une recherche lexicale pondérée (TF-IDF + couverture de la question).
3. Ces extraits — et eux seuls — sont transmis à Gemini avec la consigne
   définie au back-office.
4. L'index se reconstruit tout seul dès qu'une page ou un document change.

**La clé d'API ne quitte jamais le serveur** : le navigateur ne dialogue
qu'avec `/api/chat`. Sans clé, ou si l'API est indisponible, l'assistant reste
utile : il cite le meilleur extrait trouvé dans le site, et se tait quand la
question sort du sujet.

Débit limité à 20 questions par tranche de 10 minutes et par adresse IP.

---

## Avis Google

Deux modes, réglables dans *Avis Google* :

- **Automatique** — appel de l'API Google Places (New) côté serveur, résultat
  mis en cache 12 heures. En cas de panne ou de quota dépassé, le cache périmé
  puis les avis manuels prennent le relais.
- **Manuel** — seuls les avis saisis au back-office sont affichés.

Les huit témoignages du site d'origine sont installés au démarrage.

---

## Stockage sans base de données

Tout vit dans des fichiers JSON, avec les garanties qu'on attend d'une base :

| Garantie | Mise en œuvre |
|---|---|
| **Écriture atomique** | écriture dans un fichier temporaire puis `rename()` — le front ne voit jamais un fichier à moitié écrit |
| **Verrouillage** | `flock()` exclusif à l'écriture, partagé à la lecture, avec délai maximal pour ne jamais figer une requête |
| **Lecture-modification-écriture** | `JsonStore::mutate()` exécute le tout sous verrou : aucune perte en cas d'écritures simultanées |
| **Reprise de contenu** | chaque écriture archive la version précédente ; 30 versions par fichier, restaurables une par une depuis le back-office |
| **Auto-réparation** | un JSON illisible est automatiquement remplacé par la dernière version valide |
| **Contrôle d'intégrité** | un JSON qui ne peut pas être relu n'est jamais écrit |
| **Tolérance de schéma** | toute donnée lue est fusionnée avec un schéma de référence |

### Pourquoi le front ne casse jamais

C'est le rôle de `App\Core\Schema` :

- **clé manquante** → valeur par défaut (la vue affiche toujours quelque chose) ;
- **clé inconnue** → conservée (une version plus ancienne du code ne détruit pas
  les données produites par une version plus récente) ;
- **type incohérent** → valeur par défaut (jamais d'erreur de type en vue) ;
- **bloc de type inconnu** → ignoré au rendu, mais **conservé** dans le fichier.

Conséquence pratique : ajouter un champ, un bloc ou une langue ne demande
aucune migration, et une restauration partielle ne laisse jamais le site
dans un état incohérent.

---

## Sécurité

**Isolation** — `data/` et `storage/` sont hors de la racine web, avec en plus
un `.htaccess` de refus et un `index.html` vide, au cas où la configuration du
serveur changerait.

**Authentification** — mots de passe hachés en Argon2id (re-hachage
automatique si l'algorithme évolue), comparaison à temps constant même pour un
compte inexistant (pas d'énumération), blocage après 5 échecs par e-mail et
15 par IP, session régénérée à la connexion, expiration après 45 minutes
d'inactivité, cookie `HttpOnly` + `SameSite=Lax` + `Secure` en HTTPS,
vérification de l'agent utilisateur contre le détournement de session.

**Formulaires et API** — jeton CSRF par formulaire, contrôle d'origine,
pot de miel et délai minimum de remplissage, limitation de débit par IP
(contact, pop-up, assistant, connexion, mot de passe oublié).

**Contenus** — le HTML du WYSIWYG passe par un assainisseur à liste blanche
(balises, attributs, classes), à l'enregistrement **et** au rendu. Les URL
`javascript:` et assimilées sont rejetées, les liens externes reçoivent
`rel="noopener noreferrer"`.

**Téléversements** — type déterminé par le contenu réel du fichier (`finfo`),
liste blanche de types, taille plafonnée, SVG refusés s'ils contiennent du
script, nom de fichier réécrit. Les fichiers sont stockés **hors racine web**
et servis par une route contrôlée, avec `Content-Security-Policy: sandbox` :
un fichier téléversé ne peut donc jamais être exécuté.

**Chemins** — slugs et noms de fichiers filtrés, résolution vérifiée par
`realpath()` contre la traversée de répertoire.

**En-têtes** — CSP, `X-Content-Type-Options`, `X-Frame-Options`,
`Referrer-Policy`, `Permissions-Policy`, HSTS en HTTPS.

**Traçabilité** — journal d'audit des actions du back-office
(`storage/logs/audit.log`), consultable depuis *Sauvegardes*. Les secrets sont
masqués dans les journaux ; les adresses IP des demandes sont **hachées**,
jamais stockées en clair.

---

## Mise en production

1. Racine web du serveur → `/public`.
2. `.env` complété (`APP_URL`, `APP_KEY`, `FORCE_HTTPS=true`, SMTP).
3. `APP_DEBUG=false`.
4. Droits : `data/` et `storage/` inscriptibles par PHP uniquement (`770`).
5. Certificat HTTPS actif, puis décommenter la redirection dans
   `public/.htaccess`.
6. Vérifier le tableau de bord : tous les contrôles doivent être au vert.
7. Sauvegarde : télécharger l'archive ZIP depuis *Sauvegardes*, ou sauvegarder
   le dossier `data/` — il contient tout le site.

### Nginx

```nginx
server {
    root /chemin/du/site/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.(env|git) { deny all; }
}
```
