# Site à particules — PHP natif

Un site vitrine plein écran dans l'esprit de [usta.agency](https://usta.agency) : un nuage
de plusieurs milliers de particules occupe le fond en permanence et **se recompose en un
dessin différent à chaque section**.

La particularité tient au pilotage : **vous décidez du dessin de chaque section en écrivant
une ligne de JSON.** Un fichier SVG, une image, une forme mathématique ou un mot — le serveur
se charge de le convertir en nuage de points.

Le contenu de démonstration reprend celui de [le-digital.com](https://le-digital.com).

---

## Pile technique

| | |
|---|---|
| Serveur | PHP 8.1+ natif, sans framework ni Composer |
| Données | fichiers JSON dans `content/`, **aucune base de données** |
| API | JSON et Float32 binaire |
| Front | JavaScript en modules ES, Three.js embarqué localement |
| Racine web | `public/` uniquement |

Extensions PHP requises : `json`, `dom`, `mbstring`, et `gd` si vous échantillonnez des images.

---

## Démarrage

```bash
./tools/serve.sh 8000
```

- <http://localhost:8000> — le site
- <http://localhost:8000/labo> — le laboratoire de formes
- <http://localhost:8000/api> — la documentation de l'API

En production, faites pointer la racine web sur `public/`. Un `.htaccess` est fourni pour
Apache, et `docs/nginx.conf.example` pour nginx.

```bash
php tests/run.php                          # 54 tests hors ligne
php tests/run.php http://localhost:8000    # + les 10 tests de l'API
```

---

## Choisir le dessin d'une section

Tout se passe dans `content/sections.json`. Chaque section porte une clé `shape` :

```json
{
  "id": "solutions",
  "shape": {
    "type": "svg",
    "src": "shapes/engrenage.svg",
    "mode": "fill",
    "count": 15000,
    "spin": 0.25,
    "spinAxis": "z",
    "label": "Engrenage — les six leviers qui s'emboîtent"
  }
}
```

Rechargez la page : le dessin a changé. Rien d'autre à toucher.

### Les quatre types de dessin

**`svg`** — un fichier vectoriel déposé dans `content/shapes/`.

```json
{ "type": "svg", "src": "shapes/fusee.svg", "mode": "fill" }
```

| Clé | Valeurs | Rôle |
|---|---|---|
| `mode` | `fill` · `outline` | Remplir la surface, ou suivre seulement le contour |
| `fillRule` | `nonzero` · `evenodd` | `evenodd` pour les formes ajourées (anneaux, lettres à contre-forme) |

**`image`** — un PNG, JPEG, GIF ou WEBP, échantillonné pixel par pixel.

```json
{ "type": "image", "src": "shapes/portrait.png", "criterion": "dark" }
```

| `criterion` | Particules placées sur… |
|---|---|
| `auto` | détection automatique (transparence si présente, sinon zones sombres) |
| `alpha` | les zones opaques — idéal pour un logo détouré |
| `dark` | les zones sombres — idéal pour un dessin noir sur blanc |
| `light` | les zones claires — idéal pour un visuel clair sur fond noir |

**`preset`** — une forme mathématique, sans aucun fichier.

```json
{ "type": "preset", "preset": "galaxy", "spin": 0.2 }
```

`sphere` · `globe` · `torus` · `galaxy` · `wave` · `grid` · `cube` · `helix` ·
`tunnel` · `ring` · `cloud` · `cone` · `heart` · `infinity`

**`text`** — un mot, tracé avec la police du site.

```json
{ "type": "text", "text": "H2H" }
```

### Réglages communs

| Clé | Défaut | Effet |
|---|---|---|
| `count` | `12000` | Nombre de particules (64 à 40 000) |
| `depth` | `0.12` | Épaisseur donnée à un dessin plat, pour qu'il ne soit pas une feuille |
| `scale` | `1.0` | Taille dans le cadre |
| `spin` | `0` | Vitesse de rotation continue |
| `spinAxis` | `y` | `y` pour un volume, **`z` pour un dessin plat** (sinon il disparaît de profil) |
| `seed` | `1337` | Graine du tirage — changez-la pour une autre répartition |
| `label` | — | Légende affichée en bas de l'écran |

### Écriture courte

```json
"shape": "galaxy"                 // équivaut à { "type": "preset", "preset": "galaxy" }
"shape": "shapes/fusee.svg"       // équivaut à { "type": "svg", "src": "shapes/fusee.svg" }
```

---

## Ajouter votre propre dessin

1. Déposez le fichier dans `content/shapes/` (SVG de préférence).
2. Ouvrez `/labo`, sélectionnez-le, réglez densité, épaisseur et rotation.
3. Cliquez sur **Copier** et collez le bloc obtenu dans `content/sections.json`.

**Ce qui rend bien en particules :** une silhouette franche, peu de détails fins, des
aplats plutôt que des traits de moins d'un point d'épaisseur. Un logo monochrome ou un
pictogramme fonctionne parfaitement ; une illustration détaillée devient une tache.

Formes livrées : `fusee` · `ampoule` · `cible` · `croissance` · `bulle` · `eclair` ·
`engrenage` · `oeil` · `mains` · `logo-ld`, plus deux logos clients dans `shapes/logos/`.

---

## API

| Méthode | Route | Réponse |
|---|---|---|
| `GET` | `/api` | Documentation des points d'entrée |
| `GET` | `/api/site` | Réglages globaux, navigation, thème |
| `GET` | `/api/sections` | Sections éditoriales et forme associée |
| `GET` | `/api/shapes` | Catalogue : fichiers disponibles et préréglages |
| `GET` | `/api/shape/{id}` | Nuage de points d'une section |
| `GET` | `/api/preview?…` | Nuage calculé à la volée, depuis des paramètres d'URL |
| `GET` | `/health` | État du service |

Ajoutez `?format=bin` à `/api/shape/{id}` ou `/api/preview` pour obtenir les positions en
Float32 brut (trois flottants par particule, petit-boutiste). C'est environ **quatre fois
plus léger** que le JSON équivalent et directement transférable dans un `Float32Array`,
sans passe d'analyse côté navigateur. C'est ce que fait le site.

```bash
curl 'http://localhost:8000/api/shape/hero?format=bin' -o hero.bin   # 192 Ko pour 16 000 points
curl 'http://localhost:8000/api/preview?type=preset&preset=torus&count=5000'
```

---

## Comment ça marche

### Côté serveur : du dessin au nuage de points

`src/Shape/` contient un convertisseur autonome, écrit sans aucune bibliothèque.

1. **`PathParser`** lit l'attribut `d` d'un chemin SVG et aplatit les courbes — cubiques,
   quadratiques, arcs elliptiques, commandes lisses, coordonnées relatives — en polylignes.
2. **`SvgSampler`** parcourt le document, convertit chaque primitive (`path`, `circle`,
   `rect`, `polygon`…) et applique les `transform` cumulés des parents.
3. **`ScanlineFill`** remplit la surface. Plutôt que de tirer des points au hasard en
   testant chacun contre toutes les arêtes, il découpe la forme en lignes de balayage et
   range chaque arête dans les seules lignes qu'elle traverse. Sur le logo le plus complexe
   du dépôt (41 000 sommets), le temps de calcul passe de **33 s à 92 ms**.
4. **`ShapeService`** normalise le résultat dans le cube `[-1, 1]`, inverse l'axe Y (le SVG
   descend, WebGL monte) et conserve le nuage en cache disque.

Le tirage est déterministe : une même graine redonne exactement le même nuage, sur
n'importe quelle machine.

### Côté navigateur : le morphing

Un seul objet `THREE.Points` est alloué au chargement, dimensionné sur la forme la plus
dense. Chaque particule porte deux positions — celle d'où elle vient, celle où elle va — et
un aléa propre. Le morphing se joue entièrement dans le nuanceur de sommets : un unique
curseur `uProgress` fait voyager les 16 000 points d'un dessin à l'autre, avec un léger
retard par particule pour que le nuage se déplie au lieu de sauter.

Changer de section ne réalloue donc rien : seul l'attribut « cible » est réécrit.

### Repli

- **Sans WebGL** : le canevas est retiré, un dégradé le remplace, le contenu reste entier.
- **Mouvement réduit** (`prefers-reduced-motion`) : morphing instantané, aucune turbulence,
  textes affichés sans animation.
- **Écran tactile** : le défilement natif reprend la main.
- **Forme illisible ou absente** : repli silencieux sur une sphère, la page ne tombe jamais.

---

## Arborescence

```
bootstrap.php          Chemins et autoload PSR-4 maison
content/
  site.json            Nom, thème, navigation, pied de page
  sections.json        ← le fichier à éditer : contenu et dessin de chaque section
  shapes/              Sources des dessins (SVG, images)
src/
  Config.php           Réglages, surchargeables par config.local.php
  Content.php          Lecture et normalisation du JSON éditorial
  View.php             Rendu des gabarits, échappement
  Http/                Routeur, réponses, contrôleur d'API
  Shape/               Conversion dessin → nuage de points
views/                 Gabarits PHP, un partiel par type de section
public/                ← racine web
  index.php            Contrôleur frontal
  assets/js/           Moteur de particules et interface
  assets/css/
tests/run.php          Suite de tests sans dépendance
var/cache/             Nuages calculés (régénérés automatiquement)
```

## Réglages

Créez `config.local.php` à la racine (ignoré par git) :

```php
<?php
return [
    'shape.max_points' => 60000,
    'cache.enabled'    => false,   // pratique en développement
    'debug'            => true,
];
```

Le cache se vide en supprimant `var/cache/*.json` ; il se reconstruit tout seul, et se
régénère de lui-même dès qu'un fichier source est modifié.

## Licence des dépendances

[Three.js](https://threejs.org) r160 (licence MIT) est embarqué dans
`public/assets/js/vendor/`, avec sa licence. Aucun autre code tiers, aucun CDN.
