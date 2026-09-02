# Site à particules — PHP natif

Un site vitrine multi-pages dans l'esprit de [usta.agency](https://usta.agency) : un nuage
de plusieurs milliers de particules occupe le fond en permanence, **se recompose en un
dessin différent à chaque section**, et une poussière d'ambiance couvre toute la page en
suivant la souris et le défilement.

Deux choses le distinguent d'une maquette :

- **Vous choisissez le dessin de chaque section** — un SVG, une image, une forme
  mathématique ou un mot — depuis un back-office privé ou une ligne de JSON.
- **Vous choisissez la couleur dominante du site.** Fond, textes, bordures, halos,
  particules et poussière en découlent. Un seul champ repeint tout.

Le contenu de démonstration reprend celui de [le-digital.com](https://le-digital.com).

---

## Pile technique

| | |
|---|---|
| Serveur | PHP 8.1+ natif, sans framework ni Composer |
| Données | fichiers JSON dans `content/`, **aucune base de données** |
| API | JSON et Float32 binaire |
| Front | JavaScript en modules ES, Three.js embarqué localement, aucun CDN |
| Racine web | `public/` uniquement |

Extensions requises : `json`, `dom`, `mbstring`, et `gd` pour échantillonner des images.

---

## Démarrage

```bash
./tools/serve.sh 8000        # http://localhost:8000
php tools/admin-password.php # définit le mot de passe du back-office
```

- <http://localhost:8000> — le site
- <http://localhost:8000/admin> — le back-office (privé)
- <http://localhost:8000/api> — la documentation de l'API

En production, faites pointer la racine web sur `public/`. Un `.htaccess` est fourni pour
Apache, et `docs/nginx.conf.example` pour nginx.

### Tests

```bash
php tests/run.php                          # 70 tests hors ligne
php tests/run.php http://localhost:8000    # + 12 tests de l'API et du back-office

# Bout en bout, dans un vrai navigateur (Playwright requis)
node tests/browser.mjs http://localhost:8000 playwright "mot-de-passe-admin"
```

La suite navigateur vérifie ce que la suite PHP ne peut pas voir : que le nuage est
**réellement visible** — elle compte les pixels allumés, un statut « nuage calculé » ne
prouvant rien —, qu'il change de forme d'une section et d'une page à l'autre, qu'aucun
texte n'est rogné de 360 à 1920 px, que la navigation ne recharge pas la page, qu'un lien
atteint au clavier est bien amené à l'écran, et que le back-office fait son travail.

---

## Le back-office

Tout ce qui n'a pas à être public vit sous `/admin`, derrière un mot de passe.

| Écran | Ce qu'on y fait |
|---|---|
| `/admin` | Vue d'ensemble : pages, sections, couleur en cours |
| `/admin/pages` | Toutes les pages et le dessin de chacune de leurs sections |
| `/admin/formes` | **L'atelier** : composer un dessin en particules et l'affecter à une section |
| `/admin/theme` | La couleur dominante du site, avec aperçu en direct |

Le mot de passe se définit en ligne de commande (`php tools/admin-password.php`) et son
empreinte est stockée dans `var/admin.json`, hors de la racine web. **Aucune page publique
ne permet d'en créer un** : sur un site fraîchement mis en ligne, le premier visiteur venu
pourrait s'en emparer.

Le reste de la protection : session régénérée à la connexion, expiration après deux heures
d'inactivité, jeton anti-CSRF sur chaque écriture, et blocage de l'adresse après cinq
tentatives ratées.

Chaque écriture est atomique et précédée d'une sauvegarde dans `var/backups/`. Une forme
que le moteur ne sait pas construire est **refusée avant enregistrement** plutôt que de
casser la page en production.

---

## Choisir la couleur du site

Depuis `/admin/theme`, ou dans `content/site.json` :

```json
"theme": {
  "dominant": "#7b01f7",
  "harmony": "duo"
}
```

Quatre modes de dérivation :

| Harmonie | Les deux couleurs d'appoint |
|---|---|
| `analogue` | Dans la même famille — toujours sûr |
| `complementaire` | Contraste franc |
| `duo` | Deux pôles opposés, chaud et froid, comme un ciel étoilé |
| `monochrome` | Une seule teinte, jouée en luminosité |

En découlent l'accent, ses deux compléments, le fond (teinté par la dominante : deux
couleurs ne donnent pas le même noir), les trois nuances de texte, les bordures, les halos
et les couleurs des particules. Le contraste du texte est vérifié et corrigé
automatiquement : il reste au-dessus de 7:1 quelle que soit la couleur choisie.

Un gris reste gris — aucune teinte ne lui est inventée. Et toute clé posée à la main dans
`theme` (`accent3`, `background`, `muted`…) prime sur la dérivation.

---

## Ajouter une page

Déposez un fichier `content/pages/ma-page.json` :

```json
{
  "title": "Ma page",
  "navLabel": "Ma page",
  "order": 5,
  "meta": { "description": "…" },
  "sections": [
    { "id": "intro", "kind": "hero", "title": ["Bonjour"], "shape": "galaxy" }
  ]
}
```

Elle apparaît aussitôt sur `/ma-page`, dans le menu et dans le back-office. `order` fixe sa
place dans le menu, `"inNav": false` la garde hors menu. Le nom du fichier devient l'URL —
il doit donc rester simple : minuscules, chiffres et tirets.

`accueil.json` est servi à la racine.

### Types de section

| `kind` | Rendu |
|---|---|
| `hero` | Grand titre pleine hauteur |
| `statement` | Intertitre et texte sur deux colonnes |
| `cards` | Grille de cartes |
| `marquee` | Bandeau de texte défilant, plein et contour alternés |
| `columns` | Colonnes de listes, précédées d'un bandeau — la section « domaines d'expertise » |
| `stats` | Chiffres animés au défilement |
| `formula` | Une formule en très grand, en dégradé |
| `quote` | Citation |
| `contact` | Titre et boutons d'action |

`"outlineFrom": 1` trace au trait les lignes de titre à partir du rang indiqué : c'est le
contraste plein / contour caractéristique de ce genre de site.

---

## Choisir le dessin d'une section

Depuis `/admin/formes`, ou directement dans le JSON de la page :

```json
"shape": {
  "type": "svg",
  "src": "shapes/engrenage.svg",
  "mode": "fill",
  "count": 15000,
  "spin": 0.25,
  "spinAxis": "z",
  "offsetX": 0.45,
  "label": "Engrenage — les six leviers qui s'emboîtent"
}
```

### Les quatre types de dessin

**`svg`** — un fichier vectoriel déposé dans `content/shapes/`.

| Clé | Valeurs | Rôle |
|---|---|---|
| `mode` | `fill` · `outline` | Remplir la surface, ou suivre seulement le contour |
| `fillRule` | `nonzero` · `evenodd` | `evenodd` pour les formes ajourées (anneaux, contre-formes) |

**`image`** — un PNG, JPEG, GIF ou WEBP, échantillonné pixel par pixel.

| `criterion` | Particules placées sur… |
|---|---|
| `auto` | détection automatique (transparence si présente, sinon zones sombres) |
| `alpha` | les zones opaques — idéal pour un logo détouré |
| `dark` | les zones sombres — idéal pour un dessin noir sur blanc |
| `light` | les zones claires — idéal pour un visuel clair sur fond noir |

**`preset`** — une forme mathématique, sans aucun fichier : `sphere` · `globe` · `torus` ·
`galaxy` · `wave` · `grid` · `cube` · `helix` · `tunnel` · `ring` · `cloud` · `cone` ·
`heart` · `infinity`

**`text`** — un mot, tracé avec la police du site.

### Réglages communs

| Clé | Défaut | Effet |
|---|---|---|
| `count` | `12000` | Nombre de particules (64 à 40 000) |
| `depth` | `0.12` | Épaisseur donnée à un dessin plat, pour qu'il ne soit pas une feuille |
| `scale` | `1.0` | Taille dans le cadre |
| `offsetX` / `offsetY` | `0` | Décalage dans le cadre, de -1 à 1 — pour libérer la place au texte |
| `spin` | `0` | Vitesse de rotation continue |
| `spinAxis` | `y` | `y` pour un volume, **`z` pour un dessin plat** (sinon il disparaît de profil) |
| `seed` | `1337` | Graine du tirage — changez-la pour une autre répartition |
| `label` | — | Légende affichée en bas de l'écran |

Écriture courte : `"shape": "galaxy"` ou `"shape": "shapes/fusee.svg"`.

### Ajouter votre propre dessin

1. Déposez le fichier dans `content/shapes/` (SVG de préférence).
2. Ouvrez `/admin/formes`, sélectionnez-le, réglez densité, épaisseur, décalage, rotation.
3. Choisissez la section cible et enregistrez.

**Ce qui rend bien en particules :** une silhouette franche, peu de détails fins, des
aplats plutôt que des traits d'un point d'épaisseur. Un logo monochrome ou un pictogramme
fonctionne parfaitement ; une illustration détaillée devient une tache.

Formes livrées : `fusee` · `ampoule` · `cible` · `croissance` · `bulle` · `eclair` ·
`engrenage` · `oeil` · `mains` · `logo-ld`, plus deux logos clients dans `shapes/logos/`.

---

## API

| Méthode | Route | Réponse |
|---|---|---|
| `GET` | `/api` | Documentation des points d'entrée |
| `GET` | `/api/site` | Réglages globaux et charte dérivée |
| `GET` | `/api/pages` | Liste des pages et navigation |
| `GET` | `/api/page/{slug}` | Sections d'une page et forme de chacune |
| `GET` | `/api/shapes` | Catalogue : fichiers disponibles et préréglages |
| `GET` | `/api/shape/{page}/{section}` | Nuage de points d'une section |
| `GET` | `/api/preview?…` | Nuage calculé à la volée depuis des paramètres d'URL |
| `GET` | `/health` | État du service |

`?format=bin` renvoie les positions en Float32 brut (trois flottants par particule,
petit-boutiste) : environ **quatre fois plus léger** que le JSON équivalent et directement
transférable dans un `Float32Array`, sans passe d'analyse côté navigateur. C'est ce que
fait le site.

```bash
curl 'http://localhost:8000/api/shape/accueil/hero?format=bin' -o hero.bin   # 192 Ko / 16 000 points
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
   du dépôt (41 000 sommets), le calcul passe de **33 s à 92 ms**.
4. **`ShapeService`** normalise le résultat dans le cube `[-1, 1]`, inverse l'axe Y (le SVG
   descend, WebGL monte) et conserve le nuage en cache disque.

Le tirage est déterministe : une même graine redonne exactement le même nuage.

### Côté navigateur : le morphing

Un seul objet `THREE.Points` est alloué au chargement, dimensionné sur la forme la plus
dense du site. Chaque particule porte deux positions — d'où elle vient, où elle va — et un
aléa propre. Le morphing se joue entièrement dans le nuanceur de sommets : un unique
curseur `uProgress` fait voyager les 16 000 points d'un dessin à l'autre, avec un léger
retard par particule pour que le nuage se déplie au lieu de sauter.

Changer de section — ou de page — ne réalloue donc rien : seul l'attribut « cible » est
réécrit.

### La poussière d'ambiance

Une seconde nappe, indépendante, répartie dans tout le volume visible et bien au-delà en
profondeur. Les grains proches suivent franchement la souris et le défilement, les
lointains bougent à peine : c'est ce décalage qui crée la sensation d'espace. La nappe est
cyclique — ce qui sort d'un côté rentre de l'autre — elle reste donc dense quelle que soit
la longueur de la page.

### La navigation entre les pages

Un clic sur un lien interne ne recharge pas le document : seul le corps est remplacé, ce
qui laisse le nuage de particules en place et lui permet de se transformer vers le premier
dessin de la nouvelle page. Au moindre incident, on laisse le navigateur suivre le lien
normalement.

### Replis

- **Sans WebGL** : le canevas est retiré, un dégradé le remplace, le contenu reste entier.
- **Mouvement réduit** (`prefers-reduced-motion`) : morphing instantané, aucune
  turbulence, bandeaux immobiles, textes affichés sans animation.
- **Écran tactile** : le défilement natif reprend la main.
- **Forme illisible ou absente** : repli silencieux sur une sphère, la page ne tombe jamais.

### Une note sur le défilement lissé

Le contenu est déplacé par transformation CSS, ce qui donne le glissé caractéristique de
ces sites — mais le navigateur ne sait alors plus amener un élément à l'écran tout seul :
`scrollIntoView` devient inopérant, et un lien atteint à la tabulation resterait invisible.
Le site s'en charge à sa place (`SmoothScroll.scrollToElement`, et un rattrapage sur
`focusin`). Si vous ajoutez du code qui fait défiler la page, passez par là plutôt que par
`scrollIntoView`.

---

## Arborescence

```
bootstrap.php          Chemins et autoload PSR-4 maison
content/
  site.json            Nom, couleur dominante, pied de page
  pages/*.json         ← une page par fichier : contenu et dessins
  shapes/              Sources des dessins (SVG, images)
src/
  Config.php           Réglages, surchargeables par config.local.php
  Content.php          Lecture et normalisation du contenu
  View.php             Rendu des gabarits, échappement
  Admin/               Authentification, écritures, contrôleur du back-office
  Http/                Routeur, réponses, API, pages publiques
  Shape/               Conversion dessin → nuage de points
  Theme/               Couleur et dérivation de la charte
views/                 Gabarits, un partiel par type de section
public/                ← racine web
  index.php            Contrôleur frontal
  assets/js/           Moteur de particules, interface, back-office
  assets/css/
tests/run.php          Suite PHP, sans dépendance
tests/browser.mjs      Suite navigateur (Playwright)
var/                   Cache, sauvegardes, empreinte du mot de passe — hors du web
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
régénère dès qu'un fichier source est modifié.

## Licence des dépendances

[Three.js](https://threejs.org) r160 (licence MIT) est embarqué dans
`public/assets/js/vendor/`, avec sa licence. Aucun autre code tiers, aucun CDN.
