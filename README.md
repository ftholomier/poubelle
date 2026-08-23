# Cabinet Villard — Expertise comptable

Site vitrine du Cabinet Villard (Colombier Fontaine, 25) : comptabilité,
audit, gestion sociale et conseil. PHP natif, contenu en JSON, aucune
dépendance (ni Composer, ni base de données, ni build front).

Refonte du site WordPress existant, reconstruite sur un socle sans
dépendances. Voir **[KIT.md](KIT.md)** pour l'architecture et les conventions.

## Arborescence

```
public/     Racine web (DocumentRoot) — seul répertoire exposé
app/        Cœur applicatif : routeur, vues, contenu, contrôleurs
config/     Configuration
data/       Contenu éditorial en JSON (écrit par le back-office)
views/      Gabarits PHP (pages publiques + back-office)
storage/    Cache, sauvegardes de contenu (hors git)
tools/      Scripts utilitaires (dérivation des variantes du logo)
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

`/` · `/le-cabinet` · `/nos-services` (+ une page par service) ·
`/nos-valeurs` · `/contact` · `/mentions-legales`

Les anciennes adresses WordPress sont redirigées en 301 : `/a-propos` mène à
`/le-cabinet`.

## Back-office

`/admin` — édition de tout le contenu : coordonnées, horaires, menu, page
d'accueil, page du cabinet, fiches de service, valeurs, page de contact avec
ses questions fréquentes, médiathèque.

Services et valeurs s'ajoutent, se réordonnent, se retirent du site et se
suppriment depuis l'admin. Un service publié apparaît automatiquement dans le
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

L'écran **Avis Google** connecte la fiche Google du cabinet (API Places New).
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
préfixe d'adresse : `/en/nos-services`. Ce qui n'est pas traduit retombe sur
le français, jamais sur du vide.

Le site public ne contacte aucun service extérieur — il lit les fichiers.

## API JSON (lecture)

`/api/services`, `/api/services/{slug}`, `/api/valeurs`
