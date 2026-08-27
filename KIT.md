# Kit de reprise — socle de site vitrine PHP natif

> Ce dépôt sert actuellement le site de **Menuiserie Tréhant** (pergolas et
> carports aluminium). Le présent fichier décrit le socle lui-même, pour le
> reprendre sur un autre projet.

Ce dossier est le code complet d'un site vitrine haut de gamme livré en
production. Il est fourni comme **point de départ pour un projet similaire** :
autre client, autre contenu, même architecture.

Ce fichier s'adresse autant à un développeur qu'à Claude Code. Il décrit ce
que fait le socle, pourquoi il est bâti ainsi, et ce qu'il faut changer pour
l'adapter.

---

## 0. Par où commencer

Si vous reprenez ce dossier pour bâtir un autre site :

1. Lisez ce fichier en entier — il fait dix minutes et évite les pièges du
   §10, qui sont tous des bugs réellement rencontrés.
2. Lancez le site en local (§9) et ouvrez `/admin` : le back-office est le
   meilleur inventaire de ce qui existe.
3. Suivez le §7 dans l'ordre. Ne réécrivez pas les briques de `app/Core` :
   elles sont éprouvées et documentées dans leur en-tête.
4. Respectez les conventions du §5, en particulier **le nommage français** et
   **les commentaires qui disent pourquoi**. Un fichier écrit autrement se
   repère immédiatement au milieu des autres.

Ce que le dossier **ne contient pas**, volontairement : les photos du client
(`public/assets/img/site/`), son contenu vivant (`data/*.json`), son compte
d'administration et ses clés d'API. Le site démarre malgré tout : chaque
image manquante affiche un visuel « photo à venir », et `data-modele/` amorce
le contenu à la première visite.

---

## 1. Ce que c'est

Un site vitrine multilingue avec back-office complet, en **PHP natif, sans
aucune dépendance** : ni Composer, ni base de données, ni build front. Le
contenu vit dans des fichiers JSON, le back-office les écrit.

Conçu pour l'hébergement mutualisé français (testé sur o2switch) : on dépose
les fichiers, on pointe la racine web sur `public/`, c'est en ligne.

**Pourquoi sans dépendances ?** Un site vitrine change peu et doit vivre dix
ans. Sans Composer, il n'y a rien à mettre à jour, rien qui casse, rien à
auditer. Une base de données pour douze pages de contenu ajoute une panne
possible, une sauvegarde à gérer et un export à faire — le JSON versionné
rend le même service.

---

## 2. Contraintes à ne pas casser

Ces règles ont dicté toute l'architecture. Les reprendre telles quelles.

| Règle | Raison |
|---|---|
| **Seul `public/` est exposé** ; `app/`, `config/`, `data/`, `views/`, `storage/` vivent au-dessus | Un contenu hors racine web ne peut pas être téléchargé, même en cas de mauvaise configuration Apache |
| **Aucune dépendance externe** | Rien à mettre à jour, rien qui casse |
| **Aucun secret dans le dépôt** | `data/admin/` (mot de passe SMTP, empreinte du mot de passe admin) est ignoré par git |
| **Jamais 777** | Sur mutualisé, PHP tourne sous le compte utilisateur : 755 / 644 suffisent, et certains hébergeurs refusent de servir un fichier en 777 |
| **Le site public ne contacte aucun service extérieur** | Pas de CDN, pas de police distante, pas d'API à l'affichage — vitesse, vie privée, et rien qui tombe |

---

## 3. Carte du code

```
public/index.php        Contrôleur frontal, unique point d'entrée PHP exposé
public/.htaccess        Réécriture, HTTPS, en-têtes de sécurité, cache
public/assets/          CSS, JS, polices, images (aucun build)

app/bootstrap.php       Chargeur de classes, session, configuration
app/helpers.php         e() url() asset() route() lien() t() image() absolu()
app/routes.php          Table de routage publique
app/routes-admin.php    Table de routage du back-office
app/Core/               Briques réutilisables (voir ci-dessous)
app/Controllers/        Pages publiques, API JSON
app/Admin/              Écrans du back-office

views/layout.php        Gabarit public (métadonnées, JSON-LD, hreflang)
views/pages/            Une vue par page
views/partials/         Fragments (en-tête, pied, cookies, langues…)
views/admin/            Back-office, avec son propre layout

data-modele/            Contenu LIVRÉ avec le code (versionné)
data/*.json             Contenu VIVANT du site, écrit par le back-office
data/seo.json           Slugs, balises, redirections 301
data/langues.json       Langues déclarées
data/traductions/*.json Traductions par langue
data/assistant/         Documents, notes et journal des conversations
data/admin/             Compte et réglages — JAMAIS versionné

outils/paquet-maj.php   Fabrique le dossier à transférer par FTP
```

### `data-modele/` et `data/` : la séparation qui protège le client

Le dépôt ne contient **aucun fichier portant le chemin du contenu vivant**.
Le contenu livré vit dans `data-modele/`, et `Content::amorcer()` ne le
recopie dans `data/` que pour un fichier qui n'y existe pas encore.

C'est ce qui rend un transfert de code inoffensif : un FTP intégral et
maladroit ne peut pas écraser ce que le client a saisi, puisque le paquet ne
contient rien à ce chemin. Vérifié en poussant l'export complet du dépôt sur
un site dont le diaporama avait été modifié — rien n'a bougé.

Si `data/` n'est pas inscriptible, le modèle est servi tel quel : une page
s'affiche même quand les droits sont à revoir.

### Les briques de `app/Core`

| Classe | Rôle | À reprendre tel quel ? |
|---|---|---|
| `Router` | Routage à motifs `{param}`, premier inscrit gagne | oui |
| `View` | Gabarits PHP dans un layout via `$slot` | oui |
| `Content` | Lecture/écriture JSON, sauvegardes versionnées, traduction à la volée | oui |
| `Seo` | Slugs modifiables, redirections 301, sitemap, robots, JSON-LD | oui, en adaptant la table `PAGES` |
| `Langues` / `Traducteur` / `TraductionAuto` | Multilingue par fichiers, traduction automatique sans clé | oui |
| `Cookies` | Catégories de consentement | oui, en adaptant les catégories |
| `Auth` / `Csrf` / `Session` | Connexion, jetons, sessions durcies | oui |
| `Mailer` | SMTP natif + repli sur `mail()` | oui |
| `Mediatheque` | Envoi d'images, redimensionnement, vignettes (GD) | oui |
| `Liste` | Opérations de liste : ajouter, déplacer, masquer, retirer | oui |
| `Deploiement` | Mise à jour par git depuis l'admin | oui |
| `Avis` | Avis Google récupérés côté serveur, mis en cache | oui, en renseignant clé et fiche |
| `Assistant` | Assistant de discussion Gemini : corpus, consigne, documents, liste dynamique des modèles | oui, en renseignant la clé |
| `Conversations` | Journal des échanges avec l'assistant, repérage des coordonnées, purge à 12 mois | oui |
| `Permissions` | Analyse et réparation des droits | oui |
| `Parametres` | Réglages techniques hors git | oui |

---

## 4. Le modèle de contenu

Tout part de `data/`. Deux formes :

**Pages fixes** — `data/pages/<clé>.json` :

```json
{
  "titre": "Nos Hébergements",
  "meta": { "description": "…" },
  "hero": { "image": "assets/img/site/x.jpg", "surtitre": "…", "titre": "…" },
  "sections": [ { "titre": "…", "paragraphes": ["…"] } ]
}
```

**Collections** — `data/<collection>.json` avec un tableau `items`, chaque
entrée portant un `slug` :

```json
{
  "intro": "…",
  "items": [
    { "slug": "comptabilite", "nom": "…", "image": "…", "actif": true, "meta": {} }
  ]
}
```

Trois conventions structurantes :

- **`slug`** identifie une fiche. C'est lui qui sert de clé de traduction, pas
  le rang : réordonner les fiches ne décale pas les traductions.
- **`actif`** absent vaut publié. Un contenu enregistré avant l'arrivée de
  cette notion reste donc visible — aucune migration à écrire.
- **Les chemins d'images** sont relatifs à `public/`. Le helper `image()`
  retombe sur un visuel « photo à venir » si le fichier manque, ce qui évite
  toute image cassée sur le site comme dans l'admin.

---

## 5. Conventions de code

- **Tout est nommé en français** : classes, méthodes, variables, routes. Le
  client lit le code, les messages d'erreur lui parlent.
- **Les commentaires disent pourquoi, jamais quoi.** Un commentaire qui
  paraphrase la ligne suivante est du bruit ; un commentaire qui explique une
  contrainte ou un piège évite une régression.
- **Échapper systématiquement** : `e()` sur toute valeur affichée.
- **CSRF sur tous les formulaires** du back-office : `Csrf::champ()` /
  `Csrf::verifier()`.
- **Écritures atomiques** : fichier temporaire puis `rename()`, jamais
  d'écriture en place.
- **CSS et JS écrits à la main**, sans build. Le JS est en ES5 tolérant, chaque
  bloc se désactive tout seul si sa cible est absente de la page.

---

## 6. Ce que le socle sait déjà faire

- Pages éditoriales, collections avec fiches, formulaire de contact avec piège
  à robots et consentement explicite
- Back-office complet : édition de tous les textes, médiathèque avec envoi par
  lot et glisser-déposer, création / réordonnancement / masquage / suppression
  des fiches, éditeur JSON avancé avec restauration de versions
- Référencement : slugs modifiables avec redirection 301 automatique,
  `sitemap.xml`, `robots.txt`, `canonical`, `hreflang`, JSON-LD
- Multilingue par préfixe d'adresse, relecture manuelle, repli sur la langue
  source. La traduction automatique essaie **Google Traduction, puis MyMemory,
  puis DeepL** : les deux premiers sont gratuits mais comptés par adresse IP
  (donc partagée sur un mutualisé, d'où des refus HTTP 429 immédiats), le
  troisième n'intervient qu'en dernier recours car son offre gratuite
  n'accorde qu'un million de caractères **pour la vie du compte**. Un compteur
  suit ce qui y a été pris.
- Consentement aux cookies conforme CNIL, avec blocage réel des traceurs
- Deux dispositions de menu (panneau latéral ou barre horizontale), au choix
  du client depuis le back-office, sur le même balisage
- Avis Google récupérés côté serveur et mis en cache : aucun appel depuis le
  navigateur du visiteur, donc aucun cookie tiers à soumettre au consentement
- Carrousel d'avis à hauteur bornée, le témoignage long défilant dans sa carte
- **Diaporama du bandeau d'accueil** : plusieurs photos en fondu croisé avec
  dérive lente, jauge de progression, et réglage complet au back-office —
  ordre par glisser-déposer, activation par interrupteur, retrait immédiat,
  ajout au clic, temps de pause, intensité du voile sombre
- **Galerie de réalisations** filtrable par catégorie, visionneuse au clavier,
  et galerie propre à chaque fiche produit ; rotation des photos au back-office
- **Assistant de discussion Gemini**, activable, cantonné à trois sources —
  contenu du site, documents envoyés (PDF, DOCX, TXT, MD), texte collé dans un
  éditeur. Aucune fonction déclarée au modèle, donc rien à inventer ailleurs.
  Conversation conservée d'une page à l'autre, journal consultable au
  back-office, repérage du téléphone ou du courriel laissé en conversation, et
  formulaire de rappel intégré
- Mise à jour du site par git depuis l'admin, avec sauvegarde préalable
- Colis de mise à jour par FTP fabriqué par `outils/paquet-maj.php`, à partir
  de la même liste que la mise à jour automatique
- Diagnostic serveur et réparation des droits

---

## 6 bis. Les écrans du back-office

| Écran | Ce qu'on y fait |
|---|---|
| Tableau de bord | État du site, raccourcis |
| Coordonnées & menu | Identité, adresses, téléphone, entrées de menu |
| Page d'accueil | Bandeau, **diaporama**, voile, chiffres clés, blocs |
| La société, Services, Valeurs, FAQ | Contenus éditoriaux et fiches |
| Réalisations | Onglets par gamme, photos cochées sur planche-contact |
| Contact | Coordonnées affichées, carte, champs du formulaire |
| Photos | Médiathèque : envoi par lot, **rotation**, suppression |
| Apparence | Disposition du menu, réglages visuels |
| Avis Google | Clé d'API, fiche, filtres, rythme du carrousel |
| Assistant IA | Activation, clé Gemini, **liste dynamique des modèles**, documents, notes, question d'essai |
| Conversations | Journal des échanges, coordonnées repérées, purge |
| Référencement | Slugs, balises, redirections 301 |
| Langues | Langues, traduction automatique, **clé DeepL et compteur** |
| Éditeur avancé | JSON brut avec validation et restauration de version |
| Paramètres | SMTP, compte, droits, diagnostic serveur |
| Mises à jour | Déploiement git, sauvegarde et restauration |

---

## 7. Adapter à un nouveau projet

Dans l'ordre :

1. **Identité** — `data/site.json` (nom, adresse, téléphone, menu, liens de
   réservation), `public/assets/img/logo/` (logos SVG dessinés à la main,
   `favicon-512.png` généré avec GD), et les variables de couleur en tête de
   `public/assets/css/site.css` — puis les mêmes teintes, éclaircies pour
   l'écran sombre, en tête de `public/assets/css/admin.css`.
2. **Pages** — la table `Seo::PAGES` liste les pages fixes et leurs slugs. En
   ajouter une : une entrée ici, un `data/pages/<clé>.json`, une route dans
   `app/routes.php`, une vue dans `views/pages/`.
3. **Collections** — `Seo::COLLECTIONS` associe une page à une collection.
   Adapter ou remplacer `services` selon le métier.
4. **Contenu** — remplacer `data-modele/` par le contenu du nouveau projet et
   vider `data/` de ses JSON : ils se recréeront à la première visite.
5. **Traductions** — supprimer `data/traductions/` et `data/langues.json`, les
   langues se recréent depuis l'admin.
6. **Textes d'interface** — les mots des gabarits passent par `t('…')`. Ils
   sont relevés automatiquement dans le code : rien à déclarer.
7. **Cookies** — adapter les catégories dans `Cookies::categories()`.
8. **Assistant** — la consigne système est dans `Assistant::consigne()` : elle
   dit au modèle de quoi il parle, sur quel ton, et quand ramener vers un
   devis. C'est le seul endroit à réécrire pour un autre métier.

---

## 8. À ne jamais livrer ni versionner

- `data/admin/` — compte administrateur et mot de passe SMTP
- `storage/` — cache, sauvegardes, archives de déploiement
- `public/assets/img/site/` — photos du client
- `data/*.json` en écrasement — c'est le contenu vivant du site

Ces quatre chemins sont exclus du déploiement par git : `Deploiement::CODE`
liste explicitement ce qui est mis à jour, tout le reste est intouchable. Ce
choix est structurel, pas procédural — le contenu du client ne peut pas être
écrasé par une mise à jour, même si le dépôt contient des fichiers différents.

---

## 9. Essayer en local

```bash
php -S localhost:8080 -t public public/index.php
```

Puis `/admin` pour créer le compte administrateur. Aucun identifiant par
défaut : l'écran de première configuration s'en charge.

Sans les photos du client (`public/assets/img/site/`), le site fonctionne et
affiche le visuel « photo à venir » partout — c'est voulu.

---

## 10. Pièges rencontrés, à ne pas refaire

Cinq bugs réels de ce développement. Ils se reproduiront à l'identique sur un
projet bâti sur le même socle.

**`var` n'est pas limité au bloc.** `site.js` est une seule fonction ; un
`var panneau` écrit dans une section a écrasé celui d'une autre. Le menu est
resté `inert`, donc insensible aux clics, qui traversaient jusqu'au voile —
lequel referme le menu. Chaque section a maintenant sa propre portée.

**Un traducteur automatique traduit tout, y compris les chemins de fichiers.**
`assets/img/…` est revenu en `asset/img/…` en allemand, et toutes les photos
ont disparu. Deux garde-fous : une liste de clés techniques qui écarte toute
leur descendance, et un contrôle sur la valeur (chemin, adresse, e-mail).

**Une marge négative « compensatoire » sur un élément centré le décale.**
`left: 50%` + `translateX(-50%)` centre déjà ; un `margin-inline: -1.4rem`
censé annuler un rembourrage symétrique déplaçait le libellé de 22 px.

**Un décalage vers le bas allonge la zone défilable.** Des éléments qui entrent
en `translateY(10px)` font apparaître puis disparaître l'ascenseur pendant
l'animation. Préférer un décalage horizontal, ou un flou qui se dissipe : ni
l'un ni l'autre ne touche à la mise en page.

**`iconv('ASCII//TRANSLIT')` dépend de la locale du serveur.** Sous locale `C`
ou `POSIX`, « hébergement » devient « h?bergement » et le slug « h-bergement ».
La translittération est faite par une table explicite.

**Une règle d'affichage explicite l'emporte sur l'attribut `hidden`.** Le
panneau de consentement porte `hidden` au chargement ; lui donner un
`display: flex` en CSS le rendait visible d'emblée sur toutes les pages. Il
faut rétablir soi-même `[hidden] { display: none }` dans la portée concernée.

**Un service extérieur injoignable ralentit tout le site.** Sans temps de
repos après échec, chaque affichage de page relançait l'appel à Google et
attendait le délai réseau. Un échec est désormais horodaté dans le cache et
n'est retenté qu'une demi-heure plus tard.

**`scroll-snap: mandatory` annule une animation de défilement écrite en JS.**
Le carrousel semblait sauter d'une carte à l'autre : à chaque image, le
navigateur ramenait la piste sur son point d'ancrage le plus proche. Il faut
suspendre l'ancrage le temps du mouvement et le rendre ensuite au doigt.

**Une barre de défilement native est flottante : elle n'annonce rien.** Un
bloc borné en hauteur ne montrait aucun indice qu'il restait à lire, les
barres modernes n'apparaissant qu'en défilant. Elle est dessinée à la main,
et seulement sur les blocs qui débordent — une feuille de style ne sait pas
mesurer un débordement, il faut le repérer en JS.

**Un élément centré en absolu rencontre son voisin quand l'écran rétrécit.**
Le monogramme de l'en-tête, posé à `left: 50%`, passait par-dessus le nom de
l'entreprise sous 780 px. Le masquer coûtait l'identité visuelle ; la bonne
réponse est de le faire rentrer dans le flux et de centrer le groupe.

**Deux boutons dont la position dépend du contenu se cherchent à chaque
image.** Les flèches de la visionneuse encadraient la photo : une image
verticale les rapprochait, une panoramique les écartait. Ancrées aux bords de
la fenêtre, elles ne bougent plus.

**`backdrop-filter` interdit de calculer la couleur composite.** Vouloir
choisir la teinte d'une barre translucide pour que le rendu final atteigne un
contraste donné ne marche pas : le flou et la saturation s'appliquent avant
que la couche ne se pose. Il faut mesurer les pixels réellement peints.

**Supprimer un bloc de code voisin emporte parfois une méthode utilisée.**
En sortant la FAQ de la page contact, `contact()` et `contactEnvoi()` sont
partis avec — la route existait toujours, d'où une erreur 500. Après toute
suppression, croiser les méthodes appelées par les routes avec celles
réellement définies.

---

## 11. Vérifications utiles

Le développement s'est appuyé sur des mesures plutôt que sur l'œil :

- **Contraste** — parcourir toutes les pages, composer les fonds translucides
  et comparer au seuil WCAG AA (4,5:1, ou 3:1 pour le grand texte). Un audit
  qui ne compose pas les couches produit des faux positifs.
- **Animations** — échantillonner les styles calculés image par image plutôt
  que de juger sur une capture ; c'est ce qui a permis de chiffrer un saut à
  0,045 puis de vérifier qu'il tombait à 0,0007.
- **Débordement** — surveiller `scrollHeight - clientHeight` pendant une
  animation pour détecter un ascenseur qui clignote.
- **Traceurs** — compter les requêtes réseau vers les domaines tiers après un
  refus de consentement. La bonne valeur est zéro.
- **Téléphone** — parcourir toutes les pages à 320, 390 et 768 px et relever
  tout élément qui sort du cadre. Écarter les faux positifs : une piste qui
  défile horizontalement déborde par construction, et un élément à opacité
  nulle n'est pas encore révélé.
- **Cibles tactiles** — relever les liens et boutons de moins de 40 px de
  haut. La marge intérieure les agrandit sans toucher à la taille du texte ;
  `background-clip: content-box` garde la peinture sur le rectangle visible.
- **Routes** — croiser les méthodes appelées dans les tables de routage avec
  celles définies dans les contrôleurs, puis ouvrir chaque écran du
  back-office et vérifier le code HTTP.
