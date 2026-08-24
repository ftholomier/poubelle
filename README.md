# Menuiserie Tréhant — Pergolas & carports

Site vitrine dédié à l'activité pergola et carport de Menuiserie Tréhant :
pergolas bioclimatiques, pergolas à toile rétractable, toitures fixes,
carports aluminium et fermetures. PHP natif, contenu en JSON, aucune
dépendance (ni Composer, ni base de données, ni build front).

Le site est volontairement mono-activité : il ne présente que la gamme
pergola / carport, la société et son savoir-faire. Textes, caractéristiques
techniques, photographies, logo et couleurs sont repris du site existant
`menuiserietrehant.fr`. Liseré bleu-blanc-rouge en tête de page et au-dessus
du pied, badge « Fabriqué en France / RGE » de l'entreprise dans le pied.

Bâti sur le socle décrit dans **[KIT.md](KIT.md)** — architecture et
conventions inchangées.

## Arborescence

```
public/     Racine web (DocumentRoot) — seul répertoire exposé
app/        Cœur applicatif : routeur, vues, contenu, contrôleurs
config/     Configuration
data/       Contenu éditorial en JSON (écrit par le back-office)
views/      Gabarits PHP (pages publiques + back-office)
storage/    Cache, sauvegardes de contenu (hors git)
```

> **Pas de base de données.** Aucun fichier SQL à importer : le contenu vit
> dans `data/*.json`. Sauvegarder le site = copier `data/` et
> `public/assets/img/site/`.

## Déploiement

1. PHP 8.1+ avec les extensions `gd`, `fileinfo`, `mbstring`, `openssl`.
2. Pointer le DocumentRoot sur `public/` (le `.htaccess` fourni gère la
   réécriture, la redirection HTTPS et les en-têtes de sécurité).
3. Donner au serveur web les droits d'écriture sur `data/` et `storage/`.
4. Ouvrir `/admin` : l'écran de première configuration crée le compte
   administrateur (aucun identifiant par défaut, par sécurité).
5. Régler l'envoi des e-mails dans **Paramètres** (SMTP + destinataire du
   formulaire), puis lancer le test d'envoi.

Guide pas à pas pour o2switch, commandes SSH comprises :
**[DEPLOIEMENT.md](DEPLOIEMENT.md)**.

En local : `php -S localhost:8080 -t public public/index.php`

## Pages

`/` · `/pergolas-carports` (+ une page par gamme) · `/realisations` ·
`/savoir-faire` · `/la-societe` · `/faq` · `/contact` · `/mentions-legales`

Les quatre gammes : pergola à lames orientables, pergola à toile rétractable,
pergola à toiture fixe, carport.

**Réalisations** — une galerie filtrable par catégorie, avec visionneuse au
clavier. Les boutons de filtre se construisent à partir des catégories
réellement saisies : aucune liste à tenir à jour dans le code. Sans
JavaScript, la galerie s'affiche entière et les filtres restent cachés.

Les adresses du site précédent sont redirigées en 301 : `/pergola-carport`
mène à `/pergolas-carports`, `/pergola` à la pergola bioclimatique et
`/carport` au carport. Les slugs restent modifiables depuis l'écran
**Référencement**, qui crée alors la redirection correspondante.

## Charte

| Rôle | Jeton | Valeur |
|---|---|---|
| Orange de la marque — boutons, icônes, filets, puces, focus | `--orange` | `#f39200` |
| Survol des aplats orange | `--orange-sombre` | `#d97f00` |
| Petit texte orange sur fond blanc | `--orange-texte` | `#a35f00` |
| Grands chiffres (01, 02, millésimes) | `--orange-chiffre` | `#c97600` |
| Gris | `--encre` `--texte` `--texte-doux` | `#1a1a1a` `#313131` `#616161` |
| Texte courant | | Raleway, police du site actuel, auto-hébergée |
| Titrage | | Playfair Display |

`#f39200` est l'orange du logo, et c'est lui que l'on retrouve partout : fond
des boutons, icônes, filets, puces, contours de focus, accents sur fond
sombre — où il tient 7,4:1, y compris dans le back-office.

Une seule contrainte le suit : il ne tient que **2,35:1 sur blanc**. Un texte
blanc posé dessus serait donc illisible ; le texte porté par l'orange est
composé à l'encre, où il tient **7,4:1** — c'est aussi ce qui donne aux
boutons leur tenue. Et le petit texte orange posé *sur* du blanc (sur-titres,
liens « En savoir plus ») passe par `--orange-texte`, la même teinte
assombrie à 5,0:1. Pour un orange strictement uniforme au prix de la
lisibilité, il suffit d'écrire `#f39200` dans ce jeton, en tête de
`public/assets/css/site.css`.

## À renseigner avant la mise en ligne

| Quoi | Où |
|---|---|
| Adresse e-mail et horaires d'ouverture (absents du site actuel) | Admin → Site |
| SIRET et hébergeur | Admin → Éditeur avancé → `pages/mentions-legales` |
| Une photo de pergola à **toile rétractable** — aucune n'existe sur le site actuel, la fiche affiche pour l'instant une pergola à lames | Admin → Photos |
| Fiche Google pour les avis | Admin → Avis Google |
| Clé d'API Gemini, si l'assistant doit être activé | Admin → Assistant IA |
| Réglages SMTP et destinataire du formulaire | Admin → Paramètres |

Tant qu'une photo manque, le visuel « photo à venir » s'affiche à sa place :
le site reste présentable, aucune image cassée.

## Back-office

`/admin` — édition de tout le contenu : coordonnées, horaires, menu, page
d'accueil (bande « Fabriqué en France » comprise), page de la société, fiches
de gamme, engagements du savoir-faire, page de contact avec ses questions
fréquentes, médiathèque.

Gammes et engagements s'ajoutent, se réordonnent, se retirent du site et se
suppriment depuis l'admin. Une gamme publiée apparaît automatiquement dans le
sous-menu, en pied de page et sur la page d'accueil — aucun menu à tenir à
jour à la main.

Chaque enregistrement conserve la version précédente (20 par contenu),
restaurable depuis l'Éditeur avancé. L'écran **Référencement** pilote
l'adresse (slug) de chaque page et de chaque fiche, les balises titre et
description, l'indexation et les redirections permanentes.

Connexion protégée : mot de passe haché, verrouillage 15 min après 5 échecs,
jetons CSRF sur tous les formulaires, sessions HttpOnly/SameSite.

### Disposition du menu

L'écran **Apparence** bascule la navigation entre deux dispositions, sans
toucher au contenu :

- **latérale** — burger, logo centré, panneau qui glisse depuis la gauche ;
- **horizontale** — logo à gauche, rubriques côte à côte, sous-menus déroulants.

Le balisage est le même dans les deux cas : seul l'affichage change. En
dessous de 1080 px, où une barre horizontale ne tiendrait pas, le panneau
latéral reprend automatiquement la main.

### Avis Google

L'écran **Avis Google** connecte la fiche Google de la société (API Places New).
Les avis sont récupérés **par le serveur** et conservés en cache douze heures :
le navigateur du visiteur ne contacte jamais Google, donc aucun cookie tiers,
rien à soumettre au consentement, et une page qui s'affiche à la vitesse du
statique. Une panne de Google laisse les derniers avis en place ; la section
disparaît d'elle-même quand il n'y a rien à montrer.

L'écran retrouve l'identifiant de la fiche (Place ID) par une simple recherche
sur le nom et la ville.

### Mise à jour depuis git

L'écran **Mises à jour** interroge le dépôt, liste ce qui n'est pas installé,
sauvegarde le contenu, puis remplace **uniquement** les chemins de code listés
dans `Deploiement::CODE`. Le contenu (`data/`), les photos du client et les
réglages ne sont jamais dans cette liste : une mise à jour ne peut pas les
écraser, même si le dépôt en contient une version différente.

## Multilingue

Le français est la langue source. Chaque autre langue est une traduction
mémorisée dans `data/traductions/<code>.json` et servie sur son propre
préfixe d'adresse : `/en/pergolas-carports`. Ce qui n'est pas traduit retombe sur
le français, jamais sur du vide.

Le site public ne contacte aucun service extérieur — il lit les fichiers.

## Assistant IA

Une bulle de discussion, en bas à droite, adossée à l'API Gemini. Elle
s'active depuis **Admin → Assistant IA** et disparaît entièrement du site
quand elle est décochée — aucun code n'est alors envoyé au visiteur.

**L'appel part du serveur, jamais du navigateur.** La clé d'API resterait
sinon lisible dans le code de la page, et le visiteur ouvrirait une connexion
vers Google — donc un traceur tiers à soumettre au consentement. Ici, le
navigateur ne parle qu'à ce site, via `POST /api/assistant`.

**Le modèle ne répond que sur trois sources**, et rien n'est laissé à ses
connaissances générales :

| Source | Où | Comment elle est lue |
|---|---|---|
| Le contenu du site | `data/*.json` | aplati en texte, sans les chemins d'images ni les clés techniques |
| Des documents | `data/assistant/documents/` (hors racine web) | PDF envoyés tels quels au modèle, `.docx` extrait via ZipArchive, `.txt` / `.md` bruts |
| Des notes | éditeur riche du back-office | balisage filtré à l'enregistrement |

Trois garde-fous s'y emploient : le corpus est passé en entier dans la
requête, la consigne système l'interdit explicitement, et **aucun outil n'est
déclaré** — le modèle n'a ni recherche web ni exécution de code. À défaut de
réponse dans les sources, il répond « je n'ai pas cette information » et
renvoie vers le téléphone.

La **liste des modèles est interrogée chez Google**, pas écrite en dur : les
nouveaux modèles y apparaissent d'eux-mêmes, sans mise à jour du code. Elle
est mise en cache 24 h, et un échec est mis au repos 30 min — une clé révoquée
ne ralentit donc pas le back-office à chaque affichage.

Garde-fous d'usage : 800 caractères par question, 30 questions par session et
par heure, jeton CSRF vérifié sur chaque appel. L'écran d'administration
permet de poser une question d'essai avant d'activer la bulle.

## Correspondance des noms internes

Le socle nomme ses deux collections `services` et `valeurs`. Elles portent ici
les **gammes** (pergolas, carports, fermetures) et les **engagements** du
savoir-faire. Les clés internes ont été conservées volontairement : elles
n'apparaissent nulle part côté visiteur — les adresses (`/pergolas-carports`,
`/savoir-faire`) et les libellés viennent de `Seo::PAGES` et de `data/` — et
les renommer aurait touché des dizaines de fichiers pour un gain nul, au
risque d'attraper au passage les `valeur` du code PHP ordinaire.

| Clé interne | Ce que c'est sur le site | Adresse |
|---|---|---|
| `services` | Les gammes de pergolas et carports | `/pergolas-carports` |
| `valeurs` | Les engagements du savoir-faire | `/savoir-faire` |
| `la-societe` | La page société | `/la-societe` |
| `realisations` | La galerie de chantiers | `/realisations` |

## API JSON (lecture)

`/api/services`, `/api/services/{slug}`, `/api/valeurs`, `/api/realisations`

`POST /api/assistant` répond à une question de l'assistant. Jeton CSRF exigé,
dans le corps JSON (`_csrf`) ou dans l'en-tête `X-CSRF-Token`.
