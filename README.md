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

## Déploiement

1. PHP 8.1+ avec les extensions `gd`, `fileinfo`, `session` (incluses par défaut).
2. Pointer le DocumentRoot sur `public/` (le `.htaccess` fourni gère la
   réécriture, la redirection HTTPS et les en-têtes de sécurité).
3. Donner au serveur web les droits d'écriture sur `data/` et `storage/`.
4. Ouvrir `/admin` : l'écran de première configuration crée le compte
   administrateur (aucun compte par défaut).

En local : `php -S localhost:8080 -t public public/index.php`

## Back-office

`/admin` — édition de tout le contenu (coordonnées, liens Reservit, accueil,
hébergements, étangs et tarifs, boutique, règlement, galerie avec upload
d'images optimisées automatiquement, photos de chaque hébergement et étang).
Chaque enregistrement sauvegarde la
version précédente (20 conservées par contenu), restaurables depuis
l'Éditeur avancé. Connexion protégée : mot de passe haché, verrouillage
15 min après 5 échecs, jetons CSRF sur tous les formulaires, sessions
HttpOnly/SameSite.

## API JSON (lecture)

`/api/hebergements`, `/api/hebergements/{slug}`, `/api/peche`,
`/api/peche/{slug}`, `/api/galerie`
