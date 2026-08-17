# Étang Fourchu — Nature & Loisirs

Site vitrine du Domaine de l'Étang Fourchu (Florimont, 90) : lodges avec spa
privatif, gîte, pêche sportive et ferme biologique. PHP natif, contenu en
JSON, aucune dépendance (ni Composer, ni base de données).

## Arborescence

```
public/     Racine web (DocumentRoot) — seul répertoire exposé
app/        Cœur applicatif : routeur, vues, contenu, contrôleurs
config/     Configuration
data/       Contenu éditorial en JSON (écrit par le back-office)
views/      Gabarits PHP (pages publiques + back-office)
storage/    Cache, sauvegardes de contenu (hors git)
tools/      Scripts utilitaires (génération du logo)
```

> **Pas de base de données.** Aucun fichier SQL à importer : le contenu vit
> dans `data/*.json`. Sauvegarder le site = copier `data/` et
> `public/assets/img/site/`.

## Déploiement

1. PHP 8.1+ avec les extensions `gd`, `fileinfo`, `session` (incluses par défaut).
2. Pointer le DocumentRoot sur `public/` (le `.htaccess` fourni gère la
   réécriture, la redirection HTTPS et les en-têtes de sécurité).
3. Donner au serveur web les droits d'écriture sur `data/` et `storage/`.
4. Ouvrir `/admin` : l'écran de première configuration crée le compte
   administrateur (aucun identifiant par défaut, par sécurité).
5. Régler l'envoi des e-mails dans **Paramètres** (SMTP + destinataire du
   formulaire), puis lancer le test d'envoi.

Guide pas à pas pour o2switch : **[DEPLOIEMENT.md](DEPLOIEMENT.md)**.

En local : `php -S localhost:8080 -t public public/index.php`

## Back-office

`/admin` — édition de tout le contenu (coordonnées, liens Reservit, accueil,
hébergements, étangs et tarifs, boutique, règlement, galerie avec upload
d'images optimisées automatiquement, photos de chaque hébergement et étang).
Hébergements, produits de la boutique et photos du bandeau d'accueil
s'ajoutent, se réordonnent, se masquent et se suppriment depuis l'admin.
Chaque enregistrement sauvegarde la
version précédente (20 conservées par contenu), restaurables depuis
l'Éditeur avancé. L'écran **Référencement** pilote l'adresse (slug) de chaque
page et de chaque fiche, les balises titre et description, l'indexation et
les redirections permanentes. Connexion protégée : mot de passe haché, verrouillage
15 min après 5 échecs, jetons CSRF sur tous les formulaires, sessions
HttpOnly/SameSite.

## Multilingue

Le français est la langue source. Chaque autre langue est une traduction
mémorisée dans `data/traductions/<code>.json` et servie sur son propre
préfixe d'adresse : `/en/hebergements`. Ce qui n'est pas traduit retombe sur
le français, jamais sur du vide.

Écran **Langues** : ajout d'une langue, traduction automatique sans clé d'API
(le résultat est enregistré puis relu à la main), mise en ligne, suppression.
Le site public ne contacte aucun service extérieur — il lit les fichiers.

## API JSON (lecture)

`/api/hebergements`, `/api/hebergements/{slug}`, `/api/peche`,
`/api/peche/{slug}`, `/api/galerie`
