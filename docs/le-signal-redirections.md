# Le Signal — table de redirections

Les URL du site WordPress étaient bourrées de mots-clés
(`/coworking-montbeliard-belfort-location-bureau-prive-openspace/` pour… les
mentions légales). La refonte donne à chaque page **un seul groupe de mots
recherchés** (`/coworking-montbeliard`, `/location-bureaux-montbeliard`) et
redirige toutes les anciennes adresses, pour garder le référencement acquis.

Règles :

- **301 permanent, en un seul saut**, directement vers l'adresse finale :
  jamais de chaîne de redirections ;
- **410 Gone** pour la boutique (panier, commande, compte), qui n'a plus de
  successeur. Surtout pas un 301 vers l'accueil : Google le traite comme un
  soft 404 et la page traîne des mois dans l'index.

Implémentation : `app/Redirects.php`, appelé par `public/index.php` avant le
routeur. En PHP plutôt que dans `.htaccess` : elles marchent sur tout
hébergement, même sans `mod_rewrite`, et se testent avec le serveur de
développement.

---

## Nouvelles adresses

| Page | Français | Anglais |
| --- | --- | --- |
| Accueil | `/` | `/en` |
| Le Signal | `/coworking-montbeliard` | `/en/coworking-montbeliard` |
| Nos bureaux | `/location-bureaux-montbeliard` | `/en/office-rental-montbeliard` |
| Bureaux privés | `/location-bureaux-montbeliard/bureaux-prives` | `/en/office-rental-montbeliard/private-offices` |
| Bureaux ouverts | `/location-bureaux-montbeliard/bureaux-ouverts` | `/en/office-rental-montbeliard/open-plan-desks` |
| Bureaux disponibles | `/location-bureaux-montbeliard/bureaux-disponibles` | `/en/office-rental-montbeliard/available-offices` |
| Fiche bureau | `/location-bureaux-montbeliard/prive-05` | `/en/office-rental-montbeliard/prive-05` |
| Actualités | `/actualites` | `/en/news` |
| Contact | `/contact` | `/en/contact` |
| Mentions légales | `/mentions-legales` | `/en/legal-notice` |
| Confidentialité | `/politique-de-confidentialite` | `/en/privacy-policy` |

Les trois sélections (privés, ouverts, disponibles) reprennent les trois
catégories de l'ancienne boutique : chacune a son titre, son texte, sa
description et sa place dans le sitemap (back-office → Pages → Nos bureaux).

## Pages — 301

| Ancienne URL | Nouvelle |
| --- | --- |
| `/location-bureaux-montbeliard-coworking/` | `/coworking-montbeliard` |
| `/louer-bureau-coworking-montbeliard-location-bureaux/` | `/location-bureaux-montbeliard` |
| `/location-bureaux-montbeliard-belfort-aire-urbaine-doubs/` | `/contact` |
| `/coworking-montbeliard-belfort-location-bureau-prive-openspace/` | `/mentions-legales` |
| `/coworking-montbeliard-le-signal/` | `/` |
| `/episode/`, `/episode/le-signal-en-74-secondes/` (lecteur audio) | `/coworking-montbeliard` |
| `/le-signal`, `/nos-bureaux`, `/boutique`, `/shop`, `/produit`, `/categorie-produit` | page correspondante |

## Catégories produit — 301 vers leur sélection

| Ancienne URL | Nouvelle |
| --- | --- |
| `/categorie-produit/location-louer-bureaux-montbeliard/` | `/location-bureaux-montbeliard/bureaux-prives` |
| `/categorie-produit/location-louer-bureaux-coworking-montbeliard/` | `/location-bureaux-montbeliard/bureaux-ouverts` |
| `/categorie-produit/louer-bureaux-montbeliard-coworking/` | `/location-bureaux-montbeliard/bureaux-disponibles` |

## Boutique et WordPress — 410 Gone

`/panier/`, `/commander/`, `/mon-compte/` (et leurs sous-pages), `/feed/`,
`/comments/feed/`, `/wp-login.php`, `/wp-admin/`.

## Fiches de bureaux — 301

La correspondance est lue dans le catalogue (`sourceUrl` de chaque bureau) :
si un bureau change d'identifiant, sa redirection suit. Une ancienne fiche
sans correspondance mène au catalogue.

| Ancienne URL | Nouvelle | Bureau |
| --- | --- | --- |
| `/produit/location-bureau-coworking-montbeliard-1/` à `-6/` | `/location-bureaux-montbeliard/openspace-01` à `-06` | Bureau ouvert — n° 01 à 06 |
| `/produit/location-bureau-montbeliard-1/` à `-7/` | `/location-bureaux-montbeliard/prive-01` à `-07` | Bureau privé — n° 01 à 07 |

## Photos — 301

Les photos de `/wp-content/uploads/…`, indexées par Google Images, mènent à
leur version WebP d'après la source enregistrée dans la photothèque, y compris
les vignettes WordPress (`…-800x600.jpg`). Le logo, les favicons et le MP3
mènent à leurs nouveaux fichiers.

## Adresse canonique — 301

Une seule adresse par page : barre finale (`/contact/`), préfixe `/fr/`, slug
d'une autre langue (`/office-rental-montbeliard/prive-05`) ou filtre seul en
paramètre (`?type=private`) redirigent vers l'URL canonique. Sans préfixe,
la page est toujours en français.

---

## Vérification

Après mise en ligne, contrôler que chaque ancienne URL répond bien :

```bash
while read -r u; do
  printf '%-70s %s\n' "$u" "$(curl -s -o /dev/null -w '%{http_code} -> %{redirect_url}' "https://le-signal.com$u")"
done < anciennes-urls.txt
```

Attendu : `301` avec une destination qui répond `200`, ou `410` pour la
boutique. Aucun `404`, aucune chaîne de deux redirections. Vérifié en
développement sur les 40 adresses du sitemap de l'ancien site et de cette
table.
